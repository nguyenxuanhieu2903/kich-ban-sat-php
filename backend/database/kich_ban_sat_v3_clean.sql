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

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================
-- SEED DATA ĐẦY ĐỦ CHO DỰ ÁN V3
-- =============================================================

-- 1. Roles
INSERT INTO `roles` (`id`, `code`, `name`, `description`)
VALUES
    (1, 'PLAYER', 'Player', 'Người chơi tham gia kịch bản'),
    (2, 'DM', 'Dungeon Master', 'Quản trò / Dẫn truyện kịch bản án mạng'),
    (3, 'ADMIN', 'Administrator', 'Quản trị viên hệ thống')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`);

-- 2. Users (Mật khẩu mặc định: 'password123' mã hóa bằng bcrypt $2y$10$...)
-- Hash: $2y$10$e8wJ5g6X81xVw3lZ4J8n1OQYyD54gTqW4h8L6r.x6Z9V3x.H7v1sK (hoặc chuẩn PHP password_hash)
INSERT INTO `users` (`id`, `role_id`, `full_name`, `email`, `password_hash`, `phone`, `avatar_url`, `status`, `email_verified_at`)
VALUES
    (1, 3, 'Quản Trị Viên', 'admin@kichbansat.vn', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0901000001', 'https://api.dicebear.com/7.x/bottts/svg?seed=Admin', 'ACTIVE', NOW()),
    (2, 2, 'Host Hoàng Nam (DM)', 'dm.nam@kichbansat.vn', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0902000002', 'https://api.dicebear.com/7.x/bottts/svg?seed=HostNam', 'ACTIVE', NOW()),
    (3, 1, 'Nguyễn Xuân Hiếu (Player)', 'player.hieu@kichbansat.vn', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0903000003', 'https://api.dicebear.com/7.x/bottts/svg?seed=HieuPlayer', 'ACTIVE', NOW()),
    (4, 1, 'Trần Minh Anh (Player)', 'minhanh@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0904000004', 'https://api.dicebear.com/7.x/bottts/svg?seed=MinhAnh', 'ACTIVE', NOW())
ON DUPLICATE KEY UPDATE `full_name` = VALUES(`full_name`);

-- 3. Venues (Chi nhánh quán)
INSERT INTO `venues` (`id`, `name`, `address`, `phone`, `description`, `status`)
VALUES
    (1, 'Kịch Bản Sát Station - Chi nhánh Quận 1', '123 Nguyễn Huệ, Phường Bến Nghé, Quận 1, TP. Hồ Chí Minh', '0901234567', 'Chi nhánh trung tâm với không gian nhập vai phong cách Châu Âu cổ điển', 'ACTIVE'),
    (2, 'Kịch Bản Sát Hub - Chi nhánh Bình Thạnh', '45 Điện Biên Phủ, Phường 15, Quận Bình Thạnh, TP. Hồ Chí Minh', '0907654321', 'Không gian hiện đại chuyên kịch bản kinh dị & trinh thám Á Đông', 'ACTIVE')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- 4. Physical Rooms (Phòng chơi ngoài đời)
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

-- 5. Games (Kịch bản án mạng hấp dẫn)
INSERT INTO `games` (`id`, `name`, `slug`, `description`, `image_url`, `min_players`, `max_players`, `duration_minutes`, `status`, `display_order`)
VALUES
    (1, 'Đêm Đoạt Mệnh Tại Trang Viên', 'dem-doat-menh-tai-trang-vien', 'Một bữa tiệc gia tộc bí ẩn nơi người đứng đầu bị sát hại trong đêm giông bão. Mỗi người đều có một bí mật không thể tiết lộ.', 'https://images.unsplash.com/photo-1518709268805-4e9042af9f23', 6, 8, 240, 'ACTIVE', 1),
    (2, 'Biệt Thự Huyết Tộc', 'biet-thu-huyet-toc', 'Cuộc đối đầu tâm lý nghẹt thở giữa các gia tộc ma cà rồng cổ đại khi bảo vật ngàn năm biến mất.', 'https://images.unsplash.com/photo-1509198397868-475647b2a1e5', 5, 7, 210, 'ACTIVE', 2),
    (3, 'Án Mạng Trên Chuyến Tàu Tuyết', 'an-mang-tren-chuyen-tau-tuyet', 'Chuyến tàu Bắc Cực bị mắc kẹt giữa bão tuyết và một thi thể được tìm thấy trong toa hành khách hạng nhất.', 'https://images.unsplash.com/photo-1515263487990-61b07816b324', 6, 8, 240, 'ACTIVE', 3)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- 6. Game Configurations (Phiên bản cấu hình đạo cụ v1)
INSERT INTO `game_configurations` (`id`, `game_id`, `version`, `configuration`, `description`, `is_active`, `created_by`)
VALUES
    (1, 1, 1, JSON_OBJECT('props', JSON_ARRAY('Thư tống tiền', 'Dao găm cổ', 'Chiếc nhẫn ngọc bích'), 'clue_count', 24, 'bgm_playlist', 'gothic_mystery_v1'), 'Phiên bản đạo cụ chuẩn 8 người chơi', 1, 1),
    (2, 2, 1, JSON_OBJECT('props', JSON_ARRAY('Bình rượu máu', 'Gia huy bạc', 'Cuốn nhật ký cổ'), 'clue_count', 20, 'bgm_playlist', 'vampire_noir'), 'Phiên bản kinh dị tâm lý', 1, 1),
    (3, 3, 1, JSON_OBJECT('props', JSON_ARRAY('Vé tàu hạng nhất', 'Đồng hồ quả quýt dừng ở 11h15', 'Chai thuốc ngủ'), 'clue_count', 26, 'bgm_playlist', 'snow_train_soundtrack'), 'Phiên bản suy luận cổ điển', 1, 1)
ON DUPLICATE KEY UPDATE `version` = VALUES(`version`);

-- 7. Game Prices (Bảng giá theo khung giờ)
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

-- 8. Promotions (Khuyến mãi)
INSERT INTO `promotions` (`id`, `code`, `name`, `promotion_type`, `discount_type`, `discount_value`, `start_at`, `end_at`, `rules`, `is_active`)
VALUES
    (1, 'TUESDAY_30', 'Ưu đãi vui vẻ - Thứ Ba Giảm 30%', 'WEEKDAY', 'PERCENT', 30, '2026-01-01 00:00:00.000', '2035-12-31 23:59:59.999', JSON_OBJECT('weekdays', JSON_ARRAY(2), 'valid_from', '09:00:00', 'valid_until', '19:00:00', 'minimum_booking_hours', 24, 'applies_to', 'ANY_GAME', 'stackable', FALSE), 1),
    (2, 'HSSV_20000', 'Ưu đãi học sinh sinh viên', 'STUDENT', 'FIXED', 20000, '2026-01-01 00:00:00.000', '2035-12-31 23:59:59.999', JSON_OBJECT('weekdays', JSON_ARRAY(1, 2, 3, 4), 'valid_from', '09:00:00', 'valid_until', '19:00:00', 'discount_per_eligible_person', 20000, 'requires_student_verification', TRUE, 'stackable', FALSE), 1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `rules` = VALUES(`rules`);

-- 9. Game Room mẫu (Phòng đang ghép người)
INSERT INTO `game_rooms` (`id`, `game_id`, `game_configuration_id`, `creator_id`, `title`, `description`, `min_players`, `max_players`, `status`, `formation_deadline_at`)
VALUES
    (1, 1, 1, 3, 'Tuyển thám tử phá án Đêm Đoạt Mệnh tối Thứ 7', 'Tìm thêm 4 bạn cùng sở thích suy luận, không toxic, chơi hết mình!', 6, 8, 'FORMING', DATE_ADD(NOW(), INTERVAL 3 DAY))
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);

-- 10. Room Participant mẫu (Host đã tham gia phòng)
INSERT INTO `room_participants` (`id`, `game_room_id`, `user_id`, `status`, `joined_at`)
VALUES
    (1, 1, 3, 'PENDING_DEPOSIT', NOW())
ON DUPLICATE KEY UPDATE `status` = VALUES(`status`);
