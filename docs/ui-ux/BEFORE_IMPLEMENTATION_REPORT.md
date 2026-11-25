# BÁO CÁO TRƯỚC TRIỂN KHAI (BEFORE IMPLEMENTATION REPORT)
## DỰ ÁN TÁI THIẾT KẾ TOÀN DIỆN UI/UX KỊCH BẢN SÁT V16

**Tài liệu:** Báo cáo Phân tích Hiện trạng, Đánh giá Khoảng cách và Lộ trình Triển khai  
**Thời gian lập:** 25/11/2026  
**Chịu trách nhiệm:** Senior UI/UX Designer & Principal Frontend Architect  
**Định hướng thiết kế chính thức:** **Cinematic Modern Hybrid** (Bản sắc trinh thám Jubensha + Giao diện phẳng hiện đại, trực quan, chuyên nghiệp).

---

## 1. Current Frontend Architecture (Kiến Trúc Frontend Hiện Tại)

Hệ thống frontend của **Kịch Bản Sát V16** hiện đang vận hành theo mô hình **Hỗn hợp MPA + SPA (Hybrid Multi-Page / Single-Page Application)** bằng Vanilla JavaScript ES Modules, không sử dụng framework trung gian (React/Vue/Angular) và không dùng build step (Vite/Webpack):

```
                        ┌───────────────────────────────┐
                        │      Khách & Người Dùng        │
                        └───────────────┬───────────────┘
                                        │
                 ┌──────────────────────┴──────────────────────┐
                 ▼                                             ▼
        [LUỒNG SPA ROUTER]                            [LUỒNG MPA STANDALONE]
       frontend/index.html                           frontend/*.html
               │                                      ├── player.html (36 KB)
       frontend/src/app.js                            ├── dm.html (23 KB)
       (Hash-based Router)                            └── admin.html (76 KB)
         ├── #home   -> HomeView                              │
         ├── #games  -> GamesView                     Mỗi trang tự nạp script
         ├── #rooms  -> RoomsView                     inline, tự fetch API,
         ├── #dm     -> DmView                        tự render DOM riêng biệt!
         ├── #admin  -> AdminView
         └── #venues -> VenuesView
```

### Điểm nghẽn kiến trúc (Architectural Bottlenecks)
1. **Khủng hoảng duy trì mã nguồn kép (Dual-State Trap):**
   Cùng một màn hình quản trị Admin tồn tại song song ở hai file: `frontend/admin.html` (1.405 dòng) và `frontend/src/pages/admin/admin-view.js` (1.487 dòng). Cùng màn hình Quản trò tồn tại ở `dm.html` và `dm-view.js`. Cùng màn hình Người chơi tồn tại ở `player.html` và `rooms-view.js`. Tổng cộng hơn 3.200 dòng mã giao diện bị nhân đôi.
2. **Lạm dụng Inline Styles:**
   Hơn 85% phần tử giao diện trong các view JavaScript được gắn style trực tiếp thông qua template strings (`style="background: ...; padding: ...; color: ...;"`). Điều này vô hiệu hóa các file CSS chuẩn, khiến việc kiểm soát responsive và theming trở nên bất khả thi.
3. **Phân mảnh Stylesheets:**
   Tồn tại tới 9 file CSS được nạp cùng lúc vào `index.html`. Trong đó `tokens.css` và `variables.css` duplicate 80% biến `:root`, còn `pages.css` chứa hàng trăm dòng Dead CSS không có bất kỳ markup nào tham chiếu.

---

## 2. Complete Page Inventory (Danh Mục Toàn Bộ Trang & Màn Hình)

