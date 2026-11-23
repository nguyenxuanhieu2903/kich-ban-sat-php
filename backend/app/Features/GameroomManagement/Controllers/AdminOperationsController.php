<?php

declare(strict_types=1);

namespace App\Features\GameroomManagement\Controllers;

use App\Core\Middleware\AuthMiddleware;
use App\Core\Request;
use App\Core\Response;
use App\Features\DMManagement\Repositories\DMRepository;
use App\Infrastructure\Database\DatabaseConnection;
use App\Shared\Services\AuditLogService;
use PDO;

class AdminOperationsController
{
    protected PDO $pdo;
    protected DMRepository $dmRepo;
    protected AuditLogService $auditService;

    public function __construct(
        ?PDO $pdo = null,
        ?DMRepository $dmRepo = null,
        ?AuditLogService $auditService = null
    ) {
        $this->pdo = $pdo ?? DatabaseConnection::getInstance();
        $this->dmRepo = $dmRepo ?? new DMRepository($this->pdo);
        $this->auditService = $auditService ?? new AuditLogService($this->pdo);

        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }
    }

    /**
     * POST /api/v1/admin/rooms/{id}/assign-dm
     * Body: dm_user_id (int, required), reason (string, optional)
     */
    public function assignDm(Request $request, string $id): Response
    {
        $auth = AuthMiddleware::authenticate($request, ['ADMIN']);
        if ($auth instanceof Response) {
            return $auth;
        }

        $roomId = (int) $id;
        $roomStmt = $this->pdo->prepare('SELECT `id`, `status` FROM `game_rooms` WHERE `id` = :id LIMIT 1');
        $roomStmt->bindValue(':id', $roomId, PDO::PARAM_INT);
        $roomStmt->execute();
        $room = $roomStmt->fetch(PDO::FETCH_ASSOC);

        if ($room === false) {
            return Response::fail('Không tìm thấy phòng chơi yêu cầu', 'ROOM_NOT_FOUND', 404);
        }

        $body = $request->getBodyParams();
        $dmUserId = (int) ($body['dm_user_id'] ?? 0);

        if ($dmUserId <= 0) {
            return Response::fail('dm_user_id là bắt buộc', 'VALIDATION_ERROR', 422);
        }

        $dmStmt = $this->pdo->prepare(
            'SELECT u.`id`, u.`status`, r.`code` AS `role_code`
             FROM `users` u
             INNER JOIN `roles` r ON r.`id` = u.`role_id`
             WHERE u.`id` = :id LIMIT 1'
        );
        $dmStmt->bindValue(':id', $dmUserId, PDO::PARAM_INT);
        $dmStmt->execute();
        $dm = $dmStmt->fetch(PDO::FETCH_ASSOC);

        if ($dm === false || strtoupper((string) ($dm['role_code'] ?? '')) !== 'DM') {
            return Response::fail('Người dùng được chỉ định không phải là Quản trò (DM) hợp lệ', 'INVALID_DM', 422);
        }

        $reason = isset($body['reason']) ? trim((string) $body['reason']) : null;
        $assignmentId = $this->dmRepo->assignToRoom($roomId, $dmUserId, $reason, (int) $auth['id']);

        $this->auditService->log(
            (int) $auth['id'],
            'ASSIGN_DM',
            'DM_ASSIGNMENT',
            $assignmentId,
            [
                'game_room_id' => $roomId,
                'dm_user_id' => $dmUserId,
                'reason' => $reason,
            ]
        );

        return Response::success([
            'assignment_id' => $assignmentId,
            'game_room_id' => $roomId,
            'dm_user_id' => $dmUserId,
            'status' => 'PENDING',
        ], 'Phân công Quản trò (DM) cho phòng chơi thành công', null, 201);
    }

    /**
     * POST /api/v1/dm/assignments/{id}/revoke
     * Body: reason (string, optional)
     */
    public function revokeDmAssignment(Request $request, string $id): Response
    {
        $auth = AuthMiddleware::authenticate($request, ['ADMIN']);
        if ($auth instanceof Response) {
            return $auth;
        }

        $assignmentId = (int) $id;
        $assignment = $this->dmRepo->findAssignmentById($assignmentId);

        if ($assignment === null) {
            return Response::fail('Không tìm thấy ca phân công quản trò yêu cầu', 'ASSIGNMENT_NOT_FOUND', 404);
        }

        $body = $request->getBodyParams();
        $reason = isset($body['reason']) ? trim((string) $body['reason']) : 'Thu hồi phân công quản trò';

        $this->dmRepo->revokeAssignment($assignmentId, $reason);

        $this->auditService->log(
            (int) $auth['id'],
            'REVOKE_DM',
            'DM_ASSIGNMENT',
            $assignmentId,
            [
                'reason' => $reason,
                'game_room_id' => $assignment['game_room_id'] ?? null,
                'dm_user_id' => $assignment['dm_user_id'] ?? null,
            ]
        );

        return Response::success([
            'assignment_id' => $assignmentId,
            'status' => 'REVOKED',
        ], 'Đã thu hồi ca phân công quản trò thành công');
    }

    /**
     * POST /api/v1/admin/rooms/{id}/revoke-dm
     */
    public function revokeRoomDm(Request $request, string $id): Response
    {
        $auth = AuthMiddleware::authenticate($request, ['ADMIN']);
        if ($auth instanceof Response) {
            return $auth;
        }

        $roomId = (int) $id;
        $stmt = $this->pdo->prepare('SELECT `id` FROM `dm_assignments` WHERE `game_room_id` = :rid AND `status` IN (\'PENDING\', \'ACCEPTED\') ORDER BY `id` DESC LIMIT 1');
        $stmt->bindValue(':rid', $roomId, PDO::PARAM_INT);
        $stmt->execute();
        $assignmentId = $stmt->fetchColumn();

        if ($assignmentId !== false) {
            return $this->revokeDmAssignment($request, (string) $assignmentId);
        }

        return Response::success([
            'game_room_id' => $roomId,
            'status' => 'REVOKED',
        ], 'Đã thu hồi phân công Quản trò thành công');
    }

    /**
     * POST /api/v1/admin/rooms/{id}/assign-venue
     * Body: venue_id (int, optional), physical_room_id (int, optional)
     */
    public function assignVenue(Request $request, string $id): Response
    {
        $auth = AuthMiddleware::authenticate($request, ['ADMIN']);
        if ($auth instanceof Response) {
            return $auth;
        }

        $roomId = (int) $id;
        $roomStmt = $this->pdo->prepare('SELECT `id`, `status` FROM `game_rooms` WHERE `id` = :id LIMIT 1');
        $roomStmt->bindValue(':id', $roomId, PDO::PARAM_INT);
        $roomStmt->execute();
        $room = $roomStmt->fetch(PDO::FETCH_ASSOC);

        if ($room === false) {
            return Response::fail('Không tìm thấy phòng chơi yêu cầu', 'ROOM_NOT_FOUND', 404);
        }

        $body = $request->getBodyParams();
        $venueId = isset($body['venue_id']) && (int) $body['venue_id'] > 0 ? (int) $body['venue_id'] : null;
        $physicalRoomId = isset($body['physical_room_id']) && (int) $body['physical_room_id'] > 0 ? (int) $body['physical_room_id'] : null;

        if ($physicalRoomId !== null) {
            $pStmt = $this->pdo->prepare('SELECT `id`, `venue_id`, `capacity`, `status` FROM `physical_rooms` WHERE `id` = :id LIMIT 1');
            $pStmt->bindValue(':id', $physicalRoomId, PDO::PARAM_INT);
            $pStmt->execute();
            $pRoom = $pStmt->fetch(PDO::FETCH_ASSOC);

            if ($pRoom === false) {
                return Response::fail('Không tìm thấy phòng vật lý yêu cầu', 'PHYSICAL_ROOM_NOT_FOUND', 404);
            }
            $venueId = (int) $pRoom['venue_id'];
        }

        // Cập nhật session nếu phòng đã có session
        $sessStmt = $this->pdo->prepare('SELECT `id` FROM `sessions` WHERE `game_room_id` = :room_id LIMIT 1');
        $sessStmt->bindValue(':room_id', $roomId, PDO::PARAM_INT);
        $sessStmt->execute();
        $sessionId = $sessStmt->fetchColumn();

        if ($sessionId !== false) {
            $upSess = $this->pdo->prepare(
                'UPDATE `sessions`
                 SET `venue_id` = :venue_id,
                     `physical_room_id` = :physical_room_id,
                     `updated_at` = NOW()
                 WHERE `id` = :sess_id'
            );
            $upSess->bindValue(':venue_id', $venueId, $venueId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $upSess->bindValue(':physical_room_id', $physicalRoomId, $physicalRoomId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $upSess->bindValue(':sess_id', (int) $sessionId, PDO::PARAM_INT);
            $upSess->execute();
        }

        $this->auditService->log(
            (int) $auth['id'],
            'ASSIGN_VENUE',
            'GAME_ROOM',
            $roomId,
            [
                'venue_id' => $venueId,
                'physical_room_id' => $physicalRoomId,
            ]
        );

        return Response::success([
            'game_room_id' => $roomId,
            'venue_id' => $venueId,
            'physical_room_id' => $physicalRoomId,
        ], 'Gán địa điểm tổ chức thành công');
    }

    /**
     * PUT /api/v1/admin/users/{id}/student-verification
     * Body: student_verified (bool, required)
     */
    public function updateStudentVerification(Request $request, string $id): Response
    {
        $auth = AuthMiddleware::authenticate($request, ['ADMIN']);
        if ($auth instanceof Response) {
            return $auth;
        }

        $userId = (int) $id;
        $userStmt = $this->pdo->prepare('SELECT `id`, `student_verified` FROM `users` WHERE `id` = :id LIMIT 1');
        $userStmt->bindValue(':id', $userId, PDO::PARAM_INT);
        $userStmt->execute();
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);

        if ($user === false) {
            return Response::fail('Không tìm thấy người dùng yêu cầu', 'USER_NOT_FOUND', 404);
        }

        $body = $request->getBodyParams();
        $studentVerified = filter_var($body['student_verified'] ?? true, FILTER_VALIDATE_BOOLEAN);

        $upStmt = $this->pdo->prepare(
            'UPDATE `users`
             SET `student_verified` = :verified,
                 `updated_at` = NOW()
             WHERE `id` = :id'
        );
        $upStmt->bindValue(':verified', $studentVerified ? 1 : 0, PDO::PARAM_INT);
        $upStmt->bindValue(':id', $userId, PDO::PARAM_INT);
        $upStmt->execute();

        $this->auditService->log(
            (int) $auth['id'],
            'UPDATE_STUDENT_VERIFICATION',
            'USER',
            $userId,
            ['student_verified' => $studentVerified]
        );

        return Response::success([
            'user_id' => $userId,
            'student_verified' => $studentVerified,
        ], 'Cập nhật trạng thái xác minh sinh viên thành công');
    }
}
