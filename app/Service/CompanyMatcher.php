<?php

namespace App\Service;

use PDO;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

class CompanyMatcher
{
    private PDO $db;
    private array $matches = [];
    private ?int $maxMatchedCompanies = null;
    private ?string $query = null;
    private array $criteria = [
        'postcode' => '',
        'bedrooms' => '',
        'type' => ''
    ];

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function criteria(array $criteria): self
    {
        $criteria = array_flip($criteria);

        foreach ($criteria as $key => &$value) {
            $value = '';
        }

        $this->criteria = $criteria;

        return $this;
    }

    public function match(array $request): self
    {
        $this->setCriteriaValues($request);

        $this->prepareQuery();

        return $this;
    }

    public function pick(int $count): self
    {
        $this->maxMatchedCompanies = $count;

        return $this;
    }

    public function results(): array
    {
        if (empty($this->matches)) {
            $this->executeQuery();
        }

        return $this->matches;
    }

    private function setCriteriaValues(array $request): void
    {
        foreach ($this->criteria as $criteria => &$criteriaValue) {
            $criteriaValue = $request[$criteria];
            filter_var($criteriaValue, FILTER_SANITIZE_STRING);
            
            if ($criteria === 'postcode') {
                $criteriaValue = $this->getPostcodePrefix($criteriaValue);
            } 
        }
    }

    private function getPostcodePrefix(string $postcode): string
    {
        return strtoupper(preg_replace('#^([a-z]+).*#i','$1', $postcode));
    }

    private function prepareQuery(): void
    {
        $query = 'SELECT company_id, credits, name, description, email, phone, website
                    FROM companies c
                    JOIN company_matching_settings cms
                    ON c.id = cms.company_id
                    WHERE active = 1 AND credits > 0 ';

        foreach ($this->criteria as $criteria => $criteriaValue) {
            // db uses plural for column name, find cleaner solution for all situations like this.
            if ($criteria === 'postcode') {
                $query .= "AND postcodes LIKE :$criteria ";
            } elseif ($criteriaValue === '5+') {
                $query .= "AND $criteria REGEXP '[5-9]|\d\d\d*' "; //potential for more than 5 bedrooms.
            } else {
                $query .= "AND $criteria LIKE :$criteria ";
            }
        }

        
        $query .= 'GROUP BY company_id ORDER BY RAND() ';

        $this->query = $query;
    }

    private function executeQuery(): void
    {
        $query = $this->query;

        if ($this->maxMatchedCompanies) {
            $query .= 'LIMIT :maxMatchedCompanies ';
        }

        $statement = $this->db->prepare($query);

        foreach ($this->criteria as $criteria => $criteriaValue) {
            // type column is not an array
            if ($criteria === 'type') {
                $statement->bindValue(":$criteria", "%$criteriaValue%");
            } elseif ($criteriaValue === '5+') {
                
            } else {
                $statement->bindValue(":$criteria", "%\"$criteriaValue\"%");
            }
        }

        if ($this->maxMatchedCompanies) {
            $statement->bindValue(
                ':maxMatchedCompanies',
                $this->maxMatchedCompanies,
                PDO::PARAM_INT
            );
        }

        $statement->execute();
        $resolved = $statement->fetchALl(PDO::FETCH_ASSOC);
        $statement->closeCursor();

        $this->matches = $resolved;

        $this->deductCredits();
    }

    private function deductCredits(): void
    {
        if (!empty($this->matches)) {
            $query = 'UPDATE companies
                        SET credits  = credits - 1
                        WHERE id IN (';

            $listOfIds = array_column($this->matches, 'company_id');

            foreach ($listOfIds as $index => $id) {
                if ($index === count($listOfIds) - 1) {
                    $query .= ":$index)";
                } else {
                    $query .= ":$index,";
                } 
            }
            
            $statement = $this->db->prepare($query);
            
            foreach ($listOfIds as $index => $id) {
                $statement->bindValue(":$index", intval($id));
            }

            $statement->execute();
            $statement->closeCursor();

            foreach ($this->matches as &$match) {
                if ($match['credits'] === '1') {
                    // strval to mantain same data type as database.
                    $match['credits'] = strval($match['credits']--);
                    $this->logOutOfCredit($match);
                } else {
                    $match['credits'] = strval($match['credits']--);
                }
            }
        }
    }

    private function logOutOfCredit(array $outOfCreditCompany): void
    {
        $log = new Logger('out_of_credits');

        $dateFormat = '[Y/m/d, g:i a]';
        $output = "%datetime% - %level_name% - %message%\n";

        $formatter = new LineFormatter($output, $dateFormat);

        $handler = new StreamHandler(__DIR__ . '/../../' . 'logs/credits.log');
        $handler->setFormatter($formatter);

        $log->pushHandler($handler);

        $log->info($outOfCreditCompany['name'] . ' has run out of credits.');
    }
}
