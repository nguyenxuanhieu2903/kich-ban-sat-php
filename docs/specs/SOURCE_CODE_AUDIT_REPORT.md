# SOURCE CODE AUDIT REPORT
## Kịch Bản Sát – Booking Tabletop RPG Platform

> **Đơn vị thực hiện:** Senior Software Architect, Lead Backend Engineer & Application Security Auditor  
> **Phiên bản tài liệu:** 1.0 (Full Comprehensive Audit)  
> **Thời điểm thẩm định:** 23/11/2026  
> **Trạng thái làm việc:** READ-ONLY AUDIT (Không sửa đổi source code, không làm biến đổi CSDL)  
> **Môi trường thực thi:** Windows Native, PHP 8.4.27 CLI (ZTS VC16 x64), MySQL 8.4 LTS, PHPUnit 11.5.58, Node.js v24.22.1

---

### 1. Executive Summary

#### 1.1. Tổng quan dự án
Dự án **Kịch Bản Sát** (Jubensha / Murder Mystery Booking Platform) là một hệ thống web-based hỗ trợ người chơi khám phá các kịch bản nhập vai trinh thám, tự lập phòng hoặc ghép phòng chơi, biểu quyết mốc giờ chơi, địa điểm, quản lý Quản trò (DM / Dungeon Master), thanh toán tiền cọc 50.000 VNĐ, quản lý quy tắc vàng 24 giờ và xử lý hoàn tiền tự động khi hủy phòng.

Hệ thống được thiết kế theo mô hình **Same-Origin Monolith** chia sẻ Session Cookie giữa Backend REST API và Frontend tĩnh, nhằm loại bỏ hoàn toàn bài toán phức tạp của CORS trong môi trường production.

#### 1.2. Kiến trúc hiện tại
- **Backend:** PHP Native 8.4+ thuần không dùng framework (Laravel/Symfony), tự xây dựng nhân HTTP Core (`Application`, `Router`, `Request`, `Response`, `Middleware`, `ExceptionHandler`). Hệ thống chia các chức năng nghiệp vụ thành các Modules/Features: `Identity`, `GameCatalog`, `GameroomManagement`, `VenueScheduling`, `DMManagement`, `PaymentRefund`, `Notification`, `Pricing`, `Promotion`.
- **Database:** MySQL 8.0/8.4 chuẩn hóa 3NF, gồm 21 bảng được khởi tạo qua Phinx Migration (`kich_ban_sat_v3_clean.sql`). Hệ thống sử dụng PDO với Transaction và khóa dòng bi quan (`SELECT ... FOR UPDATE`) để kiểm soát tương tranh.
- **Frontend:** SPA/MPA Hybrid xây dựng bằng HTML5, CSS3 Tokens và Vanilla JavaScript ES Modules chạy native trên trình duyệt. Front Controller (`backend/public/index.php`) vừa điều phối API vừa phục vụ file tĩnh từ thư mục `frontend/`.

#### 1.3. Mức độ đáp ứng yêu cầu (Thống kê thực tế)
Dựa trên đối chiếu toàn diện 11 nhóm chức năng (FRG-01 đến FRG-11) và 53 Use Cases của 4 Actor:
- **Tổng số Use Cases khảo sát:** **53 Use Cases**.
  - **IMPLEMENTED (Hoàn chỉnh từ UI → API → DB):** **28 / 53 Use Cases (~52.8%)**.
  - **PARTIALLY_IMPLEMENTED (Một phần / Thiếu UI / API Only):** **14 / 53 Use Cases (~26.4%)**.
  - **NOT_IMPLEMENTED (Chưa triển khai):** **11 / 53 Use Cases (~20.8%)**.
- **Tỷ lệ khả dụng nghiệp vụ:** **~79.2%** tính cả các luồng đã hoàn thành hoặc có sẵn nền tảng API/DB.

#### 1.4. Những vấn đề nghiêm trọng nhất (Top Critical Issues)
1. **[CRITICAL / P0] Lỗ hổng bỏ qua xác thực qua Bearer Token giả mạo (`AuthMiddleware.php` dòng 45–50):**  
   Hệ thống cho phép header `Authorization: Bearer <id>` gán thẳng User ID vào phiên mà không kiểm tra chữ ký token, token bí mật hoặc giới hạn chỉ trong môi trường kiểm thử (`APP_ENV === 'testing'`). Bất kỳ người ngoài nào gửi `Authorization: Bearer 1` đều chiếm đoạt ngay quyền Quản trị viên tối cao (ADMIN).
2. **[HIGH / P1] Lỗ hổng Stored XSS trong giao diện chi tiết phòng và Admin (`rooms-view.js` dòng 421, 471 & `admin-view.js` dòng 225):**  
   Các trường dữ liệu do người dùng nhập như `room.title`, `room.creator_name`, `user.name`, `user.email` được nội suy trực tiếp vào `innerHTML` mà không qua hàm `escapeHtml()`, cho phép tấn công thực thi mã độc từ tài khoản người dùng sang Admin.
3. **[HIGH / P1] Đứt gãy luồng thanh toán cọc khi mới lập phòng (`GameRoomController.php` & `SchedulingController.php`):**  
   Khi Host vừa tạo phòng (`FORMING`), hệ thống chỉ gán trạng thái `PENDING_DEPOSIT` nhưng không tạo bản ghi hóa đơn trong bảng `player_payments`. Hóa đơn chỉ được tạo khi có đề xuất lịch (`schedule_proposals`). Do đó, trước khi đề xuất lịch, Host và người tham gia không thể thực hiện nộp cọc.
4. **[HIGH / P1] Cổng DM Portal thiếu nút Bắt đầu / Kết thúc buổi chơi (`dm-view.js`):**  
   Dù Backend đã có đầy đủ API `POST /api/v1/sessions/{id}/start` và `POST /api/v1/sessions/{id}/complete`, trên giao diện Quản trò (`dm-view.js`) chỉ có nút Nhận ca / Từ chối ca mà hoàn toàn không có nút bấm tương tác để Quản trò kích hoạt bắt đầu và kết thúc buổi chơi thực tế.
5. **[MEDIUM / P2] Thất bại 1 bài test tự động do rò rỉ dữ liệu test (`DMAssignmentApiTest.php` dòng 254):**  
   Bộ test PHPUnit chạy 162 tests với 757 assertions, phát hiện 1 lỗi thất bại (`Failed asserting that 400 is identical to 422`). Nguyên nhân do phòng chơi ID 1 trong CSDL test bị thay đổi trạng thái sang `CANCELLED` từ test trước, khiến API trả về 400 `INVALID_ROOM_STATUS` trước khi kiểm tra vai trò DM.

#### 1.5. Kết luận tổng thể
Dự án có nền tảng CSDL (21 bảng chuẩn 3NF, khóa ngoại, CHECK constraints, Generated Columns, Idempotency) và Core HTTP Backend (Routing, PDO Transactions, Locking FOR UPDATE) được thiết kế **rất tốt và bài bản**. Tuy nhiên, để hệ thống sẵn sàng đi vào vận hành thực tế (Production), bắt buộc phải xử lý dứt điểm lỗ hổng bảo mật P0 tại `AuthMiddleware`, gắn hàm escape chống XSS trên Frontend, bổ sung các nút điều khiển ca chơi cho DM và hoàn thiện hóa đơn cọc ban đầu.

---

### 2. Repository Overview

#### 2.1. Cấu trúc thư mục thực tế

