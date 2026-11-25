-- =============================================================
-- KỊCH BẢN SÁT - HỆ THỐNG CƠ SỞ DỮ LIỆU PHIÊN BẢN V3
-- Hệ quản trị CSDL mục tiêu: MySQL 8.0.16+ / MySQL 8.4 LTS
-- Bộ mã ký tự: utf8mb4 (utf8mb4_0900_ai_ci)
-- Múi giờ mặc định: +07:00 (Asia/Ho_Chi_Minh)
--
-- TỔNG QUAN KIẾN TRÚC:
--   - 19 Bảng nghiệp vụ phân bổ vào 9 Domain rõ ràng
--   - 02 Bảng hạ tầng kỹ thuật (idempotency_records, outbox_events)
--   - Đã tối ưu hóa: nhúng Replacement Window & Price Snapshot vào game_rooms
--   - Đã hoàn thiện toàn bộ CHECK constraints, Generated Columns an toàn
-- =============================================================

CREATE DATABASE IF NOT EXISTS `kich_ban_sat_v3`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_0900_ai_ci;

USE `kich_ban_sat_v3`;

SET NAMES utf8mb4;
SET time_zone = '+07:00';
SET FOREIGN_KEY_CHECKS = 0;

-- =============================================================
-- DOMAIN 1: IDENTITY & QUẢN TRỊ TÀI KHOẢN
-- =============================================================

-- 1. roles: Bảng vai trò người dùng (ADMIN, DM, PLAYER)
DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(30) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `description` VARCHAR(255) NULL,
    `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `updated_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3)
        ON UPDATE CURRENT_TIMESTAMP(3),

    PRIMARY KEY (`id`),
    CONSTRAINT `uq_roles_code` UNIQUE (`code`)
) ENGINE=InnoDB;

-- 2. users: Bảng tài khoản người dùng
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `role_id` BIGINT UNSIGNED NOT NULL,
    `full_name` VARCHAR(150) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(20) NULL,
    `avatar_url` VARCHAR(500) NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
    `student_verified` TINYINT(1) NOT NULL DEFAULT 0,
    `email_verified_at` DATETIME(3) NULL,
    `last_login_at` DATETIME(3) NULL,
    `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `updated_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3)
        ON UPDATE CURRENT_TIMESTAMP(3),

    PRIMARY KEY (`id`),
    CONSTRAINT `uq_users_email` UNIQUE (`email`),
    KEY `idx_users_role_status` (`role_id`, `status`),

    CONSTRAINT `fk_users_role`
        FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,

    CONSTRAINT `chk_users_status`
        CHECK (`status` IN ('ACTIVE', 'INACTIVE', 'SUSPENDED'))
) ENGINE=InnoDB;

-- =============================================================
-- DOMAIN 2: GAME CATALOG & CẤU HÌNH KỊCH BẢN
-- =============================================================

-- 3. games: Danh mục kịch bản Án Mạng / TRPG
DROP TABLE IF EXISTS `games`;
CREATE TABLE `games` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(200) NOT NULL,
    `slug` VARCHAR(220) NOT NULL,
    `description` TEXT NULL,
    `image_url` VARCHAR(500) NULL,
    `min_players` SMALLINT UNSIGNED NOT NULL,
    `max_players` SMALLINT UNSIGNED NOT NULL,
    `duration_minutes` SMALLINT UNSIGNED NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
    `is_18_plus` TINYINT(1) NOT NULL DEFAULT 0,
    `theme_code` VARCHAR(50) NOT NULL DEFAULT 'GENERAL',
    `display_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `updated_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3)
        ON UPDATE CURRENT_TIMESTAMP(3),

    PRIMARY KEY (`id`),
    CONSTRAINT `uq_games_slug` UNIQUE (`slug`),

    CONSTRAINT `chk_games_player_range`
        CHECK (`min_players` > 0 AND `max_players` >= `min_players`),
    CONSTRAINT `chk_games_status`
        CHECK (`status` IN ('ACTIVE', 'INACTIVE', 'DRAFT'))
) ENGINE=InnoDB;

-- 4. game_configurations: Cấu hình đạo cụ, phiên bản kịch bản theo version
DROP TABLE IF EXISTS `game_configurations`;
CREATE TABLE `game_configurations` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `game_id` BIGINT UNSIGNED NOT NULL,
    `version` INT UNSIGNED NOT NULL,
    `configuration` JSON NOT NULL,
    `description` TEXT NULL,
    `is_active` TINYINT NOT NULL DEFAULT 1,
    `created_by` BIGINT UNSIGNED NOT NULL,
    `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),

    PRIMARY KEY (`id`),
    CONSTRAINT `uq_game_configuration_version` UNIQUE (`game_id`, `version`),
    CONSTRAINT `uq_game_configuration_game_id_id` UNIQUE (`game_id`, `id`),
    KEY `idx_game_configuration_active` (`game_id`, `is_active`),

    CONSTRAINT `fk_game_configuration_game`
        FOREIGN KEY (`game_id`) REFERENCES `games`(`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_game_configuration_creator`
        FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,

    CONSTRAINT `chk_game_configuration_active`
        CHECK (`is_active` IN (0, 1))
) ENGINE=InnoDB;

-- =============================================================
-- DOMAIN 3: GAMEROOM, THÀNH VIÊN & CỬA SỔ THAY THẾ
-- =============================================================

-- 5. game_rooms: Phòng ghép người chơi (tích hợp Replacement Window & Snapshot Giá)
DROP TABLE IF EXISTS `game_rooms`;
CREATE TABLE `game_rooms` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `game_id` BIGINT UNSIGNED NOT NULL,
    `game_configuration_id` BIGINT UNSIGNED NOT NULL,
    `creator_id` BIGINT UNSIGNED NOT NULL,

    `title` VARCHAR(200) NOT NULL,
    `description` TEXT NULL,
    `min_players` SMALLINT UNSIGNED NOT NULL,
    `max_players` SMALLINT UNSIGNED NOT NULL,
        `status` VARCHAR(40) NOT NULL DEFAULT 'FORMING',
    `booking_type` VARCHAR(20) NOT NULL DEFAULT 'PUBLIC',
    `target_venue_id` BIGINT UNSIGNED NULL,
    `target_time_slot` VARCHAR(100) NULL,
    `total_players` SMALLINT UNSIGNED NULL,
    `confirmed_players` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    `required_players` SMALLINT UNSIGNED NULL,
    `guest_slots` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `is_18_plus_acknowledged` TINYINT(1) NOT NULL DEFAULT 0,
    `formation_deadline_at` DATETIME(3) NOT NULL,
    `cancel_reason` TEXT NULL,
    `cancelled_at` DATETIME(3) NULL,

    -- Cửa sổ tìm người thay thế (Replacement Window 1:0..1)
    `replacement_opened_by` BIGINT UNSIGNED NULL,
    `replacement_reason` TEXT NULL,
    `replacement_started_at` DATETIME(3) NULL,
    `replacement_deadline_at` DATETIME(3) NULL,
    `replacement_status` VARCHAR(20) NULL,
    `replacement_closed_at` DATETIME(3) NULL,

    -- Ảnh chụp giá phòng khi chốt lịch (Booking Price Snapshot 1:0..1)
    `price_total_participants` SMALLINT UNSIGNED NULL,
    `price_subtotal` BIGINT UNSIGNED NULL,
    `price_discount_amount` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `price_final_amount` BIGINT UNSIGNED NULL,
    `price_deposit_total` BIGINT UNSIGNED NULL,
    `price_remaining_total` BIGINT UNSIGNED NULL,
    `price_promotion_id` BIGINT UNSIGNED NULL,
    `price_details` JSON NULL,
    `price_calculated_at` DATETIME(3) NULL,

    `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `updated_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3)
        ON UPDATE CURRENT_TIMESTAMP(3),

    PRIMARY KEY (`id`),
    CONSTRAINT `uq_game_room_game_id_id` UNIQUE (`game_id`, `id`),

    KEY `idx_game_rooms_status_created` (`status`, `created_at`),
    KEY `idx_game_rooms_game_status` (`game_id`, `status`),
    KEY `idx_game_rooms_creator_status` (`creator_id`, `status`),
    KEY `idx_game_rooms_replacement_deadline` (`replacement_status`, `replacement_deadline_at`),
    KEY `idx_game_rooms_price_promotion` (`price_promotion_id`),

    CONSTRAINT `fk_game_rooms_game`
        FOREIGN KEY (`game_id`) REFERENCES `games`(`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_game_rooms_configuration`
        FOREIGN KEY (`game_id`, `game_configuration_id`)
        REFERENCES `game_configurations`(`game_id`, `id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_game_rooms_creator`
        FOREIGN KEY (`creator_id`) REFERENCES `users`(`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_game_rooms_replacement_opener`
        FOREIGN KEY (`replacement_opened_by`) REFERENCES `users`(`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,

    CONSTRAINT `chk_game_rooms_player_range`
        CHECK (`min_players` > 0 AND `max_players` >= `min_players`),
    CONSTRAINT `chk_game_rooms_replacement_status`
        CHECK (
            `replacement_status` IS NULL
            OR `replacement_status` IN ('OPEN', 'CLOSED', 'EXPIRED')
        ),
    CONSTRAINT `chk_game_rooms_replacement_time`
        CHECK (
            `replacement_started_at` IS NULL
            OR `replacement_deadline_at` IS NULL
            OR `replacement_deadline_at` > `replacement_started_at`
        ),
    CONSTRAINT `chk_game_rooms_price`
        CHECK (
            `price_subtotal` IS NULL
            OR (
                `price_discount_amount` <= `price_subtotal`
                AND `price_final_amount` IS NOT NULL
                AND `price_deposit_total` IS NOT NULL
                AND `price_remaining_total` IS NOT NULL
                AND `price_details` IS NOT NULL
                AND `price_total_participants` IS NOT NULL
                AND `price_final_amount` = `price_subtotal` - `price_discount_amount`
                AND `price_deposit_total` + `price_remaining_total` = `price_final_amount`
                AND `price_total_participants` > 0
            )
        )
) ENGINE=InnoDB;

-- 6. room_participants: Thành viên tham gia phòng chơi
DROP TABLE IF EXISTS `room_participants`;
CREATE TABLE `room_participants` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `game_room_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `status` VARCHAR(40) NOT NULL DEFAULT 'PENDING_DEPOSIT',

    -- Cột sinh học: NULL khi đã rời phòng/bị hủy, mang user_id khi đang chiếm slot
    `active_user_id` BIGINT UNSIGNED
        GENERATED ALWAYS AS (
            CASE
                WHEN `status` IN ('PENDING_DEPOSIT', 'DEPOSITED', 'LEAVE_REQUESTED')
                THEN `user_id`
                ELSE NULL
            END
        ) STORED,

    `joined_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `deposit_deadline_at` DATETIME(3) NULL,
    `deposited_at` DATETIME(3) NULL,
    `left_at` DATETIME(3) NULL,
    `cancelled_at` DATETIME(3) NULL,
    `cancel_reason` TEXT NULL,
    `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `updated_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3)
        ON UPDATE CURRENT_TIMESTAMP(3),

    PRIMARY KEY (`id`),
    CONSTRAINT `uq_room_active_user` UNIQUE (`game_room_id`, `active_user_id`),
    CONSTRAINT `uq_room_participant_room_id` UNIQUE (`game_room_id`, `id`),

    KEY `idx_participants_room_status` (`game_room_id`, `status`),
    KEY `idx_participants_user_created` (`user_id`, `created_at`),
    KEY `idx_participants_deposit_deadline` (`status`, `deposit_deadline_at`),

    CONSTRAINT `fk_participants_game_room`
        FOREIGN KEY (`game_room_id`) REFERENCES `game_rooms`(`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_participants_user`
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,

    CONSTRAINT `chk_participant_status`
        CHECK (`status` IN (
            'PENDING_DEPOSIT', 'DEPOSITED', 'LEAVE_REQUESTED',
            'LEFT', 'CANCELLED_UNPAID', 'CANCELLED_BY_ADMIN'
        ))
) ENGINE=InnoDB;

