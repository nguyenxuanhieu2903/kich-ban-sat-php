# BÁO CÁO KIỂM KÊ VÀ ĐẶC TẢ RÀNG BUỘC KHÓA NGOẠI VẬT LÝ (FOREIGN KEY INVENTORY AUDIT)
## DỰ ÁN: BOOKING-PROJECT-PHP — PHASE 1: DATABASE DESIGN & ERD ACCEPTANCE

**Ngày thực hiện:** 18/11/2026  
**Auditor:** Principal Database Architect & Senior Data Modeler  
**Target Engine:** MySQL 8.4 LTS (InnoDB Storage Engine)  
**Phạm vi:** 34 Bảng — 48 Ràng buộc Khóa ngoại Vật lý (Physical FK Constraints)

---

## 1. NGUYÊN TẮC QUẢN TRỊ TOÀN VẸN THAM CHIẾU (REFERENTIAL INTEGRITY RULES)

Thiết kế khóa ngoại trên MySQL 8.4 LTS InnoDB tuân thủ nghiêm ngặt các tiêu chuẩn kiến trúc:
1. **Chính sách ON DELETE:**
   - **`CASCADE`**: Chỉ áp dụng cho các thực thể phụ thuộc chặt chẽ (Dependent Children / Weak Entities / Composition), khi bản ghi cha bị xóa thì dữ liệu chi tiết không còn ý nghĩa (ví dụ: `game_rules` khi xóa `games`, `room_participants` khi xóa `game_rooms`, `payment_attempts` khi xóa `player_payments`).
   - **`RESTRICT` (hoặc `NO ACTION`):** Áp dụng bắt buộc cho các thực thể tài chính, giao dịch kiểm toán hoặc dữ liệu độc lập. Nghiêm cấm xóa bảng cha nếu đang có dữ liệu tham chiếu (ví dụ: cấm xóa `users` nếu đang là chủ phòng `game_rooms`, cấm xóa `player_payments` nếu đã có `refunds`, cấm xóa `sessions` nếu đang có thanh toán).
   - **`SET NULL`**: Áp dụng cho các khóa ngoại tùy chọn (Optional FK) biểu diễn tác nhân kiểm toán hoặc thông tin tham chiếu không mang tính sống còn (ví dụ: `changed_by_user_id`, `reviewed_by_user_id`, `promotion_id`).
2. **Chính sách ON UPDATE:**
   - Thống nhất áp dụng **`CASCADE`** (hoặc `RESTRICT`) trên toàn bộ hệ thống để bảo đảm tính nhất quán nếu khóa chính kiểu `BIGINT` có sự dịch chuyển (dù PK luôn là bất biến).
3. **Quy tắc Composite Foreign Key vs Single-column Foreign Key:**
   - Khóa ngoại tổng hợp (Composite FK) được tính là 01 ràng buộc (01 Constraint Object) trỏ tới một Composite Unique/PK ở bảng cha. Trong mô hình 34 bảng hiện tại, toàn bộ các quan hệ tham chiếu đều trỏ về Surrogate Primary Key `id` của bảng cha, nên mỗi ràng buộc khóa ngoại là Single-Column Constraint, bảo đảm hiệu năng B-Tree tối ưu.

---

## 2. MA TRẬN 48 RÀNG BUỘC KHÓA NGOẠI VẬT LÝ (PHYSICAL FOREIGN KEY MATRIX)

