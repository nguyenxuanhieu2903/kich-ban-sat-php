<?php

declare(strict_types=1);

namespace App\Features\VenueScheduling\Controllers;

use App\Core\Middleware\AuthMiddleware;
use App\Core\Request;
use App\Core\Response;
use App\Features\Notification\Repositories\NotificationRepository;
use App\Infrastructure\Database\DatabaseConnection;
use PDO;

class SessionController
{
    protected PDO $pdo;
    protected NotificationRepository $notiRepo;

    public function __construct(?PDO $pdo = null, ?NotificationRepository $notiRepo = null)
    {
        $this->pdo = $pdo ?? DatabaseConnection::getInstance();
        $this->notiRepo = $notiRepo ?? new NotificationRepository($this->pdo);

        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }
    }

    /**
     * GET /api/v1/sessions/{id}
     */
    public function show(Request $request, string $id): Response
    {
        $sessionId = (int) $id;
        $session = $this->findSessionWithDetails($sessionId);

        if ($session === null) {
            return Response::fail('Không tìm thấy buổi chơi yêu cầu', 'SESSION_NOT_FOUND', 404);
        }

        return Response::success([
            'session' => $session,
        ], 'Thông tin chi tiết buổi chơi');
    }

    /**
     * POST /api/v1/sessions/{id}/start
     * Bắt đầu buổi chơi (SCHEDULED -> IN_PROGRESS)
     * Quyền: Assigned DM hoặc ADMIN
     */
    public function start(Request $request, string $id): Response
    {
        $auth = AuthMiddleware::authenticate($request, ['DM', 'ADMIN']);
        if ($auth instanceof Response) {
            return $auth;
        }

        $sessionId = (int) $id;
        $session = $this->findSessionWithDetails($sessionId);

        if ($session === null) {
            return Response::fail('Không tìm thấy buổi chơi yêu cầu', 'SESSION_NOT_FOUND', 404);
        }

        $userRole = strtoupper((string) ($auth['role_code'] ?? ''));

        // Kiểm tra quyền: Chỉ DM được phân công hoặc Admin mới có quyền bắt đầu buổi chơi
        if ($userRole !== 'ADMIN' && (int) ($session['dm_user_id'] ?? 0) !== (int) $auth['id']) {
            return Response::fail('Chỉ Quản trò được phân công hoặc Quản trị viên mới có quyền bắt đầu buổi chơi', 'FORBIDDEN', 403);
        }

        // Idempotency: Nếu đã IN_PROGRESS thì trả về trạng thái hiện tại (replay an toàn)
        if ($session['status'] === 'IN_PROGRESS') {
            return Response::success([
                'session' => $session,
            ], 'Buổi chơi đang diễn ra (Idempotent replay)');
        }

        if ($session['status'] === 'COMPLETED') {
            return Response::fail('Buổi chơi này đã kết thúc, không thể bắt đầu lại', 'INVALID_SESSION_STATUS', 400);
        }

        if ($session['status'] === 'CANCELLED') {
            return Response::fail('Buổi chơi này đã bị hủy, không thể bắt đầu', 'INVALID_SESSION_STATUS', 400);
        }

        if ($session['status'] !== 'SCHEDULED') {
            return Response::fail("Chỉ có thể bắt đầu buổi chơi khi đang ở trạng thái SCHEDULED (Hiện tại: {$session['status']})", 'INVALID_SESSION_STATUS', 400);
        }

        // Cập nhật trạng thái buổi chơi sang IN_PROGRESS
        $stmt = $this->pdo->prepare(
            'UPDATE `sessions`
             SET `status` = \'IN_PROGRESS\',
                 `started_at` = COALESCE(`started_at`, NOW()),
                 `updated_at` = NOW()
             WHERE `id` = :id'
        );
        $stmt->bindValue(':id', $sessionId, PDO::PARAM_INT);
        $stmt->execute();

        $updated = $this->findSessionWithDetails($sessionId);

        return Response::success([
            'session' => $updated,
        ], 'Đã bắt đầu buổi chơi thành công');
    }

    /**
     * POST /api/v1/sessions/{id}/complete
     * Kết thúc buổi chơi (IN_PROGRESS -> COMPLETED)
     * Quyền: Assigned DM hoặc ADMIN
     */
    public function complete(Request $request, string $id): Response
    {
        $auth = AuthMiddleware::authenticate($request, ['DM', 'ADMIN']);
        if ($auth instanceof Response) {
            return $auth;
        }

        $sessionId = (int) $id;
        $session = $this->findSessionWithDetails($sessionId);

        if ($session === null) {
            return Response::fail('Không tìm thấy buổi chơi yêu cầu', 'SESSION_NOT_FOUND', 404);
        }

        $userRole = strtoupper((string) ($auth['role_code'] ?? ''));

        // Kiểm tra quyền: Chỉ DM được phân công hoặc Admin mới có quyền kết thúc buổi chơi
        if ($userRole !== 'ADMIN' && (int) ($session['dm_user_id'] ?? 0) !== (int) $auth['id']) {
            return Response::fail('Chỉ Quản trò được phân công hoặc Quản trị viên mới có quyền kết thúc buổi chơi', 'FORBIDDEN', 403);
        }

        // Idempotency: Nếu đã COMPLETED thì trả về trạng thái hiện tại (replay an toàn)
        if ($session['status'] === 'COMPLETED') {
            return Response::success([
                'session' => $session,
            ], 'Buổi chơi đã kết thúc (Idempotent replay)');
        }

        if ($session['status'] === 'CANCELLED') {
            return Response::fail('Buổi chơi này đã bị hủy, không thể kết thúc', 'INVALID_SESSION_STATUS', 400);
        }

        if ($session['status'] !== 'IN_PROGRESS') {
            return Response::fail("Chỉ có thể kết thúc buổi chơi khi đang diễn ra (IN_PROGRESS) (Hiện tại: {$session['status']})", 'INVALID_SESSION_STATUS', 400);
        }

        $body = $request->getBodyParams();
        $sessionNotes = isset($body['session_notes']) && trim((string) $body['session_notes']) !== '' ? trim((string) $body['session_notes']) : null;
        $incidentReport = isset($body['incident_report']) && trim((string) $body['incident_report']) !== '' ? trim((string) $body['incident_report']) : null;

        $this->pdo->beginTransaction();
        try {
            // 1. Cập nhật session sang COMPLETED kèm ghi chú và báo cáo sự cố (nếu có)
            $stmt = $this->pdo->prepare(
                'UPDATE `sessions`
                 SET `status` = \'COMPLETED\',
                     `completed_at` = COALESCE(`completed_at`, NOW()),
                     `session_notes` = :session_notes,
                     `incident_report` = :incident_report,
                     `updated_at` = NOW()
                 WHERE `id` = :id'
            );
            $stmt->bindValue(':session_notes', $sessionNotes, $sessionNotes !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':incident_report', $incidentReport, $incidentReport !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':id', $sessionId, PDO::PARAM_INT);
            $stmt->execute();

            // 2. Đồng bộ trạng thái game_rooms sang COMPLETED
            $upRoom = $this->pdo->prepare(
                'UPDATE `game_rooms`
                 SET `status` = \'COMPLETED\',
                     `updated_at` = NOW()
                 WHERE `id` = :room_id'
            );
            $upRoom->bindValue(':room_id', (int) $session['game_room_id'], PDO::PARAM_INT);
            $upRoom->execute();

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        $updated = $this->findSessionWithDetails($sessionId);

        return Response::success([
            'session' => $updated,
        ], 'Đã hoàn thành buổi chơi thành công');
    }

    /**
     * Tìm thông tin buổi chơi kèm chi tiết quản trò và phòng
     *
     * @return array<string, mixed>|null
     */
    protected function findSessionWithDetails(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.`id`, s.`game_room_id`, s.`schedule_proposal_id`, s.`dm_assignment_id`,
                    s.`venue_id`, s.`physical_room_id`, s.`start_at`, s.`end_at`, s.`status`,
                    s.`started_at`, s.`completed_at`, s.`cancelled_at`, s.`cancel_reason`,
                    s.`session_notes`, s.`incident_report`,
                    s.`created_at`, s.`updated_at`,
                    a.`dm_user_id`, a.`status` AS `dm_assignment_status`,
                    dm.`full_name` AS `dm_name`, dm.`email` AS `dm_email`,
                    r.`title` AS `room_title`, r.`status` AS `room_status`
             FROM `sessions` s
             LEFT JOIN `dm_assignments` a ON a.`id` = s.`dm_assignment_id`
             LEFT JOIN `users` dm ON dm.`id` = a.`dm_user_id`
             LEFT JOIN `game_rooms` r ON r.`id` = s.`game_room_id`
             WHERE s.`id` = :id
             LIMIT 1'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result !== false ? $result : null;
    }
}
