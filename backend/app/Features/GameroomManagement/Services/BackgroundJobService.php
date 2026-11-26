<?php

declare(strict_types=1);

namespace App\Features\GameroomManagement\Services;

use App\Features\Notification\Repositories\NotificationRepository;
use App\Infrastructure\Database\DatabaseConnection;
use PDO;

class BackgroundJobService
{
    protected PDO $pdo;
    protected NotificationRepository $notiRepo;

    public function __construct(?PDO $pdo = null, ?NotificationRepository $notiRepo = null)
    {
        $this->pdo = $pdo ?? DatabaseConnection::getInstance();
        $this->notiRepo = $notiRepo ?? new NotificationRepository($this->pdo);
    }

    /**
     * Run all background maintenance jobs.
     *
     * @return array<string, int>
     */
    public function runAll(): array
    {
        return [
            'formation_timeouts' => $this->runFormationTimeout(),
            'deposit_timeouts' => $this->runDepositTimeout(),
            'replacement_timeouts' => $this->runReplacementTimeout(),
            'proposal_timeouts' => $this->runProposalTimeout(),
            'dm_assignment_timeouts' => $this->runDmAssignmentTimeout(),
            'session_reminders' => $this->runUpcomingSession24hReminder(),
        ];
    }

    /**
     * Job 1: Quét các phòng FORMING quá hạn (formation_deadline_at < NOW())
     * và chưa đủ số người chơi tối thiểu -> Tự động CANCELLED phòng.
     */
    public function runFormationTimeout(): int
    {
        // Lấy danh sách các phòng FORMING đã quá hạn
        $sql = "SELECT gr.`id`, gr.`title`, gr.`min_players`, gr.`replacement_reason`,
                       (SELECT COUNT(*) FROM `room_participants` rp
                        WHERE rp.`game_room_id` = gr.`id`
                          AND rp.`status` IN ('PENDING_DEPOSIT', 'DEPOSITED', 'LEAVE_REQUESTED')
                       ) AS `current_players`
                FROM `game_rooms` gr
                WHERE gr.`status` = 'FORMING'
                  AND gr.`formation_deadline_at` <= NOW()";

        $stmt = $this->pdo->query($sql);
        $expiredRooms = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $cancelledCount = 0;

        foreach ($expiredRooms as $room) {
            $currentPlayers = (int) $room['current_players'];
            $minPlayers = (int) $room['min_players'];
            $roomId = (int) $room['id'];

            // Cơ chế gia hạn thông minh: Nếu phòng đã gom được >= 70% sĩ số và chưa từng gia hạn, tự động gia hạn thêm 24h
            if ($currentPlayers < $minPlayers && $currentPlayers >= (int) ceil($minPlayers * 0.7) && ($room['replacement_reason'] ?? '') !== 'AUTO_EXTENDED_24H') {
                $upExt = $this->pdo->prepare(
                    'UPDATE `game_rooms`
                     SET `formation_deadline_at` = DATE_ADD(NOW(), INTERVAL 24 HOUR),
                         `replacement_reason` = \'AUTO_EXTENDED_24H\',
                         `updated_at` = NOW()
                     WHERE `id` = :room_id'
                );
                $upExt->bindValue(':room_id', $roomId, PDO::PARAM_INT);
                $upExt->execute();
                continue;
            }

            // Nếu số người chơi hiện tại vẫn chưa đủ min_players
            if ($currentPlayers < $minPlayers) {
                $this->pdo->beginTransaction();
                try {
                    // Hủy phòng
                    $upRoom = $this->pdo->prepare(
                        'UPDATE `game_rooms`
                         SET `status` = \'CANCELLED\',
                             `cancelled_at` = NOW(),
                             `cancel_reason` = \'Hết thời hạn gom phòng nhưng chưa đủ số lượng người chơi tối thiểu\',
                             `updated_at` = NOW()
                         WHERE `id` = :room_id AND `status` = \'FORMING\''
                    );
                    $upRoom->bindValue(':room_id', $roomId, PDO::PARAM_INT);
                    $upRoom->execute();

                    // Cập nhật thành viên phòng
                    $upParts = $this->pdo->prepare(
                        'UPDATE `room_participants`
                         SET `status` = \'CANCELLED_UNPAID\',
                             `cancelled_at` = NOW(),
                             `cancel_reason` = \'Phòng chơi tự động giải tán do không gom đủ người\',
                             `updated_at` = NOW()
                         WHERE `game_room_id` = :room_id AND `status` IN (\'PENDING_DEPOSIT\', \'DEPOSITED\', \'LEAVE_REQUESTED\')'
                    );
                    $upParts->bindValue(':room_id', $roomId, PDO::PARAM_INT);
                    $upParts->execute();

                    // Lấy danh sách user_id để gửi notification
                    $uStmt = $this->pdo->prepare('SELECT `user_id` FROM `room_participants` WHERE `game_room_id` = :room_id');
                    $uStmt->bindValue(':room_id', $roomId, PDO::PARAM_INT);
                    $uStmt->execute();
                    $userIds = $uStmt->fetchAll(PDO::FETCH_COLUMN);

                    $this->pdo->commit();
                    $cancelledCount++;

                    if (!empty($userIds)) {
                        $this->notiRepo->createNotification(
                            'Phòng chơi đã tự động giải tán',
                            "Phòng '{$room['title']}' đã bị hủy do hết thời hạn gom phòng mà chưa đủ số lượng thành viên tối thiểu ({$minPlayers} người).",
                            'ROOM',
                            null,
                            array_map('intval', $userIds)
                        );
                    }
                } catch (\Throwable $e) {
                    if ($this->pdo->inTransaction()) {
                        $this->pdo->rollBack();
                    }
                }
            }
        }

        return $cancelledCount;
    }

    /**
     * Job 2: Quét các người chơi quá hạn đóng tiền cọc (deposit_deadline_at < NOW())
     * nhưng vẫn ở trạng thái PENDING_DEPOSIT -> Kick khỏi phòng và mở Replacement Window.
     */
    public function runDepositTimeout(): int
    {
        $sql = "SELECT rp.`id`, rp.`game_room_id`, rp.`user_id`, gr.`title` AS `room_title`
                FROM `room_participants` rp
                INNER JOIN `game_rooms` gr ON gr.`id` = rp.`game_room_id`
                WHERE rp.`status` = 'PENDING_DEPOSIT'
                  AND rp.`deposit_deadline_at` IS NOT NULL
                  AND rp.`deposit_deadline_at` <= NOW()";

        $stmt = $this->pdo->query($sql);
        $expiredParticipants = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $kickedCount = 0;

        foreach ($expiredParticipants as $part) {
            $partId = (int) $part['id'];
            $roomId = (int) $part['game_room_id'];
            $userId = (int) $part['user_id'];

            $this->pdo->beginTransaction();
            try {
                // Kick participant
                $upPart = $this->pdo->prepare(
                    'UPDATE `room_participants`
                     SET `status` = \'CANCELLED_UNPAID\',
                         `cancelled_at` = NOW(),
                         `cancel_reason` = \'Quá hạn nộp tiền cọc\',
                         `updated_at` = NOW()
                     WHERE `id` = :id AND `status` = \'PENDING_DEPOSIT\''
                );
                $upPart->bindValue(':id', $partId, PDO::PARAM_INT);
                $upPart->execute();

                // Nếu người bị kick là Host của phòng, chuyển quyền Host cho thành viên tiếp theo
                $hostCheckStmt = $this->pdo->prepare('SELECT `creator_id` FROM `game_rooms` WHERE `id` = :room_id LIMIT 1');
                $hostCheckStmt->bindValue(':room_id', $roomId, PDO::PARAM_INT);
                $hostCheckStmt->execute();
                $currentHostId = (int) $hostCheckStmt->fetchColumn();

                if ($currentHostId === $userId) {
                    $nextHostStmt = $this->pdo->prepare(
                        'SELECT `user_id` FROM `room_participants`
                         WHERE `game_room_id` = :room_id AND `user_id` != :uid
                           AND `status` IN (\'PENDING_DEPOSIT\', \'DEPOSITED\')
                         ORDER BY (`status` = \'DEPOSITED\') DESC, `joined_at` ASC, `id` ASC LIMIT 1'
                    );
                    $nextHostStmt->bindValue(':room_id', $roomId, PDO::PARAM_INT);
                    $nextHostStmt->bindValue(':uid', $userId, PDO::PARAM_INT);
                    $nextHostStmt->execute();
                    $nextHostId = $nextHostStmt->fetchColumn();

                    if ($nextHostId !== false) {
                        $upHost = $this->pdo->prepare('UPDATE `game_rooms` SET `creator_id` = :next_uid WHERE `id` = :room_id');
                        $upHost->bindValue(':next_uid', (int) $nextHostId, PDO::PARAM_INT);
                        $upHost->bindValue(':room_id', $roomId, PDO::PARAM_INT);
                        $upHost->execute();
                    }
                }

                // Mở Replacement Window cho phòng
                $upRoom = $this->pdo->prepare(
                    'UPDATE `game_rooms`
                     SET `replacement_status` = \'OPEN\',
                         `replacement_started_at` = NOW(),
                         `replacement_deadline_at` = DATE_ADD(NOW(), INTERVAL 24 HOUR),
                         `replacement_reason` = \'Có thành viên quá hạn đóng tiền cọc cần tìm người thay thế\',
                         `updated_at` = NOW()
                     WHERE `id` = :room_id'
                );
                $upRoom->bindValue(':room_id', $roomId, PDO::PARAM_INT);
                $upRoom->execute();

                $this->pdo->commit();
                $kickedCount++;

                // Bắn notification cho người bị kick
                $this->notiRepo->createNotification(
                    'Hủy tư cách tham gia do quá hạn cọc',
                    "Bạn đã bị hủy khỏi phòng '{$part['room_title']}' do không hoàn tất nộp tiền cọc đúng hạn.",
                    'ROOM',
                    null,
                    [$userId]
                );
            } catch (\Throwable $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
            }
        }

        return $kickedCount;
    }

    /**
     * Job 3: Quét các phòng đang mở Replacement Window (replacement_status = 'OPEN')
     * mà đã quá hạn 24 tiếng (replacement_deadline_at < NOW()) -> Đóng cửa sổ (EXPIRED).
     */
    public function runReplacementTimeout(): int
    {
        $sql = "SELECT gr.`id`, gr.`title`
                FROM `game_rooms` gr
                WHERE gr.`replacement_status` = 'OPEN'
                  AND (
                    (gr.`replacement_deadline_at` IS NOT NULL AND gr.`replacement_deadline_at` <= NOW())
                    OR EXISTS (
                      SELECT 1 FROM `sessions` s 
                      WHERE s.`game_room_id` = gr.`id` 
                        AND s.`start_at` <= DATE_ADD(NOW(), INTERVAL 4 HOUR)
                    )
                  )";

        $stmt = $this->pdo->query($sql);
        $expiredWindows = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $closedCount = 0;

        foreach ($expiredWindows as $room) {
            $roomId = (int) $room['id'];

            $this->pdo->beginTransaction();
            try {
                // Đóng replacement window
                $upRoom = $this->pdo->prepare(
                    'UPDATE `game_rooms`
                     SET `replacement_status` = \'EXPIRED\',
                         `replacement_closed_at` = NOW(),
                         `updated_at` = NOW()
                     WHERE `id` = :room_id AND `replacement_status` = \'OPEN\''
                );
                $upRoom->bindValue(':room_id', $roomId, PDO::PARAM_INT);
                $upRoom->execute();

                // Cập nhật người đang LEAVE_REQUESTED thành LEFT (bị phạt cọc không hoàn do hết hạn tìm người thay thế)
                $upParts = $this->pdo->prepare(
                    'UPDATE `room_participants`
                     SET `status` = \'LEFT\',
                         `left_at` = NOW(),
                         `cancel_reason` = \'Hết hạn 24h tìm người thay thế mà không có người nhận slot (Không hoàn cọc)\',
                         `updated_at` = NOW()
                     WHERE `game_room_id` = :room_id AND `status` = \'LEAVE_REQUESTED\''
                );
                $upParts->bindValue(':room_id', $roomId, PDO::PARAM_INT);
                $upParts->execute();

                $this->pdo->commit();
                $closedCount++;

                // Thông báo tới Host và DM về việc cửa sổ thế chỗ đã đóng
                $sessStmt = $this->pdo->prepare('SELECT s.`id`, dma.`dm_user_id`, gr.`creator_id` FROM `sessions` s INNER JOIN `game_rooms` gr ON gr.`id` = s.`game_room_id` LEFT JOIN `dm_assignments` dma ON dma.`id` = s.`dm_assignment_id` WHERE s.`game_room_id` = :room_id AND s.`status` = \'SCHEDULED\' LIMIT 1');
                $sessStmt->bindValue(':room_id', $roomId, PDO::PARAM_INT);
                $sessStmt->execute();
                $sessData = $sessStmt->fetch(PDO::FETCH_ASSOC);
                if ($sessData) {
                    $recipients = array_values(array_filter([(int) $sessData['creator_id'], (int) ($sessData['dm_user_id'] ?? 0)]));
                    if (!empty($recipients)) {
                        $this->notiRepo->createNotification(
                            'Cửa sổ tìm người thay thế đã đóng',
                            "Cửa sổ tìm người thay thế của phòng '{$room['title']}' đã đóng. Quản trò (DM) sẽ hỗ trợ điều chỉnh nhân vật hoặc đóng thế vai NPC.",
                            'ROOM',
                            null,
                            $recipients
                        );
                    }
                }
            } catch (\Throwable $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
            }
        }

        return $closedCount;
    }

    /**
     * Job 4: Quét các đề xuất lịch biểu quyết quá hạn (expires_at <= NOW() AND status = 'PROPOSED')
     * -> Tự động chuyển thành EXPIRED và đưa phòng quay về FORMING (Điểm 4 & Điểm 9).
     */
    public function runProposalTimeout(): int
    {
        $findStmt = $this->pdo->query(
            "SELECT `id`, `game_room_id` FROM `schedule_proposals`
             WHERE `status` = 'PROPOSED' AND `expires_at` IS NOT NULL AND `expires_at` <= NOW()"
        );
        $expiredProps = $findStmt ? $findStmt->fetchAll(PDO::FETCH_ASSOC) : [];
        $count = 0;

        foreach ($expiredProps as $p) {
            $propId = (int) $p['id'];
            $roomId = (int) $p['game_room_id'];

            $this->pdo->prepare("UPDATE `schedule_proposals` SET `status` = 'EXPIRED' WHERE `id` = :id")
                ->execute([':id' => $propId]);
            $this->pdo->prepare("UPDATE `game_rooms` SET `status` = 'FORMING', `updated_at` = NOW() WHERE `id` = :rid AND `status` IN ('PENDING_SCHEDULE', 'SCHEDULE_PROPOSED')")
                ->execute([':rid' => $roomId]);
            $count++;
        }

        return $count;
    }

    /**
     * Job 5: Quét các phân công DM chờ phản hồi quá 4 tiếng (assigned_at <= NOW() - 4H AND status = 'PENDING')
     * -> Tự động thu hồi, và nếu xoay tua >= 3 lượt thì phát cảnh báo khẩn cấp lên Dashboard Admin (Điểm 5).
     */
    public function runDmAssignmentTimeout(): int
    {
        $pendingStmt = $this->pdo->query(
            "SELECT `id`, `game_room_id` FROM `dm_assignments`
             WHERE `status` = 'PENDING' AND `assigned_at` <= DATE_SUB(NOW(), INTERVAL 4 HOUR)"
        );
        $assignments = $pendingStmt ? $pendingStmt->fetchAll(PDO::FETCH_ASSOC) : [];
        $revokedCount = 0;

        foreach ($assignments as $a) {
            $assignId = (int) $a['id'];
            $roomId = (int) $a['game_room_id'];

            $this->pdo->prepare(
                "UPDATE `dm_assignments`
                 SET `status` = 'REVOKED',
                     `revoked_at` = NOW(),
                     `reason` = 'Quá hạn 4 giờ Quản trò không phản hồi tiếp nhận ca trực',
                     `updated_at` = NOW()
                 WHERE `id` = :id"
            )->execute([':id' => $assignId]);
            $revokedCount++;

            // Kiểm tra số lần xoay tua của phòng chơi này
            $revCountStmt = $this->pdo->prepare("SELECT COUNT(*) FROM `dm_assignments` WHERE `game_room_id` = :rid AND `status` = 'REVOKED'");
            $revCountStmt->execute([':rid' => $roomId]);
            if ((int) $revCountStmt->fetchColumn() >= 3) {
                // Đẩy thông báo khẩn cấp cho Quản trị viên
                $adminStmt = $this->pdo->query("SELECT u.`id` FROM `users` u INNER JOIN `roles` r ON r.`id` = u.`role_id` WHERE r.`code` = 'ADMIN' AND u.`status` = 'ACTIVE'");
                $adminIds = $adminStmt ? array_map('intval', $adminStmt->fetchAll(PDO::FETCH_COLUMN)) : [1];

                $this->notiRepo->createNotification(
                    'Cảnh báo khẩn cấp: Hết lượt xoay tua Quản trò',
                    "Phòng chơi ID #{$roomId} đã xoay tua qua nhiều Quản trò nhưng chưa có người nhận ca. Yêu cầu Quản trị viên can thiệp điều phối thủ công.",
                    'ADMIN_ALERT',
                    null,
                    $adminIds
                );
            }
        }

        return $revokedCount;
    }

    /**
     * Job 6: Quét các ca chơi sắp diễn ra trong 24 giờ tới (start_at <= NOW() + 24H)
     * và tự động phát thông báo nhắc lịch cho người chơi và Quản trò (DM) phụ trách ca.
     */
    public function runUpcomingSession24hReminder(): int
    {
        $sql = "SELECT s.`id` AS `session_id`, s.`game_room_id`, s.`start_at`, s.`venue_id`, s.`physical_room_id`,
                       gr.`title` AS `room_title`,
                       pr.`name` AS `physical_room_name`,
                       v.`name` AS `venue_name`,
                       g.`name` AS `game_name`
                FROM `sessions` s
                INNER JOIN `game_rooms` gr ON gr.`id` = s.`game_room_id`
                LEFT JOIN `physical_rooms` pr ON pr.`id` = s.`physical_room_id`
                LEFT JOIN `venues` v ON v.`id` = s.`venue_id`
                LEFT JOIN `games` g ON g.`id` = gr.`game_id`
                WHERE s.`status` = 'SCHEDULED'
                  AND s.`start_at` > NOW()
                  AND s.`start_at` <= DATE_ADD(NOW(), INTERVAL 24 HOUR)";

        $stmt = $this->pdo->query($sql);
        $upcomingSessions = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        $notifiedCount = 0;

        foreach ($upcomingSessions as $session) {
            $sessionId = (int) $session['session_id'];
            $roomId = (int) $session['game_room_id'];
            $gameName = $session['game_name'] ?? $session['room_title'];
            $roomName = $session['physical_room_name'] ?? 'Phòng chỉ định';
            $venueName = $session['venue_name'] ?? 'Chi nhánh Kịch Bản Sát';
            $startAtStr = date('H:i d/m/Y', strtotime((string) $session['start_at']));

            // Lấy danh sách người chơi trong phòng
            $pStmt = $this->pdo->prepare(
                "SELECT `user_id` FROM `room_participants`
                 WHERE `game_room_id` = :room_id
                   AND `status` IN ('DEPOSITED', 'PENDING_DEPOSIT')"
            );
            $pStmt->bindValue(':room_id', $roomId, PDO::PARAM_INT);
            $pStmt->execute();
            $playerIds = array_map('intval', $pStmt->fetchAll(PDO::FETCH_COLUMN));

            // Lấy Quản trò (DM) được phân công tiếp nhận
            $dmStmt = $this->pdo->prepare(
                "SELECT `dm_user_id` FROM `dm_assignments`
                 WHERE `game_room_id` = :room_id AND `status` = 'ACCEPTED' LIMIT 1"
            );
            $dmStmt->bindValue(':room_id', $roomId, PDO::PARAM_INT);
            $dmStmt->execute();
            $dmUserId = $dmStmt->fetchColumn();

            $allTargetUserIds = $playerIds;
            if ($dmUserId !== false && $dmUserId !== null) {
                $allTargetUserIds[] = (int) $dmUserId;
            }
            $allTargetUserIds = array_unique($allTargetUserIds);

            // Kiểm tra xem đã từng gửi thông báo nhắc lịch cho ca chơi này chưa (Idempotency)
            $checkNotiStmt = $this->pdo->prepare(
                "SELECT 1 FROM `notifications`
                 WHERE `user_id` = :user_id
                   AND `type` = 'SESSION_REMINDER_24H'
                   AND `reference_type` = 'SESSION'
                   AND `reference_id` = :session_id
                 LIMIT 1"
            );

            $recipientsToNotify = [];
            foreach ($allTargetUserIds as $uid) {
                $checkNotiStmt->bindValue(':user_id', $uid, PDO::PARAM_INT);
                $checkNotiStmt->bindValue(':session_id', $sessionId, PDO::PARAM_INT);
                $checkNotiStmt->execute();
                if ($checkNotiStmt->fetchColumn() === false) {
                    $recipientsToNotify[] = $uid;
                }
            }

            if (!empty($recipientsToNotify)) {
                $title = 'Nhắc lịch ca chơi sắp diễn ra (còn dưới 24h)';
                $message = "Buổi chơi '{$gameName}' tại phòng '{$roomName}' ({$venueName}) sẽ diễn ra vào lúc {$startAtStr}. Quý khách vui lòng có mặt trước 15 phút để làm thủ tục check-in.";

                $this->notiRepo->createNotification(
                    $title,
                    $message,
                    'SESSION_REMINDER_24H',
                    null,
                    $recipientsToNotify,
                    'SESSION',
                    $sessionId
                );
                $notifiedCount += count($recipientsToNotify);
            }
        }

        return $notifiedCount;
    }
}