-- =============================================================
-- DOMAIN 4: DM ASSIGNMENT (GÁN QUẢN TRÒ)
-- =============================================================

-- 7. dm_assignments: Phân công Quản trò (Host / DM) cho phòng chơi
DROP TABLE IF EXISTS `dm_assignments`;
CREATE TABLE `dm_assignments` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `game_room_id` BIGINT UNSIGNED NOT NULL,
    `dm_user_id` BIGINT UNSIGNED NOT NULL,
    `assigned_by` BIGINT UNSIGNED NOT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'PENDING',
    `assigned_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `responded_at` DATETIME(3) NULL,
    `revoked_at` DATETIME(3) NULL,
    `reason` TEXT NULL,
    `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `updated_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3)
        ON UPDATE CURRENT_TIMESTAMP(3),

    PRIMARY KEY (`id`),
    CONSTRAINT `uq_dm_assignment_room_id` UNIQUE (`game_room_id`, `id`),
    KEY `idx_dm_assignments_dm_status` (`dm_user_id`, `status`),
    KEY `idx_dm_assignments_room_status` (`game_room_id`, `status`),

    CONSTRAINT `fk_dm_assignment_room`
        FOREIGN KEY (`game_room_id`) REFERENCES `game_rooms`(`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_dm_assignment_user`
        FOREIGN KEY (`dm_user_id`) REFERENCES `users`(`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_dm_assignment_assigner`
        FOREIGN KEY (`assigned_by`) REFERENCES `users`(`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,

    CONSTRAINT `chk_dm_assignment_status`
        CHECK (`status` IN ('PENDING', 'ACCEPTED', 'REJECTED', 'REVOKED'))
) ENGINE=InnoDB;

-- =============================================================
-- DOMAIN 5: VENUE & PHYSICAL ROOM (CƠ SỞ VẬT CHẤT & PHÒNG THỰC TẾ)
-- =============================================================

-- 8. venues: Địa điểm, chi nhánh cửa hàng Kịch Bản Sát
DROP TABLE IF EXISTS `venues`;
CREATE TABLE `venues` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(200) NOT NULL,
    `address` VARCHAR(500) NOT NULL,
    `phone` VARCHAR(20) NULL,
    `description` TEXT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
    `is_maintenance` TINYINT(1) NOT NULL DEFAULT 0,
    `theme_code` VARCHAR(50) NOT NULL DEFAULT 'GENERAL',
    `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `updated_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3)
        ON UPDATE CURRENT_TIMESTAMP(3),

    PRIMARY KEY (`id`),
    KEY `idx_venues_status` (`status`),

    CONSTRAINT `chk_venues_status`
        CHECK (`status` IN ('ACTIVE', 'INACTIVE'))
) ENGINE=InnoDB;

