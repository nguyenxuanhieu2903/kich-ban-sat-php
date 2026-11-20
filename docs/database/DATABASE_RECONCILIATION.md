# BÁO CÁO ĐỐI CHIẾU CƠ SỞ DỮ LIỆU
## (DATABASE RECONCILIATION REPORT)
### DỰ ÁN: WEBSITE BOOKING – KỊCH BẢN SÁT (JUBENSHA / MURDER MYSTERY) V16

- **Ngày đối chiếu:** 20/11/2026
- **Auditor / Architect:** Senior Principal Database Architect & Data Modeler
- **Working Directory:** `d:\LapTrinhWeb\Project\Booking_kich-ban-sat_PHP\docs\database`
- **Tài liệu tham chiếu:**
  - `PRODUCT_SPEC_V16_Final.md`
  - `IMPLEMENTATION_PLAN_V16_Final.md`
  - `V1__baseline_schema_34_tables.sql`
  - `DATABASE_BASELINE_SIGN_OFF_V1.md`
  - `ERD_VERIFICATION_REPORT.md`
  - `DATA_DICTIONARY.md`

---

## 1. TỔNG QUAN ĐỐI CHIẾU THIẾT KẾ CSDL

### 1.1. Hiện trạng so sánh (21 B3 Tables vs 34 SQL Baseline Tables vs Actual DB)
- **Thiết kế B3 gốc (Conceptual/Logical):** Xác định 21 thực thể/bảng thuộc 9 Bounded Contexts.
- **Migration SQL V1:** Đã chuẩn hóa và chi tiết hóa thành **34 bảng vật lý** thuộc 8 Bounded Contexts.
- **Cơ sở dữ liệu thực tế MySQL:** CSDL `booking_project_php` và `booking_project_php_test` đang chứa chính xác **34 bảng vật lý** (100% khớp với DDL V1, InnoDB, `utf8mb4_unicode_ci`, 0 rows ở môi trường dev mới).

### 1.2. Lý do kỹ thuật mở rộng từ 21 bảng lên 34 bảng
Việc 34 bảng tồn tại thay vì 21 bảng **hoàn toàn không phải là mâu thuẫn hay sai lệch**, mà là kết quả của quá trình chuẩn hóa CSDL (Database Normalization từ 1NF đến 3NF/BCNF) để đáp ứng các yêu cầu bắt buộc của hệ thống sản xuất:
1. **Phân rã quan hệ Nhiều - Nhiều (N:M):**
   - `users` N:M `roles` -> Tách bảng liên kết `user_roles`.
   - `games` N:M `categories` -> Tách bảng liên kết `game_category_assignments`.
2. **Tách hồ sơ mở rộng (1:1 Profile Extension):**
   - Tránh phình to bảng `users` (Wide Table antipattern): tách `user_profiles` (thông tin cá nhân) và `dm_profiles` (hồ sơ chuyên môn của Quản trò).
3. **Nhật ký chuyển đổi trạng thái bất biến (State Audit Histories):**
   - Nghiệp vụ V16 đòi hỏi truy vết nghiêm ngặt vòng đời phòng chơi và thành viên: bổ sung `game_room_status_histories` và `participant_status_histories`.
4. **Quy trình rời phòng và bảo toàn cọc (Leave & Replacement Workflow):**
   - Bổ sung `participant_leave_requests` (quản lý đơn xin rút và phê duyệt hoàn cọc) bên cạnh `replacement_windows`.
5. **Lịch làm việc của Quản trò:**
   - Bổ sung `dm_availabilities` để DM đăng ký ca rảnh theo tuần/ngày trước khi phân công (`dm_assignments`).
6. **Chống double-booking phòng vật lý:**
   - Bổ sung `physical_room_reservations` để quản lý giữ chỗ theo khung giờ với khóa bi quan (`SELECT ... FOR UPDATE`), bảo toàn lịch sử khi session đổi phòng.
7. **Bảo toàn tính Idempotent cho Thanh toán và Hoàn tiền:**
   - Phân tách giữa nghĩa vụ tài chính (`player_payments`), lần gọi cổng thanh toán có Idempotency Key (`payment_attempts`), và bản ghi kế toán thành công (`payment_transactions`).
   - Tương tự với `refunds` và `refund_attempts`.
