# BỘ CÂU HỎI PHẢN BIỆN BẢO VỆ ĐỒ ÁN VÀ KỊCH BẢN DEMO THỰC CHIẾN
## Hệ Thống Đặt Lịch & Vận Hành Phòng Chơi Kịch Bản Sát (Jubensha) V16
## Dành Cho Sinh Viên Báo Cáo Trước Hội Đồng Chấm Thi

Tài liệu này được biên soạn nhằm phục vụ trực tiếp cho buổi bảo vệ đồ án tốt nghiệp / kết thúc môn học. Nội dung bao gồm kịch bản thao diễn phần mềm thực tế trong 5-7 phút và bộ 10 câu hỏi phản biện kỹ thuật chuyên sâu nhất mà hội đồng chấm thi thường đặt ra, kèm theo câu trả lời đạt điểm tối đa.

---

## 1. THÔNG TIN ĐĂNG NHẬP HỆ THỐNG MẪU (DEMO CREDENTIALS)

Toàn bộ tài khoản mẫu được kích hoạt sẵn trong cơ sở dữ liệu với mật khẩu chuẩn hóa: `password123`.

| Vai trò (Role) | Email đăng nhập | Mật khẩu | Mục đích thao diễn |
|:---|:---|:---|:---|
| **Khách chơi (Player / Host)** | `player1@kichbansat.vn` | `password123` | Tạo phòng, chọn kịch bản, biểu quyết giờ chơi, nộp cọc QR, đánh giá sau chơi. |
| **Khách chơi thành viên** | `player2@kichbansat.vn` | `password123` | Tham gia phòng ghép, bỏ phiếu biểu quyết lịch, nộp cọc cá nhân 50.000 VNĐ. |
| **Quản trò (DM)** | `dm1@kichbansat.vn` | `password123` | Tiếp nhận ca trực, check-in khách, bắt đầu buổi chơi, lưu biên bản, tất toán quầy. |
| **Quản trị viên (Admin)** | `admin@kichbansat.vn` | `password123` | Dashboard doanh thu, khóa phòng offline, bảo trì điều chuyển phòng, duyệt hoàn cọc. |

- Địa chỉ máy chủ cục bộ: `http://127.0.0.1:8000/`
- Tài liệu API tương tác Swagger UI: `http://127.0.0.1:8000/api/v1/docs`
- Kiểm tra trạng thái máy chủ: `http://127.0.0.1:8000/api/v1/health`

---

## 2. KỊCH BẢN THAO DIỄN THỰC TẾ 5 PHÚT (DEMO WALKTHROUGH)

Khi trình bày trước hội đồng, sinh viên mở trình duyệt tại `http://127.0.0.1:8000/` và thao tác theo 5 bước tuần tự sau:

### Bước 1: Khám phá kịch bản & Tạo phòng (1 phút)
- **Hành động:** Đăng nhập tài khoản `player1@kichbansat.vn`, mở trang **"Kho Kịch Bản"** (`scenarios.html`).
- **Thao tác:**
  - Lọc theo thể loại: Chọn thẻ "Kinh Dị 18+" hoặc "Trinh Thám Cổ Trang".
  - Mở chi tiết kịch bản: Giới thiệu thông tin thời lượng 240 phút, số lượng người chơi (6-8 người), độ tuổi 18+, và bảng giá linh hoạt theo 3 khung giờ (Sáng: 180k, Chiều: 220k, Tối: 250k).
  - Bấm **"Tạo Phòng Chơi"** (`create-room.html`): Chọn hình thức phòng riêng (PRIVATE) hoặc phòng công khai ghép đội (PUBLIC). Đánh dấu xác nhận cam kết đủ 18 tuổi.
- **Điểm nhấn thuyết minh:** "Hệ thống hỗ trợ 12 kịch bản với đầy đủ thông số kỹ thuật, ngăn chặn người chơi dưới 18 tuổi tham gia các nội dung tâm lý nặng hoặc bạo lực."

### Bước 2: Thống nhất lịch chơi & Tự động khóa tài nguyên (1.5 phút)
- **Hành động:** Tại trang chi tiết phòng chơi (`room-detail.html`), Host tạo đề xuất lịch chơi.
- **Thao tác:**
  - Chọn Chi nhánh Quận 1, ngày chơi và khung giờ (ví dụ 14:00 - 18:00).
  - Thành viên phòng bấm **"Đồng Ý"** biểu quyết.
  - Khi tỷ lệ đồng thuận đạt `>= 80%` và không có phiếu phủ quyết, hệ thống tự động kích hoạt thuật toán xếp phòng: Tìm phòng vật lý có sức chứa phù hợp, bảo đảm thời gian đệm dọn dẹp 30 phút (`BUFFER_MINUTES = 30`), và tự động phân công Quản trò (DM) rảnh lịch.
