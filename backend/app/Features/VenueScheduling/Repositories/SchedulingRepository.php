<?php

declare(strict_types=1);

namespace App\Features\VenueScheduling\Repositories;

use App\Infrastructure\Database\BaseRepository;
use PDO;

class SchedulingRepository extends BaseRepository
{
    /**
     * Find session by ID.
     *
     * @return array<string, mixed>|null
     */
    public function findSessionById(int $id): ?array
    {
        return $this->executeQuery(function () use ($id): ?array {
            $stmt = $this->pdo->prepare(
                'SELECT `id`, `game_room_id`, `schedule_proposal_id`, `dm_assignment_id`,
                        `venue_id`, `physical_room_id`, CONCAT(\'SESS_\', `id`) AS `session_code`,
                        DATE(`start_at`) AS `scheduled_date`, TIME(`start_at`) AS `start_time`, TIME(`end_at`) AS `end_time`,
                        `start_at`, `end_at`, `status`, `started_at` AS `actual_start_time`,
                        `completed_at` AS `actual_end_time`, `cancel_reason` AS `cancellation_reason`,
                        `created_at`, `updated_at`
                 FROM `sessions`
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
     * Find session by session code or ID.
     *
     * @return array<string, mixed>|null
     */
    public function findSessionByCode(string $sessionCode): ?array
    {
        $id = (int) preg_replace('/[^0-9]/', '', $sessionCode);
        if ($id <= 0) {
            return null;
        }
        return $this->findSessionById($id);
    }

    /**
     * List physical rooms by capacity.
     *
     * @return list<array<string, mixed>>
     */
    public function listAvailablePhysicalRooms(int $minCapacity = 1): array
    {
        return $this->executeQuery(function () use ($minCapacity): array {
            $stmt = $this->pdo->prepare(
                'SELECT `id`, CONCAT(\'ROOM_\', `id`) AS `code`, `name`, `capacity`, `status`, `description`
                 FROM `physical_rooms`
                 WHERE `status` = \'ACTIVE\' AND `capacity` >= :min_capacity
                 ORDER BY `capacity` ASC, `name` ASC'
            );
            $stmt->bindValue(':min_capacity', max(1, $minCapacity), PDO::PARAM_INT);
            $stmt->execute();

            /** @var list<array<string, mixed>> $rows */
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $rows;
        });
    }

    /**
     * Check if a physical room has conflicting reservations.
     */
    public function hasPhysicalRoomConflict(int $physicalRoomId, string $date, string $startTime, string $endTime): bool
    {
        return $this->executeQuery(function () use ($physicalRoomId, $date, $startTime, $endTime): bool {
            $startAt = $date . ' ' . $startTime;
            $endAt = $date . ' ' . $endTime;

            $stmt = $this->pdo->prepare(
                'SELECT COUNT(*) AS conflict_count
                 FROM `sessions`
                 WHERE `physical_room_id` = :physical_room_id
                   AND `status` IN (\'SCHEDULED\', \'IN_PROGRESS\')
                   AND `start_at` < :end_at
                   AND `end_at` > :start_at'
            );

            $stmt->bindValue(':physical_room_id', $physicalRoomId, PDO::PARAM_INT);
            $stmt->bindValue(':start_at', $startAt, PDO::PARAM_STR);
            $stmt->bindValue(':end_at', $endAt, PDO::PARAM_STR);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return !empty($result['conflict_count']) && (int) $result['conflict_count'] > 0;
        });
    }
}
