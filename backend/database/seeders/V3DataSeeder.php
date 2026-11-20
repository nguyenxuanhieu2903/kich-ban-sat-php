<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

class V3DataSeeder extends AbstractSeed
{
    /**
     * Nạp dữ liệu mẫu tham chiếu chuẩn cho CSDL V3
     */
    public function run(): void
    {
        // 1. Roles
        $this->execute("
            INSERT INTO `roles` (`id`, `code`, `name`, `description`)
            VALUES
                (1, 'PLAYER', 'Player', 'Người chơi tham gia kịch bản'),
                (2, 'DM', 'Dungeon Master', 'Quản trò / Dẫn truyện kịch bản án mạng'),
                (3, 'ADMIN', 'Administrator', 'Quản trị viên hệ thống')
            ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`);
        ");

        // 2. Sample Users
        $defaultHash = password_hash('password123', PASSWORD_BCRYPT); // 'password123'
        $this->execute("
            INSERT INTO `users` (`id`, `role_id`, `full_name`, `email`, `password_hash`, `phone`, `avatar_url`, `status`, `email_verified_at`)
            VALUES
                (1, 3, 'Quản Trị Viên', 'admin@kichbansat.vn', '{$defaultHash}', '0901000001', 'https://api.dicebear.com/7.x/bottts/svg?seed=Admin', 'ACTIVE', NOW()),
                (2, 2, 'Host Hoàng Nam (DM)', 'dm.nam@kichbansat.vn', '{$defaultHash}', '0902000002', 'https://api.dicebear.com/7.x/bottts/svg?seed=HostNam', 'ACTIVE', NOW()),
                (3, 1, 'Nguyễn Xuân Hiếu (Player)', 'player.hieu@kichbansat.vn', '{$defaultHash}', '0903000003', 'https://api.dicebear.com/7.x/bottts/svg?seed=HieuPlayer', 'ACTIVE', NOW()),
                (4, 1, 'Trần Minh Anh (Player)', 'minhanh@gmail.com', '{$defaultHash}', '0904000004', 'https://api.dicebear.com/7.x/bottts/svg?seed=MinhAnh', 'ACTIVE', NOW())
            ON DUPLICATE KEY UPDATE `full_name` = VALUES(`full_name`);
        ");

        // 3. Venues
        $this->execute("
            INSERT INTO `venues` (`id`, `name`, `address`, `phone`, `description`, `status`)
            VALUES
                (1, 'Kịch Bản Sát Station - Chi nhánh Quận 1', '123 Nguyễn Huệ, Phường Bến Nghé, Quận 1, TP. Hồ Chí Minh', '0901234567', 'Chi nhánh trung tâm với không gian nhập vai phong cách Châu Âu cổ điển', 'ACTIVE'),
                (2, 'Kịch Bản Sát Hub - Chi nhánh Bình Thạnh', '45 Điện Biên Phủ, Phường 15, Quận Bình Thạnh, TP. Hồ Chí Minh', '0907654321', 'Không gian hiện đại chuyên kịch bản kinh dị & trinh thám Á Đông', 'ACTIVE')
            ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);
        ");

        // 4. Physical Rooms
        $this->execute("
            INSERT INTO `physical_rooms` (`id`, `venue_id`, `name`, `capacity`, `description`, `status`)
            VALUES
                (1, 1, 'Phòng Sherlock Holmes', 8, 'Phòng phong cách phòng khách quý tộc Anh quốc, ánh sáng ấm cúng', 'ACTIVE'),
                (2, 1, 'Phòng VIP Huyết Tộc Dracula', 10, 'Phòng VIP rộng rãi, hệ thống âm thanh vòm và đèn đổi màu theo bối cảnh', 'ACTIVE'),
                (3, 2, 'Phòng Cổ Trang Á Đông', 8, 'Bối cảnh dinh thự phong kiến, trà cụ và phục trang kèm theo', 'ACTIVE')
            ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);
        ");

        // 5. Games
        $this->execute("
            INSERT INTO `games` (`id`, `name`, `slug`, `description`, `image_url`, `min_players`, `max_players`, `duration_minutes`, `status`, `display_order`)
            VALUES
                (1, 'Đêm Đoạt Mệnh Tại Trang Viên', 'dem-doat-menh-tai-trang-vien', 'Một bữa tiệc gia tộc bí ẩn nơi người đứng đầu bị sát hại trong đêm giông bão. Mỗi người đều có một bí mật không thể tiết lộ.', 'https://images.unsplash.com/photo-1518709268805-4e9042af9f23', 6, 8, 240, 'ACTIVE', 1),
                (2, 'Biệt Thự Huyết Tộc', 'biet-thu-huyet-toc', 'Cuộc đối đầu tâm lý nghẹt thở giữa các gia tộc ma cà rồng cổ đại khi bảo vật ngàn năm biến mất.', 'https://images.unsplash.com/photo-1509198397868-475647b2a1e5', 5, 7, 210, 'ACTIVE', 2),
                (3, 'Án Mạng Trên Chuyến Tàu Tuyết', 'an-mang-tren-chuyen-tau-tuyet', 'Chuyến tàu Bắc Cực bị mắc kẹt giữa bão tuyết và một thi thể được tìm thấy trong toa hành khách hạng nhất.', 'https://images.unsplash.com/photo-1515263487990-61b07816b324', 6, 8, 240, 'ACTIVE', 3)
            ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);
        ");

        // 6. Game Configurations
        $this->execute("
            INSERT INTO `game_configurations` (`id`, `game_id`, `version`, `configuration`, `description`, `is_active`, `created_by`)
            VALUES
                (1, 1, 1, JSON_OBJECT('props', JSON_ARRAY('Thư tống tiền', 'Dao găm cổ', 'Chiếc nhẫn ngọc bích'), 'clue_count', 24, 'bgm_playlist', 'gothic_mystery_v1'), 'Phiên bản đạo cụ chuẩn 8 người chơi', 1, 1),
                (2, 2, 1, JSON_OBJECT('props', JSON_ARRAY('Bình rượu máu', 'Gia huy bạc', 'Cuốn nhật ký cổ'), 'clue_count', 20, 'bgm_playlist', 'vampire_noir'), 'Phiên bản kinh dị tâm lý', 1, 1),
                (3, 3, 1, JSON_OBJECT('props', JSON_ARRAY('Vé tàu hạng nhất', 'Đồng hồ quả quýt dừng ở 11h15', 'Chai thuốc ngủ'), 'clue_count', 26, 'bgm_playlist', 'snow_train_soundtrack'), 'Phiên bản suy luận cổ điển', 1, 1)
            ON DUPLICATE KEY UPDATE `version` = VALUES(`version`);
        ");

        // 7. Game Prices
        $this->execute("
            INSERT INTO `game_prices` (`id`, `game_id`, `price_per_person`, `start_time`, `end_time`, `effective_from`, `effective_until`, `status`)
            VALUES
                (1, 1, 180000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
                (2, 1, 220000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
                (3, 1, 250000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
                (4, 2, 200000, '09:00:00', '17:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
                (5, 2, 240000, '17:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
                (6, 3, 200000, '09:00:00', '17:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
                (7, 3, 250000, '17:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE')
            ON DUPLICATE KEY UPDATE `price_per_person` = VALUES(`price_per_person`);
        ");

        // 8. Promotions
        $this->execute("
            INSERT INTO `promotions` (`id`, `code`, `name`, `promotion_type`, `discount_type`, `discount_value`, `start_at`, `end_at`, `rules`, `is_active`)
            VALUES
                (1, 'TUESDAY_30', 'Ưu đãi vui vẻ - Thứ Ba Giảm 30%', 'WEEKDAY', 'PERCENT', 30, '2026-01-01 00:00:00.000', '2035-12-31 23:59:59.999', JSON_OBJECT('weekdays', JSON_ARRAY(2), 'valid_from', '09:00:00', 'valid_until', '19:00:00', 'minimum_booking_hours', 24, 'applies_to', 'ANY_GAME', 'stackable', FALSE), 1),
                (2, 'HSSV_20000', 'Ưu đãi học sinh sinh viên', 'STUDENT', 'FIXED', 20000, '2026-01-01 00:00:00.000', '2035-12-31 23:59:59.999', JSON_OBJECT('weekdays', JSON_ARRAY(1, 2, 3, 4), 'valid_from', '09:00:00', 'valid_until', '19:00:00', 'discount_per_eligible_person', 20000, 'requires_student_verification', TRUE, 'stackable', FALSE), 1)
            ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `rules` = VALUES(`rules`);
        ");

        // 9. Sample Game Room & Participant
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
