<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateGameTables extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<SQL
        SET FOREIGN_KEY_CHECKS = 0;

        -- 3. games: Murder Mystery / Tabletop RPG scripts
        CREATE TABLE IF NOT EXISTS `games` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `title` VARCHAR(191) NOT NULL,
            `slug` VARCHAR(191) NOT NULL,
            `category` VARCHAR(100) NOT NULL DEFAULT 'Trinh thám',
            `description` TEXT NULL,
            `rules_summary` TEXT NULL,
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

        -- 4. game_configurations: Technical props and setup configs
        CREATE TABLE IF NOT EXISTS `game_configurations` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `game_id` BIGINT UNSIGNED NOT NULL,
            `config_key` VARCHAR(100) NOT NULL,
            `config_value` TEXT NOT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_game_config_key` (`game_id`, `config_key`),
            CONSTRAINT `fk_game_configurations_games` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

        SET FOREIGN_KEY_CHECKS = 1;
        SQL);
    }

    public function down(): void
    {
        $this->execute(<<<SQL
        SET FOREIGN_KEY_CHECKS = 0;
        DROP TABLE IF EXISTS `game_configurations`;
        DROP TABLE IF EXISTS `games`;
        SET FOREIGN_KEY_CHECKS = 1;
        SQL);
    }
}
