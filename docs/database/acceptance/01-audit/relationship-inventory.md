# BÁO CÁO THỐNG KÊ QUAN HỆ NGHIỆP VỤ LOGICAL (RELATIONSHIP INVENTORY AUDIT)
## DỰ ÁN: BOOKING-PROJECT-PHP — PHASE 1: DATABASE DESIGN & ERD ACCEPTANCE

**Ngày thực hiện:** 18/11/2026  
**Auditor:** Principal Database Architect & Senior Data Modeler  
**Target Engine:** MySQL 8.4 LTS (InnoDB Storage Engine)  
**Phạm vi:** 34 Bảng — 8 Bounded Contexts

---

## 1. NGUYÊN TẮC PHÂN BIỆT LOGICAL RELATIONSHIP VÀ PHYSICAL FOREIGN KEY

Trong quy chuẩn thiết kế cơ sở dữ liệu quan hệ chuyên nghiệp:
- **Logical Relationship (Quan hệ nghiệp vụ mức quan niệm/logic):** Mô tả liên kết ngữ nghĩa giữa hai thực thể trong thế giới thực hoặc miền nghiệp vụ (Domain Model), bao gồm Bản số (Cardinality: 1:1, 1:N, N:M) và Tính bắt buộc (Optionality: Mandatory, Optional).
- **Physical Foreign Key (Ràng buộc khóa ngoại vật lý):** Là cấu trúc thực thi bảo toàn toàn vẹn dữ liệu cụ thể trong hệ quản trị CSDL (InnoDB Constraint). Một quan hệ N:M mức logic luôn được triển khai vật lý bằng 01 bảng liên kết trung gian (Junction/Pivot table) với 02 Physical Foreign Keys riêng biệt. Khóa ngoại có thể là Single Column hoặc Composite Columns.

---

## 2. TỔNG HỢP SỐ LIỆU QUAN HỆ LOGICAL TRONG HỆ THỐNG

### A. Thống kê theo Cardinality (Bản số quan hệ)
- **Tổng số quan hệ nghiệp vụ logic (Logical Relationships):** **48 quan hệ**
- **Quan hệ 1:1 (One-to-One):** **5 quan hệ**
  - `users` — `user_profiles`
  - `users` — `dm_profiles`
  - `sessions` — `booking_price_snapshots`
  - `sessions` — `physical_room_reservations` (Active reservation)
  - `payment_attempts` — `payment_transactions`
- **Quan hệ 1:N (One-to-Many):** **40 quan hệ** (Chi tiết ở bảng bên dưới)
- **Quan hệ N:M (Many-to-Many):** **3 quan hệ nghiệp vụ cốt lõi**
  - `users` — `roles` (thông qua bảng liên kết `user_roles`)
  - `games` — `categories` (thông qua bảng liên kết `game_category_assignments`)
  - `game_rooms` — `users` (thông qua bảng liên kết thành viên `room_participants`)

### B. Thống kê theo Optionality (Tính bắt buộc)
- **Mandatory Relationships (Bắt buộc):** Các liên kết mà thực thể phụ thuộc không thể tồn tại độc lập nếu thiếu thực thể cha (ví dụ: `game_room_status_histories` bắt buộc phải có `game_rooms`).
- **Optional Relationships (Tùy chọn / Có thể NULL):** Các liên kết hỗ trợ giá trị `NULL` trong chu kỳ nghiệp vụ (ví dụ: `users.id` trong `audit_logs` khi thao tác bởi khách/hệ thống ngầm, `reviewed_by` trong `participant_leave_requests` khi mới tạo ở trạng thái PENDING, `dm_assignments.id` trong `sessions` khi phòng chưa được điều phối DM).

---

## 3. BẢNG CHI TIẾT 48 QUAN HỆ NGHIỆP VỤ LOGICAL (LOGICAL RELATIONSHIP MATRIX)

