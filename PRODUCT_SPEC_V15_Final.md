# PRODUCT SPECIFICATION --- KỊCH BẢN SÁT

**Document type:** Product & Technical Specification  
**Version:** 0.5 (V15 Final)  
**Status:** **Accepted Design (Phase 3 & Phase 4)** --- Baseline đặc tả sản phẩm đã hợp nhất; toàn bộ quyết định nghiệp vụ Phase 3 và Phase 4 được giữ theo biên bản Accepted. Tài liệu là đầu vào chính thức cho Phase 5 (Database Design), Phase 6 (API Contract & Background Jobs) và các phase triển khai tiếp theo.  
**Project type:** Web-based booking platform  
**Primary language:** Vietnamese  
**Target implementation window:** 4 weeks  
**Development approach:** MVP-first, incremental implementation  
**Primary requirement:** Hoàn thiện luồng đặt chỗ cốt lõi, có thể kiểm thử và trình diễn end-to-end.

> **Baseline nghiệp vụ:** V14 (Phase 3 Accepted) và V15 (Phase 4 Accepted).  
> **Repository chính thức:** `nguyenxuanhieu2903/kich-ban-sat`.  
> **Versioning rule:** V15 Final hợp nhất nội dung Product Specification chi tiết với các quyết định Phase 4 đã Accepted. Các tài liệu nguồn được giữ nguyên, không ghi đè.

---

## Change Log --- V15 Final

| Hạng mục | Nội dung cập nhật |
| :--- | :--- |
| **Version & Status** | Hợp nhất thành V15 Final; giữ trạng thái Accepted Design cho Phase 3 và Phase 4. |
| **Phase 3 & Phase 4** | Dùng các quyết định và transition matrix trong tài liệu Phase 4 Accepted làm nguồn chuẩn; loại bỏ các mục OPEN cũ đã được chốt. |
| **Architecture & NFR** | Bổ sung có kiểm soát nguyên tắc kiến trúc Backend/Frontend, yêu cầu phi chức năng, concurrency, security, testing và acceptance criteria từ bản V15 chi tiết. |
| **Repository Governance** | Chỉ `nguyenxuanhieu2903/kich-ban-sat` là repository phát triển chính thức; `Danh-K/FE_Kich_Ban_Sat` chỉ phục vụ audit/tham khảo. |
| **Frontend Ownership** | Nhóm tự thiết kế và xây dựng lại Frontend; không mặc định kế thừa source, UI, component hoặc kiến trúc Frontend từ repository tham khảo. |
| **Database Design** | Nhóm tự phân tích và thiết kế ERD, relational schema, constraints, indexes và migrations trong Phase 5; không kế thừa ERD/schema tham khảo. |
| **API Contract** | Nhóm tự thiết kế API Contract mới trong Phase 6; không kế thừa endpoint, payload hoặc contract từ repository tham khảo. |
| **Source of Truth** | Product Specification là baseline yêu cầu; Database Design và API Contract được phê duyệt là thiết kế kỹ thuật chi tiết; source code chính thức nằm trong repository của nhóm. |

## Version History

| Version | Ngày | Nội dung |
| :--- | :--- | :--- |
| **V13 (0.2)** | 14/11/2026 | Baseline Product Specification trước khi hoàn tất Phase 3. |
| **V14 (0.3)** | 15/11/2026 | Kế thừa V13; tích hợp và nghiệm thu các quyết định Phase 3 về `GameRoom` và `RoomParticipant` state machine. |
| **V15 (0.4)** | 15/11/2026 | Bổ sung thiết kế Phase 4; sau đó được rà soát và nghiệm thu toàn bộ 16 quyết định OPEN. |
| **V15 Final (0.5)** | 15/11/2026 | Hợp nhất bản đặc tả chi tiết với Phase 4 Accepted; chốt repository governance và nguyên tắc nhóm tự thiết kế lại Frontend, ERD/Database và API Contract. |

---

## 1. Tổng quan sản phẩm

### 1.1. Tên sản phẩm

**Kịch Bản Sát**

### 1.2. Mục tiêu sản phẩm

Xây dựng nền tảng web hỗ trợ người chơi khám phá các game/kịch bản tại
quán, tự tạo phòng chơi và đề xuất thời gian bắt đầu linh hoạt, tìm
người chơi khác tham gia để thành lập nhóm, sau đó phối hợp với Admin và
DM để tổ chức phiên chơi trực tiếp tại địa điểm của quán.

Sản phẩm không vận hành theo mô hình người chơi chọn một suất cố định
được cấu hình sẵn. Người tạo phòng đề xuất thời gian bắt đầu theo mốc
giờ hoặc nửa giờ; hệ thống sử dụng điều kiện đã được Admin cấu hình cho
từng game để xác định phòng có đủ điều kiện thành lập hay chưa.

Người dùng truy cập Home Page công khai trước tiên. Home Page là trang
khám phá game và GameRoom: người chưa đăng nhập vẫn có thể xem danh
sách/chi tiết game và các GameRoom được phép công khai. Hệ thống chỉ yêu
cầu đăng nhập khi người dùng thực hiện thao tác tạo/tham gia GameRoom
hoặc truy cập tính năng cá nhân. Sau xác thực, người dùng được điều
hướng vào portal phù hợp với role Player, DM hoặc Admin. Các portal dùng
chung một backend Spring Boot được tổ chức theo Feature-oriented +
Layered Architecture và một database PostgreSQL. Backend chịu trách
nhiệm xác thực, phân quyền, kiểm tra quy tắc nghiệp vụ, quản lý trạng
thái và lưu lịch sử.

Trong MVP, thanh toán được mô phỏng; người chơi đặt cọc 30% sau khi DM
chấp nhận phân công và thanh toán 70% còn lại trước buổi chơi hoặc tại
quán. Trạng thái được cập nhật gần thời gian thực bằng polling định kỳ
và thông báo được lưu trong Notification Center.

### 1.3. Luồng nghiệp vụ cốt lõi

1.  Người dùng truy cập Home Page và có thể khám phá game cùng các
    GameRoom công khai mà không cần đăng nhập.
2.  Khi muốn tạo/tham gia GameRoom hoặc truy cập tính năng cá nhân,
    người dùng đăng ký/đăng nhập; tài khoản tự đăng ký có vai trò mặc
    định `PLAYER`.
3.  Người chơi xem thông tin và điều kiện thành lập do hệ thống công bố.
4.  Người chơi tạo phòng, chọn game và đề xuất thời gian bắt đầu đúng
    mốc `HH:00` hoặc `HH:30`. Backend kiểm tra giới hạn tối đa 3 phòng
    đang hoạt động do tài khoản đó tạo.
5.  Hệ thống mở thời hạn thành lập 4 giờ. Người chơi khác đã đăng nhập
    có thể tìm và tham gia nếu đáp ứng điều kiện của game.
6.  Khi đủ điều kiện trong thời hạn, phòng chuyển sang trạng thái chờ
    Admin phân công DM; bộ đếm 4 giờ dừng và không còn tự động hủy phòng
    theo mốc này.
7.  Admin phân công DM. DM chấp nhận hoặc từ chối; nếu từ chối, Admin
    tìm DM khác. Rejection hoặc việc chưa có DM nhận không tự động hủy
    phòng.
8.  Admin bố trí phòng vật lý và xác nhận lịch sau khi Backend kiểm tra
    sức chứa, xung đột lịch và điều kiện chuẩn bị tối thiểu 12 giờ của
    DM.
9.  Chỉ khi DM chấp nhận và các điều kiện tổ chức cần thiết được đáp
    ứng, hệ thống mở thanh toán. Mỗi người chơi đặt cọc 30% trong thời
    hạn riêng của participant; hệ thống nhắc khi còn 1 giờ và 30 phút
    trước deadline tương ứng.
10. Nếu người chơi không đặt cọc đúng hạn, lượt tham gia của người đó bị
    hủy và phòng được đánh giá lại điều kiện. Nếu phòng thiếu người,
    Admin mở thời hạn tìm người thay thế theo giới hạn 30 phút--4 giờ.
11. Người chơi thanh toán 70% còn lại trước buổi chơi hoặc tại quán.
    Admin/DM được phân công xác nhận khoản thu tại quán.
12. DM xác nhận tham gia, hỗ trợ vận hành phiên chơi và cập nhật hoàn
    thành. Người chơi theo dõi lịch, DM, phòng vật lý, trạng thái phiên
    chơi, thanh toán, thông báo và lịch sử của mình.
13. Nếu phía Admin, DM hoặc quán không thể tổ chức phiên chơi, người
    chơi được hoàn 100% số tiền đã thanh toán. Các yêu cầu người chơi
    muốn rời phòng sau khi đặt cọc do Admin xem xét.
14. Admin có thể hủy GameRoom ở bất kỳ trạng thái chưa terminal nào,
    phải nhập lý do và hệ thống thực hiện các side effect nghiệp vụ
    tương ứng.
15. Người đã rời phòng hoặc bị hủy lượt do chưa đặt cọc có thể tham gia
    lại nếu phòng còn cho phép; lần tham gia mới tạo record
    RoomParticipant mới, không tái sử dụng record cũ.

### 1.4. Định nghĩa thành công

MVP được xem là đạt yêu cầu khi:

-   Player đăng ký/đăng nhập, quản lý hồ sơ, xem game, tạo phòng và tham
    gia phòng bằng dữ liệu thật từ backend.
-   Backend áp dụng đúng điều kiện game, thời hạn thành lập 4 giờ, giới
    hạn 3 phòng đang hoạt động do một tài khoản tạo và quy tắc thời gian
    bắt đầu theo giờ/nửa giờ.
-   Phòng đủ điều kiện chuyển sang chờ Admin phân công DM; DM có thể
    chấp nhận/từ chối và Admin có thể phân công lại.
-   Admin bố trí được DM và phòng vật lý; backend kiểm tra 12 giờ chuẩn
    bị, xung đột lịch và sức chứa.
-   Thanh toán mô phỏng vận hành đúng quy tắc cọc 30%, deadline 2 giờ
    tương ứng với từng participant/payment opening policy, nhắc hạn, hủy
    lượt quá hạn và thanh toán 70% còn lại.
-   Hệ thống đánh giá lại điều kiện khi người chơi rời phòng/bị hủy lượt
    và hỗ trợ quy trình tìm người thay thế.
-   Admin có thể hủy GameRoom từ mọi trạng thái chưa terminal với lý do
    bắt buộc; hết replacement window không tự động hủy.
-   Rejoin tạo participant record mới và giữ lịch sử các lượt tham gia
    trước.
-   Player, DM và Admin sử dụng được portal theo quyền; backend thực thi
    RBAC.
-   Notification Center lưu thông báo, trạng thái đọc/chưa đọc; polling
    cập nhật trạng thái gần thời gian thực.
-   Dữ liệu và lịch sử nghiệp vụ được lưu trong PostgreSQL, schema được
    quản lý bằng Flyway; các thao tác quan trọng có kiểm tra giao dịch
    và xử lý request đồng thời.
-   Frontend và backend giao tiếp qua REST API/JSON; các luồng chính và
    trường hợp lỗi quan trọng có kiểm thử.
-   Nhóm chạy được hệ thống native trên Windows bằng VS Code, không sử
    dụng Docker trong bất kỳ giai đoạn nào của phạm vi dự án hiện tại.

Giao diện chỉ dùng dữ liệu tĩnh hoặc chỉ xử lý nghiệp vụ ở frontend
không được xem là hoàn thành MVP. Việc Phase 3 được nghiệm thu chỉ xác
nhận thiết kế nghiệp vụ/state machine, không xác nhận implementation
hoặc test đã PASS.

## 2. Phạm vi và mức độ ưu tiên

Section này xác định phạm vi MVP theo mô hình phòng chơi linh hoạt của
Kịch Bản Sát. Người chơi tạo phòng và đề xuất giờ bắt đầu; những người
chơi khác có thể tìm và tham gia. Hệ thống không vận hành theo mô hình
các suất cố định được cấu hình sẵn.

Các chức năng P0 dưới đây là phạm vi MVP đã được xác nhận. Những thông
số chưa có giá trị cụ thể được liệt kê tại mục 2.4, không tự đặt mặc
định khi triển khai.

### 2.1. Chức năng MVP --- P0

#### 2.1.1. Player Portal

-   Đăng ký, đăng nhập và đăng xuất. Người chơi tự đăng ký; tài khoản
    mới mặc định có vai trò `PLAYER`.
-   Quản lý hồ sơ cá nhân.
-   Xem trang tổng quan, danh sách game và thông tin chi tiết từng game.
-   Tạo phòng chơi và đề xuất thời gian bắt đầu. Thời gian bắt đầu phải
    rơi vào đầu giờ hoặc nửa giờ (`HH:00` hoặc `HH:30`).
-   Tìm kiếm, xem thông tin và tham gia phòng phù hợp.
-   Theo dõi thành viên, điều kiện thành lập, DM được phân công, lịch
    chơi, phòng vật lý, trạng thái phiên chơi và trạng thái thanh toán.
-   Chỉnh sửa một số thông tin phòng theo quyền và điều kiện hệ thống;
    mọi thay đổi phải được Backend kiểm tra lại.
-   Xem lịch sử phòng, phiên chơi và thanh toán của tài khoản.
-   Thanh toán mô phỏng tiền cọc 30% và khoản còn lại 70%.
-   Rời phòng tự do trước khi đặt cọc. Sau khi đã đặt cọc, người chơi
    phải gửi yêu cầu rời phòng để Admin xem xét; Admin quyết định chấp
    thuận/từ chối và việc hoàn tiền.
-   Người đã `LEFT` hoặc `CANCELLED_UNPAID` có thể rejoin nếu GameRoom
    cho phép và các guard nghiệp vụ hợp lệ; mỗi lần rejoin tạo
    RoomParticipant mới.
-   Sử dụng Notification Center để xem thông báo và trạng thái đã
    đọc/chưa đọc.

#### 2.1.2. DM Portal

-   Xem danh sách phòng/phiên chơi được Admin phân công.
-   Chấp nhận hoặc từ chối phân công; khi từ chối, DM cung cấp lý do để
    Admin xử lý và tìm DM khác.
-   Xem lịch chơi, thông tin game, danh sách người tham gia và phòng vật
    lý đã được bố trí.
-   Quản lý lịch làm việc/trạng thái khả dụng của bản thân.
-   Cập nhật trạng thái chuẩn bị phiên chơi.
-   Xác nhận người chơi tham gia thực tế.
-   Xác nhận khoản 70% được thanh toán tại quán.
-   Cập nhật trạng thái hoàn thành phiên chơi.
-   Nhận thông báo nghiệp vụ trong Notification Center.

DM chỉ được thao tác trên phiên chơi được phân công và không được tự
phân công DM khác, thay đổi cấu hình game hoặc tự ý chỉnh sửa khoản
thanh toán đã ghi nhận.