| STT | Source Table (Bảng con) | FK Column(s) | Referenced Table (Bảng cha) | Ref Column(s) | ON DELETE | ON UPDATE | Rationale & Invariant |
|:---:|---|---|---|---|:---:|:---:|---|
| 01 | `user_profiles` | `user_id` | `users` | `id` | **CASCADE** | CASCADE | Xóa tài khoản xóa luôn hồ sơ cá nhân |
| 02 | `user_roles` | `user_id` | `users` | `id` | **CASCADE** | CASCADE | Xóa user hủy luôn gán quyền |
| 03 | `user_roles` | `role_id` | `roles` | `id` | **RESTRICT**| CASCADE | Không cho phép xóa vai trò nếu đang được cấp |
| 04 | `game_rules` | `game_id` | `games` | `id` | **CASCADE** | CASCADE | Luật chơi thuộc về game gốc |
| 05 | `game_configurations` | `game_id` | `games` | `id` | **CASCADE** | CASCADE | Cấu hình gắn liền với game |
| 06 | `game_category_assignments`| `game_id` | `games` | `id` | **CASCADE** | CASCADE | Hủy gán phân loại khi xóa game |
| 07 | `game_category_assignments`| `category_id` | `categories` | `id` | **RESTRICT**| CASCADE | Không xóa danh mục nếu đang có game thuộc về nó |
| 08 | `game_prices` | `game_id` | `games` | `id` | **RESTRICT**| CASCADE | Bảo vệ lịch sử giá game cho kế toán |
| 09 | `game_rooms` | `creator_id` | `users` | `id` | **RESTRICT**| CASCADE | Không được xóa tài khoản người dùng đang làm chủ phòng |
| 10 | `game_rooms` | `game_id` | `games` | `id` | **RESTRICT**| CASCADE | Không cho phép xóa game khi đang có phòng mở |
| 11 | `room_participants` | `game_room_id` | `game_rooms` | `id` | **CASCADE** | CASCADE | Xóa phòng dọn sạch danh sách đăng ký |
| 12 | `room_participants` | `user_id` | `users` | `id` | **RESTRICT**| CASCADE | Người dùng tham gia không bị xóa vật lý bừa bãi |
| 13 | `game_room_status_histories`| `game_room_id` | `game_rooms` | `id` | **CASCADE** | CASCADE | Lịch sử gắn với vòng đời phòng |
| 14 | `game_room_status_histories`| `changed_by_user_id` | `users` | `id` | **SET NULL**| CASCADE | Giữ vết lịch sử dù tài khoản nhân sự bị xóa |
| 15 | `participant_status_histories`| `room_participant_id`| `room_participants`| `id`| **CASCADE** | CASCADE | Lịch sử gắn với bản ghi người tham gia |
| 16 | `replacement_windows` | `game_room_id` | `game_rooms` | `id` | **CASCADE** | CASCADE | Cửa sổ thay thế thuộc phòng |
| 17 | `replacement_windows` | `original_participant_id`| `room_participants`| `id`| **RESTRICT**| CASCADE | Bảo toàn vết thành viên gốc xin rút |
| 18 | `replacement_windows` | `replacement_participant_id`| `room_participants`| `id`| **SET NULL**| CASCADE | Thành viên thế chỗ có thể để trống khi mới mở |
| 19 | `participant_leave_requests`| `room_participant_id`| `room_participants`| `id`| **CASCADE** | CASCADE | Đơn xin rút gắn với người chơi |
| 20 | `participant_leave_requests`| `game_room_id` | `game_rooms` | `id` | **CASCADE** | CASCADE | Đơn xin rút gắn với phòng |
| 21 | `participant_leave_requests`| `reviewed_by_user_id`| `users` | `id` | **SET NULL**| CASCADE | Người duyệt có thể NULL khi đơn mới tạo |
| 22 | `dm_profiles` | `user_id` | `users` | `id` | **CASCADE** | CASCADE | Xóa tài khoản xóa hồ sơ DM |
| 23 | `dm_availabilities` | `dm_profile_id` | `dm_profiles` | `id` | **CASCADE** | CASCADE | Lịch rảnh thuộc về DM |
| 24 | `dm_assignments` | `game_room_id` | `game_rooms` | `id` | **CASCADE** | CASCADE | Phân công DM gắn với phòng |
| 25 | `dm_assignments` | `dm_profile_id` | `dm_profiles` | `id` | **RESTRICT**| CASCADE | Không xóa DM đang được phân công nhận phòng |
| 26 | `schedule_proposals` | `game_room_id` | `game_rooms` | `id` | **CASCADE** | CASCADE | Đề xuất lịch gắn liền với phòng |
| 27 | `schedule_proposals` | `proposed_by_user_id`| `users` | `id` | **RESTRICT**| CASCADE | Giữ thông tin người đề xuất lịch |
| 28 | `schedule_responses` | `schedule_proposal_id`| `schedule_proposals`| `id`| **CASCADE** | CASCADE | Xóa đề xuất xóa luôn biểu quyết |
| 29 | `schedule_responses` | `room_participant_id`| `room_participants`| `id`| **CASCADE** | CASCADE | Xóa thành viên xóa biểu quyết |
| 30 | `sessions` | `game_room_id` | `game_rooms` | `id` | **RESTRICT**| CASCADE | Phiên chơi vật lý bảo vệ phòng gốc |
| 31 | `sessions` | `dm_assignment_id` | `dm_assignments` | `id` | **SET NULL**| CASCADE | DM điều phối có thể điều chỉnh hoặc NULL |
| 32 | `physical_room_reservations`| `physical_room_id` | `physical_rooms` | `id` | **RESTRICT**| CASCADE | Không xóa phòng vật lý đang có khách đặt |
| 33 | `physical_room_reservations`| `session_id` | `sessions` | `id` | **CASCADE** | CASCADE | Hủy phiên chơi giải phóng bàn đặt |
| 34 | `booking_price_snapshots` | `session_id` | `sessions` | `id` | **RESTRICT**| CASCADE | Bắt buộc bảo vệ bản ghi tài chính đã snapshot |
| 35 | `booking_price_snapshots` | `game_price_id` | `game_prices` | `id` | **SET NULL**| CASCADE | Biểu giá gốc có thể điều chỉnh mà không phá vỡ snapshot |
| 36 | `booking_price_snapshots` | `promotion_id` | `promotions` | `id` | **SET NULL**| CASCADE | Khuyến mãi gốc hết hạn không làm hỏng snapshot |
| 37 | `player_payments` | `room_participant_id`| `room_participants`| `id`| **RESTRICT**| CASCADE | Tuyệt đối không xóa người chơi đã phát sinh thanh toán |
| 38 | `player_payments` | `session_id` | `sessions` | `id` | **RESTRICT**| CASCADE | Tuyệt đối không xóa phiên chơi đã có giao dịch tiền |
| 39 | `payment_attempts` | `player_payment_id` | `player_payments` | `id` | **CASCADE** | CASCADE | Xóa khoản thanh toán (nếu nháp) xóa các attempt |
| 40 | `payment_transactions` | `payment_attempt_id`| `payment_attempts` | `id` | **RESTRICT**| CASCADE | Bản ghi kế toán thành công là bất biến (Immutable Ledger)|
| 41 | `refunds` | `player_payment_id` | `player_payments` | `id` | **RESTRICT**| CASCADE | Không xóa payment nếu đang có refund |
| 42 | `refunds` | `room_participant_id`| `room_participants`| `id`| **RESTRICT**| CASCADE | Không xóa người tham gia đang được hoàn tiền |
| 43 | `refunds` | `processed_by_user_id`| `users` | `id` | **SET NULL**| CASCADE | Kế toán duyệt hoàn tiền có thể để trống khi PENDING |
| 44 | `refund_attempts` | `refund_id` | `refunds` | `id` | **CASCADE** | CASCADE | Lần thử hoàn tiền thuộc về bản ghi hoàn tiền |
| 45 | `notifications` | `sender_id` | `users` | `id` | **SET NULL**| CASCADE | Người gửi có thể là SYSTEM (NULL) |
| 46 | `notification_recipient_states`| `notification_id`| `notifications` | `id` | **CASCADE** | CASCADE | Xóa thông báo xóa luôn trạng thái đọc |
| 47 | `notification_recipient_states`| `user_id` | `users` | `id` | **CASCADE** | CASCADE | Xóa user xóa các trạng thái nhận tin |
| 48 | `audit_logs` | `user_id` | `users` | `id` | **SET NULL**| CASCADE | Vết audit vẫn tồn tại ngay cả khi tài khoản bị xóa |

