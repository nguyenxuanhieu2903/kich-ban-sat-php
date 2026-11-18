# BÁO CÁO KIỂM THỬ TÍNH TOÀN VẸN CƠ SỞ DỮ LIỆU (DATABASE INTEGRITY TEST REPORT)
## DỰ ÁN: BOOKING-PROJECT-PHP — BASELINE SCHEMA V1

- **Tài liệu kiểm toán:** `DATABASE_INTEGRITY_TEST_REPORT.md`
- **Tập tin mã nguồn kiểm thử:** `backend/tests/Integration/DatabaseIntegrityTest.php`
- **Phiên bản Migration kiểm thử:** `V1__baseline_schema_34_tables.sql`
- **Ngày thực thi kiểm thử:** 18/11/2026
- **Lead QA / Security Engineer:** Senior Database QA Lead & Security Architect
- **Kết quả chung:** **PASS 100% (27/27 Tests Passed, 40 Assertions, 0 Failures, 0 Errors)**

---

## 1. MÔI TRƯỜNG VÀ CẤU HÌNH KIỂM THỬ (TEST ENVIRONMENT)

Tuân thủ nghiêm ngặt nguyên tắc **Security & Production Safety**, toàn bộ kiểm thử tính toàn vẹn được thực thi trên môi trường cơ sở dữ liệu kiểm thử cô lập hoàn toàn:

| Thành phần môi trường | Giá trị thực tế đã xác minh | Ghi chú an toàn & Tuân thủ |
|---|---|---|
| **Hệ điều hành** | Windows 11 Pro 64-bit | Local Developer Environment |
| **PHP Runtime** | PHP 8.4.27 (cli) (ZTS Visual C++ 2022 x64) | Tương thích chuẩn PDO MySQL Native |
| **PHPUnit Framework** | PHPUnit 11.5.58 by Sebastian Bergmann | Framework test tiêu chuẩn của dự án |
| **Database Server** | MySQL Community Server 8.0.44 for Win64 | InnoDB Storage Engine |
| **Database Test riêng biệt** | `booking_project_php_test` | **Cô lập 100%**, không đụng chạm DB development hay production |
| **Cơ chế khởi tạo Test DB** | Migration V1 nạp trực tiếp qua PDO | Khởi tạo lại cấu trúc đầy đủ 34 bảng |
| **Cơ chế dọn dẹp (Teardown)** | `cleanAllTables()` multi-table fast DELETE | Độc lập giữa các test case, thời gian chạy < 1.5s |

---

## 2. TỔNG HỢP KẾT QUẢ THỰC THI (EXECUTIVE TEST SUMMARY)

| Chỉ số kiểm thử | Giá trị đạt được | Trạng thái |
|---|---:|:---:|
| **Tổng số ca kiểm thử (Total Tests)** | **27** | **100%** |
| **Số ca kiểm thử thành công (Passed)** | **27** | **PASS** |
| **Số ca kiểm thử thất bại (Failed)** | **0** | **PASS** |
| **Số ca kiểm thử lỗi (Errors)** | **0** | **PASS** |
| **Số ca kiểm thử bỏ qua (Skipped)** | **0** | **PASS** |
| **Tổng số khẳng định (Total Assertions)** | **40** | **PASS** |
| **Thời gian thực thi (Execution Time)** | **00:01.411 (1.41 giây)** | **SIÊU NHANH** |
| **Mức tiêu thụ bộ nhớ (Memory Usage)** | **8.00 MB** | **TỐI ƯU** |
| **Toàn bộ Test Suite Dự án (Unit + Integration)** | **79 tests, 230 assertions** | **100% PASS** |

### Lệnh thực thi và Bằng chứng đầu ra (Execution Evidence):

```powershell
cd backend
.\vendor\bin\phpunit --filter DatabaseIntegrityTest
```