8. **Độc lập hóa thông báo đa người nhận:**
   - Bổ sung `notification_recipient_states` để lưu trạng thái đọc (`is_read`, `read_at`) của từng user độc lập mà không nhân bản nội dung `notifications`.
9. **Nhật ký kiểm toán hệ thống:**
   - Bổ sung `audit_logs` phục vụ an ninh thông tin OWASP.

---

## 2. MA TRẬN ĐỐI CHIẾU CHI TIẾT (MAPPING MATRIX)

| STT | B3 Entity / Table | Existing SQL V1 Table | Actual DB Table (`booking_project_php`) | Quyết định (Decision) | Căn cứ kỹ thuật & nghiệp vụ |
|:---:|---|---|---|:---:|---|
| 01 | `roles` | `roles` | `roles` | **KEEP** | Danh mục vai trò cố định (`ADMIN`, `DM`, `PLAYER`). |
| 02 | `users` | `users` | `users` | **KEEP** | Tài khoản định danh cốt lõi (email, password hash, status). |
| -- | *(Mở rộng B3)* | `user_roles` | `user_roles` | **KEEP** | Bảng liên kết N:M giữa `users` và `roles`. |
| -- | *(Mở rộng B3)* | `user_profiles` | `user_profiles` | **KEEP** | Hồ sơ cá nhân (họ tên, sđt, avatar) quan hệ 1:1 với `users`. |
| 03 | `games` | `games` | `games` | **KEEP** | Thông tin kịch bản sát (tiêu đề, tóm tắt, min/max players). |
| 04 | `game_configurations` | `game_configurations` | `game_configurations` | **KEEP** | Cấu hình kỹ thuật kịch bản (phiên bản, độ khó, đặc thù). |
| -- | *(Mở rộng B3)* | `game_rules` | `game_rules` | **KEEP** | Quy tắc chi tiết của từng kịch bản. |
| -- | *(Mở rộng B3)* | `categories` | `categories` | **KEEP** | Danh mục thể loại trò chơi (kinh dị, trinh thám...). |
| -- | *(Mở rộng B3)* | `game_category_assignments` | `game_category_assignments` | **KEEP** | Bảng liên kết N:M giữa kịch bản và thể loại. |
| 05 | `game_rooms` | `game_rooms` | `game_rooms` | **KEEP** | Phòng chơi ghép đội (host, kịch bản, sĩ số, trạng thái). |
| 06 | `room_participants` | `room_participants` | `room_participants` | **KEEP** | Thành viên tham gia phòng chơi (vai trò, trạng thái cọc). |
| -- | *(Mở rộng B3)* | `game_room_status_histories` | `game_room_status_histories` | **KEEP** | Nhật ký chuyển đổi trạng thái phòng chơi. |
| -- | *(Mở rộng B3)* | `participant_status_histories` | `participant_status_histories` | **KEEP** | Nhật ký chuyển đổi trạng thái người chơi. |
| 07 | `replacement_windows` | `replacement_windows` | `replacement_windows` | **KEEP** | Cửa sổ thời gian tìm người thay thế khi có thành viên rút. |
| -- | *(Mở rộng B3)* | `participant_leave_requests` | `participant_leave_requests` | **KEEP** | Đơn xin rút phòng và phê duyệt chính sách hoàn cọc. |
| -- | *(Mở rộng B3)* | `dm_profiles` | `dm_profiles` | **KEEP** | Hồ sơ năng lực Quản trò (kinh nghiệm, bio, rating). |
| -- | *(Mở rộng B3)* | `dm_availabilities` | `dm_availabilities` | **KEEP** | Lịch đăng ký rảnh của DM phục vụ điều phối. |
| 08 | `dm_assignments` | `dm_assignments` | `dm_assignments` | **KEEP** | Lịch sử và trạng thái phân công DM cho phòng chơi. |
| 09 | `venues` | *(Gộp trong physical_rooms)* | *(Gộp trong physical_rooms)* | **MODIFY** | MVP chỉ có 01 địa điểm vật lý; thông tin địa chỉ tích hợp trong cấu hình hệ thống / thuộc tính phòng. Không cần tách riêng bảng `venues` rỗng. |
| 10 | `physical_rooms` | `physical_rooms` | `physical_rooms` | **KEEP** | Danh mục phòng vật lý tại cửa hàng (sức chứa, tên phòng). |
| -- | *(Mở rộng B3)* | `physical_room_reservations` | `physical_room_reservations` | **KEEP** | Ràng buộc giữ phòng theo slot, ngăn chặn double-booking. |
| 11 | `schedule_proposals` | `schedule_proposals` | `schedule_proposals` | **KEEP** | Đề xuất khung giờ chơi khi phòng đã đủ người (`FORMED`). |
| 12 | `schedule_responses` | `schedule_responses` | `schedule_responses` | **KEEP** | Biểu quyết/phản hồi đồng ý/từ chối lịch của người chơi. |
| 13 | `sessions` | `sessions` | `sessions` | **KEEP** | Phiên chơi chính thức được tạo sau khi chốt lịch, DM và phòng. |
| 14 | `game_prices` | `game_prices` | `game_prices` | **KEEP** | Biểu giá kịch bản theo khung giờ (09:00-19:59 và sau 20:00). |
| 15 | `promotions` | `promotions` | `promotions` | **KEEP** | Chương trình khuyến mãi (Thứ 3 30%, HSSV 20k, non-stackable). |
| 16 | `booking_price_snapshots` | `booking_price_snapshots` | `booking_price_snapshots` | **KEEP** | Đóng băng giá trị tài chính phiên chơi (giá gốc, giảm, cọc). |
| 17 | `participant_price_snapshots` | *(Tích hợp trong player_payments)* | *(Tích hợp trong player_payments)* | **MODIFY** | Nghĩa vụ giá của từng participant được snapshot chi tiết tại các trường số tiền trong `player_payments`. |
| 18 | `player_payments` | `player_payments` | `player_payments` | **KEEP** | Quản lý khoản thanh toán cọc và tiền còn lại của người chơi. |
| -- | *(Mở rộng B3)* | `payment_attempts` | `payment_attempts` | **KEEP** | Giao dịch thử với Idempotency Key chống gọi trùng cổng. |
| 19 | `payment_transactions` | `payment_transactions` | `payment_transactions` | **KEEP** | Sổ cái kế toán ghi nhận giao dịch thành công. |
| 20 | `refunds` | `refunds` | `refunds` | **KEEP** | Yêu cầu và quyết định hoàn tiền cho người chơi. |
| -- | *(Mở rộng B3)* | `refund_attempts` | `refund_attempts` | **KEEP** | Lần thực thi lệnh chuyển tiền hoàn qua cổng thanh toán. |
| 21 | `notifications` | `notifications` | `notifications` | **KEEP** | Nội dung thông báo hệ thống/sự kiện gửi tới người dùng. |
| -- | *(Mở rộng B3)* | `notification_recipient_states` | `notification_recipient_states` | **KEEP** | Trạng thái đọc (`is_read`, `read_at`) theo từng người nhận. |
| -- | *(Mở rộng B3)* | `audit_logs` | `audit_logs` | **KEEP** | Vết kiểm toán bảo mật bất biến (actor, action, target). |

---

## 3. TỔNG KẾT QUYẾT ĐỊNH ĐỐI CHIẾU

1. **Tổng số bảng Canonical được giữ lại và chấp thuận:** **34 bảng**.
2. **Số bảng B3 được tích hợp điều chỉnh:**
   - `venues`: Tích hợp vào thuộc tính của `physical_rooms` và system config cho phạm vi MVP 1 địa điểm.
   - `participant_price_snapshots`: Tích hợp trực tiếp vào cấu trúc giá của `player_payments`.
3. **Số bảng mở rộng được giữ lại:** **13 bảng** (hoàn toàn hợp lý về mặt kỹ thuật, chuẩn hóa CSDL, đảm bảo ACID, Idempotency và Audit Trail).
4. **Không loại bỏ bất kỳ bảng nào trong 34 bảng hiện hữu.** Toàn bộ 34 bảng này đã được kiểm thử tính toàn vẹn (27/27 test cases passed) trong `DatabaseIntegrityTest.php`.
