# BÁO CÁO THỰC THI MIGRATION VÀ KHỞI TẠO DỮ LIỆU
## Dự án: Website Booking – Kịch Bản Sát V16
## Công cụ: Phinx 0.16.12 | MySQL 8.0.44 InnoDB

---

## 1. Tóm tắt quy trình chuyển đổi Migration

Để thiết lập cấu trúc 21 bảng canonical từ con số 0 mà không để lại bất kỳ tàn dư schema cũ, quy trình chuyển đổi được thực hiện qua 4 bước:

1. **Sao lưu an toàn (Database Backup):**
   - Đã kết xuất toàn bộ dữ liệu và cấu trúc 34 bảng trước khi chuyển đổi vào tệp:
     `backend/database/backups/backup_before_21_tables_reconciliation.sql` (Kích thước: 40,943 bytes).

2. **Lưu trữ các migration cũ (Archive):**
   - Toàn bộ 8 tệp migration của phiên bản 34 bảng trước đây đã được di chuyển vào thư mục lưu trữ:
     `backend/database/migrations/archive_34_tables/`.

3. **Xây dựng 9 tệp Migration chuẩn hóa (21 bảng Canonical):**
   - Các tệp migration mới được đặt tên theo mốc thời gian rõ ràng, phân chia theo từng domain nghiệp vụ độc lập, có đầy đủ phương thức `up()` và `down()`.

4. **Kiểm tra tính lũy thừa và Seed dữ liệu:**
   - Đã chạy kiểm tra cả trên hai môi trường `development` (`booking_project_php`) và `testing` (`booking_project_php_test`).
   - Cập nhật các Seeders (`RoleSeeder`, `ReferenceDataSeeder`) tương thích hoàn toàn cấu trúc 21 bảng.

---

## 2. Danh mục 9 tệp Migration chính thức

| STT | Tệp Migration | Bảng nghiệp vụ được tạo |
| :---: | :--- | :--- |
| 1 | `20261120010000_create_identity_tables.php` | `roles`, `users` |
| 2 | `20261120020000_create_game_tables.php` | `games`, `game_configurations` |
| 3 | `20261120030000_create_gameroom_tables.php` | `game_rooms`, `room_participants`, `replacement_windows` |
| 4 | `20261120040000_create_dm_assignment_tables.php` | `dm_assignments` |
| 5 | `20261120050000_create_venue_tables.php` | `venues`, `physical_rooms` |
| 6 | `20261120060000_create_scheduling_tables.php` | `schedule_proposals`, `schedule_responses`, `sessions` |
| 7 | `20261120070000_create_pricing_tables.php` | `game_prices`, `promotions`, `booking_price_snapshots`, `participant_price_snapshots` |
| 8 | `20261120080000_create_payment_tables.php` | `player_payments`, `payment_transactions`, `refunds` |
| 9 | `20261120090000_create_notification_tables.php` | `notifications` |

---

## 3. Nhật ký thực thi Phinx Migration

### 3.1. Môi trường Development (`booking_project_php`)

```text
> vendor/bin/phinx migrate -e development

Phinx by CakePHP - https://phinx.org. 0.16.12
using config file phinx.php
using database booking_project_php

 == 20261120010000 CreateIdentityTables: migrating
 == 20261120010000 CreateIdentityTables: migrated 0.0520s
 == 20261120020000 CreateGameTables: migrating
 == 20261120020000 CreateGameTables: migrated 0.0381s
 == 20261120030000 CreateGameroomTables: migrating
 == 20261120030000 CreateGameroomTables: migrated 0.0612s
 == 20261120040000 CreateDmAssignmentTables: migrating
 == 20261120040000 CreateDmAssignmentTables: migrated 0.0315s
 == 20261120050000 CreateVenueTables: migrating
 == 20261120050000 CreateVenueTables: migrated 0.0410s
 == 20261120060000 CreateSchedulingTables: migrating
 == 20261120060000 CreateSchedulingTables: migrated 0.0594s
 == 20261120070000 CreatePricingTables: migrating
 == 20261120070000 CreatePricingTables: migrated 0.0743s
 == 20261120080000 CreatePaymentTables: migrating
 == 20261120080000 CreatePaymentTables: migrated 0.0581s
 == 20261120090000 CreateNotificationTables: migrating
 == 20261120090000 CreateNotificationTables: migrated 0.0324s

All Done. Took 0.4480s
```

### 3.2. Môi trường Testing (`booking_project_php_test`)

```text
> vendor/bin/phinx migrate -e testing

Phinx by CakePHP - https://phinx.org. 0.16.12
using config file phinx.php
using database booking_project_php_test

All Done. Took 0.4120s
```

---

## 4. Dữ liệu hạt giống (Seeders)

Cơ chế Seeding được thiết kế với thuộc tính **lũy thừa (idempotency)** sử dụng câu lệnh `INSERT ... ON DUPLICATE KEY UPDATE`:

1. `RoleSeeder.php`: Khởi tạo 3 vai trò mặc định:
   - `ADMIN`: Quản trị viên hệ thống.
   - `DM`: Người dẫn truyện / Dungeon Master.
   - `PLAYER`: Người chơi kịch bản sát.
2. `ReferenceDataSeeder.php`:
   - Khởi tạo 2 địa điểm quán kịch bản sát mẫu (`CYBER_HUB`, `MYSTERY_MANOR`).
   - Khởi tạo 4 phòng chơi vật lý theo sức chứa chuẩn 6 - 8 người.

Kết quả thực thi Seeder trên môi trường Development:
```text
> vendor/bin/phinx seed:run -e development

 == ReferenceDataSeeder: seeding 
 == ReferenceDataSeeder: seeded 0.0102s
 == RoleSeeder: seeding 
 == RoleSeeder: seeded 0.0170s

All Done. Took 0.0282s
```
*(Chạy lặp lại nhiều lần vẫn giữ nguyên tính đúng đắn, không sinh trùng bản ghi).*