**Bằng chứng Console Output thực tế:**
```text
PHPUnit 11.5.58 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.27
Configuration: D:\LapTrinhWeb\Project\Booking_kich-ban-sat_PHP\backend\phpunit.xml

...........................                                       27 / 27 (100%)

Time: 00:01.411, Memory: 8.00 MB

OK (27 tests, 40 assertions)
```

---

## 3. BẢNG CHI TIẾT 27 CA KIỂM THỬ THEO NHÓM CHỨC NĂNG

### Nhóm A: Valid INSERT Tests (Kiểm thử chèn dữ liệu hợp lệ)

| Test ID | Tên Test Case | Điều kiện tiên quyết & Dữ liệu kiểm thử | Kết quả kỳ vọng | Kết quả thực tế | Trạng thái |
|---|---|---|---|---|:---:|
| **TC-A01** | `testA01ValidUserAndRoleInsert` | Insert user, role, profile 1:1, và user_roles N:M với dữ liệu chuẩn. | Tất cả bản ghi được INSERT thành công, lastInsertId > 0. | Khởi tạo thành công 4 bản ghi với đầy đủ liên kết FK. | **PASS** |
| **TC-A02** | `testA02ValidGameCatalogInsert` | Insert category, game, game_rules, game_configurations, game_category_assignments. | Quan hệ kịch bản và danh mục nạp thành công không lỗi. | Thành công nạp đầy đủ cấu hình kịch bản. | **PASS** |
| **TC-A03** | `testA03ValidPricingAndPromotionInsert` | Insert game_prices và promotions hợp lệ thỏa mãn CHECK. | Giá vé và mã voucher được lưu vào cơ sở dữ liệu. | Thành công, tạo đúng price_tier và voucher. | **PASS** |

---

### Nhóm B: Invalid INSERT Tests (Kiểm thử vi phạm ràng buộc khi Insert)

| Test ID | Tên Test Case | Ràng buộc được kiểm tra | Dữ liệu vi phạm đưa vào | Kết quả kỳ vọng | Kết quả thực tế | Trạng thái |
|---|---|---|---|---|---|:---:|
| **TC-B01** | `testB01NotNullConstraintViolation` | `users.email NOT NULL` | Chèn `email = NULL`. | Bị chặn bởi `SQLSTATE[HY000]: 1048 Column 'email' cannot be null`. | Ném đúng `PDOException` với mã lỗi 1048. | **PASS** |
| **TC-B02** | `testB02ForeignKeyViolationOnInsert` | `fk_user_roles_users` | Gán quyền cho `user_id = 999999` không tồn tại. | Bị chặn bởi `SQLSTATE[23000]: 1452 Cannot add or update a child row: a foreign key constraint fails`. | Ném đúng `PDOException` với mã lỗi 1452. | **PASS** |
| **TC-B03** | `testB03CheckConstraintViolationOnGamesPlayerCount` | `chk_games_players` | Chèn game có `min_players = 8` > `max_players = 4`. | Bị chặn bởi `SQLSTATE[HY000]: 3819 Check constraint 'chk_games_players' is violated`. | Ném đúng `PDOException` với mã lỗi 3819. | **PASS** |
| **TC-B04** | `testB04CheckConstraintViolationOnGamePricesTimes` | `chk_game_prices_times` | Chèn khung giá có `time_from = 18:00` > `time_to = 14:00`. | Bị chặn bởi `Check constraint 'chk_game_prices_times' is violated`. | Ném đúng `PDOException` với mã lỗi 3819. | **PASS** |
| **TC-B05** | `testB05CheckConstraintViolationOnDmAvailableDay` | `chk_dm_avail_day` | Chèn lịch rảnh DM có `day_of_week = 7`. | Bị chặn bởi `Check constraint 'chk_dm_avail_day' is violated (between 0 and 6)`. | Ném đúng `PDOException` với mã lỗi 3819. | **PASS** |
| **TC-B06** | `testB06UniqueConstraintViolationSingleColumn` | `uq_users_email` | Chèn 2 user có cùng địa chỉ email `unique@example.com`. | Bị chặn bởi `SQLSTATE[23000]: 1062 Duplicate entry for key 'users.uq_users_email'`. | Ném đúng `PDOException` mã 1062. | **PASS** |
| **TC-B07** | `testB07UniqueConstraintViolationComposite` | `uq_game_category_assignments` | Gán 2 lần cùng một category cho cùng một game. | Bị chặn bởi `SQLSTATE[23000]: 1062 Duplicate entry for key 'uq_game_category_assignments'`. | Ném đúng `PDOException` mã 1062. | **PASS** |

