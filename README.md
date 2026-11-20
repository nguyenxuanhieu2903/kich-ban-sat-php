# Kịch Bản Sát V16 — Nền tảng đặt lịch chơi trên nền Web

Nền tảng đặt lịch chơi trực tuyến tự động, gọn nhẹ dành cho thể loại trò chơi nhập vai giải đố (Jubensha / murder mystery / kịch bản sát).

> [!NOTE]
> **Trạng thái cột mốc:** Cơ sở dữ liệu đã được **chuẩn hóa và thu gọn về đúng 21 bảng canonical theo tài liệu B3** theo yêu cầu của giảng viên hướng dẫn.<br>
> Toàn bộ 9 tệp Phinx migration, seeders lũy thừa, hệ thống Repositories và bộ kiểm thử tích hợp chuyên sâu (**83 tests, 247 assertions**) đã được kiểm chứng và đạt **100% PASS** trên môi trường Windows Native.

---

## 1. Tổng quan dự án

Kịch Bản Sát V16 cung cấp một nền tảng đặt lịch trên nền web tự động, được thiết kế chuyên biệt cho các trò chơi nhập vai bí ẩn và kịch bản sát.

- **Cột mốc hiện tại:** Platform Foundation & Canonical 21-Table Database Scope đã hoàn thành và nghiệm thu.
- **Trạng thái triển khai:** Hạ tầng HTTP Core, 21 bảng CSDL nghiệp vụ trên MySQL 8.0, Repositories tầng dữ liệu, và API health check đang vận hành ổn định.
- **Tài liệu đối chiếu CSDL:** Xem [docs/database/DATABASE_TABLE_MAPPING.md](docs/database/DATABASE_TABLE_MAPPING.md) và [docs/database/DATABASE_VERIFICATION_REPORT.md](docs/database/DATABASE_VERIFICATION_REPORT.md).

---

## 2. Phạm vi dự án

Phạm vi MVP bao gồm ba cổng giao diện:

- **Player Portal:** Duyệt danh mục kịch bản, tạo và tham gia phòng chơi, biểu quyết lịch chơi, thanh toán đặt cọc mô phỏng, gửi yêu cầu rời phòng và theo dõi tiến trình.
- **DM Portal:** Tiếp nhận phân công điều phối, chấp nhận hoặc từ chối dẫn dắt phòng chơi, xem danh sách người chơi và kiểm tra thông tin địa điểm phòng chơi vật lý.
- **Admin Portal:** Quản lý danh mục kịch bản, bảng giá, phòng chơi, cơ sở vật lý, điều phối DM, duyệt hoàn tiền cọc và quản lý mở lại phòng chơi.

---

## 3. Cấu trúc dự án

Repository áp dụng kiến trúc **Feature-oriented + Modular Monolith + Layered Architecture**, phân tách rõ ràng giữa Backend PHP thuần, Frontend Vanilla JavaScript và hệ thống tài liệu dự án:

```text
Booking_kich-ban-sat_PHP/
├── backend/                       # Ứng dụng Backend PHP thuần (Native PHP 8.4+)
│   ├── app/                       # Mã nguồn ứng dụng (PSR-4 App\)
│   │   ├── Core/                  # Nhân HTTP không phụ thuộc framework (Router, Middleware, Request, Response, ExceptionHandler)
│   │   ├── Features/              # Các lát cắt nghiệp vụ (Feature Vertical Slices)
│   │   │   ├── Booking/           # Đặt lịch & phòng chơi vật lý
│   │   │   ├── DMManagement/      # Quản lý & phân công DM
│   │   │   ├── GameCatalog/       # Danh mục kịch bản & cấu hình
│   │   │   ├── GameroomManagement/# Vòng đời phòng chơi & người tham gia
│   │   │   ├── Identity/          # Tài khoản người dùng, vai trò & hồ sơ
│   │   │   ├── Notification/      # Thông báo người dùng
│   │   │   ├── PaymentRefund/     # Nghĩa vụ thanh toán, sổ cái giao dịch & hoàn tiền
│   │   │   ├── Pricing/           # Bảng giá kịch bản & snapshot giá
│   │   │   ├── Promotion/         # Khuyến mãi & mã giảm giá
│   │   │   └── VenueScheduling/   # Cơ sở vật lý, đề xuất lịch & phiên chơi
│   │   ├── Infrastructure/        # Cấu hình môi trường (Config) và kết nối PDO (DatabaseConnection)
│   │   └── Shared/                # Tiện ích dùng chung toàn miền
│   ├── bootstrap/                 # Khởi tạo ứng dụng (app.php)
│   ├── database/                  # Quản lý CSDL (Phinx Migrations & Seeders)
│   │   ├── backups/               # Bản sao lưu an toàn CSDL
│   │   ├── migrations/            # 9 tệp Phinx migration chính thức cho 21 bảng B3
│   │   │   └── archive_34_tables/ # Lưu trữ các migration cũ (34 bảng)
│   │   └── seeders/               # ReferenceDataSeeder & RoleSeeder
│   ├── public/                    # Document root máy chủ web (index.php Front Controller)
│   ├── routes/                    # Tuyến đường API (api.php)
│   ├── tests/                     # Bộ kiểm thử tự động PHPUnit (83 tests, 247 assertions)
│   │   ├── Feature/               # HealthCheckTest
│   │   ├── Integration/           # DatabaseIntegrityTest, RepositoryIntegrationTest
│   │   └── Unit/                  # RouterTest, RequestTest, ResponseTest, v.v.
│   ├── .env.example               # Mẫu cấu hình môi trường
│   ├── composer.json              # Cấu hình Composer & PSR-4 autoload
│   ├── phinx.php                  # Cấu hình công cụ Phinx Migration
│   └── phpunit.xml                # Cấu hình bộ chạy kiểm thử PHPUnit
│
├── frontend/                      # Ứng dụng Frontend (HTML5, CSS3, Vanilla JS ES Modules)
│   ├── index.html                 # Điểm nhập HTML chính của ứng dụng
│   ├── public/                    # Tài nguyên tĩnh
│   ├── src/                       # Các module mã nguồn frontend
│   │   ├── api/                   # Shared API client (api-client.js, health-api.js)
│   │   ├── components/            # UI components tái sử dụng
│   │   ├── layouts/               # Khung bố cục giao diện
│   │   ├── pages/                 # Bộ điều khiển trang (Health, v.v.)
│   │   ├── styles/                # Định dạng CSS (reset.css, variables.css, main.css)
│   │   └── utils/                 # Cấu hình và tiện ích trợ giúp
│   └── tests/                     # Kiểm thử tự động API client với Node.js (7 tests)
│
├── docs/                          # Hệ thống tài liệu kiến trúc, CSDL & kiểm toán
│   ├── database/                  # Tài liệu 21 bảng CSDL, ánh xạ & thẩm định
│   │   ├── DATABASE_TABLE_MAPPING.md       # Bảng đối chiếu 34 bảng -> 21 bảng
│   │   ├── DATABASE_VERIFICATION_REPORT.md # Báo cáo thẩm định schema thực tế
│   │   └── DATABASE_MIGRATION_REPORT.md    # Báo cáo thực thi 9 migration Phinx
│   ├── guides/                    # Sổ tay hướng dẫn
│   │   └── LOCAL_SETUP_AND_RUN_GUIDE.md    # Hướng dẫn chạy thủ công từng bước trên Windows
│   └── audit/                     # Các báo cáo kiểm toán kỹ thuật
│
├── IMPLEMENTATION_PLAN_V16_Draft.md
├── PRODUCT_SPEC_V16_Draft.md
└── README.md                      # Tài liệu này
```

---

## 4. Công nghệ sử dụng

| Công nghệ | Phiên bản thực tế | Mục đích |
| :--- | :--- | :--- |
| **PHP CLI** | 8.4.27 (cli) ZTS x64 | Runtime Backend API & Built-in web server |
| **PHP Extensions** | `pdo`, `pdo_mysql`, `mbstring`, `openssl`, `json`, `fileinfo` | Runtime cốt lõi & tương tác CSDL qua PDO |
| **MySQL Server** | 8.0.44 Community Server | CSDL quan hệ chính (InnoDB, utf8mb4_unicode_ci) |
| **Phinx** | 0.16.12 | Quản lý Migration và Seeders |
| **Composer** | 2.11-dev | Quản lý dependencies & Autoload chuẩn PSR-4 |
| **PHPUnit** | 11.5.58 | Khung kiểm thử tự động Backend |
| **Node.js** | 24.22.1 | Trình chạy kiểm thử API client Frontend (`node --test`) |
| **Frontend** | HTML5, CSS3, Vanilla JS ES Modules | Client trình duyệt thuần, không phụ thuộc framework hay bundler |
| **Hệ điều hành** | Windows 11 Native (Không Docker) | Môi trường phát triển cục bộ |

