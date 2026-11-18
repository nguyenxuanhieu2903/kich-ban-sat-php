# BÁO CÁO ĐÁNH GIÁ RÀNG BUỘC VÀ CHỈ MỤC (CONSTRAINT & INDEX REVIEW)
## DỰ ÁN: BOOKING-PROJECT-PHP — BASELINE SCHEMA V1

- **Tài liệu kiểm toán:** `CONSTRAINT_INDEX_REVIEW.md`
- **Phiên bản Migration đối chiếu:** `V1__baseline_schema_34_tables.sql`
- **Môi trường Database:** MySQL 8.0.44 / 8.4 LTS (InnoDB Storage Engine)
- **Vai trò thực hiện:** Senior Database Architect, Database Security Engineer & Database QA Lead
- **Ngày thực hiện:** 18/11/2026
- **Trạng thái nghiệm thu:** **PASS (WITH ARCHITECTURAL APPLICATION LOCKING SPECIFICATIONS)**

---

## 1. MA TRẬN 20 CHECK CONSTRAINTS (CHECK CONSTRAINT MATRIX)

Toàn bộ 20 CHECK constraints đã được rà soát trực tiếp trong `information_schema.CHECK_CONSTRAINTS` và kiểm thử thực thi trên MySQL 8.0+:

| STT | Bảng | Tên Constraint | Biểu thức kiểm tra (Check Clause) | Business Rule được bảo vệ | Valid Case | Invalid Case (Bị chặn) | Hành vi khi gặp NULL | Đánh giá tính đầy đủ |
|:---:|---|---|---|---|---|---|---|---|
| 1 | `games` | `chk_games_players` | `((min_players > 0) and (max_players >= min_players))` | Số người chơi tối thiểu phải > 0 và số tối đa phải >= tối thiểu. | `min=4, max=6` | `min=8, max=4` hoặc `min=0` | Vi phạm nếu cột NOT NULL. | **Hoàn chỉnh** |
| 2 | `games` | `chk_games_duration` | `(duration_minutes > 0)` | Thời lượng kịch bản phải lớn hơn 0 phút. | `duration=180` | `duration=0` hoặc `-30` | Chặn do cột NOT NULL. | **Hoàn chỉnh** |
| 3 | `game_prices` | `chk_game_prices_amount` | `(base_price_per_player >= 0)` | Giá vé cơ bản cho mỗi người chơi không được âm. | `base_price=150000` | `base_price=-1000` | Chặn do NOT NULL. | **Hoàn chỉnh** |
| 4 | `game_prices` | `chk_game_prices_times` | `(time_from < time_to)` | Giờ bắt đầu áp dụng khung giá phải trước giờ kết thúc. | `08:00:00 < 17:00:00` | `18:00:00 < 14:00:00` | Chặn do NOT NULL. | **Hoàn chỉnh** |
| 5 | `promotions` | `chk_promotions_value` | `(discount_value >= 0)` | Giá trị chiết khấu (tiền mặt hoặc %) không được âm. | `discount=10.00` | `discount=-5` | Chặn do NOT NULL. | **Hoàn chỉnh** |
| 6 | `promotions` | `chk_promotions_dates` | `(valid_from <= valid_to)` | Ngày bắt đầu khuyến mãi phải trước hoặc bằng ngày kết thúc. | `2026-06-01 <= 2026-08-31` | `2026-09-01 <= 2026-08-31` | Chặn do NOT NULL. | **Hoàn chỉnh** |
| 7 | `dm_profiles` | `chk_dm_rating` | `((rating_avg >= 0.00) and (rating_avg <= 5.00))` | Điểm đánh giá trung bình của Quản trò phải nằm trong thang 0 - 5 sao. | `rating=4.85` | `rating=5.50` hoặc `-0.1` | Chặn do NOT NULL (default 5.00). | **Hoàn chỉnh** |
| 8 | `dm_availabilities` | `chk_dm_avail_day` | `(day_of_week between 0 and 6)` | Thứ trong tuần phải từ 0 (Chủ nhật) đến 6 (Thứ bảy). | `day=0` .. `6` | `day=7` hoặc `-1` | Chặn do NOT NULL. | **Hoàn chỉnh** |
| 9 | `dm_availabilities` | `chk_dm_avail_times` | `(start_time < end_time)` | Giờ rảnh của Quản trò bắt đầu phải trước giờ kết thúc. | `14:00:00 < 18:00:00` | `20:00:00 < 18:00:00` | Chặn do NOT NULL. | **Hoàn chỉnh** |
| 10 | `game_rooms` | `chk_game_rooms_players` | `((min_players > 0) and (max_players >= min_players))` | Quy định số người chơi của phòng chơi phải hợp lệ. | `min=4, max=8` | `min=0` hoặc `min > max` | Chặn do NOT NULL. | **Hoàn chỉnh** |
| 11 | `physical_rooms` | `chk_physical_rooms_capacity` | `(capacity > 0)` | Sức chứa phòng vật lý phải lớn hơn 0 người. | `capacity=10` | `capacity=0` | Chặn do NOT NULL. | **Hoàn chỉnh** |
| 12 | `schedule_proposals` | `chk_schedule_proposals_times` | `(start_time < end_time)` | Giờ đề xuất bắt đầu phải trước giờ kết thúc phiên. | `14:00:00 < 18:00:00` | `18:00:00 < 14:00:00` | Chặn do NOT NULL. | **Hoàn chỉnh** |
| 13 | `sessions` | `chk_sessions_times` | `(start_time < end_time)` | Thời gian diễn ra phiên chơi phải hợp lệ. | `14:00:00 < 18:00:00` | `22:00:00 < 20:00:00` | Chặn do NOT NULL. | **Hoàn chỉnh** |
| 14 | `physical_room_reservations` | `chk_prr_times` | `(start_time < end_time)` | Thời gian giữ chỗ phòng vật lý bắt đầu trước khi kết thúc. | `14:00:00 < 18:00:00` | `18:00:00 < 18:00:00` | Chặn do NOT NULL. | **Hoàn chỉnh** |
| 15 | `booking_price_snapshots` | `chk_bps_amounts` | `(original >= 0 and discount >= 0 and final >= 0 and deposit >= 0)` | Các giá trị tiền tệ trong snapshot tài chính không được âm. | Các khoản >= 0 | Giá < 0 | Chặn do NOT NULL. | **Hoàn chỉnh** |
| 16 | `player_payments` | `chk_player_payments_amounts` | `((amount_due >= 0) and (amount_paid >= 0))` | Số tiền phải trả và số tiền đã thanh toán không được âm. | `due=200k, paid=200k` | `paid=-50k` | Chặn do NOT NULL. | **Thiếu check paid <= due (xem mục 5.D)** |
| 17 | `payment_attempts` | `chk_payment_attempts_amount` | `(amount > 0)` | Số tiền thanh toán qua cổng phải lớn hơn 0 đồng. | `amount=200000.00` | `amount=0` hoặc `-100` | Chặn do NOT NULL. | **Hoàn chỉnh** |
| 18 | `payment_transactions` | `chk_payment_transactions_amount` | `(amount > 0)` | Giao dịch kế toán thành công số tiền phải lớn hơn 0. | `amount=200000.00` | `amount=0` | Chặn do NOT NULL. | **Hoàn chỉnh** |
| 19 | `refunds` | `chk_refunds_amount` | `(refund_amount > 0)` | Khoản tiền hoàn trả cho người chơi phải lớn hơn 0. | `refund=200000.00` | `refund=0` hoặc `-100` | Chặn do NOT NULL. | **Thiếu check tổng refund <= paid (xem mục 5.E)** |
| 20 | `refund_attempts` | `chk_refund_attempts_amount` | `(amount > 0)` | Lần thử thực thi lệnh hoàn qua cổng phải > 0. | `amount=200000.00` | `amount=0` | Chặn do NOT NULL. | **Hoàn chỉnh** |

