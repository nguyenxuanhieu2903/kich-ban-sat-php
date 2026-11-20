# 🎭 Kịch Bản Sát V16 — Nền Tảng Đặt Lịch & Ghép Phòng Chơi Nhập Vai Án Mạng

> **Hệ thống web-based chuyên nghiệp hỗ trợ tìm bạn chơi, ghép phòng, biểu quyết giờ chơi, đặt cọc giữ chỗ và điều phối Quản trò (DM) cho trò chơi nhập vai kịch bản sát (Jubensha / Murder Mystery).**

---

## 📌 1. Tổng Quan & Kiến Trúc Dự Án

Dự án được xây dựng theo chuẩn **API Convention V16** với các đặc tính kỹ thuật cốt lõi:
- **Backend:** Pure PHP Native 8.2+ MVC (Không dùng Laravel/Symfony; tự xây dựng HTTP Core: `Router`, `Request`, `Response`, `Middleware`, `Idempotency`, `PricingService`).
- **Frontend:** Vanilla HTML5, CSS3 Dark Theme hiện đại, JavaScript ES Modules (Chạy trực tiếp trên trình duyệt, không cần build tool Node.js cồng kềnh).
- **Cơ sở dữ liệu:** MySQL 8.0/8.4 (21 bảng quan hệ chuẩn hóa 3NF; quản lý cấu trúc qua Phinx Migrations & Seeders).
- **Topology:** **Same-Origin** — Frontend và Backend REST API cùng chạy trên cùng một máy chủ `http://127.0.0.1:8000`, tự động chia sẻ PHP Session Cookie (`HttpOnly`, `SameSite=Lax`), loại bỏ phức tạp CORS.
- **Bảo mật:** Bảo vệ CSRF Token cho toàn bộ request thay đổi dữ liệu (`POST`, `PUT`, `PATCH`, `DELETE`), phân quyền RBAC đa cấp (`PLAYER`, `DM`, `ADMIN`), cơ chế chống tấn công IDOR và Idempotency Key chống trùng lặp giao dịch thanh toán.

---

## 📂 2. Cấu Trúc Thư Mục Dự Án

```text
Booking_kich-ban-sat_PHP/
├── backend/                       # Nguồn Backend PHP Native
│   ├── app/
│   │   ├── Core/                  # Nhân HTTP Framework (Router, Request, Response, Middleware)
│   │   ├── Features/              # Các lắt cắt nghiệp vụ độc lập:
│   │   │   ├── Identity/          # Đăng ký, Đăng nhập, Profile, Quản lý User/Role (RBAC)
│   │   │   ├── GameCatalog/       # Danh mục kịch bản & Bảng giá theo khung giờ
│   │   │   ├── GameroomManagement/# Tạo phòng, Sảnh chờ, Ghép phòng, Takeover thế chỗ
│   │   │   ├── VenueScheduling/   # Đề xuất mốc giờ, Biểu quyết 100%, Tự chốt Session
│   │   │   ├── PaymentRefund/     # Cọc cố định 50k, Quy tắc vàng 24h, Duyệt hoàn tiền
│   │   │   ├── DMManagement/      # Phân công ca dẫn Quản trò, Nhận/Từ chối ca
│   │   │   └── Notification/      # Thông báo sự kiện thời gian thực
│   │   └── Infrastructure/        # Quản lý Database Connection (PDO) & Config
│   ├── bin/
│   │   └── cron.php               # CLI Entrypoint chạy các tiến trình nền định kỳ
│   ├── database/
│   │   ├── migrations/            # Phinx Migrations khởi tạo 21 bảng CSDL V3
│   │   └── seeders/               # Dữ liệu mẫu (Users, Kịch bản, Địa điểm, Phòng)
│   ├── public/
│   │   └── index.php              # Front Controller (Phục vụ cả REST API & Giao diện tĩnh)
│   ├── routes/
│   │   └── api.php                # Khai báo toàn bộ tuyến đường API V16
│   ├── tests/                     # 107 bài kiểm thử tự động PHPUnit
│   ├── .env                       # File cấu hình kết nối CSDL thực tế (được gitignore bảo vệ)
│   └── phinx.php                  # Cấu hình công cụ Migration Phinx
│
├── frontend/                      # Nguồn Frontend SPA (Vanilla JS ES Modules)
│   ├── index.html                 # Giao diện chính (Sticky Navbar, Tabs, Dark Theme)
│   └── src/
│       ├── api/                   # ApiClient (CSRF, Cookie Session) & AuthState
│       ├── pages/
│       │   ├── games/             # Danh mục kịch bản & Tra cứu giá
│       │   ├── rooms/             # Sảnh phòng, Thế chỗ, Biểu quyết, Đặt cọc
│       │   ├── dm/                # Cổng Quản trò (DM Portal)
│       │   ├── admin/             # Bảng điều khiển Quản trị viên
│       │   ├── auth/              # Modal Đăng nhập/Đăng ký + 1-Click Demo Switcher
│       │   └── notifications/     # Trung tâm thông báo & Đếm số lượng chưa đọc
│       ├── styles/                # Hệ thống CSS Design Tokens & Components
│       └── app.js                 # SPA Router & Điều phối ứng dụng
│
└── README.md                      # Hướng dẫn chi tiết dự án (Tài liệu này)
```

