# TÀI LIỆU KIẾN TRÚC CƠ SỞ DỮ LIỆU KỊCH BẢN SÁT V3

Tài liệu này giải thích chi tiết, trực quan toàn bộ hệ thống Cơ sở dữ liệu phiên bản 3 (`kich_ban_sat_v3`) dành cho nền tảng đặt phòng chơi **Kịch Bản Sát (Murder Mystery / Tabletop RPG)**.

---

## 1. TỔNG QUAN KIẾN TRÚC (ARCHITECTURAL OVERVIEW)

Hệ thống CSDL V3 gồm **21 bảng**, được thiết kế theo tư duy **Domain-Driven Design (DDD)**:
- **19 Bảng Nghiệp Vụ (Business Domains):** Phản ánh trọn vẹn vòng đời một buổi chơi Kịch Bản Sát từ khi ghép đội, biểu quyết lịch, thanh toán đến khi kết thúc buổi chơi.
- **02 Bảng Hạ Tầng Kỹ Thuật (Infrastructure):** Đảm bảo an toàn giao dịch tài chính (`idempotency_records`) và phân tán sự kiện tin cậy (`outbox_events`).

### Sơ đồ luồng nghiệp vụ thực tế (Core Business Lifecycle)

```
[1. User Đăng ký/Đăng nhập] 
         │
         ▼
[2. Chọn Kịch bản Game] ── (Có bảng giá & cấu hình đạo cụ)
         │
         ▼
[3. Mở Phòng ghép (GameRoom)] ◄───► [Cửa sổ tìm người thay thế (Replacement)]
         │
         ▼
[4. Phân công Quản trò (DM Assignment)]
         │
         ▼
[5. Đề xuất & Biểu quyết Lịch (Schedule Proposals & Votes)]
         │
         ▼ (Tất cả đồng ý)
[6. Chốt Buổi chơi (Session)] ── Gán với Chi nhánh & Phòng chơi vật lý (Venues & Physical Rooms)
         │
         ▼
[7. Tính Snapshot Giá & Áp dụng Khuyến mãi (Price Snapshots & Promotions)]
         │
         ▼
[8. Đặt cọc & Tất toán (Payments & Transactions & Refunds)]
         │
         ▼
[9. Gửi Thông báo trạng thái (Notifications)]
```

---

## 2. GIẢI THÍCH CHI TIẾT 9 DOMAIN NGHIỆP VỤ

### Domain 1: Identity & Authorization (Định danh & Phân quyền)
- **`roles`**: Quản lý vai trò động (`PLAYER`, `DM`, `ADMIN`).
- **`users`**: Tài khoản người dùng với các trường bảo mật: `password_hash`, `email_verified_at`, `status` (`ACTIVE`, `INACTIVE`, `SUSPENDED`).

### Domain 2: Game Catalog & Configuration (Kho Kịch bản & Đạo cụ)
- **`games`**: Thông tin kịch bản án mạng: tên kịch bản, slug URL, số người chơi tối thiểu/tối đa (`min_players`, `max_players`), thời lượng (`duration_minutes`).
- **`game_configurations`**: Quản lý phiên bản kịch bản (`version`). Cột `configuration JSON` lưu trữ danh sách đạo cụ (props), manh mối (clues), âm thanh bối cảnh (BGM) theo từng bản cập nhật mà không cần thay đổi cấu trúc bảng.

### Domain 3: GameRoom, Participants & Replacement (Phòng chơi, Thành viên & Thay thế)
- **`game_rooms`**: Phòng ghép đội chơi. 
  - *Cải tiến đột phá ở V3:* Nhúng trực tiếp **Cửa sổ thay thế (Replacement Window)** và **Snapshot giá phòng** vào các cột của bảng `game_rooms` (quan hệ 1 - 0..1), giúp loại bỏ bảng phụ, giảm phép JOIN phức tạp và tăng tốc độ truy vấn.
- **`room_participants`**: Danh sách người chơi trong phòng.
  - *Cơ chế Generated Column `active_user_id`:*
    ```sql
    active_user_id BIGINT UNSIGNED GENERATED ALWAYS AS (
        CASE WHEN status IN ('PENDING_DEPOSIT', 'DEPOSITED', 'LEAVE_REQUESTED')
             THEN user_id ELSE NULL END
    ) STORED,
    CONSTRAINT uq_room_active_user UNIQUE (game_room_id, active_user_id)
    ```
    *Ý nghĩa:* Trong MySQL, nhiều giá trị `NULL` trong một UNIQUE index không bị coi là trùng lặp. Do đó, khi một người rời phòng (`LEFT`), `active_user_id` trở thành `NULL`, cho phép họ có thể vào lại phòng sau này. Nhưng khi đang ở trong phòng (`PENDING_DEPOSIT`...), họ không thể chiếm 2 slot cùng lúc.

