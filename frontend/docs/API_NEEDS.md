# TÀI LIỆU ĐẶC TẢ YÊU CẦU API BACKEND (API CONTRACT & NEEDS)
**Dự án:** Hệ thống Đặt phòng Kịch Bản Sát (Jubensha / Murder Mystery Booking Platform)  
**Phiên bản Frontend:** v16.0 Final  
**Base URL quy chuẩn:** `http://127.0.0.1:8080/api`  
**Ngày cập nhật:** 18/11/2026  

---

## 1. NGUYÊN TẮC THIẾT KẾ & QUY CHUẨN KỸ THUẬT

### 1.1. Format phản hồi chuẩn (Standard JSON Envelope)
Tất cả các API backend trả về định dạng JSON thống nhất:

#### Thành công (`200 OK`, `201 Created`):
```json
{
  "success": true,
  "data": { ... },
  "message": "Thông báo thân thiện nếu có"
}
```

#### Danh sách có phân trang (`200 OK`):
```json
{
  "success": true,
  "data": [ ... ],
  "meta": {
    "total": 120,
    "page": 1,
    "limit": 20,
    "total_pages": 6
  }
}
```

#### Lỗi nghiệp vụ hoặc hệ thống (`400`, `401`, `403`, `404`, `409`, `422`, `500`):
```json
{
  "success": false,
  "error": {
    "code": "SLOT_CONFLICT",
    "message": "Khung giờ hoặc phòng chơi này đã bị trùng lịch với phiên chơi khác.",
    "details": [
      { "field": "start_time", "issue": "Trùng lịch với Session #SS-1002 tại Phòng Dạ Cổ" }
    ]
  }
}
```

### 1.2. Headers bắt buộc từ Frontend
1. `Content-Type: application/json`
2. `Accept: application/json`
3. `X-CSRF-Token`: Bắt buộc với mọi request làm thay đổi dữ liệu (`POST`, `PUT`, `PATCH`, `DELETE`). Token được lấy từ endpoint `/api/auth/me` hoặc cookie `XSRF-TOKEN`.
4. `X-Idempotency-Key`: UUID v4 sinh tự động bởi Frontend trên mọi thao tác nhạy cảm (Tạo phòng, Đặt cọc, Thanh toán, Phê duyệt hoàn tiền) để chống trùng lặp request khi kết nối gián đoạn.

### 1.3. Cơ chế Xác thực & Phân quyền (RBAC)
- Sử dụng Session Cookie `PHPSESSID` (hoặc Bearer Token nếu backend hỗ trợ).
- 3 Roles phân định: `PLAYER` (Người chơi), `DM` (Quản trò), `ADMIN` (Quản trị viên).
- Đăng ký công khai luôn mặc định là `PLAYER`. DM và ADMIN được cấp/phân quyền bởi quản trị viên.

---

## 2. DANH MỤC ENDPOINTS CHI TIẾT THEO DOMAIN

### 2.1. Authentication & User Profile
| Method | Endpoint | Quyền | Trạng thái Hiện tại | Mô tả & Chú thích |
|---|---|---|---|---|
| `POST` | `/api/auth/login` | Public | Live/Provisional | Đăng nhập bằng username/email + mật khẩu. Trả về thông tin user + role. |
| `POST` | `/api/auth/register` | Public | Live/Provisional | Đăng ký tài khoản người chơi (`role = PLAYER`). |
| `POST` | `/api/auth/logout` | Authenticated | Live/Provisional | Hủy phiên đăng nhập. |
| `GET` | `/api/auth/me` | Authenticated | Live/Provisional | Lấy profile hiện tại, CSRF token, thống kê số phòng active (`active_rooms_count`). |

### 2.2. Kịch bản / Game Catalog (`scripts`)
| Method | Endpoint | Quyền | Trạng thái Hiện tại | Mô tả & Chú thích |
|---|---|---|---|---|
| `GET` | `/api/scripts` | Public | **ĐÃ HOẠT ĐỘNG (Live)** | Danh sách kịch bản (có hỗ trợ filter: `min_players`, `max_players`, `difficulty`, `search`). |
| `GET` | `/api/scripts/{id}` | Public | **ĐÃ HOẠT ĐỘNG (Live)** | Chi tiết 1 kịch bản. Chứa mô tả, độ khó, giá ngày (`price_day`), giá tối (`price_night`), gắn cờ `age_restriction: 18+`. |
| `POST` | `/api/scripts` | ADMIN | Provisional | Tạo mới kịch bản vào catalog. |
| `PUT` | `/api/scripts/{id}` | ADMIN | Provisional | Cập nhật kịch bản (nội dung, thông số, trạng thái hoạt động). |

