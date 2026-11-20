# BẢNG ĐỐI CHIẾU VÀ ÁNH XẠ CƠ SỞ DỮ LIỆU
## Chuyển đổi từ Baseline 34 bảng sang Thiết kế 21 bảng Canonical (Tài liệu B3)

---

## 1. Bối cảnh & Mục tiêu

Nhằm đáp ứng yêu cầu thu gọn cấu trúc cơ sở dữ liệu của giảng viên hướng dẫn cho đồ án **Website Booking – Kịch Bản Sát (Jubensha / Murder Mystery)**, hệ thống cơ sở dữ liệu được tái cấu trúc từ bản thử nghiệm ban đầu (34 bảng) về đúng chuẩn thiết kế **21 bảng nghiệp vụ** được định nghĩa chính thức trong tài liệu **B3 – Logical ERD & Physical Data Model**.

Quá trình tinh giản này tuân thủ nghiêm ngặt các nguyên tắc:
1. **Bảo toàn 100% nghiệp vụ:** Mọi dữ liệu về tài khoản, vai trò, kịch bản, phòng chơi, người tham gia, phân công DM, lịch chơi, đặt cọc, thanh toán và hoàn tiền đều được bảo toàn trọn vẹn.
2. **Không làm mất lịch sử tài chính:** Toàn bộ giao dịch thanh toán và hoàn tiền đều được ghi nhận qua mã định danh lũy thừa (`idempotency_key`) và sổ cái kế toán.
3. **Chuẩn hóa quan hệ & toàn vẹn:** Khóa ngoại (`FOREIGN KEY`), khóa duy nhất có điều kiện (`VIRTUAL GENERATED COLUMN + UNIQUE`), kiểm tra miền giá trị (`CHECK`) được giữ nguyên vẹn.

---

## 2. Bảng phân loại và ánh xạ chi tiết 34 bảng → 21 bảng

| STT | Bảng trong Baseline 34 bảng | Trạng thái | Bảng đích (21 bảng B3) | Cơ chế gộp / Xử lý dữ liệu |
| :---: | :--- | :---: | :--- | :--- |
| 1 | `roles` | **KEEP** | `roles` | Giữ nguyên danh mục vai trò hệ thống (`ADMIN`, `DM`, `PLAYER`). |
| 2 | `users` | **KEEP** | `users` | Giữ nguyên, mở rộng thêm các trường từ `user_profiles` và khóa ngoại `role_id`. |
| 3 | `user_roles` | **MERGE** | `users` | Gộp khóa ngoại `role_id` trực tiếp vào bảng `users`. Mỗi người dùng có một vai trò chính thức. |
| 4 | `user_profiles` | **MERGE** | `users` | Gộp các trường hồ sơ (`avatar_url`, `bio`, `student_card_verified`, `dm_experience_years`, `dm_rating_avg`) trực tiếp vào `users`. |
| 5 | `categories` | **MERGE** | `games` | Chuyển thành trường chuỗi `category VARCHAR(100)` trong bảng `games`. |
| 6 | `game_category_assignments` | **MERGE** | `games` | Loại bỏ bảng liên kết trung gian, lưu trực tiếp thể loại chính tại `games.category`. |
| 7 | `games` | **KEEP** | `games` | Giữ nguyên thông tin kịch bản (title, slug, min_players, max_players, duration, v.v.). |
| 8 | `game_rules` | **MERGE** | `games` / `game_configurations` | Tóm tắt luật chơi đưa vào `games.description` hoặc cấu hình qua `game_configurations`. |
| 9 | `game_configurations` | **KEEP** | `game_configurations` | Giữ nguyên lưu trữ tham số động kịch bản (key-value). |
| 10 | `game_rooms` | **KEEP** | `game_rooms` | Giữ nguyên thông tin phòng chơi, trạng thái vòng đời, hạn chót tạo phòng. |
| 11 | `room_participants` | **KEEP** | `room_participants` | Giữ nguyên theo dõi người tham gia, vai trò trong phòng, cọc, cột ảo `active_user_id`. |
| 12 | `game_room_status_histories` | **REMOVE** | *File Logging* | Loại bỏ bảng vật lý; lịch sử chuyển trạng thái phòng được ghi nhận vào log hệ thống (`storage/logs/`). |
| 13 | `participant_status_histories` | **REMOVE** | *File Logging* | Loại bỏ bảng vật lý; audit chuyển trạng thái người chơi chuyển sang logging ứng dụng. |
| 14 | `participant_leave_requests` | **MERGE** | `room_participants` | Gộp trường `leave_reason TEXT` và `refund_eligible BOOLEAN` trực tiếp vào `room_participants`. |
| 15 | `replacement_windows` | **KEEP** | `replacement_windows` | Giữ nguyên cơ chế tuyển người thay thế khi có thành viên xin rời phòng. |
| 16 | `dm_profiles` | **MERGE** | `users` | Hồ sơ DM (`dm_experience_years`, `dm_rating_avg`, `bio`) gộp vào bảng `users`. |
| 17 | `dm_availabilities` | **MERGE** | `schedule_proposals` | Lịch rảnh DM được phối hợp động trực tiếp thông qua đề xuất lịch chơi và phân công phòng. |
| 18 | `dm_assignments` | **KEEP** | `dm_assignments` | Giữ nguyên phân công DM, liên kết trực tiếp `dm_user_id -> users(id)`. |
| 19 | `venues` | **KEEP** | `venues` | Giữ nguyên thông tin cơ sở/chi nhánh quán chơi. |
| 20 | `physical_rooms` | **KEEP** | `physical_rooms` | Giữ nguyên thông tin phòng chơi vật lý thuộc từng chi nhánh. |
| 21 | `physical_room_reservations` | **MERGE** | `sessions` | Gộp khóa ngoại `physical_room_id` và thời gian đặt phòng trực tiếp vào `sessions`. |
| 22 | `schedule_proposals` | **KEEP** | `schedule_proposals` | Giữ nguyên cơ chế biểu quyết thời gian chơi giữa các thành viên. |
| 23 | `schedule_responses` | **KEEP** | `schedule_responses` | Giữ nguyên phản hồi / phiếu bình chọn của người chơi đối với đề xuất. |
| 24 | `sessions` | **KEEP** | `sessions` | Giữ nguyên phiên chơi thực tế, bổ sung trực tiếp `physical_room_id`. |
| 25 | `game_prices` | **KEEP** | `game_prices` | Giữ nguyên bảng giá kịch bản theo khung giờ và loại ngày (thường / cuối tuần). |
| 26 | `promotions` | **KEEP** | `promotions` | Giữ nguyên thông tin khuyến mãi, mã giảm giá, thời hạn hiệu lực. |
| 27 | `booking_price_snapshots` | **KEEP** | `booking_price_snapshots` | Giữ nguyên chụp giá tổng thể của phòng chơi tại thời điểm chốt lịch. |
| 28 | `participant_price_snapshots` | **KEEP** | `participant_price_snapshots` | Giữ nguyên chụp chi tiết số tiền nghĩa vụ của từng thành viên. |
| 29 | `player_payments` | **KEEP** | `player_payments` | Giữ nguyên nghĩa vụ tài chính của người chơi (tiền cọc, thanh toán đủ). |
| 30 | `payment_attempts` | **MERGE** | `payment_transactions` | Gộp vào sổ cái giao dịch với khóa duy nhất `idempotency_key`. |
| 31 | `payment_transactions` | **KEEP** | `payment_transactions` | Giữ nguyên sổ cái giao dịch thanh toán xác nhận thành công. |
| 32 | `refunds` | **KEEP** | `refunds` | Giữ nguyên ghi nhận yêu cầu và xử lý hoàn tiền với `idempotency_key`. |
| 33 | `refund_attempts` | **MERGE** | `refunds` | Gộp lần thử hoàn tiền vào bảng `refunds` với khóa bảo vệ `idempotency_key`. |
| 34 | `notification_recipient_states` | **MERGE** | `notifications` | Gộp trường `user_id`, `is_read`, `read_at` trực tiếp vào bảng `notifications`. |
| - | `audit_logs` | **REMOVE** | *File Logging* | Loại bỏ bảng vật lý; audit logging được thực hiện qua file log xoay vòng. |

