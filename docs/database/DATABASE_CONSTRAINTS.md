# BÁO CÁO RÀNG BUỘC VÀ CHỈ MỤC CƠ SỞ DỮ LIỆU
## (DATABASE CONSTRAINTS & INDEXES SPECIFICATION)
### DỰ ÁN: WEBSITE BOOKING – KỊCH BẢN SÁT (JUBENSHA / MURDER MYSTERY) V16

- **Ngày ban hành:** 20/11/2026
- **Auditor:** Senior Database Performance & Security Engineer
- **Working Directory:** `d:\LapTrinhWeb\Project\Booking_kich-ban-sat_PHP\docs\database`
- **Tài liệu:** `docs/database/DATABASE_CONSTRAINTS.md`

---

## 1. TỔNG HỢP CÁC LOẠI RÀNG BUỘC (CONSTRAINTS SUMMARY)

| Loại ràng buộc | Số lượng | Nguyên tắc thực thi và Kiểm định |
|---|---:|---|
| **Primary Keys (PK)** | **34** | 100% Clustered B-Tree Key `id BIGINT UNSIGNED AUTO_INCREMENT`. |
| **Foreign Keys (FK)** | **48** | Khóa ngoại đơn cột chuẩn hóa trỏ về PK bảng cha, thiết lập nghiêm ngặt `ON DELETE` (`CASCADE`, `RESTRICT`, `SET NULL`) và `ON UPDATE CASCADE`. |
| **Unique Keys (UK)** | **25** | Gồm 20 Natural Unique Keys và 5 Virtual Conditional Unique Keys qua Virtual Generated Columns. |
| **Check Constraints (CHK)** | **20** | Ràng buộc miền giá trị hợp lệ: sĩ số min <= max, tiền > 0, thời gian bắt đầu < kết thúc. |
| **Virtual Generated Columns** | **05** | Kỹ thuật bù đắp thiếu hụt Partial Unique Index trên MySQL InnoDB. |
| **Secondary Indexes** | **104** | Chỉ mục tìm kiếm, chỉ mục phủ (Covering), composite indexes hỗ trợ tối đa truy vấn. |

---

## 2. RÀNG BUỘC TOÀN VẸN THAM CHIẾU (FOREIGN KEYS & CASCADE POLICY)

### 2.1. Nhóm `ON DELETE RESTRICT` (Bảo vệ dữ liệu tài chính, master và quan hệ sống còn)
Ngăn chặn tuyệt đối việc xóa thực thể cha khi còn thực thể con phụ thuộc:
- `user_roles.role_id -> roles.id`: Không được xóa Role nếu còn người dùng mang role đó.
- `game_category_assignments.category_id -> categories.id`: Không xóa thể loại khi còn game gắn.
- `game_prices.game_id -> games.id`: Không xóa Game khi còn biểu giá lịch sử.
- `game_rooms.creator_id -> users.id`: Không xóa tài khoản nếu đang là Creator phòng chơi.
- `game_rooms.game_id -> games.id`: Không xóa Game nếu còn GameRoom tham chiếu.
- `room_participants.user_id -> users.id`: Không xóa tài khoản nếu đang tham gia phòng.
- `dm_assignments.dm_profile_id -> dm_profiles.id`: Không xóa profile DM nếu đang có phân công.
- `sessions.game_room_id -> game_rooms.id`: Không xóa GameRoom nếu đã hiện thực hóa Session.
- `physical_room_reservations.physical_room_id -> physical_rooms.id`: Không xóa phòng vật lý nếu có đặt chỗ.
- `booking_price_snapshots.session_id -> sessions.id`: Không xóa Session nếu đã chốt snapshot giá.
- `player_payments.room_participant_id -> room_participants.id` & `player_payments.session_id -> sessions.id`: Bảo toàn chứng từ công nợ.
- `payment_transactions.payment_attempt_id -> payment_attempts.id`: Bảo toàn sổ cái giao dịch kế toán.
- `refunds.player_payment_id -> player_payments.id`: Bảo toàn yêu cầu hoàn tiền.

### 2.2. Nhóm `ON DELETE CASCADE` (Dữ liệu thành phần / Chi tiết phụ thuộc chặt chẽ)
Tự động xóa sạch dữ liệu chi tiết khi thực thể cha bị xóa hợp lệ:
- `user_profiles.user_id -> users.id`
- `user_roles.user_id -> users.id`
- `game_rules.game_id -> games.id`
- `game_configurations.game_id -> games.id`
- `game_category_assignments.game_id -> games.id`
- `room_participants.game_room_id -> game_rooms.id`
- `game_room_status_histories.game_room_id -> game_rooms.id`
- `participant_status_histories.room_participant_id -> room_participants.id`
- `replacement_windows.game_room_id -> game_rooms.id`
- `participant_leave_requests.room_participant_id -> room_participants.id`
- `dm_availabilities.dm_profile_id -> dm_profiles.id`
- `schedule_responses.schedule_proposal_id -> schedule_proposals.id`
- `physical_room_reservations.session_id -> sessions.id`
- `payment_attempts.player_payment_id -> player_payments.id`
- `refund_attempts.refund_id -> refunds.id`
- `notification_recipient_states.notification_id -> notifications.id`

