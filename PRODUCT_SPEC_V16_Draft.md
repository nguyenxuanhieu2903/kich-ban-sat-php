# PRODUCT SPECIFICATION --- KỊCH BẢN SÁT

**Document:** Product & Technical Specification\
**Version:** V16 Draft 0.2\
**Status:** Draft for Review --- chưa được nghiệm thu\
**Baseline:** `PRODUCT_SPEC_V15_Final.md`\
**Project:** Kịch Bản Sát --- Web-based Booking Platform\
**Architecture:** PHP Native MVC + REST API + Independent
HTML/CSS/JavaScript Frontend\
**Database:** MySQL 8.4 LTS (đề xuất kỹ thuật, cần xác nhận tại Phase
5)\
**Development:** Windows Native, VS Code, Antigravity, Git/GitHub, không
Docker\
**Delivery approach:** MVP-first, incremental implementation\
**Target window:** 4 tuần --- mục tiêu kế thừa từ V15, cần re-estimate
sau Phase 0

------------------------------------------------------------------------

## 1. Document governance

### 1.1. Mục đích

V16 cập nhật kiến trúc triển khai của Kịch Bản Sát theo lựa chọn mới:

-   Backend: PHP thuần, tự tổ chức MVC.
-   Frontend: HTML, CSS và JavaScript độc lập.
-   Communication: REST API sử dụng JSON.
-   Database: MySQL.
-   Không sử dụng Laravel, Symfony, CodeIgniter hoặc framework
    backend/frontend.
-   Không sử dụng Docker/Docker Compose/containerization trong phạm vi
    hiện tại.

V16 kế thừa nghiệp vụ đã Accepted trong V15. Đây là thay đổi công nghệ
và kế hoạch triển khai, không phải một lần thiết kế lại sản phẩm.

### 1.2. Baseline và repository

-   Tài liệu baseline: `PRODUCT_SPEC_V15_Final.md`.
-   Repository phát triển chính thức: `nguyenxuanhieu2903/kich-ban-sat`.
-   `Danh-K/FE_Kich_Ban_Sat` chỉ là repository tham khảo/audit, không
    phải source base.
-   Không ghi đè V15 Final. V16 là tài liệu mới, có Change Log riêng.

### 1.3. Trạng thái quyết định

  Nội dung                             Trạng thái
  ------------------------------------ --------------------------------------
  PHP thuần MVC                        Đã được người dùng lựa chọn
  Frontend HTML/CSS/JavaScript riêng   Đã được người dùng lựa chọn
  PHP REST API + JSON                  Đã được người dùng lựa chọn
  MySQL                                Đã được người dùng lựa chọn
  Không dùng PHP framework             Đã được người dùng lựa chọn
  Không Docker, Windows native         Kế thừa V15
  MySQL 8.4 LTS                        Đề xuất, cần xác nhận ở Phase 5
  PHP version cụ thể                   Cần xác nhận sau kiểm tra môi trường
  Composer + PSR-4 autoload            Đề xuất kỹ thuật
  PDO                                  Đề xuất kỹ thuật
  PHPUnit                              Đề xuất kỹ thuật
  Migration tool                       Chưa chốt
  Session/cookie authentication        Đề xuất cho web MVP
  Hosting/production deployment        Chưa chốt

Không diễn giải đề xuất của tài liệu này thành quyết định đã Accepted
nếu người dùng/nhóm chưa xác nhận.

------------------------------------------------------------------------

## 2. Product scope --- kế thừa V15

### 2.1. Tổng quan

Kịch Bản Sát là nền tảng web hỗ trợ người chơi khám phá game, tạo và
tham gia GameRoom, tổ chức phiên chơi tabletop RPG, phối hợp DM/địa
điểm/lịch, thanh toán mô phỏng và theo dõi thông báo.

Hệ thống có ba portal:

1.  Player Portal.
2.  DM Portal.
3.  Admin Portal.

Ba portal là ba vùng giao diện và quyền truy cập, không phải ba backend
độc lập. Chúng sử dụng chung một Backend PHP và một Database MySQL.

### 2.2. MVP P0

**Public Home Page** - Giới thiệu sản phẩm. - Hiển thị game và GameRoom
công khai theo phạm vi V15. - Điều hướng đăng ký/đăng nhập.

