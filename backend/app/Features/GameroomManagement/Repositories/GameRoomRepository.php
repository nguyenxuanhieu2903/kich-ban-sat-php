<?php

declare(strict_types=1);

namespace App\Features\GameroomManagement\Repositories;

use App\Infrastructure\Database\BaseRepository;
use PDO;

class GameRoomRepository extends BaseRepository
{
    /**
     * Find room by ID.
     *
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        return $this->executeQuery(function () use ($id): ?array {
            $stmt = $this->pdo->prepare(
                'SELECT `id`, CONCAT(\'ROOM_\', `id`) AS `room_code`, `creator_id`, `game_id`, `game_configuration_id`,
                        `title`, `description`, `min_players`, `max_players`,
                        `status`, `booking_type`, `target_venue_id`, `target_time_slot`,
                        `total_players`, `confirmed_players`, `required_players`, `guest_slots`, `is_18_plus_acknowledged`,
                        1 AS `is_active`,
                        `formation_deadline_at` AS `formation_deadline`, `formation_deadline_at`,
                        `description` AS `notes`, `cancel_reason`, `cancelled_at`,
                        `replacement_opened_by`, `replacement_status`, `replacement_deadline_at`,
                        `price_final_amount`, `price_deposit_total`, `price_remaining_total`,
                        `created_at`, `updated_at`
                 FROM `game_rooms`
                 WHERE `id` = :id
                 LIMIT 1'
            );
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result !== false ? $result : null;
        });
    }

    /**
     * Find room by room code (or room ID).
     *
     * @return array<string, mixed>|null
     */
    public function findByRoomCode(string $roomCode): ?array
    {
        $id = (int) preg_replace('/[^0-9]/', '', $roomCode);
        if ($id <= 0) {
            return null;
        }
        return $this->findById($id);
    }

