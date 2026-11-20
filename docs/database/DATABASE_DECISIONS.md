# BÁO CÁO CÁC QUYẾT ĐỊNH CƠ SỞ DỮ LIỆU VÀ TOÀN VẸN NGHIỆP VỤ
## (DATABASE DECISIONS & BUSINESS INVARIANTS)
### DỰ ÁN: WEBSITE BOOKING – KỊCH BẢN SÁT (JUBENSHA / MURDER MYSTERY) V16

- **Ngày ban hành:** 20/11/2026
- **Auditor & Architect:** Senior Principal Database Architect & System Analyst
- **Working Directory:** `d:\LapTrinhWeb\Project\Booking_kich-ban-sat_PHP\docs\database`
- **Tài liệu tham chiếu:**
  - `PRODUCT_SPEC_V16_Final.md` (Mục 7, 8, 14)
  - `IMPLEMENTATION_PLAN_V16_Final.md`
  - `docs/database/acceptance/03-report/verification-consistency-audit.md`

---

## 1. GIẢI QUYẾT 9 QUYẾT ĐỊNH MỞ (DECISIONS B3-01 ĐẾN B3-09)

Bảng phân loại trạng thái:
- **`ACCEPTED`**: Đã chốt theo Product Spec V16 Freeze hoặc đã thống nhất kỹ thuật đầy đủ căn cứ.
- **`PROPOSED`**: Đề xuất kỹ thuật tối thiểu khả thi cho MVP (Minimal Viable Architecture), chờ Lead ký duyệt chính thức.
- **`BLOCKED`**: Nghiệp vụ chưa đủ dữ liệu hoặc có xung đột trực tiếp, bắt buộc Project Lead đưa ra quyết định kinh doanh.

---

### B3-01: User Role History (Lịch sử phân quyền người dùng)
- **Bối cảnh:** Cần quyết định lưu lịch sử cấp/hủy role của người dùng theo bảng lịch sử riêng hay thông qua audit log.
- **Tác động Schema:** Bảng `user_roles` lưu cặp `(user_id, role_id)` với `created_at`.
- **Đề xuất giải quyết cho MVP:** Không tạo thêm bảng `user_role_histories`. Mọi hành động gán hoặc thu hồi vai trò nhạy cảm (`ADMIN`, `DM`) được ghi trực tiếp vào `audit_logs` với action `ROLE_ASSIGNED` hoặc `ROLE_REVOKED`, bao gồm `user_id`, `actor_id`, `role_name`, và timestamp.
- **Căn cứ:** Tối ưu hóa số bảng, tránh over-engineering cho MVP; `audit_logs` đáp ứng đầy đủ tiêu chuẩn kiểm toán bảo mật.
- **Trạng thái:** **`PROPOSED`** (Khuyến nghị giữ nguyên).

---

### B3-02: Thời điểm cố định GameConfiguration (Freeze Configuration Moment)
- **Bối cảnh:** Xác định thời điểm cấu hình kịch bản (phiên bản, độ khó, yêu cầu đạo cụ) được đóng băng cho GameRoom.
- **Tác động Schema:** Bảng `game_rooms` lưu `game_id` và tham chiếu cấu hình.
- **Đề xuất giải quyết cho MVP:** Cố định kịch bản và cấu hình ngay khi GameRoom chuyển từ trạng thái `FORMING` sang `FORMED` (đã đủ người). Kể từ thời điểm `FORMED`, thuộc tính kịch bản là bất biến.
- **Căn cứ:** Theo Business Freeze V16, thành viên đã nộp cọc hoặc cam kết tham gia dựa trên kịch bản và cấu hình đã hiển thị.
- **Trạng thái:** **`ACCEPTED`**.

---

### B3-03: Chính sách thay đổi Configuration khi phòng đang hoạt động
- **Bối cảnh:** Nếu Host muốn đổi kịch bản hoặc cấu hình khi phòng đang ở trạng thái `FORMING` hoặc `FORMED`.
- **Tác động Schema:** Bảng `game_room_status_histories` ghi nhận thay đổi.
- **Đề xuất giải quyết cho MVP:**
  - Ở trạng thái `FORMING`: Cho phép Host đổi kịch bản/cấu hình. Khi đổi, hệ thống gửi thông báo cho toàn bộ người chơi hiện có trong phòng.
  - Ở trạng thái `FORMED` hoặc sau đó: **NGHIÊM CẤM** thay đổi kịch bản/cấu hình. Nếu muốn chơi kịch bản khác, Host phải hủy phòng và tạo phòng mới.