| STT | Tên Màn Hình / Trang | Tệp Nguồn (Source File) | Actor Chính | Mục Đích Nghiệp Vụ | Trạng Thái Hiện Tại |
| :---: | :--- | :--- | :--- | :--- | :--- |
| **01** | Public Landing Page | `frontend/index.html`<br>`frontend/src/pages/home/home-view.js` | Guest / All | Giới thiệu nền tảng, Scrollytelling dinh thự án mạng, kịch bản hot, định vị Google Maps quán. | Hoạt động (SPA); Parallax 320vh quá dài gây mỏi tay trên mobile. |
| **02** | Kho Kịch Bản (Catalog) | `frontend/src/pages/games/games-view.js` | Guest, Player | Tìm kiếm 42 kịch bản, bộ lọc 5 tiêu chí (người, thể loại, độ khó, tuổi), xem bảng giá giờ chơi. | Hoạt động (SPA); Bộ lọc chiếm nhiều diện tích; thiếu debounce tìm kiếm. |
| **03** | Chi Nhánh & Phòng | `frontend/src/pages/venues/venues-view.js` | Guest, Player | Xem thông tin chi nhánh (36/52 Nguyễn Gia Trí), danh sách phòng chơi vật lý tại quán. | Hoạt động (SPA); Gọi API `/venues` tốt. |
| **04** | Sảnh Ghép Phòng Chơi | `frontend/src/pages/rooms/rooms-view.js` | Player, Host | Xem phòng đang tuyển người, slot thế chỗ khẩn cấp, tạo phòng mới, nộp cọc 50k, QR Check-in. | Hoạt động (SPA); File quá lớn (1.152 dòng), nhiều inline styles. |
| **05** | Cổng Người Chơi | `frontend/player.html` | Player | Hồ sơ cá nhân, gửi thẻ HSSV (-20k), danh sách vé cọc cá nhân, lịch sử chơi. | Hoạt động (MPA); Trùng lặp chức năng sảnh với `rooms-view.js`. |
| **06** | Bàn Trực Quản Trò (SPA) | `frontend/src/pages/dm/dm-view.js` | DM | Nhận ca trực, từ chối ca kèm lý do, điểm danh người chơi, bắt đầu/kết thúc buổi chơi. | Hoạt động (SPA); Thiếu đồng hồ đếm ngược phiên và checklist đạo cụ. |
| **07** | Cổng Quản Trò (MPA) | `frontend/dm.html` | DM | Giao diện toàn màn hình cho DM ca trực và bàn làm việc briefing desk. | Hoạt động (MPA); Trùng lặp 90% logic với `dm-view.js`. |
| **08** | Console Quản Trị (SPA) | `frontend/src/pages/admin/admin-view.js` | Admin | 9 phân hệ: KPI Doanh thu, Sổ cái thanh toán, User & HSSV, Hủy phòng 24h, Duyệt cọc, Kịch bản, Audit. | Hoạt động (SPA); Quá tải (84.8 KB), dùng `alert()` và `prompt()`. |
| **09** | Console Quản Trị (MPA) | `frontend/admin.html` | Admin | Trang quản trị dạng 2 cột Sidebar cố định + Bảng dữ liệu. | Hoạt động (MPA); 1.405 dòng lặp lại hoàn toàn `admin-view.js`. |
| **10** | Modal Xác Thực & Phân Quyền | `frontend/src/pages/auth/auth-modal.js` | Guest / All | Đăng nhập/đăng ký cho 3 vai trò (Player, DM, Admin), demo auto-fill 1-click. | Hoạt động (Dialog); Thiếu ẩn/hiện mật khẩu, modal cao dễ tràn mobile. |
| **11** | Trung Tâm Thông Báo | `frontend/src/pages/notifications/notifications-view.js` | Player, DM, Admin | Nhận thông báo phòng, xác nhận cọc, biểu quyết giờ chơi theo thời gian thực. | Hoạt động (SPA); Giao diện danh sách cơ bản. |

---

## 3. Shared Component Inventory (Danh Mục Thành Phần Dùng Chung)

