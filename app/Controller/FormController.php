<?php

namespace App\Controller;

use App\Service\CompanyMatcher;

class FormController extends Controller
{
    public function index()
    {
        $this->render('form.twig');
    }

    public function submit(array $request)
    {
        $matcher = new CompanyMatcher($this->db());

        $matchCriteria = ['postcode'];
        // use html attribute name
        // remember company settings table only has 3 settings.
        
        $matchedCompanies = $matcher
            // ->criteria($matchCriteria) //optional, default is all 3 settings.
            ->match($request)
            ->pick($_ENV['MAX_MATCHED_COMPANIES']) //optional
            ->results();

        $this->render('results.twig', [
            'matchedCompanies'  => $matchedCompanies,
        ]);
    }
}