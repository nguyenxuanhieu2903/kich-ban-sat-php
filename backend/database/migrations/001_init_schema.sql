SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Người dùng (3 vai trò: PLAYER, DM, ADMIN)
CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(160) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'PLAYER',
    student_verified TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Danh mục kịch bản (Games)
CREATE TABLE IF NOT EXISTS games (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(180) NOT NULL,
    slug VARCHAR(200) NOT NULL UNIQUE,
    category VARCHAR(80) NOT NULL DEFAULT 'Trinh thám',
    description TEXT NULL,
    min_players TINYINT UNSIGNED NOT NULL DEFAULT 5,
    max_players TINYINT UNSIGNED NOT NULL DEFAULT 7,
    duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 210,
    is_18_plus TINYINT(1) NOT NULL DEFAULT 0,
    price_day_vnd BIGINT UNSIGNED NOT NULL COMMENT 'Khung 09:00-19:59',
    price_evening_vnd BIGINT UNSIGNED NOT NULL COMMENT 'Khung tu 20:00',
    deposit_vnd BIGINT UNSIGNED NOT NULL DEFAULT 50000 COMMENT 'Business Freeze V16: co dinh 50.000d/nguoi',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    menu_verified TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_games_active_cat (is_active, category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Phòng chơi (GameRooms)
CREATE TABLE IF NOT EXISTS game_rooms (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    room_code VARCHAR(20) NOT NULL UNIQUE,
    game_id BIGINT UNSIGNED NOT NULL,
    host_user_id BIGINT UNSIGNED NOT NULL,
    scheduled_start_at DATETIME NOT NULL COMMENT 'Chi nhan phut :00 hoac :30',
    status VARCHAR(40) NOT NULL DEFAULT 'FORMING',
    formation_deadline_at DATETIME NOT NULL COMMENT 'Han 4 gio gom nguoi',
    reopen_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
    snapshot_game_rule JSON NOT NULL,
    cancel_reason TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_rooms_game FOREIGN KEY (game_id) REFERENCES games(id),
    CONSTRAINT fk_rooms_host FOREIGN KEY (host_user_id) REFERENCES users(id),
    INDEX idx_rooms_status_start (status, scheduled_start_at),
    INDEX idx_rooms_host_status (host_user_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Thành viên phòng chơi (RoomParticipants - bản ghi terminal không tái kích hoạt, rejoin tạo dòng mới)
CREATE TABLE IF NOT EXISTS room_participants (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    room_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'PENDING_DEPOSIT',
    deposit_deadline_at DATETIME NULL COMMENT 'Han coc 2 gio rieng tung nguoi',
    joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    left_at DATETIME NULL,
    leave_reason TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_part_room FOREIGN KEY (room_id) REFERENCES game_rooms(id),
    CONSTRAINT fk_part_user FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_part_room_status (room_id, status),
    INDEX idx_part_user_status (user_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Phòng vật lý tại quán
CREATE TABLE IF NOT EXISTS physical_rooms (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    capacity TINYINT UNSIGNED NOT NULL COMMENT 'Tinh ca DM',
    theme VARCHAR(100) NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'AVAILABLE',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Phân công Quản trò (DM Assignments)
CREATE TABLE IF NOT EXISTS dm_assignments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    room_id BIGINT UNSIGNED NOT NULL,
    dm_user_id BIGINT UNSIGNED NOT NULL,
    assigned_by BIGINT UNSIGNED NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'PENDING',
    reason TEXT NULL,
    responded_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_dm_room FOREIGN KEY (room_id) REFERENCES game_rooms(id),
    CONSTRAINT fk_dm_user FOREIGN KEY (dm_user_id) REFERENCES users(id),
    CONSTRAINT fk_dm_admin FOREIGN KEY (assigned_by) REFERENCES users(id),
    INDEX idx_dm_room_status (room_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Đề xuất đổi lịch (Schedule Proposals)
CREATE TABLE IF NOT EXISTS schedule_proposals (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    room_id BIGINT UNSIGNED NOT NULL,
    proposed_by BIGINT UNSIGNED NOT NULL,
    old_start_at DATETIME NOT NULL,
    new_start_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL COMMENT 'Han bieu quyet 2 gio',
    status VARCHAR(30) NOT NULL DEFAULT 'PROPOSED',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_prop_room FOREIGN KEY (room_id) REFERENCES game_rooms(id),
    CONSTRAINT fk_prop_user FOREIGN KEY (proposed_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Phiên chơi chính thức (Game Sessions)
CREATE TABLE IF NOT EXISTS game_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    room_id BIGINT UNSIGNED NOT NULL UNIQUE,
    dm_user_id BIGINT UNSIGNED NOT NULL,
    physical_room_id BIGINT UNSIGNED NOT NULL,
    start_at DATETIME NOT NULL,
    end_at DATETIME NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'SCHEDULED',
    cancel_reason TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_sess_room FOREIGN KEY (room_id) REFERENCES game_rooms(id),
    CONSTRAINT fk_sess_dm FOREIGN KEY (dm_user_id) REFERENCES users(id),
    CONSTRAINT fk_sess_phys FOREIGN KEY (physical_room_id) REFERENCES physical_rooms(id),
    INDEX idx_sess_phys_time (physical_room_id, start_at, end_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Giao dịch thanh toán (Player Payments - hỗ trợ Idempotency)
CREATE TABLE IF NOT EXISTS payment_transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    room_id BIGINT UNSIGNED NOT NULL,
    participant_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    idempotency_key VARCHAR(100) NULL UNIQUE,
    payment_type VARCHAR(30) NOT NULL COMMENT 'DEPOSIT | REMAINING',
    amount_vnd BIGINT UNSIGNED NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'DEPOSIT_PENDING',
    confirmed_by BIGINT UNSIGNED NULL,
    note VARCHAR(255) NULL,
    paid_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_pay_room FOREIGN KEY (room_id) REFERENCES game_rooms(id),
    CONSTRAINT fk_pay_part FOREIGN KEY (participant_id) REFERENCES room_participants(id),
    CONSTRAINT fk_pay_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Hoàn tiền (Refunds)
CREATE TABLE IF NOT EXISTS refunds (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payment_id BIGINT UNSIGNED NOT NULL,
    participant_id BIGINT UNSIGNED NOT NULL,
    amount_vnd BIGINT UNSIGNED NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'REQUESTED',
    reference_code VARCHAR(100) NULL,
    reason TEXT NOT NULL,
    processed_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_ref_pay FOREIGN KEY (payment_id) REFERENCES payment_transactions(id),
    CONSTRAINT fk_ref_part FOREIGN KEY (participant_id) REFERENCES room_participants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Thông báo (Notifications)
CREATE TABLE IF NOT EXISTS notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    event_type VARCHAR(60) NOT NULL,
    title VARCHAR(180) NOT NULL,
    body TEXT NOT NULL,
    payload JSON NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_notif_user_read (user_id, is_read, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. Nhật ký kiểm toán (Audit Logs)
CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_user_id BIGINT UNSIGNED NULL,
    action VARCHAR(80) NOT NULL,
    target_type VARCHAR(50) NOT NULL,
    target_id BIGINT UNSIGNED NOT NULL,
    reason TEXT NULL,
    metadata JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_target (target_type, target_id),
    INDEX idx_audit_actor (actor_user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;