- **Căn cứ:** Bảo vệ quyền lợi người chơi đã cam kết thời gian và đặt cọc cho kịch bản cụ thể.
- **Trạng thái:** **`PROPOSED`**.

---

### B3-04: Participant PriceSnapshot Versioning (Phiên bản hóa Snapshot giá người chơi)
- **Bối cảnh:** Khi có người rút khỏi phòng và người thay thế tham gia, số tiền cọc và biểu giá áp dụng như thế nào?
- **Tác động Schema:** Bảng `booking_price_snapshots` và `player_payments`.
- **Đề xuất giải quyết cho MVP:** Giá của Session được chốt bất biến tại `booking_price_snapshots` khi tạo phiên chơi. Mọi người chơi (dù là người tham gia ban đầu hay người vào thay thế qua `replacement_windows`) đều chịu mức tiền cọc chuẩn cố định là **50.000 VNĐ** và mức giá còn lại bằng đúng giá niêm yết trong snapshot của Session đó.
- **Căn cứ:** Chính sách menu cửa hàng đồng nhất mức cọc 50.000 VNĐ/người cho mọi lượt tham gia.
- **Trạng thái:** **`ACCEPTED`**.

---

### B3-05: Liên kết Khuyến mãi vào Snapshot Tổng hay Snapshot Từng người chơi
- **Bối cảnh:** Ưu đãi Thứ 3 (giảm 30%) áp dụng cho toàn bộ booking trong khung 09:00–19:00. Ưu đãi Học sinh - Sinh viên (HSSV) giảm 20.000 VNĐ/người. Hai ưu đãi không được cộng dồn.
- **Tác động Schema:** Bảng `promotions`, `booking_price_snapshots`, và `player_payments`.
- **Đề xuất giải quyết cho MVP:**
  1. `booking_price_snapshots` liên kết `promotion_id` đại diện cho chương trình khuyến mãi cấp phiên chơi (ví dụ: Ưu đãi Thứ 3 giảm 30%).
  2. Các khoản giảm giá cá nhân (HSSV) được ghi nhận chi tiết tại từng bản ghi `player_payments` của người chơi đó.
  3. **Quy tắc bất biến:** Không bao giờ cộng dồn Ưu đãi Thứ 3 và HSSV trong cùng một phép tính.
  4. **Điểm mở (BLOCKED):** Nếu một phiên chơi rơi vào Thứ 3 (khung 09:00-19:00) và người chơi có thẻ HSSV, thứ tự ưu tiên chưa được xác nhận bởi Project Lead.
     - *Phương án 1:* Hệ thống tự chọn mức giảm có lợi nhất cho người chơi (Thứ 3 giảm 30% luôn lớn hơn 20.000 VNĐ đối với giá vé kịch bản sát >= 150.000 VNĐ).
     - *Phương án 2:* Mặc định áp dụng ưu đãi Thứ 3 cho cả phòng, vô hiệu hóa HSSV.
- **Trạng thái:** **`PROPOSED` cho kiến trúc; `BLOCKED` cho tie-breaker priority**.

---

### B3-06: Session Thay thế hay Nhiều buổi độc lập (Session Lifecycle)
- **Bối cảnh:** Khi phiên chơi bị hủy do Quản trò từ chối, phòng vật lý bảo trì, hoặc người chơi xin hoãn.
- **Tác động Schema:** Bảng `sessions` có quan hệ 1:N với `game_rooms`.
- **Đề xuất giải quyết cho MVP:** Một GameRoom có thể có nhiều bản ghi `sessions` theo lịch sử (trạng thái `CANCELLED`), nhưng tại một thời điểm chỉ tồn tại tối đa **01 Session ở trạng thái Active** (`SCHEDULED` hoặc `IN_PROGRESS`).
- **Căn cứ:** Mô hình 1:N vật lý bảo toàn toàn bộ lịch sử kế toán và lý do hủy phiên, trong khi ràng buộc nghiệp vụ đảm bảo chỉ 1 phiên chơi diễn ra.
- **Trạng thái:** **`ACCEPTED`**.

---

