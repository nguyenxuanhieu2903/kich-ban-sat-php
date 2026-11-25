# BẢNG KIỂM KÊ THÀNH PHẦN GIAO DIỆN (COMPONENT INVENTORY)
## KỊCH BẢN SÁT V16 — HỆ THỐNG 4 PORTALS

---

## 1. THÀNH PHẦN DÙNG CHUNG TOÀN HỆ THỐNG (GLOBAL SHARED COMPONENTS)

### 1.1. Header & Navigation Bar
- **Vị trí**: Cố định trên đỉnh màn hình (`position: sticky; top: 0; z-index: 100;`).
- **Phần tử cốt lõi**:
  - Logo thương hiệu Kịch Bản Sát V16 kèm hiệu ứng đổ bóng ánh vàng.
  - Nhóm nút điều hướng (`#nav-btn-home`, `#nav-btn-games`, `#nav-btn-rooms`, `#nav-btn-venues`, `#nav-btn-dm`, `#nav-btn-admin`).
  - Nút thông báo hệ thống `#nav-btn-notif` kèm huy hiệu số đếm chưa đọc `#notif-unread-count`.
  - Khối tài khoản `#nav-user-container`:
    - Khách: Nút "Đăng nhập" (`#btn-open-login`) và "Đăng ký" (`#btn-open-register`).
    - Đã đăng nhập: Huy hiệu `.user-pill` hiển thị vai trò (`PLAYER`, `DM`, `ADMIN`), tên người dùng và nút `#btn-logout`.
  - Nút chuyển menu di động `#nav-hamburger-btn` và ngăn kéo `#mobile-nav-drawer`.

### 1.2. Slide-Over Drawer Service
- **Bộ điều khiển**: `DialogService.drawer(title, htmlContent)`
- **Quy cách**:
  - Trượt vào từ cạnh phải với độ rộng `min(560px, 92vw)`.
  - Nền tối đa tầng `var(--kbs-bg-card, #1A1D27)`, đổ bóng `-4px 0 30px rgba(0,0,0,0.7)`.
  - Nút đóng `×` với kích thước bấm $44 \times 44$px.
  - Phím `Escape` và click ra ngoài backdrop để thoát.

### 1.3. Dialog & Prompt Modal
- **Bộ điều khiển**: `DialogService.prompt(title, message, defaultValue)` & `DialogService.confirm(title, message)`
- **Quy cách**:
  - Modal nổi chính giữa màn hình với nền mờ backdrop.
  - Trường nhập liệu `form-control` bo góc 8px với viền vàng khi focus.
  - Hai nút hành động: Hủy bỏ (`btn-ghost`) và Xác nhận (`btn-gold` / `btn-primary`).

### 1.4. Unified Footer
- **Vị trí**: Chân toàn bộ các trang.
- **Cấu trúc 4 cột**:
  - Cột 1: Logo, mô tả và liên kết mạng xã hội (Facebook, Instagram, TikTok).
  - Cột 2: Bản đồ Google Maps nhúng trực tiếp chi nhánh 36/52 Nguyễn Gia Trí, Bình Thạnh.
  - Cột 3: Điều hướng nhanh các trang.
  - Cột 4: Thông tin mở cửa (09:00 - 23:00) và Hotline hỗ trợ/hợp tác.

---

## 2. THÀNH PHẦN CỔNG NGƯỜI CHƠI (PLAYER PORTAL COMPONENTS)

### 2.1. Thanh tiến trình 4 bước (`kbs-steps-row`)
- **Vị trí**: Đỉnh trang chi tiết phòng chơi (`renderRoomDetail`) và thẻ vé của tôi.
- **Cấu trúc 4 bước**:
  - Bước 1: **Gom người** (Forming) — Đủ số lượng người chơi tối thiểu.
  - Bước 2: **Chốt lịch & Quán** (Voting & Confirmation) — Biểu quyết giờ và địa điểm.
  - Bước 3: **Đặt cọc 50.000đ** (Deposit Payment) — Nộp cọc xác nhận giữ ghế.
  - Bước 4: **Nhận vé QR & Chơi** (Active / In Progress) — Quét mã check-in tại quán.
- **Quy cách hiển thị**: Vòng tròn số thứ tự (`kbs-step-num`), tự động đổi thành dấu tích xanh (`✓`) khi hoàn thành bước.

### 2.2. Widget đếm ngược giữ chỗ 4 giờ (Countdown Widget)
- **Vị trí**: Đỉnh trang chi tiết phòng chơi khi phòng đang ở trạng thái `FORMING`.
- **Nội dung**: Đồng hồ cát `⏳`, thời gian giữ chỗ còn lại, thông báo hạn chót nộp cọc 50.000đ trước khi tự động giải tán phòng.

