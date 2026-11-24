<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

class V3DataSeeder extends AbstractSeed
{
    /**
     * Nạp dữ liệu mẫu tham chiếu chuẩn cho CSDL V16 (42 kịch bản không cài ảnh, 10 phòng vật lý, 2 chi nhánh, 4 tài khoản).
     */
    public function run(): void
    {
        $this->execute("
            INSERT INTO `roles` (`id`, `code`, `name`, `description`)
            VALUES
                (1, 'PLAYER', 'Player', 'Người chơi tham gia kịch bản'),
                (2, 'DM', 'Dungeon Master', 'Quản trò / Dẫn truyện kịch bản án mạng'),
                (3, 'ADMIN', 'Administrator', 'Quản trị viên hệ thống')
            ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`);
        ");

        $defaultHash = password_hash('password123', PASSWORD_BCRYPT);
        $this->execute("
            INSERT INTO `users` (`id`, `role_id`, `full_name`, `email`, `password_hash`, `phone`, `avatar_url`, `status`, `student_verified`, `email_verified_at`)
            VALUES
                (1, 3, 'Quản Trị Viên', 'admin@kichbansat.vn', '{$defaultHash}', '0901000001', NULL, 'ACTIVE', 0, NOW()),
                (2, 2, 'Host Hoàng Nam (DM)', 'dm.nam@kichbansat.vn', '{$defaultHash}', '0902000002', NULL, 'ACTIVE', 0, NOW()),
                (3, 1, 'Nguyễn Xuân Hiếu (Player)', 'player.hieu@kichbansat.vn', '{$defaultHash}', '0903000003', NULL, 'ACTIVE', 1, NOW()),
                (4, 1, 'Trần Minh Anh (Player)', 'minhanh@gmail.com', '{$defaultHash}', '0904000004', NULL, 'ACTIVE', 0, NOW())
            ON DUPLICATE KEY UPDATE `full_name` = VALUES(`full_name`), `password_hash` = VALUES(`password_hash`), `student_verified` = VALUES(`student_verified`);
        ");

        $this->execute("
            INSERT INTO `venues` (`id`, `name`, `address`, `phone`, `description`, `status`)
            VALUES
                (1, 'Kịch Bản Sát Station - Chi nhánh Quận 1', '123 Nguyễn Huệ, Phường Bến Nghé, Quận 1, TP. Hồ Chí Minh', '0901234567', 'Chi nhánh trung tâm với không gian nhập vai phong cách Châu Âu cổ điển', 'ACTIVE'),
                (2, 'Kịch Bản Sát Hub - Chi nhánh Bình Thạnh', '36/52 Nguyễn Gia Trí, Phường 25, Quận Bình Thạnh, TP. Hồ Chí Minh', '0915690033', 'Không gian hiện đại chuyên kịch bản kinh dị & trinh thám Á Đông', 'ACTIVE')
            ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `address` = VALUES(`address`), `phone` = VALUES(`phone`);
        ");

        $this->execute("
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
            ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `theme_code` = VALUES(`theme_code`), `capacity` = VALUES(`capacity`);
        ");

        $scenarios = require dirname(__DIR__) . '/catalog_42_scenarios.php';
        $pdo = $this->getAdapter()->getConnection();

        $gameStmt = $pdo->prepare("
            INSERT INTO `games`
                (`id`, `name`, `slug`, `description`, `image_url`, `min_players`, `max_players`, `duration_minutes`, `status`, `is_18_plus`, `theme_code`, `display_order`, `created_at`, `updated_at`)
            VALUES
                (:id, :name, :slug, :description, NULL, :min_players, :max_players, :duration_minutes, 'ACTIVE', :is_18_plus, :theme_code, :display_order, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                `name` = VALUES(`name`), `description` = VALUES(`description`), `image_url` = NULL,
                `is_18_plus` = VALUES(`is_18_plus`), `theme_code` = VALUES(`theme_code`), `display_order` = VALUES(`display_order`)
        ");

        $cfgStmt = $pdo->prepare("
            INSERT INTO `game_configurations` (`id`, `game_id`, `version`, `configuration`, `description`, `is_active`, `created_by`, `created_at`)
            VALUES (:cfg_id, :game_id, 1, :cfg, :desc_config, 1, 1, NOW())
            ON DUPLICATE KEY UPDATE `configuration` = VALUES(`configuration`), `description` = VALUES(`description`), `is_active` = 1
        ");

        $delPriceStmt = $pdo->prepare("DELETE FROM `game_prices` WHERE `game_id` = :gid");
        $insPriceStmt = $pdo->prepare("
            INSERT INTO `game_prices` (`game_id`, `price_per_person`, `start_time`, `end_time`, `effective_from`, `effective_until`, `status`, `created_at`, `updated_at`)
            VALUES (:gid, :p, :st, :et, '2026-01-01', '2030-12-31', 'ACTIVE', NOW(), NOW())
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
                $insPriceStmt->execute([':gid' => $id, ':p' => (int) $slot['p'], ':st' => $slot['s'], ':et' => $slot['e']]);
            }
        }

        $this->execute("
            INSERT INTO `promotions` (`id`, `code`, `name`, `promotion_type`, `discount_type`, `discount_value`, `start_at`, `end_at`, `rules`, `is_active`)
            VALUES
                (1, 'TUESDAY_30', 'Ưu đãi vui vẻ - Thứ Ba Giảm 30%', 'WEEKDAY', 'PERCENT', 30, '2026-01-01 00:00:00.000', '2035-12-31 23:59:59.999', JSON_OBJECT('weekdays', JSON_ARRAY(2), 'valid_from', '09:00:00', 'valid_until', '19:00:00', 'minimum_booking_hours', 24, 'applies_to', 'ANY_GAME', 'stackable', FALSE), 1),
                (2, 'HSSV_20000', 'Ưu đãi học sinh sinh viên', 'STUDENT', 'FIXED', 20000, '2026-01-01 00:00:00.000', '2035-12-31 23:59:59.999', JSON_OBJECT('weekdays', JSON_ARRAY(1, 2, 3, 4), 'valid_from', '09:00:00', 'valid_until', '19:00:00', 'discount_per_eligible_person', 20000, 'requires_student_verification', TRUE, 'stackable', FALSE), 1)
            ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `rules` = VALUES(`rules`);
        ");

        $this->execute("
            INSERT INTO `game_rooms` (`id`, `game_id`, `game_configuration_id`, `creator_id`, `title`, `description`, `min_players`, `max_players`, `status`, `formation_deadline_at`)
            VALUES
                (1, 1, 1, 3, 'Tuyển thám tử phá án Đêm Đoạt Mệnh tối Thứ 7', 'Tìm thêm 4 bạn cùng sở thích suy luận, không toxic, chơi hết mình!', 6, 8, 'FORMING', DATE_ADD(NOW(), INTERVAL 3 DAY))
            ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);

            INSERT INTO `room_participants` (`id`, `game_room_id`, `user_id`, `status`, `joined_at`)
            VALUES
                (1, 1, 3, 'PENDING_DEPOSIT', NOW())
            ON DUPLICATE KEY UPDATE `status` = VALUES(`status`);
        ");
    }
}