#### 2.1.3. Admin Portal

-   Quản lý tài khoản và vai trò. Người chơi tự đăng ký; tài khoản DM và
    Admin do Admin có thẩm quyền tạo hoặc cấp quyền. Người dùng không
    thể tự chọn hoặc tự nâng quyền.
-   Quản lý danh sách game và trạng thái hoạt động của game.
-   Cấu hình điều kiện thành lập cho từng game, bao gồm số lượng người
    chơi tối thiểu/tối đa, điều kiện độ tuổi, giới tính và các điều kiện
    riêng của game.
-   Theo dõi, quản lý và xử lý vòng đời phòng chơi.
-   Quản lý danh sách DM, lịch làm việc/trạng thái khả dụng và lịch sử
    phân công.
-   Phân công DM cho phòng; theo dõi việc DM chấp nhận hoặc từ chối;
    phân công DM khác khi cần.
-   Quản lý phòng vật lý tại quán, sức chứa và trạng thái sử dụng.
-   Chọn phòng vật lý và xác nhận lịch tổ chức. Backend phải kiểm tra
    xung đột lịch và sức chứa trước khi xác nhận.
-   Theo dõi tiền cọc, khoản thanh toán còn lại, yêu cầu rời phòng và
    nghiệp vụ hoàn tiền.
-   Có thể hủy GameRoom từ mọi trạng thái chưa terminal; bắt buộc nhập
    lý do. Có thể reopen tuyển người theo chính sách replacement window;
    bắt buộc nhập lý do và lưu `reopen_count`.
-   Nhận thông báo và theo dõi các sự kiện vận hành qua Notification
    Center.

#### 2.1.4. Quy tắc tạo và thành lập phòng

-   Người dùng không cần đăng nhập để khám phá/xem GameRoom công khai;
    phải đăng nhập trước khi tạo hoặc tham gia phòng.
-   Mỗi tài khoản được tạo tối đa **3 phòng đang hoạt động tại cùng một
    thời điểm**. Đây là giới hạn đồng thời, không phải giới hạn theo
    ngày hoặc trọn đời. Khi phòng do tài khoản tạo chuyển sang trạng
    thái kết thúc, hạn mức tạo phòng được giải phóng.
-   Thời gian bắt đầu do người tạo đề xuất và phải đúng mốc giờ hoặc nửa
    giờ; không yêu cầu chọn từ danh sách suất cố định có sẵn.
-   Điều kiện thành lập được lấy từ cấu hình của game tương ứng, không
    do người tạo phòng tự đặt.
-   Khi tạo phòng, hệ thống bắt đầu bộ đếm thời hạn thành lập **4 giờ**.
-   Nếu phòng đáp ứng đủ điều kiện game trong 4 giờ, phòng chuyển sang
    giai đoạn chờ Admin phân công DM. Từ thời điểm này, mốc hết hạn
    thành lập 4 giờ không còn áp dụng; phòng không bị tự động hủy chỉ vì
    đã qua mốc 4 giờ.
-   Nếu hết 4 giờ mà phòng vẫn chưa đáp ứng điều kiện, phòng hết hạn, vị
    trí/tài nguyên liên quan được giải phóng và lịch sử nghiệp vụ được
    giữ lại.
-   Người tạo phòng chỉ được chỉnh sửa các trường được hệ thống cho
    phép. Mọi thay đổi phải được Backend kiểm tra lại điều kiện game,
    thời gian hợp lệ, số lượng thành viên và các ràng buộc lịch liên
    quan.

#### 2.1.5. Phân công DM và bố trí phiên chơi

-   Khi phòng đủ điều kiện, Admin phân công DM. Phòng chưa mở thanh toán
    khi chưa có DM chấp nhận.
-   DM có thể chấp nhận hoặc từ chối. Nếu DM từ chối, Admin tiếp tục tìm
    và phân công DM khác; việc DM từ chối không tự động làm hủy phòng
    của người chơi.
-   DM cần có tối thiểu **12 giờ chuẩn bị** trước giờ chơi. Khoảng chuẩn
    bị được tính từ thời điểm phòng đủ điều kiện và được xác nhận đến
    thời gian bắt đầu phiên chơi; điều kiện này phải được kiểm tra khi
    phân công/DM chấp nhận.
-   Nếu DM được phân công không đáp ứng được mốc 12 giờ, Admin ưu tiên
    tìm DM khác vẫn đáp ứng điều kiện. Nếu không có DM phù hợp, Admin có
    thể đề xuất lịch chơi mới và phải chờ người chơi xác nhận trước khi
    cập nhật lịch chính thức.
-   Admin là người chọn phòng vật lý. Backend kiểm tra phòng có đủ sức
    chứa và không bị trùng lịch; đồng thời kiểm tra lịch DM để ngăn phân
    công trùng thời gian.
-   Chỉ sau khi DM chấp nhận phân công và các điều kiện tổ chức cần
    thiết được đáp ứng, hệ thống mới mở bước thanh toán.
-   Nếu DM hoặc lịch bị thay đổi sau khi GameRoom đã
    `READY_FOR_SESSION`, GameRoom phải quay về
    `WAITING_FOR_ORGANIZATION`; các điều kiện tổ chức phải được xác nhận
    lại trước khi có thể READY trở lại.

#### 2.1.6. Thanh toán, nhắc hạn và hoàn tiền

-   Thanh toán trong MVP được mô phỏng, không kết nối cổng thanh toán
    tiền thật.
-   Sau khi DM chấp nhận và hệ thống mở thanh toán, mỗi người chơi phải
    đặt cọc **30%** phần chi phí của mình.
-   Với participant ban đầu, deadline đặt cọc là 2 giờ kể từ thời điểm
    hệ thống mở thanh toán.
-   Với participant tham gia thay thế, deadline đặt cọc là 2 giờ kể từ
    thời điểm join thành công. Deadline này độc lập cho từng participant
    và không reset deadline của các participant hiện hữu.
-   Với rejoin, lượt tham gia mới là một RoomParticipant record mới và
    tuân theo chính sách deadline theo từng lượt tham gia mới; việc áp
    dụng deadline 2 giờ từ thời điểm rejoin được xem là cách áp dụng quy
    tắc participant mới, không phải một chính sách riêng đã được xác
    nhận độc lập.
-   Hệ thống gửi thông báo nhắc đặt cọc trong Notification Center tại
    hai mốc: còn **1 giờ** và còn **30 phút** trước deadline tương ứng.
-   Nếu hết deadline mà người chơi chưa đặt cọc, hệ thống hủy lượt tham
    gia của người đó, giải phóng vị trí và kiểm tra lại điều kiện thành
    lập phòng. Người đã thanh toán không nhận nhắc nhở đặt cọc tiếp
    theo.
-   **70% còn lại** có thể được thanh toán trước buổi chơi hoặc trực
    tiếp tại quán. Khi thanh toán tại quán, Admin hoặc DM được phân công
    có quyền xác nhận đã nhận tiền; hệ thống lưu trạng thái thanh toán
    và thông tin người xác nhận.
-   Nếu phiên chơi bị hủy do phía Admin, DM hoặc quán không thể tổ chức,
    người chơi được hoàn lại **100% số tiền đã thanh toán**, bao gồm
    tiền cọc và khoản thanh toán còn lại nếu có.
-   Nếu người chơi đã đặt cọc và muốn rời phòng, người chơi gửi yêu cầu
    để Admin xem xét. Admin quyết định chấp thuận/từ chối yêu cầu và
    quyết định có hoàn tiền hay không.

#### 2.1.7. Xử lý khi phòng mất điều kiện và tìm người thay thế

-   Nếu một người chơi bị hủy lượt do không đặt cọc đúng hạn hoặc được
    Admin chấp thuận rời phòng, hệ thống cập nhật số lượng thành viên và
    đánh giá lại điều kiện game.
-   Nếu phòng không còn đáp ứng điều kiện, phòng chuyển sang giai đoạn
    chờ bổ sung người chơi (`REPLENISHING`); Admin và DM liên quan được
    thông báo.
-   Người chơi mới có thể tham gia thay thế nếu đáp ứng điều kiện game.
-   Admin chọn thời hạn tìm người thay thế trong khoảng **tối thiểu 30
    phút và tối đa 4 giờ**. Thời hạn cụ thể không được vượt quá thời
    điểm bắt đầu session.
-   Khi đủ người, Backend kiểm tra lại điều kiện game và các ràng buộc
    tổ chức trước khi tiếp tục phiên chơi.
-   Nếu hết thời hạn mà vẫn không đủ người, GameRoom chuyển sang
    `WAITING_FOR_ADMIN_DECISION`. Hệ thống không tự động hủy GameRoom
    hoặc tự chọn phương án khác.
-   Admin có thể reopen tuyển người không giới hạn số lần trong MVP. Mỗi
    lần reopen phải có lý do, tạo một replacement window mới, tăng
    `reopen_count` và lưu audit. Window mới vẫn phải tuân thủ min 30
    phút, max 4 giờ và không vượt quá session start.

#### 2.1.8. Notification Center và cập nhật trạng thái

-   Mỗi vai trò Player, DM và Admin có Notification Center trong portal
    tương ứng.
-   Thông báo được lưu trong database và hỗ trợ phân biệt đã đọc/chưa
    đọc.
-   Các sự kiện cần thông báo bao gồm: người chơi tham gia/rời phòng;
    phòng đủ điều kiện hoặc hết hạn; phân công DM và kết quả phản hồi;
    mở thanh toán; nhắc hạn đặt cọc; hủy lượt do quá hạn; phòng mất điều
    kiện; đề xuất/thay đổi lịch; xác nhận thanh toán; hủy phiên chơi và
    hoàn tiền.
-   MVP sử dụng **polling định kỳ** để cập nhật gần thời gian thực.
    Không yêu cầu WebSocket trong phạm vi này.
-   Tần suất polling cụ thể được ghi nhận là quyết định cần chốt tại mục
    2.4.

#### 2.1.9. Backend, database và yêu cầu nền tảng

-   Cung cấp REST API/JSON cho cả ba portal và các luồng nghiệp vụ P0.
-   Xác thực và phân quyền RBAC ở Backend; không dựa vào việc ẩn giao
    diện ở Frontend.
-   Backend là nguồn dữ liệu có thẩm quyền đối với trạng thái phòng,
    điều kiện game, lịch DM, phòng vật lý, thanh toán và hoàn tiền.
-   Lưu dữ liệu nghiệp vụ và lịch sử cần thiết trong PostgreSQL; quản lý
    thay đổi schema bằng Flyway.
-   Kiểm tra dữ liệu ở Backend, xử lý lỗi nhất quán và bảo vệ các thao
    tác nhạy cảm.
-   Bảo đảm các giới hạn và kiểm tra xung đột quan trọng hoạt động an
    toàn khi có nhiều request đồng thời; không chỉ kiểm tra ở Frontend.
-   Mọi state transition phải kiểm tra trạng thái mới nhất trong
    transaction/locking phù hợp; thao tác lặp hoặc event trùng không
    được gây chuyển trạng thái sai.
-   Hỗ trợ chạy native trên Windows bằng VS Code theo quyết định môi
    trường phát triển; không sử dụng Docker trong bất kỳ giai đoạn nào
    của phạm vi dự án hiện tại.

### 2.2. Chức năng ưu tiên sau MVP --- P1

Chỉ triển khai sau khi toàn bộ luồng P0 đã hoạt động ổn định và còn đủ
thời gian:

-   Tích hợp cổng thanh toán thật sau khi nhóm chọn nhà cung cấp và xác
    minh yêu cầu kỹ thuật/pháp lý liên quan.
-   Gửi thông báo qua email, SMS hoặc push notification.
-   Cập nhật tức thời bằng WebSocket thay cho polling nếu có nhu cầu và
    đủ nguồn lực.
-   Báo cáo/thống kê nâng cao về hoạt động phòng, doanh thu, người chơi
    và DM.
-   Tự động đề xuất DM hoặc phòng vật lý phù hợp; MVP vẫn để Admin chủ
    động lựa chọn.
-   Tra cứu/lọc lịch sử nâng cao và các công cụ hỗ trợ vận hành mở rộng.

### 2.3. Ngoài phạm vi MVP ban đầu

Không triển khai trong MVP hiện tại:

-   Ứng dụng mobile native.
-   Kiến trúc microservices hoặc tách thành nhiều backend độc lập cho
    từng portal.
-   Redis hoặc hệ thống cache phân tán nếu chưa có nhu cầu đã được chứng
    minh.
-   Kubernetes.
-   WebSocket/realtime nâng cao; MVP dùng polling định kỳ.
-   Tự động phân công DM hoặc tự động chọn phòng vật lý; Admin thực hiện
    các thao tác này.
-   Hệ thống loyalty, coupon hoặc chương trình thành viên.
-   Phân tích dữ liệu và tự động hóa vận hành nâng cao.
-   Các chức năng không trực tiếp hỗ trợ luồng tạo phòng, tham gia
    phòng, tổ chức phiên chơi và thanh toán của MVP.

Ba portal Player, DM và Admin là ba giao diện theo vai trò, **không phải
ba hệ thống backend riêng biệt**. MVP sử dụng một backend Spring Boot
duy nhất, tổ chức theo Feature-oriented + Layered Architecture, và một
database PostgreSQL dùng chung, được bảo vệ bằng RBAC.

### 2.4. Các quyết định chi tiết còn mở

Các nội dung dưới đây chưa có quy tắc cuối cùng trong tài liệu nguồn. Không tự đặt giá trị trong code, API hoặc database trước khi nhóm xác nhận:

| Nội dung | Phần cần chốt |
| :--- | :--- |
| Chỉnh sửa phòng | Danh sách trường được phép chỉnh sửa và thời điểm khóa chỉnh sửa. |
| Điều kiện riêng của game | Danh mục loại điều kiện được hỗ trợ và cách biểu diễn/cấu hình có cấu trúc. |
| Polling | Chu kỳ polling cho từng màn hình/nhóm dữ liệu. |
| Lịch DM và phòng vật lý | Quy tắc khoảng đệm trước/sau phiên chơi khi kiểm tra trùng lịch. |
| Join/rejoin sát giờ bắt đầu | Có chặn join/rejoin nếu không còn đủ thời gian để hoàn thành deadline đặt cọc 2 giờ hay không. |
| Game rule version | Cách áp dụng phiên bản cấu hình game đối với GameRoom đã được tạo khi cấu hình game thay đổi. |

**Đã đóng trong Phase 3:** replacement window min 30 phút/max 4 giờ; hết replacement window chuyển sang chờ Admin quyết định; Admin có thể hủy mọi trạng thái chưa terminal với lý do bắt buộc; Admin reopen không giới hạn trong MVP với lý do bắt buộc; các quy tắc rejoin được mô tả tại Section 5.

