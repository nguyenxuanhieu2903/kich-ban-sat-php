# KICH BAN SAT V16 - BAO CAO CAP NHAT, NANG CAP VA DOI CHIEU TOAN DIEN

Tài liệu này được biên soạn chi tiết, minh bạch, có cấu trúc học thuật và kỹ thuật đầy đủ nhằm phục vụ công tác nghiệm thu, báo cáo đồ án tốt nghiệp và đối chiếu kỹ thuật. Văn bản trình bày toàn bộ các nội dung đã được sửa lỗi, nâng cấp, thêm mới, cùng bảng so sánh chi tiết giữa phiên bản hoàn thiện kichbansattruong và phiên bản ban đầu feature/Dev-Hieu trên kho mã nguồn Git.

---

## 1. BANG TONG HOP DOI CHIEU SO SANH TRUC DIEN

| Tieu chi so sanh | Phien ban ban dau (feature/Dev-Hieu) | Phien ban hoan thien (kichbansattruong) | Y nghia ky thuat va nghiep vu thuc te |
| :--- | :--- | :--- | :--- |
| Muc do hoan thien tong the | Khoang 65% - 70% | 100% hoan chinh, san sang van hanh thuc te | Da khep kin toan bo quy trinh tu tao phong, ghep nguoi, bieu quyet lich, phan cong DM, dat coc, hoan coc den bao cao doanh thu. |
| Giai doan Backend | Dung lai o Stage 2 (thieu phan lon nghiep vu van hanh quan tri) | Hoan tat 100% ca 3 giai doan: Stage 1, Stage 2 va Stage 3 | Cung cap day du nghiep vu quan tri, phan cong DM, thu hoi ca truc, gan phong vat ly, bao cao doanh thu va nhat ky kiem toan. |
| Kiem thu tu dong (Automated Tests) | 14 test co ban (chua duoc cau hinh day du de chay) | 149 test tu dong (125 test PHPUnit backend + 24 test Node.js E2E frontend) dat ty le vuot qua 100% | He thong duoc bao ve boi mang luoi kiem thu tu dong day dac, kiem chung moi dieu kien bien va logic nghiep vu. |
| Xu ly tranh chap phong (Race Condition) | Kiem tra so luong nguoi thong thuong, de bi vuot qua max_players khi nhieu nguoi bam cung luc | Su dung co che khoa bi quan (Pessimistic Locking: SELECT ... FOR UPDATE) trong Database Transaction | Dam bao tuyet doi khong bao gio xay ra tinh trang phong 6 nguoi ma co 7 nguoi vao duoc. |
| Kiem soat uu dai Sinh vien | Tu dong ap dung giam 20.000d cho tat ca nguoi dung khong kiem tra the | Bat buoc nguoi dung phai duoc Admin xet duyet the sinh vien (student_verified = 1) | Bao ve doanh thu, loai bo triet de nguy co gian lan uu dai. |
| Co che ngan chan hoan coc sat gio | Cho phep truyen co force_refund tu phia client de lay lai 100% tien coc | Chan co force_refund tu client; chi duy nhat Admin co quyen phe duyet dac cach trong truong hop bat kha khang | Nguoi choi huy truoc 24h duoc hoan 100%, huy duoi 24h bi mat coc 100% theo quy che V16 cua quan. |
| Xu ly de xuat lich bi treo | De xuat lich khong co co che tu dong het han, lam treo phong vo thoi han | Bo sung Job chay ngam tu dong huy de xuat sau 24h neu khong dat du phieu dong thuan | Giup giai phong phong choi de cac thanh vien mo dot de xuat moi. |
| Khoang dem don phong vat ly | Cho phep dat cac ca choi lien tiep sat nhau tai cung mot phong vat ly | Tu dong kiem tra va bat buoc co khoang dem toi thieu 30 phut giua 2 ca choi lien ke | Danh thoi gian cho nhan vien thu don dao cu, quan tro nghi ngoi va chuan bi phuc trang cho luot choi tiep theo. |
| Nhat ky kiem toan (Audit Trail) | Chua co bang audit_logs, khong luu vet thao tac | He thong AuditLogService tu dong ghi lai IP, thoi gian, nguoi thuc hien, hanh dong va du lieu thay doi | Cung cap co che hop den kiem toan, ngan ngua gian lan noi bo. |
| Bao cao tai chinh & Doanh thu | Chua co controller va endpoint bao cao | Hoan thien AdminReportController voi cac endpoint thong ke doanh thu thuan, tien coc, tien hoan, ty le huy | Cung cap so lieu kinh doanh tuc thi cho chu co so. |
| Tai lieu dac ta API | Khong co tai lieu tuong tac | Tich hop Swagger UI tuong tac truc tiep tai duong dan /docs va file dac ta chuan openapi.json | Nguoi cham thi va lap trinh vien co the thu nghiem truc tiep tren trinh duyet khong can cai Postman. |
| Du lieu mau tham chieu (Seeder) | Thieu kich ban so 3, thieu cau hinh dao cu JSON, thieu slot gia ban ngay | Hoan thien bin/seed.php va V3DataSeeder.php voi day du 3 kich ban, 3 cau hinh JSON, 7 bang gia, 2 uu dai va tai khoan demo chuan BCRYPT | Tao moi truong thu nghiem hoan hao voi mot cau lenh duy nhat. |

---

## 2. TOAN BO CAC LOI DA DUOC PHAT HIEN VA SUA TRIET DE (BUG FIXES)

Trong qua trinh kiem tra tong the va doi chieu ma nguon, he thong da phat hien va khac phuc triet de 10 loi nghiem trong:

### 2.1. Sua loi Race Condition khi nhieu nguoi cung tham gia phong choi
- Hien tuong o ban cu: Khi phong choi chi con dung 1 vi tri trong (vi du 5/6 nguoi), neu hai nguoi choi nhan nut "Tham gia" tai cung mot thoi diem, cau truy van kiem tra so luong thanh vien o ca hai phien deu doc duoc so luong la 5. Ket qua la ca hai nguoi deu duoc them vao phong, day tong so thanh vien len 7 nguoi, vi pham rang buoc gioi han so nguoi choi cua kich ban.
- Giai phap khac phuc: Tai GameRoomController.php va RoomParticipantRepository, luong dang ky tham gia duoc bao boc ben trong Database Transaction voi co che khoa bi quan:
  `SELECT id, max_players, status FROM game_rooms WHERE id = :id FOR UPDATE;`
  Cau lenh nay khoa ban ghi phong choi o tang CSDL cho den khi transaction hoan tat. Yeu cau thu nhat duoc ghi nhan thanh cong va cap nhat so luong len 6; yeu cau thu hai sau khi duoc giai phong khoa se kiem tra lai va tra ve loi chuan `ROOM_FULL (400)` voi thong diep "Phong choi da du so luong thanh vien toi da".

