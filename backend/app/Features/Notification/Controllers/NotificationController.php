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
        $roleCode = strtoupper((string) ($auth['role_code'] ?? $auth['role'] ?? 'PLAYER'));
        $limit = max(1, min(100, (int) $request->query('limit', 25)));
        $unreadParam = $request->query('unread_only');
        $unreadOnly = in_array($unreadParam, ['1', 1, 'true', true], true);

        $rows = $this->notiRepo->listByUserId($userId, $limit, $unreadOnly, $roleCode);
        $notifications = array_map(fn (array $n): array => $this->enrichNotification($n, $roleCode), $rows);

        return Response::success([
            'notifications' => $notifications,
            'count' => count($notifications),
        ], 'Danh sách thông báo');
    }

    /**
     * POST /api/v1/admin/notifications/broadcast
     * Body: audience (ALL|PLAYER|DM), title, content, target_section (optional: INFO|PROMOTION|GAME|LOBBY|DM_PORTAL)
     */
    public function broadcast(Request $request): Response
    {
        $auth = AuthMiddleware::authenticate($request, ['ADMIN']);
        if ($auth instanceof Response) {
            return $auth;
        }

        $body = $request->getBodyParams();
        $audience = strtoupper(trim((string) ($body['audience'] ?? 'ALL')));
        if (!in_array($audience, ['ALL', 'PLAYER', 'DM'], true)) {
            return Response::fail('Nhóm nhận thông báo không hợp lệ (chỉ chấp nhận ALL, PLAYER, DM)', 'VALIDATION_ERROR', 422);
        }

        $title = trim((string) ($body['title'] ?? ''));
        $content = trim((string) ($body['content'] ?? $body['message'] ?? ''));
        if ($title === '' || $content === '') {
            return Response::fail('Tiêu đề và nội dung thông báo là bắt buộc', 'VALIDATION_ERROR', 422);
        }

        $targetSection = strtoupper(trim((string) ($body['target_section'] ?? '')));
        $refType = in_array($targetSection, ['PROMOTION', 'GAME', 'LOBBY', 'DM_PORTAL', 'INFO', 'ROOM'], true)
            ? $targetSection
            : ($audience === 'DM' ? 'DM_PORTAL' : ($audience === 'PLAYER' ? 'PROMOTION' : 'INFO'));
        $refId = isset($body['reference_id']) && (int) $body['reference_id'] > 0 ? (int) $body['reference_id'] : null;

        $result = $this->notiRepo->broadcastToAudience(
            $audience,
            $title,
            $content,
            (int) $auth['id'],
            $refType,
            $refId
        );

        $audVi = match ($audience) {
            'PLAYER' => 'Người chơi',
            'DM' => 'Quản trò',
            default => 'Tất cả người dùng',
        };

        return Response::success([
            'audience' => $audience,
            'type' => $result['type'],
            'recipient_count' => $result['recipient_count'],
        ], "Đã gửi thông báo tới {$result['recipient_count']} tài khoản ({$audVi})", null, 201);
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

    /**
     * POST /api/v1/notifications/read-all
     */
    public function markAllAsRead(Request $request): Response
    {
        $auth = AuthMiddleware::authenticate($request);
        if ($auth instanceof Response) {
            return $auth;
        }

        $count = $this->notiRepo->markAllAsRead((int) $auth['id']);
        return Response::success(['updated_count' => $count], 'Đã đánh dấu tất cả thông báo là đã đọc');
    }

    /**
     * Gắn phạm vi hiển thị (scope_label) và đường dẫn đích (target_url) cho từng thông báo.
     *
     * @param array<string, mixed> $n
     * @return array<string, mixed>
     */
    protected function enrichNotification(array $n, string $roleCode): array
    {
        $type = strtoupper((string) ($n['type'] ?? 'SYSTEM'));
        $refType = strtoupper((string) ($n['reference_type'] ?? ''));
        $refId = !empty($n['reference_id']) ? (int) $n['reference_id'] : null;
        $title = (string) ($n['title'] ?? '');
        $content = (string) ($n['content'] ?? '');
        $haystack = mb_strtolower($title . ' ' . $content);

        // 1. Xác định phạm vi thông báo
        if ($type === 'BROADCAST_ALL') {
            $scope = 'BROADCAST_ALL';
            $scopeLabel = 'Thông báo chung';
        } elseif ($type === 'BROADCAST_PLAYER' || $type === 'PROMOTION') {
            $scope = 'BROADCAST_PLAYER';
            $scopeLabel = 'Quyền lợi người chơi';
        } elseif ($type === 'BROADCAST_DM') {
            $scope = 'BROADCAST_DM';
            $scopeLabel = 'Thông báo Quản trò';
        } else {
            $scope = 'PERSONAL';
            $scopeLabel = 'Riêng bạn';
        }

        // 2. Xác định room_id nếu được nhắc dạng "phòng #123" hoặc reference_type = ROOM
        $extractedRoomId = null;
        if ($refType === 'ROOM' && $refId !== null) {
            $extractedRoomId = $refId;
        } elseif (preg_match('/phòng\s*#(\d+)/iu', $content . ' ' . $title, $m)) {
            $extractedRoomId = (int) $m[1];
        }

        // 3. Giải đường dẫn đích trực tiếp (target_url) theo reference_type + role + ngữ cảnh
        $targetUrl = '/pages/lobby.html';
        $actionLabel = 'Xem chi tiết';

        if ($refType === 'GAME' && $refId !== null) {
            $targetUrl = $roleCode === 'ADMIN' ? '/pages/admin-portal.html?tab=games' : '/pages/scenario-detail.html?id=DB-' . $refId;
            $actionLabel = $roleCode === 'ADMIN' ? 'Mở mục Kịch bản' : 'Xem kịch bản';
        } elseif ($refType === 'PROMOTION' || $type === 'PROMOTION' || str_contains($haystack, 'khuyến mãi') || str_contains($haystack, 'ưu đãi')) {
            $targetUrl = $roleCode === 'ADMIN' ? '/pages/admin-portal.html?tab=promos' : '/pages/info.html';
            $actionLabel = $roleCode === 'ADMIN' ? 'Mở mục Khuyến mãi' : 'Xem ưu đãi & giá vé';
        } elseif ($refType === 'INFO') {
            $targetUrl = '/pages/info.html';
            $actionLabel = 'Xem quy định & thông tin';
        } elseif ($refType === 'DM_PORTAL' || $type === 'BROADCAST_DM') {
            $targetUrl = '/pages/dm-portal.html';
            $actionLabel = 'Mở Cổng Quản Trò';
        } elseif ($refType === 'REFUND' || $type === 'REFUND' || str_contains($haystack, 'hoàn tiền') || str_contains($haystack, 'hoàn cọc')) {
            if ($roleCode === 'ADMIN') {
                $targetUrl = '/pages/admin-portal.html?tab=refunds';
                $actionLabel = 'Mở mục Duyệt hoàn tiền';
            } else {
                $targetUrl = '/pages/profile.html#pf-history-section';
                $actionLabel = 'Xem lịch sử hoàn cọc';
            }
        } elseif ($refType === 'HSSV' || $type === 'HSSV' || str_contains($haystack, 'hssv') || str_contains($haystack, 'sinh viên')) {
            if ($roleCode === 'ADMIN') {
                $targetUrl = '/pages/admin-portal.html?tab=hssv';
                $actionLabel = 'Mở mục Duyệt thẻ HSSV';
            } else {
                $targetUrl = '/pages/profile.html#pf-hssv-section';
                $actionLabel = 'Xem trạng thái HSSV';
            }
        } elseif ($refType === 'PAYMENT' || $type === 'PAYMENT' || str_contains($haystack, 'thanh toán') || str_contains($haystack, 'đặt cọc')) {
            if ($roleCode === 'ADMIN') {
                $targetUrl = '/pages/admin-portal.html?tab=payments';
                $actionLabel = 'Xem giao dịch thanh toán';
            } elseif ($extractedRoomId !== null) {
                $targetUrl = '/pages/booking-status.html?id=' . $extractedRoomId;
                $actionLabel = "Xem vé phòng #{$extractedRoomId}";
            } else {
                $targetUrl = '/pages/profile.html#pf-history-section';
                $actionLabel = 'Xem lịch sử thanh toán';
            }
        } elseif ($type === 'DM_ASSIGNMENT' || $refType === 'DM_ASSIGNMENT') {
            if ($roleCode === 'DM') {
                $targetUrl = $extractedRoomId !== null ? '/pages/dm-portal.html?room_id=' . $extractedRoomId : '/pages/dm-portal.html';
                $actionLabel = $extractedRoomId !== null ? "Xử lý ca phòng #{$extractedRoomId}" : 'Mở Cổng Quản Trò';
            } elseif ($roleCode === 'ADMIN') {
                $targetUrl = $extractedRoomId !== null ? '/pages/admin-portal.html?tab=rooms&room_id=' . $extractedRoomId : '/pages/admin-portal.html?tab=rooms';
                $actionLabel = 'Mở bảng Điều phối';
            } elseif ($extractedRoomId !== null) {
                $targetUrl = '/pages/room-detail.html?id=' . $extractedRoomId;
                $actionLabel = "Xem phòng #{$extractedRoomId}";
            }
        } elseif ($type === 'ADMIN') {
            $targetUrl = $extractedRoomId !== null
                ? '/pages/admin-portal.html?tab=rooms&room_id=' . $extractedRoomId
                : '/pages/admin-portal.html?tab=rooms';
            $actionLabel = $extractedRoomId !== null ? "Điều phối phòng #{$extractedRoomId}" : 'Mở bảng Điều phối';
        } elseif ($extractedRoomId !== null) {
            if ($roleCode === 'DM') {
                $targetUrl = '/pages/dm-portal.html?room_id=' . $extractedRoomId;
                $actionLabel = "Xem ca phòng #{$extractedRoomId}";
            } elseif ($roleCode === 'ADMIN') {
                $targetUrl = '/pages/admin-portal.html?tab=rooms&room_id=' . $extractedRoomId;
                $actionLabel = "Điều phối phòng #{$extractedRoomId}";
            } else {
                $targetUrl = '/pages/room-detail.html?id=' . $extractedRoomId;
                $actionLabel = "Mở phòng #{$extractedRoomId}";
            }
        } elseif (str_contains($haystack, 'kịch bản')) {
            $targetUrl = $roleCode === 'ADMIN' ? '/pages/admin-portal.html?tab=games' : '/pages/scenarios.html';
            $actionLabel = 'Xem Kho kịch bản';
        } elseif ($roleCode === 'DM') {
            $targetUrl = '/pages/dm-portal.html';
            $actionLabel = 'Mở Cổng Quản Trò';
        } elseif ($roleCode === 'ADMIN') {
            $targetUrl = '/pages/admin-portal.html?tab=rooms';
            $actionLabel = 'Mở Cổng Quản Trị';
        } else {
            $targetUrl = '/pages/lobby.html';
            $actionLabel = 'Vào Sảnh ghép phòng';
        }

        $n['is_read'] = (bool) ($n['is_read'] ?? false);
        $n['scope'] = $scope;
        $n['scope_label'] = $scopeLabel;
        $n['target_url'] = $targetUrl;
        $n['action_label'] = $actionLabel;
        return $n;
    }
}