### Domain 4: DM Assignment (Gán Quản Trò / Host)
- **`dm_assignments`**: Gán người dẫn truyện (DM) cho phòng. Trạng thái gồm `PENDING` (chờ nhận phòng), `ACCEPTED` (nhận phòng), `REJECTED` (từ chối), `REVOKED` (hủy gán).

### Domain 5: Venue & Physical Room (Cơ sở & Phòng chơi ngoài đời)
- **`venues`**: Các chi nhánh cửa hàng thực tế (địa chỉ, số điện thoại).
- **`physical_rooms`**: Các phòng chơi cụ thể trong chi nhánh (sức chứa, phong cách decor như phòng cổ điển, phòng Á Đông, phòng Dracula).

### Domain 6: Scheduling & Session (Lịch chơi & Buổi chơi)
- **`schedule_proposals`**: Các mốc thời gian đề xuất cho buổi chơi. Cột sinh học `active_game_room_id` đảm bảo mỗi phòng chỉ có duy nhất 1 cuộc bình chọn mở tại một thời điểm.
- **`schedule_responses`**: Lượt vote của từng thành viên (`ACCEPTED`, `REJECTED`, `PENDING`). Sử dụng Khóa ngoại kết hợp (Composite Foreign Key) để ngăn chặn việc người ở phòng này vote nhầm lịch của phòng khác.
- **`sessions`**: Buổi chơi chính thức được tạo ra sau khi đề xuất lịch được toàn bộ thành viên chấp thuận.

### Domain 7: Pricing & Promotion (Bảng giá & Khuyến mãi)
- **`game_prices`**: Bảng giá theo khung giờ (sáng/chiều/tối) và khoảng ngày áp dụng.
- **`promotions`**: Quản lý voucher, ưu đãi học sinh/sinh viên. Điều kiện áp dụng được lưu trong trường `rules JSON` linh hoạt.
- **`participant_price_snapshots`**: Lưu giá tiền chính xác của từng người chơi tại thời điểm chốt phòng, chống việc thay đổi giá trong tương lai làm sai lệch số tiền phải thu.

### Domain 8: Payment & Refund (Thanh toán & Hoàn tiền)
- **`player_payments`**: Quản lý 2 đợt thanh toán riêng biệt: Đặt cọc (`DEPOSIT`) và Thanh toán phần còn lại (`REMAINING`).
- **`payment_transactions`**: Nhật ký giao dịch thanh toán. Trường `idempotency_key` đảm bảo ngăn chặn triệt để tình trạng quẹt thẻ hoặc thanh toán 2 lần khi mạng giật lag.
- **`refunds`**: Ghi nhận yêu cầu hoàn tiền khi người chơi rời phòng hợp lệ hoặc phòng bị hủy.

### Domain 9: Notifications (Thông báo)
- **`notifications`**: Hệ thống thông báo tự động (thông báo có người vào phòng, thông báo lịch chơi được chốt, thông báo nhắc cọc).

### Domain Hạ tầng: Idempotency & Outbox
- **`idempotency_records`**: Chống trùng lặp các yêu cầu gửi lên API backend.
- **`outbox_events`**: Mô hình Transactional Outbox Pattern, đảm bảo việc gửi email, webhook, thông báo realtime không bao giờ bị mất nếu máy chủ gặp sự cố giữa chừng.

---

## 3. CÁC ĐIỀU CHỈNH ĐÃ THỰC HIỆN ĐỂ TỐI ƯU SCHEMA

1. **Sửa lỗi logic 3 giá trị (Three-valued logic) trong CHECK constraints:**
   - Cột `replacement_deadline_at`: Cho phép `NULL` khi cửa sổ mới mở mà chưa ấn định hạn đóng.
   - Cột `price_discount_amount`: Đặt `DEFAULT 0` và đảm bảo phép tính trừ trong `CHECK (price_final_amount = price_subtotal - price_discount_amount)` luôn hợp lệ.
2. **Bổ sung trọn bộ Dữ liệu mẫu (Seed Data) thực tế:**
   - Đầy đủ tài khoản Admin, DM, Người chơi với mật khẩu chuẩn bcrypt `password123`.
   - Chi nhánh mẫu, Phòng chơi thực tế mẫu.
   - 3 Kịch bản án mạng hấp dẫn với đầy đủ cấu hình đạo cụ, manh mối JSON v1 và bảng giá theo ca.
   - Phòng ghép mẫu sẵn sàng trải nghiệm.
3. **Đồng bộ toàn bộ Repository PHP:**
   - Áp dụng kỹ thuật Alias và Adapter pattern trong các repository (`UserRepository`, `GameRepository`, `GameRoomRepository`, v.v.) giúp tương thích 100% với schema V3 mà không phá vỡ logic nghiệp vụ của backend.
