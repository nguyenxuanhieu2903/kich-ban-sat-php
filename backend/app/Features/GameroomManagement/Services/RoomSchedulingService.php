<?php

declare(strict_types=1);

namespace App\Features\GameroomManagement\Services;

use App\Shared\Services\AuditLogService;
use DateTimeImmutable;
use PDO;
use RuntimeException;

class RoomSchedulingService
{
    private const BUFFER_MINUTES = 30;
    private const FIXED_DEPOSIT = 50000;
    private const DEPOSIT_DEADLINE_HOURS = 2;

    protected PDO $pdo;
    protected AuditLogService $auditService;

    public function __construct(PDO $pdo, ?AuditLogService $auditService = null)
    {
        $this->pdo = $pdo;
        $this->auditService = $auditService ?? new AuditLogService($pdo);
    }

    /**
     * Thuật toán xếp lịch trực tiếp cho Admin/DM (chuẩn schema DB V16):
     * 1. Khóa phòng chơi (FOR UPDATE), kiểm tra trạng thái & số người tối thiểu.
     * 2. Kiểm tra phòng vật lý thuộc chi nhánh, ACTIVE và đủ sức chứa.
     * 3. Chống trùng lịch phòng vật lý & DM kèm khoảng đệm 30 phút.
     * 4. Tạo DM Assignment + Session (SCHEDULED) + Chuyển phòng sang SCHEDULED.
     * 5. Sinh hóa đơn cọc 50.000đ trong bảng player_payments (status PENDING, hạn 2h) + Gửi notifications.
     *
     * @return array<string, mixed>
     */
    public function scheduleRoom(
        int $roomId,
        int $actorId,
        string $actorRole,
        int $venueId,
        int $physicalRoomId,
        int $dmUserId,
        string $startAtRaw,
        ?string $endAtRaw = null,
        bool $forceUnderMin = false
    ): array {
        $startAt = $this->parseDateTime($startAtRaw, 'Thời gian bắt đầu không hợp lệ');

        $this->pdo->beginTransaction();
        try {
            // 1. Khóa bản ghi phòng chơi + cấu hình kịch bản
            $rStmt = $this->pdo->prepare(
                'SELECT gr.`id`, gr.`game_id`, gr.`title`, gr.`status`,
                        g.`min_players`, g.`max_players`, g.`duration_minutes`, g.`name` AS `game_name`
                 FROM `game_rooms` gr
                 INNER JOIN `games` g ON g.`id` = gr.`game_id`
                 WHERE gr.`id` = :rid
                 LIMIT 1 FOR UPDATE'
            );
            $rStmt->bindValue(':rid', $roomId, PDO::PARAM_INT);
            $rStmt->execute();
            $room = $rStmt->fetch(PDO::FETCH_ASSOC);

            if ($room === false) {
                throw new RuntimeException('ROOM_NOT_FOUND:Phòng chơi không tồn tại', 404);
            }

            if (!in_array((string) $room['status'], ['FORMING', 'SCHEDULED'], true)) {
                throw new RuntimeException('INVALID_ROOM_STATUS:Chỉ có thể xếp lịch cho phòng đang FORMING hoặc SCHEDULED', 409);
            }

            $duration = max(30, (int) ($room['duration_minutes'] ?? 120));
            $endAt = $endAtRaw !== null && trim($endAtRaw) !== ''
                ? $this->parseDateTime($endAtRaw, 'Thời gian kết thúc không hợp lệ')
                : $startAt->modify("+{$duration} minutes");

            if ($endAt <= $startAt) {
                throw new RuntimeException('INVALID_TIME_RANGE:Thời gian kết thúc phải sau thời gian bắt đầu', 422);
            }

            // Lấy thành viên active
            $pStmt = $this->pdo->prepare(
                'SELECT `id`, `user_id`, `status`
                 FROM `room_participants`
                 WHERE `game_room_id` = :rid
                   AND `status` IN (\'PENDING_DEPOSIT\', \'DEPOSITED\', \'PAID\')
                 FOR UPDATE'
            );
            $pStmt->bindValue(':rid', $roomId, PDO::PARAM_INT);
            $pStmt->execute();
            $participants = $pStmt->fetchAll(PDO::FETCH_ASSOC);
            $activeCount = count($participants);

            if (!$forceUnderMin && $activeCount < (int) $room['min_players']) {
                throw new RuntimeException(
                    "INSUFFICIENT_PLAYERS:Phòng hiện có {$activeCount}/{$room['min_players']} người, chưa đủ số lượng tối thiểu",
                    409
                );
            }

            // 2. Kiểm tra phòng vật lý & sức chứa (chặn phòng đang bảo trì)
            $prStmt = $this->pdo->prepare(
                'SELECT `id`, `venue_id`, `name`, `capacity`, `status`
                 FROM `physical_rooms`
                 WHERE `id` = :prid AND `venue_id` = :vid AND `status` = \'ACTIVE\'
                   AND (`is_maintenance` = 0 OR `is_maintenance` IS NULL)
                 LIMIT 1 FOR UPDATE'
            );
            $prStmt->bindValue(':prid', $physicalRoomId, PDO::PARAM_INT);
            $prStmt->bindValue(':vid', $venueId, PDO::PARAM_INT);
            $prStmt->execute();
            $physicalRoom = $prStmt->fetch(PDO::FETCH_ASSOC);

            if ($physicalRoom === false) {
                throw new RuntimeException('PHYSICAL_ROOM_NOT_FOUND:Phòng vật lý không tồn tại hoặc không thuộc chi nhánh đã chọn', 404);
            }

            if ((int) $physicalRoom['capacity'] < max(1, $activeCount)) {
                throw new RuntimeException(
                    "ROOM_CAPACITY_EXCEEDED:Phòng vật lý chỉ chứa tối đa {$physicalRoom['capacity']} người (hiện có {$activeCount} người)",
                    409
                );
            }

            // Kiểm tra tài khoản DM hợp lệ
            $dmStmt = $this->pdo->prepare(
                'SELECT u.`id`, u.`status`, r.`code` AS `role_code`
                 FROM `users` u
                 INNER JOIN `roles` r ON r.`id` = u.`role_id`
                 WHERE u.`id` = :uid LIMIT 1'
            );
            $dmStmt->bindValue(':uid', $dmUserId, PDO::PARAM_INT);
            $dmStmt->execute();
            $dmUser = $dmStmt->fetch(PDO::FETCH_ASSOC);

            if ($dmUser === false || strtoupper((string) $dmUser['status']) !== 'ACTIVE') {
                throw new RuntimeException('DM_NOT_FOUND:Tài khoản Quản trò không tồn tại hoặc đã bị khóa', 404);
            }
            if (!in_array(strtoupper((string) $dmUser['role_code']), ['DM', 'ADMIN'], true)) {
                throw new RuntimeException('INVALID_DM_ROLE:Người được chọn không có vai trò Quản trò (DM)', 422);
            }

            // 3. Thuật toán kiểm tra xung đột lịch kèm khoảng đệm 30 phút
            $startStr = $startAt->format('Y-m-d H:i:s');
            $endStr = $endAt->format('Y-m-d H:i:s');
            $bufferStart = $startAt->modify('-' . self::BUFFER_MINUTES . ' minutes')->format('Y-m-d H:i:s');
            $bufferEnd = $endAt->modify('+' . self::BUFFER_MINUTES . ' minutes')->format('Y-m-d H:i:s');

            // 3a. Trùng phòng vật lý (kèm đệm 30 phút)
            $roomConflictStmt = $this->pdo->prepare(
                'SELECT `id`
                 FROM `sessions`
                 WHERE `physical_room_id` = :prid
                   AND `game_room_id` != :rid
                   AND `status` IN (\'SCHEDULED\', \'IN_PROGRESS\')
                   AND `start_at` < :buf_end
                   AND `end_at` > :buf_start
                 LIMIT 1 FOR UPDATE'
            );
            $roomConflictStmt->bindValue(':prid', $physicalRoomId, PDO::PARAM_INT);
            $roomConflictStmt->bindValue(':rid', $roomId, PDO::PARAM_INT);
            $roomConflictStmt->bindValue(':buf_end', $bufferEnd, PDO::PARAM_STR);
            $roomConflictStmt->bindValue(':buf_start', $bufferStart, PDO::PARAM_STR);
            $roomConflictStmt->execute();
            if ($roomConflictStmt->fetch(PDO::FETCH_ASSOC) !== false) {
                throw new RuntimeException(
                    'PHYSICAL_ROOM_CONFLICT:Phòng vật lý bị trùng lịch hoặc vi phạm khoảng đệm dọn phòng 30 phút',
                    409
                );
            }

            // 3a-bis. Trùng lịch khóa giữ chỗ Hotline / Walk-in (offline_room_blocks kèm đệm 30 phút)
            $offlineConflictStmt = $this->pdo->prepare(
                'SELECT `id`
                 FROM `offline_room_blocks`
                 WHERE `physical_room_id` = :prid
                   AND `start_at` < :buf_end
                   AND `end_at` > :buf_start
                 LIMIT 1'
            );
            $offlineConflictStmt->bindValue(':prid', $physicalRoomId, PDO::PARAM_INT);
            $offlineConflictStmt->bindValue(':buf_end', $bufferEnd, PDO::PARAM_STR);
            $offlineConflictStmt->bindValue(':buf_start', $bufferStart, PDO::PARAM_STR);
            $offlineConflictStmt->execute();
            if ($offlineConflictStmt->fetch(PDO::FETCH_ASSOC) !== false) {
                throw new RuntimeException(
                    'PHYSICAL_ROOM_CONFLICT:Phòng vật lý đã được khóa giữ chỗ cho khách Hotline/Walk-in trong khung giờ này',
                    409
                );
            }

            // 3b. Trùng ca Quản trò (kèm đệm 30 phút)
            $dmConflictStmt = $this->pdo->prepare(
                'SELECT s.`id`
                 FROM `sessions` s
                 INNER JOIN `dm_assignments` da ON da.`id` = s.`dm_assignment_id`
                 WHERE da.`dm_user_id` = :dm_id
                   AND da.`status` IN (\'PENDING\', \'ACCEPTED\')
                   AND s.`game_room_id` != :rid
                   AND s.`status` IN (\'SCHEDULED\', \'IN_PROGRESS\')
                   AND s.`start_at` < :buf_end
                   AND s.`end_at` > :buf_start
                 LIMIT 1 FOR UPDATE'
            );
            $dmConflictStmt->bindValue(':dm_id', $dmUserId, PDO::PARAM_INT);
            $dmConflictStmt->bindValue(':rid', $roomId, PDO::PARAM_INT);
            $dmConflictStmt->bindValue(':buf_end', $bufferEnd, PDO::PARAM_STR);
            $dmConflictStmt->bindValue(':buf_start', $bufferStart, PDO::PARAM_STR);
            $dmConflictStmt->execute();
            if ($dmConflictStmt->fetch(PDO::FETCH_ASSOC) !== false) {
                throw new RuntimeException(
                    'DM_SCHEDULE_CONFLICT:Quản trò đã có ca trùng khung giờ hoặc vi phạm khoảng đệm nghỉ 30 phút',
                    409
                );
            }

            // 4. Thu hồi phân công DM cũ & tạo phân công mới
            $revStmt = $this->pdo->prepare(
                'UPDATE `dm_assignments`
                 SET `status` = \'REVOKED\', `reason` = \'Cập nhật lịch mới\', `revoked_at` = NOW(), `updated_at` = NOW()
                 WHERE `game_room_id` = :rid AND `status` IN (\'PENDING\', \'ACCEPTED\')'
            );
            $revStmt->bindValue(':rid', $roomId, PDO::PARAM_INT);
            $revStmt->execute();

            $dmStatus = ($dmUserId === $actorId) ? 'ACCEPTED' : 'PENDING';
            $insDm = $this->pdo->prepare(
                'INSERT INTO `dm_assignments` (`game_room_id`, `dm_user_id`, `assigned_by`, `status`, `assigned_at`, `created_at`, `updated_at`)
                 VALUES (:rid, :dm_id, :by_id, :status, NOW(), NOW(), NOW())'
            );
            $insDm->bindValue(':rid', $roomId, PDO::PARAM_INT);
            $insDm->bindValue(':dm_id', $dmUserId, PDO::PARAM_INT);
            $insDm->bindValue(':by_id', $actorId, PDO::PARAM_INT);
            $insDm->bindValue(':status', $dmStatus, PDO::PARAM_STR);
            $insDm->execute();
            $dmAssignmentId = (int) $this->pdo->lastInsertId();

            // Tạo hoặc cập nhật Session
            $existSess = $this->pdo->prepare(
                'SELECT `id` FROM `sessions` WHERE `game_room_id` = :rid AND `status` = \'SCHEDULED\' LIMIT 1'
            );
            $existSess->bindValue(':rid', $roomId, PDO::PARAM_INT);
            $existSess->execute();
            $existingSessionId = $existSess->fetchColumn();

            if ($existingSessionId !== false) {
                $sessionId = (int) $existingSessionId;
                $upSess = $this->pdo->prepare(
                    'UPDATE `sessions`
                     SET `venue_id` = :vid, `physical_room_id` = :prid, `dm_assignment_id` = :daid,
                         `start_at` = :start_at, `end_at` = :end_at, `updated_at` = NOW()
                     WHERE `id` = :sid'
                );
                $upSess->bindValue(':vid', $venueId, PDO::PARAM_INT);
                $upSess->bindValue(':prid', $physicalRoomId, PDO::PARAM_INT);
                $upSess->bindValue(':daid', $dmAssignmentId, PDO::PARAM_INT);
                $upSess->bindValue(':start_at', $startStr, PDO::PARAM_STR);
                $upSess->bindValue(':end_at', $endStr, PDO::PARAM_STR);
                $upSess->bindValue(':sid', $sessionId, PDO::PARAM_INT);
                $upSess->execute();
            } else {
                $insSess = $this->pdo->prepare(
                    'INSERT INTO `sessions` (`game_room_id`, `venue_id`, `physical_room_id`, `dm_assignment_id`, `start_at`, `end_at`, `status`, `created_at`, `updated_at`)
                     VALUES (:rid, :vid, :prid, :daid, :start_at, :end_at, \'SCHEDULED\', NOW(), NOW())'
                );
                $insSess->bindValue(':rid', $roomId, PDO::PARAM_INT);
                $insSess->bindValue(':vid', $venueId, PDO::PARAM_INT);
                $insSess->bindValue(':prid', $physicalRoomId, PDO::PARAM_INT);
                $insSess->bindValue(':daid', $dmAssignmentId, PDO::PARAM_INT);
                $insSess->bindValue(':start_at', $startStr, PDO::PARAM_STR);
                $insSess->bindValue(':end_at', $endStr, PDO::PARAM_STR);
                $insSess->execute();
                $sessionId = (int) $this->pdo->lastInsertId();
            }

            // Chuyển trạng thái phòng sang SCHEDULED
            $upRoom = $this->pdo->prepare(
                'UPDATE `game_rooms` SET `status` = \'SCHEDULED\', `updated_at` = NOW() WHERE `id` = :rid'
            );
            $upRoom->bindValue(':rid', $roomId, PDO::PARAM_INT);
            $upRoom->execute();

            // 5. Sinh hóa đơn cọc 50.000đ trong bảng player_payments (status PENDING theo check constraint)
            $depositDeadline = (new DateTimeImmutable())
                ->modify('+' . self::DEPOSIT_DEADLINE_HOURS . ' hours')
                ->format('Y-m-d H:i:s');
            $createdPayments = 0;

            foreach ($participants as $p) {
                $participantId = (int) $p['id'];
                $uid = (int) $p['user_id'];

                $chkPay = $this->pdo->prepare(
                    'SELECT `id` FROM `player_payments`
                     WHERE `room_participant_id` = :pid AND `payment_type` = \'DEPOSIT\'
                     LIMIT 1'
                );
                $chkPay->bindValue(':pid', $participantId, PDO::PARAM_INT);
                $chkPay->execute();

                if ($chkPay->fetchColumn() === false) {
                    $insPay = $this->pdo->prepare(
                        'INSERT INTO `player_payments` (`room_participant_id`, `payment_type`, `amount`, `paid_amount`, `status`, `deadline_at`, `created_at`, `updated_at`)
                         VALUES (:pid, \'DEPOSIT\', :amount, 0, \'PENDING\', :deadline, NOW(), NOW())'
                    );
                    $insPay->bindValue(':pid', $participantId, PDO::PARAM_INT);
                    $insPay->bindValue(':amount', self::FIXED_DEPOSIT, PDO::PARAM_INT);
                    $insPay->bindValue(':deadline', $depositDeadline, PDO::PARAM_STR);
                    $insPay->execute();
                    $createdPayments++;
                }

                if ((string) $p['status'] === 'PENDING_DEPOSIT') {
                    $upPart = $this->pdo->prepare(
                        'UPDATE `room_participants` SET `deposit_deadline_at` = :dl, `updated_at` = NOW() WHERE `id` = :pid'
                    );
                    $upPart->bindValue(':dl', $depositDeadline, PDO::PARAM_STR);
                    $upPart->bindValue(':pid', $participantId, PDO::PARAM_INT);
                    $upPart->execute();
                }

                // Gửi thông báo vào bảng notifications
                $this->notifyUser(
                    $uid,
                    'ROOM_SCHEDULED',
                    'Phòng chơi đã được xếp lịch',
                    "Phòng #{$roomId} ({$room['game_name']}) đã được xếp lịch lúc {$startStr}. Vui lòng hoàn tất tiền cọc 50.000đ trong vòng 2 giờ.",
                    'game_room',
                    $roomId
                );
            }

            // Ghi Audit Log
            $this->auditService->log(
                $actorId,
                'SCHEDULE_ROOM',
                'session',
                $sessionId,
                [
                    'game_room_id' => $roomId,
                    'venue_id' => $venueId,
                    'physical_room_id' => $physicalRoomId,
                    'dm_user_id' => $dmUserId,
                    'start_at' => $startStr,
                    'end_at' => $endStr,
                    'created_payments' => $createdPayments,
                ]
            );

            $this->pdo->commit();

            return [
                'session_id' => $sessionId,
                'game_room_id' => $roomId,
                'dm_assignment_id' => $dmAssignmentId,
                'venue_id' => $venueId,
                'physical_room_id' => $physicalRoomId,
                'start_at' => $startStr,
                'end_at' => $endStr,
                'created_payments' => $createdPayments,
                'deposit_amount' => self::FIXED_DEPOSIT,
                'deposit_deadline_at' => $depositDeadline,
            ];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    private function parseDateTime(string $raw, string $errorMsg): DateTimeImmutable
    {
        try {
            return new DateTimeImmutable(trim($raw));
        } catch (\Throwable) {
            throw new RuntimeException("VALIDATION_ERROR:{$errorMsg}", 422);
        }
    }

    private function notifyUser(
        int $userId,
        string $type,
        string $title,
        string $content,
        string $refType,
        int $refId
    ): void {
        $stmt = $this->pdo->prepare(
            'INSERT INTO `notifications` (`user_id`, `type`, `title`, `content`, `reference_type`, `reference_id`, `is_read`, `created_at`)
             VALUES (:uid, :type, :title, :content, :rtype, :rid, 0, NOW())'
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':type', $type, PDO::PARAM_STR);
        $stmt->bindValue(':title', $title, PDO::PARAM_STR);
        $stmt->bindValue(':content', $content, PDO::PARAM_STR);
        $stmt->bindValue(':rtype', $refType, PDO::PARAM_STR);
        $stmt->bindValue(':rid', $refId, PDO::PARAM_INT);
        $stmt->execute();
    }
}