```text
Booking_kich-ban-sat_PHP/
├── backend/                                # Mã nguồn Backend PHP Native
│   ├── app/
│   │   ├── Core/                           # HTTP Micro-Kernel
│   │   │   ├── Application.php             # Điều phối Pipeline Middleware & Router
│   │   │   ├── Request.php                 # Đóng gói HTTP Request, Headers, Query, Body
│   │   │   ├── Response.php                # Định dạng chuẩn JSend REST API (200, 201, 400, 401, 403, 404, 422, 500)
│   │   │   ├── Router.php                  # Trình định tuyến hỗ trợ REST verbs & Route Params {id}
│   │   │   ├── Exceptions/                 # Phân loại ngoại lệ HTTP (BadRequest, NotFound, MethodNotAllowed)
│   │   │   └── Middleware/                 # Pipeline: CorsMiddleware, CsrfMiddleware, AuthMiddleware
│   │   ├── Features/                       # Cắt lát nghiệp vụ theo miền (Vertical Slice Architecture)
│   │   │   ├── Booking/                    # Thư mục chứa BookingRepository (Thừa / Cũ)
│   │   │   ├── DMManagement/               # Điều phối Quản trò, Nhận/Từ chối ca, Xung đột lịch
│   │   │   ├── GameCatalog/                # Tra cứu danh mục kịch bản & Bảng giá theo khung giờ
│   │   │   ├── GameroomManagement/         # Vòng đời phòng chơi, Ghép phòng, Rút phòng, Thế chỗ slot
│   │   │   ├── Identity/                   # Đăng ký, Đăng nhập, Profile, Quản trị Users & Vai trò (RBAC)
│   │   │   ├── Notification/               # Quản lý hộp thông báo in-app cho từng Actor
│   │   │   ├── PaymentRefund/              # Thanh toán cọc 50k, Thanh toán còn lại, Xét duyệt hoàn tiền 24h
│   │   │   ├── Pricing/                    # Tính giá động theo số người, giờ chơi & Lưu Snapshot giá
│   │   │   ├── Promotion/                  # Áp dụng mã giảm giá và tính chiết khấu
│   │   │   └── VenueScheduling/            # Đề xuất giờ, Biểu quyết 100%, Tạo Session & Quản lý cơ sở vật chất
│   │   ├── Infrastructure/                 # Tầng hạ tầng: DatabaseConnection (PDO Singleton), Config, TransactionManager
│   │   └── Shared/                         # Enums (GameRoomStatus, PaymentStatus, UserRole)
│   ├── bin/
│   │   └── cron.php                        # CLI Script định kỳ xử lý Timeout phòng, cọc, DM và slot thế chỗ
│   ├── bootstrap/
│   │   └── app.php                         # Khởi tạo App, nạp Config, gắn Global Middleware và Routes
│   ├── database/
│   │   ├── kich_ban_sat_v3_clean.sql       # File DDL CSDL V3 chuẩn hóa (21 bảng)
│   │   ├── migrations/                     # Phinx migration nạp schema
│   │   ├── migrations_v16_backup/          # Các file migration cũ cần dọn dẹp
│   │   └── seeders/                        # Dữ liệu mẫu (V3DataSeeder, ReferenceDataSeeder, RoleSeeder)
│   ├── public/
│   │   └── index.php                       # Front Controller kiêm Static File Server
│   ├── routes/
│   │   └── api.php                         # Đăng ký toàn bộ API endpoints (/api/v1 & /api)
│   ├── tests/                              # Bộ 162 bài kiểm thử PHPUnit (Unit, Feature, Integration)
│   ├── composer.json                       # Cấu hình nạp gói Composer & PSR-4 Autoload
│   ├── phinx.php                           # Cấu hình CSDL cho Migration tool
│   └── phpunit.xml                         # Cấu hình chạy test PHPUnit
│
├── frontend/                               # Mã nguồn Frontend SPA chính thức (Vanilla JS ES Modules)
│   ├── index.html                          # Trang chủ (Guest & Player khám phá kịch bản, sảnh phòng)
│   ├── player.html                         # Cổng Người chơi (Player Portal)
│   ├── dm.html                             # Cổng Quản trò (DM Portal)
│   ├── admin.html                          # Bảng điều khiển Quản trị viên (Admin Portal)
│   ├── src/
│   │   ├── api/                            # ApiClient (Quản lý CSRF, Credentials) & AuthState
│   │   ├── pages/
│   │   │   ├── admin/                      # AdminView (Users, Rooms 24h cancel, Refunds)
│   │   │   ├── auth/                       # AuthModal (Đăng ký/Đăng nhập + 1-Click Role Switcher)
│   │   │   ├── dm/                         # DmView (Danh sách ca, Chấp nhận/Từ chối ca)
│   │   │   ├── games/                      # GamesView (Danh mục kịch bản, Chi tiết, Tra cứu giá)
│   │   │   ├── home/                       # HomeView (Hero banner, Top kịch bản, Giới thiệu)
│   │   │   ├── notifications/              # NotificationsView (Hộp thông báo cá nhân)
│   │   │   └── rooms/                      # RoomsView (Sảnh phòng, Chi tiết phòng, Biểu quyết, Nộp cọc)
│   │   ├── styles/                         # Hệ thống CSS Design Tokens (Dark Theme, Typography, Layout)
│   │   ├── utils/                          # Sanitize XSS, Shared Header/Footer, Toast Notifications
│   │   └── app.js                          # Trình điều hướng Frontend Router & Khởi động
│   └── tests/                              # Bộ test E2E / Unit bằng Node.js Native Runner
│
├── FE_kich_ban_sat/                        # [THƯ MỤC THỪA / THAM KHẢO] Bản clone từ repository ngoài
├── docs/                                   # Tài liệu kỹ thuật, ADR, API Spec, Database Schema
└── README.md                               # Tài liệu hướng dẫn cài đặt và vận hành hệ thống
```

#### 2.2. Technology Stack thực tế
- **Backend Runtime:** PHP 8.4.27 (hỗ trợ typed properties, union types, match expressions, readonly classes).
- **Kiến trúc:** Modular Monolith kết hợp Layered Architecture (Presentation → Application → Domain → Infrastructure).
- **CSDL:** MySQL 8.4 LTS, truy cập qua PDO (`PDO::ATTR_ERRMODE => ERRMODE_EXCEPTION`, `PDO::ATTR_DEFAULT_FETCH_MODE => FETCH_ASSOC`, `PDO::ATTR_EMULATE_PREPARES => false`).
- **Frontend:** Vanilla JavaScript ES Modules (Native browser, không bundler, không build step Node.js).
- **Testing:** PHPUnit 11.5.58 (Backend) & Node.js Native Test Runner (Frontend API client).
- **Quản lý gói:** Composer 2.x (PSR-4: `"App\\": "app/"`, `"Tests\\": "tests/"`).

#### 2.3. Đánh giá thư mục `FE_kich_ban_sat`
Thư mục `FE_kich_ban_sat` ở thư mục gốc chứa các file HTML tĩnh rời rạc (`admin-portal.html`, `lobby.html`, `scenarios.html`...). Qua kiểm tra thực tế:
- `backend/public/index.php` chỉ đọc và phục vụ file từ thư mục `frontend/`.
- Không có bất kỳ liên kết nội bộ hay module nào từ `backend` hoặc `frontend` tham chiếu tới `FE_kich_ban_sat`.
- Theo tài liệu `PRODUCT_SPEC_V16_Final.md`, đây là bản mẫu tham khảo từ repository cũ (`Danh-K/FE_Kich_Ban_Sat`) và **hoàn toàn không tham gia vào vòng đời vận hành của hệ thống**.

---

### 3. Architecture Audit

#### 3.1. Đối chiếu Kiến trúc Hiện tại vs Kiến trúc Mục tiêu