**Player Portal** - Đăng ký, đăng nhập, đăng xuất. - Quản lý hồ sơ. -
Xem danh sách và chi tiết game. - Xem/tìm kiếm GameRoom công khai. - Tạo
GameRoom. - Tham gia GameRoom. - Theo dõi participant, điều kiện game,
trạng thái phòng. - Theo dõi DM, lịch và địa điểm. - Thanh toán mô phỏng
tiền cọc và khoản còn lại. - Gửi yêu cầu rời phòng khi đã đặt cọc. -
Rejoin theo policy V15. - Xem Notification Center. - Xem lịch sử
GameRoom, Session, Payment và Refund theo quyền.

**DM Portal** - Xem assignment. - Chấp nhận/từ chối assignment. - Xem
GameRoom, game, participant, lịch và venue liên quan. - Quản lý
availability theo phạm vi V15. - Theo dõi trạng thái chuẩn bị và
Session. - Xác nhận người chơi tham gia khi được phép. - Xác nhận khoản
thanh toán tại quán theo quyền V15. - Nhận và xem thông báo.

**Admin Portal** - Quản lý tài khoản và role. - Quản lý Game và game
configuration. - Quản lý GameRoom. - Phân công/thu hồi DM. - Quản lý
PhysicalRoom và reservation. - Quản lý ScheduleProposal và Session. -
Theo dõi PlayerPayment. - Quản lý Refund và retry theo V15. - Duyệt/từ
chối yêu cầu rời phòng. - Reopen recruitment. - Cancel GameRoom/Session
theo quyền và policy. - Theo dõi Notification và Audit.

Phạm vi chi tiết của từng màn hình/API phải đối chiếu V15 và được chốt
trong Phase 3 (API Contract), không tự thêm tính năng ngoài MVP.

### 2.3. Quy tắc nghiệp vụ kế thừa

Các giá trị dưới đây là các điểm nghiệp vụ quan trọng được giữ từ V15;
khi triển khai phải kiểm tra lại toàn bộ điều kiện, actor, state
transition và side effect trong V15, không chỉ dựa trên bảng tóm tắt
này.

  -----------------------------------------------------------------------
  Quy tắc                             Giá trị/policy kế thừa
  ----------------------------------- -----------------------------------
  GameRoom active tối đa do một       3
  creator tạo                         

  Formation timeout                   4 giờ

  DM preparation                      Tối thiểu 12 giờ

  Deposit                             30%

  Remaining payment                   70%

  Deposit deadline                    2 giờ cho từng participant

  Deposit reminder                    Còn 1 giờ và 30 phút

  Replacement window                  Tối thiểu 30 phút, tối đa 4 giờ

  Replacement deadline                Không vượt Session start

  Rejoin                              Tạo RoomParticipant record mới

  Reopen recruitment                  Không giới hạn số lần trong MVP;
                                      tạo window mới và audit

  Admin reason                        Bắt buộc ở các hành động V15 quy
                                      định

  MVP payment                         Mô phỏng, trừ khi có quyết định mới
  -----------------------------------------------------------------------

Không làm tròn, tính deadline hoặc suy diễn transition chỉ từ các giá
trị trong bảng. Dùng chính sách chi tiết của V15.

### 2.4. State machines bắt buộc giữ nguyên

V16 không thiết kế lại state machine đã Accepted trong V15:

-   GameRoom.
-   RoomParticipant.
-   DMAssignment.
-   ScheduleProposal.
-   Session.
-   PlayerPayment.
-   Refund.

Transition matrix, guards, actor, invariants, terminal states, side
effects liên domain và edge cases phải được chuyển thành implementation
rules và test cases. Không đổi tên hoặc gộp state nếu chưa có quyết định
thay đổi được phê duyệt.

### 2.5. Các edge cases quan trọng phải có test

-   Hai người cùng join slot cuối.
-   Rejoin đồng thời của cùng user vào cùng GameRoom.
-   Participant `LEAVE_REQUESTED` vẫn được tính theo policy V15.
-   Participant terminal không được tái kích hoạt; rejoin tạo record
    mới.
-   Payment callback/confirmation trùng lặp.
-   Payment đến muộn sau khi participant đã terminal không được tự động
    active lại.
