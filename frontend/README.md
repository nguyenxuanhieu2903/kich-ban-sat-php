# GIAO DIỆN HỆ THỐNG KỊCH BẢN SÁT (FRONTEND PORTAL SUITE)
**Dự án:** Nền tảng Đặt phòng & Vận hành Chơi Kịch Bản Sát (Jubensha / Murder Mystery)  
**Công nghệ:** HTML5, CSS3 Tokens hiện đại, Vanilla JavaScript (ES Modules chuẩn trình duyệt, Zero-Build, Zero-Framework)  
**Tiêu chuẩn thẩm mỹ:** *Dark Cinematic Luxury / Film Noir* (Cormorant Garamond, Be Vietnam Pro, JetBrains Mono, Tông Vàng Cổ `#C4A36B` & Đỏ Huyết `#B52E36`).

---

## 1. KHỞI CHẠY HỆ THỐNG

### 1.1. Chạy Frontend
Từ thư mục gốc của repository, chạy máy chủ phát triển tích hợp của PHP:
```bash
php -S 127.0.0.1:3000 -t frontend
```
Sau đó truy cập bằng trình duyệt web:
👉 **`http://127.0.0.1:3000/`** (hoặc `http://localhost:3000/`)

### 1.2. Chạy Backend API (Tùy chọn khi test Live Mode)
```bash
php -S 127.0.0.1:8080 -t backend
```
API Endpoint chuẩn: `http://127.0.0.1:8080/api`

---

## 2. CHẾ ĐỘ DỮ LIỆU & TÀI KHOẢN TRẢI NGHIỆM (DEMO MODE)

Hệ thống được trang bị bộ chuyển đổi thông minh (`DATA_MODE`) trong `src/utils/config.js`:
- **`demo` (Mặc định):** Sử dụng `sharedStore` lưu trữ trong `localStorage`. Cả 3 vai trò (Player, DM, Admin) cùng thao tác trên một cơ sở dữ liệu đồng nhất. Thao tác ở portal này sẽ ngay lập tức phản ánh sang portal khác!
- **`live`:** Tự động kết nối tới backend thực tế tại `http://127.0.0.1:8080/api`. Tự động fallback sang mock data nếu endpoint chưa hoàn thiện trên backend.

### 2.1. Đăng nhập 1-Click (Demo Switcher)
Trên thanh điều hướng đỉnh trang (Top Navigation), nhấp vào nút vai trò hoặc truy cập trang `#/login` để chọn tài khoản mẫu có sẵn:
1. **Người chơi (Player):** `Minh Tuấn` (`player1`) — Đã xác thực thẻ HSSV, hưởng ưu đãi -20.000đ từ T2-T5.
2. **Quản trò (DM):** `Hoàng Nam` (`dm1`) — Xem các kịch bản được phân công, chấp nhận/từ chối dẫn, cập nhật khung giờ rảnh.
3. **Quản trị viên (Admin):** `Quản trị viên Diệp` (`admin1`) — Quản lý phòng chơi, chỉ định DM, chốt phòng & lịch, duyệt hoàn tiền, xem audit log.

*Nút "Reset Demo" trên thanh Menu giúp khôi phục kho dữ liệu 12 phòng mẫu ban đầu bất kỳ lúc nào.*

---

## 3. CẤU TRÚC THƯ MỤC NGUỒN (`frontend/`)

