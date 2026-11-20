<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateNotificationTables extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<SQL
        SET FOREIGN_KEY_CHECKS = 0;

        -- 32. notifications: Announcements & Alerts
        CREATE TABLE IF NOT EXISTS `notifications` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `sender_id` BIGINT UNSIGNED NULL,
            `title` VARCHAR(191) NOT NULL,
            `message` TEXT NOT NULL,
            `notification_type` VARCHAR(40) NOT NULL,
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
            INDEX `idx_nrs_user_unread` (`user_id`, `is_read`, `created_at`),
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
            INDEX `idx_audit_logs_entity` (`entity_type`, `entity_id`, `created_at`),
            INDEX `idx_audit_logs_user` (`user_id`, `created_at`),
            CONSTRAINT `fk_audit_logs_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

        SET FOREIGN_KEY_CHECKS = 1;
        SQL);
    }

    public function down(): void
    {
        $this->execute(<<<SQL
        SET FOREIGN_KEY_CHECKS = 0;
        DROP TABLE IF EXISTS `audit_logs`;
        DROP TABLE IF EXISTS `notification_recipient_states`;
        DROP TABLE IF EXISTS `notifications`;
        SET FOREIGN_KEY_CHECKS = 1;
        SQL);
    }
}
