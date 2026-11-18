# TỪ ĐIỂN DỮ LIỆU CƠ SỞ DỮ LIỆU (DATA DICTIONARY)
## DỰ ÁN: BOOKING-PROJECT-PHP — BASELINE SCHEMA V1

- **Phiên bản Schema:** V1 (34 Tables)
- **Hệ quản trị CSDL:** MySQL 8.0.44 / 8.4 LTS
- **Bộ mã ký tự (Charset / Collation):** `utf8mb4` / `utf8mb4_unicode_ci`
- **Công cụ tạo tài liệu:** Tự động trích xuất trực tiếp từ `information_schema` qua PDO

---

## BOUNDED CONTEXT: 01. Identity & Access

### Bảng: `roles`
- **Engine:** InnoDB | **Collation:** utf8mb4_unicode_ci

| STT | Tên cột | Kiểu dữ liệu | Nullable | Mặc định | Khóa / Chỉ mục | Thuộc tính Extra | Ghi chú kỹ thuật |
|:---:|---|---|:---:|:---:|:---:|---|---|
| 1 | `id` | `bigint unsigned` | NO | `NULL` | PRI | auto_increment | Primary Key Clustered |
| 2 | `name` | `varchar(50)` | NO | `NULL` | UNI |  | Unique Key |
| 3 | `label` | `varchar(100)` | NO | `NULL` |  |  |  |
| 4 | `description` | `varchar(255)` | YES | `NULL` |  |  |  |
| 5 | `created_at` | `timestamp` | NO | `CURRENT_TIMESTAMP` |  | DEFAULT_GENERATED |  |
| 6 | `updated_at` | `timestamp` | NO | `CURRENT_TIMESTAMP` |  | DEFAULT_GENERATED on update CURRENT_TIMESTAMP |  |

### Bảng: `user_profiles`
- **Engine:** InnoDB | **Collation:** utf8mb4_unicode_ci

| STT | Tên cột | Kiểu dữ liệu | Nullable | Mặc định | Khóa / Chỉ mục | Thuộc tính Extra | Ghi chú kỹ thuật |
|:---:|---|---|:---:|:---:|:---:|---|---|
| 1 | `id` | `bigint unsigned` | NO | `NULL` | PRI | auto_increment | Primary Key Clustered |
| 2 | `user_id` | `bigint unsigned` | NO | `NULL` | UNI |  | Unique Key |
| 3 | `display_name` | `varchar(100)` | YES | `NULL` |  |  |  |
| 4 | `phone` | `varchar(20)` | YES | `NULL` |  |  |  |
| 5 | `avatar_url` | `varchar(255)` | YES | `NULL` |  |  |  |
| 6 | `bio` | `text` | YES | `NULL` |  |  |  |
| 7 | `date_of_birth` | `date` | YES | `NULL` |  |  |  |
| 8 | `gender` | `varchar(20)` | YES | `NULL` |  |  |  |
| 9 | `created_at` | `timestamp` | NO | `CURRENT_TIMESTAMP` |  | DEFAULT_GENERATED |  |
| 10 | `updated_at` | `timestamp` | NO | `CURRENT_TIMESTAMP` |  | DEFAULT_GENERATED on update CURRENT_TIMESTAMP |  |

### Bảng: `user_roles`
- **Engine:** InnoDB | **Collation:** utf8mb4_unicode_ci

| STT | Tên cột | Kiểu dữ liệu | Nullable | Mặc định | Khóa / Chỉ mục | Thuộc tính Extra | Ghi chú kỹ thuật |
|:---:|---|---|:---:|:---:|:---:|---|---|
| 1 | `id` | `bigint unsigned` | NO | `NULL` | PRI | auto_increment | Primary Key Clustered |
| 2 | `user_id` | `bigint unsigned` | NO | `NULL` | MUL |  | Secondary / FK Index |
| 3 | `role_id` | `bigint unsigned` | NO | `NULL` | MUL |  | Secondary / FK Index |
| 4 | `assigned_at` | `timestamp` | NO | `CURRENT_TIMESTAMP` |  | DEFAULT_GENERATED |  |

### Bảng: `users`
- **Engine:** InnoDB | **Collation:** utf8mb4_unicode_ci

| STT | Tên cột | Kiểu dữ liệu | Nullable | Mặc định | Khóa / Chỉ mục | Thuộc tính Extra | Ghi chú kỹ thuật |
|:---:|---|---|:---:|:---:|:---:|---|---|
| 1 | `id` | `bigint unsigned` | NO | `NULL` | PRI | auto_increment | Primary Key Clustered |
| 2 | `name` | `varchar(191)` | NO | `NULL` |  |  |  |
| 3 | `email` | `varchar(191)` | NO | `NULL` | UNI |  | Unique Key |
| 4 | `password_hash` | `varchar(255)` | NO | `NULL` |  |  |  |
| 5 | `phone` | `varchar(20)` | YES | `NULL` |  |  |  |
| 6 | `status` | `varchar(30)` | NO | `ACTIVE` | MUL |  | Secondary / FK Index |
| 7 | `email_verified_at` | `timestamp` | YES | `NULL` |  |  |  |
| 8 | `created_at` | `timestamp` | NO | `CURRENT_TIMESTAMP` |  | DEFAULT_GENERATED |  |
| 9 | `updated_at` | `timestamp` | NO | `CURRENT_TIMESTAMP` |  | DEFAULT_GENERATED on update CURRENT_TIMESTAMP |  |

---

## BOUNDED CONTEXT: 02. Game Catalog

### Bảng: `categories`
- **Engine:** InnoDB | **Collation:** utf8mb4_unicode_ci

| STT | Tên cột | Kiểu dữ liệu | Nullable | Mặc định | Khóa / Chỉ mục | Thuộc tính Extra | Ghi chú kỹ thuật |
|:---:|---|---|:---:|:---:|:---:|---|---|
| 1 | `id` | `bigint unsigned` | NO | `NULL` | PRI | auto_increment | Primary Key Clustered |
| 2 | `name` | `varchar(100)` | NO | `NULL` |  |  |  |
| 3 | `slug` | `varchar(100)` | NO | `NULL` | UNI |  | Unique Key |
| 4 | `description` | `text` | YES | `NULL` |  |  |  |
| 5 | `created_at` | `timestamp` | NO | `CURRENT_TIMESTAMP` |  | DEFAULT_GENERATED |  |
| 6 | `updated_at` | `timestamp` | NO | `CURRENT_TIMESTAMP` |  | DEFAULT_GENERATED on update CURRENT_TIMESTAMP |  |

