# BIÊN BẢN NGHIỆM THU BASELINE CƠ SỞ DỮ LIỆU — V1
## (DATABASE BASELINE SIGN-OFF — V1)
### DỰ ÁN: BOOKING-PROJECT-PHP

- **Tài liệu:** `DATABASE_BASELINE_SIGN_OFF_V1.md`
- **Phiên bản Migration nghiệm thu:** `V1__baseline_schema_34_tables.sql`
- **Thời gian nghiệm thu:** 18/11/2026
- **Auditor / Hội đồng kiểm định:**
  - Senior Database Architect
  - Database Security Engineer
  - Database QA Lead
- **Quyết định nghiệm thu (Final Decision):** **APPROVED WITH CONDITIONS**

---

## 1. PHẠM VI NGHIỆM THU (SCOPE OF SIGN-OFF)

Biên bản nghiệm thu này xác nhận tính sẵn sàng kỹ thuật của Cơ sở dữ liệu V1 phục vụ việc tích hợp tầng Backend PHP (Phase 4: Database Connection, PDO, Repositories, Services, Transaction Management):
- Toàn bộ 34 bảng vật lý thuộc 8 Bounded Contexts.
- 34 Primary Keys, 48 Foreign Keys, 25 Unique Keys, 20 Check Constraints, 5 Virtual Generated Columns, 104 Indexes.
- Toàn bộ kết quả kiểm thử tính toàn vẹn cơ sở dữ liệu trên môi trường cô lập.
- Các điều kiện và ràng buộc kiến trúc bắt buộc chuyển giao cho đội ngũ backend PHP.

---

## 2. MÔI TRƯỜNG VÀ CÔNG CỤ XÁC MINH (AUDIT ENVIRONMENT)

| Tiêu chuẩn môi trường | Cấu hình thực tế kiểm định | Bằng chứng kiểm tra |
|---|---|---|
| **Hệ quản trị CSDL** | MySQL Community Server 8.0.44 for Win64 | InnoDB Storage Engine, `utf8mb4_unicode_ci` |
| **PHP Runtime** | PHP 8.4.27 (cli) | Đã kích hoạt extension `pdo_mysql` |
| **Test Framework** | PHPUnit 11.5.58 | Đã chạy thành công 100% test suite dự án |
| **Database Nghiệm thu** | `booking_project_php` (Live) | Khớp 100% với DDL migration V1 |
| **Database Kiểm thử** | `booking_project_php_test` (Test) | Chạy 27 ca test tính toàn vẹn (Pass 100%) |

---

## 3. TỔNG HỢP KẾT QUẢ CÁC GIAI ĐOẠN KIỂM TOÁN (TASKS 3.2 – 3.4)

