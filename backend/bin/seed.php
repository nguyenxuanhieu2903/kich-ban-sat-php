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
            (1, 3, 'Quản Trị Viên', 'admin@kichbansat.vn', '{$pwd}', '0901000001', NULL, 'ACTIVE', 0, NOW()),
            (2, 2, 'Host Hoàng Nam (DM)', 'dm.nam@kichbansat.vn', '{$pwd}', '0902000002', NULL, 'ACTIVE', 0, NOW()),
            (3, 1, 'Nguyễn Xuân Hiếu (Player)', 'player.hieu@kichbansat.vn', '{$pwd}', '0903000003', NULL, 'ACTIVE', 1, NOW()),
            (4, 1, 'Trần Minh Anh (Player)', 'minhanh@gmail.com', '{$pwd}', '0904000004', NULL, 'ACTIVE', 0, NOW())
        ON DUPLICATE KEY UPDATE
            `password_hash` = VALUES(`password_hash`),
            `full_name` = VALUES(`full_name`),
            `phone` = VALUES(`phone`),
            `student_verified` = VALUES(`student_verified`),
            `status` = VALUES(`status`);
    ");

    // 3. Seed Venues
    echo "[3/6] Seeding venues...\n";
    $pdo->exec("
        INSERT INTO `venues` (`id`, `name`, `address`, `phone`, `description`, `status`)
        VALUES
            (1, 'Kịch Bản Sát Station - Chi nhánh Quận 1', '123 Nguyễn Huệ, Phường Bến Nghé, Quận 1, TP. Hồ Chí Minh', '0901234567', 'Chi nhánh trung tâm với không gian nhập vai Châu Âu cổ điển', 'ACTIVE'),
            (2, 'Kịch Bản Sát Hub - Chi nhánh Bình Thạnh', '36/52 Nguyễn Gia Trí, Phường 25, Quận Bình Thạnh, TP. Hồ Chí Minh', '0915690033', 'Không gian hiện đại chuyên kịch bản kinh dị & trinh thám Á Đông', 'ACTIVE')
        ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `address` = VALUES(`address`), `phone` = VALUES(`phone`);
    ");

    // 4. Seed 10 Physical Rooms with matching theme_code
    echo "[4/6] Seeding 10 physical rooms across venues...\n";
    $pdo->exec("
        INSERT INTO `physical_rooms` (`id`, `venue_id`, `name`, `capacity`, `description`, `status`, `is_maintenance`, `theme_code`)
        VALUES
            (1, 1, 'Phòng Sherlock Holmes', 8, 'Phòng phong cách phòng khách quý tộc Anh quốc, ánh sáng ấm cúng', 'ACTIVE', 0, 'DETECTIVE'),
            (2, 1, 'Phòng VIP Huyết Tộc Dracula', 10, 'Phòng VIP rộng rãi, hệ thống âm thanh vòm và đèn đổi màu theo bối cảnh', 'ACTIVE', 0, 'VAMPIRE'),
            (3, 2, 'Phòng Cổ Trang Á Đông', 8, 'Bối cảnh dinh thự phong kiến, trà cụ và phục trang kèm theo', 'ACTIVE', 0, 'ORIENTAL'),
            (4, 1, 'Phòng Biệt Thự Thám Tử', 8, 'Không gian cổ điển Châu Âu với giá sách bí mật và két sắt cổ', 'ACTIVE', 0, 'DETECTIVE'),
            (5, 1, 'Phòng Mật Thất Gothic', 10, 'Thiết kế hầm rượu Gothic bí ẩn, phù hợp kịch bản kinh dị giật gân', 'ACTIVE', 0, 'GOTHIC'),
            (6, 1, 'Phòng Tòa Án Phán Xét', 12, 'Mô phỏng phiên tòa tranh luận kịch tính, bục thẩm phán và nhân chứng', 'ACTIVE', 0, 'COURT'),
            (7, 2, 'Phòng Trinh Thám Hiện Đại', 8, 'Văn phòng thám tử tư hiện đại với bảng manh mối và máy ghi âm', 'ACTIVE', 0, 'MODERN'),
            (8, 2, 'Phòng Quán Rượu Thập Niên 80', 10, 'Không gian hoài niệm ánh đèn ấm, thích hợp kịch bản cơ chế và đấu trí', 'ACTIVE', 0, 'MODERN'),
            (9, 2, 'Phòng Tàu Hỏa Phương Đông', 8, 'Khoang tàu lửa sang trọng trên tuyến tốc hành xuyên biên giới', 'ACTIVE', 0, 'TRAIN'),
            (10, 2, 'Phòng Bệnh Viện Bỏ Hoang', 10, 'Không gian hành lang bệnh viện u ám với hồ sơ bệnh án cổ và trạm không gian', 'ACTIVE', 0, 'HOSPITAL')
        ON DUPLICATE KEY UPDATE
            `name` = VALUES(`name`),
            `capacity` = VALUES(`capacity`),
            `description` = VALUES(`description`),
            `status` = VALUES(`status`),
            `is_maintenance` = VALUES(`is_maintenance`),
            `theme_code` = VALUES(`theme_code`);
    ");

    // 5. Seed Promotions
    echo "[5/6] Seeding promotions (Tuesday 30% & Student discount)...\n";
    $pdo->exec("
        INSERT INTO `promotions` (`id`, `code`, `name`, `promotion_type`, `discount_type`, `discount_value`, `start_at`, `end_at`, `rules`, `is_active`)
        VALUES
            (1, 'TUESDAY_30', 'Ưu đãi vui vẻ - Thứ Ba Giảm 30%', 'WEEKDAY', 'PERCENT', 30, '2026-01-01 00:00:00', '2035-12-31 23:59:59', '{\"weekdays\": [2], \"stackable\": false, \"applies_to\": \"ANY_GAME\", \"valid_from\": \"09:00:00\", \"valid_until\": \"19:00:00\", \"minimum_booking_hours\": 24}', 1),
            (2, 'HSSV_20000', 'Ưu đãi học sinh sinh viên', 'STUDENT', 'FIXED', 20000, '2026-01-01 00:00:00', '2035-12-31 23:59:59', '{\"weekdays\": [1, 2, 3, 4], \"stackable\": false, \"valid_from\": \"09:00:00\", \"valid_until\": \"19:00:00\", \"discount_per_eligible_person\": 20000, \"requires_student_verification\": true}', 1)
        ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `rules` = VALUES(`rules`);
    ");

    // 6. Seed all 42 Jubensha scenarios (image_url = NULL)
    echo "[6/7] Seeding full catalog of 42 Jubensha scenarios (no images)...\n";
    require dirname(__DIR__) . '/database/seed_rich_catalog.php';

    // 7. Seed baseline demo rooms & session #1 for instant portal preview and test fixtures
    echo "[7/7] Seeding baseline demo rooms (#1, #2, #3) & session #1...\n";
    $pdo->exec("
        INSERT INTO `game_rooms` (`id`, `game_id`, `game_configuration_id`, `creator_id`, `title`, `description`, `min_players`, `max_players`, `status`, `booking_type`, `target_venue_id`, `total_players`, `confirmed_players`, `required_players`, `guest_slots`, `formation_deadline_at`, `created_at`, `updated_at`)
        VALUES
            (1, 1, 1, 3, 'Hội Thám Tử Cuối Tuần - Đêm Đoạt Mệnh', 'Tuyển đồng đội thích suy luận logic, ưu tiên người chơi mới hoặc đã chơi 1-2 kịch bản.', 6, 8, 'FORMING', 'PUBLIC', 1, 6, 2, 6, 0, DATE_ADD(NOW(), INTERVAL 5 DAY), NOW(), NOW()),
            (2, 3, 3, 3, 'Nhóm Bạn Riêng - Chuyến Tàu Tuyết Tối Thứ 7', 'Phòng kín dành riêng cho nhóm bạn đại học, mời thành viên qua liên kết có mã mời.', 6, 8, 'FORMING', 'PRIVATE', 2, 6, 1, 6, 0, DATE_ADD(NOW(), INTERVAL 4 DAY), NOW(), NOW()),
            (3, 2, 2, 3, 'Ca Chơi Chốt Lịch - Biệt Thự Huyết Tộc', 'Phiên chơi đã chốt lịch tại Quận 1 và có Quản trò Hoàng Nam tiếp nhận.', 5, 7, 'SCHEDULED', 'PUBLIC', 1, 6, 2, 5, 0, DATE_ADD(NOW(), INTERVAL 3 DAY), NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            `title` = VALUES(`title`),
            `status` = VALUES(`status`),
            `booking_type` = VALUES(`booking_type`),
            `target_venue_id` = VALUES(`target_venue_id`),
            `total_players` = VALUES(`total_players`),
            `formation_deadline_at` = VALUES(`formation_deadline_at`);
    ");

    $pdo->exec("
        INSERT IGNORE INTO `room_participants` (`id`, `game_room_id`, `user_id`, `status`, `joined_at`)
        VALUES
            (1, 1, 3, 'PENDING_DEPOSIT', NOW()),
            (2, 1, 4, 'PENDING_DEPOSIT', NOW()),
            (3, 2, 3, 'PENDING_DEPOSIT', NOW()),
            (4, 3, 3, 'DEPOSITED', NOW()),
            (5, 3, 4, 'PENDING_DEPOSIT', NOW());
    ");

    $pdo->exec("
        INSERT INTO `schedule_proposals` (`id`, `game_room_id`, `proposed_by`, `proposed_start_at`, `proposed_end_at`, `status`, `expires_at`, `confirmed_at`, `created_at`)
        VALUES
            (1, 3, 3, DATE_ADD(DATE_FORMAT(NOW(), '%Y-%m-%d 18:00:00'), INTERVAL 3 DAY), DATE_ADD(DATE_FORMAT(NOW(), '%Y-%m-%d 21:30:00'), INTERVAL 3 DAY), 'CONFIRMED', DATE_ADD(NOW(), INTERVAL 12 HOUR), NOW(), NOW()),
            (2, 1, 3, DATE_ADD(DATE_FORMAT(NOW(), '%Y-%m-%d 14:00:00'), INTERVAL 4 DAY), DATE_ADD(DATE_FORMAT(NOW(), '%Y-%m-%d 18:00:00'), INTERVAL 4 DAY), 'PROPOSED', DATE_ADD(NOW(), INTERVAL 12 HOUR), NULL, NOW())
        ON DUPLICATE KEY UPDATE
            `proposed_start_at` = VALUES(`proposed_start_at`),
            `proposed_end_at` = VALUES(`proposed_end_at`),
            `status` = VALUES(`status`);
    ");

    $pdo->exec("
        INSERT INTO `dm_assignments` (`id`, `game_room_id`, `dm_user_id`, `assigned_by`, `status`, `assigned_at`, `responded_at`, `created_at`, `updated_at`)
        VALUES
            (1, 3, 2, 1, 'ACCEPTED', NOW(), NOW(), NOW(), NOW())
        ON DUPLICATE KEY UPDATE `status` = VALUES(`status`), `dm_user_id` = VALUES(`dm_user_id`);
    ");

    $pdo->exec("
        INSERT INTO `sessions` (`id`, `game_room_id`, `schedule_proposal_id`, `dm_assignment_id`, `venue_id`, `physical_room_id`, `start_at`, `end_at`, `status`, `created_at`, `updated_at`)
        VALUES
            (1, 3, 1, 1, 1, 2, DATE_ADD(DATE_FORMAT(NOW(), '%Y-%m-%d 18:00:00'), INTERVAL 3 DAY), DATE_ADD(DATE_FORMAT(NOW(), '%Y-%m-%d 21:30:00'), INTERVAL 3 DAY), 'SCHEDULED', NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            `start_at` = VALUES(`start_at`),
            `end_at` = VALUES(`end_at`),
            `status` = VALUES(`status`);
    ");

    $pdo->exec("
        INSERT INTO `player_payments` (`id`, `room_participant_id`, `payment_type`, `amount`, `paid_amount`, `status`, `deadline_at`, `paid_at`, `created_at`, `updated_at`)
        VALUES
            (1, 4, 'DEPOSIT', 50000, 50000, 'PAID', DATE_ADD(NOW(), INTERVAL 2 HOUR), NOW(), NOW(), NOW()),
            (2, 5, 'DEPOSIT', 50000, 0, 'PENDING', DATE_ADD(NOW(), INTERVAL 2 HOUR), NULL, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            `amount` = VALUES(`amount`),
            `paid_amount` = VALUES(`paid_amount`),
            `status` = VALUES(`status`);
    ");

    $pdo->exec("
        INSERT IGNORE INTO `payment_transactions` (`id`, `player_payment_id`, `idempotency_key`, `transaction_reference`, `amount`, `status`, `requested_at`, `processed_at`, `created_at`)
        VALUES
            (1, 1, 'SEED_DEP_ROOM3_P3', 'TX_SEED_50000', 50000, 'SUCCESS', NOW(), NOW(), NOW());
    ");

    $pdo->exec("
        INSERT IGNORE INTO `audit_logs` (`id`, `actor_id`, `action`, `entity_type`, `entity_id`, `metadata`, `ip_address`, `created_at`)
        VALUES
            (1, 2, 'APPROVE_PROPOSAL', 'SCHEDULE_PROPOSAL', 1, '{\"room_id\": 3, \"session_id\": 1}', '127.0.0.1', NOW()),
            (2, 4, 'STUDENT_VERIFICATION_REQUESTED', 'USER', 4, '{\"student_card_id\": \"SV2026-HCMUS\", \"school_name\": \"ĐH Khoa Học Tự Nhiên\"}', '127.0.0.1', NOW());
    ");

    echo "\n[SUCCESS] Database seeded successfully with all 42 scenarios!\n";
    exit(0);
} catch (Throwable $e) {
    echo "\n[ERROR] Seeding failed: " . $e->getMessage() . "\n";
    exit(1);
}
