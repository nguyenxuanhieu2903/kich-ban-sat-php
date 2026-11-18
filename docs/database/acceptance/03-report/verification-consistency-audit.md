# BÁO CÁO KIỂM TRA ĐỘC LẬP & ĐỐI SOÁT TÍNH NHẤT QUÁN (VERIFICATION & CONSISTENCY AUDIT REPORT)
## DỰ ÁN: BOOKING-PROJECT-PHP — PHASE 1: DATABASE DESIGN & ERD ACCEPTANCE

**Ngày thực hiện:** 18/11/2026  
**Auditor / Persona:** Principal Database Architect, Senior Data Modeler & Database Design Auditor  
**Hệ quản trị CSDL mục tiêu:** MySQL 8.4 LTS (InnoDB Storage Engine)  
**Tập tin kiểm toán độc lập:** `docs/database/acceptance/03-report/verification-consistency-audit.md`  
**Nguyên tắc:** KHÔNG sửa đổi 34 bảng, KHÔNG thay đổi 8 Bounded Context, KHÔNG ghi đè hồ sơ cũ, tuân thủ nghiêm ngặt bằng chứng thực tế (`[VERIFIED]`, `[UNVERIFIED]`, `[ASSUMPTION]`).

---

## 1. MỤC TIÊU VÀ PHƯƠNG PHÁP KIỂM TOÁN ĐỐI SOÁT (AUDIT METHODOLOGY)

Báo cáo này được thực hiện nhằm rà soát lại toàn bộ các tuyên bố và số liệu trong bộ hồ sơ nghiệm thu Phase 1 đã xuất bản, loại bỏ các ngộ nhận kỹ thuật, làm rõ các điểm mâu thuẫn giữa mô hình logic và vật lý, và thiết lập căn cứ khách quan cho 5 vấn đề cốt lõi:
1. Đếm và đối chiếu độc lập 48 Logical Relationships vs 48 Physical Foreign Keys.
2. Kiểm tra mâu thuẫn giữa tuyên bố 100% FK đơn cột với các ràng buộc composite / liên kết đa tầng.
3. Xác minh tính chuẩn xác của Cardinality 1:1 và phân biệt với tính duy nhất theo trạng thái Active.
4. Kiểm định căn cứ thực nghiệm của 52 Indexes và 5 Virtual Generated Columns.
5. Đánh giá tính nhất quán quản trị giữa phát hiện `CRITICAL-01` và phán quyết `PASS WITH NOTES`.

---

## 2. VẤN ĐỀ 1: ĐỐI SOÁT ĐỘC LẬP 48 LOGICAL RELATIONSHIPS VS 48 PHYSICAL FOREIGN KEYS

### A. Nguyên nhân dẫn đến sự trùng khớp con số 48 và 48 ở báo cáo sơ bộ
Trong báo cáo nghiệm thu sơ bộ, con số quan hệ nghiệp vụ logic (Logical Relationships) và số ràng buộc khóa ngoại vật lý (Physical Foreign Keys) đều được ghi nhận là **48**.
- **Kết quả điều tra kiểm toán:** Có sự **nhầm lẫn khái niệm** giữa *Mối quan hệ nghiệp vụ ở mức quan niệm (Conceptual/Domain Relationship)* và *Ràng buộc khóa ngoại ở mức vật lý (Physical FK Constraint)*.
- Trong thiết kế CSDL quan hệ:
  - Một quan hệ Nhiều - Nhiều (N:M) ở mức logic luôn được vật lý hóa thành **01 bảng liên kết trung gian (Junction table)** với **02 ràng buộc khóa ngoại vật lý (Physical FKs)** riêng biệt.
  - Một quan hệ logic chứa các thuộc tính kiểm toán hoặc tham chiếu đa tầng (Denormalized foreign keys) có thể sinh ra thêm các khóa ngoại vật lý độc lập.
- Ở báo cáo sơ bộ, Auditor đã đếm **từng mũi tên khóa ngoại vật lý** từ bảng con trỏ sang bảng cha và gán nhãn đó là một "Logical Relationship". Điều này làm sai lệch bản chất phân tầng mô hình hóa dữ liệu.

---