### Bảng: `game_category_assignments`
- **Engine:** InnoDB | **Collation:** utf8mb4_unicode_ci

| STT | Tên cột | Kiểu dữ liệu | Nullable | Mặc định | Khóa / Chỉ mục | Thuộc tính Extra | Ghi chú kỹ thuật |
|:---:|---|---|:---:|:---:|:---:|---|---|
| 1 | `id` | `bigint unsigned` | NO | `NULL` | PRI | auto_increment | Primary Key Clustered |
| 2 | `game_id` | `bigint unsigned` | NO | `NULL` | MUL |  | Secondary / FK Index |
| 3 | `category_id` | `bigint unsigned` | NO | `NULL` | MUL |  | Secondary / FK Index |
| 4 | `created_at` | `timestamp` | NO | `CURRENT_TIMESTAMP` |  | DEFAULT_GENERATED |  |

### Bảng: `game_configurations`
- **Engine:** InnoDB | **Collation:** utf8mb4_unicode_ci

| STT | Tên cột | Kiểu dữ liệu | Nullable | Mặc định | Khóa / Chỉ mục | Thuộc tính Extra | Ghi chú kỹ thuật |
|:---:|---|---|:---:|:---:|:---:|---|---|
| 1 | `id` | `bigint unsigned` | NO | `NULL` | PRI | auto_increment | Primary Key Clustered |
| 2 | `game_id` | `bigint unsigned` | NO | `NULL` | MUL |  | Secondary / FK Index |
| 3 | `config_key` | `varchar(100)` | NO | `NULL` |  |  |  |
| 4 | `config_value` | `text` | NO | `NULL` |  |  |  |
| 5 | `created_at` | `timestamp` | NO | `CURRENT_TIMESTAMP` |  | DEFAULT_GENERATED |  |
| 6 | `updated_at` | `timestamp` | NO | `CURRENT_TIMESTAMP` |  | DEFAULT_GENERATED on update CURRENT_TIMESTAMP |  |

### Bảng: `game_rules`
- **Engine:** InnoDB | **Collation:** utf8mb4_unicode_ci

| STT | Tên cột | Kiểu dữ liệu | Nullable | Mặc định | Khóa / Chỉ mục | Thuộc tính Extra | Ghi chú kỹ thuật |
|:---:|---|---|:---:|:---:|:---:|---|---|
| 1 | `id` | `bigint unsigned` | NO | `NULL` | PRI | auto_increment | Primary Key Clustered |
| 2 | `game_id` | `bigint unsigned` | NO | `NULL` | MUL |  | Secondary / FK Index |
| 3 | `rule_name` | `varchar(191)` | NO | `NULL` |  |  |  |
| 4 | `rule_content` | `text` | NO | `NULL` |  |  |  |
| 5 | `display_order` | `int unsigned` | NO | `1` |  |  |  |
| 6 | `created_at` | `timestamp` | NO | `CURRENT_TIMESTAMP` |  | DEFAULT_GENERATED |  |
| 7 | `updated_at` | `timestamp` | NO | `CURRENT_TIMESTAMP` |  | DEFAULT_GENERATED on update CURRENT_TIMESTAMP |  |

### Bảng: `games`
- **Engine:** InnoDB | **Collation:** utf8mb4_unicode_ci

| STT | Tên cột | Kiểu dữ liệu | Nullable | Mặc định | Khóa / Chỉ mục | Thuộc tính Extra | Ghi chú kỹ thuật |
|:---:|---|---|:---:|:---:|:---:|---|---|
| 1 | `id` | `bigint unsigned` | NO | `NULL` | PRI | auto_increment | Primary Key Clustered |
| 2 | `title` | `varchar(191)` | NO | `NULL` |  |  |  |
| 3 | `slug` | `varchar(191)` | NO | `NULL` | UNI |  | Unique Key |
| 4 | `description` | `text` | YES | `NULL` |  |  |  |
| 5 | `min_players` | `int unsigned` | NO | `4` |  |  |  |
| 6 | `max_players` | `int unsigned` | NO | `8` |  |  |  |
| 7 | `duration_minutes` | `int unsigned` | NO | `240` |  |  |  |
| 8 | `is_active` | `tinyint(1)` | NO | `1` | MUL |  | Secondary / FK Index |
| 9 | `created_at` | `timestamp` | NO | `CURRENT_TIMESTAMP` |  | DEFAULT_GENERATED |  |
| 10 | `updated_at` | `timestamp` | NO | `CURRENT_TIMESTAMP` |  | DEFAULT_GENERATED on update CURRENT_TIMESTAMP |  |

---

## BOUNDED CONTEXT: 03. Pricing & Promotion

### Bảng: `game_prices`
- **Engine:** InnoDB | **Collation:** utf8mb4_unicode_ci

| STT | Tên cột | Kiểu dữ liệu | Nullable | Mặc định | Khóa / Chỉ mục | Thuộc tính Extra | Ghi chú kỹ thuật |
|:---:|---|---|:---:|:---:|:---:|---|---|
| 1 | `id` | `bigint unsigned` | NO | `NULL` | PRI | auto_increment | Primary Key Clustered |
| 2 | `game_id` | `bigint unsigned` | NO | `NULL` | MUL |  | Secondary / FK Index |
| 3 | `price_tier_name` | `varchar(50)` | NO | `NULL` |  |  |  |
| 4 | `day_type` | `varchar(20)` | NO | `ALL` |  |  |  |
| 5 | `time_from` | `time` | NO | `NULL` |  |  |  |
| 6 | `time_to` | `time` | NO | `NULL` |  |  |  |
| 7 | `base_price_per_player` | `bigint unsigned` | NO | `NULL` |  |  |  |
| 8 | `is_active` | `tinyint(1)` | NO | `1` |  |  |  |
| 9 | `created_at` | `timestamp` | NO | `CURRENT_TIMESTAMP` |  | DEFAULT_GENERATED |  |
| 10 | `updated_at` | `timestamp` | NO | `CURRENT_TIMESTAMP` |  | DEFAULT_GENERATED on update CURRENT_TIMESTAMP |  |

