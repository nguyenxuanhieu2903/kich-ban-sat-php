# SỔ TAY HƯỚNG DẪN CÁCH VẬN HÀNH HỆ THỐNG KỊCH BẢN SÁT V16
## Standard Operating Procedure (SOP) — Dành Cho Người Chơi, Quản Trò (DM), Lễ Tân & Quản Trị Viên

Tài liệu này là cẩm nang vận hành thực tế, chuẩn hóa toàn bộ quy trình từ online đến offline tại quầy chi nhánh, giải quyết triệt để các tình huống nghiệp vụ và bảo đảm trải nghiệm trọn vẹn cho khách hàng cũng như nhân sự vận hành.

---

## 1. TỔNG QUAN LUỒNG VẬN HÀNH 5 BƯỚC

Hệ thống hoạt động theo nguyên tắc vàng: **"Xếp Lịch & Gom Đủ Người Trước — Thu Tiền Cọc Sau — Trải Nghiệm & Tất Toán Tại Quầy"**.

```
[BƯỚC 1: TẠO PHÒNG & GOM ĐỘI]
   |
   +---> Phòng PUBLIC (Khách lẻ): Gom đủ tối thiểu min_players (ví dụ 6 người)
   +---> Phòng PRIVATE (Nhóm bạn): Điền sĩ số nhóm (ví dụ 6 người) và guest_slots
   |
[BƯỚC 2: CHỐT LỊCH & BỐ TRÍ TÀI NGUYÊN]
   |
   +---> Biểu quyết 12h (đạt >= 80% đồng thuận và 0 phiếu từ chối)
   +---> Hệ thống tự động kiểm tra phòng vật lý trống + DM rảnh (đệm dọn phòng 30p)
   |
[BƯỚC 3: GIỮ PHÒNG & NỘP CỌC (SLA 2H)]
   |
   +---> Khách lẻ: Mỗi người tự nộp 50.000 VNĐ qua mã QR cá nhân
   +---> Nhóm riêng: Host nộp cọc gộp cho nhóm = (total_players - guest_slots) * 50.000đ
   +---> Đủ 100% cọc: Phòng chuyển sang CONFIRMED, chính thức khóa lịch phòng vật lý
   |
[BƯỚC 4: CHECK-IN & DIỄN RA BUỔI CHƠI]
   |
   +---> Khách đến quầy trước 15 phút, xuất trình thẻ HSSV (nếu có) để giảm 10%
   +---> DM phát kịch bản giấy, hỗ trợ phân vai theo giới tính & sở thích
   +---> DM bấm "Bắt Đầu Buổi Chơi" (IN_PROGRESS) -> Điều phối các vòng án
   +---> Kết thúc vụ án, DM bấm "Hoàn Tất Buổi Chơi" (COMPLETED)
   |
[BƯỚC 5: TẤT TOÁN TẠI QUẦY & ĐÁNH GIÁ]
   |
   +---> Quầy thu ngân quét mã thanh toán số tiền vé còn lại sau khi trừ cọc
   +---> Khách đánh giá 1-5 sao cho Kịch bản và Quản trò DM trên trang cá nhân
```

---

## 2. HƯỚNG DẪN DÀNH CHO NGƯỜI CHƠI (PLAYER & HOST)

### 2.1. Đối với Khách lẻ tìm bạn chơi cùng (Phòng PUBLIC)
1. **Tìm phòng trên Sảnh ghép (`lobby.html`):**
   - Xem rõ thông tin kịch bản, độ tuổi (18+), chi nhánh (Quận 1 / Bình Thạnh) và khung giờ dự kiến để đảm bảo phù hợp lịch cá nhân trước khi bấm "Tham Gia".
2. **Biểu quyết mốc lịch (`room-detail.html`):**
   - Khi phòng gom đủ số người tối thiểu, Host sẽ mở đề xuất mốc lịch chơi chính xác.
   - Mỗi người chơi có 12 giờ để bấm "Đồng Ý (ACCEPTED)" hoặc "Không Phù Hợp (REJECTED)".
   - Nếu bạn bấm từ chối, bạn có thể đề xuất một khung giờ khác hoặc bấm "Rút lui" để nhường chỗ cho người khác mà không bị phạt gì.
3. **Nộp tiền cọc (50.000 VNĐ):**
   - Sau khi lịch được chốt, hệ thống mở cổng nộp cọc trong vòng 02 giờ.
   - Bấm "Nộp Cọc 50.000đ" và quét mã QR chuyển khoản. Khi toàn bộ thành viên nộp đủ, phòng được xác nhận chính thức.