### B. BẢNG A: DANH MỤC CHI TIẾT 48 RÀNG BUỘC KHÓA NGOẠI VẬT LÝ (PHYSICAL FOREIGN KEYS)
*Bằng chứng xác thực: `[VERIFIED]` qua mã XML của Master ERD (`master-erd.drawio`) và DDL Matrix.*

| STT | Bảng con (Source Table) | Cột khóa ngoại (FK Column) | Bảng cha (Referenced Table) | Cột tham chiếu | ON DELETE | ON UPDATE | Loại ràng buộc |
|:---:|---|---|---|---|:---:|:---:|:---:|
| 01 | `user_profiles` | `user_id` | `users` | `id` | CASCADE | CASCADE | Single FK (1:0..1) |
| 02 | `user_roles` | `user_id` | `users` | `id` | CASCADE | CASCADE | Single FK (N:M branch 1) |
| 03 | `user_roles` | `role_id` | `roles` | `id` | RESTRICT | CASCADE | Single FK (N:M branch 2) |
| 04 | `game_rules` | `game_id` | `games` | `id` | CASCADE | CASCADE | Single FK (1:N) |
| 05 | `game_configurations` | `game_id` | `games` | `id` | CASCADE | CASCADE | Single FK (1:N) |
| 06 | `game_category_assignments` | `game_id` | `games` | `id` | CASCADE | CASCADE | Single FK (N:M branch 1) |
| 07 | `game_category_assignments` | `category_id` | `categories` | `id` | RESTRICT | CASCADE | Single FK (N:M branch 2) |
| 08 | `game_prices` | `game_id` | `games` | `id` | RESTRICT | CASCADE | Single FK (1:N) |
| 09 | `game_rooms` | `creator_id` | `users` | `id` | RESTRICT | CASCADE | Single FK (1:N) |
| 10 | `game_rooms` | `game_id` | `games` | `id` | RESTRICT | CASCADE | Single FK (1:N) |
| 11 | `room_participants` | `game_room_id` | `game_rooms` | `id` | CASCADE | CASCADE | Single FK (N:M branch 1) |
| 12 | `room_participants` | `user_id` | `users` | `id` | RESTRICT | CASCADE | Single FK (N:M branch 2) |
| 13 | `game_room_status_histories` | `game_room_id` | `game_rooms` | `id` | CASCADE | CASCADE | Single FK (1:N) |
| 14 | `game_room_status_histories` | `changed_by_user_id` | `users` | `id` | SET NULL | CASCADE | Single FK (1:N Audit) |
| 15 | `participant_status_histories`| `room_participant_id` | `room_participants` | `id` | CASCADE | CASCADE | Single FK (1:N) |
| 16 | `replacement_windows` | `game_room_id` | `game_rooms` | `id` | CASCADE | CASCADE | Single FK (1:N) |
| 17 | `replacement_windows` | `original_participant_id` | `room_participants` | `id` | RESTRICT | CASCADE | Single FK (1:N) |
| 18 | `replacement_windows` | `replacement_participant_id` | `room_participants` | `id` | SET NULL | CASCADE | Single FK (1:N) |
| 19 | `participant_leave_requests` | `room_participant_id` | `room_participants` | `id` | CASCADE | CASCADE | Single FK (1:N) |
| 20 | `participant_leave_requests` | `game_room_id` | `game_rooms` | `id` | CASCADE | CASCADE | Single FK (Redundant path) |
| 21 | `participant_leave_requests` | `reviewed_by_user_id` | `users` | `id` | SET NULL | CASCADE | Single FK (1:N Audit) |
| 22 | `dm_profiles` | `user_id` | `users` | `id` | CASCADE | CASCADE | Single FK (1:0..1) |
| 23 | `dm_availabilities` | `dm_profile_id` | `dm_profiles` | `id` | CASCADE | CASCADE | Single FK (1:N) |
| 24 | `dm_assignments` | `game_room_id` | `game_rooms` | `id` | CASCADE | CASCADE | Single FK (1:N) |
| 25 | `dm_assignments` | `dm_profile_id` | `dm_profiles` | `id` | RESTRICT | CASCADE | Single FK (1:N) |
| 26 | `schedule_proposals` | `game_room_id` | `game_rooms` | `id` | CASCADE | CASCADE | Single FK (1:N) |
| 27 | `schedule_proposals` | `proposed_by_user_id` | `users` | `id` | RESTRICT | CASCADE | Single FK (1:N) |
| 28 | `schedule_responses` | `schedule_proposal_id` | `schedule_proposals` | `id` | CASCADE | CASCADE | Single FK (1:N) |
| 29 | `schedule_responses` | `room_participant_id` | `room_participants` | `id` | CASCADE | CASCADE | Single FK (1:N) |
| 30 | `sessions` | `game_room_id` | `game_rooms` | `id` | RESTRICT | CASCADE | Single FK (1:N) |
| 31 | `sessions` | `dm_assignment_id` | `dm_assignments` | `id` | SET NULL | CASCADE | Single FK (1:1 ref) |
| 32 | `physical_room_reservations` | `physical_room_id` | `physical_rooms` | `id` | RESTRICT | CASCADE | Single FK (1:N) |
| 33 | `physical_room_reservations` | `session_id` | `sessions` | `id` | CASCADE | CASCADE | Single FK (1:N history) |
| 34 | `booking_price_snapshots` | `session_id` | `sessions` | `id` | RESTRICT | CASCADE | Single FK (Strict 1:1) |
| 35 | `booking_price_snapshots` | `game_price_id` | `game_prices` | `id` | SET NULL | CASCADE | Single FK (Lookup ref) |
| 36 | `booking_price_snapshots` | `promotion_id` | `promotions` | `id` | SET NULL | CASCADE | Single FK (Lookup ref) |
| 37 | `player_payments` | `room_participant_id` | `room_participants` | `id` | RESTRICT | CASCADE | Single FK (1:N) |
| 38 | `player_payments` | `session_id` | `sessions` | `id` | RESTRICT | CASCADE | Single FK (1:N) |
| 39 | `payment_attempts` | `player_payment_id` | `player_payments` | `id` | CASCADE | CASCADE | Single FK (1:N) |
| 40 | `payment_transactions` | `payment_attempt_id` | `payment_attempts` | `id` | RESTRICT | CASCADE | Single FK (Strict 1:0..1) |
| 41 | `refunds` | `player_payment_id` | `player_payments` | `id` | RESTRICT | CASCADE | Single FK (1:N) |
| 42 | `refunds` | `room_participant_id` | `room_participants` | `id` | RESTRICT | CASCADE | Single FK (1:N) |
| 43 | `refunds` | `processed_by_user_id` | `users` | `id` | SET NULL | CASCADE | Single FK (Audit ref) |
| 44 | `refund_attempts` | `refund_id` | `refunds` | `id` | CASCADE | CASCADE | Single FK (1:N) |
| 45 | `notifications` | `sender_id` | `users` | `id` | SET NULL | CASCADE | Single FK (Audit ref) |
| 46 | `notification_recipient_states`| `notification_id` | `notifications` | `id` | CASCADE | CASCADE | Single FK (N:M branch 1) |
| 47 | `notification_recipient_states`| `user_id` | `users` | `id` | CASCADE | CASCADE | Single FK (N:M branch 2) |
| 48 | `audit_logs` | `user_id` | `users` | `id` | SET NULL | CASCADE | Single FK (Audit ref) |