> [!NOTE]
> Trong MySQL 8.0.16+, CHECK constraints được thực thi chuẩn xác (ENFORCED). Nếu giá trị kiểm tra trả về `UNKNOWN` (do chứa NULL), MySQL sẽ cho phép đi qua. Tuy nhiên, 100% các cột tham gia vào 20 CHECK constraints trên đều được khai báo `NOT NULL`, do đó dữ liệu được bảo vệ an toàn và không thể bị bypass qua cơ chế NULL.

---

## 2. MA TRẬN 25 UNIQUE CONSTRAINTS (UNIQUE CONSTRAINT MATRIX)

Toàn bộ 25 khai báo UNIQUE KEY đã được kiểm toán:

| STT | Bảng | Tên Unique Constraint | Cột tham gia | Loại Unique | Business Rule & Ý nghĩa thực thi | Ảnh hưởng của giá trị NULL trong MySQL |
|:---:|---|---|---|:---:|---|---|
| 1 | `users` | `uq_users_email` | `email` | Single Natural | Email người dùng là định danh đăng nhập duy nhất trong toàn hệ thống. | Cột NOT NULL nên không có NULL. |
| 2 | `roles` | `uq_roles_name` | `name` | Single Natural | Mã quyền duy nhất (`ADMIN`, `STAFF`, `DM`, `CUSTOMER`). | Cột NOT NULL. |
| 3 | `user_profiles` | `uq_user_profiles_user_id` | `user_id` | Single 1:1 | Mỗi người dùng chỉ có tối đa một hồ sơ cá nhân mở rộng. | Cột NOT NULL. |
| 4 | `user_roles` | `uq_user_roles_user_role` | `user_id`, `role_id` | Composite Pivot | Ngăn ngừa việc cấp trùng một vai trò cho cùng một tài khoản người dùng. | Cả 2 cột NOT NULL. |
| 5 | `categories` | `uq_categories_slug` | `slug` | Single Slug | Đường dẫn SEO thân thiện của danh mục kịch bản là duy nhất. | Cột NOT NULL. |
| 6 | `games` | `uq_games_slug` | `slug` | Single Slug | Đường dẫn URL của kịch bản là duy nhất trên toàn hệ thống. | Cột NOT NULL. |
| 7 | `game_configurations` | `uq_game_configurations_key` | `game_id`, `config_key` | Composite Config | Không được trùng lặp khóa cấu hình trong cùng một kịch bản. | Cả 2 cột NOT NULL. |
| 8 | `game_category_assignments` | `uq_game_category_assignments` | `game_id`, `category_id` | Composite Pivot | Không thể gán một thể loại nhiều lần cho cùng một kịch bản. | Cả 2 cột NOT NULL. |
| 9 | `promotions` | `uq_promotions_code` | `code` | Single Natural | Mã voucher khuyến mãi là duy nhất trong toàn hệ thống. | Cột NOT NULL. |
| 10 | `dm_profiles` | `uq_dm_profiles_user_id` | `user_id` | Single 1:1 | Mỗi user chỉ có thể đăng ký làm Quản trò với một hồ sơ duy nhất. | Cột NOT NULL. |
| 11 | `game_rooms` | `uq_game_rooms_code` | `room_code` | Single Code | Mã phòng chơi (VD: `KBS-ROOM-8821`) là duy nhất để mời bạn bè. | Cột NOT NULL. |
| 12 | `dm_assignments` | `uq_active_dm_assignment` | `active_assigned_room_id` | **Virtual Generated** | **CHỐNG TRÙNG DM ACTIVE TRONG PHÒNG**: Một phòng tại một thời điểm chỉ có tối đa 1 DM ở trạng thái `ASSIGNED` hoặc `CONFIRMED`. | **Cho phép nhiều NULL**. Khi DM bị REJECTED hoặc CANCELLED, cột sinh ra NULL nên phòng có thể nhận DM khác. |
| 13 | `room_participants` | `uq_room_active_participant` | `game_room_id`, `active_user_id` | **Virtual Generated Composite** | **CHỐNG THÀNH VIÊN TRÙNG LẶP ACTIVE**: Một user chỉ được ở trạng thái Active trong phòng 1 lần duy nhất. | **Cho phép nhiều NULL**. Khi user có trạng thái `EXITED` hoặc `KICKED`, `active_user_id` là NULL, cho phép user tham gia lại hợp lệ. |
| 14 | `participant_leave_requests` | `uq_pending_leave_request` | `pending_participant_id` | **Virtual Generated** | **CHỐNG SPAM YÊU CẦU RÚT LUI**: Một người chơi chỉ có tối đa 1 yêu cầu rời phòng ở trạng thái `PENDING`. | **Cho phép nhiều NULL**. Khi request được APPROVED hoặc REJECTED, giá trị trả về NULL, cho phép tạo request mới nếu cần. |
| 15 | `physical_rooms` | `uq_physical_rooms_code` | `code` | Single Code | Mã phòng vật lý thực tế tại quán là duy nhất (VD: `VIP-01`). | Cột NOT NULL. |
| 16 | `schedule_proposals` | `uq_active_proposed_schedule` | `proposed_game_room_id` | **Virtual Generated** | **CHỐNG TRÙNG LỊCH PROPOSED**: Một phòng chơi tại một thời điểm chỉ có tối đa 1 đề xuất lịch ở trạng thái `PROPOSED`. | **Cho phép nhiều NULL**. Khi đề xuất được ACCEPTED hoặc REJECTED, trả về NULL, cho phép tạo đề xuất mới. |
| 17 | `schedule_responses` | `uq_schedule_responses_vote` | `schedule_proposal_id`, `room_participant_id` | Composite Vote | Một thành viên chỉ được gửi đúng một phiếu biểu quyết cho mỗi đề xuất lịch. | Cả 2 cột NOT NULL. |
| 18 | `sessions` | `uq_sessions_code` | `session_code` | Single Code | Mã định danh duy nhất của phiên chơi (VD: `SESS-20261118-001`). | Cột NOT NULL. |
| 19 | `physical_room_reservations` | `uq_session_active_reservation` | `active_session_id` | **Virtual Generated** | **MỖI PHIÊN CHỈ GIỮ 1 CHỖ ACTIVE**: Một session chỉ có tối đa một bản giữ phòng vật lý `RESERVED` hoặc `CONFIRMED`. | **Cho phép nhiều NULL**. Khi reservation bị CANCELLED, trả về NULL, cho phép đặt phòng khác. |
| 20 | `booking_price_snapshots` | `uq_booking_price_snapshots_session` | `session_id` | Single 1:1 | Mỗi session chỉ có duy nhất một bản snapshot đóng băng giá tài chính. | Cột NOT NULL. |
| 21 | `payment_attempts` | `uq_payment_attempts_idempotency` | `idempotency_key` | Single Idempotency | **BẢO ĐẢM IDEMPOTENCY THANH TOÁN**: Ngăn chặn trùng lặp giao dịch gửi sang cổng thanh toán khi người dùng nhấn nhiều lần. | Cột NOT NULL. |
| 22 | `payment_transactions` | `uq_payment_transactions_attempt` | `payment_attempt_id` | Single 1:1 | Mỗi lần thử thanh toán chỉ được đối soát và ghi nhận tối đa 1 giao dịch kế toán thành công. | Cột NOT NULL. |
| 23 | `payment_transactions` | `uq_payment_transactions_reference` | `transaction_reference` | Single Ledger | Mã tham chiếu giao dịch đối soát ngân hàng/cổng (VNPAY/MoMo TxnID) là duy nhất. | Cột NOT NULL. |
| 24 | `refund_attempts` | `uq_refund_attempts_idempotency` | `idempotency_key` | Single Idempotency | **BẢO ĐẢM IDEMPOTENCY HOÀN TIỀN**: Ngăn ngừa gửi nhiều lệnh hoàn tiền trùng lặp cho cùng một yêu cầu. | Cột NOT NULL. |
| 25 | `notification_recipient_states` | `uq_notification_recipient` | `notification_id`, `user_id` | Composite State | Mỗi người dùng chỉ có 1 bản ghi lưu trạng thái đọc/chưa đọc cho mỗi thông báo. | Cả 2 cột NOT NULL. |