| Mã Q/H | Source Entity (Parent) | Target Entity (Child) | Cardinality | Source Optionality | Target Optionality | Bản chất nghiệp vụ |
|:---:|---|---|:---:|:---:|:---:|---|
| **R01** | `users` | `user_profiles` | 1:1 | Optional | Mandatory | Hồ sơ mở rộng gắn với tài khoản người dùng |
| **R02** | `users` | `user_roles` | 1:N | Optional | Mandatory | Người dùng được cấp vai trò hệ thống |
| **R03** | `roles` | `user_roles` | 1:N | Optional | Mandatory | Vai trò được phân bổ cho các tài khoản |
| **R04** | `games` | `game_rules` | 1:N | Optional | Mandatory | Kịch bản có danh sách quy tắc chơi chi tiết |
| **R05** | `games` | `game_configurations` | 1:N | Optional | Mandatory | Tham số kỹ thuật và cấu hình kịch bản |
| **R06** | `games` | `game_category_assignments`| 1:N | Optional | Mandatory | Kịch bản thuộc các thể loại khác nhau |
| **R07** | `categories` | `game_category_assignments`| 1:N | Optional | Mandatory | Thể loại gom nhóm các kịch bản |
| **R08** | `games` | `game_prices` | 1:N | Optional | Mandatory | Biểu giá theo khung giờ áp dụng cho game |
| **R09** | `games` | `game_rooms` | 1:N | Optional | Mandatory | Phòng chơi ghép đội chọn kịch bản nào |
| **R10** | `users` (creator) | `game_rooms` | 1:N | Optional | Mandatory | Người tạo / chủ trì phòng chơi |
| **R11** | `game_rooms` | `room_participants` | 1:N | Optional | Mandatory | Thành viên tham gia vào phòng chơi |
| **R12** | `users` (player) | `room_participants` | 1:N | Optional | Mandatory | Người dùng đăng ký vào phòng |
| **R13** | `game_rooms` | `game_room_status_histories` | 1:N | Optional | Mandatory | Lịch sử đổi trạng thái phòng |
| **R14** | `users` (actor) | `game_room_status_histories` | 1:N | Optional | Optional | Tác nhân thực hiện chuyển trạng thái phòng |
| **R15** | `room_participants`| `participant_status_histories`| 1:N | Optional | Mandatory | Lịch sử đổi trạng thái của thành viên |
| **R16** | `game_rooms` | `replacement_windows` | 1:N | Optional | Mandatory | Cửa sổ tìm người thay thế trong phòng |
| **R17** | `room_participants`| `replacement_windows` (out) | 1:N | Optional | Mandatory | Thành viên gốc cần tìm người thay thế |
| **R18** | `room_participants`| `replacement_windows` (in) | 1:N | Optional | Optional | Thành viên mới vào thế chỗ (khi FILLED) |
| **R19** | `room_participants`| `participant_leave_requests` | 1:N | Optional | Mandatory | Yêu cầu xin rời phòng của thành viên |
| **R20** | `game_rooms` | `participant_leave_requests` | 1:N | Optional | Mandatory | Phòng tiếp nhận yêu cầu xin rút |
| **R21** | `users` (reviewer)| `participant_leave_requests` | 1:N | Optional | Optional | Quản trị viên/chủ phòng duyệt yêu cầu |
| **R22** | `users` | `dm_profiles` | 1:1 | Optional | Mandatory | Hồ sơ năng lực Quản trò (DM) |
| **R23** | `dm_profiles` | `dm_availabilities` | 1:N | Optional | Mandatory | Khung giờ rảnh theo tuần/ngày của DM |
| **R24** | `dm_profiles` | `dm_assignments` | 1:N | Optional | Mandatory | Phân công DM nhận phòng chơi |
| **R25** | `game_rooms` | `dm_assignments` | 1:N | Optional | Mandatory | Lịch sử phân công DM cho phòng chơi |
| **R26** | `game_rooms` | `schedule_proposals` | 1:N | Optional | Mandatory | Đề xuất khung giờ chơi của phòng |
| **R27** | `users` (proposer)| `schedule_proposals` | 1:N | Optional | Mandatory | Thành viên đưa ra đề xuất lịch |
| **R28** | `schedule_proposals`| `schedule_responses` | 1:N | Optional | Mandatory | Biểu quyết của từng thành viên với đề xuất |
| **R29** | `room_participants`| `schedule_responses` | 1:N | Optional | Mandatory | Ý kiến đồng thuận hay từ chối lịch |
| **R30** | `game_rooms` | `sessions` | 1:1 (or 1:N)| Optional | Mandatory | Phiên chơi vật lý sinh ra từ phòng chơi |
| **R31** | `dm_assignments` | `sessions` | 1:1 | Optional | Optional | Gán phân công DM vào phiên chơi chính thức |
| **R32** | `physical_rooms` | `physical_room_reservations`| 1:N | Optional | Mandatory | Đặt bàn tại phòng vật lý cơ sở |
| **R33** | `sessions` | `physical_room_reservations`| 1:1 | Optional | Mandatory | Phiên chơi liên kết với bản giữ chỗ bàn |
| **R34** | `sessions` | `booking_price_snapshots` | 1:1 | Optional | Mandatory | Snapshot tài chính đóng băng cho phiên |
| **R35** | `game_prices` | `booking_price_snapshots` | 1:N | Optional | Optional | Biểu giá gốc được tham chiếu tại thời điểm chốt |
| **R36** | `promotions` | `booking_price_snapshots` | 1:N | Optional | Optional | Mã khuyến mãi áp dụng cho phiên chơi |
| **R37** | `room_participants`| `player_payments` | 1:N | Optional | Mandatory | Các nghĩa vụ thanh toán của người chơi (cọc/còn lại)|
| **R38** | `sessions` | `player_payments` | 1:N | Optional | Mandatory | Khoản thanh toán thuộc phiên chơi nào |
| **R39** | `player_payments` | `payment_attempts` | 1:N | Optional | Mandatory | Các lần gọi thanh toán (hỗ trợ thử lại/idempotency)|
| **R40** | `payment_attempts`| `payment_transactions` | 1:1 | Optional | Mandatory | Giao dịch kế toán ghi nhận thành công |
| **R41** | `player_payments` | `refunds` | 1:N | Optional | Mandatory | Hoàn tiền phát sinh từ khoản thanh toán gốc |
| **R42** | `room_participants`| `refunds` | 1:N | Optional | Mandatory | Người tham gia thụ hưởng khoản hoàn tiền |
| **R43** | `users` (reviewer)| `refunds` | 1:N | Optional | Optional | Nhân viên kế toán/Admin duyệt hoàn tiền |
| **R44** | `refunds` | `refund_attempts` | 1:N | Optional | Mandatory | Lần thực thi giải ngân hoàn tiền |
| **R45** | `notifications` | `notification_recipient_states`| 1:N | Optional | Mandatory | Phân phối thông báo tới từng người nhận |
| **R46** | `users` (receiver)| `notification_recipient_states`| 1:N | Optional | Mandatory | Trạng thái đọc của người nhận |
| **R47** | `users` (sender) | `notifications` | 1:N | Optional | Optional | Người khởi tạo thông báo (NULL nếu System)|
| **R48** | `users` (actor) | `audit_logs` | 1:N | Optional | Optional | Người dùng thực hiện hành động kiểm toán (NULL nếu System)|