-- 9. physical_rooms: Phòng chơi thực tế ngoài đời tại chi nhánh
DROP TABLE IF EXISTS `physical_rooms`;
CREATE TABLE `physical_rooms` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `venue_id` BIGINT UNSIGNED NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `capacity` SMALLINT UNSIGNED NOT NULL,
    `description` TEXT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
    `is_maintenance` TINYINT(1) NOT NULL DEFAULT 0,
    `theme_code` VARCHAR(50) NOT NULL DEFAULT 'GENERAL',
    `is_maintenance` TINYINT(1) NOT NULL DEFAULT 0,
    `theme_code` VARCHAR(50) NOT NULL DEFAULT 'GENERAL',
    `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `updated_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3)
        ON UPDATE CURRENT_TIMESTAMP(3),

    PRIMARY KEY (`id`),
    CONSTRAINT `uq_physical_room_venue_name` UNIQUE (`venue_id`, `name`),
    CONSTRAINT `uq_physical_room_venue_id` UNIQUE (`venue_id`, `id`),
    KEY `idx_physical_rooms_venue_status` (`venue_id`, `status`),

    CONSTRAINT `fk_physical_rooms_venue`
        FOREIGN KEY (`venue_id`) REFERENCES `venues`(`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,

    CONSTRAINT `chk_physical_room_capacity` CHECK (`capacity` > 0),
    CONSTRAINT `chk_physical_room_status`
        CHECK (`status` IN ('ACTIVE', 'INACTIVE'))
) ENGINE=InnoDB;

-- =============================================================
-- DOMAIN 6: SCHEDULING & SESSION (ĐỀ XUẤT LỊCH & BUỔI CHƠI)
-- =============================================================

-- 10. schedule_proposals: Đề xuất lịch chơi do thành viên khởi tạo
DROP TABLE IF EXISTS `schedule_proposals`;
CREATE TABLE `schedule_proposals` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `game_room_id` BIGINT UNSIGNED NOT NULL,
    `proposed_by` BIGINT UNSIGNED NOT NULL,
    `proposed_start_at` DATETIME(3) NOT NULL,
    `proposed_end_at` DATETIME(3) NOT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'PROPOSED',

    -- Đảm bảo mỗi GameRoom chỉ có tối đa 1 đề xuất đang mở bình chọn cùng lúc
    `active_game_room_id` BIGINT UNSIGNED
        GENERATED ALWAYS AS (
            CASE WHEN `status` = 'PROPOSED' THEN `game_room_id` ELSE NULL END
        ) STORED,

    `expires_at` DATETIME(3) NOT NULL,
    `confirmed_at` DATETIME(3) NULL,
    `rejected_at` DATETIME(3) NULL,
    `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),

    PRIMARY KEY (`id`),
    CONSTRAINT `uq_active_schedule_proposal` UNIQUE (`active_game_room_id`),
    CONSTRAINT `uq_schedule_proposal_room_id` UNIQUE (`game_room_id`, `id`),
    KEY `idx_schedule_proposals_room_status` (`game_room_id`, `status`),

    CONSTRAINT `fk_schedule_proposal_room`
        FOREIGN KEY (`game_room_id`) REFERENCES `game_rooms`(`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_schedule_proposal_user`
        FOREIGN KEY (`proposed_by`) REFERENCES `users`(`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,

    CONSTRAINT `chk_schedule_proposal_time`
        CHECK (`proposed_start_at` < `proposed_end_at`),
    CONSTRAINT `chk_schedule_proposal_status`
        CHECK (`status` IN ('PROPOSED', 'CONFIRMED', 'REJECTED', 'EXPIRED', 'CANCELLED'))
) ENGINE=InnoDB;

-- 11. schedule_responses: Biểu quyết của từng thành viên với đề xuất lịch
DROP TABLE IF EXISTS `schedule_responses`;
CREATE TABLE `schedule_responses` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `game_room_id` BIGINT UNSIGNED NOT NULL,
    `schedule_proposal_id` BIGINT UNSIGNED NOT NULL,
    `room_participant_id` BIGINT UNSIGNED NOT NULL,
    `response` VARCHAR(20) NOT NULL DEFAULT 'PENDING',
    `responded_at` DATETIME(3) NULL,
    `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),

    PRIMARY KEY (`id`),
    CONSTRAINT `uq_schedule_response_participant`
        UNIQUE (`schedule_proposal_id`, `room_participant_id`),
    KEY `idx_schedule_responses_room_response` (`game_room_id`, `response`),

    -- Composite FK chống vote chéo phòng
    CONSTRAINT `fk_schedule_response_proposal`
        FOREIGN KEY (`game_room_id`, `schedule_proposal_id`)
        REFERENCES `schedule_proposals`(`game_room_id`, `id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_schedule_response_participant`
        FOREIGN KEY (`game_room_id`, `room_participant_id`)
        REFERENCES `room_participants`(`game_room_id`, `id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,

    CONSTRAINT `chk_schedule_response`
        CHECK (`response` IN ('ACCEPTED', 'REJECTED', 'PENDING'))
) ENGINE=InnoDB;

-- 12. sessions: Buổi chơi thực tế đã chốt lịch, phòng và DM
DROP TABLE IF EXISTS `sessions`;
CREATE TABLE `sessions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `game_room_id` BIGINT UNSIGNED NOT NULL,
    `schedule_proposal_id` BIGINT UNSIGNED NOT NULL,
    `dm_assignment_id` BIGINT UNSIGNED NOT NULL,
    `venue_id` BIGINT UNSIGNED NOT NULL,
    `physical_room_id` BIGINT UNSIGNED NOT NULL,
    `start_at` DATETIME(3) NOT NULL,
    `end_at` DATETIME(3) NOT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'SCHEDULED',
    `started_at` DATETIME(3) NULL,
    `completed_at` DATETIME(3) NULL,
    `cancelled_at` DATETIME(3) NULL,
    `cancel_reason` TEXT NULL,
    `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `updated_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3)
        ON UPDATE CURRENT_TIMESTAMP(3),

    PRIMARY KEY (`id`),
    CONSTRAINT `uq_session_game_room` UNIQUE (`game_room_id`),
    CONSTRAINT `uq_session_schedule_proposal` UNIQUE (`schedule_proposal_id`),

    KEY `idx_sessions_room_status` (`game_room_id`, `status`),
    KEY `idx_sessions_physical_time` (`physical_room_id`, `status`, `start_at`, `end_at`),

    CONSTRAINT `fk_sessions_proposal`
        FOREIGN KEY (`game_room_id`, `schedule_proposal_id`)
        REFERENCES `schedule_proposals`(`game_room_id`, `id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_sessions_dm_assignment`
        FOREIGN KEY (`game_room_id`, `dm_assignment_id`)
        REFERENCES `dm_assignments`(`game_room_id`, `id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_sessions_physical_room`
        FOREIGN KEY (`venue_id`, `physical_room_id`)
        REFERENCES `physical_rooms`(`venue_id`, `id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,

    CONSTRAINT `chk_sessions_time` CHECK (`start_at` < `end_at`),
    CONSTRAINT `chk_sessions_status`
        CHECK (`status` IN ('SCHEDULED', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED'))
) ENGINE=InnoDB;

-- =============================================================
-- DOMAIN 7: PRICING & PROMOTION (GIÁ & KHUYẾN MÃI)
-- =============================================================

-- 13. game_prices: Bảng giá kịch bản theo khung giờ và khoảng ngày hiệu lực
DROP TABLE IF EXISTS `game_prices`;
CREATE TABLE `game_prices` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `game_id` BIGINT UNSIGNED NOT NULL,
    `price_per_person` BIGINT UNSIGNED NOT NULL,
    `start_time` TIME NOT NULL,
    `end_time` TIME NOT NULL,
    `effective_from` DATE NOT NULL,
    `effective_until` DATE NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
    `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `updated_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3)
        ON UPDATE CURRENT_TIMESTAMP(3),

    PRIMARY KEY (`id`),
    KEY `idx_game_prices_lookup`
        (`game_id`, `status`, `effective_from`, `effective_until`),

    CONSTRAINT `fk_game_prices_game`
        FOREIGN KEY (`game_id`) REFERENCES `games`(`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,

    CONSTRAINT `chk_game_prices_amount` CHECK (`price_per_person` > 0),
    CONSTRAINT `chk_game_prices_time` CHECK (`start_time` < `end_time`),
    CONSTRAINT `chk_game_prices_period`
        CHECK (`effective_until` IS NULL OR `effective_until` >= `effective_from`),
    CONSTRAINT `chk_game_prices_status`
        CHECK (`status` IN ('ACTIVE', 'INACTIVE'))
) ENGINE=InnoDB;

-- 14. promotions: Chương trình khuyến mãi, voucher giảm giá
DROP TABLE IF EXISTS `promotions`;
CREATE TABLE `promotions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(50) NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `promotion_type` VARCHAR(30) NOT NULL,
    `discount_type` VARCHAR(20) NOT NULL,
    `discount_value` BIGINT UNSIGNED NOT NULL,
    `start_at` DATETIME(3) NOT NULL,
    `end_at` DATETIME(3) NOT NULL,
    `rules` JSON NULL,
    `is_active` TINYINT NOT NULL DEFAULT 1,
    `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `updated_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3)
        ON UPDATE CURRENT_TIMESTAMP(3),

    PRIMARY KEY (`id`),
    CONSTRAINT `uq_promotions_code` UNIQUE (`code`),
    KEY `idx_promotions_active_period` (`is_active`, `start_at`, `end_at`),

    CONSTRAINT `chk_promotions_period` CHECK (`end_at` > `start_at`),
    CONSTRAINT `chk_promotions_discount_type`
        CHECK (`discount_type` IN ('PERCENT', 'FIXED')),
    CONSTRAINT `chk_promotions_discount_value` CHECK (`discount_value` > 0),
    CONSTRAINT `chk_promotions_active` CHECK (`is_active` IN (0, 1)),
    CONSTRAINT `chk_promotions_percent`
        CHECK (`discount_type` <> 'PERCENT' OR `discount_value` <= 100)
) ENGINE=InnoDB;

-- Liên kết khóa ngoại giữa game_rooms và promotions
ALTER TABLE `game_rooms`
    ADD CONSTRAINT `fk_game_rooms_price_promotion`
    FOREIGN KEY (`price_promotion_id`) REFERENCES `promotions`(`id`)
    ON DELETE RESTRICT ON UPDATE RESTRICT;

-- 15. participant_price_snapshots: Chi tiết snapshot giá cho từng thành viên
DROP TABLE IF EXISTS `participant_price_snapshots`;
CREATE TABLE `participant_price_snapshots` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `game_room_id` BIGINT UNSIGNED NOT NULL,
    `room_participant_id` BIGINT UNSIGNED NOT NULL,
    `base_price` BIGINT UNSIGNED NOT NULL,
    `discount_amount` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `final_amount` BIGINT UNSIGNED NOT NULL,
    `deposit_amount` BIGINT UNSIGNED NOT NULL,
    `remaining_amount` BIGINT UNSIGNED NOT NULL,
    `promotion_id` BIGINT UNSIGNED NULL,
    `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),

    PRIMARY KEY (`id`),
    CONSTRAINT `uq_participant_price_snapshot` UNIQUE (`room_participant_id`),
    KEY `idx_participant_snapshot_room` (`game_room_id`),
    KEY `idx_participant_snapshot_promotion` (`promotion_id`),

    CONSTRAINT `fk_participant_snapshot_room_participant`
        FOREIGN KEY (`game_room_id`, `room_participant_id`)
        REFERENCES `room_participants`(`game_room_id`, `id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_participant_snapshot_promotion`
        FOREIGN KEY (`promotion_id`) REFERENCES `promotions`(`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,

    CONSTRAINT `chk_participant_snapshot_amounts`
        CHECK (
            `discount_amount` <= `base_price`
            AND `final_amount` = `base_price` - `discount_amount`
            AND `deposit_amount` + `remaining_amount` = `final_amount`
        )
) ENGINE=InnoDB;

-- =============================================================
-- DOMAIN 8: PAYMENT & REFUND (THANH TOÁN & HOÀN TIỀN)
-- =============================================================

-- 16. player_payments: Theo dõi công nợ của người chơi (Cọc & Tất toán)
DROP TABLE IF EXISTS `player_payments`;
CREATE TABLE `player_payments` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `room_participant_id` BIGINT UNSIGNED NOT NULL,
    `payment_type` VARCHAR(20) NOT NULL,
    `amount` BIGINT UNSIGNED NOT NULL,
    `paid_amount` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `status` VARCHAR(30) NOT NULL DEFAULT 'PENDING',
    `deadline_at` DATETIME(3) NULL,
    `paid_at` DATETIME(3) NULL,
    `expired_at` DATETIME(3) NULL,
    `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `updated_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3)
        ON UPDATE CURRENT_TIMESTAMP(3),

    PRIMARY KEY (`id`),
    CONSTRAINT `uq_player_payment_type` UNIQUE (`room_participant_id`, `payment_type`),
    KEY `idx_player_payments_status_deadline` (`status`, `deadline_at`),

    CONSTRAINT `fk_player_payments_participant`
        FOREIGN KEY (`room_participant_id`) REFERENCES `room_participants`(`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,

    CONSTRAINT `chk_player_payment_type`
        CHECK (`payment_type` IN ('DEPOSIT', 'REMAINING')),
    CONSTRAINT `chk_player_payment_status`
        CHECK (`status` IN (
            'PENDING', 'PROCESSING', 'PAID', 'PARTIALLY_PAID',
            'FAILED', 'EXPIRED', 'CANCELLED'
        )),
    CONSTRAINT `chk_player_payment_amount`
        CHECK (`amount` > 0 AND `paid_amount` <= `amount`)
) ENGINE=InnoDB;

-- 17. payment_transactions: Lịch sử giao dịch thanh toán (có Idempotency Key)
DROP TABLE IF EXISTS `payment_transactions`;
CREATE TABLE `payment_transactions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `player_payment_id` BIGINT UNSIGNED NOT NULL,
    `idempotency_key` VARCHAR(100) NOT NULL,
    `transaction_reference` VARCHAR(150) NULL,
    `amount` BIGINT UNSIGNED NOT NULL,
    `status` VARCHAR(30) NOT NULL DEFAULT 'PENDING',
    `provider_response` JSON NULL,
    `requested_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `processed_at` DATETIME(3) NULL,
    `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),

    PRIMARY KEY (`id`),
    CONSTRAINT `uq_payment_transaction_idempotency` UNIQUE (`idempotency_key`),
    KEY `idx_payment_transactions_payment_created` (`player_payment_id`, `created_at`),

    CONSTRAINT `fk_payment_transactions_payment`
        FOREIGN KEY (`player_payment_id`) REFERENCES `player_payments`(`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,

    CONSTRAINT `chk_payment_transaction_amount` CHECK (`amount` > 0),
    CONSTRAINT `chk_payment_transaction_status`
        CHECK (`status` IN ('PENDING', 'SUCCESS', 'FAILED', 'CANCELLED'))
) ENGINE=InnoDB;

-- 18. refunds: Yêu cầu và lịch sử hoàn tiền
DROP TABLE IF EXISTS `refunds`;
CREATE TABLE `refunds` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `player_payment_id` BIGINT UNSIGNED NOT NULL,
    `amount` BIGINT UNSIGNED NOT NULL,
    `reason` TEXT NOT NULL,
    `status` VARCHAR(30) NOT NULL DEFAULT 'PENDING',
    `requested_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `reviewed_by` BIGINT UNSIGNED NULL,
    `reviewed_at` DATETIME(3) NULL,
    `completed_at` DATETIME(3) NULL,
    `review_reason` TEXT NULL,
    `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `updated_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3)
        ON UPDATE CURRENT_TIMESTAMP(3),

    PRIMARY KEY (`id`),
    KEY `idx_refunds_payment_status` (`player_payment_id`, `status`),
    KEY `idx_refunds_reviewer` (`reviewed_by`),

    CONSTRAINT `fk_refunds_payment`
        FOREIGN KEY (`player_payment_id`) REFERENCES `player_payments`(`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_refunds_reviewer`
        FOREIGN KEY (`reviewed_by`) REFERENCES `users`(`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,

    CONSTRAINT `chk_refunds_amount` CHECK (`amount` > 0),
    CONSTRAINT `chk_refunds_status`
        CHECK (`status` IN (
            'PENDING', 'APPROVED', 'REJECTED',
            'PROCESSING', 'COMPLETED', 'FAILED'
        ))
) ENGINE=InnoDB;

-- =============================================================
-- DOMAIN 9: NOTIFICATIONS (THÔNG BÁO)
-- =============================================================

-- 19. notifications: Thông báo gửi tới người dùng
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `type` VARCHAR(50) NOT NULL,
    `title` VARCHAR(200) NOT NULL,
    `content` TEXT NOT NULL,
    `reference_type` VARCHAR(50) NULL,
    `reference_id` BIGINT UNSIGNED NULL,
    `is_read` TINYINT NOT NULL DEFAULT 0,
    `read_at` DATETIME(3) NULL,
    `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),

    PRIMARY KEY (`id`),
    KEY `idx_notifications_user_read_created` (`user_id`, `is_read`, `created_at`),

    CONSTRAINT `fk_notifications_user`
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,

    CONSTRAINT `chk_notifications_is_read` CHECK (`is_read` IN (0, 1))
) ENGINE=InnoDB;

-- =============================================================
-- INFRASTRUCTURE: IDEMPOTENCY & OUTBOX (HẠ TẦNG HỆ THỐNG)
-- =============================================================

-- 20. idempotency_records: Chống gọi trùng lặp API quan trọng
DROP TABLE IF EXISTS `idempotency_records`;
CREATE TABLE `idempotency_records` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `idempotency_key` VARCHAR(100) NOT NULL,
    `request_hash` CHAR(64) NOT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'PROCESSING',
    `response_code` SMALLINT UNSIGNED NULL,
    `response_body` JSON NULL,
    `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `expires_at` DATETIME(3) NOT NULL,

    PRIMARY KEY (`id`),
    CONSTRAINT `uq_idempotency_user_key` UNIQUE (`user_id`, `idempotency_key`),
    KEY `idx_idempotency_expiry` (`status`, `expires_at`),

    CONSTRAINT `fk_idempotency_user`
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,

    CONSTRAINT `chk_idempotency_status`
        CHECK (`status` IN ('PROCESSING', 'COMPLETED', 'FAILED'))
) ENGINE=InnoDB;

-- 21. outbox_events: Đảm bảo giao phát sự kiện an toàn (Transactional Outbox Pattern)
DROP TABLE IF EXISTS `outbox_events`;
CREATE TABLE `outbox_events` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `aggregate_type` VARCHAR(80) NOT NULL,
    `aggregate_id` BIGINT UNSIGNED NOT NULL,
    `event_type` VARCHAR(100) NOT NULL,
    `payload` JSON NOT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'PENDING',
    `attempts` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `available_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `processed_at` DATETIME(3) NULL,
    `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),

    PRIMARY KEY (`id`),
    KEY `idx_outbox_dispatch` (`status`, `available_at`),
    KEY `idx_outbox_aggregate` (`aggregate_type`, `aggregate_id`),

    CONSTRAINT `chk_outbox_status`
        CHECK (`status` IN ('PENDING', 'PROCESSING', 'PROCESSED', 'FAILED'))
) ENGINE=InnoDB;


-- 22. audit_logs: Nhật ký kiểm toán hệ thống (Stage 3 & V16)
DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
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
) ENGINE=InnoDB;

-- 23. offline_room_blocks: Khóa giữ chỗ phòng vật lý cho khách Hotline / Walk-in
DROP TABLE IF EXISTS `offline_room_blocks`;
CREATE TABLE `offline_room_blocks` (
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
) ENGINE=InnoDB;


SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================
-- SEED DATA ĐẦY ĐỦ CHO HỆ THỐNG KỊCH BẢN SÁT V16 (42 KỊCH BẢN KHÔNG CÀI ẢNH)
-- =============================================================