---

## 3. PHÂN TÍCH RỦI RO CHU KỲ (CIRCULAR REFERENCES) VÀ DEADLOCK TRÊN INNODB

1. **Chu kỳ lặp (Circular Dependency Analysis):**
   - Giữa `sessions` và `physical_room_reservations`: `physical_room_reservations` giữ `session_id FK -> sessions.id`. Không tạo FK ngược lại từ `sessions` sang `physical_room_reservations` để tránh vòng lặp tham chiếu hai chiều gây lỗi khi INSERT hoặc CASCADE DELETE.
   - Giữa `sessions` và `dm_assignments`: `sessions` giữ `dm_assignment_id FK -> dm_assignments.id` với `ON DELETE SET NULL`. `dm_assignments` chỉ giữ `game_room_id`, không tạo chu kỳ trực tiếp.
2. **Khuyến nghị khóa ngoại cho Phase 2 (Migration Script):**
   - Phải khởi tạo các bảng độc lập (Lookup/Master) trước: `users`, `roles`, `categories`, `games`, `physical_rooms`, `promotions`.
   - Tiếp theo tạo các bảng trung gian và master nghiệp vụ: `game_prices`, `game_rooms`, `dm_profiles`.
   - Cuối cùng tạo các bảng detail, giao dịch tài chính và audit: `sessions`, `player_payments`, `refunds`, `audit_logs`.
   - Trong quá trình chạy seeder hoặc migration, luôn chạy trong `SET FOREIGN_KEY_CHECKS = 0;` trước khi cấu trúc xong toàn bộ bảng để tránh xung đột thứ tự định nghĩa.