**Đã đóng trong Phase 4:** timeout DM, thu hồi assignment, schedule proposal, Session operation, payment 30%/70%, quy tắc làm tròn, payment deadline/retry và refund mô phỏng. Không đưa các mục này trở lại danh sách OPEN.

## 3. Quyết định đã xác nhận và vấn đề còn mở

### 3.1. Quyết định kỹ thuật đã xác nhận

  -----------------------------------------------------------------------
  Hạng mục                            Quyết định
  ----------------------------------- -----------------------------------
  Frontend framework                  Next.js + React + TypeScript
                                      (stack nền được ghi nhận; nhóm
                                      tự thiết kế lại Frontend)

  CSS/UI                              Nhóm tự thiết kế lại UI/UX và
                                      component; không kế thừa mặc định
                                      giao diện/source tham khảo

  Backend                             Java 21 + Spring Boot 3.x

  Build backend                       Maven

  Persistence                         Spring Data JPA / Hibernate

  Database                            PostgreSQL

  Database migration                  Flyway

  Security                            Spring Security theo phạm vi chức
                                      năng cần bảo vệ

  API                                 REST API với JSON

  Kiến trúc                           Feature-oriented + Layered
                                      Architecture

  IDE chính                           Visual Studio Code

  AI coding agent                     Antigravity, dùng để phân tích và
                                      hỗ trợ triển khai

  Version control                     Git và GitHub

  Local development                   Cài đặt và chạy native trên Windows

  Containerization                    Không sử dụng Docker trong bất kỳ
                                      phase nào của dự án hiện tại

  Triển khai                          Chưa chốt trong tài liệu này
  -----------------------------------------------------------------------

Giữ các công nghệ nền đã được xác nhận trong bảng trên, nhưng không xem
cấu trúc source code hiện có hoặc repository tham khảo là kiến trúc bắt
buộc phải kế thừa. Mọi thay đổi framework/dependency phải có lý do kỹ
thuật và kế hoạch kiểm thử.

### 3.2. Repository Governance & Source Code Ownership

#### 3.2.1. Repository chính thức

`nguyenxuanhieu2903/kich-ban-sat` là repository duy nhất được nhóm sử dụng để phát triển, quản lý và duy trì source code sản phẩm Kịch Bản Sát.

Mọi hoạt động phát triển tính năng, quản lý phiên bản, tạo branch, Pull Request, code review, tích hợp Backend–Frontend, kiểm thử và chuẩn bị triển khai MVP phải thực hiện trong repository này.

#### 3.2.2. Repository tham khảo

`Danh-K/FE_Kich_Ban_Sat` chỉ được sử dụng để:
- Audit và tìm hiểu luồng nghiệp vụ hiện có.
- Nghiên cứu các trường hợp sử dụng, vấn đề UX hoặc cách xử lý nghiệp vụ đáng lưu ý.
- Chọn lọc ý tưởng phù hợp sau khi đối chiếu với Product Specification.

Repository này không phải repository phát triển của nhóm, không phải source code nền để tiếp tục xây dựng sản phẩm và không phải nguồn quyết định hành vi hệ thống.

Không mặc định sao chép hoặc kế thừa source code, UI, component, routing, frontend architecture, ERD, database schema, migration, endpoint, request/response DTO hoặc API contract từ repository tham khảo. Việc tái sử dụng code (nếu có) phải được nhóm đánh giá riêng về quyền sử dụng, license, bảo mật, dependency và khả năng bảo trì; quyết định tái sử dụng không làm thay đổi repository phát triển chính thức.

#### 3.2.3. Nhóm tự thiết kế và xây dựng lại

- **Frontend:** Nhóm tự thiết kế UI/UX, routing, layout, component, state management, API client và cấu trúc source trong repository chính thức.
- **ERD & Database:** Nhóm tự phân tích domain và thiết kế ERD, quan hệ, cardinality, schema, constraints, indexes, transaction boundaries và migration. Không sao chép nguyên trạng ERD/schema từ repository tham khảo.
- **API:** Nhóm tự xác định endpoint, HTTP method, DTO, validation, error contract, authorization, idempotency và API versioning. Không kế thừa nguyên trạng endpoint hoặc payload từ repository tham khảo.
- **Backend:** Nhóm triển khai nghiệp vụ dựa trên Product Specification, Database Design và API Contract được nhóm phê duyệt.

#### 3.2.4. Thứ tự ưu tiên nguồn chuẩn

1. Product Specification V15 Final đã được nhóm xác nhận.
2. Database Design/ERD và API Contract được nhóm phê duyệt ở các phase tiếp theo.
3. Source code trong `nguyenxuanhieu2903/kich-ban-sat`.
4. `Danh-K/FE_Kich_Ban_Sat` chỉ có giá trị tham khảo/audit, không có quyền ghi đè các quyết định ở trên.

### 3.3. Quyết định sản phẩm đã xác nhận và nội dung còn mở

Các quyết định nghiệp vụ đã xác nhận được mô tả tại Section 1, Section 2 và Section 5; biên bản nghiệm thu nằm tại Section 12 và Section 13.

**Phase 3 — Accepted:**
- State machine riêng cho `GameRoom` và `RoomParticipant`, phối hợp qua Backend/application services/domain events.
- GameRoom chỉ tự hết hạn ở giai đoạn `FORMING` sau 4 giờ.
- Admin có quyền hủy GameRoom từ mọi trạng thái chưa terminal; bắt buộc nhập lý do.
- Hết replacement window chuyển sang `WAITING_FOR_ADMIN_DECISION`, không tự động hủy GameRoom.
- Admin reopen không giới hạn số lần trong MVP; mỗi lần yêu cầu lý do, tạo replacement window mới và lưu audit.
- Replacement window từ 30 phút đến 4 giờ, không vượt quá session start.
- Rejoin sau `LEFT` hoặc `CANCELLED_UNPAID` được phép nếu room cho phép; tạo RoomParticipant record mới.
- Rejoin khi refund cũ đang pending được phép; payment/refund của lượt cũ độc lập với lượt mới.
- `READY_FOR_SESSION` yêu cầu điều kiện game/tổ chức hợp lệ và tất cả participant active đã đặt cọc.
- Thay đổi DM/lịch sau READY đưa GameRoom về `WAITING_FOR_ORGANIZATION` để xác nhận lại.

**Phase 4 — Accepted:**
- `DMAssignment`: không timeout tự động cho `PENDING`; Admin có thể thu hồi assignment đã ACCEPTED trước khi Session bắt đầu, bắt buộc reason.
- `ScheduleProposal`: tối đa một proposal `PROPOSED` trên mỗi GameRoom; 100% participant active xác nhận trong 2 giờ; bất kỳ từ chối hoặc hết hạn đều dẫn đến `REJECTED`, giữ lịch cũ.
- `Session`: DM được phân công hoặc Admin xác nhận bắt đầu; chỉ Admin được hủy khẩn cấp Session `IN_PROGRESS`, bắt buộc reason.
- `PlayerPayment`: VNĐ, giá vé cố định/người; cọc 30% làm tròn lên hàng nghìn; khoản còn lại bằng tổng tiền trừ tiền cọc; retry không giới hạn trong hạn 2 giờ; timestamp server nhận request là chuẩn xử lý cạnh tranh deadline.
- `Refund`: Admin xác nhận hoàn tiền thủ công; retry thủ công không giới hạn khi FAILED; quyết định hoàn 100% hoặc 0% khi Player xin rời sau cọc do Admin đưa ra và phải có reason.

Các nội dung còn mở ở cấp sản phẩm được liệt kê tại Section 2.4. Không được suy diễn các nội dung này thành quyết định đã xác nhận.

## 4. Kiến trúc hệ thống

### 4.1. Kiến trúc tổng quan

MVP sử dụng kiến trúc **Feature-oriented + Layered Architecture**.
Frontend có Home Page công khai và các portal theo role; backend là một
ứng dụng Spring Boot duy nhất, tổ chức theo feature và layer; dữ liệu
lưu trong một PostgreSQL database dùng chung:

``` text
                         PUBLIC USER
                              |
                              v
                 PUBLIC HOME PAGE (NO LOGIN)
             Explore Games + Public GameRooms
                              |
             +----------------+----------------+
             |                                 |
       Public browsing                   Protected action
       (game/room view)                 Create / Join / Personal
             |                                 |
             |                          LOGIN / REGISTER
             |                                 |
             |                       AUTHENTICATION + RBAC
             |                                 |
             |                +----------------+----------------+
             |                |                |                |
             |           PLAYER PORTAL      DM PORTAL       ADMIN PORTAL
             |                |                |                |
             +----------------+----------------+----------------+
                              |
                         REST API / JSON
                              |
                    JAVA 21 + SPRING BOOT
                  FEATURE-ORIENTED + LAYERED
                              |
                   SPRING DATA JPA / HIBERNATE
                              |
                         POSTGRESQL
                              |
                       FLYWAY MIGRATIONS
```

Ba portal là các vùng giao diện và quyền truy cập khác nhau, không phải
ba backend độc lập. Backend là nguồn dữ liệu có thẩm quyền và thực thi
toàn bộ quy tắc nghiệp vụ.

Các state machine được tách theo domain (GameRoom, RoomParticipant,
DMAssignment, Session/Scheduling, PlayerPayment, Refund); việc tách
state machine không đồng nghĩa các domain hoạt động độc lập. Backend
điều phối tính nhất quán qua application service/domain event và
transaction phù hợp. **Phase 3 đặc tả GameRoom và RoomParticipant; Phase 4 bổ sung thiết kế
cho DMAssignment, ScheduleProposal/Session, PlayerPayment và Refund tại
Section 5.12.**

Frontend không được tự quyết định trạng thái phòng, quyền, điều kiện
game, lịch, sức chứa hoặc kết quả thanh toán. Trong môi trường local,
frontend chạy native bằng Node.js/npm, backend chạy bằng Java 21/Maven
và PostgreSQL chạy native trên Windows. Docker/Docker Compose/container
runtime không được sử dụng trong bất kỳ phase nào của dự án hiện tại.

### 4.2. Trách nhiệm các thành phần

**Public Home Page** - Là điểm vào mặc định của người dùng, không yêu
cầu đăng nhập. - Cho phép khám phá danh sách/chi tiết game và danh
sách/chi tiết GameRoom được xác định là công khai. - Các thao tác tạo
GameRoom, tham gia GameRoom và truy cập dữ liệu/tính năng cá nhân phải
yêu cầu đăng nhập. - Khi đăng nhập thành công, điều hướng người dùng đến
portal theo role; tài khoản tự đăng ký mặc định vào Player Portal.

**Player Portal** - Đăng ký/đăng nhập/đăng xuất, quản lý hồ sơ. - Khám
phá game, tạo và tham gia phòng, theo dõi điều kiện và trạng thái. -
Thanh toán mô phỏng, gửi yêu cầu rời phòng sau đặt cọc, xem lịch sử và
thông báo.

**DM Portal** - Xem các phiên được phân công, chấp nhận/từ chối và nêu
lý do. - Quản lý lịch khả dụng, xem lịch và người tham gia. - Cập nhật
chuẩn bị, xác nhận tham gia, xác nhận khoản 70% thu tại quán và hoàn
thành phiên chơi.

**Admin Portal** - Quản lý tài khoản/vai trò, game và cấu hình điều kiện
game. - Quản lý vòng đời phòng, phân công DM, phòng vật lý và lịch tổ
chức. - Quản lý thanh toán, yêu cầu rời phòng, hoàn tiền và hoạt động
vận hành.

**Backend** - Cung cấp các API đọc công khai cần thiết cho Home Page;
API tạo/tham gia GameRoom và API dữ liệu cá nhân phải yêu cầu xác
thực. - Xác thực, RBAC và kiểm tra quyền trên từng thao tác/tài
nguyên. - Xử lý quy tắc thành lập phòng, thời hạn, phân công DM, lịch,
sức chứa, thanh toán, thông báo và lịch sử. - Thực hiện validation,
transaction, concurrency control và trả lỗi nhất quán.

**Database** - Lưu tài khoản, vai trò, game, cấu hình điều kiện, phòng
chơi, thành viên, DM, lịch khả dụng, phòng vật lý, lịch tổ chức, thanh
toán, thông báo và lịch sử/audit cần thiết. - Bảo đảm ràng buộc dữ liệu;
thay đổi schema thông qua Flyway. - Không xóa vật lý các bản ghi nghiệp
vụ cần lưu lịch sử; sử dụng trạng thái kết thúc và cơ chế lưu lịch sử
phù hợp.

### 4.3. Nguyên tắc tổ chức backend

Backend áp dụng **Feature-oriented + Layered Architecture**:

-   Tổ chức package/module cấp cao theo feature nghiệp vụ (ví dụ
    `identity`, `game`, `game_room`, `dm`, `venue`, `session`,
    `payment`, `notification`, `audit`).
-   Bên trong mỗi feature, phân lớp trách nhiệm rõ ràng, tối thiểu gồm
    `controller`, `service`, `repository`, `entity` và `dto` khi phù
    hợp.
-   `controller` tiếp nhận HTTP request/response và validation ở biên;
    không chứa business logic.
-   `service` điều phối use case và transaction; domain/business rules
    phải được đặt ở vị trí phù hợp, không dồn toàn bộ vào controller
    hoặc repository.
-   `repository` chịu trách nhiệm truy cập dữ liệu; không để frontend
    hoặc controller truy cập persistence trực tiếp.
-   Thành phần dùng chung như security, exception handling,
    configuration và response convention đặt trong `common` khi thực sự
    dùng chung; tránh tạo common package thành nơi chứa logic nghiệp vụ.
-   Không tạo backend riêng cho từng portal; các portal gọi chung
    backend và được bảo vệ bằng RBAC.

Các module nghiệp vụ dự kiến:

-   `identity`: tài khoản, xác thực, vai trò và hồ sơ.
-   `game`: danh sách game và cấu hình điều kiện thành lập.
-   `game-room`: GameRoom, RoomParticipant, điều kiện và vòng đời phòng.
-   `dm`: hồ sơ DM, lịch khả dụng và phân công.
-   `venue`: phòng vật lý, sức chứa và lịch sử dụng.
-   `session`: lịch tổ chức và trạng thái phiên chơi.
-   `payment`: tiền cọc, khoản còn lại, xác nhận thanh toán và hoàn tiền
    mô phỏng.
-   `notification`: Notification Center, trạng thái đọc/chưa đọc và sự
    kiện thông báo.
-   `audit`: lịch sử các thao tác nghiệp vụ quan trọng.

Trong từng module có thể phân lớp Controller, Service, Repository,
Entity, DTO và cấu hình liên quan. Không đặt business logic trong
controller; không tạo abstraction hoặc design pattern phức tạp nếu chưa
giải quyết vấn đề thực tế.

### 4.4. Nguyên tắc frontend