### 2.3. Báo giá & Khung giờ (`quotes`)
| Method | Endpoint | Quyền | Trạng thái Hiện tại | Mô tả & Chú thích |
|---|---|---|---|---|
| `POST` | `/api/quotes/calculate` | Public/Auth | Provisional | **Backend cần tính giá & lưu snapshot**: Tính giá dựa trên game, số người, khung giờ (`09:00-19:59` vs `20:00+`), ngày trong tuần (Ưu đãi thứ 3 -30%), ưu đãi HSSV (-20.000đ/người từ T2-T5). Trả về: `base_price_per_player`, `time_window`, `subtotal`, `discount_code`, `discount_amount`, `deposit_per_player` (50.000đ), `total_deposit`, `remaining_amount`. |

### 2.4. Quản lý Phòng chơi (`rooms` / GameRoom Domain)
| Method | Endpoint | Quyền | Trạng thái Hiện tại | Mô tả & Chú thích |
|---|---|---|---|---|
| `GET` | `/api/rooms` | Public | Provisional | Danh sách phòng công khai (lọc `status`, `game_id`, `date`). |
| `GET` | `/api/rooms/{id}` | Public/Auth | Provisional | Chi tiết phòng, danh sách participants, tiến trình stepper, trạng thái thanh toán. |
| `POST` | `/api/rooms` | PLAYER | Provisional | Tạo phòng mới. Header `X-Idempotency-Key`. Kiểm tra: user không vượt quá 3 phòng active; start_time phải ở mốc `:00` hoặc `:30`. |
| `POST` | `/api/rooms/{id}/join` | PLAYER | Provisional | Tham gia phòng chơi còn chỗ. |
| `POST` | `/api/rooms/{id}/leave` | PLAYER | Provisional | Rời phòng. Yêu cầu lý do nếu phòng đã ở giai đoạn chốt lịch. |
| `POST` | `/api/rooms/{id}/cancel` | Host/ADMIN | Provisional | Hủy phòng. Bắt buộc lý do nếu là ADMIN. |
| `POST` | `/api/rooms/{id}/reopen` | ADMIN | Provisional | Mở lại tuyển quân khi có người rời phòng (kèm `replacement_window: 30m - 4h`). |

### 2.5. Quản trò DM (`dm` / DMAssignment Domain)
| Method | Endpoint | Quyền | Trạng thái Hiện tại | Mô tả & Chú thích |
|---|---|---|---|---|
| `GET` | `/api/dm/assignments` | DM | Provisional | Danh sách phân công cho DM đang đăng nhập (chờ xác nhận, đã nhận, lịch sắp tới). |
| `POST` | `/api/dm/assignments/{id}/accept` | DM | Provisional | DM chấp nhận dẫn kịch bản. |
| `POST` | `/api/dm/assignments/{id}/reject` | DM | Provisional | **BẮT BUỘC LÝ DO**. DM từ chối dẫn kịch bản (không làm hủy phòng chơi). |
| `POST` | `/api/admin/rooms/{id}/assign-dm` | ADMIN | Provisional | Admin chỉ định DM cho phòng (`dm_id`). Yêu cầu DM có $\ge 12$ giờ chuẩn bị. |
| `POST` | `/api/admin/rooms/{id}/revoke-dm` | ADMIN | Provisional | **BẮT BUỘC LÝ DO**. Admin thu hồi phân công của DM đã `ACCEPTED`. |
| `GET` | `/api/dm/availability` | DM/ADMIN | Provisional | Đăng ký & xem khung giờ rảnh của DM. |

### 2.6. Địa điểm & Phòng chơi vật lý (`venues`)
| Method | Endpoint | Quyền | Trạng thái Hiện tại | Mô tả & Chú thích |
|---|---|---|---|---|
| `GET` | `/api/venues` | Public/Auth | Provisional | Danh sách phòng vật lý tại quán (sức chứa, phong cách decor, tiện nghi). |
| `POST` | `/api/admin/rooms/{id}/confirm-schedule` | ADMIN | Provisional | **Admin chốt lịch và phòng vật lý**. Backend kiểm tra: Sức chứa phòng $\ge$ (Số người chơi + 1 DM); Trùng lịch phòng; DM có đủ 12h chuẩn bị. Chuyển phòng sang `PAYMENT_PENDING`. |

### 2.7. Thanh toán & Đặt cọc (`payments`)
| Method | Endpoint | Quyền | Trạng thái Hiện tại | Mô tả & Chú thích |
|---|---|---|---|---|
| `POST` | `/api/payments/deposit` | PLAYER | Provisional | Đặt cọc 50.000 VNĐ cố định. Mỗi participant có countdown 2 giờ riêng. |
| `POST` | `/api/payments/pay-remaining` | PLAYER | Provisional | Thanh toán số tiền còn lại sau khi trừ cọc (tại quán hoặc online). |
| `GET` | `/api/payments/room/{roomId}` | Auth/ADMIN | Provisional | Bảng kê trạng thái thanh toán của toàn bộ người chơi trong phòng. |

