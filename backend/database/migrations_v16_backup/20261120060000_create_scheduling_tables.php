<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateSchedulingTables extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<SQL
        SET FOREIGN_KEY_CHECKS = 0;

        -- 11. schedule_proposals: Time negotiation proposals
        CREATE TABLE IF NOT EXISTS `schedule_proposals` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `game_room_id` BIGINT UNSIGNED NOT NULL,
            `proposed_by_user_id` BIGINT UNSIGNED NOT NULL,
            `proposed_date` DATE NOT NULL,
            `start_time` TIME NOT NULL,
            `end_time` TIME NOT NULL,
            `status` VARCHAR(30) NOT NULL DEFAULT 'PROPOSED',
            `deadline` TIMESTAMP NOT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `proposed_game_room_id` BIGINT UNSIGNED GENERATED ALWAYS AS (
                CASE
                    WHEN `status` = 'PROPOSED' THEN `game_room_id`
                    ELSE NULL
                END
            ) VIRTUAL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_active_proposed_schedule` (`proposed_game_room_id`),
            INDEX `idx_schedule_proposals_room_status` (`game_room_id`, `status`),
            INDEX `idx_schedule_proposals_proposer` (`proposed_by_user_id`),
            CONSTRAINT `fk_schedule_proposals_room` FOREIGN KEY (`game_room_id`) REFERENCES `game_rooms` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT `fk_schedule_proposals_user` FOREIGN KEY (`proposed_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT `chk_schedule_proposals_times` CHECK (`start_time` < `end_time`)
        ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

        -- 12. schedule_responses: Participant votes on schedule proposals
        CREATE TABLE IF NOT EXISTS `schedule_responses` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `schedule_proposal_id` BIGINT UNSIGNED NOT NULL,
            `room_participant_id` BIGINT UNSIGNED NOT NULL,
            `response` VARCHAR(20) NOT NULL,
            `note` VARCHAR(255) NULL,
            `responded_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_schedule_responses_vote` (`schedule_proposal_id`, `room_participant_id`),
            INDEX `idx_schedule_responses_participant` (`room_participant_id`),
            CONSTRAINT `fk_schedule_responses_proposal` FOREIGN KEY (`schedule_proposal_id`) REFERENCES `schedule_proposals` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT `fk_schedule_responses_participant` FOREIGN KEY (`room_participant_id`) REFERENCES `room_participants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

        -- 13. sessions: Materialized TRPG play sessions
        CREATE TABLE IF NOT EXISTS `sessions` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `game_room_id` BIGINT UNSIGNED NOT NULL,
            `dm_assignment_id` BIGINT UNSIGNED NULL,
            `physical_room_id` BIGINT UNSIGNED NULL,
            `session_code` VARCHAR(32) NOT NULL,
            `scheduled_date` DATE NOT NULL,
            `start_time` TIME NOT NULL,
            `end_time` TIME NOT NULL,
            `actual_start_time` TIMESTAMP NULL,
            `actual_end_time` TIMESTAMP NULL,
            `status` VARCHAR(40) NOT NULL DEFAULT 'SCHEDULED',
            `cancellation_reason` TEXT NULL,
            `version` BIGINT UNSIGNED NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_sessions_code` (`session_code`),
            INDEX `idx_sessions_room` (`game_room_id`),
            INDEX `idx_sessions_dm` (`dm_assignment_id`),
            INDEX `idx_sessions_room_schedule` (`physical_room_id`, `scheduled_date`, `status`, `start_time`, `end_time`),
            CONSTRAINT `fk_sessions_room` FOREIGN KEY (`game_room_id`) REFERENCES `game_rooms` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT `fk_sessions_dm` FOREIGN KEY (`dm_assignment_id`) REFERENCES `dm_assignments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
            CONSTRAINT `fk_sessions_physical_room` FOREIGN KEY (`physical_room_id`) REFERENCES `physical_rooms` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
            CONSTRAINT `chk_sessions_times` CHECK (`start_time` < `end_time`)
        ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

        SET FOREIGN_KEY_CHECKS = 1;
        SQL);
    }

    public function down(): void
    {
        $this->execute(<<<SQL
        SET FOREIGN_KEY_CHECKS = 0;
        DROP TABLE IF EXISTS `sessions`;
        DROP TABLE IF EXISTS `schedule_responses`;
        DROP TABLE IF EXISTS `schedule_proposals`;
        SET FOREIGN_KEY_CHECKS = 1;
        SQL);
    }
}