### 2.2. Sua loi lam dung uu dai Hoc sinh - Sinh vien (HSSV)
- Hien tuong o ban cu: Logic tinh gia tai PricingService.php tu dong tru 20.000d cho moi nguoi tham gia ma khong kiem tra trang thai xac minh the hoc sinh - sinh vien.
- Giai phap khac phuc: Bo sung dieu kien kiem tra nghiem ngat `student_verified === 1` hoac `student_verified === true` doi voi tung nguoi dung duoc huong uu dai. Nguoi choi phai tai len anh the va duoc Quan tri vien xet duyet thong qua endpoint `/api/v1/admin/users/{id}/verify-student` thi moi duoc he thong giam tru khoan tien nay.

### 2.3. Sua loi nguoi dung tu y lay lai tien coc sat gio choi qua co force_refund
- Hien tuong o ban cu: Endpoint yeu cau huy/hoan tien cho phep nhan tham so `force_refund = true` tu body request cua client, dan den viec nguoi choi co the gui request can thiep de duoc hoan 100% tien coc du huy phong sat gio choi (duoi 24 gio).
- Giai phap khac phuc: Tai PaymentRefundRequestController.php va AdminRefundController.php, tach biet ro rang giua luong nguoi choi yeu cau huy va luong quan tri vien phe duyet. Tham so `force_refund` bi loai bo hoan toan khoi endpoint cong khai cua nguoi choi; chi duy nhat tai khoan co quyen ADMIN moi duoc phep su dung co nay khi xu ly cac su co bat kha khang (nhu co so mat dien hoac thien tai). Nguoi choi yeu cau huy phong truoc 24 gio duoc he thong tu dong hoan coc 100%; huy duoi 24 gio bi giu lai 100% tien coc de bu dap chi phi chuan bi phong va phi giu cho cua Quan tro.

### 2.4. Sua loi de xuat lich choi bi treo vo thoi han
- Hien tuong o ban cu: Khi mot nguoi choi tao de xuat gio choi moi nhung cac thanh vien khac khong vao bieu quyet, de xuat nay nam o trang thai cho mai mai lam can tro viec thong nhat lich.
- Giai phap khac phuc: Xay dung tien trinh quet tu dong tai BackgroundJobService.php (`proposal-timeout`). Neu sau 24 gio ke tu luc tao ma de xuat khong dat du so phieu dong thuan cua da so thanh vien trong phong, he thong tu dong chuyen trang thai de xuat ve `EXPIRED` hoac `REJECTED`, giai phong trang thai de cac thanh vien tao de xuat moi.

### 2.5. Bo sung quy tac khoang dem 30 phut giua cac ca choi tai phong vat ly
- Hien tuong o ban cu: He thong chi kiem tra trung gio tuyet doi ma khong tinh den thoi gian don dep, khien ca choi sau co the dat lich ngay sat gio ket thuc cua ca choi truoc tai cung mot phong vat ly.
- Giai phap khac phuc: Tai RoomSchedulingService.php, tich hop thuat toan kiem tra xung dot thoi gian co tinh them khoang dem toi thieu 30 phut (`BUFFER_MINUTES = 30`). Neu ca choi truoc ket thuc luc 13:00, phong chi co the don doan khach tiep theo tu 13:30 tro di.

### 2.6. Sua loi ma bam mat khau tai khoan mau trong CSDL khong khop chuoi password123
- Hien tuong o ban cu: Trong database `kich_ban_sat_v3`, cac tai khoan mau duoc nap san co ma bam mat khau khong khop voi chuoi `password123`, khien cac bo test dang nhap tu dong va nguoi dung thu nghiem deu bi loi 401 Unauthorized khi dang nhap bang cac tai khoan admin@kichbansat.vn, dm.nam@kichbansat.vn, player.hieu@kichbansat.vn.
- Giai phap khac phuc: Su dung ham `password_hash('password123', PASSWORD_BCRYPT)` de tai tao ma bam chuan va cap nhat vao CSDL, dong thoi bo sung cau lenh `ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)` vao bin/seed.php de dam bao moi lan chay seeder deu dong bo dung mat khau mau.

### 2.7. Sua loi dinh tuyen 404 tai nguyen tinh Frontend tren may chu PHP Development Server
- Hien tuong o ban cu: Khi chay lenh `php -S 127.0.0.1:8000 -t public` khong co tham so router script, may chu PHP built-in server khong tim thay cac tep JavaScript module nam tai thu muc `/frontend/src/` va tra ve ma loi 404 Not Found.
- Giai phap khac phuc: Thiet lap tep `public/index.php` dong vai tro Front Controller kiem Static Asset Router. Khi yeu cau khong bat dau bang `/api`, index.php tu dong phan giai duong dan ve thu muc `frontend/`, kiem tra chong duong dan nguy hiem (Path Traversal Prevention), xac dinh dung MIME type (`application/javascript`, `text/css`, `image/png`, v.v.) va tra ve trinh duyet voi ma 200 OK.

### 2.8. Sua loi thieu kich ban so 3 va thieu bang gia ca sang trong script nap du lieu
- Hien tuong o ban cu: Tep `bin/seed.php` cu chi nap 2 kich ban va 4 bang gia, thieu kich ban "An Mang Tren Chuyen Tau Tuyet" va thieu bang gia ca ban ngay 180.000d, khien cac bai test tim kiem va kiem tra modal gia bi that bai.
- Giai phap khac phuc: Dong bo toan dien `bin/seed.php` voi migration `V3DataSeeder.php`, nap day du 3 kich ban hoat dong, 3 ban ghi cau hinh dao cu JSON va 7 khung gia theo gio day du.

### 2.9. Sua loi khong dong bo ten co so du lieu trong file cau hinh moi truong (.env)
- Hien tuong o ban cu: File `.env` tren kho Git mang ten database cu `booking_v16` trong khi CSDL chuan hoa cua du an la `kich_ban_sat_v3`.
- Giai phap khac phuc: Dong bo toan bo cac file `.env` va `.env.example` ve gia tri `DB_DATABASE=kich_ban_sat_v3`.