Nhóm tự thiết kế và xây dựng lại Frontend trong repository chính thức `nguyenxuanhieu2903/kich-ban-sat`. Công nghệ nền được ghi nhận trong tài liệu hiện tại là Next.js + React + TypeScript; việc sử dụng framework này không đồng nghĩa phải kế thừa kiến trúc hoặc source code Frontend từ repository tham khảo.

Nguyên tắc:
- Chủ động thiết kế lại UI/UX, route/layout theo Player, DM và Admin, component, state management, API client và cấu trúc thư mục.
- Không mặc định kế thừa giao diện, component, routing hoặc kiến trúc Frontend của `Danh-K/FE_Kich_Ban_Sat`.
- Tách UI, API client, type và logic theo trách nhiệm; tránh coupling giữa giao diện và business rules.
- Có loading, empty, error, success và trạng thái polling phù hợp.
- Không hardcode URL backend; sử dụng biến môi trường.
- Không đưa secret hoặc dữ liệu chỉ backend được phép biết vào bundle frontend.
- Không hardcode điều kiện game, quyền hạn, state transition hoặc kết quả tính tiền trong giao diện.
- Frontend route guard chỉ phục vụ UX; Backend vẫn phải xác thực và kiểm tra quyền trên từng resource/action.

## 5. Yêu cầu chức năng và Domain State Machine

### 5.1. Tài khoản và phân quyền

-   Người chơi tự đăng ký; tài khoản mới mặc định có role `PLAYER`.
-   Tài khoản `DM` và `ADMIN` do Admin có thẩm quyền tạo hoặc cấp role.
-   Người dùng không thể tự chọn hoặc tự nâng quyền.
-   Các API phải xác thực và kiểm tra RBAC ở backend; kiểm tra cả quyền
    trên tài nguyên cụ thể, ví dụ DM chỉ thao tác trên phiên được phân
    công.

### 5.1.1. Public Home Page và quyền truy cập trước đăng nhập

-   Home Page là trang mặc định khi người dùng truy cập website.
-   Người chưa đăng nhập được phép xem danh sách game, chi tiết game,
    danh sách GameRoom công khai và chi tiết GameRoom công khai.
-   Người chưa đăng nhập không được tạo GameRoom, tham gia GameRoom,
    truy cập hồ sơ cá nhân, lịch sử, thanh toán, Notification Center
    hoặc các chức năng dành riêng cho Player/DM/Admin.
-   Khi người dùng chọn thao tác cần xác thực, frontend chuyển đến
    Login/Register; sau khi xác thực thành công, hệ thống tiếp tục luồng
    thao tác ban đầu nếu còn hợp lệ.
-   API public chỉ trả về dữ liệu được phép công khai; không trả thông
    tin cá nhân, thông tin liên hệ, dữ liệu thanh toán hoặc dữ liệu nội
    bộ của người tham gia.
-   Backend vẫn phải kiểm tra authentication, authorization và quyền
    trên tài nguyên; không xem việc ẩn nút ở frontend là biện pháp bảo
    mật.

### 5.2. Game và điều kiện thành lập

-   Người dùng xem danh sách game và thông tin chi tiết.
-   Admin cấu hình tối thiểu/tối đa người chơi, điều kiện độ tuổi, giới
    tính và các điều kiện riêng của từng game.
-   Backend dùng cấu hình hiện hành để kiểm tra khi tạo/tham gia/chỉnh
    sửa phòng và khi đánh giá lại điều kiện.
-   Không hardcode điều kiện game ở frontend. Cấu hình cần có lịch
    sử/phiên bản để tránh thay đổi cấu hình làm thay đổi ngoài ý muốn
    các phòng đang hoạt động.
-   Cách biểu diễn điều kiện mở rộng phải có cấu trúc và kiểu dữ liệu
    được kiểm soát; không dùng JSON tùy ý không có validation.

### 5.3. Tạo và tham gia phòng

-   Người chơi không cần đăng nhập để khám phá/xem GameRoom công khai;
    phải đăng nhập trước khi tạo hoặc tham gia phòng.
-   Một tài khoản có tối đa 3 phòng đang hoạt động do chính tài khoản đó
    tạo tại cùng thời điểm; backend phải bảo vệ giới hạn này khi có
    request đồng thời.
-   Người tạo chọn game và đề xuất thời gian bắt đầu theo mốc `HH:00`
    hoặc `HH:30`.
-   Người chơi khác tìm kiếm/xem phòng và tham gia nếu đáp ứng điều kiện
    game.
-   Backend kiểm tra điều kiện và số lượng thành viên trong transaction;
    không tin dữ liệu do frontend gửi mà không xác minh.
-   Thời hạn thành lập là 4 giờ từ khi tạo phòng. Phòng đủ điều kiện
    trong thời hạn sẽ dừng bộ đếm và chuyển sang chờ Admin phân công DM.
    Phòng chưa đủ điều kiện khi hết hạn chuyển sang trạng thái
    `EXPIRED`, giải phóng tài nguyên liên quan và giữ lịch sử.
-   Người dùng chỉ được có tối đa một participation đang active trong
    cùng một GameRoom. Rejoin sau trạng thái terminal phải tạo record
    mới.

### 5.4. Quản lý vòng đời GameRoom

#### 5.4.1. GameRoomStatus

Các trạng thái GameRoom trong Phase 3:

  ------------------------------------------------------------------------------
  Status                         Ý nghĩa                 Terminal
  ------------------------------ ----------------------- -----------------------
  `FORMING`                      Đang tuyển người trong  No
                                 thời hạn thành lập ban  
                                 đầu 4 giờ               

  `WAITING_FOR_DM`               Đã đủ điều kiện game,   No
                                 chờ Admin phân công     
                                 DM/DM nhận phân công    

  `WAITING_FOR_ORGANIZATION`     DM đã nhận hoặc cần xác No
                                 nhận lại tổ chức; chờ   
                                 lịch/venue/DM hợp lệ    

  `WAITING_FOR_DEPOSIT`          Điều kiện tổ chức đã    No
                                 sẵn sàng, đang chờ các  
                                 participant đặt cọc     

  `REPLENISHING`                 Phòng mất điều kiện và  No
                                 đang tìm participant    
                                 thay thế                

  `WAITING_FOR_ADMIN_DECISION`   Replacement window hết  No
                                 hạn nhưng phòng vẫn     
                                 chưa đủ điều kiện; chờ  
                                 Admin                   

  `READY_FOR_SESSION`            Điều kiện game/tổ chức  No
                                 hợp lệ và tất cả        
                                 participant active đã   
                                 đặt cọc                 

  `COMPLETED`                    Session đã hoàn tất     Yes

  `EXPIRED`                      Hết hạn formation ban   Yes
                                 đầu khi chưa đủ điều    
                                 kiện                    

  `CANCELLED`                    Admin hủy GameRoom      Yes
  ------------------------------------------------------------------------------

Terminal states: `COMPLETED`, `EXPIRED`, `CANCELLED`. Terminal state
không được chuyển sang trạng thái khác. Reopen/rejoin không hồi sinh
GameRoom terminal.

#### 5.4.2. GameRoom transition matrix

  ----------------------------------------------------------------------------------------------------------------------------
  Current                        Event                            Guard          Next                           Actor
  ------------------------------ -------------------------------- -------------- ------------------------------ --------------
  ---                            `ROOM_CREATED`                   Dữ liệu hợp    `FORMING`                      Player
                                                                  lệ; creator có                                
                                                                  dưới 3                                        
                                                                  GameRoom                                      
                                                                  active do mình                                
                                                                  tạo                                           

  `FORMING`                      `CONDITIONS_MET`                 Điều kiện game `WAITING_FOR_DM`               System
                                                                  được đáp ứng                                  

  `FORMING`                      `FORMATION_DEADLINE_EXPIRED`     Hết 4 giờ ban  `EXPIRED`                      System
                                                                  đầu và điều                                   
                                                                  kiện game chưa                                
                                                                  đạt                                           

  `WAITING_FOR_DM`               `DM_ACCEPTED`                    DM được phân   `WAITING_FOR_ORGANIZATION`     DM
                                                                  công hiện tại                                 
                                                                  chấp nhận                                     

  `WAITING_FOR_ORGANIZATION`     `ORGANIZATION_READY`             DM đã accept;  `WAITING_FOR_DEPOSIT`          Admin/System
                                                                  schedule,                                     
                                                                  venue và các                                  
                                                                  guard tổ chức                                 
                                                                  hợp lệ                                        

  `WAITING_FOR_DEPOSIT`          `ALL_DEPOSITS_COMPLETED`         Tất cả         `READY_FOR_SESSION`            System
                                                                  participant                                   
                                                                  active đã                                     
                                                                  deposit và                                    
                                                                  điều kiện                                     
                                                                  game/tổ chức                                  
                                                                  vẫn hợp lệ                                    

  `WAITING_FOR_DEPOSIT`          `ROOM_CONDITIONS_LOST`           Participant    `REPLENISHING`                 System
                                                                  thay đổi khiến                                
                                                                  điều kiện game                                
                                                                  không còn đạt                                 

  `REPLENISHING`                 `REPLACEMENT_CONDITIONS_MET`     Điều kiện game `WAITING_FOR_DEPOSIT`          System
                                                                  được đáp ứng                                  
                                                                  lại                                           

  `REPLENISHING`                 `REPLACEMENT_DEADLINE_EXPIRED`   Hết            `WAITING_FOR_ADMIN_DECISION`   System
                                                                  replacement                                   
                                                                  window và vẫn                                 
                                                                  chưa đủ điều                                  
                                                                  kiện                                          

  `WAITING_FOR_ADMIN_DECISION`   `ADMIN_REOPENS_RECRUITMENT`      Admin có       `REPLENISHING`                 Admin
                                                                  quyền; reason                                 
                                                                  bắt buộc;                                     
                                                                  window 30                                     
                                                                  phút--4 giờ và                                
                                                                  không vượt                                    
                                                                  session start                                 

  `READY_FOR_SESSION`            `DM_OR_SCHEDULE_CHANGED`         DM hoặc lịch   `WAITING_FOR_ORGANIZATION`     Admin/System
                                                                  thay đổi cần                                  
                                                                  xác nhận lại                                  
                                                                  điều kiện tổ                                  
                                                                  chức                                          

  `READY_FOR_SESSION`            `SESSION_COMPLETED`              Session được   `COMPLETED`                    DM
                                                                  xác nhận hoàn                                 
                                                                  tất                                           

  Any nonterminal                `ADMIN_CANCELS_ROOM`             Admin có       `CANCELLED`                    Admin
                                                                  quyền; reason                                 
                                                                  bắt buộc; thực                                
                                                                  hiện side                                     
                                                                  effects đồng                                  
                                                                  bộ                                            
  ----------------------------------------------------------------------------------------------------------------------------

Các lưu ý bắt buộc:

-   `DM_ASSIGNED` và `DM_REJECTED` là sự kiện thuộc DMAssignment; bản
    thân chúng không nhất thiết làm đổi GameRoom status. Khi DM từ chối,
    GameRoom vẫn chờ DM/Admin xử lý.
-   `DEPOSIT_DEADLINE_EXPIRED` là deadline của từng RoomParticipant,
    không phải deadline chung của GameRoom. Hết hạn chỉ hủy participant
    tương ứng rồi đánh giá lại GameRoom.
-   `READY_FOR_SESSION` không yêu cầu 70% còn lại đã thanh toán; chỉ yêu
    cầu tiền cọc 30% của toàn bộ participant active cùng các điều kiện
    game/tổ chức.
-   Khi participant rời phòng lúc GameRoom READY, Backend phải đánh giá
    lại điều kiện. Nếu chỉ thiếu người thì chuyển `REPLENISHING`; nếu
    DM/schedule/venue cần xác nhận lại thì chuyển
    `WAITING_FOR_ORGANIZATION`.
-   Hết replacement window không tự hủy GameRoom. Admin có thể reopen
    tuyển người hoặc hủy room theo quyền đã xác nhận.
-   Admin cancellation được phép từ mọi trạng thái chưa terminal, bắt
    buộc có lý do.
-   Nếu GameRoom đã terminal, không được join/rejoin, reopen hoặc xử lý
    như room đang hoạt động.

#### 5.4.3. GameRoom invariants

1.  Mỗi GameRoom có đúng một trạng thái hiện tại.
2.  Terminal states không được transition tiếp.
3.  `READY_FOR_SESSION` yêu cầu điều kiện game hợp lệ, điều kiện tổ chức
    hợp lệ, danh sách participant active không rỗng và tất cả
    participant active đã đặt cọc 30%.
4.  Chỉ `FORMING` có thể tự chuyển `EXPIRED` do deadline 4 giờ ban đầu.
5.  Hết replacement window không tự động chuyển `CANCELLED`.
6.  Admin có thể hủy mọi GameRoom nonterminal; lý do là bắt buộc.
7.  Mỗi lần Admin reopen phải tạo replacement window mới, lưu lý do,
    tăng `reopen_count` và audit; không xóa lịch sử window cũ.
8.  Participant terminal bị loại khỏi phép tính điều kiện game.
9.  `LEAVE_REQUESTED` vẫn là participant active và tiếp tục được tính
    cho đến khi Admin quyết định.
10. Thay đổi DM hoặc lịch sau READY phải đưa GameRoom về
    `WAITING_FOR_ORGANIZATION`.
11. Mọi transition phải xác minh trạng thái mới nhất trong
    transaction/locking phù hợp.
12. `allMatch()` hoặc phép kiểm tra tương đương không được khiến danh
    sách rỗng mặc nhiên được coi là đã đặt cọc đầy đủ; phải kiểm tra
    danh sách active không rỗng và điều kiện game tối thiểu.

#### 5.4.4. GameRoom side effects

  -----------------------------------------------------------------------
  Event                               Side effects tối thiểu
  ----------------------------------- -----------------------------------
  `ROOM_CREATED`                      Tạo GameRoom, tạo participant của
                                      creator, tạo formation deadline,
                                      ghi audit

  `CONDITIONS_MET`                    Dừng formation timer, chuyển trạng
                                      thái, thông báo Admin

  `FORMATION_DEADLINE_EXPIRED`        Chuyển `EXPIRED`, lưu history,
                                      thông báo participants, giải phóng
                                      tài nguyên liên quan

  `DM_ACCEPTED`                       Cập nhật assignment; chuyển room
                                      theo transition; thông báo các bên
                                      liên quan

  `ORGANIZATION_READY`                Lưu schedule/venue, mở deposit cho
                                      participant hiện hành, tạo deadline
                                      riêng

  `DEPOSIT_CONFIRMED`                 Cập nhật payment/participant và
                                      đánh giá lại GameRoom

  `DEPOSIT_DEADLINE_EXPIRED`          Hủy participant chưa trả cọc; đánh
                                      giá lại điều kiện GameRoom

  `ROOM_CONDITIONS_LOST`              Tạo replacement window, chuyển
                                      `REPLENISHING`, thông báo
                                      Admin/participants

  `ADMIN_REOPENS_RECRUITMENT`         Tạo window mới, lưu reason, tăng
                                      `reopen_count`, audit

  `LEAVE_APPROVED`                    Đóng participation, khởi tạo refund
                                      workflow nếu phù hợp, đánh giá lại
                                      GameRoom

  `ADMIN_CANCELS_ROOM`                Đóng các participation active, khởi
                                      tạo refund workflow phù hợp,
                                      release PhysicalRoom/DM, ghi audit

  `DM_OR_SCHEDULE_CHANGED`            Chuyển về
                                      `WAITING_FOR_ORGANIZATION`, yêu cầu
                                      xác nhận lại tổ chức

  `SESSION_COMPLETED`                 Chuyển `COMPLETED`, ghi lịch sử và
                                      thông báo
  -----------------------------------------------------------------------

