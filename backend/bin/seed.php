<?php

declare(strict_types=1);

/**
 * Kịch Bản Sát V16 - CLI Database Seeder
 * Usage: php bin/seed.php
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Infrastructure\Config\Config;
use App\Infrastructure\Database\DatabaseConnection;

Config::load(dirname(__DIR__));

echo "=====================================================\n";
echo " KỊCH BẢN SÁT V16 - DATABASE SEEDER RUNNER\n";
echo " Database: " . Config::get('DB_DATABASE', 'kich_ban_sat_v3') . "\n";
echo " Time:     " . date('Y-m-d H:i:s') . "\n";
echo "=====================================================\n\n";

try {
    $pdo = DatabaseConnection::getInstance();

    // 1. Seed Roles
    echo "[1/6] Seeding roles...\n";
    $pdo->exec("
        INSERT INTO `roles` (`id`, `code`, `name`, `description`)
        VALUES
            (1, 'PLAYER', 'Player', 'Người chơi tham gia kịch bản'),
            (2, 'DM', 'Dungeon Master', 'Quản trò / Dẫn truyện kịch bản án mạng'),
            (3, 'ADMIN', 'Administrator', 'Quản trị viên hệ thống')
        ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`);
    ");

    // 2. Seed Default Users
    echo "[2/6] Seeding sample users...\n";
    $pwd = password_hash('password123', PASSWORD_BCRYPT);
    $pdo->exec("
        INSERT INTO `users` (`id`, `role_id`, `full_name`, `email`, `password_hash`, `phone`, `avatar_url`, `status`, `student_verified`, `email_verified_at`)
        VALUES
            (1, 3, 'Quản Trị Viên', 'admin@kichbansat.vn', '{$pwd}', '0901000001', 'https://api.dicebear.com/7.x/bottts/svg?seed=Admin', 'ACTIVE', 0, NOW()),
            (2, 2, 'Host Hoàng Nam (DM)', 'dm.nam@kichbansat.vn', '{$pwd}', '0902000002', 'https://api.dicebear.com/7.x/bottts/svg?seed=HostNam', 'ACTIVE', 0, NOW()),
            (3, 1, 'Nguyễn Xuân Hiếu (Player)', 'player.hieu@kichbansat.vn', '{$pwd}', '0903000003', 'https://api.dicebear.com/7.x/bottts/svg?seed=HieuPlayer', 'ACTIVE', 1, NOW()),
            (4, 1, 'Trần Minh Anh (Player)', 'minhanh@gmail.com', '{$pwd}', '0904000004', 'https://api.dicebear.com/7.x/bottts/svg?seed=MinhAnh', 'ACTIVE', 0, NOW())
        ON DUPLICATE KEY UPDATE `full_name` = VALUES(`full_name`), `student_verified` = VALUES(`student_verified`);
    ");

    // 3. Seed Venues
    echo "[3/6] Seeding venues...\n";
    $pdo->exec("
        INSERT INTO `venues` (`id`, `name`, `address`, `phone`, `description`, `status`)
        VALUES
            (1, 'Kịch Bản Sát Station - Chi nhánh Quận 1', '123 Nguyễn Huệ, Phường Bến Nghé, Quận 1, TP. Hồ Chí Minh', '0901234567', 'Chi nhánh trung tâm với không gian nhập vai Châu Âu cổ điển', 'ACTIVE'),
            (2, 'Kịch Bản Sát Hub - Chi nhánh Bình Thạnh', '45 Điện Biên Phủ, Phường 15, Quận Bình Thạnh, TP. Hồ Chí Minh', '0907654321', 'Không gian hiện đại chuyên kịch bản kinh dị & trinh thám Á Đông', 'ACTIVE')
        ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);
    ");

    // 4. Seed Physical Rooms
    echo "[4/6] Seeding physical rooms...\n";
    $pdo->exec("
        INSERT INTO `physical_rooms` (`id`, `venue_id`, `name`, `capacity`, `description`, `status`)
        VALUES
            (1, 1, 'Phòng Sherlock Holmes', 8, 'Phòng phong cách phòng khách quý tộc Anh quốc, ánh sáng ấm cúng', 'ACTIVE'),
            (2, 1, 'Phòng VIP Huyết Tộc Dracula', 10, 'Phòng VIP rộng rãi, hệ thống âm thanh vòm và đèn đổi màu theo bối cảnh', 'ACTIVE'),
            (3, 2, 'Phòng Cổ Trang Á Đông', 8, 'Bối cảnh dinh thự phong kiến, trà cụ và phục trang kèm theo', 'ACTIVE')
        ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);
    ");

    // 5. Seed Games & Prices
    echo "[5/6] Seeding games & pricing tiers...\n";
    $pdo->exec("
        INSERT INTO `games` (`id`, `name`, `slug`, `description`, `image_url`, `min_players`, `max_players`, `duration_minutes`, `status`, `display_order`)
        VALUES
            (1, 'Đêm Đoạt Mệnh Tại Trang Viên', 'dem-doat-menh-tai-trang-vien', 'Một bữa tiệc gia tộc bí ẩn nơi người đứng đầu bị sát hại trong đêm giông bão. Mỗi người đều có một bí mật không thể tiết lộ.', 'https://images.unsplash.com/photo-1518709268805-4e9042af9f23', 6, 8, 240, 'ACTIVE', 1),
            (2, 'Biệt Thự Huyết Tộc', 'biet-thu-huyet-toc', 'Cuộc đối đầu tâm lý nghẹt thở giữa các gia tộc ma cà rồng cổ đại khi bảo vật ngàn năm biến mất.', 'https://images.unsplash.com/photo-1509198397868-475647b2a1e5', 5, 7, 210, 'ACTIVE', 2)
        ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);
    ");

    $pdo->exec("
        INSERT INTO `game_prices` (`id`, `game_id`, `price_per_person`, `start_time`, `end_time`, `effective_from`, `status`)
        VALUES
            (1, 1, 220000, '09:00:00', '19:59:59', '2026-01-01', 'ACTIVE'),
            (2, 1, 260000, '20:00:00', '23:59:59', '2026-01-01', 'ACTIVE'),
            (3, 2, 200000, '09:00:00', '19:59:59', '2026-01-01', 'ACTIVE'),
            (4, 2, 240000, '20:00:00', '23:59:59', '2026-01-01', 'ACTIVE')
        ON DUPLICATE KEY UPDATE `price_per_person` = VALUES(`price_per_person`), `effective_from` = VALUES(`effective_from`);
    ");

    // 6. Seed Promotions
    echo "[6/6] Seeding promotions (Tuesday 30% & Student discount)...\n";
    $pdo->exec("
        INSERT INTO `promotions` (`id`, `code`, `name`, `promotion_type`, `discount_type`, `discount_value`, `start_at`, `end_at`, `rules`, `is_active`)
        VALUES
            (1, 'TUESDAY_30', 'Ưu đãi vui vẻ - Thứ Ba Giảm 30%', 'WEEKDAY', 'PERCENT', 30, '2026-01-01 00:00:00', '2035-12-31 23:59:59', '{\"weekdays\": [2], \"stackable\": false, \"applies_to\": \"ANY_GAME\", \"valid_from\": \"09:00:00\", \"valid_until\": \"19:00:00\", \"minimum_booking_hours\": 24}', 1),
            (2, 'HSSV_20000', 'Ưu đãi học sinh sinh viên', 'STUDENT', 'FIXED', 20000, '2026-01-01 00:00:00', '2035-12-31 23:59:59', '{\"weekdays\": [1, 2, 3, 4], \"stackable\": false, \"valid_from\": \"09:00:00\", \"valid_until\": \"19:00:00\", \"discount_per_eligible_person\": 20000, \"requires_student_verification\": true}', 1)
        ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `rules` = VALUES(`rules`);
    ");

    echo "\n[SUCCESS] Database seeded successfully!\n";
    exit(0);
} catch (Throwable $e) {
    echo "\n[ERROR] Seeding failed: " . $e->getMessage() . "\n";
    exit(1);
}