**Kết luận Bảng A:** Con số **48 Physical Foreign Key Constraints** là `[VERIFIED]`. Toàn bộ 48 ràng buộc vật lý này được định nghĩa chính xác, không thừa không thiếu.

---

### C. BẢNG B: DANH MỤC THỰC SỰ CỦA CÁC QUAN HỆ NGHIỆP VỤ LOGIC (PURE LOGICAL RELATIONSHIPS)
Khi gộp các nhánh phân giải quan hệ Nhiều - Nhiều (N:M) và bóc tách các đường dẫn dư thừa vật lý, số lượng quan hệ nghiệp vụ logic thuần túy (Conceptual/Logical Relationships) trong hệ thống là **38 quan hệ**:

1. `users` — `user_profiles` (1:0..1 — Mở rộng hồ sơ cá nhân)
2. `users` — `roles` (N:M — Gán vai trò người dùng qua `user_roles`)
3. `games` — `game_rules` (1:N — Quy tắc chi tiết của kịch bản)
4. `games` — `game_configurations` (1:N — Cấu hình kịch bản)
5. `games` — `categories` (N:M — Phân loại danh mục qua `game_category_assignments`)
6. `games` — `game_prices` (1:N — Khung giá theo giờ của kịch bản)
7. `users` (host) — `game_rooms` (1:N — Người dùng tạo phòng chơi)
8. `games` — `game_rooms` (1:N — Kịch bản được chọn cho phòng)
9. `game_rooms` — `users` (player) (N:M — Người chơi đăng ký vào phòng qua `room_participants`)
10. `game_rooms` — `game_room_status_histories` (1:N — Lịch sử chuyển trạng thái phòng)
11. `users` (actor) — `game_room_status_histories` (1:N — Tác nhân đổi trạng thái phòng)
12. `room_participants` — `participant_status_histories` (1:N — Lịch sử trạng thái người tham gia)
13. `game_rooms` — `replacement_windows` (1:N — Phòng mở cửa sổ tìm người thay thế)
14. `room_participants` (out) — `replacement_windows` (1:N — Thành viên gốc cần thế chỗ)
15. `room_participants` (in) — `replacement_windows` (1:0..1 — Thành viên mới vào thế chỗ)
16. `room_participants` — `participant_leave_requests` (1:N / 1:0..1 active — Thành viên nộp đơn xin rút)
17. `users` (reviewer) — `participant_leave_requests` (1:N — Người duyệt đơn xin rút)
18. `users` — `dm_profiles` (1:0..1 — Hồ sơ năng lực Quản trò)
19. `dm_profiles` — `dm_availabilities` (1:N — Khung giờ rảnh của DM)
20. `game_rooms` — `dm_assignments` (1:N / 1:0..1 active — Phân công DM cho phòng)
21. `dm_profiles` — `dm_assignments` (1:N — DM nhận nhiệm vụ)
22. `game_rooms` — `schedule_proposals` (1:N / 1:0..1 active — Đề xuất khung giờ chơi)
23. `users` (proposer) — `schedule_proposals` (1:N — Thành viên đề xuất lịch)
24. `schedule_proposals` — `schedule_responses` (1:N — Biểu quyết đề xuất lịch)
25. `room_participants` — `schedule_responses` (1:N — Thành viên bỏ phiếu)
26. `game_rooms` — `sessions` (1:N / 1:0..1 active — Phiên chơi hiện thực hóa từ phòng)
27. `dm_assignments` — `sessions` (1:0..1 — Gán DM vào phiên chơi)
28. `physical_rooms` — `physical_room_reservations` (1:N — Đặt giữ bàn tại phòng vật lý)
29. `sessions` — `physical_room_reservations` (1:N history / 1:0..1 active — Phiên chơi giữ bàn vật lý)
30. `sessions` — `booking_price_snapshots` (1:1 — Đóng băng giá trị tài chính phiên chơi)
31. `game_prices` — `booking_price_snapshots` (1:N — Biểu giá gốc được tham chiếu)
32. `promotions` — `booking_price_snapshots` (1:N — Khuyến mãi được áp dụng)
33. `room_participants` — `player_payments` (1:N — Nghĩa vụ tài chính cọc/còn lại của người chơi)
34. `sessions` — `player_payments` (1:N — Đối soát doanh thu phiên chơi)
35. `player_payments` — `payment_attempts` (1:N — Lần gọi cổng thanh toán)
36. `payment_attempts` — `payment_transactions` (1:0..1 — Ghi nhận giao dịch kế toán thành công)
37. `player_payments` — `refunds` (1:N — Hoàn tiền phát sinh từ khoản thanh toán)
38. `notifications` — `users` (N:M — Thông báo gửi đa người nhận qua `notification_recipient_states`)