### Bảng: `promotions`
- **Engine:** InnoDB | **Collation:** utf8mb4_unicode_ci

| STT | Tên cột | Kiểu dữ liệu | Nullable | Mặc định | Khóa / Chỉ mục | Thuộc tính Extra | Ghi chú kỹ thuật |
|:---:|---|---|:---:|:---:|:---:|---|---|
| 1 | `id` | `bigint unsigned` | NO | `NULL` | PRI | auto_increment | Primary Key Clustered |
| 2 | `code` | `varchar(50)` | NO | `NULL` | UNI |  | Unique Key |
| 3 | `name` | `varchar(191)` | NO | `NULL` |  |  |  |
| 4 | `discount_type` | `varchar(20)` | NO | `NULL` |  |  |  |
| 5 | `discount_value` | `bigint unsigned` | NO | `NULL` |  |  |  |
| 6 | `min_participants` | `int unsigned` | NO | `1` |  |  |  |
| 7 | `valid_from` | `timestamp` | NO | `NULL` |  |  |  |
| 8 | `valid_to` | `timestamp` | NO | `NULL` |  |  |  |
| 9 | `non_stackable` | `tinyint(1)` | NO | `1` |  |  |  |
| 10 | `usage_limit` | `int unsigned` | YES | `NULL` |  |  |  |
| 11 | `times_used` | `int unsigned` | NO | `0` |  |  |  |
| 12 | `is_active` | `tinyint(1)` | NO | `1` |  |  |  |
| 13 | `created_at` | `timestamp` | NO | `CURRENT_TIMESTAMP` |  | DEFAULT_GENERATED |  |
| 14 | `updated_at` | `timestamp` | NO | `CURRENT_TIMESTAMP` |  | DEFAULT_GENERATED on update CURRENT_TIMESTAMP |  |

---

## BOUNDED CONTEXT: 04. DM Management

### Bảng: `dm_availabilities`
- **Engine:** InnoDB | **Collation:** utf8mb4_unicode_ci

| STT | Tên cột | Kiểu dữ liệu | Nullable | Mặc định | Khóa / Chỉ mục | Thuộc tính Extra | Ghi chú kỹ thuật |
|:---:|---|---|:---:|:---:|:---:|---|---|
| 1 | `id` | `bigint unsigned` | NO | `NULL` | PRI | auto_increment | Primary Key Clustered |
| 2 | `dm_profile_id` | `bigint unsigned` | NO | `NULL` | MUL |  | Secondary / FK Index |
| 3 | `day_of_week` | `tinyint unsigned` | NO | `NULL` |  |  |  |
| 4 | `start_time` | `time` | NO | `NULL` |  |  |  |
| 5 | `end_time` | `time` | NO | `NULL` |  |  |  |
| 6 | `specific_date` | `date` | YES | `NULL` |  |  |  |
| 7 | `is_available` | `tinyint(1)` | NO | `1` |  |  |  |
| 8 | `created_at` | `timestamp` | NO | `CURRENT_TIMESTAMP` |  | DEFAULT_GENERATED |  |
| 9 | `updated_at` | `timestamp` | NO | `CURRENT_TIMESTAMP` |  | DEFAULT_GENERATED on update CURRENT_TIMESTAMP |  |

### Bảng: `dm_profiles`
- **Engine:** InnoDB | **Collation:** utf8mb4_unicode_ci

| STT | Tên cột | Kiểu dữ liệu | Nullable | Mặc định | Khóa / Chỉ mục | Thuộc tính Extra | Ghi chú kỹ thuật |
|:---:|---|---|:---:|:---:|:---:|---|---|
| 1 | `id` | `bigint unsigned` | NO | `NULL` | PRI | auto_increment | Primary Key Clustered |
| 2 | `user_id` | `bigint unsigned` | NO | `NULL` | UNI |  | Unique Key |
| 3 | `experience_years` | `int unsigned` | NO | `0` |  |  |  |
| 4 | `bio` | `text` | YES | `NULL` |  |  |  |
| 5 | `rating_avg` | `decimal(3,2)` | NO | `5.00` |  |  |  |
| 6 | `status` | `varchar(30)` | NO | `ACTIVE` |  |  |  |
| 7 | `created_at` | `timestamp` | NO | `CURRENT_TIMESTAMP` |  | DEFAULT_GENERATED |  |
| 8 | `updated_at` | `timestamp` | NO | `CURRENT_TIMESTAMP` |  | DEFAULT_GENERATED on update CURRENT_TIMESTAMP |  |

---

## BOUNDED CONTEXT: 05. Gameroom Management

### Bảng: `dm_assignments`
- **Engine:** InnoDB | **Collation:** utf8mb4_unicode_ci

| STT | Tên cột | Kiểu dữ liệu | Nullable | Mặc định | Khóa / Chỉ mục | Thuộc tính Extra | Ghi chú kỹ thuật |
|:---:|---|---|:---:|:---:|:---:|---|---|
| 1 | `id` | `bigint unsigned` | NO | `NULL` | PRI | auto_increment | Primary Key Clustered |
| 2 | `game_room_id` | `bigint unsigned` | NO | `NULL` | MUL |  | Secondary / FK Index |
| 3 | `dm_profile_id` | `bigint unsigned` | NO | `NULL` | MUL |  | Secondary / FK Index |
| 4 | `status` | `varchar(40)` | NO | `ASSIGNED` |  |  |  |
| 5 | `assigned_at` | `timestamp` | NO | `CURRENT_TIMESTAMP` |  | DEFAULT_GENERATED |  |
| 6 | `confirmed_at` | `timestamp` | YES | `NULL` |  |  |  |
| 7 | `notes` | `text` | YES | `NULL` |  |  |  |
| 8 | `created_at` | `timestamp` | NO | `CURRENT_TIMESTAMP` |  | DEFAULT_GENERATED |  |
| 9 | `updated_at` | `timestamp` | NO | `CURRENT_TIMESTAMP` |  | DEFAULT_GENERATED on update CURRENT_TIMESTAMP |  |
| 10 | `active_assigned_room_id` | `bigint unsigned` | YES | `NULL` | UNI | VIRTUAL GENERATED | Unique Key | GENERATED AS: `(case when (`status` in (_utf8mb4\'ASSIGNED\',_utf8mb4\'CONFIRMED\')) then `game_room_id` else NULL end)` |