Notification delivery failure không được làm rollback business
transaction đã commit. Transactional outbox là hướng thiết kế cần đánh
giá ở phase kiến trúc/implementation; chưa coi là lựa chọn kỹ thuật đã
khóa.

### 5.5. RoomParticipant lifecycle

#### 5.5.1. RoomParticipantStatus

  -----------------------------------------------------------------------
  Status                  Ý nghĩa                 Terminal
  ----------------------- ----------------------- -----------------------
  `PENDING_DEPOSIT`       Đã tham gia nhưng chưa  No
                          hoàn tất đặt cọc        

  `DEPOSITED`             Đã đặt cọc 30% hợp lệ   No

  `LEAVE_REQUESTED`       Đã gửi yêu cầu rời      No
                          phòng, chờ Admin quyết  
                          định                    

  `LEFT`                  Đã rời phòng theo luồng Yes
                          được phép               

  `CANCELLED_UNPAID`      Bị hủy lượt do quá hạn  Yes
                          đặt cọc                 

  `CANCELLED_BY_ADMIN`    Participation bị đóng   Yes
                          do Admin hủy GameRoom   
  -----------------------------------------------------------------------

#### 5.5.2. RoomParticipant transition matrix

  -------------------------------------------------------------------------------------------------------
  Current              Event                        Guard           Next / Action          Actor
  -------------------- ---------------------------- --------------- ---------------------- --------------
  ---                  `PLAYER_JOINED`              Room cho phép   Tạo record             Player
                                                    join; user hợp  `PENDING_DEPOSIT`      
                                                    lệ; không có                           
                                                    active                                 
                                                    participation                          
                                                    trùng                                  

  `PENDING_DEPOSIT`    `DEPOSIT_CONFIRMED`          Payment hợp lệ  `DEPOSITED`            System
                                                    và được xác                            
                                                    nhận theo                              
                                                    policy                                 

  `PENDING_DEPOSIT`    `DEPOSIT_FAILED`             Payment thất    Giữ `PENDING_DEPOSIT`; System
                                                    bại             cho phép retry theo    
                                                                    payment policy         

  `PENDING_DEPOSIT`    `DEPOSIT_DEADLINE_EXPIRED`   Đã quá deadline `CANCELLED_UNPAID`     System
                                                    và chưa có                             
                                                    deposit hợp lệ                         

  `PENDING_DEPOSIT`    `PLAYER_LEAVES`              Chưa deposit    `LEFT`                 Player

  `DEPOSITED`          `LEAVE_REQUESTED`            Participant còn `LEAVE_REQUESTED`      Player
                                                    active                                 

  `LEAVE_REQUESTED`    `LEAVE_APPROVED`             Admin quyết     `LEFT`                 Admin
                                                    định; reason                           
                                                    bắt buộc                               

  `LEAVE_REQUESTED`    `LEAVE_REJECTED`             Admin quyết     `DEPOSITED`            Admin
                                                    định; reason                           
                                                    bắt buộc                               

  Active participation `ROOM_CANCELLED`             GameRoom chuyển `CANCELLED_BY_ADMIN`   System
                                                    `CANCELLED`                            

  `LEFT` hoặc          `PLAYER_REJOINS`             GameRoom cho    Tạo record mới         Player
  `CANCELLED_UNPAID`                                phép; user đủ   `PENDING_DEPOSIT`; giữ 
                                                    điều kiện;      record cũ              
                                                    không có active                        
                                                    participation                          
  -------------------------------------------------------------------------------------------------------

`LEFT`, `CANCELLED_UNPAID` và `CANCELLED_BY_ADMIN` là terminal
participant states. Không tái kích hoạt record terminal; rejoin luôn tạo
record mới.

#### 5.5.3. RoomParticipant rules and invariants

-   Mỗi `(game_room_id, user_id)` chỉ có tối đa một participation active
    tại cùng thời điểm.
-   `LEAVE_REQUESTED` vẫn là active và vẫn được tính vào điều kiện/số
    lượng cho đến khi Admin approve.
-   Chỉ participant đã `DEPOSITED` mới được gửi yêu cầu rời phòng cần
    Admin review.
-   Admin bắt buộc nhập lý do khi approve/reject yêu cầu rời phòng.
-   `PENDING_DEPOSIT` quá deadline chỉ hủy participant tương ứng, không
    tự động hủy GameRoom.
-   Participant đã terminal không được tái sử dụng; rejoin tạo record
    mới.
-   Người đã `LEFT` hoặc `CANCELLED_UNPAID` được phép rejoin nếu
    GameRoom còn cho phép.
-   Rejoin được phép ngay cả khi refund của participation cũ đang
    pending. Payment/refund cũ và payment/deposit mới là các lifecycle
    độc lập.
-   Không giới hạn số lần rejoin trong MVP; mọi record và hành động phải
    được audit.
-   Participant mới không làm reset deadline của participant hiện hữu.
-   Participant terminal không được tính vào điều kiện game hoặc
    `deposit_all`.
-   Khi GameRoom bị Admin hủy, mọi participation active phải được đóng
    theo quy tắc hủy; không xóa vật lý lịch sử.

### 5.6. Deadline policies

  -----------------------------------------------------------------------
  Deadline                Bắt đầu tính từ         Thời lượng/quy tắc
  ----------------------- ----------------------- -----------------------
  Initial formation       GameRoom được tạo       4 giờ; chỉ áp dụng khi
                                                  GameRoom ở `FORMING`

  Initial participant     Hệ thống mở thanh toán  2 giờ
  deposit                                         

  Replacement participant Join thành công         2 giờ; riêng từng
  deposit                                         participant

  Rejoin participant      Rejoin thành công, áp   2 giờ theo cách áp dụng
  deposit                 dụng policy participant policy participant mới;
                          mới                     không phải quyết định
                                                  riêng độc lập

  Replacement window      Admin bắt đầu/mở window 30 phút--4 giờ; không
                                                  vượt session start

  DM preparation          Mốc phòng đủ điều       Tối thiểu 12 giờ trước
                          kiện/được xác nhận theo session start
                          policy hiện hành        
  -----------------------------------------------------------------------

-   Lưu deadline đặt cọc ở cấp RoomParticipant hoặc payment obligation
    gắn với participant; không dùng một deadline toàn GameRoom cho tất
    cả.
-   Không reset deadline của participant cũ khi participant mới tham
    gia.
-   Reminder ở mốc còn 1 giờ và 30 phút phải gắn với deadline tương ứng;
    không gửi reminder đặt cọc sau khi payment đã hợp lệ.
-   Không tự thêm guard "từ chối join/rejoin nếu không đủ 2 giờ trước
    session start" khi chưa được nhóm xác nhận.
-   Deadline worker phải xử lý idempotent; kiểm tra lại trạng
    thái/payment hiện tại trước khi chuyển participant sang terminal.

### 5.7. Quy tắc hủy GameRoom và Admin decision

-   Admin có quyền hủy GameRoom ở bất kỳ trạng thái chưa terminal
    (`any_nonterminal`).
-   Admin bắt buộc nhập reason không rỗng khi hủy.
-   Không cho phép Admin hủy GameRoom đã `COMPLETED`, `EXPIRED` hoặc
    `CANCELLED`.
-   Hủy GameRoom phải đóng các participant active, xử lý các
    assignment/session/venue liên quan, khởi tạo refund workflow nếu có
    khoản tiền phải hoàn và lưu audit.
-   Các side effect liên domain phải được điều phối nhất quán; không
    được chỉ đổi status GameRoom rồi bỏ lại booking/assignment/payment
    đang active.
-   Khi replacement window hết hạn, Admin quyết định reopen tuyển người
    hoặc hủy room; hệ thống không tự chọn quyết định.
-   Admin có thể reopen nhiều lần không giới hạn trong MVP; mỗi lần cần
    reason và window hợp lệ mới.

### 5.8. Chỉnh sửa phòng, lịch và các domain khác

-   Người tạo chỉ chỉnh sửa các trường được phép; danh sách trường cụ
    thể còn mở.
-   Mọi thay đổi phải được backend revalidate điều kiện game, thời gian,
    thành viên và ràng buộc lịch.
-   Không cho phép chỉnh sửa làm ảnh hưởng người đã đặt cọc/xác nhận nếu
    chưa có quy trình xử lý tương ứng.
-   Admin đề xuất lịch mới phải thu thập xác nhận của người chơi theo
    quy trình; thời hạn phản hồi và cách xử lý khi người chơi không đồng
    ý còn mở.
-   Thay đổi DM hoặc schedule sau `READY_FOR_SESSION` chuyển GameRoom về
    `WAITING_FOR_ORGANIZATION`.
-   Thiết kế state machine Phase 4 cho DMAssignment,
    ScheduleProposal/Session, PlayerPayment và Refund được mô tả tại
    Section 5.12. Các chính sách được đánh dấu OPEN vẫn cần xác nhận;
    không tự suy diễn thành yêu cầu đã chốt.

### 5.9. Notification Center và polling

-   Player, DM và Admin có Notification Center trong portal tương ứng.
-   Thông báo được lưu trong database, có trạng thái đã đọc/chưa đọc.
-   Các sự kiện chính gồm: tham gia/rời phòng; phòng đủ điều kiện/hết
    hạn; phân công DM và phản hồi; mở thanh toán; nhắc hạn; hủy lượt do
    quá hạn; phòng mất điều kiện; đổi lịch; xác nhận thanh toán; hủy
    phiên và hoàn tiền.
-   MVP dùng polling định kỳ để cập nhật gần thời gian thực; không triển
    khai WebSocket.
-   Chu kỳ polling phải được chốt theo từng màn hình/nhóm dữ liệu và
    tránh gọi API khi không cần thiết.

### 5.10. Lịch sử và audit

-   Lưu lịch sử trạng thái GameRoom, RoomParticipant, replacement
    windows, thay đổi lịch, phân công DM, phản hồi DM, thanh toán/hoàn
    tiền, yêu cầu rời phòng và quyết định của Admin.
-   Mỗi bản ghi lịch sử quan trọng cần xác định được đối tượng, hành
    động, người thực hiện và thời điểm; lưu reason khi nghiệp vụ yêu
    cầu.
-   Lưu `reopen_count` trên GameRoom hoặc một cấu trúc tương đương có
    thể truy vết; replacement window cũ không bị ghi đè.
-   Rejoin tạo participant record mới; không xóa hoặc ghi đè
    participation cũ.
-   Không xóa vật lý dữ liệu nghiệp vụ cần phục vụ tra cứu/audit chỉ vì
    phòng đã kết thúc; chuyển trạng thái terminal và áp dụng chính sách
    lưu trữ riêng nếu cần.

### 5.11. Trạng thái và kết quả

-   Backend là nguồn sự thật duy nhất về trạng thái phòng, thành viên,
    phân công DM, lịch, phiên chơi và thanh toán.
-   Frontend hiển thị trạng thái từ API và cập nhật bằng polling.
-   Enum và transition matrix của GameRoom/RoomParticipant được xác định
    tại Phase 3 trong tài liệu này.
-   State machine của DMAssignment, ScheduleProposal/Session,
    PlayerPayment và Refund được đặc tả ở Section 5.12; chỉ triển khai
    theo transition/guard đã được nghiệm thu, không suy ra từ UI.

### 5.12. Phase 4 --- Các domain state machine còn lại (Accepted)

#### 5.12.1. DMAssignment lifecycle

**Trạng thái**
| Status | Ý nghĩa | Terminal |
| :--- | :--- | :--- |
| `PENDING` | Admin đã tạo phân công, chờ DM phản hồi | No |
| `ACCEPTED` | DM được phân công đã chấp nhận | No |
| `REJECTED` | DM từ chối phân công | Yes |
| `CANCELLED` | Phân công bị Admin hủy hoặc thu hồi | Yes |

**Transition matrix**
| Current | Event | Guard / điều kiện | Next / Action | Actor |
| :--- | :--- | :--- | :--- | :--- |
| --- | `ASSIGN_DM` | GameRoom đang chờ DM hoặc cần phân công lại; DM hợp lệ và không trùng lịch | Tạo assignment mới ở `PENDING`; lưu người giao, thời điểm và audit | Admin |
| `PENDING` | `DM_ACCEPTS` | Đúng DM được giao; assignment vẫn là hiện hành; GameRoom chưa terminal | `ACCEPTED`; cập nhật điều kiện DM của GameRoom | DM |
| `PENDING` | `DM_REJECTS` | Đúng DM được giao; reason không rỗng | `REJECTED`; lưu reason; thông báo Admin để tìm DM khác | DM |
| `PENDING` | `ADMIN_CANCELS_ASSIGNMENT` | Admin có quyền; assignment còn hiệu lực | `CANCELLED`; lưu audit; cho phép Admin tạo assignment mới | Admin |
| `ACCEPTED` | `ADMIN_REVOKES_ASSIGNMENT` | Admin có quyền; Session chưa `IN_PROGRESS`/`COMPLETED`; reason bắt buộc | `CANCELLED`; lưu reason & audit; đưa GameRoom về `WAITING_FOR_ORGANIZATION` | Admin |

#### 5.12.2. ScheduleProposal lifecycle

**Trạng thái**
| Status | Ý nghĩa | Terminal |
| :--- | :--- | :--- |
| `PROPOSED` | Đề xuất lịch mới đang chờ 100% participant active phản hồi (hạn 2 giờ) | No |
| `CONFIRMED` | Đạt 100% đồng ý và đã trở thành lịch chính thức | Yes |
| `REJECTED` | Có ít nhất 1 người từ chối hoặc hết hạn 2 giờ chưa đủ 100% | Yes |
| `CANCELLED` | Đề xuất bị Admin hủy trước khi kết thúc | Yes |

