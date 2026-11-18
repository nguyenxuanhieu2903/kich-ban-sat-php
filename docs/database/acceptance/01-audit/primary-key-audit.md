# BÁO CÁO ĐÁNH GIÁ VÀ ĐẶC TẢ KHÓA CHÍNH (PRIMARY KEY AUDIT)
## DỰ ÁN: BOOKING-PROJECT-PHP — PHASE 1: DATABASE DESIGN & ERD ACCEPTANCE

**Ngày thực hiện:** 18/11/2026  
**Auditor:** Principal Database Architect & Senior Data Modeler  
**Target Engine:** MySQL 8.4 LTS (InnoDB Storage Engine)  
**Phạm vi:** 34 Bảng — 8 Bounded Contexts

---

## 1. CHIẾN LƯỢC THIẾT KẾ KHÓA CHÍNH TRÊN MYSQL 8.4 INNODB

Trong kiến trúc lưu trữ của MySQL InnoDB:
1. **Cấu trúc Clustered Index (Chỉ mục cụm):** Toàn bộ dữ liệu của một hàng (row data) được lưu trữ trực tiếp trong các lá (leaf nodes) của cây B-Tree khóa chính. 
2. **Con trỏ chỉ mục thứ cấp (Secondary Index Pointer):** Mọi chỉ mục phụ trong InnoDB đều lưu giá trị của khóa chính làm con trỏ tới bản ghi gốc. Nếu khóa chính quá rộng (Wide / Composite key) hoặc phân tán ngẫu nhiên (như UUID v4 không tuần tự), dung lượng RAM dành cho Buffer Pool sẽ bị chiếm dụng nghiêm trọng và hiệu năng truy vấn thứ cấp sẽ giảm sút do hiện tượng Page Split và Cache Eviction.
3. **Chiến lược chuẩn hóa toàn hệ thống:**
   - **Surrogate Primary Key:** Toàn bộ 34 bảng được thống nhất sử dụng `id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` làm Khóa chính duy nhất.
   - **Bảo toàn tính duy nhất nghiệp vụ:** Sử dụng các chỉ mục `UNIQUE INDEX` (đơn lẻ hoặc tổng hợp) để thực thi tính toàn vẹn nghiệp vụ, thay vì dùng Natural Key làm Clustered Index.
   - **Xử lý bảng liên kết N:M (`user_roles`, `game_category_assignments`):** Vẫn trang bị `id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY` kèm theo ràng buộc `UNIQUE (foreign_key_1, foreign_key_2)`. Cách tiếp cận này giúp đơn giản hóa lớp truy cập dữ liệu PHP PDO, hỗ trợ thao tác Entity Identity chuẩn xác và thuận tiện cho việc tham chiếu kiểm toán trong `audit_logs`.

---

## 2. BẢNG KIỂM KÊ KHÓA CHÍNH CHO 34 BẢNG (PRIMARY KEY AUDIT MATRIX)