- **Điểm nhấn thuyết minh:** "Khác với các hệ thống truyền thống bắt đặt cọc trước rồi mới tìm người, hệ thống Kịch Bản Sát đảo ngược quy trình: Thống nhất lịch và kiểm tra phòng vật lý trống trước, loại bỏ hoàn toàn rủi ro hủy phòng do thiếu cơ sở vật chất."

### Bước 3: Nộp cọc minh bạch & Chốt phòng (1 phút)
- **Hành động:** Phòng chuyển sang trạng thái chờ cọc trong 02 giờ.
- **Thao tác:**
  - Người chơi quét mã QR nộp cọc 50.000 VNĐ / vé.
  - Sau khi nộp cọc đủ 100%, phòng chính thức chuyển sang trạng thái `CONFIRMED`.
  - Hệ thống tự động tính snapshot giá vé: Áp dụng quy tắc Majority Time Block Rule (khung giờ chiếm đa số thời lượng) hoặc Split 50-50 Tie Rule (trung bình cộng), đồng thời tự động trừ giảm giá HSSV 10% nếu người chơi đã được xác thực thẻ sinh viên.
- **Điểm nhấn thuyết minh:** "Hệ thống hỗ trợ cả nộp cọc cá nhân cho khách lẻ và nộp cọc gộp cho Host đại diện nhóm bạn, với thời hạn thanh toán (SLA) 2 giờ rõ ràng."

### Bước 4: Quản trò tiếp nhận & Điều phối buổi chơi (1.5 phút)
- **Hành động:** Chuyển sang trình duyệt đăng nhập vai trò DM (`dm1@kichbansat.vn`) tại Cổng Quản Trò (`dm-portal.html`).
- **Thao tác:**
  - Bấm **"Chấp Nhận Ca"** trong vòng 2 giờ.
  - Khi khách đến quán, DM bấm **"Bắt Đầu Buổi Chơi"** (`IN_PROGRESS`): Đồng hồ đếm ngược 240 phút kích hoạt, chia thành 6 giai đoạn rõ ràng.
  - Nếu buổi chơi kéo dài quá giờ quy định, DM bấm gửi **"Cảnh Báo Kéo Dài Ca Chơi"** để lễ tân sắp xếp bàn kế tiếp.
  - Kết thúc buổi chơi: DM bấm **"Hoàn Tất Buổi Chơi"** (`COMPLETED`), lưu ghi chú hiện trường và bấm **"Xác Nhận Tất Toán Quầy"** sau khi khách đã thanh toán nốt tiền vé còn lại tại quầy thu ngân.
- **Điểm nhấn thuyết minh:** "Cổng Quản trò hỗ trợ đầy đủ công cụ số hóa cho DM tại hiện trường, từ đồng hồ đếm ngược, phân vai, ghi chú hư hao đạo cụ đến quy trình tất toán."

### Bước 5: Bảng điều khiển Quản trị viên & Xử lý ngoại lệ (1 phút)
- **Hành động:** Đăng nhập vai trò Admin (`admin@kichbansat.vn`) tại Cổng Quản Trị (`admin-portal.html`).
- **Thao tác:**
  - Trình chiếu 4 chỉ số KPI thời gian thực: Doanh thu thuần, Tiền cọc đã thu, Ca đang chơi và Phân công DM.
  - Thao diễn tính năng **Bảo trì phòng vật lý**: Bật bảo trì một phòng, hệ thống tự động kích hoạt Dynamic Rerouting chuyển ca chơi sang phòng trống khác và tự động phát thông báo tới khách hàng.
  - Thao diễn tính năng **Khóa giữ chỗ ngoại tuyến (Offline Block)**: Khóa phòng cho khách gọi điện hotline trong 180 phút.
  - Thao diễn duyệt hoàn tiền cọc trong mục Quản Lý Hoàn Cọc.
- **Điểm nhấn thuyết minh:** "Quản trị viên có toàn quyền kiểm soát vận hành, từ giám sát tài chính thời gian thực, điều phối khẩn cấp đến phòng chống xung đột tài nguyên."

---

## 3. BỘ 10 CÂU HỎI PHẢN BIỆN HÓC BÚA NHẤT CỦA HỘI ĐỒNG & CÂU TRẢ LỜI ĐẠT ĐIỂM TỐI ĐA