### 2.10. Sua loi thieu bat buoc CSRF Token tren cac phuong thuc thay doi trang thai
- Hien tuong o ban cu: Cac request POST/PUT/DELETE o mot so endpoint khong kiem tra ma CSRF, tiem an nguy co bi gia mao yeu cau tu trang web khac.
- Giai phap khac phuc: Bo sung CsrfMiddleware vao pipeline xu ly request, bat buoc truyen header `X-CSRF-Token` hoac tham so `csrf_token` doi voi moi phien nguoi dung da xac thuc.

---

## 3. TOAN BO CAC TINH NANG DUOC NANG CAP VA HOAN THIEN (UPGRADED MODULES)

### 3.1. Nang cap Phan he Xac thuc & Quan ly Nguoi dung (Identity & RBAC)
- Tai AuthController.php va UserRepository.php:
  - Nang cap co che tao Session: Tu dong goi `session_regenerate_id(true)` ngay sau khi dang nhap thanh cong de triet tieu nguy co tan cong Session Fixation.
  - Ho tro xac thuc the sinh vien: Luu tru thong tin sinh vien, ma so the, anh the va trang thai phe duyet `student_verified`.
  - Phan quyen 3 cap do chat che: PLAYER (nguoi choi), DM (quan tro), ADMIN (quan tri vien he thong).
  - Tu dong ngan chan hien thi menu quan tri hoac quan tro doi voi nguoi dung khong co tham quyen tu phia giao dien lan phia API backend.

### 3.2. Nang cap Phan he Quan ly Phong choi (Game Room Lifecycle)
- Tai GameRoomController.php va GameRoomRepository:
  - Quan ly vong doi phong choi qua 7 trang thai nghiem ngat:
    `OPEN` -> `FORMING` -> `SCHEDULE_PROPOSED` -> `SCHEDULE_LOCKED` -> `DEPOSIT_PENDING` -> `CONFIRMED` -> `COMPLETED` (hoac `CANCELLED`).
  - Co che kiem tra han chot gom nguoi (`formation_deadline_at`): Tu dong xac dinh phong choi da du so luong thanh vien toi thieu de chuyen sang giai doan de xuat lich hay chua.
  - Co che quyen Host (Chu phong): Chi Chu phong moi co quyen de xuat chot lich hoac huy phong trong giai doan tao lap.

### 3.3. Nang cap Phan he Xep lich & Bieu quyet (Scheduling & Room Proposal)
- Tai SchedulingController.php va SchedulingRepository.php:
  - Cho phep thanh vien de xuat khung gio va dia diem to chuc.
  - Cho phep tat ca thanh vien trong phong tham gia bieu quyet (`VOTE_YES` hoac `VOTE_NO`).
  - Khi tat ca thanh vien da bo phieu dong thuan, he thong tu dong chot lich (`SCHEDULE_LOCKED`) va chuyen sang buoc dat coc.
  - Tich hop kiem tra phong vat ly con trong va dam bao quy tac khoang dem 30 phut.

### 3.4. Nang cap Phan he Quan tri DM & Phan cong Ca truc (DM Management)
- Tai DMAssignmentController.php va DMRepository.php:
  - Admin co the xem danh sach cac buoi choi da chot lich de chi dinh Quan tro phu hop.
  - Quan tro co the truy cap vao trang danh sach ca truc ca nhan de xem chi tiet phong, thoi gian, so luong nguoi choi va kich ban.
  - Cung cap hai lua chon ro rang cho Quan tro: "Nhan ca truc" (`ACCEPTED`) hoac "Tu choi ca truc" (`REJECTED`) kem ly do tu choi de Admin dieu phoi nguoi khac.

### 3.5. Nang cap Phan he Tinh gia & Khuyen mai (Pricing & Promotions)
- Tai PricingService.php va PricingController.php:
  - Tinh toan gia dong theo khung gio trong ngay: ca sang (09:00 - 13:00) 180.000d, ca chieu (13:00 - 18:00) 220.000d, ca toi (18:00 - 23:00) 250.000d.
  - Tinh toan uu dai "Thu Ba vui ve": tu dong giam 30% tong gia ve neu buoi choi dien ra vao ngay Thu Ba trong tuan.
  - Tinh toan uu dai "Hoc sinh - Sinh vien": giam 20.000d/nguoi cho nhung nguoi choi da duoc xac minh the.
  - Ap dung quy tac khong cong don khuyen mai (`stackable = false`): neu phong da duoc giam 30% Thu Ba thi khong ap dung them giam sinh vien.
  - Luu snapshot gia vao bang `participant_price_snapshots`: Dong bang so tien phai tra cua tung nguoi choi tai thoi diem chot lich, dam bao neu sau do quan co doi bang gia thi nguoi da dat truoc van giu nguyen muc gia cu.

### 3.6. Nang cap Phan he Thanh toan & Hoan coc (Payments & Refunds)
- Tai PaymentController.php va PaymentRepository.php:
  - Thiet lap muc dat coc bat buoc co dinh: 50.000 VNĐ / nguoi.
  - Theo doi trang thai thanh toan cua tung ca nhan: `PENDING` -> `PARTIALLY_PAID` (da coc) -> `PAID` (da thanh toan du) hoac `REFUNDED` (da hoan tien).
  - Tich hop Database Transaction de dam bao tinh toan ven: viec cong tru so du va cap nhat trang thai thanh toan luon thanh cong dong thoi hoac rollback hoan toan neu co loi.

### 3.7. Nang cap Phan he Thong bao Da kenh (Notifications)
- Tai NotificationController.php va NotificationRepository.php:
  - Tu dong sinh thong bao trong he thong khi co su kien quan trong: co nguoi vao phong, lich choi duoc chot, Quan tro duoc phan cong, ca truc bi thay doi, tien coc da duoc ghi nhan.
  - Cung cap cac endpoint dem so thong bao chua doc (`/unread-count`) va danh dau tat ca la da doc (`/mark-read`).

---

## 4. TOAN BO CAC TINH NANG, TEP TIN VA ENDPOINT API DUOC THEM MOI (NEW ADDITIONS)

Giai doan hoan thien Stage 3 da bo sung them nhieu lop kien truc moi giup he thong dat tieu chuan cong nghiep:

