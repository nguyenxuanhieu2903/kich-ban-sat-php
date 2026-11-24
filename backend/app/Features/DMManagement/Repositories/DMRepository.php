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
     * Revokes any previous PENDING assignments for this room to avoid orphaned pending records.
     */
    public function assignToRoom(int $gameRoomId, int $dmUserId, ?string $reason = null, int $assignedBy = 1): int
    {
        return $this->executeQuery(function () use ($gameRoomId, $dmUserId, $reason, $assignedBy): int {
            // Revoke any existing PENDING assignment for this game room
            $revokeStmt = $this->pdo->prepare(
                'UPDATE `dm_assignments`
                 SET `status` = \'REVOKED\', `revoked_at` = NOW(),
                     `reason` = \'Thu hồi do phân công quản trò mới\', `updated_at` = NOW()
                 WHERE `game_room_id` = :game_room_id AND `status` = \'PENDING\''
            );
            $revokeStmt->bindValue(':game_room_id', $gameRoomId, PDO::PARAM_INT);
            $revokeStmt->execute();

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
                        g.`id` AS `game_id`, g.`name` AS `game_name`, g.`name` AS `game_title`,
                        u.`full_name` AS `dm_name`, u.`email` AS `dm_email`,
                        s.`id` AS `session_id`, s.`status` AS `session_status`,
                        COALESCE(s.`start_at`, sp.`proposed_start_at`) AS `session_start_at`,
                        COALESCE(s.`end_at`, sp.`proposed_end_at`) AS `session_end_at`,
                        v.`name` AS `venue_name`, pr.`name` AS `room_name`
                 FROM `dm_assignments` a
                 INNER JOIN `game_rooms` gr ON gr.`id` = a.`game_room_id`
                 INNER JOIN `games` g ON g.`id` = gr.`game_id`
                 INNER JOIN `users` u ON u.`id` = a.`dm_user_id`
                 LEFT JOIN `sessions` s ON s.`game_room_id` = a.`game_room_id` AND s.`status` != \'CANCELLED\'
                 LEFT JOIN (
                     SELECT sp1.`game_room_id`, sp1.`proposed_start_at`, sp1.`proposed_end_at`
                     FROM `schedule_proposals` sp1
                     INNER JOIN (
                         SELECT `game_room_id`, MAX(`id`) AS `max_id`
                         FROM `schedule_proposals`
                         WHERE `status` IN (\'CONFIRMED\', \'PROPOSED\')
                         GROUP BY `game_room_id`
                     ) sp_latest ON sp1.`id` = sp_latest.`max_id`
                 ) sp ON sp.`game_room_id` = a.`game_room_id`
                 LEFT JOIN `venues` v ON v.`id` = s.`venue_id`
                 LEFT JOIN `physical_rooms` pr ON pr.`id` = s.`physical_room_id`
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
                           g.`id` AS `game_id`, g.`name` AS `game_name`, g.`name` AS `game_title`,
                           u.`full_name` AS `dm_name`,
                           s.`id` AS `session_id`, s.`status` AS `session_status`,
                           COALESCE(s.`start_at`, sp.`proposed_start_at`) AS `session_start_at`,
                           COALESCE(s.`end_at`, sp.`proposed_end_at`) AS `session_end_at`,
                           v.`name` AS `venue_name`, pr.`name` AS `room_name`
                    FROM `dm_assignments` a
                    INNER JOIN `game_rooms` gr ON gr.`id` = a.`game_room_id`
                    INNER JOIN `games` g ON g.`id` = gr.`game_id`
                    INNER JOIN `users` u ON u.`id` = a.`dm_user_id`
                    LEFT JOIN `sessions` s ON s.`game_room_id` = a.`game_room_id` AND s.`status` != 'CANCELLED'
                    LEFT JOIN (
                        SELECT sp1.`game_room_id`, sp1.`proposed_start_at`, sp1.`proposed_end_at`
                        FROM `schedule_proposals` sp1
                        INNER JOIN (
                            SELECT `game_room_id`, MAX(`id`) AS `max_id`
                            FROM `schedule_proposals`
                            WHERE `status` IN ('CONFIRMED', 'PROPOSED')
                            GROUP BY `game_room_id`
                        ) sp_latest ON sp1.`id` = sp_latest.`max_id`
                    ) sp ON sp.`game_room_id` = a.`game_room_id`
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
     * Check if a DM has another ACCEPTED assignment with overlapping schedule.
     *
     * @return array<string, mixed>|null Returns conflict information if overlap exists, null otherwise.
     */
    public function checkScheduleConflict(int $dmUserId, string $startAt, string $endAt, ?int $excludeAssignmentId = null): ?array
    {
        return $this->executeQuery(function () use ($dmUserId, $startAt, $endAt, $excludeAssignmentId): ?array {
            // 1. Check against active sessions linked to accepted assignments of this DM
            $sql1 = 'SELECT a.`id` AS `assignment_id`, a.`game_room_id`, s.`id` AS `session_id`,
                            s.`start_at`, s.`end_at`, gr.`title` AS `room_title`
                     FROM `dm_assignments` a
                     INNER JOIN `sessions` s ON s.`dm_assignment_id` = a.`id` AND s.`game_room_id` = a.`game_room_id`
                     INNER JOIN `game_rooms` gr ON gr.`id` = a.`game_room_id`
                     WHERE a.`dm_user_id` = :dm_user_id
                       AND a.`status` = \'ACCEPTED\'
                       AND s.`status` NOT IN (\'CANCELLED\')
                       AND s.`start_at` < :end_at
                       AND s.`end_at` > :start_at';
            if ($excludeAssignmentId !== null) {
                $sql1 .= ' AND a.`id` != :exclude_id';
            }
            $sql1 .= ' LIMIT 1';

            $stmt1 = $this->pdo->prepare($sql1);
            $stmt1->bindValue(':dm_user_id', $dmUserId, PDO::PARAM_INT);
            $stmt1->bindValue(':start_at', $startAt, PDO::PARAM_STR);
            $stmt1->bindValue(':end_at', $endAt, PDO::PARAM_STR);
            if ($excludeAssignmentId !== null) {
                $stmt1->bindValue(':exclude_id', $excludeAssignmentId, PDO::PARAM_INT);
            }
            $stmt1->execute();
            $conflict1 = $stmt1->fetch(PDO::FETCH_ASSOC);
            if ($conflict1 !== false) {
                return $conflict1;
            }

            // 2. Check against confirmed schedule proposals linked to accepted assignments of this DM
            $sql2 = 'SELECT a.`id` AS `assignment_id`, a.`game_room_id`, sp.`id` AS `proposal_id`,
                            sp.`proposed_start_at` AS `start_at`, sp.`proposed_end_at` AS `end_at`, gr.`title` AS `room_title`
                     FROM `dm_assignments` a
                     INNER JOIN `schedule_proposals` sp ON sp.`game_room_id` = a.`game_room_id` AND sp.`status` = \'CONFIRMED\'
                     INNER JOIN `game_rooms` gr ON gr.`id` = a.`game_room_id`
                     WHERE a.`dm_user_id` = :dm_user_id
                       AND a.`status` = \'ACCEPTED\'
                       AND sp.`proposed_start_at` < :end_at
                       AND sp.`proposed_end_at` > :start_at';
            if ($excludeAssignmentId !== null) {
                $sql2 .= ' AND a.`id` != :exclude_id';
            }
            $sql2 .= ' LIMIT 1';

            $stmt2 = $this->pdo->prepare($sql2);
            $stmt2->bindValue(':dm_user_id', $dmUserId, PDO::PARAM_INT);
            $stmt2->bindValue(':start_at', $startAt, PDO::PARAM_STR);
            $stmt2->bindValue(':end_at', $endAt, PDO::PARAM_STR);
            if ($excludeAssignmentId !== null) {
                $stmt2->bindValue(':exclude_id', $excludeAssignmentId, PDO::PARAM_INT);
            }
            $stmt2->execute();
            $conflict2 = $stmt2->fetch(PDO::FETCH_ASSOC);
            return $conflict2 !== false ? $conflict2 : null;
        });
    }

    /**
     * Get scheduled start and end time for an assignment.
     *
     * @return array{start_at: string, end_at: string}|null
     */
    public function getAssignmentScheduleTiming(int $assignmentId): ?array
    {
        return $this->executeQuery(function () use ($assignmentId): ?array {
            // First check sessions
            $stmt = $this->pdo->prepare(
                'SELECT s.`start_at`, s.`end_at`
                 FROM `sessions` s
                 INNER JOIN `dm_assignments` da ON da.`id` = s.`dm_assignment_id` AND da.`game_room_id` = s.`game_room_id`
                 WHERE da.`id` = :id AND s.`status` != \'CANCELLED\'
                 LIMIT 1'
            );
            $stmt->bindValue(':id', $assignmentId, PDO::PARAM_INT);
            $stmt->execute();
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($res !== false) {
                return ['start_at' => (string) $res['start_at'], 'end_at' => (string) $res['end_at']];
            }

            // Next check schedule proposals
            $stmt2 = $this->pdo->prepare(
                'SELECT sp.`proposed_start_at` AS `start_at`, sp.`proposed_end_at` AS `end_at`
                 FROM `schedule_proposals` sp
                 INNER JOIN `dm_assignments` da ON da.`game_room_id` = sp.`game_room_id`
                 WHERE da.`id` = :id AND sp.`status` IN (\'CONFIRMED\', \'PROPOSED\')
                 ORDER BY CASE WHEN sp.`status` = \'CONFIRMED\' THEN 1 ELSE 2 END, sp.`id` DESC
                 LIMIT 1'
            );
            $stmt2->bindValue(':id', $assignmentId, PDO::PARAM_INT);
            $stmt2->execute();
            $res2 = $stmt2->fetch(PDO::FETCH_ASSOC);
            if ($res2 !== false) {
                return ['start_at' => (string) $res2['start_at'], 'end_at' => (string) $res2['end_at']];
            }

            return null;
        });
    }

    /**
     * Get scheduled start and end time for a game room.
     *
     * @return array{start_at: string, end_at: string}|null
     */
    public function getAssignmentScheduleTimingForRoom(int $gameRoomId): ?array
    {
        return $this->executeQuery(function () use ($gameRoomId): ?array {
            $stmt = $this->pdo->prepare(
                'SELECT s.`start_at`, s.`end_at`
                 FROM `sessions` s
                 WHERE s.`game_room_id` = :room_id AND s.`status` != \'CANCELLED\'
                 LIMIT 1'
            );
            $stmt->bindValue(':room_id', $gameRoomId, PDO::PARAM_INT);
            $stmt->execute();
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($res !== false) {
                return ['start_at' => (string) $res['start_at'], 'end_at' => (string) $res['end_at']];
            }

            $stmt2 = $this->pdo->prepare(
                'SELECT sp.`proposed_start_at` AS `start_at`, sp.`proposed_end_at` AS `end_at`
                 FROM `schedule_proposals` sp
                 WHERE sp.`game_room_id` = :room_id AND sp.`status` IN (\'CONFIRMED\', \'PROPOSED\')
                 ORDER BY CASE WHEN sp.`status` = \'CONFIRMED\' THEN 1 ELSE 2 END, sp.`id` DESC
                 LIMIT 1'
            );
            $stmt2->bindValue(':room_id', $gameRoomId, PDO::PARAM_INT);
            $stmt2->execute();
            $res2 = $stmt2->fetch(PDO::FETCH_ASSOC);
            if ($res2 !== false) {
                return ['start_at' => (string) $res2['start_at'], 'end_at' => (string) $res2['end_at']];
            }

            return null;
        });
    }

    /**
     * Remove or unlink any Session referencing a specific assignment.
     * Guarantees no session references an invalid/rejected/revoked assignment.
     */
    public function unlinkOrRemoveSessionForAssignment(int $assignmentId): void
    {
        $this->executeQuery(function () use ($assignmentId): void {
            $stmt = $this->pdo->prepare('DELETE FROM `sessions` WHERE `dm_assignment_id` = :id');
            $stmt->bindValue(':id', $assignmentId, PDO::PARAM_INT);
            $stmt->execute();
        });
    }

    /**
     * Link an accepted DM assignment to a room's Session.
     * If session doesn't exist yet but room has a confirmed proposal, creates the session.
     */
    public function linkSessionToAssignment(int $gameRoomId, int $assignmentId): ?int
    {
        return $this->executeQuery(function () use ($gameRoomId, $assignmentId): ?int {
            // Check if session exists for this room
            $chk = $this->pdo->prepare('SELECT `id` FROM `sessions` WHERE `game_room_id` = :room_id LIMIT 1');
            $chk->bindValue(':room_id', $gameRoomId, PDO::PARAM_INT);
            $chk->execute();
            $sessionId = $chk->fetchColumn();

            if ($sessionId !== false) {
                $up = $this->pdo->prepare('UPDATE `sessions` SET `dm_assignment_id` = :aid, `updated_at` = NOW() WHERE `id` = :sid');
                $up->bindValue(':aid', $assignmentId, PDO::PARAM_INT);
                $up->bindValue(':sid', (int) $sessionId, PDO::PARAM_INT);
                $up->execute();
                return (int) $sessionId;
            }

            // Check if room has confirmed schedule proposal
            $propStmt = $this->pdo->prepare('SELECT `id`, `proposed_start_at`, `proposed_end_at` FROM `schedule_proposals` WHERE `game_room_id` = :room_id AND `status` = \'CONFIRMED\' LIMIT 1');
            $propStmt->bindValue(':room_id', $gameRoomId, PDO::PARAM_INT);
            $propStmt->execute();
            $prop = $propStmt->fetch(PDO::FETCH_ASSOC);

            if ($prop !== false) {
                $ins = $this->pdo->prepare(
                    'INSERT INTO `sessions` (`game_room_id`, `schedule_proposal_id`, `dm_assignment_id`, `venue_id`, `physical_room_id`, `start_at`, `end_at`, `status`, `created_at`, `updated_at`)
                     VALUES (:room_id, :proposal_id, :dm_id, 1, 1, :start_at, :end_at, \'SCHEDULED\', NOW(), NOW())'
                );
                $ins->bindValue(':room_id', $gameRoomId, PDO::PARAM_INT);
                $ins->bindValue(':proposal_id', (int) $prop['id'], PDO::PARAM_INT);
                $ins->bindValue(':dm_id', $assignmentId, PDO::PARAM_INT);
                $ins->bindValue(':start_at', $prop['proposed_start_at'], PDO::PARAM_STR);
                $ins->bindValue(':end_at', $prop['proposed_end_at'], PDO::PARAM_STR);
                $ins->execute();
                return (int) $this->pdo->lastInsertId();
            }

            return null;
        });
    }
}
