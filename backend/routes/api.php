<?php

declare(strict_types=1);

use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Features\Identity\Controllers\AuthController;
use App\Features\GameCatalog\Controllers\GameController;
use App\Features\GameroomManagement\Controllers\GameRoomController;
use App\Features\VenueScheduling\Controllers\SchedulingController;

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

// =============================================================
// DOMAIN 3: GAMEROOM MANAGEMENT API
// =============================================================
$router->get('/api/rooms', [GameRoomController::class, 'index']);
$router->post('/api/rooms', [GameRoomController::class, 'create']);
$router->get('/api/rooms/{id}', [GameRoomController::class, 'show']);
$router->post('/api/rooms/{id}/join', [GameRoomController::class, 'join']);
$router->post('/api/rooms/{id}/leave', [GameRoomController::class, 'leave']);

// =============================================================
// DOMAIN 4 & 5: SCHEDULING, VOTING & VENUES API
// =============================================================
$router->get('/api/venues', [SchedulingController::class, 'venues']);
$router->post('/api/rooms/{id}/proposals', [SchedulingController::class, 'propose']);
$router->get('/api/proposals/{id}', [SchedulingController::class, 'showProposal']);
$router->post('/api/proposals/{id}/vote', [SchedulingController::class, 'vote']);