---

## 4. MA TRẬN LIÊN KẾT LIÊN BOUNDED CONTEXT (CROSS-CONTEXT INTERACTIONS)

Các quan hệ kết nối giữa 8 Bounded Context bảo đảm nguyên tắc Low Coupling & High Cohesion:
1. **Identity & Access -> GameRoom Management:** `users` -> `game_rooms.creator_id`, `users` -> `room_participants.user_id`.
2. **Identity & Access -> DM Management:** `users` -> `dm_profiles.user_id`.
3. **Identity & Access -> Notification & Audit:** `users` -> `notifications.sender_id`, `users` -> `notification_recipient_states.user_id`, `users` -> `audit_logs.user_id`.
4. **Game Catalog -> GameRoom Management:** `games` -> `game_rooms.game_id`.
5. **Game Catalog -> Pricing & Promotion:** `games` -> `game_prices.game_id`.
6. **GameRoom Management -> DM Management:** `game_rooms` -> `dm_assignments.game_room_id`.
7. **GameRoom Management -> Venue & Scheduling:** `game_rooms` -> `schedule_proposals.game_room_id`, `game_rooms` -> `sessions.game_room_id`.
8. **DM Management -> Venue & Scheduling:** `dm_assignments` -> `sessions.dm_assignment_id`.
9. **Venue & Scheduling -> Pricing & Promotion:** `sessions` -> `booking_price_snapshots.session_id`.
10. **GameRoom Management & Venue -> Payment & Refund:** `room_participants` -> `player_payments.room_participant_id`, `sessions` -> `player_payments.session_id`.
11. **Pricing & Promotion -> Payment & Refund:** `booking_price_snapshots` xác định nghĩa vụ cọc 50.000 VNĐ cho `player_payments`.
