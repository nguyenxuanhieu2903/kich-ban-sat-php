# Kịch Bản Sát V16 — Nền tảng đặt lịch chơi trên nền Web

Nền tảng đặt lịch chơi trực tuyến tự động, gọn nhẹ dành cho thể loại trò chơi nhập vai giải đố (Jubensha / murder mystery / kịch bản sát).

> [!NOTE]
> **Trạng thái cột mốc:** Cột mốc **P0.4 (Platform Technical Foundation)** đã được **ACCEPTED** (Nghiệm thu).<br>
> Kiến trúc kỹ thuật cốt lõi, định tuyến, middleware, làm sạch ngoại lệ và baseline kiểm thử tự động đã được kiểm chứng. Các cổng nghiệp vụ (Player, DM, Admin) là phạm vi MVP dự kiến đã được phê duyệt, được lên lịch triển khai trong các cột mốc phát triển tiếp theo.

---

## 1. Tổng quan dự án

Kịch Bản Sát V16 cung cấp một nền tảng đặt lịch trên nền web tự động, được thiết kế chuyên biệt cho các trò chơi nhập vai bí ẩn và kịch bản sát.

- **Cột mốc hiện tại:** P0.4 Foundation đã được nghiệm thu (xem [docs/audit/P0_4_FOUNDATION_ACCEPTANCE_REPORT.md](docs/audit/P0_4_FOUNDATION_ACCEPTANCE_REPORT.md)).
- **Trạng thái triển khai:** Hạ tầng nền tảng và các endpoint kiểm tra trạng thái hoạt động (health check) đang vận hành. Toàn bộ các cổng nghiệp vụ là phạm vi MVP dự kiến.
- **Baseline đặc tả:** V15 là approved business baseline (baseline nghiệp vụ đã được phê duyệt). V16 kế thừa và mở rộng các yêu cầu nghiệp vụ đã được duyệt của V15 với các đặc tả chi tiết hướng tới triển khai. [PRODUCT_SPEC_V16_Draft.md](PRODUCT_SPEC_V16_Draft.md) là bản kế nhiệm đang ở trạng thái Draft của V15 và vẫn cần được xem xét, phê duyệt chính thức.

---

## 2. Phạm vi dự án

Phạm vi MVP đã được phê duyệt bao gồm ba cổng giao diện dự kiến:

- **Player Portal (Dự kiến):** Duyệt danh mục kịch bản, tạo và tham gia phòng chơi, xếp lịch phiên chơi, mô phỏng thanh toán đặt cọc, gửi yêu cầu rời phòng và theo dõi tiến trình phiên chơi.
- **DM Portal (Dự kiến):** Tiếp nhận phân công điều phối, chấp nhận hoặc từ chối dẫn dắt phiên chơi, xem danh sách người chơi và kiểm tra thông tin địa điểm phòng chơi vật lý.
- **Admin Portal (Dự kiến):** Quản lý phòng chơi và không gian vật lý, điều phối DM, duyệt đề xuất lịch chơi, xử lý hoàn tiền cọc và quản lý mở lại phòng chơi.

Chi tiết yêu cầu nghiệp vụ đầy đủ và lộ trình triển khai công việc, xem tại [PRODUCT_SPEC_V16_Draft.md](PRODUCT_SPEC_V16_Draft.md) và [IMPLEMENTATION_PLAN_V16_Draft.md](IMPLEMENTATION_PLAN_V16_Draft.md).

---

## 3. Cấu trúc dự án

Repository áp dụng kiến trúc **Feature-oriented + Layered Architecture**, phân tách rõ ràng giữa Backend PHP thuần, Frontend Vanilla JavaScript và hệ thống tài liệu dự án:

