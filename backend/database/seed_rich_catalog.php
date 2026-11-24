<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Infrastructure\Config\Config;
use App\Infrastructure\Database\DatabaseConnection;

Config::load(dirname(__DIR__));
$pdo = DatabaseConnection::getInstance();

$scenarios = require __DIR__ . '/catalog_42_scenarios.php';

echo "Seeding full 42 Jubensha scenarios (NO IMAGES: image_url = NULL) into database...\n";

$gameStmt = $pdo->prepare("
    INSERT INTO `games`
        (`id`, `name`, `slug`, `description`, `image_url`, `min_players`, `max_players`, `duration_minutes`, `status`, `is_18_plus`, `theme_code`, `display_order`, `created_at`, `updated_at`)
    VALUES
        (:id, :name, :slug, :description, NULL, :min_players, :max_players, :duration_minutes, 'ACTIVE', :is_18_plus, :theme_code, :display_order, NOW(), NOW())
    ON DUPLICATE KEY UPDATE
        `name` = VALUES(`name`),
        `slug` = VALUES(`slug`),
        `description` = VALUES(`description`),
        `image_url` = NULL,
        `min_players` = VALUES(`min_players`),
        `max_players` = VALUES(`max_players`),
        `duration_minutes` = VALUES(`duration_minutes`),
        `status` = 'ACTIVE',
        `is_18_plus` = VALUES(`is_18_plus`),
        `theme_code` = VALUES(`theme_code`),
        `display_order` = VALUES(`display_order`),
        `updated_at` = NOW()
");

$cfgStmt = $pdo->prepare("
    INSERT INTO `game_configurations`
        (`id`, `game_id`, `version`, `configuration`, `description`, `is_active`, `created_by`, `created_at`)
    VALUES
        (:cfg_id, :game_id, 1, :cfg, :desc_config, 1, 1, NOW())
    ON DUPLICATE KEY UPDATE
        `configuration` = VALUES(`configuration`),
        `description` = VALUES(`description`),
        `is_active` = 1
");

$delPriceStmt = $pdo->prepare("DELETE FROM `game_prices` WHERE `game_id` = :gid");
$insPriceStmt = $pdo->prepare("
    INSERT INTO `game_prices`
        (`game_id`, `price_per_person`, `start_time`, `end_time`, `effective_from`, `effective_until`, `status`, `created_at`, `updated_at`)
    VALUES
        (:gid, :p, :st, :et, '2026-01-01', '2030-12-31', 'ACTIVE', NOW(), NOW())
");

foreach ($scenarios as $sc) {
    $id = (int) $sc['id'];
    $props = array_map('trim', $sc['props']);

    $gameStmt->execute([
        ':id' => $id,
        ':name' => $sc['name'],
        ':slug' => $sc['slug'],
        ':description' => $sc['description'],
        ':min_players' => (int) $sc['min_players'],
        ':max_players' => (int) $sc['max_players'],
        ':duration_minutes' => (int) $sc['duration_minutes'],
        ':is_18_plus' => (int) $sc['is_18_plus'],
        ':theme_code' => (string) $sc['theme_code'],
        ':display_order' => $id,
    ]);

    $cfgJson = json_encode([
        'props' => $props,
        'clue_count' => count($props) * 6,
        'bgm_playlist' => $sc['slug'] . '_bgm',
        'genre' => $sc['genre'],
        'difficulty' => $sc['difficulty'],
        'theme_code' => $sc['theme_code'],
    ], JSON_UNESCAPED_UNICODE);

    $cfgStmt->execute([
        ':cfg_id' => $id,
        ':game_id' => $id,
        ':cfg' => $cfgJson,
        ':desc_config' => "Kịch bản {$sc['genre']} - Độ khó: {$sc['difficulty']}",
    ]);

    $delPriceStmt->execute([':gid' => $id]);
    [$pMorning, $pAfternoon, $pEvening] = $sc['prices'];
    foreach ([
        ['p' => $pMorning, 's' => '09:00:00', 'e' => '13:00:00'],
        ['p' => $pAfternoon, 's' => '13:00:00', 'e' => '18:00:00'],
        ['p' => $pEvening, 's' => '18:00:00', 'e' => '23:00:00'],
    ] as $slot) {
        $insPriceStmt->execute([
            ':gid' => $id,
            ':p' => (int) $slot['p'],
            ':st' => $slot['s'],
            ':et' => $slot['e'],
        ]);
    }
}

$count = (int) $pdo->query("SELECT COUNT(*) FROM `games` WHERE `status` = 'ACTIVE'")->fetchColumn();
$imgCount = (int) $pdo->query("SELECT COUNT(*) FROM `games` WHERE `image_url` IS NOT NULL AND `image_url` != ''")->fetchColumn();
echo "Success! Total active scenarios in database: {$count} (scenarios with images: {$imgCount})\n";
