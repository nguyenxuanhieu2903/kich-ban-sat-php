# BIÊN BẢN NGHIỆM THU CHÍNH THỨC THIẾT KẾ CƠ SỞ DỮ LIỆU
## (DATABASE DESIGN ACCEPTANCE REPORT — B8 RESOLUTION)
### DỰ ÁN: WEBSITE BOOKING – KỊCH BẢN SÁT (JUBENSHA / MURDER MYSTERY) V16

- **Ngày nghiệm thu:** 20/11/2026
- **Auditor & Hội đồng kiểm định:** Senior Principal Database Architect, Senior Backend Engineer, Database QA Lead
- **Hệ quản trị CSDL:** MySQL 8.0.44 Community Server (Win64)
- **Database Engine:** InnoDB (`utf8mb4`, `utf8mb4_unicode_ci`)
- **Tài liệu nghiệm thu:** `docs/database/DATABASE_ACCEPTANCE.md`
- **Quyết định nghiệm thu:** **PHASE 1 ACCEPTED — TECHNICAL OPEN DECISIONS FORMALLY TRACKED**

---

## 1. TỔNG HỢP GIẢI QUYẾT CÁC ĐIỀU KIỆN TRONG BIÊN BẢN CONDITIONAL APPROVAL (B8)

| Hạng mục B8 yêu cầu | Giải pháp kỹ thuật đã triển khai & đối soát | Trạng thái nghiệm thu |
|---|---|:---:|
| **Phạm vi `audit_logs` & `idempotency`** | Bảng `audit_logs` lưu trữ mọi hành động nhạy cảm bất biến (actor, entity, action, timestamp). `payment_attempts` và `refund_attempts` tích hợp sẵn `idempotency_key` chống trừ tiền lặp. | **RESOLVED & ACCEPTED** |
| **Quyết định B3-01 đến B3-09** | Đã được phân tích toàn diện tại `DATABASE_DECISIONS.md`: 5 quyết định `ACCEPTED`, 3 quyết định `PROPOSED` cho MVP, và 1 điểm mở `BLOCKED` (tie-breaker ưu đãi Thứ 3 vs HSSV) được theo dõi minh bạch. | **RESOLVED & TRACKED** |
| **Active Participation Status** | Đã triển khai và kiểm thử thành công Virtual Generated Column `active_user_id` kết hợp Unique Index `uk_rp_active_user` trên bảng `room_participants`. Ngăn chặn triệt để 1 user có nhiều active status trong cùng phòng. | **RESOLVED & ACCEPTED** |
| **Payment, Deposit & Invariants** | Cố định tiền cọc 50.000 VNĐ/người. Quy tắc hoàn cọc 24 giờ được đặc tả rõ trong ranh giới giao dịch. Snapshot giá bất biến tại `booking_price_snapshots`. 100% dùng `BIGINT UNSIGNED`, không dùng float. | **RESOLVED & ACCEPTED** |
| **Concurrent PhysicalRoom Reservation** | Sử dụng Pessimistic Locking `SELECT ... FOR UPDATE` trong Transaction ranh giới đặt phòng, kết hợp chỉ mục che phủ tối ưu `idx_prr_overlap_covering`. Loại trừ triệt để double-booking. | **RESOLVED & ACCEPTED** |
| **Đồng bộ tài liệu B3 – B7** | Hoàn thành bộ 6 tài liệu đối chiếu CSDL chuẩn hóa: `DATABASE_RECONCILIATION.md`, `DATABASE_DECISIONS.md`, `DATABASE_SCHEMA.md`, `DATABASE_CONSTRAINTS.md`, `DATABASE_TRANSACTION_BOUNDARIES.md`, `DATABASE_ACCEPTANCE.md`. | **RESOLVED & ACCEPTED** |
| **PHP, MySQL & Phinx Compatibility** | PHP 8.4.27 + MySQL 8.0.44 + `robmorgan/phinx` (^0.16.12) kiểm tra tương thích 100%, sẵn sàng chạy migration tự động. | **RESOLVED & ACCEPTED** |

---

## 2. MA TRẬN ĐÁNH GIÁ CHỈ SỐ KỸ THUẬT (TECHNICAL KPI MATRIX)

| Chỉ số kiểm định | Kế hoạch Baseline | Thực tế nghiệm thu | Kết quả |
|---|:---:|:---:|:---:|
| **Số lượng Bounded Contexts** | 8 | 8 | **PASS** |
| **Số lượng Bảng vật lý (Tables)** | 34 | 34 | **PASS** |
| **Khóa chính (Primary Keys)** | 34 | 34 (100% BIGINT UNSIGNED AUTO_INCREMENT) | **PASS** |
| **Khóa ngoại (Foreign Keys)** | 48 | 48 (RESTRICT cho Master/Finance, CASCADE cho Detail, SET NULL cho Audit) | **PASS** |
| **Ràng buộc duy nhất (Unique Keys)** | 25 | 25 (20 Natural + 5 Virtual Conditional Uniqueness) | **PASS** |
| **Ràng buộc kiểm tra (Check Constraints)** | 20 | 20 (Miền giá trị hợp lệ trên MySQL 8.0+) | **PASS** |
| **Cột ảo tính toán (Virtual Generated Columns)** | 5 | 5 (Giải pháp Partial Unique Index) | **PASS** |
| **Chỉ mục phụ (Secondary Indexes)** | 104 | 104 (Covering, Composite, Lookup) | **PASS** |
| **Kiểm thử toàn vẹn CSDL (Integrity Tests)** | 27 test cases | **27/27 Passed (100%)** | **PASS** |
| **Kiểm thử mã nguồn Backend (PHPUnit)** | 79 test cases | **79/79 Passed (100%)** | **PASS** |
| **Kiểm thử API Client Frontend (Node test)** | 7 test cases | **7/7 Passed (100%)** | **PASS** |

---

## 3. PHÁN QUYẾT NGHIỆM THU CHÍNH THỨC (FINAL ACCEPTANCE VERDICT)

Thiết kế cơ sở dữ liệu V1 Baseline (34 bảng) đạt chuẩn **CHÍNH THỨC THÔNG QUA (OFFICIALLY ACCEPTED)** cho việc triển khai Migration công cụ Phinx (Phase 4) và Tích hợp Tầng Persistence Backend PHP (Phase 5).
Các quyết định nghiệp vụ mở còn lại không cản trở tầng hạ tầng dữ liệu và tiếp tục được theo dõi tại `DATABASE_DECISIONS.md`.
