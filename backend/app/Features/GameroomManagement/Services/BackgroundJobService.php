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
     * Job 4: Quét các đề xuất lịch biểu quyết quá hạn (expires_at <= NOW() AND status = 'PROPOSED')
     * -> Tự động chuyển thành EXPIRED.
     */
    public function runProposalTimeout(): int
    {
        $stmt = $this->pdo->prepare(
            'UPDATE `schedule_proposals`
             SET `status` = \'EXPIRED\'
             WHERE `status` = \'PROPOSED\' AND `expires_at` IS NOT NULL AND `expires_at` <= NOW()'
        );
        $stmt->execute();

        return $stmt->rowCount();
    }

    /**
     * Job 5: Quét các phân công DM chờ phản hồi quá 4 tiếng (assigned_at <= NOW() - 4H AND status = 'PENDING')
     * -> Tự động thu hồi và mở lại cơ hội cho DM khác.
     */
    public function runDmAssignmentTimeout(): int
    {
        $stmt = $this->pdo->prepare(
            'UPDATE `dm_assignments`
             SET `status` = \'REVOKED\',
                 `revoked_at` = NOW(),
                 `reason` = \'Quá hạn 4 giờ Quản trò không phản hồi tiếp nhận ca trực\',
                 `updated_at` = NOW()
             WHERE `status` = \'PENDING\' AND `assigned_at` <= DATE_SUB(NOW(), INTERVAL 4 HOUR)'
        );
        $stmt->execute();

        return $stmt->rowCount();
    }
}