---

### Nhóm C: Invalid UPDATE Tests (Kiểm thử vi phạm ràng buộc khi Update)

| Test ID | Tên Test Case | Ràng buộc được kiểm tra | Hành vi cập nhật vi phạm | Kết quả kỳ vọng | Kết quả thực tế | Trạng thái |
|---|---|---|---|---|---|:---:|
| **TC-C01** | `testC01CheckConstraintViolationOnUpdate` | `chk_games_players` | Cập nhật `min_players = 10` khi `max_players = 8`. | Bị chặn bởi Check constraint violation 3819. | Ném đúng `PDOException` mã 3819. | **PASS** |
| **TC-C02** | `testC02UniqueConstraintViolationOnUpdate` | `uq_users_email` | Cập nhật email của user 2 thành email đang tồn tại của user 1. | Bị chặn bởi Duplicate key 1062. | Ném đúng `PDOException` mã 1062. | **PASS** |
| **TC-C03** | `testC03ForeignKeyViolationOnUpdate` | `fk_game_rooms_creator` | Cập nhật `creator_id` của phòng thành `999999`. | Bị chặn bởi Foreign key constraint fails 1452. | Ném đúng `PDOException` mã 1452. | **PASS** |

---

### Nhóm D: Foreign Key DELETE Behavior (Kiểm thử tính toàn vẹn khi Xóa)

| Test ID | Tên Test Case | Hành vi FK được kiểm tra | Thao tác thực hiện | Kết quả kỳ vọng | Kết quả thực tế | Trạng thái |
|---|---|---|---|---|---|:---:|
| **TC-D01** | `testD01ForeignKeyOnDeleteCascade` | `ON DELETE CASCADE` (`user_profiles`) | Xóa bản ghi cha `users`. | Bản ghi con `user_profiles` tự động bị xóa theo. | `COUNT(*)` của profile sau khi xóa user cha giảm về 0. | **PASS** |
| **TC-D02** | `testD02ForeignKeyOnDeleteRestrict` | `ON DELETE RESTRICT` (`game_rooms.creator_id`) | Xóa user đang là creator của phòng chơi mở. | Bị chặn bởi `SQLSTATE[23000]: 1451 Cannot delete or update a parent row: a foreign key constraint fails`. | Ném đúng `PDOException` mã 1451, không thể xóa user. | **PASS** |
| **TC-D03** | `testD03ForeignKeyOnDeleteSetNull` | `ON DELETE SET NULL` (`notifications.sender_id`) | Xóa user đang là sender của notification. | Bản ghi notification vẫn tồn tại, cột `sender_id` chuyển thành `NULL`. | Notification tồn tại, `sender_id` đọc ra trả về `NULL`. | **PASS** |

---

### Nhóm E: Virtual Generated Column Unique Constraints (Kiểm thử 5 Generated Columns)