---

## 3. Danh mục 21 bảng Canonical chính thức (Theo B3)

Danh mục 21 bảng được tổ chức theo 8 domain nghiệp vụ:

1. **Identity & Phân quyền (2 bảng):**
   - `roles`: Vai trò người dùng (`ADMIN`, `DM`, `PLAYER`).
   - `users`: Tài khoản, thông tin cá nhân, hồ sơ DM, trạng thái xác thực.

2. **Game Catalog (2 bảng):**
   - `games`: Danh mục kịch bản, thể loại, số người chơi, thời lượng.
   - `game_configurations`: Tham số cấu hình đặc thù của từng kịch bản.

3. **Game Room & Tuyển người (3 bảng):**
   - `game_rooms`: Phòng chơi, trạng thái vòng đời (`FORMING`, `ACTIVE`, `CANCELLED`).
   - `room_participants`: Thành viên trong phòng, trạng thái cọc, xin rời phòng.
   - `replacement_windows`: Cửa sổ tìm kiếm người chơi thay thế.

4. **DM Assignment (1 bảng):**
   - `dm_assignments`: Phân công DM cho phòng chơi, khóa ảo `active_assigned_room_id`.

5. **Venue & Physical Rooms (2 bảng):**
   - `venues`: Địa điểm, chi nhánh quán kịch bản sát.
   - `physical_rooms`: Phòng chơi thực tế tại quán, sức chứa tối đa.

6. **Scheduling & Sessions (3 bảng):**
   - `schedule_proposals`: Đề xuất khung giờ chơi, khóa ảo `proposed_game_room_id`.
   - `schedule_responses`: Phiếu bình chọn khung giờ của từng thành viên.
   - `sessions`: Phiên chơi đã chốt, gán phòng vật lý `physical_room_id`.

7. **Pricing & Snapshot (4 bảng):**
   - `game_prices`: Bảng giá theo ngày và khung giờ.
   - `promotions`: Mã giảm giá, điều kiện áp dụng.
   - `booking_price_snapshots`: Snapshot giá của phòng khi chốt lịch.
   - `participant_price_snapshots`: Snapshot nghĩa vụ tài chính của từng người chơi.

8. **Payment, Refund & Notification (4 bảng):**
   - `player_payments`: Khoản thanh toán người chơi cần đóng.
   - `payment_transactions`: Sổ cái thanh toán mô phỏng với `idempotency_key`.
   - `refunds`: Bản ghi hoàn tiền với `idempotency_key`.
   - `notifications`: Thông báo trực tiếp gửi tới người dùng.

**Tổng cộng: 21 bảng nghiệp vụ** (+ 1 bảng kỹ thuật `phinxlog` do công cụ quản lý).