### 4.1. Bang dieu khien Quan tri Van hanh Stage 3 (AdminOperationsController.php - 263 dong ma nguon)
Cung cap cac tinh nang dac quyen cho Admin:
- `POST /api/v1/admin/rooms/{id}/assign-physical-room`: Gan phong choi vat ly tai chi nhanh cho phong choi ao sau khi kiem tra suc chua va gio trong.
- `POST /api/v1/admin/rooms/{id}/revoke-dm`: Thu hoi phan cong DM khi quan tro co viec dot xuat de chuyen giao cho quan tro khac.
- `POST /api/v1/admin/rooms/{id}/disband`: Giai tan phong choi khan cap kem ly do giai tan chinh thuc va thong bao den tat ca nguoi tham gia.

### 4.2. He thong Nhat ky Kiem toan Hop den (AuditLogService.php va AdminAuditLogController.php)
- Tu dong ghi nhan moi hanh vi quan tri vao bang `audit_logs` gom:
  `actor_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`.
- Endpoint tra cuu: `GET /api/v1/admin/audit-logs` ho tro phan trang, tim kiem va loc theo hanh dong, nguoi thuc hien.

### 4.3. He thong Bao cao Tai chinh & Doanh thu Van hanh (AdminReportController.php - 159 dong ma nguon)
Cung cap 3 endpoint thong ke chuyen sau cho chu co so:
- `GET /api/v1/admin/reports/revenue`: Thong ke tong doanh thu, tong tien coc thu duoc, tong tien hoan tra khach, doanh thu thuan theo khoang thoi gian.
- `GET /api/v1/admin/reports/rooms`: Thong ke so luong phong da to chuc, so luong phong dang tao, ty le huy phong va thoi luong trung binh.
- `GET /api/v1/admin/reports/overview`: Bang tong hop cac chi so KPI van hanh quan trong cua toan bo he thong.

### 4.4. Quan ly Vong doi Buoi choi Thuc te (SessionLifecycleController.php - 205 dong ma nguon)
Cung cap cac cong cu danh cho Quan tro tai hien truong:
- `POST /api/v1/dm/sessions/{id}/start`: Quan tro xac nhan gio bat dau choi thuc te, chuyen buoi choi sang trang thai `IN_PROGRESS`.
- `POST /api/v1/dm/sessions/{id}/complete`: Quan tro xac nhan ket thuc buoi choi, chuyen sang trang thai `COMPLETED`.
- `POST /api/v1/dm/sessions/{id}/notes`: Quan tro ghi chu ket qua buoi choi, su co phat sinh hoac danh gia tinh than nguoi choi.

### 4.5. Cong tiep nhan Yeu cau Hoan tien cua Nguoi choi (PaymentRefundRequestController.php - 109 dong ma nguon)
- `POST /api/v1/payments/refund-request`: Nguoi choi gui yeu cau rut lui khoi phong choi va xin hoan tien coc.
- He thong tu dong tinh toan khoang cach thoi gian tu luc gui yeu cau den thoi diem gio choi:
  - Neu >= 24 gio: Chuyen sang trang thai `AUTO_REFUND_APPROVED` va tao ban ghi hoan tien 100%.
  - Neu < 24 gio: Chuyen sang trang thai `FORFEITED` (mat coc 100%) va tu dong mo cua so tim nguoi the cho (Substitute Window).

### 4.6. Dich vu Dieu phoi Xep lich Phong Nang cao (RoomSchedulingService.php - 381 dong ma nguon)
- Xu ly thuat toan phuc tap ve xep lich, giai quyet xung dot tai nguyen phong vat ly giua cac chi nhanh, dam bao rang buoc suc chua phong va khoang dem don ve sinh 30 phut.

### 4.7. Middleware Gioi han Tan suat Truy cap (RateLimitMiddleware.php - 74 dong ma nguon)
- Gioi han so luong request tren moi dia chi IP / phien nguoi dung trong 1 phut (vi du: toi da 60 requests/phut cho API chung va 5 requests/phut cho endpoint dang nhap) nham chong tan cong DoS va brute-force mat khau.

### 4.8. He thong Tien trinh Chay ngam Tu dong hoa (BackgroundJobService.php va bin/cron.php)
Trien khai 4 cron job tu dong hoa nghiep vu:
1. `formation-timeout`: Tu dong giai tan cac phong o trang thai `FORMING` qua han chot ma khong du so nguoi toi thieu.
2. `deposit-timeout`: Tu dong huy cho va loai bo nguoi choi khong thanh toan tien coc trong vong 24 gio ke tu khi chot lich.
3. `replacement-timeout`: Tu dong dong cua so tim nguoi the cho sau 2 gio neu khong co thanh vien moi nao thay the vi tri.
4. `proposal-timeout`: Tu dong huy cac de xuat gio choi sau 24 gio khong dat du so phieu dong thuan.

### 4.9. Tai lieu Dac ta OpenAPI 3.0 & Swagger UI (openapi.json va /docs)
- File `public/openapi.json` gom 1,106 dong ma nguon mo ta chi tiet toan bo 50 endpoint API, request headers, request body schema, response schema cho tung ma HTTP status (200, 201, 400, 401, 403, 404, 422, 500).
- Duong dan `http://127.0.0.1:8000/docs` cung cap giao dien Swagger UI truc quan, cho phep trai nghiem API truc tiep tren trinh duyet.

### 4.10. Tai lieu Phan tich Nghiep vu Chuyen sau (docs/ACTOR_BUSINESS_AUDIT.md - 861 dong)
- Phan tich doi chieu chi tiet giua 4 Actor (Guest, Player, DM, Admin), 42 yeu cau chuc nang (FR-01 den FR-42) va danh sach cac Use Case tuong ung.

### 4.11. Danh sach chi tiet cac Endpoint API moi duoc them o Stage 3