| Tên Thành Phần | Vị Trí Định Nghĩa Hiện Tại | Trách Nhiệm UI | Vấn Đề Ghi Nhận |
| :--- | :--- | :--- | :--- |
| **Site Header / Navbar** | `frontend/index.html:28-112`<br>`src/utils/shared-header.js:21-95` | Điều hướng chính, logo, chuông thông báo, tài khoản đăng nhập/đăng xuất. | **LỆCH PHA:** Menu trên `index.html` hiển thị khác hoàn toàn so với menu trên `shared-header.js` của các trang con. |
| **Auth Modal** | `src/pages/auth/auth-modal.js` | Đăng nhập, đăng ký, chuyển vai trò. | Thiếu `aria-label` nút đóng; thiếu toggle password; chiều cao cố định dễ tràn mobile. |
| **Toast Notifications** | `src/utils/toast.js` | Hiển thị thông báo nổi (Success, Error, Info, Warning). | Hoạt động tốt, an toàn XSS (dùng `textContent`), cần tinh chỉnh hiệu ứng xuất hiện. |
| **Buttons (.btn)** | `src/styles/components.css`<br>`src/styles/base.css` | Nút bấm hành động chính, phụ, nguy hiểm. | Xung đột màu sắc (lúc `.btn-primary` màu xanh, lúc `.btn-gold` màu vàng, lúc inline style đỏ). |
| **Cards (.card)** | `src/styles/components.css`<br>`src/styles/portal-themes.css` | Khung bao bọc nội dung, dashboard metrics. | Đa dạng kích thước bo góc (4px đến 16px), màu nền đối nghịch giữa các trang. |
| **Data Tables** | `admin.html`<br>`admin-view.js` | Hiển thị danh sách người dùng, thanh toán, ca trực, kiểm toán. | Chưa có responsive card-view trên mobile; thiếu sticky header khi cuộn dài. |
| **Dialogs / Modals** | Nằm rải rác trong từng view | Hủy phòng khẩn cấp, từ chối ca, bảng giá, xác nhận 18+. | Một số dialog chưa có phím Esc để đóng; thiếu bẫy focus (focus trap). |
| **Status Badges** | Rải rác trong các view | Thể hiện trạng thái phòng, cọc, ca trực. | Dùng chuỗi tiếng Anh kỹ thuật (`FORMING`, `PENDING_DEPOSIT`); thiếu ánh xạ màu đồng nhất. |

---

## 4. Design System Gap Analysis (Phân Tích Khoảng Cách Thiết Kế)

| Tiêu Chí Hệ Thống | Hiện Trạng Thực Tế Trong Mã Nguồn | Mục Tiêu Chuẩn Hóa "Cinematic Modern Hybrid" | Khoảng Cách Cần Lấp Đầy |
| :--- | :--- | :--- | :--- |
| **1. Color System** | Tranh chấp giữa 3 hệ màu: Tone vàng cổ điển (`#D4AF37`), Tone xanh SaaS (`#3B82F6`), Tone GitHub Dark (`#0D1117`). | Nền Void Black (`#0B0C10`), Surface Dark (`#13151D`), Accent Vàng kim (`#D4AF37`), Crimson (`#C43737`), Modern Action Blue (`#3B82F6`), Success Emerald (`#10B981`). | Hợp nhất 100% về `design-tokens.css` duy nhất; loại bỏ mã màu hardcode. |
| **2. Typography System** | `Playfair Display` bị gắn bừa bãi vào tiêu đề bảng quản trị kỹ thuật; kích thước font nhảy lộn xộn. | Phân tầng rõ rệt: `Playfair Display` chỉ dành cho Brand Headings & Script Titles; `Be Vietnam Pro` cho toàn bộ UI Dashboard; `JetBrains Mono` cho mã phòng, giá vé, countdown. | Thiết lập Type Scale chuẩn từ 12px đến 36px với `line-height` và `letter-spacing` quy chuẩn. |
| **3. Spacing System** | Spacing tùy tiện (inline `padding: 18px`, `22px`, `14px`, `7px`). | Hệ thống Spacing 4px/8px lưới chuẩn: 4, 8, 12, 16, 20, 24, 32, 48px. | Thay thế toàn bộ spacing inline bằng CSS utility classes hoặc biến tokens. |
| **4. Navigation System** | Menu desktop bị lệch cấu trúc giữa trang chủ và trang con; mobile drawer đơn sơ. | Thanh Navbar đồng nhất 100% tự co giãn theo quyền người dùng; bổ sung **Bottom Navigation** tiện lợi cho Player trên mobile. | Chuẩn hóa một `AppHeader` và `MobileBottomNav` dùng chung cho toàn bộ hệ thống. |
| **5. Data Display System** | Bảng 9 cột bị tràn ngang trên mobile; xem chi tiết hóa đơn bằng lệnh `window.alert()`. | Bảng chuyển đổi thành **Responsive Card List** trên màn hình nhỏ; xem chi tiết bằng **Slide-over Drawer** chuyên nghiệp. | Xây dựng Slide-over Drawer Component; loại bỏ hoàn toàn `alert()` và `prompt()`. |
| **6. Feedback System** | Hiển thị mã lỗi tiếng Anh; trạng thái loading chỉ là text "Đang nạp...". | Bổ sung Skeleton Loading; trạng thái Empty State có hình minh họa và nút hành động rõ ràng. | Chuẩn hóa Empty / Error / Loading States với icon và nút điều hướng khắc phục. |

