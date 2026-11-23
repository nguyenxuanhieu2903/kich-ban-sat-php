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

        if ($assignment['status'] !== 'PENDING') {
            return Response::fail('Ca làm việc này đã được xử lý trước đó (Trạng thái: ' . $assignment['status'] . ')', 'INVALID_STATUS', 400);
        }

        $body = $request->getBodyParams();
        $reason = trim((string) ($body['reason'] ?? ''));

        if ($reason === '') {
            return Response::fail('Vui lòng nêu rõ lý do từ chối ca làm việc', 'VALIDATION_ERROR', 422);
        }

        $this->dmRepo->respondAssignment($assignmentId, 'REJECTED', $reason);

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