### 2.3. Nhóm `ON DELETE SET NULL` (Dữ liệu kiểm toán và quan hệ tùy chọn)
Giữ nguyên bản ghi lịch sử, chỉ gỡ tham chiếu khi đối tượng cha bị hủy:
- `game_room_status_histories.changed_by_user_id -> users.id`
- `participant_leave_requests.reviewed_by_user_id -> users.id`
- `sessions.dm_assignment_id -> dm_assignments.id`
- `booking_price_snapshots.game_price_id -> game_prices.id`
- `booking_price_snapshots.promotion_id -> promotions.id`
- `refunds.processed_by_user_id -> users.id`
- `notifications.sender_id -> users.id`
- `audit_logs.user_id -> users.id`

---

## 3. RÀNG BUỘC DUY NHẤT CÓ ĐIỀU KIỆN (5 VIRTUAL GENERATED COLUMNS)

MySQL 8.0/8.4 không hỗ trợ cú pháp `CREATE UNIQUE INDEX ... WHERE condition` (Partial Unique Index như PostgreSQL). Do đó, dự án sử dụng Virtual Generated Column: nếu thỏa mãn điều kiện active thì sinh ra ID, ngược lại trả về `NULL`. Vì chuẩn SQL coi `NULL != NULL`, các dòng không active sẽ không bao giờ gây lỗi duplicate key.

| Bảng | Cột Virtual Generated | Biểu thức tính toán | Ràng buộc Unique Key | Ý nghĩa nghiệp vụ |
|---|---|---|---|---|
| `room_participants` | `active_user_id` | `IF(status IN ('JOINED', 'CONFIRMED'), user_id, NULL)` | `uk_rp_active_user` (`game_room_id`, `active_user_id`) | **Chống spam:** 1 User chỉ có tối đa 01 lượt tham gia active/room. |
| `schedule_proposals` | `proposed_game_room_id` | `IF(status = 'PROPOSED', game_room_id, NULL)` | `uk_sp_proposed_room` (`proposed_game_room_id`) | 1 GameRoom tại một thời điểm chỉ có tối đa 01 đề xuất lịch đang chờ duyệt. |
| `dm_assignments` | `active_assigned_room_id`| `IF(status IN ('PENDING', 'ACCEPTED'), game_room_id, NULL)` | `uk_da_active_room` (`active_assigned_room_id`) | 1 GameRoom chỉ có tối đa 01 DM đang được phân công/nhận phòng. |
| `participant_leave_requests` | `pending_participant_id` | `IF(status = 'PENDING', room_participant_id, NULL)` | `uk_plr_pending_participant` (`pending_participant_id`) | 1 người chơi chỉ được có tối đa 01 đơn xin rút phòng đang chờ duyệt. |
| `physical_room_reservations` | `active_session_id` | `IF(status IN ('RESERVED', 'CONFIRMED'), session_id, NULL)` | `uk_prr_active_session` (`active_session_id`) | 1 Session chỉ có tối đa 01 giữ chỗ phòng vật lý active. |

---

## 4. DANH MỤC 20 CHECK CONSTRAINTS

1. `chk_games_players`: `min_players <= max_players`
2. `chk_games_min_players`: `min_players >= 1`
3. `chk_games_duration`: `duration_minutes > 0`
4. `chk_gc_character_count`: `character_count >= 1`
5. `chk_dma_time_range`: `start_time < end_time`
6. `chk_dma_dow`: `day_of_week BETWEEN 1 AND 7`
7. `chk_sp_time_range`: `start_time < end_time`
8. `chk_sessions_time_range`: `start_time < end_time`
9. `chk_prr_time_range`: `start_time < end_time`
10. `chk_pr_capacity`: `capacity >= 1`
11. `chk_gp_price`: `base_price_vnd > 0`
12. `chk_promotions_discount`: `discount_value > 0`
13. `chk_promotions_dow`: `applicable_day_of_week IS NULL OR (applicable_day_of_week BETWEEN 1 AND 7)`
14. `chk_bps_base_price`: `base_price_vnd > 0`
15. `chk_bps_deposit`: `deposit_per_player_vnd >= 0`
16. `chk_bps_final_price`: `final_price_per_player_vnd >= 0`
17. `chk_bps_total_amount`: `total_amount_vnd >= 0`
18. `chk_pp_amount`: `amount_vnd > 0`
19. `chk_pa_amount`: `amount_vnd > 0`
20. `chk_refunds_amount`: `refund_amount_vnd > 0`