-   Payment cạnh tranh với deposit deadline.
-   Admin cancel cạnh tranh với join/payment/DM accept.
-   Participant mới không reset deadline participant hiện hữu.
-   Reopen tạo replacement window mới, không ghi đè window cũ.
-   Replacement deadline không vượt Session start.
-   Notification delivery thất bại không rollback business transaction
    đã commit.
-   Không xác nhận booking nếu persistence thất bại.
-   Refund cũ và participation mới có lifecycle độc lập.

------------------------------------------------------------------------

## 3. Technology stack

### 3.1. Stack mục tiêu

  Layer                  Technology
  ---------------------- ------------------------------------
  Frontend               HTML5, CSS3, JavaScript ES Modules
  Browser API            Fetch API, DOM API
  Backend runtime        PHP native
  Backend architecture   Feature-oriented + Layered MVC
  HTTP API               REST + JSON
  Database access        PDO
  Database               MySQL
  Dependency/autoload    Composer + PSR-4
  Testing                PHPUnit (đề xuất)
  API documentation      OpenAPI (đề xuất)
  Version control        Git/GitHub
  IDE/AI assistance      VS Code, Antigravity
  Local environment      Windows native
  Containerization       Không Docker

### 3.2. PHP version

Chọn PHP version sau khi: - Kiểm tra PHP CLI trên máy phát triển và máy
thành viên. - Kiểm tra PHP extensions cần thiết. - Kiểm tra
compatibility của PHPUnit và dependency được chọn. - Kiểm tra phiên bản
PHP mà hosting có thể hỗ trợ (nếu hosting đã được chọn).

Không khóa PHP version chỉ dựa trên suy đoán. Ghi phiên bản chính thức
trong README và CI/test environment sau khi xác nhận.

### 3.3. Composer và dependency policy

Composer được dùng cho dependency management và PSR-4 autoload, không
phải framework.

Nguyên tắc: - Chỉ thêm dependency khi có nhu cầu rõ ràng. - Review
license, maintenance, security và compatibility. - Commit
`composer.json` và `composer.lock`. - Không commit thư mục dependency
nếu nhóm thống nhất cài bằng Composer. - Không tự viết framework hoặc
abstraction tổng quát quá mức.

### 3.4. Không sử dụng

-   Laravel, Symfony, CodeIgniter hoặc PHP MVC framework.
-   React, Next.js, Vue, Angular hoặc frontend framework.
-   ORM trong MVP; dùng PDO + Repository.
-   Docker/Docker Compose/container runtime.

Nếu sau này cần thay đổi, phải có ADR (Architecture Decision Record) và
review ảnh hưởng.

------------------------------------------------------------------------

## 4. Backend architecture --- PHP Native MVC

### 4.1. Nguyên tắc

Backend dùng **Feature-oriented + Layered MVC Architecture**.

Tổ chức module theo nghiệp vụ; trong từng module phân tách trách nhiệm
Controller, Validator, Service, Repository và Domain khi cần.

Không đặt business logic trong Controller hoặc SQL trong
Service/Controller. Không để Frontend truy cập database.

### 4.2. Request lifecycle

``` text
Browser
  |
  | fetch() / HTTP
  v
public/index.php (Front Controller)
  |
  v
Router
  |
  v
Middleware Pipeline
  |
  v
Controller
  |
  v
Request Validator
  |
  v
Application Service
  |
  +----> Domain Rules / State Transition
  |
  v
Repository
  |
  v
PDO
  |
  v
MySQL
```

Response đi ngược lại qua Controller dưới dạng JSON.

### 4.3. Trách nhiệm từng layer

  -------------------------------------------------------------------------
  Layer                   Trách nhiệm               Không được làm
  ----------------------- ------------------------- -----------------------
  Front Controller        Bootstrap ứng dụng,       Chứa business workflow
                          dispatch request          

  Router                  Match method/path, route  Truy vấn DB
                          params                    

  Middleware              Authentication,           Thay thế business
                          authorization, CSRF, rate validation
                          limit                     

  Controller              HTTP input/output, gọi    Chứa SQL/state machine
                          use case                  

  Validator               Kiểm tra                  Là nguồn quyết định
                          shape/type/format/range   quyền
                          của input                 

  Service                 Điều phối use case,       Render HTML
                          transaction, side effects 

  Domain                  Invariant, transition     Phụ thuộc HTTP
                          rules, enum/value objects 

  Repository              Truy vấn và persistence   Điều phối workflow
                          bằng PDO                  

  Infrastructure          PDO, config, logger,      Chứa policy domain
                          clock, mail/notification  
                          adapters                  

  Job/CLI                 Xử lý deadline, reminder, Tin dữ liệu client
                          reconciliation            
  -------------------------------------------------------------------------

