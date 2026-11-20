# MA TRẬN PHÂN QUYỀN (AUTHORIZATION MATRIX) – KỊCH BẢN SÁT V16

**Phiên bản:** V16 Baseline 1.0  
**Tài liệu tham chiếu:** `PRODUCT_SPEC_V16_Final.md`, `PRODUCT_SPEC_V15_Final.md`  
**Hệ thống vai trò (RBAC):** `PLAYER` (Người chơi), `DM` (Quản trò), `ADMIN` (Quản trị viên)  
**Nguyên tắc bảo mật cốt lõi:**
1. **Server-side Identity:** Không bao giờ tin cậy `user_id`, `role` gửi từ client; danh tính và vai trò luôn được trích xuất từ PHP Session đã được xác thực phía Server (`$_SESSION['user_id']`, `$_SESSION['user_role']`).
2. **Resource-level Authorization (Chống IDOR):** Phân quyền không chỉ dựa vào Role mà còn phải kiểm tra quan hệ sở hữu hoặc phân công tài nguyên (Resource Ownership & Assignment Check).
3. **Fail-Closed Principle:** Mọi endpoint không được khai báo `Public` đều mặc định yêu cầu xác thực và từ chối nếu không thỏa mãn điều kiện quyền.

---

## 1. MA TRẬN PHÂN QUYỀN THEO ENDPOINT (ENDPOINT MATRIX)

Ký hiệu:
- **`PUBLIC`**: Khách vãng lai, chưa đăng nhập vẫn truy cập được.
- **`AUTH`**: Bất kỳ người dùng nào đã đăng nhập hợp lệ.
- **`OWNER`**: Người dùng sở hữu tài nguyên (ví dụ: người tạo phòng, người tham gia, chủ tài khoản).
- **`ASSIGNED`**: DM hoặc nhân sự được phân công trực tiếp cho tài nguyên đó.
- **`ADMIN`**: Quản trị viên hệ thống (toàn quyền quản trị).
- **`DENIED`**: Nghiêm cấm truy cập, trả về `403 FORBIDDEN`.

### API-01: Identity & Authentication
| Method | Endpoint | Quyền truy cập | Điều kiện Resource-Level & Chống IDOR |
|---|---|---|---|
| POST | `/api/v1/auth/register` | `PUBLIC` | Mặc định tạo tài khoản role `PLAYER`; cấm tự cấp role `DM`/`ADMIN`. |
| POST | `/api/v1/auth/login` | `PUBLIC` | Kiểm tra tài khoản `ACTIVE`; regenerate session ID sau khi login. |
| POST | `/api/v1/auth/logout` | `AUTH` | Hủy session phía server, xóa session cookie. |
| GET | `/api/v1/auth/me` | `AUTH` | Trả về thông tin của chính user trong session; kèm CSRF token. |
| GET | `/api/v1/auth/csrf-token` | `PUBLIC` / `AUTH` | Cấp CSRF token bảo vệ các mutation requests. |
| GET | `/api/v1/users/me/profile` | `AUTH` | Chỉ đọc profile của chính mình (`session.user_id`). |
| PATCH | `/api/v1/users/me/profile` | `AUTH` | Chỉ cập nhật các trường profile cho phép của chính mình (`full_name`, `phone`, `avatar_url`). |

---

### API-02: User & Role Management (Dành riêng cho Admin)
| Method | Endpoint | Quyền truy cập | Điều kiện Resource-Level & Chống IDOR |
|---|---|---|---|
| GET | `/api/v1/admin/users` | `ADMIN` | Quản trị viên tra cứu danh sách người dùng, phân trang. |
| GET | `/api/v1/admin/users/{userId}` | `ADMIN` | Xem chi tiết thông tin và lịch sử tài khoản. |
| PATCH | `/api/v1/admin/users/{userId}` | `ADMIN` | Khóa/kích hoạt tài khoản (`ACTIVE`, `INACTIVE`, `SUSPENDED`). Không được tự khóa chính mình. |
| PUT | `/api/v1/admin/users/{userId}/role` | `ADMIN` | Nâng/hạ role giữa `PLAYER`, `DM`, `ADMIN`. |

