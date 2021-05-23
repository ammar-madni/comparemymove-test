<?php

require __DIR__ . '/../bootstrap/init.php';

date_default_timezone_set('Europe/London');

$router = new App\Router();
$router->load($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD'], $_REQUEST);