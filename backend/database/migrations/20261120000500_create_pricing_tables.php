<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreatePricingTables extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<SQL
        SET FOREIGN_KEY_CHECKS = 0;

        -- 24. game_prices: Time-band and day-type price schedule
        CREATE TABLE IF NOT EXISTS `game_prices` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `game_id` BIGINT UNSIGNED NOT NULL,
            `price_tier_name` VARCHAR(50) NOT NULL,
            `day_type` VARCHAR(20) NOT NULL DEFAULT 'ALL',
            `time_from` TIME NOT NULL,
            `time_to` TIME NOT NULL,
            `base_price_per_player` BIGINT UNSIGNED NOT NULL,
            `is_active` BOOLEAN NOT NULL DEFAULT TRUE,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `idx_game_prices_lookup` (`game_id`, `day_type`, `time_from`, `time_to`),
            CONSTRAINT `fk_game_prices_games` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT `chk_game_prices_times` CHECK (`time_from` < `time_to`),
            CONSTRAINT `chk_game_prices_amount` CHECK (`base_price_per_player` >= 0)
        ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

        -- 25. promotions: Discount vouchers & marketing campaigns
        CREATE TABLE IF NOT EXISTS `promotions` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `code` VARCHAR(50) NOT NULL,
            `name` VARCHAR(191) NOT NULL,
            `discount_type` VARCHAR(20) NOT NULL,
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

        -- 26. booking_price_snapshots: Frozen financial snapshot (Strict 1:1 with session)
        CREATE TABLE IF NOT EXISTS `booking_price_snapshots` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `session_id` BIGINT UNSIGNED NOT NULL,
            `game_price_id` BIGINT UNSIGNED NULL,
            `promotion_id` BIGINT UNSIGNED NULL,
            `original_base_price` BIGINT UNSIGNED NOT NULL,
            `discount_amount` BIGINT UNSIGNED NOT NULL DEFAULT 0,
            `final_price` BIGINT UNSIGNED NOT NULL,
            `required_deposit_per_player` BIGINT UNSIGNED NOT NULL DEFAULT 50000,
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

        SET FOREIGN_KEY_CHECKS = 1;
        SQL);
    }

    public function down(): void
    {
        $this->execute(<<<SQL
        SET FOREIGN_KEY_CHECKS = 0;
        DROP TABLE IF EXISTS `booking_price_snapshots`;
        DROP TABLE IF EXISTS `promotions`;
        DROP TABLE IF EXISTS `game_prices`;
        SET FOREIGN_KEY_CHECKS = 1;
        SQL);
    }
}
