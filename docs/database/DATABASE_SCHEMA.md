# ĐẶC TẢ CHI TIẾT CẤU TRÚC CƠ SỞ DỮ LIỆU CANONICAL (34 BẢNG)
## (CANONICAL DATABASE SCHEMA SPECIFICATION)
### DỰ ÁN: WEBSITE BOOKING – KỊCH BẢN SÁT (JUBENSHA / MURDER MYSTERY) V16

- **Phiên bản Schema:** V1 Baseline (34 Tables)
- **Engine:** MySQL 8.0+ / 8.4 LTS, InnoDB Storage Engine
- **Bộ mã ký tự:** `utf8mb4`, Collation: `utf8mb4_unicode_ci`
- **Khóa chính chuẩn hóa:** 100% sử dụng `id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
- **Tài liệu:** `docs/database/DATABASE_SCHEMA.md`

---

## 1. PHÂN BỔ THEO 8 BOUNDED CONTEXTS

```
                                  [IDENTITY & ACCESS]
                                     users (Core)
                                   /      |       \
                      user_profiles  user_roles    dm_profiles
                                          |             |
                                        roles     dm_availabilities
                                                        |
[GAME CATALOG]                                    dm_assignments
games (Core)                                            |
 ├── game_rules                                         v
 ├── game_configurations ──────────────────────> [VENUE & SCHEDULING]
 ├── game_category_assignments ──> categories      physical_rooms
 ├── game_prices                                        |
 └── game_rooms (Core) <────────────────── physical_room_reservations
         |                                              ^
         ├── room_participants                          |
         │     ├── participant_status_histories         |
         │     ├── participant_leave_requests           |
         │     └── replacement_windows                  |
         ├── game_room_status_histories                 |
         ├── schedule_proposals ──> schedule_responses  |
         └── sessions ──────────────────────────────────┘
                 |
                 ├── booking_price_snapshots <── promotions
                 └── player_payments (Financial)
                         ├── payment_attempts ──> payment_transactions
                         └── refunds ──> refund_attempts