**Transition matrix**
| Current | Event | Guard / điều kiện | Next / Action | Actor |
| :--- | :--- | :--- | :--- | :--- |
| --- | `PROPOSE_SCHEDULE` | Admin có quyền; phòng chưa có proposal nào đang `PROPOSED`; lịch mới hợp lệ | Tạo proposal `PROPOSED` với deadline 2 giờ (không vượt giờ chơi cũ); lưu snapshot lịch cũ | Admin |
| `PROPOSED` | `SCHEDULE_ACCEPTANCE_COMPLETED` | **100% participant active** đã bấm đồng ý trong hạn; DM/venue/xung đột lịch/12h chuẩn bị hợp lệ | `CONFIRMED`; cập nhật lịch chính thức; lưu lịch sử; đưa phòng về `WAITING_FOR_ORGANIZATION` nếu đang READY | System |
| `PROPOSED` | `SCHEDULE_REJECTED` | Có ít nhất 1 participant active từ chối HOẶC hết deadline 2 giờ mà chưa đủ 100% đồng ý | `REJECTED`; giữ nguyên lịch chính thức hiện tại; thông báo Admin | Player/System |
| `PROPOSED` | `CANCEL_SCHEDULE_PROPOSAL` | Proposal chưa terminal; Admin có quyền | `CANCELLED`; giữ nguyên lịch chính thức hiện tại | Admin |

#### 5.12.3. Session lifecycle

**Trạng thái**
| Status | Ý nghĩa | Terminal |
| :--- | :--- | :--- |
| `SCHEDULED` | Session đã có lịch/địa điểm được xác nhận | No |
| `IN_PROGRESS` | Phiên chơi đã bắt đầu thực tế tại quán | No |
| `COMPLETED` | Phiên chơi hoàn tất | Yes |
| `CANCELLED` | Phiên chơi bị hủy | Yes |

**Transition matrix**
| Current | Event | Guard / điều kiện | Next / Action | Actor |
| :--- | :--- | :--- | :--- | :--- |
| --- | `CREATE_SESSION` | DM accepted; lịch và venue được xác nhận; các guard tổ chức hợp lệ | Tạo Session `SCHEDULED`; liên kết GameRoom, schedule, venue và DM | System/Admin |
| `SCHEDULED` | `SESSION_STARTED` | GameRoom đang ở trạng thái `READY_FOR_SESSION`; đúng DM được phân công hoặc Admin thao tác | `IN_PROGRESS`; lưu thời điểm bắt đầu thực tế | DM / Admin |
| `IN_PROGRESS` | `SESSION_COMPLETED` | Session đang diễn ra; đúng DM được phân công hoặc Admin thao tác | `COMPLETED`; đồng bộ GameRoom sang `COMPLETED`; lưu audit | DM / Admin |
| `SCHEDULED` | `CANCEL_SESSION` | GameRoom bị hủy hoặc tổ chức lại; xử lý reservation/refund liên quan | `CANCELLED`; đồng bộ các domain liên quan | Admin/System |
| `IN_PROGRESS` | `CANCEL_SESSION` | Chỉ **Admin** có quyền hủy khẩn cấp; reason bắt buộc | `CANCELLED`; đồng bộ GameRoom sang `CANCELLED`; Admin tự tạo refund thủ công nếu cần | Admin |

#### 5.12.4. PlayerPayment lifecycle

**Trạng thái**
| Status | Ý nghĩa | Terminal |
| :--- | :--- | :--- |
| `NOT_OPEN` | Chưa mở nghĩa vụ thanh toán | No |
| `DEPOSIT_PENDING` | Đã mở thanh toán, đang chờ tiền cọc 30% (VNĐ, làm tròn lên `1.000đ`) | No |
| `PARTIALLY_PAID` | Đã ghi nhận tiền cọc 30%, còn khoản 70% (`Tổng vé - Tiền cọc`) | No |
| `PAID` | Đã thanh toán đủ 100% | Yes |
| `EXPIRED` | Hết deadline cọc 2 giờ mà chưa có khoản cọc hợp lệ | Yes |
| `CANCELLED` | Nghĩa vụ thanh toán bị đóng trước khi có khoản thanh toán thành công | Yes |

**Transition matrix**
| Current | Event | Guard / điều kiện | Next / Action | Actor |
| :--- | :--- | :--- | :--- | :--- |
| `NOT_OPEN` | `OPEN_DEPOSIT` | DM accepted và điều kiện tổ chức hợp lệ; participant còn active | `DEPOSIT_PENDING`; tính tiền cọc 30% (làm tròn lên `1.000đ`); tạo deadline 2 giờ | System |
| `DEPOSIT_PENDING` | `DEPOSIT_CONFIRMED` | Payment hợp lệ; server nhận request trước khi participant bị job chuyển `CANCELLED_UNPAID` | `PARTIALLY_PAID`; ghi nhận số tiền thực nhận; cập nhật RoomParticipant thành `DEPOSITED` | System/Admin |
| `DEPOSIT_PENDING` | `FULL_PAYMENT_CONFIRMED` | Giao dịch hợp lệ và tổng số tiền thực nhận đạt 100% giá vé | `PAID`; cập nhật participant thành `DEPOSITED` | System/Admin |
| `DEPOSIT_PENDING` | `DEPOSIT_DEADLINE_EXPIRED` | Deadline 2 giờ đã qua; chưa có deposit hợp lệ trong transaction lock | `EXPIRED`; hủy participant thành `CANCELLED_UNPAID`; đánh giá lại GameRoom | System |
| `NOT_OPEN` hoặc `DEPOSIT_PENDING` | `CLOSE_UNPAID_OBLIGATION` | Room/participant bị đóng trước khi có payment thành công | `CANCELLED`; không tạo refund | System |
| `PARTIALLY_PAID` | `REMAINING_PAYMENT_CONFIRMED` | Giao dịch hợp lệ; tổng tiền thực nhận đạt 100% giá vé | `PAID`; lưu payment record, người xác nhận tại quán và audit | System/Admin/DM |

#### 5.12.5. Refund lifecycle

**Trạng thái**
| Status | Ý nghĩa | Terminal |
| :--- | :--- | :--- |
| `REQUESTED` | Refund được tạo, đang chờ Admin xử lý thủ công | No |
| `PROCESSING` | Admin đang thực hiện hoàn tiền mô phỏng | No |
| `SUCCEEDED` | Admin đã xác nhận hoàn tiền thành công, có mã tham chiếu/ghi chú | Yes |
| `FAILED` | Lần xử lý hoàn tiền ghi nhận thất bại | No |
| `REJECTED` | Yêu cầu hoàn tiền bị từ chối theo quyết định của Admin | Yes |

**Transition matrix**
| Current | Event | Guard / điều kiện | Next / Action | Actor |
| :--- | :--- | :--- | :--- | :--- |
| --- | `REQUEST_REFUND` | Có payment thực nhận; phát sinh từ hủy phòng phía tổ chức (100%) hoặc Admin duyệt rời phòng có hoàn tiền (100%) | Tạo refund `REQUESTED`; số tiền không vượt quá thực nhận | System/Admin |
| `REQUESTED` | `START_REFUND` | Admin bắt đầu xử lý khoản hoàn | `PROCESSING`; lưu thời điểm và người xử lý | Admin |
| `REQUESTED` | `REJECT_REFUND` | Có căn cứ từ chối; reason bắt buộc | `REJECTED`; lưu reason và actor | Admin |
| `REQUESTED` hoặc `PROCESSING` | `REFUND_SUCCEEDED` | Admin xác nhận đã hoàn tiền thủ công thành công (kèm mã giao dịch tham chiếu/ghi chú) | `SUCCEEDED`; lưu bằng chứng, timestamp và số tiền thực hoàn | Admin |
| `PROCESSING` | `REFUND_FAILED` | Admin ghi nhận giao dịch hoàn thất bại | `FAILED`; lưu lý do lỗi | Admin |
| `FAILED` | `RETRY_REFUND` | Admin chủ động thao tác thử lại (không giới hạn số lần) | `PROCESSING`; tạo attempt mới, giữ log cũ | Admin |

#### 5.12.6. Liên kết và side effects giữa các domain
| Trigger | Domain nguồn | Domain chịu ảnh hưởng | Side effects tối thiểu |
| :--- | :--- | :--- | :--- |
| DM từ chối | DMAssignment | GameRoom, Notification, Audit | Assignment thành `REJECTED`; GameRoom tiếp tục chờ DM; thông báo Admin |
| DM chấp nhận | DMAssignment | GameRoom, Schedule/Session | Assignment thành `ACCEPTED`; GameRoom chuyển `WAITING_FOR_ORGANIZATION` |
| Admin thu hồi DM đã accept | DMAssignment | GameRoom, Session | Assignment thành `CANCELLED`; GameRoom về `WAITING_FOR_ORGANIZATION` |
| Lịch được xác nhận (100%) | ScheduleProposal | Session, GameRoom, Venue | Cập nhật lịch chính thức; revalidate DM/venue; GameRoom về `WAITING_FOR_ORGANIZATION` nếu đang READY |
| Tổ chức hợp lệ | Session/Organization | PlayerPayment, GameRoom | Mở deposit cho từng participant active; tính 30% làm tròn lên `1.000đ`; tạo deadline 2 giờ |
| Deposit hợp lệ | PlayerPayment | RoomParticipant, GameRoom | Participant thành `DEPOSITED`; đánh giá lại điều kiện GameRoom |
| Deposit deadline hết hạn | PlayerPayment | RoomParticipant, GameRoom | Chỉ participant tương ứng bị `CANCELLED_UNPAID`; đánh giá lại room |
| Admin hủy GameRoom | GameRoom | DMAssignment, Session, Venue, PlayerPayment, Refund, Audit | Đóng participation active; hủy assignment/session/reservation; tạo refund `REQUESTED` 100% cho khoản đã nhận |
| Admin duyệt rời phòng sau cọc | RoomParticipant | GameRoom, Refund, Audit | Participant thành `LEFT`; nếu Admin chọn hoàn cọc thì tạo refund `REQUESTED` 100%; đánh giá lại room |
| Refund hoàn tất | Refund | Notification, Audit | Admin xác nhận `SUCCEEDED`, lưu mã tham chiếu và thông báo người chơi |

---

## 6. Yêu cầu phi chức năng

### 6.1. Maintainability

-   Ưu tiên Clean Code, SOLID và separation of concerns.
-   Tên biến, hàm, class và module rõ nghĩa.
-   Tránh duplicate logic.
-   Tránh business logic trong UI component hoặc controller.
-   Giữ cấu trúc đủ đơn giản để nhóm 6 người có thể hiểu và phối hợp
    trong 4 tuần.

### 6.2. Security

-   Validate dữ liệu ở backend.
-   Phân quyền ở backend, không chỉ ẩn nút trên frontend.
-   Không hardcode secret, password hoặc token.
-   Không commit `.env`.
-   Dùng `.env.example` với giá trị minh họa.
-   Không log password, token hoặc dữ liệu nhạy cảm.
-   Dùng truy vấn có tham số thông qua cơ chế persistence an toàn.
-   Xử lý lỗi mà không trả stack trace hoặc thông tin nội bộ cho client.
-   Cấu hình CORS theo origin cần thiết, không mở rộng tùy tiện.
-   Admin-only transitions (cancel/reopen/leave decision) phải kiểm tra
    role và resource authorization ở backend; reason phải được validate
    và lưu audit.
-   Không cho phép client tự gửi state đích để ép transition; API nhận
    command/event có ý nghĩa nghiệp vụ, Backend xác định trạng thái tiếp
    theo.
-   Chống IDOR: mọi thao tác trên GameRoom/RoomParticipant phải xác minh
    quyền trên tài nguyên.
-   Chống replay/duplicate request cho payment callback và command có
    side effect bằng idempotency hoặc cơ chế tương đương phù hợp.

### 6.3. Reliability và data integrity

-   Có ràng buộc database cho dữ liệu quan trọng.
-   Xử lý request lỗi và tình huống dữ liệu thay đổi đồng thời.
-   Không xác nhận booking nếu thao tác lưu dữ liệu thất bại.
-   Quy tắc chống đặt vượt sức chứa phải được xử lý ở backend/database,
    không chỉ ở giao diện.
-   Migration phải có thể kiểm tra và tái lập trên database local mới.
-   Transition phải kiểm tra trạng thái hiện tại mới nhất trong
    transaction.
-   Simultaneous join ở slot cuối phải được bảo vệ bằng
    transaction/locking/constraint phù hợp.
-   Duplicate event hoặc duplicate callback không được thực hiện side
    effect nhiều lần.
-   Race giữa payment và deposit deadline phải được giải quyết dựa trên
    timestamp/quy tắc xác nhận có thẩm quyền và locking phù hợp.
-   Callback thanh toán đến muộn sau khi participant đã terminal không
    được tự động re-activate participant; phải đi theo quy trình
    reconciliation/refund.
-   Reopen tạo window mới, không ghi đè window cũ.
-   Khi room bị cancel, không được tiếp tục join, payment, assignment
    hoặc booking mới dựa trên trạng thái cũ.
-   Notification/email failure không được rollback business transaction
    đã commit; cơ chế delivery bền vững sẽ được thiết kế ở phase phù
    hợp.

### 6.4. Performance và usability

-   Ưu tiên tải nhanh các trang thuộc luồng booking.
-   Tránh gọi API lặp không cần thiết.
-   Có trạng thái loading và phản hồi khi thao tác.
-   Giao diện phải sử dụng được trên desktop và mobile.
-   Không bổ sung animation hoặc hiệu ứng làm ảnh hưởng khả năng sử
    dụng.

### 6.5. Testing

-   Unit test cho logic nghiệp vụ quan trọng.
-   Integration test cho repository/API ở phạm vi phù hợp.
-   Kiểm thử frontend cho các tương tác và trạng thái chính.
-   Kiểm thử thủ công luồng booking end-to-end.
-   Không báo PASS nếu chưa thực sự chạy lệnh kiểm thử tương ứng.
-   GameRoom tests phải bao phủ transition hợp lệ/không hợp lệ, terminal
    states, admin cancel, reopen window boundaries, READY guards và
    DM/schedule change sau READY.
-   RoomParticipant tests phải bao phủ join, deposit, unpaid deadline,
    leave, admin approve/reject, rejoin tạo record mới, rejoin khi
    refund pending và ngăn duplicate active participation.
-   Concurrency tests phải bao phủ simultaneous join cuối slot,
    payment/deadline race, duplicate events và cancel-vs-join/payment.
-   Kiểm thử deadline phải bảo đảm mỗi participant có deadline riêng và
    join participant mới không reset deadline participant cũ.

------------------------------------------------------------------------

## 7. Dữ liệu và API

### 7.1. Nguyên tắc thiết kế dữ liệu

ERD và Database Schema là deliverable thiết kế mới của nhóm tại Phase 5. Thiết kế phải xuất phát từ nghiệp vụ đã Accepted tại Phase 3/4 và các yêu cầu sản phẩm trong tài liệu này; không sao chép nguyên schema, bảng, quan hệ hoặc migration từ repository tham khảo.

