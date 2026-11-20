<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateGameroomTables extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<SQL
        SET FOREIGN_KEY_CHECKS = 0;

        -- 10. game_rooms: Matchmaking rooms
        CREATE TABLE IF NOT EXISTS `game_rooms` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `room_code` VARCHAR(32) NOT NULL,
            `creator_id` BIGINT UNSIGNED NOT NULL,
            `game_id` BIGINT UNSIGNED NOT NULL,
            `title` VARCHAR(191) NOT NULL,
            `min_players` INT UNSIGNED NOT NULL DEFAULT 4,
            `max_players` INT UNSIGNED NOT NULL DEFAULT 8,
            `status` VARCHAR(40) NOT NULL DEFAULT 'FORMING',
            `is_active` BOOLEAN NOT NULL DEFAULT TRUE,
            `formation_deadline` TIMESTAMP NOT NULL,
            `notes` TEXT NULL,
            `cancel_reason` TEXT NULL,
            `version` BIGINT UNSIGNED NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_game_rooms_code` (`room_code`),
            INDEX `idx_game_rooms_creator_active` (`creator_id`, `is_active`),
            INDEX `idx_game_rooms_game_status` (`game_id`, `status`),
            INDEX `idx_game_rooms_status_deadline` (`status`, `formation_deadline`),
            CONSTRAINT `fk_game_rooms_creator` FOREIGN KEY (`creator_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT `fk_game_rooms_games` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT `chk_game_rooms_players` CHECK (`min_players` > 0 AND `max_players` >= `min_players`)
        ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

        -- 11. room_participants: Participants in a game room
        -- [VIRTUAL GENERATED COLUMN 2]: active_user_id ensures user only has 1 active slot per room
        CREATE TABLE IF NOT EXISTS `room_participants` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `game_room_id` BIGINT UNSIGNED NOT NULL,
            `user_id` BIGINT UNSIGNED NOT NULL,
            `role_in_room` VARCHAR(20) NOT NULL DEFAULT 'PLAYER',
            `status` VARCHAR(40) NOT NULL DEFAULT 'JOINED',
            `deposit_amount` BIGINT UNSIGNED NOT NULL DEFAULT 50000,
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
                    WHEN `status` IN ('JOINED', 'DEPOSIT_PENDING', 'DEPOSITED', 'REMAINING_PENDING', 'FULLY_PAID', 'LEAVE_REQUESTED') THEN `user_id`
                    ELSE NULL
                END
            ) VIRTUAL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_room_active_participant` (`game_room_id`, `active_user_id`),
            INDEX `idx_participants_user` (`user_id`, `status`),
            INDEX `idx_participants_room_status` (`game_room_id`, `status`),
            CONSTRAINT `fk_room_participants_room` FOREIGN KEY (`game_room_id`) REFERENCES `game_rooms` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT `fk_room_participants_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
        ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

        -- 12. game_room_status_histories: Audit lifecycle transitions of rooms
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

        -- 13. participant_status_histories: Audit lifecycle transitions of participants
        CREATE TABLE IF NOT EXISTS `participant_status_histories` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `room_participant_id` BIGINT UNSIGNED NOT NULL,
            `from_status` VARCHAR(40) NOT NULL,
            `to_status` VARCHAR(40) NOT NULL,
            `reason` TEXT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `idx_psh_participant_created` (`room_participant_id`, `created_at`),
            CONSTRAINT `fk_psh_participant` FOREIGN KEY (`room_participant_id`) REFERENCES `room_participants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

        -- 14. replacement_windows: Slot replacement recruitment window
        CREATE TABLE IF NOT EXISTS `replacement_windows` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `game_room_id` BIGINT UNSIGNED NOT NULL,
            `original_participant_id` BIGINT UNSIGNED NOT NULL,
            `replacement_participant_id` BIGINT UNSIGNED NULL,
            `status` VARCHAR(30) NOT NULL DEFAULT 'OPEN',
            `expires_at` TIMESTAMP NOT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `idx_rw_room_status` (`game_room_id`, `status`, `expires_at`),
            INDEX `idx_rw_replacement_id` (`replacement_participant_id`),
            CONSTRAINT `fk_replacement_windows_room` FOREIGN KEY (`game_room_id`) REFERENCES `game_rooms` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT `fk_replacement_windows_orig` FOREIGN KEY (`original_participant_id`) REFERENCES `room_participants` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT `fk_replacement_windows_repl` FOREIGN KEY (`replacement_participant_id`) REFERENCES `room_participants` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

        -- 15. participant_leave_requests: Formal participant departure requests
        -- [VIRTUAL GENERATED COLUMN 3]: pending_participant_id ensures max 1 PENDING request per participant
        CREATE TABLE IF NOT EXISTS `participant_leave_requests` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `room_participant_id` BIGINT UNSIGNED NOT NULL,
            `game_room_id` BIGINT UNSIGNED NOT NULL,
            `reviewed_by_user_id` BIGINT UNSIGNED NULL,
            `reason` TEXT NOT NULL,
            `status` VARCHAR(30) NOT NULL DEFAULT 'PENDING',
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

        SET FOREIGN_KEY_CHECKS = 1;
        SQL);
    }

    public function down(): void
    {
        $this->execute(<<<SQL
        SET FOREIGN_KEY_CHECKS = 0;
        DROP TABLE IF EXISTS `participant_leave_requests`;
        DROP TABLE IF EXISTS `replacement_windows`;
        DROP TABLE IF EXISTS `participant_status_histories`;
        DROP TABLE IF EXISTS `game_room_status_histories`;
        DROP TABLE IF EXISTS `room_participants`;
        DROP TABLE IF EXISTS `game_rooms`;
        SET FOREIGN_KEY_CHECKS = 1;
        SQL);
    }
}