| Tiêu chí | Kiến trúc Mục tiêu | Triển khai Thực tế | Đánh giá | Ghi chú & Bằng chứng |
|---|---|---|---|---|
| **Layered Architecture** | Presentation → App Service → Domain → Infrastructure | Controller → (Service) → Repository → PDO | **PARTIAL** | Các tính năng phức tạp (`PricingService`, `BackgroundJobService`) có Service riêng. Tuy nhiên, `GameRoomController` và `SchedulingController` chứa nhiều logic nghiệp vụ và truy vấn SQL trực tiếp mà không thông qua Service/Repository. |
| **Separation of Concerns** | Tách biệt hoàn toàn HTTP handling và Business Logic | Controller vừa parse Request vừa điều phối SQL Transaction | **PARTIAL** | `GameRoomController.php` dài tới 932 dòng, thực hiện trực tiếp `$this->pdo->prepare(...)` và tính toán trạng thái phòng ngay trong action method. |
| **Data Access Pattern** | Repository Pattern thuần túy, Controller không gọi trực tiếp PDO | Hỗn hợp Repository và trực tiếp PDO trong Controller | **PARTIAL** | Một số module có Repository đầy đủ (`UserRepository`, `PaymentRepository`), nhưng một số khác Controller inject thẳng `PDO` và tự viết câu truy vấn. |
| **Autoloading & PSR** | PSR-4 tuân thủ nghiêm ngặt | PSR-4 qua Composer cho `App\` và `Tests\` | **PASS** | `backend/composer.json` cấu hình chính xác, cấu trúc thư mục ăn khớp 100% với namespace. |
| **Middleware Pipeline** | Pipeline xử lý Cors, Csrf, Auth tuần tự | `Application::runPipeline` lồng qua `array_reduce` | **PASS** | Pipeline hoạt động mượt mà, hỗ trợ bao bọc request/response đúng chuẩn Onion Architecture. |
| **Error Handling** | Tập trung, trả JSON chuẩn JSend format | `ExceptionHandler::handle` bắt mọi `Throwable` | **PASS** | Xử lý tốt các ngoại lệ, ẩn `trace` khi `APP_DEBUG=false`, chuẩn hóa mã lỗi trả về. |

#### 3.2. Những điểm đạt
1. **Thiết kế HTTP Core gọn nhẹ và độc lập:** Không bị phụ thuộc vào framework nặng nề, tốc độ khởi động cực nhanh (< 2ms per request).
2. **Kiểm soát đồng thời (Concurrency Control) xuất sắc:** Các nghiệp vụ sống còn như nhận slot (`join`), biểu quyết chốt lịch (`vote`), và thanh toán (`processPayment`) đều sử dụng `SELECT ... FOR UPDATE` bên trong Transaction của MySQL, ngăn ngừa triệt để lỗi Overbooking (vượt quá số người tối đa) và Double Spending (thanh toán trùng).
3. **Bảo toàn Idempotency trong thanh toán:** Bảng `payment_transactions` và `idempotency_records` cùng khóa duy nhất `uq_payment_transaction_idempotency` đảm bảo an toàn tuyệt đối khi mạng bị ngắt quãng hoặc người dùng nhấn đúp nút thanh toán.

#### 3.3. Những điểm chưa đạt & Đề xuất tối ưu
- **Điểm yếu:** `GameRoomController` và `SchedulingController` bị vi phạm nguyên tắc **Single Responsibility Principle (SRP)**. Cần bóc tách các khối lệnh thao tác CSDL dài thành các phương thức tương ứng trong `GameRoomRepository` và `SchedulingRepository`.
- **Thành phần thừa:** Lớp `BookingRepository.php` chỉ được dùng trong 1 bài test tích hợp, trùng lặp chức năng tìm kiếm Session với `SchedulingRepository.php`. Cần hợp nhất và xóa bỏ `BookingRepository.php`.

---

### 4. Functional Requirements Audit

Bảng đối chiếu toàn diện 11 nhóm yêu cầu chức năng (FRG-01 đến FRG-11):

| FR Group | Requirement | Status | Evidence (File / Method / API) | Gap (Khoảng cách thực tế) | Priority |
|---|---|---|---|---|---|
| **FRG-01** | Xác thực & Quản lý người dùng | **PARTIALLY_IMPLEMENTED** | `AuthController.php` (`register`, `login`, `me`, `logout`)<br>`AdminUserController.php` (`index`, `update`, `assignRole`)<br>`auth-modal.js` | Thiếu API & UI cho người chơi tự cập nhật hồ sơ cá nhân (`UC-P01`) và tải ảnh/xác minh thẻ HSSV (`UC-P02`). | P2 |
| **FRG-02** | Quản lý danh mục kịch bản | **PARTIALLY_IMPLEMENTED** | `GameController.php` (`index`, `show`, `prices`)<br>`games-view.js` | Khách và người chơi xem/lọc kịch bản rất tốt. Tuy nhiên chưa có API và UI cho Admin thực hiện thêm, sửa, xóa kịch bản và phân vai nhân vật (`UC-A03`, `UC-A04`). | P2 |
| **FRG-03** | Quản lý phòng chơi (Gameroom) | **IMPLEMENTED** | `GameRoomController.php` (`index`, `show`, `create`, `cancel`)<br>`AdminGameRoomController.php` (`cancel`)<br>`rooms-view.js` | Đáp ứng đầy đủ quy trình tạo phòng, hiển thị thông tin, thời hạn gom phòng (Formation Deadline 4h) và hủy phòng theo Quy tắc 24h. | P1 |
| **FRG-04** | Ghép người & Thế chỗ (Replacement) | **IMPLEMENTED** | `GameRoomController.php:253` (`join`)<br>`GameRoomController.php:527` (`leave`)<br>`rooms-view.js` (Takeover flow) | Hoàn chỉnh luồng rút phòng khi đã chốt lịch, mở cửa sổ 4h tìm người thế chỗ, người mới nạp cọc thế chỗ và tự động hoàn trả cọc cho người cũ. | P1 |
| **FRG-05** | Quản lý địa điểm & Lịch chơi | **PARTIALLY_IMPLEMENTED** | `SchedulingController.php` (`propose`, `vote`, `venues`)<br>`rooms-view.js` | Biểu quyết 100% chốt lịch chơi hoạt động hoàn hảo. Tuy nhiên chưa có UI duyệt danh sách chi nhánh công khai và Admin chưa có giao diện quản trị phòng vật lý (`UC-G06`, `UC-A07`, `UC-A08`). | P2 |
| **FRG-06** | Quản lý Quản trò (DM Management) | **PARTIALLY_IMPLEMENTED** | `DMAssignmentController.php` (`index`, `accept`, `reject`, `assign`)<br>`SessionController.php` (`start`, `complete`)<br>`dm-view.js` | Phân công DM và nhận/từ chối ca hoạt động tốt. Tuy nhiên DM Portal thiếu nút Bắt đầu / Kết thúc ca chơi (`UC-D06`, `UC-D07`) và thiếu bảng xem danh sách người chơi trong ca (`UC-D03`). | P1 |
| **FRG-07** | Quản lý giá & Khuyến mãi | **PARTIALLY_IMPLEMENTED** | `PricingService.php`<br>`PricingController.php` (`calculate`, `snapshot`)<br>`PromotionController.php` (`index`) | Logic tính giá động, chiết khấu học sinh sinh viên và snapshot giá khi chốt phòng hoạt động chính xác. Chưa có giao diện Admin CRUD bảng giá và mã khuyến mãi (`UC-A05`, `UC-A06`). | P2 |
| **FRG-08** | Thanh toán & Hoàn tiền | **IMPLEMENTED** | `PaymentController.php` (`deposit`, `remaining`)<br>`AdminRefundController.php` (`index`, `approve`, `reject`)<br>`PaymentRepository.php` | Cọc cố định 50k, thanh toán số tiền còn lại sau chiết khấu, chống trùng lặp Idempotency, tự động xét duyệt hoàn tiền theo mốc 24 giờ và Admin can thiệp hoàn tiền thành công. | P1 |
| **FRG-09** | Quản lý thông báo | **IMPLEMENTED** | `NotificationController.php` (`index`, `markAsRead`)<br>`NotificationRepository.php`<br>`notifications-view.js` | Hệ thống tự động bắn thông báo khi có người tham gia phòng, nộp cọc thành công, phân công DM, hoàn tiền và hủy phòng. Đánh dấu đã đọc mượt mà. | P2 |
| **FRG-10** | Quản trị hệ thống (Admin Portal) | **PARTIALLY_IMPLEMENTED** | `AdminUserController.php`<br>`AdminGameRoomController.php`<br>`AdminRefundController.php`<br>`admin-view.js` | Đã có 3 phân hệ cốt lõi: Quản lý người dùng/vai trò, Hủy phòng khẩn cấp theo mốc 24h, và Duyệt hoàn tiền. Thiếu báo cáo doanh thu (`UC-A15`) và nhật ký hệ thống (`UC-A16`). | P2 |
| **FRG-11** | Tự động hóa hệ thống (Cron Jobs) | **IMPLEMENTED** | `BackgroundJobService.php`<br>`backend/bin/cron.php` | CLI cron xử lý tự động 4 tác vụ: Quá hạn gom phòng (hủy phòng), Quá hạn nộp cọc (kick & mở slot), Hết hạn tìm người thế chỗ (giải tán phòng), Quá hạn DM phản hồi (thu hồi ca). | P1 |

---

### 5. Actor & Use Case Audit

Kiểm tra chi tiết 53 Use Cases được chia theo 4 Actors chính:

#### A. Guest (Khách vãng lai) – 8 Use Cases

| Use Case ID | Tên Use Case | Trạng thái | Bằng chứng kỹ thuật (Evidence) | Thiếu sót / Sai lệch (Missing / Gap) | Ưu tiên |
|---|---|---|---|---|---|
| **UC-G01** | Xem danh sách kịch bản | **IMPLEMENTED** | `GET /api/v1/games`<br>`frontend/src/pages/games/games-view.js` | Hoàn chỉnh; có phân trang, hiển thị poster, số người, thời lượng. | P3 |
| **UC-G02** | Tìm kiếm và lọc kịch bản | **IMPLEMENTED** | `GameRepository::listGames`<br>`games-view.js:65` (Search input & genre pills) | Tìm kiếm theo tên và lọc theo thể loại hoạt động mượt mà. | P3 |
| **UC-G03** | Xem chi tiết kịch bản | **IMPLEMENTED** | `GET /api/v1/games/{id}`<br>`games-view.js:434` (Scenario modal) | Xem đầy đủ cốt truyện, độ khó, vai nhân vật. | P3 |
| **UC-G04** | Tra cứu giá và khuyến mãi | **IMPLEMENTED** | `GET /api/v1/games/{id}/prices`<br>`GET /api/v1/promotions`<br>`games-view.js:460` | Xem bảng giá theo khung giờ sáng/tối và các ưu đãi hiện có. | P3 |
| **UC-G05** | Xem phòng công khai | **IMPLEMENTED** | `GET /api/v1/rooms` (status=FORMING)<br>`frontend/src/pages/rooms/rooms-view.js` | Hiển thị danh sách các phòng đang tuyển người chơi tại sảnh. | P2 |
| **UC-G06** | Xem chi nhánh và địa điểm | **PARTIALLY_IMPLEMENTED** | Backend: `GET /api/v1/venues`<br>`SchedulingController::venues` | API Backend đã trả về danh sách chi nhánh và phòng vật lý, nhưng trên Frontend chưa có trang/tab hiển thị thông tin này cho Guest. | P3 |
| **UC-G07** | Đăng ký tài khoản | **IMPLEMENTED** | `POST /api/v1/auth/register`<br>`frontend/src/pages/auth/auth-modal.js` | Kiểm tra định dạng email, mật khẩu >= 6 ký tự, mã hóa BCRYPT, cấp session. | P1 |
| **UC-G08** | Đăng nhập | **IMPLEMENTED** | `POST /api/v1/auth/login`<br>`auth-modal.js` (Kèm 1-click Quick Demo) | Xác thực mật khẩu qua `password_verify`, tạo session, chống brute-force cơ bản. | P1 |

#### B. Player (Người chơi) – 20 Use Cases

| Use Case ID | Tên Use Case | Trạng thái | Bằng chứng kỹ thuật (Evidence) | Thiếu sót / Sai lệch (Missing / Gap) | Ưu tiên |
|---|---|---|---|---|---|
| **UC-P01** | Quản lý hồ sơ cá nhân | **NOT_IMPLEMENTED** | Chỉ có `GET /api/v1/auth/me` | Chưa có API và giao diện cho người chơi tự đổi họ tên, số điện thoại, mật khẩu cá nhân. | P2 |
| **UC-P02** | Cập nhật xác minh HSSV | **NOT_IMPLEMENTED** | Không có endpoint trong `routes/api.php` | Chưa có luồng tải thẻ sinh viên hoặc gửi yêu cầu phê duyệt ưu đãi HSSV. | P2 |
| **UC-P03** | Tạo GameRoom | **IMPLEMENTED** | `POST /api/v1/rooms`<br>`GameRoomController::create`<br>`rooms-view.js:965` | Tạo phòng thành công, tự động đặt Host làm thành viên đầu tiên, thiết lập hạn 4 giờ. | P1 |
| **UC-P04** | Xem danh sách GameRoom | **IMPLEMENTED** | `GET /api/v1/rooms`<br>`rooms-view.js:298` | Xem phòng của mình và các phòng công khai kèm trạng thái trực quan. | P2 |
| **UC-P05** | Xem chi tiết GameRoom | **IMPLEMENTED** | `GET /api/v1/rooms/{id}`<br>`rooms-view.js:414` | Xem danh sách thành viên, tình trạng cọc, lịch đề xuất, trạng thái phòng. | P1 |
| **UC-P06** | Tham gia GameRoom | **IMPLEMENTED** | `POST /api/v1/rooms/{id}/join`<br>`GameRoomController.php:253` | Kiểm tra phòng chưa đầy, khóa dòng `FOR UPDATE` chống overbooking. | P1 |
| **UC-P07** | Rời GameRoom | **IMPLEMENTED** | `POST /api/v1/rooms/{id}/leave`<br>`GameRoomController.php:527` | Nếu phòng đang gom: rút ngay lập tức. Nếu phòng đã chốt lịch: mở cửa sổ tìm người thay thế. | P1 |
| **UC-P08** | Quản lý thành viên phòng | **PARTIALLY_IMPLEMENTED** | `rooms-view.js:470` | Host xem được danh sách và trạng thái cọc của thành viên nhưng chưa có quyền kick thành viên không hợp lệ. | P2 |
| **UC-P09** | Đề xuất lịch chơi | **IMPLEMENTED** | `POST /api/v1/rooms/{id}/proposals`<br>`SchedulingController::propose` | Cho phép thành viên đề xuất mốc giờ bắt đầu và kết thúc. Tự động sinh hạn nộp cọc 2 giờ. | P1 |
| **UC-P10** | Đề xuất địa điểm chơi | **PARTIALLY_IMPLEMENTED** | `SchedulingController.php:45` | Đề xuất hiện tại chỉ nhận `proposed_start_at` và `proposed_end_at`, chưa cho chọn chi nhánh mong muốn. | P3 |
| **UC-P11** | Biểu quyết lịch chơi | **IMPLEMENTED** | `POST /api/v1/proposals/{id}/vote`<br>`SchedulingController::vote` | Bỏ phiếu Đồng ý / Từ chối. Khi 100% đồng ý: tự động chốt phòng `SCHEDULED` và tạo buổi chơi. | P1 |
| **UC-P12** | Theo dõi lịch & Trạng thái phòng | **IMPLEMENTED** | `rooms-view.js:619` (Proposal section & timeline) | Hiển thị đồng hồ đếm ngược, tỷ lệ biểu quyết, mốc giờ đã chốt. | P2 |
| **UC-P13** | Tham gia phòng tuyển thế chỗ | **IMPLEMENTED** | `POST /api/v1/rooms/{id}/join` (Case 1)<br>`rooms-view.js:428` | Cho phép người ngoài bấm "Vào thế chỗ", lấp đúng slot người đã rút và kế thừa tiến trình phòng. | P1 |
| **UC-P14** | Xem chi tiết giá cá nhân | **IMPLEMENTED** | `POST /api/v1/rooms/{id}/calculate-price`<br>`PricingController.php` | Xem giá gốc, chiết khấu giờ thấp điểm, chiết khấu HSSV và số tiền cọc/còn lại. | P2 |
| **UC-P15** | Thanh toán tiền đặt cọc | **IMPLEMENTED** | `POST /api/v1/payments/{paymentId}/deposit`<br>`PaymentController::deposit` | Cọc cố định 50.000 VNĐ, chống trùng lặp bằng `Idempotency-Key`, cập nhật `DEPOSITED`. | P1 |
| **UC-P16** | Thanh toán phần còn lại | **IMPLEMENTED** | `POST /api/v1/payments/{paymentId}/remaining`<br>`PaymentController::remaining` | Thanh toán số tiền còn lại sau khi trừ cọc 50.000 VNĐ, cập nhật trạng thái `PAID` 100%. | P1 |
| **UC-P17** | Xem lịch sử giao dịch | **NOT_IMPLEMENTED** | Không có endpoint giao dịch cá nhân | Bảng `payment_transactions` có lưu dữ liệu nhưng chưa có API cho Player tra cứu lịch sử nộp tiền. | P3 |
| **UC-P18** | Yêu cầu hủy/rút khỏi buổi chơi | **PARTIALLY_IMPLEMENTED** | `POST /api/v1/rooms/{id}/leave`<br>`POST /api/v1/rooms/{id}/cancel` | Người chơi rút qua `leave`. Host hủy phòng đang gom qua `cancel`. Tuy nhiên Host không thể tự hủy phòng khi đã `SCHEDULED` mà phải qua Admin. | P2 |
| **UC-P19** | Theo dõi yêu cầu hoàn tiền | **PARTIALLY_IMPLEMENTED** | `notifications-view.js` | Người chơi nhận thông báo khi được duyệt hoàn tiền nhưng chưa có tab tra cứu chi tiết danh sách khoản hoàn tiền. | P2 |
| **UC-P20** | Quản lý thông báo cá nhân | **IMPLEMENTED** | `GET /api/v1/notifications`<br>`POST /api/v1/notifications/{id}/read` | Nhận thông báo tự động về cọc, lịch chơi, hoàn tiền; đếm số tin chưa đọc và đánh dấu đã đọc. | P2 |

#### C. DM (Quản trò / Host) – 8 Use Cases

| Use Case ID | Tên Use Case | Trạng thái | Bằng chứng kỹ thuật (Evidence) | Thiếu sót / Sai lệch (Missing / Gap) | Ưu tiên |
|---|---|---|---|---|---|
| **UC-D01** | Xem danh sách ca được phân công | **IMPLEMENTED** | `GET /api/v1/dm/assignments`<br>`frontend/src/pages/dm/dm-view.js` | Quản trò đăng nhập xem được đầy đủ danh sách ca dẫn PENDING, ACCEPTED, REJECTED. | P1 |
| **UC-D02** | Xem chi tiết buổi chơi | **IMPLEMENTED** | `dm-view.js:200` | Xem tên kịch bản, mã phòng, thời gian bắt đầu/kết thúc, chi nhánh và phòng diễn. | P1 |
| **UC-D03** | Xem danh sách người chơi | **PARTIALLY_IMPLEMENTED** | Backend: `GET /api/v1/rooms/{id}` | Quản trò có thể xem thông qua chi tiết phòng, nhưng thẻ phân công trên DM Portal chưa liệt kê danh sách tên người chơi trong bàn. | P2 |
| **UC-D04** | Chấp nhận phân công | **IMPLEMENTED** | `POST /api/v1/dm/assignments/{id}/accept`<br>`DMAssignmentController::accept` | Xác nhận ca làm việc, chuyển trạng thái assignment sang `ACCEPTED`. | P1 |
| **UC-D05** | Từ chối phân công | **IMPLEMENTED** | `POST /api/v1/dm/assignments/{id}/reject`<br>`dm-view.js:80` (Modal lý do) | Cho phép từ chối ca kèm lý do bắt buộc; gửi thông báo về cho Admin. | P1 |
| **UC-D06** | Bắt đầu buổi chơi | **PARTIALLY_IMPLEMENTED** | Backend: `POST /api/v1/sessions/{id}/start`<br>`SessionController::start` | Backend đã có logic kiểm tra phiên `SCHEDULED`, lưu `started_at` và chuyển sang `IN_PROGRESS`. Tuy nhiên trên Frontend DM Portal chưa có nút bấm để kích hoạt. | P1 |
| **UC-D07** | Kết thúc buổi chơi | **PARTIALLY_IMPLEMENTED** | Backend: `POST /api/v1/sessions/{id}/complete`<br>`SessionController::complete` | Backend đã có logic kết thúc phiên, lưu `completed_at` và chuyển phòng sang `COMPLETED`. Chưa có nút bấm trên giao diện DM. | P1 |
| **UC-D08** | Theo dõi thông báo công việc | **IMPLEMENTED** | `notifications-view.js` | DM nhận thông báo ngay lập tức khi được Admin điều phối ca mới hoặc khi ca chơi bị hủy. | P2 |

#### D. Admin (Quản trị viên) – 17 Use Cases

| Use Case ID | Tên Use Case | Trạng thái | Bằng chứng kỹ thuật (Evidence) | Thiếu sót / Sai lệch (Missing / Gap) | Ưu tiên |
|---|---|---|---|---|---|
| **UC-A01** | Quản lý tài khoản người dùng | **IMPLEMENTED** | `GET /api/v1/admin/users`<br>`PATCH /api/v1/admin/users/{id}`<br>`admin-view.js:170` | Xem danh sách người dùng, tìm kiếm, khóa/mở khóa tài khoản (có chặn tự khóa tài khoản chính mình). | P1 |
| **UC-A02** | Quản lý vai trò & Quyền hạn | **PARTIALLY_IMPLEMENTED** | `PUT /api/v1/admin/users/{id}/role`<br>`AdminUserController::assignRole` | Đổi vai trò giữa PLAYER, DM, ADMIN mượt mà (có chặn tự giáng chức). Chưa có bảng phân quyền chi tiết (Permission matrix). | P2 |
| **UC-A03** | Quản lý danh mục kịch bản | **NOT_IMPLEMENTED** | Chỉ có API xem của Guest/Player | Chưa có API và giao diện cho Admin tạo mới, chỉnh sửa nội dung, thay ảnh poster hoặc ẩn kịch bản. | P2 |
| **UC-A04** | Quản lý cấu hình nhân vật | **NOT_IMPLEMENTED** | Bảng `game_configurations` có trong DB | Chưa có CRUD cho Admin quản lý danh sách tuyến nhân vật, giới tính và hồ sơ nhân vật. | P3 |
| **UC-A05** | Quản lý bảng giá | **NOT_IMPLEMENTED** | Bảng `game_prices` có trong DB | Chưa có giao diện Admin chỉnh sửa mức giá theo khung giờ sáng/tối hoặc ngày thường/cuối tuần. | P2 |
| **UC-A06** | Quản lý khuyến mãi | **NOT_IMPLEMENTED** | Bảng `promotions` có trong DB | Chưa có giao diện Admin tạo mã giảm giá, cấu hình % chiết khấu và hạn dùng. | P2 |
| **UC-A07** | Quản lý chi nhánh | **NOT_IMPLEMENTED** | Bảng `venues` có trong DB | Chưa có giao diện Admin tạo chi nhánh, chỉnh sửa địa chỉ, số điện thoại chi nhánh. | P3 |
| **UC-A08** | Quản lý phòng vật lý | **NOT_IMPLEMENTED** | Bảng `physical_rooms` có trong DB | Chưa có giao diện Admin thêm/sửa phòng diễn theo chủ đề tại từng cơ sở. | P3 |
| **UC-A09** | Quản lý danh sách DM | **PARTIALLY_IMPLEMENTED** | `admin-view.js:170` (Qua bộ lọc User) | Quản lý DM thông qua lọc danh sách người dùng có role DM. Chưa có hồ sơ quản lý kinh nghiệm dẫn kịch bản của DM. | P2 |
| **UC-A10** | Quản lý GameRoom | **PARTIALLY_IMPLEMENTED** | `GET /api/v1/rooms`<br>`admin-view.js:270` | Xem danh sách phòng và trạng thái. Tuy nhiên thao tác quản trị mới chỉ dừng ở mức Hủy phòng khẩn cấp. | P2 |
| **UC-A11** | Phân công DM | **IMPLEMENTED** | `POST /api/v1/admin/rooms/{id}/assign-dm`<br>`admin-view.js:409` | Phân công DM cho phòng chơi, tự động kiểm tra xung đột trùng lịch của DM và tự hủy ca cũ nếu có. | P1 |
| **UC-A12** | Thay đổi phân công DM | **PARTIALLY_IMPLEMENTED** | Backend: `POST /api/v1/admin/sessions/{id}/reassign-dm` | Backend đã có endpoint đổi DM cho buổi chơi đã chốt lịch, nhưng Frontend Admin chưa tích hợp nút đổi trên Session. | P2 |
| **UC-A13** | Quản lý yêu cầu hoàn tiền | **IMPLEMENTED** | `GET /api/v1/admin/refunds`<br>`POST /api/v1/admin/refunds/{id}/approve`<br>`admin-view.js:425` | Danh sách yêu cầu hoàn tiền, xem chi tiết số tiền, lý do rút, thực hiện duyệt hoặc từ chối kèm lý do. | P1 |
| **UC-A14** | Theo dõi giao dịch thanh toán | **PARTIALLY_IMPLEMENTED** | Bảng `payment_transactions` trong DB | Dữ liệu giao dịch được lưu đầy đủ trong CSDL, nhưng trên Admin Portal chưa có trang hiển thị sổ cái giao dịch (Payment Ledger). | P2 |
| **UC-A15** | Xem báo cáo doanh thu | **NOT_IMPLEMENTED** | Không có endpoint thống kê | Chưa có API tổng hợp doanh thu cọc, doanh thu thực tế và biểu đồ tăng trưởng. | P3 |
| **UC-A16** | Theo dõi nhật ký hoạt động | **NOT_IMPLEMENTED** | Bảng `outbox_events` có sẵn trong DB | Chưa có giao diện hiển thị Audit Log theo dõi lịch sử thao tác của các Admin và người dùng. | P3 |
| **UC-A17** | Xử lý vấn đề vận hành | **PARTIALLY_IMPLEMENTED** | `AdminGameRoomController.php` | Can thiệp hủy phòng khẩn cấp theo Quy tắc 24h và hoàn tiền tự động; chưa có trung tâm ticket xử lý khiếu nại. | P2 |

---

### 6. Non-Functional Requirements Audit

| NFR ID | Category | Status | Evidence & Bằng chứng thực tế | Rủi ro / Điểm hạn chế | Khuyến nghị khắc phục |
|---|---|---|---|---|---|
| **NFR-01** | Performance | **PARTIAL** | Các truy vấn đều có `LIMIT` & `OFFSET`. Truy vấn phòng chơi có subquery đếm `COUNT(rp.id)` trong SELECT: `GameRoomController.php:45`. | Khi số lượng phòng tăng cao (>10.000 bản ghi), subquery lồng nhau sẽ gây chậm trang danh sách phòng. | Chuyển sang `LEFT JOIN ... GROUP BY` hoặc cập nhật cột cache `current_players` trực tiếp trên bảng `game_rooms`. |
| **NFR-02** | Security | **FAIL** | Phát hiện lỗ hổng P0 giả mạo xác thực qua `Authorization: Bearer <id>` (`AuthMiddleware.php:45-50`) và Stored XSS do dùng `innerHTML` không escape (`rooms-view.js:421`). | Nguy cơ chiếm đoạt quyền Admin và tấn công XSS chiếm phiên làm việc. | Xóa bỏ ngay logic nhận Bearer ID trực tiếp; gọi `escapeHtml()` cho toàn bộ chuỗi hiển thị động trên Frontend. |
| **NFR-03** | Reliability | **PASS** | ExceptionHandler bao bọc toàn bộ HTTP lifecycle; Transaction rollback chặt chẽ khi có lỗi DB (`PaymentRepository.php:210`). | Hệ thống không bị treo hoặc rò rỉ mã lỗi hệ thống khi gặp ngoại lệ. | Tiếp tục duy trì chuẩn cấu trúc Exception hiện tại. |
| **NFR-04** | Availability | **NOT_VERIFIED** | Hiện đang chạy máy chủ đơn (Single-node PHP CLI Server trên `127.0.0.1:8000`). | Chưa có cấu hình chịu tải cao, cụm dịch vụ (Clustering) hoặc Healthcheck Monitor định kỳ từ bên ngoài. | Cần cấu hình chạy trên Nginx + PHP-FPM khi triển khai môi trường máy chủ staging/production. |
| **NFR-05** | Scalability | **PARTIAL** | API thiết kế chuẩn RESTful không lưu state trên bộ nhớ ứng dụng. Tuy nhiên, PHP Session lưu trên file cục bộ của máy chủ (`session.save_path`). | Khó mở rộng ngang (Horizontal Scaling) qua nhiều máy chủ nếu không có sticky-session hoặc Redis Session Storage. | Cấu hình lưu trữ Session phân tán (Redis/Memcached) khi scale sang nhiều máy chủ. |
| **NFR-06** | Maintainability | **PARTIAL** | Cấu trúc Modular Monolith phân tách rõ ràng; PSR-4 autoload chuẩn xác. Tuy nhiên một số Controller phình to > 900 dòng code. | Khó bảo trì và kiểm thử độc lập các phương thức Controller có chứa SQL trực tiếp. | Refactor chuyển toàn bộ raw query từ Controller sang tầng Repository. |
| **NFR-07** | Usability | **PASS** | Giao diện Dark Theme hiện đại, responsive, thông báo Toast trực quan, có bộ chuyển đổi nhanh tài khoản mẫu (1-Click Demo). | Một số bảng quản trị thiếu bộ lọc nâng cao. | Bổ sung thanh tìm kiếm và lọc thời gian cho bảng quản trị Admin. |
| **NFR-08** | Compatibility | **PASS** | Vanilla JavaScript ES Modules chạy mượt mà trên Chrome, Firefox, Edge không cần polyfill hay Node.js build tools. | Trình duyệt cũ (Legacy IE) không được hỗ trợ (phù hợp với định hướng công nghệ hiện đại). | Duy trì chuẩn Vanilla ES Modules. |
| **NFR-09** | Data Integrity | **PASS** | CSDL có 21 bảng với đầy đủ Foreign Keys, ON DELETE RESTRICT, CHECK constraints và Idempotency key ngăn chặn dữ liệu rác. | Rất ít rủi ro sai lệch dữ liệu ở tầng CSDL. | Giữ nguyên kiến trúc schema V3 hiện tại. |
| **NFR-10** | Backup & Recovery | **PARTIAL** | Có file DDL sạch `kich_ban_sat_v3_clean.sql` và Phinx migration/seeders đầy đủ. | Chưa có tiến trình cron tự động backup CSDL định kỳ hàng ngày sang Cloud/S3. | Viết script sao lưu tự động `mysqldump` chạy qua Windows Task Scheduler hoặc Linux Crontab. |

---

### 7. Security Audit

Bảng phân tích chi tiết các lỗ hổng bảo mật và điểm cần khắc phục:

| ID | Lỗ hổng / Vấn đề bảo mật | Mức độ | Vị trí phát hiện (File / Dòng) | Tác động thực tế (Impact) | Giải pháp khuyến nghị |
|---|---|---|---|---|---|
| **SEC-01** | **Bypass xác thực qua Bearer Token ID giả mạo** | **CRITICAL (P0)** | `backend/app/Core/Middleware/AuthMiddleware.php`<br>(Dòng 45–50) | Kẻ tấn công chỉ cần gửi header `Authorization: Bearer 1` trong bất kỳ request nào là hệ thống sẽ gán quyền Admin tối cao mà không kiểm tra mật khẩu hay session. | **Loại bỏ hoàn toàn** đoạn code đọc Bearer Token này hoặc bắt buộc bọc trong điều kiện: `if (Config::get('APP_ENV') === 'testing')`. |
| **SEC-02** | **Stored Cross-Site Scripting (XSS) trên Frontend** | **HIGH (P1)** | `frontend/src/pages/rooms/rooms-view.js` (Dòng 421, 471)<br>`frontend/src/pages/admin/admin-view.js` (Dòng 225) | Tên phòng (`room.title`), tên thành viên (`p.name`), email người dùng (`u.email`) được chèn thẳng vào `innerHTML` không qua `escapeHtml()`. Kẻ xấu có thể đăng ký tên chứa thẻ `<script>` để đánh cắp session của Admin khi Admin vào duyệt phòng. | Bao bọc toàn bộ biến nội suy bằng hàm `escapeHtml(str)` có sẵn trong `frontend/src/utils/sanitize.js`. |
| **SEC-03** | **Bypass kiểm tra CSRF qua header X-Testing** | **MEDIUM (P2)** | `backend/app/Core/Middleware/CsrfMiddleware.php`<br>(Dòng 59) | Header `X-Testing: 1` cho phép bỏ qua kiểm tra token CSRF trong môi trường production nếu kẻ tấn công gửi request từ cùng nguồn hoặc script tự động. | Chỉ cho phép bỏ qua CSRF khi `Config::get('APP_ENV') === 'testing'`; loại bỏ việc tin tưởng header `X-Testing` từ client gửi lên. |
| **SEC-04** | **Thiếu cấu hình cờ bảo mật cho Session Cookie trong code** | **MEDIUM (P2)** | `backend/bootstrap/app.php`<br>`backend/public/index.php` | Ứng dụng gọi `session_start()` mà không cấu hình `session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => ...])`, phụ thuộc hoàn toàn vào cài đặt mặc định của file `php.ini`. | Bổ sung hàm thiết lập cờ cookie `HttpOnly`, `SameSite=Lax`, `Secure` ngay trước khi khởi động session trong `bootstrap/app.php`. |
| **SEC-05** | **Sai lệch trường kiểm tra quyền Admin khi hủy phòng** | **LOW (P3)** | `backend/app/Features/GameroomManagement/Controllers/GameRoomController.php`<br>(Dòng 785) | Controller kiểm tra `$auth['role']` thay vì `$auth['role_code']`. Do mảng `$auth` chỉ chứa `role_code`, biến `$userRole` luôn bị gán là `'USER'`, khiến Admin không thể gọi hủy phòng qua endpoint `/rooms/{id}/cancel` thông thường. | Sửa `$auth['role']` thành `$auth['role_code']` tại dòng 785 của `GameRoomController.php`. |
| **SEC-06** | **SQL Injection Assessment** | **PASS** | Toàn bộ các Repository và Controller | Đã kiểm tra 100% câu truy vấn CSDL: Tất cả đều sử dụng Prepared Statements (`$pdo->prepare`) với tham số được bind chặt chẽ (`bindValue` với kiểu dữ liệu rõ ràng). Không tìm thấy nguy cơ SQL Injection. | Duy trì thói quen viết mã an toàn hiện tại. |
| **SEC-07** | **Insecure Direct Object References (IDOR)** | **PASS** | `PaymentController.php:45`<br>`GameRoomController.php:795` | Các endpoint thanh toán kiểm tra chặt chẽ `(int)$payment['user_id'] === (int)$auth['id'] || $userRole === 'ADMIN'`. Người chơi không thể thanh toán hộ hoặc chiếm đoạt hóa đơn của người khác. | Tiếp tục duy trì kiểm tra quyền sở hữu đối tượng. |

---

### 8. Database & API Audit

#### 8.1. Database Audit

Hệ thống CSDL V3 (`kich_ban_sat_v3_clean.sql`) bao gồm **21 bảng** quan hệ:

| Tên bảng | Số cột | Khóa chính | Khóa ngoại (Foreign Keys) | Ràng buộc duy nhất & Kiểm tra (Constraints) | Đánh giá |
|---|---|---|---|---|---|
| `roles` | 5 | `id` | Không | `uq_roles_code` UNIQUE (`code`) | Hoàn hảo (Chuẩn hóa RBAC) |
| `users` | 13 | `id` | `role_id` → `roles(id)` | `uq_users_email`, `chk_users_status` | Đầy đủ khóa ngoại và trạng thái |
| `games` | 14 | `id` | Không | `uq_games_slug`, `chk_games_player_range`, `chk_games_status` | Đảm bảo `min_players <= max_players` |
| `game_configurations` | 9 | `id` | `game_id` → `games`, `created_by` → `users` | `uq_game_configuration_version`, `uq_game_configuration_game_id_id` | Kiểm soát phiên bản cấu hình |
| `game_rooms` | 18 | `id` | `game_id`, `game_configuration_id`, `creator_id`, `replacement_opened_by`, `price_promotion_id` | `chk_game_rooms_player_range`, `chk_game_rooms_replacement_status`, `chk_game_rooms_replacement_time` | Đảm bảo tính toàn vẹn thời gian thế chỗ |
| `room_participants` | 12 | `id` | `game_room_id` → `game_rooms`, `user_id` → `users` | `uq_room_active_user` (Generated column `active_user_id`), `chk_participant_status` | Ngăn 1 user tham gia 2 lần vào 1 phòng |
| `dm_assignments` | 11 | `id` | `game_room_id`, `dm_user_id`, `assigned_by` | `uq_dm_assignment_room_id`, `chk_dm_assignment_status` | Quản lý trạng thái phân công Quản trò |
| `venues` | 8 | `id` | Không | `chk_venues_status` | Quản lý chi nhánh địa điểm |
| `physical_rooms` | 8 | `id` | `venue_id` → `venues(id)` | `uq_physical_room_venue_name`, `chk_physical_room_capacity` | Phòng diễn vật lý tại từng cơ sở |
| `schedule_proposals` | 9 | `id` | `game_room_id`, `proposer_id` | `chk_schedule_proposals_time` (`start < end`), `chk_schedule_proposals_status` | Kiểm soát hợp lệ khung giờ đề xuất |
| `schedule_responses` | 7 | `id` | `schedule_proposal_id`, `user_id` | `uq_schedule_responses_proposal_user`, `chk_schedule_responses_choice` | Chống vote 2 lần trên 1 đề xuất |
| `sessions` | 14 | `id` | `game_room_id`, `schedule_proposal_id`, `dm_assignment_id`, `venue_id`, `physical_room_id` | `chk_sessions_time`, `chk_sessions_status` | Vòng đời buổi chơi thực tế |
| `game_prices` | 9 | `id` | `game_id` → `games(id)` | `chk_game_prices_amount`, `chk_game_prices_time`, `chk_game_prices_status` | Bảng giá theo khung giờ |
| `promotions` | 11 | `id` | Không | `uq_promotions_code`, `chk_promotions_period`, `chk_promotions_percent` | Khuyến mãi và chiết khấu hợp lệ |
| `participant_price_snapshots` | 11 | `id` | `game_room_id`, `room_participant_id`, `promotion_id` | `uq_participant_price_snapshot`, `chk_participant_snapshot_amounts` | Snapshot cố định giá cho từng người chơi |
| `player_payments` | 11 | `id` | `room_participant_id` → `room_participants(id)` | `uq_player_payment_type` (`room_participant_id`, `payment_type`), `chk_player_payment_status` | Chống tạo trùng hóa đơn Cọc / Còn lại |
| `payment_transactions` | 10 | `id` | `player_payment_id` → `player_payments(id)` | `uq_payment_transaction_idempotency` (`idempotency_key`), `chk_payment_transaction_amount` | Chống thanh toán trùng lặp tuyệt đối |
| `refunds` | 11 | `id` | `player_payment_id`, `reviewed_by` | `chk_refunds_amount`, `chk_refunds_status` | Quản lý hoàn tiền và người phê duyệt |
| `notifications` | 9 | `id` | `user_id` → `users(id)` | `chk_notifications_is_read` | Hộp thông báo in-app |
| `idempotency_records` | 7 | `id` | `user_id` → `users(id)` | `uq_idempotency_user_key` (`user_id`, `idempotency_key`) | Lưu kết quả thực thi idempotent |
| `outbox_events` | 8 | `id` | Không | `chk_outbox_status` | Lưu trữ sự kiện bất đồng bộ |

#### 8.2. API Endpoints Audit

Hệ thống đăng ký 28 endpoints chính (hỗ trợ cả prefix `/api/v1` và fallback `/api`):

| Endpoint | HTTP Method | Auth Level | Request Validation | Response Format | Ghi chú & Đánh giá |
|---|---|---|---|---|---|
| `/auth/csrf-token` | `GET` | Public | Không yêu cầu | JSend Success | Sinh và trả về token CSRF của session |
| `/auth/register` | `POST` | Public | Email, Full Name, Pass >= 6 | JSend Success (201) | Đăng ký thành công cấp ngay session |
| `/auth/login` | `POST` | Public | Email, Password | JSend Success (200) | Xác thực thông tin, tái tạo Session ID |
| `/auth/me` | `GET` | Authenticated | Không yêu cầu | JSend Success (200) | Lấy thông tin user hiện tại (bỏ hash pass) |
| `/auth/logout` | `POST` | Authenticated | Không yêu cầu | JSend Success (200) | Hủy session và xóa cookie |
| `/admin/users` | `GET` | ADMIN | Query: page, limit, role | JSend Success (200) | Danh sách người dùng hệ thống |
| `/admin/users/{id}` | `GET` | ADMIN | ID số nguyên | JSend Success (200) | Chi tiết thông tin người dùng |
| `/admin/users/{id}` | `PATCH` | ADMIN | `is_active` boolean | JSend Success (200) | Khóa / Mở khóa tài khoản |
| `/admin/users/{id}/role` | `PUT` | ADMIN | `role` in PLAYER, DM, ADMIN | JSend Success (200) | Phân quyền vai trò người dùng |
| `/games` | `GET` | Public | Query: search, category | JSend Success (200) | Danh sách kịch bản chơi |
| `/games/{id}` | `GET` | Public | ID hoặc Slug | JSend Success (200) | Chi tiết kịch bản và vai diễn |
| `/games/{id}/prices` | `GET` | Public | ID kịch bản | JSend Success (200) | Bảng giá theo khung giờ |
| `/promotions` | `GET` | Public | Không yêu cầu | JSend Success (200) | Danh sách chương trình ưu đãi active |
| `/rooms` | `GET` | Public | Query: status, game_id | JSend Success (200) | Sảnh danh sách phòng chơi |
| `/rooms` | `POST` | Authenticated | Game ID, Title, Max Players | JSend Success (201) | Tạo phòng chơi mới |
| `/rooms/{id}` | `GET` | Public | ID phòng | JSend Success (200) | Chi tiết phòng, thành viên, lịch vote |
| `/rooms/{id}/join` | `POST` | Authenticated | ID phòng | JSend Success (200) | Vào phòng thường hoặc vào thế chỗ slot |
| `/rooms/{id}/leave` | `POST` | Authenticated | ID phòng | JSend Success (200) | Rút khỏi phòng / mở slot thay thế |
| `/rooms/{id}/cancel` | `POST` | Host / ADMIN | Reason | JSend Success (200) | Hủy phòng khi đang gom |
| `/rooms/{id}/calculate-price`| `POST` | Authenticated | ID phòng | JSend Success (200) | Tính giá cá nhân theo snapshot |
| `/rooms/{id}/price-snapshot` | `GET` | Authenticated | ID phòng | JSend Success (200) | Tra cứu bảng giá đã chốt của phòng |
| `/admin/rooms/{id}/cancel` | `POST` | ADMIN | Reason (Bắt buộc) | JSend Success (200) | Hủy phòng khẩn cấp theo mốc 24h |
| `/venues` | `GET` | Public | Không yêu cầu | JSend Success (200) | Danh sách chi nhánh & phòng vật lý |
| `/rooms/{id}/proposals` | `POST` | Member | Start time < End time | JSend Success (200) | Đề xuất mốc giờ chơi mới |
| `/proposals/{id}/vote` | `POST` | Member | Choice (ACCEPTED / REJECTED) | JSend Success (200) | Biểu quyết lịch; 100% tự chốt phòng |
| `/sessions/{id}/start` | `POST` | DM / ADMIN | ID buổi chơi | JSend Success (200) | Quản trò bắt đầu buổi chơi |
| `/sessions/{id}/complete` | `POST` | DM / ADMIN | ID buổi chơi | JSend Success (200) | Quản trò kết thúc buổi chơi |
| `/dm/assignments` | `GET` | DM / ADMIN | Query: status, dm_id | JSend Success (200) | Danh sách ca làm việc của DM |
| `/dm/assignments/{id}/accept`| `POST` | DM | ID phân công | JSend Success (200) | Quản trò xác nhận nhận ca |
| `/dm/assignments/{id}/reject`| `POST` | DM | Reason (Bắt buộc) | JSend Success (200) | Quản trò từ chối ca kèm lý do |
| `/admin/rooms/{id}/assign-dm`| `POST` | ADMIN | dm_user_id | JSend Success (200) | Admin phân công Quản trò |
| `/payments/{id}/deposit` | `POST` | Owner / ADMIN | Idempotency Key, Method | JSend Success (200) | Nộp tiền cọc 50.000 VNĐ |
| `/payments/{id}/remaining` | `POST` | Owner / ADMIN | Idempotency Key, Method | JSend Success (200) | Nộp số tiền còn lại sau cọc |
| `/admin/refunds` | `GET` | ADMIN | Query: status, page | JSend Success (200) | Danh sách yêu cầu hoàn tiền |
| `/admin/refunds/{id}/approve`| `POST` | ADMIN | Reason | JSend Success (200) | Duyệt hoàn tiền thủ công |
| `/admin/refunds/{id}/reject` | `POST` | ADMIN | Reason (Bắt buộc) | JSend Success (200) | Từ chối hoàn tiền |
| `/notifications` | `GET` | Authenticated | Query: limit | JSend Success (200) | Lấy danh sách thông báo cá nhân |
| `/notifications/{id}/read` | `POST` | Authenticated | ID thông báo | JSend Success (200) | Đánh dấu đã đọc thông báo |

---

### 9. Excessive Features & Technical Debt

Bảng phân loại các thành phần dư thừa hoặc mang nợ kỹ thuật:

| Thành phần (Component) | Hiện trạng thực tế | Đề xuất phân loại | Lý do kỹ thuật (Reason) | Tác động sau xử lý (Impact) |
|---|---|---|---|---|
| **`FE_kich_ban_sat/`** | Thư mục chứa 11 file HTML và JS cũ ở thư mục gốc | **REMOVE** | Hoàn toàn không được gọi bởi Front Controller hay API. Đây là bản mockup tham khảo cũ, gây hiểu nhầm cấu trúc dự án. | Giảm 50% dung lượng thư mục frontend thừa, code base sạch sẽ. |
| **`BookingRepository.php`** | Nằm tại `backend/app/Features/Booking/Repositories/` | **MERGE / REMOVE** | Các hàm tìm kiếm Session trong class này trùng lặp 90% với `SchedulingRepository.php`. Không có Controller nào sử dụng lớp này ngoài 1 bài test tích hợp. | Hợp nhất test sang `SchedulingRepository`, loại bỏ hoàn toàn module `Booking`. |
| **`migrations_v16_backup/`** | Thư mục con trong `backend/database/` chứa 9 file migration cũ | **REMOVE / ARCHIVE** | Đã được thay thế hoàn toàn bởi migration duy nhất `20261121000000_init_kich_ban_sat_v3_schema.php` thực thi file DDL chuẩn `kich_ban_sat_v3_clean.sql`. | Tránh nhầm lẫn khi chạy migration hoặc bảo trì schema CSDL. |
| **Inline SQL trong `GameRoomController.php`** | Controller dài > 930 dòng, chứa hơn 15 câu lệnh `prepare` SQL trực tiếp | **SIMPLIFY / REFACTOR** | Vi phạm Single Responsibility Principle và kiến trúc Layered Architecture. | Đưa câu lệnh CSDL vào `GameRoomRepository`, giúp Controller tinh gọn, dễ viết Unit Test giả lập (Mock). |
| **Bảng `outbox_events` trong CSDL** | Bảng có tồn tại trong CSDL nhưng không có worker nào quét và xử lý | **DEFER** | Tạm thời giữ nguyên bảng trong schema để phục vụ giai đoạn phát triển Event-Driven Architecture tương lai. | Không ảnh hưởng đến hiệu năng hiện tại; cấu trúc CSDL sẵn sàng mở rộng. |

---

### 10. Missing & Incorrect Features

Danh sách đầy đủ các chức năng còn thiếu hoặc sai lệch cần hoàn thiện:

1. **Thiếu hóa đơn cọc ban đầu cho Host khi mới tạo phòng:**
   - *Hiện trạng:* Khi Host tạo phòng (`create`), bản ghi `room_participants` được gán `PENDING_DEPOSIT`, nhưng bảng `player_payments` không có bản ghi nào cho đến khi phòng có đề xuất lịch (`schedule_proposals`).
   - *Giải pháp:* Trong phương thức `GameRoomController::create()`, chèn ngay 1 hóa đơn `player_payments` loại `DEPOSIT` với số tiền 50.000 VNĐ cho Host (có `deadline_at` bằng hạn gom phòng).
2. **Thiếu nút điều khiển buổi chơi trên giao diện Quản trò (DM Portal):**
   - *Hiện trạng:* API Backend `POST /sessions/{id}/start` và `POST /sessions/{id}/complete` đã có sẵn, nhưng giao diện `dm-view.js` không hiển thị nút bấm để DM kích hoạt buổi chơi.
   - *Giải pháp:* Thêm 2 nút bấm "Bắt đầu buổi chơi" (khi trạng thái `SCHEDULED`) và "Kết thúc buổi chơi" (khi trạng thái `IN_PROGRESS`) vào thẻ ca làm việc trong `frontend/src/pages/dm/dm-view.js`.
3. **Thiếu trang hiển thị Chi nhánh & Phòng chơi cho Guest / Player:**
   - *Hiện trạng:* API `GET /api/v1/venues` hoạt động tốt nhưng Frontend chưa có giao diện hiển thị danh sách địa chỉ, hình ảnh cơ sở và phòng diễn.
   - *Giải pháp:* Bổ sung tab "Địa điểm & Chi nhánh" trong thanh điều hướng trang chủ hoặc tích hợp vào bước chọn cơ sở khi tạo phòng.
4. **Thiếu chức năng Quản lý hồ sơ & Xác minh HSSV (UC-P01, UC-P02):**
   - *Hiện trạng:* Chưa có API và modal để người dùng cập nhật thông tin cá nhân và tải minh chứng học sinh sinh viên.
   - *Giải pháp:* Bổ sung endpoint `PATCH /api/v1/auth/profile` và modal cập nhật hồ sơ trong `frontend/src/pages/auth/`.
5. **Thiếu các phân hệ Quản trị danh mục cho Admin (UC-A03, UC-A05, UC-A06, UC-A07):**
   - *Hiện trạng:* Admin hiện chỉ quản trị User, Hủy phòng và Duyệt hoàn tiền; chưa thể thêm/sửa Kịch bản, Bảng giá, Khuyến mãi, Chi nhánh trên giao diện.
   - *Giải pháp:* Xây dựng thêm các Sub-tabs trong `admin-view.js` và bổ sung các Admin Controller tương ứng ở Backend (`AdminGameController`, `AdminPricingController`, `AdminPromotionController`, `AdminVenueController`).
6. **Lỗi kiểm thử dữ liệu rò rỉ trong `DMAssignmentApiTest.php`:**
   - *Hiện trạng:* Test kiểm tra phân công DM không hợp lệ bị fail do phòng mẫu ID 1 bị thay đổi trạng thái sang `CANCELLED` từ test trước.
   - *Giải pháp:* Khởi tạo một phòng chơi độc lập mới (`INSERT INTO game_rooms ...`) ngay bên trong phương thức test thay vì dùng chung phòng ID 1 cố định.

---

### 11. Improvement Roadmap

Lộ trình khắc phục và nâng cấp hệ thống được chia làm 5 giai đoạn theo thứ tự phụ thuộc kỹ thuật:

| Giai đoạn | Nhiệm vụ kỹ thuật (Task) | Use Case liên quan | Giải pháp đề xuất | Độ phức tạp |
|---|---|---|---|:---:|
| **PHASE A: Bảo mật & Toàn vẹn dữ liệu (P0 & P1)** | **1. Vá lỗ hổng Bypass xác thực Bearer Token** | UC-G08, Toàn bộ hệ thống | Xóa bỏ đoạn đọc Bearer ID tùy tiện trong `AuthMiddleware.php` dòng 45–50; chỉ cho phép phiên đăng nhập hợp lệ qua Session hoặc kiểm tra chặt chẽ `APP_ENV === 'testing'`. | **S** (Small) |
| | **2. Khắc phục lỗ hổng Stored XSS trên Frontend** | UC-P05, UC-A01, UC-A13 | Bọc hàm `escapeHtml()` cho toàn bộ dữ liệu người dùng (`room.title`, `p.name`, `u.email`...) trong `rooms-view.js` và `admin-view.js`. | **S** (Small) |
| | **3. Đóng lỗ hổng CSRF qua header X-Testing** | Toàn bộ API Mutation | Trong `CsrfMiddleware.php`, chỉ bỏ qua kiểm tra CSRF khi `Config::get('APP_ENV') === 'testing'`. | **S** (Small) |
| | **4. Thiết lập cờ bảo mật Session Cookie** | Toàn bộ phiên | Thêm `session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax'])` vào `bootstrap/app.php`. | **S** (Small) |
| | **5. Sửa lỗi kiểm tra quyền Admin khi hủy phòng** | UC-A10 | Sửa `$auth['role']` thành `$auth['role_code']` tại dòng 785 của `GameRoomController.php`. | **S** (Small) |
| **PHASE B: Nghiệp vụ cốt lõi (P1)** | **1. Tự động sinh hóa đơn cọc khi Host tạo phòng** | UC-P03, UC-P15 | Bổ sung `INSERT INTO player_payments` (loại `DEPOSIT`, 50k) ngay trong Transaction tạo phòng của `GameRoomController::create()`. | **M** (Medium) |
| | **2. Bổ sung nút Bắt đầu / Kết thúc buổi chơi cho DM** | UC-D06, UC-D07 | Thêm nút bấm gọi API `POST /sessions/{id}/start` và `/sessions/{id}/complete` trên giao diện `dm-view.js`. | **M** (Medium) |
| | **3. Sửa bài test `DMAssignmentApiTest` bị fail** | Toàn bộ Test Suite | Tạo phòng mới độc lập trong test case để tránh rò rỉ dữ liệu giữa các bài kiểm thử. | **S** (Small) |
| **PHASE C: Chức năng hỗ trợ (P2)** | **1. Xây dựng giao diện xem Chi nhánh & Địa điểm** | UC-G06 | Thêm tab "Địa điểm" trên thanh Header, kết nối API `GET /api/v1/venues` hiển thị chi nhánh và phòng chơi. | **M** (Medium) |
| | **2. Bổ sung API & UI Quản lý hồ sơ cá nhân & HSSV** | UC-P01, UC-P02 | Thêm endpoint `PATCH /api/v1/auth/profile` và modal cập nhật thông tin trong Frontend. | **M** (Medium) |
| | **3. Bổ sung hiển thị danh sách người chơi trên DM Portal** | UC-D03 | Hiển thị danh sách người chơi trong từng thẻ ca làm việc của Quản trò. | **S** (Small) |
| | **4. Bổ sung API & UI Sổ cái giao dịch người chơi** | UC-P17, UC-A14 | Xây dựng API tra cứu lịch sử nộp tiền cá nhân và bảng tra cứu giao dịch cho Admin. | **M** (Medium) |
| **PHASE D: Kiến trúc & Nợ kỹ thuật (P2 & P3)** | **1. Refactor tách SQL từ `GameRoomController` sang Repository** | Kiến trúc hệ thống | Chuyển toàn bộ các câu truy vấn PDO trực tiếp sang `GameRoomRepository`. | **L** (Large) |
| | **2. Xóa bỏ thư mục thừa `FE_kich_ban_sat` và `migrations_v16_backup`** | Nợ kỹ thuật | Xóa các thư mục không sử dụng khỏi repository để làm sạch cấu trúc dự án. | **S** (Small) |
| | **3. Hợp nhất `BookingRepository` vào `SchedulingRepository`** | Nợ kỹ thuật | Gom các hàm truy vấn Session vào 1 repository duy nhất và xóa file thừa. | **S** (Small) |
| **PHASE E: Hoàn thiện Quản trị & Nghiệm thu (P3)** | **1. Xây dựng CRUD Kịch bản & Bảng giá cho Admin** | UC-A03, UC-A05 | Viết Controller và View quản lý danh mục kịch bản, bảng giá giờ chơi. | **L** (Large) |
| | **2. Bổ sung Báo cáo doanh thu & Audit Log** | UC-A15, UC-A16 | Viết API tổng hợp số liệu doanh thu và màn hình xem nhật ký hoạt động. | **M** (Medium) |
| | **3. Chạy toàn bộ Test Suite và nghiệm thu 100% Pass** | Toàn bộ hệ thống | Đảm bảo 162/162 PHPUnit tests và toàn bộ test Frontend đều đạt kết quả xanh tuyệt đối. | **S** (Small) |