| Test ID | Tên Test Case | Cột Generated & Ràng buộc Unique | Kịch bản kiểm thử trạng thái | Kết quả kỳ vọng | Kết quả thực tế | Trạng thái |
|---|---|---|---|---|---|:---:|
| **TC-E01** | `testE01GeneratedColumnActiveDmAssignment` | `active_assigned_room_id` / `uq_active_dm_assignment` | 1. Gán DM1 (ASSIGNED).<br>2. Cố tình gán DM2 (CONFIRMED) cho cùng phòng.<br>3. Chuyển DM1 sang CANCELLED, gán lại DM2. | Bước 2 ném duplicate key error.<br>Bước 3 thành công do cột sinh trả về NULL. | Bước 2 bị chặn chính xác bởi `uq_active_dm_assignment`. Bước 3 gán DM2 thành công. | **PASS** |
| **TC-E02** | `testE02GeneratedColumnActiveRoomParticipant` | `active_user_id` / `uq_room_active_participant` | 1. Player tham gia phòng (JOINED).<br>2. Player cố tình gửi request tham gia lần 2.<br>3. Player rời phòng (EXITED), sau đó đăng ký lại. | Bước 2 ném duplicate error.<br>Bước 3 thành công khi đã rời phòng. | Bước 2 ném đúng lỗi `uq_room_active_participant`. Bước 3 cho phép rejoin an toàn. | **PASS** |
| **TC-E03** | `testE03GeneratedColumnPendingParticipantLeaveRequest` | `pending_participant_id` / `uq_pending_leave_request` | 1. Người chơi gửi yêu cầu xin rời phòng (PENDING).<br>2. Gửi tiếp yêu cầu thứ 2 khi yêu cầu cũ chưa duyệt. | Bị chặn trùng lặp, chống spam request. | Bị chặn chính xác bởi `uq_pending_leave_request`. | **PASS** |
| **TC-E04** | `testE04GeneratedColumnProposedSchedule` | `proposed_game_room_id` / `uq_active_proposed_schedule` | 1. Host đề xuất lịch chơi 1 (PROPOSED).<br>2. Host đề xuất thêm lịch chơi 2 khi lịch 1 đang chờ vote. | Bị chặn trùng lặp đề xuất active. | Bị chặn chính xác bởi `uq_active_proposed_schedule`. | **PASS** |
| **TC-E05** | `testE05GeneratedColumnActivePhysicalRoomReservation` | `active_session_id` / `uq_session_active_reservation` | 1. Session giữ chỗ phòng A (RESERVED).<br>2. Cùng session đó cố giữ thêm phòng B (CONFIRMED). | Bị chặn, 1 session không thể giữ 2 phòng cùng lúc. | Bị chặn chính xác bởi `uq_session_active_reservation`. | **PASS** |

---

### Nhóm F: Idempotency Key Enforcement (Kiểm thử tính bất biến giao dịch)

| Test ID | Tên Test Case | Ràng buộc Idempotency | Kịch bản kiểm thử | Kết quả kỳ vọng | Kết quả thực tế | Trạng thái |
|---|---|---|---|---|---|:---:|
| **TC-F01** | `testF01PaymentAttemptIdempotencyKey` | `uq_payment_attempts_idempotency` | Gửi 2 lần thanh toán mang cùng `idempotency_key` (mô phỏng double click của user). | Lần thứ 2 bị chặn bởi lỗi Duplicate entry 1062. | Ném đúng `PDOException` mã 1062, bảo vệ chống trừ tiền kép. | **PASS** |
| **TC-F02** | `testF02RefundAttemptIdempotencyKey` | `uq_refund_attempts_idempotency` | Gửi 2 lần hoàn tiền mang cùng `idempotency_key`. | Lần thứ 2 bị chặn bởi Duplicate entry 1062. | Ném đúng `PDOException` mã 1062, bảo vệ chống hoàn tiền 2 lần. | **PASS** |

---

### Nhóm G: ACID Transaction Rollback (Kiểm thử hoàn nguyên giao dịch)

| Test ID | Tên Test Case | Phạm vi kiểm tra | Kịch bản kiểm thử | Kết quả kỳ vọng | Kết quả thực tế | Trạng thái |
|---|---|---|---|---|---|:---:|
| **TC-G01** | `testG01TransactionRollbackRestoresState` | ACID Atomicity | Mở transaction, INSERT user thành công, sau đó cố tình gây lỗi constraint ở bảng roles, thực hiện `rollBack()`. | Bản ghi user không được xuất hiện trong database sau rollback. | `COUNT(*)` user trả về 0. Không tồn tại dữ liệu bẩn / uncommitted. | **PASS** |