### B3-07: Giới hạn Session Active trong GameRoom
- **Bối cảnh:** Ngăn chặn tình trạng tạo nhiều phiên chơi song song cho cùng một phòng chơi ghép đội.
- **Tác động Schema:** Bảng `sessions`.
- **Đề xuất giải quyết cho MVP:** Backend Service bắt buộc kiểm tra điều kiện tiên quyết trong Transaction: `SELECT COUNT(1) FROM sessions WHERE game_room_id = :id AND status IN ('SCHEDULED', 'IN_PROGRESS') FOR UPDATE`. Nếu > 0, từ chối tạo phiên mới.
- **Căn cứ:** Ràng buộc Application Transaction Boundary (B6) kết hợp Row-Level Lock.
- **Trạng thái:** **`ACCEPTED`**.

---

### B3-08: Thực thể Giữ phòng Vật lý và Chống Double-Booking
- **Bối cảnh:** Hai phiên chơi khác nhau có thể yêu cầu đặt cùng một phòng vật lý tại cùng khung giờ.
- **Tác động Schema:** Bảng `physical_room_reservations`.
- **Đề xuất giải quyết cho MVP:**
  1. Giữ chỗ phòng vật lý được biểu diễn thành thực thể độc lập `physical_room_reservations` với các cột: `physical_room_id`, `session_id`, `reservation_date`, `start_time`, `end_time`, `status`.
  2. Chống double-booking thực hiện bằng **Pessimistic Locking**: Khi đặt phòng, mở transaction và thực hiện:
     ```sql
     SELECT id FROM physical_room_reservations
     WHERE physical_room_id = :room_id
       AND reservation_date = :date
       AND status IN ('RESERVED', 'CONFIRMED')
       AND (start_time < :new_end AND end_time > :new_start)
     FOR UPDATE;
     ```
     Nếu trả về >= 1 dòng, transaction lập tức rollback và trả về lỗi xung đột phòng.
  3. Bổ sung Covering Index `idx_prr_overlap_covering` theo đề xuất `CP-01`.
- **Căn cứ:** MySQL không hỗ trợ Exclude Constraint (như PostgreSQL), do đó Transaction + `FOR UPDATE` là giải pháp ACID tiêu chuẩn công nghiệp duy nhất ngăn chặn triệt để race condition.
- **Trạng thái:** **`ACCEPTED`**.

---

### B3-09: Quan hệ giữa Session và ScheduleProposal
- **Bối cảnh:** Khởi tạo Session từ đề xuất lịch hẹn đã được biểu quyết đồng thuận.
- **Tác động Schema:** Bảng `schedule_proposals` và `sessions`.
- **Đề xuất giải quyết cho MVP:** Khi tất cả thành viên trong phòng bỏ phiếu `AGREE` cho một `schedule_proposal`, proposal đó chuyển trạng thái sang `ACCEPTED`. Backend tự động tạo một bản ghi `sessions` với `scheduled_date = proposal.proposed_date`, `start_time = proposal.start_time`, `end_time = proposal.end_time`, và cập nhật `schedule_proposal.status = 'CONVERTED_TO_SESSION'`.
- **Căn cứ:** Quy trình khép kín từ thương lượng lịch đến hiện thực hóa phiên chơi.
- **Trạng thái:** **`ACCEPTED`**.

---

## 2. BẢO ĐẢM TOÀN VẸN CƠ SỞ DỮ LIỆU (DATABASE INVARIANTS)

### 2.1. Domain: Identity & Access
- Email là duy nhất tuyệt đối (`UNIQUE KEY users.uk_users_email`).
- Mật khẩu chỉ lưu dưới dạng băm an toàn (`password_hash` sinh bởi `password_hash(..., PASSWORD_DEFAULT)`).
- Không bao giờ trả về trường `password_hash` ra API response.
- Mỗi user có tối đa một profile người dùng (`UNIQUE KEY user_profiles.uk_user_profiles_user_id`).

### 2.2. Domain: Game & Catalog
- Ràng buộc sĩ số hợp lệ: `min_players <= max_players` (Ràng buộc cứng `chk_games_players`).
- Sĩ số tối thiểu >= 1 (`chk_games_min_players`).
- Kịch bản gán vào GameRoom phải là kịch bản đang active (`is_active = 1`).