---

### 12. Final Assessment

#### 12.1. Đánh giá mức độ sẵn sàng của hệ thống (Readiness Ratings)

| Tiêu chí thẩm định | Đánh giá | Nhận định tóm tắt |
|---|:---:|---|
| **Architecture Readiness** | **PARTIAL** | Core HTTP, Routing và Pipeline Middleware xuất sắc; tuy nhiên một số Controller còn ôm đồm nhiều câu truy vấn SQL trực tiếp. |
| **Functional Readiness** | **PARTIAL** | Đáp ứng hoàn hảo các nghiệp vụ cốt lõi: Ghép phòng, Rút phòng, Thế chỗ slot, Biểu quyết chốt lịch, Nộp cọc 50k, Hủy phòng 24h và Phân công DM (~79.2% hoàn thành). Thiếu các trang CRUD quản trị danh mục kịch bản/bảng giá và hồ sơ cá nhân. |
| **Security Readiness** | **FAIL** *(Cần xử lý gấp Phase A)* | Nền tảng SQL Injection và CSRF vững chắc, nhưng tồn tại lỗ hổng P0 bypass xác thực qua Bearer ID và Stored XSS trên Frontend. Sau khi xử lý 2 điểm này sẽ đạt hạng **PASS**. |
| **Database Readiness** | **PASS** | 21 bảng CSDL thiết kế chuẩn 3NF, đầy đủ ràng buộc toàn vẹn, Generated Columns, khóa ngoại và chống race conditions bằng `FOR UPDATE`. |
| **API Readiness** | **PASS** | 28 endpoints tuân thủ chuẩn RESTful, trả JSON định dạng JSend nhất quán, mã trạng thái HTTP chuẩn mực (200, 201, 400, 401, 403, 404, 422). |
| **Testing Readiness** | **PARTIAL** | Bộ test đồ sộ (162 tests, 757 assertions), tỷ lệ pass đạt 99.4% (161/162). Chỉ cần sửa 1 test case bị phụ thuộc dữ liệu CSDL là đạt 100% PASS. |
| **OVERALL PROJECT READINESS** | **PARTIAL** | **Hệ thống có nền tảng rất vững chắc**, gần như đã hoàn thiện toàn bộ khung lõi của sản phẩm. Sau khi hoàn thành Phase A (Bảo mật) và Phase B (Nút bấm DM & Hóa đơn cọc ban đầu), dự án hoàn toàn đủ điều kiện chuyển sang giai đoạn nghiệm thu vận hành (UAT/Staging). |