| STT | Tên bảng | Kiểu Khóa chính | Tên cột PK | Kiểu dữ liệu MySQL 8.4 | Ràng buộc duy nhất nghiệp vụ bổ trợ | Đánh giá kiến trúc |
|:---:|---|:---:|---|---|---|:---:|
| 01 | `users` | Surrogate | `id` | BIGINT UNSIGNED AUTO_INCREMENT | `UNIQUE(email)` | **OPTIMAL** |
| 02 | `user_profiles` | Surrogate | `id` | BIGINT UNSIGNED AUTO_INCREMENT | `UNIQUE(user_id)` (Quan hệ 1:1) | **OPTIMAL** |
| 03 | `roles` | Surrogate | `id` | BIGINT UNSIGNED AUTO_INCREMENT | `UNIQUE(name)` | **OPTIMAL** |
| 04 | `user_roles` | Surrogate | `id` | BIGINT UNSIGNED AUTO_INCREMENT | `UNIQUE(user_id, role_id)` | **OPTIMAL** |
| 05 | `games` | Surrogate | `id` | BIGINT UNSIGNED AUTO_INCREMENT | `UNIQUE(slug)` | **OPTIMAL** |
| 06 | `game_rules` | Surrogate | `id` | BIGINT UNSIGNED AUTO_INCREMENT | `INDEX(game_id, display_order)` | **OPTIMAL** |
| 07 | `game_configurations` | Surrogate | `id` | BIGINT UNSIGNED AUTO_INCREMENT | `UNIQUE(game_id, config_key)` | **OPTIMAL** |
| 08 | `categories` | Surrogate | `id` | BIGINT UNSIGNED AUTO_INCREMENT | `UNIQUE(slug)` | **OPTIMAL** |
| 09 | `game_category_assignments`| Surrogate | `id` | BIGINT UNSIGNED AUTO_INCREMENT | `UNIQUE(game_id, category_id)` | **OPTIMAL** |
| 10 | `game_rooms` | Surrogate | `id` | BIGINT UNSIGNED AUTO_INCREMENT | `UNIQUE(room_code)` | **OPTIMAL** |
| 11 | `room_participants` | Surrogate | `id` | BIGINT UNSIGNED AUTO_INCREMENT | Active Participation Invariant (xem constraint-audit) | **OPTIMAL** |
| 12 | `game_room_status_histories` | Surrogate | `id` | BIGINT UNSIGNED AUTO_INCREMENT | Append-only (không có natural key) | **OPTIMAL** |
| 13 | `participant_status_histories`| Surrogate | `id` | BIGINT UNSIGNED AUTO_INCREMENT | Append-only (không có natural key) | **OPTIMAL** |
| 14 | `replacement_windows` | Surrogate | `id` | BIGINT UNSIGNED AUTO_INCREMENT | `INDEX(game_room_id, status)` | **OPTIMAL** |
| 15 | `participant_leave_requests` | Surrogate | `id` | BIGINT UNSIGNED AUTO_INCREMENT | Single Pending Invariant | **OPTIMAL** |
| 16 | `dm_profiles` | Surrogate | `id` | BIGINT UNSIGNED AUTO_INCREMENT | `UNIQUE(user_id)` (Quan hệ 1:1) | **OPTIMAL** |
| 17 | `dm_availabilities` | Surrogate | `id` | BIGINT UNSIGNED AUTO_INCREMENT | `INDEX(dm_profile_id, day_of_week)` | **OPTIMAL** |
| 18 | `dm_assignments` | Surrogate | `id` | BIGINT UNSIGNED AUTO_INCREMENT | Single Active DM Invariant | **OPTIMAL** |
| 19 | `physical_rooms` | Surrogate | `id` | BIGINT UNSIGNED AUTO_INCREMENT | `UNIQUE(code)` | **OPTIMAL** |
| 20 | `physical_room_reservations`| Surrogate | `id` | BIGINT UNSIGNED AUTO_INCREMENT | Single Active Reservation per Session | **OPTIMAL** |
| 21 | `schedule_proposals` | Surrogate | `id` | BIGINT UNSIGNED AUTO_INCREMENT | Single Proposed Invariant | **OPTIMAL** |
| 22 | `schedule_responses` | Surrogate | `id` | BIGINT UNSIGNED AUTO_INCREMENT | `UNIQUE(schedule_proposal_id, room_participant_id)` | **OPTIMAL** |
| 23 | `sessions` | Surrogate | `id` | BIGINT UNSIGNED AUTO_INCREMENT | `UNIQUE(session_code)` | **OPTIMAL** |
| 24 | `game_prices` | Surrogate | `id` | BIGINT UNSIGNED AUTO_INCREMENT | `INDEX(game_id, day_type, time_from)` | **OPTIMAL** |
| 25 | `promotions` | Surrogate | `id` | BIGINT UNSIGNED AUTO_INCREMENT | `UNIQUE(code)` | **OPTIMAL** |
| 26 | `booking_price_snapshots` | Surrogate | `id` | BIGINT UNSIGNED AUTO_INCREMENT | `UNIQUE(session_id)` (Strict 1:1 Snapshot) | **OPTIMAL** |
| 27 | `player_payments` | Surrogate | `id` | BIGINT UNSIGNED AUTO_INCREMENT | `INDEX(room_participant_id, payment_type)` | **OPTIMAL** |
| 28 | `payment_attempts` | Surrogate | `id` | BIGINT UNSIGNED AUTO_INCREMENT | `UNIQUE(idempotency_key)` | **OPTIMAL** |
| 29 | `payment_transactions` | Surrogate | `id` | BIGINT UNSIGNED AUTO_INCREMENT | `UNIQUE(transaction_reference)` | **OPTIMAL** |
| 30 | `refunds` | Surrogate | `id` | BIGINT UNSIGNED AUTO_INCREMENT | `INDEX(player_payment_id, status)` | **OPTIMAL** |
| 31 | `refund_attempts` | Surrogate | `id` | BIGINT UNSIGNED AUTO_INCREMENT | `UNIQUE(idempotency_key)` | **OPTIMAL** |
| 32 | `notifications` | Surrogate | `id` | BIGINT UNSIGNED AUTO_INCREMENT | Broadcast entity | **OPTIMAL** |
| 33 | `notification_recipient_states`| Surrogate | `id` | BIGINT UNSIGNED AUTO_INCREMENT | `UNIQUE(notification_id, user_id)` | **OPTIMAL** |
| 34 | `audit_logs` | Surrogate | `id` | BIGINT UNSIGNED AUTO_INCREMENT | Append-only immutable log | **OPTIMAL** |

---

## 3. KẾT LUẬN NGHIỆM THU KHÓA CHÍNH (PRIMARY KEY ACCEPTANCE)

1. **Tính nhất quán (Consistency):** 34/34 bảng đều áp dụng chuẩn `id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`.
2. **Hiệu năng Clustered Index:** Đạt hiệu năng ghi tuần tự tối đa, triệt tiêu phân mảnh dữ liệu (Data Page Fragmentation) khi khối lượng giao dịch tăng cao.
3. **An toàn kiểm toán:** Đảm bảo mỗi dòng bản ghi trong hệ thống đều có định danh nguyên thủy bất biến, phục vụ cơ chế kiểm toán chi tiết của `audit_logs` mà không phụ thuộc vào các trường dữ liệu người dùng có thể thay đổi (như email, mã code).
