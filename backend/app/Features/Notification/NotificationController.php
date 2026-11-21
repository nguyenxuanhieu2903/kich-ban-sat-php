<?php

declare(strict_types=1);

namespace App\Features\Notification;

use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;

class NotificationController
{
    private NotificationService $service;

    public function __construct(?NotificationService $service = null)
    {
        $this->service = $service ?? new NotificationService();
    }

    public function index(Request $request): Response
    {
        $userId = $this->requireUserId();
        $unreadOnly = in_array(strtolower((string) $request->query('unread_only', '0')), ['1', 'true', 'yes'], true);
        $limit = is_numeric($request->query('limit')) ? (int) $request->query('limit') : 50;

        return Response::json([
            'success' => true,
            'data' => $this->service->listForUser($userId, $unreadOnly, $limit),
            'serverTime' => date('c'),
        ]);
    }

    public function markRead(Request $request, string $id): Response
    {
        $userId = $this->requireUserId();
        $notifId = filter_var($id, FILTER_VALIDATE_INT);
        if ($notifId === false || $notifId <= 0) {
            throw new HttpException('Tham số id phải là số nguyên dương', 400);
        }

        return Response::json([
            'success' => true,
            'message' => 'Đã đánh dấu thông báo đã đọc',
            'data' => $this->service->markAsRead((int) $notifId, $userId),
            'serverTime' => date('c'),
        ]);
    }

    public function markAllRead(Request $request): Response
    {
        $userId = $this->requireUserId();
        $updated = $this->service->markAllAsRead($userId);

        return Response::json([
            'success' => true,
            'message' => 'Đã đánh dấu toàn bộ thông báo là đã đọc',
            'data' => ['updated_count' => $updated],
            'serverTime' => date('c'),
        ]);
    }

    private function requireUserId(): int
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            throw new HttpException('Bạn cần đăng nhập để thực hiện thao tác này', 401);
        }

        return $userId;
    }
}