4. **Tại quầy chi nhánh:**
   - Đến trước giờ chơi 15 phút để làm thủ tục.
   - Nếu là học sinh / sinh viên: Xuất trình thẻ HSSV vật lý cho Quản trò/Thu ngân để được giảm ngay 10% giá vé.
   - Nhận kịch bản giấy từ DM, đọc lời giới thiệu nhân vật và bắt đầu nhập vai.

### 2.2. Đối với Nhóm bạn đi chung (Phòng PRIVATE)
1. **Tạo phòng riêng (`create-room.html`):**
   - Chọn hình thức `PRIVATE`.
   - Nhập tổng số bạn bè đi cùng (`total_players`, ví dụ 6 người).
   - Nếu nhóm có 5 người và muốn mở 1 slot cho người ngoài vào chơi cùng: Nhập `guest_slots = 1`.
2. **Nộp cọc trọn gói cho nhóm bạn (Lump-sum Deposit):**
   - Chủ phòng (Host) đại diện nộp cọc gộp cho cả nhóm: `(6 - 1) * 50.000 = 250.000 VNĐ`.
   - Slot khách lẻ còn lại sẽ tự do hiển thị trên Sảnh ghép để khách lạ tự tham gia và tự nộp 50.000đ tiền cọc của họ.
3. **Quyền đổi lịch & rút lui:**
   - Được đổi lịch chơi miễn phí 01 lần nếu báo trước giờ chơi tối thiểu 24 giờ.
   - Hủy phòng trước 24 giờ được hoàn cọc 100%; hủy phòng dưới 24 giờ bị giữ lại tiền cọc để bù đắp chi phí chuẩn bị phòng và in ấn tài liệu kịch bản.

---

## 3. HƯỚNG DẪN DÀNH CHO QUẢN TRÒ (DM / GAME MASTER)

Quản trò là người chịu trách nhiệm chuyên môn dẫn dắt vụ án, nắm giữ vai trò tạo nên linh hồn cho buổi chơi.

### 3.1. Tiếp nhận và phản hồi ca trực (SLA 2 giờ)
1. Truy cập Cổng Quản Trò tại `dm-portal.html`.
2. Tại mục "Danh Sách Ca Trực Được Phân Công", xem kỹ thông tin: Tên kịch bản, thời gian bắt đầu, số lượng người chơi, chi nhánh và phòng vật lý.
3. Bấm **"Chấp Nhận Ca"** trong vòng 02 giờ kể từ khi được hệ thống gán. Nếu bận lịch đột xuất, bấm **"Từ Chối Ca"** kèm lý do chính đáng để hệ thống tự động xoay tua chuyển ca cho DM khác.

### 3.2. Quy trình đón khách và vận hành hiện trường
1. **Trước giờ chơi 30 phút:**
   - Kiểm tra phòng vật lý: Đạo cụ, manh mối hiện trường, bút viết, bảng ghi án, đèn và âm thanh nền.
   - Đảm bảo đủ số lượng tập kịch bản giấy tương ứng với các nhân vật.
2. **Khi khách check-in tại quầy:**
   - Chào đón người chơi, hỗ trợ đối soát thẻ HSSV vật lý: Bấm nút "Xác Nhận HSSV" trên Cổng DM để áp dụng giảm giá vé cho khách.
   - Phân vai nhân vật: Dựa trên tỷ lệ giới tính (Nam/Nữ) và sở thích của khách để phát tập kịch bản phù hợp. Nếu phòng thiếu bạn nam/nữ, DM hướng dẫn khách đổi vai (Cross-gender) một cách tự nhiên, thoải mái.
3. **Bắt đầu buổi chơi:**
   - Bấm nút **"Bắt Đầu Buổi Chơi"** trên Cổng DM (trạng thái chuyển sang `IN_PROGRESS`).
   - Hướng dẫn luật chơi cơ bản, đọc lời dẫn mở màn, kiểm soát thời gian đọc kịch bản riêng tư của từng người.
   - Điều phối các vòng phát biểu, đọc manh mối công khai, vòng lục soát hiện trường và vòng thẩm vấn kín.
4. **Kết thúc buổi chơi:**
   - Cho người chơi bỏ phiếu tìm hung thủ.
   - Đọc diễn biến toàn bộ vụ án (chân tướng sự thật, động cơ gây án, timeline gây án).
   - Bấm nút **"Hoàn Tất Buổi Chơi"** trên Cổng DM (trạng thái chuyển sang `COMPLETED`).
   - Nhập ghi chú hiện trường (hư hao đạo cụ, khách chơi xuất sắc) và bấm "Lưu Ghi Chú Hiện Trường".
