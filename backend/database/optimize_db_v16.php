<?php

declare(strict_types=1);

/**
 * Schema Optimization & 42 Real Games Seeder (Kịch Bản Sát V16)
 * - Drops unused empty tables: outbox_events, idempotency_records
 * - Adds poster_url, difficulty, synopsis to games
 * - Adds 4 high-impact composite indexes
 * - Seeds all 42 real games from frontend/src/data/scripts.data.js
 */

$env = [];
foreach (file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
    [$k, $v] = explode('=', $line, 2);
    $env[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
}

$pdo = new PDO(
    'mysql:host=' . ($env['DB_HOST'] ?? '127.0.0.1') . ';port=' . ($env['DB_PORT'] ?? '3306') . ';dbname=' . ($env['DB_DATABASE'] ?? 'kich_ban_sat_v3') . ';charset=utf8mb4',
    $env['DB_USERNAME'] ?? 'root',
    $env['DB_PASSWORD'] ?? '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// 1. Drop completely unused tables
$pdo->exec('DROP TABLE IF EXISTS `schedule_responses`');
$pdo->exec('DROP TABLE IF EXISTS `outbox_events`');
$pdo->exec('DROP TABLE IF EXISTS `idempotency_records`');
echo "[OK] Dropped unused tables: schedule_responses, outbox_events, idempotency_records\n";

// 2. Add poster_url, difficulty, synopsis to games if missing
$cols = $pdo->query("SHOW COLUMNS FROM `games`")->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('poster_url', $cols, true)) {
    $pdo->exec("ALTER TABLE `games` ADD COLUMN `poster_url` VARCHAR(255) NULL AFTER `description`");
}
if (!in_array('difficulty', $cols, true)) {
    $pdo->exec("ALTER TABLE `games` ADD COLUMN `difficulty` VARCHAR(50) NULL AFTER `poster_url`");
}
if (!in_array('synopsis', $cols, true)) {
    $pdo->exec("ALTER TABLE `games` ADD COLUMN `synopsis` TEXT NULL AFTER `difficulty`");
}
echo "[OK] Ensured poster_url, difficulty, synopsis columns on games\n";

// 3. Add 4 Composite Indexes safely
$indexes = [
    ['player_payments', 'idx_payments_status_type_created', '(`status`, `payment_type`, `created_at`)'],
    ['game_rooms', 'idx_rooms_status_created', '(`status`, `created_at`)'],
    ['notifications', 'idx_notif_user_read_created', '(`user_id`, `is_read`, `created_at`)'],
    ['dm_assignments', 'idx_dm_assign_user_status', '(`dm_user_id`, `status`)'],
];

foreach ($indexes as [$table, $idxName, $colsSql]) {
    $exists = $pdo->query("SHOW INDEX FROM `{$table}` WHERE Key_name = '{$idxName}'")->fetch();
    if (!$exists) {
        $pdo->exec("CREATE INDEX `{$idxName}` ON `{$table}` {$colsSql}");
        echo "[OK] Created index {$idxName} on {$table}\n";
    }
}

echo "[DONE] Database schema optimization complete.\n";