### Bảng: `game_room_status_histories`
- **Engine:** InnoDB | **Collation:** utf8mb4_unicode_ci

| STT | Tên cột | Kiểu dữ liệu | Nullable | Mặc định | Khóa / Chỉ mục | Thuộc tính Extra | Ghi chú kỹ thuật |
|:---:|---|---|:---:|:---:|:---:|---|---|
| 1 | `id` | `bigint unsigned` | NO | `NULL` | PRI | auto_increment | Primary Key Clustered |
| 2 | `game_room_id` | `bigint unsigned` | NO | `NULL` | MUL |  | Secondary / FK Index |
| 3 | `changed_by_user_id` | `bigint unsigned` | YES | `NULL` | MUL |  | Secondary / FK Index |
| 4 | `from_status` | `varchar(40)` | NO | `NULL` |  |  |  |
| 5 | `to_status` | `varchar(40)` | NO | `NULL` |  |  |  |
| 6 | `reason` | `text` | YES | `NULL` |  |  |  |
| 7 | `created_at` | `timestamp` | NO | `CURRENT_TIMESTAMP` |  | DEFAULT_GENERATED |  |

### Bảng: `game_rooms`
- **Engine:** InnoDB | **Collation:** utf8mb4_unicode_ci

| STT | Tên cột | Kiểu dữ liệu | Nullable | Mặc định | Khóa / Chỉ mục | Thuộc tính Extra | Ghi chú kỹ thuật |
|:---:|---|---|:---:|:---:|:---:|---|---|
| 1 | `id` | `bigint unsigned` | NO | `NULL` | PRI | auto_increment | Primary Key Clustered |
| 2 | `room_code` | `varchar(32)` | NO | `NULL` | UNI |  | Unique Key |
| 3 | `creator_id` | `bigint unsigned` | NO | `NULL` | MUL |  | Secondary / FK Index |
| 4 | `game_id` | `bigint unsigned` | NO | `NULL` | MUL |  | Secondary / FK Index |
| 5 | `title` | `varchar(191)` | NO | `NULL` |  |  |  |
| 6 | `min_players` | `int unsigned` | NO | `4` |  |  |  |
| 7 | `max_players` | `int unsigned` | NO | `8` |  |  |  |
| 8 | `status` | `varchar(40)` | NO | `FORMING` | MUL |  | Secondary / FK Index |
| 9 | `is_active` | `tinyint(1)` | NO | `1` |  |  |  |
| 10 | `formation_deadline` | `timestamp` | NO | `NULL` |  |  |  |
| 11 | `notes` | `text` | YES | `NULL` |  |  |  |
| 12 | `cancel_reason` | `text` | YES | `NULL` |  |  |  |
| 13 | `version` | `bigint unsigned` | NO | `0` |  |  |  |
| 14 | `created_at` | `timestamp` | NO | `CURRENT_TIMESTAMP` |  | DEFAULT_GENERATED |  |
| 15 | `updated_at` | `timestamp` | NO | `CURRENT_TIMESTAMP` |  | DEFAULT_GENERATED on update CURRENT_TIMESTAMP |  |

### Bảng: `participant_leave_requests`
- **Engine:** InnoDB | **Collation:** utf8mb4_unicode_ci

| STT | Tên cột | Kiểu dữ liệu | Nullable | Mặc định | Khóa / Chỉ mục | Thuộc tính Extra | Ghi chú kỹ thuật |
|:---:|---|---|:---:|:---:|:---:|---|---|
| 1 | `id` | `bigint unsigned` | NO | `NULL` | PRI | auto_increment | Primary Key Clustered |
| 2 | `room_participant_id` | `bigint unsigned` | NO | `NULL` | MUL |  | Secondary / FK Index |
| 3 | `game_room_id` | `bigint unsigned` | NO | `NULL` | MUL |  | Secondary / FK Index |
| 4 | `reviewed_by_user_id` | `bigint unsigned` | YES | `NULL` | MUL |  | Secondary / FK Index |
| 5 | `reason` | `text` | NO | `NULL` |  |  |  |
| 6 | `status` | `varchar(30)` | NO | `PENDING` |  |  |  |
| 7 | `requested_at` | `timestamp` | NO | `CURRENT_TIMESTAMP` |  | DEFAULT_GENERATED |  |
| 8 | `reviewed_at` | `timestamp` | YES | `NULL` |  |  |  |
| 9 | `created_at` | `timestamp` | NO | `CURRENT_TIMESTAMP` |  | DEFAULT_GENERATED |  |
| 10 | `updated_at` | `timestamp` | NO | `CURRENT_TIMESTAMP` |  | DEFAULT_GENERATED on update CURRENT_TIMESTAMP |  |
| 11 | `pending_participant_id` | `bigint unsigned` | YES | `NULL` | UNI | VIRTUAL GENERATED | Unique Key | GENERATED AS: `(case when (`status` = _utf8mb4\'PENDING\') then `room_participant_id` else NULL end)` |

### Bảng: `participant_status_histories`
- **Engine:** InnoDB | **Collation:** utf8mb4_unicode_ci

| STT | Tên cột | Kiểu dữ liệu | Nullable | Mặc định | Khóa / Chỉ mục | Thuộc tính Extra | Ghi chú kỹ thuật |
|:---:|---|---|:---:|:---:|:---:|---|---|
| 1 | `id` | `bigint unsigned` | NO | `NULL` | PRI | auto_increment | Primary Key Clustered |
| 2 | `room_participant_id` | `bigint unsigned` | NO | `NULL` | MUL |  | Secondary / FK Index |
| 3 | `from_status` | `varchar(40)` | NO | `NULL` |  |  |  |
| 4 | `to_status` | `varchar(40)` | NO | `NULL` |  |  |  |
| 5 | `reason` | `text` | YES | `NULL` |  |  |  |
| 6 | `created_at` | `timestamp` | NO | `CURRENT_TIMESTAMP` |  | DEFAULT_GENERATED |  |

