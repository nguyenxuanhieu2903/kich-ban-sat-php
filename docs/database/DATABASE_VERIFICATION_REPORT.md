# BÁO CÁO THẨM ĐỊNH CƠ SỞ DỮ LIỆU CANONICAL (21 BẢNG)
## Dự án: Website Booking – Kịch Bản Sát V16
## Môi trường: Windows Native | MySQL 8.0.44 | PHP 8.4.27 | Phinx 0.16.12

---

## 1. Tóm tắt kết quả thẩm định

| Hạng mục kiểm tra | Tiêu chuẩn mục tiêu | Hiện trạng thực tế | Kết luận |
| :--- | :--- | :--- | :---: |
| **Số lượng bảng nghiệp vụ** | Đúng 21 bảng (Tài liệu B3) | 21 bảng nghiệp vụ (+ 1 `phinxlog`) | **ĐẠT** |
| **Database Engine** | InnoDB 100% | InnoDB cho toàn bộ 21 bảng | **ĐẠT** |
| **Bảng mã (Charset/Collation)** | utf8mb4 / utf8mb4_unicode_ci | utf8mb4_unicode_ci toàn bộ | **ĐẠT** |
| **Khóa chính (Primary Keys)** | `BIGINT UNSIGNED AUTO_INCREMENT` | 21/21 bảng tuân thủ | **ĐẠT** |
| **Khóa ngoại (Foreign Keys)** | Khóa ngoại có hành vi cascade/restrict | 24 quan hệ ràng buộc toàn vẹn | **ĐẠT** |
| **Kiểm tra miền giá trị (CHECK)** | Giá trị dương, thời gian hợp lệ | 5 ràng buộc CHECK hoạt động chuẩn | **ĐẠT** |
| **Khóa ảo duy nhất (VIRTUAL + UNIQUE)** | Ngăn chặn trùng lặp có điều kiện | 3 cột ảo tính toán + chỉ mục duy nhất | **ĐẠT** |
| **Chống lặp giao dịch (Idempotency)** | `idempotency_key` duy nhất | Áp dụng trên `payment_transactions` & `refunds` | **ĐẠT** |
| **Dữ liệu hạt giống (Seeders)** | Khởi tạo vai trò và địa điểm mẫu | Lũy thừa (idempotent), không sinh trùng lặp | **ĐẠT** |
| **Kiểm thử tự động Backend (PHPUnit)** | 100% kiểm thử tích hợp & đơn vị | **83 tests, 247 assertions PASS** | **ĐẠT** |
| **Kiểm thử tự động Frontend (Node.js)** | 100% kiểm thử API client | **7 tests PASS (0 failures)** | **ĐẠT** |

---

## 2. Danh mục 21 bảng vật lý thực tế trên MySQL

Kiểm tra trực tiếp qua câu lệnh `SELECT TABLE_NAME FROM information_schema.TABLES`:

1. `booking_price_snapshots`
2. `dm_assignments`
3. `game_configurations`
4. `game_prices`
5. `game_rooms`
6. `games`
7. `notifications`
8. `participant_price_snapshots`
9. `payment_transactions`
10. `physical_rooms`
11. `player_payments`
12. `promotions`
13. `refunds`
14. `replacement_windows`
15. `roles`
16. `room_participants`
17. `schedule_proposals`
18. `schedule_responses`
19. `sessions`
20. `users`
21. `venues`

*(Bảng phụ trợ kỹ thuật: `phinxlog` lưu lịch sử thực thi migration của Phinx).*

---

## 3. Thẩm định chi tiết các cơ chế ràng buộc kỹ thuật

### 3.1. Ràng buộc Khóa ảo có điều kiện (Virtual Generated Columns + UNIQUE)

Để giải quyết bài toán nghiệp vụ phức tạp mà không làm phát sinh bảng trung gian, cơ sở dữ liệu áp dụng tính năng **Generated Columns** kết hợp **UNIQUE Index** của MySQL:

1. **Một phòng chỉ có tối đa 1 DM tích cực (`uq_active_dm_assignment`):**
   - Bảng: `dm_assignments`
   - Cột ảo: `active_assigned_room_id = CASE WHEN status IN ('ASSIGNED', 'ACCEPTED') THEN game_room_id ELSE NULL END`
   - Hiệu lực: Khi DM đang ở trạng thái `ASSIGNED` hoặc `ACCEPTED`, hệ thống chặn hoàn toàn việc phân công thêm DM thứ hai cho cùng một phòng. Khi bị hủy (`CANCELLED`), giá trị trở về `NULL`, cho phép phòng nhận DM thay thế.