```text
Booking_kich-ban-sat_PHP/
├── backend/                       # Ứng dụng Backend PHP thuần (Native PHP)
│   ├── app/                       # Mã nguồn ứng dụng (PSR-4 App\)
│   │   ├── Core/                  # Nhân HTTP không phụ thuộc framework (Router, Middleware, Request, Response)
│   │   ├── Features/              # [Dự kiến] Các lát cắt nghiệp vụ (Feature vertical slices) (.gitkeep)
│   │   ├── Infrastructure/        # Bộ nạp cấu hình và kết nối cơ sở dữ liệu qua PDO
│   │   └── Shared/                # [Dự kiến] Các hàm tiện ích dùng chung toàn miền (.gitkeep)
│   ├── bootstrap/                 # Khởi tạo ứng dụng (app.php)
│   ├── database/                  # Công cụ chẩn đoán kết nối, [Dự kiến] migrations & seeders
│   ├── public/                    # Document root của máy chủ web (index.php Front Controller)
│   ├── routes/                    # Khai báo các tuyến đường API (api.php)
│   ├── tests/                     # Bộ kiểm thử tự động PHPUnit (Feature/ & Unit/)
│   ├── .env.example               # File mẫu cấu hình biến môi trường
│   ├── composer.json              # Cấu hình Composer và quy tắc autoloading
│   ├── composer.lock              # Khóa phiên bản dependency (PHPUnit 11.5.58)
│   └── phpunit.xml                # Cấu hình bộ chạy kiểm thử PHPUnit
│
├── frontend/                      # Ứng dụng Single Page Application bằng Vanilla JavaScript
│   ├── index.html                 # Điểm nhập HTML chính của ứng dụng
│   ├── public/                    # [Dự kiến] Tài nguyên tĩnh (.gitkeep)
│   ├── src/                       # Các module mã nguồn frontend
│   │   ├── api/                   # Lớp trừu tượng gọi HTTP client (api-client.js, health-api.js)
│   │   ├── components/            # [Dự kiến] Các UI component tái sử dụng (.gitkeep)
│   │   ├── layouts/               # [Dự kiến] Khung bố cục giao diện (.gitkeep)
│   │   ├── pages/                 # Bộ điều khiển trang (hiện tại có pages/health/health-page.js)
│   │   ├── styles/                # Định dạng CSS (reset.css, variables.css, main.css)
│   │   ├── utils/                 # Cấu hình và tiện ích trợ giúp (config.js)
│   │   └── app.js                 # Script khởi động ứng dụng frontend
│   └── tests/                     # Kiểm thử tự động với Node.js (api-client.test.mjs)
│
├── docs/                          # Tài liệu kiến trúc, kiểm toán và cài đặt môi trường
├── .gitignore                     # Quy tắc loại trừ file của Git
├── IMPLEMENTATION_PLAN_V16_Draft.md # Kế hoạch triển khai (bản thảo đang xem xét)
├── PRODUCT_SPEC_V16_Draft.md      # Đặc tả kỹ thuật (bản kế nhiệm của V15 đang ở trạng thái Draft)
└── README.md                      # Tài liệu dự án và hướng dẫn làm quen (onboarding)
```

### Trách nhiệm các thư mục chính

| Thư mục | Trạng thái | Trách nhiệm |
| :--- | :---: | :--- |
| `backend/app/Core/` | **Implemented** | Nhân HTTP không framework: Router, Middleware, Request, Response và ExceptionHandler. |
| `backend/app/Features/` | *Planned* (`.gitkeep`) | Nơi đặt các lát cắt nghiệp vụ trong tương lai (Controllers, Services, Repositories). |
| `backend/app/Infrastructure/` | **Implemented** / *Planned* | Bộ đọc cấu hình môi trường (`Config.php`) và PDO `ConnectionFactory.php`. Logging đang được dự kiến. |
| `backend/database/` | **Implemented** / *Planned* | Script kiểm tra kết nối (`verify-connection.php`). Migrations và seeders dự kiến triển khai tại Phase 1. |
| `backend/tests/` | **Implemented** | Bộ kiểm thử tự động PHPUnit: Kiểm thử tích hợp Feature và kiểm thử đơn vị Unit. |
| `frontend/src/` | **Implemented** / *Planned* | Client Fetch API, controller trang health, styles và cấu hình. Các component giao diện và cổng nghiệp vụ là dự kiến. |
| `frontend/tests/` | **Implemented** | Kiểm thử tự động với Node.js cho API client: hủy request, timeout và chuẩn hóa lỗi. |
| `docs/` | **Implemented** / *Planned* | Quyết định kiến trúc (`adr/`), báo cáo kiểm toán và nghiệm thu (`audit/`), hướng dẫn nhà phát triển (`setup/`). |

*(Ghi chú: Các thư mục chỉ tồn tại cục bộ như `backend/vendor/`, `backend/.phpunit.cache/`, và file cục bộ `backend/.env` được loại trừ khỏi version control thông qua `.gitignore`).*

---

## 4. Kiến trúc hệ thống

Kịch Bản Sát V16 tuân thủ mô hình **Feature-oriented + Layered Architecture** và không phụ thuộc vào bất kỳ framework bên ngoài nào trong thời gian chạy (runtime). Ở phía Backend, mô hình kiến trúc nội bộ **Model-View-Controller (MVC)** được áp dụng để phân tách các tầng xử lý yêu cầu HTTP, nghiệp vụ ứng dụng và truy xuất dữ liệu.

### Sơ đồ luồng xử lý