-- 1. Roles
INSERT INTO `roles` (`id`, `code`, `name`, `description`)
VALUES
    (1, 'PLAYER', 'Player', 'Người chơi tham gia kịch bản'),
    (2, 'DM', 'Dungeon Master', 'Quản trò / Dẫn truyện kịch bản án mạng'),
    (3, 'ADMIN', 'Administrator', 'Quản trị viên hệ thống')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`);

-- 2. Users (Mật khẩu mặc định chuẩn: 'password123')
INSERT INTO `users` (`id`, `role_id`, `full_name`, `email`, `password_hash`, `phone`, `avatar_url`, `status`, `student_verified`, `email_verified_at`)
VALUES
    (1, 3, 'Quản Trị Viên', 'admin@kichbansat.vn', '$2y$10$XHQBXTi96ur1Scf6wQuziufuSNHK1FptP0L.I/drLXTdqjpfqHxGq', '0901000001', NULL, 'ACTIVE', 0, NOW()),
    (2, 2, 'Host Hoàng Nam (DM)', 'dm.nam@kichbansat.vn', '$2y$10$XHQBXTi96ur1Scf6wQuziufuSNHK1FptP0L.I/drLXTdqjpfqHxGq', '0902000002', NULL, 'ACTIVE', 0, NOW()),
    (3, 1, 'Nguyễn Xuân Hiếu (Player)', 'player.hieu@kichbansat.vn', '$2y$10$XHQBXTi96ur1Scf6wQuziufuSNHK1FptP0L.I/drLXTdqjpfqHxGq', '0903000003', NULL, 'ACTIVE', 1, NOW()),
    (4, 1, 'Trần Minh Anh (Player)', 'minhanh@gmail.com', '$2y$10$XHQBXTi96ur1Scf6wQuziufuSNHK1FptP0L.I/drLXTdqjpfqHxGq', '0904000004', NULL, 'ACTIVE', 0, NOW())
ON DUPLICATE KEY UPDATE `full_name` = VALUES(`full_name`), `password_hash` = VALUES(`password_hash`), `student_verified` = VALUES(`student_verified`);

-- 3. Venues (Chi nhánh quán)
INSERT INTO `venues` (`id`, `name`, `address`, `phone`, `description`, `status`)
VALUES
    (1, 'Kịch Bản Sát Station - Chi nhánh Quận 1', '123 Nguyễn Huệ, Phường Bến Nghé, Quận 1, TP. Hồ Chí Minh', '0901234567', 'Chi nhánh trung tâm với không gian nhập vai phong cách Châu Âu cổ điển', 'ACTIVE'),
    (2, 'Kịch Bản Sát Hub - Chi nhánh Bình Thạnh', '36/52 Nguyễn Gia Trí, Phường 25, Quận Bình Thạnh, TP. Hồ Chí Minh', '0915690033', 'Không gian hiện đại chuyên kịch bản kinh dị & trinh thám Á Đông', 'ACTIVE')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `address` = VALUES(`address`), `phone` = VALUES(`phone`);

-- 4. Physical Rooms (10 Phòng chơi chuyên đề)
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
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `capacity` = VALUES(`capacity`), `description` = VALUES(`description`), `status` = VALUES(`status`), `is_maintenance` = VALUES(`is_maintenance`), `theme_code` = VALUES(`theme_code`);

-- 5. Games (Đầy đủ 42 kịch bản Jubensha - Tuyệt đối không cài ảnh: image_url = NULL)
INSERT INTO `games` (`id`, `name`, `slug`, `description`, `image_url`, `min_players`, `max_players`, `duration_minutes`, `status`, `is_18_plus`, `theme_code`, `display_order`)
VALUES
    (1, 'Cô Gái Bên Cửa Sổ', 'co-gai-ben-cua-so', 'Đêm mưa tầm tã tại khu chung cư cũ kỹ, bóng dáng một cô gái đứng bất động bên khung cửa sổ tầng bốn trước khi vụ án mạng khóa kín xảy ra. Sáu đến tám nhân chứng trong tòa nhà đều giấu kín mối liên hệ ngầm với nạn nhân.', NULL, 6, 8, 240, 'ACTIVE', 0, 'DETECTIVE', 1),
    (2, 'Gương', 'guong', 'Tại dinh thự cổ ngoại ô, tấm gương đồng cổ truyền qua ba thế hệ phản chiếu những ký ức bị bóp méo. Mỗi vị khách bước vào phòng soi đều phát hiện lời khai của người bên cạnh trái ngược hoàn toàn với hiện trường.', NULL, 5, 7, 210, 'ACTIVE', 0, 'GOTHIC', 2),
    (3, 'Nguyệt Lạc OA', 'nguyet-lac-oa', 'Khi trăng lặn xuống thung lũng Nguyệt Lạc, gia chủ thư trang gục ngã bên cuốn cổ thư vừa mở niêm phong. Mọi dấu chân quanh hiện trường đều biến mất kỳ lạ trước bình minh.', NULL, 6, 7, 240, 'ACTIVE', 0, 'DETECTIVE', 3),
    (4, 'Sinh Tử', 'sinh-tu', 'Bảy con người xa lạ tỉnh dậy quanh bàn cờ đá giữa tòa tháp biệt lập. Mỗi vòng suy luận không chỉ phơi bày hung thủ của vụ án năm xưa mà còn quyết định ai được bước qua cánh cửa cuối cùng.', NULL, 6, 8, 270, 'ACTIVE', 0, 'GOTHIC', 4),
    (5, 'Lý Luận Hư Cấu', 'ly-luan-hu-cau', 'Một tiểu thuyết gia trinh thám bị sát hại theo đúng chương cuối cuốn bản thảo chưa xuất bản. Để tìm ra sự thật, bàn chơi phải dựng lên những giả thuyết tưởng chừng phi lý nhất để bẻ gãy bằng chứng ngoại phạm hoàn hảo.', NULL, 5, 7, 270, 'ACTIVE', 0, 'DETECTIVE', 5),
    (6, 'Ngôi Nhà Ngọt Ngào', 'ngoi-nha-ngot-ngao', 'Bữa tối đoàn viên ấm cúng trong căn biệt thự đồi thông nhanh chóng biến sắc khi hộp nhạc trên gác mái tự động vang lên và cánh cửa chính bị khóa chặt từ bên ngoài.', NULL, 5, 6, 210, 'ACTIVE', 0, 'GOTHIC', 6),
    (7, 'Yandere 1', 'yandere-1', 'Buổi tiệc sinh nhật kín đáo của nhóm bạn thân trở thành màn đấu trí tâm lý căng thẳng khi những bức thư nặc danh tiết lộ sự chiếm hữu cực đoan và mối quan hệ chồng chéo giữa các thành viên.', NULL, 5, 6, 210, 'ACTIVE', 0, 'MODERN', 7),
    (8, 'Yandere 2', 'yandere-2', 'Hai năm sau biến cố cũ, sáu người nhận được thiệp mời tái ngộ tại căn hộ tầng thượng. Một vụ mất tích mới xảy ra ngay khi đồng hồ điểm nửa đêm.', NULL, 6, 7, 240, 'ACTIVE', 0, 'MODERN', 8),
    (9, 'Tuyết Hương Liên Hoàn Án', 'tuyet-huong-lien-hoan-an', 'Giữa trận đại tuyết phong tỏa dịch trạm biên thùy, ba vụ án nối tiếp xảy ra cùng mùi hương mai lạnh kỳ lạ vương trên cổ tay nạn nhân. Quần hùng buộc phải tìm ra chân hung trước khi tuyết lấp kín lối về.', NULL, 6, 8, 240, 'ACTIVE', 0, 'ORIENTAL', 9),
    (10, 'Lên Đường', 'len-duong', 'Trên chuyến tàu đêm xuyên qua miền ký ức, sáu hành khách mang theo sáu tấm vé một chiều cùng những lời hứa chưa kịp thực hiện với người đã khuất.', NULL, 5, 7, 240, 'ACTIVE', 0, 'TRAIN', 10),
    (11, '1 Giờ 30', '1-gio-30', 'Toàn bộ đồng hồ trong biệt thự đều vỡ mặt kính và dừng lại ở mốc 01:30 rạng sáng. Mỗi nghi phạm chỉ có bằng chứng ngoại phạm chênh nhau từng phút ngắn ngủi.', NULL, 6, 7, 240, 'ACTIVE', 0, 'DETECTIVE', 11),
    (12, 'Giày Thêu Hoa', 'giay-theu-hoa', 'Đêm vu quy tại đại trạch họ Thẩm biến thành tang sự khi chiếc kiệu hoa dừng trước cổng chỉ còn lại một đôi hài thêu chỉ đỏ và bức huyết thư cổ.', NULL, 6, 7, 240, 'ACTIVE', 0, 'ORIENTAL', 12),
    (13, '2/12', '2-12', 'Mười hai mảnh hồ sơ vụ án kinh điển bị khóa trong két thép ngân hàng, chỉ còn hai mảnh lộ diện. Tám bộ óc suy luận phải đối chiếu chuỗi thời gian chuẩn xác từng giây để mở khóa chân tướng.', NULL, 7, 8, 270, 'ACTIVE', 0, 'DETECTIVE', 13),
    (14, 'Điểm Danh', 'diem-danh', 'Lớp học tự习 buổi tối tại dãy nhà cũ bị cắt điện đúng 21:00. Cuốn sổ đầu bài trên bàn giáo viên xuất hiện thêm một cái tên đã chuyển trường từ ba năm trước.', NULL, 6, 8, 210, 'ACTIVE', 0, 'SCHOOL', 14),
    (15, 'Tần Số Cuối', 'tan-so-cuoi', 'Trạm quan trắc viễn thông trên đỉnh núi tuyết thu được tín hiệu vô tuyến lặp lại từ chính tọa độ của trạm vào ngày mai. Trong số các kỹ sư trực ca, một người đang che giấu mật lệnh hủy dữ liệu.', NULL, 6, 7, 240, 'ACTIVE', 0, 'SCIFI', 15),
    (16, 'Vòng Lặp Mưu Sát Đảo Mèo', 'vong-lap-muu-sat-dao-meo', 'Bảy du khách đặt chân lên hòn đảo biệt lập hình dáng loài mèo đen và bị cuốn vào chuỗi án mạng lặp đi lặp lại sau mỗi tiếng chuông hải đăng. Kịch bản giới hạn độ tuổi 18+ với chiều sâu tâm lý và suy luận đa tầng.', NULL, 6, 7, 270, 'ACTIVE', 1, 'GOTHIC', 16),
    (17, 'Bệnh Viện Phụ Sản Ái Âu', 'benh-vien-phu-san-ai-au', 'Bệnh viện tư nhân Ái Âu đóng cửa đột ngột sau một tai biến y khoa mười năm trước. Đêm nay, các bác sĩ, hộ sinh và nhân chứng cũ bị triệu tập trở lại khu nội trú khóa kín. Yêu cầu người chơi đủ 18 tuổi.', NULL, 6, 8, 240, 'ACTIVE', 1, 'HOSPITAL', 17),
    (18, 'Cậu Bé Váy Đỏ', 'cau-be-vay-do', 'Tại trang viên cổ hẻo lánh, bức tranh sơn dầu vẽ đứa trẻ mặc váy đỏ bị che phủ bằng vải đen suốt hai thập kỷ bất ngờ lộ diện trong buổi đọc di chúc. Kịch bản 18+ khai thác tâm lý tội phạm gay cấn.', NULL, 5, 7, 240, 'ACTIVE', 1, 'GOTHIC', 18),
    (19, 'Điều Dân', 'dieu-dan', 'Phiên xử án náo nhiệt bậc nhất huyện nha khi bảy vị dân lành ai cũng nhận mình vô tội nhưng lời khai lại vòng vo lật kèo liên tục. Kịch bản cơ chế hài hước bùng nổ tiếng cười.', NULL, 6, 8, 210, 'ACTIVE', 0, 'COURT', 19),
    (20, 'Tội Diễn Sư', 'toi-dien-su', 'Tại nhà hát kịch danh tiếng, đạo diễn bậc thầy gục chết ngay trên ghế khán phòng VIP khi buổi tổng duyệt vở án mạng vừa hạ màn hồi một.', NULL, 6, 8, 240, 'ACTIVE', 0, 'DETECTIVE', 20),
    (21, 'Kiếm Tiền', 'kiem-tien', 'Sáu đến tám nhà đầu tư bước vào cuộc đấu giá ngầm xuyên đêm để thâu tóm cổ phần tập đoàn và đồng thời vạch mặt kẻ biển thủ ngân quỹ dự án.', NULL, 6, 8, 210, 'ACTIVE', 0, 'MODERN', 21),
    (22, 'Cuộc Gọi Đến', 'cuoc-goi-den', 'Chiếc điện thoại bàn cổ trong căn biệt thự không kết nối đường dây bỗng đổ chuông đúng mười hai tiếng lúc nửa đêm, đọc chính xác từng việc làm bí mật của năm vị khách có mặt.', NULL, 5, 6, 210, 'ACTIVE', 0, 'MODERN', 22),
    (23, 'Thiên Hạ Đệ Nhất Tửu', 'thien-ha-de-nhat-tuu', 'Đại hội phẩm rượu mười năm mở một lần quy tụ các cao thủ tứ phương. Khi nắp vò Thiên Hạ Đệ Nhất Tửu vừa khui mở, chưởng quỹ tửu lâu đột tử ngay giữa đại sảnh.', NULL, 6, 8, 240, 'ACTIVE', 0, 'ORIENTAL', 23),
    (24, 'Ly Hôn Đi! Nhanh Lên!', 'ly-hon-di-nhanh-len', 'Ba cặp đôi cùng hội ngộ tại văn phòng luật sư để giải quyết thủ tục hôn nhân nhưng bất ngờ vướng vào vụ mất cắp viên kim cương gia bảo trị giá hàng chục tỷ đồng.', NULL, 6, 6, 210, 'ACTIVE', 0, 'MODERN', 24),
    (25, '100 Cách Chết NYC', '100-cach-chet-nyc', 'Tại căn penthouse giữa lòng New York, nhóm khách mời đều từng có ân oán tình cảm rối rắm với chủ tiệc. Những mưu kế trêu chọc chồng lên nhau tạo nên hiện trường rối như tơ vò.', NULL, 5, 7, 210, 'ACTIVE', 0, 'MODERN', 25),
    (26, 'Thông Báo Tìm Người', 'thong-bao-tim-nguoi', 'Một thị trấn sương mù ven rừng liên tiếp xuất hiện những tờ rơi tìm người mất tích có chân dung giống hệt sáu lữ khách vừa dừng chân tại nhà trọ địa phương.', NULL, 5, 7, 240, 'ACTIVE', 0, 'GOTHIC', 26),
    (27, 'Thược Dược Đen', 'thuoc-duoc-den', 'Tại câu lạc bộ quý tộc London thập niên 1930, một nữ danh ca gục ngã trong phòng thay đồ khóa kín, bên cạnh là nhành thược dược đen còn đọng sương đêm.', NULL, 6, 8, 240, 'ACTIVE', 0, 'DETECTIVE', 27),
    (28, 'Niên Luân', 'nien-luan', 'Kiệt tác suy luận kinh điển: từng vòng vân gỗ trong căn nhà cổ miền núi tương ứng với một tầng sự thật kéo dài suốt mười tám năm. Đòi hỏi khả năng xâu chuỗi chi tiết sắc bén.', NULL, 5, 6, 270, 'ACTIVE', 0, 'DETECTIVE', 28),
    (29, 'Vụ Nhà Quán', 'vu-nha-quan', 'Tòa kiến trúc kỳ dị mang tên Vụ Nhà Quán nằm cheo leo bên sườn vực chỉ có một cây cầu treo duy nhất nối với thế giới bên ngoài. Đêm đầu tiên, cầu treo bị cắt đứt trong sương mù.', NULL, 6, 7, 240, 'ACTIVE', 0, 'ORIENTAL', 29),
    (30, 'Yandere 3', 'yandere-3', 'Hồi kết của bộ ba tâm lý giật gân đình đám: những nhân vật tưởng chừng là người ngoài cuộc ở hai vụ việc trước chính thức bước lên bàn đối chất cuối cùng.', NULL, 6, 7, 240, 'ACTIVE', 0, 'MODERN', 30),
    (31, 'Thanh Lâu', 'thanh-lau', 'Tại nhạc phường phồn hoa bậc nhất kinh thành, bức mật hàm liên quan vận mệnh biên cương biến mất ngay trong đêm đấu giá khúc nhạc mới của hoa khôi.', NULL, 6, 8, 240, 'ACTIVE', 0, 'ORIENTAL', 31),
    (32, 'Hậu Cung', 'hau-cung', 'Đêm trung thu ngự hoa viên, chén canh sâm tiến thượng bị phát hiện có độc. Các cung tần, hoàng tử và thái y vừa phải tự minh oan vừa đấu mưu bảo toàn thế lực.', NULL, 7, 8, 240, 'ACTIVE', 0, 'ORIENTAL', 32),
    (33, 'Thanh Thanh Man', 'thanh-thanh-man', 'Câu chuyện cổ phong đượm chất thơ về sáu người trẻ lớn lên bên bờ Giang Nam, bị dòng xoáy gia biến và chiến loạn cuốn đi, để rồi gặp lại nhau trước mộ phần cố nhân.', NULL, 6, 6, 240, 'ACTIVE', 0, 'ORIENTAL', 33),
    (34, 'Khách Điếm Hồ Ly', 'khach-diem-ho-ly', 'Giữa ranh giới nhân gian và linh giới, Khách Điếm Hồ Ly chỉ mở cửa vào đêm trăng máu để tiếp đón sáu vị khách đến chuộc lại nhân duyên và giải mã cái chết của vị pháp sư năm xưa.', NULL, 5, 7, 240, 'ACTIVE', 0, 'ORIENTAL', 34),
    (35, 'Lý Xuyên Quái Đàm Bộ', 'ly-xuyen-quai-dam-bo', 'Sáu câu chuyện truyền thuyết đô thị tại thị trấn Lý Xuyên tưởng chừng rời rạc lại hội tụ về cùng một vụ án mạng khóa kín trong tiệm sách cổ cuối con dốc.', NULL, 6, 7, 240, 'ACTIVE', 0, 'ORIENTAL', 35),
    (36, 'Đền Thần Shimizu Hachiman', 'den-than-shimizu-hachiman', 'Trong đêm lễ hội mùa thu tại ngôi đền cổ trên sườn núi, vị thần chủ trông coi điện chính được phát hiện tử vong trong phòng tịnh tâm cài chốt gỗ từ bên trong.', NULL, 6, 8, 240, 'ACTIVE', 0, 'ORIENTAL', 36),
    (37, 'Xin Chào', 'xin-chao', 'Tại khu phố nhỏ yên bình thập niên 1990, sáu thành viên trong hai gia đình láng giềng cùng mở lại cuốn album ảnh cũ để tháo gỡ hiểu lầm kéo dài suốt hai mươi năm.', NULL, 5, 6, 210, 'ACTIVE', 0, 'MODERN', 37),
    (38, 'Thi Khúc Li Biệt', 'thi-khuc-li-biet', 'Trong nhạc viện cổ bên bờ sông sương mù, bản sonata li biệt vang lên từ căn phòng khóa kín đánh thức ký ức về vụ hỏa hoạn năm xưa và sự hy sinh thầm lặng.', NULL, 6, 7, 240, 'ACTIVE', 0, 'GOTHIC', 38),
    (39, 'Dư Hương', 'du-huong', 'Tại xưởng điều chế nước hoa thủ công, sáu vị khách mỗi người nhận được một lọ hương mang tên một kỷ niệm riêng tư gắn liền với biến cố mất tích của nhà điều hương trưởng.', NULL, 5, 6, 210, 'ACTIVE', 0, 'MODERN', 39),
    (40, 'Cổ Thụ Ngân Nga', 'co-thu-ngan-nga', 'Đoàn khảo sát thực vật học tiến vào khu bảo tồn rừng già nơi cây đại thụ ngàn năm phát ra âm thanh trầm bổng mỗi khi gió nổi lên, dẫn lối tới di tích bị lãng quên.', NULL, 6, 8, 240, 'ACTIVE', 0, 'GOTHIC', 40),
    (41, 'Martinez Chết Tại Kinh Kỳ Quán', 'martinez-chet-tai-kinh-ky-quan', 'Tác phẩm Honkaku chính thống: nhà sưu tầm nghệ thuật ngoại quốc Martinez bị sát hại trong căn phòng có hai lớp khóa cơ học tại lữ quán Kinh Kỳ giữa bão tuyết.', NULL, 6, 8, 270, 'ACTIVE', 0, 'DETECTIVE', 41),
    (42, 'Lập Phương Quán', 'lap-phuong-quan', 'Tòa biệt thự hình khối lập phương gồm hai mươi bảy căn phòng có thể dịch chuyển vị trí theo chu kỳ giờ chẵn. Để chứng minh ai là hung thủ, người chơi phải giải mã cơ chế vận hành của cả tòa nhà.', NULL, 6, 8, 270, 'ACTIVE', 0, 'SCIFI', 42)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`), `image_url` = NULL, `is_18_plus` = VALUES(`is_18_plus`), `theme_code` = VALUES(`theme_code`), `display_order` = VALUES(`display_order`);

