# FE_kich_ban_sat
Frontend web đặt phòng &amp; ghép nhóm trò chơi nhập vai phá án Kịch Bản Sát V16 (HTML5 / CSS3 / Vanilla JS)
# 🎭 KỊCH BẢN SÁT V16 — Murder Mystery Booking Platform

Hệ thống website đặt phòng, ghép nhóm và điều phối trò chơi nhập vai phá án (**Murder Mystery / TTRPG**) thực tế tại TP.HCM.

* **Địa chỉ quán:** 36/52 Nguyễn Gia Trí, P.25, Q.Bình Thạnh, TP.HCM
* **Giờ mở cửa:** 09:00 – 23:00 mỗi ngày
* **Hotline đặt phòng:** `091.569.0033` | **Hợp tác & Kịch bản:** `0989.896.833` (Mrs Yến)
* **Công nghệ:** HTML5, CSS3 (Custom Properties, Scrollytelling Parallax, 3D Flip), Vanilla JavaScript (ES Modules, LocalStorage, API Service Layer).

---

## 👥 Phân Công Thành Viên (Nhóm 3 Người)

| Thành viên | GitHub | Nhánh làm việc | Nhiệm vụ chính |
| :--- | :--- | :--- | :--- |
| **Y_Huỳnhh (Lead / Quản lý)** | `@yhuynh06` | `feat/ui-guest` | Design System (`tokens.css`, `components.css`), Lớp kết nối API (`src/api/`), Dữ liệu 30 kịch bản (`scripts.data.js`), Trang chủ Parallax (`01_Home`), Kho kịch bản & Modal 18+ (`03a`, `03b`), Trang thông tin (`09`, `10a`, `10b`) & Duyệt Pull Request. |
| **Lệ Châu** | `@lechau06` | `feat/player-booking` | Cụm vé xác thực (`02a-c`), Sảnh phòng (`04`), Tạo phòng & Tính giá tự động (`05a`), Vé giữ chỗ 4h vs Đã cọc QR (`05b-c`), Chi tiết phòng 8 slot & Hoàn cọc/Hủy phòng (`06a`), Hồ sơ & Upload thẻ HSSV (`06b`). |
| **Như** | `@devnu` | `feat/dm-admin` | Cổng Quản trò DM (`07a-b`: nhận ca, lịch dẫn, điểm danh check-in) & Cổng Quản trị Admin (`08a-f`: Tổng quan KPI, Điều phối phòng/DM, Duyệt hoàn cọc 24h, Quản lý 30 kịch bản, Phòng vật lý, Duyệt thẻ HSSV). |

---

## 📁 Cấu Trúc Thư Mục Dự Án

```text
FE_kich_ban_sat/
├── index.html                  # Trang chủ (01_Home)
├── pages/                      # Các trang chức năng độc lập
│   ├── auth.html               # Đăng nhập, Đăng ký, Quên mật khẩu
│   ├── scenarios.html          # Kho 30 kịch bản chính thức
│   ├── scenario-detail.html    # Chi tiết kịch bản & Modal 18+
│   ├── lobby.html              # Sảnh phòng đang tuyển & đã chốt
│   ├── create-room.html        # Vé tạo phòng mới
│   ├── booking-status.html     # Vé giữ chỗ 4h & Vé xác nhận cọc QR
│   ├── room-detail.html        # Chi tiết phòng 8 slot & 3 trạng thái cọc
│   ├── profile.html            # Hồ sơ người chơi & Xác minh thẻ HSSV
│   ├── dm-portal.html          # Cổng Quản trò (DM)
│   ├── admin-portal.html       # Cổng Quản trị viên (Admin)
│   └── info.html               # Liên hệ, Bản đồ, Chính sách & 404
└── src/
    ├── assets/                 # Hình ảnh (logo.png, bìa 30 kịch bản) & Icons
    ├── styles/                 # CSS tách theo phạm vi (tokens, components, home, player, portals)
    ├── api/                    # Lớp giao tiếp dữ liệu (Mock Mode & sẵn sàng nối PHP Backend)
    ├── data/                   # Dữ liệu 30 kịch bản thật & Mock DB
    ├── logic/                  # Xử lý Store, Tính giá, Đếm ngược 4h/24h, Phân quyền
    └── pages-js/               # JS điều khiển từng cụm trang