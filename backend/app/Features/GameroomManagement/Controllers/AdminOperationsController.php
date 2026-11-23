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

        // Gửi thông báo phân công ca cho Quản trò (DM)
        $notiRepo = new \App\Features\Notification\Repositories\NotificationRepository($this->pdo);
        $notiRepo->createNotification(
            'Bạn được phân công ca trực mới',
            "Quản trị viên đã phân công bạn phụ trách ca chơi phòng #{$roomId}. Vui lòng truy cập Cổng Quản Trò để phản hồi ca trực trong vòng 02 giờ.",
            'DM_ASSIGNMENT',
            (int) $auth['id'],
            [$dmUserId]
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

        if (!empty($assignment['dm_user_id'])) {
            $notiRepo = new \App\Features\Notification\Repositories\NotificationRepository($this->pdo);
            $notiRepo->createNotification(
                'Ca trực đã được thu hồi',
                "Ca phân công quản trò của bạn đã được Quản trị viên thu hồi. Lý do: {$reason}.",
                'DM_ASSIGNMENT',
                (int) $auth['id'],
                [(int) $assignment['dm_user_id']]
            );
        }

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
        $auth = AuthMiddleware::authenticate($request, ['ADMIN', 'DM']);
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

    /**
     * POST /api/v1/admin/rooms/offline-block
     * Khóa phòng vật lý thủ công cho khách đặt qua Hotline / Khách vãng lai (Walk-in)
     */
    public function offlineBlock(Request $request): Response
    {
        $auth = AuthMiddleware::authenticate($request, ['ADMIN']);
        if ($auth instanceof Response) {
            return $auth;
        }

        $adminId = (int) $auth['id'];
        $body = $request->getBodyParams();

        $venueId = (int) ($body['venue_id'] ?? 0);
        $physicalRoomId = (int) ($body['physical_room_id'] ?? 0);
        $startAt = trim((string) ($body['start_at'] ?? ''));
        $endAt = trim((string) ($body['end_at'] ?? ''));
        $customerName = trim((string) ($body['customer_name'] ?? ''));
        $customerPhone = trim((string) ($body['customer_phone'] ?? ''));
        $notes = isset($body['notes']) ? trim((string) $body['notes']) : null;

        if ($venueId <= 0 || $physicalRoomId <= 0 || $startAt === '' || $endAt === '' || $customerName === '' || $customerPhone === '') {
            return Response::fail('Vui lòng điền đầy đủ thông tin: chi nhánh, phòng vật lý, giờ bắt đầu, giờ kết thúc, tên và số điện thoại khách hàng', 'VALIDATION_ERROR', 422);
        }

        if (strtotime($startAt) >= strtotime($endAt)) {
            return Response::fail('Thời gian bắt đầu phải trước thời gian kết thúc', 'VALIDATION_ERROR', 422);
        }

        // Kiểm tra phòng vật lý tồn tại và active
        $prStmt = $this->pdo->prepare('SELECT `id`, `name`, `status` FROM `physical_rooms` WHERE `id` = :id AND `venue_id` = :vid LIMIT 1');
        $prStmt->bindValue(':id', $physicalRoomId, PDO::PARAM_INT);
        $prStmt->bindValue(':vid', $venueId, PDO::PARAM_INT);
        $prStmt->execute();
        $pr = $prStmt->fetch(PDO::FETCH_ASSOC);

        if ($pr === false) {
            return Response::fail('Phòng vật lý không tồn tại hoặc không thuộc chi nhánh đã chọn', 'PHYSICAL_ROOM_NOT_FOUND', 404);
        }

        // Kiểm tra xung đột với Session đã có
        $conflictStmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM `sessions`
             WHERE `physical_room_id` = :pr_id
               AND `status` IN (\'SCHEDULED\', \'IN_PROGRESS\')
               AND `start_at` < :end_at AND `end_at` > :start_at'
        );
        $conflictStmt->bindValue(':pr_id', $physicalRoomId, PDO::PARAM_INT);
        $conflictStmt->bindValue(':end_at', $endAt, PDO::PARAM_STR);
        $conflictStmt->bindValue(':start_at', $startAt, PDO::PARAM_STR);
        $conflictStmt->execute();
        if ((int) $conflictStmt->fetchColumn() > 0) {
            return Response::fail('Phòng vật lý đã có lịch chơi trong khung giờ này', 'PHYSICAL_ROOM_CONFLICT', 409);
        }

        // Kiểm tra xung đột với offline block khác
        $blockConflictStmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM `offline_room_blocks`
             WHERE `physical_room_id` = :pr_id
               AND `start_at` < :end_at AND `end_at` > :start_at'
        );
        $blockConflictStmt->bindValue(':pr_id', $physicalRoomId, PDO::PARAM_INT);
        $blockConflictStmt->bindValue(':end_at', $endAt, PDO::PARAM_STR);
        $blockConflictStmt->bindValue(':start_at', $startAt, PDO::PARAM_STR);
        $blockConflictStmt->execute();
        if ((int) $blockConflictStmt->fetchColumn() > 0) {
            return Response::fail('Phòng vật lý đã được khóa giữ chỗ thủ công trong khung giờ này', 'OFFLINE_BLOCK_CONFLICT', 409);
        }

        $insStmt = $this->pdo->prepare(
            'INSERT INTO `offline_room_blocks` (`venue_id`, `physical_room_id`, `start_at`, `end_at`, `customer_name`, `customer_phone`, `notes`, `created_by`, `created_at`)
             VALUES (:vid, :prid, :start, :end, :cname, :cphone, :notes, :created_by, NOW())'
        );
        $insStmt->bindValue(':vid', $venueId, PDO::PARAM_INT);
        $insStmt->bindValue(':prid', $physicalRoomId, PDO::PARAM_INT);
        $insStmt->bindValue(':start', $startAt, PDO::PARAM_STR);
        $insStmt->bindValue(':end', $endAt, PDO::PARAM_STR);
        $insStmt->bindValue(':cname', $customerName, PDO::PARAM_STR);
        $insStmt->bindValue(':cphone', $customerPhone, PDO::PARAM_STR);
        $insStmt->bindValue(':notes', $notes, $notes === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $insStmt->bindValue(':created_by', $adminId, PDO::PARAM_INT);
        $insStmt->execute();

        $blockId = (int) $this->pdo->lastInsertId();

        $this->auditService->log(
            actorId: $adminId,
            action: 'OFFLINE_ROOM_BLOCK_CREATED',
            entityType: 'physical_room',
            entityId: $physicalRoomId,
            metadata: [
                'block_id' => $blockId,
                'customer_name' => $customerName,
                'customer_phone' => $customerPhone,
                'start_at' => $startAt,
                'end_at' => $endAt,
            ]
        );

        return Response::success([
            'block_id' => $blockId,
            'physical_room_id' => $physicalRoomId,
            'physical_room_name' => $pr['name'],
            'start_at' => $startAt,
            'end_at' => $endAt,
            'customer_name' => $customerName,
            'customer_phone' => $customerPhone,
        ], 'Đã khóa phòng vật lý thủ công thành công cho khách Hotline/Walk-in', null, 201);
    }

    /**
     * GET /api/v1/admin/physical-rooms
     * Danh sách phòng vật lý tại tất cả chi nhánh kèm trạng thái bảo trì và ca chơi tiếp theo
     */
    public function listPhysicalRooms(Request $request): Response
    {
        $auth = AuthMiddleware::authenticate($request, ['ADMIN']);
        if ($auth instanceof Response) {
            return $auth;
        }

        $stmt = $this->pdo->query(
            'SELECT pr.`id`, pr.`venue_id`, v.`name` AS `venue_name`, pr.`name`, pr.`capacity`,
                    pr.`description`, pr.`status`, COALESCE(pr.`is_maintenance`, 0) AS `is_maintenance`,
                    pr.`theme_code`,
                    (
                        SELECT CONCAT(s.`id`, \':\', s.`status`, \':\', s.`start_at`, \':\', s.`end_at`, \':\', gr.`title`)
                        FROM `sessions` s
                        INNER JOIN `game_rooms` gr ON gr.`id` = s.`game_room_id`
                        WHERE s.`physical_room_id` = pr.`id`
                          AND s.`status` IN (\'SCHEDULED\', \'IN_PROGRESS\')
                          AND s.`end_at` > NOW()
                        ORDER BY s.`start_at` ASC
                        LIMIT 1
                    ) AS `upcoming_session_info`
             FROM `physical_rooms` pr
             INNER JOIN `venues` v ON v.`id` = pr.`venue_id`
             ORDER BY pr.`venue_id` ASC, pr.`id` ASC'
        );
        $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $data = array_map(function ($r) {
            $r['id'] = (int) $r['id'];
            $r['venue_id'] = (int) $r['venue_id'];
            $r['capacity'] = (int) $r['capacity'];
            $r['is_maintenance'] = (int) $r['is_maintenance'] === 1;
            return $r;
        }, $rooms);

        return Response::success($data, 'Lấy danh sách phòng vật lý thành công');
    }

    /**
     * POST /api/v1/admin/physical-rooms/{id}/maintenance
     * Bật/tắt chế độ bảo trì phòng vật lý và tự động điều chuyển ca chơi (Dynamic Room Rerouting)
     */
    public function togglePhysicalRoomMaintenance(Request $request, string $id): Response
    {
        $auth = AuthMiddleware::authenticate($request, ['ADMIN']);
        if ($auth instanceof Response) {
            return $auth;
        }

        $adminId = (int) $auth['id'];
        $roomId = (int) $id;
        $body = $request->getBodyParams();

        $prStmt = $this->pdo->prepare('SELECT `id`, `venue_id`, `name`, `capacity`, `is_maintenance`, `status` FROM `physical_rooms` WHERE `id` = :id LIMIT 1');
        $prStmt->bindValue(':id', $roomId, PDO::PARAM_INT);
        $prStmt->execute();
        $pr = $prStmt->fetch(PDO::FETCH_ASSOC);

        if ($pr === false) {
            return Response::fail('Không tìm thấy phòng vật lý', 'PHYSICAL_ROOM_NOT_FOUND', 404);
        }

        $currentMaint = (int) ($pr['is_maintenance'] ?? 0);
        $newMaint = isset($body['is_maintenance']) ? ($body['is_maintenance'] ? 1 : 0) : ($currentMaint === 1 ? 0 : 1);
        $reason = trim((string) ($body['reason'] ?? 'Bảo trì trang thiết bị và đạo cụ phòng'));

        $this->pdo->beginTransaction();
        try {
            $upStmt = $this->pdo->prepare('UPDATE `physical_rooms` SET `is_maintenance` = :m, `updated_at` = NOW() WHERE `id` = :id');
            $upStmt->bindValue(':m', $newMaint, PDO::PARAM_INT);
            $upStmt->bindValue(':id', $roomId, PDO::PARAM_INT);
            $upStmt->execute();

            $reroutedSessions = [];
            $unresolvedSessions = [];

            if ($newMaint === 1) {
                // Quét các ca chơi tương lai đang gán vào phòng này
                $sessStmt = $this->pdo->prepare(
                    'SELECT s.`id`, s.`game_room_id`, s.`start_at`, s.`end_at`, s.`dm_assignment_id`,
                            dma.`dm_user_id`, gr.`title` AS `room_title`, gr.`creator_id`,
                            COALESCE(gr.`total_players`, gr.`min_players`, 6) AS `total_players`
                     FROM `sessions` s
                     INNER JOIN `game_rooms` gr ON gr.`id` = s.`game_room_id`
                     LEFT JOIN `dm_assignments` dma ON dma.`id` = s.`dm_assignment_id`
                     WHERE s.`physical_room_id` = :pr_id
                       AND s.`status` = \'SCHEDULED\'
                       AND s.`end_at` > NOW()
                     ORDER BY s.`start_at` ASC'
                );
                $sessStmt->bindValue(':pr_id', $roomId, PDO::PARAM_INT);
                $sessStmt->execute();
                $upcoming = $sessStmt->fetchAll(PDO::FETCH_ASSOC);

                $schedRepo = new \App\Features\VenueScheduling\Repositories\SchedulingRepository($this->pdo);
                $notiRepo = new \App\Features\Notification\Repositories\NotificationRepository($this->pdo);

                foreach ($upcoming as $sess) {
                    $altRoom = $schedRepo->findAlternativePhysicalRoom(
                        (int) $pr['venue_id'],
                        $roomId,
                        (int) $sess['total_players'],
                        $sess['start_at'],
                        $sess['end_at'],
                        30
                    );

                    if ($altRoom !== null) {
                        $reStmt = $this->pdo->prepare('UPDATE `sessions` SET `physical_room_id` = :alt_id, `updated_at` = NOW() WHERE `id` = :sid');
                        $reStmt->bindValue(':alt_id', (int) $altRoom['id'], PDO::PARAM_INT);
                        $reStmt->bindValue(':sid', (int) $sess['id'], PDO::PARAM_INT);
                        $reStmt->execute();

                        $reroutedSessions[] = [
                            'session_id' => (int) $sess['id'],
                            'game_room_id' => (int) $sess['game_room_id'],
                            'room_title' => $sess['room_title'],
                            'from_room' => $pr['name'],
                            'to_room' => $altRoom['name'],
                        ];

                        // Gửi thông báo tới Host và DM
                        $recipients = array_values(array_filter([(int) $sess['creator_id'], (int) ($sess['dm_user_id'] ?? 0)]));
                        if (!empty($recipients)) {
                            $notiRepo->createNotification(
                                'Điều chuyển phòng chơi vật lý',
                                "Phòng '{$sess['room_title']}' đã được tự động điều chuyển từ {$pr['name']} sang {$altRoom['name']} do phòng cũ bảo trì kỹ thuật.",
                                'SYSTEM',
                                $adminId,
                                $recipients
                            );
                        }
                    } else {
                        $unresolvedSessions[] = [
                            'session_id' => (int) $sess['id'],
                            'game_room_id' => (int) $sess['game_room_id'],
                            'room_title' => $sess['room_title'],
                            'reason' => 'Không tìm thấy phòng vật lý thay thế trống trong khung giờ này',
                        ];
                    }
                }
            }

            $this->auditService->log(
                actorId: $adminId,
                action: $newMaint === 1 ? 'SET_PHYSICAL_ROOM_MAINTENANCE' : 'CLEAR_PHYSICAL_ROOM_MAINTENANCE',
                entityType: 'physical_room',
                entityId: $roomId,
                metadata: [
                    'room_name' => $pr['name'],
                    'is_maintenance' => $newMaint === 1,
                    'reason' => $reason,
                    'rerouted_sessions' => $reroutedSessions,
                    'unresolved_sessions' => $unresolvedSessions,
                ]
            );

            $this->pdo->commit();

            return Response::success([
                'physical_room_id' => $roomId,
                'name' => $pr['name'],
                'is_maintenance' => $newMaint === 1,
                'rerouted_sessions' => $reroutedSessions,
                'unresolved_sessions' => $unresolvedSessions,
            ], $newMaint === 1 ? 'Đã kích hoạt chế độ bảo trì và tự động điều chuyển ca chơi' : 'Đã tắt chế độ bảo trì phòng vật lý');
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
