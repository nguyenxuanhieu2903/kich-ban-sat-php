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
        ON DUPLICATE KEY UPDATE `password_hash` = VALUES(`password_hash`), `full_name` = VALUES(`full_name`), `student_verified` = VALUES(`student_verified`), `status` = VALUES(`status`);
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
    echo "[4/7] Seeding 10 physical rooms across venues...\n";
    $pdo->exec("
        INSERT INTO `physical_rooms` (`id`, `venue_id`, `name`, `capacity`, `description`, `status`)
        VALUES
            (1, 1, 'Phòng Sherlock Holmes', 8, 'Phòng phong cách phòng khách quý tộc Anh quốc, ánh sáng ấm cúng', 'ACTIVE'),
            (2, 1, 'Phòng VIP Huyết Tộc Dracula', 10, 'Phòng VIP rộng rãi, hệ thống âm thanh vòm và đèn đổi màu theo bối cảnh', 'ACTIVE'),
            (3, 2, 'Phòng Cổ Trang Á Đông', 8, 'Bối cảnh dinh thự phong kiến, trà cụ và phục trang kèm theo', 'ACTIVE'),
            (4, 1, 'Phòng Biệt Thự Thám Tử', 8, 'Không gian cổ điển Châu Âu với giá sách bí mật và két sắt cổ', 'ACTIVE'),
            (5, 1, 'Phòng Mật Thất Gothic', 10, 'Thiết kế hầm rượu Gothic bí ẩn, phù hợp kịch bản kinh dị giật gân', 'ACTIVE'),
            (6, 1, 'Phòng Tòa Án Phán Xét', 12, 'Mô phỏng phiên tòa tranh luận kịch tính, bục thẩm phán và nhân chứng', 'ACTIVE'),
            (7, 2, 'Phòng Trinh Thám Hiện Đại', 8, 'Văn phòng thám tử tư New York thập niên 90 với bảng manh mối và máy ghi âm', 'ACTIVE'),
            (8, 2, 'Phòng Quán Rượu Thập Niên 80', 10, 'Quán pub hoài niệm ánh đèn neon, thích hợp kịch bản xã hội đen và gián điệp', 'ACTIVE'),
            (9, 2, 'Phòng Tàu Hỏa Phương Đông', 8, 'Khoang tàu lửa sang trọng trên tuyến tốc hành xuyên biên giới', 'ACTIVE'),
            (10, 2, 'Phòng Bệnh Viện Bỏ Hoang', 10, 'Không gian hành lang bệnh viện u ám với đèn chớp tắt và hồ sơ bệnh án cổ', 'ACTIVE')
        ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `capacity` = VALUES(`capacity`), `description` = VALUES(`description`), `status` = VALUES(`status`);
    ");

    // 5. Seed Games, Configurations & Prices
    echo "[5/7] Seeding games, configurations & pricing tiers...\n";
    $pdo->exec("
        INSERT INTO `games` (`id`, `name`, `slug`, `description`, `image_url`, `min_players`, `max_players`, `duration_minutes`, `status`, `display_order`)
        VALUES
            (1, 'Đêm Đoạt Mệnh Tại Trang Viên', 'dem-doat-menh-tai-trang-vien', 'Một bữa tiệc gia tộc bí ẩn nơi người đứng đầu bị sát hại trong đêm giông bão. Mỗi người đều có một bí mật không thể tiết lộ.', 'https://images.unsplash.com/photo-1518709268805-4e9042af9f23', 6, 8, 240, 'ACTIVE', 1),
            (2, 'Biệt Thự Huyết Tộc', 'biet-thu-huyet-toc', 'Cuộc đối đầu tâm lý nghẹt thở giữa các gia tộc ma cà rồng cổ đại khi bảo vật ngàn năm biến mất.', 'https://images.unsplash.com/photo-1509198397868-475647b2a1e5', 5, 7, 210, 'ACTIVE', 2),
            (3, 'Án Mạng Trên Chuyến Tàu Tuyết', 'an-mang-tren-chuyen-tau-tuyet', 'Chuyến tàu Bắc Cực bị mắc kẹt giữa bão tuyết và một thi thể được tìm thấy trong toa hành khách hạng nhất.', 'https://images.unsplash.com/photo-1515263487990-61b07816b324', 6, 8, 240, 'ACTIVE', 3)
        ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);
    ");

    $pdo->exec("
        INSERT INTO `game_configurations` (`id`, `game_id`, `version`, `configuration`, `description`, `is_active`, `created_by`)
        VALUES
            (1, 1, 1, '{\"props\": [\"Thư tống tiền\", \"Dao găm cổ\", \"Chiếc nhẫn ngọc bích\"], \"clue_count\": 24, \"bgm_playlist\": \"gothic_mystery_v1\"}', 'Phiên bản đạo cụ chuẩn 8 người chơi', 1, 1),
            (2, 2, 1, '{\"props\": [\"Bình rượu máu\", \"Gia huy bạc\", \"Cuốn nhật ký cổ\"], \"clue_count\": 20, \"bgm_playlist\": \"vampire_noir\"}', 'Phiên bản kinh dị tâm lý', 1, 1),
            (3, 3, 1, '{\"props\": [\"Vé tàu hạng nhất\", \"Đồng hồ quả quýt dừng ở 11h15\", \"Chai thuốc ngủ\"], \"clue_count\": 26, \"bgm_playlist\": \"snow_train_soundtrack\"}', 'Phiên bản suy luận cổ điển', 1, 1)
        ON DUPLICATE KEY UPDATE `version` = VALUES(`version`);
    ");

    $pdo->exec("
        INSERT INTO `game_prices` (`id`, `game_id`, `price_per_person`, `start_time`, `end_time`, `effective_from`, `effective_until`, `status`)
        VALUES
            (1, 1, 180000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
            (2, 1, 220000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
            (3, 1, 250000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
            (4, 2, 200000, '09:00:00', '17:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
            (5, 2, 240000, '17:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
            (6, 3, 200000, '09:00:00', '17:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
            (7, 3, 250000, '17:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE')
        ON DUPLICATE KEY UPDATE `price_per_person` = VALUES(`price_per_person`), `start_time` = VALUES(`start_time`), `end_time` = VALUES(`end_time`), `effective_from` = VALUES(`effective_from`), `status` = VALUES(`status`);
    ");

    // 6. Seed Promotions
    echo "[6/7] Seeding promotions (Tuesday 30% & Student discount)...\n";
    $pdo->exec("
        INSERT INTO `promotions` (`id`, `code`, `name`, `promotion_type`, `discount_type`, `discount_value`, `start_at`, `end_at`, `rules`, `is_active`)
        VALUES
            (1, 'TUESDAY_30', 'Ưu đãi vui vẻ - Thứ Ba Giảm 30%', 'WEEKDAY', 'PERCENT', 30, '2026-01-01 00:00:00', '2035-12-31 23:59:59', '{\"weekdays\": [2], \"stackable\": false, \"applies_to\": \"ANY_GAME\", \"valid_from\": \"09:00:00\", \"valid_until\": \"19:00:00\", \"minimum_booking_hours\": 24}', 1),
            (2, 'HSSV_20000', 'Ưu đãi học sinh sinh viên', 'STUDENT', 'FIXED', 20000, '2026-01-01 00:00:00', '2035-12-31 23:59:59', '{\"weekdays\": [1, 2, 3, 4], \"stackable\": false, \"valid_from\": \"09:00:00\", \"valid_until\": \"19:00:00\", \"discount_per_eligible_person\": 20000, \"requires_student_verification\": true}', 1)
        ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `rules` = VALUES(`rules`);
    ");

    // 7. Seed 9 additional rich Jubensha scenarios (Total: 12 scenarios)
    $richCatalogFile = dirname(__DIR__) . '/database/seed_rich_catalog.php';
    if (file_exists($richCatalogFile)) {
        echo "[7/7] Seeding full catalog (12 Jubensha scenarios)...\n";
        require $richCatalogFile;
    }

    echo "\n[SUCCESS] Database seeded successfully!\n";
    exit(0);
} catch (Throwable $e) {
    echo "\n[ERROR] Seeding failed: " . $e->getMessage() . "\n";
    exit(1);
}
