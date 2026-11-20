```markdown
# 📜 QUY ĐỊNH LÀM VIỆC NHÓM & QUY TRÌNH DUYỆT CODE

Để tránh xung đột code (`Merge Conflict`), đảm bảo toàn bộ 23 màn hình đồng nhất về giao diện lẫn nghiệp vụ, và sẵn sàng tích hợp mượt mà với Backend PHP, tất cả thành viên bắt buộc tuân thủ **5 quy tắc sau**:

---

## 1. Quy Tắc Phân Nhánh Git (Branching)
* **KHÔNG BAO GIỜ** code hoặc push trực tiếp lên nhánh `main`. Nhánh `main` đã được khóa bảo vệ.
* Mỗi thành viên chỉ làm việc trên đúng nhánh cá nhân của mình (chuyển nhánh bằng lệnh `git checkout <ten-nhanh>`):
  * `@yhuynh06` (Lead): `feat/ui-guest`
  * `@lechau06` (Lệ Châu): `feat/player-booking`
  * `@devnu` (Như): `feat/dm-admin`

---

## 2. Quy Tắc File Khóa Chung & Tích Hợp Backend
Các file sau là nền tảng dùng chung cho toàn website, do Lead (`@yhuynh06`) quản lý:
* `src/styles/tokens.css` (Bảng màu `#0D0D0D`, `#F5F2EB`, `#992B2B`, `#D4AF37` và Font chữ)
* `src/styles/components.css` (Header, Footer, Tấm vé răng cưa `.ticket-card`, Nút bấm, Modal)
* `src/api/api-client.js` (Lớp cầu nối dữ liệu dùng chung để sẵn sàng tích hợp PHP Backend)
* `src/data/scripts.data.js` (Danh sách 30 kịch bản thật của KBS Hồ Chí Minh)
* `src/logic/store.js` (Cấu trúc lưu trữ dữ liệu dùng chung giữa 4 vai trò)

👉 **Quy định bắt buộc:**
1. Thành viên 2 và 3 chỉ được `import` và sử dụng các file trên, **không tự ý chỉnh sửa nội dung bên trong**. Nếu cần bổ sung class CSS hoặc trường dữ liệu mới, nhắn lên nhóm để Lead cập nhật đồng bộ.
2. **Luôn dùng đường dẫn tương đối (`./` hoặc `../`)**: Ví dụ trong `pages/` viết `src="../src/assets/images/logo.png"`, **tuyệt đối không** dùng dấu `/` ở đầu đường dẫn để không bị lỗi khi ghép vào thư mục `frontend/` của Backend PHP.

---

## 3. Quy Tắc Đặt Tên CSS & Phạm Vi File
* Chỉ viết CSS trong file được phân công (`home.css`, `player.css`, `portals.css`).
* Bắt buộc đặt tiền tố theo cụm trang để không đè style lẫn nhau:
  * Trang chủ & Kho kịch bản (`@yhuynh06`): `.home-...`, `.catalog-...`
  * Luồng người chơi & Vé (`@lechau06`): `.booking-...`, `.room-...`, `.profile-...`
  * Cổng DM & Admin (`@devnu`): `.dm-...`, `.admin-...`

---

## 4. Quy Tắc Nghiệp Vụ Cốt Lõi (Bắt Buộc Đúng 100%)
1. **Tiền cọc & Giữ chỗ:** Cọc cố định **50.000đ/người**. Tạo/tham gia phòng sẽ giữ chỗ trong **4 giờ** (đếm ngược). Mã QR check-in **chỉ hiển thị** sau khi đã xác nhận thanh toán cọc.
2. **Hoàn cọc 24 giờ:** Hủy trước giờ chơi **> 24 giờ** được hoàn **100% cọc (50.000đ)**; hủy **< 24 giờ** bị mất cọc và hiển thị popup cảnh báo.
3. **Ưu đãi & Giảm giá:**
   * **Thứ 3 Bí Ẩn:** Tự động giảm **30%** giá vé khi chọn lịch chơi rơi vào Thứ 3.
   * **Ưu đãi HSSV:** Giảm **20.000đ/người** áp dụng từ **Thứ 2 đến Thứ 5 (khung giờ 09:00 – 19:59)** khi tài khoản đã được Admin duyệt thẻ HSSV.
4. **Giới hạn độ tuổi:** Các kịch bản gắn nhãn `18+` bắt buộc bật Popup xác nhận đủ 18 tuổi trước khi cho xem chi tiết hoặc đặt phòng.

---

## 5. Quy Trình Lấy Code & Gửi Yêu Cầu Duyệt (Pull Request)
1. **Trước khi bắt đầu code mỗi ngày** (kéo bản cập nhật mới nhất từ `main` về nhánh của mình):
   ```bash
   git pull origin main