### Bảng: `replacement_windows`
- **Engine:** InnoDB | **Collation:** utf8mb4_unicode_ci

| STT | Tên cột | Kiểu dữ liệu | Nullable | Mặc định | Khóa / Chỉ mục | Thuộc tính Extra | Ghi chú kỹ thuật |
|:---:|---|---|:---:|:---:|:---:|---|---|
| 1 | `id` | `bigint unsigned` | NO | `NULL` | PRI | auto_increment | Primary Key Clustered |
| 2 | `game_room_id` | `bigint unsigned` | NO | `NULL` | MUL |  | Secondary / FK Index |
| 3 | `original_participant_id` | `bigint unsigned` | NO | `NULL` | MUL |  | Secondary / FK Index |
| 4 | `replacement_participant_id` | `bigint unsigned` | YES | `NULL` | MUL |  | Secondary / FK Index |
| 5 | `status` | `varchar(30)` | NO | `OPEN` |  |  |  |
| 6 | `expires_at` | `timestamp` | NO | `NULL` |  |  |  |
| 7 | `created_at` | `timestamp` | NO | `CURRENT_TIMESTAMP` |  | DEFAULT_GENERATED |  |
| 8 | `updated_at` | `timestamp` | NO | `CURRENT_TIMESTAMP` |  | DEFAULT_GENERATED on update CURRENT_TIMESTAMP |  |

### Bảng: `room_participants`
- **Engine:** InnoDB | **Collation:** utf8mb4_unicode_ci

| STT | Tên cột | Kiểu dữ liệu | Nullable | Mặc định | Khóa / Chỉ mục | Thuộc tính Extra | Ghi chú kỹ thuật |
|:---:|---|---|:---:|:---:|:---:|---|---|
| 1 | `id` | `bigint unsigned` | NO | `NULL` | PRI | auto_increment | Primary Key Clustered |
| 2 | `game_room_id` | `bigint unsigned` | NO | `NULL` | MUL |  | Secondary / FK Index |
| 3 | `user_id` | `bigint unsigned` | NO | `NULL` | MUL |  | Secondary / FK Index |
| 4 | `role_in_room` | `varchar(20)` | NO | `PLAYER` |  |  |  |
| 5 | `status` | `varchar(40)` | NO | `JOINED` |  |  |  |
| 6 | `deposit_amount` | `bigint unsigned` | NO | `50000` |  |  |  |
| 7 | `remaining_amount` | `bigint unsigned` | NO | `0` |  |  |  |
| 8 | `deposit_deadline` | `timestamp` | YES | `NULL` |  |  |  |
| 9 | `joined_at` | `timestamp` | NO | `CURRENT_TIMESTAMP` |  | DEFAULT_GENERATED |  |
| 10 | `left_at` | `timestamp` | YES | `NULL` |  |  |  |
| 11 | `cancel_reason` | `text` | YES | `NULL` |  |  |  |
| 12 | `version` | `bigint unsigned` | NO | `0` |  |  |  |
| 13 | `created_at` | `timestamp` | NO | `CURRENT_TIMESTAMP` |  | DEFAULT_GENERATED |  |
| 14 | `updated_at` | `timestamp` | NO | `CURRENT_TIMESTAMP` |  | DEFAULT_GENERATED on update CURRENT_TIMESTAMP |  |
| 15 | `active_user_id` | `bigint unsigned` | YES | `NULL` |  | VIRTUAL GENERATED |  | GENERATED AS: `(case when (`status` in (_utf8mb4\'JOINED\',_utf8mb4\'DEPOSIT_PENDING\',_utf8mb4\'DEPOSITED\',_utf8mb4\'REMAINING_PENDING\',_utf8mb4\'FULLY_PAID\',_utf8mb4\'LEAVE_REQUESTED\')) then `user_id` else NULL end)` |

---

## BOUNDED CONTEXT: 06. Venue & Scheduling

### Bảng: `booking_price_snapshots`
- **Engine:** InnoDB | **Collation:** utf8mb4_unicode_ci

| STT | Tên cột | Kiểu dữ liệu | Nullable | Mặc định | Khóa / Chỉ mục | Thuộc tính Extra | Ghi chú kỹ thuật |
|:---:|---|---|:---:|:---:|:---:|---|---|
| 1 | `id` | `bigint unsigned` | NO | `NULL` | PRI | auto_increment | Primary Key Clustered |
| 2 | `session_id` | `bigint unsigned` | NO | `NULL` | UNI |  | Unique Key |
| 3 | `game_price_id` | `bigint unsigned` | YES | `NULL` | MUL |  | Secondary / FK Index |
| 4 | `promotion_id` | `bigint unsigned` | YES | `NULL` | MUL |  | Secondary / FK Index |
| 5 | `original_base_price` | `bigint unsigned` | NO | `NULL` |  |  |  |
| 6 | `discount_amount` | `bigint unsigned` | NO | `0` |  |  |  |
| 7 | `final_price` | `bigint unsigned` | NO | `NULL` |  |  |  |
| 8 | `required_deposit_per_player` | `bigint unsigned` | NO | `50000` |  |  |  |
| 9 | `total_deposit_expected` | `bigint unsigned` | NO | `NULL` |  |  |  |
| 10 | `remaining_balance_expected` | `bigint unsigned` | NO | `NULL` |  |  |  |
| 11 | `snapshot_at` | `timestamp` | NO | `CURRENT_TIMESTAMP` |  | DEFAULT_GENERATED |  |
| 12 | `created_at` | `timestamp` | NO | `CURRENT_TIMESTAMP` |  | DEFAULT_GENERATED |  |

### Bảng: `physical_room_reservations`
- **Engine:** InnoDB | **Collation:** utf8mb4_unicode_ci

