<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateNotificationTables extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<SQL
        SET FOREIGN_KEY_CHECKS = 0;

        -- 21. notifications: Direct user announcements & alerts
        CREATE TABLE IF NOT EXISTS `notifications` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` BIGINT UNSIGNED NOT NULL,
            `sender_id` BIGINT UNSIGNED NULL,
            `title` VARCHAR(191) NOT NULL,
            `message` TEXT NOT NULL,
            `notification_type` VARCHAR(40) NOT NULL DEFAULT 'ROOM_EVENT',
            `is_read` BOOLEAN NOT NULL DEFAULT FALSE,
            `read_at` TIMESTAMP NULL,
            `payload` JSON NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `idx_notifications_user_unread` (`user_id`, `is_read`, `created_at`),
            INDEX `idx_notifications_sender` (`sender_id`),
            CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT `fk_notifications_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

        SET FOREIGN_KEY_CHECKS = 1;
        SQL);
    }

    public function down(): void
    {
        $this->execute(<<<SQL
        SET FOREIGN_KEY_CHECKS = 0;
        DROP TABLE IF EXISTS `notifications`;
        SET FOREIGN_KEY_CHECKS = 1;
        SQL);
    }
}
