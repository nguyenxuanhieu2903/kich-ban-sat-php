<?php

declare(strict_types=1);

namespace App\Features\Booking\Repositories;

use App\Infrastructure\Database\BaseRepository;
use PDO;

class BookingRepository extends BaseRepository
{
    /**
     * Find session by primary key ID.
     *
     * @return array<string, mixed>|null
     */
    public function findSessionById(int $sessionId): ?array
    {
        return $this->executeQuery(function () use ($sessionId): ?array {
            $stmt = $this->pdo->prepare(
                'SELECT `id`, `game_room_id`, `dm_assignment_id`, `session_code`, `scheduled_date`,
                        `start_time`, `end_time`, `actual_start_time`, `actual_end_time`,
                        `status`, `cancellation_reason`, `version`, `created_at`, `updated_at`
                 FROM `sessions`
                 WHERE `id` = :id
                 LIMIT 1'
            );
            $stmt->bindValue(':id', $sessionId, PDO::PARAM_INT);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result !== false ? $result : null;
        });
    }

    /**
     * Find active session for a game room.
     *
     * @return array<string, mixed>|null
     */
    public function findActiveSessionByGameRoomId(int $gameRoomId): ?array
    {
        return $this->executeQuery(function () use ($gameRoomId): ?array {
            $stmt = $this->pdo->prepare(
                'SELECT `id`, `game_room_id`, `dm_assignment_id`, `session_code`, `scheduled_date`,
                        `start_time`, `end_time`, `status`, `cancellation_reason`, `version`, `created_at`, `updated_at`
                 FROM `sessions`
                 WHERE `game_room_id` = :game_room_id
                   AND `status` IN (\'SCHEDULED\', \'IN_PROGRESS\')
                 ORDER BY `id` DESC
                 LIMIT 1'
            );
            $stmt->bindValue(':game_room_id', $gameRoomId, PDO::PARAM_INT);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result !== false ? $result : null;
        });
    }

    /**
     * Check if a physical room is already booked in an overlapping time window.
     */
    public function isPhysicalRoomBooked(
        int $physicalRoomId,
        string $reservationDate,
        string $startTime,
        string $endTime,
        ?int $excludeReservationId = null
    ): bool {
        return $this->executeQuery(function () use ($physicalRoomId, $reservationDate, $startTime, $endTime, $excludeReservationId): bool {
            $sql = 'SELECT COUNT(1)
                    FROM `sessions`
                    WHERE `physical_room_id` = :physical_room_id
                      AND `scheduled_date` = :reservation_date
                      AND `status` IN (\'SCHEDULED\', \'IN_PROGRESS\')
                      AND (`start_time` < :end_time AND `end_time` > :start_time)';

            if ($excludeReservationId !== null) {
                $sql .= ' AND `id` != :exclude_id';
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':physical_room_id', $physicalRoomId, PDO::PARAM_INT);
            $stmt->bindValue(':reservation_date', $reservationDate, PDO::PARAM_STR);
            $stmt->bindValue(':start_time', $startTime, PDO::PARAM_STR);
            $stmt->bindValue(':end_time', $endTime, PDO::PARAM_STR);

            if ($excludeReservationId !== null) {
                $stmt->bindValue(':exclude_id', $excludeReservationId, PDO::PARAM_INT);
            }

            $stmt->execute();
            return (int) $stmt->fetchColumn() > 0;
        });
    }
}