| STT | Tên cột | Kiểu dữ liệu | Nullable | Mặc định | Khóa / Chỉ mục | Thuộc tính Extra | Ghi chú kỹ thuật |
|:---:|---|---|:---:|:---:|:---:|---|---|
| 1 | `id` | `bigint unsigned` | NO | `NULL` | PRI | auto_increment | Primary Key Clustered |
| 2 | `physical_room_id` | `bigint unsigned` | NO | `NULL` | MUL |  | Secondary / FK Index |
| 3 | `session_id` | `bigint unsigned` | NO | `NULL` | MUL |  | Secondary / FK Index |
| 4 | `reservation_date` | `date` | NO | `NULL` |  |  |  |
| 5 | `start_time` | `time` | NO | `NULL` |  |  |  |
| 6 | `end_time` | `time` | NO | `NULL` |  |  |  |
| 7 | `status` | `varchar(30)` | NO | `RESERVED` |  |  |  |
| 8 | `created_at` | `timestamp` | NO | `CURRENT_TIMESTAMP` |  | DEFAULT_GENERATED |  |
| 9 | `updated_at` | `timestamp` | NO | `CURRENT_TIMESTAMP` |  | DEFAULT_GENERATED on update CURRENT_TIMESTAMP |  |
| 10 | `active_session_id` | `bigint unsigned` | YES | `NULL` | UNI | VIRTUAL GENERATED | Unique Key | GENERATED AS: `(case when (`status` in (_utf8mb4\'RESERVED\',_utf8mb4\'CONFIRMED\')) then `session_id` else NULL end)` |

### Bảng: `physical_rooms`
- **Engine:** InnoDB | **Collation:** utf8mb4_unicode_ci

| STT | Tên cột | Kiểu dữ liệu | Nullable | Mặc định | Khóa / Chỉ mục | Thuộc tính Extra | Ghi chú kỹ thuật |
|:---:|---|---|:---:|:---:|:---:|---|---|
| 1 | `id` | `bigint unsigned` | NO | `NULL` | PRI | auto_increment | Primary Key Clustered |
| 2 | `code` | `varchar(32)` | NO | `NULL` | UNI |  | Unique Key |
| 3 | `name` | `varchar(100)` | NO | `NULL` |  |  |  |
| 4 | `capacity` | `int unsigned` | NO | `8` |  |  |  |
| 5 | `status` | `varchar(30)` | NO | `AVAILABLE` |  |  |  |
| 6 | `description` | `text` | YES | `NULL` |  |  |  |
| 7 | `created_at` | `timestamp` | NO | `CURRENT_TIMESTAMP` |  | DEFAULT_GENERATED |  |
| 8 | `updated_at` | `timestamp` | NO | `CURRENT_TIMESTAMP` |  | DEFAULT_GENERATED on update CURRENT_TIMESTAMP |  |

### Bảng: `schedule_proposals`
- **Engine:** InnoDB | **Collation:** utf8mb4_unicode_ci

| STT | Tên cột | Kiểu dữ liệu | Nullable | Mặc định | Khóa / Chỉ mục | Thuộc tính Extra | Ghi chú kỹ thuật |
|:---:|---|---|:---:|:---:|:---:|---|---|
| 1 | `id` | `bigint unsigned` | NO | `NULL` | PRI | auto_increment | Primary Key Clustered |
| 2 | `game_room_id` | `bigint unsigned` | NO | `NULL` | MUL |  | Secondary / FK Index |
| 3 | `proposed_by_user_id` | `bigint unsigned` | NO | `NULL` | MUL |  | Secondary / FK Index |
| 4 | `proposed_date` | `date` | NO | `NULL` |  |  |  |
| 5 | `start_time` | `time` | NO | `NULL` |  |  |  |
| 6 | `end_time` | `time` | NO | `NULL` |  |  |  |
| 7 | `status` | `varchar(30)` | NO | `PROPOSED` |  |  |  |
| 8 | `deadline` | `timestamp` | NO | `NULL` |  |  |  |
| 9 | `created_at` | `timestamp` | NO | `CURRENT_TIMESTAMP` |  | DEFAULT_GENERATED |  |
| 10 | `updated_at` | `timestamp` | NO | `CURRENT_TIMESTAMP` |  | DEFAULT_GENERATED on update CURRENT_TIMESTAMP |  |
| 11 | `proposed_game_room_id` | `bigint unsigned` | YES | `NULL` | UNI | VIRTUAL GENERATED | Unique Key | GENERATED AS: `(case when (`status` = _utf8mb4\'PROPOSED\') then `game_room_id` else NULL end)` |

### Bảng: `schedule_responses`
- **Engine:** InnoDB | **Collation:** utf8mb4_unicode_ci

| STT | Tên cột | Kiểu dữ liệu | Nullable | Mặc định | Khóa / Chỉ mục | Thuộc tính Extra | Ghi chú kỹ thuật |
|:---:|---|---|:---:|:---:|:---:|---|---|
| 1 | `id` | `bigint unsigned` | NO | `NULL` | PRI | auto_increment | Primary Key Clustered |
| 2 | `schedule_proposal_id` | `bigint unsigned` | NO | `NULL` | MUL |  | Secondary / FK Index |
| 3 | `room_participant_id` | `bigint unsigned` | NO | `NULL` | MUL |  | Secondary / FK Index |
| 4 | `response` | `varchar(20)` | NO | `NULL` |  |  |  |
| 5 | `note` | `varchar(255)` | YES | `NULL` |  |  |  |
| 6 | `responded_at` | `timestamp` | NO | `CURRENT_TIMESTAMP` |  | DEFAULT_GENERATED |  |
| 7 | `created_at` | `timestamp` | NO | `CURRENT_TIMESTAMP` |  | DEFAULT_GENERATED |  |

### Bảng: `sessions`
- **Engine:** InnoDB | **Collation:** utf8mb4_unicode_ci