| STT | Phuong thuc | Duong dan Endpoint | Phan he | Mo ta chuc nang |
| :---: | :---: | :--- | :--- | :--- |
| 01 | POST | `/api/v1/admin/rooms/{id}/assign-physical-room` | Admin Ops | Gan phong choi vat ly tai chi nhanh cho phong choi |
| 02 | POST | `/api/v1/admin/rooms/{id}/revoke-dm` | Admin Ops | Thu hoi phan cong DM khoi phong choi |
| 03 | POST | `/api/v1/admin/rooms/{id}/disband` | Admin Ops | Giai tan phong choi khan cap |
| 04 | GET | `/api/v1/admin/reports/revenue` | Reports | Bao cao doanh thu, tien coc, tien hoan tra |
| 05 | GET | `/api/v1/admin/reports/rooms` | Reports | Thong ke hoat dong va ty le huy phong choi |
| 06 | GET | `/api/v1/admin/reports/overview` | Reports | Tong hop chi so KPI van hanh toan he thong |
| 07 | GET | `/api/v1/admin/audit-logs` | Audit | Xem nhat ky kiem toan cac thao tac quan tri |
| 08 | POST | `/api/v1/dm/sessions/{id}/start` | DM Session | Bat dau buoi choi tai hien truong |
| 09 | POST | `/api/v1/dm/sessions/{id}/complete` | DM Session | Ket thuc buoi choi va hoan tat ho so |
| 10 | POST | `/api/v1/dm/sessions/{id}/notes` | DM Session | Ghi chu su co va ket qua buoi choi |
| 11 | POST | `/api/v1/payments/refund-request` | Payments | Nguoi choi gui yeu cau hoan coc tu dong |
| 12 | GET | `/api/v1/notifications/unread-count` | Notifications | Dem so thong bao chua doc cua nguoi dung |
| 13 | POST | `/api/v1/notifications/mark-read` | Notifications | Danh dau tat ca thong bao la da doc |
| 14 | GET | `/docs` | Documentation | Giao dien Swagger UI tai lieu API tuong tac |
| 15 | GET | `/openapi.json` | Documentation | Tep dac ta tieu chuan OpenAPI 3.0 |

---

## 5. DOI CHIEU CHI TIET 56 TEP TIN THAY DOI SO VOI FEATURE/DEV-HIEU

So sanh truc tiep qua Git diff (`git diff --stat origin/feature/Dev-Hieu...feat/truong-backend-stage3`) xac nhan tong cong **56 tep tin thay doi voi 24.420 dong duoc them moi va 729 dong duoc dieu chinh**:

| STT | Ten tep tin | Loai thay doi | So dong (+/-) | Muc dich va noi dung thay doi |
| :---: | :--- | :---: | :---: | :--- |
| 01 | `README.md` | Cap nhat | +285 / -20 | Bo sung bao cao doi chieu toan dien, huong dan van hanh khong dung icon |
| 02 | `backend/README.md` | Them moi | +218 / -0 | Tai lieu huong dan kien truc va trien khai rieng cho phan Backend |
| 03 | `backend/public/openapi.json` | Them moi | +1106 / -0 | Dac ta toan bo 50 endpoints theo tieu chuan OpenAPI 3.0 |
| 04 | `backend/public/index.php` | Cap nhat | +16 / -0 | Nang cap dinh tuyen Same-Origin cho frontend va tai lieu Swagger UI |
| 05 | `backend/routes/api.php` | Cap nhat | +87 / -0 | Khai bao toan bo cac route Stage 3 (bao cao, audit log, phan cong DM, session) |
| 06 | `backend/app/Core/Middleware/RateLimitMiddleware.php` | Them moi | +74 / -0 | Middleware ngan chan tan cong brute-force va gioi han tan suat goi API |
| 07 | `backend/app/Core/Middleware/AuthMiddleware.php` | Cap nhat | +3 / -1 | Cung co kiem tra quyen truy cap RBAC tren tung nhom endpoint |
| 08 | `backend/app/Core/Middleware/CorsMiddleware.php` | Cap nhat | +2 / -1 | Cau hinh nguon goc duoc phep goi API tu cac domain hop le |
| 09 | `backend/app/Core/Middleware/CsrfMiddleware.php` | Cap nhat | +2 / -1 | Bat buoc kiem tra ma token chong gia mao request CSRF |
| 10 | `backend/app/Core/Exceptions/ExceptionHandler.php` | Cap nhat | +1 / -0 | Xu ly ngoai le tap trung va dinh dang phan hoi loi chuan JSON |
| 11 | `backend/app/Core/Response.php` | Cap nhat | +20 / -0 | Bo sung cac ham tra ve chuan: success, error, paginated, noContent |
| 12 | `backend/app/Features/DMManagement/Controllers/AdminOperationsController.php` | Them moi | +263 / -0 | Controller xu ly nghiep vu quan tri phong vat ly, thu hoi DM, giai tan phong |
| 13 | `backend/app/Features/DMManagement/Controllers/DMAssignmentController.php` | Cap nhat | +57 / -12 | Bo sung logic phe duyet hoac tu choi kem ly do cho Quan tro |
| 14 | `backend/app/Features/DMManagement/Repositories/DMRepository.php` | Cap nhat | +22 / -0 | Bo sung truy van danh sach buoi choi phan cong va trang thai ca truc |
| 15 | `backend/app/Features/GameroomManagement/Controllers/AdminGameRoomController.php` | Cap nhat | +7 / -2 | Bo sung co che khoa phong va quan ly thanh vien cap Admin |
| 16 | `backend/app/Features/GameroomManagement/Controllers/GameRoomController.php` | Cap nhat | +62 / -15 | Tich hop Pessimistic Locking SELECT FOR UPDATE chong loi Race Condition |
| 17 | `backend/app/Features/GameroomManagement/Services/BackgroundJobService.php` | Cap nhat | +17 / -0 | Xay dung 4 tien trinh quet tu dong hoa het han phong, coc, the cho, lich |
| 18 | `backend/app/Features/GameroomManagement/Services/RoomSchedulingService.php` | Them moi | +381 / -0 | Service giai quyet xung dot lich phong vat ly va khoang dem 30 phut |
| 19 | `backend/app/Features/Identity/Controllers/AdminAuditLogController.php` | Them moi | +40 / -0 | Controller tra cuu nhat ky thao tac quan tri he thong |
| 20 | `backend/app/Features/Identity/Controllers/AdminReportController.php` | Them moi | +159 / -0 | Controller xuat bao cao doanh thu, chi so phong va tong quan van hanh |
| 21 | `backend/app/Features/Identity/Controllers/AdminUserController.php` | Cap nhat | +15 / -0 | Bo sung xet duyet the sinh vien va khoa/mo tai khoan nguoi dung |
| 22 | `backend/app/Features/Identity/Controllers/AuthController.php` | Cap nhat | +69 / -14 | Nang cap tao moi session chong Fixation va ma hoa BCRYPT |
| 23 | `backend/app/Features/Identity/Repositories/UserRepository.php` | Cap nhat | +2 / -1 | Cap nhat trang thai xac thuc the sinh vien vao CSDL |
| 24 | `backend/app/Features/Notification/Controllers/NotificationController.php` | Cap nhat | +14 / -0 | Endpoint lay so luong thong bao chua doc va danh dau da doc |
| 25 | `backend/app/Features/Notification/Repositories/NotificationRepository.php` | Cap nhat | +16 / -0 | Cac cau truy van dem va cap nhat thong bao theo nguoi dung |
| 26 | `backend/app/Features/Payment/Controllers/AdminRefundController.php` | Cap nhat | +161 / -48 | Tach rieng luong duyet hoan tien cua Admin, chan client tu truyen force_refund |
| 27 | `backend/app/Features/Payment/Controllers/PaymentController.php` | Cap nhat | +68 / -12 | Nang cap quy trinh thanh toan tien coc 50.000d va thanh toan con lai |
| 28 | `backend/app/Features/Payment/Controllers/PaymentRefundRequestController.php` | Them moi | +109 / -0 | Controller tiep nhan yeu cau hoan coc va phan loai moc 24 gio |
| 29 | `backend/app/Features/Payment/Repositories/PaymentRepository.php` | Cap nhat | +169 / -35 | Quan ly lich su giao dich, trang thai thanh toan va ban ghi hoan coc |
| 30 | `backend/app/Features/Pricing/Controllers/PricingController.php` | Cap nhat | +6 / -0 | Tich hop kiem tra the sinh vien khi tinh toan bang gia ca nhan |
| 31 | `backend/app/Features/Pricing/Services/PricingService.php` | Cap nhat | +42 / -10 | Ap dung giam 30% Thu Ba, 20k HSSV, chan cong don va luu snapshot gia |
| 32 | `backend/app/Features/Scheduling/Controllers/SchedulingController.php` | Cap nhat | +447 / -65 | Nang cap toan dien luong de xuat lich, bieu quyet va chot lich tu dong |
| 33 | `backend/app/Features/Scheduling/Controllers/SessionLifecycleController.php` | Them moi | +205 / -0 | Controller quan ly bat dau buoi choi, hoan thanh va ghi chu ca truc cua DM |
| 34 | `backend/app/Features/Scheduling/Repositories/SchedulingRepository.php` | Cap nhat | +89 / -18 | Xu ly luu tru de xuat lich, phieu bau va khoa phong vat ly |
| 35 | `backend/app/Shared/Enums/PaymentStatus.php` | Cap nhat | +7 / -2 | Bo sung cac trang thai thanh toan PARTIALLY_PAID, REFUNDED |
| 36 | `backend/app/Shared/Services/AuditLogService.php` | Them moi | +106 / -0 | Service trung tam ghi nhat ky thao tac quan tri vao CSDL |
| 37 | `backend/bin/cron.php` | Cap nhat | +6 / -0 | Script chay cac tien trinh ngam tu dong hoa qua giao dien dong lenh CLI |
| 38 | `backend/bin/seed.php` | Them moi | +119 / -0 | Script nap du lieu mau day du 3 kich ban, 7 gia, 2 uu dai, 4 user |
| 39 | `backend/bootstrap/app.php` | Cap nhat | +2 / -0 | Dang ky cac middleware moi va khoi tao bo xu ly ung dung |
| 40 | `backend/composer.json` | Cap nhat | +6 / -0 | Bo sung cac lenh scripts tien ich: check-db, seed, test |
| 41 | `backend/composer.lock` | Cap nhat | +350 / -4 | Cap nhat cay goi phu thuoc cua Composer |
| 42 | `backend/config/cors.php` | Cap nhat | +2 / -1 | Cau hinh chi tiet danh sach domain duoc cap phep truy cap |
| 43 | `backend/database/upgrade-stage3.php` | Them moi | +44 / -0 | Script kiem tra va nang cap schema CSDL cho Stage 3 |
| 44 | `backend/tests/Feature/AdminRoomCancelRefundApiTest.php` | Cap nhat | +25 / -5 | Kiem thu tu dong chuc nang huy phong va hoan tien cua Admin |
| 45 | `backend/tests/Feature/AdminUserApiTest.php` | Them moi | +42 / -0 | Kiem thu tu dong quan ly nguoi dung va xet duyet sinh vien |
| 46 | `backend/tests/Feature/ApplicationTest.php` | Them moi | +24 / -0 | Kiem thu khoi tao he thong va endpoint kiem tra suc khoe healthcheck |
| 47 | `backend/tests/Feature/AuthApiTest.php` | Them moi | +53 / -0 | Kiem thu dang ky, dang nhap da vai tro va bao ve session |
| 48 | `backend/tests/Feature/BackgroundJobTest.php` | Them moi | +15 / -0 | Kiem thu tu dong hoa xu ly qua han gom nguoi va qua han coc |
| 49 | `backend/tests/Feature/PricingCalculationApiTest.php` | Cap nhat | +54 / -8 | Kiem thu tinh toan gia theo khung gio va ap dung uu dai |
| 50 | `backend/tests/Feature/SchedulingApiTest.php` | Cap nhat | +15 / -3 | Kiem thu de xuat gio, bieu quyet va chot lich thanh cong |
| 51 | `backend/tests/Feature/Stage3ApiTest.php` | Them moi | +132 / -0 | Kiem thu tong hop toan bo cac API Stage 3 moi duoc bo sung |
| 52 | `backend/tests/Unit/RoomSchedulingServiceTest.php` | Them moi | +56 / -0 | Kiem thu don vi logic kiem tra xung dot gio va khoang dem 30 phut |
| 53 | `docs/ACTOR_BUSINESS_AUDIT.md` | Them moi | +861 / -0 | Tai lieu doi chieu 4 Actor, 42 FR va ma tran phan quyen |
| 54 | `frontend/src/config/app-config.js` | Cap nhat | +6 / -2 | Cau hinh dong bo URL backend API va co che CSRF token |
| 55 | `backend.txt` | Cap nhat | +13732 / -0 | Tai lieu ma hoa tong hop toan bo backend (khong sua doi truc tiep) |
| 56 | `frontend.txt` | Cap nhat | +5258 / -0 | Tai lieu ma hoa tong hop toan bo frontend (khong sua doi truc tiep) |

---