---

## 3. THỐNG KÊ TOÀN DIỆN 104 CHỈ MỤC (INDEX INVENTORY)

Toàn bộ database có **104 Indexes vật lý**:
- **34 Clustered Primary B-Tree Indexes** (100% các bảng trên `id`).
- **25 Unique Indexes** (18 Natural Unique + 5 Virtual Generated Conditional Unique + 2 Composite Ledger).
- **45 Non-Unique Secondary Indexes** (chỉ mục phục vụ Foreign Key joins, bộ lọc WHERE và sắp xếp ORDER BY).

### Đánh giá chất lượng và tối ưu hóa chỉ mục:

1. **Hỗ trợ truy vấn WHERE, JOIN và ORDER BY:**
   - 100% Foreign Key columns có index hỗ trợ (tránh Full Table Scan khi thực hiện `ON DELETE / UPDATE` hoặc JOIN cha-con).
   - Các cột lọc trạng thái phổ biến (`users.status`, `games.is_active`, `game_rooms.status`, `sessions.status`, `player_payments.status`) đều có chỉ mục phụ riêng biệt.
2. **Composite Indexes quan trọng:**
   - `game_prices`: `idx_game_prices_lookup (game_id, day_type, is_active)` -> Tối ưu hóa truy vấn tìm giá kịch bản.
   - `physical_room_reservations`: `idx_prr_lookup (physical_room_id, reservation_date, status)` -> Tối ưu hóa truy vấn kiểm tra phòng trống.
   - `sessions`: `idx_sessions_schedule (scheduled_date, status)` -> Tối ưu hóa truy vấn lịch hoạt động trong ngày.
   - `dm_availabilities`: `idx_dm_avail_lookup (dm_profile_id, day_of_week, is_active)` -> Tối ưu hóa lọc giờ rảnh của Quản trò.
