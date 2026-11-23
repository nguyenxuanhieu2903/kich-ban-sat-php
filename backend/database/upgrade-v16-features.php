<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Infrastructure\Config\Config;
use App\Infrastructure\Database\DatabaseConnection;

Config::load(dirname(__DIR__));
$pdo = DatabaseConnection::getInstance();

function addColumnIfNotExists(PDO $pdo, string $table, string $column, string $definition): void
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = :table
          AND COLUMN_NAME = :column
    ");
    $stmt->execute([':table' => $table, ':column' => $column]);
    if ((int) $stmt->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
        echo "[OK] Added column {$table}.{$column}\n";
    } else {
        echo "[SKIP] Column {$table}.{$column} already exists.\n";
    }
}

// 1. Nâng cấp bảng game_rooms cho Public / Private booking và target venue/slot
addColumnIfNotExists($pdo, 'game_rooms', 'booking_type', "VARCHAR(20) NOT NULL DEFAULT 'PUBLIC' AFTER `status`");
addColumnIfNotExists($pdo, 'game_rooms', 'target_venue_id', "BIGINT UNSIGNED NULL AFTER `booking_type`");
addColumnIfNotExists($pdo, 'game_rooms', 'target_time_slot', "VARCHAR(100) NULL AFTER `target_venue_id`");
addColumnIfNotExists($pdo, 'game_rooms', 'total_players', "SMALLINT UNSIGNED NULL AFTER `target_time_slot`");
addColumnIfNotExists($pdo, 'game_rooms', 'confirmed_players', "SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER `total_players`");
addColumnIfNotExists($pdo, 'game_rooms', 'required_players', "SMALLINT UNSIGNED NULL AFTER `confirmed_players`");
addColumnIfNotExists($pdo, 'game_rooms', 'guest_slots', "SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER `required_players`");
addColumnIfNotExists($pdo, 'game_rooms', 'is_18_plus_acknowledged', "TINYINT(1) NOT NULL DEFAULT 0 AFTER `guest_slots`");

// 2. Nâng cấp bảng physical_rooms cho bảo trì và theme chuyên đề
addColumnIfNotExists($pdo, 'physical_rooms', 'is_maintenance', "TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`");
addColumnIfNotExists($pdo, 'physical_rooms', 'theme_code', "VARCHAR(50) NOT NULL DEFAULT 'GENERAL' AFTER `is_maintenance`");

// 3. Nâng cấp bảng games cho tag 18+ và theme chuyên đề
addColumnIfNotExists($pdo, 'games', 'is_18_plus', "TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`");
addColumnIfNotExists($pdo, 'games', 'theme_code', "VARCHAR(50) NOT NULL DEFAULT 'GENERAL' AFTER `is_18_plus`");

// 4. Tạo bảng offline_room_blocks cho việc khóa phòng thủ công (Hotline / Walk-in)
$pdo->exec("
CREATE TABLE IF NOT EXISTS `offline_room_blocks` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `venue_id` BIGINT UNSIGNED NOT NULL,
    `physical_room_id` BIGINT UNSIGNED NOT NULL,
    `start_at` DATETIME(3) NOT NULL,
    `end_at` DATETIME(3) NOT NULL,
    `customer_name` VARCHAR(150) NOT NULL,
    `customer_phone` VARCHAR(30) NOT NULL,
    `notes` TEXT NULL,
    `created_by` BIGINT UNSIGNED NOT NULL,
    `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    PRIMARY KEY (`id`),
    KEY `idx_offline_room_time` (`physical_room_id`, `start_at`, `end_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
");
echo "[OK] Table offline_room_blocks ready.\n";

echo "ALL SCHEMA UPGRADES COMPLETED SUCCESSFULLY.\n";
