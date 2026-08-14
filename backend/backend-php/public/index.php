<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use App\Core\Cors;
use App\Core\Request;
use App\Core\Router;

Cors::apply();

$request = new Request();
$router = new Router();

// routes/api.php populates $router using the variable injected here.
require __DIR__ . '/../routes/api.php';

$router->dispatch($request);