```text
Trình duyệt (Vanilla JS ES Modules)
   │
   │ fetch() [JSON qua HTTP]
   ▼
backend/public/index.php (Front Controller)
   │
   ▼
App\Core\Router -> App\Core\Middleware (CORS, Bảo mật)
   │
   ▼
[Các tầng nghiệp vụ mục tiêu: Phase 1+]
Controller (Xác thực dữ liệu đầu vào & Chuẩn hóa JSON Response)
   │
   ▼
Application Service (Xử lý nghiệp vụ & Bảo vệ trạng thái State Machine)
   │
   ▼
Repository (Truy vấn tham số hóa qua PDO Prepared Statements)
   │
   ▼
Cơ sở dữ liệu MySQL (utf8mb4, Strict Mode)
```

- **Foundation hiện tại (Cột mốc P0.4):** Front Controller, HTTP Router, CORS Middleware, các lớp trừu tượng Request/Response, ExceptionHandler kèm cơ chế làm sạch thông tin nhạy cảm, và PDO Connection Factory. Phục vụ endpoint `GET /api/health`.
- **Kiến trúc nghiệp vụ mục tiêu (Phase 1+):** Logic nghiệp vụ, các domain controller, application service, repository và các bảng dữ liệu quan hệ sẽ được đưa vào theo từng lát cắt nghiệp vụ (vertical slice).
- **Quy tắc an toàn dữ liệu:** Mọi truy vấn liên quan đến tham số động hoặc dữ liệu từ bên ngoài bắt buộc phải sử dụng PDO prepared statements với bound parameters. Nghiêm cấm hoàn toàn việc nối chuỗi trực tiếp từ dữ liệu chưa qua kiểm chứng.

---

## 5. Công nghệ sử dụng

| Công nghệ | Phiên bản tối thiểu / Yêu cầu | Baseline môi trường đã xác minh | Mục đích |
| :--- | :--- | :--- | :--- |
| **PHP CLI** | 8.2+ | PHP 8.4.27 (cli) ZTS x64 | Runtime API Backend & máy chủ phát triển tích hợp |
| **PHP Extensions** | Bắt buộc | `pdo`, `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `json`, `session` | Các khả năng runtime cốt lõi |
| **MySQL Server** | 8.0+ | MySQL Community Server 8.0.44 | Cơ sở dữ liệu quan hệ (dịch vụ Windows: `MySQL80`) |
| **Composer** | 2.x | Composer 2.11-dev | Autoloading chuẩn PSR-4 & quản lý dev dependency |
| **Node.js** | 20+ | Node.js 24.22.1 | Trình chạy kiểm thử tích hợp sẵn (`node --test`) cho frontend |
| **PHPUnit** | ^11.5 | PHPUnit 11.5.58 | Khung kiểm thử tự động Backend |
| **Frontend** | Trình duyệt hiện đại | HTML5, CSS3, Vanilla JS ES Modules | Client trình duyệt thuần, không phụ thuộc build toolchain |
| **Git** | 2.40+ | Git 2.52.0.windows.1 | Quản lý mã nguồn theo mô hình local-first |
| **Nền tảng** | Windows 10/11 x64 | Windows 11 Native (Không dùng Docker) | Môi trường hệ điều hành phát triển |

---

## 6. Cài đặt và cấu hình

### Bước 1: Mở Terminal & Kiểm tra PATH
Mở Windows PowerShell tại thư mục gốc của repository:
```powershell
cd /path/to/Booking_kich-ban-sat_PHP
```
Đảm bảo các lệnh `php`, `composer`, `node` và `mysql` đã sẵn sàng trong phiên làm việc PowerShell. Nếu chưa được cấu hình biến môi trường toàn hệ thống, hãy thêm đường dẫn của chúng vào `$env:PATH` cho phiên hiện tại (xem đường dẫn mẫu trong [docs/setup/PREREQUISITES.md](docs/setup/PREREQUISITES.md)).

### Bước 2: Cấu hình biến môi trường
Sao chép cấu hình mẫu từ file template:
```powershell
Copy-Item backend/.env.example backend/.env
```
Mở file `backend/.env` và cập nhật thông tin đăng nhập cơ sở dữ liệu MySQL cục bộ của bạn (`DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`). Tuyệt đối không commit file `.env` lên Git.

### Bước 3: Cài đặt các gói phụ thuộc Backend
```powershell
cd backend
composer install
cd ..
```

### Bước 4: Kiểm tra kết nối cơ sở dữ liệu (Tùy chọn)
Chạy script kiểm tra kết nối PDO tới dịch vụ MySQL cục bộ:
```powershell
php backend/database/verify-connection.php
```
*(Yêu cầu dịch vụ MySQL đang chạy và cơ sở dữ liệu khớp với cấu hình `DB_DATABASE` đã được tạo sẵn. Các migration tạo bảng sẽ được triển khai trong Phase 1).*

Xem chi tiết kết quả kiểm toán môi trường tại [docs/setup/ENVIRONMENT_AUDIT.md](docs/setup/ENVIRONMENT_AUDIT.md).

---

## 7. Khởi chạy ứng dụng

### 7.1. Khởi chạy máy chủ Backend API
```powershell
php -S 127.0.0.1:8080 -t backend/public backend/public/index.php
```
Kiểm tra endpoint health check tại `http://127.0.0.1:8080/api/health`. Phản hồi JSON mẫu kỳ vọng:
```json
{"success":true,"data":{"status":"ok","application":"Kich Ban Sat","version":"16"}}
```

