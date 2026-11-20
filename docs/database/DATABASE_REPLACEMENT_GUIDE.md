# HƯỚNG DẪN THAY THẾ CƠ SỞ DỮ LIỆU (DATABASE REPLACEMENT GUIDE)

Tài liệu này hướng dẫn bạn từng bước cách tích hợp cơ sở dữ liệu mới (schema mới) vào dự án mà không làm hỏng cấu trúc code hiện có.

---

## 1. Nguyên lý kết nối CSDL trong dự án

Dự án sử dụng **PDO Native** được bọc qua lớp Singleton `DatabaseConnection`.
- Cấu hình kết nối nằm tại: `backend/.env`
- Lớp nạp cấu hình: `backend/app/Infrastructure/Config/Config.php`
- Lớp tạo kết nối PDO: `backend/app/Infrastructure/Database/ConnectionFactory.php`
- Lớp cung cấp PDO: `backend/app/Infrastructure/Database/DatabaseConnection.php`
- Lớp cơ sở cho truy vấn: `backend/app/Infrastructure/Database/BaseRepository.php`

---

## 2. Quy trình 4 bước thay thế Database mới

### Bước 1: Tạo Database mới trong MySQL
Mở MySQL Workbench, phpMyAdmin, Navicat hoặc dòng lệnh MySQL:
```sql
CREATE DATABASE ten_database_moi CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### Bước 2: Nạp bảng của bạn vào CSDL
Bạn có thể nạp bảng bằng một trong hai cách:
- **Cách 1 (Đơn giản nhất - Khuyên dùng):** Import trực tiếp file `.sql` chứa thiết kế CSDL mới của bạn vào database vừa tạo bằng phpMyAdmin / HeidiSQL / DBeaver.
- **Cách 2 (Dùng Phinx Migration):** Viết file migration mới trong `backend/database/migrations/` và chạy lệnh:
  ```powershell
  cd backend
  .\vendor\bin\phinx migrate
  ```

### Bước 3: Cập nhật file cấu hình `backend/.env`
Mở file `backend/.env` và cập nhật lại tên database, username và mật khẩu:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ten_database_moi
DB_USERNAME=root
DB_PASSWORD=mat_khau_cua_ban
DB_CHARSET=utf8mb4
```

### Bước 4: Cập nhật Repositories tương ứng với bảng mới
Trong thư mục `backend/app/Features/`, mỗi feature quản lý các câu lệnh SQL liên quan đến một nhóm bảng.

Ví dụ, nếu bảng người dùng mới của bạn có tên `tai_khoan` (thay vì `users`), bạn chỉ cần mở file Repository tương ứng (hoặc tạo mới) và viết câu SQL:
```php
<?php

declare(strict_types=1);

namespace App\Features\Identity\Repositories;

use App\Infrastructure\Database\BaseRepository;
use PDO;

class UserRepository extends BaseRepository
{
    public function findById(int $id): ?array
    {
        return $this->executeQuery(function () use ($id): ?array {
            $stmt = $this->pdo->prepare('SELECT * FROM tai_khoan WHERE id = :id LIMIT 1');
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result !== false ? $result : null;
        });
    }
}
```

---

## 3. Cách kiểm tra kết nối CSDL nhanh
Sau khi cập nhật file `.env`, bạn có thể kiểm tra kết nối bằng script có sẵn:
```powershell
cd backend
php database/verify-connection.php
```
Nếu màn hình thông báo:
```text
[OK] Database connection successful.
```
tức là ứng dụng đã kết nối thành công với CSDL mới của bạn!
