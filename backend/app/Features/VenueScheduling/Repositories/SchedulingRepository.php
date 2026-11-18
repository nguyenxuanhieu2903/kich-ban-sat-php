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
                'SELECT `id`, `game_room_id`, `dm_assignment_id`, `session_code`, `scheduled_date`,
                        `start_time`, `end_time`, `actual_start_time`, `actual_end_time`,
                        `status`, `cancellation_reason`, `version`, `created_at`, `updated_at`
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
     * Find session by session code.
     *
     * @return array<string, mixed>|null
     */
    public function findSessionByCode(string $sessionCode): ?array
    {
        return $this->executeQuery(function () use ($sessionCode): ?array {
            $stmt = $this->pdo->prepare(
                'SELECT `id`, `game_room_id`, `dm_assignment_id`, `session_code`, `scheduled_date`,
                        `start_time`, `end_time`, `actual_start_time`, `actual_end_time`,
                        `status`, `cancellation_reason`, `version`, `created_at`, `updated_at`
                 FROM `sessions`
                 WHERE `session_code` = :session_code
                 LIMIT 1'
            );
            $stmt->bindValue(':session_code', $sessionCode, PDO::PARAM_STR);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result !== false ? $result : null;
        });
    }

    /**
     * Create a scheduled play session.
     *
     * @param array{
     *     game_room_id: int,
     *     dm_assignment_id?: int|null,
     *     session_code: string,
     *     scheduled_date: string,
     *     start_time: string,
     *     end_time: string,
     *     status?: string
     * } $data
     */
    public function createSession(array $data): int
    {
        return $this->executeQuery(function () use ($data): int {
            $stmt = $this->pdo->prepare(
                'INSERT INTO `sessions` (`game_room_id`, `dm_assignment_id`, `session_code`,
                                        `scheduled_date`, `start_time`, `end_time`, `status`,
                                        `version`, `created_at`, `updated_at`)
                 VALUES (:game_room_id, :dm_assignment_id, :session_code,
                         :scheduled_date, :start_time, :end_time, :status,
                         0, NOW(), NOW())'
            );

            $stmt->bindValue(':game_room_id', $data['game_room_id'], PDO::PARAM_INT);
            $stmt->bindValue(':dm_assignment_id', $data['dm_assignment_id'] ?? null, ($data['dm_assignment_id'] ?? null) === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':session_code', $data['session_code'], PDO::PARAM_STR);
            $stmt->bindValue(':scheduled_date', $data['scheduled_date'], PDO::PARAM_STR);
            $stmt->bindValue(':start_time', $data['start_time'], PDO::PARAM_STR);
            $stmt->bindValue(':end_time', $data['end_time'], PDO::PARAM_STR);
            $stmt->bindValue(':status', $data['status'] ?? 'SCHEDULED', PDO::PARAM_STR);

            $stmt->execute();

            return (int) $this->pdo->lastInsertId();
        });
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
                'SELECT `id`, `code`, `name`, `capacity`, `status`, `description`
                 FROM `physical_rooms`
                 WHERE `status` = \'AVAILABLE\' AND `capacity` >= :min_capacity
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
     * Create reservation for physical room.
     */
    public function reservePhysicalRoom(
        int $physicalRoomId,
        int $sessionId,
        string $reservationDate,
        string $startTime,
        string $endTime
    ): int {
        return $this->executeQuery(function () use (
            $physicalRoomId,
            $sessionId,
            $reservationDate,
            $startTime,
            $endTime
        ): int {
            $stmt = $this->pdo->prepare(
                'INSERT INTO `physical_room_reservations` (`physical_room_id`, `session_id`, `reservation_date`, `start_time`, `end_time`, `status`, `created_at`, `updated_at`)
                 VALUES (:physical_room_id, :session_id, :reservation_date, :start_time, :end_time, \'RESERVED\', NOW(), NOW())'
            );

            $stmt->bindValue(':physical_room_id', $physicalRoomId, PDO::PARAM_INT);
            $stmt->bindValue(':session_id', $sessionId, PDO::PARAM_INT);
            $stmt->bindValue(':reservation_date', $reservationDate, PDO::PARAM_STR);
            $stmt->bindValue(':start_time', $startTime, PDO::PARAM_STR);
            $stmt->bindValue(':end_time', $endTime, PDO::PARAM_STR);

            $stmt->execute();

            return (int) $this->pdo->lastInsertId();
        });
    }

    /**
     * Check if a physical room has conflicting reservations.
     */
    public function hasPhysicalRoomConflict(int $physicalRoomId, string $date, string $startTime, string $endTime): bool
    {
        return $this->executeQuery(function () use ($physicalRoomId, $date, $startTime, $endTime): bool {
            $stmt = $this->pdo->prepare(
                'SELECT COUNT(*) AS conflict_count
                 FROM `physical_room_reservations`
                 WHERE `physical_room_id` = :physical_room_id
                   AND `reservation_date` = :reservation_date
                   AND `status` IN (\'RESERVED\', \'CONFIRMED\')
                   AND `start_time` < :end_time
                   AND `end_time` > :start_time'
            );

            $stmt->bindValue(':physical_room_id', $physicalRoomId, PDO::PARAM_INT);
            $stmt->bindValue(':reservation_date', $date, PDO::PARAM_STR);
            $stmt->bindValue(':start_time', $startTime, PDO::PARAM_STR);
            $stmt->bindValue(':end_time', $endTime, PDO::PARAM_STR);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return !empty($result['conflict_count']) && (int) $result['conflict_count'] > 0;
        });
    }
}
