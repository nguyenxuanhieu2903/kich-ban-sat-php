# BÁO CÁO KIỂM ĐỊNH RÀNG BUỘC TOÀN VẸN VÀ BẢO ĐẢM INVARIANT (CONSTRAINT AUDIT)
## DỰ ÁN: BOOKING-PROJECT-PHP — PHASE 1: DATABASE DESIGN & ERD ACCEPTANCE

**Ngày thực hiện:** 18/11/2026  
**Auditor:** Principal Database Architect & Senior Data Modeler  
**Target Engine:** MySQL 8.4 LTS (InnoDB Storage Engine)  
**Phạm vi:** Unique Constraints, Check Constraints, Generated Columns, Transactional Invariants

---

## 1. NGUYÊN TẮC PHÂN ĐỊNH CƠ CHẾ BẢO ĐẢM QUY TẮC NGHIỆP VỤ

Trong kỹ nghệ thiết kế CSDL thực chiến, tuyên bố sai lệch rằng "Database đã đảm bảo 100% quy tắc nghiệp vụ" khi hệ quản trị CSDL không có cấu trúc thực thi là lỗi cấm kỵ (Hallucination). Báo cáo kiểm định này phân định rõ 3 tầng thực thi:
1. **Database-enforced constraints (Ràng buộc cấp Database):** Được thực thi bởi MySQL Engine thông qua `PRIMARY KEY`, `FOREIGN KEY`, `UNIQUE KEY`, `CHECK CONSTRAINT` và `VIRTUAL GENERATED COLUMNS`. Bất kỳ câu lệnh `INSERT` hoặc `UPDATE` nào vi phạm đều bị engine từ chối ngay lập tức và sinh ra mã lỗi SQLSTATE chuẩn.
2. **Application-enforced rules (Quy tắc tầng ứng dụng):** Được kiểm tra trong mã nguồn PHP (Service/Validator/Domain Model) trước khi bắn câu lệnh SQL xuống database. Tầng này chịu rủi ro Race Condition nếu không có Transaction Locking.
3. **Transaction / Locking requirements (Yêu cầu Giao dịch và Khóa đồng thời):** Cơ chế phối hợp bắt buộc (`SELECT ... FOR UPDATE`, `SERIALIZABLE` hoặc Pessimistic Locking trên bản ghi cha) để ngăn chặn tranh chấp đa luồng (Concurrency Conflict / Lost Updates / Phantom Reads) khi kiểm tra quy tắc nghiệp vụ dạng khoảng (Range / Overlap).

---

## 2. KIỂM ĐỊNH CHI TIẾT 7 QUY TẮC NGHIỆP VỤ CỐT LÕI (TASK 3 AUDIT)

### Quy tắc 1: Một User chỉ có một lượt tham gia đang hoạt động trong một GameRoom
- **Mô tả nghiệp vụ:** Người dùng chỉ được ở 1 vị trí active trong phòng. Khi trạng thái chuyển sang kết thúc (`LEFT`, `EXPIRED`, `CANCELLED`), họ có thể được phép tham gia lại trong tương lai nếu phòng còn mở.
- **Tập trạng thái Active:** `['JOINED', 'DEPOSIT_PENDING', 'DEPOSITED', 'REMAINING_PENDING', 'FULLY_PAID', 'LEAVE_REQUESTED']`.
- **Hạn chế kỹ thuật của MySQL 8.4 LTS:** MySQL **không** hỗ trợ Partial Index (`WHERE status IN (...)`). Nếu chỉ tạo `UNIQUE (game_room_id, user_id)`, người dùng sau khi rời phòng (`LEFT`) sẽ vĩnh viễn không thể vào lại phòng đó.
- **Giải pháp Database-enforced:** Sử dụng **Virtual Generated Column** kết hợp **Unique Key**. Trong MySQL InnoDB, giá trị `NULL` không bị xung đột trong Unique Key (cho phép nhiều giá trị NULL).
  ```sql
  -- Định nghĩa cột ảo: trả về user_id nếu Active, ngược lại trả về NULL
  ALTER TABLE room_participants 
  ADD COLUMN active_user_id BIGINT UNSIGNED 
  GENERATED ALWAYS AS (
    CASE 
      WHEN status IN ('JOINED', 'DEPOSIT_PENDING', 'DEPOSITED', 'REMAINING_PENDING', 'FULLY_PAID', 'LEAVE_REQUESTED') 
      THEN user_id 
      ELSE NULL 
    END
  ) VIRTUAL;

  ALTER TABLE room_participants 
  ADD CONSTRAINT uq_room_active_participant 
  UNIQUE (game_room_id, active_user_id);
  ```
- **Tầng bổ trợ (Transaction / Locking):** Khi thực hiện hành động Join Room, ứng dụng PHP phải mở Transaction và khóa phòng: `SELECT id, min_players, max_players FROM game_rooms WHERE id = :room_id FOR UPDATE;` để kiểm tra sĩ số và bảo vệ slot cuối.
- **Phân loại:** **Database-enforced (via Generated Column) + Transaction Locking.**

