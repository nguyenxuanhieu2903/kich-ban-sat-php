<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateVenueAndSchedulingTables extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<SQL
        SET FOREIGN_KEY_CHECKS = 0;

        -- 16. dm_profiles: Dungeon Master capacity & ratings
        CREATE TABLE IF NOT EXISTS `dm_profiles` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` BIGINT UNSIGNED NOT NULL,
            `experience_years` INT UNSIGNED NOT NULL DEFAULT 0,
            `bio` TEXT NULL,
            `rating_avg` DECIMAL(3, 2) NOT NULL DEFAULT 5.00,
            `status` VARCHAR(30) NOT NULL DEFAULT 'ACTIVE',
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_dm_profiles_user_id` (`user_id`),
            CONSTRAINT `fk_dm_profiles_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT `chk_dm_rating` CHECK (`rating_avg` >= 0.00 AND `rating_avg` <= 5.00)
        ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

        -- 17. dm_availabilities: Working schedules and open slots
        CREATE TABLE IF NOT EXISTS `dm_availabilities` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `dm_profile_id` BIGINT UNSIGNED NOT NULL,
            `day_of_week` TINYINT UNSIGNED NOT NULL,
            `start_time` TIME NOT NULL,
            `end_time` TIME NOT NULL,
            `specific_date` DATE NULL,
            `is_available` BOOLEAN NOT NULL DEFAULT TRUE,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `idx_dm_avail_lookup` (`dm_profile_id`, `day_of_week`, `is_available`),
            CONSTRAINT `fk_dm_availabilities_dm` FOREIGN KEY (`dm_profile_id`) REFERENCES `dm_profiles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT `chk_dm_avail_day` CHECK (`day_of_week` BETWEEN 0 AND 6),
            CONSTRAINT `chk_dm_avail_times` CHECK (`start_time` < `end_time`)
        ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

        -- 18. dm_assignments: Room-to-DM assignment
        -- [VIRTUAL GENERATED COLUMN 1]: active_assigned_room_id ensures max 1 active DM per room
        CREATE TABLE IF NOT EXISTS `dm_assignments` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `game_room_id` BIGINT UNSIGNED NOT NULL,
            `dm_profile_id` BIGINT UNSIGNED NOT NULL,
            `status` VARCHAR(40) NOT NULL DEFAULT 'ASSIGNED',
            `assigned_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `confirmed_at` TIMESTAMP NULL,
            `notes` TEXT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `active_assigned_room_id` BIGINT UNSIGNED GENERATED ALWAYS AS (
                CASE
                    WHEN `status` IN ('ASSIGNED', 'CONFIRMED') THEN `game_room_id`
                    ELSE NULL
                END
            ) VIRTUAL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_active_dm_assignment` (`active_assigned_room_id`),
            INDEX `idx_dm_assignments_dm_status` (`dm_profile_id`, `status`),
            INDEX `idx_dm_assignments_room` (`game_room_id`),
            CONSTRAINT `fk_dm_assignments_room` FOREIGN KEY (`game_room_id`) REFERENCES `game_rooms` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT `fk_dm_assignments_dm` FOREIGN KEY (`dm_profile_id`) REFERENCES `dm_profiles` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
        ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

        -- 19. physical_rooms: Brick-and-mortar rooms / tables
        CREATE TABLE IF NOT EXISTS `physical_rooms` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `code` VARCHAR(32) NOT NULL,
            `name` VARCHAR(100) NOT NULL,
            `capacity` INT UNSIGNED NOT NULL DEFAULT 8,
            `status` VARCHAR(30) NOT NULL DEFAULT 'AVAILABLE',
            `description` TEXT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_physical_rooms_code` (`code`),
            CONSTRAINT `chk_physical_rooms_capacity` CHECK (`capacity` > 0)
        ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

        -- 20. schedule_proposals: Time negotiation proposals
        -- [VIRTUAL GENERATED COLUMN 4]: proposed_game_room_id ensures max 1 active PROPOSED schedule per room
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

        -- 21. schedule_responses: Participant votes on schedule proposals
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

        -- 22. sessions: Materialized TRPG play sessions
        CREATE TABLE IF NOT EXISTS `sessions` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `game_room_id` BIGINT UNSIGNED NOT NULL,
            `dm_assignment_id` BIGINT UNSIGNED NULL,
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
            INDEX `idx_sessions_date_status` (`scheduled_date`, `status`),
            CONSTRAINT `fk_sessions_room` FOREIGN KEY (`game_room_id`) REFERENCES `game_rooms` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT `fk_sessions_dm` FOREIGN KEY (`dm_assignment_id`) REFERENCES `dm_assignments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
            CONSTRAINT `chk_sessions_times` CHECK (`start_time` < `end_time`)
        ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

        -- 23. physical_room_reservations: Physical table reservations
        -- [VIRTUAL GENERATED COLUMN 5]: active_session_id ensures max 1 active reservation per session
        CREATE TABLE IF NOT EXISTS `physical_room_reservations` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `physical_room_id` BIGINT UNSIGNED NOT NULL,
            `session_id` BIGINT UNSIGNED NOT NULL,
            `reservation_date` DATE NOT NULL,
            `start_time` TIME NOT NULL,
            `end_time` TIME NOT NULL,
            `status` VARCHAR(30) NOT NULL DEFAULT 'RESERVED',
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `active_session_id` BIGINT UNSIGNED GENERATED ALWAYS AS (
                CASE
                    WHEN `status` IN ('RESERVED', 'CONFIRMED') THEN `session_id`
                    ELSE NULL
                END
            ) VIRTUAL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_session_active_reservation` (`active_session_id`),
            INDEX `idx_prr_schedule_check` (`physical_room_id`, `reservation_date`, `status`, `start_time`, `end_time`),
            INDEX `idx_prr_session` (`session_id`),
            CONSTRAINT `fk_prr_physical_room` FOREIGN KEY (`physical_room_id`) REFERENCES `physical_rooms` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT `fk_prr_session` FOREIGN KEY (`session_id`) REFERENCES `sessions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT `chk_prr_times` CHECK (`start_time` < `end_time`)
        ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

        SET FOREIGN_KEY_CHECKS = 1;
        SQL);
    }

    public function down(): void
    {
        $this->execute(<<<SQL
        SET FOREIGN_KEY_CHECKS = 0;
        DROP TABLE IF EXISTS `physical_room_reservations`;
        DROP TABLE IF EXISTS `sessions`;
        DROP TABLE IF EXISTS `schedule_responses`;
        DROP TABLE IF EXISTS `schedule_proposals`;
        DROP TABLE IF EXISTS `physical_rooms`;
        DROP TABLE IF EXISTS `dm_assignments`;
        DROP TABLE IF EXISTS `dm_availabilities`;
        DROP TABLE IF EXISTS `dm_profiles`;
        SET FOREIGN_KEY_CHECKS = 1;
        SQL);
    }
}
