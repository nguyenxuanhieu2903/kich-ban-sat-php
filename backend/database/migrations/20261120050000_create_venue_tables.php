<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateVenueTables extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<SQL
        SET FOREIGN_KEY_CHECKS = 0;

        -- 9. venues: Store establishment and location
        CREATE TABLE IF NOT EXISTS `venues` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `code` VARCHAR(32) NOT NULL,
            `name` VARCHAR(191) NOT NULL,
            `address` VARCHAR(255) NOT NULL,
            `phone` VARCHAR(20) NULL,
            `opening_hours` VARCHAR(100) NOT NULL DEFAULT '09:00 - 23:00',
            `is_active` BOOLEAN NOT NULL DEFAULT TRUE,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_venues_code` (`code`)
        ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

        -- 10. physical_rooms: Brick-and-mortar rooms / tables
        CREATE TABLE IF NOT EXISTS `physical_rooms` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `venue_id` BIGINT UNSIGNED NOT NULL,
            `code` VARCHAR(32) NOT NULL,
            `name` VARCHAR(100) NOT NULL,
            `capacity` INT UNSIGNED NOT NULL DEFAULT 8,
            `status` VARCHAR(30) NOT NULL DEFAULT 'AVAILABLE',
            `description` TEXT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_physical_rooms_code` (`code`),
            INDEX `idx_physical_rooms_venue_status` (`venue_id`, `status`),
            CONSTRAINT `fk_physical_rooms_venue` FOREIGN KEY (`venue_id`) REFERENCES `venues` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT `chk_physical_rooms_capacity` CHECK (`capacity` > 0)
        ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

        SET FOREIGN_KEY_CHECKS = 1;
        SQL);
    }

    public function down(): void
    {
        $this->execute(<<<SQL
        SET FOREIGN_KEY_CHECKS = 0;
        DROP TABLE IF EXISTS `physical_rooms`;
        DROP TABLE IF EXISTS `venues`;
        SET FOREIGN_KEY_CHECKS = 1;
        SQL);
    }
}