### 2.3. Domain: GameRoom & Participants
- Sĩ số tham gia phòng không được vượt quá `max_players` của kịch bản.
- **Active Participation Invariant:** Một người dùng không được có nhiều hơn 01 lượt tham gia active trong cùng một phòng. Được bảo vệ vật lý qua Virtual Generated Column `active_user_id` và Unique Index `uk_rp_active_user`.
- Thành viên đã rời phòng (`LEFT`, `CANCELLED`) không được tự động phục hồi về `JOINED`. Việc tham gia lại (Rejoin) phải tạo một bản ghi mới với ID mới.
- Một người dùng làm Host không được tạo quá 3 phòng active đồng thời (`FORMING`, `FORMED`, `SCHEDULE_NEGOTIATING`).

### 2.4. Domain: DM Management
- Session chỉ được bắt đầu khi đã có DM phân công ở trạng thái `ACCEPTED`.
- Một phòng chơi tại một thời điểm chỉ có tối đa 01 DM active (`uk_da_active_room` qua generated column `active_assigned_room_id`).
- Bảo toàn toàn bộ lịch sử phân công DM dù DM trước đó đã từ chối (`REJECTED`) hoặc bị hủy (`CANCELLED`).

### 2.5. Domain: Scheduling & Venue
- Thời gian bắt đầu luôn nhỏ hơn thời gian kết thúc: `start_time < end_time` (`chk_prr_time_range` và `chk_sessions_time_range`).
- Tuyệt đối không trùng lịch phòng vật lý: Bảo vệ bằng Pessimistic Lock `FOR UPDATE` trong Transaction ranh giới đặt phòng.
- Giờ hoạt động cửa hàng: Từ 09:00 đến 23:00.

### 2.6. Domain: Pricing & Promotion
- **Tiền tệ:** 100% sử dụng số nguyên VNĐ (`BIGINT UNSIGNED`). Tuyệt đối cấm sử dụng `FLOAT` hoặc `DOUBLE`.
- **Tiền cọc cố định:** Đúng **50.000 VNĐ** / người chơi tham gia.
- **Ưu đãi Thứ 3:** Giảm 30% giá kịch bản, áp dụng khung 09:00–19:00 vào các ngày Thứ 3.
- **Ưu đãi HSSV:** Giảm 20.000 VNĐ / người chơi, áp dụng từ Thứ 2 đến Thứ 5 trong khung 09:00–19:00.
- **Quy tắc không cộng dồn (Non-Stackable Invariant):** Không được áp dụng đồng thời ưu đãi Thứ 3 và ưu đãi HSSV trên cùng một giao dịch.
- **Bảo toàn giá (Snapshot Immutability):** Toàn bộ giá trị tài chính được chốt tại `booking_price_snapshots`. Giá thay đổi trong danh mục sau thời điểm booking không làm thay đổi giá của các phiên đã đặt.
- **Xác thực HSSV:** Không tin tưởng checkbox client. Bắt buộc có cờ xác minh từ Admin/Staff hoặc tải ảnh thẻ hợp lệ.

### 2.7. Domain: Payment & Refund
- Tổng số tiền thanh toán thực nhận không được vượt quá số tiền phải trả theo snapshot.
- Số tiền hoàn lại không được vượt quá số tiền cọc/tiền chơi thực tế đã thu.
- **Quy tắc hoàn cọc 24 giờ:** Người chơi chỉ đủ điều kiện hoàn cọc khi gửi yêu cầu hủy/rời phòng trước giờ bắt đầu Session ít nhất 24 giờ.
- Hủy phòng dưới 24 giờ: Tiền cọc không được hoàn trả tự động (theo baseline V16); các ngoại lệ do sự cố cửa hàng bắt buộc có phê duyệt quyền Admin.
- **Tính Idempotent:** Cột `idempotency_key` trong `payment_attempts` và `refund_attempts` ngăn chặn triệt để giao dịch bị trừ tiền 2 lần khi mạng chập chờn.

### 2.8. Domain: Notification & Audit
- Lỗi gửi thông báo không bao giờ làm rollback giao dịch nghiệp vụ cốt lõi đã commit.
- Mọi thao tác đổi trạng thái nhạy cảm (duyệt cọc, hoàn tiền, đổi role, hủy phòng) đều ghi bản ghi bất biến vào `audit_logs`.