### 2.8. Hoàn tiền (`refunds` / Refund Domain)
| Method | Endpoint | Quyền | Trạng thái Hiện tại | Mô tả & Chú thích |
|---|---|---|---|---|
| `GET` | `/api/refunds` | ADMIN | Provisional | Danh sách yêu cầu hoàn tiền chờ duyệt. |
| `POST` | `/api/refunds/{id}/approve` | ADMIN | Provisional | **BẮT BUỘC MÃ THAM CHIẾU/GHI CHÚ GIAO DỊCH**. Duyệt hoàn cọc (áp dụng khi hủy trước giờ chơi $\ge 24$h). |
| `POST` | `/api/refunds/{id}/reject` | ADMIN | Provisional | **BẮT BUỘC LÝ DO**. Từ chối hoàn tiền (ví dụ hủy $< 24$h trước giờ chơi). |

### 2.9. Phiên chơi trực tiếp (`sessions` / Session Domain)
| Method | Endpoint | Quyền | Trạng thái Hiện tại | Mô tả & Chú thích |
|---|---|---|---|---|
| `POST` | `/api/sessions/{id}/start` | DM/ADMIN | Provisional | Bắt đầu phiên chơi (chuyển sang `IN_PROGRESS`). |
| `POST` | `/api/sessions/{id}/complete` | DM/ADMIN | Provisional | Kết thúc phiên chơi (chuyển sang `COMPLETED`). |
| `POST` | `/api/sessions/{id}/cancel` | ADMIN | Provisional | **BẮT BUỘC LÝ DO**. Hủy khẩn cấp phiên chơi đang diễn ra. |

### 2.10. Thông báo & Nhật ký Kiểm toán (`notifications`, `audit-logs`)
| Method | Endpoint | Quyền | Trạng thái Hiện tại | Mô tả & Chú thích |
|---|---|---|---|---|
| `GET` | `/api/notifications` | Authenticated | Provisional | Danh sách thông báo của tài khoản (nhắc cọc còn 1h, 30m; phân công DM; chốt lịch). |
| `PATCH` | `/api/notifications/{id}/read` | Authenticated | Provisional | Đánh dấu đã đọc. |
| `POST` | `/api/notifications/read-all` | Authenticated | Provisional | Đánh dấu đọc tất cả. |
| `GET` | `/api/audit-logs` | ADMIN | Provisional | Xem nhật ký kiểm toán hệ thống. |

---

## 3. BẢNG MÃ LỖI CHUẨN ĐOÁN (ERROR CODES CONTRACT)

| HTTP Code | Error Code | Mô tả |
|---|---|---|
| `400` | `SLOT_TIME_INVALID` | Giờ bắt đầu không rơi vào mốc `:00` hoặc `:30`. |
| `400` | `REPLACEMENT_WINDOW_INVALID` | Cửa sổ tìm người thay thế không nằm trong khoảng 30 phút - 4 giờ, hoặc vượt quá giờ bắt đầu Session. |
| `400` | `REASON_REQUIRED` | Thao tác bắt buộc phải có lý do nhưng frontend/người dùng gửi chuỗi rỗng. |
| `400` | `MAX_ACTIVE_ROOMS_REACHED` | Tài khoản người chơi đã tạo đủ 3 phòng đang active. |
| `403` | `FORBIDDEN_ROLE` | Tài khoản không có quyền truy cập portal này. |
| `409` | `ROOM_FULL` | Phòng chơi đã đủ số lượng người tối đa của kịch bản. |
| `409` | `DM_PREPARATION_TIME_INSUFFICIENT` | DM được chỉ định không có đủ $\ge 12$ giờ chuẩn bị trước giờ bắt đầu. |
| `409` | `VENUE_CAPACITY_EXCEEDED` | Sức chứa phòng vật lý nhỏ hơn (Số người chơi + 1 DM). |
| `409` | `VENUE_SCHEDULE_CONFLICT` | Phòng vật lý bị trùng lịch với phiên chơi khác. |
| `422` | `PROMOTION_NOT_STACKABLE` | Cố tình áp dụng đồng thời ưu đãi thứ 3 và ưu đãi HSSV. |
| `422` | `DEPOSIT_DEADLINE_EXPIRED` | Quá thời hạn đặt cọc 2 giờ của người chơi. |
| `422` | `REFUND_NOT_ELIGIBLE` | Hủy phòng dưới 24 giờ trước giờ chơi không đủ điều kiện hoàn cọc theo chính sách hiện hành. |

---

## 4. TỔNG HỢP GẮN CỜ `PROVISIONAL` & LƯU Ý CHO BACKEND TEAM
- **Live Endpoints hiện có:** `/api/health`, `/api/scripts`, `/api/scripts/{id}` đang phản hồi đúng chuẩn.
- **Provisional Endpoints:** Toàn bộ các endpoints về Đặt phòng, DM Assignment, Venue Confirmation, Cọc/Thanh toán, Hoàn tiền và Session hiện đang được Frontend giả lập trong mock adapter (`src/api/demo/demo-api.js` và `shared-store.js`) với độ trễ 300-600ms mô phỏng mạng thật.
- Khi Backend Team hoàn thiện các endpoints theo tài liệu này, Frontend chỉ cần đổi `DATA_MODE: 'live'` trong `src/utils/config.js` để chạy 100% với backend thực tế mà không cần sửa cấu trúc component hay business logic.