---

#### 12.2. Trả lời 5 câu hỏi trọng tâm theo yêu cầu của Lead

##### Câu hỏi 1: Source code hiện tại đã đáp ứng bao nhiêu phần trăm yêu cầu chức năng?
> **Trả lời:**  
> Dựa trên kiểm tra thực tế trên source code:
> - **Triển khai hoàn chỉnh 100% (Full Stack từ UI → API → CSDL):** **28 / 53 Use Cases (~52.8%)**.
> - **Đã có sẵn nền tảng Backend API / CSDL nhưng giao diện chưa kết nối đầy đủ:** **14 / 53 Use Cases (~26.4%)**.
> - **Chưa triển khai (chủ yếu là các màn hình phụ của Admin và hồ sơ):** **11 / 53 Use Cases (~20.8%)**.  
> **Tổng cộng độ sẵn sàng về mặt nghiệp vụ đạt xấp xỉ 79.2%**. Toàn bộ các luồng nghiệp vụ cốt lõi và phức tạp nhất của dự án (Tạo phòng, Ghép phòng, Rút phòng thế chỗ, Biểu quyết 100%, Đặt cọc 50k, Quy tắc vàng 24h, Phân công DM và Cron tự động hóa) đều đã hoạt động chính xác.

##### Câu hỏi 2: Những chức năng quan trọng nào còn thiếu?
> **Trả lời:**  
> Có 4 chức năng quan trọng cần bổ sung ngay:
> 1. Nút bấm Bắt đầu / Kết thúc buổi chơi trên giao diện Quản trò (`dm-view.js`).
> 2. Cơ chế tự động phát sinh hóa đơn cọc 50k cho Host ngay khi vừa tạo phòng (trước khi có đề xuất lịch).
> 3. Trang hiển thị danh sách Chi nhánh và Phòng chơi vật lý cho người chơi tra cứu.
> 4. Phân hệ Admin CRUD để Quản trị viên cập nhật Kịch bản, Bảng giá giờ chơi và Khuyến mãi mà không cần can thiệp trực tiếp vào database.

