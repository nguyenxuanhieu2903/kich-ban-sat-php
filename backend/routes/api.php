<?php

declare(strict_types=1);

use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Features\Admin\AdminController;
use App\Features\Audit\AuditLogController;
use App\Features\Auth\AuthController;
use App\Features\Game\GameController;
use App\Features\GameRoom\GameRoomController;
use App\Features\GameRoom\RoomWorkflowController;
use App\Features\Notification\NotificationController;
use App\Features\Payment\PaymentController;

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

// Module 2B: Room Workflow (Expire, Reopen, DM Proposal, Schedule Proposal)
$router->post('/api/rooms/{id}/expire', [RoomWorkflowController::class, 'expire']);
$router->post('/api/rooms/{id}/reopen', [RoomWorkflowController::class, 'reopen']);
$router->post('/api/rooms/{id}/dm-proposals', [RoomWorkflowController::class, 'proposeDm']);
$router->post('/api/dm-assignments/{id}/respond', [RoomWorkflowController::class, 'respondDm']);
$router->post('/api/rooms/{id}/schedule-proposals', [RoomWorkflowController::class, 'proposeSchedule']);
$router->post('/api/schedule-proposals/{id}/respond', [RoomWorkflowController::class, 'respondSchedule']);

// Module 3: Payments & Refunds
$router->get('/api/rooms/{id}/payments', [PaymentController::class, 'listByRoom']);
$router->post('/api/rooms/{id}/payments/deposit', [PaymentController::class, 'createDeposit']);
$router->post('/api/rooms/{id}/payments/remaining', [PaymentController::class, 'payRemaining']);
$router->post('/api/payments/{id}/confirm', [PaymentController::class, 'confirm']);
$router->post('/api/payments/{id}/fail', [PaymentController::class, 'fail']);
$router->post('/api/payments/{id}/retry', [PaymentController::class, 'retry']);
$router->post('/api/payments/{id}/refunds', [PaymentController::class, 'requestRefund']);
$router->post('/api/refunds/{id}/process', [PaymentController::class, 'processRefund']);

// Module 4: Notifications, Audit Logs & Admin Operations
$router->get('/api/notifications', [NotificationController::class, 'index']);
$router->post('/api/notifications/{id}/read', [NotificationController::class, 'markRead']);
$router->post('/api/notifications/read-all', [NotificationController::class, 'markAllRead']);

$router->get('/api/physical-rooms', [AdminController::class, 'listPhysicalRooms']);
$router->post('/api/physical-rooms', [AdminController::class, 'createPhysicalRoom']);
$router->post('/api/physical-rooms/{id}/update', [AdminController::class, 'updatePhysicalRoom']);

$router->get('/api/admin/users', [AdminController::class, 'listUsers']);
$router->post('/api/admin/users/{id}/status', [AdminController::class, 'updateUserStatus']);
$router->post('/api/admin/users/{id}/role', [AdminController::class, 'updateUserRole']);
$router->get('/api/admin/audit-logs', [AuditLogController::class, 'index']);