[NOTIFICATION & AUDIT]
notifications ──> notification_recipient_states
audit_logs (Immutable System Log)
```

---

## 2. DANH MỤC CHI TIẾT 34 BẢNG VẬT LÝ

### Context 01: Identity & Access (4 Bảng)

#### 1. `roles` (Danh mục vai trò hệ thống)
- `id`: BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `name`: VARCHAR(50) NOT NULL UNIQUE KEY (Ví dụ: `ADMIN`, `DM`, `PLAYER`)
- `description`: VARCHAR(255) NULL
- `created_at`: DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3)
- `updated_at`: DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3)

#### 2. `users` (Tài khoản người dùng)
- `id`: BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `name`: VARCHAR(100) NOT NULL
- `email`: VARCHAR(191) NOT NULL UNIQUE KEY (`uk_users_email`)
- `password_hash`: VARCHAR(255) NOT NULL
- `phone`: VARCHAR(20) NULL
- `status`: ENUM('ACTIVE', 'SUSPENDED', 'DELETED') NOT NULL DEFAULT 'ACTIVE'
- `email_verified_at`: DATETIME(3) NULL
- `created_at`: DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3)
- `updated_at`: DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3)
- Indexes: `idx_users_status` (`status`)

#### 3. `user_roles` (Bảng liên kết phân quyền N:M)
- `id`: BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `user_id`: BIGINT UNSIGNED NOT NULL, FK -> `users(id)` ON DELETE CASCADE
- `role_id`: BIGINT UNSIGNED NOT NULL, FK -> `roles(id)` ON DELETE RESTRICT
- `created_at`: DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3)
- Unique Key: `uk_user_roles_pair` (`user_id`, `role_id`)

#### 4. `user_profiles` (Hồ sơ mở rộng người dùng 1:1)
- `id`: BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `user_id`: BIGINT UNSIGNED NOT NULL UNIQUE KEY (`uk_user_profiles_user_id`), FK -> `users(id)` ON DELETE CASCADE
- `nickname`: VARCHAR(100) NULL
- `avatar_url`: VARCHAR(2048) NULL
- `bio`: TEXT NULL
- `student_card_verified`: TINYINT(1) NOT NULL DEFAULT 0
- `student_card_image_url`: VARCHAR(2048) NULL
- `created_at`: DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3)
- `updated_at`: DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3)

---

### Context 02: Game Catalog (5 Bảng)

#### 5. `games` (Kịch bản kịch bản sát / TRPG)
- `id`: BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `title`: VARCHAR(255) NOT NULL
- `slug`: VARCHAR(255) NOT NULL UNIQUE KEY (`uk_games_slug`)
- `description`: TEXT NULL
- `duration_minutes`: INT UNSIGNED NOT NULL
- `min_players`: INT UNSIGNED NOT NULL
- `max_players`: INT UNSIGNED NOT NULL
- `difficulty`: ENUM('EASY', 'MEDIUM', 'HARD', 'EXPERT') NOT NULL DEFAULT 'MEDIUM'
- `is_active`: TINYINT(1) NOT NULL DEFAULT 1
- `created_at`: DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3)
- `updated_at`: DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3)
- Check Constraints: `chk_games_players` (`min_players <= max_players`), `chk_games_min_players` (`min_players >= 1`)

#### 6. `game_rules` (Quy tắc kịch bản 1:N)
- `id`: BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `game_id`: BIGINT UNSIGNED NOT NULL, FK -> `games(id)` ON DELETE CASCADE
- `title`: VARCHAR(255) NOT NULL
- `content`: TEXT NOT NULL
- `order_index`: INT UNSIGNED NOT NULL DEFAULT 0
- `created_at`: DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3)
- `updated_at`: DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3)

#### 7. `game_configurations` (Cấu hình kỹ thuật kịch bản)
- `id`: BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `game_id`: BIGINT UNSIGNED NOT NULL, FK -> `games(id)` ON DELETE CASCADE
- `config_version`: VARCHAR(50) NOT NULL DEFAULT '1.0'
- `character_count`: INT UNSIGNED NOT NULL
- `requires_costume`: TINYINT(1) NOT NULL DEFAULT 0
- `has_special_props`: TINYINT(1) NOT NULL DEFAULT 0
- `config_payload`: JSON NULL
- `created_at`: DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3)
- `updated_at`: DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3)

#### 8. `categories` (Danh mục thể loại)
- `id`: BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `name`: VARCHAR(100) NOT NULL UNIQUE KEY (`uk_categories_name`)
- `slug`: VARCHAR(100) NOT NULL UNIQUE KEY (`uk_categories_slug`)
- `created_at`: DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3)

#### 9. `game_category_assignments` (Liên kết đa thể loại N:M)
- `id`: BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `game_id`: BIGINT UNSIGNED NOT NULL, FK -> `games(id)` ON DELETE CASCADE
- `category_id`: BIGINT UNSIGNED NOT NULL, FK -> `categories(id)` ON DELETE RESTRICT
- `created_at`: DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3)
- Unique Key: `uk_gca_pair` (`game_id`, `category_id`)

---

### Context 03: GameRoom Management (6 Bảng)

#### 10. `game_rooms` (Phòng chơi ghép đội)
- `id`: BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `code`: VARCHAR(32) NOT NULL UNIQUE KEY (`uk_game_rooms_code`)
- `creator_id`: BIGINT UNSIGNED NOT NULL, FK -> `users(id)` ON DELETE RESTRICT
- `game_id`: BIGINT UNSIGNED NOT NULL, FK -> `games(id)` ON DELETE RESTRICT
- `status`: ENUM('FORMING', 'FORMED', 'SCHEDULE_NEGOTIATING', 'SCHEDULED', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED', 'EXPIRED') NOT NULL DEFAULT 'FORMING'
- `current_player_count`: INT UNSIGNED NOT NULL DEFAULT 1
- `max_player_count`: INT UNSIGNED NOT NULL
- `forming_expires_at`: DATETIME(3) NOT NULL
- `created_at`: DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3)
- `updated_at`: DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3)

#### 11. `room_participants` (Thành viên trong phòng)
- `id`: BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `game_room_id`: BIGINT UNSIGNED NOT NULL, FK -> `game_rooms(id)` ON DELETE CASCADE
- `user_id`: BIGINT UNSIGNED NOT NULL, FK -> `users(id)` ON DELETE RESTRICT
- `role_in_room`: ENUM('HOST', 'MEMBER') NOT NULL DEFAULT 'MEMBER'
- `status`: ENUM('JOINED', 'CONFIRMED', 'LEFT', 'CANCELLED') NOT NULL DEFAULT 'JOINED'
- `deposit_status`: ENUM('UNPAID', 'PAID', 'REFUNDED') NOT NULL DEFAULT 'UNPAID'
- `active_user_id`: BIGINT UNSIGNED GENERATED ALWAYS AS (IF(status IN ('JOINED', 'CONFIRMED'), user_id, NULL)) VIRTUAL
- `created_at`: DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3)
- `updated_at`: DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3)
- Unique Key: `uk_rp_active_user` (`game_room_id`, `active_user_id`)

#### 12. `game_room_status_histories` (Nhật ký trạng thái phòng)
- `id`: BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `game_room_id`: BIGINT UNSIGNED NOT NULL, FK -> `game_rooms(id)` ON DELETE CASCADE
- `from_status`: VARCHAR(50) NOT NULL
- `to_status`: VARCHAR(50) NOT NULL
- `changed_by_user_id`: BIGINT UNSIGNED NULL, FK -> `users(id)` ON DELETE SET NULL
- `reason`: VARCHAR(255) NULL
- `created_at`: DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3)

#### 13. `participant_status_histories` (Nhật ký trạng thái người chơi)
- `id`: BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `room_participant_id`: BIGINT UNSIGNED NOT NULL, FK -> `room_participants(id)` ON DELETE CASCADE
- `from_status`: VARCHAR(50) NOT NULL
- `to_status`: VARCHAR(50) NOT NULL
- `reason`: VARCHAR(255) NULL
- `created_at`: DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3)

#### 14. `replacement_windows` (Cửa sổ tìm người thay thế)
- `id`: BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `game_room_id`: BIGINT UNSIGNED NOT NULL, FK -> `game_rooms(id)` ON DELETE CASCADE
- `original_participant_id`: BIGINT UNSIGNED NOT NULL, FK -> `room_participants(id)` ON DELETE RESTRICT
- `replacement_participant_id`: BIGINT UNSIGNED NULL, FK -> `room_participants(id)` ON DELETE SET NULL
- `status`: ENUM('OPEN', 'FILLED', 'EXPIRED', 'CANCELLED') NOT NULL DEFAULT 'OPEN'
- `expires_at`: DATETIME(3) NOT NULL
- `created_at`: DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3)
- `updated_at`: DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3)

#### 15. `participant_leave_requests` (Yêu cầu rời phòng)
- `id`: BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `room_participant_id`: BIGINT UNSIGNED NOT NULL, FK -> `room_participants(id)` ON DELETE CASCADE
- `game_room_id`: BIGINT UNSIGNED NOT NULL, FK -> `game_rooms(id)` ON DELETE CASCADE
- `reason`: TEXT NOT NULL
- `status`: ENUM('PENDING', 'APPROVED', 'REJECTED') NOT NULL DEFAULT 'PENDING'
- `eligible_for_refund`: TINYINT(1) NOT NULL DEFAULT 0
- `reviewed_by_user_id`: BIGINT UNSIGNED NULL, FK -> `users(id)` ON DELETE SET NULL
- `reviewed_at`: DATETIME(3) NULL
- `pending_participant_id`: BIGINT UNSIGNED GENERATED ALWAYS AS (IF(status = 'PENDING', room_participant_id, NULL)) VIRTUAL
- `created_at`: DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3)
- Unique Key: `uk_plr_pending_participant` (`pending_participant_id`)

---

### Context 04: DM Management (3 Bảng)

#### 16. `dm_profiles` (Hồ sơ năng lực Quản trò)
- `id`: BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `user_id`: BIGINT UNSIGNED NOT NULL UNIQUE KEY (`uk_dm_profiles_user_id`), FK -> `users(id)` ON DELETE CASCADE
- `bio`: TEXT NULL
- `experience_years`: INT UNSIGNED NOT NULL DEFAULT 0
- `rating_avg`: DECIMAL(3, 2) NOT NULL DEFAULT 5.00
- `total_sessions_hosted`: INT UNSIGNED NOT NULL DEFAULT 0
- `is_active`: TINYINT(1) NOT NULL DEFAULT 1
- `created_at`: DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3)
- `updated_at`: DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3)

#### 17. `dm_availabilities` (Lịch rảnh đăng ký của DM)
- `id`: BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `dm_profile_id`: BIGINT UNSIGNED NOT NULL, FK -> `dm_profiles(id)` ON DELETE CASCADE
- `day_of_week`: TINYINT UNSIGNED NOT NULL COMMENT '1=Thứ 2, 7=Chủ Nhật'
- `start_time`: TIME NOT NULL
- `end_time`: TIME NOT NULL
- `is_active`: TINYINT(1) NOT NULL DEFAULT 1
- `created_at`: DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3)
- Check Constraint: `chk_dma_time_range` (`start_time < end_time`)

#### 18. `dm_assignments` (Phân công Quản trò)
- `id`: BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `game_room_id`: BIGINT UNSIGNED NOT NULL, FK -> `game_rooms(id)` ON DELETE CASCADE
- `dm_profile_id`: BIGINT UNSIGNED NOT NULL, FK -> `dm_profiles(id)` ON DELETE RESTRICT
- `status`: ENUM('PENDING', 'ACCEPTED', 'REJECTED', 'CANCELLED') NOT NULL DEFAULT 'PENDING'
- `assigned_at`: DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3)
- `responded_at`: DATETIME(3) NULL
- `rejection_reason`: VARCHAR(255) NULL
- `active_assigned_room_id`: BIGINT UNSIGNED GENERATED ALWAYS AS (IF(status IN ('PENDING', 'ACCEPTED'), game_room_id, NULL)) VIRTUAL
- Unique Key: `uk_da_active_room` (`active_assigned_room_id`)

---

### Context 05: Venue & Scheduling (5 Bảng)

#### 19. `physical_rooms` (Phòng chơi vật lý tại cửa hàng)
- `id`: BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `name`: VARCHAR(100) NOT NULL UNIQUE KEY (`uk_physical_rooms_name`)
- `capacity`: INT UNSIGNED NOT NULL
- `theme_description`: TEXT NULL
- `is_active`: TINYINT(1) NOT NULL DEFAULT 1
- `created_at`: DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3)
- `updated_at`: DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3)

#### 20. `schedule_proposals` (Đề xuất khung giờ chơi)
- `id`: BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `game_room_id`: BIGINT UNSIGNED NOT NULL, FK -> `game_rooms(id)` ON DELETE CASCADE
- `proposed_by_user_id`: BIGINT UNSIGNED NOT NULL, FK -> `users(id)` ON DELETE RESTRICT
- `proposed_date`: DATE NOT NULL
- `start_time`: TIME NOT NULL
- `end_time`: TIME NOT NULL
- `status`: ENUM('PROPOSED', 'ACCEPTED', 'REJECTED', 'EXPIRED', 'CONVERTED_TO_SESSION') NOT NULL DEFAULT 'PROPOSED'
- `proposed_game_room_id`: BIGINT UNSIGNED GENERATED ALWAYS AS (IF(status = 'PROPOSED', game_room_id, NULL)) VIRTUAL
- `created_at`: DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3)
- Unique Key: `uk_sp_proposed_room` (`proposed_game_room_id`)

#### 21. `schedule_responses` (Phản hồi biểu quyết lịch hẹn)
- `id`: BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `schedule_proposal_id`: BIGINT UNSIGNED NOT NULL, FK -> `schedule_proposals(id)` ON DELETE CASCADE
- `room_participant_id`: BIGINT UNSIGNED NOT NULL, FK -> `room_participants(id)` ON DELETE CASCADE
- `response`: ENUM('AGREE', 'DECLINE') NOT NULL
- `responded_at`: DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3)
- Unique Key: `uk_sr_participant_proposal` (`schedule_proposal_id`, `room_participant_id`)

#### 22. `sessions` (Phiên chơi chính thức)
- `id`: BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `game_room_id`: BIGINT UNSIGNED NOT NULL, FK -> `game_rooms(id)` ON DELETE RESTRICT
- `dm_assignment_id`: BIGINT UNSIGNED NULL, FK -> `dm_assignments(id)` ON DELETE SET NULL
- `scheduled_date`: DATE NOT NULL
- `start_time`: TIME NOT NULL
- `end_time`: TIME NOT NULL
- `status`: ENUM('SCHEDULED', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED') NOT NULL DEFAULT 'SCHEDULED'
- `cancellation_reason`: VARCHAR(255) NULL
- `created_at`: DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3)
- `updated_at`: DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3)
- Check Constraint: `chk_sessions_time_range` (`start_time < end_time`)

#### 23. `physical_room_reservations` (Giữ chỗ phòng vật lý)
- `id`: BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `physical_room_id`: BIGINT UNSIGNED NOT NULL, FK -> `physical_rooms(id)` ON DELETE RESTRICT
- `session_id`: BIGINT UNSIGNED NOT NULL, FK -> `sessions(id)` ON DELETE CASCADE
- `reservation_date`: DATE NOT NULL
- `start_time`: TIME NOT NULL
- `end_time`: TIME NOT NULL
- `status`: ENUM('RESERVED', 'CONFIRMED', 'CANCELLED') NOT NULL DEFAULT 'RESERVED'
- `active_session_id`: BIGINT UNSIGNED GENERATED ALWAYS AS (IF(status IN ('RESERVED', 'CONFIRMED'), session_id, NULL)) VIRTUAL
- `created_at`: DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3)
- `updated_at`: DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3)
- Unique Key: `uk_prr_active_session` (`active_session_id`)
- Check Constraint: `chk_prr_time_range` (`start_time < end_time`)
- Secondary Index: `idx_prr_lookup` (`physical_room_id`, `reservation_date`, `status`)

---

### Context 06: Pricing & Promotion (3 Bảng)

#### 24. `game_prices` (Biểu giá kịch bản theo khung giờ)
- `id`: BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `game_id`: BIGINT UNSIGNED NOT NULL, FK -> `games(id)` ON DELETE RESTRICT
- `time_slot`: ENUM('DAY_09_19', 'NIGHT_AFTER_20') NOT NULL
- `base_price_vnd`: BIGINT UNSIGNED NOT NULL
- `effective_from`: DATE NOT NULL
- `effective_to`: DATE NULL
- `is_active`: TINYINT(1) NOT NULL DEFAULT 1
- `created_at`: DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3)
- `updated_at`: DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3)
- Check Constraint: `chk_gp_price` (`base_price_vnd > 0`)

#### 25. `promotions` (Chương trình khuyến mãi)
- `id`: BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `code`: VARCHAR(50) NOT NULL UNIQUE KEY (`uk_promotions_code`)
- `name`: VARCHAR(255) NOT NULL
- `discount_type`: ENUM('PERCENT', 'FIXED_AMOUNT') NOT NULL
- `discount_value`: BIGINT UNSIGNED NOT NULL
- `start_date`: DATE NULL
- `end_date`: DATE NULL
- `applicable_day_of_week`: TINYINT UNSIGNED NULL COMMENT '2=Thứ 3'
- `is_active`: TINYINT(1) NOT NULL DEFAULT 1
- `is_stackable`: TINYINT(1) NOT NULL DEFAULT 0
- `created_at`: DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3)
- `updated_at`: DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3)

#### 26. `booking_price_snapshots` (Snapshot tài chính đóng băng phiên chơi)
- `id`: BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `session_id`: BIGINT UNSIGNED NOT NULL UNIQUE KEY (`uk_bps_session_id`), FK -> `sessions(id)` ON DELETE RESTRICT
- `game_price_id`: BIGINT UNSIGNED NULL, FK -> `game_prices(id)` ON DELETE SET NULL
- `promotion_id`: BIGINT UNSIGNED NULL, FK -> `promotions(id)` ON DELETE SET NULL
- `base_price_vnd`: BIGINT UNSIGNED NOT NULL
- `deposit_per_player_vnd`: BIGINT UNSIGNED NOT NULL DEFAULT 50000
- `discount_amount_vnd`: BIGINT UNSIGNED NOT NULL DEFAULT 0
- `final_price_per_player_vnd`: BIGINT UNSIGNED NOT NULL
- `total_amount_vnd`: BIGINT UNSIGNED NOT NULL
- `snapshot_reason`: VARCHAR(255) NOT NULL DEFAULT 'SESSION_BOOKING_LOCKED'
- `created_at`: DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3)

---

### Context 07: Payment & Refund (5 Bảng)

#### 27. `player_payments` (Nghĩa vụ thanh toán của người chơi)
- `id`: BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `room_participant_id`: BIGINT UNSIGNED NOT NULL, FK -> `room_participants(id)` ON DELETE RESTRICT
- `session_id`: BIGINT UNSIGNED NOT NULL, FK -> `sessions(id)` ON DELETE RESTRICT
- `payment_type`: ENUM('DEPOSIT', 'REMAINING', 'FULL') NOT NULL
- `amount_vnd`: BIGINT UNSIGNED NOT NULL
- `status`: ENUM('PENDING', 'PAID', 'FAILED', 'REFUNDED') NOT NULL DEFAULT 'PENDING'
- `due_date`: DATETIME(3) NULL
- `created_at`: DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3)
- `updated_at`: DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3)
- Check Constraint: `chk_pp_amount` (`amount_vnd > 0`)

#### 28. `payment_attempts` (Lần gọi cổng thanh toán mô phỏng)
- `id`: BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `player_payment_id`: BIGINT UNSIGNED NOT NULL, FK -> `player_payments(id)` ON DELETE CASCADE
- `idempotency_key`: VARCHAR(128) NOT NULL UNIQUE KEY (`uk_pa_idempotency_key`)
- `gateway_provider`: VARCHAR(50) NOT NULL DEFAULT 'SIMULATED_MOCK'
- `amount_vnd`: BIGINT UNSIGNED NOT NULL
- `status`: ENUM('PENDING', 'PROCESSING', 'SUCCESS', 'FAILED') NOT NULL DEFAULT 'PENDING'
- `error_message`: VARCHAR(255) NULL
- `created_at`: DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3)
- `updated_at`: DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3)

#### 29. `payment_transactions` (Sổ cái giao dịch kế toán thành công)
- `id`: BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `payment_attempt_id`: BIGINT UNSIGNED NOT NULL UNIQUE KEY (`uk_pt_attempt_id`), FK -> `payment_attempts(id)` ON DELETE RESTRICT
- `transaction_reference`: VARCHAR(128) NOT NULL UNIQUE KEY (`uk_pt_tx_ref`)
- `amount_vnd`: BIGINT UNSIGNED NOT NULL
- `transacted_at`: DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3)
- `metadata_payload`: JSON NULL

#### 30. `refunds` (Yêu cầu và quyết định hoàn tiền)
- `id`: BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `player_payment_id`: BIGINT UNSIGNED NOT NULL, FK -> `player_payments(id)` ON DELETE RESTRICT
- `room_participant_id`: BIGINT UNSIGNED NOT NULL, FK -> `room_participants(id)` ON DELETE RESTRICT
- `refund_amount_vnd`: BIGINT UNSIGNED NOT NULL
- `status`: ENUM('PENDING', 'APPROVED', 'PROCESSING', 'COMPLETED', 'REJECTED') NOT NULL DEFAULT 'PENDING'
- `reason`: TEXT NOT NULL
- `processed_by_user_id`: BIGINT UNSIGNED NULL, FK -> `users(id)` ON DELETE SET NULL
- `processed_at`: DATETIME(3) NULL
- `created_at`: DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3)
- `updated_at`: DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3)
- Check Constraint: `chk_refunds_amount` (`refund_amount_vnd > 0`)

#### 31. `refund_attempts` (Lần thực thi hoàn tiền qua cổng)
- `id`: BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `refund_id`: BIGINT UNSIGNED NOT NULL, FK -> `refunds(id)` ON DELETE CASCADE
- `idempotency_key`: VARCHAR(128) NOT NULL UNIQUE KEY (`uk_ra_idempotency_key`)
- `amount_vnd`: BIGINT UNSIGNED NOT NULL
- `status`: ENUM('PENDING', 'SUCCESS', 'FAILED') NOT NULL DEFAULT 'PENDING'
- `error_message`: VARCHAR(255) NULL
- `created_at`: DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3)
- `updated_at`: DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3)

---

### Context 08: Notification & Audit (3 Bảng)

#### 32. `notifications` (Nội dung thông báo)
- `id`: BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `sender_id`: BIGINT UNSIGNED NULL, FK -> `users(id)` ON DELETE SET NULL
- `title`: VARCHAR(255) NOT NULL
- `content`: TEXT NOT NULL
- `type`: ENUM('SYSTEM', 'ROOM_UPDATE', 'PAYMENT', 'SCHEDULE', 'REMINDER') NOT NULL DEFAULT 'SYSTEM'
- `created_at`: DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3)

#### 33. `notification_recipient_states` (Trạng thái đọc của từng người nhận)
- `id`: BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `notification_id`: BIGINT UNSIGNED NOT NULL, FK -> `notifications(id)` ON DELETE CASCADE
- `user_id`: BIGINT UNSIGNED NOT NULL, FK -> `users(id)` ON DELETE CASCADE
- `is_read`: TINYINT(1) NOT NULL DEFAULT 0
- `read_at`: DATETIME(3) NULL
- `created_at`: DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3)
- Unique Key: `uk_nrs_pair` (`notification_id`, `user_id`)
- Index: `idx_nrs_user_unread` (`user_id`, `is_read`, `created_at`)

#### 34. `audit_logs` (Nhật ký kiểm toán hệ thống bất biến)
- `id`: BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `user_id`: BIGINT UNSIGNED NULL, FK -> `users(id)` ON DELETE SET NULL
- `action`: VARCHAR(100) NOT NULL (Ví dụ: `ROLE_ASSIGNED`, `ROOM_CANCELLED`, `REFUND_APPROVED`)
- `entity_type`: VARCHAR(100) NOT NULL (Ví dụ: `User`, `GameRoom`, `Refund`)
- `entity_id`: BIGINT UNSIGNED NOT NULL
- `ip_address`: VARCHAR(45) NULL
- `user_agent`: VARCHAR(255) NULL
- `details_payload`: JSON NULL
- `created_at`: DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3)
- Indexes: `idx_audit_logs_entity` (`entity_type`, `entity_id`), `idx_audit_logs_user` (`user_id`, `created_at`)