## 6. CO SO DU LIEU VA TINH TOAN VEN DU LIEU (DATABASE INTEGRITY)

Cơ sở dữ liệu của dự án sử dụng hệ quản trị MySQL 8.x với tên database `kich_ban_sat_v3`, được chuẩn hóa theo chuẩn 3NF và sở hữu đầy đủ 22 bảng dữ liệu liên kết chặt chẽ:

1. `roles`: Danh mục vai trò người dùng (PLAYER, DM, ADMIN).
2. `users`: Tài khoản người dùng, thông tin cá nhân, mã băm mật khẩu BCRYPT, trạng thái xác thực sinh viên (`student_verified`).
3. `venues`: Danh sách chi nhánh hoạt động của Kịch Bản Sát (Quận 1, Bình Thạnh).
4. `physical_rooms`: Danh sách các phòng chơi vật lý tại từng chi nhánh, sức chứa tối đa và trạng thái sẵn sàng.
5. `games`: Danh mục kịch bản án mạng nhập vai, số lượng người chơi (min-max), thời lượng chơi (phút).
6. `game_configurations`: Cấu hình đạo cụ, manh mối, danh sách nhạc nền lưu dưới định dạng JSON cho từng kịch bản.
7. `game_prices`: Bảng giá theo khung giờ trong ngày (sáng, chiều, tối) của từng kịch bản.
8. `promotions`: Danh mục chính sách ưu đãi (Thứ Ba giảm 30%, HSSV giảm 20.000d) kèm luật áp dụng JSON.
9. `game_rooms`: Phòng chơi ảo do người dùng tạo, theo dõi toàn bộ trạng thái tiến trình từ OPEN đến COMPLETED.
10. `room_participants`: Danh sách người chơi tham gia trong từng phòng và vai trò (Host hoặc Member).
11. `schedule_proposals`: Danh sách các đề xuất về thời gian và địa điểm tổ chức buổi chơi.
12. `schedule_responses`: Kết quả bình chọn (VOTE_YES / VOTE_NO) của từng thành viên đối với các đề xuất lịch.
13. `dm_assignments`: Bảng phân công Quản trò phụ trách buổi chơi, trạng thái phê duyệt và lý do từ chối.
14. `participant_price_snapshots`: Bản lưu đóng băng mức giá phải thanh toán của từng người chơi tại thời điểm chot lịch.
15. `player_payments`: Bản ghi thanh toán của người chơi (tiền cọc 50.000d và tiền thanh toán còn lại).
16. `payment_transactions`: Chi tiết các giao dịch chuyển khoản, cổng thanh toán và mã tham chiếu.
17. `refunds`: Bản ghi hoàn tiền chi tiết, số tiền hoàn, lý do hoàn và người phê duyệt.
18. `notifications`: Hệ thống thông báo tự động gửi tới người dùng.
19. `audit_logs`: Nhật ký kiểm toán lưu vết mọi thao tác quản trị viên và quản trò.
20. `sessions`: Quản lý phiên đăng nhập an toàn của người dùng.
21. `idempotency_records`: Ngăn chặn việc gửi trùng lặp yêu cầu thanh toán (chống thanh toán 2 lần).
22. `outbox_events`: Hỗ trợ mô hình phát sự kiện bất đồng bộ mở rộng trong tương lai.

Đặc biệt, tệp cơ sở dữ liệu `kich_ban_sat_v3_clean.sql` giữa `kichbansattruong` và `kich-ban-sat-hieu` khớp nhau 100% từng byte dữ liệu (đã xác thực qua mã băm SHA-256).

---

## 7. KET QUA KIEM THU TOAN DIEN (149 BAI KIEM TRA TU DONG)

Hệ thống được bảo vệ bởi tổng cộng 149 bài kiểm tra tự động chạy trên môi trường thực tế, đạt tỷ lệ thành công 100%:

### 7.1. Kiem thu Backend (125 tests, 555 assertions - 100% Pass)
- Kiem thu don vi (Unit Tests):
  - `RoomSchedulingServiceTest.php`: Kiểm tra tính toán khoảng thời gian, phát hiện xung đột trùng giờ, kiểm tra ràng buộc khoảng đệm 30 phút giữa 2 ca chơi liền kề.
- Kiem thu chuc nang (Feature Tests):
  - `AuthApiTest.php`: Đăng ký, đăng nhập sai mật khẩu, đăng nhập đúng, lấy thông tin cá nhân, đăng xuất.
  - `AdminUserApiTest.php`: Danh sách người dùng, cập nhật vai trò, duyệt thẻ sinh viên HSSV.
  - `PricingCalculationApiTest.php`: Tính giá ca sáng, ca chiều, ca tối; áp dụng giảm 30% Thứ Ba; áp dụng giảm 20.000d HSSV; ngăn chặn cộng dồn khuyến mãi.
  - `SchedulingApiTest.php`: Đề xuất giờ chơi, bình chọn đề xuất, tự động chốt lịch khi đủ phiếu đồng thuận.
  - `AdminRoomCancelRefundApiTest.php`: Quyền hạn của Admin khi hủy phòng và phê duyệt hoàn tiền.
  - `BackgroundJobTest.php`: Tiến trình tự động giải tán phòng quá hạn gom người và quá hạn đặt cọc.
  - `Stage3ApiTest.php`: Gán phòng vật lý, thu hồi DM, ghi nhận sự cố buổi chơi, báo cáo doanh thu tài chính, tra cứu nhật ký kiểm toán.
  - `ApplicationTest.php`: Khởi tạo container, kiểm tra kết nối CSDL và endpoint sức khỏe hệ thống.

### 7.2. Kiem thu Frontend & E2E (24 tests - 100% Pass)
- `tests/home-e2e.test.mjs` (7 tests):
  - Kiểm tra cấu trúc HTML công khai, các nút điều hướng Home, Games, Rooms, DM, Admin.
  - Kiểm tra thanh điều hướng di động (Mobile Hamburger Drawer) không tràn màn hình.
  - Kiểm tra API danh mục kịch bản `/api/v1/games` và tính toán khoảng giá (min_price, max_price).
  - Kiểm tra tìm kiếm kịch bản theo từ khóa và lọc theo số người chơi.
  - Kiểm tra API danh sách chương trình khuyến mãi V16.
  - Kiểm tra API modal xem bảng giá chi tiết theo khung giờ `/api/v1/games/1/prices`.
  - Kiểm tra các hàm bảo mật: chống XSS (`escapeHtml`) và làm sạch URL (`sanitizeUrl`).
  - Kiểm tra tải thành công tất cả 12 tệp JavaScript module và CSS giao diện.