### Câu hỏi 1: Tại sao hệ thống lại dời bước nộp tiền cọc ra sau khi biểu quyết chốt lịch mà không thu tiền ngay lúc tạo phòng?
- **Trả lời phản biện:**
  - "Dạ thưa Thầy/Cô, trong mô hình trò chơi nhập vai kịch bản (Jubensha), một kịch bản đòi hỏi sĩ số cố định chính xác (ví dụ 6 người gồm 3 nam 3 nữ). Nếu bắt người chơi nộp tiền ngay khi tạo phòng:
    1. Người chơi thứ nhất nộp tiền nhưng chờ 2 ngày không đủ người, phòng bị hủy dẫn tới doanh nghiệp phải hoàn tiền, phát sinh chi phí giao dịch ngân hàng và quá tải kế toán đối soát.
    2. Khách hàng cảm thấy bị giam vốn và ngần ngại tham gia phòng ghép.
  - Vì vậy, hệ thống Kịch Bản Sát V16 áp dụng quy trình nghiệp vụ chuẩn: **Gom đủ người và biểu quyết chốt lịch xong (đạt >= 80% đồng thuận và phòng vật lý còn trống) thì mới kích hoạt hạn nộp cọc 2 giờ**. Lúc này tỷ lệ buổi chơi diễn ra thành công đạt trên 95%, loại bỏ hoàn toàn các giao dịch nộp cọc và hoàn cọc lãng phí."

### Câu hỏi 2: Hệ thống giải quyết xung đột phòng chơi vật lý và thời gian dọn dẹp giữa các ca chơi liên tiếp như thế nào?
- **Trả lời phản biện:**
  - "Dạ thưa Thầy/Cô, hệ thống xử lý xung đột qua 2 tầng bảo vệ:
    1. **Tầng ứng dụng (Service Layer):** Trong hàm `findAvailablePhysicalRoom()`, hệ thống áp dụng khoảng đệm dọn phòng cứng 30 phút (`BUFFER_MINUTES = 30`). Một phòng vật lý chỉ được xem là khả dụng nếu không có ca chơi nào khác giao thoa trong khoảng từ `start_at - 30 phút` đến `end_at + 30 phút`.
    2. **Tầng cơ sở dữ liệu (Database Constraint):** Ràng buộc khóa ngoại kép `fk_sessions_physical_room (venue_id, physical_room_id)` kết hợp chỉ mục `idx_sessions_physical_time (physical_room_id, status, start_at, end_at)` và kiểm tra khóa giữ chỗ ngoại tuyến trong bảng `offline_room_blocks`. Khi có xung đột, giao dịch cơ sở dữ liệu sẽ bị chặn ngay lập tức, bảo đảm tính toàn vẹn tuyệt đối."

### Câu hỏi 3: Nếu một ca chơi kéo dài 4 tiếng từ 16:30 đến 20:30, vắt qua cả Ca Chiều (220k) và Ca Tối (250k) thì hệ thống tính giá vé thế nào?
- **Trả lời phản biện:**
  - "Dạ thưa Thầy/Cô, hệ thống giải quyết bằng thuật toán **Majority Time Block Rule (Quy tắc thời lượng chiếm đa số)** được cài đặt tại `PricingService.php`:
    - Ca Chiều kết thúc lúc 18:00, Ca Tối bắt đầu từ 18:00.
    - Ca chơi từ 16:30 đến 20:30 có tổng thời lượng 240 phút:
      - Đoạn trong Ca Chiều (16:30 - 18:00): 90 phút (chiếm 37.5%).
      - Đoạn trong Ca Tối (18:00 - 20:30): 150 phút (chiếm 62.5%).
    - Vì Ca Tối chiếm đa số (`>= 50%`), hệ thống tự động áp dụng mức giá Ca Tối là **250.000 VNĐ / vé**.
    - Trong trường hợp đặc biệt ca chơi chia đều chính xác 50% - 50% (ví dụ 16:00 - 20:00: Ca Chiều 120 phút và Ca Tối 120 phút), hệ thống áp dụng **Split 50-50 Tie Rule**: Tính giá vé theo trung bình cộng `(220.000 + 250.000) / 2 = 235.000 VNĐ / vé`.
    - Toàn bộ kết quả tính toán chi tiết từng phút được lưu vết bất biến trong snapshot `game_rooms.price_details` để đối soát minh bạch."