### 7.2. Khởi chạy máy chủ Frontend
Mở một cửa sổ PowerShell riêng biệt và chạy:
```powershell
php -S 127.0.0.1:3000 -t frontend
```
Mở địa chỉ `http://127.0.0.1:3000` trên trình duyệt web để truy cập giao diện kiểm tra trạng thái hoạt động của Platform Foundation.

---

## 8. Kiểm thử

Thực thi các bộ kiểm thử tự động từ thư mục gốc của repository:

```powershell
# 1. Bộ kiểm thử tự động Backend PHPUnit (52 tests, 190 assertions)
cd backend
.\vendor\bin\phpunit
composer validate
cd ..

# 2. Bộ kiểm thử tự động Frontend chạy trên Node.js (7 tests)
node --test frontend/tests/api-client.test.mjs
```

> [!NOTE]
> Các chỉ số trên đại diện cho **baseline Foundation** đã được kiểm chứng. Các bài test cho từng chức năng nghiệp vụ sẽ được bổ sung song song với quá trình triển khai tính năng trong Phase 1+. Xem nhật ký kiểm thử chi tiết tại [docs/setup/FOUNDATION_VERIFICATION.md](docs/setup/FOUNDATION_VERIFICATION.md).

---

## 9. Quy trình Git

- **Cấu trúc nhánh (Branch Structure):**
  - `main`: Nhánh chứa mã nguồn sẵn sàng cho môi trường production, chỉ nhận các mốc phát hành đã nghiệm thu.
  - `develop`: Nhánh tích hợp chính cho quá trình phát triển thường nhật.
  - Nhánh chức năng: `feat/<tên-chức-năng>`, `fix/<tên-lỗi>`, `test/<phạm-vi-kiểm-thử>`.
- **Quy tắc làm việc:**
  - Mọi nhánh tính năng đều phải được rẽ nhánh từ `develop` và tạo Pull Request (PR) hợp nhất về lại `develop`.
  - Các bản phát hành từ `develop` được hợp nhất sang `main` thông qua Release Pull Request sau khi đạt nghiệm thu mốc.
  - Toàn bộ bài kiểm tra (`phpunit`, `node --test`) và lệnh kiểm tra `composer validate` phải vượt qua thành công trước khi mở PR.
  - Bắt buộc phải có review từ thành viên khác (peer review) trước khi merge.
- **Quy chuẩn thông điệp commit (Commit Format):** `<type>: <mô tả ngắn>` (`feat`, `fix`, `chore`, `docs`, `refactor`, `test`, `security`).
- **Kỷ luật mã nguồn:** Tuyệt đối không commit các file cấu hình `.env`, thông tin bảo mật hoặc thư mục phụ thuộc `vendor/`.

---

## 10. Tài liệu tham khảo

Để nắm rõ các tài liệu kỹ thuật chi tiết và hồ sơ kiến trúc, vui lòng tham khảo:
- [PRODUCT_SPEC_V16_Draft.md](PRODUCT_SPEC_V16_Draft.md) — Đặc tả sản phẩm & kỹ thuật (bản thảo kế nhiệm của V15).
- [IMPLEMENTATION_PLAN_V16_Draft.md](IMPLEMENTATION_PLAN_V16_Draft.md) — Kế hoạch và lộ trình triển khai qua các giai đoạn.
- [docs/adr/ADR-0001-project-foundation.md](docs/adr/ADR-0001-project-foundation.md) — Bản ghi quyết định kiến trúc nền tảng (ADR).
- [docs/setup/PREREQUISITES.md](docs/setup/PREREQUISITES.md) — Yêu cầu tiên quyết cho máy trạm phát triển.
- [docs/setup/ENVIRONMENT_AUDIT.md](docs/setup/ENVIRONMENT_AUDIT.md) — Báo cáo kiểm toán môi trường và phần mở rộng PHP.
- [docs/setup/FOUNDATION_VERIFICATION.md](docs/setup/FOUNDATION_VERIFICATION.md) — Quy trình xác minh chi tiết và sổ tay lệnh chạy thử.
- [docs/audit/P0_4_FOUNDATION_ACCEPTANCE_REPORT.md](docs/audit/P0_4_FOUNDATION_ACCEPTANCE_REPORT.md) — Báo cáo nghiệm thu chính thức cột mốc P0.4 Foundation.
