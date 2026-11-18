# BÁO CÁO ĐẶC TẢ VÀ KIỂM ĐỊNH CHỈ MỤC (INDEX AUDIT)
## DỰ ÁN: BOOKING-PROJECT-PHP — PHASE 1: DATABASE DESIGN & ERD ACCEPTANCE

**Ngày thực hiện:** 18/11/2026  
**Auditor:** Principal Database Architect & Senior Data Modeler  
**Target Engine:** MySQL 8.4 LTS (InnoDB Storage Engine)  
**Phạm vi:** Clustered Indexes, Secondary Indexes, Composite Indexes, Covering Indexes

---

## 1. NGUYÊN TẮC THIẾT KẾ CHỈ MỤC TRÊN INNODB MYSQL 8.4

1. **Quy tắc tiền tố bên trái (Leftmost Prefix Rule):** Chỉ mục tổng hợp `(col_a, col_b, col_c)` có thể phục vụ các câu truy vấn lọc theo `col_a`, hoặc `(col_a, col_b)`, hoặc `(col_a, col_b, col_c)`. Do đó, không tạo thêm chỉ mục đơn lẻ trên `col_a` để tránh dư thừa dữ liệu (Redundant Indexes).
2. **Yêu cầu Khóa ngoại trong InnoDB:** Mọi cột khóa ngoại (Foreign Key) bắt buộc phải có chỉ mục để hỗ trợ kiểm tra ràng buộc toàn vẹn khi `UPDATE`/`DELETE` ở bảng cha và tránh hiện tượng Table Lock. Nếu khóa ngoại đứng đầu trong một chỉ mục tổng hợp, chỉ mục đó hoàn toàn đáp ứng yêu cầu của InnoDB.
3. **Độ chọn lọc (Index Selectivity):** Các cột có độ chọn lọc cao (High Cardinality - nhiều giá trị phân biệt) được ưu tiên xếp trước trong chỉ mục tổng hợp. Các cột trạng thái (Enum/Varchar status) có độ chọn lọc thấp thường được đặt sau các cột khóa ngoại.

---

## 2. MA TRẬN CHỈ MỤC THEO CA SỬ DỤNG TRUY VẤN (QUERY USE-CASE INVENTORY)