-- 6. Game Configurations (42 cấu hình đạo cụ & thể loại)
INSERT INTO `game_configurations` (`id`, `game_id`, `version`, `configuration`, `description`, `is_active`, `created_by`)
VALUES
    (1, 1, 1, '{"props":["Nhật ký bên bệ cửa sổ","Chiếc ô đen rách tán","Chìa khóa đồng căn hộ 404","Bản ghi âm tiếng mưa đêm"],"clue_count":24,"bgm_playlist":"co-gai-ben-cua-so_bgm","genre":"Kinh dị","difficulty":"Khó","difficulty_level":4,"subtitle":"BÍ MẬT BÊN Ô KÍNH MƯA ĐÊM","tagline":"Khi tiếng mưa đập vào ô kính, bóng hình ai đang đứng đợi ngoài màn đêm?","badge":"KINH DỊ - TRINH THÁM","theme_code":"DETECTIVE"}', 'Kịch bản Kinh dị - Độ khó: Khó', 1, 1),
    (2, 2, 1, '{"props":["Mảnh gương đồng khắc cổ tự","Nhật ký trị liệu tâm lý","Lọ thuốc an thần mất nhãn","Chân nến bạc ba nhánh"],"clue_count":24,"bgm_playlist":"guong_bgm","genre":"Tâm lý giật gân","difficulty":"Khó","difficulty_level":4,"subtitle":"LỜI NGUYỀN CHIẾC GƯƠNG MA ÁM","tagline":"Ngươi nhìn vào gương thấy chính mình, hay kẻ thủ ác đang mỉm cười sau lưng?","badge":"TÂM LÝ GIẬT GÂN","theme_code":"GOTHIC"}', 'Kịch bản Tâm lý giật gân - Độ khó: Khó', 1, 1),
    (3, 3, 1, '{"props":["Tập hồ sơ Nguyệt Lạc niêm sáp","Ấn chương ngọc khuyết góc","Bản đồ địa đạo thư trang","Lọ mực chu sa"],"clue_count":24,"bgm_playlist":"nguyet-lac-oa_bgm","genre":"Trinh thám","difficulty":"Khó","difficulty_level":5,"subtitle":"TẬP HỒ SƠ ÁN MẠNG NGUYỆT LẠC","tagline":"Trang sách cổ giấu kín lời khai giả và những cái chết không lời giải.","badge":"TRINH THÁM - 6 NGƯỜI","theme_code":"DETECTIVE"}', 'Kịch bản Trinh thám - Độ khó: Khó', 1, 1),
    (4, 4, 1, '{"props":["Quân cờ đen khắc số thứ tự","Thư triệu tập không tên","Đồng hồ cát ba mươi phút","Bản sơ đồ tháp đá"],"clue_count":24,"bgm_playlist":"sinh-tu_bgm","genre":"Hardcore","difficulty":"Rất khó","difficulty_level":5,"subtitle":"BÀN CỜ SINH TỬ GIỮA SƯƠNG ĐÊM","tagline":"Một bước đi sai, tính mạng thuộc về bóng tối lâu đài.","badge":"HARDCORE - 7 NGƯỜI","theme_code":"GOTHIC"}', 'Kịch bản Hardcore - Độ khó: Rất khó', 1, 1),
    (5, 5, 1, '{"props":["Bản thảo đánh máy chương cuối","Ruy băng mực đen đứt đoạn","Kính lúp đồng thau","Biên bản khám nghiệm hiện trường"],"clue_count":24,"bgm_playlist":"ly-luan-hu-cau_bgm","genre":"Hardcore","difficulty":"Rất khó","difficulty_level":5,"subtitle":"MẬT VỤ SUY LUẬN PHẢN NGHỊCH","tagline":"Khi logic bị bóp méo, câu chữ trên máy đánh chữ là lời thú tội.","badge":"HARDCORE SUY LUẬN","theme_code":"DETECTIVE"}', 'Kịch bản Hardcore - Độ khó: Rất khó', 1, 1),
    (6, 6, 1, '{"props":["Hộp nhạc gỗ gác mái","Bức ảnh gia đình bị xé góc","Chìa khóa hầm bánh kẹo","Thực đơn bữa tối viết tay"],"clue_count":24,"bgm_playlist":"ngoi-nha-ngot-ngao_bgm","genre":"Kinh dị","difficulty":"Trung bình","difficulty_level":3,"subtitle":"CƠN ÁC MỘNG TRONG DINH THỰ CỔ","tagline":"Tòa nhà ngọt ngào chỉ dành cho những linh hồn không còn lối về.","badge":"KINH DỊ - 6 NGƯỜI","theme_code":"GOTHIC"}', 'Kịch bản Kinh dị - Độ khó: Trung bình', 1, 1),
    (7, 7, 1, '{"props":["Nhật ký khóa số màu đỏ","Hộp quà sinh nhật giấu thư mật","Cuộn băng cassette ghi âm","Chiếc vòng tay bạc khắc chữ"],"clue_count":24,"bgm_playlist":"yandere-1_bgm","genre":"Tâm lý giật gân","difficulty":"Trung bình","difficulty_level":3,"subtitle":"ÁI TÌNH ĐIÊN CUỒNG VÀ BÓNG TỐI","tagline":"Nếu không thể giữ trọn lời thề, bí mật đêm nay sẽ khóa chặt tất cả.","badge":"TÂM LÝ GIẬT GÂN","theme_code":"MODERN"}', 'Kịch bản Tâm lý giật gân - Độ khó: Trung bình', 1, 1),
    (8, 8, 1, '{"props":["Phong thư niêm sáp đỏ","Thẻ thang máy tầng thượng","Ly rượu vang vương vết son","Lịch trình cuộc gọi ẩn danh"],"clue_count":24,"bgm_playlist":"yandere-2_bgm","genre":"Tâm lý giật gân","difficulty":"Khó","difficulty_level":4,"subtitle":"LÁ THƯ TÌNH CUỐI CÙNG","tagline":"Mật thư niêm phong bằng sáp đỏ chứng thực tội ác không thể tha thứ.","badge":"TÂM LÝ GIẬT GÂN","theme_code":"MODERN"}', 'Kịch bản Tâm lý giật gân - Độ khó: Khó', 1, 1),
    (9, 9, 1, '{"props":["Túi hương Tuyết Mai cung đình","Lệnh bài dịch trạm biên ải","Mật thư chữ triện","Trâm bạc gãy đôi"],"clue_count":24,"bgm_playlist":"tuyet-huong-lien-hoan-an_bgm","genre":"Cổ trang trinh thám","difficulty":"Khó","difficulty_level":4,"subtitle":"HUYẾT ÁN TUYẾT TRẮNG TỬ CẤM THÀNH","tagline":"Mùi hương thoang thoảng trong bão tuyết là điềm báo của án mạng liên hoàn.","badge":"CỔ TRANG TRINH THÁM","theme_code":"ORIENTAL"}', 'Kịch bản Cổ trang trinh thám - Độ khó: Khó', 1, 1),
    (10, 10, 1, '{"props":["Vé tàu hỏa khoang đêm","Chiếc đồng hồ quả quýt cũ","Bức thư chưa dán tem","Vali da nâu khóa đồng"],"clue_count":24,"bgm_playlist":"len-duong_bgm","genre":"Cảm xúc","difficulty":"Trung bình","difficulty_level":3,"subtitle":"CHUYẾN TÀU HOÀI NIỆM CUỐI CÙNG","tagline":"Còi tàu xé toạc màn sương, mang theo bí mật của những kẻ ly hương.","badge":"CẢM XÚC - 6 NGƯỜI","theme_code":"TRAIN"}', 'Kịch bản Cảm xúc - Độ khó: Trung bình', 1, 1),
    (11, 11, 1, '{"props":["Đồng hồ đeo tay vỡ kính ở 01:30","Sơ đồ vị trí phòng ngủ","Vỏ đạn đồng tại hành lang","Sổ ghi trực bảo vệ"],"clue_count":24,"bgm_playlist":"1-gio-30_bgm","genre":"Trinh thám","difficulty":"Khó","difficulty_level":4,"subtitle":"KHOẢNH KHẮC THỜI GIAN NGỪNG TRÔI","tagline":"Kim đồng hồ dừng lại đúng 1 giờ 30 phút, đánh dấu phát súng định mệnh.","badge":"TRINH THÁM - 6 NGƯỜI","theme_code":"DETECTIVE"}', 'Kịch bản Trinh thám - Độ khó: Khó', 1, 1),
    (12, 12, 1, '{"props":["Hài lụa thêu mẫu đơn đỏ","Đèn lồng giấy dán bùa niêm","Gia phả họ Thẩm khuyết trang","Khăn voan tân nương"],"clue_count":24,"bgm_playlist":"giay-theu-hoa_bgm","genre":"Kinh dị cổ trang","difficulty":"Khó","difficulty_level":4,"subtitle":"OAN HỒN DƯỚI ĐÈN LỒNG ĐỎ","tagline":"Đôi giày thêu hoa cất bước lúc nửa đêm mang theo lời nguyền truyền kiếp.","badge":"KINH DỊ CỔ TRANG","theme_code":"ORIENTAL"}', 'Kịch bản Kinh dị cổ trang - Độ khó: Khó', 1, 1),
    (13, 13, 1, '{"props":["Bảng mã số két sắt 12 ký tự","Hồ sơ niêm phong 02\\/12","Thẻ định danh kho lưu trữ","Biên bản giám định đường đạn"],"clue_count":24,"bgm_playlist":"2-12_bgm","genre":"Hardcore","difficulty":"Rất khó","difficulty_level":5,"subtitle":"MẬT MÃ KÉT SẮT TỬ THẦN","tagline":"Hai phần mười hai sự thật đang bị phong tỏa sau lớp thép lạnh.","badge":"HARDCORE - 8 NGƯỜI","theme_code":"DETECTIVE"}', 'Kịch bản Hardcore - Độ khó: Rất khó', 1, 1),
    (14, 14, 1, '{"props":["Sổ điểm danh lớp 12B","Thẻ học sinh mờ ảnh","Chìa khóa phòng truyền thống","Mảnh giấy nhắn trong ngăn bàn"],"clue_count":24,"bgm_playlist":"diem-danh_bgm","genre":"Học đường kinh dị","difficulty":"Trung bình","difficulty_level":3,"subtitle":"DANH SÁCH HỌC SINH BIẾN MẤT","tagline":"Khi tiếng chuông reo lên, người thứ mười ba trên bục giảng là ai?","badge":"HỌC ĐƯỜNG KINH DỊ","theme_code":"SCHOOL"}', 'Kịch bản Học đường kinh dị - Độ khó: Trung bình', 1, 1),
    (15, 15, 1, '{"props":["Băng từ ghi sóng ngắn","Bảng giải mã tần số Morse","Thẻ từ khoang máy chủ","Nhật ký trực trạm viễn thông"],"clue_count":24,"bgm_playlist":"tan-so-cuoi_bgm","genre":"Khoa học viễn tưởng","difficulty":"Khó","difficulty_level":4,"subtitle":"TÍN HIỆU SỐ BỊ NHIỄU LOẠN","tagline":"Tần số cuối cùng truyền về từ hố sâu không gian chứa đựng bí mật kinh hoàng.","badge":"KHOA HỌC VIỄN TƯỞNG","theme_code":"SCIFI"}', 'Kịch bản Khoa học viễn tưởng - Độ khó: Khó', 1, 1),
    (16, 16, 1, '{"props":["Bản đồ hải đăng Đảo Mèo","Tượng mèo đá khắc ký hiệu","Nhật ký tàu phà chuyến cuối","Mảnh đồng hồ vỡ kim"],"clue_count":24,"bgm_playlist":"vong-lap-muu-sat-dao-meo_bgm","genre":"18+ Giật gân","difficulty":"Rất khó","difficulty_level":5,"subtitle":"VÒNG LẶP TỬ ĐỊA ĐẢO HOANG","tagline":"Mỗi lần mặt trời lặn trên vách đá biển, vụ ám sát lại bắt đầu lại từ đầu.","badge":"18+ GIẬT GÂN","theme_code":"GOTHIC"}', 'Kịch bản 18+ Giật gân - Độ khó: Rất khó', 1, 1),
    (17, 17, 1, '{"props":["Hồ sơ bệnh án niêm phong mật","Thẻ trực ca đêm khoa nội","Chìa khóa kho lưu trữ hồ sơ","Sơ đồ tầng hầm bệnh viện"],"clue_count":24,"bgm_playlist":"benh-vien-phu-san-ai-au_bgm","genre":"18+ Kinh dị","difficulty":"Khó","difficulty_level":4,"subtitle":"BÍ MẬT HÀNH LANG PHÒNG SINH CŨ","tagline":"Tiếng bước chân vang vọng trong khu bệnh viện bỏ hoang giữa đêm mưa.","badge":"18+ KINH DỊ","theme_code":"HOSPITAL"}', 'Kịch bản 18+ Kinh dị - Độ khó: Khó', 1, 1),
    (18, 18, 1, '{"props":["Bức ký họa sơn dầu cổ","Bản di chúc gia tộc niêm sáp","Chìa khóa gác xép rỉ sét","Cuốn sổ gia nhân ghi chép"],"clue_count":24,"bgm_playlist":"cau-be-vay-do_bgm","genre":"18+ Ám ảnh","difficulty":"Khó","difficulty_level":4,"subtitle":"BÓNG HÌNH TRONG GÁC XÉP CỔ","tagline":"Ánh nhìn u tối từ bức chân dung sơn dầu giấu kín bi kịch gia tộc.","badge":"18+ ÁM ẢNH","theme_code":"GOTHIC"}', 'Kịch bản 18+ Ám ảnh - Độ khó: Khó', 1, 1),
    (19, 19, 1, '{"props":["Mộc bản kinh đường của quan huyện","Đơn kiện viết tay hài hước","Ngân phiếu thông hành giả thật","Lệnh bài nha dịch"],"clue_count":24,"bgm_playlist":"dieu-dan_bgm","genre":"Hài hước","difficulty":"Dễ","difficulty_level":3,"subtitle":"KỲ ÁN QUAN HUYỆN XỬ ÁN","tagline":"Bức tranh hoa mỹ giấu kín những mưu mẹo dở khóc dở cười chốn công đường.","badge":"HÀI HƯỚC HOAN LẠC","theme_code":"COURT"}', 'Kịch bản Hài hước - Độ khó: Dễ', 1, 1),
    (20, 20, 1, '{"props":["Kịch bản sân khấu đánh dấu đỏ","Mặt nạ nửa mặt mạ vàng","Vé mời hàng ghế danh dự","Chìa khóa phòng đạo cụ"],"clue_count":24,"bgm_playlist":"toi-dien-su_bgm","genre":"Trinh thám","difficulty":"Khó","difficulty_level":4,"subtitle":"VỞ DIỄN CUỐI CỦA SÁT NHÂN","tagline":"Dưới ánh đèn rọi sân khấu, kẻ đang diễn xuất là thám tử hay hung thủ?","badge":"TRINH THÁM - 7 NGƯỜI","theme_code":"DETECTIVE"}', 'Kịch bản Trinh thám - Độ khó: Khó', 1, 1),
    (21, 21, 1, '{"props":["Bộ phỉnh cổ phần thương hội","Hợp đồng đầu tư mật","Sổ cái kế toán hai tầng","Thẻ định danh hội đồng"],"clue_count":24,"bgm_playlist":"kiem-tien_bgm","genre":"Cơ chế đấu trí","difficulty":"Trung bình","difficulty_level":3,"subtitle":"VÁN CƯỢC ĐẤU TRÍ THƯƠNG TRƯỜNG","tagline":"Trên bàn thương thuyết, tiền bạc là mồi nhử còn chiến thuật quyết định người thắng.","badge":"CƠ CHẾ ĐẤU TRÍ","theme_code":"MODERN"}', 'Kịch bản Cơ chế đấu trí - Độ khó: Trung bình', 1, 1),
    (22, 22, 1, '{"props":["Ống nghe điện thoại quay số cổ","Sổ danh bạ viết mật mã","Băng ghi âm hộp thư thoại","Tờ hóa đơn bưu điện cũ"],"clue_count":24,"bgm_playlist":"cuoc-goi-den_bgm","genre":"Tâm lý giật gân","difficulty":"Trung bình","difficulty_level":3,"subtitle":"TIẾNG CHUÔNG LÚC NỬA ĐÊM","tagline":"Đừng nhấc máy nếu bạn chưa sẵn sàng đối diện với bí mật bị chôn giấu.","badge":"TÂM LÝ GIẬT GÂN","theme_code":"MODERN"}', 'Kịch bản Tâm lý giật gân - Độ khó: Trung bình', 1, 1),
    (23, 23, 1, '{"props":["Nậm rượu sứ Bạch Ngọc","Ngân châm thử độc","Lệnh bài võ lâm minh","Bí phương ủ rượu thất truyền"],"clue_count":24,"bgm_playlist":"thien-ha-de-nhat-tuu_bgm","genre":"Cổ trang kiếm hiệp","difficulty":"Trung bình","difficulty_level":4,"subtitle":"VÒ RƯỢU ĐẪM HẬN GIANG HỒ","tagline":"Chén rượu tương phùng tại tửu điếm hóa thành cuộc phân định ân oán võ lâm.","badge":"CỔ TRANG KIẾM HIỆP","theme_code":"ORIENTAL"}', 'Kịch bản Cổ trang kiếm hiệp - Độ khó: Trung bình', 1, 1),
    (24, 24, 1, '{"props":["Bản thỏa thuận tài sản tiền hôn nhân","Hộp nhẫn kim cương rỗng","Sao kê chi tiêu bí mật","Danh thiếp thám tử tư"],"clue_count":24,"bgm_playlist":"ly-hon-di-nhanh-len_bgm","genre":"Hài hước hoan lạc","difficulty":"Dễ","difficulty_level":3,"subtitle":"CUỘC CHIẾN TÀI SẢN VÀ HÔN NHÂN","tagline":"Trước khi ký đơn phân chia tài sản, hãy xem ai đang giấu quỹ đen giỏi nhất.","badge":"HÀI HƯỚC HOAN LẠC","theme_code":"MODERN"}', 'Kịch bản Hài hước hoan lạc - Độ khó: Dễ', 1, 1),
    (25, 25, 1, '{"props":["Sổ tay 100 kế hoạch trả đũa","Ly cocktail đổi màu","Thiệp mời dạ hội Manhattan","Chìa khóa tủ rượu thông minh"],"clue_count":24,"bgm_playlist":"100-cach-chet-nyc_bgm","genre":"Hài hước hoan lạc","difficulty":"Dễ","difficulty_level":3,"subtitle":"ĐÊM TIỆC TÁI NGỘ DỞ KHÓC DỞ CƯỜI","tagline":"Một trăm kịch bản trả đũa người yêu cũ hóa thành vụ án bất ngờ giữa bữa tiệc.","badge":"HÀI HƯỚC - 6 NGƯỜI","theme_code":"MODERN"}', 'Kịch bản Hài hước hoan lạc - Độ khó: Dễ', 1, 1),
    (26, 26, 1, '{"props":["Tờ rơi tìm người in đen trắng","Đèn pin tuần tra kiểm lâm","Sổ lưu trú lữ quán","Bản đồ đường mòn rừng thông"],"clue_count":24,"bgm_playlist":"thong-bao-tim-nguoi_bgm","genre":"Kinh dị tâm lý","difficulty":"Khó","difficulty_level":4,"subtitle":"DẤU VẾT TRONG RỪNG ĐEN","tagline":"Tờ thông báo tìm người dán trên thân cây thông đẫm sương đêm u tịch.","badge":"KINH DỊ TÂM LÝ","theme_code":"GOTHIC"}', 'Kịch bản Kinh dị tâm lý - Độ khó: Khó', 1, 1),
    (27, 27, 1, '{"props":["Đóa thược dược đen ép khô","Tờ nhật báo buổi chiều London","Ống tẩu thuốc chạm bạc","Thư tuyệt mệnh đánh máy"],"clue_count":24,"bgm_playlist":"thuoc-duoc-den_bgm","genre":"Trinh thám châu Âu","difficulty":"Khó","difficulty_level":4,"subtitle":"ĐÓA HOA ĐEN CHÂU ÂU CỔ ĐIỂN","tagline":"Vụ án mạng bí ẩn bậc nhất thành London đằng sau đóa hoa thược dược đen.","badge":"TRINH THÁM CHÂU ÂU","theme_code":"DETECTIVE"}', 'Kịch bản Trinh thám châu Âu - Độ khó: Khó', 1, 1),
    (28, 28, 1, '{"props":["Thớt gỗ khắc mười tám vòng niên luân","Nhật ký người thợ mộc già","Mảnh ngọc bội nứt đôi","Bản vẽ kết cấu mật thất gỗ"],"clue_count":24,"bgm_playlist":"nien-luan_bgm","genre":"Hardcore kinh điển","difficulty":"Rất khó","difficulty_level":5,"subtitle":"VÒNG XOAY THỜI GIAN MÊ CUNG","tagline":"Vân gỗ niên luân ghi dấu ký ức của những người đã từng bước vào vòng lặp.","badge":"HARDCORE KINH ĐIỂN","theme_code":"DETECTIVE"}', 'Kịch bản Hardcore kinh điển - Độ khó: Rất khó', 1, 1),
    (29, 29, 1, '{"props":["Bản thiết kế hai tầng Vụ Nhà Quán","Đoạn dây thừng cầu treo bị cắt","Chìa khóa đồng sáu phòng khách","Đèn bão dầu hỏa"],"clue_count":24,"bgm_playlist":"vu-nha-quan_bgm","genre":"Bí ẩn","difficulty":"Khó","difficulty_level":4,"subtitle":"BÍ MẬT QUÁN TRỌ TRONG RỪNG THÔNG","tagline":"Màn sương mù giăng kín lối thoát, giam giữ bảy vị khách trong nguy hiểm.","badge":"BÍ ẨN - 7 NGƯỜI","theme_code":"ORIENTAL"}', 'Kịch bản Bí ẩn - Độ khó: Khó', 1, 1),
    (30, 30, 1, '{"props":["Khung ảnh thủy tinh rạn nứt","Bản ghi lời khai cũ","Chiếc nhẫn bạc khắc mật hiệu","Thẻ nhớ máy ảnh kỹ thuật số"],"clue_count":24,"bgm_playlist":"yandere-3_bgm","genre":"Tâm lý giật gân","difficulty":"Khó","difficulty_level":4,"subtitle":"TẤM KÍNH VỠ VỤN VÀ KÝ ỨC","tagline":"Những mảnh thủy tinh phản chiếu góc khuất tâm lý không thể lường trước.","badge":"TÂM LÝ GIẬT GÂN","theme_code":"MODERN"}', 'Kịch bản Tâm lý giật gân - Độ khó: Khó', 1, 1),
    (31, 31, 1, '{"props":["Quạt lụa đề thơ ẩn mật tự","Dây đàn tỳ bà đứt","Thẻ bài thượng khách","Hộp phấn sáp hai đáy"],"clue_count":24,"bgm_playlist":"thanh-lau_bgm","genre":"Cổ trang","difficulty":"Trung bình","difficulty_level":3,"subtitle":"HỒNG TRẦN KỲ ÁN CHỐN LẦU HOA","tagline":"Rèm lụa lay động, khúc tỳ bà vừa dứt đã mở màn kỳ án kinh thành.","badge":"CỔ TRANG - 7 NGƯỜI","theme_code":"ORIENTAL"}', 'Kịch bản Cổ trang - Độ khó: Trung bình', 1, 1),
    (32, 32, 1, '{"props":["Thẻ bài lục cung","Sổ mạch án Thái Y Viện","Hộp trầm hương ngự ban","Trâm phượng khảm ngọc"],"clue_count":24,"bgm_playlist":"hau-cung_bgm","genre":"Cung đấu","difficulty":"Trung bình","difficulty_level":4,"subtitle":"QUYỀN LỰC THÂM CUNG VÀ MƯU LƯỢC","tagline":"Dưới mái ngói lưu ly hoàng thành, mỗi bước đi đều là cuộc đấu trí sinh tồn.","badge":"CUNG ĐẤU - 8 NGƯỜI","theme_code":"ORIENTAL"}', 'Kịch bản Cung đấu - Độ khó: Trung bình', 1, 1),
    (33, 33, 1, '{"props":["Bản cầm phổ Thanh Thanh Man","Đèn hoa đăng giấy dó","Dải lụa xanh thêu tên","Ống tiêu trúc khắc chữ"],"clue_count":24,"bgm_playlist":"thanh-thanh-man_bgm","genre":"Cảm xúc cổ phong","difficulty":"Dễ","difficulty_level":3,"subtitle":"KHÚC CA CỔ PHONG TRẦM MẶC","tagline":"Tiếng đàn tranh giữa làn sương ngọc ngân nga nỗi niềm vương vấn ngàn thu.","badge":"CẢM XÚC CỔ PHONG","theme_code":"ORIENTAL"}', 'Kịch bản Cảm xúc cổ phong - Độ khó: Dễ', 1, 1),
    (34, 34, 1, '{"props":["Mặt nạ hồ ly sứ trắng","Chuông đồng dẫn hồn","Khế ước linh giới niêm ấn","Viên châu phát sáng"],"clue_count":24,"bgm_playlist":"khach-diem-ho-ly_bgm","genre":"Huyền huyễn","difficulty":"Trung bình","difficulty_level":3,"subtitle":"QUÁN TRỌ HỒ LY TRÊN ĐỈNH NÚI","tagline":"Ánh đèn lồng đỏ mờ ảo dẫn lối lữ khách vào kỳ cục huyền huyễn.","badge":"HUYỀN HUYỄN - 6 NGƯỜI","theme_code":"ORIENTAL"}', 'Kịch bản Huyền huyễn - Độ khó: Trung bình', 1, 1),
    (35, 35, 1, '{"props":["Tập bản thảo Lý Xuyên Quái Đàm","Bùa hộ mệnh đền cổ","Vé tàu điện tuyến đêm","Chiếc ô giấy dầu truyền thống"],"clue_count":24,"bgm_playlist":"ly-xuyen-quai-dam-bo_bgm","genre":"Kỳ bí Nhật Bản","difficulty":"Khó","difficulty_level":4,"subtitle":"HẺM ĐÊM MƯA KỲ BÍ NHẬT BẢN","tagline":"Dưới ánh đèn vàng phố cổ Lý Xuyên, cuốn sổ quái đàm mở ra từng vụ án lạ.","badge":"KỲ BÍ NHẬT BẢN","theme_code":"ORIENTAL"}', 'Kịch bản Kỳ bí Nhật Bản - Độ khó: Khó', 1, 1),
    (36, 36, 1, '{"props":["Thẻ gỗ ước nguyện Ema","Sơ đồ bậc đá cổng Torii","Lịch trình gác đêm đền thần","Chuông đồng kagura"],"clue_count":24,"bgm_playlist":"den-than-shimizu-hachiman_bgm","genre":"Kỳ bí Nhật Bản","difficulty":"Khó","difficulty_level":4,"subtitle":"LỜI NGUYỀN CỔNG TORII LINH THIÊNG","tagline":"Giữa làn sương nước đền cổ, nghi lễ mùa thu che giấu chân tướng vụ án.","badge":"KỲ BÍ NHẬT BẢN","theme_code":"ORIENTAL"}', 'Kịch bản Kỳ bí Nhật Bản - Độ khó: Khó', 1, 1),
    (37, 37, 1, '{"props":["Album ảnh gia đình năm 1998","Chiếc radio băng từ cũ","Hộp bánh thiếc đựng thư tay","Chìa khóa căn nhà số 12"],"clue_count":24,"bgm_playlist":"xin-chao_bgm","genre":"Cảm xúc gia đình","difficulty":"Dễ","difficulty_level":3,"subtitle":"LỜI TẠM BIỆT TRÊN CON ĐƯỜNG THU","tagline":"Lá vàng rơi rụng mang theo lời nhắn nhủ muộn màng của những người thương yêu.","badge":"CẢM XÚC GIA ĐÌNH","theme_code":"MODERN"}', 'Kịch bản Cảm xúc gia đình - Độ khó: Dễ', 1, 1),
    (38, 38, 1, '{"props":["Bản nhạc vĩ cầm cháy xém góc","Vĩ kéo đàn khắc tên tắt","Vé buổi hòa nhạc mùa đông","Chiếc hộp đựng nhựa thông"],"clue_count":24,"bgm_playlist":"thi-khuc-li-biet_bgm","genre":"Cảm xúc bi kịch","difficulty":"Trung bình","difficulty_level":3,"subtitle":"KHÚC VĨ CẦM BI KỊCH TÌNH TAN","tagline":"Bản nhạc viết dở trên giấy ố vàng cất giấu câu chuyện bi thương chưa kể.","badge":"CẢM XÚC BI KỊCH","theme_code":"GOTHIC"}', 'Kịch bản Cảm xúc bi kịch - Độ khó: Trung bình', 1, 1),
    (39, 39, 1, '{"props":["Sáu ống thử hương đánh mã số","Sổ công thức điều hương mật","Lọ pha lê tím cổ điển","Thiệp mời triển lãm mùi hương"],"clue_count":24,"bgm_playlist":"du-huong_bgm","genre":"Cảm xúc","difficulty":"Trung bình","difficulty_level":3,"subtitle":"MÙI HƯƠNG CUỐI CỦA KÝ ỨC","tagline":"Lọ pha lê cổ lưu giữ tầng hương duy nhất vạch trần sự thật năm xưa.","badge":"CẢM XÚC - 6 NGƯỜI","theme_code":"MODERN"}', 'Kịch bản Cảm xúc - Độ khó: Trung bình', 1, 1),
    (40, 40, 1, '{"props":["La bàn đồng đoàn khảo sát","Mẫu hổ phách rừng nguyên sinh","Nhật ký nhà thực vật học","Bản dập ký tự trên vỏ cây"],"clue_count":24,"bgm_playlist":"co-thu-ngan-nga_bgm","genre":"Huyền bí","difficulty":"Khó","difficulty_level":4,"subtitle":"TIẾNG THÌ THẦM RỪNG NGUYÊN SINH","tagline":"Cổ thụ ngàn năm chứng kiến bí mật bị chôn vùi dưới tầng rễ cây rêu phong.","badge":"HUYỀN BÍ - 7 NGƯỜI","theme_code":"GOTHIC"}', 'Kịch bản Huyền bí - Độ khó: Khó', 1, 1),
    (41, 41, 1, '{"props":["Sơ đồ kiến trúc Kinh Kỳ Quán","Chìa khóa đồng phòng thượng hạng","Biên bản dấu chân trên tuyết","Bức thư mời đấu giá cổ vật"],"clue_count":24,"bgm_playlist":"martinez-chet-tai-kinh-ky-quan_bgm","genre":"Trinh thám phong cách Nhật","difficulty":"Rất khó","difficulty_level":5,"subtitle":"ÁN MẠNG PHÒng KÍN GIỮA NÚI TUYẾT","tagline":"Quán trọ biệt lập trên đỉnh núi tuyết phong tỏa mọi lối thoát của hung thủ.","badge":"TRINH THÁM NHẬT BẢN","theme_code":"DETECTIVE"}', 'Kịch bản Trinh thám phong cách Nhật - Độ khó: Rất khó', 1, 1),
    (42, 42, 1, '{"props":["Mô hình khối lập phương 3x3","Bảng lịch trình xoay trục phòng","Thẻ đánh số khoang cơ khí","Bản thiết kế kiến trúc sư trưởng"],"clue_count":24,"bgm_playlist":"lap-phuong-quan_bgm","genre":"Mật thất suy luận","difficulty":"Rất khó","difficulty_level":5,"subtitle":"MẬT THẤT KHỐI LẬP PHƯƠNG TỬ ĐỊA","tagline":"Mê cung hình học xoay chuyển theo cơ chế ngầm, đòi hỏi tư duy không gian đỉnh cao.","badge":"MẬT THẤT SUY LUẬN","theme_code":"SCIFI"}', 'Kịch bản Mật thất suy luận - Độ khó: Rất khó', 1, 1)
