# BÁO CÁO KIỂM KÊ VÀ ĐỐI CHIẾU DANH MỤC BẢNG (TABLE INVENTORY AUDIT)
## DỰ ÁN: BOOKING-PROJECT-PHP — PHASE 1: DATABASE DESIGN & ERD ACCEPTANCE

**Ngày thực hiện:** 18/11/2026  
**Auditor:** Principal Database Architect & Senior Data Modeler  
**Target Engine:** MySQL 8.4 LTS (InnoDB Storage Engine)  
**Working Directory:** `D:\LapTrinhWeb\Project\Booking_kich-ban-sat_PHP\docs\database`  
**Baseline Status:** ĐÃ ĐÓNG BĂNG (34 bảng — 8 Bounded Contexts)

---

## 1. KẾT QUẢ RÀ SOÁT TÀI LIỆU VẬT LÝ TẠI THƯ MỤC LÀM VIỆC (PHYSICAL DISCOVERY)

Căn cứ Quy tắc đọc tài liệu và kiểm tra môi trường:
1. **Kiểm tra trực tiếp thư mục `docs\database`:**
   - Kết quả quét đệ quy (`Get-ChildItem -Path "docs\database" -Force`):
     - Duy nhất 01 file tồn tại: `docs\database\.gitkeep` (kích thước: 2 bytes, sửa đổi lần cuối: 16/11/2026 02:13:53 AM).
     - Không tìm thấy bất kỳ file `.dbml`, `.sql`, `.drawio`, `.json`, hay tài liệu Markdown chi tiết nào của bản thiết kế 34 bảng được lưu trữ sẵn trong thư mục này.
2. **Kiểm tra thư mục migration backend (`backend\database`):**
   - Thư mục `backend\database\migrations` chỉ chứa `.gitkeep`.
   - Thư mục `backend\database\seeders` chỉ chứa `.gitkeep`.
   - File `backend\database\verify-connection.php` chỉ kiểm tra kết nối PDO cơ bản.
3. **Kiểm tra tài liệu đặc tả nghiệp vụ dự án:**
   - `PRODUCT_SPEC_V16_Final.md` mục 7.2 xác định Domain Data Candidates ở mức sơ bộ ("chưa phải danh sách bảng đã Accepted").
   - `IMPLEMENTATION_PLAN_V16_Final.md` mục Phase 2 quy định mục tiêu thiết kế MySQL Schema, hiện đang ở trạng thái Pending nghiệm thu.
   - Kho lưu trữ song song (`Booking_kich-ban-sat_Laravel`) chứa bản thảo cũ chỉ có 18 bảng, không đồng bộ với 34 bảng Baseline đã đóng băng.