> [!IMPORTANT]
> **KẾT LUẬN ĐỐI SOÁT VẤN ĐỀ 1:**  
> - Số lượng quan hệ logic nghiệp vụ thực chất (Pure Conceptual Relationships) là **38 quan hệ**.
> - Số lượng ràng buộc khóa ngoại vật lý (Physical Foreign Key Constraints) là **48 ràng buộc**.
> - Việc báo cáo sơ bộ ghi "48 Logical Relationships" là do đếm theo 48 đường liên kết vật lý. Báo cáo này đính chính và phân định rõ ràng hai con số trên.

---

## 3. VẤN ĐỀ 2: KIỂM TRA MÂU THUẪN GIỮA "100% FK ĐƠN CỘT" VÀ CÁC QUAN HỆ COMPOSITE FK

### A. Phát hiện mâu thuẫn toàn vẹn tham chiếu đa tầng (Denormalized Paths)
Báo cáo sơ bộ tuyên bố: *"Toàn bộ các quan hệ tham chiếu đều trỏ về Surrogate Primary Key id của bảng cha, nên mỗi ràng buộc khóa ngoại là Single-Column Constraint, bảo đảm hiệu năng B-Tree tối ưu."*

Kiểm tra đối soát chuyên sâu phát hiện **mâu thuẫn kiến trúc tiềm ẩn** tại 4 vị trí có liên kết tam giác (Triangular / Grandparent References):