3. **Phân tích chỉ mục trùng lặp hoặc dư thừa (Redundancy Analysis):**
   - Không có trường hợp chỉ mục đơn trùng lặp với tiền tố trái nhất của composite index (Leftmost Prefix Redundancy).
   - Ví dụ: `uq_game_category_assignments (game_id, category_id)` đã bao phủ tìm kiếm theo `game_id`, và bảng có thêm `idx_gca_category_id (category_id)` để phục vụ tìm kiếm ngược từ phía Category. Thiết kế đạt chuẩn tối ưu 100%.

---

## 4. PHÂN TÍCH CHỈ MỤC BỊ THIẾU (MISSING INDEX ANALYSIS)

Dựa trên các ca sử dụng truy vấn trong tài liệu thiết kế hệ thống, đề xuất các chỉ mục bổ sung khi hệ thống bước vào giai đoạn tải cao (Phase 4 / Phase 5):

| Bảng | Chỉ mục đề xuất | Các cột | Ca sử dụng nghiệp vụ được tối ưu | Mức độ ưu tiên |
|---|---|---|---|:---:|
| `physical_room_reservations` | `idx_prr_overlap_check` | `(physical_room_id, reservation_date, start_time, end_time, status)` | Hỗ trợ Covering Index cực nhanh cho câu lệnh kiểm tra trùng phòng vật lý trong Pessimistic Lock (`SELECT FOR UPDATE`). | **HIGH** |
| `player_payments` | `idx_pp_session_status` | `(session_id, status, amount_due, amount_paid)` | Covering index phục vụ màn hình tổng kết công nợ phiên chơi của Host/Quản lý. | **MEDIUM** |
| `audit_logs` | `idx_audit_entity` | `(entity_type, entity_id, created_at)` | Truy vấn toàn bộ lịch sử biến động của một đối tượng nghiệp vụ cụ thể. | **MEDIUM** |