ON DUPLICATE KEY UPDATE `configuration` = VALUES(`configuration`), `description` = VALUES(`description`), `is_active` = 1;

-- 7. Game Prices (126 khung giá Sáng - Chiều - Tối cho 42 kịch bản)
INSERT INTO `game_prices` (`id`, `game_id`, `price_per_person`, `start_time`, `end_time`, `effective_from`, `effective_until`, `status`)
VALUES
    (1, 1, 180000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (2, 1, 220000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (3, 1, 250000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (4, 2, 200000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (5, 2, 230000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (6, 2, 260000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (7, 3, 200000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (8, 3, 230000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (9, 3, 250000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (10, 4, 210000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (11, 4, 240000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (12, 4, 270000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (13, 5, 200000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (14, 5, 230000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (15, 5, 260000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (16, 6, 180000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (17, 6, 210000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (18, 6, 240000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (19, 7, 180000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (20, 7, 210000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (21, 7, 240000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (22, 8, 190000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (23, 8, 220000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (24, 8, 250000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (25, 9, 190000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (26, 9, 220000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (27, 9, 250000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (28, 10, 180000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (29, 10, 210000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (30, 10, 240000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (31, 11, 190000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (32, 11, 220000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (33, 11, 250000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (34, 12, 190000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (35, 12, 220000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (36, 12, 250000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (37, 13, 210000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (38, 13, 240000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (39, 13, 270000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (40, 14, 170000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (41, 14, 200000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (42, 14, 230000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (43, 15, 200000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (44, 15, 230000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (45, 15, 260000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (46, 16, 210000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (47, 16, 240000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (48, 16, 270000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (49, 17, 200000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (50, 17, 230000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (51, 17, 260000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (52, 18, 200000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (53, 18, 230000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (54, 18, 260000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (55, 19, 170000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (56, 19, 200000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (57, 19, 230000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (58, 20, 190000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (59, 20, 220000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (60, 20, 250000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (61, 21, 180000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (62, 21, 210000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (63, 21, 240000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (64, 22, 180000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (65, 22, 210000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (66, 22, 240000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (67, 23, 190000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (68, 23, 220000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (69, 23, 250000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (70, 24, 180000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (71, 24, 210000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (72, 24, 240000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (73, 25, 180000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (74, 25, 210000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (75, 25, 240000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (76, 26, 190000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (77, 26, 220000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (78, 26, 250000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (79, 27, 190000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (80, 27, 230000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (81, 27, 260000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (82, 28, 200000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (83, 28, 230000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (84, 28, 260000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (85, 29, 190000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (86, 29, 220000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (87, 29, 250000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (88, 30, 200000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (89, 30, 230000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (90, 30, 260000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (91, 31, 190000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (92, 31, 220000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (93, 31, 250000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (94, 32, 200000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (95, 32, 230000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (96, 32, 260000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (97, 33, 180000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (98, 33, 210000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (99, 33, 240000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (100, 34, 190000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (101, 34, 220000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (102, 34, 250000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (103, 35, 190000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (104, 35, 220000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (105, 35, 250000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (106, 36, 200000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (107, 36, 230000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (108, 36, 260000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (109, 37, 180000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (110, 37, 210000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (111, 37, 240000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (112, 38, 190000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (113, 38, 220000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (114, 38, 250000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (115, 39, 180000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (116, 39, 210000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (117, 39, 240000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (118, 40, 190000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (119, 40, 220000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (120, 40, 250000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (121, 41, 210000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (122, 41, 240000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (123, 41, 270000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (124, 42, 210000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (125, 42, 240000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (126, 42, 270000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE')
ON DUPLICATE KEY UPDATE `price_per_person` = VALUES(`price_per_person`), `start_time` = VALUES(`start_time`), `end_time` = VALUES(`end_time`), `status` = VALUES(`status`);

-- 8. Promotions (Khuyến mãi chuẩn V16)
INSERT INTO `promotions` (`id`, `code`, `name`, `promotion_type`, `discount_type`, `discount_value`, `start_at`, `end_at`, `rules`, `is_active`)
VALUES
    (1, 'TUESDAY_30', 'Ưu đãi vui vẻ - Thứ Ba Giảm 30%', 'WEEKDAY', 'PERCENT', 30, '2026-01-01 00:00:00.000', '2035-12-31 23:59:59.999', JSON_OBJECT('weekdays', JSON_ARRAY(2), 'valid_from', '09:00:00', 'valid_until', '19:00:00', 'minimum_booking_hours', 24, 'applies_to', 'ANY_GAME', 'stackable', FALSE), 1),
    (2, 'HSSV_20000', 'Ưu đãi học sinh sinh viên', 'STUDENT', 'FIXED', 20000, '2026-01-01 00:00:00.000', '2035-12-31 23:59:59.999', JSON_OBJECT('weekdays', JSON_ARRAY(1, 2, 3, 4), 'valid_from', '09:00:00', 'valid_until', '19:00:00', 'discount_per_eligible_person', 20000, 'requires_student_verification', TRUE, 'stackable', FALSE), 1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `rules` = VALUES(`rules`);

-- 9. Sample Game Room
INSERT INTO `game_rooms` (`id`, `game_id`, `game_configuration_id`, `creator_id`, `title`, `description`, `min_players`, `max_players`, `status`, `booking_type`, `target_venue_id`, `total_players`, `confirmed_players`, `required_players`, `guest_slots`, `formation_deadline_at`)
VALUES
    (1, 1, 1, 3, 'Hội Thám Tử Cuối Tuần - Cô Gái Bên Cửa Sổ', 'Tìm thêm đồng đội suy luận logic, không khí thân thiện.', 6, 8, 'FORMING', 'PUBLIC', 1, 6, 1, 6, 0, DATE_ADD(NOW(), INTERVAL 3 DAY))
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);

INSERT INTO `room_participants` (`id`, `game_room_id`, `user_id`, `status`, `joined_at`)
VALUES
    (1, 1, 3, 'PENDING_DEPOSIT', NOW())
ON DUPLICATE KEY UPDATE `status` = VALUES(`status`);