Các domain/data concepts cần được phân tích trong ERD gồm:
- Identity: User, Role, Profile.
- Game: Game, GameRule/RuleVersion và cấu hình điều kiện có cấu trúc.
- Game room: GameRoom, RoomParticipant, trạng thái/lịch sử chuyển trạng thái, ReplacementWindow và lịch sử chỉnh sửa nếu cần.
- DM: DM profile/availability, DMAssignment và assignment history.
- Venue: PhysicalRoom, capacity và trạng thái sử dụng.
- Session/Scheduling: Session, ScheduleProposal, participant responses và lịch chính thức.
- Payment: PlayerPayment, các khoản thu/deposit/remaining payment, Refund và payment attempts/history.
- Notification: Notification và trạng thái đọc theo người nhận.
- Audit: lịch sử thao tác nghiệp vụ cần truy vết.

Danh sách trên là phạm vi domain cần khảo sát, **không phải danh sách bảng đã chốt**. Tên bảng, cách tách/gộp entity, khóa chính/ngoại, cardinality và kiểu dữ liệu phải được nhóm phân tích và quyết định trong Phase 5.

Phải phân biệt **GameRoom** (nhóm người chơi đăng ký cùng một game) với **PhysicalRoom** (phòng thực tế tại quán). Không gộp hai khái niệm thành một entity.

#### 7.1.1. Data design requirements từ Phase 3 & Phase 4

Các invariant và yêu cầu dữ liệu sau phải được bảo toàn trong ERD/database design:
- Một GameRoom có một trạng thái hiện tại; lịch sử transition phải truy vết được.
- Một GameRoom có nhiều RoomParticipant records theo thời gian; mỗi lần rejoin tạo record mới.
- Tại một thời điểm, chỉ có tối đa một RoomParticipant active cho cùng cặp `(game_room_id, user_id)`.
- RoomParticipant cần lưu status, joinedAt, depositDeadline và các mốc thời gian cần thiết để audit.
- `LEFT`, `CANCELLED_UNPAID`, `CANCELLED_BY_ADMIN` là trạng thái terminal của participation.
- Lưu actor, reason và timestamp đối với Admin cancel/reopen, approve/reject leave, DM revoke, Session emergency cancel và các quyết định refund liên quan.
- Lưu được số lần reopen hoặc dữ liệu lịch sử tương đương.
- Mỗi replacement window là một occurrence riêng, không ghi đè lịch sử window cũ.
- Deposit/payment/refund của participation cũ độc lập với participation mới sau rejoin.
- Rejoin khi refund pending vẫn phải bảo toàn quan hệ giữa refund cũ và participant cũ.
- Không hard-delete các RoomParticipant record cũ chỉ vì user rejoin.
- `DMAssignment` phải lưu được lịch sử phân công, từ chối, hủy/thu hồi và actor/reason tương ứng.
- Mỗi GameRoom chỉ có tối đa một ScheduleProposal đang `PROPOSED`; phải lưu deadline và phản hồi của từng participant active.
- Lịch cũ phải được bảo toàn khi ScheduleProposal bị `REJECTED` hoặc `CANCELLED`.
- Session phải lưu được trạng thái, mốc bắt đầu/kết thúc thực tế và actor thực hiện các transition quan trọng.
- Payment phải lưu được tổng tiền, deposit, remaining amount, số tiền thực nhận và các mốc thời gian cần thiết để xử lý deadline/race condition.
- Refund không vượt quá tổng số tiền đã thực nhận; các lần xử lý/retry cần giữ lịch sử độc lập.
- Các thao tác cập nhật chéo domain cần xác định transaction boundary và concurrency strategy.

Dữ liệu cấu hình game cần có version/history để việc cập nhật rule không âm thầm thay đổi điều kiện của các phòng đã tạo. Cách áp dụng rule version cho GameRoom đang hoạt động là open decision tại Section 2.4.

**Không coi bất kỳ bảng, field, index hoặc quan hệ minh họa nào trong tài liệu tham khảo là thiết kế đã được phê duyệt.** ERD, constraints, indexes, transaction boundaries và Flyway migrations chỉ được chốt sau review/acceptance Phase 5.

### 7.2. Nguyên tắc API

API Contract là deliverable thiết kế mới của nhóm tại Phase 6. Nhóm tự xác định endpoint, HTTP method, request/response DTO, validation, error contract, authorization và idempotency strategy dựa trên Product Specification V15 Final cùng Database Design đã được phê duyệt. Không sao chép nguyên trạng endpoint, payload hoặc API contract từ repository tham khảo.

Các nguyên tắc bắt buộc:
- Sử dụng REST API và JSON, có quy ước versioning nhất quán (định hướng `/api/v1`; contract cụ thể được chốt tại Phase 6).
- Request/response dùng DTO; không trả trực tiếp persistence entity.
- HTTP status code đúng ngữ nghĩa; lỗi có định dạng thống nhất.
- Validate request và authorization ở Backend.
- Các thao tác thay đổi trạng thái quan trọng phải có transaction và kiểm soát concurrency phù hợp.
- Không tin tổng tiền, tỷ lệ, quyền, trạng thái hoặc điều kiện do client tự gửi; Backend tự tính và xác minh.
- API polling cần hỗ trợ truy vấn thay đổi gần đây/phân trang phù hợp để tránh tải lại toàn bộ dữ liệu liên tục.
- API Contract phải ghi rõ quyền truy cập theo vai trò và quyền trên từng resource.
- API thay đổi trạng thái biểu diễn command nghiệp vụ; client không được tự chỉ định status tiếp theo.
- Admin cancel/reopen/leave decision, DM revoke và Session emergency cancel phải có reason bắt buộc theo policy.
- Join/rejoin cần idempotency/concurrency strategy để không tạo duplicate active participation.
- Payment/deadline endpoints phải xử lý duplicate và race condition theo policy Phase 3/4 đã Accepted.

### 7.3. Nhóm API năng lực dự kiến

Danh sách dưới đây chỉ mô tả nhóm năng lực sản phẩm, **không phải endpoint contract đã chốt**:
- Authentication/profile: đăng ký, đăng nhập, đăng xuất, hồ sơ cá nhân.
- Game: danh sách/chi tiết game và thông tin điều kiện công khai.
- GameRoom: tạo, tìm kiếm, xem chi tiết, tham gia, rejoin, rời phòng, chỉnh sửa theo quyền, xem lịch sử/trạng thái.
- GameRoom Admin: cancel GameRoom; reopen recruitment; truy vấn replacement windows và audit.
- RoomParticipant: deposit status, leave request, Admin approve/reject, participant history.
- DM: xem phân công, accept/reject, quản lý lịch khả dụng và cập nhật trạng thái phiên được giao.
- Admin: quản lý user/role, game/rule, GameRoom, phân công DM, PhysicalRoom, lịch, thanh toán, yêu cầu rời phòng và refund.
- Scheduling/Session: tạo và phản hồi ScheduleProposal, xác nhận lịch, bắt đầu/hoàn thành/hủy Session theo quyền.
- Payment: mở nghĩa vụ thanh toán, ghi nhận tiền cọc mô phỏng, ghi nhận khoản còn lại và refund mô phỏng.
- Notification: danh sách thông báo, đánh dấu đã đọc/chưa đọc và polling cập nhật.
- History/audit: truy vấn lịch sử theo quyền.

Path, payload, response, error codes, idempotency requirements và authorization matrix phải được thống nhất trong API Contract trước khi Frontend/Backend triển khai song song.

## 8. Môi trường phát triển

### 8.1. Công cụ chính

  -----------------------------------------------------------------------
  Công cụ                             Vai trò
  ----------------------------------- -----------------------------------
  Visual Studio Code                  IDE chính để viết code, debug,
                                      terminal và quản lý workspace

  Antigravity                         AI coding agent hỗ trợ audit, lập
                                      kế hoạch, viết và chỉnh sửa code

  Git                                 Quản lý phiên bản local

  GitHub                              Repository chính thức và cộng tác
                                      nhóm

  Node.js + npm                       Môi trường frontend

  Java 21                             Runtime và compiler backend

  Maven / Maven Wrapper               Build và dependency management
                                      backend

  PostgreSQL                          Database chạy native trên Windows
  -----------------------------------------------------------------------

### 8.2. Quy tắc local development

-   Cài đặt và chạy các thành phần trực tiếp trên Windows.
-   PostgreSQL chạy như Windows service nếu được cài theo cách đó.
-   Frontend chạy bằng npm scripts.
-   Backend chạy bằng Maven Wrapper hoặc Maven đã cài.
-   Kết nối database qua `localhost`.
-   Thông tin kết nối lấy từ biến môi trường hoặc cấu hình local không
    commit.
-   Không sử dụng Docker, Docker Desktop, Docker Compose hoặc container
    runtime trong bất kỳ phase nào của dự án hiện tại.

### 8.3. Triển khai sau khi hệ thống ổn định

-   Không sử dụng Docker/Docker Compose/containerization trong bất kỳ
    phase nào của phạm vi dự án hiện tại.
-   Trong giai đoạn phát triển và nghiệm thu local, các thành phần chạy
    native trên Windows theo Section 8.2.
-   Chỉ bắt đầu thiết kế triển khai sau khi hệ thống đã ổn định và các
    luồng MVP được nghiệm thu.
-   Hình thức triển khai, hosting, CI/CD, cấu hình môi trường
    production, secrets, backup và monitoring là phạm vi thiết kế riêng
    ở giai đoạn sau; không mặc định chọn Docker hoặc bất kỳ nền tảng
    triển khai nào trước khi nhóm thống nhất.

------------------------------------------------------------------------

## 9. Giới hạn và giả định

-   Thời gian thực hiện mục tiêu: 4 tuần.
-   Nhóm có 6 thành viên.
-   Ưu tiên MVP hoạt động được trước, sau đó mới cải tiến.
-   Frontend được nhóm tự thiết kế và xây dựng lại; không phụ thuộc source Frontend của repository tham khảo.
-   Repository tham khảo không phải repository phát triển chính thức.
-   Thanh toán mô phỏng trong MVP, trừ khi nhóm có quyết định mới.
-   Các quy tắc nghiệp vụ chưa xác nhận phải được ghi thành open
    questions.
-   Phase 3 đã nghiệm thu về mặt thiết kế; không được diễn giải thành
    code/test đã hoàn thành.

------------------------------------------------------------------------

## 10. Tiêu chí nghiệm thu sản phẩm MVP

MVP chỉ được đánh dấu hoàn thành khi các tiêu chí P0 sau được kiểm thử
và có bằng chứng.

### 10.1. Môi trường và tích hợp

-   [ ] Frontend chạy được native trên local.
-   [ ] Backend chạy được với Java 21/Maven.
-   [ ] Backend kết nối PostgreSQL native thành công.
-   [ ] Flyway migration chạy thành công trên database local mới.
-   [ ] Frontend gọi được backend qua REST API/JSON.
-   [ ] README hướng dẫn thành viên setup và chạy dự án native trên
    Windows; không sử dụng Docker trong bất kỳ phase nào của dự án hiện
    tại.

### 10.2. Public Home Page và Player Portal

-   [ ] Người dùng truy cập Home Page mà không cần đăng nhập.
-   [ ] Người chưa đăng nhập xem được danh sách/chi tiết game và
    GameRoom công khai.
-   [ ] Người chưa đăng nhập không thể tạo/tham gia GameRoom hoặc truy
    cập dữ liệu cá nhân.
-   [ ] Khi thực hiện thao tác cần xác thực, người dùng được yêu cầu
    Login/Register và sau đăng nhập được điều hướng đúng portal/luồng.
-   [ ] API public không làm lộ dữ liệu cá nhân, thanh toán hoặc dữ liệu
    nội bộ.

### 10.3. Player Portal và vòng đời phòng

-   [ ] Người chơi đăng ký, đăng nhập, đăng xuất; tài khoản mới có role
    `PLAYER`.
-   [ ] Người chơi quản lý hồ sơ và xem danh sách/chi tiết game.
-   [ ] Người chơi tạo phòng với giờ bắt đầu đúng mốc `HH:00` hoặc
    `HH:30`.
-   [ ] Backend chặn tài khoản tạo phòng thứ tư khi đã có 3 phòng đang
    hoạt động do tài khoản đó tạo, kể cả request đồng thời.
-   [ ] Người chơi tìm và tham gia phòng; backend kiểm tra điều kiện
    game và số lượng.
-   [ ] Phòng chưa đủ điều kiện hết hạn sau 4 giờ; phòng đã đủ điều kiện
    chuyển chờ DM và không bị timeout 4 giờ nữa.
-   [ ] Người chơi rời phòng trước khi đặt cọc; sau đặt cọc phải gửi yêu
    cầu Admin.
-   [ ] Khi một thành viên rời/bị hủy lượt, backend đánh giá lại điều
    kiện phòng.
-   [ ] Rejoin sau `LEFT`/`CANCELLED_UNPAID` tạo record RoomParticipant
    mới; record cũ vẫn truy vấn/audit được.
-   [ ] Rejoin không tạo duplicate active participation cho cùng
    user/GameRoom.
-   [ ] Rejoin được xử lý độc lập với refund pending của lượt cũ.

### 10.4. DM, Admin và lịch tổ chức

-   [ ] Admin tạo/cấp tài khoản DM và quản lý role; người dùng không thể
    tự nâng quyền.
-   [ ] Admin phân công DM; DM chấp nhận/từ chối và nêu lý do.
-   [ ] DM từ chối không làm phòng tự hủy; Admin có thể phân công DM
    khác.
-   [ ] Phòng tiếp tục chờ nếu chưa có DM nhận; không có timeout tự động
    chưa được xác nhận.
-   [ ] Backend kiểm tra điều kiện chuẩn bị DM tối thiểu 12 giờ theo mốc
    đã thống nhất.
-   [ ] Admin chọn phòng vật lý; backend kiểm tra sức chứa bao gồm người
    chơi và DM, trạng thái phòng và xung đột lịch.
-   [ ] Đổi lịch phải lưu lịch cũ, lịch đề xuất, xác nhận người chơi và
    lịch chính thức.
-   [ ] Thay đổi DM/lịch sau READY đưa GameRoom về
    `WAITING_FOR_ORGANIZATION`.

### 10.5. GameRoom/RoomParticipant state machine

-   [ ] Chỉ `FORMING` tự hết hạn sau 4 giờ ban đầu.
-   [ ] Transition GameRoom tuân theo matrix và guards tại Section 5.4.
-   [ ] Terminal GameRoom không thể rejoin/reopen/transition.
-   [ ] Admin cancel được từ mọi nonterminal state và yêu cầu reason.
-   [ ] Hết replacement window chuyển `WAITING_FOR_ADMIN_DECISION`,
    không tự động cancel.
-   [ ] Replacement window min 30 phút, max 4 giờ và không vượt session
    start.