---

## 5. BUSINESS INTEGRITY REVIEW (ĐÁNH GIÁ 6 TRỤ CỘT BẢO VỆ NGHIỆP VỤ CỐT LÕI)

### A. Chống đặt trùng phòng vật lý (Physical Room Overlap)
- **Cơ chế Database hiện tại:** Bảng `physical_room_reservations` có `uq_session_active_reservation` trên `active_session_id`. Ràng buộc này chỉ đảm bảo **1 session không thể có 2 lần giữ phòng active cùng lúc**.
- **Điểm yếu / Lỗ hổng Database:** MySQL B-Tree Index **KHÔNG THỂ tự động ngăn chặn hai session khác nhau đặt cùng một phòng vật lý có khoảng thời gian giao nhau** `[start_time, end_time)`.
- **Bằng chứng kiểm thử:** Test `testH01PhysicalRoomOverlapVulnerabilityDemonstration` đã chứng minh: 2 session khác nhau có thể cùng đặt phòng VIP1 vào lúc `14:00 - 18:00` và `16:00 - 20:00` và cả 2 bản ghi đều INSERT thành công vào DB mà không gặp bất kỳ lỗi constraint nào.
- **Giải pháp bắt buộc ở Application Layer:**
  Bắt buộc thực thi kiểm tra phòng trống bên trong Transaction với Pessimistic Lock (`SELECT ... FOR UPDATE`):
  ```sql
  SELECT id FROM physical_room_reservations 
  WHERE physical_room_id = :room_id 
    AND reservation_date = :date 
    AND status IN ('RESERVED', 'CONFIRMED')
    AND (start_time < :new_end_time AND end_time > :new_start_time)
  FOR UPDATE;
  ```
  Nếu kết quả trả về `> 0`, lập tức throw `RoomConflictException` và abort transaction.