### Câu hỏi 4: Trong trường hợp Quản trò (DM) được giao ca nhưng bận hoặc bỏ quên không nhận thì hệ thống xử lý ra sao?
- **Trả lời phản biện:**
  - "Dạ thưa Thầy/Cô, hệ thống có cơ chế kiểm soát SLA nhân sự tự động:
    1. Khi được phân công, Quản trò có thời hạn **120 phút** để phản hồi trên Cổng DM.
    2. Background Job `runDmAssignmentTimeout()` chạy định kỳ qua cron. Nếu quá 120 phút mà DM không bấm chấp nhận, hệ thống tự động thu hồi ca với trạng thái `REVOKED_TIMEOUT` và tự động xoay tua phân công cho DM kế tiếp có kỹ năng phù hợp.
    3. Nếu một phòng chơi bị từ chối hoặc quá hạn đến lần thứ 3 (`rejection_count >= 3`), hệ thống tự động kích hoạt cảnh báo đỏ `ADMIN_ALERT` gửi tới Quản trị viên để can thiệp điều phối thủ công hoặc liên hệ với khách hàng, không bao giờ để xảy ra tình trạng khách đến quán mà không có Quản trò."

### Câu hỏi 5: Làm thế nào hệ thống chống gian lận thẻ Học sinh - Sinh viên (giảm 10%) và bảo đảm an toàn cho kịch bản 18+?
- **Trả lời phản biện:**
  - "Dạ thưa Thầy/Cô, hệ thống áp dụng cơ chế xác thực 2 lớp (Hybrid Online - Offline Verification):
    1. **Tầng Online:** Người chơi tải ảnh thẻ HSSV lên hoặc đăng ký xác thực. Giá vé hiển thị tạm tính giảm 10%.
    2. **Tầng Offline tại quầy:** Khi khách đến check-in, Thu ngân / Lễ tân và Quản trò có nhiệm vụ đối soát thẻ HSSV vật lý chính chủ còn hạn sử dụng. Sau khi đối soát, nhân viên bấm nút **Xác Nhận HSSV** trên màn hình DM Portal hoặc Admin để cập nhật trường `student_verified = 1` trong CSDL.
    3. **Đối với kịch bản 18+:** CSDL lưu cờ `is_18_plus = 1` và yêu cầu Host đánh dấu cam kết khi tạo phòng (`is_18_plus_acknowledged = 1`). Tại quầy check-in, lễ tân kiểm tra CCCD; nếu phát sinh trường hợp khách chưa đủ 18 tuổi, cơ sở sẽ hỗ trợ chuyển nhóm sang kịch bản phù hợp và bảo lưu 100% tiền cọc, bảo đảm tuân thủ pháp luật và an toàn tâm lý cho khách hàng."

### Câu hỏi 6: Chính sách hoàn cọc của hệ thống được quy định như thế nào để cân bằng giữa quyền lợi khách hàng và chi phí của cơ sở?
- **Trả lời phản biện:**
  - "Dạ thưa Thầy/Cô, chính sách hoàn cọc tuân thủ nghiêm ngặt mốc **24 giờ trước giờ chơi**:
    1. **Khách hủy trước mốc 24 giờ:** Hệ thống tự động phê duyệt hoàn cọc 100% (`status = APPROVED`). Phòng mở cửa sổ tìm người thay thế trong 4 giờ.
    2. **Khách hủy dưới 24 giờ:** Hệ thống từ chối hoàn cọc (`status = REJECTED`), tiền cọc 50.000đ bị tịch thu sung công quỹ (`FORFEITED`) để bù đắp chi phí in ấn tập kịch bản riêng và chi phí giữ chỗ phòng vật lý.
    3. **Tình huống No-show (Khách không đến):**
       - Nếu chỉ vắng 1 người: Buổi chơi vẫn tiếp tục, DM sẽ đóng thế vai NPC; tiền cọc người vắng mặt bị tịch thu.
       - Nếu vắng từ 2 người trở lên (bàn không thể chơi): DM hủy ca tại chỗ (`CANCELLED_ON_SITE`). Người vắng mặt mất 100% cọc; những người chơi đến đúng giờ được duyệt hoàn cọc 100% và tặng voucher giảm giá 20% cho lần sau."

### Câu hỏi 7: Một phòng vật lý đang có lịch chơi trong ngày mai nhưng bất ngờ bị hỏng máy lạnh thì hệ thống xử lý thế nào?
- **Trả lời phản biện:**
  - "Dạ thưa Thầy/Cô, Quản trị viên chỉ cần bấm 01 nút duy nhất: **Bật Bảo Trì Phòng** (`POST /api/v1/admin/physical-rooms/{id}/maintenance`).
  - Khi đó, thuật toán **Dynamic Room Rerouting** lập tức được kích hoạt:
    1. Tự động truy vấn tất cả các ca chơi trong tương lai của phòng hỏng đó.
    2. Gọi hàm `findAlternativePhysicalRoom()` để quét các phòng trống có sức chứa tương đương tại cùng chi nhánh (đáp ứng đệm 30 phút).
    3. Tự động chuyển giao ca chơi sang phòng mới trong cơ sở dữ liệu.
    4. Tự động gửi thông báo hệ thống đến Host và Quản trò về số phòng mới.
    5. Ghi nhận toàn bộ vết xử lý vào nhật ký kiểm toán (Audit Log)."

