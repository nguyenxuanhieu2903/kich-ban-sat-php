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

    /**
     * Find assignment by ID with game and room details.
     *
     * @return array<string, mixed>|null
     */
    public function findAssignmentById(int $id): ?array
    {
        return $this->executeQuery(function () use ($id): ?array {
            $stmt = $this->pdo->prepare(
                'SELECT a.`id`, a.`game_room_id`, a.`dm_user_id`, a.`assigned_by`, a.`status`,
                        a.`assigned_at`, a.`responded_at`, a.`revoked_at`, a.`reason`,
                        a.`created_at`, a.`updated_at`,
                        gr.`title` AS `room_title`, gr.`status` AS `room_status`,
                        g.`id` AS `game_id`, g.`name` AS `game_name`,
                        u.`full_name` AS `dm_name`, u.`email` AS `dm_email`
                 FROM `dm_assignments` a
                 INNER JOIN `game_rooms` gr ON gr.`id` = a.`game_room_id`
                 INNER JOIN `games` g ON g.`id` = gr.`game_id`
                 INNER JOIN `users` u ON u.`id` = a.`dm_user_id`
                 WHERE a.`id` = :id
                 LIMIT 1'
            );
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result !== false ? $result : null;
        });
    }

    /**
     * List assignments filtered by DM user ID or status.
     *
     * @return list<array<string, mixed>>
     */
    public function listAssignments(?int $dmUserId = null, ?string $status = null): array
    {
        return $this->executeQuery(function () use ($dmUserId, $status): array {
            $where = ['1 = 1'];
            $params = [];

            if ($dmUserId !== null && $dmUserId > 0) {
                $where[] = 'a.`dm_user_id` = :dm_user_id';
                $params[':dm_user_id'] = [$dmUserId, PDO::PARAM_INT];
            }

            if ($status !== null && trim($status) !== '' && $status !== 'ALL') {
                $where[] = 'a.`status` = :status';
                $params[':status'] = [strtoupper(trim($status)), PDO::PARAM_STR];
            }

            $whereSql = implode(' AND ', $where);
            $sql = "SELECT a.`id`, a.`game_room_id`, a.`dm_user_id`, a.`assigned_by`, a.`status`,
                           a.`assigned_at`, a.`responded_at`, a.`revoked_at`, a.`reason`,
                           a.`created_at`, a.`updated_at`,
                           gr.`title` AS `room_title`, gr.`status` AS `room_status`,
                           gr.`min_players`, gr.`max_players`, gr.`total_players`,
                           gr.`creator_id`, gr.`booking_type`, gr.`guest_slots`, gr.`target_time_slot`, gr.`price_details`,
                           g.`id` AS `game_id`, g.`name` AS `game_name`, g.`duration_minutes`,
                           gc.`configuration` AS `game_configuration`,
                           u.`full_name` AS `dm_name`,
                           s.`id` AS `session_id`, s.`status` AS `session_status`,
                           s.`start_at`, s.`end_at`, s.`started_at`, s.`completed_at`,
                           v.`name` AS `venue_name`, pr.`name` AS `physical_room_name`
                    FROM `dm_assignments` a
                    INNER JOIN `game_rooms` gr ON gr.`id` = a.`game_room_id`
                    INNER JOIN `games` g ON g.`id` = gr.`game_id`
                    LEFT JOIN `game_configurations` gc ON gc.`id` = gr.`game_configuration_id`
                    INNER JOIN `users` u ON u.`id` = a.`dm_user_id`
                    LEFT JOIN `sessions` s ON s.`game_room_id` = a.`game_room_id`
                    LEFT JOIN `venues` v ON v.`id` = s.`venue_id`
                    LEFT JOIN `physical_rooms` pr ON pr.`id` = s.`physical_room_id`
                    WHERE {$whereSql}
                    ORDER BY a.`id` DESC";

            $stmt = $this->pdo->prepare($sql);
            foreach ($params as $param => [$val, $type]) {
                $stmt->bindValue($param, $val, $type);
            }
            $stmt->execute();

            /** @var list<array<string, mixed>> $rows */
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($rows)) {
                $roomIds = array_values(array_unique(array_map(static fn (array $r): int => (int) $r['game_room_id'], $rows)));
                $placeholders = implode(',', array_fill(0, count($roomIds), '?'));
                $pStmt = $this->pdo->prepare(
                    "SELECT `game_room_id`, `user_id`
                     FROM `room_participants`
                     WHERE `game_room_id` IN ({$placeholders})
                       AND `status` IN ('PENDING_DEPOSIT', 'DEPOSITED', 'LEAVE_REQUESTED')"
                );
                foreach ($roomIds as $idx => $rid) {
                    $pStmt->bindValue($idx + 1, $rid, PDO::PARAM_INT);
                }
                $pStmt->execute();
                $partsByRoom = [];
                foreach ($pStmt->fetchAll(PDO::FETCH_ASSOC) as $prRow) {
                    $partsByRoom[(int) $prRow['game_room_id']][] = (int) $prRow['user_id'];
                }

                foreach ($rows as &$row) {
                    $rid = (int) $row['game_room_id'];
                    $activeUids = $partsByRoom[$rid] ?? [];
                    $seatCalc = \App\Shared\Services\RoomSeatHelper::computeSeats($row, $activeUids);
                    $occupiedSeats = $seatCalc['occupied_seats'];
                    $minP = max(1, (int) ($row['min_players'] ?? 1));
                    $maxP = max($minP, (int) ($row['max_players'] ?? $minP));

                    $row['current_players'] = $occupiedSeats;
                    $row['min_players'] = $minP;
                    $row['max_players'] = $maxP;
                    $row['has_enough_players'] = $occupiedSeats >= $minP;
                    $row['gameplay_finished'] = strtoupper((string) ($row['session_status'] ?? '')) === 'IN_PROGRESS' && !empty($row['completed_at']);
                    unset($row['price_details']);
                }
                unset($row);
            }

            return $rows;
        });
    }

    /**
     * Update DM assignment response status (ACCEPTED or REJECTED).
     */
    public function respondAssignment(int $id, string $status, ?string $reason = null): bool
    {
        return $this->executeQuery(function () use ($id, $status, $reason): bool {
            $stmt = $this->pdo->prepare(
                'UPDATE `dm_assignments`
                 SET `status` = :status,
                     `responded_at` = NOW(),
                     `reason` = :reason,
                     `updated_at` = NOW()
                 WHERE `id` = :id'
            );
            $stmt->bindValue(':status', strtoupper($status), PDO::PARAM_STR);
            $stmt->bindValue(':reason', $reason, $reason === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->rowCount() > 0;
        });
    }

    /**
     * Revoke DM assignment (Admin operation).
     */
    public function revokeAssignment(int $id, ?string $reason = null): bool
    {
        return $this->executeQuery(function () use ($id, $reason): bool {
            $stmt = $this->pdo->prepare(
                'UPDATE `dm_assignments`
                 SET `status` = \'REVOKED\',
                     `revoked_at` = NOW(),
                     `reason` = COALESCE(:reason, `reason`),
                     `updated_at` = NOW()
                 WHERE `id` = :id'
            );
            $stmt->bindValue(':reason', $reason, $reason === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->rowCount() > 0;
        });
    }
}
