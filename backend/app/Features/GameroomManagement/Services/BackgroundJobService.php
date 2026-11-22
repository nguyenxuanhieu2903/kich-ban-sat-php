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
            'dm_assignment_timeouts' => $this->runDMAssignmentTimeout(),
        ];
    }

    /**
     * Job 1: Quét các phòng FORMING quá hạn (formation_deadline_at < NOW())
     * và chưa đủ số người chơi tối thiểu -> Tự động CANCELLED phòng.
     */
    public function runFormationTimeout(): int
    {
        // Lấy danh sách các phòng FORMING đã quá hạn
        $sql = "SELECT gr.`id`, gr.`title`, gr.`min_players`,
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

                    // Hủy các hóa đơn chưa thanh toán (PENDING)
                    $upUnpaid = $this->pdo->prepare(
                        'UPDATE `player_payments` p
                         INNER JOIN `room_participants` rp ON rp.`id` = p.`room_participant_id`
                         SET p.`status` = \'CANCELLED\', p.`updated_at` = NOW()
                         WHERE rp.`game_room_id` = :room_id AND p.`status` = \'PENDING\''
                    );
                    $upUnpaid->bindValue(':room_id', $roomId, PDO::PARAM_INT);
                    $upUnpaid->execute();

                    // Tự động hoàn tiền COMPLETED cho các thành viên đã cọc (Option A là FINAL)
                    $payStmt = $this->pdo->prepare(
                        'SELECT p.`id` AS `payment_id`, p.`paid_amount`, rp.`user_id`
                         FROM `player_payments` p
                         INNER JOIN `room_participants` rp ON rp.`id` = p.`room_participant_id`
                         WHERE rp.`game_room_id` = :room_id AND p.`paid_amount` > 0
                           AND NOT EXISTS (SELECT 1 FROM `refunds` r WHERE r.`player_payment_id` = p.`id`)'
                    );
                    $payStmt->bindValue(':room_id', $roomId, PDO::PARAM_INT);
                    $payStmt->execute();
                    $paidList = $payStmt->fetchAll(PDO::FETCH_ASSOC);

                    $insRef = $this->pdo->prepare(
                        'INSERT INTO `refunds` (`player_payment_id`, `amount`, `reason`, `status`, `requested_at`, `completed_at`, `review_reason`, `created_at`, `updated_at`)
                         VALUES (:payment_id, :amount, :reason, \'COMPLETED\', NOW(), NOW(), :review_reason, NOW(), NOW())'
                    );

                    $refundedUserMap = [];
                    foreach ($paidList as $payment) {
                        $payId = (int) $payment['payment_id'];
                        $amount = (int) $payment['paid_amount'];
                        $pUserId = (int) $payment['user_id'];
                        $timeoutReason = 'Tự động hoàn tiền do phòng không đủ người chơi tối thiểu';
                        $reviewNote = 'Tự động hoàn tiền bởi hệ thống (Formation Timeout Option A)';

                        $insRef->bindValue(':payment_id', $payId, PDO::PARAM_INT);
                        $insRef->bindValue(':amount', $amount, PDO::PARAM_INT);
                        $insRef->bindValue(':reason', $timeoutReason, PDO::PARAM_STR);
                        $insRef->bindValue(':review_reason', $reviewNote, PDO::PARAM_STR);
                        $insRef->execute();
                        $refundId = (int) $this->pdo->lastInsertId();

                        // Cập nhật player_payments sang CANCELLED
                        $upPay = $this->pdo->prepare('UPDATE `player_payments` SET `status` = \'CANCELLED\', `updated_at` = NOW() WHERE `id` = :id');
                        $upPay->bindValue(':id', $payId, PDO::PARAM_INT);
                        $upPay->execute();

                        // Ghi nhận giao dịch hoàn tiền thành công vào payment_transactions với Idempotency Key xác định
                        $idempotencyKey = 'TIMEOUT_REFUND_' . $payId;
                        $txRef = 'TX_REFUND_AUTO_' . $payId;
                        $metadata = json_encode([
                            'action' => 'FORMATION_TIMEOUT_AUTO_REFUND',
                            'refund_id' => $refundId,
                            'amount' => $amount,
                        ]);
                        $insTx = $this->pdo->prepare(
                            'INSERT INTO `payment_transactions` (`player_payment_id`, `idempotency_key`, `transaction_reference`,
                                                                 `amount`, `status`, `provider_response`, `requested_at`, `processed_at`, `created_at`)
                             VALUES (:payment_id, :idempotency_key, :tx_ref,
                                     :amount, \'SUCCESS\', :provider_response, NOW(), NOW(), NOW())'
                        );
                        $insTx->bindValue(':payment_id', $payId, PDO::PARAM_INT);
                        $insTx->bindValue(':idempotency_key', $idempotencyKey, PDO::PARAM_STR);
                        $insTx->bindValue(':tx_ref', $txRef, PDO::PARAM_STR);
                        $insTx->bindValue(':amount', $amount, PDO::PARAM_INT);
                        $insTx->bindValue(':provider_response', $metadata, PDO::PARAM_STR);
                        $insTx->execute();

                        $refundedUserMap[$pUserId] = $amount;
                    }

                    // Lấy danh sách user_id để gửi notification
                    $uStmt = $this->pdo->prepare('SELECT DISTINCT `user_id` FROM `room_participants` WHERE `game_room_id` = :room_id');
                    $uStmt->bindValue(':room_id', $roomId, PDO::PARAM_INT);
                    $uStmt->execute();
                    $userIds = $uStmt->fetchAll(PDO::FETCH_COLUMN);

                    $this->pdo->commit();
                    $cancelledCount++;

                    // Gửi thông báo hoàn cọc tự động cho những user đã nộp tiền
                    foreach ($refundedUserMap as $rUid => $rAmount) {
                        try {
                            $this->notiRepo->createNotification(
                                'Tự động hoàn cọc - Phòng giải tán',
                                "Phòng '{$room['title']}' đã tự động giải tán do không gom đủ người. Tiền cọc " . number_format($rAmount) . " VNĐ đã được tự động hoàn lại cho bạn.",
                                'REFUND',
                                null,
                                [$rUid]
                            );
                        } catch (\Throwable) {
                        }
                    }

                    // Gửi thông báo chung cho các user khác chưa đóng cọc
                    $unpaidUserIds = array_diff(array_map('intval', $userIds), array_keys($refundedUserMap));
                    if (!empty($unpaidUserIds)) {
                        try {
                            $this->notiRepo->createNotification(
                                'Phòng chơi đã tự động giải tán',
                                "Phòng '{$room['title']}' đã bị hủy do hết thời hạn gom phòng mà chưa đủ số lượng thành viên tối thiểu ({$minPlayers} người).",
                                'ROOM',
                                null,
                                array_values($unpaidUserIds)
                            );
                        } catch (\Throwable) {
                        }
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

                // Đánh dấu hóa đơn cọc sang EXPIRED
                $upPay = $this->pdo->prepare(
                    'UPDATE `player_payments`
                     SET `status` = \'EXPIRED\', `expired_at` = NOW(), `updated_at` = NOW()
                     WHERE `room_participant_id` = :part_id AND `status` = \'PENDING\''
                );
                $upPay->bindValue(':part_id', $partId, PDO::PARAM_INT);
                $upPay->execute();

                // Mở Replacement Window cho phòng
                $upRoom = $this->pdo->prepare(
                    'UPDATE `game_rooms`
                     SET `replacement_status` = \'OPEN\',
                         `replacement_started_at` = NOW(),
                         `replacement_deadline_at` = DATE_ADD(NOW(), INTERVAL 4 HOUR),
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
                  AND gr.`replacement_deadline_at` IS NOT NULL
                  AND gr.`replacement_deadline_at` <= NOW()";

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
            } catch (\Throwable $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
            }
        }

        return $closedCount;
    }

    /**
     * Job 4: Quét các phân công quản trò PENDING quá hạn phản hồi
     * (> 24 giờ kể từ lúc phân công HOẶC trong vòng 12 giờ trước giờ chơi)
     * -> Tự động REVOKED ca, gỡ bỏ Session mồ côi (nếu có), và gửi thông báo cho Admin.
     */
    public function runDMAssignmentTimeout(): int
    {
        $sql = "SELECT da.`id`, da.`game_room_id`, da.`dm_user_id`, gr.`title` AS `room_title`,
                       u.`full_name` AS `dm_name`
                FROM `dm_assignments` da
                INNER JOIN `game_rooms` gr ON gr.`id` = da.`game_room_id`
                INNER JOIN `users` u ON u.`id` = da.`dm_user_id`
                LEFT JOIN `sessions` s ON s.`dm_assignment_id` = da.`id` AND s.`game_room_id` = da.`game_room_id`
                LEFT JOIN `schedule_proposals` sp ON sp.`game_room_id` = da.`game_room_id` AND sp.`status` = 'CONFIRMED'
                WHERE da.`status` = 'PENDING'
                  AND (
                      da.`assigned_at` <= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                      OR (s.`start_at` IS NOT NULL AND s.`start_at` <= DATE_ADD(NOW(), INTERVAL 12 HOUR))
                      OR (sp.`proposed_start_at` IS NOT NULL AND sp.`proposed_start_at` <= DATE_ADD(NOW(), INTERVAL 12 HOUR))
                  )";

        $stmt = $this->pdo->query($sql);
        $expiredAssignments = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $revokedCount = 0;

        // Lấy danh sách ID Admin để thông báo
        $adminStmt = $this->pdo->query("SELECT u.`id` FROM `users` u INNER JOIN `roles` r ON r.`id` = u.`role_id` WHERE r.`code` = 'ADMIN'");
        $adminIds = $adminStmt !== false ? $adminStmt->fetchAll(PDO::FETCH_COLUMN) : [];

        foreach ($expiredAssignments as $item) {
            $assignmentId = (int) $item['id'];
            $roomId = (int) $item['game_room_id'];
            $roomTitle = $item['room_title'] ?? ('#' . $roomId);
            $dmName = $item['dm_name'] ?? 'Quản trò';

            $this->pdo->beginTransaction();
            try {
                // 1. Chuyển trạng thái assignment thành REVOKED
                $upStmt = $this->pdo->prepare(
                    'UPDATE `dm_assignments`
                     SET `status` = \'REVOKED\',
                         `revoked_at` = NOW(),
                         `reason` = \'Quá hạn phản hồi nhận ca (Timeout)\',
                         `updated_at` = NOW()
                     WHERE `id` = :id AND `status` = \'PENDING\''
                );
                $upStmt->bindValue(':id', $assignmentId, PDO::PARAM_INT);
                $upStmt->execute();

                // 2. DEF-05: Gỡ bỏ Session tham chiếu đến assignment đã hết hạn
                $delSess = $this->pdo->prepare('DELETE FROM `sessions` WHERE `dm_assignment_id` = :aid');
                $delSess->bindValue(':aid', $assignmentId, PDO::PARAM_INT);
                $delSess->execute();

                $this->pdo->commit();
                $revokedCount++;

                // 3. Thông báo cho Admin
                if (!empty($adminIds)) {
                    $this->notiRepo->createNotification(
                        'Quản trò quá hạn phản hồi ca trực',
                        "Phân công quản trò của {$dmName} cho phòng '{$roomTitle}' đã tự động thu hồi do quá hạn phản hồi. Vui lòng phân công lại DM.",
                        'DM_TIMEOUT',
                        null,
                        array_map('intval', $adminIds)
                    );
                }
            } catch (\Throwable $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
            }
        }

        return $revokedCount;
    }
}
