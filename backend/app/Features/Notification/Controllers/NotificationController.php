<?php

declare(strict_types=1);

namespace App\Features\Notification\Controllers;

use App\Core\Middleware\AuthMiddleware;
use App\Core\Request;
use App\Core\Response;
use App\Features\Notification\Repositories\NotificationRepository;
use App\Infrastructure\Database\DatabaseConnection;
use PDO;

class NotificationController
{
    protected NotificationRepository $notiRepo;
    protected PDO $pdo;

    public function __construct(?NotificationRepository $notiRepo = null, ?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DatabaseConnection::getInstance();
        $this->notiRepo = $notiRepo ?? new NotificationRepository($this->pdo);

        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }
    }

    /**
     * GET /api/v1/notifications
     * Query: limit, unread_only (0 or 1)
     */
    public function index(Request $request): Response
    {
        $auth = AuthMiddleware::authenticate($request);
        if ($auth instanceof Response) {
            return $auth;
        }

        $userId = (int) $auth['id'];
        $limit = max(1, min(100, (int) $request->query('limit', 20)));
        $unreadParam = $request->query('unread_only');
        $unreadOnly = in_array($unreadParam, ['1', 1, 'true', true], true);

        $notifications = $this->notiRepo->listByUserId($userId, $limit, $unreadOnly);

        return Response::success([
            'notifications' => $notifications,
            'count' => count($notifications),
        ], 'Danh sách thông báo');
    }

    /**
     * POST /api/v1/notifications/{id}/read
     */
    public function markAsRead(Request $request, string $id): Response
    {
        $auth = AuthMiddleware::authenticate($request);
        if ($auth instanceof Response) {
            return $auth;
        }

        $userId = (int) $auth['id'];
        $notiId = (int) $id;

        $updated = $this->notiRepo->markAsRead($notiId, $userId);

        if (!$updated) {
            return Response::fail('Không tìm thấy thông báo hoặc bạn không có quyền truy cập', 'NOT_FOUND', 404);
        }

        return Response::success(null, 'Đã đánh dấu thông báo là đã đọc');
    }
}