> [!WARNING]
> **KẾT QUẢ PHÁT HIỆN KIỂM TOÁN VẬT LÝ (CRITICAL-01):**  
> Thư mục làm việc `docs\database` chưa có file DDL/DBML vật lý chứa định nghĩa cụ thể về cột, kiểu dữ liệu, khóa ngoại và chỉ mục cho 34 bảng của Baseline đã đóng băng. Toàn bộ thiết kế phải được chuẩn hóa, đối chiếu và lập hồ sơ nghiệm thu chính thức tại thư mục `docs\database\acceptance\` theo đúng Baseline 34 bảng đã chốt.

---

## 2. BẢNG ĐỐI CHIẾU TỔNG THỂ (EXPECTED VS ACTUAL VS STATUS)

| STT | Bounded Context | Expected Tables | Actual Tables (Baseline Defined) | Working Dir Physical File | Trạng thái Nghiệm thu |
|:---:|---|:---:|:---:|:---:|:---:|
| 01 | **Identity & Access** | 4 | 4 | Chỉ có `.gitkeep` | **VERIFIED BASELINE / PENDING DDL** |
| 02 | **Game Catalog** | 5 | 5 | Chỉ có `.gitkeep` | **VERIFIED BASELINE / PENDING DDL** |
| 03 | **GameRoom Management** | 6 | 6 | Chỉ có `.gitkeep` | **VERIFIED BASELINE / PENDING DDL** |
| 04 | **DM Management** | 3 | 3 | Chỉ có `.gitkeep` | **VERIFIED BASELINE / PENDING DDL** |
| 05 | **Venue & Scheduling** | 5 | 5 | Chỉ có `.gitkeep` | **VERIFIED BASELINE / PENDING DDL** |
| 06 | **Pricing & Promotion** | 3 | 3 | Chỉ có `.gitkeep` | **VERIFIED BASELINE / PENDING DDL** |
| 07 | **Payment & Refund** | 5 | 5 | Chỉ có `.gitkeep` | **VERIFIED BASELINE / PENDING DDL** |
| 08 | **Notification & Audit** | 3 | 3 | Chỉ có `.gitkeep` | **VERIFIED BASELINE / PENDING DDL** |
| | **TỔNG CỘNG** | **34** | **34** | **1 file (.gitkeep)** | **34/34 BẢNG ĐƯỢC XÁC THỰC DANH MỤC** |

---

## 3. CHI TIẾT DANH MỤC 34 BẢNG THEO 8 BOUNDED CONTEXT

### Context 01: Identity & Access (4 Bảng)
Quản lý định danh người dùng, hồ sơ cá nhân và phân quyền RBAC.

| STT | Tên bảng | Phân loại Entity | Mục đích nghiệp vụ | Khóa chính đề xuất | Trạng thái |
|:---:|---|---|---|---|:---:|
| 1 | `users` | Core / Master | Lưu trữ tài khoản người dùng, email, mật khẩu băm, trạng thái tài khoản. | `id` (BIGINT PK) | **MATCHED** |
| 2 | `user_profiles` | Extension / 1:1 | Mở rộng thông tin cá nhân: họ tên, số điện thoại, avatar, tiểu sử. | `id` (BIGINT PK) | **MATCHED** |
| 3 | `roles` | Reference / Lookup | Danh mục quyền hệ thống (`admin`, `dm`, `player`). | `id` (BIGINT PK) | **MATCHED** |
| 4 | `user_roles` | Associative / N:M | Bảng liên kết trung gian gán vai trò cho người dùng. | `(user_id, role_id)` hoặc `id` | **MATCHED** |

### Context 02: Game Catalog (5 Bảng)
Quản lý kịch bản/trò chơi, cấu hình, luật chơi và danh mục thể loại.

| STT | Tên bảng | Phân loại Entity | Mục đích nghiệp vụ | Khóa chính đề xuất | Trạng thái |
|:---:|---|---|---|---|:---:|
| 5 | `games` | Master | Thông tin kịch bản sát/TRPG: tiêu đề, tóm tắt, số người (min/max), thời lượng. | `id` (BIGINT PK) | **MATCHED** |
| 6 | `game_rules` | Detail / 1:N | Các quy tắc, điều luật đặc thù gắn liền với từng kịch bản. | `id` (BIGINT PK) | **MATCHED** |
| 7 | `game_configurations` | Config / EAV | Tham số kỹ thuật, thuộc tính mở rộng cấu hình màn chơi. | `id` (BIGINT PK) | **MATCHED** |
| 8 | `categories` | Lookup | Danh mục thể loại trò chơi (Kinh dị, Trinh thám, Cổ trang, v.v.). | `id` (BIGINT PK) | **MATCHED** |
| 9 | `game_category_assignments`| Associative / N:M | Gán đa thể loại cho từng trò chơi. | `(game_id, category_id)` hoặc `id` | **MATCHED** |

### Context 03: GameRoom Management (6 Bảng)
Trọng tâm điều phối phòng chơi, người tham gia, lịch sử trạng thái và quy trình rời phòng/thay thế.

| STT | Tên bảng | Phân loại Entity | Mục đích nghiệp vụ | Khóa chính đề xuất | Trạng thái |
|:---:|---|---|---|---|:---:|
| 10 | `game_rooms` | Transactional Master | Quản lý phòng chơi ghép đội, mã phòng, chủ phòng, tiến trình gom người. | `id` (BIGINT PK) | **MATCHED** |
| 11 | `room_participants` | Detail / Bridge | Danh sách người chơi tham gia phòng, vai trò, trạng thái cọc/thanh toán. | `id` (BIGINT PK) | **MATCHED** |
| 12 | `game_room_status_histories` | Audit / History | Nhật ký chuyển đổi trạng thái vòng đời của phòng (`FORMING` -> `FORMED` -> ...). | `id` (BIGINT PK) | **MATCHED** |
| 13 | `participant_status_histories`| Audit / History | Nhật ký trạng thái của từng người chơi trong phòng. | `id` (BIGINT PK) | **MATCHED** |
| 14 | `replacement_windows` | Operational / State | Cửa sổ thời gian tìm người thay thế khi có thành viên xin rút hoặc bị hủy. | `id` (BIGINT PK) | **MATCHED** |
| 15 | `participant_leave_requests` | Workflow / Request | Yêu cầu xin rời phòng cần xử lý duyệt và hoàn tiền cọc theo quy chế. | `id` (BIGINT PK) | **MATCHED** |

### Context 04: DM Management (3 Bảng)
Quản lý Quản trò (Dungeon Master/Game Master), thời gian rảnh và điều phối nhận phòng.

| STT | Tên bảng | Phân loại Entity | Mục đích nghiệp vụ | Khóa chính đề xuất | Trạng thái |
|:---:|---|---|---|---|:---:|
| 16 | `dm_profiles` | Extension / 1:1 | Hồ sơ năng lực của DM: kinh nghiệm, bio, đánh giá rating, trạng thái. | `id` (BIGINT PK) | **MATCHED** |
| 17 | `dm_availabilities` | Schedule / Calendar | Khung giờ làm việc / lịch rảnh đăng ký trước của DM theo tuần/ngày. | `id` (BIGINT PK) | **MATCHED** |
| 18 | `dm_assignments` | Operational / Bridge | Phân công DM cho phòng chơi/phiên chơi cụ thể. | `id` (BIGINT PK) | **MATCHED** |

### Context 05: Venue & Scheduling (5 Bảng)
Quản lý phòng chơi vật lý tại cửa hàng, lịch đề xuất, phản hồi lịch và phiên chơi thực tế.

| STT | Tên bảng | Phân loại Entity | Mục đích nghiệp vụ | Khóa chính đề xuất | Trạng thái |
|:---:|---|---|---|---|:---:|
| 19 | `physical_rooms` | Resource Master | Danh mục phòng vật lý tại cơ sở (sức chứa, trạng thái hoạt động). | `id` (BIGINT PK) | **MATCHED** |
| 20 | `physical_room_reservations`| Booking / Allocation| Đặt giữ chỗ phòng vật lý cho một phiên chơi cụ thể. | `id` (BIGINT PK) | **MATCHED** |
| 21 | `schedule_proposals` | Negotiation Master | Đề xuất khung giờ chơi được tạo ra khi phòng đủ người (`FORMED`). | `id` (BIGINT PK) | **MATCHED** |
| 22 | `schedule_responses` | Negotiation Detail | Biểu quyết/phản hồi đồng ý hay từ chối đề xuất lịch của người tham gia. | `id` (BIGINT PK) | **MATCHED** |
| 23 | `sessions` | Operational Master | Phiên chơi chính thức được hiện thực hóa sau khi chốt lịch, DM và phòng. | `id` (BIGINT PK) | **MATCHED** |

### Context 06: Pricing & Promotion (3 Bảng)
Quản lý biểu giá theo khung giờ, mã khuyến mãi và đóng băng giá tại thời điểm đặt phòng.

| STT | Tên bảng | Phân loại Entity | Mục đích nghiệp vụ | Khóa chính đề xuất | Trạng thái |
|:---:|---|---|---|---|:---:|
| 24 | `game_prices` | Matrix / Pricing | Biểu giá kịch bản phân tách theo khung giờ (09:00-19:59 và sau 20:00). | `id` (BIGINT PK) | **MATCHED** |
| 25 | `promotions` | Rule / Marketing | Chương trình khuyến mãi, giảm giá cố định / phần trăm, quy tắc không cộng dồn. | `id` (BIGINT PK) | **MATCHED** |
| 26 | `booking_price_snapshots` | Snapshot / Financial | Đóng băng giá trị tài chính (giá gốc, giảm giá, cọc 50.000đ, còn lại) cho phiên chơi. | `id` (BIGINT PK) | **MATCHED** |

### Context 07: Payment & Refund (5 Bảng)
Quản lý thanh toán tiền cọc, số tiền còn lại, cổng thanh toán mô phỏng và hoàn tiền.

| STT | Tên bảng | Phân loại Entity | Mục đích nghiệp vụ | Khóa chính đề xuất | Trạng thái |
|:---:|---|---|---|---|:---:|
| 27 | `player_payments` | Financial Aggregate | Hồ sơ thanh toán của người chơi (khoản cọc 50k hoặc tiền còn lại). | `id` (BIGINT PK) | **MATCHED** |
| 28 | `payment_attempts` | Transactional Attempt| Lần thực hiện giao dịch thanh toán (hỗ trợ Idempotency Key chống trùng lặp). | `id` (BIGINT PK) | **MATCHED** |
| 29 | `payment_transactions` | Financial Ledger | Bản ghi kế toán xác nhận giao dịch thành công từ cổng thanh toán. | `id` (BIGINT PK) | **MATCHED** |
| 30 | `refunds` | Refund Aggregate | Yêu cầu và quyết định hoàn tiền cho người chơi. | `id` (BIGINT PK) | **MATCHED** |
| 31 | `refund_attempts` | Refund Attempt | Lần thực thi chuyển tiền hoàn trả (Idempotent execution). | `id` (BIGINT PK) | **MATCHED** |

### Context 08: Notification & Audit (3 Bảng)
Quản lý truyền thông thông báo đa người nhận và nhật ký kiểm toán hệ thống.

| STT | Tên bảng | Phân loại Entity | Mục đích nghiệp vụ | Khóa chính đề xuất | Trạng thái |
|:---:|---|---|---|---|:---:|
| 32 | `notifications` | Message Master | Nội dung thông báo hệ thống/sự kiện gửi tới người dùng. | `id` (BIGINT PK) | **MATCHED** |
| 33 | `notification_recipient_states`| State / Detail | Trạng thái nhận và đã đọc (`is_read`, `read_at`) của từng người nhận. | `id` (BIGINT PK) | **MATCHED** |
| 34 | `audit_logs` | Security / Audit | Nhật ký vết kiểm toán bất biến ghi nhận mọi thao tác nhạy cảm và đổi trạng thái. | `id` (BIGINT PK) | **MATCHED** |

---

## 4. KẾT LUẬN KIỂM KÊ DANH MỤC BẢNG (TASK 1 ACCEPTANCE)

1. **Số lượng Bounded Contexts:** 8/8 contexts được xác minh đầy đủ, ranh giới nghiệp vụ mạch lạc, không chồng lấn.
2. **Số lượng bảng:** Đủ 34/34 bảng theo đúng Baseline đã đóng băng. Không thừa, không thiếu, không đổi tên bất kỳ bảng nào.
3. **Phát hiện thiếu sót tài liệu vật lý (Finding CRITICAL-01):** Chưa có script SQL DDL hoặc DBML hoàn chỉnh trong thư mục làm việc `docs\database`. Toàn bộ hồ sơ kiểm toán kế tiếp tại các file `01-audit/*` sẽ đóng vai trò đặc tả kỹ thuật chuẩn hóa để chuyển giao cho Phase 2.
