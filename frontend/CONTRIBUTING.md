# 📜 QUY ĐỊNH LÀM VIỆC NHÓM & QUY TRÌNH DUYỆT CODE

Để tránh xung đột code (`Merge Conflict`), đảm bảo toàn bộ màn hình đồng nhất về giao diện lẫn nghiệp vụ, và sẵn sàng tích hợp mượt mà với Backend PHP & MySQL (22 bảng), tất cả thành viên bắt buộc tuân thủ **5 quy tắc sau**:

---

## 1. Quy Tắc Phân Nhánh Git (Branching)
* **KHÔNG BAO GIỜ** code hoặc push trực tiếp lên nhánh `main`. Nhánh `main` đã được khóa bảo vệ.
* Mỗi thành viên chỉ làm việc trên đúng nhánh cá nhân của mình (chuyển nhánh bằng lệnh `git checkout <ten-nhanh>`):
  * `@yhuynh06` (Lead — Nền tảng & Guest): `feat/foundation-guest`
  * `@devnu` (Như — Giao diện Người chơi): `feat/player-portal`
  * `@lechau06` (Lệ Châu — Giao diện Admin & DM): `feat/admin-portal` và `feat/dm-portal`

---

## 2. Quy Tắc File Khóa Chung & Tích Hợp Backend
Các file sau là nền tảng dùng chung cho toàn website, do Lead (`@yhuynh06`) quản lý:
* `src/styles/tokens.css` (Bảng màu `#0D0D0D`, `#F5F2EB`, `#992B2B`, `#D4AF37` và Font chữ)
* `src/styles/components.css` (Header, Footer, Tấm vé răng cưa `.ticket-card`, Nút bấm, Modal, Badge)
* `src/logic/layout.js` (Tự động render Header 4 vai trò, Chuông thông báo & Footer 4 cột kèm bản đồ)
* `src/logic/guard.js`, `src/logic/store.js`, `src/logic/utils.js` (Phân quyền, LocalStorage dùng chung, hàm định dạng)
* `src/api/api-client.js` (Lớp cầu nối dữ liệu dùng chung để sẵn sàng tích hợp PHP Backend, gắn `Idempotency-Key`)
* `src/data/scripts.data.js` (Danh sách 42 kịch bản thật của KBS Hồ Chí Minh)

👉 **Quy định bắt buộc:**
1. Thành viên 2 và 3 chỉ được `import` và sử dụng các file trên, **không tự ý chỉnh sửa nội dung bên trong**. Nếu cần bổ sung class CSS hoặc trường dữ liệu mới, nhắn lên nhóm để Lead cập nhật đồng bộ.
2. **Dữ liệu giả (`src/data/mock-db.js`):** Là file dùng chung nên mỗi người chỉ thêm dữ liệu vào đúng vùng comment của mình (`// === PLAYER ===`, `// === DM ===`, `// === ADMIN ===`), không sửa vùng của người khác.
3. **Luôn dùng đường dẫn tương đối (`./` hoặc `../`)**: Ví dụ trong `pages/` viết `src="../src/assets/images/logo.png"`, **tuyệt đối không** dùng dấu `/` ở đầu đường dẫn để không bị lỗi khi ghép vào thư mục `frontend/` của Backend PHP.
4. **Chống bấm đúp giao dịch (`idempotency_records`):** Khi bấm các nút *Tạo phòng*, *Xác nhận thanh toán cọc*, *Duyệt hoàn tiền* hoặc *Xác nhận thu tiền*, bắt buộc vô hiệu hóa nút ngay lập tức (`disabled = true`) và đính kèm mã `Idempotency-Key` (UUID) trong request gửi API.

---

## 3. Quy Tắc Đặt Tên CSS & Phạm Vi File
* Chỉ viết CSS trong file được phân công (`home.css`, `player.css`, `portals.css`).
* Bắt buộc đặt tiền tố theo cụm trang để không đè style lẫn nhau:
  * Trang chủ & Kho kịch bản (`@yhuynh06`): `.home-...`, `.catalog-...`
  * Luồng người chơi & Vé (`@devnu`): `.booking-...`, `.room-...`, `.profile-...`
  * Cổng DM & Admin (`@lechau06`): `.dm-...`, `.admin-...`

---

## 4. Quy Tắc Nghiệp Vụ Cốt Lõi (Bắt Buộc Đúng 100%)
1. **Tiền cọc, Giữ chỗ 4h & 2 Giai đoạn thanh toán:** Cọc cố định **50.000đ/người** (`DEPOSIT`). Tạo/tham gia phòng sẽ giữ chỗ trong **4 giờ** (đếm ngược `deposit_deadline_at`). Mã QR check-in **chỉ hiển thị** sau khi đã xác nhận thanh toán cọc; phần tiền vé còn lại (`REMAINING`) hiển thị rõ trên vé và được Quản trò (DM) xác nhận thu tại quán khi check-in.
2. **Hoàn cọc 24 giờ:** Hủy trước giờ chơi **> 24 giờ** được gửi yêu cầu hoàn **100% cọc (50.000đ)** để Admin duyệt; hủy **< 24 giờ** bị mất cọc và bắt buộc hiển thị Popup cảnh báo xác nhận trước khi hủy.
3. **Ưu đãi & Giảm giá (KHÔNG CỘNG DỒN):**
   * **Thứ 3 Bí Ẩn:** Tự động giảm **30%** giá vé khi chọn lịch chơi rơi vào Thứ 3.
   * **Ưu đãi HSSV:** Giảm **20.000đ/người** áp dụng từ **Thứ 2 đến Thứ 5 (khung giờ 09:00 – 19:59)** khi tài khoản đã được Admin duyệt thẻ HSSV.
   * **Quy tắc bắt buộc:** Tuyệt đối **không cộng dồn** hai chương trình trên; hệ thống tự động chọn 1 mức giảm có lợi nhất cho người chơi tại một thời điểm.
4. **Đề xuất & Biểu quyết lịch chơi:** Trong `room-detail.html`, các thành viên có thể đề xuất khung giờ và bấm Đồng ý / Từ chối trước khi chốt thành phiên chơi chính thức (`sessions`).
5. **Giới hạn độ tuổi:** Các kịch bản gắn nhãn `18+` bắt buộc bật Modal xác nhận đủ 18 tuổi trước khi cho xem chi tiết hoặc đặt phòng.

---

## 5. Quy Trình Lấy Code & Gửi Yêu Cầu Duyệt (Pull Request)
1. **Trước khi bắt đầu code mỗi ngày** (kéo bản cập nhật mới nhất từ `main` về nhánh của mình):
   ```bash
   git pull origin main