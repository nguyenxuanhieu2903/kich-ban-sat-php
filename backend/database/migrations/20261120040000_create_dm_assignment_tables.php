<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateDmAssignmentTables extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<SQL
        SET FOREIGN_KEY_CHECKS = 0;

        -- 8. dm_assignments: Room-to-DM assignment
        CREATE TABLE IF NOT EXISTS `dm_assignments` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `game_room_id` BIGINT UNSIGNED NOT NULL,
            `dm_user_id` BIGINT UNSIGNED NOT NULL,
            `status` VARCHAR(40) NOT NULL DEFAULT 'ASSIGNED',
            `assigned_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `responded_at` TIMESTAMP NULL,
            `notes` TEXT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `active_assigned_room_id` BIGINT UNSIGNED GENERATED ALWAYS AS (
                CASE
                    WHEN `status` IN ('ASSIGNED', 'ACCEPTED') THEN `game_room_id`
                    ELSE NULL
                END
            ) VIRTUAL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_active_dm_assignment` (`active_assigned_room_id`),
            INDEX `idx_dm_assignments_dm_status` (`dm_user_id`, `status`),
            INDEX `idx_dm_assignments_room` (`game_room_id`),
            CONSTRAINT `fk_dm_assignments_room` FOREIGN KEY (`game_room_id`) REFERENCES `game_rooms` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT `fk_dm_assignments_user` FOREIGN KEY (`dm_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
        ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

        SET FOREIGN_KEY_CHECKS = 1;
        SQL);
    }

    public function down(): void
    {
        $this->execute(<<<SQL
        SET FOREIGN_KEY_CHECKS = 0;
        DROP TABLE IF EXISTS `dm_assignments`;
        SET FOREIGN_KEY_CHECKS = 1;
        SQL);
    }
}