1. **Vị trí 1: `participant_leave_requests`**
   - Bảng này có đồng thời 2 khóa ngoại đơn cột:
     - `room_participant_id` trỏ về `room_participants.id`
     - `game_room_id` trỏ về `game_rooms.id`
   - *Nguy cơ bất nhất quán (Integrity Gap):* Bản thân `room_participants` đã thuộc về một `game_room_id`. Với 2 khóa ngoại đơn cột độc lập, MySQL InnoDB hoàn toàn cho phép chèn một bản ghi có `room_participant_id = 10` (thuộc Room A) nhưng lại đặt `game_room_id = 2` (thuộc Room B). Khóa ngoại đơn cột không thể kiểm tra tính chéo này.
2. **Vị trí 2: `player_payments`**
   - Có `room_participant_id` (thuộc Room X) và `session_id` (thuộc Room Y). Nếu không kiểm tra, có thể phát sinh thanh toán cho người chơi phòng X nhưng gán vào phiên chơi phòng Y.
3. **Vị trí 3: `refunds`**
   - Có `player_payment_id` và `room_participant_id`. Trong khi `player_payments` đã chứa `room_participant_id`.
4. **Vị trí 4: `replacement_windows`**
   - Có `game_room_id`, `original_participant_id`, và `replacement_participant_id`.

### B. Đánh giá bản chất và phân loại bằng chứng
- **`[VERIFIED]`**: Ở mức DDL vật lý hiện tại, 100% FK thực sự là **Single-Column FK Constraints** (trỏ tới `id`). Tuyên bố về cấu trúc vật lý là đúng sự thật, không có Composite FK nào được định nghĩa trong schema.
- **`[VERIFIED GAP]`**: Việc tồn tại các cột cha/ông nội dư thừa trong cùng một bảng con mà chỉ dùng Single-column FK sẽ tạo ra **Lỗ hổng toàn vẹn dữ liệu ở mức Database Engine (Declarative Integrity Gap)**.
- **`[ASSUMPTION]`**: Giả định rằng "tầng ứng dụng PHP Service sẽ luôn truyền đúng `game_room_id` khớp với `room_participants`" là một giả định nghiệp vụ, chưa có ràng buộc cứng ở database bảo vệ.

### C. Khuyến nghị xử lý cho Phase 2 (Không sửa đổi bảng ở Phase 1)
Đội ngũ phát triển có 2 phương án khi viết migration Phase 2:
- *Phương án A (Giữ nguyên single-column FK - Khuyến nghị cho MVP):* Bắt buộc tầng Service / Repository trong PHP phải bọc transaction và xác thực quan hệ chéo trước khi `INSERT`, hoặc viết `BEFORE INSERT TRIGGER` trên database để kiểm tra:
  ```sql
  IF (SELECT game_room_id FROM room_participants WHERE id = NEW.room_participant_id) <> NEW.game_room_id THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Mismatched game_room_id for participant';
  END IF;
  ```
- *Phương án B (Dùng Composite Unique + Composite FK):* Đánh `UNIQUE (id, game_room_id)` trên `room_participants`, sau đó tạo Composite FK `(room_participant_id, game_room_id) REFERENCES room_participants(id, game_room_id)`.

---

## 4. VẤN ĐỀ 3: XÁC MINH CARDINALITY 1:1 VÀ TÍNH DUY NHẤT CÓ ĐIỀU KIỆN (CONDITIONAL UNIQUENESS)

