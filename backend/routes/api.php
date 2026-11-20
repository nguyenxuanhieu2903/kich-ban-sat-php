<?php

declare(strict_types=1);

use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Features\Identity\Controllers\AuthController;

/** @var Router $router */

// Health check endpoint
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

// =============================================================
// DOMAIN 1: AUTH & IDENTITY API
// =============================================================
$router->post('/api/auth/register', [AuthController::class, 'register']);
$router->post('/api/auth/login', [AuthController::class, 'login']);
$router->get('/api/auth/me', [AuthController::class, 'me']);
$router->post('/api/auth/logout', [AuthController::class, 'logout']);
