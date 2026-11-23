<?php

declare(strict_types=1);

use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Features\Identity\Controllers\AuthController;
use App\Features\Identity\Controllers\AdminUserController;
use App\Features\Identity\Controllers\AdminAuditLogController;
use App\Features\GameroomManagement\Controllers\AdminOperationsController;
use App\Features\PaymentRefund\Controllers\PaymentRefundRequestController;
use App\Features\VenueScheduling\Controllers\SessionLifecycleController;
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
        $dbStatus = 'ok';
        $dbLatencyMs = 0.0;
        try {
            $pdo = \App\Infrastructure\Database\DatabaseConnection::getInstance();
            $t0 = microtime(true);
            $pdo->query('SELECT 1');
            $dbLatencyMs = round((microtime(true) - $t0) * 1000, 2);
        } catch (\Throwable) {
            $dbStatus = 'unreachable';
        }

        return Response::success([
            'database' => ['status' => $dbStatus, 'latency_ms' => $dbLatencyMs],
            'runtime' => ['php_version' => PHP_VERSION, 'memory_usage_mb' => round(memory_get_usage(true) / 1048576, 2)],
            'status' => 'ok',
            'application' => 'Kich Ban Sat',
            'version' => '16',
        ], 'API Service Operational');
    });

    // OpenAPI Specification & Interactive Documentation
    $router->get($prefix . '/openapi.json', function (Request $request): Response {
        $specPath = dirname(__DIR__) . '/public/openapi.json';
        if (!file_exists($specPath)) {
            return Response::fail('OpenAPI specification file not found', 'NOT_FOUND', 404);
        }
        $spec = (string) file_get_contents($specPath);
        return Response::raw($spec, 200, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    });

    $router->get($prefix . '/docs', function (Request $request): Response {
        $html = <<<HTML
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Kịch Bản Sát V16 - Interactive API Documentation</title>
  <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css" />
  <style>
    body { margin: 0; background: #fafafa; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
    .topbar { display: none; }
    .swagger-ui .info { margin: 25px 0; }
    .swagger-ui .info .title { color: #0f172a; font-weight: 800; letter-spacing: -0.02em; }
    .swagger-ui .scheme-container { background: #ffffff; box-shadow: 0 1px 3px rgba(0,0,0,0.06); }
    .swagger-ui .btn.execute { background-color: #0284c7; border-color: #0284c7; color: #fff; }
  </style>
</head>
<body>
  <div id="swagger-ui"></div>
  <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js" crossorigin></script>
  <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-standalone-preset.js" crossorigin></script>
  <script>
    window.onload = () => {
      window.ui = SwaggerUIBundle({
        url: '/openapi.json',
        dom_id: '#swagger-ui',
        deepLinking: true,
        presets: [
          SwaggerUIBundle.presets.apis,
          SwaggerUIStandalonePreset
        ],
        layout: "BaseLayout"
      });
    };
  </script>
</body>
</html>
HTML;
        return Response::html($html);
    });

    // =============================================================
    // DOMAIN 1: AUTH & IDENTITY API
    // =============================================================
    $router->get($prefix . '/auth/csrf-token', [AuthController::class, 'csrfToken']);
    $router->post($prefix . '/auth/register', [AuthController::class, 'register']);
    $router->post($prefix . '/auth/login', [AuthController::class, 'login']);
    $router->get($prefix . '/auth/me', [AuthController::class, 'me']);
    $router->post($prefix . '/auth/logout', [AuthController::class, 'logout']);
    $router->patch($prefix . '/auth/profile', [AuthController::class, 'updateProfile']);
    $router->post($prefix . '/auth/student-verification', [AuthController::class, 'requestStudentVerification']);

    // Admin User & Role Management
    $router->get($prefix . '/admin/users', [AdminUserController::class, 'index']);
    $router->get($prefix . '/admin/users/{id}', [AdminUserController::class, 'show']);
    $router->patch($prefix . '/admin/users/{id}', [AdminUserController::class, 'update']);
    $router->put($prefix . '/admin/users/{id}/role', [AdminUserController::class, 'assignRole']);
    $router->get($prefix . '/admin/audit-logs', [AdminAuditLogController::class, 'index']);
    $router->get($prefix . '/admin/reports/revenue', [\App\Features\Identity\Controllers\AdminReportController::class, 'revenue']);
    $router->get($prefix . '/admin/payments', [\App\Features\Identity\Controllers\AdminReportController::class, 'payments']);
    $router->post($prefix . '/admin/rooms/{id}/assign-dm', [AdminOperationsController::class, 'assignDm']);
    $router->post($prefix . '/dm/assignments/{id}/revoke', [AdminOperationsController::class, 'revokeDmAssignment']);
    $router->post($prefix . '/admin/rooms/{id}/revoke-dm', [AdminOperationsController::class, 'revokeRoomDm']);
    $router->post($prefix . '/admin/rooms/{id}/assign-venue', [AdminOperationsController::class, 'assignVenue']);
    $router->post($prefix . '/admin/rooms/{id}/assign-physical-room', [AdminOperationsController::class, 'assignVenue']);
    $router->post($prefix . '/admin/rooms/{id}/disband', [AdminGameRoomController::class, 'cancel']);
    $router->put($prefix . '/admin/users/{id}/student-verification', [AdminOperationsController::class, 'updateStudentVerification']);
    $router->post($prefix . '/admin/users/{id}/verify-student', [AdminOperationsController::class, 'updateStudentVerification']);
    $router->get($prefix . '/sessions/{id}', [SessionLifecycleController::class, 'show']);
    $router->post($prefix . '/sessions/{id}/start', [SessionLifecycleController::class, 'start']);
    $router->post($prefix . '/sessions/{id}/complete', [SessionLifecycleController::class, 'complete']);
    $router->post($prefix . '/sessions/{id}/notes', [SessionLifecycleController::class, 'saveNotes']);
    $router->post($prefix . '/dm/sessions/{id}/start', [SessionLifecycleController::class, 'start']);
    $router->post($prefix . '/dm/sessions/{id}/complete', [SessionLifecycleController::class, 'complete']);
    $router->post($prefix . '/dm/sessions/{id}/notes', [SessionLifecycleController::class, 'saveNotes']);
    $router->post($prefix . '/payments/{id}/refund-request', [PaymentRefundRequestController::class, 'requestRefund']);
    $router->post($prefix . '/payments/refund-request', [PaymentRefundRequestController::class, 'requestRefundByRoom']);

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

    // Pricing calculation & snapshot
    $router->post($prefix . '/rooms/{id}/calculate-price', [PricingController::class, 'calculate']);
    $router->get($prefix . '/rooms/{id}/price-snapshot', [PricingController::class, 'snapshot']);

    // Admin GameRoom Cancel with 24-hour Cutoff
    $router->post($prefix . '/admin/rooms/{id}/cancel', [AdminGameRoomController::class, 'cancel']);

    // =============================================================
    // DOMAIN 4 & 5: SCHEDULING, PROPOSALS & VENUES API
    // =============================================================
    $router->get($prefix . '/venues', [SchedulingController::class, 'venues']);
    $router->post($prefix . '/rooms/{id}/proposals', [SchedulingController::class, 'propose']);
    $router->get($prefix . '/proposals', [SchedulingController::class, 'listProposals']);
    $router->get($prefix . '/admin/proposals', [SchedulingController::class, 'listProposals']);
    $router->get($prefix . '/proposals/{id}', [SchedulingController::class, 'showProposal']);
    $router->post($prefix . '/proposals/{id}/vote', [SchedulingController::class, 'vote']);
    $router->post($prefix . '/scheduling/proposals/{id}/vote', [SchedulingController::class, 'vote']);
    $router->post($prefix . '/proposals/{id}/approve', [SchedulingController::class, 'approveProposal']);
    $router->post($prefix . '/admin/proposals/{id}/approve', [SchedulingController::class, 'approveProposal']);
    $router->post($prefix . '/proposals/{id}/reject', [SchedulingController::class, 'rejectProposal']);
    $router->post($prefix . '/admin/proposals/{id}/reject', [SchedulingController::class, 'rejectProposal']);

    // =============================================================
    // DOMAIN 6: DM ASSIGNMENT API
    // =============================================================
    $router->get($prefix . '/dm/assignments', [DMAssignmentController::class, 'index']);
    $router->post($prefix . '/dm/assignments/{id}/accept', [DMAssignmentController::class, 'accept']);
    $router->post($prefix . '/dm/assignments/{id}/reject', [DMAssignmentController::class, 'reject']);

    // =============================================================
    // DOMAIN 7: PAYMENT & REFUND API
    // =============================================================
    $router->get($prefix . '/rooms/{id}/payments', [PaymentController::class, 'listByRoom']);
    $router->post($prefix . '/payments/{paymentId}/deposit', [PaymentController::class, 'deposit']);
    $router->post($prefix . '/payments/deposit', [PaymentController::class, 'quickDeposit']);
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
    $router->post($prefix . '/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
};

// Register for both standard V16 prefix (/api/v1) and legacy fallback (/api)
$registerApiRoutes('/api/v1');
$registerApiRoutes('/api');
