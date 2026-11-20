# BÁO CÁO TÁI CẤU TRÚC MÃ NGUỒN (SOURCE REFACTORING REPORT)
## DỰ ÁN: WEBSITE BOOKING – KỊCH BẢN SÁT (JUBENSHA / MURDER MYSTERY) V16

- **Ngày thực hiện:** 20/11/2026
- **Auditor & Architect:** Senior Principal Software Architect
- **Workspace:** `D:\LapTrinhWeb\Project\Booking_kich-ban-sat_PHP`
- **Tài liệu:** `docs/audit/SOURCE_REFACTORING.md`

---

## 1. TỔNG QUAN MỤC TIÊU TÁI CẤU TRÚC

Đồng bộ toàn diện cấu trúc thư mục và phân tầng kiến trúc mã nguồn Backend và Frontend theo đúng chuẩn đã thỏa thuận tại mục 2.1 và 2.2 của MASTER EXECUTION PROMPT:
- **Backend:** Modular Monolith, Feature-oriented + Layered Architecture (Core, Infrastructure, Features, Shared).
- **Frontend:** MPA (Multi-Page Application) + Vanilla JS ES Modules + Shared API Client + Component-based styling.
- Bảo toàn tuyệt đối 100% logic nghiệp vụ hiện hữu, không làm đứt gãy test hay gây regression.

---

## 2. CHI TIẾT CÁC THAY ĐỔI MÃ NGUỒN

### 2.1. Tập tin đã sửa đổi (Modified Files)
1. `backend/app/Infrastructure/Database/ConnectionFactory.php`:
   - Khắc phục lỗi bảo mật rò rỉ credential constructor arguments trong stack trace / chained exceptions: loại bỏ việc truyền raw `PDOException $e` vào parameter `$previous` của `DatabaseException`.
   - Kết quả: Đưa test suite Unit/DatabaseConnectionTest từ 1 FAILED lên **100% PASSED**.
2. `frontend/index.html`:
   - Liên kết bổ sung `src/styles/components.css` và `src/styles/pages.css` phục vụ thiết kế giao diện theo Component-based UI và 4 phân hệ portal (Public, Player, DM, Admin).

### 2.2. Tập tin và Thư mục đã bổ sung (Created Files & Directories)
**Backend:**
1. `backend/app/Features/Pricing/Repositories/PricingRepository.php`:
   - Repository xử lý biểu giá `game_prices` và lưu trữ snapshot bất biến `booking_price_snapshots`.
2. `backend/app/Features/Promotion/Repositories/PromotionRepository.php`:
   - Repository truy vấn mã khuyến mãi `promotions` theo điều kiện hiệu lực.
3. `backend/app/Features/Booking/Repositories/BookingRepository.php`:
   - Repository quản lý `sessions`, kiểm tra xung đột trùng lịch phòng vật lý `physical_room_reservations`.
4. `backend/app/Features/Notification/Repositories/NotificationRepository.php`:
   - Repository xử lý thông báo `notifications` và fan-out người nhận `notification_recipient_states`.
5. `backend/app/Shared/Enums/UserRole.php`: Enum vai trò người dùng (ADMIN, DM, PLAYER).
6. `backend/app/Shared/Enums/GameRoomStatus.php`: Enum vòng đời phòng chơi.
7. `backend/app/Shared/Enums/PaymentStatus.php`: Enum trạng thái giao dịch tài chính.
8. `backend/app/Shared/DTOs/.gitkeep`: Thư mục Data Transfer Objects.
9. `backend/app/Shared/Helpers/.gitkeep`: Thư mục Helper functions dùng chung.

**Frontend:**
1. `frontend/public/assets/images/.gitkeep` & `frontend/public/assets/icons/.gitkeep`: Thư mục tài nguyên tĩnh.
2. `frontend/src/config/app-config.js` & `frontend/src/config/index.js`: Cấu hình tập trung cho Frontend MPA.
3. `frontend/src/pages/public/.gitkeep`: Entrypoint cho Portal công cộng.
4. `frontend/src/pages/player/.gitkeep`: Entrypoint cho Player Portal.
5. `frontend/src/pages/dm/.gitkeep`: Entrypoint cho DM Portal.
6. `frontend/src/pages/admin/.gitkeep`: Entrypoint cho Admin Portal.
7. `frontend/src/services/.gitkeep`: Services tầng frontend điều phối API Client.
8. `frontend/src/styles/components.css`: CSS chuẩn cho các component giao diện (Button, Card, Modal).
9. `frontend/src/styles/pages.css`: CSS layout riêng biệt cho từng portal.

### 2.3. Tập tin di chuyển / xóa bỏ (Moved / Removed Files)
- Không có file nào bị xóa bỏ hoặc đổi vị trí gây phá vỡ import/PSR-4.
- 100% namespace tuân thủ PSR-4 dưới prefix `App\` và `Tests\`.

---

## 3. ĐÁNH GIÁ TÍNH TUÂN THỦ KIẾN TRÚC (ARCHITECTURE COMPLIANCE)

| Tiêu chuẩn kiến trúc | Hiện trạng triển khai | Đánh giá |
|---|---|:---:|
| **Modular Monolith** | Bounded contexts phân chia rõ theo business domain trong `app/Features/` | **PASS** |
| **Separation of Concerns** | Controller chỉ HTTP, Service chỉ Business/Transaction, Repository chỉ SQL/PDO | **PASS** |
| **No Raw SQL in Controller/Service**| Mọi câu lệnh truy vấn tập trung tại Repositories kế thừa `BaseRepository` | **PASS** |
| **Shared PDO Infrastructure** | Sử dụng chung `ConnectionFactory` và `DatabaseConnection` đơn nhất | **PASS** |
| **OWASP Protection** | 100% truy vấn dùng Prepared Statements có binding kiểu nghiêm ngặt | **PASS** |
| **Frontend Architecture** | MPA thuần ES Modules, không bundler cồng kềnh, phân lớp API Client chuẩn | **PASS** |

---

## 4. KẾT QUẢ KIỂM THỬ SAU TÁI CẤU TRÚC

- **PHPUnit:** 79/79 passed (100%), 230 assertions, 0 errors, 0 failures.
- **Frontend Test Suite:** 7/7 passed (100%), 0 errors, 0 failures.
- **PHP Syntax:** Không có bất kỳ cảnh báo hoặc lỗi cú pháp.