---

### Nhóm H: Concurrency & Business Integrity Simulation (Mô phỏng rủi ro nghiệp vụ)

| Test ID | Tên Test Case | Hiện tượng mô phỏng | Phân tích kết quả thực tế | Kết luận kỹ thuật | Trạng thái |
|---|---|---|---|---|:---:|
| **TC-H01** | `testH01PhysicalRoomOverlapVulnerabilityDemonstration` | 2 session khác nhau cùng đặt phòng VIP1 tại thời gian giao nhau (`14:00-18:00` và `16:00-20:00`). | Cả 2 bản ghi đều INSERT thành công vào DB. Database không có exclusion constraint. | **Chứng minh thành công lỗ hổng**: Bắt buộc phải có Pessimistic Locking (`SELECT FOR UPDATE`) ở Application Service. | **PASS** |
| **TC-H02** | `testH02DmOverlappingAssignmentDemonstration` | Gán cùng 1 DM cho 2 phòng chơi khác nhau diễn ra cùng thời điểm. | Database cho phép gán cả 2 phòng vì chỉ kiểm tra unique phòng, không kiểm tra DM. | **Chứng minh thành công lỗ hổng**: Service phân công DM phải kiểm tra lịch chéo trước khi lưu. | **PASS** |
| **TC-H03** | `testH03OverpaymentProtectionConstraintScope` | Nhập `amount_paid = 500,000` trong khi `amount_due = 200,000`. | Bản ghi lưu thành công vì CHECK chỉ bắt buộc `>= 0`, không bắt buộc `paid <= due`. | **Linh hoạt nghiệp vụ có kiểm soát**: Service thanh toán phải validate số tiền giao dịch chính xác. | **PASS** |

---

## 4. ĐÁNH GIÁ CHẤT LƯỢNG MÃ KIỂM THỬ (TEST QUALITY ASSESSMENT)

- **Nguyên tắc SOLID & Clean Code:** Test suite được cấu trúc theo các section logic mạch lạc, sử dụng các phương thức độc lập, mỗi test case chỉ chịu trách nhiệm kiểm tra một khía cạnh duy nhất.
- **Tính độc lập (Isolation):** Phương thức `setUp()` thực thi `cleanAllTables()` trước mỗi test case, đảm bảo 100% không có sự phụ thuộc thứ tự chạy test.
- **An toàn dữ liệu:** Toàn bộ test chỉ nhắm vào database `booking_project_php_test`, không chứa bất kỳ câu lệnh nào tác động đến môi trường development hay production.
- **Tốc độ thực thi:** Tối ưu hóa từ 63 giây xuống **1.41 giây** nhờ chuyển từ TRUNCATE DDL sang multi-table DELETE có kiểm soát FK.

---

## 5. KẾT LUẬN TASK 3.4

Test suite đã hoàn thành và kiểm chứng thành công toàn diện:
1. 100% các ràng buộc NOT NULL, Foreign Key, Check Constraint và Unique Constraint hoạt động chính xác theo tiêu chuẩn thiết kế.
2. Cả 5 Virtual Generated Columns đã chứng minh khả năng giải quyết triệt để bài toán Partial Unique Index trên MySQL 8.
3. Cơ chế Idempotency chống thanh toán / hoàn tiền trùng lặp hoạt động tin cậy.
4. Cơ chế Rollback bảo vệ nguyên vẹn tính nhất quán dữ liệu ACID.
5. Đã tạo ra bằng chứng kiểm thử thực nghiệm rõ ràng cho 3 rủi ro cần xử lý ở tầng Application (Đặt trùng phòng vật lý, Phân công DM trùng giờ, và Kiểm soát hạn mức hoàn tiền).

**KẾT LUẬN: PASS (100% KIỂM THỬ THÀNH CÔNG)**