---

### Quy tắc 2: Một GameRoom chỉ có một ScheduleProposal ở trạng thái PROPOSED
- **Mô tả nghiệp vụ:** Tại một thời điểm đàm phán lịch chơi, phòng chỉ có duy nhất 01 đề xuất lịch đang chờ biểu quyết (`PROPOSED`). Đề xuất cũ phải bị `REJECTED`, `EXPIRED` hoặc `ACCEPTED` trước khi tạo đề xuất mới.
- **Giải pháp Database-enforced:**
  ```sql
  ALTER TABLE schedule_proposals 
  ADD COLUMN proposed_game_room_id BIGINT UNSIGNED 
  GENERATED ALWAYS AS (
    CASE WHEN status = 'PROPOSED' THEN game_room_id ELSE NULL END
  ) VIRTUAL;

  ALTER TABLE schedule_proposals 
  ADD CONSTRAINT uq_active_proposed_schedule 
  UNIQUE (proposed_game_room_id);
  ```
- **Phân loại:** **Database-enforced (via Generated Column) + Application Service Validation.**

---

### Quy tắc 3: Một GameRoom chỉ có một DM Assignment đang hoạt động
- **Mô tả nghiệp vụ:** Một phòng chơi chỉ có tối đa 01 Quản trò (DM) đang nhận nhiệm vụ ở trạng thái hiệu lực (`ASSIGNED` hoặc `CONFIRMED`). Nếu DM từ chối (`REJECTED`) hoặc bị hủy (`CANCELLED`), hệ thống mới cho phép phân công DM khác.
- **Giải pháp Database-enforced:**
  ```sql
  ALTER TABLE dm_assignments 
  ADD COLUMN active_assigned_room_id BIGINT UNSIGNED 
  GENERATED ALWAYS AS (
    CASE WHEN status IN ('ASSIGNED', 'CONFIRMED') THEN game_room_id ELSE NULL END
  ) VIRTUAL;

  ALTER TABLE dm_assignments 
  ADD CONSTRAINT uq_active_dm_assignment 
  UNIQUE (active_assigned_room_id);
  ```
- **Phân loại:** **Database-enforced (via Generated Column) + Dispatcher Domain Logic.**

---

### Quy tắc 4: Một RoomParticipant chỉ có một LeaveRequest PENDING
- **Mô tả nghiệp vụ:** Thành viên đã gửi đơn xin rút khỏi phòng (`PENDING`) không được phép gửi tiếp đơn thứ hai khi đơn trước chưa được duyệt (`APPROVED`) hoặc từ chối (`REJECTED`).
- **Giải pháp Database-enforced:**
  ```sql
  ALTER TABLE participant_leave_requests 
  ADD COLUMN pending_participant_id BIGINT UNSIGNED 
  GENERATED ALWAYS AS (
    CASE WHEN status = 'PENDING' THEN room_participant_id ELSE NULL END
  ) VIRTUAL;

  ALTER TABLE participant_leave_requests 
  ADD CONSTRAINT uq_pending_leave_request 
  UNIQUE (pending_participant_id);
  ```
- **Phân loại:** **Database-enforced (via Generated Column).**

---

### Quy tắc 5: Một Session chỉ có một Booking Price Snapshot
- **Mô tả nghiệp vụ:** Giá trị tài chính của phiên chơi phải được đóng băng duy nhất 1 lần khi phiên được chốt lịch. Không được phép tồn tại 2 bản snapshot tài chính cho cùng một phiên chơi.
- **Giải pháp Database-enforced:** Khóa ngoại `session_id` trong bảng `booking_price_snapshots` được định nghĩa là **Strict Unique**:
  ```sql
  ALTER TABLE booking_price_snapshots 
  ADD CONSTRAINT uq_session_price_snapshot 
  UNIQUE (session_id);
  ```
- **Phân loại:** **Database-enforced (Native Unique Constraint).**

---

### Quy tắc 6: Một Session chỉ có một Active Reservation
- **Mô tả nghiệp vụ:** Một phiên chơi chỉ được giữ bàn vật lý tại 01 phòng chơi ở trạng thái hiệu lực (`RESERVED`, `CONFIRMED`). Nếu bản giữ chỗ bị hủy (`CANCELLED`, `RELEASED`), phiên chơi mới có thể đặt phòng khác.
- **Giải pháp Database-enforced:**
  ```sql
  ALTER TABLE physical_room_reservations 
  ADD COLUMN active_session_id BIGINT UNSIGNED 
  GENERATED ALWAYS AS (
    CASE WHEN status IN ('RESERVED', 'CONFIRMED') THEN session_id ELSE NULL END
  ) VIRTUAL;

  ALTER TABLE physical_room_reservations 
  ADD CONSTRAINT uq_session_active_reservation 
  UNIQUE (active_session_id);
  ```