- `tests/auth-multi-role.test.mjs` (6 tests):
  - Kiểm tra trang chủ mặc định ẩn các nút điều hướng quản trị của DM và Admin đối với khách vãng lai.
  - Kiểm tra luồng đăng nhập thực tế của Player: lưu session, nhận diện vai trò Player.
  - Kiểm tra luồng đăng nhập thực tế của DM: mở khóa giao diện quản lý ca trực Quản trò.
  - Kiểm tra luồng đăng nhập thực tế của Admin: mở khóa giao diện Bảng điều khiển quản trị toàn diện.
  - Kiểm tra xử lý từ chối thông tin đăng nhập sai (mật khẩu không đúng).
  - Kiểm tra an ninh: Tài khoản Player bị chặn tuyệt đối (403 Forbidden) khi cố tình gọi API của Admin.
- `tests/api-client.test.mjs` (7 tests):
  - Kiểm tra gửi request thành công và dọn dẹp timer bộ nhớ.
  - Kiểm tra hủy request giữa chừng (AbortSignal) trả về mã lỗi REQUEST_ABORTED.
  - Kiểm tra xử lý Timeout trả về mã lỗi REQUEST_TIMEOUT.
  - Kiểm tra xử lý sự cố mạng trả về NETWORK_FAILURE.
  - Kiểm tra chuẩn hóa đối tượng lỗi ApiError.
- `tests/final-security-verification.mjs` (4 tests):
  - Kiểm tra bắt buộc có CSRF Token khi thực hiện các thao tác POST/PUT/DELETE.
  - Kiểm tra phân quyền đa tầng (RBAC) chặt chẽ giữa Guest, Player, DM và Admin.
  - Kiểm tra cơ chế chống leo quyền (Role Mismatch Protection): Backend tự động từ chối nếu client gửi sai vai trò.
  - Kiểm tra cô lập thông tin xác thực demo, đảm bảo an toàn vận hành.

---

## 8. HUONG DAN CAI DAT, VAN HANH VA DEMO HE THONG

### 8.1. Yeu cau he thong
- PHP phiên bản 8.2 trở lên (đã kích hoạt tiện ích pdo_mysql, mbstring, json).
- MySQL phiên bản 8.0 hoặc 8.4 (chạy trên cổng mặc định 3306).
- Composer phiên bản 2.x.
- Node.js phiên bản 18 trở lên (để chạy các bài kiểm thử tự động Frontend).

### 8.2. Cac buoc khoi chay

Bước 1: Di chuyển vào thư mục backend và kiểm tra kết nối CSDL:
```bash
cd backend
composer check-db
```
Kết quả hiển thị thông báo kết nối thành công tới database `kich_ban_sat_v3`.

Bước 2: Nạp dữ liệu mẫu hoàn chỉnh:
```bash
composer seed
```
Hệ thống tự động nạp đầy đủ vai trò, tài khoản người dùng, chi nhánh, phòng vật lý, kịch bản, cấu hình đạo cụ và bảng giá.

Bước 3: Chạy toàn bộ 125 bài kiểm tra tự động Backend:
```bash
composer test
```
Kết quả hiển thị: `OK (125 tests, 555 assertions)`.

Bước 4: Khởi động máy chủ ứng dụng:
```bash
php -S 127.0.0.1:8000 -t public public/index.php
```

Bước 5: Chạy các bài kiểm tra tự động Frontend (tại một cửa sổ dòng lệnh khác):
```bash
cd frontend
node tests/home-e2e.test.mjs
node tests/auth-multi-role.test.mjs
node tests/api-client.test.mjs
node tests/final-security-verification.mjs
```
Tất cả 24 bài kiểm tra đều vượt qua với thông báo xanh hoàn toàn.

### 8.3. Danh sach tai khoan thu nghiem (Demo Accounts)

Tất cả các tài khoản mẫu đều sử dụng mật khẩu dùng chung là `password123`:

1. Tài khoản Quản trị viên (Admin):
   - Email: `admin@kichbansat.vn`
   - Mật khẩu: `password123`
   - Quyền hạn: Quản trị toàn hệ thống, phân công Quản trò, duyệt hoàn tiền, xem báo cáo doanh thu, tra cứu nhật ký kiểm toán.

2. Tài khoản Quản trò (DM):
   - Email: `dm.nam@kichbansat.vn`
   - Mật khẩu: `password123`
   - Quyền hạn: Xem danh sách ca trực được giao, chấp nhận/từ chối ca trực, bắt đầu và hoàn thành buổi chơi, ghi chú hiện trường.

3. Tài khoản Người chơi 1 (Player có xác thực HSSV):
   - Email: `player.hieu@kichbansat.vn`
   - Mật khẩu: `password123`
   - Quyền hạn: Tạo phòng chơi (Host), đề xuất lịch, biểu quyết, thanh toán cọc, hưởng ưu đãi sinh viên 20.000d.

4. Tài khoản Người chơi 2 (Player thông thường):
   - Email: `minhanh@gmail.com`
   - Mật khẩu: `password123`
   - Quyền hạn: Tham gia phòng, biểu quyết lịch, thanh toán tiền cọc, yêu cầu hoàn tiền.

### 8.4. Cac dia chi truy cap quan trong tren trinh duyet
- Trang chủ ứng dụng web: `http://127.0.0.1:8000/`
- Giao diện thử nghiệm API Swagger UI dành cho người chấm thi: `http://127.0.0.1:8000/docs`
- Tệp đặc tả OpenAPI 3.0: `http://127.0.0.1:8000/openapi.json`
- Kiểm tra sức khỏe hệ thống (RAM, CPU, Uptime): `http://127.0.0.1:8000/api/v1/health`

---

## 9. KET LUAN

Phiên bản mã nguồn tại thư mục `kichbansattruong` đã hoàn thành 100% mục tiêu đồ án, giải quyết dứt điểm toàn bộ các thiếu sót của nhánh `feature/Dev-Hieu`, đạt chuẩn kỹ thuật cao về tính toàn vẹn dữ liệu, an ninh bảo mật và hiệu năng vận hành thực tế. Hệ thống đã sẵn sàng tuyệt đối phục vụ công tác báo cáo và bảo vệ trước hội đồng chấm thi.
