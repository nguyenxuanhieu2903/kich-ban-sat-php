<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateIdentityTables extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<SQL
        SET FOREIGN_KEY_CHECKS = 0;

        -- 1. roles: System authorization roles
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

        -- 2. users: Core user accounts with role reference and profile fields
        CREATE TABLE IF NOT EXISTS `users` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `role_id` BIGINT UNSIGNED NOT NULL,
            `name` VARCHAR(191) NOT NULL,
            `email` VARCHAR(191) NOT NULL,
            `password_hash` VARCHAR(255) NOT NULL,
            `phone` VARCHAR(20) NULL,
            `avatar_url` VARCHAR(255) NULL,
            `bio` TEXT NULL,
            `student_card_verified` BOOLEAN NOT NULL DEFAULT FALSE,
            `dm_experience_years` INT UNSIGNED NOT NULL DEFAULT 0,
            `dm_rating_avg` DECIMAL(3, 2) NOT NULL DEFAULT 5.00,
            `status` VARCHAR(30) NOT NULL DEFAULT 'ACTIVE',
            `email_verified_at` TIMESTAMP NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_users_email` (`email`),
            INDEX `idx_users_role_id` (`role_id`),
            INDEX `idx_users_status` (`status`),
            CONSTRAINT `fk_users_roles` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
        ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

        SET FOREIGN_KEY_CHECKS = 1;
        SQL);
    }

    public function down(): void
    {
        $this->execute(<<<SQL
        SET FOREIGN_KEY_CHECKS = 0;
        DROP TABLE IF EXISTS `users`;
        DROP TABLE IF EXISTS `roles`;
        SET FOREIGN_KEY_CHECKS = 1;
        SQL);
    }
}