5. **Hỗ trợ tất toán tại quầy:**
   - Hướng dẫn khách qua quầy lễ tân để thanh toán nốt tiền vé còn lại.
   - Bấm **"Xác Nhận Tất Toán Quầy"** trên Cổng DM khi khách đã thanh toán xong.

---

## 4. HƯỚNG DẪN DÀNH CHO THU NGÂN & LỄ TÂN CHI NHÁNH

Thu ngân / Lễ tân chịu trách nhiệm kiểm soát dòng tiền tại chỗ, đối soát vé và đón tiếp khách hàng.

### 4.1. Quy trình đón tiếp và kiểm tra thông tin
1. Tra cứu mã phòng hoặc số điện thoại của Host trên hệ thống.
2. Kiểm tra danh sách người chơi đã nộp đủ tiền cọc (`status = DEPOSITED` hoặc `CONFIRMED`).
3. Đối soát thẻ Học sinh - Sinh viên: Yêu cầu khách xuất trình thẻ HSSV chính chủ có ảnh và còn hạn sử dụng; bấm duyệt trên hệ thống để tự động trừ 10% tiền vé.
4. Đối soát độ tuổi kịch bản 18+: Đối với kịch bản có yếu tố kinh dị/bạo lực hoặc tâm lý nặng, lễ tân có quyền kiểm tra CCCD.

### 4.2. Tất toán tiền vé sau buổi chơi
1. Sau khi buổi chơi hoàn tất, mở chi tiết phòng để xem bảng tính giá cuối cùng:
   - Tổng tiền vé = `(Giá vé gốc * Số người) - Giảm giá HSSV/Khuyến mãi`.
   - Tiền cọc đã nộp = `Số người * 50.000 VNĐ`.
   - Tiền còn lại phải thu tại quầy = `Tổng tiền vé - Tiền cọc đã nộp`.
2. Phương thức thu tiền:
   - **Đối với nhóm bạn (PRIVATE):** In 01 mã QR tổng số tiền còn lại để Host thanh toán một lần cho cả bàn.
   - **Đối với khách lẻ ghép đội (PUBLIC):** Hướng dẫn từng người quét mã QR tương ứng với số tiền cá nhân của mình.
   - **Thu phụ phí phát sinh (Extra Fees / Prop Damage):** Nếu khách làm rách kịch bản, làm hỏng trang phục hóa trang hoặc gọi thêm đồ uống, Thu ngân nhập số tiền phụ phí vào ô "Phụ phí" kèm lý do đền bù trước khi xác nhận tất toán.
3. Bấm xác nhận đã thu đủ tiền để hệ thống chuyển trạng thái thanh toán sang `SETTLED`. Nếu phát sinh tranh chấp hoặc khách nợ tiền, bấm ghi nhận cờ `PAYMENT_DISPUTE` để chuyển kế toán đối soát.

---

## 5. HƯỚNG DẪN DÀNH CHO QUẢN TRỊ VIÊN & CHỦ CƠ SỞ (ADMIN)

Quản trị viên sử dụng Cổng Quản Trị tại `admin-portal.html` để điều phối toàn bộ chi nhánh và tài chính.

### 5.1. Bảng điều khiển kinh doanh (KPI Dashboard)
- Theo dõi 4 chỉ số tài chính theo thời gian thực:
  - **Tổng Doanh Thu Thuần:** Doanh thu thực tế sau khi trừ chiết khấu và tiền hoàn cọc.
  - **Tiền Cọc Đã Thu:** Tổng tiền cọc 50.000đ/slot đang được giữ chỗ.
  - **Số Phòng Chơi Đang Diễn Ra:** Số ca đang ở trạng thái `IN_PROGRESS`.
  - **Số Ca DM Được Phân Công:** Khối lượng công việc của đội ngũ quản trò.

### 5.2. Điều phối phòng vật lý & Quản trò DM khi có xung đột
1. **Khóa phòng giữ chỗ ngoại tuyến (Offline Block):**
   - Khi có khách gọi qua Hotline đặt bàn hoặc khách vãng lai (Walk-in), Admin sử dụng tính năng "Khóa Giữ Chỗ Thủ Công" (`POST /api/v1/admin/rooms/offline-block`) để giữ phòng vật lý, ngăn chặn khách trên mạng đặt trùng.