### 4.4. Dependency direction

-   Controller phụ thuộc Service.
-   Service phụ thuộc Domain và Repository abstraction/implementation
    theo mức cần thiết.
-   Repository phụ thuộc PDO/infrastructure.
-   Domain không phụ thuộc HTTP, SQL hoặc JavaScript.
-   Không tạo interface cho mọi class nếu chưa có nhu cầu kiểm thử/thay
    thế implementation thực tế.

### 4.5. Error handling

-   Có exception handler tập trung.
-   Map exception sang HTTP status và error schema.
-   Không trả stack trace, SQL, file path hoặc secret ra client.
-   Log exception kèm correlation/request ID nếu khả thi.
-   Phân biệt validation error, business conflict, authorization error
    và internal error.

------------------------------------------------------------------------

## 5. Frontend architecture --- HTML/CSS/JavaScript độc lập

### 5.1. Nguyên tắc

Frontend gồm HTML, CSS và JavaScript ES Modules. Frontend không sử dụng
framework.

Frontend chịu trách nhiệm: - Render giao diện. - Navigation và
interaction. - Form handling. - Gọi API qua Fetch. - Hiển thị loading,
empty, error, success. - Cập nhật view theo response từ Backend.

Backend là nguồn có thẩm quyền đối với state, permission, pricing,
deadline và business rules.

### 5.2. API client

Tập trung logic HTTP trong một API client dùng chung: - Base
URL/config. - JSON serialization/deserialization. - Credentials/cookie
policy. - CSRF header. - HTTP error mapping. - Timeout/abort khi cần. -
Không log secret hoặc dữ liệu nhạy cảm.

Không viết `fetch()` rải rác trong mọi event handler nếu có thể dùng API
client thống nhất.

### 5.3. Frontend security

-   Không coi việc ẩn nút là authorization.
-   Không lưu password hoặc session secret trong localStorage.
-   Không tin role/user ID/price/status do client gửi.
-   Render dữ liệu không tin cậy bằng text APIs an toàn; tránh nối HTML
    trực tiếp.
-   Escape/sanitize nội dung theo ngữ cảnh.
-   CSRF protection cho request thay đổi dữ liệu nếu dùng cookie
    session.
-   Không để lộ stack trace hoặc thông tin nội bộ.

### 5.4. Origin/deployment model

Đề xuất ưu tiên cùng origin cho static frontend và `/api/v1` để giảm độ
phức tạp cookie/CORS trong MVP.

Nếu chạy frontend và API khác origin: - Chỉ allow origin cụ thể. - Không
dùng wildcard với credentialed requests. - Cấu hình credentials rõ
ràng. - Thiết kế CSRF và SameSite cookie phù hợp. - Kiểm thử preflight
và session trên browser thực tế.

Cách serve static frontend và API trong local Windows cần được chốt tại
Phase 1; không mặc định rằng hai port khác nhau sẽ tự chia sẻ session
cookie.

------------------------------------------------------------------------

## 6. REST API contract

### 6.1. Base path và format

Base path đề xuất:

``` text
/api/v1
```

API sử dụng JSON cho request/response, ngoại trừ endpoint upload nếu có
quyết định riêng.

### 6.2. HTTP methods

  Method   Ý nghĩa
  -------- -------------------------------------
  GET      Đọc resource
  POST     Tạo resource hoặc thực hiện command
  PATCH    Cập nhật một phần
  PUT      Thay thế resource khi phù hợp
  DELETE   Xóa resource khi nghiệp vụ cho phép

GET không được gây side effect nghiệp vụ.

### 6.3. Response envelope đề xuất

Success:

``` json
{
  "success": true,
  "message": "Request completed",
  "data": {}
}
```

Error:

``` json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "field": ["The field is required."]
  }
}
```

Response envelope là đề xuất; cần khóa trong API Contract.

### 6.4. HTTP status convention đề xuất