---

## 5. Duplicate Code Analysis (Phân Tích Trùng Lặp Mã Nguồn)

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                       TRÙNG LẶP MÃ NGUỒN NGHIÊM TRỌNG                       │
├───────────────────────────────┬───────────────────────────────┬─────────────┤
│ Tệp Thứ Nhất                  │ Tệp Thứ Hai                   │ Tỷ Lệ Lặp   │
├───────────────────────────────┼───────────────────────────────┼─────────────┤
│ frontend/admin.html (1.405 dòng)│ frontend/src/pages/admin/     │ ~90%        │
│                               │ admin-view.js (1.487 dòng)    │             │
│   -> Cả hai đều tự viết template HTML, tự render bảng người dùng, tự xử lý  │
│      hủy phòng, tự quản lý hoàn cọc, tự gọi API thanh toán và audit log.    │
├───────────────────────────────┼───────────────────────────────┼─────────────┤
│ frontend/dm.html (459 dòng)   │ frontend/src/pages/dm/        │ ~85%        │
│                               │ dm-view.js (430 dòng)         │             │
│   -> Cả hai đều tự render danh sách phân công, form từ chối, điểm danh.     │
├───────────────────────────────┼───────────────────────────────┼─────────────┤
│ frontend/player.html (648 dòng)│ frontend/src/pages/rooms/     │ ~65%        │
│                               │ rooms-view.js (1.152 dòng)    │             │
│   -> Trùng lặp phần render sảnh phòng chơi và lọc trạng thái phòng.         │
├───────────────────────────────┼───────────────────────────────┼─────────────┤
│ frontend/src/styles/tokens.css│ frontend/src/styles/          │ ~80%        │
│ (40 dòng)                     │ variables.css (50 dòng)       │             │
│   -> Cả hai đều khai báo cùng các biến :root và màu sắc thương hiệu.        │
└───────────────────────────────┴───────────────────────────────┴─────────────┘
```

**Giải pháp loại bỏ trùng lặp:**  
Chuyển đổi các tệp standalone MPA (`admin.html`, `dm.html`, `player.html`) thành các trang vỏ mỏng (Thin Shell Containers). Các trang này sẽ nạp trực tiếp chính các Feature Component chuẩn hóa từ `src/pages/*`, chấm dứt hoàn toàn tình trạng phải bảo trì hai bộ mã nguồn song song.

---

## 6. Implementation Roadmap (Lộ Trình Triển Khai)

Lộ trình được sắp xếp nghiêm ngặt theo mức độ ưu tiên nghiệp vụ và tính độc lập kỹ thuật, đảm bảo không có bước nào gây lỗi cho các bài test tự động hiện hữu:

```
[GIAI ĐOẠN 0: NỀN TẢNG THIẾT KẾ & KIẾN TRÚC - P0]
  ├── 1. Hợp nhất tokens.css, variables.css thành core design tokens.
  ├── 2. Chuẩn hóa Navigation & Header dùng chung trên toàn hệ thống.
  └── 3. Xây dựng Drawer & Custom Dialog Components thay thế alert/prompt.

[GIAI ĐOẠN 1: TÁI THIẾT KẾ PLAYER PORTAL & BOOKING FLOW - P1] (ƯU TIÊN HÀNG ĐẦU)
  ├── 1. Tái cấu trúc RoomsView: Phân tách rõ Sảnh ghép và Chi tiết phòng.
  ├── 2. Thiết kế Step Indicator quy trình: Gom người -> Chốt giờ -> Nộp cọc -> QR Vé.
  ├── 3. Nổi bật đồng hồ đếm ngược giữ chỗ 4 giờ và khoản cọc 50.000đ.
  ├── 4. Bổ sung Bottom Navigation cho Player trên mobile.
  └── 5. Đồng bộ hóa với player.html để loại bỏ trùng lặp.

[GIAI ĐOẠN 2: TÁI THIẾT KẾ ADMIN ENTERPRISE CONSOLE - P2]
  ├── 1. Chuẩn hóa Layout 2 cột Admin Sidebar + Main Area chuyên nghiệp.
  ├── 2. Thay thế window.alert() bằng Slide-over Drawer xem chi tiết hóa đơn.
  ├── 3. Thay thế window.prompt() bằng Modal xác nhận từ chối thẻ HSSV.
  ├── 4. Chuyển đổi Data Tables sang Responsive Card List trên thiết bị di động.
  └── 5. Biến admin.html thành thin container nạp AdminView.

[GIAI ĐOẠN 3: TÁI THIẾT KẾ DM OPERATIONS DASHBOARD - P3]
  ├── 1. Thiết kế Bàn trực Quản trò (Tactical Desk) rõ ràng, trực quan.
  ├── 2. Tinh chỉnh nút Nhận/Từ chối ca (ưu tiên thị giác rõ rệt, tránh bấm nhầm).
  ├── 3. Bổ sung đồng hồ đếm ngược phiên làm việc (Session Timer) và Live Banner.
  └── 4. Hợp nhất dm.html với DmView.

[GIAI ĐOẠN 4: TỐI ƯU PUBLIC PORTAL & KHO KỊCH BẢN - P4]
  ├── 1. Tinh gọn chiều cao Scrollytelling Parallax (giảm jank cuộn trang).
  ├── 2. Bổ sung Floating CTA "Đặt phòng nhanh" và nút cuộn thẳng xuống nội dung.
  ├── 3. Tối ưu bộ lọc Kho kịch bản và bổ sung debounce cho ô tìm kiếm.
  └── 4. Chuẩn hóa thẻ hiển thị kịch bản (Script Cards).

[GIAI ĐOẠN 5: RESPONSIVE, ACCESSIBILITY & CHẠY TOÀN BỘ TEST SUITES - P5]
  ├── 1. Rà soát tỷ lệ tương phản màu WCAG AA và touch target $\ge 44\times 44$px.
  ├── 2. Kiểm tra không có thanh cuộn ngang (horizontal scroll) trên mọi breakpoint.
  └── 3. Chạy toàn bộ 64 bài test Node.js và 226 bài test PHPUnit (100% PASS).
```

---

## 7. Risk Assessment & Mitigation (Đánh Giá Rủi Ro & Biện Pháp Phòng Ngừa)

| Rủi Ro Nhận Diện | Mức Độ | Tác Động Tiềm Tàng | Biện Pháp Phòng Ngừa & Giảm Thiểu |
| :--- | :---: | :--- | :--- |
| **Gãy vỡ bộ kiểm thử tự động (Test Breakage)** | **CAO** | Các bài test tự động (Node.js & CDP browser) phụ thuộc vào DOM ID và class cụ thể (`#btn-open-login`, `#btn-accept-*`, `.role-tab-btn`, v.v.). | **BẮT BUỘC BẢO TOÀN 100% ID và DATA-ATTRIBUTES**. Không xóa hoặc đổi tên các ID mà test đang query. |
| **Mất phiên đăng nhập khi chuyển trang** | **TRUNG BÌNH** | Chuyển đổi giữa hash link (`#rooms`) và URL tĩnh (`/player.html`) có thể làm lệch trạng thái auth nếu storage không đồng bộ. | Đảm bảo `authState.js` luôn đồng bộ session qua API `/auth/me` và `localStorage` trên mọi trang. |
| **Xung đột CSS Cascade khi dọn dẹp** | **TRUNG BÌNH** | Xóa bỏ inline styles có thể làm vỡ layout nếu CSS class mới có độ ưu tiên thấp hơn. | Định nghĩa CSS có namespace rõ ràng (`.player-*`, `.dm-*`, `.admin-*`), kiểm tra giao diện thực tế bằng headless Chrome sau mỗi bước. |
| **Regression nghiệp vụ cọc và hoàn tiền** | **CỰC CAO** | Hiển thị sai quy tắc 24h hoặc tính sai tiền cọc 50.000đ sẽ gây tổn hại tài chính thực tế của khách hàng. | Giữ nguyên 100% công thức tính toán và API payloads; chỉ thay đổi lớp hiển thị (Presentation Layer). |

---

*Hồ sơ phân tích hiện trạng đã hoàn tất. Dự án sẵn sàng bước vào Giai đoạn 0: Thiết lập Tầng Thiết Kế Dùng Chung (P0).*
