# Kịch Bản Sát V16 — Nền tảng đặt lịch chơi trên nền Web

Nền tảng đặt lịch chơi trực tuyến tự động, gọn nhẹ dành cho thể loại trò chơi nhập vai giải đố (Jubensha / murder mystery / kịch bản sát).

---

## 1. Tổng quan dự án

Dự án áp dụng kiến trúc **Tách biệt Frontend - Backend (Decoupled Architecture)**:
- **Backend:** Native PHP 8.2+ (sử dụng Mini-Framework HTTP Core tự phát triển, PDO MySQL, không phụ thuộc framework bên thứ ba cồng kềnh).
- **Frontend:** Vanilla HTML5, CSS3, JavaScript ES Modules (chạy trực tiếp trên trình duyệt, không cần Node.js build tool).

---

## 2. Cấu trúc thư mục tinh gọn

```text
Booking_kich-ban-sat_PHP/
├── backend/                       # Ứng dụng Backend (Native PHP 8.2+)
│   ├── app/                       # Mã nguồn ứng dụng (PSR-4 App\)
│   │   ├── Core/                  # Nhân HTTP Core (Router, Middleware, Request, Response, ExceptionHandler)
│   │   ├── Features/              # Các lắt cắt nghiệp vụ (Booking, Identity, GameCatalog...)
│   │   ├── Infrastructure/        # Cấu hình môi trường (Config) & kết nối PDO (DatabaseConnection)
│   │   └── Shared/                # Các Enum và tiện ích dùng chung
│   ├── bootstrap/                 # Khởi tạo ứng dụng (app.php)
│   ├── config/                    # Cấu hình chi tiết (database.php, session.php)
│   ├── database/                  # Quản lý CSDL (migrations, seeders, verify-connection.php)
│   ├── public/                    # Document Root cho Web Server (index.php - Front Controller)
│   ├── routes/                    # Khai báo tuyến đường API (api.php)
│   ├── tests/                     # Bộ kiểm thử tự động PHPUnit
│   ├── .env                       # File cấu hình môi trường thực tế (chứa thông tin DB)
│   ├── .env.example               # Mẫu cấu hình môi trường
│   ├── composer.json              # Khai báo PSR-4 autoloading và dependencies
│   └── phpunit.xml                # Cấu hình PHPUnit
│
├── frontend/                      # Ứng dụng Frontend (HTML5, CSS3, Vanilla JS)
│   ├── index.html                 # Điểm nhập giao diện chính
│   ├── src/                       # Mã nguồn ES Modules
│   │   ├── api/                   # API Client gọi dữ liệu backend (api-client.js, health-api.js)
│   │   ├── config/                # Cấu hình frontend (app-config.js)
│   │   ├── pages/                 # Bộ điều khiển giao diện các trang (HealthPage...)
│   │   ├── styles/                # CSS định dạng giao diện (reset, variables, main, pages)
│   │   ├── utils/                 # Tiện ích hỗ trợ
│   │   └── app.js                 # Điểm khởi động frontend
│   └── tests/                     # Kiểm thử API client
│
├── docs/                          # Hệ thống tài liệu chọn lọc
│   ├── adr/                       # Ghi chép quyết định kiến trúc (ADR-0001)
│   ├── database/                  # Hướng dẫn thay thế CSDL (DATABASE_REPLACEMENT_GUIDE.md)
│   ├── guides/                    # Sổ tay cài đặt & chạy dự án (LOCAL_SETUP_AND_RUN_GUIDE.md)
│   └── setup/                     # Yêu cầu môi trường cài đặt (PREREQUISITES.md)
│
├── PRODUCT_SPEC_V16_Final.md      # Tài liệu đặc tả chức năng nghiệp vụ MVP
├── IMPLEMENTATION_PLAN_V16_Final.md # Kế hoạch triển khai chi tiết
└── README.md                      # Tài liệu này
```

---

## 3. Khởi chạy nhanh dự án (Quick Start)

### Yêu cầu môi trường
- PHP 8.2 trở lên (bật extension: `pdo`, `pdo_mysql`, `mbstring`, `openssl`).
- MySQL 8.0 trở lên.
- Composer 2.x.

### Các bước chạy cục bộ:
1. **Khởi động Backend API (Port 8080):**
   ```powershell
   cd backend
   php -S 127.0.0.1:8080 -t public
   ```
2. **Khởi động Frontend (Port 3000):**
   Mở thêm 1 cửa sổ PowerShell mới:
   ```powershell
   cd frontend
   php -S 127.0.0.1:3000
   ```
3. **Mở trình duyệt:**
   Truy cập `http://127.0.0.1:3000` — Màn hình trạng thái hệ thống sẽ hiển thị kết nối thành công với Backend API.

---

## 4. Chạy kiểm thử tự động (Tests)

Trong thư mục `backend`:
```powershell
cd backend
.\vendor\bin\phpunit --testsuite Unit
```
> Kết quả mong đợi: `OK (47 tests, 164 assertions)`

---

## 5. Tài liệu tham khảo quan trọng
- [Hướng dẫn chạy thủ công chi tiết](docs/guides/LOCAL_SETUP_AND_RUN_GUIDE.md)
- [Hướng dẫn thay thế Cơ sở dữ liệu](docs/database/DATABASE_REPLACEMENT_GUIDE.md)
- [Đặc tả nghiệp vụ chi tiết](PRODUCT_SPEC_V16_Final.md)
