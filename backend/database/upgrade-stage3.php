<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Infrastructure\Config\Config;
use App\Infrastructure\Database\DatabaseConnection;

Config::load(dirname(__DIR__));
$pdo = DatabaseConnection::getInstance();

// 1. Tạo bảng audit_logs
$pdo->exec("
CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `actor_id` BIGINT UNSIGNED NULL,
    `action` VARCHAR(100) NOT NULL,
    `entity_type` VARCHAR(80) NOT NULL,
    `entity_id` BIGINT UNSIGNED NULL,
    `metadata` JSON NULL,
    `ip_address` VARCHAR(45) NULL,
    `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    PRIMARY KEY (`id`),
    KEY `idx_audit_actor_created` (`actor_id`, `created_at`),
    KEY `idx_audit_entity` (`entity_type`, `entity_id`),
    KEY `idx_audit_action` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
");
echo "[OK] Table audit_logs ready.\n";

// 2. Thêm cột student_verified vào bảng users nếu chưa có
$colCheck = $pdo->query("
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'student_verified'
");
if ((int) $colCheck->fetchColumn() === 0) {
    $pdo->exec("ALTER TABLE `users` ADD COLUMN `student_verified` TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`");
    echo "[OK] Added column users.student_verified.\n";
} else {
    echo "[OK] Column users.student_verified already exists.\n";
}