-   200 --- Success.
-   201 --- Created.
-   204 --- Success, no body.
-   400 --- Malformed/invalid request.
-   401 --- Unauthenticated.
-   403 --- Forbidden.
-   404 --- Resource not found.
-   409 --- State/concurrency conflict.
-   422 --- Validation/business rule error (cần thống nhất phân loại).
-   429 --- Rate limited.
-   500 --- Internal server error.

Không dùng status code thay thế cho business state.

### 6.5. API modules

-   Identity.
-   Game.
-   GameRoom.
-   RoomParticipant.
-   DM/DMAssignment.
-   Venue.
-   ScheduleProposal/Session.
-   PlayerPayment.
-   Refund.
-   Notification.
-   Audit (chỉ endpoint phù hợp quyền).

### 6.6. API contract deliverables

Mỗi endpoint phải có: - ID/operation name. - HTTP method và path. -
Authentication requirement. - Authorization rule và resource ownership
check. - Request schema. - Response schema. - Validation rules. -
Business errors. - HTTP status. - Transaction/locking note nếu có. -
Idempotency requirement. - Pagination/filter/sort nếu có. - Test cases.

OpenAPI là định dạng tài liệu đề xuất; không phải yêu cầu phải sinh code
từ OpenAPI.

------------------------------------------------------------------------

## 7. Database design

### 7.1. MySQL

MySQL là lựa chọn database đã được người dùng chọn. MySQL 8.4 LTS là
phiên bản đề xuất cần xác nhận ở Phase 5.

Yêu cầu: - InnoDB. - Foreign keys. - Unique constraints. - Indexes theo
query/use case. - Transaction. - Row-level locking phù hợp. -
Referential integrity. - Migration history. - Charset/collation thống
nhất (đề xuất `utf8mb4`).

### 7.2. Domain data inventory

Đây là inventory để phân tích, chưa phải danh sách bảng đã Accepted:

  Domain            Data candidates
  ----------------- ---------------------------------------
  Identity          User, Role, Profile
  Game              Game, GameRule, GameConfiguration
  GameRoom          GameRoom, StatusHistory
  RoomParticipant   Participant, ParticipationHistory
  Recruitment       ReplacementWindow, WindowHistory
  DM                DMProfile, Availability, DMAssignment
  Venue             PhysicalRoom, Reservation
  Session           ScheduleProposal, Session
  Payment           PlayerPayment, PaymentAttempt
  Refund            Refund, RefundAttempt
  Notification      Notification, RecipientReadState
  Audit             AuditLog

### 7.3. Data integrity

-   Không dùng FLOAT/DOUBLE để lưu tiền.
-   Dùng kiểu số nguyên phù hợp cho VNĐ theo V15.
-   Không xóa vật lý record cần giữ audit/history.
-   Ràng buộc unique phải phản ánh invariant.
-   Kiểm tra trạng thái mới nhất trong transaction.
-   Dùng locking/constraint để bảo vệ slot cuối và active participation.
-   Không xác nhận booking nếu persistence thất bại.

### 7.4. Migration

Phase 5 phải chọn migration tool và quy định: - Naming convention. -
Ordering. - Up/down policy. - Rollback policy. - Seed data. - Fresh
database setup. - Migration execution evidence.

Có thể đánh giá Phinx hoặc giải pháp migration độc lập tương đương. Chưa
mặc định chốt công cụ.

Không tự xây một migration framework lớn nếu công cụ phù hợp đáp ứng yêu
cầu.

------------------------------------------------------------------------

## 8. Authentication, authorization và security

### 8.1. Authentication

Đề xuất cho web MVP: PHP Session với cookie bảo mật.

Yêu cầu: - Password hashing bằng `password_hash()` và xác minh bằng
`password_verify()`. - Regenerate session ID sau đăng nhập. - Session
expiration policy. - Cookie HttpOnly. - Secure khi HTTPS. - SameSite phù
hợp. - Logout invalidate session. - Không lưu password/session secret
trong localStorage.

Token-based auth chỉ bổ sung khi có use case mobile/third-party được xác
nhận.

### 8.2. Authorization

-   RBAC cho PLAYER, DM, ADMIN.
-   Backend kiểm tra quyền ở từng action/resource.
-   Chống IDOR bằng kiểm tra quyền trên resource, không chỉ kiểm tra
    role.
