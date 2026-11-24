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
    (1, 3, 'Quản Trị Viên', 'admin@kichbansat.vn', '$2y$10$r4QuAePIauap1X43qe70L.g87g4PGWb4l/xh346lrZbNFcbNTTbSO', '0901000001', NULL, 'ACTIVE', 0, NOW()),
    (2, 2, 'Host Hoàng Nam (DM)', 'dm.nam@kichbansat.vn', '$2y$10$r4QuAePIauap1X43qe70L.g87g4PGWb4l/xh346lrZbNFcbNTTbSO', '0902000002', NULL, 'ACTIVE', 0, NOW()),
    (3, 1, 'Nguyễn Xuân Hiếu (Player)', 'player.hieu@kichbansat.vn', '$2y$10$r4QuAePIauap1X43qe70L.g87g4PGWb4l/xh346lrZbNFcbNTTbSO', '0903000003', NULL, 'ACTIVE', 1, NOW()),
    (4, 1, 'Trần Minh Anh (Player)', 'minhanh@gmail.com', '$2y$10$r4QuAePIauap1X43qe70L.g87g4PGWb4l/xh346lrZbNFcbNTTbSO', '0904000004', NULL, 'ACTIVE', 0, NOW())
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
    (1, 'Đêm Đoạt Mệnh Tại Trang Viên', 'dem-doat-menh-tai-trang-vien', 'Một bữa tiệc gia tộc bí ẩn nơi người đứng đầu bị sát hại trong đêm giông bão. Tám vị khách bị cô lập giữa dinh thự cổ với những bí mật huyết thống không thể tiết lộ.', NULL, 6, 8, 240, 'ACTIVE', 0, 'DETECTIVE', 1),
    (2, 'Biệt Thự Huyết Tộc', 'biet-thu-huyet-toc', 'Cuộc đối đầu tâm lý nghẹt thở giữa các nhánh ma cà rồng cổ đại khi chén thánh ngàn năm biến mất ngay trước nghi lễ thức tỉnh.', NULL, 5, 7, 210, 'ACTIVE', 1, 'VAMPIRE', 2),
    (3, 'Án Mạng Trên Chuyến Tàu Tuyết', 'an-mang-tren-chuyen-tau-tuyet', 'Chuyến tàu xuyên Bắc Cực mắc kẹt giữa bão tuyết trắng xóa. Một thi thể được phát hiện trong toa hạng nhất khóa kín từ bên trong.', NULL, 6, 8, 240, 'ACTIVE', 0, 'TRAIN', 3),
    (4, 'Kỳ Án Trường An', 'ky-an-truong-an', 'Đêm hội hoa đăng kinh thành, vị Thượng thư bộ Hình trúng độc gục chết giữa yến tiệc. Ân oán triều đình buộc các nhân vật phải giải án trước canh năm.', NULL, 6, 8, 240, 'ACTIVE', 0, 'ORIENTAL', 4),
    (5, 'Hồi Ức Đẫm Máu 18+', 'hoi-uc-dam-mau', 'Nhóm bạn đại học hội ngộ tại căn nhà gỗ ven rừng sau 10 năm. Khi màn đêm buông xuống, chân tướng vụ mất tích năm xưa trỗi dậy qua từng bức thư nặc danh.', NULL, 5, 7, 210, 'ACTIVE', 1, 'GOTHIC', 5),
    (6, 'Án Mạng Trong Thư Viện Cổ', 'an-mang-trong-thu-vien-co', 'Nhà sử học danh tiếng gục chết trong thư viện khóa trái hai lớp cửa gỗ sồi. Không dấu chân lạ, không hung khí hiện trường, chỉ có 24 mảnh văn thư cổ.', NULL, 6, 8, 240, 'ACTIVE', 0, 'DETECTIVE', 6),
    (7, 'Trạm Không Gian Vô Định', 'tram-khong-gian-vo-dinh', 'Năm 2140, trạm nghiên cứu quỹ đạo mất liên lạc với mặt đất. Chỉ huy trưởng tử vong trong khoang sinh học, hệ thống oxy chỉ còn đủ cho 4 giờ điều tra.', NULL, 6, 8, 240, 'ACTIVE', 0, 'SCIFI', 7),
    (8, 'Bữa Tiệc Tử Thần Gatsby', 'bua-tiec-tu-than-gatsby', 'Thập niên 1920 hoa lệ, bữa tiệc xa hoa tại dinh thự Long Island hóa thành hiện trường án mạng ngay thời khắc pháo hoa giao thừa bùng nổ.', NULL, 7, 9, 240, 'ACTIVE', 0, 'MODERN', 8),
    (9, 'Căn Phòng Số 13', 'can-phong-so-13', 'Khách sạn ven đồi đóng cửa mùa mưa bão chỉ còn sáu vị khách trọ. Cánh cửa phòng số 13 bị niêm phong suốt bảy năm bất ngờ bật mở lúc nửa đêm.', NULL, 5, 6, 180, 'ACTIVE', 1, 'GOTHIC', 9),
    (10, 'Bí Mật Làng Cổ Hắc Miêu', 'bi-mat-lang-co-hac-mieu', 'Ngôi làng biệt lập giữa thung lũng sương mù cử hành lễ tế hằng năm. Đúng khắc giao thời, vị tộc trưởng bị sát hại ngay trước từ đường.', NULL, 6, 8, 240, 'ACTIVE', 1, 'ORIENTAL', 10),
    (11, 'Vụ Trộm Đề Thi Học Đường', 'vu-trom-de-thi-hoc-duong', 'Bộ đề thi học kỳ đặc biệt biến mất khỏi két sắt phòng giáo vụ trước giờ thi một đêm. Sáu thành viên hội học sinh vừa đấu trí vừa che giấu những trò nhất quỷ nhì ma.', NULL, 5, 7, 180, 'ACTIVE', 0, 'SCHOOL', 11),
    (12, 'Bản Giao Hưởng Lúc Nửa Đêm', 'ban-giao-huong-luc-nua-dem', 'Nhạc trưởng tài hoa của nhà hát giao hưởng gục ngã ngay trên bục chỉ huy trong buổi tổng duyệt kín. Mỗi khuông nhạc để lại là một mật mã chỉ tên hung thủ.', NULL, 6, 8, 210, 'ACTIVE', 0, 'DETECTIVE', 12),
    (13, 'Quỷ Ám Nhà Họ Trần', 'quy-am-nha-ho-tran', 'Đêm cúng thất tuần tại phủ cổ đất thần kinh, bức bình phong gỗ lim đổ sập làm lộ ra căn mật thất bị bít kín từ hai mươi năm trước.', NULL, 6, 8, 240, 'ACTIVE', 1, 'ORIENTAL', 13),
    (14, 'Đại Hội Võ Lâm Lầy Lội', 'dai-hoi-vo-lam-lay-loi', 'Minh chủ võ lâm đột ngột bặt vô âm tín ngay trước giờ trao thưởng ngân phiếu vạn lượng. Quần hùng vừa truy tìm chân tướng vừa đấu giá bí kíp giả thật lẫn lộn.', NULL, 6, 9, 210, 'ACTIVE', 0, 'ORIENTAL', 14),
    (15, 'Thư Gửi Người Ở Lại', 'thu-gui-nguoi-o-lai', 'Bảy người bạn cũ hội ngộ tại tiệm cà phê sương mù Đà Lạt theo di nguyện của người bạn đã khuất, cùng mở chiếc hộp thiếc chôn dưới gốc thông già.', NULL, 6, 7, 240, 'ACTIVE', 0, 'MODERN', 15),
    (16, 'Học Viện Pháp Thuật Nocturne', 'hoc-vien-phap-thuat-nocturne', 'Viên đá phong ấn tại tháp chuông học viện pháp thuật bị đánh tráo trong đêm nguyệt thực. Sáu học viên ưu tú phải giải mã ma trận cổ ngữ trước khi cổng hư không mở.', NULL, 6, 8, 240, 'ACTIVE', 0, 'GOTHIC', 16),
    (17, 'Biệt Thự Hoa Hồng Đen', 'biet-thu-hoa-hong-den', 'Bữa tiệc kỷ niệm mười năm ngày cưới biến thành phiên chất vấn sinh tử khi mỗi vị khách mở phong bì dưới đĩa ăn và thấy bằng chứng tội lỗi của chính mình.', NULL, 6, 8, 240, 'ACTIVE', 1, 'GOTHIC', 17),
    (18, 'Phiên Tòa Bóng Đêm', 'phien-toa-bong-dem', 'Mười năm sau một vụ án oan chấn động thành phố, thẩm phán, công tố viên, luật sư và các nhân chứng năm xưa bị triệu tập vào một phòng xử án đóng kín.', NULL, 7, 10, 240, 'ACTIVE', 0, 'COURT', 18),
    (19, 'Hồ Sơ Bệnh Viện Saint Jude', 'ho-so-benh-vien-saint-jude', 'Khu điều trị nội trú bỏ hoang từ trận dịch mười năm trước bất ngờ sáng đèn. Bảy bác sĩ và điều dưỡng cũ phải đối mặt với bệnh án số không chưa từng công bố.', NULL, 6, 8, 240, 'ACTIVE', 1, 'HOSPITAL', 19),
    (20, 'Đêm Nhạc Jazz Phố Cảng', 'dem-nhac-jazz-pho-cang', 'Tại quán rượu ngầm thập niên 1980 nơi bến cảng sương mù, ông trùm buôn cổ vật ngã gục ngay khi bản kèn saxophone vừa dứt tiếng.', NULL, 6, 9, 210, 'ACTIVE', 0, 'MODERN', 20),
    (21, 'Mật Mệnh Hoàng Cung', 'mat-menh-hoang-cung', 'Đêm trước lễ đăng cơ thái tử, ngọc tỷ truyền quốc biến mất khỏi điện Càn Thanh. Các hoàng thân và đại thần vừa tra xét manh mối vừa tranh đoạt quyền nhiếp chính.', NULL, 7, 10, 240, 'ACTIVE', 0, 'ORIENTAL', 21),
    (22, 'Người Khách Cuối Cùng Lúc Mưa Rơi', 'nguoi-khach-cuoi-cung-luc-mua-roi', 'Tại nhà trọ ven đèo bị sạt lở chia cắt đường xuống núi, một thám tử tư được phát hiện tử vong cùng cuốn sổ ghi chép ba vụ án chưa khép lại.', NULL, 5, 6, 210, 'ACTIVE', 0, 'DETECTIVE', 22),
    (23, 'Di Chúc Nhà Họ Hứa', 'di-chuc-nha-ho-hua', 'Thương gia giàu nhất thương cảng qua đời để lại khối tài sản khổng lồ với điều kiện người thừa kế phải vạch trần kẻ đã đầu độc ông ngay trong buổi công bố di chúc.', NULL, 6, 8, 240, 'ACTIVE', 0, 'DETECTIVE', 23),
    (24, 'Lời Nguyền Cổ Mộ Lâu Lan', 'loi-nguyen-co-mo-lau-lan', 'Đoàn khảo cổ tiến sâu vào lăng mộ sa mạc bị cát vùi ngàn năm. Khi cánh cửa đá sập xuống, từng thành viên phát hiện trong đoàn có kẻ mang thân phận giả mạo.', NULL, 6, 8, 240, 'ACTIVE', 0, 'ORIENTAL', 24),
    (25, 'Tín Hiệu Từ Vực Thẳm Mariana', 'tin-hieu-tu-vuc-tham-mariana', 'Trạm nghiên cứu dưới đáy đại dương ở độ sâu 10.000 mét nhận được tín hiệu cầu cứu phát ra từ chính tần số nội bộ đã ngừng hoạt động ba năm trước.', NULL, 6, 7, 240, 'ACTIVE', 0, 'SCIFI', 25),
    (26, 'Tiệm Cầm Đồ Thời Gian', 'tiem-cam-do-thoi-gian', 'Một tiệm cầm đồ kỳ lạ chỉ mở cửa lúc không giờ, nơi khách hàng đem cầm cố ký ức để đổi lấy điều ước. Đêm nay, chủ tiệm biến mất để lại sáu món đồ vô chủ.', NULL, 5, 6, 210, 'ACTIVE', 0, 'GOTHIC', 26),
    (27, 'Sát Nhân Màn Sương Luân Đôn', 'sat-nhan-man-suong-luan-don', 'Luân Đôn cuối thế kỷ XIX chìm trong sương mù dày đặc. Một vụ án mạng xảy ra ngay bên ngoài trụ sở cảnh sát khiến giới thám tử tư buộc phải vào cuộc.', NULL, 6, 8, 240, 'ACTIVE', 1, 'DETECTIVE', 27),
    (28, 'Cuộc Chiến Thừa Kế Tập Đoàn Tinh Vân', 'cuoc-chien-thua-ke-tap-doan-tinh-van', 'Cuộc họp hội đồng quản trị khẩn cấp trên tầng cao nhất tòa tháp tài chính. Ai gom đủ cổ phần và lật tẩy được kẻ biển thủ quỹ đầu tư sẽ nắm quyền chủ tịch.', NULL, 6, 9, 210, 'ACTIVE', 0, 'MODERN', 28),
    (29, 'Hẹn Ước Mùa Hoa Anh Đào', 'hen-uoc-mua-hoa-anh-dao', 'Mười năm sau lễ tốt nghiệp trung học, sáu người bạn trở về sân trường cũ chuẩn bị giải tỏa để tìm lại viên nang thời gian và lời hứa chưa kịp nói.', NULL, 5, 6, 210, 'ACTIVE', 0, 'SCHOOL', 29),
    (30, 'Đêm Trực Xác Số 7', 'dem-truc-xac-so-7', 'Ca trực đêm mưa bão tại viện pháp y ngoại ô: ngăn tủ lạnh số 7 trống rỗng và camera hành lang bị xóa sạch đúng mười lăm phút định mệnh.', NULL, 5, 7, 210, 'ACTIVE', 1, 'HOSPITAL', 30),
    (31, 'Bí Ẩn Đoàn Kịch Mặt Nạ Trắng', 'bi-an-doan-kich-mat-na-trang', 'Đêm công diễn vở bi kịch cổ điển, diễn viên chính ngã gục ngay trên sân khấu trước hàng trăm khán giả khi tưởng rằng đó chỉ là màn diễn xuất nhập tâm.', NULL, 6, 8, 240, 'ACTIVE', 0, 'GOTHIC', 31),
    (32, 'Khách Sạn Hoàng Hôn Đỏ', 'khach-san-hoang-hon-do', 'Giữa hoang mạc miền Tây rực nắng, bảy kẻ săn tiền thưởng và lữ khách bị bão cát giam chân trong quán trọ gỗ cùng một rương vàng bị đánh cắp từ đoàn tàu.', NULL, 6, 8, 210, 'ACTIVE', 0, 'MODERN', 32),
    (33, 'Thần Thoại Đảo Sương Mù', 'than-thoai-dao-suong-mu', 'Con thuyền phà duy nhất rời đảo bị đánh đắm trong đêm sương dày. Tám du khách nhận ra câu đồng dao cổ trên bia đá hải đăng đang ứng nghiệm từng câu một.', NULL, 6, 8, 240, 'ACTIVE', 0, 'DETECTIVE', 33),
    (34, 'Vòng Lặp Không Giờ', 'vong-lap-khong-gio', 'Mỗi khi kim đồng hồ điểm đúng 00:00, tiếng chuông vang lên và cả sáu người lại tỉnh dậy lúc 22:00 trước khi vụ nổ xảy ra. Chỉ kẻ tìm ra nguyên mẫu thời gian mới thoát khỏi vòng lặp.', NULL, 5, 6, 240, 'ACTIVE', 0, 'SCIFI', 34),
    (35, 'Phong Hoa Tuyết Nguyệt Lâu', 'phong-hoa-tuyet-nguyet-lau', 'Giữa buổi đấu thơ nhạc tại trà lâu bậc nhất Giang Nam, bức họa tuyệt mật chứa danh sách mật thám biên ải không cánh mà bay ngay sau tiếng đàn tỳ bà đứt dây.', NULL, 6, 8, 240, 'ACTIVE', 0, 'ORIENTAL', 35),
    (36, 'Bản Án Mười Hai Bồi Thẩm', 'ban-an-muoi-hai-boi-tham', 'Trong phòng nghị án khóa kín không điều hòa giữa trưa hè oi bức, các bồi thẩm viên tranh biện nảy lửa về một vụ án tưởng chừng đã rõ mười mươi.', NULL, 7, 10, 210, 'ACTIVE', 0, 'COURT', 36),
    (37, 'Hôn Lễ Áo Đỏ', 'hon-le-ao-do', 'Đêm rước dâu vào dinh thự cổ miền sơn cước, kiệu hoa dừng lại trước cổng đá nhưng bên trong không phải tân nương mà là một bức huyết thư buộc tội.', NULL, 6, 7, 240, 'ACTIVE', 1, 'ORIENTAL', 37),
    (38, 'Ký Túc Xá Tầng Tư', 'ky-tuc-xa-tang-tu', 'Kỳ nghỉ lễ vắng người, sáu sinh viên ở lại dãy ký túc xá cũ tình cờ tìm thấy cuốn sổ trực ban năm 1998 giấu dưới tấm ván sàn phòng sinh hoạt chung.', NULL, 5, 6, 180, 'ACTIVE', 1, 'SCHOOL', 38),
    (39, 'Phi Vụ Đấu Giá Kim Cương Xanh', 'phi-vu-dau-gia-kim-cuong-xanh', 'Đúng ba mươi giây mất điện tại buổi đấu giá từ thiện thượng lưu, viên kim cương xanh trị giá triệu đô bị đánh tráo bằng một viên đá thủy tinh hoàn hảo.', NULL, 6, 9, 210, 'ACTIVE', 0, 'MODERN', 39),
    (40, 'Chuyến Tàu Đêm Đi Về Phía Nam', 'chuyen-tau-dem-di-ve-phia-nam', 'Trên chuyến tàu thống nhất xuyên đêm cuối năm, sáu hành khách xa lạ cùng chung một toa giường nằm dần nhận ra cuộc đời họ được nối với nhau bởi một vụ hỏa hoạn cũ.', NULL, 6, 6, 240, 'ACTIVE', 0, 'TRAIN', 40),
    (41, 'Thành Phố Ngầm Cyber 2099', 'thanh-pho-ngam-cyber-2099', 'Tại khu đô thị ngầm điều hành bởi trí tuệ nhân tạo trung tâm, một kỹ sư giải mã ký ức bị xóa sạch dữ liệu não bộ ngay trước phiên điều trần công nghệ.', NULL, 6, 8, 240, 'ACTIVE', 0, 'SCIFI', 41),
    (42, 'Mật Thất Tháp Đồng Hồ Cổ', 'mat-that-thap-dong-ho-co', 'Bậc thầy chế tác đồng hồ cơ khí để lại lời thách đố cuối cùng bên trong tháp chuông cổ: chỉ khi sáu bánh răng manh mối khớp nhau, chân tướng ba mươi năm trước mới hé mở.', NULL, 6, 8, 240, 'ACTIVE', 0, 'DETECTIVE', 42)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`), `image_url` = NULL, `is_18_plus` = VALUES(`is_18_plus`), `theme_code` = VALUES(`theme_code`), `display_order` = VALUES(`display_order`);

-- 6. Game Configurations (42 cấu hình đạo cụ & thể loại)
INSERT INTO `game_configurations` (`id`, `game_id`, `version`, `configuration`, `description`, `is_active`, `created_by`)
VALUES
    (1, 1, 1, '{"props":["Thư tống tiền niêm sáp đỏ","Dao găm bạc khắc gia huy","Chiếc nhẫn ngọc bích","Sơ đồ hầm rượu mật"],"clue_count":24,"bgm_playlist":"dem-doat-menh-tai-trang-vien_bgm","genre":"Suy luận phá án","difficulty":"Trung bình","theme_code":"DETECTIVE"}', 'Kịch bản Suy luận phá án - Độ khó: Trung bình', 1, 1),
    (2, 2, 1, '{"props":["Ly rượu huyết phách","Gia huy bạc cổ","Cuốn nhật ký bìa da đen","Đồng hồ cát đỏ"],"clue_count":24,"bgm_playlist":"biet-thu-huyet-toc_bgm","genre":"Kinh dị tâm linh","difficulty":"Khó","theme_code":"VAMPIRE"}', 'Kịch bản Kinh dị tâm linh - Độ khó: Khó', 1, 1),
    (3, 3, 1, '{"props":["Vé tàu khoang hạng nhất","Đồng hồ quả quýt dừng ở 11:15","Lọ thuốc an thần","Sổ trực ban trưởng tàu"],"clue_count":24,"bgm_playlist":"an-mang-tren-chuyen-tau-tuyet_bgm","genre":"Suy luận phá án","difficulty":"Khó","theme_code":"TRAIN"}', 'Kịch bản Suy luận phá án - Độ khó: Khó', 1, 1),
    (4, 4, 1, '{"props":["Lệnh bài Cẩm Y Vệ","Mật chỉ hoàng cung","Bình rượu hoa quỳnh","Ngọc bội khắc chữ Minh"],"clue_count":24,"bgm_playlist":"ky-an-truong-an_bgm","genre":"Suy luận phá án","difficulty":"Khó","theme_code":"ORIENTAL"}', 'Kịch bản Suy luận phá án - Độ khó: Khó', 1, 1),
    (5, 5, 1, '{"props":["Cuốn nhật ký ố vàng","Băng ghi âm cũ","Chiếc vòng tay đứt đoạn","Chìa khóa tầng hầm rỉ sét"],"clue_count":24,"bgm_playlist":"hoi-uc-dam-mau_bgm","genre":"Kinh dị tâm linh","difficulty":"Khó","theme_code":"GOTHIC"}', 'Kịch bản Kinh dị tâm linh - Độ khó: Khó', 1, 1),
    (6, 6, 1, '{"props":["Kính lúp đồng cổ","Bản thảo da cừu","Lọ mực tím nghi độc","Sổ mượn sách mật"],"clue_count":24,"bgm_playlist":"an-mang-trong-thu-vien-co_bgm","genre":"Suy luận phá án","difficulty":"Khó","theme_code":"DETECTIVE"}', 'Kịch bản Suy luận phá án - Độ khó: Khó', 1, 1),
    (7, 7, 1, '{"props":["Thẻ từ kỹ thuật viên","Ống mẫu sinh học niêm phong","Nhật ký hộp đen buồng lái","Mã khóa khoang thoát hiểm"],"clue_count":24,"bgm_playlist":"tram-khong-gian-vo-dinh_bgm","genre":"Giả tưởng, siêu nhiên","difficulty":"Khó","theme_code":"SCIFI"}', 'Kịch bản Giả tưởng, siêu nhiên - Độ khó: Khó', 1, 1),
    (8, 8, 1, '{"props":["Ly pha lê vương vết son","Chuỗi ngọc trai đứt","Bật lửa mạ vàng khắc chữ","Thiệp mời dạ hội ẩn mật mã"],"clue_count":24,"bgm_playlist":"bua-tiec-tu-than-gatsby_bgm","genre":"Tình cảm, tâm lý xã hội","difficulty":"Trung bình","theme_code":"MODERN"}', 'Kịch bản Tình cảm, tâm lý xã hội - Độ khó: Trung bình', 1, 1),
    (9, 9, 1, '{"props":["Chìa khóa đồng số 13","Sổ lưu trú khách sạn","Biên lai thuốc ngủ","Sơ đồ hành lang thoát hiểm"],"clue_count":24,"bgm_playlist":"can-phong-so-13_bgm","genre":"Kinh dị tâm linh","difficulty":"Trung bình","theme_code":"GOTHIC"}', 'Kịch bản Kinh dị tâm linh - Độ khó: Trung bình', 1, 1),
    (10, 10, 1, '{"props":["Chuông đồng cổ","Tượng mèo gỗ mun","Mảnh giấy khấn cổ","Gia phả dòng họ khuyết trang"],"clue_count":24,"bgm_playlist":"bi-mat-lang-co-hac-mieu_bgm","genre":"Kinh dị tâm linh","difficulty":"Khó","theme_code":"ORIENTAL"}', 'Kịch bản Kinh dị tâm linh - Độ khó: Khó', 1, 1),
    (11, 11, 1, '{"props":["Chìa khóa phòng giáo vụ","Thẻ hội trưởng học sinh","Biên bản trực tuần","Mảnh phong bì niêm phong"],"clue_count":24,"bgm_playlist":"vu-trom-de-thi-hoc-duong_bgm","genre":"Cơ chế, hài hước","difficulty":"Dễ","theme_code":"SCHOOL"}', 'Kịch bản Cơ chế, hài hước - Độ khó: Dễ', 1, 1),
    (12, 12, 1, '{"props":["Bản tổng phổ chương cuối","Đũa chỉ huy gãy đôi","Vé lô thượng hạng nhà hát","Thư nặc danh hậu trường"],"clue_count":24,"bgm_playlist":"ban-giao-huong-luc-nua-dem_bgm","genre":"Tình cảm, tâm lý xã hội","difficulty":"Trung bình","theme_code":"DETECTIVE"}', 'Kịch bản Tình cảm, tâm lý xã hội - Độ khó: Trung bình', 1, 1),
    (13, 13, 1, '{"props":["Khế ước cổ chữ Hán Nôm","Đèn dầu đồng cổ","Tràng hạt gỗ trầm","Chìa khóa rương gia bảo"],"clue_count":24,"bgm_playlist":"quy-am-nha-ho-tran_bgm","genre":"Kinh dị tâm linh","difficulty":"Trung bình","theme_code":"ORIENTAL"}', 'Kịch bản Kinh dị tâm linh - Độ khó: Trung bình', 1, 1),
    (14, 14, 1, '{"props":["Ngân phiếu thông hành trang","Bí kíp võ công khuyết trang","Lệnh bài minh chủ","Xúc xắc điểm huyệt"],"clue_count":24,"bgm_playlist":"dai-hoi-vo-lam-lay-loi_bgm","genre":"Cơ chế, hài hước","difficulty":"Dễ","theme_code":"ORIENTAL"}', 'Kịch bản Cơ chế, hài hước - Độ khó: Dễ', 1, 1),
    (15, 15, 1, '{"props":["Hộp thiếc kỷ niệm","Bảy phong thư viết tay","Cuộn phim máy ảnh cơ","Vé tàu hỏa Đà Lạt cũ"],"clue_count":24,"bgm_playlist":"thu-gui-nguoi-o-lai_bgm","genre":"Tình cảm, tâm lý xã hội","difficulty":"Dễ","theme_code":"MODERN"}', 'Kịch bản Tình cảm, tâm lý xã hội - Độ khó: Dễ', 1, 1),
    (16, 16, 1, '{"props":["Huy hiệu bốn học viện","Sách cổ ngữ nguyên tố","Bình pha chế giả kim","Bản đồ hành lang biến đổi"],"clue_count":24,"bgm_playlist":"hoc-vien-phap-thuat-nocturne_bgm","genre":"Giả tưởng, siêu nhiên","difficulty":"Trung bình","theme_code":"GOTHIC"}', 'Kịch bản Giả tưởng, siêu nhiên - Độ khó: Trung bình', 1, 1),
    (17, 17, 1, '{"props":["Bông hồng nhung đen","Phong bì sáp đen ẩn danh","Nhẫn cưới khắc ngày tháng","Bản di chúc sửa đổi"],"clue_count":24,"bgm_playlist":"biet-thu-hoa-hong-den_bgm","genre":"Kinh dị tâm linh","difficulty":"Khó","theme_code":"GOTHIC"}', 'Kịch bản Kinh dị tâm linh - Độ khó: Khó', 1, 1),
    (18, 18, 1, '{"props":["Búa gỗ chủ tọa","Hồ sơ giám định pháp y cũ","Băng ghi âm lời khai","Biên bản nghị án niêm phong"],"clue_count":24,"bgm_playlist":"phien-toa-bong-dem_bgm","genre":"Suy luận phá án","difficulty":"Khó","theme_code":"COURT"}', 'Kịch bản Suy luận phá án - Độ khó: Khó', 1, 1),
    (19, 19, 1, '{"props":["Bệnh án mã số 00","Thẻ trực ca đêm bác sĩ","Chìa khóa tủ dược phẩm","Phim chụp X-quang đánh dấu"],"clue_count":24,"bgm_playlist":"ho-so-benh-vien-saint-jude_bgm","genre":"Kinh dị tâm linh","difficulty":"Khó","theme_code":"HOSPITAL"}', 'Kịch bản Kinh dị tâm linh - Độ khó: Khó', 1, 1),
    (20, 20, 1, '{"props":["Đĩa than bản giới hạn","Phỉnh sòng bài khắc ký hiệu","Hóa đơn kho hàng bến cảng","Vé tàu viễn dương"],"clue_count":24,"bgm_playlist":"dem-nhac-jazz-pho-cang_bgm","genre":"Cơ chế, hài hước","difficulty":"Trung bình","theme_code":"MODERN"}', 'Kịch bản Cơ chế, hài hước - Độ khó: Trung bình', 1, 1),
    (21, 21, 1, '{"props":["Hộp đựng ngọc tỷ trống","Thẻ bài nội đình","Bản tấu chương mật","Túi hương cung đình"],"clue_count":24,"bgm_playlist":"mat-menh-hoang-cung_bgm","genre":"Cơ chế, hài hước","difficulty":"Trung bình","theme_code":"ORIENTAL"}', 'Kịch bản Cơ chế, hài hước - Độ khó: Trung bình', 1, 1),
    (22, 22, 1, '{"props":["Sổ tay thám tử bìa da","Áo mưa dính bùn đất đỏ","Chìa khóa cốp xe","Tờ báo cắt rời mười năm trước"],"clue_count":24,"bgm_playlist":"nguoi-khach-cuoi-cung-luc-mua-roi_bgm","genre":"Suy luận phá án","difficulty":"Trung bình","theme_code":"DETECTIVE"}', 'Kịch bản Suy luận phá án - Độ khó: Trung bình', 1, 1),
    (23, 23, 1, '{"props":["Bản di chúc niêm phong","Tách trà sứ Thanh Hoa","Con dấu thương hội","Sổ nợ mật phòng thu ngân"],"clue_count":24,"bgm_playlist":"di-chuc-nha-ho-hua_bgm","genre":"Suy luận phá án","difficulty":"Trung bình","theme_code":"DETECTIVE"}', 'Kịch bản Suy luận phá án - Độ khó: Trung bình', 1, 1),
    (24, 24, 1, '{"props":["La bàn định vị cổ mộ","Bản dập bia đá Lâu Lan","Mặt nạ đồng xanh","Nhật ký đoàn thám hiểm"],"clue_count":24,"bgm_playlist":"loi-nguyen-co-mo-lau-lan_bgm","genre":"Giả tưởng, siêu nhiên","difficulty":"Trung bình","theme_code":"ORIENTAL"}', 'Kịch bản Giả tưởng, siêu nhiên - Độ khó: Trung bình', 1, 1),
    (25, 25, 1, '{"props":["Băng sóng âm sonar","Nhật ký áp suất khoang ngầm","Thẻ phân quyền kỹ sư trưởng","Mẫu khoáng vật phát quang"],"clue_count":24,"bgm_playlist":"tin-hieu-tu-vuc-tham-mariana_bgm","genre":"Giả tưởng, siêu nhiên","difficulty":"Khó","theme_code":"SCIFI"}', 'Kịch bản Giả tưởng, siêu nhiên - Độ khó: Khó', 1, 1),
    (26, 26, 1, '{"props":["Sáu biên lai cầm cố ký ức","Đồng hồ quả lắc chạy ngược","Cân tiểu ly đồng","Chiếc hộp nhạc gỗ sồi"],"clue_count":24,"bgm_playlist":"tiem-cam-do-thoi-gian_bgm","genre":"Tình cảm, tâm lý xã hội","difficulty":"Dễ","theme_code":"GOTHIC"}', 'Kịch bản Tình cảm, tâm lý xã hội - Độ khó: Dễ', 1, 1),
    (27, 27, 1, '{"props":["Tờ nhật báo buổi sáng","Ống tẩu thuốc gỗ thạch nam","Bản đồ các con hẻm Đông Luân Đôn","Găng tay da dính vết than"],"clue_count":24,"bgm_playlist":"sat-nhan-man-suong-luan-don_bgm","genre":"Suy luận phá án","difficulty":"Khó","theme_code":"DETECTIVE"}', 'Kịch bản Suy luận phá án - Độ khó: Khó', 1, 1),
    (28, 28, 1, '{"props":["Chứng nhận cổ phần ủy thác","Báo cáo kiểm toán mật","USB mã hóa hai lớp","Biên bản họp hội đồng quản trị"],"clue_count":24,"bgm_playlist":"cuoc-chien-thua-ke-tap-doan-tinh-van_bgm","genre":"Cơ chế, hài hước","difficulty":"Trung bình","theme_code":"MODERN"}', 'Kịch bản Cơ chế, hài hước - Độ khó: Trung bình', 1, 1),
    (29, 29, 1, '{"props":["Lưu bút lớp 12A3","Huy hiệu áo đồng phục","Tấm vé tàu điện mùa xuân","Bản nhạc viết dở trên giấy tập"],"clue_count":24,"bgm_playlist":"hen-uoc-mua-hoa-anh-dao_bgm","genre":"Tình cảm, tâm lý xã hội","difficulty":"Dễ","theme_code":"SCHOOL"}', 'Kịch bản Tình cảm, tâm lý xã hội - Độ khó: Dễ', 1, 1),
    (30, 30, 1, '{"props":["Sổ bàn giao ca trực đêm","Thẻ gắn hồ sơ số 7","Nhật ký ra vào cửa từ","Biên bản khám nghiệm ban đầu"],"clue_count":24,"bgm_playlist":"dem-truc-xac-so-7_bgm","genre":"Kinh dị tâm linh","difficulty":"Khó","theme_code":"HOSPITAL"}', 'Kịch bản Kinh dị tâm linh - Độ khó: Khó', 1, 1),
    (31, 31, 1, '{"props":["Chiếc mặt nạ sứ trắng","Kịch bản sân khấu ghi chú đỏ","Chai rượu đạo cụ bị tráo","Chìa khóa phòng thay phục trang"],"clue_count":24,"bgm_playlist":"bi-an-doan-kich-mat-na-trang_bgm","genre":"Suy luận phá án","difficulty":"Trung bình","theme_code":"GOTHIC"}', 'Kịch bản Suy luận phá án - Độ khó: Trung bình', 1, 1),
    (32, 32, 1, '{"props":["Lệnh truy nã thưởng vàng","Huy hiệu cảnh sát trưởng","Bản đồ mỏ vàng biên giới","Chìa khóa rương sắt"],"clue_count":24,"bgm_playlist":"khach-san-hoang-hon-do_bgm","genre":"Cơ chế, hài hước","difficulty":"Dễ","theme_code":"MODERN"}', 'Kịch bản Cơ chế, hài hước - Độ khó: Dễ', 1, 1),
    (33, 33, 1, '{"props":["Bản chép bài đồng dao cổ","Nhật ký người gác hải đăng","Dây thừng mỏ neo bị cắt","Ống nhòm đồng hải quân"],"clue_count":24,"bgm_playlist":"than-thoai-dao-suong-mu_bgm","genre":"Suy luận phá án","difficulty":"Khó","theme_code":"DETECTIVE"}', 'Kịch bản Suy luận phá án - Độ khó: Khó', 1, 1),
    (34, 34, 1, '{"props":["Đồng hồ bấm giờ ký ức","Sơ đồ mạch lõi thời gian","Ghi chú từ lần lặp thứ tư","Thẻ định danh phòng thí nghiệm"],"clue_count":24,"bgm_playlist":"vong-lap-khong-gio_bgm","genre":"Giả tưởng, siêu nhiên","difficulty":"Khó","theme_code":"SCIFI"}', 'Kịch bản Giả tưởng, siêu nhiên - Độ khó: Khó', 1, 1),
    (35, 35, 1, '{"props":["Ống đựng tranh lụa trống","Quạt xếp đề thơ ẩn ý","Trâm cài ngọc bích","Thẻ bài khách quý trà lâu"],"clue_count":24,"bgm_playlist":"phong-hoa-tuyet-nguyet-lau_bgm","genre":"Tình cảm, tâm lý xã hội","difficulty":"Trung bình","theme_code":"ORIENTAL"}', 'Kịch bản Tình cảm, tâm lý xã hội - Độ khó: Trung bình', 1, 1),
    (36, 36, 1, '{"props":["Phiếu biểu quyết bồi thẩm đoàn","Sơ đồ căn hộ hiện trường","Cặp kính cận nhân chứng","Biên bản thực nghiệm thời gian"],"clue_count":24,"bgm_playlist":"ban-an-muoi-hai-boi-tham_bgm","genre":"Suy luận phá án","difficulty":"Trung bình","theme_code":"COURT"}', 'Kịch bản Suy luận phá án - Độ khó: Trung bình', 1, 1),
    (37, 37, 1, '{"props":["Khăn voan thêu phượng","Thiếp hồng khắc bát tự","Đôi nến long phụng tắt nửa chừng","Hộp sính lễ khóa đồng"],"clue_count":24,"bgm_playlist":"hon-le-ao-do_bgm","genre":"Kinh dị tâm linh","difficulty":"Khó","theme_code":"ORIENTAL"}', 'Kịch bản Kinh dị tâm linh - Độ khó: Khó', 1, 1),
    (38, 38, 1, '{"props":["Sổ trực ký túc xá 1998","Chìa khóa phòng kho tầng tư","Thẻ thư viện mờ tên","Đèn pin tuần tra bảo vệ"],"clue_count":24,"bgm_playlist":"ky-tuc-xa-tang-tu_bgm","genre":"Kinh dị tâm linh","difficulty":"Trung bình","theme_code":"SCHOOL"}', 'Kịch bản Kinh dị tâm linh - Độ khó: Trung bình', 1, 1),
    (39, 39, 1, '{"props":["Bảng số đấu giá VIP","Kính lúp kiểm định kim hoàn","Danh mục vật phẩm niêm yết","Sơ đồ hệ thống tia hồng ngoại"],"clue_count":24,"bgm_playlist":"phi-vu-dau-gia-kim-cuong-xanh_bgm","genre":"Cơ chế, hài hước","difficulty":"Dễ","theme_code":"MODERN"}', 'Kịch bản Cơ chế, hài hước - Độ khó: Dễ', 1, 1),
    (40, 40, 1, '{"props":["Vé tàu giường nằm khoang 4","Tấm ảnh gia đình cháy xém góc","Chiếc đồng hồ đeo tay cũ","Hộp quà tết buộc dây đỏ"],"clue_count":24,"bgm_playlist":"chuyen-tau-dem-di-ve-phia-nam_bgm","genre":"Tình cảm, tâm lý xã hội","difficulty":"Trung bình","theme_code":"TRAIN"}', 'Kịch bản Tình cảm, tâm lý xã hội - Độ khó: Trung bình', 1, 1),
    (41, 41, 1, '{"props":["Chip lưu trữ ký ức phân mảnh","Nhật ký truy cập máy chủ lõi","Thẻ căn cước sinh trắc học","Bản thiết kế giao thức tường lửa"],"clue_count":24,"bgm_playlist":"thanh-pho-ngam-cyber-2099_bgm","genre":"Giả tưởng, siêu nhiên","difficulty":"Khó","theme_code":"SCIFI"}', 'Kịch bản Giả tưởng, siêu nhiên - Độ khó: Khó', 1, 1),
    (42, 42, 1, '{"props":["Sáu bánh răng đồng khắc ký tự","Bản vẽ cơ khí tháp chuông","Chìa lên dây cót cổ","Nhật ký người thợ đồng hồ"],"clue_count":24,"bgm_playlist":"mat-that-thap-dong-ho-co_bgm","genre":"Suy luận phá án","difficulty":"Khó","theme_code":"DETECTIVE"}', 'Kịch bản Suy luận phá án - Độ khó: Khó', 1, 1)
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
    (10, 4, 190000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (11, 4, 220000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (12, 4, 250000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (13, 5, 200000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (14, 5, 230000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (15, 5, 260000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (16, 6, 180000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (17, 6, 220000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (18, 6, 240000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (19, 7, 210000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (20, 7, 240000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (21, 7, 270000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (22, 8, 210000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (23, 8, 240000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (24, 8, 270000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (25, 9, 180000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (26, 9, 200000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (27, 9, 230000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (28, 10, 190000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (29, 10, 220000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (30, 10, 250000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (31, 11, 160000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (32, 11, 190000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (33, 11, 210000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (34, 12, 190000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (35, 12, 220000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (36, 12, 250000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (37, 13, 190000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (38, 13, 220000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (39, 13, 250000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (40, 14, 170000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (41, 14, 190000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (42, 14, 220000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (43, 15, 180000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (44, 15, 210000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (45, 15, 240000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (46, 16, 190000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (47, 16, 230000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (48, 16, 260000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (49, 17, 200000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (50, 17, 230000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (51, 17, 260000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (52, 18, 190000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (53, 18, 220000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (54, 18, 250000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (55, 19, 200000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (56, 19, 230000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (57, 19, 260000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (58, 20, 180000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (59, 20, 210000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (60, 20, 240000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (61, 21, 190000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (62, 21, 220000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (63, 21, 250000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (64, 22, 180000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (65, 22, 210000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (66, 22, 240000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (67, 23, 180000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (68, 23, 220000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (69, 23, 250000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (70, 24, 190000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (71, 24, 220000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (72, 24, 250000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (73, 25, 200000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (74, 25, 230000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (75, 25, 260000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (76, 26, 180000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (77, 26, 210000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (78, 26, 240000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (79, 27, 190000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (80, 27, 230000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (81, 27, 260000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (82, 28, 180000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (83, 28, 210000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (84, 28, 240000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (85, 29, 170000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (86, 29, 200000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (87, 29, 230000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (88, 30, 200000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (89, 30, 230000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (90, 30, 260000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (91, 31, 180000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (92, 31, 220000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (93, 31, 250000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (94, 32, 170000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (95, 32, 200000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (96, 32, 230000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (97, 33, 190000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (98, 33, 230000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (99, 33, 260000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (100, 34, 200000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (101, 34, 230000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (102, 34, 260000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (103, 35, 190000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (104, 35, 220000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (105, 35, 250000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (106, 36, 180000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (107, 36, 210000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (108, 36, 240000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (109, 37, 200000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (110, 37, 230000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (111, 37, 260000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (112, 38, 170000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (113, 38, 200000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (114, 38, 230000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (115, 39, 180000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (116, 39, 210000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (117, 39, 240000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (118, 40, 180000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (119, 40, 210000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (120, 40, 240000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (121, 41, 200000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (122, 41, 230000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (123, 41, 260000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (124, 42, 190000, '09:00:00', '13:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (125, 42, 220000, '13:00:00', '18:00:00', '2026-01-01', '2030-12-31', 'ACTIVE'),
    (126, 42, 250000, '18:00:00', '23:00:00', '2026-01-01', '2030-12-31', 'ACTIVE')
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
    (1, 1, 1, 3, 'Tuyển thám tử phá án Đêm Đoạt Mệnh tối Thứ 7', 'Tìm thêm đồng đội suy luận logic, không khí thân thiện.', 6, 6, 'FORMING', 'PUBLIC', 1, 6, 1, 6, 0, DATE_ADD(NOW(), INTERVAL 3 DAY))
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);

INSERT INTO `room_participants` (`id`, `game_room_id`, `user_id`, `status`, `joined_at`)
VALUES
    (1, 1, 3, 'PENDING_DEPOSIT', NOW())
ON DUPLICATE KEY UPDATE `status` = VALUES(`status`);
