<?php

declare(strict_types=1);

namespace App\Features\DMManagement\Repositories;

use App\Infrastructure\Database\BaseRepository;
use PDO;

class DMRepository extends BaseRepository
{
    /**
     * Find DM profile by DM profile ID.
     *
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        return $this->executeQuery(function () use ($id): ?array {
            $stmt = $this->pdo->prepare(
                'SELECT `id`, `user_id`, `experience_years`, `bio`, `rating_avg`, `status`, `created_at`, `updated_at`
                 FROM `dm_profiles`
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
     * Find DM profile by User ID.
     *
     * @return array<string, mixed>|null
     */
    public function findByUserId(int $userId): ?array
    {
        return $this->executeQuery(function () use ($userId): ?array {
            $stmt = $this->pdo->prepare(
                'SELECT `id`, `user_id`, `experience_years`, `bio`, `rating_avg`, `status`, `created_at`, `updated_at`
                 FROM `dm_profiles`
                 WHERE `user_id` = :user_id
                 LIMIT 1'
            );
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result !== false ? $result : null;
        });
    }

    /**
     * Get active DM availabilities for a DM profile.
     *
     * @return list<array<string, mixed>>
     */
    public function getAvailabilities(int $dmProfileId): array
    {
        return $this->executeQuery(function () use ($dmProfileId): array {
            $stmt = $this->pdo->prepare(
                'SELECT `id`, `dm_profile_id`, `day_of_week`, `start_time`, `end_time`, `specific_date`, `is_available`, `created_at`, `updated_at`
                 FROM `dm_availabilities`
                 WHERE `dm_profile_id` = :dm_profile_id AND `is_available` = 1
                 ORDER BY `day_of_week` ASC, `start_time` ASC'
            );
            $stmt->bindValue(':dm_profile_id', $dmProfileId, PDO::PARAM_INT);
            $stmt->execute();

            /** @var list<array<string, mixed>> $rows */
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $rows;
        });
    }

    /**
     * Assign a DM to a game room.
     */
    public function assignToRoom(int $gameRoomId, int $dmProfileId, ?string $notes = null): int
    {
        return $this->executeQuery(function () use ($gameRoomId, $dmProfileId, $notes): int {
            $stmt = $this->pdo->prepare(
                'INSERT INTO `dm_assignments` (`game_room_id`, `dm_profile_id`, `status`, `notes`, `assigned_at`, `created_at`, `updated_at`)
                 VALUES (:game_room_id, :dm_profile_id, \'ASSIGNED\', :notes, NOW(), NOW(), NOW())'
            );
            $stmt->bindValue(':game_room_id', $gameRoomId, PDO::PARAM_INT);
            $stmt->bindValue(':dm_profile_id', $dmProfileId, PDO::PARAM_INT);
            $stmt->bindValue(':notes', $notes, $notes === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
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
                'SELECT `id`, `game_room_id`, `dm_profile_id`, `status`, `assigned_at`, `confirmed_at`, `notes`, `created_at`, `updated_at`
                 FROM `dm_assignments`
                 WHERE `game_room_id` = :game_room_id AND `status` IN (\'ASSIGNED\', \'CONFIRMED\')
                 LIMIT 1'
            );
            $stmt->bindValue(':game_room_id', $gameRoomId, PDO::PARAM_INT);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result !== false ? $result : null;
        });
    }
}
