-- Migration: 001_create_scripts_table.sql
-- Description: Tạo bảng kịch bản (scripts / escape rooms) cho hệ thống đặt lịch
-- Author: TV2 (Backend Developer)

CREATE TABLE IF NOT EXISTS `scripts` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Mã định danh kịch bản (VD: KBS-01)',
    `title` VARCHAR(255) NOT NULL COMMENT 'Tên kịch bản / Tên phòng chơi',
    `genre` VARCHAR(100) NOT NULL COMMENT 'Thể loại (Kinh dị, Cổ trang, Trinh thám, Hài hước...)',
    `description` TEXT NOT NULL COMMENT 'Cốt truyện tóm tắt / Bối cảnh trò chơi',
    `min_players` TINYINT UNSIGNED NOT NULL DEFAULT 4 COMMENT 'Số người chơi tối thiểu',
    `max_players` TINYINT UNSIGNED NOT NULL DEFAULT 8 COMMENT 'Số người chơi tối đa',
    `male_players` TINYINT UNSIGNED NULL COMMENT 'Số nhân vật nam đề xuất',
    `female_players` TINYINT UNSIGNED NULL COMMENT 'Số nhân vật nữ đề xuất',
    `duration_minutes` SMALLINT UNSIGNED NOT NULL DEFAULT 180 COMMENT 'Thời lượng phiên chơi (phút)',
    `price_per_player` DECIMAL(12, 0) NOT NULL DEFAULT 0 COMMENT 'Giá vé trên mỗi người chơi (VND)',
    `difficulty` TINYINT UNSIGNED NOT NULL DEFAULT 3 COMMENT 'Độ khó từ 1 đến 5 sao',
    `cover_image` VARCHAR(500) NULL COMMENT 'Đường dẫn ảnh bìa / poster kịch bản',
    `status` ENUM('ACTIVE', 'INACTIVE') NOT NULL DEFAULT 'ACTIVE' COMMENT 'Trạng thái hoạt động',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_scripts_genre` (`genre`),
    INDEX `idx_scripts_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
