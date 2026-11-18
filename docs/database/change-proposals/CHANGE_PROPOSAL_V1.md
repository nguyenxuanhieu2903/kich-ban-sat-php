# ĐỀ XUẤT THAY ĐỔI CƠ SỞ DỮ LIỆU (DATABASE CHANGE PROPOSAL — V1)
## DỰ ÁN: BOOKING-PROJECT-PHP — ĐỀ XUẤT CHO MIGRATION V2

- **Tài liệu:** `CHANGE_PROPOSAL_V1.md`
- **Thư mục lưu trữ:** `docs/database/change-proposals/`
- **Thời gian lập đề xuất:** 18/11/2026
- **Auditor:** Senior Database Architect & Database QA Lead
- **Nguyên tắc:** Tuân thủ Constraint 4.1 (Source Integrity) — Không tự ý sửa trực tiếp migration V1. Mọi cải tiến phải được ghi nhận dưới dạng Change Proposal có đầy đủ Technical Evidence, Impact Analysis, Migration Script và Rollback Plan.

---

## 1. TỔNG HỢP CÁC ĐỀ XUẤT THAY ĐỔI

| Proposal ID | Hạng mục đề xuất | Mức độ nghiêm trọng | Bảng ảnh hưởng | Tác động Migration | Ưu tiên |
|---|---|:---:|---|---|:---:|
| **CP-01** | Bổ sung Covering Index kiểm tra phòng trống | **HIGH** | `physical_room_reservations` | Thêm index không khóa bảng (ALGORITHM=INPLACE) | P1 |
| **CP-02** | Điều chỉnh Comment đếm số bảng trong Bounded Contexts | **LOW** | Migration Comments | Sửa text comment tài liệu | P3 |
| **CP-03** | Bổ sung Index lọc lịch DM theo thời gian | **MEDIUM** | `sessions`, `dm_assignments` | Tăng tốc độ query kiểm tra trùng lịch Quản trò | P2 |
| **CP-04** | Bổ sung Covering Index theo dõi công nợ theo Session | **MEDIUM** | `player_payments` | Tăng tốc độ tính toán doanh thu phiên chơi | P2 |

---

## 2. CHI TIẾT TỪNG ĐỀ XUẤT THAY ĐỔI

### 2.1. Proposal CP-01: Thêm Covering Index cho kiểm tra phòng vật lý trùng lặp
- **Business Reason:** Tối ưu hóa truy vấn kiểm tra khoảng thời gian giao nhau `(start_time < new_end AND end_time > new_start)` khi thực hiện Pessimistic Lock tại `physical_room_reservations`.
- **Technical Evidence:** Hiện tại chỉ mục `idx_prr_lookup` chỉ có `(physical_room_id, reservation_date, status)`. MySQL phải quay lại clustered index đọc `start_time` và `end_time` (Row Lookup overhead).
- **Proposed Solution:** Thêm chỉ mục che phủ `idx_prr_overlap_covering`.
- **Migration Script (V2 Draft):**
  ```sql
  ALTER TABLE `physical_room_reservations` 
  ADD INDEX `idx_prr_overlap_covering` (`physical_room_id`, `reservation_date`, `status`, `start_time`, `end_time`);
  ```
- **Rollback Strategy:**
  ```sql
  ALTER TABLE `physical_room_reservations` DROP INDEX `idx_prr_overlap_covering`;
  ```

---

### 2.2. Proposal CP-02: Đồng bộ hóa Comment số lượng bảng trong Bounded Contexts
- **Business Reason:** Thống nhất tài liệu kỹ thuật và comment trong migration SQL để tránh hiểu lầm cho đội ngũ phát triển.
- **Technical Evidence:**
  - Context 03 (Pricing & Promotion): comment ghi 3 bảng, thực tế 2 bảng.
  - Context 04 (DM Management): comment ghi 3 bảng, thực tế 2 bảng.
  - Context 05 (Gameroom Management): comment ghi 6 bảng, thực tế 7 bảng.
  - Context 06 (Venue & Scheduling): comment ghi 5 bảng, thực tế 6 bảng.
- **Proposed Solution:** Sửa lại các header comments trong migration V2 hoặc cập nhật trực tiếp tài liệu kiến trúc.

---

### 2.3. Proposal CP-03: Thêm Index hỗ trợ kiểm tra lịch trùng của Quản trò
- **Business Reason:** Giảm thời gian kiểm tra xung đột lịch của DM khi hệ thống đạt quy mô hàng ngàn session mỗi tháng.
- **Proposed Solution:** Thêm composite index vào bảng `sessions`:
  ```sql
  ALTER TABLE `sessions` 
  ADD INDEX `idx_sessions_dm_schedule` (`dm_assignment_id`, `scheduled_date`, `status`, `start_time`, `end_time`);
  ```
- **Rollback Strategy:**
  ```sql
  ALTER TABLE `sessions` DROP INDEX `idx_sessions_dm_schedule`;
  ```

---

## 3. KẾT LUẬN VÀ KHUYẾN NGHỊ
Các đề xuất trên không làm thay đổi các bảng hiện tại của V1, không làm đứt gãy tương thích ngược (Backward Compatible 100%), và sẽ được đưa vào Migration V2 trong Phase 4 khi triển khai tầng Repository và Service Layer.