    /**
     * Create a new game room.
     *
     * @param array{
     *     game_id: int,
     *     game_configuration_id?: int,
     *     creator_id: int,
     *     title: string,
     *     description?: string|null,
     *     min_players?: int,
     *     max_players?: int,
     *     status?: string,
     *     formation_deadline?: string,
     *     formation_deadline_at?: string,
     *     notes?: string|null
     * } $data
     */
    public function create(array $data): int
    {
        return $this->executeQuery(function () use ($data): int {
            $gameId = (int) $data['game_id'];
            $configId = $data['game_configuration_id'] ?? null;
            if ($configId === null) {
                $cfgStmt = $this->pdo->prepare('SELECT `id` FROM `game_configurations` WHERE `game_id` = :game_id AND `is_active` = 1 ORDER BY `version` DESC LIMIT 1');
                $cfgStmt->bindValue(':game_id', $gameId, PDO::PARAM_INT);
                $cfgStmt->execute();
                $resolved = $cfgStmt->fetchColumn();
                $configId = $resolved !== false ? (int) $resolved : 1;
            }

            $deadline = $data['formation_deadline_at'] ?? $data['formation_deadline'] ?? date('Y-m-d H:i:s', strtotime('+3 days'));
            $desc = $data['description'] ?? $data['notes'] ?? null;
            $bookingType = $data['booking_type'] ?? 'PUBLIC';
            $targetVenueId = isset($data['target_venue_id']) ? (int) $data['target_venue_id'] : null;
            $targetTimeSlot = $data['target_time_slot'] ?? null;
            $totalPlayers = isset($data['total_players']) ? (int) $data['total_players'] : null;
            $confirmedPlayers = isset($data['confirmed_players']) ? (int) $data['confirmed_players'] : 1;
            $requiredPlayers = isset($data['required_players']) ? (int) $data['required_players'] : ($data['min_players'] ?? 4);
            $guestSlots = isset($data['guest_slots']) ? (int) $data['guest_slots'] : 0;
            $is18Plus = !empty($data['is_18_plus_acknowledged']) ? 1 : 0;

            $stmt = $this->pdo->prepare(
                'INSERT INTO `game_rooms` (`game_id`, `game_configuration_id`, `creator_id`, `title`,
                                           `description`, `min_players`, `max_players`, `status`,
                                           `booking_type`, `target_venue_id`, `target_time_slot`,
                                           `total_players`, `confirmed_players`, `required_players`, `guest_slots`, `is_18_plus_acknowledged`,
                                           `formation_deadline_at`, `created_at`, `updated_at`)
                 VALUES (:game_id, :game_configuration_id, :creator_id, :title,
                         :description, :min_players, :max_players, :status,
                         :booking_type, :target_venue_id, :target_time_slot,
                         :total_players, :confirmed_players, :required_players, :guest_slots, :is_18_plus,
                         :formation_deadline_at, NOW(), NOW())'
            );

            $stmt->bindValue(':game_id', $gameId, PDO::PARAM_INT);
            $stmt->bindValue(':game_configuration_id', $configId, PDO::PARAM_INT);
            $stmt->bindValue(':creator_id', $data['creator_id'], PDO::PARAM_INT);
            $stmt->bindValue(':title', $data['title'], PDO::PARAM_STR);
            $stmt->bindValue(':description', $desc, $desc === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':min_players', $data['min_players'] ?? 4, PDO::PARAM_INT);
            $stmt->bindValue(':max_players', $data['max_players'] ?? 8, PDO::PARAM_INT);
            $stmt->bindValue(':status', $data['status'] ?? 'FORMING', PDO::PARAM_STR);
            $stmt->bindValue(':booking_type', $bookingType, PDO::PARAM_STR);
            $stmt->bindValue(':target_venue_id', $targetVenueId, $targetVenueId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':target_time_slot', $targetTimeSlot, $targetTimeSlot === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':total_players', $totalPlayers, $totalPlayers === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':confirmed_players', $confirmedPlayers, PDO::PARAM_INT);
            $stmt->bindValue(':required_players', $requiredPlayers, PDO::PARAM_INT);
            $stmt->bindValue(':guest_slots', $guestSlots, PDO::PARAM_INT);
            $stmt->bindValue(':is_18_plus', $is18Plus, PDO::PARAM_INT);
            $stmt->bindValue(':formation_deadline_at', $deadline, PDO::PARAM_STR);

            $stmt->execute();

            return (int) $this->pdo->lastInsertId();
        });
    }

    /**
     * Update room status.
     */
    public function updateStatus(int $id, string $newStatus, int $expectedVersion = 0, ?string $cancelReason = null): bool
    {
        return $this->executeQuery(function () use ($id, $newStatus, $cancelReason): bool {
            $stmt = $this->pdo->prepare(
                'UPDATE `game_rooms`
                 SET `status` = :status,
                     `cancel_reason` = :cancel_reason,
                     `cancelled_at` = CASE WHEN :status = \'CANCELLED\' THEN NOW() ELSE `cancelled_at` END,
                     `updated_at` = NOW()
                 WHERE `id` = :id'
            );

            $stmt->bindValue(':status', $newStatus, PDO::PARAM_STR);
            $stmt->bindValue(':cancel_reason', $cancelReason, $cancelReason === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);

            $stmt->execute();

            return $stmt->rowCount() > 0;
        });
    }

    /**
     * Add a participant to a game room.
     */
    public function addParticipant(int $gameRoomId, int $userId, string $roleInRoom = 'PLAYER', int $depositAmount = 50000): int
    {
        return $this->executeQuery(function () use ($gameRoomId, $userId): int {
            $stmt = $this->pdo->prepare(
                'INSERT INTO `room_participants` (`game_room_id`, `user_id`, `status`, `joined_at`, `created_at`, `updated_at`)
                 VALUES (:game_room_id, :user_id, \'PENDING_DEPOSIT\', NOW(), NOW(), NOW())'
            );

            $stmt->bindValue(':game_room_id', $gameRoomId, PDO::PARAM_INT);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);

            $stmt->execute();

            return (int) $this->pdo->lastInsertId();
        });
    }

    /**
     * Get participants for a game room.
     *
     * @return list<array<string, mixed>>
     */
    public function getParticipants(int $gameRoomId): array
    {
        return $this->executeQuery(function () use ($gameRoomId): array {
            $stmt = $this->pdo->prepare(
                'SELECT rp.`id`, rp.`game_room_id`, rp.`user_id`, rp.`status`,
                        rp.`joined_at`, rp.`deposit_deadline_at`, rp.`deposited_at`, rp.`left_at`,
                        u.`full_name` AS `user_name`, u.`full_name`, u.`email` AS `user_email`
                 FROM `room_participants` rp
                 INNER JOIN `users` u ON u.`id` = rp.`user_id`
                 WHERE rp.`game_room_id` = :game_room_id
                 ORDER BY rp.`joined_at` ASC'
            );
            $stmt->bindValue(':game_room_id', $gameRoomId, PDO::PARAM_INT);
            $stmt->execute();

            /** @var list<array<string, mixed>> $rows */
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $rows;
        });
    }
}
