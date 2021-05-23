<?php

namespace App;

class Router
{
    private $routes;

    public function __construct()
    {
        $this->routes = require __DIR__ . '/routes.php';
    }

    public function load(string $uri, ?string $type = 'GET', ?array $params = null)
    {
        if (!isset($this->routes[$uri])) {
            header('HTTP/1.0 404 Not Found');
            exit();
        }

        $match = false;

        foreach ($this->routes[$uri] as $route) {
            if ($route['type'] == $type) {
                [ $controller, $method ] = explode('@', $route['handler']);
                $controller = sprintf('App\Controller\%s', $controller);
                
                $request = new $controller();
                $request->{$method}($params);
                $match = true;
                break;
            }
        }
        
        if (!$match) {
            header('HTTP/1.0 405 Method Not Allowed');
        }
    }
}