---

### API-03: Game Catalog & Configuration
| Method | Endpoint | Quyền truy cập | Điều kiện Resource-Level & Chống IDOR |
|---|---|---|---|
| GET | `/api/v1/games` | `PUBLIC` | Xem danh mục kịch bản đang `ACTIVE`, hỗ trợ filter số người chơi. |
| GET | `/api/v1/games/{gameId}` | `PUBLIC` | Xem chi tiết kịch bản, thời lượng, cốt truyện giới thiệu. |
| GET | `/api/v1/games/{gameId}/configuration` | `PUBLIC` / `PLAYER` | Chỉ trả về cấu hình kịch bản công khai; ẩn các manh mối bí mật của DM. |
| GET | `/api/v1/games/{gameId}/prices` | `PUBLIC` | Xem bảng giá theo khung giờ sáng/chiều/tối. |
| POST | `/api/v1/admin/games` | `ADMIN` | Thêm kịch bản mới. |
| PATCH | `/api/v1/admin/games/{gameId}` | `ADMIN` | Cập nhật thông tin kịch bản. |
| PUT | `/api/v1/admin/games/{gameId}/configuration` | `ADMIN` | Quản lý phiên bản đạo cụ/manh mối (JSON versioning). |
| PUT | `/api/v1/admin/games/{gameId}/pricing` | `ADMIN` | Cập nhật bảng giá khung giờ. |

---

### API-04: GameRoom (Phòng Ghép Đội)
| Method | Endpoint | Quyền truy cập | Điều kiện Resource-Level & Chống IDOR |
|---|---|---|---|
| GET | `/api/v1/game-rooms` | `PUBLIC` | Danh sách phòng công khai đang tuyển người (`status = FORMING`). |
| GET | `/api/v1/game-rooms/{roomId}` | `PUBLIC` | Chi tiết phòng, số lượng slot hiện tại/tối đa. |
| POST | `/api/v1/game-rooms` | `PLAYER` / `ADMIN` | Tạo phòng mới. Giới hạn tối đa **3 phòng `FORMING` active** cho 1 creator. |
| GET | `/api/v1/game-rooms/me` | `AUTH` | Danh sách phòng do chính user tạo hoặc đang tham gia. |
| PATCH | `/api/v1/game-rooms/{roomId}` | `OWNER` / `ADMIN` | Chỉ người tạo phòng (`creator_id === session.user_id`) hoặc Admin mới được sửa thông tin phòng (tiêu đề, ghi chú). |
| POST | `/api/v1/game-rooms/{roomId}/cancel` | `ADMIN` (hoặc `OWNER` khi chưa có người cọc) | Hủy phòng bắt buộc phải có `cancel_reason`. |
| GET | `/api/v1/admin/game-rooms` | `ADMIN` | Toàn quyền tra cứu danh sách phòng mọi trạng thái. |

---

### API-05: RoomParticipant (Thành viên Tham gia)
| Method | Endpoint | Quyền truy cập | Điều kiện Resource-Level & Chống IDOR |
|---|---|---|---|
| POST | `/api/v1/game-rooms/{roomId}/participants` | `PLAYER` / `ADMIN` | Join phòng: Phòng phải `FORMING`, chưa vượt `max_players`, user chưa có slot active. |
| GET | `/api/v1/game-rooms/{roomId}/participants` | `PUBLIC` / `AUTH` | Danh sách người trong phòng (ẩn thông tin nhạy cảm đối với khách). |
| GET | `/api/v1/game-rooms/{roomId}/participants/me`| `AUTH` | Lấy trạng thái tham gia của chính user trong phòng đó. |
| POST | `/api/v1/game-rooms/{roomId}/leave-requests` | `OWNER` | Chỉ thành viên đang active trong phòng mới được xin rời phòng. |
| POST | `/api/v1/admin/leave-requests/{id}/approve` | `ADMIN` | Admin duyệt yêu cầu rời phòng, bắt buộc kèm audit reason. |
| POST | `/api/v1/admin/leave-requests/{id}/reject` | `ADMIN` | Admin từ chối yêu cầu rời phòng với lý do cụ thể. |
| POST | `/api/v1/game-rooms/{roomId}/rejoin` | `PLAYER` | Cho phép người đã `LEFT` tham gia lại tạo record mới nếu còn slot. |