- **Phân loại:** **Database-enforced (via Generated Column).**

---

### Quy tắc 7: Không có Active Reservation chồng lấn trên cùng PhysicalRoom
- **Mô tả nghiệp vụ:** Cùng một phòng vật lý tại cửa hàng, không được phép có hai phiên đặt chỗ có khung giờ giao nhau (`overlap`) trong cùng một ngày (`reservation_date`).
- **Đánh giá năng lực Engine MySQL 8.4 LTS:** 
  - MySQL **không hỗ trợ** Generalized Search Tree (GiST) Exclusion Constraints (`EXCLUDE USING gist`) như PostgreSQL.
  - Phép so sánh chồng lấn khoảng thời gian là điều kiện bất đẳng thức: `(start_time < new_end_time AND end_time > new_start_time)`. Điều kiện này **không thể** biểu diễn bằng `UNIQUE KEY` hoặc Virtual Generated Column đơn thuần.
- **Cơ chế thực thi chuẩn xác:**
  1. **Yêu cầu Bắt buộc:** **Application-enforced Rule bên trong Database Transaction có Pessimistic Locking.**
  2. **Quy trình khóa và kiểm tra trong Transaction:**
     ```sql
     START TRANSACTION;
     -- Bước 1: Khóa dòng phòng vật lý cha để ngăn chặn tranh chấp đặt bàn đồng thời
     SELECT id FROM physical_rooms WHERE id = :room_id FOR UPDATE;

     -- Bước 2: Kiểm tra tồn tại xung đột khung giờ
     SELECT COUNT(*) FROM physical_room_reservations 
     WHERE physical_room_id = :room_id 
       AND reservation_date = :reservation_date 
       AND status IN ('RESERVED', 'CONFIRMED') 
       AND (start_time < :new_end_time AND end_time > :new_start_time);

     -- Bước 3: Nếu COUNT == 0 thì INSERT, ngược lại ROLLBACK và ném ReservationConflictException
     INSERT INTO physical_room_reservations (...);
     COMMIT;
     ```
  3. **Tùy chọn Database Trigger (Bổ trợ bảo vệ ở tầng DB):** Có thể viết `BEFORE INSERT` trigger trên bảng `physical_room_reservations` thực hiện câu query kiểm tra xung đột và bắn `SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Room time slot conflict detected'`. Tuy nhiên, Trigger này vẫn phụ thuộc vào việc lock phòng cha để tránh hiện tượng Phantom Read giữa các transaction đồng thời.
- **Phân loại:** **Application-enforced + Transaction Row Locking (Pessimistic Locking) + Optional DB Trigger.**

---

## 3. TỔNG HỢP CHECK CONSTRAINTS TOÀN HỆ THỐNG

MySQL 8.4 LTS hỗ trợ đầy đủ `CHECK CONSTRAINT`. Các ràng buộc kiểm tra toàn vẹn miền giá trị (Domain Value Integrity) bắt buộc phải có trong DDL Phase 2:

1. `games`:
   - `chk_games_players`: `CHECK (min_players > 0 AND max_players >= min_players)`
   - `chk_games_duration`: `CHECK (duration_minutes > 0)`
2. `game_rooms`:
   - `chk_rooms_players`: `CHECK (min_players > 0 AND max_players >= min_players)`
3. `game_prices`:
   - `chk_price_positive`: `CHECK (base_price_per_player >= 0)`
   - `chk_price_times`: `CHECK (time_from < time_to)`
4. `booking_price_snapshots`:
   - `chk_snapshot_financials`: `CHECK (original_base_price >= 0 AND discount_amount >= 0 AND final_price >= 0 AND required_deposit_per_player >= 0)`
5. `player_payments`:
   - `chk_payment_amounts`: `CHECK (amount_due >= 0 AND amount_paid >= 0)`
6. `refunds`:
   - `chk_refund_amount`: `CHECK (refund_amount > 0)`
7. `physical_room_reservations`:
   - `chk_res_times`: `CHECK (start_time < end_time)`

---

## 4. KẾT LUẬN KIỂM ĐỊNH RÀNG BUỘC (CONSTRAINT ACCEPTANCE)

- Toàn bộ 7/7 quy tắc nghiệp vụ trọng yếu đã có phương án kỹ thuật khả thi và chính xác trên MySQL 8.4 LTS.
- Xóa bỏ hoàn toàn ngộ nhận về năng lực của MySQL trong việc chặn chồng lấn lịch (đã chuyển giao đúng sang cơ chế Khóa Giao dịch Bi quan - Pessimistic Locking).
- Sử dụng triệt để năng lực **Virtual Generated Column + Unique Key** của MySQL 8.4 để giải quyết bài toán thiếu Partial Index, bảo đảm toàn vẹn dữ liệu ở mức database engine cao nhất.
