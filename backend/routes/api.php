<?php

declare(strict_types=1);

use App\Core\Request;
use App\Core\Response;
use App\Core\Router;

/** @var Router $router */

$router->get('/api/health', function (Request $request): Response {
    return Response::json([
        'success' => true,
        'data' => [
            'status' => 'ok',
            'application' => 'Kich Ban Sat',
            'version' => '16',
        ],
    ]);
});