---

### API-06: Recruitment & Replacement (Tìm người thay thế)
| Method | Endpoint | Quyền truy cập | Điều kiện Resource-Level & Chống IDOR |
|---|---|---|---|
| GET | `/api/v1/game-rooms/{roomId}/replacement-windows` | `AUTH` | Xem lịch sử cửa sổ tuyển người thay thế của phòng. |
| POST | `/api/v1/admin/game-rooms/{roomId}/reopen-recruitment` | `ADMIN` | Chỉ Admin được mở lại tuyển thành viên khẩn cấp khi có người bỏ cọc. |

---

### API-07: DM & Assignment (Quản trò)
| Method | Endpoint | Quyền truy cập | Điều kiện Resource-Level & Chống IDOR |
|---|---|---|---|
| GET | `/api/v1/dm/assignments` | `DM` | DM chỉ xem các phân công gán cho chính tài khoản của mình (`dm_user_id === session.user_id`). |
| GET | `/api/v1/dm/assignments/{id}` | `DM` / `ADMIN` | Xem chi tiết phân công được gán cho chính DM đó. |
| POST | `/api/v1/dm/assignments/{id}/accept` | `DM` | DM chấp nhận dẫn kịch bản (chỉ thực hiện được trên assignment của chính mình). |
| POST | `/api/v1/dm/assignments/{id}/reject` | `DM` | DM từ chối phân công, bắt buộc nhập lý do từ chối. |
| POST | `/api/v1/admin/game-rooms/{roomId}/dm-assignments` | `ADMIN` | Admin phân công DM cho phòng chơi. |
| POST | `/api/v1/admin/dm-assignments/{id}/revoke` | `ADMIN` | Admin thu hồi phân công DM kèm lý do. |

---

### API-08: Venue & Physical Room (Cơ sở & Phòng vật lý)
| Method | Endpoint | Quyền truy cập | Điều kiện Resource-Level & Chống IDOR |
|---|---|---|---|
| GET | `/api/v1/venues` | `PUBLIC` | Xem danh sách chi nhánh quán. |
| GET | `/api/v1/admin/physical-rooms` | `ADMIN` | Xem và quản lý các phòng chơi ngoài đời thực. |
| POST | `/api/v1/admin/physical-rooms` | `ADMIN` | Thêm phòng chơi thực tế cho chi nhánh. |
| POST | `/api/v1/admin/game-rooms/{roomId}/venue-reservations` | `ADMIN` | Xếp phòng thực tế cho buổi chơi (Backend khóa dòng kiểm tra trùng lịch). |

---

### API-09: ScheduleProposal & Session (Lịch & Buổi chơi)
| Method | Endpoint | Quyền truy cập | Điều kiện Resource-Level & Chống IDOR |
|---|---|---|---|
| GET | `/api/v1/game-rooms/{roomId}/schedule-proposals` | `AUTH` | Thành viên phòng xem các mốc giờ đề xuất. |
| POST | `/api/v1/game-rooms/{roomId}/schedule-proposals` | `OWNER` / `ADMIN` | Chỉ thành viên đang active trong phòng mới được đề xuất giờ chơi. |
| GET | `/api/v1/schedule-proposals/{id}` | `AUTH` | Xem chi tiết đề xuất và danh sách các phiếu biểu quyết. |
| POST | `/api/v1/schedule-proposals/{id}/votes` | `OWNER` | Chỉ thành viên trong phòng mới được vote (`ACCEPTED`/`REJECTED`). Không vote hộ người khác. |
| GET | `/api/v1/sessions/{sessionId}` | `AUTH` | Xem buổi chơi (chỉ thành viên trong phòng, DM phụ trách, hoặc Admin). |
| POST | `/api/v1/sessions/{sessionId}/start` | `ASSIGNED` / `ADMIN` | Chỉ DM được gán cho session hoặc Admin mới được bấm bắt đầu chơi (`IN_PROGRESS`). |
| POST | `/api/v1/admin/sessions/{sessionId}/cancel` | `ADMIN` | Hủy khẩn cấp buổi chơi, yêu cầu nhập lý do. |

