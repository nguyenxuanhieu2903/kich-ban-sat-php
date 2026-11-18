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
                'SELECT `id`, `room_code`, `creator_id`, `game_id`, `title`, `min_players`, `max_players`,
                        `status`, `is_active`, `formation_deadline`, `notes`, `cancel_reason`, `version`,
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
     * Find room by unique room code.
     *
     * @return array<string, mixed>|null
     */
    public function findByRoomCode(string $roomCode): ?array
    {
        return $this->executeQuery(function () use ($roomCode): ?array {
            $stmt = $this->pdo->prepare(
                'SELECT `id`, `room_code`, `creator_id`, `game_id`, `title`, `min_players`, `max_players`,
                        `status`, `is_active`, `formation_deadline`, `notes`, `cancel_reason`, `version`,
                        `created_at`, `updated_at`
                 FROM `game_rooms`
                 WHERE `room_code` = :room_code
                 LIMIT 1'
            );
            $stmt->bindValue(':room_code', $roomCode, PDO::PARAM_STR);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result !== false ? $result : null;
        });
    }

    /**
     * Create a new game room.
     *
     * @param array{
     *     room_code: string,
     *     creator_id: int,
     *     game_id: int,
     *     title: string,
     *     min_players?: int,
     *     max_players?: int,
     *     status?: string,
     *     formation_deadline: string,
     *     notes?: string|null
     * } $data
     */
    public function create(array $data): int
    {
        return $this->executeQuery(function () use ($data): int {
            $stmt = $this->pdo->prepare(
                'INSERT INTO `game_rooms` (`room_code`, `creator_id`, `game_id`, `title`, `min_players`,
                                           `max_players`, `status`, `is_active`, `formation_deadline`, `notes`,
                                           `version`, `created_at`, `updated_at`)
                 VALUES (:room_code, :creator_id, :game_id, :title, :min_players,
                         :max_players, :status, 1, :formation_deadline, :notes,
                         0, NOW(), NOW())'
            );

            $stmt->bindValue(':room_code', $data['room_code'], PDO::PARAM_STR);
            $stmt->bindValue(':creator_id', $data['creator_id'], PDO::PARAM_INT);
            $stmt->bindValue(':game_id', $data['game_id'], PDO::PARAM_INT);
            $stmt->bindValue(':title', $data['title'], PDO::PARAM_STR);
            $stmt->bindValue(':min_players', $data['min_players'] ?? 4, PDO::PARAM_INT);
            $stmt->bindValue(':max_players', $data['max_players'] ?? 8, PDO::PARAM_INT);
            $stmt->bindValue(':status', $data['status'] ?? 'FORMING', PDO::PARAM_STR);
            $stmt->bindValue(':formation_deadline', $data['formation_deadline'], PDO::PARAM_STR);
            $stmt->bindValue(':notes', $data['notes'] ?? null, ($data['notes'] ?? null) === null ? PDO::PARAM_NULL : PDO::PARAM_STR);

            $stmt->execute();

            return (int) $this->pdo->lastInsertId();
        });
    }

    /**
     * Update room status with optimistic locking.
     */
    public function updateStatus(int $id, string $newStatus, int $expectedVersion, ?string $cancelReason = null): bool
    {
        return $this->executeQuery(function () use ($id, $newStatus, $expectedVersion, $cancelReason): bool {
            $stmt = $this->pdo->prepare(
                'UPDATE `game_rooms`
                 SET `status` = :status,
                     `cancel_reason` = :cancel_reason,
                     `version` = `version` + 1,
                     `updated_at` = NOW()
                 WHERE `id` = :id AND `version` = :expected_version'
            );

            $stmt->bindValue(':status', $newStatus, PDO::PARAM_STR);
            $stmt->bindValue(':cancel_reason', $cancelReason, $cancelReason === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->bindValue(':expected_version', $expectedVersion, PDO::PARAM_INT);

            $stmt->execute();

            return $stmt->rowCount() > 0;
        });
    }

    /**
     * Add a participant to a game room.
     */
    public function addParticipant(int $gameRoomId, int $userId, string $roleInRoom = 'PLAYER', int $depositAmount = 50000): int
    {
        return $this->executeQuery(function () use ($gameRoomId, $userId, $roleInRoom, $depositAmount): int {
            $stmt = $this->pdo->prepare(
                'INSERT INTO `room_participants` (`game_room_id`, `user_id`, `role_in_room`, `status`, `deposit_amount`, `joined_at`, `version`, `created_at`, `updated_at`)
                 VALUES (:game_room_id, :user_id, :role_in_room, \'JOINED\', :deposit_amount, NOW(), 0, NOW(), NOW())'
            );

            $stmt->bindValue(':game_room_id', $gameRoomId, PDO::PARAM_INT);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':role_in_room', $roleInRoom, PDO::PARAM_STR);
            $stmt->bindValue(':deposit_amount', $depositAmount, PDO::PARAM_INT);

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
                'SELECT rp.`id`, rp.`game_room_id`, rp.`user_id`, rp.`role_in_room`, rp.`status`,
                        rp.`deposit_amount`, rp.`remaining_amount`, rp.`deposit_deadline`,
                        rp.`joined_at`, rp.`left_at`, rp.`version`,
                        u.`name` AS `user_name`, u.`email` AS `user_email`
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
