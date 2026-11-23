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
                'SELECT `id`, `venue_id`, CONCAT(\'ROOM_\', `id`) AS `code`, `name`, `capacity`, `status`, `description`
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

    /**
     * Find an active physical room with sufficient capacity and no conflicting session or offline block.
     * Enforces strict buffer (default 30m) without degrading to 0m.
     *
     * @return array<string, mixed>|null
     */
    public function findAvailablePhysicalRoom(
        int $minCapacity,
        string $startAt,
        string $endAt,
        int $bufferMinutes = 30,
        ?int $venueId = null
    ): ?array {
        $strictBuffer = max(30, $bufferMinutes);
        return $this->querySingleAvailablePhysicalRoom($minCapacity, $startAt, $endAt, $strictBuffer, $venueId);
    }

    protected function querySingleAvailablePhysicalRoom(
        int $minCapacity,
        string $startAt,
        string $endAt,
        int $bufferMinutes,
        ?int $venueId = null
    ): ?array {
        return $this->executeQuery(function () use ($minCapacity, $startAt, $endAt, $bufferMinutes, $venueId): ?array {
            $venueSql = ($venueId !== null && $venueId > 0) ? ' AND pr.`venue_id` = :target_venue_id' : '';
            $stmt = $this->pdo->prepare(
                'SELECT pr.`id`, pr.`venue_id`, pr.`name`, pr.`capacity`
                 FROM `physical_rooms` pr
                 INNER JOIN `venues` v ON v.`id` = pr.`venue_id` AND v.`status` = \'ACTIVE\'' . $venueSql . '
                 WHERE pr.`status` = \'ACTIVE\'
                   AND pr.`capacity` >= :min_capacity
                   AND (pr.`is_maintenance` = 0 OR pr.`is_maintenance` IS NULL)
                   AND NOT EXISTS (
                       SELECT 1 FROM `sessions` s
                       WHERE s.`physical_room_id` = pr.`id`
                         AND s.`status` IN (\'SCHEDULED\', \'IN_PROGRESS\')
                         AND s.`start_at` < DATE_ADD(:end_at1, INTERVAL :buf1 MINUTE)
                         AND s.`end_at` > DATE_SUB(:start_at1, INTERVAL :buf2 MINUTE)
                   )
                   AND NOT EXISTS (
                       SELECT 1 FROM `offline_room_blocks` orb
                       WHERE orb.`physical_room_id` = pr.`id`
                         AND orb.`start_at` < DATE_ADD(:end_at2, INTERVAL :buf3 MINUTE)
                         AND orb.`end_at` > DATE_SUB(:start_at2, INTERVAL :buf4 MINUTE)
                   )
                 ORDER BY pr.`capacity` ASC, pr.`id` ASC
                 LIMIT 1'
            );
            $stmt->bindValue(':min_capacity', max(1, $minCapacity), PDO::PARAM_INT);
            $stmt->bindValue(':end_at1', $endAt, PDO::PARAM_STR);
            $stmt->bindValue(':start_at1', $startAt, PDO::PARAM_STR);
            $stmt->bindValue(':end_at2', $endAt, PDO::PARAM_STR);
            $stmt->bindValue(':start_at2', $startAt, PDO::PARAM_STR);
            $stmt->bindValue(':buf1', max(0, $bufferMinutes), PDO::PARAM_INT);
            $stmt->bindValue(':buf2', max(0, $bufferMinutes), PDO::PARAM_INT);
            $stmt->bindValue(':buf3', max(0, $bufferMinutes), PDO::PARAM_INT);
            $stmt->bindValue(':buf4', max(0, $bufferMinutes), PDO::PARAM_INT);
            if ($venueId !== null && $venueId > 0) {
                $stmt->bindValue(':target_venue_id', $venueId, PDO::PARAM_INT);
            }
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result !== false ? $result : null;
        });
    }

    /**
     * Find an alternative available physical room in the same venue excluding the current room.
     *
     * @return array<string, mixed>|null
     */
    public function findAlternativePhysicalRoom(
        int $venueId,
        int $excludePhysicalRoomId,
        int $minCapacity,
        string $startAt,
        string $endAt,
        int $bufferMinutes = 30
    ): ?array {
        return $this->executeQuery(function () use ($venueId, $excludePhysicalRoomId, $minCapacity, $startAt, $endAt, $bufferMinutes): ?array {
            $strictBuffer = max(30, $bufferMinutes);
            $stmt = $this->pdo->prepare(
                'SELECT pr.`id`, pr.`venue_id`, pr.`name`, pr.`capacity`
                 FROM `physical_rooms` pr
                 INNER JOIN `venues` v ON v.`id` = pr.`venue_id` AND v.`status` = \'ACTIVE\'
                 WHERE pr.`status` = \'ACTIVE\'
                   AND pr.`venue_id` = :venue_id
                   AND pr.`id` != :exclude_id
                   AND pr.`capacity` >= :min_capacity
                   AND (pr.`is_maintenance` = 0 OR pr.`is_maintenance` IS NULL)
                   AND NOT EXISTS (
                       SELECT 1 FROM `sessions` s
                       WHERE s.`physical_room_id` = pr.`id`
                         AND s.`status` IN (\'SCHEDULED\', \'IN_PROGRESS\')
                         AND s.`start_at` < DATE_ADD(:end_at1, INTERVAL :buf1 MINUTE)
                         AND s.`end_at` > DATE_SUB(:start_at1, INTERVAL :buf2 MINUTE)
                   )
                   AND NOT EXISTS (
                       SELECT 1 FROM `offline_room_blocks` orb
                       WHERE orb.`physical_room_id` = pr.`id`
                         AND orb.`start_at` < DATE_ADD(:end_at2, INTERVAL :buf3 MINUTE)
                         AND orb.`end_at` > DATE_SUB(:start_at2, INTERVAL :buf4 MINUTE)
                   )
                 ORDER BY pr.`capacity` ASC, pr.`id` ASC
                 LIMIT 1'
            );
            $stmt->bindValue(':venue_id', $venueId, PDO::PARAM_INT);
            $stmt->bindValue(':exclude_id', $excludePhysicalRoomId, PDO::PARAM_INT);
            $stmt->bindValue(':min_capacity', max(1, $minCapacity), PDO::PARAM_INT);
            $stmt->bindValue(':end_at1', $endAt, PDO::PARAM_STR);
            $stmt->bindValue(':start_at1', $startAt, PDO::PARAM_STR);
            $stmt->bindValue(':end_at2', $endAt, PDO::PARAM_STR);
            $stmt->bindValue(':start_at2', $startAt, PDO::PARAM_STR);
            $stmt->bindValue(':buf1', $strictBuffer, PDO::PARAM_INT);
            $stmt->bindValue(':buf2', $strictBuffer, PDO::PARAM_INT);
            $stmt->bindValue(':buf3', $strictBuffer, PDO::PARAM_INT);
            $stmt->bindValue(':buf4', $strictBuffer, PDO::PARAM_INT);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result !== false ? $result : null;
        });
    }

    /**
     * Find an active DM user who does not have an overlapping session.
     */
    public function findAvailableDmUserId(string $startAt, string $endAt, int $bufferMinutes = 30): ?int
    {
        return $this->executeQuery(function () use ($startAt, $endAt, $bufferMinutes): ?int {
            $stmt = $this->pdo->prepare(
                'SELECT u.`id`
                 FROM `users` u
                 INNER JOIN `roles` r ON r.`id` = u.`role_id`
                 WHERE r.`code` = \'DM\'
                   AND u.`status` = \'ACTIVE\'
                   AND NOT EXISTS (
                       SELECT 1
                       FROM `sessions` s
                       INNER JOIN `dm_assignments` da ON da.`id` = s.`dm_assignment_id`
                       WHERE da.`dm_user_id` = u.`id`
                         AND s.`status` IN (\'SCHEDULED\', \'IN_PROGRESS\')
                         AND s.`start_at` < DATE_ADD(:end_at, INTERVAL :buf1 MINUTE)
                         AND s.`end_at` > DATE_SUB(:start_at, INTERVAL :buf2 MINUTE)
                   )
                 ORDER BY u.`id` ASC
                 LIMIT 1'
            );
            $stmt->bindValue(':end_at', $endAt, PDO::PARAM_STR);
            $stmt->bindValue(':start_at', $startAt, PDO::PARAM_STR);
            $stmt->bindValue(':buf1', max(0, $bufferMinutes), PDO::PARAM_INT);
            $stmt->bindValue(':buf2', max(0, $bufferMinutes), PDO::PARAM_INT);
            $stmt->execute();

            $id = $stmt->fetchColumn();
            if ($id !== false && $id !== null) {
                return (int) $id;
            }

            // Fallback: bất kỳ DM ACTIVE nào nếu tất cả đều bận (để Admin điều phối lại)
            $fallback = $this->pdo->query(
                'SELECT u.`id` FROM `users` u INNER JOIN `roles` r ON r.`id` = u.`role_id`
                 WHERE r.`code` = \'DM\' AND u.`status` = \'ACTIVE\' ORDER BY u.`id` ASC LIMIT 1'
            );
            $fallbackId = $fallback !== false ? $fallback->fetchColumn() : false;
            return $fallbackId !== false && $fallbackId !== null ? (int) $fallbackId : null;
        });
    }
}