| STT | Tên cột | Kiểu dữ liệu | Nullable | Mặc định | Khóa / Chỉ mục | Thuộc tính Extra | Ghi chú kỹ thuật |
|:---:|---|---|:---:|:---:|:---:|---|---|
| 1 | `id` | `bigint unsigned` | NO | `NULL` | PRI | auto_increment | Primary Key Clustered |
| 2 | `game_room_id` | `bigint unsigned` | NO | `NULL` | MUL |  | Secondary / FK Index |
| 3 | `dm_assignment_id` | `bigint unsigned` | YES | `NULL` | MUL |  | Secondary / FK Index |
| 4 | `session_code` | `varchar(32)` | NO | `NULL` | UNI |  | Unique Key |
| 5 | `scheduled_date` | `date` | NO | `NULL` | MUL |  | Secondary / FK Index |
| 6 | `start_time` | `time` | NO | `NULL` |  |  |  |
| 7 | `end_time` | `time` | NO | `NULL` |  |  |  |
| 8 | `actual_start_time` | `timestamp` | YES | `NULL` |  |  |  |
| 9 | `actual_end_time` | `timestamp` | YES | `NULL` |  |  |  |
| 10 | `status` | `varchar(40)` | NO | `SCHEDULED` |  |  |  |
| 11 | `cancellation_reason` | `text` | YES | `NULL` |  |  |  |
| 12 | `version` | `bigint unsigned` | NO | `0` |  |  |  |
| 13 | `created_at` | `timestamp` | NO | `CURRENT_TIMESTAMP` |  | DEFAULT_GENERATED |  |
| 14 | `updated_at` | `timestamp` | NO | `CURRENT_TIMESTAMP` |  | DEFAULT_GENERATED on update CURRENT_TIMESTAMP |  |

---

## BOUNDED CONTEXT: 07. Payment & Refund

### Bảng: `payment_attempts`
- **Engine:** InnoDB | **Collation:** utf8mb4_unicode_ci

| STT | Tên cột | Kiểu dữ liệu | Nullable | Mặc định | Khóa / Chỉ mục | Thuộc tính Extra | Ghi chú kỹ thuật |
|:---:|---|---|:---:|:---:|:---:|---|---|
| 1 | `id` | `bigint unsigned` | NO | `NULL` | PRI | auto_increment | Primary Key Clustered |
| 2 | `player_payment_id` | `bigint unsigned` | NO | `NULL` | MUL |  | Secondary / FK Index |
| 3 | `attempt_number` | `int unsigned` | NO | `1` |  |  |  |
| 4 | `idempotency_key` | `varchar(64)` | NO | `NULL` | UNI |  | Unique Key |
| 5 | `payment_method` | `varchar(30)` | NO | `SIMULATED` |  |  |  |
| 6 | `amount` | `bigint unsigned` | NO | `NULL` |  |  |  |
| 7 | `status` | `varchar(30)` | NO | `INITIATED` |  |  |  |
| 8 | `gateway_request` | `json` | YES | `NULL` |  |  |  |
| 9 | `gateway_response` | `json` | YES | `NULL` |  |  |  |
| 10 | `created_at` | `timestamp` | NO | `CURRENT_TIMESTAMP` |  | DEFAULT_GENERATED |  |
| 11 | `updated_at` | `timestamp` | NO | `CURRENT_TIMESTAMP` |  | DEFAULT_GENERATED on update CURRENT_TIMESTAMP |  |

### Bảng: `payment_transactions`
- **Engine:** InnoDB | **Collation:** utf8mb4_unicode_ci

| STT | Tên cột | Kiểu dữ liệu | Nullable | Mặc định | Khóa / Chỉ mục | Thuộc tính Extra | Ghi chú kỹ thuật |
|:---:|---|---|:---:|:---:|:---:|---|---|
| 1 | `id` | `bigint unsigned` | NO | `NULL` | PRI | auto_increment | Primary Key Clustered |
| 2 | `payment_attempt_id` | `bigint unsigned` | NO | `NULL` | UNI |  | Unique Key |
| 3 | `transaction_reference` | `varchar(64)` | NO | `NULL` | UNI |  | Unique Key |
| 4 | `amount` | `bigint unsigned` | NO | `NULL` |  |  |  |
| 5 | `currency` | `varchar(10)` | NO | `VND` |  |  |  |
| 6 | `transacted_at` | `timestamp` | NO | `CURRENT_TIMESTAMP` |  | DEFAULT_GENERATED |  |
| 7 | `metadata` | `json` | YES | `NULL` |  |  |  |
| 8 | `created_at` | `timestamp` | NO | `CURRENT_TIMESTAMP` |  | DEFAULT_GENERATED |  |

### Bảng: `player_payments`
- **Engine:** InnoDB | **Collation:** utf8mb4_unicode_ci

| STT | Tên cột | Kiểu dữ liệu | Nullable | Mặc định | Khóa / Chỉ mục | Thuộc tính Extra | Ghi chú kỹ thuật |
|:---:|---|---|:---:|:---:|:---:|---|---|
| 1 | `id` | `bigint unsigned` | NO | `NULL` | PRI | auto_increment | Primary Key Clustered |
| 2 | `room_participant_id` | `bigint unsigned` | NO | `NULL` | MUL |  | Secondary / FK Index |
| 3 | `session_id` | `bigint unsigned` | NO | `NULL` | MUL |  | Secondary / FK Index |
| 4 | `payment_type` | `varchar(30)` | NO | `NULL` |  |  |  |
| 5 | `amount_due` | `bigint unsigned` | NO | `NULL` |  |  |  |
| 6 | `amount_paid` | `bigint unsigned` | NO | `0` |  |  |  |
| 7 | `status` | `varchar(30)` | NO | `PENDING` |  |  |  |
| 8 | `deadline` | `timestamp` | YES | `NULL` |  |  |  |
| 9 | `created_at` | `timestamp` | NO | `CURRENT_TIMESTAMP` |  | DEFAULT_GENERATED |  |
| 10 | `updated_at` | `timestamp` | NO | `CURRENT_TIMESTAMP` |  | DEFAULT_GENERATED on update CURRENT_TIMESTAMP |  |

### Bảng: `refund_attempts`
- **Engine:** InnoDB | **Collation:** utf8mb4_unicode_ci