---

### B. Chống trùng lịch Quản trò (DM Conflict Prevention)
- **Cơ chế Database hiện tại:** Bảng `dm_assignments` có `uq_active_dm_assignment` trên `active_assigned_room_id`. Ràng buộc này đảm bảo **1 phòng chơi chỉ có tối đa 1 DM ở trạng thái active**.
- **Điểm yếu / Lỗ hổng Database:** Database **KHÔNG ngăn chặn 1 DM được gán vào 2 phòng chơi khác nhau diễn ra cùng thời gian**.
- **Bằng chứng kiểm thử:** Test `testH02DmOverlappingAssignmentDemonstration` chứng minh: cùng một DM Profile có thể được gán `CONFIRMED` cho 2 phòng chơi khác nhau cùng lúc.
- **Giải pháp bắt buộc ở Application Layer:**
  Khi phân công DM, Service phải query các `sessions` đang hoạt động của DM để phát hiện xung đột thời gian trước khi lưu phân công:
  ```sql
  SELECT s.id FROM sessions s
  JOIN dm_assignments da ON s.dm_assignment_id = da.id
  WHERE da.dm_profile_id = :dm_id
    AND s.scheduled_date = :date
    AND s.status NOT IN ('CANCELLED', 'COMPLETED')
    AND (s.start_time < :new_end AND s.end_time > :new_start)
  FOR UPDATE;
  ```

---

### C. Chống đăng ký thành viên trùng (Duplicate Participant Prevention)
- **Cơ chế Database hiện tại:** Bảng `room_participants` sử dụng Virtual Generated Column:
  ```sql
  `active_user_id` BIGINT UNSIGNED GENERATED ALWAYS AS (
      CASE WHEN `status` IN ('JOINED', 'DEPOSIT_PENDING', 'DEPOSITED', 'REMAINING_PENDING', 'FULLY_PAID', 'LEAVE_REQUESTED') 
           THEN `user_id` ELSE NULL END
  ) VIRTUAL
  ```
  và ràng buộc duy nhất: `uq_room_active_participant (game_room_id, active_user_id)`.
- **Đánh giá:** **XUẤT SẮC (100% DB-ENFORCED)**.
  - Khi user đang trong phòng với bất kỳ trạng thái hoạt động nào, không một concurrent INSERT nào có thể lách qua được.
  - Khi user rời phòng (`EXITED`) hoặc bị đuổi (`KICKED`), `active_user_id` trở thành `NULL`. Theo quy chuẩn SQL của MySQL, giá trị `NULL` không bị ràng buộc bởi Unique Index, cho phép người chơi đăng ký lại phòng mà vẫn bảo lưu toàn bộ lịch sử giao dịch trước đó.

---

### D. Chống thanh toán vượt nghĩa vụ (Overpayment Prevention)
- **Cơ chế Database hiện tại:** Bảng `player_payments` có `chk_player_payments_amounts: amount_due >= 0 AND amount_paid >= 0`.
- **Điểm yếu / Lỗ hổng Database:** Không có ràng buộc `amount_paid <= amount_due`.
- **Bằng chứng kiểm thử:** Test `testH03OverpaymentProtectionConstraintScope` chứng minh: bảng cho phép lưu `amount_paid = 500,000` khi `amount_due = 200,000`.
- **Nhận định nghiệp vụ:** Đây là thiết kế chủ ý nhằm cho phép khách hàng tip hoặc bù phí phụ thu. Tuy nhiên, quy trình thanh toán qua cổng điện tử phải đảm bảo chỉ tạo `payment_attempts` với đúng số tiền còn thiếu:
  ```php
  $remainingDue = $payment->amount_due - $payment->amount_paid;
  if ($attemptAmount > $remainingDue) {
      throw new InvalidPaymentAmountException("Payment exceeds remaining balance.");
  }
  ```

---