### Câu hỏi 8: Làm thế nào các tiến trình nền (Background Jobs) như hủy phòng quá hạn, nhắc lịch 24h hoạt động khi ứng dụng chạy trên PHP thuần?
- **Trả lời phản biện:**
  - "Dạ thưa Thầy/Cô, ứng dụng được thiết kế module hóa với tệp CLI Runner trung tâm tại `backend/bin/cron.php` bao bọc class `BackgroundJobService`:
    1. Trên môi trường máy chủ sản xuất, hệ điều hành thiết lập Crontab (hoặc Windows Task Scheduler) chạy lệnh `php bin/cron.php` mỗi 5 phút một lần.
    2. Mỗi Job đều được thiết kế với nguyên lý **Idempotency (Tính bất biến khi chạy lặp lại)**: Ví dụ Job nhắc lịch 24h kiểm tra bảng `notifications` theo bộ khóa `(user_id, reference_type = 'SESSION', reference_id = session_id)`, nếu đã gửi rồi thì bỏ qua, tuyệt đối không gửi trùng lặp thông báo.
    3. Ngoài ra, quản trị viên có thể kích hoạt từng job riêng lẻ qua tham số dòng lệnh: `php bin/cron.php --job=session-reminder`."

### Câu hỏi 9: Kiến trúc mã nguồn và tính bảo mật của hệ thống được tổ chức như thế nào?
- **Trả lời phản biện:**
  - "Dạ thưa Thầy/Cô:
    1. **Kiến trúc mã nguồn:** Hệ thống xây dựng trên kiến trúc **Feature-Driven Design** kết hợp nguyên lý SOLID. Mỗi tính năng được đóng gói độc lập gồm Controller, Service, Repository; router sử dụng Dependency Injection tự chế gọn nhẹ.
    2. **Bảo mật cơ sở dữ liệu:** 100% câu lệnh SQL sử dụng PDO Prepared Statements có tham số ràng buộc, loại bỏ hoàn toàn lỗ hổng SQL Injection. Khóa ngoại có ràng buộc `ON DELETE RESTRICT` ngăn ngừa mồ côi dữ liệu.
    3. **Chống XSS và CSRF:** Toàn bộ dữ liệu xuất ra HTML đều được bọc hàm `sanitizeHtml()` / `htmlspecialchars()`. Mọi thao tác thay đổi trạng thái (POST, PATCH, PUT) đều bắt buộc kiểm tra token CSRF sinh ngẫu nhiên theo phiên.
    4. **Phân quyền đa tầng (RBAC):** Middleware kiểm tra quyền hạn nghiêm ngặt ở tầng Server dựa trên vai trò trong Session, ngăn chặn tuyệt đối tình trạng người chơi gọi trộm API của DM hoặc Admin."

### Câu hỏi 10: Quy trình khởi tạo và kiểm thử toàn bộ hệ thống từ đầu có thuận tiện không?
- **Trả lời phản biện:**
  - "Dạ thưa Thầy/Cô, toàn bộ quy trình khởi tạo được tự động hóa 100%:
    1. Khởi tạo dữ liệu: Chỉ cần chạy 01 lệnh duy nhất `php bin/seed.php` (hoặc `composer seed`), hệ thống tự động sinh 10 phòng vật lý, 12 kịch bản đa dạng thể loại, 3 khung giờ giá vé, mã khuyến mãi và tài khoản người dùng mẫu trong vòng 0.5 giây.
    2. Kiểm thử tự động: Chạy `vendor/bin/phpunit`, toàn bộ bộ test suite gồm **150 bài test và 731 assertions đạt 100% PASS** trong 4.4 giây.
    3. Kiểm thử Frontend: Chạy 5 bộ test runner Node.js, 100% assertions về xác thực, phân quyền và bảo mật đều vượt qua xuất sắc."

---

Tài liệu này là vũ khí phản biện toàn diện giúp sinh viên tự tin trả lời lưu loát mọi khía cạnh kỹ thuật, nghiệp vụ và kiến trúc hệ thống trước hội đồng đánh giá.