### 2.3. Bảng phân tách tài chính 2 cột (Financial Breakdown Cards)
- **Thẻ 1 — Tiền cọc giữ chỗ**:
  - Mức cọc cố định 50.000 VNĐ / người.
  - Trạng thái nộp cọc kèm nút `#btn-pay-deposit` quét mã nộp cọc mô phỏng.
- **Thẻ 2 — Khoản còn lại thanh toán tại quán**:
  - Số tiền chính xác sau khi lấy giá vé gốc trừ cọc 50k và trừ ưu đãi HSSV (nếu có).
  - Nút `#btn-pay-remaining` thanh toán trước số tiền còn lại.

### 2.4. Danh sách thành viên & Nút Mời ra (`[data-kick-user]`)
- **Quy cách**:
  - Hiển thị thứ tự, tên người chơi, email và huy hiệu trạng thái tiếng Việt ("✓ Đã cọc 50k", "Chờ thế chỗ", "Chờ nộp cọc").
  - Nút "Mời ra" dành riêng cho Chủ phòng (Host): kích thước đạt chuẩn chạm $\ge 38$px, viền đỏ cảnh báo, mở hộp thoại nhập lý do trước khi loại thành viên chưa nộp cọc.

### 2.5. Mobile Bottom Navigation (`kbs-mobile-bottom-nav`)
- **Vị trí**: Cố định đáy màn hình trên thiết bị di động $\le 640$px.
- **4 nút chạm nhanh**:
  - 🎲 Sảnh phòng
  - 🎟️ Vé của tôi
  - 🎓 Hồ sơ HSSV
  - 🎭 Kho kịch bản

### 2.6. Khung tải & Xem trước ảnh thẻ HSSV (`#student-preview-container`)
- Tự động hiển thị bản thu nhỏ của ảnh thẻ ngay khi người dùng chọn tệp từ máy ảnh hoặc thư viện ảnh.
- Huy hiệu hiển thị 4 trạng thái: `CHƯA XÁC MINH`, `ĐANG CHỜ DUYỆT`, `ĐÃ XÁC MINH HỢP LỆ` (-20k), và `BỊ TỪ CHỐI`.

---

## 3. THÀNH PHẦN CỔNG QUẢN TRÒ (DM PORTAL COMPONENTS)

### 3.1. Thẻ phân công ca trực (DM Assignment Card)
- **Độ ưu tiên thị giác**:
  - Nút "✓ Chấp nhận ca" (`btn-accept`): Màu xanh ngọc Emerald Gradient (`#10b981`), bóng đổ mềm, nổi bật rõ nét.
  - Nút "✕ Từ chối ca" (`btn-reject`): Viền đỏ nhạt, nút dạng ghost tinh tế.
  - Nút "👥 Danh sách người chơi" (`btn-toggle-players`): Nút phụ xem nhanh người chơi trong ca trực.

### 3.2. Live Session Banner
- Xuất hiện khi ca trực chuyển sang trạng thái `IN_PROGRESS`.
- Nhãn nhấp nháy đỏ trinh thám: `🔴 CA CHƠI ĐANG DIỄN RA (Phiên 240 phút)`.
- Huy hiệu kiểm kê đạo cụ: `Đạo cụ: Đủ 100%`.

### 3.3. Hộp thoại hoàn tất ca trực (`btn-complete-session`)
- Mở quy trình 2 bước:
  - Bước 1: Nhập tóm tắt kết quả phá án (`session_notes`).
  - Bước 2: Báo cáo sự cố phát sinh / hư hỏng đạo cụ nhân vật (`incident_report`).

---

## 4. THÀNH PHẦN CỔNG QUẢN TRỊ (ADMIN PORTAL COMPONENTS)

### 4.1. Thẻ chỉ số tổng quan (Overview KPI Cards)
- 4 thẻ thống kê chính: Doanh thu thực tế (`PAID`), Ca chơi hoàn thành, Yêu cầu hoàn cọc chờ duyệt (`PENDING`), Tỷ lệ lấp đầy phòng.

### 4.2. Bảng dữ liệu co giãn thông minh (`kbs-table-responsive`)
- Bao bọc toàn bộ các bảng: Người dùng, Phòng chơi, Giao dịch thanh toán, Hoàn tiền, Kịch bản, Khuyến mãi, Chi nhánh và Nhật ký hệ thống.
- Hỗ trợ cuộn ngang êm ái trên màn hình nhỏ mà không làm biến dạng layout trang.

### 4.3. Ngăn kéo chi tiết thanh toán & Nhật ký kiểm toán
- **Drawer hóa đơn**: Hiển thị chi tiết khách hàng, phòng chơi, tiền cọc, số tiền còn lại, danh sách giao dịch thẻ và yêu cầu hoàn tiền.
- **Drawer kiểm toán**: Hiển thị thời gian, IP, User Agent, và khối đối chiếu JSON giữa dữ liệu cũ (`old_values`) và dữ liệu mới (`new_values`).
