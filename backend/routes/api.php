<?php

declare(strict_types=1);

use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Features\Identity\Controllers\AuthController;
use App\Features\GameCatalog\Controllers\GameController;

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

// =============================================================
// DOMAIN 2: GAME CATALOG API
// =============================================================
$router->get('/api/games', [GameController::class, 'index']);
$router->get('/api/games/{id}', [GameController::class, 'show']);
$router->get('/api/games/{id}/prices', [GameController::class, 'prices']);