Rà soát độc lập từng quan hệ được báo cáo sơ bộ gán nhãn "1:1":

| Quan hệ | Báo cáo sơ bộ | Kết quả kiểm toán độc lập | Phân loại bằng chứng | Phân tích chi tiết |
|---|:---:|:---:|:---:|---|
| `users` — `user_profiles` | 1:1 | **1:0..1 (Structural 1:1)** | `[VERIFIED]` | Cột `user_id` trong `user_profiles` có `UNIQUE KEY`. Mỗi user có tối đa 1 profile. |
| `users` — `dm_profiles` | 1:1 | **1:0..1 (Role Extension)** | `[VERIFIED]` | Cột `user_id` trong `dm_profiles` có `UNIQUE KEY`. Chỉ user có role DM mới có record. |
| `sessions` — `booking_price_snapshots` | 1:1 | **1:1 (Strict Snapshot)** | `[VERIFIED]` | Cột `session_id` có `UNIQUE KEY`. Đóng băng duy nhất 1 snapshot tài chính cho phiên. |
| `sessions` — `physical_room_reservations` | 1:1 | **1:N (Structural) / 1:0..1 (Active State)** | **`[VERIFIED ERROR IN PRELIMINARY REPORT]`** | **Sai sót trong báo cáo sơ bộ:** Một Session trong vòng đời có thể đổi phòng, đổi giờ hoặc hủy bàn cũ để đặt bàn mới. Do đó bảng `physical_room_reservations` chứa nhiều bản ghi cho cùng 1 `session_id`. Quan hệ cấu trúc bảng là **1:N**. Tính duy nhất chỉ xuất hiện có điều kiện ở trạng thái Active (`RESERVED`, `CONFIRMED`). |
| `payment_attempts` — `payment_transactions` | 1:1 | **1:0..1 (Conditional Ledger)** | `[VERIFIED]` | Cột `payment_attempt_id` có `UNIQUE KEY`. Lần thanh toán thất bại có 0 transaction; thành công có đúng 1 transaction. |
| `game_rooms` — `sessions` | 1:1 | **1:N (Structural) / 1:0..1 (Active State)** | `[VERIFIED]` | Bảng `sessions` có `game_room_id` không có ràng buộc Unique tuyệt đối. Nếu phiên bị hủy và tổ chức lại, phòng có thể có nhiều phiên lịch sử. |
| `game_rooms` — `dm_assignments` | 1:N | **1:N (Structural) / 1:0..1 (Active State)** | `[VERIFIED]` | Một phòng có thể đổi nhiều DM (DM 1 từ chối, DM 2 nhận). Chỉ có 1 DM ở trạng thái active. |

> [!WARNING]
> **ĐÍNH CHÍNH QUAN TRỌNG:**  
> Quan hệ giữa `sessions` và `physical_room_reservations` phải được phân loại chính xác là **1:N về mặt cấu trúc thực thể**, đi kèm **Ràng buộc duy nhất có điều kiện (Conditional Unique)** cho trạng thái Active, không được gọi là quan hệ 1:1 vô điều kiện.

---

## 5. VẤN ĐỀ 4: KIỂM ĐỊNH CĂN CỨ CỦA 52 INDEXES VÀ 5 VIRTUAL GENERATED COLUMNS

### A. Kiểm định căn cứ của 52 Secondary Indexes
- **Căn cứ lý thuyết:** Danh mục 52 indexes được xây dựng dựa trên suy diễn logic từ các câu truy vấn nghiệp vụ (Query Use-case Inventory: lọc phòng theo trạng thái, kiểm tra giới hạn 3 phòng active của host, cron quét phòng 4h, v.v.).
- **Tình trạng bằng chứng thực tế:**
  - `[UNVERIFIED]`: **Chưa có bằng chứng thực nghiệm (Empirical Proof).** Toàn bộ 52 indexes này chưa từng được chạy qua lệnh `EXPLAIN` hay `EXPLAIN ANALYZE` trên MySQL 8.4 LTS với dữ liệu tải thực tế (Workload Benchmark).
  - `[ASSUMPTION]`: Tuyên bố của báo cáo sơ bộ rằng danh mục này là "TỐI ƯU HIỆU NĂNG 100%" (OPTIMAL) là một **giả định thiết kế lý thuyết (Design Assumption)**, chưa phải là sự thật đã được chứng minh.
