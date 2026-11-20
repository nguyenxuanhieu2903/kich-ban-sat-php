# BÁO CÁO THỰC THI VÀ XÁC MINH SEED DỮ LIỆU
## (DATABASE SEEDING IMPLEMENTATION & VERIFICATION REPORT)
### DỰ ÁN: WEBSITE BOOKING – KỊCH BẢN SÁT (JUBENSHA / MURDER MYSTERY) V16

- **Ngày thực hiện:** 20/11/2026
- **Auditor:** Senior Database Architect & Backend Engineer
- **Công cụ:** Phinx Seeders v0.16.12
- **Cơ sở dữ liệu:** MySQL 8.0.44 Community Server, `booking_project_php`
- **Tài liệu:** `docs/database/SEED_REPORT.md`

---

## 1. NGUYÊN TẮC AN TOÀN VÀ KIỂM SOÁT DỮ LIỆU SEED (SECTION 4.4 COMPLIANCE)

Tuân thủ nghiêm ngặt các quy định an toàn dữ liệu:
1. **Không tạo dữ liệu giả làm dữ liệu thật:** Tuyệt đối không tự bịa đặt danh mục kịch bản (Game Catalog), giá vé, mã khuyến mãi hay tài khoản admin khi chưa có danh mục menu gốc được đối soát chính thức.
2. **Tính lũy thừa (Idempotency):** Chạy seeder một lần hay nhiều lần đều mang lại kết quả dữ liệu nhất quán, không sinh dòng trùng lặp hay gây lỗi khóa Unique.
3. **Sử dụng Stable Key:** Mọi bản ghi tham chiếu sử dụng khóa tự nhiên ổn định (`roles.name`, `categories.slug`, `physical_rooms.code`) để kiểm tra tồn tại trước khi chèn/cập nhật.
4. **Bảo mật tuyệt đối:** Không seed mật khẩu mặc định yếu (như `123456`, `password`), không đưa token, khóa bí mật hay credential vào repository.

---

## 2. DANH MỤC SEEDERS ĐÃ TRIỂN KHAI

### 2.1. `RoleSeeder.php` (Danh mục vai trò hệ thống)
Khởi tạo 3 vai trò phân quyền cốt lõi của hệ thống (RBAC):
- `ADMIN`: Quản trị viên (Toàn quyền quản trị và điều phối hệ thống).
- `DM`: Quản trò / Dungeon Master (Quản lý phiên chơi, kịch bản và dẫn truyện).
- `PLAYER`: Người chơi (Người dùng tham gia ghép đội, đặt phòng và chơi game).

### 2.2. `ReferenceDataSeeder.php` (Dữ liệu tham chiếu chuẩn)
1. **Thể loại kịch bản (`categories`):**
   - `trinh-tham-suy-luan`: Trinh thám / Suy luận (Tập trung tìm manh mối, giải mã án mạng logic).
   - `kinh-di-giat-gan`: Kinh dị / Giật gân (Không khí u ám, yếu tố tâm linh hoặc rùng rợn).
   - `co-trang-cung-dau`: Cổ trang / Cung đấu (Bối cảnh hoàng cung, gia tộc lịch sử).
   - `tinh-cam-tam-ly`: Tình cảm / Tâm lý (Chiều sâu nhân vật, câu chuyện xúc động).
   - `hai-huoc-giai-tri`: Hài hước / Giải trí (Không khí vui vẻ, tương tác đồng đội nhẹ nhàng).
2. **Phòng chơi vật lý tại cơ sở cửa hàng (`physical_rooms`):**
   - `ROOM_01`: Phòng Án Mạng 01 (Sức chứa 8 người, phong cách Châu Âu cổ điển).
   - `ROOM_02`: Phòng Án Mạng 02 (Sức chứa 8 người, phong cách Á Đông truyền thống).
   - `ROOM_VIP`: Phòng VIP Hoàng Gia (Sức chứa 10 người, chuyên dụng cho kịch bản đông người).

---

## 3. KẾT QUẢ THỰC NGHIỆM VÀ XÁC MINH TÍNH LŨY THỪA

### 3.1. Lần chạy khởi tạo đầu tiên (First Execution):
```text
using environment development
using adapter mysql
using database booking_project_php

 == ReferenceDataSeeder: seeding 
 == ReferenceDataSeeder: seeded 0.0260s

 == RoleSeeder: seeding 
 == RoleSeeder: seeded 0.0098s

All Done. Took 0.0371s
```
- Số bản ghi chèn thành công: 3 roles, 5 categories, 3 physical_rooms.

### 3.2. Lần chạy kiểm chứng lũy thừa thứ hai (Idempotency Re-run):
```text
using environment development
using adapter mysql
using database booking_project_php

 == ReferenceDataSeeder: seeding 
 == ReferenceDataSeeder: seeded 0.0144s

 == RoleSeeder: seeding 
 == RoleSeeder: seeded 0.0106s

All Done. Took 0.0262s
```
- Kết quả: Không phát sinh bất kỳ lỗi `Duplicate entry`, số lượng bản ghi trong database được bảo toàn chính xác:
  - `SELECT COUNT(*) FROM roles`: Đúng 3 bản ghi.
  - `SELECT COUNT(*) FROM categories`: Đúng 5 bản ghi.
  - `SELECT COUNT(*) FROM physical_rooms`: Đúng 3 bản ghi.

---

## 4. KẾT LUẬN
Tầng dữ liệu khởi tạo đạt tiêu chuẩn **ACCEPTED FOR FOUNDATION INTEGRATION**.