---

## ⚙️ 3. Yêu Cầu Môi Trường & Chuẩn Bị

- **PHP:** Phiên bản 8.2 trở lên (bật các extensions: `pdo`, `pdo_mysql`, `mbstring`, `openssl`).
- **MySQL:** Phiên bản 8.0 hoặc 8.4 đang chạy local.
- **Composer:** Phiên bản 2.x.
- **Trình duyệt Web:** Chrome, Edge, Firefox hỗ trợ ES Modules hiện đại.

---

## 🗄️ 4. Hướng Dẫn Kết Nối Cơ Sở Dữ Liệu & Nạp Dữ Liệu Mẫu

### Bước 4.1: Cấu hình thông tin kết nối MySQL
Tạo hoặc mở file `backend/.env` (tham khảo mẫu tại `backend/.env.example`) và điền thông tin đăng nhập MySQL của bạn:

```env
APP_ENV=development
APP_DEBUG=true

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=kich_ban_sat_v3
DB_USERNAME=root
DB_PASSWORD=your_database_password_here
DB_CHARSET=utf8mb4

# Database dùng riêng cho chạy kiểm thử PHPUnit
DB_TEST_DATABASE=kich_ban_sat_v3_test
```
*(Thay `your_database_password_here` và `root` bằng tài khoản/mật khẩu MySQL trên máy của bạn).*

### Bước 4.2: Tạo Cơ sở dữ liệu trong MySQL
Mở MySQL Command Line hoặc công cụ quản trị (phpMyAdmin, DBeaver, MySQL Workbench) và tạo database:
```sql
CREATE DATABASE IF NOT EXISTS `kich_ban_sat_v3` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
CREATE DATABASE IF NOT EXISTS `kich_ban_sat_v3_test` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
```

### Bước 4.3: Chạy Migrations khởi tạo cấu trúc bảng
Mở terminal PowerShell tại thư mục dự án và chạy:
```powershell
cd backend
.\vendor\bin\phinx migrate
```
> Lệnh này sẽ tự động tạo đủ **21 bảng** CSDL chuẩn hóa (Users, Roles, Games, Game Prices, Venues, Physical Rooms, Game Rooms, Room Participants, Schedule Proposals, Votes, Sessions, DM Assignments, Payments, Refunds, Notifications...).

### Bước 4.4: Nạp dữ liệu mẫu ban đầu (Seeding)
Chạy lệnh seeder để nạp dữ liệu mẫu:
```powershell
.\vendor\bin\phinx seed:run
```
> Hệ thống sẽ nạp sẵn:
> - 4 Tài khoản mẫu demo: Admin, DM Hoàng Nam, Player Xuân Hiếu, Player Minh Anh.
> - Danh mục kịch bản sát kèm ma trận bảng giá sáng/tối.
> - Chi nhánh địa điểm (Venues) và các phòng chơi vật lý theo chủ đề.

---

## 🚀 5. Khởi Chạy Dự Án (Chỉ 1 Dòng Lệnh Duy Nhất)

Từ thư mục gốc dự án, bạn chỉ cần gõ đúng **1 câu lệnh** trong PowerShell:

```powershell
php -S 127.0.0.1:8000 -t backend/public
```