-   DM chỉ thao tác trên assignment/session được phép.
-   Player chỉ thao tác trên resource thuộc quyền của họ.
-   Admin action phải xác minh role và reason bắt buộc theo V15.
-   Frontend route guard chỉ phục vụ UX.

### 8.3. OWASP-oriented controls

-   SQL Injection: PDO prepared statements.
-   XSS: output encoding, DOM text APIs, không tin HTML từ user.
-   CSRF: token cho cookie-authenticated state-changing requests.
-   Broken Access Control/IDOR: authorization tại Backend.
-   Authentication failures: password hashing, session rotation, rate
    limit.
-   Security Misconfiguration: env/config riêng, tắt debug production.
-   Sensitive Data Exposure: TLS production, không log
    secret/payment-sensitive data.
-   Mass assignment: allowlist fields theo use case.
-   File upload: validate type/size/content, random storage name,
    non-public storage nếu có upload.
-   Error handling: không trả stack trace hoặc SQL.
-   Audit: lưu actor/action/resource/reason/timestamp theo policy.

Security test phải có bằng chứng, không chỉ checklist giấy.

------------------------------------------------------------------------

## 9. Transactions, concurrency và idempotency

### 9.1. Transaction boundaries

Các use case cần transaction được phân tích tối thiểu: - Join/rejoin
GameRoom. - Enforce creator active-room limit. - Validate GameRoom
conditions. - Leave request/approval/rejection. - DM
assignment/accept/reject/revoke. - Schedule proposal/confirmation. -
Deposit/payment confirmation. - Deposit deadline expiration. - Admin
cancel/reopen. - Refund create/process/retry/confirm.

### 9.2. Concurrency strategy

-   Đọc trạng thái mới nhất trong transaction.
-   Sử dụng `SELECT ... FOR UPDATE` khi phù hợp.
-   Dùng unique constraints/check strategy phù hợp để bảo vệ invariants.
-   Xử lý deadlock/retry có giới hạn và logging.
-   Không dựa vào frontend disable button để chống double submit.
-   Không dựa vào pre-check ngoài transaction.

### 9.3. Idempotency

Các command có side effect và payment/refund callback phải có
idempotency strategy.

-   Duplicate request không tạo side effect lặp.
-   Duplicate event/callback không cập nhật state nhiều lần.
-   Lưu key/reference và kết quả xử lý theo thiết kế Phase 5/6.
-   Late payment sau terminal state đi qua reconciliation/refund, không
    re-activate participant.

------------------------------------------------------------------------

## 10. Background jobs và Notification

### 10.1. Job execution

PHP thuần không có scheduler tích hợp như Spring. V16 đề xuất PHP CLI
scripts được gọi bởi scheduler/Cron trên môi trường triển khai.

Job catalog: - Formation deadline. - Participant deposit deadline. -
Deposit reminder (1 giờ, 30 phút). - Replacement deadline. -
Notification dispatch. - Payment/refund reconciliation.

### 10.2. Job requirements

-   Job kiểm tra trạng thái mới nhất trước khi xử lý.
-   Idempotent.
-   Có execution log.
-   Có retry policy giới hạn.
-   Có cơ chế phát hiện job thất bại.
-   Không làm side effect trùng.
-   Timezone và clock policy thống nhất.
-   Notification delivery failure không rollback business transaction đã
    commit.

Cơ chế queue/outbox có thể được đánh giá; chưa mặc định yêu cầu message
broker.

### 10.3. Polling

MVP dùng polling cho Notification Center và dữ liệu cần refresh; không
yêu cầu WebSocket.

Polling interval, visibility behavior, backoff và endpoint payload phải
chốt tại Phase 3 (API Contract). Tránh polling dày hoặc lặp khi tab
không hoạt động.

------------------------------------------------------------------------

## 11. Source code structure --- mục tiêu

