```markdown
#  KỊCH BẢN SÁT V16 — Murder Mystery Booking Platform

Frontend web đặt phòng, ghép nhóm và điều phối trò chơi nhập vai phá án (**Murder Mystery / TTRPG**) thực tế tại TP.HCM.

* **Địa chỉ quán:** 36/52 Nguyễn Gia Trí, P.25, Q.Bình Thạnh, TP.HCM
* **Giờ mở cửa:** 09:00 – 23:00 mỗi ngày
* **Hotline đặt phòng:** `091.569.0033` | **Hợp tác & Kịch bản:** `0989.896.833` (Mrs Yến)
* **Baseline Backend:** MySQL 8.0 / InnoDB (22 bảng) & PHP API
* **Công nghệ Frontend:** HTML5, CSS3 (Design Tokens, Scrollytelling Parallax, 3D Flip), Vanilla JavaScript (ES Modules, LocalStorage, Service Layer).

---

##  Phân Công Thành Viên (Nhóm 3 Người)

| Thành viên | GitHub | Nhánh làm việc | Nhiệm vụ chính |
| :--- | :--- | :--- | :--- |
| **Y_Huỳnhh (Lead / Quản lý)** | `@yhuynh06` | `feat/foundation-guest` | Design System (`tokens.css`, `components.css`), Khung giao diện chung (`layout.js`), Phân quyền & Store (`guard.js`, `store.js`, `utils.js`), Cầu nối API gắn `Idempotency-Key` (`api-client.js`), Dữ liệu 42 kịch bản (`scripts.data.js`), Trang chủ Parallax (`index.html`), Kho 42 kịch bản & Modal 18+ (`scenarios.html`, `scenario-detail.html`), Thông tin & Xác thực (`info.html`, `auth.html`), Duyệt Pull Request. |
| **Như (Thành viên 2)** | `@devnu` | `feat/player-portal` | Sảnh phòng đang tuyển (`lobby.html`), Tạo phòng & Tính giá tự động không cộng dồn (`create-room.html`, `pricing.engine.js`), Vé giữ chỗ 4h & Vé đã cọc QR (`booking-status.html`, `timer.engine.js`), Chi tiết phòng 8 slot, biểu quyết lịch & Hoàn cọc 24h (`room-detail.html`), Hồ sơ & Upload thẻ HSSV (`profile.html`). |
| **Lệ Châu (Thành viên 3)** | `@lechau06` | `feat/admin-portal`<br>`feat/dm-portal` | **Cổng Quản trị Admin (`admin-portal.html`):** Tổng quan KPI, Điều phối phòng & Phân công DM, Duyệt hoàn cọc 24h, Quản lý kho 42 kịch bản & bảng giá, Quản lý phòng vật lý & Duyệt thẻ HSSV.<br>**Cổng Quản trò DM (`dm-portal.html`):** Nhận/từ chối ca dẫn, Lịch dẫn ca, Check-in QR & Thu tiền còn lại (`REMAINING`), Bắt đầu & Hoàn tất phiên chơi. |

---

##  Cấu Trúc Thư Mục Dự Án

```text
FE_kich_ban_sat/
├── index.html                  # Trang chủ Parallax (01_Home)
├── README.md                   # Giới thiệu dự án & phân công nhóm
├── CONTRIBUTING.md             # Quy định Git, file khóa & quy tắc nghiệp vụ
├── pages/                      # Các trang chức năng độc lập
│   ├── auth.html               # Đăng nhập, Đăng ký, Quên mật khẩu
│   ├── scenarios.html          # Kho 42 kịch bản chính thức
│   ├── scenario-detail.html    # Chi tiết kịch bản & Modal 18+
│   ├── lobby.html              # Sảnh phòng đang tuyển & đã chốt
│   ├── create-room.html        # Vé tạo phòng mới
│   ├── booking-status.html     # Vé giữ chỗ 4h & Vé xác nhận cọc QR
│   ├── room-detail.html        # Chi tiết phòng 8 slot, biểu quyết lịch & hoàn cọc 24h
│   ├── profile.html            # Hồ sơ người chơi & Xác minh thẻ HSSV
│   ├── dm-portal.html          # Cổng Quản trò (DM)
│   ├── admin-portal.html       # Cổng Quản trị viên (Admin)
│   └── info.html               # Bảng giá, Nội quy, Liên hệ, Bản đồ & 404
└── src/
    ├── assets/
    │   ├── images/             # logo.png & ảnh bìa 42 kịch bản
    │   └── icons/              # Biểu tượng giao diện
    ├── styles/
    │   ├── tokens.css          # [Khóa] Biến màu sắc, font chữ, khoảng cách
    │   ├── components.css      # [Khóa] Header, Footer, .ticket-card, Modal, Badge
    │   ├── home.css            # Style Trang chủ & Kho kịch bản (.home-, .catalog-)
    │   ├── player.css          # Style luồng Người chơi (.booking-, .room-, .profile-)
    │   └── portals.css         # Style cổng DM & Admin (.dm-, .admin-)
    ├── api/
    │   └── api-client.js       # [Khóa] Lớp giao tiếp dữ liệu & gắn Idempotency-Key
    ├── data/
    │   ├── scripts.data.js     # [Khóa] Dữ liệu 42 kịch bản thật
    │   └── mock-db.js          # [Dùng chung] Dữ liệu giả chia theo vùng comment
    ├── logic/
    │   ├── layout.js           # [Khóa] Tự động render Header, Footer & Thông báo
    │   ├── guard.js            # [Khóa] Phân quyền truy cập theo 4 vai trò
    │   ├── store.js            # [Khóa] Kho lưu trữ LocalStorage dùng chung
    │   ├── utils.js            # [Khóa] Hàm định dạng tiền VNĐ & ngày giờ
    │   ├── pricing.engine.js   # Tính giá vé & ưu đãi (Thứ 3 / HSSV không cộng dồn)
    │   └── timer.engine.js     # Đếm ngược giữ chỗ 4h & mốc hoàn cọc 24h
    └── pages-js/
        ├── home.page.js        # Logic Trang chủ
        ├── catalog.page.js     # Logic Kho kịch bản & Chi tiết kịch bản
        ├── auth.page.js        # Logic Đăng nhập, Đăng ký, Quên mật khẩu
        ├── booking.page.js     # Logic luồng Người chơi (có thể tách lobby/room/profile)
        ├── dm.page.js          # Logic cổng Quản trò (DM)
        └── admin.page.js       # Logic cổng Quản trị (Admin)