---

### API-10: PlayerPayment & Refund (Thanh toán & Hoàn tiền)
| Method | Endpoint | Quyền truy cập | Điều kiện Resource-Level & Chống IDOR |
|---|---|---|---|
| GET | `/api/v1/game-rooms/{roomId}/payments` | `AUTH` | Thành viên xem khoản thanh toán của phòng (chỉ thấy chi tiết của chính mình và tổng quan phòng). |
| GET | `/api/v1/payments/{paymentId}` | `OWNER` / `ADMIN` | Người chơi chỉ xem được hóa đơn thanh toán của chính mình. |
| POST | `/api/v1/payments/{paymentId}/deposit` | `OWNER` | Người chơi thực hiện cọc (50.000 VNĐ cố định) cho slot của chính mình; có `idempotency_key`. |
| POST | `/api/v1/payments/{paymentId}/remaining` | `OWNER` | Người chơi thanh toán phần còn lại cho slot của mình. |
| POST | `/api/v1/admin/payments/{paymentId}/confirm` | `ADMIN` | Admin xác nhận khoản thanh toán đã vào tài khoản. |
| POST | `/api/v1/dm/payments/{paymentId}/confirm-in-person` | `ASSIGNED` / `ADMIN` | DM xác nhận thu tiền mặt tại quán cho người chơi trong session của mình. |
| GET | `/api/v1/admin/refunds` | `ADMIN` | Admin quản lý danh sách yêu cầu hoàn tiền. |
| POST | `/api/v1/admin/refunds/{id}/approve` | `ADMIN` | Admin duyệt yêu cầu hoàn tiền. |

---

### API-12: Notifications
| Method | Endpoint | Quyền truy cập | Điều kiện Resource-Level & Chống IDOR |
|---|---|---|---|
| GET | `/api/v1/notifications` | `AUTH` | Chỉ đọc thông báo thuộc về chính `session.user_id`. |
| GET | `/api/v1/notifications/unread-count` | `AUTH` | Lấy số lượng thông báo chưa đọc của chính mình. |
| POST | `/api/v1/notifications/{id}/read` | `AUTH` | Chỉ đánh dấu đọc nếu thông báo thuộc về chính user. |
| POST | `/api/v1/notifications/read-all` | `AUTH` | Đánh dấu đọc tất cả thông báo của user hiện tại. |

---

## 2. CHÍNH SÁCH BẢO VỆ CHỐNG LỖ HỔNG IDOR (INSECURE DIRECT OBJECT REFERENCES)

Để ngăn ngừa triệt để lỗi người dùng can thiệp dữ liệu của người khác bằng cách đổi ID trên URL/Payload:

1. **Xác thực quyền sở hữu (Ownership Verification Helper):**
   Mọi controller khi thao tác trên tài nguyên cá nhân bắt buộc thực thi hàm kiểm tra:
   ```php
   // Ví dụ trong PaymentController:
   if ($payment['user_id'] !== $currentUserId && $currentUserRole !== 'ADMIN') {
       return Response::fail('Bạn không có quyền truy cập hóa đơn này', 'FORBIDDEN', 403);
   }
   ```
2. **Ngăn chặn nâng quyền trái phép (Privilege Escalation):**
   Endpoint đăng ký `/api/v1/auth/register` ép cứng `role_id = PLAYER`. Không chấp nhận tham số `role` hoặc `role_id` truyền vào từ body.
3. **DM Isolation (Cách ly Quản trò):**
   Mọi query của DM trong DM Portal đều được gắn chặt với điều kiện `WHERE dm_user_id = :current_user_id`, ngăn chặn DM này xem dữ liệu hoặc kịch bản của phòng do DM khác phụ trách.
