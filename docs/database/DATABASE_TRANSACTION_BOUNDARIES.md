# ĐẶC TẢ RANH GIỚI GIAO DỊCH VÀ KHÓA BI QUAN
## (DATABASE TRANSACTION BOUNDARIES & CONCURRENCY CONTROL)
### DỰ ÁN: WEBSITE BOOKING – KỊCH BẢN SÁT (JUBENSHA / MURDER MYSTERY) V16

- **Ngày ban hành:** 20/11/2026
- **Auditor & Architect:** Senior Principal Database Architect
- **Working Directory:** `d:\LapTrinhWeb\Project\Booking_kich-ban-sat_PHP\docs\database`
- **Tài liệu:** `docs/database/DATABASE_TRANSACTION_BOUNDARIES.md`

---

## 1. NGUYÊN TẮC QUẢN LÝ GIAO DỊCH (ACID PRINCIPLES)

1. **Isolation Level:** Sử dụng `REPEATABLE READ` (mặc định tối ưu của MySQL InnoDB).
2. **Transaction Scope:** Ranh giới giao dịch bắt buộc được khởi tạo và điều phối tại tầng **Application Service** qua `TransactionManager`, không viết logic điều phối giao dịch trong Controller hay Repository.
3. **No External Calls in DB Transaction:** Tuyệt đối không gọi HTTP API của bên thứ ba (Cổng thanh toán, SMS, Email, FCM Notification) bên trong Database Transaction. Thực hiện gọi ngoài transaction hoặc sử dụng Outbox Pattern.
4. **Pessimistic Locking (`SELECT ... FOR UPDATE`):** Áp dụng bắt buộc cho các kịch bản cạnh tranh tài nguyên hữu hạn (sức chứa phòng, lịch phòng vật lý, thanh toán cọc).

---

## 2. CHI TIẾT CÁC RANH GIỚI GIAO DỊCH CỐT LÕI (CORE TRANSACTION BOUNDARIES)

### Ranh giới 1: Tạo phòng chơi và Tự động thêm Host (GameRoom Creation)
- **Mục tiêu:** Tạo phòng chơi mới và đưa người tạo làm HOST với trạng thái `JOINED`.
- **Thực thi trong 1 Transaction:**
  1. Kiểm tra điều kiện Host không quá 3 phòng active:
     ```sql
     SELECT COUNT(1) FROM game_rooms
     WHERE creator_id = :user_id AND status IN ('FORMING', 'FORMED', 'SCHEDULE_NEGOTIATING')
     FOR UPDATE;
     ```
  2. Tạo bản ghi `game_rooms` (code, creator_id, game_id, status='FORMING', current_player_count=1).
  3. Tạo bản ghi `room_participants` (game_room_id, user_id=host_id, role_in_room='HOST', status='JOINED').
  4. Ghi `game_room_status_histories` (from='NONE', to='FORMING').
  5. Ghi `participant_status_histories` (from='NONE', to='JOINED').
  6. COMMIT.

---

### Ranh giới 2: Tham gia phòng chơi ghép đội (Join GameRoom & Capacity Check)
- **Mục tiêu:** Cho phép người chơi tham gia phòng, ngăn chặn race condition vượt quá sĩ số tối đa của kịch bản.
- **Thực thi trong 1 Transaction:**
  1. Khóa bi quan phòng chơi để chống race condition 2 request đồng thời vào slot cuối:
     ```sql
     SELECT current_player_count, max_player_count, status
     FROM game_rooms
     WHERE id = :game_room_id
     FOR UPDATE;
     ```
  2. Kiểm tra `status == 'FORMING'` và `current_player_count < max_player_count`. Nếu vi phạm -> ROLLBACK ngay.
  3. Kiểm tra người chơi chưa có mặt trong phòng:
     ```sql
     SELECT id FROM room_participants
     WHERE game_room_id = :game_room_id AND active_user_id = :user_id
     FOR UPDATE;
     ```
  4. Chèn `room_participants` (game_room_id, user_id, role_in_room='MEMBER', status='JOINED').
  5. Cập nhật `game_rooms.current_player_count = current_player_count + 1`.
  6. Nếu `current_player_count + 1 == max_player_count`:
     - Cập nhật `game_rooms.status = 'FORMED'`.
     - Ghi `game_room_status_histories` ('FORMING' -> 'FORMED').
  7. Ghi `participant_status_histories` ('NONE' -> 'JOINED').
  8. COMMIT.