2. **Xử lý cảnh báo khẩn cấp `ADMIN_ALERT` khi xoay tua DM thất bại:**
   - Khi một phòng bị từ chối ca $\ge 3$ lần, hệ thống dừng xoay tua tự động và phát cảnh báo đỏ lên màn hình Admin.
   - Admin trực tiếp liên hệ điều động Quản trò cơ hữu của quán hoặc gọi điện thoại thống nhất với khách đổi sang khung giờ kế tiếp.
3. **Phân công và thu hồi DM:**
   - Bấm nút "Phân Công DM" hoặc "Thu Hồi DM" để can thiệp cưỡng chế nhân sự khi có sự cố phát sinh.

### 5.3. Phê duyệt và hoàn tất hoàn tiền cọc (Refunds Management)
1. Tại tab "Quản Lý Hoàn Cọc":
   - Các yêu cầu hủy trước 24 giờ đã được hệ thống tự động duyệt sang `APPROVED`.
   - Admin kiểm tra danh sách yêu cầu cần tra soát thủ công (ví dụ hủy do cơ sở mất điện đột xuất), bấm **"Phê Duyệt (Approve)"** hoặc **"Từ Chối (Reject)"** kèm lý do cụ thể.
2. Sau khi bộ phận kế toán thực hiện ủy nhiệm chi/chuyển khoản trả lại tiền cọc cho khách, Admin bấm **"Hoàn Tất Hoàn Cọc (Complete)"** (`POST /api/v1/admin/refunds/{id}/complete`) để đóng hồ sơ hoàn tiền (trạng thái `COMPLETED`).
3. SLA hoàn tiền cam kết: Tối đa 02 giờ làm việc qua cổng thanh toán tự động, tối đa 24 giờ đối với chuyển khoản tra soát thủ công.

### 5.4. Quản lý tài khoản & Khóa tài khoản vi phạm (Account Suspension)
- Khóa tài khoản người chơi gian lận hoặc vi phạm quy chế ứng xử tại quán.
- Hệ thống tự động cách ly an toàn: Nếu người bị khóa là Host của phòng đang gom người (`FORMING`), quyền Host tự động chuyển giao cho người kế tiếp; nếu là thành viên thường, chỉ loại khỏi phòng mà không làm ảnh hưởng đến các thành viên khác.

---

## 6. QUY TRÌNH XỬ LÝ CÁC TÌNH HUỐNG NGOẠI LỆ & SỰ CỐ (SOP SỰ CỐ)

### 6.1. Khách vắng mặt (No-Show) tại giờ chơi
- **Trường hợp 1: Thiếu đúng 01 người:**
  - DM ghi nhận ID người vắng mặt, bấm "Báo Cáo No-show".
  - Tiền cọc 50.000đ của người vắng mặt bị tịch thu sung công quỹ cơ sở (`FORFEITED`).
  - Buổi chơi tiếp tục diễn ra: Quản trò DM trực tiếp đóng thế vai nhân vật thứ 6 (vai NPC) để đọc manh mối và tương tác với bàn chơi.
- **Trường hợp 2: Thiếu từ 02 người trở lên hoặc cả nhóm bùng lịch:**
  - Bàn chơi bị thiếu người nghiêm trọng, không thể tiến hành giải án (Game Unplayable).
  - DM bấm báo cáo No-show $\ge 2$ người -> Hệ thống tự động hủy ca tại chỗ (`CANCELLED_ON_SITE`).
  - Tiền cọc của những người vắng mặt bị tịch thu 100%.
  - Những người chơi đã đến đúng giờ được bảo vệ quyền lợi tối đa: Tự động duyệt hoàn cọc 100% trong vòng 02 giờ làm việc, tặng kèm voucher giảm giá 20% cho lần chơi sau và mời chơi Board game miễn phí tại khu vực sảnh chờ.

### 6.2. Thành viên rút lui sau khi đã nộp cọc (`CONFIRMED`)
- Người chơi gửi yêu cầu rút lui trên trang phòng:
  - Nếu báo trước giờ chơi $\ge 24$ giờ: Được duyệt hoàn cọc 100%. Phòng chuyển sang trạng thái `NEED_SUBSTITUTE` và xuất hiện nhãn `[Cần Người Thế Chỗ Gấp]` trên đầu Sảnh ghép.
  - Cửa sổ tìm người thế chỗ tự động đóng trước giờ chơi 04 giờ. Nếu chạm mốc 4 giờ mà chưa có người thay thế, hệ thống thông báo cho DM chuyển sang phương án điều chỉnh nhân vật hoặc DM đóng thế vai NPC.
  - Nếu rút lui dưới 24 giờ: Tiền cọc 50.000đ bị giữ lại để bù đắp chi phí kịch bản đã in ấn.