Sau đó, mở trình duyệt và truy cập:
👉 **`http://127.0.0.1:8000/`**

---

## 👥 6. Danh Sách Tài Khoản Mẫu & Tính Năng Quick Demo

Để việc trình diễn diễn ra thuận tiện nhất, trong Modal đăng nhập đã có sẵn **Bộ nút bấm 1-click chuyển đổi nhanh tài khoản mẫu**:

| Vai trò (Role) | Họ và Tên | Email mẫu | Mật khẩu mẫu | Mục đích trình diễn |
| :--- | :--- | :--- | :--- | :--- |
| **👑 ADMIN** | Quản Trị Viên | `admin@kichbansat.vn` | `password123` | Quản lý người dùng, phân quyền RBAC, Hủy phòng khẩn cấp theo Quy tắc vàng 24h, Duyệt hoàn tiền |
| **🎭 DM** | Host Hoàng Nam (DM) | `dm.nam@kichbansat.vn` | `password123` | Quản trò xem danh sách ca làm việc, bấm Chấp nhận ca hoặc Từ chối ca kèm lý do |
| **🎲 PLAYER** | Nguyễn Xuân Hiếu | `player.hieu@kichbansat.vn` | `password123` | Người chơi tạo phòng, rủ bạn bè, đề xuất khung giờ, nộp cọc 50.000 VNĐ |
| **🎲 PLAYER** | Trần Minh Anh | `minhanh@gmail.com` | `password123` | Người chơi thứ hai vào ghép phòng, vote biểu quyết giờ chơi hoặc thế chỗ (takeover) |

---

## 🎬 7. Kịch Bản Trình Diễn Demo Hoàn Chỉnh (Step-by-Step)

### Luồng 1: Xem kịch bản & Biểu phí khung giờ V16
1. Đang ở Tab **Kịch Bản**: Xem danh sách các kịch bản với hình ảnh và thông tin số lượng người chơi.
2. Bấm **"Xem bảng giá"** trên một kịch bản bất kỳ để xem hệ thống giá phân định theo quy chuẩn V16:
   - Khung giờ Ngày: `09:00 - 19:59`
   - Khung giờ Tối: Từ `20:00` trở đi
   - Mức cọc quy định cố định: **50.000 VNĐ / người**.

### Luồng 2: Tạo phòng chơi & Ghép người (Matchmaking)
1. Bấm nút **"Đăng nhập / Đổi vai trò"** $\rightarrow$ Chọn **"🎲 Player Hiếu"**.
2. Chuyển sang Tab **Sảnh Phòng** $\rightarrow$ Bấm nút **"➕ Tạo phòng mới"**.
3. Chọn kịch bản *"Đêm Đoạt Mệnh Tại Trang Viên"* $\rightarrow$ Bấm **Tạo phòng**. Phòng lập tức hiển thị ở trạng thái gom người `FORMING`.

### Luồng 3: Ghép người chơi thứ hai & Biểu quyết (Consensus Voting)
1. Đổi sang tài khoản **"🎲 Player Minh Anh"** qua Quick Switcher.
2. Vào chi tiết phòng vừa tạo $\rightarrow$ Bấm **"Tham gia phòng này"**.
3. Tại phần *Đề xuất giờ chơi*: Chọn thời gian dự kiến (VD: Ngày mai lúc 14:00) $\rightarrow$ Bấm **Đề xuất giờ chơi**.
4. Cả hai người chơi lần lượt bấm **"✓ Đồng ý"**:
   - Khi tỷ lệ đồng thuận đạt **100%**, Backend tự động kích hoạt tạo `Session` và phân công Quản trò (DM Assignment).

### Luồng 4: Tính toán Snapshot giá & Thanh toán đặt cọc 50k
1. Sau khi chốt giờ, hệ thống gọi `PricingService` để chốt biểu giá snapshot theo đúng khung giờ đã vote.
2. Người chơi bấm nút **"💳 Đặt Cọc 50.000đ Giữ Chỗ"** $\rightarrow$ Hệ thống gọi API thanh toán với `Idempotency-Key` an toàn, xác nhận người chơi đã nộp cọc thành công.