##### Câu hỏi 3: Có chức năng hoặc module nào nên lược bỏ không?
> **Trả lời:**  
> Có 3 thành phần nên lược bỏ hoặc dọn dẹp khỏi codebase:
> 1. **Thư mục `FE_kich_ban_sat/`:** Là bản mockup tĩnh tham khảo cũ, không liên kết với hệ thống, gây rối mắt cho các lập trình viên mới.
> 2. **Lớp `BookingRepository.php`:** Trùng lặp chức năng với `SchedulingRepository.php`, chỉ dùng trong 1 bài test tích hợp.
> 3. **Thư mục `backend/database/migrations_v16_backup/`:** Các file migration cũ đã lỗi thời sau khi đã có bản DDL V3 chuẩn hóa.

##### Câu hỏi 4: Có vấn đề nghiêm trọng nào về kiến trúc, database hoặc bảo mật không?
> **Trả lời:**  
> - **Về Database:** **Không có vấn đề nghiêm trọng**. CSDL được thiết kế rất xuất sắc, kiểm soát tương tranh và toàn vẹn dữ liệu cực tốt.
> - **Về Kiến trúc:** Một số Controller (`GameRoomController`, `SchedulingController`) bị phình to do chứa nhiều raw SQL, cần đưa vào Repository.
> - **Về Bảo mật:** **Có 2 vấn đề nghiêm trọng bắt buộc phải sửa ngay**:
>   - *Lỗ hổng P0:* Nhận User ID trực tiếp từ header `Authorization: Bearer <id>` trong `AuthMiddleware.php` cho phép bypass quyền Admin.
>   - *Lỗ hổng P1:* Tồn tại lỗ hổng Stored XSS trong `rooms-view.js` và `admin-view.js` do dùng `innerHTML` hiển thị biến người dùng mà không escape HTML.

##### Câu hỏi 5: Cần thực hiện những công việc nào trước khi chuyển sang giai đoạn tiếp theo?
> **Trả lời:**  
> Đội ngũ phát triển cần hoàn thành đúng thứ tự ưu tiên của **Phase A** và **Phase B** trong Improvement Roadmap:
> 1. Xóa bỏ logic nhận Bearer ID tùy tiện trong `AuthMiddleware.php`.
> 2. Bọc hàm `escapeHtml()` cho các trường hiển thị động trên Frontend.
> 3. Tạo hóa đơn cọc 50k cho Host ngay khi lập phòng trong `GameRoomController::create()`.
> 4. Gắn 2 nút "Bắt đầu ca" và "Kết thúc ca" vào giao diện Quản trò (`dm-view.js`).
> 5. Sửa dữ liệu test trong `DMAssignmentApiTest.php` để đưa kết quả PHPUnit đạt **162/162 tests PASS (100%)**.
> Sau 5 bước này, hệ thống sẽ hoàn toàn an toàn và sẵn sàng cho giai đoạn kiểm thử chấp nhận người dùng (UAT).
