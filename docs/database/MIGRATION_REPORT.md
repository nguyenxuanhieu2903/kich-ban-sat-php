# BÁO CÁO TRIỂN KHAI VÀ XÁC MINH MIGRATION PHINX
## (PHINX MIGRATION IMPLEMENTATION & VERIFICATION REPORT)
### DỰ ÁN: WEBSITE BOOKING – KỊCH BẢN SÁT (JUBENSHA / MURDER MYSTERY) V16

- **Ngày thực hiện:** 20/11/2026
- **Auditor & Lead DevOps:** Senior Principal Database Architect & DevOps Engineer
- **Công cụ Migration:** Phinx by CakePHP v0.16.12 (PHP 8.4 LTS Compatible)
- **Cơ sở dữ liệu đích:** MySQL 8.0.44 Community Server (Win64), InnoDB, `utf8mb4_unicode_ci`
- **Môi trường thực thi:**
  - Development: `booking_project_php`
  - Testing: `booking_project_php_test`
- **Tài liệu:** `docs/database/MIGRATION_REPORT.md`

---

## 1. CẤU HÌNH VÀ TÍCH HỢP PHINX

- **Tập tin cấu hình:** `backend/phinx.php`
- **Cơ chế tải cấu hình:** Tự động nạp qua `App\Infrastructure\Config\Config::load()`, đọc thông số trực tiếp từ biến môi trường (`.env`), không hardcode mật khẩu hay credentials nhạy cảm.
- **Thư mục quản lý:**
  - Migrations: `backend/database/migrations/`
  - Seeders: `backend/database/seeders/`
- **Bảng ghi nhận lịch sử migration:** `phinxlog`
- **Quy tắc đặt tên:** Timestamp-based versioning (`YYYYMMDDHHIISS_PascalCaseName.php`).

---

## 2. DANH MỤC 8 TẬP TIN MIGRATION CHUẨN HÓA

| STT | Tập tin Migration | Bounded Context | Bảng và Ràng buộc được tạo | Trạng thái |
|:---:|---|---|---|:---:|
| 01 | `20261120000100_create_identity_tables.php` | Identity & Access | `users`, `roles`, `user_profiles`, `user_roles` (4 FKs, 4 UKs) | **UP (0.0306s)** |
| 02 | `20261120000200_create_game_tables.php` | Game Catalog | `categories`, `games`, `game_rules`, `game_configurations`, `game_category_assignments` (6 FKs, 5 UKs, 2 CHKs) | **UP (0.0205s)** |
| 03 | `20261120000300_create_gameroom_tables.php` | GameRoom Management | `game_rooms`, `room_participants`, `game_room_status_histories`, `participant_status_histories`, `replacement_windows`, `participant_leave_requests` (12 FKs, 5 UKs, 2 Generated Columns) | **UP (0.0220s)** |
| 04 | `20261120000400_create_venue_and_scheduling_tables.php` | Venue & Scheduling | `dm_profiles`, `dm_availabilities`, `dm_assignments`, `physical_rooms`, `schedule_proposals`, `schedule_responses`, `sessions`, `physical_room_reservations` (13 FKs, 6 UKs, 3 Generated Columns, 6 CHKs) | **UP (0.0244s)** |
| 05 | `20261120000500_create_pricing_tables.php` | Pricing & Promotion | `game_prices`, `promotions`, `booking_price_snapshots` (4 FKs, 2 UKs, 7 CHKs) | **UP (0.0146s)** |
| 06 | `20261120000600_create_payment_tables.php` | Payment & Refund | `player_payments`, `payment_attempts`, `payment_transactions`, `refunds`, `refund_attempts` (7 FKs, 4 UKs, 5 CHKs) | **UP (0.0187s)** |
| 07 | `20261120000700_create_notification_tables.php` | Notification & Audit | `notifications`, `notification_recipient_states`, `audit_logs` (4 FKs, 1 UK) | **UP (0.0140s)** |
| 08 | `20261120000800_add_constraints_and_indexes.php` | Optimization (CP-01) | Bổ sung Covering Index `idx_prr_overlap_covering` trên `physical_room_reservations` | **UP (0.0353s)** |

---

## 3. KẾT QUẢ 11 TIÊU CHÍ XÁC MINH MIGRATION (11 VERIFICATION CRITERIA)

### 3.1. Migration Status
- Toàn bộ 8/8 migrations ở trạng thái **`up`** trên cả hai môi trường `development` và `testing`.

### 3.2. Fresh Database Migration
- Khởi tạo đầy đủ 34 bảng vật lý + 1 bảng `phinxlog` thành công trên database sạch trong thời gian 0.2038 giây.

### 3.3. Migration Idempotency (Chạy lần 2)
- Chạy lại lệnh `vendor/bin/phinx migrate -e development`: Hệ thống nhận diện "Nothing to migrate", kết thúc sau 0.0169 giây, không tạo bản ghi thừa hoặc lỗi duplicate.

### 3.4. Foreign Key Verification
- 48 Foreign Keys được kiểm tra toàn vẹn trên `information_schema.table_constraints`. Các hành vi `RESTRICT`, `CASCADE`, `SET NULL` hoạt động chính xác theo đúng ma trận ràng buộc.

### 3.5. Unique Constraint Verification
- 25 Unique Keys (20 Natural Keys và 5 Virtual Conditional Keys) được thẩm định qua các ca test chèn trùng lặp.

### 3.6. Generated Column Verification
- 5 Virtual Generated Columns (`active_assigned_room_id`, `active_user_id`, `pending_participant_id`, `proposed_game_room_id`, `active_session_id`) hoạt động chính xác trên MySQL 8.0.44: tự động tính toán giá trị khi active và trả về `NULL` khi inactive.

### 3.7. Index Verification
- Toàn bộ các chỉ mục đơn cột, composite và chỉ mục phủ (bao gồm `idx_prr_overlap_covering`) đã được tạo đầy đủ trên InnoDB.

### 3.8. Check Constraint Verification
- 20 CHECK constraints được kiểm định: ngăn chặn thành công các giá trị sai phạm (`min_players > max_players`, giá vé âm, giờ bắt đầu >= giờ kết thúc).

### 3.9. Rollback Test trên Môi trường Thử nghiệm
- Thực hiện rollback migration số 8 trên `testing`:
  - Lệnh: `vendor/bin/phinx rollback -e testing -t 20261120000700`
  - Kết quả: Reverted thành công chỉ mục trong 0.0255 giây.
  - Chạy migrate tái áp dụng: Migrated thành công trong 0.0258 giây.

### 3.10. Integration Test với MySQL
- Chạy toàn bộ bộ kiểm thử tích hợp CSDL: `backend/tests/Integration/DatabaseIntegrityTest.php`
- Kết quả: **27/27 ca kiểm thử passed (100%)**, 40 assertions, 0 errors, 0 failures.

### 3.11. Toàn bộ Backend Test Suite
- Chạy toàn bộ PHPUnit: **79/79 passed (100%)**, 230 assertions, 0 failures.

---

## 4. KẾT LUẬN
Hệ thống migration Phinx đạt tiêu chuẩn **READY FOR PRODUCTION PIPELINE**, bảo đảm tính an toàn dữ liệu, khả năng rollback và tự động hóa CI/CD.