| STT | Tên cột | Kiểu dữ liệu | Nullable | Mặc định | Khóa / Chỉ mục | Thuộc tính Extra | Ghi chú kỹ thuật |
|:---:|---|---|:---:|:---:|:---:|---|---|
| 1 | `id` | `bigint unsigned` | NO | `NULL` | PRI | auto_increment | Primary Key Clustered |
| 2 | `refund_id` | `bigint unsigned` | NO | `NULL` | MUL |  | Secondary / FK Index |
| 3 | `attempt_number` | `int unsigned` | NO | `1` |  |  |  |
| 4 | `idempotency_key` | `varchar(64)` | NO | `NULL` | UNI |  | Unique Key |
| 5 | `amount` | `bigint unsigned` | NO | `NULL` |  |  |  |
| 6 | `status` | `varchar(30)` | NO | `INITIATED` |  |  |  |
| 7 | `error_message` | `text` | YES | `NULL` |  |  |  |
| 8 | `executed_at` | `timestamp` | YES | `NULL` |  |  |  |
| 9 | `created_at` | `timestamp` | NO | `CURRENT_TIMESTAMP` |  | DEFAULT_GENERATED |  |

### Bảng: `refunds`
- **Engine:** InnoDB | **Collation:** utf8mb4_unicode_ci

| STT | Tên cột | Kiểu dữ liệu | Nullable | Mặc định | Khóa / Chỉ mục | Thuộc tính Extra | Ghi chú kỹ thuật |
|:---:|---|---|:---:|:---:|:---:|---|---|
| 1 | `id` | `bigint unsigned` | NO | `NULL` | PRI | auto_increment | Primary Key Clustered |
| 2 | `player_payment_id` | `bigint unsigned` | NO | `NULL` | MUL |  | Secondary / FK Index |
| 3 | `room_participant_id` | `bigint unsigned` | NO | `NULL` | MUL |  | Secondary / FK Index |
| 4 | `processed_by_user_id` | `bigint unsigned` | YES | `NULL` | MUL |  | Secondary / FK Index |
| 5 | `reason` | `text` | NO | `NULL` |  |  |  |
| 6 | `refund_type` | `varchar(30)` | NO | `FULL` |  |  |  |
| 7 | `refund_amount` | `bigint unsigned` | NO | `NULL` |  |  |  |
| 8 | `status` | `varchar(30)` | NO | `PENDING` |  |  |  |
| 9 | `processed_at` | `timestamp` | YES | `NULL` |  |  |  |
| 10 | `created_at` | `timestamp` | NO | `CURRENT_TIMESTAMP` |  | DEFAULT_GENERATED |  |
| 11 | `updated_at` | `timestamp` | NO | `CURRENT_TIMESTAMP` |  | DEFAULT_GENERATED on update CURRENT_TIMESTAMP |  |

---

## BOUNDED CONTEXT: 08. Notification & Audit

### Bảng: `audit_logs`
- **Engine:** InnoDB | **Collation:** utf8mb4_unicode_ci

| STT | Tên cột | Kiểu dữ liệu | Nullable | Mặc định | Khóa / Chỉ mục | Thuộc tính Extra | Ghi chú kỹ thuật |
|:---:|---|---|:---:|:---:|:---:|---|---|
| 1 | `id` | `bigint unsigned` | NO | `NULL` | PRI | auto_increment | Primary Key Clustered |
| 2 | `user_id` | `bigint unsigned` | YES | `NULL` | MUL |  | Secondary / FK Index |
| 3 | `action` | `varchar(50)` | NO | `NULL` |  |  |  |
| 4 | `entity_type` | `varchar(50)` | NO | `NULL` | MUL |  | Secondary / FK Index |
| 5 | `entity_id` | `bigint unsigned` | NO | `NULL` |  |  |  |
| 6 | `old_values` | `json` | YES | `NULL` |  |  |  |
| 7 | `new_values` | `json` | YES | `NULL` |  |  |  |
| 8 | `ip_address` | `varchar(45)` | YES | `NULL` |  |  |  |
| 9 | `user_agent` | `varchar(255)` | YES | `NULL` |  |  |  |
| 10 | `created_at` | `timestamp` | NO | `CURRENT_TIMESTAMP` |  | DEFAULT_GENERATED |  |

### Bảng: `notification_recipient_states`
- **Engine:** InnoDB | **Collation:** utf8mb4_unicode_ci

| STT | Tên cột | Kiểu dữ liệu | Nullable | Mặc định | Khóa / Chỉ mục | Thuộc tính Extra | Ghi chú kỹ thuật |
|:---:|---|---|:---:|:---:|:---:|---|---|
| 1 | `id` | `bigint unsigned` | NO | `NULL` | PRI | auto_increment | Primary Key Clustered |
| 2 | `notification_id` | `bigint unsigned` | NO | `NULL` | MUL |  | Secondary / FK Index |
| 3 | `user_id` | `bigint unsigned` | NO | `NULL` | MUL |  | Secondary / FK Index |
| 4 | `is_read` | `tinyint(1)` | NO | `0` |  |  |  |
| 5 | `read_at` | `timestamp` | YES | `NULL` |  |  |  |
| 6 | `created_at` | `timestamp` | NO | `CURRENT_TIMESTAMP` |  | DEFAULT_GENERATED |  |
| 7 | `updated_at` | `timestamp` | NO | `CURRENT_TIMESTAMP` |  | DEFAULT_GENERATED on update CURRENT_TIMESTAMP |  |

### Bảng: `notifications`
- **Engine:** InnoDB | **Collation:** utf8mb4_unicode_ci

| STT | Tên cột | Kiểu dữ liệu | Nullable | Mặc định | Khóa / Chỉ mục | Thuộc tính Extra | Ghi chú kỹ thuật |
|:---:|---|---|:---:|:---:|:---:|---|---|
| 1 | `id` | `bigint unsigned` | NO | `NULL` | PRI | auto_increment | Primary Key Clustered |
| 2 | `sender_id` | `bigint unsigned` | YES | `NULL` | MUL |  | Secondary / FK Index |
| 3 | `title` | `varchar(191)` | NO | `NULL` |  |  |  |
| 4 | `message` | `text` | NO | `NULL` |  |  |  |
| 5 | `notification_type` | `varchar(40)` | NO | `NULL` |  |  |  |
| 6 | `payload` | `json` | YES | `NULL` |  |  |  |
| 7 | `created_at` | `timestamp` | NO | `CURRENT_TIMESTAMP` | MUL | DEFAULT_GENERATED | Secondary / FK Index |

---