### 3.1. Kết quả Task 3.2: ERD Verification
- Đã kiểm tra chi tiết toàn bộ **34 thực thể**, xác định rõ mục đích nghiệp vụ, cấu trúc cột, khóa chính, khóa ngoại và nullability.
- Xác minh toàn bộ **48 Foreign Keys**: Không phát hiện rủi ro mồ côi dữ liệu; các hành vi `CASCADE`, `RESTRICT`, `SET NULL` được thiết lập chính xác theo bản chất nghiệp vụ.
- Thiết lập hoàn chỉnh **Cardinality Matrix** (5 quan hệ 1:1, 40 quan hệ 1:N, 3 quan hệ N:M) và **Optionality Matrix** cho cả hai đầu quan hệ.
- Phân tích và kiểm chứng **6 Junction Tables**: Đều có Unique constraints ngăn chặn triệt để bản ghi trùng lặp.
- **Báo cáo chi tiết:** [ERD_VERIFICATION_REPORT.md](file:///d:/LapTrinhWeb/Project/Booking_kich-ban-sat_PHP/docs/database/ERD_VERIFICATION_REPORT.md).
- **Trạng thái:** **PASS**.

### 3.2. Kết quả Task 3.3: Constraint & Index Review
- Đã rà soát toàn bộ **20 CHECK constraints**: 100% có cú pháp hợp lệ trên MySQL 8.0+ và không bị bypass do các cột tham gia đều `NOT NULL`.
- Đã rà soát toàn bộ **25 UNIQUE constraints**: Phân tích kỹ lưỡng ảnh hưởng của NULL và ghi nhận tính hiệu quả cao của 5 Virtual Generated Columns.
- Kiểm kê toàn bộ **104 Indexes**: Không có index dư thừa hoặc trùng lặp tiền tố.
- Phân tích 6 trụ cột Business Integrity: Nhận diện rõ ranh giới những gì Database bảo vệ được và những gì bắt buộc Application phải kiểm soát bằng Transaction & Pessimistic Locking.
- **Báo cáo chi tiết:** [CONSTRAINT_INDEX_REVIEW.md](file:///d:/LapTrinhWeb/Project/Booking_kich-ban-sat_PHP/docs/database/CONSTRAINT_INDEX_REVIEW.md).
- **Trạng thái:** **PASS**.

### 3.3. Kết quả Task 3.4: Database Integrity Testing
- Xây dựng và thực thi thành công bộ kiểm thử tích hợp: `backend/tests/Integration/DatabaseIntegrityTest.php`.
- **27/27 test cases thành công (100% Passed)**, 40 assertions, 0 failures, 0 errors trong thời gian 1.41 giây.
- Toàn bộ test suite dự án đạt: **79 tests, 230 assertions, 100% OK**.
- **Báo cáo chi tiết:** [DATABASE_INTEGRITY_TEST_REPORT.md](file:///d:/LapTrinhWeb/Project/Booking_kich-ban-sat_PHP/docs/database/DATABASE_INTEGRITY_TEST_REPORT.md).
- **Trạng thái:** **PASS**.

---

## 4. MA TRẬN ĐỐI CHIẾU TIÊU CHÍ NGHIỆM THU (ACCEPTANCE CRITERIA MATRIX)

| Mã tiêu chí | Nội dung tiêu chí nghiệm thu | Trạng thái | Bằng chứng đối chiếu kỹ thuật |
|:---:|---|:---:|---|
| **DB-01** | Có đủ 34 bảng theo migration V1 | **PASS** | `information_schema.TABLES` ghi nhận chính xác 34 bảng. |
| **DB-02** | Khóa chính (PK) được xác minh 100% | **PASS** | 34/34 bảng có `id BIGINT UNSIGNED AUTO_INCREMENT`. |
| **DB-03** | Toàn bộ 48 Foreign Keys được đối chiếu | **PASS** | Khớp 100% giữa SQL V1 và `REFERENTIAL_CONSTRAINTS`. |
| **DB-04** | Cardinality được xác minh | **PASS** | Ma trận 1:1, 1:N, N:M được phân định rõ DB-enforced vs App. |
| **DB-05** | Optionality được xác minh hai phía | **PASS** | Ma trận Mandatory/Optional được kiểm chứng qua Nullability. |
| **DB-06** | Junction Tables được đánh giá | **PASS** | 6 bảng trung gian đều có Unique index chống trùng lặp. |
| **DB-07** | CHECK Constraints được review (20 CHK) | **PASS** | Cú pháp chuẩn MySQL 8, kiểm thử chặn giá trị âm & đảo giờ. |
| **DB-08** | UNIQUE Constraints được review (25 UK) | **PASS** | 18 Natural UK + 5 Generated Column UK + 2 Ledger UK. |
| **DB-09** | Normal Indexes được review (104 Indexes) | **PASS** | 100% FK có index, không có redundant leftmost prefix. |
| **DB-10** | Booking conflict được đánh giá & test | **PASS** | Test `testH01` chứng minh cơ chế và yêu cầu lock tầng App. |
| **DB-11** | DM scheduling conflict được test | **PASS** | Test `testH02` xác minh ranh giới bảo vệ phòng vs lịch DM. |
| **DB-12** | Duplicate participant được kiểm tra | **PASS** | Test `testE02` chứng minh chống trùng active & cho phép rejoin. |
| **DB-13** | Payment integrity được kiểm tra | **PASS** | Test `testA01`, `testH03` kiểm chứng ràng buộc thanh toán. |
| **DB-14** | Refund integrity được kiểm tra | **PASS** | Test `testF02` kiểm chứng ràng buộc hoàn tiền. |
| **DB-15** | Idempotency được kiểm tra | **PASS** | Test `testF01`, `testF02` kiểm chứng chặn duplicate attempt. |
| **DB-16** | Transaction rollback được kiểm tra | **PASS** | Test `testG01` kiểm chứng atomicity, không để lại dữ liệu bẩn. |
| **DB-17** | Concurrent booking được đánh giá | **PASS** | Đã xác lập chiến lược Pessimistic Lock (`SELECT FOR UPDATE`). |
| **DB-18** | Tài liệu kỹ thuật đồng bộ với migration | **PASS** | Đã lập Data Dictionary, Constraint Spec, Change Proposal. |
| **DB-19** | Có Database Test Report chi tiết | **PASS** | Có file `DATABASE_INTEGRITY_TEST_REPORT.md` kèm test code. |
| **DB-20** | Không còn lỗi Critical/High chưa xử lý | **PASS** | Các rủi ro interval overlap đã có giải pháp kiến trúc rõ ràng. |

---

## 5. CÁC VẤN ĐỀ ĐÃ BIẾT VÀ RỦI RO ĐƯỢC CHẤP NHẬN (KNOWN ISSUES & ACCEPTED RISKS)

1. **Rủi ro Overlapping Time Ranges ở Phòng vật lý và Quản trò:**
   - *Bản chất kỹ thuật:* MySQL 8.x sử dụng B-Tree Index, không hỗ trợ Partial Exclusion Constraints trên khoảng thời gian (`[start_time, end_time)`).
   - *Quyết định chấp nhận:* **ACCEPTED AT DATABASE LAYER**. Bắt buộc xử lý ở tầng Application Service Layer bằng Transaction cô lập với `SELECT ... FOR UPDATE`.
2. **Sai lệch số lượng bảng ghi trong comment Migration V1:**
   - *Bản chất kỹ thuật:* Comment trong migration V1 ghi số bảng của Context 03 là 3 (thực tế 2), Context 04 là 3 (thực tế 2), Context 05 là 6 (thực tế 7), Context 06 là 5 (thực tế 6). Tổng số bảng vẫn chuẩn xác là 34 bảng.
   - *Quyết định chấp nhận:* **ACCEPTED DOCUMENTATION DISCREPANCY**. Không can thiệp sửa trực tiếp DDL V1 để bảo vệ Source Integrity; ghi nhận trong `CHANGE_PROPOSAL_V1.md` cho Migration V2.
3. **Player Payments cho phép amount_paid > amount_due:**
   - *Bản chất kỹ thuật:* Check constraint chỉ kiểm tra `>= 0`.
   - *Quyết định chấp nhận:* **ACCEPTED AS INTENDED BUSINESS FLEXIBILITY**. Cho phép tiền tip hoặc phụ thu; tầng Payment Gateway Service chịu trách nhiệm tạo attempt đúng số tiền phải thu.

---

## 6. HÀNH ĐỘNG CẦN THỰC HIỆN TRONG PHASE 4 (PENDING ACTIONS)

Đội ngũ phát triển Backend PHP bắt buộc tuân thủ 4 điều kiện kỹ thuật khi bước vào Phase 4:

1. **Action 01 (Physical Room Locking):**
   Tại `PhysicalRoomReservationService`, bắt buộc bọc logic đặt phòng trong transaction với truy vấn kiểm tra giao nhau thời gian có khóa `FOR UPDATE` trước khi thực hiện INSERT.
2. **Action 02 (DM Schedule Validation):**
   Tại `DmAssignmentService`, bắt buộc kiểm tra xem DM đã có lịch `sessions` nào bị trùng khung giờ trước khi chuyển trạng thái sang `CONFIRMED`.
3. **Action 03 (Refund Aggregation Guard):**
   Tại `RefundService`, bắt buộc khóa dòng `player_payments` và kiểm tra `SUM(refund_amount) <= amount_paid` trước khi cho phép tạo lệnh hoàn tiền.
4. **Action 04 (Idempotency Key Forwarding):**
   Mọi controller tiếp nhận thanh toán và hoàn tiền bắt buộc sinh hoặc truyền `Idempotency-Key` vào attempt table để ngăn chặn race condition từ phía người dùng.

---

## 7. KẾT LUẬN NGHIỆM THU CUỐI CÙNG (FINAL DECISION)

Căn cứ trên kết quả kiểm tra cấu trúc 34 bảng, 48 khóa ngoại, 25 khóa duy nhất, 20 ràng buộc kiểm tra, 5 cột sinh ảo và 27 ca kiểm thử tự động đã chạy thành công 100%:

### **KẾT LUẬN: APPROVED WITH CONDITIONS**

Cơ sở dữ liệu V1 **ĐẠT TIÊU CHUẨN LÀM BASELINE CHÍNH THỨC** của dự án `Booking-project-php` và **ĐỦ ĐIỀU KIỆN CHUYỂN SANG GIAI ĐOẠN PHASE 4: PHP DATABASE INTEGRATION**.

---

*Biên bản được ký duyệt và có hiệu lực kể từ ngày 18/11/2026.*
