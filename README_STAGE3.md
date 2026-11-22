# Nhật ký cập nhật Backend — Kịch Bản Sát Hạch / Đặt Lịch Phòng Chơi

## 1. Bảo mật, Hạ tầng & Đồng bộ dữ liệu
- **Xác thực, CSRF, CORS & Rate Limit**: Củng cố middleware bảo mật và thêm `RateLimitMiddleware` giới hạn tần suất gọi `/auth/login` và `/auth/register` theo IP (tự bỏ qua khi chạy test).
- **Pricing & Timezone**: Đồng bộ logic tính giá, khuyến mãi, múi giờ và cập nhật `PaymentStatus` khớp 7 trạng thái trong DB V3.
- **Database Upgrade (`backend/database/upgrade-stage3.php`)**: Tạo bảng `audit_logs` và bổ sung cột `student_verified` cho bảng `users` theo hướng idempotent (chạy lại an toàn).

## 2. Nghiệp vụ Đặt lịch, Thanh toán & Hoàn cọc
- **Xếp lịch động & Chống xung đột**: Kiểm tra trùng khung giờ phòng vật lý / DM khi đề xuất và biểu quyết chốt session; dùng khóa hàng (`FOR UPDATE`) cho các thao tác tranh chấp.
- **Thanh toán cọc & Hoàn tiền**: Xử lý thanh toán có idempotency key, luồng hủy phòng / yêu cầu hoàn tiền (`POST /api/v1/payments/{id}/refund-request`), kiểm soát chặt chuyển đổi trạng thái refund (`PENDING` -> `APPROVED` / `REJECTED`).
- **Thông báo**: Bổ sung đánh dấu đọc toàn bộ thông báo (`POST /api/v1/notifications/read-all`).

## 3. Điều phối Admin, Vòng đời Session (DM) & Audit Log
- **Admin điều phối & xác minh**:
  - `POST /api/v1/admin/rooms/{id}/assign-dm` — gán DM cho phòng chơi và đồng bộ session đang mở.
  - `POST /api/v1/dm/assignments/{id}/revoke` — thu hồi phân công DM kèm lý do và cập nhật `revoked_at`.
  - `POST /api/v1/admin/rooms/{id}/assign-venue` — chốt chi nhánh & phòng vật lý sau khi kiểm tra trùng lịch.
  - `PUT /api/v1/admin/users/{id}/student-verification` — cập nhật trạng thái xác minh HSSV (`student_verified`).
  - `GET /api/v1/admin/audit-logs` — xem nhật ký kiểm toán thao tác hệ thống qua `AuditLogService`.
- **Vòng đời phiên chơi (DM / Admin)**:
  - `POST /api/v1/sessions/{id}/start` (`SCHEDULED` -> `IN_PROGRESS`).
  - `POST /api/v1/sessions/{id}/complete` (`IN_PROGRESS` -> `COMPLETED`).

## 4. Kiểm thử
- Cập nhật và mở rộng PHPUnit feature tests (`SchedulingApiTest`, `AdminRoomCancelRefundApiTest`, `Stage3ApiTest`).