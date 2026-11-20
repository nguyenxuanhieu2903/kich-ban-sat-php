<?php

declare(strict_types=1);

use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Features\Identity\Controllers\AuthController;
use App\Features\Identity\Controllers\AdminUserController;
use App\Features\GameCatalog\Controllers\GameController;
use App\Features\GameroomManagement\Controllers\GameRoomController;
use App\Features\VenueScheduling\Controllers\SchedulingController;

/** @var Router $router */

$registerApiRoutes = function (string $prefix) use ($router): void {
    // Health check endpoint
    $router->get($prefix . '/health', function (Request $request): Response {
        return Response::success([
            'status' => 'ok',
            'application' => 'Kich Ban Sat',
            'version' => '16',
        ], 'API Service Operational');
    });

    // =============================================================
    // DOMAIN 1: AUTH & IDENTITY API
    // =============================================================
    $router->get($prefix . '/auth/csrf-token', [AuthController::class, 'csrfToken']);
    $router->post($prefix . '/auth/register', [AuthController::class, 'register']);
    $router->post($prefix . '/auth/login', [AuthController::class, 'login']);
    $router->get($prefix . '/auth/me', [AuthController::class, 'me']);
    $router->post($prefix . '/auth/logout', [AuthController::class, 'logout']);

    // Admin User & Role Management
    $router->get($prefix . '/admin/users', [AdminUserController::class, 'index']);
    $router->get($prefix . '/admin/users/{id}', [AdminUserController::class, 'show']);
    $router->patch($prefix . '/admin/users/{id}', [AdminUserController::class, 'update']);
    $router->put($prefix . '/admin/users/{id}/role', [AdminUserController::class, 'assignRole']);

    // =============================================================
    // DOMAIN 2: GAME CATALOG API
    // =============================================================
    $router->get($prefix . '/games', [GameController::class, 'index']);
    $router->get($prefix . '/games/{id}', [GameController::class, 'show']);
    $router->get($prefix . '/games/{id}/prices', [GameController::class, 'prices']);

    // =============================================================
    // DOMAIN 3: GAMEROOM MANAGEMENT API
    // =============================================================
    $router->get($prefix . '/rooms', [GameRoomController::class, 'index']);
    $router->post($prefix . '/rooms', [GameRoomController::class, 'create']);
    $router->get($prefix . '/rooms/{id}', [GameRoomController::class, 'show']);
    $router->post($prefix . '/rooms/{id}/join', [GameRoomController::class, 'join']);
    $router->post($prefix . '/rooms/{id}/leave', [GameRoomController::class, 'leave']);

    // =============================================================
    // DOMAIN 4 & 5: SCHEDULING, VOTING & VENUES API
    // =============================================================
    $router->get($prefix . '/venues', [SchedulingController::class, 'venues']);
    $router->post($prefix . '/rooms/{id}/proposals', [SchedulingController::class, 'propose']);
    $router->get($prefix . '/proposals/{id}', [SchedulingController::class, 'showProposal']);
    $router->post($prefix . '/proposals/{id}/vote', [SchedulingController::class, 'vote']);
};

// Register for both standard V16 prefix (/api/v1) and legacy fallback (/api)
$registerApiRoutes('/api/v1');
$registerApiRoutes('/api');
