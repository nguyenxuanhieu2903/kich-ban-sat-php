<?php

declare(strict_types=1);

use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Features\Auth\AuthController;
use App\Features\Game\GameController;
use App\Features\GameRoom\GameRoomController;

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

// Module 0: Auth
$router->post('/api/auth/register', [AuthController::class, 'register']);
$router->post('/api/auth/login', [AuthController::class, 'login']);
$router->post('/api/auth/logout', [AuthController::class, 'logout']);
$router->get('/api/auth/me', [AuthController::class, 'me']);

// Module 1: Games
$router->get('/api/games', [GameController::class, 'index']);
$router->get('/api/games/{id}', [GameController::class, 'show']);
$router->post('/api/games', [GameController::class, 'store']);

// Module 2: GameRooms & Participants
$router->get('/api/rooms', [GameRoomController::class, 'index']);
$router->get('/api/rooms/{id}', [GameRoomController::class, 'show']);
$router->post('/api/rooms', [GameRoomController::class, 'store']);
$router->post('/api/rooms/{id}/join', [GameRoomController::class, 'join']);
$router->post('/api/rooms/{id}/leave', [GameRoomController::class, 'leave']);
$router->post('/api/rooms/{id}/assign-dm', [GameRoomController::class, 'assignDm']);
$router->post('/api/rooms/{id}/deposit', [GameRoomController::class, 'deposit']);
$router->post('/api/rooms/{id}/cancel', [GameRoomController::class, 'cancel']);
$router->post('/api/rooms/{id}/complete', [GameRoomController::class, 'complete']);