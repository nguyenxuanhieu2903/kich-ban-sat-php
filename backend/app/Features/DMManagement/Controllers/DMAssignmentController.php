<?php

declare(strict_types=1);

namespace App\Features\DMManagement\Controllers;

use App\Core\Middleware\AuthMiddleware;
use App\Core\Request;
use App\Core\Response;
use App\Features\DMManagement\Repositories\DMRepository;
use App\Features\Notification\Repositories\NotificationRepository;
use App\Infrastructure\Database\DatabaseConnection;
use App\Shared\Services\AuditLogService;
use PDO;

class DMAssignmentController
{
    protected DMRepository $dmRepo;
    protected NotificationRepository $notiRepo;
    protected AuditLogService $auditService;
    protected PDO $pdo;

    public function __construct(
        ?DMRepository $dmRepo = null,
        ?NotificationRepository $notiRepo = null,
        ?AuditLogService $auditService = null,
        ?PDO $pdo = null
    ) {
        $this->pdo = $pdo ?? DatabaseConnection::getInstance();
        $this->dmRepo = $dmRepo ?? new DMRepository($this->pdo);
        $this->notiRepo = $notiRepo ?? new NotificationRepository($this->pdo);
        $this->auditService = $auditService ?? new AuditLogService($this->pdo);

        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }
    }

    /**
     * GET /api/v1/dm/assignments
     * Query: status, dm_id (admin only)
     */
    public function index(Request $request): Response
    {
        $auth = AuthMiddleware::authenticate($request, ['DM', 'ADMIN']);
        if ($auth instanceof Response) {
            return $auth;
        }

        $userRole = strtoupper((string) ($auth['role_code'] ?? ''));
        $targetDmId = (int) $auth['id'];

        if ($userRole === 'ADMIN') {
            $queryDmId = $request->query('dm_id');
            $targetDmId = $queryDmId !== null && (int) $queryDmId > 0 ? (int) $queryDmId : null;
        }

        $status = $request->query('status') !== null ? (string) $request->query('status') : null;

        $assignments = $this->dmRepo->listAssignments($targetDmId, $status);

        return Response::success([
            'assignments' => $assignments,
            'count' => count($assignments),
        ], 'Danh sách phân công quản trò');
    }

    /**
     * POST /api/v1/dm/assignments/{id}/accept
     */
    public function accept(Request $request, string $id): Response
    {
        $auth = AuthMiddleware::authenticate($request, ['DM', 'ADMIN']);
        if ($auth instanceof Response) {
            return $auth;
        }

        $assignmentId = (int) $id;
        $assignment = $this->dmRepo->findAssignmentById($assignmentId);

        if ($assignment === null) {
            return Response::fail('Không tìm thấy ca làm việc yêu cầu', 'ASSIGNMENT_NOT_FOUND', 404);
        }

        $userRole = strtoupper((string) ($auth['role_code'] ?? ''));

        // Chống IDOR: Chỉ DM được gán cho ca này hoặc Admin mới có quyền accept
        if ($userRole !== 'ADMIN' && (int) $assignment['dm_user_id'] !== (int) $auth['id']) {
            return Response::fail('Bạn không có quyền phản hồi ca làm việc của người khác', 'FORBIDDEN', 403);
        }

        if ($assignment['status'] !== 'PENDING') {
            return Response::fail('Ca làm việc này đã được xử lý trước đó (Trạng thái: ' . $assignment['status'] . ')', 'INVALID_STATUS', 400);
        }

        $this->dmRepo->respondAssignment($assignmentId, 'ACCEPTED');

        // Audit log & thông báo
        $this->auditService->log((int) $auth['id'], 'ACCEPT_DM_ASSIGNMENT', 'DM_ASSIGNMENT', $assignmentId, [
            'game_room_id' => $assignment['game_room_id'] ?? null,
        ]);

        $partsStmt = $this->pdo->prepare(
            'SELECT `user_id` FROM `room_participants` WHERE `game_room_id` = :room_id AND `status` IN (\'PENDING_DEPOSIT\', \'DEPOSITED\', \'LEAVE_REQUESTED\')'
        );
        $partsStmt->bindValue(':room_id', (int) $assignment['game_room_id'], PDO::PARAM_INT);
        $partsStmt->execute();
        $targetUserIds = array_map('intval', $partsStmt->fetchAll(PDO::FETCH_COLUMN));

        if (!empty($targetUserIds)) {
            $dmName = $auth['full_name'] ?? $auth['name'] ?? 'Quản trò';
            $this->notiRepo->createNotification(
                'Quản trò đã tiếp nhận phòng',
                "Quản trò {$dmName} đã chính thức tiếp nhận điều phối phiên chơi cho phòng '{$assignment['room_title']}'.",
                'ROOM',
                (int) $auth['id'],
                $targetUserIds
            );
        }

        $updated = $this->dmRepo->findAssignmentById($assignmentId);

        return Response::success([
            'assignment' => $updated,
        ], 'Đã chấp nhận ca làm việc thành công');
    }

    /**
     * POST /api/v1/dm/assignments/{id}/reject
     * Body: reason (string, required)
     */
    public function reject(Request $request, string $id): Response
    {
        $auth = AuthMiddleware::authenticate($request, ['DM', 'ADMIN']);
        if ($auth instanceof Response) {
            return $auth;
        }

        $assignmentId = (int) $id;
        $assignment = $this->dmRepo->findAssignmentById($assignmentId);

        if ($assignment === null) {
            return Response::fail('Không tìm thấy ca làm việc yêu cầu', 'ASSIGNMENT_NOT_FOUND', 404);
        }

        $userRole = strtoupper((string) ($auth['role_code'] ?? ''));

        // Chống IDOR: Chỉ DM được gán hoặc Admin mới có quyền reject
        if ($userRole !== 'ADMIN' && (int) $assignment['dm_user_id'] !== (int) $auth['id']) {
            return Response::fail('Bạn không có quyền từ chối ca làm việc của người khác', 'FORBIDDEN', 403);
        }

        if (!in_array($assignment['status'], ['PENDING', 'ACCEPTED'], true)) {
            return Response::fail('Ca làm việc này đã được xử lý trước đó (Trạng thái: ' . $assignment['status'] . ')', 'INVALID_STATUS', 400);
        }

        // Nếu ca đã ACCEPTED, kiểm tra xem phiên chơi đã diễn ra hoặc kết thúc chưa
        if ($assignment['status'] === 'ACCEPTED') {
            $sessCheck = $this->pdo->prepare('SELECT `status` FROM `sessions` WHERE `game_room_id` = :rid ORDER BY `id` DESC LIMIT 1');
            $sessCheck->bindValue(':rid', (int) $assignment['game_room_id'], PDO::PARAM_INT);
            $sessCheck->execute();
            $sessStatus = $sessCheck->fetchColumn();
            if (in_array($sessStatus, ['IN_PROGRESS', 'COMPLETED'], true)) {
                return Response::fail('Không thể hủy nhận ca khi buổi chơi đang diễn ra hoặc đã hoàn tất', 'SESSION_ALREADY_STARTED', 409);
            }
        }

        $body = $request->getBodyParams();
        $reason = trim((string) ($body['reason'] ?? ''));

        if ($reason === '') {
            return Response::fail('Vui lòng nêu rõ lý do từ chối ca làm việc', 'VALIDATION_ERROR', 422);
        }

        $this->dmRepo->respondAssignment($assignmentId, 'REJECTED', $reason);

        // Tự động xoay tua sang Quản trò (DM) rảnh tiếp theo chưa từ chối phòng này và không trùng lịch ca khác
        $rotatedAssignmentId = null;
        $rotatedDmId = null;
        $roomId = (int) ($assignment['game_room_id'] ?? 0);
        if ($roomId > 0) {
            $sessTimeStmt = $this->pdo->prepare(
                "SELECT `start_at`, `end_at` FROM `sessions`
                 WHERE `game_room_id` = :rid AND `status` = 'SCHEDULED'
                 ORDER BY `id` DESC LIMIT 1"
            );
            $sessTimeStmt->execute([':rid' => $roomId]);
            $sessTime = $sessTimeStmt->fetch(PDO::FETCH_ASSOC);

            if ($sessTime !== false && !empty($sessTime['start_at']) && !empty($sessTime['end_at'])) {
                $nextDmStmt = $this->pdo->prepare(
                    "SELECT u.`id`
                     FROM `users` u
                     INNER JOIN `roles` r ON r.`id` = u.`role_id`
                     WHERE r.`code` = 'DM'
                       AND u.`status` = 'ACTIVE'
                       AND u.`id` NOT IN (
                           SELECT da.`dm_user_id` FROM `dm_assignments` da
                           WHERE da.`game_room_id` = :rid AND da.`status` IN ('REJECTED', 'PENDING', 'ACCEPTED')
                       )
                       AND NOT EXISTS (
                           SELECT 1 FROM `sessions` s2
                           INNER JOIN `dm_assignments` da2 ON da2.`id` = s2.`dm_assignment_id`
                           WHERE da2.`dm_user_id` = u.`id`
                             AND da2.`status` IN ('PENDING', 'ACCEPTED')
                             AND s2.`status` IN ('SCHEDULED', 'IN_PROGRESS')
                             AND s2.`start_at` < DATE_ADD(:end_at, INTERVAL 30 MINUTE)
                             AND s2.`end_at` > DATE_SUB(:start_at, INTERVAL 30 MINUTE)
                       )
                     ORDER BY u.`id` ASC
                     LIMIT 1"
                );
                $nextDmStmt->execute([
                    ':rid' => $roomId,
                    ':start_at' => $sessTime['start_at'],
                    ':end_at' => $sessTime['end_at'],
                ]);
            } else {
                $nextDmStmt = $this->pdo->prepare(
                    "SELECT u.`id`
                     FROM `users` u
                     INNER JOIN `roles` r ON r.`id` = u.`role_id`
                     WHERE r.`code` = 'DM'
                       AND u.`status` = 'ACTIVE'
                       AND u.`id` NOT IN (
                           SELECT da.`dm_user_id` FROM `dm_assignments` da
                           WHERE da.`game_room_id` = :rid AND da.`status` IN ('REJECTED', 'PENDING', 'ACCEPTED')
                       )
                     ORDER BY u.`id` ASC
                     LIMIT 1"
                );
                $nextDmStmt->execute([':rid' => $roomId]);
            }
            $candidateId = (int) $nextDmStmt->fetchColumn();
            if ($candidateId > 0) {
                $rotatedDmId = $candidateId;
                $rotatedAssignmentId = $this->dmRepo->assignToRoom(
                    $roomId,
                    $candidateId,
                    'Tự động xoay tua do Quản trò trước từ chối ca',
                    (int) $auth['id']
                );
                $updSess = $this->pdo->prepare(
                    "UPDATE `sessions` SET `dm_assignment_id` = :daid, `updated_at` = NOW()
                     WHERE `game_room_id` = :rid AND `status` = 'SCHEDULED'"
                );
                $updSess->bindValue(':daid', $rotatedAssignmentId, PDO::PARAM_INT);
                $updSess->bindValue(':rid', $roomId, PDO::PARAM_INT);
                $updSess->execute();

                $this->notiRepo->createNotification(
                    'Phân công ca trực mới (Xoay tua tự động)',
                    "Bạn được hệ thống tự động xoay tua phân công phụ trách phòng '{$assignment['room_title']}'. Vui lòng xác nhận trong vòng 2 giờ.",
                    'DM_ASSIGNMENT',
                    (int) $auth['id'],
                    [$candidateId]
                );
            }
        }

        // Audit log & thông báo Admin
        $this->auditService->log((int) $auth['id'], 'REJECT_DM_ASSIGNMENT', 'DM_ASSIGNMENT', $assignmentId, [
            'game_room_id' => $assignment['game_room_id'] ?? null,
            'reason' => $reason,
        ]);

        $adminStmt = $this->pdo->query('SELECT u.`id` FROM `users` u JOIN `roles` r ON r.`id` = u.`role_id` WHERE r.`code` = \'ADMIN\' AND u.`status` = \'ACTIVE\'');
        $adminIds = array_map('intval', $adminStmt->fetchAll(PDO::FETCH_COLUMN));

        if (!empty($adminIds)) {
            $dmName = $auth['full_name'] ?? $auth['name'] ?? 'Quản trò';
            $this->notiRepo->createNotification(
                'Quản trò từ chối ca làm việc',
                "Quản trò {$dmName} đã từ chối nhận ca làm việc tại phòng '{$assignment['room_title']}'. Lý do: {$reason}.",
                'ADMIN',
                (int) $auth['id'],
                $adminIds
            );
        }

        $updated = $this->dmRepo->findAssignmentById($assignmentId);

        return Response::success([
            'assignment' => $updated,
        ], 'Đã từ chối ca làm việc');
    }
}
