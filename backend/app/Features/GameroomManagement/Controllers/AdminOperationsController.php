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
        $roomStmt = $this->pdo->prepare(
            'SELECT gr.`id`, gr.`status`, gr.`min_players`, gr.`max_players`, gr.`total_players`,
                    gr.`creator_id`, gr.`booking_type`, gr.`guest_slots`, gr.`target_time_slot`, gr.`price_details`,
                    COALESCE(g.`duration_minutes`, 210) AS `duration_minutes`
             FROM `game_rooms` gr
             INNER JOIN `games` g ON g.`id` = gr.`game_id`
             WHERE gr.`id` = :id LIMIT 1'
        );
        $roomStmt->bindValue(':id', $roomId, PDO::PARAM_INT);
        $roomStmt->execute();
        $room = $roomStmt->fetch(PDO::FETCH_ASSOC);

        if ($room === false) {
            return Response::fail('Không tìm thấy phòng chơi yêu cầu', 'ROOM_NOT_FOUND', 404);
        }
        $roomStatusUpper = strtoupper((string) ($room['status'] ?? ''));
        if (in_array($roomStatusUpper, ['CANCELLED', 'COMPLETED'], true)) {
            return Response::fail('Không thể phân công Quản trò cho phòng đã hủy hoặc đã hoàn tất', 'INVALID_ROOM_STATUS', 400);
        }
        if ($roomStatusUpper === 'IN_PROGRESS') {
            return Response::fail('Phòng chơi đang diễn ra, không thể thay đổi Quản trò giữa chừng', 'SESSION_IN_PROGRESS', 409);
        }

        // Chỉ cho phép gán Quản trò khi phòng đã đạt đủ số lượng người chơi tối thiểu (min_players)
        $pRowsStmt = $this->pdo->prepare(
            'SELECT `user_id` FROM `room_participants`
             WHERE `game_room_id` = :rid AND `status` IN (\'PENDING_DEPOSIT\', \'DEPOSITED\', \'LEAVE_REQUESTED\')'
        );
        $pRowsStmt->execute([':rid' => $roomId]);
        $activeUids = array_map('intval', $pRowsStmt->fetchAll(PDO::FETCH_COLUMN));
        $seatCalc = \App\Shared\Services\RoomSeatHelper::computeSeats($room, $activeUids);
        $occupiedSeats = $seatCalc['occupied_seats'];
        $minRequired = max(1, (int) ($room['min_players'] ?? 1));

        if ($occupiedSeats < $minRequired) {
            return Response::fail(
                "Phòng chơi chưa đạt đủ số lượng người chơi tối thiểu ({$occupiedSeats}/{$minRequired} người), chưa được phép gán Quản trò.",
                'INSUFFICIENT_PLAYERS',
                422
            );
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
            return Response::fail('Người dùng được chỉ định không phải là Quản trò hợp lệ', 'INVALID_DM', 422);
        }
        if (strtoupper((string) ($dm['status'] ?? '')) !== 'ACTIVE') {
            return Response::fail('Tài khoản Quản trò này đang bị khóa hoặc chưa kích hoạt', 'DM_INACTIVE', 422);
        }

        // Xác định khung giờ thực tế của phòng theo thời lượng kịch bản (sessions -> schedule_proposals -> target_time_slot)
        $roomSessStmt = $this->pdo->prepare(
            'SELECT s.`id`, s.`start_at`, s.`end_at`, pr.`name` AS `physical_room_name`
             FROM `sessions` s
             LEFT JOIN `physical_rooms` pr ON pr.`id` = s.`physical_room_id`
             WHERE s.`game_room_id` = :rid AND s.`status` IN (\'SCHEDULED\', \'IN_PROGRESS\')
             ORDER BY s.`id` DESC LIMIT 1'
        );
        $roomSessStmt->execute([':rid' => $roomId]);
        $roomSess = $roomSessStmt->fetch(PDO::FETCH_ASSOC);

        $durMins = max(60, (int) ($room['duration_minutes'] ?? 210));
        $slotStart = null;
        $slotEnd = null;
        if ($roomSess !== false && !empty($roomSess['start_at'])) {
            $ts = strtotime((string) $roomSess['start_at']);
            if ($ts !== false) {
                $slotStart = date('Y-m-d H:i:s', $ts);
                $slotEnd = date('Y-m-d H:i:s', $ts + $durMins * 60);
            }
        } else {
            $propStmt = $this->pdo->prepare('SELECT `proposed_start_at`, `proposed_end_at` FROM `schedule_proposals` WHERE `game_room_id` = :rid ORDER BY `id` DESC LIMIT 1');
            $propStmt->execute([':rid' => $roomId]);
            $propRow = $propStmt->fetch(PDO::FETCH_ASSOC);
            if ($propRow !== false && !empty($propRow['proposed_start_at'])) {
                $ts = strtotime((string) $propRow['proposed_start_at']);
                if ($ts !== false) {
                    $slotStart = date('Y-m-d H:i:s', $ts);
                    $slotEnd = date('Y-m-d H:i:s', $ts + $durMins * 60);
                }
            } elseif (!empty($room['target_time_slot']) && strtotime((string) $room['target_time_slot']) !== false) {
                $ts = strtotime((string) $room['target_time_slot']);
                $slotStart = date('Y-m-d H:i:s', $ts);
                $slotEnd = date('Y-m-d H:i:s', $ts + $durMins * 60);
            }
        }

        // Phương án 1: Kiểm tra trùng khung giờ theo thời lượng từng kịch bản + 30 phút đệm nghỉ/dọn phòng
        // (Ca IN_PROGRESS tính từ giờ bấm bắt đầu thực tế started_at + thời lượng kịch bản + 30 phút đệm)
        if ($slotStart !== null && $slotEnd !== null) {
            $myS = strtotime($slotStart);
            $myE = strtotime($slotEnd);
            $nowTs = time();

            $otherSessStmt = $this->pdo->prepare(
                'SELECT s.`game_room_id`, s.`status`, s.`start_at`, s.`end_at`, s.`started_at`,
                        COALESCE(g.`duration_minutes`, 210) AS `duration_minutes`
                 FROM `sessions` s
                 INNER JOIN `dm_assignments` da ON da.`id` = s.`dm_assignment_id`
                 INNER JOIN `game_rooms` gr ON gr.`id` = s.`game_room_id`
                 INNER JOIN `games` g ON g.`id` = gr.`game_id`
                 WHERE da.`dm_user_id` = :dm_uid
                   AND da.`status` IN (\'PENDING\', \'ACCEPTED\')
                   AND s.`game_room_id` != :rid
                   AND s.`status` IN (\'SCHEDULED\', \'IN_PROGRESS\')'
            );
            $otherSessStmt->execute([':dm_uid' => $dmUserId, ':rid' => $roomId]);
            $checkedRooms = [];
            foreach ($otherSessStmt->fetchAll(PDO::FETCH_ASSOC) as $osRow) {
                $oRid = (int) $osRow['game_room_id'];
                $checkedRooms[$oRid] = true;
                $oDur = max(60, (int) ($osRow['duration_minutes'] ?? 210));
                $isRun = strtoupper((string) $osRow['status']) === 'IN_PROGRESS';
                $os = $isRun && !empty($osRow['started_at'])
                    ? strtotime((string) $osRow['started_at'])
                    : strtotime((string) ($osRow['start_at'] ?? ''));
                if ($os === false) {
                    $os = $isRun ? $nowTs : false;
                }
                if ($os === false) {
                    continue;
                }
                $oe = $os + $oDur * 60;
                if ($isRun) {
                    $oe = max($oe, $nowTs + 60);
                }
                if ($myS < ($oe + 1800) && $myE > ($os - 1800)) {
                    $freeAt = date('H:i d/m', $oe + 1800);
                    $spanTxt = date('H:i', $os) . '–' . date('H:i', $oe);
                    return Response::fail(
                        "Quản trò này đã bận dẫn phòng #{$oRid} ({$spanTxt} + 30 phút nghỉ, rảnh lại từ {$freeAt}), không thể gán trùng khung giờ.",
                        'DM_SCHEDULE_CONFLICT',
                        409
                    );
                }
            }

            // Kiểm tra thêm các phòng khác đã gán DM này (PENDING/ACCEPTED) nhưng chưa khởi tạo dòng sessions
            $otherDaStmt = $this->pdo->prepare(
                'SELECT gr.`id`, gr.`status`, gr.`target_time_slot`, COALESCE(g.`duration_minutes`, 210) AS `duration_minutes`
                 FROM `dm_assignments` da
                 INNER JOIN `game_rooms` gr ON gr.`id` = da.`game_room_id`
                 INNER JOIN `games` g ON g.`id` = gr.`game_id`
                 WHERE da.`dm_user_id` = :dm_uid
                   AND da.`status` IN (\'PENDING\', \'ACCEPTED\')
                   AND gr.`id` != :rid
                   AND gr.`status` NOT IN (\'CANCELLED\', \'COMPLETED\')
                   AND gr.`target_time_slot` IS NOT NULL'
            );
            $otherDaStmt->execute([':dm_uid' => $dmUserId, ':rid' => $roomId]);
            foreach ($otherDaStmt->fetchAll(PDO::FETCH_ASSOC) as $oda) {
                $oRid = (int) $oda['id'];
                if (isset($checkedRooms[$oRid])) {
                    continue;
                }
                $os = strtotime((string) $oda['target_time_slot']);
                if ($os === false) {
                    continue;
                }
                $oDur = max(60, (int) $oda['duration_minutes']);
                $oe = $os + $oDur * 60;
                if ($myS < ($oe + 1800) && $myE > ($os - 1800)) {
                    $freeAt = date('H:i d/m', $oe + 1800);
                    $spanTxt = date('H:i', $os) . '–' . date('H:i', $oe);
                    return Response::fail(
                        "Quản trò này đã được phân công phòng #{$oRid} ({$spanTxt} + 30 phút nghỉ, rảnh lại từ {$freeAt}), không thể gán trùng khung giờ.",
                        'DM_SCHEDULE_CONFLICT',
                        409
                    );
                }
            }
        }

        $reason = isset($body['reason']) ? trim((string) $body['reason']) : null;

        // Thu hồi các phân công DM cũ đang PENDING/ACCEPTED của phòng này
        $revOld = $this->pdo->prepare(
            "UPDATE `dm_assignments`
             SET `status` = 'REVOKED', `reason` = 'Admin phân công Quản trò mới', `revoked_at` = NOW(), `updated_at` = NOW()
             WHERE `game_room_id` = :rid AND `status` IN ('PENDING', 'ACCEPTED')"
        );
        $revOld->bindValue(':rid', $roomId, PDO::PARAM_INT);
        $revOld->execute();

        $assignmentId = $this->dmRepo->assignToRoom($roomId, $dmUserId, $reason, (int) $auth['id']);

        // Đồng bộ trạng thái phòng: khi gán DM cho phòng FORMING hoặc CONFIRMED (đổi DM) -> chuyển sang SCHEDULED (chờ DM mới xác nhận ca)
        $currentRoomStatus = strtoupper((string) ($room['status'] ?? 'FORMING'));
        $nextRoomStatus = in_array($currentRoomStatus, ['FORMING', 'CONFIRMED'], true) ? 'SCHEDULED' : $currentRoomStatus;
        if ($nextRoomStatus !== $currentRoomStatus) {
            $this->pdo->prepare('UPDATE `game_rooms` SET `status` = :st, `updated_at` = NOW() WHERE `id` = :rid')
                ->execute([':st' => $nextRoomStatus, ':rid' => $roomId]);
        }

        // Đồng bộ dm_assignment_id vào session SCHEDULED hiện có (nếu phòng đã xếp lịch)
        $updSess = $this->pdo->prepare(
            "UPDATE `sessions` SET `dm_assignment_id` = :daid, `updated_at` = NOW()
             WHERE `game_room_id` = :rid AND `status` = 'SCHEDULED'"
        );
        $updSess->bindValue(':daid', $assignmentId, PDO::PARAM_INT);
        $updSess->bindValue(':rid', $roomId, PDO::PARAM_INT);
        $updSess->execute();

        $this->auditService->log(
            (int) $auth['id'],
            'ASSIGN_DM',
            'DM_ASSIGNMENT',
            $assignmentId,
            [
                'game_room_id' => $roomId,
                'dm_user_id' => $dmUserId,
                'room_status' => $nextRoomStatus,
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
            [$dmUserId],
            'ROOM',
            $roomId
        );

        return Response::success([
            'assignment_id' => $assignmentId,
            'game_room_id' => $roomId,
            'dm_user_id' => $dmUserId,
            'room_status' => $nextRoomStatus,
            'status' => 'PENDING',
        ], 'Phân công Quản trò (DM) thành công — phòng đã chuyển trạng thái chờ DM xác nhận (SCHEDULED)', null, 201);
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

        // Nếu phòng đang ở CONFIRMED, đưa lại về SCHEDULED (chờ Admin gán DM mới)
        if (!empty($assignment['game_room_id'])) {
            $this->pdo->prepare(
                "UPDATE `game_rooms` SET `status` = 'SCHEDULED', `updated_at` = NOW()
                 WHERE `id` = :rid AND `status` = 'CONFIRMED'"
            )->execute([':rid' => (int) $assignment['game_room_id']]);
        }

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
        $roomStmt = $this->pdo->prepare(
            'SELECT gr.`id`, gr.`status`, gr.`min_players`, gr.`max_players`, gr.`total_players`,
                    gr.`creator_id`, gr.`booking_type`, gr.`guest_slots`, gr.`target_time_slot`, gr.`price_details`,
                    COALESCE(g.`duration_minutes`, 210) AS `duration_minutes`
             FROM `game_rooms` gr
             INNER JOIN `games` g ON g.`id` = gr.`game_id`
             WHERE gr.`id` = :id LIMIT 1'
        );
        $roomStmt->bindValue(':id', $roomId, PDO::PARAM_INT);
        $roomStmt->execute();
        $room = $roomStmt->fetch(PDO::FETCH_ASSOC);

        if ($room === false) {
            return Response::fail('Không tìm thấy phòng chơi yêu cầu', 'ROOM_NOT_FOUND', 404);
        }
        $roomStatusUpper = strtoupper((string) ($room['status'] ?? ''));
        if (in_array($roomStatusUpper, ['CANCELLED', 'COMPLETED'], true)) {
            return Response::fail('Không thể gán phòng vật lý cho phòng đã hủy hoặc đã hoàn tất', 'INVALID_ROOM_STATUS', 400);
        }
        if ($roomStatusUpper === 'IN_PROGRESS') {
            return Response::fail('Phòng chơi đang diễn ra, không thể đổi phòng vật lý giữa chừng', 'SESSION_IN_PROGRESS', 409);
        }

        // Kiểm tra sĩ số phòng chuẩn xác qua RoomSeatHelper
        $pRowsStmt = $this->pdo->prepare(
            'SELECT `user_id` FROM `room_participants`
             WHERE `game_room_id` = :rid AND `status` IN (\'PENDING_DEPOSIT\', \'DEPOSITED\', \'LEAVE_REQUESTED\')'
        );
        $pRowsStmt->execute([':rid' => $roomId]);
        $activeUids = array_map('intval', $pRowsStmt->fetchAll(PDO::FETCH_COLUMN));
        $seatCalc = \App\Shared\Services\RoomSeatHelper::computeSeats($room, $activeUids);
        $occupiedSeats = $seatCalc['occupied_seats'];
        $minRequired = max(1, (int) ($room['min_players'] ?? 1));

        if ($occupiedSeats < $minRequired) {
            return Response::fail(
                "Phòng chơi chưa đạt đủ số lượng người chơi tối thiểu ({$occupiedSeats}/{$minRequired} người), chưa được phép gán phòng vật lý.",
                'INSUFFICIENT_PLAYERS',
                422
            );
        }

        $body = $request->getBodyParams();
        $venueId = isset($body['venue_id']) && (int) $body['venue_id'] > 0 ? (int) $body['venue_id'] : null;
        $physicalRoomId = isset($body['physical_room_id']) && (int) $body['physical_room_id'] > 0 ? (int) $body['physical_room_id'] : null;
        $pRoom = null;

        if ($physicalRoomId !== null) {
            $pStmt = $this->pdo->prepare('SELECT `id`, `venue_id`, `name`, `capacity`, `status`, `is_maintenance` FROM `physical_rooms` WHERE `id` = :id LIMIT 1');
            $pStmt->bindValue(':id', $physicalRoomId, PDO::PARAM_INT);
            $pStmt->execute();
            $pRoom = $pStmt->fetch(PDO::FETCH_ASSOC);

            if ($pRoom === false) {
                return Response::fail('Không tìm thấy phòng vật lý yêu cầu', 'PHYSICAL_ROOM_NOT_FOUND', 404);
            }
            if (strtoupper((string) ($pRoom['status'] ?? '')) !== 'ACTIVE' || !empty($pRoom['is_maintenance'])) {
                return Response::fail('Phòng vật lý đang bảo trì hoặc không khả dụng', 'PHYSICAL_ROOM_UNAVAILABLE', 422);
            }
            $requiredCap = max($occupiedSeats, $minRequired);
            if ((int) $pRoom['capacity'] < $requiredCap) {
                return Response::fail(
                    "Phòng vật lý '{$pRoom['name']}' chỉ có sức chứa {$pRoom['capacity']} chỗ, không đủ cho {$requiredCap} người chơi.",
                    'INSUFFICIENT_ROOM_CAPACITY',
                    422
                );
            }
            $venueId = (int) $pRoom['venue_id'];
        }

        // Xác định session hiện có và khung giờ thực tế của phòng
        $sessStmt = $this->pdo->prepare('SELECT `id`, `start_at`, `end_at`, `dm_assignment_id` FROM `sessions` WHERE `game_room_id` = :room_id LIMIT 1');
        $sessStmt->bindValue(':room_id', $roomId, PDO::PARAM_INT);
        $sessStmt->execute();
        $sessionRow = $sessStmt->fetch(PDO::FETCH_ASSOC);
        $sessionId = $sessionRow ? (int) $sessionRow['id'] : false;

        $durMins = max(60, (int) ($room['duration_minutes'] ?? 210));
        $propStmt = $this->pdo->prepare('SELECT `id`, `proposed_start_at`, `proposed_end_at`, `status` FROM `schedule_proposals` WHERE `game_room_id` = :rid ORDER BY `id` DESC LIMIT 1');
        $propStmt->execute([':rid' => $roomId]);
        $propRow = $propStmt->fetch(PDO::FETCH_ASSOC);

        if ($sessionRow !== false && !empty($sessionRow['start_at']) && !empty($sessionRow['end_at'])) {
            $effStart = (string) $sessionRow['start_at'];
            $effEnd = (string) $sessionRow['end_at'];
        } elseif ($propRow !== false && !empty($propRow['proposed_start_at'])) {
            $effStart = (string) $propRow['proposed_start_at'];
            $effEnd = !empty($propRow['proposed_end_at'])
                ? (string) $propRow['proposed_end_at']
                : date('Y-m-d H:i:s', strtotime($effStart) + $durMins * 60);
        } elseif (!empty($room['target_time_slot']) && strtotime((string) $room['target_time_slot']) !== false) {
            $ts = strtotime((string) $room['target_time_slot']);
            $effStart = date('Y-m-d H:i:s', $ts);
            $effEnd = date('Y-m-d H:i:s', $ts + $durMins * 60);
        } else {
            $ts = strtotime('+1 day 14:00:00');
            $effStart = date('Y-m-d H:i:s', $ts);
            $effEnd = date('Y-m-d H:i:s', $ts + $durMins * 60);
        }

        // Phương án 1: Kiểm tra trùng khung giờ phòng vật lý theo thời lượng từng kịch bản + 30 phút dọn phòng
        if ($physicalRoomId !== null) {
            $myS = strtotime($effStart);
            $myE = strtotime($effEnd);
            $nowTs = time();

            $otherPrSessStmt = $this->pdo->prepare(
                "SELECT s.`game_room_id`, s.`status`, s.`start_at`, s.`end_at`, s.`started_at`,
                        COALESCE(g.`duration_minutes`, 210) AS `duration_minutes`
                 FROM `sessions` s
                 INNER JOIN `game_rooms` gr ON gr.`id` = s.`game_room_id`
                 INNER JOIN `games` g ON g.`id` = gr.`game_id`
                 WHERE s.`physical_room_id` = :pr_id
                   AND s.`game_room_id` != :rid
                   AND s.`status` IN ('SCHEDULED', 'IN_PROGRESS')"
            );
            $otherPrSessStmt->execute([':pr_id' => $physicalRoomId, ':rid' => $roomId]);
            foreach ($otherPrSessStmt->fetchAll(PDO::FETCH_ASSOC) as $opRow) {
                $oDur = max(60, (int) ($opRow['duration_minutes'] ?? 210));
                $isRun = strtoupper((string) $opRow['status']) === 'IN_PROGRESS';
                $os = $isRun && !empty($opRow['started_at'])
                    ? strtotime((string) $opRow['started_at'])
                    : strtotime((string) ($opRow['start_at'] ?? ''));
                if ($os === false) {
                    $os = $isRun ? $nowTs : false;
                }
                if ($os === false) {
                    continue;
                }
                $oe = $os + $oDur * 60;
                if ($isRun) {
                    $oe = max($oe, $nowTs + 60);
                }
                if ($myS !== false && $myE !== false && $myS < ($oe + 1800) && $myE > ($os - 1800)) {
                    $freeAt = date('H:i d/m', $oe + 1800);
                    $spanTxt = date('H:i', $os) . '–' . date('H:i', $oe);
                    return Response::fail(
                        "Phòng vật lý đã có lịch phòng #{$opRow['game_room_id']} ({$spanTxt} + 30 phút dọn phòng, trống lại từ {$freeAt}).",
                        'PHYSICAL_ROOM_CONFLICT',
                        409
                    );
                }
            }

            $bStmt = $this->pdo->prepare(
                'SELECT COUNT(*) FROM `offline_room_blocks`
                 WHERE `physical_room_id` = :pr_id
                   AND `start_at` < DATE_ADD(:end_at, INTERVAL 30 MINUTE)
                   AND `end_at` > DATE_SUB(:start_at, INTERVAL 30 MINUTE)'
            );
            $bStmt->execute([
                ':pr_id' => $physicalRoomId,
                ':start_at' => $effStart,
                ':end_at' => $effEnd,
            ]);
            if ((int) $bStmt->fetchColumn() > 0) {
                return Response::fail('Phòng vật lý đã bị khóa thủ công trong khung giờ này', 'OFFLINE_BLOCK_CONFLICT', 409);
            }
        }

        if ($venueId !== null) {
            $upRoomVenue = $this->pdo->prepare(
                'UPDATE `game_rooms`
                 SET `target_venue_id` = :venue_id,
                     `updated_at` = NOW()
                 WHERE `id` = :room_id'
            );
            $upRoomVenue->bindValue(':venue_id', $venueId, PDO::PARAM_INT);
            $upRoomVenue->bindValue(':room_id', $roomId, PDO::PARAM_INT);
            $upRoomVenue->execute();
        }

        // Tìm phân công DM đang hoạt động để gắn vào session (hoặc thông báo cho DM)
        $activeDaStmt = $this->pdo->prepare(
            "SELECT `id`, `dm_user_id`, `status` FROM `dm_assignments`
             WHERE `game_room_id` = :rid AND `status` IN ('PENDING', 'ACCEPTED')
             ORDER BY `id` DESC LIMIT 1"
        );
        $activeDaStmt->execute([':rid' => $roomId]);
        $activeDa = $activeDaStmt->fetch(PDO::FETCH_ASSOC);

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
        } elseif ($physicalRoomId !== null) {
            // Chưa có dòng sessions -> cần có DM để tạo sessions SCHEDULED lưu thật physical_room_id vào MySQL
            if ($activeDa === false) {
                return Response::fail(
                    'Vui lòng gán Quản trò (DM) cho phòng trước (hoặc chọn cả DM và Phòng vật lý rồi bấm Lưu điều phối).',
                    'DM_REQUIRED_BEFORE_PHYSICAL_ROOM',
                    422
                );
            }

            // Đảm bảo có schedule_proposal_id hợp lệ (trạng thái CONFIRMED để không đụng active_game_room_id)
            if ($propRow !== false) {
                $propId = (int) $propRow['id'];
                if (strtoupper((string) ($propRow['status'] ?? '')) !== 'CONFIRMED') {
                    $this->pdo->prepare(
                        "UPDATE `schedule_proposals` SET `status` = 'CONFIRMED', `confirmed_at` = NOW() WHERE `id` = :pid"
                    )->execute([':pid' => $propId]);
                }
            } else {
                $insProp = $this->pdo->prepare(
                    "INSERT INTO `schedule_proposals` (`game_room_id`, `proposed_by`, `proposed_start_at`, `proposed_end_at`, `status`, `expires_at`, `confirmed_at`, `created_at`)
                     VALUES (:rid, :by_uid, :st, :en, 'CONFIRMED', DATE_ADD(NOW(), INTERVAL 24 HOUR), NOW(), NOW())"
                );
                $insProp->execute([
                    ':rid' => $roomId,
                    ':by_uid' => (int) ($room['creator_id'] ?? $auth['id']),
                    ':st' => $effStart,
                    ':en' => $effEnd,
                ]);
                $propId = (int) $this->pdo->lastInsertId();
            }

            $insSess = $this->pdo->prepare(
                "INSERT INTO `sessions` (`game_room_id`, `schedule_proposal_id`, `dm_assignment_id`, `venue_id`, `physical_room_id`, `start_at`, `end_at`, `status`, `created_at`, `updated_at`)
                 VALUES (:rid, :pid, :daid, :vid, :prid, :st, :en, 'SCHEDULED', NOW(), NOW())"
            );
            $insSess->execute([
                ':rid' => $roomId,
                ':pid' => $propId,
                ':daid' => (int) $activeDa['id'],
                ':vid' => (int) $venueId,
                ':prid' => $physicalRoomId,
                ':st' => $effStart,
                ':en' => $effEnd,
            ]);
            $sessionId = (int) $this->pdo->lastInsertId();
        }

        // Gửi thông báo cho DM đang phụ trách phòng biết phòng vật lý đã được sắp xếp/cập nhật
        if ($activeDa !== false && !empty($activeDa['dm_user_id']) && $pRoom !== null) {
            $notiRepo = new \App\Features\Notification\Repositories\NotificationRepository($this->pdo);
            $notiRepo->createNotification(
                'Đã sắp xếp phòng vật lý cho ca trực',
                "Quản trị viên đã gán phòng vật lý '{$pRoom['name']}' cho phòng chơi #{$roomId} (khung giờ {$effStart}).",
                'DM_ASSIGNMENT',
                (int) $auth['id'],
                [(int) $activeDa['dm_user_id']],
                'ROOM',
                $roomId
            );
        }

        $this->auditService->log(
            (int) $auth['id'],
            'ASSIGN_VENUE',
            'GAME_ROOM',
            $roomId,
            [
                'venue_id' => $venueId,
                'physical_room_id' => $physicalRoomId,
                'session_id' => $sessionId !== false ? $sessionId : null,
            ]
        );

        return Response::success([
            'game_room_id' => $roomId,
            'venue_id' => $venueId,
            'physical_room_id' => $physicalRoomId,
            'physical_room_name' => $pRoom['name'] ?? null,
            'session_id' => $sessionId !== false ? $sessionId : null,
        ], 'Gán phòng vật lý và đồng bộ lịch chơi thành công');
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
        $userStmt = $this->pdo->prepare(
            'SELECT u.`id`, u.`student_verified`, r.`code` AS `role_code`
             FROM `users` u
             INNER JOIN `roles` r ON r.`id` = u.`role_id`
             WHERE u.`id` = :id LIMIT 1'
        );
        $userStmt->bindValue(':id', $userId, PDO::PARAM_INT);
        $userStmt->execute();
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);

        if ($user === false) {
            return Response::fail('Không tìm thấy người dùng yêu cầu', 'USER_NOT_FOUND', 404);
        }

        if (strtoupper((string) ($user['role_code'] ?? 'PLAYER')) !== 'PLAYER') {
            return Response::fail('Chỉ xét duyệt thẻ HSSV cho tài khoản Người chơi (Player), không áp dụng cho DM/Admin', 'INVALID_TARGET_ROLE', 422);
        }

        $body = $request->getBodyParams();
        $studentVerified = filter_var($body['student_verified'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $rejectReason = isset($body['reason']) ? trim((string) $body['reason']) : null;

        $upStmt = $this->pdo->prepare(
            'UPDATE `users`
             SET `student_verified` = :verified,
                 `student_card_status` = :card_status,
                 `student_verified_at` = IF(:verified_at_flag = 1, NOW(), NULL),
                 `student_reject_reason` = :reject_reason,
                 `updated_at` = NOW()
             WHERE `id` = :id'
        );
        $upStmt->bindValue(':verified', $studentVerified ? 1 : 0, PDO::PARAM_INT);
        $upStmt->bindValue(':card_status', $studentVerified ? 'APPROVED' : 'REJECTED', PDO::PARAM_STR);
        $upStmt->bindValue(':verified_at_flag', $studentVerified ? 1 : 0, PDO::PARAM_INT);
        $upStmt->bindValue(':reject_reason', $studentVerified ? null : $rejectReason, $studentVerified || $rejectReason === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
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
        $rawReason = trim((string) ($body['reason'] ?? ''));

        if ($physicalRoomId <= 0 || $startAt === '' || $endAt === '') {
            return Response::fail('Vui lòng điền đầy đủ thông tin: phòng vật lý, giờ bắt đầu và giờ kết thúc', 'VALIDATION_ERROR', 422);
        }

        $startTs = strtotime($startAt);
        $endTs = strtotime($endAt);
        if ($startTs === false || $endTs === false || $startTs >= $endTs) {
            return Response::fail('Thời gian bắt đầu phải hợp lệ và trước thời gian kết thúc', 'VALIDATION_ERROR', 422);
        }

        // Kiểm tra phòng vật lý tồn tại, active và không bảo trì (tự suy ra venue_id nếu không truyền)
        if ($venueId > 0) {
            $prStmt = $this->pdo->prepare('SELECT `id`, `venue_id`, `name`, `status`, `is_maintenance` FROM `physical_rooms` WHERE `id` = :id AND `venue_id` = :vid LIMIT 1');
            $prStmt->bindValue(':id', $physicalRoomId, PDO::PARAM_INT);
            $prStmt->bindValue(':vid', $venueId, PDO::PARAM_INT);
        } else {
            $prStmt = $this->pdo->prepare('SELECT `id`, `venue_id`, `name`, `status`, `is_maintenance` FROM `physical_rooms` WHERE `id` = :id LIMIT 1');
            $prStmt->bindValue(':id', $physicalRoomId, PDO::PARAM_INT);
        }
        $prStmt->execute();
        $pr = $prStmt->fetch(PDO::FETCH_ASSOC);

        if ($pr === false) {
            return Response::fail('Phòng vật lý không tồn tại hoặc không thuộc chi nhánh đã chọn', 'PHYSICAL_ROOM_NOT_FOUND', 404);
        }
        $venueId = (int) $pr['venue_id'];
        if (strtoupper((string) ($pr['status'] ?? '')) !== 'ACTIVE' || !empty($pr['is_maintenance'])) {
            return Response::fail('Phòng vật lý đang bảo trì hoặc không khả dụng', 'PHYSICAL_ROOM_UNAVAILABLE', 422);
        }

        // Kiểm tra xung đột với Session đã có (kèm khoảng đệm 30 phút dọn phòng)
        $conflictStmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM `sessions`
             WHERE `physical_room_id` = :pr_id
               AND `status` IN (\'SCHEDULED\', \'IN_PROGRESS\')
               AND `start_at` < DATE_ADD(:end_at, INTERVAL 30 MINUTE)
               AND `end_at` > DATE_SUB(:start_at, INTERVAL 30 MINUTE)'
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

        if ($customerName !== '') {
            $reasonText = $customerName . ($customerPhone !== '' ? " ({$customerPhone})" : '') . ($notes !== null && $notes !== '' ? " - {$notes}" : '');
        } else {
            $reasonText = $rawReason !== '' ? $rawReason : ($notes ?: 'Khóa giữ chỗ khách Hotline/Walk-in');
            $customerName = 'Khách Hotline/Walk-in';
        }
        $reasonText = mb_substr($reasonText, 0, 250);

        $insStmt = $this->pdo->prepare(
            'INSERT INTO `offline_room_blocks` (`physical_room_id`, `blocked_by`, `start_at`, `end_at`, `reason`, `created_at`)
             VALUES (:prid, :blocked_by, :start, :end, :reason, NOW())'
        );
        $insStmt->bindValue(':prid', $physicalRoomId, PDO::PARAM_INT);
        $insStmt->bindValue(':blocked_by', $adminId, PDO::PARAM_INT);
        $insStmt->bindValue(':start', $startAt, PDO::PARAM_STR);
        $insStmt->bindValue(':end', $endAt, PDO::PARAM_STR);
        $insStmt->bindValue(':reason', $reasonText, PDO::PARAM_STR);
        $insStmt->execute();

        $blockId = (int) $this->pdo->lastInsertId();

        $this->auditService->log(
            actorId: $adminId,
            action: 'OFFLINE_ROOM_BLOCK_CREATED',
            entityType: 'physical_room',
            entityId: $physicalRoomId,
            metadata: [
                'block_id' => $blockId,
                'venue_id' => $venueId,
                'customer_name' => $customerName,
                'customer_phone' => $customerPhone,
                'reason' => $reasonText,
                'start_at' => $startAt,
                'end_at' => $endAt,
            ]
        );

        return Response::success([
            'block_id' => $blockId,
            'venue_id' => $venueId,
            'physical_room_id' => $physicalRoomId,
            'physical_room_name' => $pr['name'],
            'start_at' => $startAt,
            'end_at' => $endAt,
            'reason' => $reasonText,
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
     * GET /api/v1/admin/dispatch-overview
     * Tổng quan điều phối: trả về danh sách phòng chơi kèm DM đang rảnh/bận và Phòng vật lý đang trống/bận theo khung giờ của từng phòng.
     */
    public function dispatchOverview(Request $request): Response
    {
        $auth = AuthMiddleware::authenticate($request, ['ADMIN']);
        if ($auth instanceof Response) {
            return $auth;
        }

        $limit = max(1, min(50, (int) $request->query('per_page', 25)));

        // 1. Tải danh sách phòng chơi gần nhất
        $roomsStmt = $this->pdo->prepare(
            'SELECT gr.`id`, gr.`title`, gr.`status`, gr.`booking_type`, gr.`min_players`, gr.`max_players`,
                    gr.`total_players`, gr.`creator_id`, gr.`guest_slots`, gr.`target_venue_id`, gr.`target_time_slot`,
                    gr.`price_details`, gr.`created_at`,
                    g.`name` AS `game_name`, COALESCE(g.`duration_minutes`, 210) AS `duration_minutes`,
                    v_target.`name` AS `target_venue_name`
             FROM `game_rooms` gr
             INNER JOIN `games` g ON g.`id` = gr.`game_id`
             LEFT JOIN `venues` v_target ON v_target.`id` = gr.`target_venue_id`
             ORDER BY gr.`id` DESC
             LIMIT :lim'
        );
        $roomsStmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $roomsStmt->execute();
        $rooms = $roomsStmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. Tải danh sách DM đang hoạt động
        $dmsStmt = $this->pdo->query(
            'SELECT u.`id`, u.`full_name`, u.`email`, u.`phone`
             FROM `users` u
             INNER JOIN `roles` r ON r.`id` = u.`role_id`
             WHERE r.`code` = \'DM\' AND u.`status` = \'ACTIVE\'
             ORDER BY u.`id` ASC'
        );
        $dms = $dmsStmt->fetchAll(PDO::FETCH_ASSOC);

        // 3. Tải danh sách phòng vật lý kèm chi nhánh
        $prStmt = $this->pdo->query(
            'SELECT pr.`id`, pr.`venue_id`, v.`name` AS `venue_name`, pr.`name`, pr.`capacity`,
                    pr.`status`, COALESCE(pr.`is_maintenance`, 0) AS `is_maintenance`
             FROM `physical_rooms` pr
             INNER JOIN `venues` v ON v.`id` = pr.`venue_id`
             ORDER BY pr.`venue_id` ASC, pr.`id` ASC'
        );
        $physicalRooms = $prStmt->fetchAll(PDO::FETCH_ASSOC);

        // 4. Tải toàn bộ phiên đang SCHEDULED / IN_PROGRESS kèm started_at và thời lượng thực tế của kịch bản
        $activeSessions = $this->pdo->query(
            'SELECT s.`id`, s.`game_room_id`, s.`physical_room_id`, s.`venue_id`, s.`start_at`, s.`end_at`, s.`started_at`, s.`status`,
                    s.`dm_assignment_id`, dma.`dm_user_id`, dma.`status` AS `dm_assignment_status`,
                    gr.`title` AS `room_title`, COALESCE(g.`duration_minutes`, 210) AS `duration_minutes`,
                    pr.`name` AS `physical_room_name`, v.`name` AS `venue_name`,
                    u.`full_name` AS `dm_name`
             FROM `sessions` s
             INNER JOIN `game_rooms` gr ON gr.`id` = s.`game_room_id`
             INNER JOIN `games` g ON g.`id` = gr.`game_id`
             LEFT JOIN `physical_rooms` pr ON pr.`id` = s.`physical_room_id`
             LEFT JOIN `venues` v ON v.`id` = s.`venue_id`
             LEFT JOIN `dm_assignments` dma ON dma.`id` = s.`dm_assignment_id`
             LEFT JOIN `users` u ON u.`id` = dma.`dm_user_id`
             WHERE s.`status` IN (\'SCHEDULED\', \'IN_PROGRESS\')
             ORDER BY s.`id` DESC'
        )->fetchAll(PDO::FETCH_ASSOC);

        // 5. Tải các lịch khóa phòng vật lý thủ công (Hotline/Walk-in)
        $offlineBlocks = $this->pdo->query(
            'SELECT `id`, `physical_room_id`, `start_at`, `end_at`, `reason`
             FROM `offline_room_blocks`
             WHERE `end_at` > DATE_SUB(NOW(), INTERVAL 1 DAY)'
        )->fetchAll(PDO::FETCH_ASSOC);

        // 6. Tải các phân công DM mới nhất cho từng phòng chơi
        $allAssignments = $this->pdo->query(
            'SELECT a.`id`, a.`game_room_id`, a.`dm_user_id`, a.`status`, a.`reason`,
                    u.`full_name` AS `dm_name`,
                    gr.`status` AS `room_status`, gr.`target_time_slot`, gr.`title` AS `room_title`,
                    COALESCE(g.`duration_minutes`, 210) AS `duration_minutes`
             FROM `dm_assignments` a
             INNER JOIN `users` u ON u.`id` = a.`dm_user_id`
             INNER JOIN `game_rooms` gr ON gr.`id` = a.`game_room_id`
             INNER JOIN `games` g ON g.`id` = gr.`game_id`
             ORDER BY a.`id` DESC'
        )->fetchAll(PDO::FETCH_ASSOC);

        $latestAssignmentByRoom = [];
        $activeDmBookings = [];
        foreach ($allAssignments as $da) {
            $rid = (int) $da['game_room_id'];
            if (!isset($latestAssignmentByRoom[$rid])) {
                $latestAssignmentByRoom[$rid] = $da;
            }
            if (
                in_array(strtoupper((string) $da['status']), ['PENDING', 'ACCEPTED'], true)
                && !in_array(strtoupper((string) $da['room_status']), ['CANCELLED', 'COMPLETED'], true)
            ) {
                $activeDmBookings[] = $da;
            }
        }

        // 7. Tải phiên mới nhất và đề xuất lịch mới nhất cho các phòng trong danh sách
        $sessionByRoom = [];
        foreach ($activeSessions as $as) {
            $rid = (int) $as['game_room_id'];
            if (!isset($sessionByRoom[$rid])) {
                $sessionByRoom[$rid] = $as;
            }
        }

        $roomIds = array_map(static fn (array $r): int => (int) $r['id'], $rooms);
        $partsByRoom = [];
        $proposalByRoom = [];
        if (!empty($roomIds)) {
            $inSql = implode(',', array_fill(0, count($roomIds), '?'));
            $pStmt = $this->pdo->prepare(
                "SELECT `game_room_id`, `user_id`
                 FROM `room_participants`
                 WHERE `game_room_id` IN ({$inSql})
                   AND `status` IN ('PENDING_DEPOSIT', 'DEPOSITED', 'LEAVE_REQUESTED')"
            );
            foreach ($roomIds as $i => $rid) {
                $pStmt->bindValue($i + 1, $rid, PDO::PARAM_INT);
            }
            $pStmt->execute();
            foreach ($pStmt->fetchAll(PDO::FETCH_ASSOC) as $prRow) {
                $partsByRoom[(int) $prRow['game_room_id']][] = (int) $prRow['user_id'];
            }

            $propStmt = $this->pdo->prepare(
                "SELECT `game_room_id`, `proposed_start_at`, `proposed_end_at`
                 FROM `schedule_proposals`
                 WHERE `game_room_id` IN ({$inSql})
                 ORDER BY `id` DESC"
            );
            foreach ($roomIds as $i => $rid) {
                $propStmt->bindValue($i + 1, $rid, PDO::PARAM_INT);
            }
            $propStmt->execute();
            foreach ($propStmt->fetchAll(PDO::FETCH_ASSOC) as $prp) {
                $rid = (int) $prp['game_room_id'];
                if (!isset($proposalByRoom[$rid])) {
                    $proposalByRoom[$rid] = $prp;
                }
            }
        }

        $parseWindow = static function (?string $startRaw, ?string $endRaw, ?string $targetSlot, int $durationMins): array {
            $startTs = null;
            $endTs = null;
            if ($startRaw !== null && trim($startRaw) !== '') {
                $t = strtotime($startRaw);
                if ($t !== false) {
                    $startTs = $t;
                }
            }
            if ($startTs === null && $targetSlot !== null && trim($targetSlot) !== '') {
                $t = strtotime(trim($targetSlot));
                if ($t !== false) {
                    $startTs = $t;
                }
            }
            if ($endRaw !== null && trim($endRaw) !== '') {
                $t = strtotime($endRaw);
                if ($t !== false) {
                    $endTs = $t;
                }
            }
            if ($startTs !== null && ($endTs === null || $endTs <= $startTs)) {
                $endTs = $startTs + max(60, $durationMins) * 60;
            }
            return [$startTs, $endTs];
        };

        $overlaps = static function (?int $aStart, ?int $aEnd, ?int $bStart, ?int $bEnd, int $bufferSeconds = 1800): bool {
            if ($aStart === null || $aEnd === null || $bStart === null || $bEnd === null) {
                return false;
            }
            return ($aStart < ($bEnd + $bufferSeconds)) && ($aEnd > ($bStart - $bufferSeconds));
        };

        // Chuẩn hóa khung giờ bận thực tế theo thời lượng từng kịch bản (Phương án 1: IN_PROGRESS tính từ started_at + duration_minutes)
        $nowTs = time();
        $dmBusySlots = [];
        $prBusySlots = [];
        foreach ($activeSessions as $as) {
            $isRunning = strtoupper((string) ($as['status'] ?? '')) === 'IN_PROGRESS';
            $asDur = max(60, (int) ($as['duration_minutes'] ?? 210));
            $st = null;
            if ($isRunning && !empty($as['started_at'])) {
                $t = strtotime((string) $as['started_at']);
                if ($t !== false) {
                    $st = $t;
                }
            }
            if ($st === null && !empty($as['start_at'])) {
                $t = strtotime((string) $as['start_at']);
                if ($t !== false) {
                    $st = $t;
                }
            }
            if ($st === null && $isRunning) {
                $st = $nowTs;
            }
            if ($st === null) {
                continue;
            }
            $en = $st + $asDur * 60;
            if ($isRunning) {
                $en = max($en, $nowTs + 60);
            }
            $spanStr = date('H:i', $st) . '–' . date('H:i', $en);
            $dayKey = date('Y-m-d', $st);
            $slotLabel = $isRunning
                ? 'Phòng #' . (int) $as['game_room_id'] . ' (Đang dẫn đến ' . date('H:i', $en) . ')'
                : 'Phòng #' . (int) $as['game_room_id'] . ' (' . date('d/m', $st) . ' ' . $spanStr . ')';

            $dmUid = (int) ($as['dm_user_id'] ?? 0);
            if ($dmUid > 0 && in_array(strtoupper((string) ($as['dm_assignment_status'] ?? 'ACCEPTED')), ['PENDING', 'ACCEPTED'], true)) {
                $dmBusySlots[$dmUid][] = [
                    'room_id' => (int) $as['game_room_id'],
                    'start' => $st,
                    'end' => $en,
                    'day' => $dayKey,
                    'in_progress' => $isRunning,
                    'span' => $spanStr,
                    'label' => $slotLabel,
                ];
            }

            $prId = (int) ($as['physical_room_id'] ?? 0);
            if ($prId > 0) {
                $prBusySlots[$prId][] = [
                    'room_id' => (int) $as['game_room_id'],
                    'start' => $st,
                    'end' => $en,
                    'day' => $dayKey,
                    'in_progress' => $isRunning,
                    'span' => $spanStr,
                    'label' => $isRunning
                        ? 'Phòng #' . (int) $as['game_room_id'] . ' (Đang diễn ra đến ' . date('H:i', $en) . ')'
                        : 'Phòng #' . (int) $as['game_room_id'] . ' (' . date('d/m', $st) . ' ' . $spanStr . ')',
                ];
            }
        }
        foreach ($activeDmBookings as $da) {
            $dmUid = (int) $da['dm_user_id'];
            $rid = (int) $da['game_room_id'];
            $isRunning = strtoupper((string) ($da['room_status'] ?? '')) === 'IN_PROGRESS';
            $already = false;
            foreach ($dmBusySlots[$dmUid] ?? [] as $existing) {
                if ($existing['room_id'] === $rid) {
                    $already = true;
                    break;
                }
            }
            if ($already) {
                continue;
            }
            $daDur = max(60, (int) ($da['duration_minutes'] ?? 210));
            [$st, $en] = $parseWindow(null, null, $da['target_time_slot'] ?? null, $daDur);
            if ($st === null && $isRunning) {
                $st = $nowTs;
                $en = $st + $daDur * 60;
            }
            if ($st !== null && $en !== null) {
                if ($isRunning) {
                    $en = max($en, $nowTs + 60);
                }
                $spanStr = date('H:i', $st) . '–' . date('H:i', $en);
                $dmBusySlots[$dmUid][] = [
                    'room_id' => $rid,
                    'start' => $st,
                    'end' => $en,
                    'day' => date('Y-m-d', $st),
                    'in_progress' => $isRunning,
                    'span' => $spanStr,
                    'label' => $isRunning
                        ? 'Phòng #' . $rid . ' (Đang dẫn đến ' . date('H:i', $en) . ')'
                        : 'Phòng #' . $rid . ' (' . date('d/m', $st) . ' ' . $spanStr . ')',
                ];
            }
        }

        // Xây dựng danh sách chi tiết cho từng phòng
        $resultRooms = [];
        foreach ($rooms as $r) {
            $rid = (int) $r['id'];
            $sess = $sessionByRoom[$rid] ?? null;
            $latestDa = $latestAssignmentByRoom[$rid] ?? null;
            $prop = $proposalByRoom[$rid] ?? null;
            $dur = max(60, (int) ($r['duration_minutes'] ?? 210));

            [$slotStart, $slotEnd] = $parseWindow(
                $sess['start_at'] ?? ($prop['proposed_start_at'] ?? null),
                null,
                $r['target_time_slot'] ?? null,
                $dur
            );
            $roomDayKey = $slotStart !== null ? date('Y-m-d', $slotStart) : null;
            $durHoursText = ($dur % 60 === 0)
                ? ((int) ($dur / 60) . ' giờ')
                : (str_replace('.', ',', (string) round($dur / 60, 1)) . ' giờ');
            $slotDisplay = ($slotStart !== null && $slotEnd !== null)
                ? date('d/m/Y H:i', $slotStart) . ' – ' . date('H:i', $slotEnd) . " ({$durHoursText})"
                : ($r['target_time_slot'] ?: 'Chưa ấn định');

            $activeUids = $partsByRoom[$rid] ?? [];
            $seatCalc = \App\Shared\Services\RoomSeatHelper::computeSeats($r, $activeUids);
            $occupiedSeats = $seatCalc['occupied_seats'];
            $requiredCap = max($occupiedSeats, (int) ($r['min_players'] ?? 1));

            $isClosed = in_array(strtoupper((string) $r['status']), ['CANCELLED', 'COMPLETED'], true);
            $currentDmId = $latestDa !== null ? (int) $latestDa['dm_user_id'] : null;
            $currentPrId = $sess !== null && !empty($sess['physical_room_id']) ? (int) $sess['physical_room_id'] : null;

            $dmOptions = [];
            $prOptions = [];
            if (!$isClosed) {
                foreach ($dms as $d) {
                    $did = (int) $d['id'];
                    $busyReason = null;
                    $sameDayShifts = 0;
                    foreach ($dmBusySlots[$did] ?? [] as $slot) {
                        if ($slot['room_id'] === $rid) {
                            continue;
                        }
                        if ($roomDayKey !== null && ($slot['day'] ?? '') === $roomDayKey) {
                            $sameDayShifts++;
                        }
                        if ($overlaps($slotStart, $slotEnd, $slot['start'], $slot['end'], 1800)) {
                            $busyReason = !empty($slot['in_progress'])
                                ? 'Đang dẫn Phòng #' . $slot['room_id'] . ' đến ' . date('H:i', (int) $slot['end']) . ' (+30p nghỉ)'
                                : 'Bận Phòng #' . $slot['room_id'] . ': ' . ($slot['span'] ?? '') . ' (+30p nghỉ)';
                            break;
                        }
                    }
                    $dmOptions[] = [
                        'id' => $did,
                        'full_name' => $d['full_name'],
                        'email' => $d['email'],
                        'available' => $busyReason === null,
                        'busy_reason' => $busyReason,
                        'same_day_shifts' => $sameDayShifts,
                        'is_current' => $currentDmId === $did,
                    ];
                }
                // Tự động xếp Quản trò rảnh & ít ca nhất trong ngày lên đầu
                usort($dmOptions, static function (array $a, array $b): int {
                    if ($a['available'] !== $b['available']) {
                        return $a['available'] ? -1 : 1;
                    }
                    if ($a['same_day_shifts'] !== $b['same_day_shifts']) {
                        return $a['same_day_shifts'] <=> $b['same_day_shifts'];
                    }
                    return $a['id'] <=> $b['id'];
                });

                foreach ($physicalRooms as $pr) {
                    $prId = (int) $pr['id'];
                    $isMaint = (int) $pr['is_maintenance'] === 1 || strtoupper((string) $pr['status']) !== 'ACTIVE';
                    $busyReason = null;

                    if ($isMaint) {
                        $busyReason = 'Đang bảo trì';
                    } elseif ((int) $pr['capacity'] < $requiredCap) {
                        $busyReason = 'Thiếu sức chứa (' . (int) $pr['capacity'] . ' < ' . $requiredCap . ')';
                    } else {
                        foreach ($prBusySlots[$prId] ?? [] as $pslot) {
                            if ($pslot['room_id'] === $rid) {
                                continue;
                            }
                            if ($overlaps($slotStart, $slotEnd, $pslot['start'], $pslot['end'], 1800)) {
                                $busyReason = !empty($pslot['in_progress'])
                                    ? 'Đang diễn ra Phòng #' . $pslot['room_id'] . ' đến ' . date('H:i', (int) $pslot['end']) . ' (+30p dọn)'
                                    : 'Bận Phòng #' . $pslot['room_id'] . ': ' . ($pslot['span'] ?? '') . ' (+30p dọn)';
                                break;
                            }
                        }
                        if ($busyReason === null && $slotStart !== null && $slotEnd !== null) {
                            foreach ($offlineBlocks as $ob) {
                                if ((int) $ob['physical_room_id'] !== $prId) {
                                    continue;
                                }
                                $obStart = strtotime((string) $ob['start_at']);
                                $obEnd = strtotime((string) $ob['end_at']);
                                if ($obStart !== false && $obEnd !== false && $overlaps($slotStart, $slotEnd, $obStart, $obEnd, 0)) {
                                    $busyReason = 'Đã khóa trực tiếp (' . date('d/m H:i', $obStart) . ')';
                                    break;
                                }
                            }
                        }
                    }

                    $prOptions[] = [
                        'id' => $prId,
                        'name' => $pr['name'],
                        'venue_id' => (int) $pr['venue_id'],
                        'venue_name' => $pr['venue_name'],
                        'capacity' => (int) $pr['capacity'],
                        'is_maintenance' => $isMaint,
                        'available' => $busyReason === null,
                        'busy_reason' => $busyReason,
                        'is_current' => $currentPrId === $prId,
                    ];
                }
                // Tự động xếp Phòng trống & sức chứa phù hợp nhất lên đầu
                usort($prOptions, static function (array $a, array $b): int {
                    if ($a['available'] !== $b['available']) {
                        return $a['available'] ? -1 : 1;
                    }
                    if ($a['capacity'] !== $b['capacity']) {
                        return $a['capacity'] <=> $b['capacity'];
                    }
                    return $a['id'] <=> $b['id'];
                });
            }

            $minP = max(1, (int) ($r['min_players'] ?? 1));
            $hasEnoughPlayers = $occupiedSeats >= $minP;

            $resultRooms[] = [
                'id' => $rid,
                'title' => $r['title'],
                'game_name' => $r['game_name'],
                'duration_minutes' => $dur,
                'status' => $r['status'],
                'current_players' => $occupiedSeats,
                'min_players' => $minP,
                'max_players' => (int) $r['max_players'],
                'has_enough_players' => $hasEnoughPlayers,
                'target_time_slot' => $r['target_time_slot'],
                'slot_start_at' => $slotStart !== null ? date('Y-m-d H:i:s', $slotStart) : null,
                'slot_end_at' => $slotEnd !== null ? date('Y-m-d H:i:s', $slotEnd) : null,
                'slot_display' => $slotDisplay,
                'current_dm' => $latestDa !== null ? [
                    'assignment_id' => (int) $latestDa['id'],
                    'dm_user_id' => (int) $latestDa['dm_user_id'],
                    'dm_name' => $latestDa['dm_name'],
                    'status' => $latestDa['status'],
                    'reason' => $latestDa['reason'],
                ] : null,
                'current_physical_room' => $currentPrId !== null ? [
                    'id' => $currentPrId,
                    'name' => $sess['physical_room_name'] ?? ('Phòng #' . $currentPrId),
                    'venue_name' => $sess['venue_name'] ?? '',
                    'session_id' => (int) $sess['id'],
                    'session_status' => $sess['status'],
                ] : null,
                'dm_options' => $dmOptions,
                'physical_room_options' => $prOptions,
            ];
        }

        // Thống kê nhanh trạng thái hiện tại của tất cả Quản trò và Phòng vật lý
        $dmSummary = array_map(function (array $d) use ($dmBusySlots, $nowTs): array {
            $did = (int) $d['id'];
            $activeNow = null;
            $upcomingCount = 0;
            foreach ($dmBusySlots[$did] ?? [] as $sl) {
                if (!empty($sl['in_progress'])) {
                    $activeNow = 'Phòng #' . $sl['room_id'] . ' — Đang dẫn (xong ' . date('H:i', (int) $sl['end']) . ')';
                } elseif ($activeNow === null && $sl['start'] !== null && $sl['end'] !== null && $sl['start'] <= $nowTs && $sl['end'] >= $nowTs) {
                    $activeNow = $sl['label'];
                }
                if (empty($sl['in_progress']) && $sl['end'] !== null && $sl['end'] >= $nowTs) {
                    $upcomingCount++;
                }
            }
            return [
                'id' => $did,
                'full_name' => $d['full_name'],
                'email' => $d['email'],
                'is_busy_now' => $activeNow !== null,
                'current_shift' => $activeNow,
                'upcoming_shifts' => $upcomingCount,
            ];
        }, $dms);

        $prSummary = array_map(function (array $pr) use ($prBusySlots, $offlineBlocks, $nowTs): array {
            $prId = (int) $pr['id'];
            $isMaint = (int) $pr['is_maintenance'] === 1 || strtoupper((string) $pr['status']) !== 'ACTIVE';
            $busyNow = null;
            $upcomingCount = 0;
            foreach ($prBusySlots[$prId] ?? [] as $sl) {
                if (!empty($sl['in_progress'])) {
                    $busyNow = 'Phòng #' . $sl['room_id'] . ' — Đang diễn ra (xong ' . date('H:i', (int) $sl['end']) . ')';
                } elseif ($busyNow === null && $sl['start'] <= $nowTs && $sl['end'] >= $nowTs) {
                    $busyNow = $sl['label'];
                }
                if (empty($sl['in_progress']) && $sl['end'] >= $nowTs) {
                    $upcomingCount++;
                }
            }
            if ($busyNow === null) {
                foreach ($offlineBlocks as $ob) {
                    if ((int) $ob['physical_room_id'] !== $prId) {
                        continue;
                    }
                    $st = strtotime((string) $ob['start_at']);
                    $en = strtotime((string) $ob['end_at']);
                    if ($st !== false && $en !== false && $st <= $nowTs && $en >= $nowTs) {
                        $busyNow = 'Khóa Hotline';
                    }
                }
            }
            return [
                'id' => $prId,
                'venue_id' => (int) $pr['venue_id'],
                'venue_name' => $pr['venue_name'],
                'name' => $pr['name'],
                'capacity' => (int) $pr['capacity'],
                'is_maintenance' => $isMaint,
                'is_busy_now' => $busyNow !== null,
                'current_usage' => $busyNow,
                'upcoming_sessions' => $upcomingCount,
            ];
        }, $physicalRooms);

        return Response::success([
            'rooms' => $resultRooms,
            'dms' => $dmSummary,
            'physical_rooms' => $prSummary,
        ], 'Lấy tổng quan điều phối phòng & quản trò thành công');
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