``` text
kich-ban-sat/
├── backend/
│   ├── app/
│   │   ├── Core/
│   │   │   ├── Router.php
│   │   │   ├── Request.php
│   │   │   ├── Response.php
│   │   │   ├── Database.php
│   │   │   └── ExceptionHandler.php
│   │   ├── Middleware/
│   │   ├── Shared/
│   │   │   ├── Exceptions/
│   │   │   ├── Validation/
│   │   │   ├── Security/
│   │   │   └── Support/
│   │   ├── Modules/
│   │   │   ├── Identity/
│   │   │   ├── Game/
│   │   │   ├── GameRoom/
│   │   │   ├── RoomParticipant/
│   │   │   ├── DM/
│   │   │   ├── Venue/
│   │   │   ├── Session/
│   │   │   ├── Payment/
│   │   │   ├── Refund/
│   │   │   ├── Notification/
│   │   │   └── Audit/
│   │   └── Jobs/
│   ├── config/
│   ├── database/
│   │   ├── migrations/
│   │   ├── seeders/
│   │   └── factories/
│   ├── routes/
│   │   └── api.php
│   ├── public/
│   │   └── index.php
│   ├── storage/
│   │   └── logs/
│   ├── tests/
│   │   ├── Unit/
│   │   └── Feature/
│   ├── bin/
│   │   └── jobs/
│   ├── composer.json
│   └── .env.example
├── frontend/
│   ├── public/
│   │   ├── index.html
│   │   └── assets/
│   └── src/
│       ├── styles/
│       ├── scripts/
│       │   ├── core/
│       │   ├── modules/
│       │   └── shared/
│       └── components/
├── docs/
│   ├── product/
│   ├── architecture/
│   ├── database/
│   ├── api/
│   └── migration/
├── .gitignore
└── README.md
```

Cấu trúc trên là mục tiêu. Không tạo toàn bộ file rỗng hoặc class chỉ để
lấp folder. Chỉ tạo module/layer khi feature cần đến.

------------------------------------------------------------------------

## 12. Testing strategy

### 12.1. Unit tests

Ưu tiên: - Domain invariants. - State transitions. - Deadline
calculation. - Deposit calculation/rounding. - Authorization policy. -
Idempotency behavior. - Validation rules.

### 12.2. Integration tests

-   Repository với MySQL test database.
-   Transaction/rollback.
-   Unique constraints.
-   Row locking/concurrency behavior.
-   API authentication/authorization.
-   JSON response/error mapping.
-   Background job behavior.

### 12.3. Frontend tests

-   Form validation UX.
-   API client success/error.
-   Loading/empty/error/success states.
-   Portal navigation.
-   Permission-aware rendering.
-   Duplicate submission handling.

### 12.4. E2E scenarios

Ít nhất một luồng end-to-end: 1. Player đăng ký/đăng nhập. 2. Tạo
GameRoom. 3. Player khác tham gia. 4. Admin tổ chức DM/venue/schedule.
5. DM accept assignment. 6. Participant deposit. 7. Session được tổ chức
theo trạng thái hợp lệ. 8. Payment/refund/notification/audit được kiểm
tra theo kịch bản.

Các nhánh cancel, timeout, rejoin, replacement và race conditions phải
có test riêng.

### 12.5. Evidence

Không đánh dấu PASS nếu chưa chạy test. Mỗi test report ghi: -
Command. - Environment. - Timestamp. - Passed/failed/skipped. - Failure
details. - Người thực hiện hoặc CI run reference.

------------------------------------------------------------------------

## 13. Development environment và deployment

### 13.1. Local development

-   Windows native.
-   PHP CLI.
-   Composer.
-   MySQL native.
-   Web server phù hợp với PHP.
-   VS Code.
-   Git/GitHub.
-   Không Docker.

README phải hướng dẫn setup sạch từ máy mới: cài runtime, extension,
database, env, migration, seed, chạy API/frontend và test.

### 13.2. Deployment

Hosting, CI/CD, production secrets, backup, monitoring và domain chưa
được chốt trong V15. V16 tiếp tục để mở.

Chỉ thiết kế deployment sau khi MVP ổn định và luồng P0 được nghiệm thu.
Không mặc định shared hosting/VPS nếu chưa kiểm tra yêu cầu PHP CLI,
Cron, document root, database, HTTPS, backup và log access.

------------------------------------------------------------------------

## 14. Open decisions

Không tự ý chốt các vấn đề nghiệp vụ còn OPEN trong V15:

1.  Các trường GameRoom được phép chỉnh sửa.
2.  Cách biểu diễn và validation game conditions.
3.  Polling interval.
4.  Schedule buffer.
5.  Join/rejoin gần thời điểm Session bắt đầu.
6.  Game configuration versioning.

