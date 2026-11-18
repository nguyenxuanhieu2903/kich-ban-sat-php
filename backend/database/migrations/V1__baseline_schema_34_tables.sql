-- =============================================================================
-- Migration: V1__baseline_schema_34_tables.sql
-- Project: Booking-project-php (Kịch Bản Sát Tabletop RPG)
-- Target Engine: MySQL 8.4 LTS (Compatible with MySQL 8.0.x / InnoDB)
-- Encoding: utf8mb4 / utf8mb4_unicode_ci
-- Total Tables: 34 across 8 Bounded Contexts
-- =============================================================================
CREATE DATABASE IF NOT EXISTS `booking_project_php` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `booking_project_php`;

SET NAMES utf8mb4;

SET FOREIGN_KEY_CHECKS = 0;

-- =============================================================================
-- CONTEXT 01: IDENTITY & ACCESS (4 TABLES)
-- =============================================================================

-- 1. users: Core user accounts
CREATE TABLE IF NOT EXISTS `users` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(191) NOT NULL,
    `email` VARCHAR(191) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(20) NULL,
    `status` VARCHAR(30) NOT NULL DEFAULT 'ACTIVE',
    `email_verified_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_email` (`email`),
    INDEX `idx_users_status` (`status`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- 2. roles: System authorization roles
CREATE TABLE IF NOT EXISTS `roles` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(50) NOT NULL,
    `label` VARCHAR(100) NOT NULL,
    `description` VARCHAR(255) NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_roles_name` (`name`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- 3. user_profiles: Extended user profile (1:0..1)
CREATE TABLE IF NOT EXISTS `user_profiles` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `display_name` VARCHAR(100) NULL,
    `phone` VARCHAR(20) NULL,
    `avatar_url` VARCHAR(255) NULL,
    `bio` TEXT NULL,
    `date_of_birth` DATE NULL,
    `gender` VARCHAR(20) NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_user_profiles_user_id` (`user_id`),
    CONSTRAINT `fk_user_profiles_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- 4. user_roles: User-Role assignment pivot (N:M)
CREATE TABLE IF NOT EXISTS `user_roles` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `role_id` BIGINT UNSIGNED NOT NULL,
    `assigned_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_user_roles_user_role` (`user_id`, `role_id`),
    INDEX `idx_user_roles_role_id` (`role_id`),
    CONSTRAINT `fk_user_roles_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_user_roles_roles` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- =============================================================================
-- CONTEXT 02: GAME CATALOG (5 TABLES)
-- =============================================================================

-- 5. categories: Game genres/themes
CREATE TABLE IF NOT EXISTS `categories` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_categories_slug` (`slug`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- 6. games: Game scenarios / scripts
CREATE TABLE IF NOT EXISTS `games` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(191) NOT NULL,
    `slug` VARCHAR(191) NOT NULL,
    `description` TEXT NULL,
    `min_players` INT UNSIGNED NOT NULL DEFAULT 4,
    `max_players` INT UNSIGNED NOT NULL DEFAULT 8,
    `duration_minutes` INT UNSIGNED NOT NULL DEFAULT 240,
    `is_active` BOOLEAN NOT NULL DEFAULT TRUE,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_games_slug` (`slug`),
    INDEX `idx_games_status_players` (
        `is_active`,
        `min_players`,
        `max_players`
    ),
    CONSTRAINT `chk_games_players` CHECK (
        `min_players` > 0
        AND `max_players` >= `min_players`
    ),
    CONSTRAINT `chk_games_duration` CHECK (`duration_minutes` > 0)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- 7. game_rules: Game specific rules / guidelines
CREATE TABLE IF NOT EXISTS `game_rules` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `game_id` BIGINT UNSIGNED NOT NULL,
    `rule_name` VARCHAR(191) NOT NULL,
    `rule_content` TEXT NOT NULL,
    `display_order` INT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_game_rules_game_order` (`game_id`, `display_order`),
    CONSTRAINT `fk_game_rules_games` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- 8. game_configurations: Technical props and setup configs
CREATE TABLE IF NOT EXISTS `game_configurations` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `game_id` BIGINT UNSIGNED NOT NULL,
    `config_key` VARCHAR(100) NOT NULL,
    `config_value` TEXT NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_game_configurations_key` (`game_id`, `config_key`),
    CONSTRAINT `fk_game_configurations_games` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- 9. game_category_assignments: Game-Category junction (N:M)
CREATE TABLE IF NOT EXISTS `game_category_assignments` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `game_id` BIGINT UNSIGNED NOT NULL,
    `category_id` BIGINT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_game_category_assignments` (`game_id`, `category_id`),
    INDEX `idx_gca_category_id` (`category_id`),
    CONSTRAINT `fk_gca_games` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_gca_categories` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- =============================================================================
-- CONTEXT 03: PRICING & PROMOTION (3 TABLES)
-- =============================================================================

-- 10. game_prices: Time-band and day-type price schedule
CREATE TABLE IF NOT EXISTS `game_prices` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `game_id` BIGINT UNSIGNED NOT NULL,
    `price_tier_name` VARCHAR(50) NOT NULL,
    `day_type` VARCHAR(20) NOT NULL DEFAULT 'ALL', -- WEEKDAY, WEEKEND, ALL
    `time_from` TIME NOT NULL,
    `time_to` TIME NOT NULL,
    `base_price_per_player` BIGINT UNSIGNED NOT NULL, -- Integer VND
    `is_active` BOOLEAN NOT NULL DEFAULT TRUE,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_game_prices_lookup` (
        `game_id`,
        `day_type`,
        `time_from`,
        `time_to`
    ),
    CONSTRAINT `fk_game_prices_games` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_game_prices_times` CHECK (`time_from` < `time_to`),
    CONSTRAINT `chk_game_prices_amount` CHECK (`base_price_per_player` >= 0)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- 11. promotions: Discount vouchers & marketing campaigns
CREATE TABLE IF NOT EXISTS `promotions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(50) NOT NULL,
    `name` VARCHAR(191) NOT NULL,
    `discount_type` VARCHAR(20) NOT NULL, -- PERCENTAGE, FIXED_AMOUNT
    `discount_value` BIGINT UNSIGNED NOT NULL,
    `min_participants` INT UNSIGNED NOT NULL DEFAULT 1,
    `valid_from` TIMESTAMP NOT NULL,
    `valid_to` TIMESTAMP NOT NULL,
    `non_stackable` BOOLEAN NOT NULL DEFAULT TRUE,
    `usage_limit` INT UNSIGNED NULL,
    `times_used` INT UNSIGNED NOT NULL DEFAULT 0,
    `is_active` BOOLEAN NOT NULL DEFAULT TRUE,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_promotions_code` (`code`),
    CONSTRAINT `chk_promotions_dates` CHECK (`valid_from` <= `valid_to`),
    CONSTRAINT `chk_promotions_value` CHECK (`discount_value` >= 0)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- =============================================================================
-- CONTEXT 04: DM MANAGEMENT (3 TABLES)
-- =============================================================================

-- 12. dm_profiles: Dungeon Master capacity & ratings
CREATE TABLE IF NOT EXISTS `dm_profiles` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `experience_years` INT UNSIGNED NOT NULL DEFAULT 0,
    `bio` TEXT NULL,
    `rating_avg` DECIMAL(3, 2) NOT NULL DEFAULT 5.00,
    `status` VARCHAR(30) NOT NULL DEFAULT 'ACTIVE', -- ACTIVE, INACTIVE
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_dm_profiles_user_id` (`user_id`),
    CONSTRAINT `fk_dm_profiles_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `chk_dm_rating` CHECK (
        `rating_avg` >= 0.00
        AND `rating_avg` <= 5.00
    )
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- 13. dm_availabilities: Working schedules and open slots
CREATE TABLE IF NOT EXISTS `dm_availabilities` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `dm_profile_id` BIGINT UNSIGNED NOT NULL,
    `day_of_week` TINYINT UNSIGNED NOT NULL, -- 0=Sunday, 1=Monday ... 6=Saturday
    `start_time` TIME NOT NULL,
    `end_time` TIME NOT NULL,
    `specific_date` DATE NULL,
    `is_available` BOOLEAN NOT NULL DEFAULT TRUE,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_dm_avail_lookup` (
        `dm_profile_id`,
        `day_of_week`,
        `is_available`
    ),
    CONSTRAINT `fk_dm_availabilities_dm` FOREIGN KEY (`dm_profile_id`) REFERENCES `dm_profiles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `chk_dm_avail_day` CHECK (`day_of_week` BETWEEN 0 AND 6),
    CONSTRAINT `chk_dm_avail_times` CHECK (`start_time` < `end_time`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- =============================================================================
-- CONTEXT 05: GAMEROOM MANAGEMENT (6 TABLES)
-- =============================================================================

-- 14. game_rooms: Matchmaking rooms
CREATE TABLE IF NOT EXISTS `game_rooms` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `room_code` VARCHAR(32) NOT NULL,
    `creator_id` BIGINT UNSIGNED NOT NULL,
    `game_id` BIGINT UNSIGNED NOT NULL,
    `title` VARCHAR(191) NOT NULL,
    `min_players` INT UNSIGNED NOT NULL DEFAULT 4,
    `max_players` INT UNSIGNED NOT NULL DEFAULT 8,
    `status` VARCHAR(40) NOT NULL DEFAULT 'FORMING', -- FORMING, FORMED, SCHEDULED, IN_SESSION, COMPLETED, CANCELLED, EXPIRED
    `is_active` BOOLEAN NOT NULL DEFAULT TRUE, -- Host limit <= 3 active rooms
    `formation_deadline` TIMESTAMP NOT NULL,
    `notes` TEXT NULL,
    `cancel_reason` TEXT NULL,
    `version` BIGINT UNSIGNED NOT NULL DEFAULT 0, -- Optimistic locking
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_game_rooms_code` (`room_code`),
    INDEX `idx_game_rooms_creator_active` (`creator_id`, `is_active`),
    INDEX `idx_game_rooms_game_status` (`game_id`, `status`),
    INDEX `idx_game_rooms_status_deadline` (
        `status`,
        `formation_deadline`
    ),
    CONSTRAINT `fk_game_rooms_creator` FOREIGN KEY (`creator_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_game_rooms_games` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_game_rooms_players` CHECK (
        `min_players` > 0
        AND `max_players` >= `min_players`
    )
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- 15. dm_assignments: Room-to-DM assignment
-- [VIRTUAL GENERATED COLUMN 1]: active_assigned_room_id ensures max 1 active DM per room
CREATE TABLE IF NOT EXISTS `dm_assignments` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `game_room_id` BIGINT UNSIGNED NOT NULL,
    `dm_profile_id` BIGINT UNSIGNED NOT NULL,
    `status` VARCHAR(40) NOT NULL DEFAULT 'ASSIGNED', -- ASSIGNED, CONFIRMED, REJECTED, REPLACED, CANCELLED
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

-- 16. room_participants: Participants in a game room
-- [VIRTUAL GENERATED COLUMN 2]: active_user_id ensures user only has 1 active slot per room
CREATE TABLE IF NOT EXISTS `room_participants` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `game_room_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `role_in_room` VARCHAR(20) NOT NULL DEFAULT 'PLAYER', -- HOST, PLAYER
    `status` VARCHAR(40) NOT NULL DEFAULT 'JOINED', -- JOINED, DEPOSIT_PENDING, DEPOSITED, REMAINING_PENDING, FULLY_PAID, LEAVE_REQUESTED, LEFT, EXPIRED, CANCELLED
    `deposit_amount` BIGINT UNSIGNED NOT NULL DEFAULT 50000, -- Fixed 50.000 VNĐ
    `remaining_amount` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `deposit_deadline` TIMESTAMP NULL,
    `joined_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `left_at` TIMESTAMP NULL,
    `cancel_reason` TEXT NULL,
    `version` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `active_user_id` BIGINT UNSIGNED GENERATED ALWAYS AS (
        CASE
            WHEN `status` IN (
                'JOINED',
                'DEPOSIT_PENDING',
                'DEPOSITED',
                'REMAINING_PENDING',
                'FULLY_PAID',
                'LEAVE_REQUESTED'
            ) THEN `user_id`
            ELSE NULL
        END
    ) VIRTUAL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_room_active_participant` (
        `game_room_id`,
        `active_user_id`
    ),
    INDEX `idx_participants_user` (`user_id`, `status`),
    INDEX `idx_participants_room_status` (`game_room_id`, `status`),
    CONSTRAINT `fk_room_participants_room` FOREIGN KEY (`game_room_id`) REFERENCES `game_rooms` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_room_participants_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- 17. game_room_status_histories: Audit lifecycle transitions of rooms
CREATE TABLE IF NOT EXISTS `game_room_status_histories` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `game_room_id` BIGINT UNSIGNED NOT NULL,
    `changed_by_user_id` BIGINT UNSIGNED NULL,
    `from_status` VARCHAR(40) NOT NULL,
    `to_status` VARCHAR(40) NOT NULL,
    `reason` TEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_grsh_room_created` (`game_room_id`, `created_at`),
    CONSTRAINT `fk_grsh_room` FOREIGN KEY (`game_room_id`) REFERENCES `game_rooms` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_grsh_user` FOREIGN KEY (`changed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- 18. participant_status_histories: Audit lifecycle transitions of participants
CREATE TABLE IF NOT EXISTS `participant_status_histories` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `room_participant_id` BIGINT UNSIGNED NOT NULL,
    `from_status` VARCHAR(40) NOT NULL,
    `to_status` VARCHAR(40) NOT NULL,
    `reason` TEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_psh_participant_created` (
        `room_participant_id`,
        `created_at`
    ),
    CONSTRAINT `fk_psh_participant` FOREIGN KEY (`room_participant_id`) REFERENCES `room_participants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- 19. replacement_windows: Slot replacement recruitment window
CREATE TABLE IF NOT EXISTS `replacement_windows` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `game_room_id` BIGINT UNSIGNED NOT NULL,
    `original_participant_id` BIGINT UNSIGNED NOT NULL,
    `replacement_participant_id` BIGINT UNSIGNED NULL,
    `status` VARCHAR(30) NOT NULL DEFAULT 'OPEN', -- OPEN, FILLED, EXPIRED, CANCELLED
    `expires_at` TIMESTAMP NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_rw_room_status` (
        `game_room_id`,
        `status`,
        `expires_at`
    ),
    INDEX `idx_rw_replacement_id` (`replacement_participant_id`),
    CONSTRAINT `fk_replacement_windows_room` FOREIGN KEY (`game_room_id`) REFERENCES `game_rooms` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_replacement_windows_orig` FOREIGN KEY (`original_participant_id`) REFERENCES `room_participants` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_replacement_windows_repl` FOREIGN KEY (`replacement_participant_id`) REFERENCES `room_participants` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- 20. participant_leave_requests: Formal participant departure requests
-- [VIRTUAL GENERATED COLUMN 3]: pending_participant_id ensures max 1 PENDING request per participant
CREATE TABLE IF NOT EXISTS `participant_leave_requests` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `room_participant_id` BIGINT UNSIGNED NOT NULL,
    `game_room_id` BIGINT UNSIGNED NOT NULL,
    `reviewed_by_user_id` BIGINT UNSIGNED NULL,
    `reason` TEXT NOT NULL,
    `status` VARCHAR(30) NOT NULL DEFAULT 'PENDING', -- PENDING, APPROVED, REJECTED, CANCELLED
    `requested_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `reviewed_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `pending_participant_id` BIGINT UNSIGNED GENERATED ALWAYS AS (
        CASE
            WHEN `status` = 'PENDING' THEN `room_participant_id`
            ELSE NULL
        END
    ) VIRTUAL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_pending_leave_request` (`pending_participant_id`),
    INDEX `idx_plr_room_status` (`game_room_id`, `status`),
    INDEX `idx_plr_participant` (`room_participant_id`),
    CONSTRAINT `fk_plr_participant` FOREIGN KEY (`room_participant_id`) REFERENCES `room_participants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_plr_room` FOREIGN KEY (`game_room_id`) REFERENCES `game_rooms` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_plr_reviewer` FOREIGN KEY (`reviewed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- =============================================================================
-- CONTEXT 06: VENUE & SCHEDULING (5 TABLES)
-- =============================================================================

-- 21. physical_rooms: Brick-and-mortar rooms / tables
CREATE TABLE IF NOT EXISTS `physical_rooms` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(32) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `capacity` INT UNSIGNED NOT NULL DEFAULT 8,
    `status` VARCHAR(30) NOT NULL DEFAULT 'AVAILABLE', -- AVAILABLE, MAINTENANCE, CLOSED
    `description` TEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_physical_rooms_code` (`code`),
    CONSTRAINT `chk_physical_rooms_capacity` CHECK (`capacity` > 0)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- 22. schedule_proposals: Time negotiation proposals
-- [VIRTUAL GENERATED COLUMN 4]: proposed_game_room_id ensures max 1 active PROPOSED schedule per room
CREATE TABLE IF NOT EXISTS `schedule_proposals` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `game_room_id` BIGINT UNSIGNED NOT NULL,
    `proposed_by_user_id` BIGINT UNSIGNED NOT NULL,
    `proposed_date` DATE NOT NULL,
    `start_time` TIME NOT NULL,
    `end_time` TIME NOT NULL,
    `status` VARCHAR(30) NOT NULL DEFAULT 'PROPOSED', -- PROPOSED, ACCEPTED, REJECTED, EXPIRED
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

-- 23. schedule_responses: Participant votes on schedule proposals
CREATE TABLE IF NOT EXISTS `schedule_responses` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `schedule_proposal_id` BIGINT UNSIGNED NOT NULL,
    `room_participant_id` BIGINT UNSIGNED NOT NULL,
    `response` VARCHAR(20) NOT NULL, -- ACCEPT, REJECT
    `note` VARCHAR(255) NULL,
    `responded_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_schedule_responses_vote` (
        `schedule_proposal_id`,
        `room_participant_id`
    ),
    INDEX `idx_schedule_responses_participant` (`room_participant_id`),
    CONSTRAINT `fk_schedule_responses_proposal` FOREIGN KEY (`schedule_proposal_id`) REFERENCES `schedule_proposals` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_schedule_responses_participant` FOREIGN KEY (`room_participant_id`) REFERENCES `room_participants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- 24. sessions: Materialized TRPG play sessions
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
    `status` VARCHAR(40) NOT NULL DEFAULT 'SCHEDULED', -- SCHEDULED, IN_PROGRESS, COMPLETED, CANCELLED
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

-- 25. physical_room_reservations: Physical table reservations
-- [VIRTUAL GENERATED COLUMN 5]: active_session_id ensures max 1 active reservation per session
CREATE TABLE IF NOT EXISTS `physical_room_reservations` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `physical_room_id` BIGINT UNSIGNED NOT NULL,
    `session_id` BIGINT UNSIGNED NOT NULL,
    `reservation_date` DATE NOT NULL,
    `start_time` TIME NOT NULL,
    `end_time` TIME NOT NULL,
    `status` VARCHAR(30) NOT NULL DEFAULT 'RESERVED', -- RESERVED, CONFIRMED, CANCELLED, RELEASED
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
    INDEX `idx_prr_schedule_check` (
        `physical_room_id`,
        `reservation_date`,
        `status`,
        `start_time`,
        `end_time`
    ),
    INDEX `idx_prr_session` (`session_id`),
    CONSTRAINT `fk_prr_physical_room` FOREIGN KEY (`physical_room_id`) REFERENCES `physical_rooms` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_prr_session` FOREIGN KEY (`session_id`) REFERENCES `sessions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `chk_prr_times` CHECK (`start_time` < `end_time`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- 26. booking_price_snapshots: Frozen financial snapshot (Strict 1:1 with session)
CREATE TABLE IF NOT EXISTS `booking_price_snapshots` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `session_id` BIGINT UNSIGNED NOT NULL,
    `game_price_id` BIGINT UNSIGNED NULL,
    `promotion_id` BIGINT UNSIGNED NULL,
    `original_base_price` BIGINT UNSIGNED NOT NULL,
    `discount_amount` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `final_price` BIGINT UNSIGNED NOT NULL,
    `required_deposit_per_player` BIGINT UNSIGNED NOT NULL DEFAULT 50000, -- Fixed 50.000 VNĐ
    `total_deposit_expected` BIGINT UNSIGNED NOT NULL,
    `remaining_balance_expected` BIGINT UNSIGNED NOT NULL,
    `snapshot_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_booking_price_snapshots_session` (`session_id`),
    INDEX `idx_bps_game_price` (`game_price_id`),
    INDEX `idx_bps_promotion` (`promotion_id`),
    CONSTRAINT `fk_bps_session` FOREIGN KEY (`session_id`) REFERENCES `sessions` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_bps_game_price` FOREIGN KEY (`game_price_id`) REFERENCES `game_prices` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_bps_promotion` FOREIGN KEY (`promotion_id`) REFERENCES `promotions` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `chk_bps_amounts` CHECK (
        `original_base_price` >= 0
        AND `discount_amount` >= 0
        AND `final_price` >= 0
        AND `required_deposit_per_player` >= 0
    )
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- =============================================================================
-- CONTEXT 07: PAYMENT & REFUND (5 TABLES)
-- =============================================================================

-- 27. player_payments: Player financial liabilities & payment tracking
CREATE TABLE IF NOT EXISTS `player_payments` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `room_participant_id` BIGINT UNSIGNED NOT NULL,
    `session_id` BIGINT UNSIGNED NOT NULL,
    `payment_type` VARCHAR(30) NOT NULL, -- DEPOSIT, REMAINING_BALANCE, FULL
    `amount_due` BIGINT UNSIGNED NOT NULL,
    `amount_paid` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `status` VARCHAR(30) NOT NULL DEFAULT 'PENDING', -- PENDING, PARTIALLY_PAID, PAID, FAILED, REFUNDED
    `deadline` TIMESTAMP NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_player_payments_participant` (
        `room_participant_id`,
        `status`
    ),
    INDEX `idx_player_payments_session` (`session_id`, `payment_type`),
    CONSTRAINT `fk_player_payments_participant` FOREIGN KEY (`room_participant_id`) REFERENCES `room_participants` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_player_payments_session` FOREIGN KEY (`session_id`) REFERENCES `sessions` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_player_payments_amounts` CHECK (
        `amount_due` >= 0
        AND `amount_paid` >= 0
    )
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- 28. payment_attempts: Idempotent gateway transaction attempts
CREATE TABLE IF NOT EXISTS `payment_attempts` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `player_payment_id` BIGINT UNSIGNED NOT NULL,
    `attempt_number` INT UNSIGNED NOT NULL DEFAULT 1,
    `idempotency_key` VARCHAR(64) NOT NULL,
    `payment_method` VARCHAR(30) NOT NULL DEFAULT 'SIMULATED', -- SIMULATED, BANK_TRANSFER, VNPAY, MOMO
    `amount` BIGINT UNSIGNED NOT NULL,
    `status` VARCHAR(30) NOT NULL DEFAULT 'INITIATED', -- INITIATED, SUCCESS, FAILED, TIMED_OUT
    `gateway_request` JSON NULL,
    `gateway_response` JSON NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_payment_attempts_idempotency` (`idempotency_key`),
    INDEX `idx_payment_attempts_payment_status` (`player_payment_id`, `status`),
    CONSTRAINT `fk_payment_attempts_payment` FOREIGN KEY (`player_payment_id`) REFERENCES `player_payments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `chk_payment_attempts_amount` CHECK (`amount` > 0)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- 29. payment_transactions: Accounting ledger entries for confirmed payments
CREATE TABLE IF NOT EXISTS `payment_transactions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `payment_attempt_id` BIGINT UNSIGNED NOT NULL,
    `transaction_reference` VARCHAR(64) NOT NULL,
    `amount` BIGINT UNSIGNED NOT NULL,
    `currency` VARCHAR(10) NOT NULL DEFAULT 'VND',
    `transacted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `metadata` JSON NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_payment_transactions_attempt` (`payment_attempt_id`),
    UNIQUE KEY `uq_payment_transactions_reference` (`transaction_reference`),
    CONSTRAINT `fk_payment_transactions_attempt` FOREIGN KEY (`payment_attempt_id`) REFERENCES `payment_attempts` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_payment_transactions_amount` CHECK (`amount` > 0)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- 30. refunds: Refund requests and ledger
CREATE TABLE IF NOT EXISTS `refunds` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `player_payment_id` BIGINT UNSIGNED NOT NULL,
    `room_participant_id` BIGINT UNSIGNED NOT NULL,
    `processed_by_user_id` BIGINT UNSIGNED NULL,
    `reason` TEXT NOT NULL,
    `refund_type` VARCHAR(30) NOT NULL DEFAULT 'FULL', -- FULL, PARTIAL, DEPOSIT_FORFEIT
    `refund_amount` BIGINT UNSIGNED NOT NULL,
    `status` VARCHAR(30) NOT NULL DEFAULT 'PENDING', -- PENDING, APPROVED, PROCESSING, COMPLETED, REJECTED
    `processed_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_refunds_payment_status` (`player_payment_id`, `status`),
    INDEX `idx_refunds_participant` (`room_participant_id`),
    INDEX `idx_refunds_processor` (`processed_by_user_id`),
    CONSTRAINT `fk_refunds_payment` FOREIGN KEY (`player_payment_id`) REFERENCES `player_payments` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_refunds_participant` FOREIGN KEY (`room_participant_id`) REFERENCES `room_participants` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_refunds_processor` FOREIGN KEY (`processed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `chk_refunds_amount` CHECK (`refund_amount` > 0)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- 31. refund_attempts: Execution attempts for disbursement
CREATE TABLE IF NOT EXISTS `refund_attempts` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `refund_id` BIGINT UNSIGNED NOT NULL,
    `attempt_number` INT UNSIGNED NOT NULL DEFAULT 1,
    `idempotency_key` VARCHAR(64) NOT NULL,
    `amount` BIGINT UNSIGNED NOT NULL,
    `status` VARCHAR(30) NOT NULL DEFAULT 'INITIATED', -- INITIATED, SUCCESS, FAILED
    `error_message` TEXT NULL,
    `executed_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_refund_attempts_idempotency` (`idempotency_key`),
    INDEX `idx_refund_attempts_refund` (`refund_id`),
    CONSTRAINT `fk_refund_attempts_refund` FOREIGN KEY (`refund_id`) REFERENCES `refunds` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `chk_refund_attempts_amount` CHECK (`amount` > 0)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- =============================================================================
-- CONTEXT 08: NOTIFICATION & AUDIT (3 TABLES)
-- =============================================================================

-- 32. notifications: Announcements & Alerts
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `sender_id` BIGINT UNSIGNED NULL,
    `title` VARCHAR(191) NOT NULL,
    `message` TEXT NOT NULL,
    `notification_type` VARCHAR(40) NOT NULL, -- ROOM_EVENT, PAYMENT_REMINDER, SYSTEM_ALERT
    `payload` JSON NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_notifications_created_at` (`created_at`),
    INDEX `idx_notifications_sender` (`sender_id`),
    CONSTRAINT `fk_notifications_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- 33. notification_recipient_states: Recipient delivery & read tracking (N:M)
CREATE TABLE IF NOT EXISTS `notification_recipient_states` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `notification_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `is_read` BOOLEAN NOT NULL DEFAULT FALSE,
    `read_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_notification_recipient` (`notification_id`, `user_id`),
    INDEX `idx_nrs_user_unread` (
        `user_id`,
        `is_read`,
        `created_at`
    ),
    CONSTRAINT `fk_nrs_notification` FOREIGN KEY (`notification_id`) REFERENCES `notifications` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_nrs_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- 34. audit_logs: Immutable event trail
CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NULL,
    `action` VARCHAR(50) NOT NULL,
    `entity_type` VARCHAR(50) NOT NULL,
    `entity_id` BIGINT UNSIGNED NOT NULL,
    `old_values` JSON NULL,
    `new_values` JSON NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` VARCHAR(255) NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_audit_logs_entity` (
        `entity_type`,
        `entity_id`,
        `created_at`
    ),
    INDEX `idx_audit_logs_user` (`user_id`, `created_at`),
    CONSTRAINT `fk_audit_logs_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- END OF MIGRATION: V1__baseline_schema_34_tables.sql
-- =============================================================================

-- =============================================================================
-- RESTORE FOREIGN KEY CHECKS
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- MIGRATION VERIFICATION
-- =============================================================================

SELECT DATABASE() AS current_database;

SELECT COUNT(*) AS total_tables
FROM information_schema.tables
WHERE
    table_schema = DATABASE()
    AND table_type = 'BASE TABLE';

SELECT COUNT(*) AS total_foreign_keys
FROM information_schema.table_constraints
WHERE
    constraint_schema = DATABASE()
    AND constraint_type = 'FOREIGN KEY';

SELECT
    COUNT(*) AS total_virtual_generated_columns
FROM information_schema.columns
WHERE
    table_schema = DATABASE()
    AND extra LIKE '%VIRTUAL GENERATED%';

SELECT
    table_name,
    engine,
    table_collation
FROM information_schema.tables
WHERE
    table_schema = DATABASE()
    AND table_type = 'BASE TABLE'
ORDER BY table_name;

-- =============================================================================
-- END OF MIGRATION
-- =============================================================================