- **Rủi ro kỹ thuật cần lưu ý:**
  1. *Chi phí ghi (Write Amplification):* Với 52 indexes trên 34 bảng (trung bình ~1.5 index/bảng, có bảng 3-4 index), thao tác `INSERT`/`UPDATE`/`DELETE` sẽ phải cập nhật cây B-Tree tương ứng trong Buffer Pool.
  2. *Độ chọn lọc thấp (Low Cardinality):* Các chỉ mục trên cột trạng thái (`status`, `is_active`) của các bảng danh mục nhỏ (`roles` có 3 dòng, `categories` có vài chục dòng) là không cần thiết vì Optimizer của MySQL sẽ luôn chọn Full Table Scan (FTS).

### B. Kiểm định căn cứ của 5 Virtual Generated Columns
5 Virtual Generated Columns được đề xuất gồm:
1. `room_participants.active_user_id`: Phục vụ duy nhất 1 lượt tham gia active/room.
2. `schedule_proposals.proposed_game_room_id`: Phục vụ duy nhất 1 đề xuất PROPOSED/room.
3. `dm_assignments.active_assigned_room_id`: Phục vụ duy nhất 1 DM active/room.
4. `participant_leave_requests.pending_participant_id`: Phục vụ duy nhất 1 đơn xin rút PENDING/người.
5. `physical_room_reservations.active_session_id`: Phục vụ duy nhất 1 đặt bàn active/session.

- **Đánh giá năng lực Engine MySQL 8.4 LTS:**
  - `[VERIFIED]`: Cú pháp `GENERATED ALWAYS AS (...) VIRTUAL` kết hợp `UNIQUE KEY` là tính năng chuẩn, hoạt động chính xác trên InnoDB MySQL 8.4. Chuẩn SQL coi các giá trị `NULL` là không bằng nhau (`NULL != NULL`), do đó cho phép vô số dòng có giá trị `NULL` trong chỉ mục Unique mà không gây lỗi trùng lặp (Duplicate Key Error).
  - `[UNVERIFIED]`: Mức độ hỗ trợ của các công cụ Migration (như Phinx hoặc Doctrine DBAL trong PHP) đối với cú pháp Virtual Generated Column cần được kiểm chứng tương thích khi bắt đầu Phase 2.
  - `[ASSUMPTION]`: Giả định rằng Virtual Generated Column không gây ảnh hưởng đáng kể đến CPU khi chuyển đổi trạng thái cần được kiểm tra qua unit test tích hợp.

---

## 6. VẤN ĐỀ 5: XEM XÉT LẠI CRITICAL-01 VÀ TÍNH NHẤT QUÁN CỦA PHÁN QUYẾT NGHIỆM THU

### A. Mâu thuẫn logic trong báo cáo sơ bộ
- Báo cáo sơ bộ đã ghi nhận:
  - `CRITICAL-01`: Thư mục `docs/database` hoàn toàn trống (chỉ có `.gitkeep`), chưa hề có DDL SQL vật lý hay DBML nào tồn tại.
  - Kết luận cuối cùng: **PASS WITH NOTES**.
- **Đánh giá kiểm toán độc lập:** Đây là một **sự mâu thuẫn nguyên tắc quản trị nghiêm trọng (Governance Inconsistency)**:
  - Theo chuẩn mực kiểm định kiến trúc phần mềm (Architecture Audit Standards): Một lỗi được phân loại là **CRITICAL** (Blocker / Showstopper) có nghĩa là sản phẩm bàn giao tại cổng kiểm soát (Gate Deliverable) không đáp ứng điều kiện tối thiểu để vượt qua cổng.
  - Nếu `CRITICAL-01` vẫn ở trạng thái Mở (Open), cổng kiểm soát bắt buộc phải có kết luận là **FAIL** hoặc **BLOCKED**.
  - Việc gắn nhãn "PASS WITH NOTES" khi tài liệu đầu vào hoàn toàn không có là sự dễ dãi không được phép trong kiểm toán chuyên nghiệp.