-   [ ] Admin reopen nhiều lần được; mỗi lần có reason, window mới,
    `reopen_count` và audit.
-   [ ] `READY_FOR_SESSION` chỉ đạt khi tất cả participant active đã
    deposit và điều kiện game/tổ chức hợp lệ.
-   [ ] `LEAVE_REQUESTED` vẫn được tính active cho đến khi Admin quyết
    định.
-   [ ] Participant terminal không được tái kích hoạt; rejoin tạo record
    mới.
-   [ ] Mỗi participant có deposit deadline riêng; participant mới không
    reset deadline participant cũ.
-   [ ] Admin approve/reject leave bắt buộc reason và lưu audit.

### 10.6. Thanh toán, thông báo và audit

-   [ ] DMAssignment: DM được giao có thể accept/reject; reject phải có reason; Admin có thể thu hồi assignment đã ACCEPTED trước khi Session bắt đầu và phải lưu reason/audit.
-   [ ] ScheduleProposal: mỗi GameRoom chỉ có tối đa một proposal đang PROPOSED; chỉ CONFIRMED khi 100% participant active đồng ý trong 2 giờ và các điều kiện DM/venue/lịch hợp lệ.
-   [ ] ScheduleProposal: có ít nhất một participant từ chối hoặc hết hạn mà chưa đủ 100% đồng ý thì proposal thành REJECTED và lịch chính thức cũ không bị ghi đè.
-   [ ] Session: DM được phân công hoặc Admin có thể bắt đầu Session từ READY_FOR_SESSION; chỉ Admin được hủy khẩn cấp Session IN_PROGRESS và phải nhập reason.
-   [ ] PlayerPayment: tiền tệ VNĐ; deposit 30% làm tròn lên bội số 1.000đ; remaining amount = total amount - deposit amount.
-   [ ] PlayerPayment: retry không giới hạn trong deadline 2 giờ; payment/deadline race tuân theo thời điểm server nhận request và trạng thái transaction mới nhất.
-   [ ] Refund: Admin xử lý thủ công; lưu reference/notes, actor, timestamp và amount; retry FAILED không giới hạn; không refund vượt số tiền thực nhận.
-   [ ] Refund: Admin quyết định hoàn 100% hoặc 0% khi duyệt yêu cầu Player rời sau cọc; reason bắt buộc.

-   [ ] Thanh toán mô phỏng mở sau khi DM chấp nhận và đủ điều kiện tổ
    chức.
-   [ ] Tiền cọc 30% có deadline theo policy participant; nhắc ở mốc còn
    1 giờ và 30 phút.
-   [ ] Người chưa cọc khi hết deadline bị hủy lượt; người đã cọc không
    nhận nhắc cọc tiếp.
-   [ ] Khoản 70% có thể ghi nhận thanh toán trước hoặc tại quán;
    Admin/DM được phân công xác nhận khoản thu tại quán.
-   [ ] Hủy do phía Admin/DM/quán không thể tổ chức dẫn đến hoàn 100% số
    tiền đã thanh toán.
-   [ ] Yêu cầu rời sau đặt cọc được Admin quyết định và lưu lý do/người
    xử lý/thời điểm/kết quả.
-   [ ] Notification Center lưu thông báo, trạng thái đọc/chưa đọc và
    polling cập nhật gần thời gian thực.
-   [ ] Lịch sử/audit của các thao tác quan trọng được lưu, không xóa
    vật lý dữ liệu nghiệp vụ cần tra cứu.

### 10.7. Chất lượng kỹ thuật

-   [ ] RBAC và quyền trên tài nguyên được kiểm tra ở backend.
-   [ ] Các giới hạn, kiểm tra lịch và thay đổi trạng thái quan trọng an
    toàn trước request đồng thời.
-   [ ] Có unit test cho business rules quan trọng và integration test
    cho API/repository phù hợp.
-   [ ] Có kiểm thử happy path, validation failure,
    unauthorized/forbidden, timeout và race condition quan trọng.
-   [ ] Frontend production build thành công.
-   [ ] Không đánh dấu PASS nếu chưa thực sự chạy lệnh kiểm thử/build
    tương ứng.

------------------------------------------------------------------------

## 11. Quy tắc cập nhật tài liệu và quản lý phiên bản

`PRODUCT_SPEC` xác định **sản phẩm cần xây dựng, phạm vi, yêu cầu và các
quyết định nghiệp vụ/kỹ thuật đã xác nhận**.

Khi có thay đổi:

1.  Nêu rõ yêu cầu hoặc lý do thay đổi.
2.  Xác định phần bị ảnh hưởng.
3.  Phân biệt quyết định đã xác nhận và đề xuất.
4.  Cập nhật tài liệu trước hoặc đồng thời với code liên quan.
5.  Không âm thầm thay đổi phạm vi MVP.
6.  Nếu thay đổi ảnh hưởng lớn đến dữ liệu, API hoặc kiến trúc, phải
    được nhóm xác nhận trước.
7.  Không ghi đè phiên bản Product Specification cũ.
8.  Mỗi phase chỉ được xem là kết thúc sau khi thiết kế phase đó được
    người dùng xác nhận; khi đó tạo version Product Specification kế
    tiếp.
9.  Version mới kế thừa toàn bộ nội dung version trước, chỉ sửa/bổ sung
    có kiểm soát và cập nhật Version History/Change Log.
10. Không biến đề xuất của AI thành quyết định đã xác nhận nếu người
    dùng chưa chấp thuận.
11. Sau khi xuất version mới, người dùng kiểm tra/xác nhận tài liệu
    trước khi chuyển phase tiếp theo.

### 11.1. Roadmap phase và version

| Phase | Phạm vi | Trạng thái / deliverable |
| :--- | :--- | :--- |
| Phase 1 | Product Specification Baseline | V13 — Completed |
| Phase 2 | Architecture & Domain Design | V13 — Completed |
| Phase 3 | GameRoom & RoomParticipant State Machine | V14 — **Accepted** |
| Phase 4 | Other Domain State Machines & Open Policies | V15 — **Accepted** |
| Phase 5 | Database Design | Next — ERD, relational schema, constraints, indexes, transaction boundaries và Flyway migrations do nhóm tự thiết kế |
| Phase 6 | API Contract & Background Jobs | Pending — API Contract/OpenAPI, authorization matrix, idempotency, scheduler/deadline jobs và notification delivery do nhóm tự thiết kế |
| Phase 7 | Implementation & Testing | Pending — triển khai trong repository chính thức và kiểm thử có bằng chứng |

V15 Final là baseline nghiệp vụ/đặc tả đầu vào cho Phase 5. Việc Phase 3/4 được Accepted chỉ xác nhận thiết kế nghiệp vụ và state machine; không có nghĩa ERD, API Contract, source code hoặc kiểm thử đã hoàn tất.

## 12. Phase 3 --- Biên bản nghiệm thu thiết kế

### 12.1. Phạm vi đã nghiệm thu

-   GameRoom state machine.
-   RoomParticipant state machine.
-   Transition matrix, guards, invariants và side effects.
-   Initial formation timeout, per-participant deposit deadline,
    replacement window.
-   Admin cancel/reopen authority và reason requirements.
-   Rejoin policies và audit.
-   READY conditions và cách quay lại xác nhận tổ chức sau khi
    DM/schedule thay đổi.
-   Các edge cases/concurrency cases cần bảo vệ trong implementation.

### 12.2. Các quyết định OPEN đã được đóng

  -----------------------------------------------------------------------
  Open ID                             Quyết định đã xác nhận
  ----------------------------------- -----------------------------------
  OPEN-01                             User ở trạng thái `LEFT` được
                                      rejoin cùng GameRoom nếu room cho
                                      phép; tạo RoomParticipant record
                                      mới; record cũ giữ lại để audit.

  OPEN-02                             User ở trạng thái
                                      `CANCELLED_UNPAID` được rejoin; tạo
                                      record mới và deadline đặt cọc mới.

  OPEN-03                             Cho phép rejoin khi refund của lượt
                                      cũ đang pending; payment/refund cũ
                                      độc lập với participation mới.

  OPEN-04                             Không giới hạn số lần rejoin trong
                                      MVP; audit tất cả lượt.

  OPEN-05                             Admin được reopen recruitment không
                                      giới hạn trong MVP; lưu
                                      `reopen_count` và audit.

  OPEN-06                             Admin bắt buộc nhập reason khi
                                      reopen, cancel GameRoom,
                                      approve/reject leave.

  OPEN-07                             Replacement window min 30 phút, max
                                      4 giờ, không vượt session start.

  OPEN-08                             DM/schedule thay đổi sau READY đưa
                                      GameRoom về
                                      `WAITING_FOR_ORGANIZATION`.
  -----------------------------------------------------------------------

### 12.3. Các edge cases phải xử lý

-   Hai người đồng thời join slot cuối: transaction/locking/constraint
    phải bảo đảm không vượt capacity.
-   Hai event cùng làm room đủ điều kiện: chỉ một transition hợp lệ được
    commit.
-   Payment confirmation cạnh tranh với deposit deadline: kiểm tra
    timestamp và trạng thái mới nhất trong transaction.
-   Duplicate payment callback: idempotent, không ghi nhận tiền hai lần.
-   Callback đến sau khi participant đã bị cancel: không tự re-activate;
    đưa vào reconciliation/refund flow.
-   Admin reopen nhiều lần: mỗi lần tạo replacement window mới; không
    ghi đè window trước.
-   Reopen có deadline vượt session start: reject.
-   Admin cancel cạnh tranh với join/payment/DM accept: mọi command phải
    validate state mới nhất; không cho side effect mới sau khi room đã
    cancel.
-   Admin cancel phải release PhysicalRoom/DM reservation và xử lý
    participant/payment liên quan.
-   Participant deposited xin leave: Admin review; Refund là lifecycle
    riêng.
-   Replacement participant không deposit: chỉ cancel participant đó và
    đánh giá lại room.
-   Participant list rỗng: không được để `allMatch()` trả true một cách
    sai nghiệp vụ; cần non-empty và minimum game conditions.
-   Participant rời khi room READY: đánh giá lại điều kiện, không giữ
    READY vô điều kiện.
-   Rejoin đồng thời: bảo đảm chỉ có tối đa một active
    participation/user/room.
-   Refund cũ và participation mới phải độc lập; không tự động chuyển
    tiền cọc cũ sang lượt mới.

### 12.4. Ranh giới nghiệm thu

**Đã nghiệm thu:** thiết kế nghiệp vụ và state machine
GameRoom/RoomParticipant theo các quyết định nêu trong V14.

**Chưa nghiệm thu:** ERD, database constraints/migrations, API contract,
scheduler/background jobs implementation, code, unit/integration/e2e
tests, performance và security verification.

Không được diễn giải Phase 3 accepted là implementation completed hoặc
test passed.

------------------------------------------------------------------------

## 13. Phase 4 --- Biên bản nghiệm thu thiết kế (Accepted)

### 13.1. Phạm vi đã nghiệm thu

Phase 4 đã hoàn thiện và nghiệm thu state machine tối giản cho 5 domain:
- `DMAssignment` — 4 states.
- `ScheduleProposal` — 4 states.
- `Session` — 4 states.
- `PlayerPayment` — 6 states.
- `Refund` — 5 states.

Transition matrix, guards, actor, invariants, side effects liên domain và edge cases được quy định tại Section 5.12.

### 13.2. Biên bản đóng 16 quyết định OPEN của Phase 4

| Nhóm quyết định | Quyết định đã Accepted |
| :--- | :--- |
| `OPEN-DM-01..02` | Không timeout tự động cho assignment `PENDING`; Admin được thu hồi assignment `ACCEPTED` trước khi Session bắt đầu (`IN_PROGRESS`), bắt buộc reason và đưa GameRoom về `WAITING_FOR_ORGANIZATION`. |
| `OPEN-SCH-01..05` | Tối đa 1 proposal `PROPOSED` trên mỗi GameRoom; 100% participant active phải đồng ý trong 2 giờ (không vượt giờ chơi cũ); bất kỳ từ chối hoặc hết hạn đều chuyển `REJECTED`, giữ lịch cũ. |
| `OPEN-SES-01..02` | DM được phân công hoặc Admin có thể xác nhận bắt đầu Session từ `READY_FOR_SESSION`; chỉ Admin được hủy khẩn cấp Session `IN_PROGRESS`, bắt buộc reason. |
| `OPEN-PAY-01..04` | Đơn vị tiền VNĐ (`BIGINT`); giá vé cố định/người; cọc 30% làm tròn lên hàng nghìn (`1.000đ`); remaining = total - deposit; retry không giới hạn trong hạn 2 giờ; timestamp server nhận request là chuẩn xử lý tranh chấp deadline. |
| `OPEN-REF-01..03` | Refund mô phỏng do Admin xác nhận thủ công bằng mã tham chiếu/ghi chú; retry thủ công không giới hạn khi `FAILED`; Admin quyết định hoàn 100% hoặc 0% khi Player xin rời sau cọc và phải nhập reason. |

### 13.3. Ranh giới nghiệm thu

**Đã Accepted:** thiết kế nghiệp vụ, state machine, transition matrix, guards, actor, invariants, deadline/payment/refund policies và side effects của Phase 4.

**Chưa thuộc phạm vi nghiệm thu Phase 4:** ERD chi tiết, database schema/constraints/indexes, Flyway migration, API Contract/OpenAPI, scheduler/background job implementation, source code, unit/integration/E2E tests, performance verification và security verification.

Không được diễn giải Phase 4 Accepted là implementation completed hoặc test passed.

## 14. Roadmap phát triển tiếp theo

Thứ tự triển khai sau V15 Final:

1. **Phase 5 — Database Design:** phân tích domain đã Accepted, tự thiết kế ERD, relational schema, keys, relationships, constraints, indexes, transaction boundaries và Flyway migrations. Không kế thừa schema/ERD từ repository tham khảo.
2. **Phase 6 — API Contract & Background Jobs:** tự thiết kế REST API Contract/OpenAPI, DTO, validation, error response, authorization matrix, idempotency và scheduler/deadline jobs dựa trên Product Specification và Database Design đã duyệt.
3. **Phase 7 — Implementation & Testing:** Backend và Frontend triển khai song song trong `nguyenxuanhieu2903/kich-ban-sat`; Frontend được nhóm xây dựng lại; tích hợp theo API Contract; kiểm thử unit/integration/E2E và lưu bằng chứng.
4. **Release readiness:** kiểm tra build, migration trên database mới, cấu hình môi trường, bảo mật, hướng dẫn cài đặt/chạy local và nghiệm thu end-to-end.

Không nhảy sang triển khai tính năng phụ thuộc khi thiết kế dữ liệu/API liên quan chưa được thống nhất. Các phase chỉ được đánh dấu hoàn thành sau khi có deliverable và được nhóm xác nhận.
