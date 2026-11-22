<?php

declare(strict_types=1);

namespace App\Features\DMManagement\Controllers;

use App\Core\Middleware\AuthMiddleware;
use App\Core\Request;
use App\Core\Response;
use App\Features\DMManagement\Repositories\DMRepository;
use App\Features\Notification\Repositories\NotificationRepository;
use App\Infrastructure\Database\DatabaseConnection;
use PDO;

class DMAssignmentController
{
    protected DMRepository $dmRepo;
    protected NotificationRepository $notiRepo;
    protected PDO $pdo;

    public function __construct(
        ?DMRepository $dmRepo = null,
        ?NotificationRepository $notiRepo = null,
        ?PDO $pdo = null
    ) {
        $this->pdo = $pdo ?? DatabaseConnection::getInstance();
        $this->dmRepo = $dmRepo ?? new DMRepository($this->pdo);
        $this->notiRepo = $notiRepo ?? new NotificationRepository($this->pdo);

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
     * POST /api/v1/admin/rooms/{id}/assign-dm
     * Phân công hoặc phân công lại DM cho phòng chơi (Dành riêng cho ADMIN)
     * Body: dm_user_id (int, required), notes (string, optional)
     */
    public function assign(Request $request, string $id): Response
    {
        $auth = AuthMiddleware::authenticate($request, ['ADMIN']);
        if ($auth instanceof Response) {
            return $auth;
        }

        $roomId = (int) $id;

        // 1. Kiểm tra phòng tồn tại và hợp lệ
        $rStmt = $this->pdo->prepare('SELECT `id`, `title`, `status` FROM `game_rooms` WHERE `id` = :id LIMIT 1');
        $rStmt->bindValue(':id', $roomId, PDO::PARAM_INT);
        $rStmt->execute();
        $room = $rStmt->fetch(PDO::FETCH_ASSOC);

        if ($room === false) {
            return Response::fail('Không tìm thấy phòng chơi yêu cầu', 'ROOM_NOT_FOUND', 404);
        }

        if (in_array($room['status'], ['CANCELLED', 'COMPLETED'], true)) {
            return Response::fail('Không thể phân công quản trò cho phòng đã kết thúc (Trạng thái: ' . $room['status'] . ')', 'INVALID_ROOM_STATUS', 400);
        }

        $body = $request->getBodyParams();
        $dmUserId = isset($body['dm_user_id']) ? (int) $body['dm_user_id'] : 0;
        $notes = trim((string) ($body['notes'] ?? ($body['reason'] ?? '')));

        if ($dmUserId <= 0) {
            return Response::fail('Vui lòng chọn quản trò hợp lệ', 'VALIDATION_ERROR', 422);
        }

        // 2. Kiểm tra tài khoản được phân công có vai trò DM hoặc ADMIN
        $uStmt = $this->pdo->prepare(
            'SELECT u.`id`, u.`full_name`, r.`code` AS `role_code`
             FROM `users` u
             INNER JOIN `roles` r ON r.`id` = u.`role_id`
             WHERE u.`id` = :id LIMIT 1'
        );
        $uStmt->bindValue(':id', $dmUserId, PDO::PARAM_INT);
        $uStmt->execute();
        $targetUser = $uStmt->fetch(PDO::FETCH_ASSOC);

        if ($targetUser === false || !in_array(strtoupper((string) $targetUser['role_code']), ['DM', 'ADMIN'], true)) {
            return Response::fail('Người dùng được chọn không có vai trò Quản trò (DM)', 'INVALID_DM_USER', 422);
        }

        // 3. Kiểm tra trùng lịch của DM (nếu phòng đã có khung giờ đề xuất hoặc chốt)
        $timing = $this->dmRepo->getAssignmentScheduleTimingForRoom($roomId);
        if ($timing !== null) {
            $conflict = $this->dmRepo->checkScheduleConflict($dmUserId, $timing['start_at'], $timing['end_at']);
            if ($conflict !== null) {
                return Response::fail(
                    "Quản trò {$targetUser['full_name']} đã có lịch dẫn buổi chơi khác trong khung giờ này ({$conflict['start_at']} - {$conflict['end_at']})",
                    'DM_SCHEDULE_CONFLICT',
                    400
                );
            }
        }

        // 4. Tạo assignment mới với status PENDING (và tự động revoke assignment PENDING cũ của phòng)
        $this->pdo->beginTransaction();
        try {
            $assignmentId = $this->dmRepo->assignToRoom(
                $roomId,
                $dmUserId,
                $notes !== '' ? $notes : 'Admin phân công quản trò',
                (int) $auth['id']
            );

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        // 5. Gửi thông báo cho DM được phân công
        $this->notiRepo->createNotification(
            'Phân công ca quản trò mới',
            "Bạn được phân công làm quản trò cho phòng '{$room['title']}'. Vui lòng phản hồi nhận ca sớm.",
            'DM_ASSIGNMENT',
            (int) $auth['id'],
            [$dmUserId]
        );

        $assignment = $this->dmRepo->findAssignmentById($assignmentId);

        return Response::success([
            'assignment' => $assignment,
        ], 'Phân công quản trò thành công', null, 201);
    }

    /**
     * POST /api/v1/admin/sessions/{id}/reassign-dm
     * Tái phân công DM từ Session (Tìm room tương ứng và gọi assign)
     */
    public function reassignSession(Request $request, string $id): Response
    {
        $sessionId = (int) $id;
        $sStmt = $this->pdo->prepare('SELECT `game_room_id` FROM `sessions` WHERE `id` = :id LIMIT 1');
        $sStmt->bindValue(':id', $sessionId, PDO::PARAM_INT);
        $sStmt->execute();
        $roomId = $sStmt->fetchColumn();

        if ($roomId === false) {
            // Thử tìm theo game_room_id trực tiếp nếu client truyền room_id
            $rStmt = $this->pdo->prepare('SELECT `id` FROM `game_rooms` WHERE `id` = :id LIMIT 1');
            $rStmt->bindValue(':id', $sessionId, PDO::PARAM_INT);
            $rStmt->execute();
            $roomId = $rStmt->fetchColumn();

            if ($roomId === false) {
                return Response::fail('Không tìm thấy buổi chơi yêu cầu', 'SESSION_NOT_FOUND', 404);
            }
        }

        return $this->assign($request, (string) $roomId);
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

        // Kiểm tra xung đột lịch với các ca khác của DM
        $timing = $this->dmRepo->getAssignmentScheduleTiming($assignmentId);
        if ($timing !== null) {
            $conflict = $this->dmRepo->checkScheduleConflict((int) $assignment['dm_user_id'], $timing['start_at'], $timing['end_at'], $assignmentId);
            if ($conflict !== null) {
                return Response::fail(
                    "Bạn đã có lịch dẫn buổi chơi khác trong khung giờ này ({$conflict['start_at']} - {$conflict['end_at']})",
                    'DM_SCHEDULE_CONFLICT',
                    400
                );
            }
        }

        $this->pdo->beginTransaction();
        try {
            // Cập nhật trạng thái ACCEPTED
            $this->dmRepo->respondAssignment($assignmentId, 'ACCEPTED');

            // DEF-05: Liên kết hoặc khởi tạo Session chính thức nếu phòng đã chốt biểu quyết lịch
            $this->dmRepo->linkSessionToAssignment((int) $assignment['game_room_id'], $assignmentId);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        $updated = $this->dmRepo->findAssignmentById($assignmentId);

        // Thông báo cho người tạo phòng
        $cStmt = $this->pdo->prepare('SELECT `creator_id`, `title` FROM `game_rooms` WHERE `id` = :room_id LIMIT 1');
        $cStmt->bindValue(':room_id', (int) $assignment['game_room_id'], PDO::PARAM_INT);
        $cStmt->execute();
        $roomInfo = $cStmt->fetch(PDO::FETCH_ASSOC);

        if ($roomInfo !== false) {
            $this->notiRepo->createNotification(
                'Quản trò đã nhận ca chơi',
                "Quản trò đã xác nhận nhận ca dẫn kịch bản cho phòng '{$roomInfo['title']}'.",
                'DM_ASSIGNMENT',
                (int) $auth['id'],
                [(int) $roomInfo['creator_id']]
            );
        }

        return Response::success([
            'assignment' => $updated,
        ], 'Đã chấp nhận ca làm việc thành công');
    }

    /**
     * POST /api/v1/dm/assignments/{id}/reject
     * Body: reason (string, required, min 5 chars)
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
        $reason = trim((string) ($body['reason'] ?? ($body['rejection_reason'] ?? '')));

        if ($reason === '' || mb_strlen($reason) < 5) {
            return Response::fail('Vui lòng nêu rõ lý do từ chối ca làm việc (tối thiểu 5 ký tự)', 'VALIDATION_ERROR', 422);
        }

        $this->pdo->beginTransaction();
        try {
            // 1. Cập nhật assignment sang REJECTED
            $this->dmRepo->respondAssignment($assignmentId, 'REJECTED', $reason);

            // 2. DEF-05: Gỡ bỏ Session tham chiếu đến assignment đã bị từ chối
            // Đảm bảo không tồn tại Session trỏ đến assignment REJECTED
            $this->dmRepo->unlinkOrRemoveSessionForAssignment($assignmentId);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        // 3. Thông báo cho toàn bộ tài khoản Admin để thực hiện điều phối / phân công lại DM
        $adminStmt = $this->pdo->query("SELECT u.`id` FROM `users` u INNER JOIN `roles` r ON r.`id` = u.`role_id` WHERE r.`code` = 'ADMIN'");
        $adminIds = $adminStmt !== false ? $adminStmt->fetchAll(PDO::FETCH_COLUMN) : [];

        if (!empty($adminIds)) {
            $dmName = $assignment['dm_name'] ?? 'Quản trò';
            $roomTitle = $assignment['room_title'] ?? ('#' . $assignment['game_room_id']);
            $this->notiRepo->createNotification(
                'Quản trò từ chối nhận ca',
                "{$dmName} đã từ chối nhận ca dẫn phòng '{$roomTitle}'. Lý do: {$reason}. Vui lòng phân công quản trò thay thế.",
                'DM_REASSIGNMENT_REQUIRED',
                (int) $auth['id'],
                array_map('intval', $adminIds)
            );
        }

        $updated = $this->dmRepo->findAssignmentById($assignmentId);

        return Response::success([
            'assignment' => $updated,
        ], 'Đã từ chối ca làm việc');
    }
}