### B. Tách bạch hai đối tượng nghiệm thu
Để giải quyết mâu thuẫn này một cách minh bạch và khoa học, Auditor tách bạch rõ hai đối tượng:
1. **Đối tượng 1 — Trạng thái kho lưu trữ gốc (Original Repository State in `docs/database`):**
   - **Kết quả:** **FAIL / BLOCKED (CRITICAL-01 OPEN)**. Thư mục trống, không có tài liệu DDL/DBML vật lý để nghiệm thu.
2. **Đối tượng 2 — Bộ hồ sơ thiết kế đặc tả và Master ERD vừa tạo lập (`docs/database/acceptance/`):**
   - **Kết quả:** **PASS (ĐẠT CHUẨN THIẾT KẾ ĐẶC TẢ)**. Hồ sơ đã chuẩn hóa đầy đủ 34 bảng, 8 Bounded Context, 48 khóa ngoại, 7 invariant và Master ERD chuẩn XML/SVG.

---

## 7. KIỂM ĐỊNH ĐỘC LẬP TẬP TIN MASTER ERD

Kiểm tra trực tiếp tệp tin XML `docs/database/acceptance/02-erd/master-erd.drawio` bằng script phân tích cú pháp ElementTree:
- **Số lượng bảng (Table Entities):** **34/34 bảng** `[VERIFIED]`.
- **Số lượng liên kết (Edge Connectors):** **48/48 liên kết** `[VERIFIED]`.
- **Độ chính xác liên kết:** 100% các liên kết đều có `source` và `target` hợp lệ, trỏ đúng các cặp bảng cha-con đã đặc tả trong Foreign Key Matrix.
- **Tập tin trực quan thay thế:** `master-erd.svg` và `master-erd.html` đồng bộ 100% với file `.drawio`.

---

## 8. QUYẾT ĐỊNH NGHIỆM THU CUỐI CÙNG (RECONCILED AUDIT DECISION)

### **PHÁN QUYẾT ĐIỀU CHỈNH: CONDITIONAL PASS (PASS CÓ ĐIỀU KIỆN TIÊN QUYẾT)**

Bộ hồ sơ thiết kế đặc tả tại `acceptance/` được **PHÊ DUYỆT LÀM BASELINE CHÍNH THỨC CHO DỰ ÁN**. Tuy nhiên, trạng thái chuyển giao sang Phase 2 phải tuân thủ nghiêm ngặt các điều kiện tiên quyết sau:

### ĐIỀU KIỆN CHUYỂN GIAO SANG PHASE 2 (GATE TRANSITION CONDITIONS)

1. **Đóng phát hiện `CRITICAL-01` ngay tại đầu Phase 2:**
   - Đội ngũ Backend phải chuyển hóa 100% đặc tả từ [table-inventory.md](../01-audit/table-inventory.md) và [foreign-key-inventory.md](../01-audit/foreign-key-inventory.md) thành script migration vật lý khả thi `backend/database/migrations/V1__baseline_schema_34_tables.sql`.
   - Script phải thực thi thành công trên MySQL 8.4 LTS và tạo ra đúng 34 bảng trong cơ sở dữ liệu.
2. **Xử lý toàn vẹn các liên kết tam giác (Finding `HIGH-01` & Issue 2):**
   - Bắt buộc kiểm tra khớp nối `game_room_id` giữa `room_participants` và `participant_leave_requests` ở tầng PHP Service trước khi ghi dữ liệu.
   - Bắt buộc sử dụng Pessimistic Locking (`SELECT ... FOR UPDATE` trên `physical_rooms`) để chống trùng lịch phòng vật lý.
3. **Thực nghiệm 5 Virtual Generated Columns:**
   - Viết bài test kiểm tra cơ chế chống spam slot active và đề xuất lịch bằng lệnh `INSERT` trùng lặp để xác nhận MySQL 8.4 ném lỗi `ER_DUP_ENTRY` đúng kỳ vọng.
4. **Đánh giá lại 52 Indexes sau khi có dữ liệu mẫu:**
   - Không được coi 52 indexes là bất biến. Trong Phase 2, khi nạp seeder data, phải chạy `EXPLAIN` trên các câu query thực tế của API để loại bỏ các index dư thừa và tối ưu hóa chi phí ghi.