### E. Chống hoàn tiền vượt giới hạn (Over-refund Prevention)
- **Cơ chế Database hiện tại:** Bảng `refunds` có `chk_refunds_amount: refund_amount > 0`.
- **Điểm yếu / Lỗ hổng Database:** Database không thể thực hiện check xuyên bảng để đảm bảo `SUM(refunds.refund_amount) <= player_payments.amount_paid`.
- **Giải pháp bắt buộc ở Application Layer:**
  Mọi hành động tạo lệnh hoàn tiền phải thực hiện trong transaction có khóa dòng:
  ```sql
  SELECT amount_paid FROM player_payments WHERE id = :id FOR UPDATE;
  SELECT COALESCE(SUM(refund_amount), 0) AS total_refunded FROM refunds 
  WHERE player_payment_id = :id AND status NOT IN ('REJECTED', 'FAILED') FOR UPDATE;
  ```
  Nếu `total_refunded + new_refund_amount > amount_paid`, từ chối giao dịch hoàn tiền ngay lập tức.

---

### F. Đảm bảo tính Idempotency (Giao dịch bất biến)
- **Cơ chế Database hiện tại:**
  - Bảng `payment_attempts` có `uq_payment_attempts_idempotency (idempotency_key)`.
  - Bảng `refund_attempts` có `uq_refund_attempts_idempotency (idempotency_key)`.
  - Bảng `payment_transactions` có `uq_payment_transactions_attempt (payment_attempt_id)` và `uq_payment_transactions_reference (transaction_reference)`.
- **Đánh giá:** **BẢO VỆ TUYỆT ĐỐI Ở TẦNG DB**.
  - Bất kỳ request gửi trùng từ client (do double click hoặc mạng giật) đều sinh ra cùng idempotency key và bị chặn đứng bởi `MySQL Error 1062 Duplicate entry`.
  - Không thể ghi nhận 2 giao dịch thành công cho cùng 1 lần thử thanh toán.

---

## 6. PHÂN LOẠI RỦI RO VÀ GIẢI PHÁP ĐỀ XUẤT (RISK CLASSIFICATION)

| Risk ID | Hạng mục | Mức độ rủi ro | Trạng thái bảo vệ DB | Giải pháp & Trách nhiệm triển khai |
|---|---|:---:|:---:|---|
| **RSK-01** | Trùng lịch phòng vật lý | **CRITICAL** | Không thể enforce bằng B-Tree SQL | **Bắt buộc**: Áp dụng Pessimistic Locking (`SELECT FOR UPDATE`) tại `PhysicalRoomReservationService` trong Phase 4. |
| **RSK-02** | Trùng lịch Quản trò | **HIGH** | Chưa enforce xuyên session | **Bắt buộc**: Triển khai `DmScheduleValidationService` kiểm tra lịch trước khi gán DM trong Phase 4. |
| **RSK-03** | Hoàn tiền vượt hạn mức | **HIGH** | Không enforce tổng tiền | **Bắt buộc**: Áp dụng Transaction Lock và kiểm tra hạn mức hoàn tiền tại `RefundService` trong Phase 4. |
| **RSK-04** | Thanh toán vượt nghĩa vụ | **MEDIUM** | Cho phép linh hoạt | Triển khai validation số tiền tại `PaymentService` trước khi tạo Payment Attempt. |
| **RSK-05** | Sai lệch số lượng trong Comment Migration | **LOW** | Không ảnh hưởng DDL vật lý | Cập nhật comment trong tài liệu và đề xuất chỉnh sửa tại `CHANGE_PROPOSAL_V1.md`. |

---

## 7. KẾT LUẬN TASK 3.3

- **20 CHECK constraints:** 100% hợp lệ, có cú pháp chuẩn MySQL 8.0/8.4, bao phủ toàn bộ các miền giá trị âm, giới hạn sỹ số và khoảng thời gian logic (**PASS**).
- **25 UNIQUE constraints:** 100% chính xác, giải quyết xuất sắc bài toán Partial Index nhờ kỹ thuật Virtual Generated Columns (**PASS**).
- **104 Indexes:** Bao phủ toàn diện các khóa ngoại và trường truy vấn chính, không có index dư thừa hoặc trùng lặp tiền tố (**PASS**).
- **6 Trụ cột Business Integrity:** Ranh giới giữa Database Enforcement và Application Locking đã được xác lập minh bạch và có bằng chứng kiểm thử thực tế (**PASS WITH CONDITIONS**).

**KẾT LUẬN: PASS (APPROVED FOR PHASE 4 IMPLEMENTATION)**
