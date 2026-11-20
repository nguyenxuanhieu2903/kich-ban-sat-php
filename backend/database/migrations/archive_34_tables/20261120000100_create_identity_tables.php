<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateIdentityTables extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<SQL
        SET FOREIGN_KEY_CHECKS = 0;

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

        SET FOREIGN_KEY_CHECKS = 1;
        SQL);
    }

    public function down(): void
    {
        $this->execute(<<<SQL
        SET FOREIGN_KEY_CHECKS = 0;
        DROP TABLE IF EXISTS `user_roles`;
        DROP TABLE IF EXISTS `user_profiles`;
        DROP TABLE IF EXISTS `roles`;
        DROP TABLE IF EXISTS `users`;
        SET FOREIGN_KEY_CHECKS = 1;
        SQL);
    }
}