```
frontend/
├── index.html                   # File HTML duy nhất (SPA container)
├── README.md                    # Tài liệu hướng dẫn sử dụng & triển khai
├── docs/
│   └── API_NEEDS.md             # Đặc tả yêu cầu API Backend đầy đủ
├── tests/
│   ├── api-client.test.mjs      # Test suite kiểm thử kết nối API, abort, timeout
│   └── utils.test.mjs           # Test suite kiểm thử định dạng tiền, countdown, router, rules
└── src/
    ├── app.js                   # Điểm khởi chạy SPA & đăng ký tuyến đường (Routes)
    ├── styles/
    │   ├── variables.css        # Hệ thống biến CSS Token chuẩn Cinematic Luxury
    │   └── main.css             # Toàn bộ CSS bố cục, bảng biểu, modal, toast, responsive
    ├── content/
    │   ├── brand.js             # Nội dung định vị thương hiệu, cam kết & quy tắc chơi
    │   └── assets.js            # Trình vẽ SVG poster tự động cho 42 kịch bản
    ├── utils/
    │   ├── config.js            # Cấu hình API URL, DATA_MODE, timeout
    │   ├── status.js            # Enums trạng thái 7 domain & nhãn tiếng Việt chuẩn
    │   ├── rules.js             # Quy tắc nghiệp vụ Freeze V16 & 13 quyết định mở
    │   ├── format.js            # Định dạng tiền tệ VND, thời gian GMT+7, countdown
    │   ├── dom.js               # Helper tạo thẻ DOM an toàn & thư viện biểu tượng SVG
    │   ├── idempotency.js       # Quản lý khóa chống gửi lặp UUID v4
    │   ├── auth.js              # Quản lý phiên đăng nhập & phân quyền RBAC
    │   └── router.js            # Trình định tuyến Hash Router (`#/...`) có bảo vệ vai trò
    ├── api/
    │   ├── api-service.js       # Gateway trung chuyển giữa Live API và Demo Store
    │   ├── live-api.js          # Adapter gọi REST API Backend thực tế
    │   └── demo/
    │       ├── games-data.js    # Danh mục 42 kịch bản (kèm 3 game 18+)
    │       ├── quote-simulator.js # Mô phỏng tính giá theo quy tắc Freeze V16
    │       ├── shared-store.js  # Kho dữ liệu dùng chung duy nhất cho cả 3 Portal
    │       └── demo-api.js      # Mock API adapter kèm độ trễ mạng thực tế (300-600ms)
    ├── components/
    │   ├── toast.js             # Thông báo nổi trợ năng aria-live
    │   ├── modal.js             # Hộp thoại modal bẫy tiêu điểm (focus trap)
    │   ├── confirm-dialog.js    # Hộp thoại xác nhận thay thế native confirm()
    │   ├── reason-dialog.js     # Hộp thoại nhập lý do bắt buộc cho Admin/DM
    │   ├── status-badge.js      # Huy hiệu trạng thái phong cách con dấu sáp niêm phong
    │   ├── countdown.js         # Bộ đếm ngược đồng bộ chống lệch giờ máy khách
    │   ├── stepper.js           # Thanh tiến trình chu kỳ sống của phòng chơi
    │   ├── participant-ring.js  # Sơ đồ chỗ ngồi bàn tròn trực quan SVG
    │   ├── price-breakdown.js   # Bảng kê bóc tách chi phí & ưu đãi minh bạch
    │   ├── data-table.js        # Bảng dữ liệu quản trị: tìm kiếm, lọc, sắp xếp, phân trang
    │   ├── top-nav.js           # Thanh điều hướng trên cùng & bộ chuyển vai trò
    │   └── bottom-nav.js        # Thanh tabbar dưới đáy màn hình trên thiết bị di động
    └── pages/
        ├── public/              # Trang công khai: Trang chủ, Danh mục kịch bản, Chi tiết kịch bản, Danh sách phòng, 403, 404
        ├── auth/                # Đăng nhập & Đăng ký
        ├── player/              # Portal Người chơi: Dashboard, Tạo phòng, Chi tiết phòng, Lịch sử, Hồ sơ, Thông báo
        ├── dm/                  # Portal Quản trò: Dashboard, Lịch phân công, Chi tiết phòng, Đăng ký rảnh, Điều khiển phiên, Thông báo
        └── admin/               # Portal Quản trị: Dashboard, Quản lý phòng, Phân công DM, Phòng vật lý, Hoàn tiền, Kịch bản, Ưu đãi, Sessions, Người dùng, Audit Logs
```

---

## 4. CHẠY BỘ KIỂM THỬ TỰ ĐỘNG (UNIT TESTS)

Dự án tích hợp bộ kiểm thử độc lập chạy trực tiếp trên Node.js (không cần cài thêm npm packages):
```bash
node --test frontend/tests/api-client.test.mjs frontend/tests/utils.test.mjs
```
Kết quả nghiệm thu: **16/16 tests pass hoàn toàn 100%**.

---

## 5. CÁC QUY TẮC NGHIỆP VỤ BĂNG ĐÔNG V16 (BUSINESS FREEZE) ĐÃ ÁP DỤNG
1. **Tiền cọc cố định:** 50.000 VNĐ/người, tuyệt đối không tính theo phần trăm, không làm tròn số lẻ. Số tiền còn lại = Tổng sau ưu đãi − Cọc đã ghi nhận.
2. **Chính sách hoàn cọc:** Hủy trước giờ bắt đầu Session $\ge 24$ giờ đủ điều kiện hoàn cọc. Hủy dưới 24 giờ không hoàn tiền. Mọi khoản hoàn tiền đều đi qua state machine Refund minh bạch.
3. **Hai khung giờ tính giá:** Khung ngày (`09:00 - 19:59`) và Khung tối (`từ 20:00`). Giá lấy từ cấu hình từng kịch bản.
4. **Quy tắc ưu đãi:**
   - Ưu đãi Thứ Ba: Giảm 30% cho mọi game đặt trước ít nhất 1 ngày (áp dụng 09:00 - 19:00).
   - Ưu đãi Học sinh - Sinh viên (HSSV): Giảm 20.000đ/người từ Thứ Hai đến Thứ Năm (09:00 - 19:00).
   - **Tuyệt đối không cộng dồn hai ưu đãi trên**.
5. **Hạn cọc 2 giờ độc lập:** Mỗi người chơi có bộ đếm ngược 2 giờ riêng biệt từ lúc mở thanh toán (hoặc từ lúc người thay thế tham gia).
6. **Hộp thoại lý do bắt buộc:** Áp dụng nghiêm ngặt khi DM từ chối phân công, Admin thu hồi phân công, Admin hủy phòng, Admin từ chối hoàn tiền, Admin hủy khẩn cấp phiên chơi.
