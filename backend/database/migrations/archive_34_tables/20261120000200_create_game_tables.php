<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateGameTables extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<SQL
        SET FOREIGN_KEY_CHECKS = 0;

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
            INDEX `idx_games_status_players` (`is_active`, `min_players`, `max_players`),
            CONSTRAINT `chk_games_players` CHECK (`min_players` > 0 AND `max_players` >= `min_players`),
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

        SET FOREIGN_KEY_CHECKS = 1;
        SQL);
    }

    public function down(): void
    {
        $this->execute(<<<SQL
        SET FOREIGN_KEY_CHECKS = 0;
        DROP TABLE IF EXISTS `game_category_assignments`;
        DROP TABLE IF EXISTS `game_configurations`;
        DROP TABLE IF EXISTS `game_rules`;
        DROP TABLE IF EXISTS `games`;
        DROP TABLE IF EXISTS `categories`;
        SET FOREIGN_KEY_CHECKS = 1;
        SQL);
    }
}