2. **Một người chơi chỉ tham gia 1 lần trong 1 phòng tại một thời điểm (`uq_room_active_participant`):**
   - Bảng: `room_participants`
   - Cột ảo: `active_user_id = CASE WHEN status IN ('JOINED', 'CONFIRMED') THEN user_id ELSE NULL END`
   - Chỉ mục: `UNIQUE KEY (game_room_id, active_user_id)`
   - Hiệu lực: Chặn người chơi gửi nhiều yêu cầu tham gia cùng một phòng khi đang hoạt động. Khi rời phòng (`EXITED` hoặc `KICKED`), cột ảo trở thành `NULL`, cho phép người chơi có thể tái gia nhập nếu phòng mở tuyển lại.

3. **Một phòng chỉ có tối đa 1 đề xuất lịch đang biểu quyết (`uq_active_proposed_schedule`):**
   - Bảng: `schedule_proposals`
   - Cột ảo: `proposed_game_room_id = CASE WHEN status = 'PROPOSED' THEN game_room_id ELSE NULL END`
   - Hiệu lực: Ngăn chặn việc gửi chồng chéo nhiều lịch đề xuất cùng lúc trong cùng một phòng chơi.

### 3.2. Ràng buộc kiểm tra miền giá trị (CHECK Constraints)

- `chk_games_players`: `min_players > 0 AND max_players >= min_players` trên bảng `games`.
- `chk_game_rooms_players`: `min_players > 0 AND max_players >= min_players` trên bảng `game_rooms`.
- `chk_game_prices_times`: `time_from < time_to` trên bảng `game_prices`.
- `chk_schedule_proposals_times`: `start_time < end_time` trên bảng `schedule_proposals`.
- `chk_player_payments_amounts`: `amount_due >= 0 AND amount_paid >= 0` trên bảng `player_payments`.
- `chk_payment_transactions_amount`: `amount > 0` trên bảng `payment_transactions`.
- `chk_refunds_amount`: `refund_amount > 0` trên bảng `refunds`.
- `chk_physical_rooms_capacity`: `capacity > 0` trên bảng `physical_rooms`.

### 3.3. Ràng buộc toàn vẹn khóa ngoại (Referential Actions)

- **ON DELETE RESTRICT:** Bảo vệ dữ liệu tài chính và người dùng quan trọng (`users -> game_rooms`, `users -> dm_assignments`, `player_payments -> payment_transactions`, `player_payments -> refunds`). Không thể xóa tài khoản hay đơn thanh toán khi đã phát sinh dữ liệu liên quan.
- **ON DELETE CASCADE:** Tự động dọn dẹp các dữ liệu phụ thuộc thuần túy theo vòng đời cha (`games -> game_configurations`, `game_rooms -> room_participants`, `schedule_proposals -> schedule_responses`).
- **ON DELETE SET NULL:** Duy trì bản ghi khi người thao tác bị xóa (`users -> notifications.sender_id`, `users -> refunds.processed_by_user_id`).

### 3.4. Cơ chế Idempotency chống Duplicate Thanh toán & Hoàn tiền

- Bảng `payment_transactions`: Ràng buộc `UNIQUE KEY uq_payment_transactions_idempotency (idempotency_key)`.
- Bảng `refunds`: Ràng buộc `UNIQUE KEY uq_refunds_idempotency (idempotency_key)`.
- Kết quả kiểm chứng: Kiểm thử tích hợp `DatabaseIntegrityTest::testF01PaymentTransactionIdempotencyKey` và `testF02RefundIdempotencyKey` xác nhận việc gửi lặp request thanh toán/hoàn tiền với cùng UUID sẽ bị MySQL từ chối ngay tại tầng CSDL, bảo vệ tuyệt đối an toàn số dư tài chính.

---

## 4. Nhật ký thực thi kiểm thử (Test Execution Log)

### 4.1. PHPUnit Test Suite (Backend)

```text
PHPUnit 11.5.58 by Sebastian Bergmann and contributors.
Runtime:       PHP 8.4.27
Configuration: D:\LapTrinhWeb\Project\Booking_kich-ban-sat_PHP\backend\phpunit.xml

................................................................. 65 / 83 ( 78%)
..................                                                83 / 83 (100%)

Time: 00:03.864, Memory: 10.00 MB
OK (83 tests, 247 assertions)
```

### 4.2. Node.js Test Suite (Frontend API Client)

```text
✔ F-02: Request succeeds and cleans up timer and event listener (18.5678ms)
✔ F-02: Caller aborting in-flight request throws REQUEST_ABORTED, not REQUEST_TIMEOUT (25.8979ms)
✔ F-02: Pre-aborted caller signal immediately throws REQUEST_ABORTED without hanging (0.356ms)
✔ F-02: True timeout triggers REQUEST_TIMEOUT with accurate duration (46.1497ms)
✔ F-02: Network failure produces NETWORK_FAILURE error (0.304ms)
✔ F-02: HTTP error response creates normalized ApiError with status and details (0.2638ms)
✔ F-02: Event listener cleanup verified on caller signal (0.284ms)
ℹ tests 7
ℹ suites 0
ℹ pass 7
ℹ fail 0
```
