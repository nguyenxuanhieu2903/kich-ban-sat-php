# ĐẶC TẢ KỸ THUẬT RÀNG BUỘC VÀ CHỈ MỤC (CONSTRAINT & INDEX SPECIFICATION)
## DỰ ÁN: BOOKING-PROJECT-PHP — BASELINE SCHEMA V1

- **Tài liệu:** `CONSTRAINT_INDEX_SPECIFICATION.md`
- **Phiên bản Schema:** V1 (34 Tables, 48 FKs, 25 UKs, 20 CHKs, 104 Indexes)
- **Target Engine:** MySQL 8.0.x / 8.4 LTS (InnoDB Storage Engine)
- **Chuẩn mã hóa:** `utf8mb4` / `utf8mb4_unicode_ci`

---

## 1. NGUYÊN TẮC THIẾT KẾ RÀNG BUỘC (INTEGRITY CONSTRAINTS DESIGN)

### 1.1. Primary Key Strategy (Khóa chính)
- 100% các bảng sử dụng surrogate key `id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT`.
- InnoDB tổ chức dữ liệu theo Clustered Index B-Tree dựa trên Primary Key, giúp tối ưu hóa hiệu năng ghi tuần tự và truy vấn tìm kiếm theo ID với độ phức tạp $O(\log N)$.

### 1.2. Foreign Key Strategy (Khóa ngoại & Hành vi Cascade/Restrict)
1. **`ON DELETE CASCADE`**: Áp dụng cho các quan hệ phụ thuộc vòng đời chặt chẽ (Dependent Entities & Pivots):
   - `users` -> `user_profiles`, `user_roles`.
   - `games` -> `game_rules`, `game_configurations`, `game_category_assignments`.
   - `game_rooms` -> `dm_assignments`, `room_participants`, `game_room_status_histories`, `replacement_windows`, `participant_leave_requests`, `schedule_proposals`.
   - `sessions` -> `physical_room_reservations`.
   - `player_payments` -> `payment_attempts`.
   - `refunds` -> `refund_attempts`.
   - `notifications` -> `notification_recipient_states`.
2. **`ON DELETE RESTRICT`**: Áp dụng cho các quan hệ Master / Financial / Legal / Accounting nhằm ngăn chặn việc xóa bản ghi làm mất dấu vết:
   - `roles` (không xóa vai trò đang có user).
   - `categories` (không xóa thể loại đang có game).
   - `games` (không xóa game đang có phòng hoặc bảng giá).
   - `users` (không xóa user đang là creator phòng, participant, payer).
   - `payment_attempts` (không xóa lần thử khi đã có `payment_transactions` thành công).
   - `physical_rooms` (không xóa phòng vật lý khi đang có lịch giữ chỗ).
   - `sessions` (không xóa session khi đã phát sinh công nợ hoặc snapshot giá).
3. **`ON DELETE SET NULL`**: Áp dụng cho các cột Audit / Actor / Reviewer tùy chọn:
   - `audit_logs.user_id`.
   - `notifications.sender_id`.
   - `game_room_status_histories.changed_by_user_id`.
   - `participant_leave_requests.reviewed_by_user_id`.
   - `refunds.processed_by_user_id`.
   - `booking_price_snapshots.game_price_id`, `promotion_id`.
   - `sessions.dm_assignment_id`.

---

## 2. VIRTUAL GENERATED COLUMNS & CONDITIONAL UNIQUE PATTERN

MySQL 8 không hỗ trợ cú pháp Partial Index dạng PostgreSQL (`CREATE UNIQUE INDEX ... WHERE status = 'ACTIVE'`). Hệ thống V1 áp dụng giải pháp kiến trúc: **Virtual Generated Columns kết hợp với Unique Key**.

Quy tắc: Cột sinh trả về `ID` nếu trạng thái thuộc nhóm Active, và trả về `NULL` nếu thuộc nhóm Inactive. Do MySQL cho phép nhiều giá trị `NULL` trong B-Tree Unique Index, cơ chế này hoạt động chính xác tương đương Partial Unique Index.

### Danh mục 5 Virtual Generated Columns:

```sql
-- 1. dm_assignments: Đảm bảo 1 phòng chỉ có tối đa 1 DM active
`active_assigned_room_id` BIGINT UNSIGNED GENERATED ALWAYS AS (
    CASE WHEN `status` IN ('ASSIGNED', 'CONFIRMED') THEN `game_room_id` ELSE NULL END
) VIRTUAL,
UNIQUE KEY `uq_active_dm_assignment` (`active_assigned_room_id`)

-- 2. room_participants: Đảm bảo 1 user chỉ active trong phòng 1 lần, cho phép rejoin khi EXITED/KICKED
`active_user_id` BIGINT UNSIGNED GENERATED ALWAYS AS (
    CASE WHEN `status` IN ('JOINED', 'DEPOSIT_PENDING', 'DEPOSITED', 'REMAINING_PENDING', 'FULLY_PAID', 'LEAVE_REQUESTED') 
         THEN `user_id` ELSE NULL END
) VIRTUAL,
UNIQUE KEY `uq_room_active_participant` (`game_room_id`, `active_user_id`)

-- 3. participant_leave_requests: Chống spam nhiều yêu cầu rời phòng cùng lúc
`pending_participant_id` BIGINT UNSIGNED GENERATED ALWAYS AS (
    CASE WHEN `status` = 'PENDING' THEN `room_participant_id` ELSE NULL END
) VIRTUAL,
UNIQUE KEY `uq_pending_leave_request` (`pending_participant_id`)

-- 4. schedule_proposals: Chỉ cho phép 1 đề xuất lịch active tại một thời điểm
`proposed_game_room_id` BIGINT UNSIGNED GENERATED ALWAYS AS (
    CASE WHEN `status` = 'PROPOSED' THEN `game_room_id` ELSE NULL END
) VIRTUAL,
UNIQUE KEY `uq_active_proposed_schedule` (`proposed_game_room_id`)

-- 5. physical_room_reservations: Một session chỉ có tối đa 1 giữ chỗ phòng vật lý active
`active_session_id` BIGINT UNSIGNED GENERATED ALWAYS AS (
    CASE WHEN `status` IN ('RESERVED', 'CONFIRMED') THEN `session_id` ELSE NULL END
) VIRTUAL,
UNIQUE KEY `uq_session_active_reservation` (`active_session_id`)
```

---

## 3. CHECK CONSTRAINTS SPECIFICATION

| Constraint Name | Bảng | Biểu thức kiểm tra | Mô tả nghiệp vụ |
|---|---|---|---|
| `chk_games_players` | `games` | `min_players > 0 AND max_players >= min_players` | Giới hạn số lượng người chơi của kịch bản |
| `chk_games_duration` | `games` | `duration_minutes > 0` | Thời lượng chơi tối thiểu của kịch bản |
| `chk_game_prices_amount` | `game_prices` | `base_price_per_player >= 0` | Giá vé người chơi không âm |
| `chk_game_prices_times` | `game_prices` | `time_from < time_to` | Khung giờ áp dụng giá vé hợp lệ |
| `chk_promotions_value` | `promotions` | `discount_value >= 0` | Giá trị chiết khấu voucher không âm |
| `chk_promotions_dates` | `promotions` | `valid_from <= valid_to` | Thời hạn hiệu lực voucher hợp lệ |
| `chk_dm_rating` | `dm_profiles` | `rating_avg >= 0.00 AND rating_avg <= 5.00` | Điểm đánh giá DM từ 0 đến 5 sao |
| `chk_dm_avail_day` | `dm_availabilities` | `day_of_week BETWEEN 0 AND 6` | Thứ trong tuần (0: CN -> 6: T7) |
| `chk_dm_avail_times` | `dm_availabilities` | `start_time < end_time` | Khung giờ rảnh của DM hợp lệ |
| `chk_game_rooms_players` | `game_rooms` | `min_players > 0 AND max_players >= min_players` | Giới hạn số lượng người chơi phòng |
| `chk_physical_rooms_capacity`| `physical_rooms` | `capacity > 0` | Sức chứa phòng vật lý lớn hơn 0 |
| `chk_schedule_proposals_times`| `schedule_proposals` | `start_time < end_time` | Giờ đề xuất bắt đầu trước kết thúc |
| `chk_sessions_times` | `sessions` | `start_time < end_time` | Thời gian diễn ra phiên chơi hợp lệ |
| `chk_prr_times` | `physical_room_reservations` | `start_time < end_time` | Thời gian giữ chỗ phòng vật lý hợp lệ |
| `chk_bps_amounts` | `booking_price_snapshots` | `original >= 0 AND discount >= 0 AND final >= 0 AND deposit >= 0` | Số tiền trong snapshot giá không âm |
| `chk_player_payments_amounts`| `player_payments` | `amount_due >= 0 AND amount_paid >= 0` | Số tiền công nợ và đã trả không âm |
| `chk_payment_attempts_amount`| `payment_attempts` | `amount > 0` | Số tiền thanh toán cổng > 0 |
| `chk_payment_transactions_amount`| `payment_transactions` | `amount > 0` | Số tiền giao dịch kế toán > 0 |
| `chk_refunds_amount` | `refunds` | `refund_amount > 0` | Số tiền hoàn trả cho người chơi > 0 |
| `chk_refund_attempts_amount` | `refund_attempts` | `amount > 0` | Số tiền lệnh hoàn qua cổng > 0 |

---

## 4. QUY TẮC TRIỂN KHAI CHO APPLICATION DEVELOPERS (PHASE 4)

1. **Khóa chống đặt trùng phòng vật lý:** Phải dùng Transaction với `SELECT ... FOR UPDATE` kiểm tra điều kiện giao nhau thời gian trước khi INSERT vào `physical_room_reservations`.
2. **Khóa chống trùng lịch Quản trò:** Phải query kiểm tra lịch rảnh và lịch session hiện có của DM trước khi lưu phân công `CONFIRMED`.
3. **Bảo vệ tính Idempotency:** Mọi API thanh toán và hoàn tiền bắt buộc nhận `Idempotency-Key` từ Client và gán trực tiếp vào cột `idempotency_key` của `payment_attempts` và `refund_attempts`.
4. **Bảo toàn Transaction:** Sử dụng helper `DatabaseConnection::transaction(callable)` trong backend để tự động rollback khi gặp bất kỳ `PDOException` nào.
