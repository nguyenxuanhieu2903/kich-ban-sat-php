<?php

declare(strict_types=1);

use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Features\Identity\Controllers\AuthController;
use App\Features\Identity\Controllers\AdminUserController;
use App\Features\DMManagement\Controllers\DMAssignmentController;
use App\Features\GameCatalog\Controllers\GameController;
use App\Features\GameroomManagement\Controllers\GameRoomController;
use App\Features\GameroomManagement\Controllers\AdminGameRoomController;
use App\Features\VenueScheduling\Controllers\SchedulingController;
use App\Features\PaymentRefund\Controllers\PaymentController;
use App\Features\PaymentRefund\Controllers\AdminRefundController;
use App\Features\Notification\Controllers\NotificationController;
use App\Features\Pricing\Controllers\PricingController;
use App\Features\Promotion\Controllers\PromotionController;

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
    // DOMAIN 2: GAME CATALOG & PROMOTIONS API
    // =============================================================
    $router->get($prefix . '/games', [GameController::class, 'index']);
    $router->get($prefix . '/games/{id}', [GameController::class, 'show']);
    $router->get($prefix . '/games/{id}/prices', [GameController::class, 'prices']);
    $router->get($prefix . '/promotions', [PromotionController::class, 'index']);

    // =============================================================
    // DOMAIN 3: GAMEROOM MANAGEMENT & PRICING SNAPSHOT API
    // =============================================================
    $router->get($prefix . '/rooms', [GameRoomController::class, 'index']);
    $router->post($prefix . '/rooms', [GameRoomController::class, 'create']);
    $router->get($prefix . '/rooms/{id}', [GameRoomController::class, 'show']);
    $router->post($prefix . '/rooms/{id}/join', [GameRoomController::class, 'join']);
    $router->post($prefix . '/rooms/{id}/leave', [GameRoomController::class, 'leave']);
    $router->post($prefix . '/rooms/{id}/cancel', [GameRoomController::class, 'cancel']);

    // Pricing calculation & snapshot
    $router->post($prefix . '/rooms/{id}/calculate-price', [PricingController::class, 'calculate']);
    $router->get($prefix . '/rooms/{id}/price-snapshot', [PricingController::class, 'snapshot']);

    // Admin GameRoom Cancel with 24-hour Cutoff
    $router->post($prefix . '/admin/rooms/{id}/cancel', [AdminGameRoomController::class, 'cancel']);

    // =============================================================
    // DOMAIN 4 & 5: SCHEDULING, VOTING & VENUES API
    // =============================================================
    $router->get($prefix . '/venues', [SchedulingController::class, 'venues']);
    $router->post($prefix . '/rooms/{id}/proposals', [SchedulingController::class, 'propose']);
    $router->get($prefix . '/proposals/{id}', [SchedulingController::class, 'showProposal']);
    $router->post($prefix . '/proposals/{id}/vote', [SchedulingController::class, 'vote']);

    // =============================================================
    // DOMAIN 6: DM ASSIGNMENT API
    // =============================================================
    $router->get($prefix . '/dm/assignments', [DMAssignmentController::class, 'index']);
    $router->post($prefix . '/dm/assignments/{id}/accept', [DMAssignmentController::class, 'accept']);
    $router->post($prefix . '/dm/assignments/{id}/reject', [DMAssignmentController::class, 'reject']);
    $router->post($prefix . '/admin/rooms/{id}/assign-dm', [DMAssignmentController::class, 'assign']);
    $router->post($prefix . '/admin/sessions/{id}/reassign-dm', [DMAssignmentController::class, 'reassignSession']);

    // =============================================================
    // DOMAIN 7: PAYMENT & REFUND API
    // =============================================================
    $router->post($prefix . '/payments/{paymentId}/deposit', [PaymentController::class, 'deposit']);
    $router->post($prefix . '/payments/{paymentId}/remaining', [PaymentController::class, 'remaining']);

    // Admin Refunds Management
    $router->get($prefix . '/admin/refunds', [AdminRefundController::class, 'index']);
    $router->post($prefix . '/admin/refunds/{id}/approve', [AdminRefundController::class, 'approve']);
    $router->post($prefix . '/admin/refunds/{id}/reject', [AdminRefundController::class, 'reject']);

    // =============================================================
    // DOMAIN 8: NOTIFICATION API
    // =============================================================
    $router->get($prefix . '/notifications', [NotificationController::class, 'index']);
    $router->post($prefix . '/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
};

// Register for both standard V16 prefix (/api/v1) and legacy fallback (/api)
$registerApiRoutes('/api/v1');
$registerApiRoutes('/api');
