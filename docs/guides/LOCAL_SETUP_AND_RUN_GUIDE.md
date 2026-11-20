# HƯỚNG DẪN CÀI ĐẶT VÀ KHỞI CHẠY DỰ ÁN THỦ CÔNG TRÊN WINDOWS
## Dự án: Website Booking – Kịch Bản Sát (Jubensha / Murder Mystery)
## Môi trường: Windows 10/11 Native (Không sử dụng Docker)

---

## 1. Yêu cầu tiên quyết (Prerequisites)

Trước khi thực hiện, máy tính Windows của bạn cần cài đặt sẵn:
- **PHP CLI**: Phiên bản 8.2 trở lên (Khuyến nghị PHP 8.4.x). Các extension bắt buộc: `pdo`, `pdo_mysql`, `mbstring`, `openssl`, `json`, `fileinfo`.
- **MySQL Server**: Phiên bản 8.0 trở lên (Dịch vụ `MySQL80` hoặc tương đương đang chạy cổng 3306).
- **Composer**: Phiên bản 2.x (dùng cho autoloading và quản lý gói PHP).
- **Node.js**: Phiên bản 20 trở lên (dùng để chạy bộ test client frontend).
- **Git**: Đã cài đặt để quản lý mã nguồn.

---

## 2. Quy trình 9 bước khởi chạy thủ công (Step-by-Step)

### BƯỚC 1: Mở PowerShell và kiểm tra các công cụ hệ thống

Mở **PowerShell** tại thư mục gốc của dự án:
```powershell
cd D:\LapTrinhWeb\Project\Booking_kich-ban-sat_PHP
```

Kiểm tra sự hiện diện của các công cụ bằng lệnh:
```powershell
php -v
composer -V
mysql --version
node -v
```

> **Kết quả kỳ vọng:** Cả 4 lệnh đều in ra phiên bản tương ứng mà không báo lỗi "command not found".

---

### BƯỚC 2: Cấu hình biến môi trường (`.env`)

Tại thư mục `backend/`, sao chép file mẫu `.env.example` thành `.env`:
```powershell
Copy-Item backend/.env.example backend/.env
```

Mở tệp `backend/.env` bằng trình chỉnh sửa (Notepad, VS Code) và kiểm tra/chỉnh sửa các thông số kết nối MySQL của máy bạn:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=booking_project_php
DB_TEST_DATABASE=booking_project_php_test
DB_USERNAME=root
DB_PASSWORD=Mật_Khẩu_MySQL_Của_Bạn
DB_CHARSET=utf8mb4
```

---

### BƯỚC 3: Cài đặt các gói phụ thuộc Backend qua Composer

Di chuyển vào thư mục `backend` và chạy Composer:
```powershell
cd backend
composer install
cd ..
```

> **Kết quả kỳ vọng:** Thư mục `backend/vendor/` được cài đặt đầy đủ các thư viện (`phpunit`, `phinx`, `psr/log`, v.v.) và autoloading PSR-4 sẵn sàng.

---

### BƯỚC 4: Tạo cơ sở dữ liệu trên MySQL

Mở MySQL client hoặc PowerShell để tạo 2 cơ sở dữ liệu (Development và Testing) nếu chưa có:
```powershell
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS booking_project_php CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE IF NOT EXISTS booking_project_php_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```
*(Nhập mật khẩu `root` MySQL khi được hỏi).*

---

### BƯỚC 5: Thực thi Phinx Migration để tạo 21 bảng nghiệp vụ

Chạy migration cho cả hai môi trường Development và Testing:
```powershell
cd backend

# Chạy migration cho môi trường Development
.\vendor\bin\phinx migrate -e development

# Chạy migration cho môi trường Testing
.\vendor\bin\phinx migrate -e testing

cd ..
```

> **Kết quả kỳ vọng:** Phinx thông báo `All Done` và hiển thị trạng thái `migrated` cho đủ 9 tệp migration từ `CreateIdentityTables` đến `CreateNotificationTables`.

---

### BƯỚC 6: Khởi tạo dữ liệu hạt giống (Seed Data)

Nạp dữ liệu mẫu ban đầu (Vai trò hệ thống `ADMIN, DM, PLAYER`, các địa điểm quán và phòng chơi vật lý):
```powershell
cd backend
.\vendor\bin\phinx seed:run -e development
cd ..
```

> **Kết quả kỳ vọng:** `ReferenceDataSeeder` và `RoleSeeder` nạp thành công trong thời gian dưới 1 giây.

---

### BƯỚC 7: Chạy toàn bộ bộ kiểm thử tự động (Test Verification)

Kiểm tra tính toàn vẹn 100% của hệ thống:

```powershell
# 1. Chạy PHPUnit Test Suite (Backend)
cd backend
.\vendor\bin\phpunit
cd ..

# 2. Chạy Node.js Test Suite (Frontend API Client)
node frontend/tests/api-client.test.mjs
```

> **Kết quả kỳ vọng:**
> - PHPUnit: `OK (83 tests, 247 assertions)`.
> - Node.js: `pass 7, fail 0`.

---

### BƯỚC 8: Khởi chạy máy chủ Backend API

Mở cửa sổ PowerShell thứ nhất và thực thi:
```powershell
cd D:\LapTrinhWeb\Project\Booking_kich-ban-sat_PHP
php -S 127.0.0.1:8080 -t backend/public backend/public/index.php
```

Mở trình duyệt web hoặc PowerShell khác để kiểm tra endpoint:
```powershell
curl http://127.0.0.1:8080/api/health
```

> **Kết quả JSON kỳ vọng:**
> ```json
> {"success":true,"data":{"status":"ok","application":"Kich Ban Sat","version":"16"}}
> ```

---

### BƯỚC 9: Khởi chạy máy chủ Frontend

Mở cửa sổ PowerShell thứ hai và thực thi:
```powershell
cd D:\LapTrinhWeb\Project\Booking_kich-ban-sat_PHP
php -S 127.0.0.1:3000 -t frontend
```

Mở trình duyệt web và truy cập địa chỉ:
**`http://127.0.0.1:3000`**

> **Kết quả giao diện kỳ vọng:** Giao diện Platform Foundation hiển thị bảng điều khiển kiểm tra trạng thái Backend API, kết nối Cơ sở dữ liệu và phiên bản ứng dụng hoạt động bình thường với trạng thái màu xanh (`ONLINE / OK`).

---

## 3. Các sự cố thường gặp & Cách khắc phục (Troubleshooting)

1. **Lỗi `Access denied for user 'root'@'localhost'`:**
   - Nguyên nhân: Mật khẩu MySQL trong file `backend/.env` không khớp với mật khẩu của tài khoản `root` trên máy của bạn.
   - Khắc phục: Mở `backend/.env`, chỉnh sửa lại `DB_PASSWORD` cho chính xác.

2. **Lỗi `Port 8080 or 3000 already in use`:**
   - Nguyên nhân: Có một tiến trình khác đang chiếm cổng 8080 hoặc 3000.
   - Khắc phục: Đổi sang cổng khác (ví dụ: `8081` và `3001`), đồng thời cập nhật `ALLOWED_ORIGINS` và `APP_URL` trong `backend/.env` cho khớp.

3. **Lỗi `Extension pdo_mysql not found`:**
   - Nguyên nhân: File cấu hình `php.ini` chưa bật extension `extension=pdo_mysql`.
   - Khắc phục: Mở file `php.ini` trong thư mục cài đặt PHP, bỏ dấu `;` trước dòng `extension=pdo_mysql` và `extension=mbstring`, sau đó lưu lại.