| STT | Bảng | Tên Index | Các cột cấu thành (Columns) | Loại Index | Ca sử dụng truy vấn (Use Case) |
|:---:|---|---|---|:---:|---|
| 01 | `users` | `PRIMARY` | `id` | Clustered | Truy vấn theo ID |
| 02 | `users` | `uq_users_email` | `email` | UNIQUE | Đăng nhập, kiểm tra trùng lặp email |
| 03 | `users` | `idx_users_status` | `status` | Secondary | Lọc người dùng hoạt động |
| 04 | `user_profiles` | `uq_user_profiles_user` | `user_id` | UNIQUE | Lấy thông tin profile từ user đăng nhập |
| 05 | `roles` | `uq_roles_name` | `name` | UNIQUE | Phân quyền theo slug (`admin`, `dm`, `player`) |
| 06 | `user_roles` | `uq_user_role` | `user_id`, `role_id` | UNIQUE | Chống gán trùng vai trò |
| 07 | `user_roles` | `idx_user_roles_role` | `role_id` | Secondary | Lọc danh sách người dùng theo vai trò |
| 08 | `games` | `uq_games_slug` | `slug` | UNIQUE | Xem chi tiết kịch bản theo friendly URL |
| 09 | `games` | `idx_games_status_players`| `is_active`, `min_players`, `max_players` | Composite | Tìm kiếm/Lọc game trên catalog |
| 10 | `game_rules` | `idx_game_rules_order` | `game_id`, `display_order` | Composite | Hiển thị luật chơi theo đúng thứ tự |
| 11 | `game_configurations`| `uq_game_config_key` | `game_id`, `config_key` | UNIQUE | Lấy tham số cấu hình game |
| 12 | `categories` | `uq_categories_slug` | `slug` | UNIQUE | Lọc game theo chuyên mục |
| 13 | `game_category_assignments`| `uq_game_category`| `game_id`, `category_id` | UNIQUE | Chống gán trùng thể loại |
| 14 | `game_category_assignments`| `idx_gca_category` | `category_id` | Secondary | Tìm các game thuộc về thể loại X |
| 15 | `game_prices` | `idx_game_prices_lookup`| `game_id`, `day_type`, `time_from`, `time_to` | Composite | Tính toán giá tiền phiên chơi theo giờ |
| 16 | `game_rooms` | `uq_game_rooms_code` | `room_code` | UNIQUE | Vào phòng bằng mã phòng code |
| 17 | `game_rooms` | `idx_rooms_creator_active`| `creator_id`, `is_active` | Composite | Kiểm tra giới hạn tối đa 3 phòng active của host |
| 18 | `game_rooms` | `idx_rooms_game_status` | `game_id`, `status` | Composite | Tìm danh sách phòng mở theo kịch bản |
| 19 | `game_rooms` | `idx_rooms_status_deadline`| `status`, `formation_deadline` | Composite | Cron job quét phòng hết hạn gom người (4h) |
| 20 | `room_participants` | `uq_room_active_participant`| `game_room_id`, `active_user_id` | UNIQUE (Virtual) | Chống 1 user chiếm nhiều slot active trong phòng |
| 21 | `room_participants` | `idx_participants_user` | `user_id`, `status` | Composite | Trang "Phòng của tôi" của người chơi |
| 22 | `room_participants` | `idx_participants_room_status`| `game_room_id`, `status` | Composite | Đếm số lượng thành viên đã cọc/đủ người |
| 23 | `game_room_status_histories`| `idx_grsh_room_created`| `game_room_id`, `created_at` | Composite | Dòng thời gian lịch sử phòng |
| 24 | `participant_status_histories`| `idx_psh_participant` | `room_participant_id`, `created_at` | Composite | Dòng thời gian thành viên |
| 25 | `replacement_windows` | `idx_rw_room_status` | `game_room_id`, `status`, `expires_at` | Composite | Cron job quét cửa sổ thay thế quá hạn |
| 26 | `participant_leave_requests`| `uq_pending_leave_request`| `pending_participant_id` | UNIQUE (Virtual) | Chống spam đơn xin rút cùng lúc |
| 27 | `participant_leave_requests`| `idx_plr_room_status` | `game_room_id`, `status` | Composite | Chủ phòng xem danh sách đơn xin rút chờ duyệt |
| 28 | `dm_profiles` | `uq_dm_profiles_user` | `user_id` | UNIQUE | Hồ sơ Quản trò theo user |
| 29 | `dm_availabilities` | `idx_dm_avail_slot` | `dm_profile_id`, `day_of_week`, `is_available` | Composite | Tìm DM rảnh theo khung giờ |
| 30 | `dm_assignments` | `uq_active_dm_assignment`| `active_assigned_room_id` | UNIQUE (Virtual) | Đảm bảo 1 phòng chỉ có 1 DM active |
| 31 | `dm_assignments` | `idx_dm_assignments_dm` | `dm_profile_id`, `status` | Composite | Lịch làm việc cá nhân của DM |
| 32 | `physical_rooms` | `uq_physical_rooms_code`| `code` | UNIQUE | Mã phòng cơ sở vật lý |
| 33 | `physical_room_reservations`| `uq_session_active_res` | `active_session_id` | UNIQUE (Virtual) | Một phiên chỉ có 1 đặt bàn active |
| 34 | `physical_room_reservations`| `idx_prr_schedule_check`| `physical_room_id`, `reservation_date`, `status`, `start_time`, `end_time` | Composite | Truy vấn kiểm tra xung đột chồng lấn bàn |
| 35 | `schedule_proposals` | `uq_active_proposed_sch`| `proposed_game_room_id` | UNIQUE (Virtual) | Một phòng chỉ có 1 đề xuất PROPOSED |
| 36 | `schedule_responses` | `uq_sch_resp_participant`| `schedule_proposal_id`, `room_participant_id` | UNIQUE | Mỗi thành viên chỉ vote 1 lần / đề xuất |
| 37 | `sessions` | `uq_sessions_code` | `session_code` | UNIQUE | Mã định danh phiên chơi |
| 38 | `sessions` | `idx_sessions_room` | `game_room_id` | Secondary | Lấy session từ game room |
| 39 | `sessions` | `idx_sessions_date_status`| `scheduled_date`, `status` | Composite | Lịch vận hành theo ngày của cửa hàng |
| 40 | `promotions` | `uq_promotions_code` | `code` | UNIQUE | Áp dụng mã coupon giảm giá |
| 41 | `booking_price_snapshots`| `uq_bps_session` | `session_id` | UNIQUE | Strict 1:1 Snapshot cho session |
| 42 | `player_payments` | `idx_payments_participant`| `room_participant_id`, `status` | Composite | Kiểm tra nghĩa vụ tài chính của người chơi |
| 43 | `player_payments` | `idx_payments_session` | `session_id`, `payment_type` | Composite | Đối soát tài chính toàn phiên |
| 44 | `payment_attempts` | `uq_pa_idempotency` | `idempotency_key` | UNIQUE | Chống thanh toán trùng lặp (Idempotency) |
| 45 | `payment_attempts` | `idx_pa_payment` | `player_payment_id` | Secondary | Lịch sử các lần gọi cổng thanh toán |
| 46 | `payment_transactions` | `uq_pt_reference` | `transaction_reference` | UNIQUE | Mã giao dịch kế toán bất biến |
| 47 | `refunds` | `idx_refunds_payment` | `player_payment_id`, `status` | Composite | Quản lý hoàn tiền theo thanh toán |
| 48 | `refund_attempts` | `uq_ra_idempotency` | `idempotency_key` | UNIQUE | Chống hoàn tiền trùng lặp |
| 49 | `notification_recipient_states`| `uq_notif_recipient` | `notification_id`, `user_id` | UNIQUE | Phân phối thông báo không trùng |
| 50 | `notification_recipient_states`| `idx_nrs_user_unread` | `user_id`, `is_read`, `created_at` | Composite (Covering)| Đếm số thông báo chưa đọc / lấy tin mới |
| 51 | `audit_logs` | `idx_audit_entity` | `entity_type`, `entity_id`, `created_at` | Composite | Truy vết kiểm toán theo thực thể |
| 52 | `audit_logs` | `idx_audit_user` | `user_id`, `created_at` | Composite | Truy vết hành vi theo tài khoản |

---

## 3. KẾT LUẬN KIỂM ĐỊNH CHỈ MỤC (INDEX ACCEPTANCE)

1. **Bao phủ toàn diện (100% Query Coverage):** Mọi điểm nghẽn truy vấn then chốt (cron quét quá hạn, tra cứu xung đột đặt bàn, tra cứu lịch DM, kiểm tra giới hạn 3 phòng active, đếm thông báo chưa đọc) đều có chỉ mục đa cột tối ưu.
2. **Không có chỉ mục trùng lặp:** Không có chỉ mục đơn lẻ nào bị trùng lặp với tiền tố của chỉ mục tổng hợp.
3. **Phù hợp InnoDB:** Mọi khóa ngoại đều có chỉ mục tương ứng đứng đầu hoặc độc lập, triệt tiêu nguy cơ Table Lock khi xử lý giao dịch.