### Luồng 5: Cơ chế Thế Chỗ (Replacement Slot Takeover)
1. Một người chơi bấm **"Rút khỏi phòng"** $\rightarrow$ Cửa sổ thay thế mở ra (`replacement_status = 'OPEN'`).
2. Một tài khoản người chơi khác truy cập vào phòng sẽ thấy nút **"⚡ Thế Chỗ Slot Trống"** để lập tức trám vào vị trí người vừa rút lui mà không làm vỡ phòng chơi.

### Luồng 6: Quản trò (DM Portal) Xác nhận ca
1. Chuyển sang tài khoản **"🎭 DM Hoàng Nam"** $\rightarrow$ Vào Tab **"Quản Trò (DM)"**.
2. Thấy ca vừa được chốt giờ kèm tên kịch bản, thời gian và phòng diễn vật lý.
3. Bấm **"✓ Chấp nhận ca"** để nhận lịch diễn hoặc **"✕ Từ chối ca"** (sẽ hiện modal yêu cầu nhập lý do từ chối theo nghiệp vụ).

### Luồng 7: Bảng điều khiển Quản trị viên (Admin Portal)
1. Chuyển sang tài khoản **"👑 Admin"** $\rightarrow$ Vào Tab **"Quản Trị (Admin)"**:
   - **Tab Quản lý người dùng:** Đổi vai trò RBAC giữa Player/DM/Admin, khóa/mở khóa tài khoản (có cơ chế an toàn ngăn Admin tự giáng chức hoặc tự khóa chính mình).
   - **Tab Quản lý phòng & Hủy phòng khẩn cấp:** Áp dụng **Quy Tắc Vàng 24 Giờ**:
     - Nếu Admin hủy phòng trước giờ chơi $\ge 24\text{h}$ $\rightarrow$ Toàn bộ người chơi đã cọc được tự động duyệt hoàn tiền (`APPROVED`).
     - Nếu Admin hủy phòng $< 24\text{h}$ $\rightarrow$ Yêu cầu hoàn cọc bị từ chối (`REJECTED`) do vi phạm mốc báo trước.
   - **Tab Xét duyệt hoàn tiền:** Xem danh sách, bấm Duyệt hoàn cọc hoặc Từ chối kèm lý do.

---

## ⏰ 8. Chạy Tiến Trình Nền (CLI Background Cron Jobs)

Dự án cung cấp file thực thi tiến trình nền `backend/bin/cron.php` để tự động dọn dẹp và xử lý timeout theo chu kỳ mà không cần can thiệp thủ công:

```powershell
# Chạy toàn bộ các jobs kiểm tra định kỳ:
php backend/bin/cron.php

# Hoặc chỉ định chạy riêng từng job:
php backend/bin/cron.php formation-timeout   # Hủy các phòng gom người quá hạn 48 giờ
php backend/bin/cron.php deposit-timeout     # Hủy phòng nếu quá hạn nộp cọc 24 giờ
php backend/bin/cron.php replacement-timeout # Xử lý cửa sổ tìm người thay thế quá hạn 12 giờ
```

---

## 🧪 9. Kiểm Thử Hệ Thống (Automated Testing)

Dự án đạt tỷ lệ kiểm thử thành công **100%** trên cả Backend và Frontend:

### Kiểm thử Backend (PHPUnit):
```powershell
cd backend
.\vendor\bin\phpunit
```
> Kết quả: **`OK (107 tests, 483 assertions)`** — Bao phủ toàn bộ Unit Tests, Feature API Tests, Idempotency, Pricing, và 24h Golden Rule Refund.

### Kiểm thử Frontend (Node.js Test Runner):
```powershell
node frontend/tests/api-client.test.mjs
```
> Kết quả: **`7 tests pass (100%)`** — Xác thực cơ chế AbortController, Request Timeout, CSRF Token Prefetch, và Xử lý mã lỗi chuẩn hóa Envelope.

---

## 📜 10. Bản Quyền & Giấy Phép
Dự án được xây dựng phục vụ mục đích học tập và phát triển ứng dụng web chất lượng cao theo kiến trúc hiện đại Native MVC và Clean Code.
Mọi thắc mắc kỹ thuật vui lòng tham khảo mã nguồn hoặc tài liệu trong thư mục [`docs/`](file:///d:/LapTrinhWeb/Project/Booking_kich-ban-sat_PHP/docs/).