---

## 5. Quy trình 9 bước cài đặt & Khởi chạy thủ công trên Windows

Xem chi tiết từng bước có giải thích tại [docs/guides/LOCAL_SETUP_AND_RUN_GUIDE.md](docs/guides/LOCAL_SETUP_AND_RUN_GUIDE.md).

```powershell
# BƯỚC 1: Mở PowerShell tại thư mục gốc và kiểm tra công cụ
cd D:\LapTrinhWeb\Project\Booking_kich-ban-sat_PHP
php -v; composer -V; mysql --version; node -v

# BƯỚC 2: Cấu hình biến môi trường
Copy-Item backend/.env.example backend/.env
# Chỉnh sửa mật khẩu DB_PASSWORD trong backend/.env khớp với MySQL của bạn

# BƯỚC 3: Cài đặt gói phụ thuộc Backend
cd backend; composer install; cd ..

# BƯỚC 4: Tạo cơ sở dữ liệu MySQL (nếu chưa có)
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS booking_project_php CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE IF NOT EXISTS booking_project_php_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# BƯỚC 5: Thực thi Phinx Migration tạo 21 bảng Canonical
cd backend
.\vendor\bin\phinx migrate -e development
.\vendor\bin\phinx migrate -e testing
cd ..

# BƯỚC 6: Khởi tạo dữ liệu hạt giống (Seed Data)
cd backend
.\vendor\bin\phinx seed:run -e development
cd ..

# BƯỚC 7: Chạy kiểm thử tự động xác minh tính toàn vẹn
cd backend; .\vendor\bin\phpunit; cd ..
node frontend/tests/api-client.test.mjs

# BƯỚC 8: Khởi chạy Backend API Server (PowerShell 1)
php -S 127.0.0.1:8080 -t backend/public backend/public/index.php
# Kiểm tra: curl http://127.0.0.1:8080/api/health

# BƯỚC 9: Khởi chạy Frontend Server (PowerShell 2)
php -S 127.0.0.1:3000 -t frontend
# Truy cập trình duyệt: http://127.0.0.1:3000
```

---

## 6. Kết quả kiểm thử tự động

- **Backend (PHPUnit 11.5.58):**
  - **83 tests, 247 assertions — 100% PASS (0 errors, 0 failures)**.
  - Bao gồm: kiểm thử toàn vẹn ACID, ràng buộc khóa ngoại (RESTRICT, CASCADE, SET NULL), khóa ảo tính toán có điều kiện (`active_user_id`, `active_assigned_room_id`, `proposed_game_room_id`), kiểm tra miền giá trị CHECK, chống trùng lặp giao dịch thanh toán/hoàn tiền bằng `idempotency_key`, và kiểm thử các Repositories.
- **Frontend (Node.js test runner):**
  - **7 tests — 100% PASS (0 failures)**.
  - Bao gồm: kiểm thử HTTP timeout, signal hủy request, khôi phục kết nối và chuẩn hóa mã lỗi `ApiError`.

---

## 7. Tài liệu kỹ thuật liên quan

- [docs/database/DATABASE_TABLE_MAPPING.md](docs/database/DATABASE_TABLE_MAPPING.md) — Đối chiếu chi tiết 34 bảng → 21 bảng.
- [docs/database/DATABASE_VERIFICATION_REPORT.md](docs/database/DATABASE_VERIFICATION_REPORT.md) — Báo cáo thẩm định kỹ thuật 21 bảng trên MySQL.
- [docs/database/DATABASE_MIGRATION_REPORT.md](docs/database/DATABASE_MIGRATION_REPORT.md) — Nhật ký thực thi 9 migration Phinx.
- [docs/guides/LOCAL_SETUP_AND_RUN_GUIDE.md](docs/guides/LOCAL_SETUP_AND_RUN_GUIDE.md) — Sổ tay hướng dẫn cài đặt & vận hành thủ công trên Windows.
- [docs/adr/ADR-0001-project-foundation.md](docs/adr/ADR-0001-project-foundation.md) — Bản ghi quyết định kiến trúc nền tảng (ADR).