---

### Ranh giới 3: Đặt giữ chỗ phòng vật lý và Chống Double-Booking (Venue Reservation)
- **Mục tiêu:** Đảm bảo không có 2 phiên chơi trùng phòng vật lý trong cùng khung giờ.
- **Thực thi trong 1 Transaction:**
  1. Mở Transaction.
  2. Khóa và kiểm tra khoảng thời gian giao thoa (Overlap Window):
     ```sql
     SELECT id FROM physical_room_reservations
     WHERE physical_room_id = :physical_room_id
       AND reservation_date = :reservation_date
       AND status IN ('RESERVED', 'CONFIRMED')
       AND (start_time < :new_end_time AND end_time > :new_start_time)
     FOR UPDATE;
     ```
  3. Nếu kết quả trả về count > 0 -> Xung đột phòng vật lý -> ROLLBACK và trả về mã lỗi `ROOM_SLOT_UNAVAILABLE`.
  4. Nếu không xung đột: Chèn bản ghi `physical_room_reservations` (physical_room_id, session_id, reservation_date, start_time, end_time, status='RESERVED').
  5. Cập nhật `sessions.status = 'SCHEDULED'`.
  6. COMMIT.

---

### Ranh giới 4: Thanh toán tiền cọc có Idempotency và Khóa Snapshot (Payment & Deposit)
- **Mục tiêu:** Ghi nhận tiền cọc 50.000 VNĐ, chống thanh toán lặp, cập nhật trạng thái người chơi.
- **Thực thi trong 1 Transaction:**
  1. Mở Transaction.
  2. Kiểm tra `payment_attempts` theo `idempotency_key` bằng `FOR UPDATE`:
     - Nếu đã tồn tại và `status = 'SUCCESS'` -> Bỏ qua, trả về kết quả cũ (Idempotent replay).
  3. Khóa bản ghi `player_payments`:
     ```sql
     SELECT id, amount_vnd, status FROM player_payments
     WHERE id = :payment_id FOR UPDATE;
     ```
  4. Cập nhật `payment_attempts.status = 'SUCCESS'`.
  5. Chèn `payment_transactions` (payment_attempt_id, transaction_reference, amount_vnd, transacted_at).
  6. Cập nhật `player_payments.status = 'PAID'`.
  7. Cập nhật `room_participants.deposit_status = 'PAID'`.
  8. Ghi `audit_logs` (action='PAYMENT_CONFIRMED', entity='player_payments', id=:payment_id).
  9. COMMIT.

---

### Ranh giới 5: Rời phòng chơi và Xét duyệt chính sách hoàn cọc 24 giờ (Leave & Refund)
- **Mục tiêu:** Xử lý yêu cầu xin rút phòng, kiểm tra quy tắc hoàn cọc 24 giờ trước giờ bắt đầu Session.
- **Thực thi trong 1 Transaction:**
  1. Mở Transaction.
  2. Khóa thông tin Session và Participant:
     ```sql
     SELECT s.scheduled_date, s.start_time, rp.deposit_status
     FROM room_participants rp
     INNER JOIN game_rooms gr ON rp.game_room_id = gr.id
     LEFT JOIN sessions s ON s.game_room_id = gr.id AND s.status = 'SCHEDULED'
     WHERE rp.id = :participant_id FOR UPDATE;
     ```
  3. Tính toán khoảng cách thời gian giữa thời điểm hiện tại (`NOW()`) và thời điểm bắt đầu phiên chơi (`TIMESTAMP(s.scheduled_date, s.start_time)`):
     - **Nếu khoảng cách >= 24 giờ:** Đủ điều kiện hoàn cọc (`eligible_for_refund = 1`). Hệ thống tự động tạo bản ghi `refunds` (player_payment_id, refund_amount_vnd=50000, status='APPROVED').
     - **Nếu khoảng cách < 24 giờ:** Không đủ điều kiện hoàn cọc tự động (`eligible_for_refund = 0`). Tiền cọc bị giữ lại theo chính sách đã thống nhất.
  4. Cập nhật `room_participants.status = 'LEFT'`.
  5. Cập nhật `game_rooms.current_player_count = current_player_count - 1`.
  6. Mở `replacement_windows` (game_room_id, original_participant_id, expires_at = NOW() + INTERVAL 12 HOUR).
  7. Cập nhật `game_rooms.status = 'SCHEDULE_NEGOTIATING'` hoặc mở lại gom người.
  8. COMMIT.
