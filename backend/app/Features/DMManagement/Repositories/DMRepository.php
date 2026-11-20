<?php

declare(strict_types=1);

namespace App\Features\DMManagement\Repositories;

use App\Infrastructure\Database\BaseRepository;
use PDO;

class DMRepository extends BaseRepository
{
    /**
     * Find DM profile by DM user ID.
     *
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        return $this->executeQuery(function () use ($id): ?array {
            $stmt = $this->pdo->prepare(
                'SELECT u.`id`, u.`id` AS `user_id`, u.`full_name` AS `name`, u.`full_name`, u.`email`,
                        u.`phone`, u.`avatar_url`, u.`status`,
                        u.`created_at`, u.`updated_at`
                 FROM `users` u
                 WHERE u.`id` = :id
                 LIMIT 1'
            );
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result !== false ? $result : null;
        });
    }

    /**
     * Find DM profile by User ID.
     *
     * @return array<string, mixed>|null
     */
    public function findByUserId(int $userId): ?array
    {
        return $this->findById($userId);
    }

    /**
     * Get active DM availabilities for a DM profile.
     *
     * @return list<array<string, mixed>>
     */
    public function getAvailabilities(int $dmProfileId): array
    {
        return [];
    }

    /**
     * Assign a DM to a game room.
     */
    public function assignToRoom(int $gameRoomId, int $dmUserId, ?string $reason = null, int $assignedBy = 1): int
    {
        return $this->executeQuery(function () use ($gameRoomId, $dmUserId, $reason, $assignedBy): int {
            $stmt = $this->pdo->prepare(
                'INSERT INTO `dm_assignments` (`game_room_id`, `dm_user_id`, `assigned_by`, `status`, `reason`, `assigned_at`, `created_at`, `updated_at`)
                 VALUES (:game_room_id, :dm_user_id, :assigned_by, \'PENDING\', :reason, NOW(), NOW(), NOW())'
            );
            $stmt->bindValue(':game_room_id', $gameRoomId, PDO::PARAM_INT);
            $stmt->bindValue(':dm_user_id', $dmUserId, PDO::PARAM_INT);
            $stmt->bindValue(':assigned_by', $assignedBy, PDO::PARAM_INT);
            $stmt->bindValue(':reason', $reason, $reason === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->execute();

            return (int) $this->pdo->lastInsertId();
        });
    }

    /**
     * Get active DM assignment for a game room.
     *
     * @return array<string, mixed>|null
     */
    public function getActiveAssignmentForRoom(int $gameRoomId): ?array
    {
        return $this->executeQuery(function () use ($gameRoomId): ?array {
            $stmt = $this->pdo->prepare(
                'SELECT `id`, `game_room_id`, `dm_user_id`, `dm_user_id` AS `dm_profile_id`,
                        `status`, `assigned_at`, `responded_at`, `reason` AS `notes`, `created_at`, `updated_at`
                 FROM `dm_assignments`
                 WHERE `game_room_id` = :game_room_id AND `status` IN (\'PENDING\', \'ACCEPTED\')
                 LIMIT 1'
            );
            $stmt->bindValue(':game_room_id', $gameRoomId, PDO::PARAM_INT);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result !== false ? $result : null;
        });
    }
}