### 6.3. Khách hàng chưa đủ 18 tuổi đến chơi kịch bản 18+
- Lễ tân kiểm tra CCCD phát hiện khách chưa đủ 18 tuổi:
  - Tuyệt đối không cho phép tham gia kịch bản 18+ để đảm bảo an toàn tâm lý và tuân thủ pháp luật.
  - Xử lý nhân văn: Quán không phạt mất cọc của khách; nhân viên hỗ trợ cả nhóm đổi sang một kịch bản trinh thám học đường hoặc gia đình phù hợp lứa tuổi, và chuyển toàn bộ số tiền cọc đã đóng sang kịch bản mới.

### 6.4. Sự cố bất khả kháng từ phía cơ sở (DM ốm đột xuất hoặc phòng hỏng)
- Trong trường hợp phòng vật lý bị sự cố (chập điện, hỏng máy lạnh) hoặc DM gặp tai nạn không thể đến:
  - Admin ưu tiên điều chuyển sang phòng vật lý khác hoặc điều động DM dự phòng.
  - Nếu không thể khắc phục: Admin bấm hủy phòng với lý do bất khả kháng. Hệ thống tự động duyệt hoàn tiền cọc 100% cho toàn bộ người chơi bất kể mốc thời gian 24 giờ, đồng thời gửi lời xin lỗi và tặng voucher giảm giá 50% cho buổi chơi tiếp theo.

---

## 7. BẢNG MÃ TRẠNG THÁI 4 STATE MACHINE TRONG VẬN HÀNH

| Thực thể | Trạng thái | Ý nghĩa nghiệp vụ thực tế |
|:---|:---|:---|
| **Phòng chơi (Room)** | `FORMING` | Đang mở trên sảnh để người chơi vào gom đội. |
| | `SCHEDULED` | Đã chốt mốc thời gian và gán DM; đang chờ các thành viên nộp cọc. |
| | `CONFIRMED` | Đã thu đủ 100% tiền cọc bàn; chính thức khóa lịch phòng vật lý. |
| | `NEED_SUBSTITUTE` | Có người rút lui sau khi cọc; đang mở tuyển người thế chỗ khẩn cấp. |
| | `IN_PROGRESS` | Buổi chơi đang diễn ra tại phòng kịch bản. |
| | `COMPLETED` | Buổi chơi đã hoàn tất thành công. |
| | `CANCELLED` | Phòng bị hủy (do khách hủy hợp lệ hoặc sự cố bất khả kháng). |
| | `EXPIRED` | Quá hạn gom người 48h hoặc quá hạn nộp cọc 2h. |
| **Thanh toán (Payment)** | `PENDING` | Hóa đơn cọc được tạo, đang chờ khách thanh toán. |
| | `PAID` | Đã nộp cọc thành công hoặc đã tất toán xong tại quầy lễ tân. |
| | `FORFEITED` | Tiền cọc bị tịch thu sung công quỹ do vi phạm No-show hoặc hủy sát giờ. |
| | `SETTLED` | Đã quyết toán xong 100% tiền vé của toàn bộ bàn chơi tại quầy. |
| | `PAYMENT_DISPUTE` | Phát sinh tranh chấp số tiền hoặc lỗi mạng tại quầy thu ngân. |
| **Phiên chơi (Session)** | `SCHEDULED` | Ca chơi đã được lên lịch chính thức. |
| | `IN_PROGRESS` | Quản trò đã bấm bắt đầu buổi chơi. |
| | `COMPLETED` | Quản trò đã bấm kết thúc buổi chơi. |
| | `CANCELLED_ON_SITE`| Hủy ca tại chỗ do No-show từ 2 người trở lên hoặc sự cố phòng. |
| **Hoàn tiền (Refund)** | `REQUESTED` | Người chơi gửi yêu cầu rút lui / hoàn cọc. |
| | `APPROVED` | Yêu cầu được duyệt hợp lệ (trước 24h hoặc Admin duyệt). |
| | `PROCESSING` | Kế toán đang thực hiện lệnh chuyển khoản hoàn tiền. |
| | `COMPLETED` | Đã chuyển khoản tiền cọc trả lại cho người chơi thành công. |
| | `REJECTED` | Từ chối hoàn tiền do hủy sát giờ dưới 24 giờ. |

---

Tài liệu này được áp dụng thống nhất cho toàn bộ hệ sinh thái Kịch Bản Sát V16 tại tất cả các chi nhánh. Mọi thay đổi về chính sách giá, thời gian đệm và quy chế hoàn tiền đều phải được cập nhật đồng bộ vào tài liệu này và mã nguồn hệ thống.