Các quyết định kỹ thuật cần review: - PHP version và extensions. - MySQL
exact version. - Migration tool. - Static frontend/API local serving và
deployment topology. - Session/cookie/CSRF details. - Job runner, retry
và reconciliation strategy. - Logging/monitoring approach. - Hosting và
production environment.

Mỗi quyết định phải ghi: context, options, decision, consequences,
owner, date, status.

------------------------------------------------------------------------

## 15. Phase roadmap

  -----------------------------------------------------------------------
  Phase             Nội dung          Deliverable       Trạng thái
  ----------------- ----------------- ----------------- -----------------
  Phase 0           Freeze V15 và     Baseline, backup, Pending
                    source audit      audit report      

  Phase 1           PHP MVC           Runnable          Pending
                    foundation        skeleton, health  
                                      API, README       

  Phase 2           Database design   ERD, data         Pending
                                      dictionary,       
                                      schema,           
                                      migrations        

  Phase 3           API contract &    OpenAPI, auth     Pending
                    jobs design       matrix, job       
                                      catalog           

  Phase 4           Backend           Feature modules + Pending
                    implementation    tests             

  Phase 5           Frontend rebuild  Player/DM/Admin   Pending
                                      portals           

  Phase 6           Integration &     End-to-end flows, Pending
                    background jobs   job evidence      

  Phase 7           MVP testing &     Test report,      Pending
                    acceptance        release checklist 
  -----------------------------------------------------------------------

Các phase kỹ thuật trên là cách tổ chức triển khai V16; chúng không thay
thế các phase nghiệp vụ đã Accepted trong V15.

------------------------------------------------------------------------

## 16. MVP acceptance criteria

-   [ ] PHP backend chạy native trên Windows.
-   [ ] Frontend HTML/CSS/JS chạy độc lập.
-   [ ] Backend kết nối MySQL.
-   [ ] Database migration chạy trên database mới.
-   [ ] API contract được thống nhất.
-   [ ] Authentication hoạt động.
-   [ ] Authorization/RBAC được kiểm tra tại Backend.
-   [ ] Public Home Page hoạt động.
-   [ ] Player đăng ký/đăng nhập.
-   [ ] Player tạo và tham gia GameRoom.
-   [ ] GameRoom state machine đúng V15.
-   [ ] RoomParticipant state machine đúng V15.
-   [ ] Admin quản lý DM và PhysicalRoom.
-   [ ] DM accept/reject assignment.
-   [ ] ScheduleProposal/Session đúng V15.
-   [ ] Deposit 30% và remaining 70% đúng policy.
-   [ ] Deadline/reminder hoạt động đúng.
-   [ ] Replacement window/reopen đúng policy.
-   [ ] Rejoin tạo participation record mới.
-   [ ] Admin cancel/reopen đúng quyền và có reason.
-   [ ] Refund workflow đúng V15.
-   [ ] Notification Center hoạt động.
-   [ ] Audit lưu lịch sử cần thiết.
-   [ ] Concurrency/idempotency critical cases được test.
-   [ ] Security test có bằng chứng.
-   [ ] Unit/integration/E2E tests đã thực thi.
-   [ ] README setup Windows hoàn chỉnh.
-   [ ] Không sử dụng Docker.

------------------------------------------------------------------------

## 17. Change log

  ----------------------------------------------------------------------------
  Version                 Status                  Changes
  ----------------------- ----------------------- ----------------------------
  V15 Final               Accepted Design Phase 3 Baseline nghiệp vụ;
                          & 4                     Next.js/React/TypeScript +
                                                  Java/Spring Boot +
                                                  PostgreSQL

  V16 Draft 0.1           Draft                   Chuyển hướng PHP native
                                                  MVC + REST API +
                                                  HTML/CSS/JS + MySQL

  V16 Draft 0.2           Draft                   Bổ sung governance, layer
                                                  boundaries,
                                                  API/security/data
                                                  integrity/testing và
                                                  deployment caveats

  V16 Final               Pending                 Chỉ phát hành sau review và
                                                  xác nhận các quyết định còn
                                                  mở
  ----------------------------------------------------------------------------

**Kết luận:** V16 giữ nguyên sản phẩm và nghiệp vụ đã Accepted của V15,
thay đổi công nghệ triển khai sang PHP thuần MVC, REST API,
HTML/CSS/JavaScript độc lập và MySQL. Trạng thái hiện tại là Draft for
Review.
