<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Application;
use App\Core\Request;
use App\Features\GameroomManagement\Services\BackgroundJobService;
use App\Features\PaymentRefund\Repositories\PaymentRepository;
use App\Features\Pricing\Services\PricingService;
use App\Infrastructure\Config\Config;
use App\Infrastructure\Database\DatabaseConnection;
use PDO;
use PHPUnit\Framework\TestCase;

class PaymentApiTest extends TestCase
{
    protected Application $app;
    protected PaymentRepository $paymentRepo;
    protected PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();
        $basePath = dirname(__DIR__, 2);
        Config::load($basePath);
        $this->app = new Application($basePath);
        $_SESSION = [];

        $this->pdo = DatabaseConnection::getInstance();
        $this->paymentRepo = new PaymentRepository($this->pdo);

        // Dọn dẹp phòng test cũ của các user test
        $this->pdo->exec("UPDATE `game_rooms` SET `status` = 'CANCELLED' WHERE `title` LIKE 'Phòng test%' AND `status` = 'FORMING'");

        $routesFile = $basePath . '/routes/api.php';
        $router = $this->app->getRouter();
        require $routesFile;
    }

    /**
     * P2 - DEC-01 (Option B): Không tạo hóa đơn khi Player vừa Join GameRoom
     */
    public function testNoInvoiceCreatedOnJoin(): void
    {
        // 1. User 3 tạo phòng
        $createReq = new Request('POST', '/api/v1/rooms', [], [
            'game_id' => 1,
            'title' => 'Phòng test Option B No Invoice On Join',
        ], ['Authorization' => 'Bearer 3']);
        $createRes = $this->app->dispatch($createReq);
        $this->assertSame(201, $createRes->getStatusCode());
        $roomId = (int) json_decode($createRes->getContent(), true)['data']['room']['id'];

        // 2. User 4 join phòng
        $join4 = new Request('POST', "/api/v1/rooms/{$roomId}/join", [], [], ['Authorization' => 'Bearer 4']);
        $res4 = $this->app->dispatch($join4);
        $this->assertSame(200, $res4->getStatusCode());

        // 3. Kiểm tra DB: Hoàn toàn KHÔNG có hóa đơn nào được tạo trong player_payments
        $payCount = (int) $this->pdo->query(
            "SELECT COUNT(*) FROM `player_payments` pp
             INNER JOIN `room_participants` rp ON rp.`id` = pp.`room_participant_id`
             WHERE rp.`game_room_id` = {$roomId}"
        )->fetchColumn();
        $this->assertSame(0, $payCount, 'Không được tạo bất kỳ hóa đơn cọc nào khi người chơi vừa Join');

        // 4. Kiểm tra deposit_deadline_at của các participant vẫn là NULL
        $nullDeadlines = (int) $this->pdo->query(
            "SELECT COUNT(*) FROM `room_participants` WHERE `game_room_id` = {$roomId} AND `deposit_deadline_at` IS NULL"
        )->fetchColumn();
        $this->assertSame(2, $nullDeadlines);
    }

    /**
     * P2 - DEC-01 (Option B): Chỉ tạo hóa đơn cọc khi phòng chuyển sang giai đoạn biểu quyết lịch
     */
    public function testInvoiceCreatedOnScheduleProposalOptionB(): void
    {
        // 1. User 3 tạo phòng và User 4 tham gia
        $createReq = new Request('POST', '/api/v1/rooms', [], [
            'game_id' => 1,
            'title' => 'Phòng test Option B Invoice On Propose',
        ], ['Authorization' => 'Bearer 3']);
        $createRes = $this->app->dispatch($createReq);
        $roomId = (int) json_decode($createRes->getContent(), true)['data']['room']['id'];

        $join4 = new Request('POST', "/api/v1/rooms/{$roomId}/join", [], [], ['Authorization' => 'Bearer 4']);
        $this->app->dispatch($join4);

        // 2. User 3 tạo đề xuất lịch -> Chuyển sang giai đoạn biểu quyết lịch
        $start = date('Y-m-d H:i:s', strtotime('+2 days 18:00:00'));
        $end = date('Y-m-d H:i:s', strtotime('+2 days 22:00:00'));
        $propReq = new Request('POST', "/api/v1/rooms/{$roomId}/proposals", [], [
            'proposed_start_at' => $start,
            'proposed_end_at' => $end,
        ], ['Authorization' => 'Bearer 3']);
        $propRes = $this->app->dispatch($propReq);
        $this->assertSame(201, $propRes->getStatusCode());

        // 3. Kiểm tra DB: Mỗi participant active (User 3 và User 4) phải có đúng 1 hóa đơn DEPOSIT 50.000 VNĐ
        $payments = $this->pdo->query(
            "SELECT pp.`id`, pp.`room_participant_id`, pp.`payment_type`, pp.`amount`, pp.`paid_amount`, pp.`status`, pp.`deadline_at`, rp.`user_id`
             FROM `player_payments` pp
             INNER JOIN `room_participants` rp ON rp.`id` = pp.`room_participant_id`
             WHERE rp.`game_room_id` = {$roomId}
             ORDER BY rp.`user_id` ASC"
        )->fetchAll(PDO::FETCH_ASSOC);

        $this->assertCount(2, $payments, 'Phải tạo đúng 2 hóa đơn cọc cho 2 thành viên trong phòng');

        foreach ($payments as $p) {
            $this->assertSame('DEPOSIT', $p['payment_type']);
            $this->assertSame(50000, (int) $p['amount']);
            $this->assertSame(0, (int) $p['paid_amount']);
            $this->assertSame('PENDING', $p['status']);
            $this->assertNotNull($p['deadline_at']);

            // Hạn cọc phải là khoảng 2 giờ từ thời điểm tạo
            $diffHours = (strtotime((string) $p['deadline_at']) - time()) / 3600;
            $this->assertGreaterThan(1.9, $diffHours);
            $this->assertLessThan(2.1, $diffHours);
        }

        // 4. Kiểm tra deposit_deadline_at trong room_participants đã được cập nhật đồng bộ
        $parts = $this->pdo->query("SELECT `deposit_deadline_at` FROM `room_participants` WHERE `game_room_id` = {$roomId}")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($parts as $dl) {
            $this->assertNotNull($dl);
        }
    }

    /**
     * P2: Không tạo duplicate invoice khi đề xuất lịch lại hoặc retry
     */
    public function testNoDuplicateInvoiceOnRetryProposal(): void
    {
        // 1. Tạo phòng và 2 thành viên
        $createReq = new Request('POST', '/api/v1/rooms', [], [
            'game_id' => 1,
            'title' => 'Phòng test No Duplicate Invoice On Retry',
        ], ['Authorization' => 'Bearer 3']);
        $roomId = (int) json_decode($this->app->dispatch($createReq)->getContent(), true)['data']['room']['id'];

        $join4 = new Request('POST', "/api/v1/rooms/{$roomId}/join", [], [], ['Authorization' => 'Bearer 4']);
        $this->app->dispatch($join4);

        // 2. Đề xuất lịch lần 1
        $start1 = date('Y-m-d H:i:s', strtotime('+2 days 18:00:00'));
        $end1 = date('Y-m-d H:i:s', strtotime('+2 days 22:00:00'));
        $prop1 = new Request('POST', "/api/v1/rooms/{$roomId}/proposals", [], [
            'proposed_start_at' => $start1,
            'proposed_end_at' => $end1,
        ], ['Authorization' => 'Bearer 3']);
        $this->assertSame(201, $this->app->dispatch($prop1)->getStatusCode());

        $firstCount = (int) $this->pdo->query(
            "SELECT COUNT(*) FROM `player_payments` pp
             INNER JOIN `room_participants` rp ON rp.`id` = pp.`room_participant_id`
             WHERE rp.`game_room_id` = {$roomId}"
        )->fetchColumn();
        $this->assertSame(2, $firstCount);

        // 3. Đề xuất lịch lần 2 (mốc thời gian khác)
        $start2 = date('Y-m-d H:i:s', strtotime('+3 days 18:00:00'));
        $end2 = date('Y-m-d H:i:s', strtotime('+3 days 22:00:00'));
        $prop2 = new Request('POST', "/api/v1/rooms/{$roomId}/proposals", [], [
            'proposed_start_at' => $start2,
            'proposed_end_at' => $end2,
        ], ['Authorization' => 'Bearer 3']);
        $this->assertSame(201, $this->app->dispatch($prop2)->getStatusCode());

        // 4. Số lượng hóa đơn cọc vẫn phải là 2, tuyệt đối không bị nhân đôi
        $secondCount = (int) $this->pdo->query(
            "SELECT COUNT(*) FROM `player_payments` pp
             INNER JOIN `room_participants` rp ON rp.`id` = pp.`room_participant_id`
             WHERE rp.`game_room_id` = {$roomId}"
        )->fetchColumn();
        $this->assertSame(2, $secondCount, 'Không được tạo duplicate payment khi đề xuất lịch lại');
    }

    /**
     * P3 & P7: Đúng 50.000 VNĐ tiền cọc, Client không thể giả mạo amount
     */
    public function testDepositAmountStrictlyFiftyThousandAndClientAmountIgnored(): void
    {
        // 1. Tạo phòng và mở proposal để sinh hóa đơn
        $createReq = new Request('POST', '/api/v1/rooms', [], [
            'game_id' => 1,
            'title' => 'Phòng test Deposit Amount Integrity',
        ], ['Authorization' => 'Bearer 3']);
        $roomId = (int) json_decode($this->app->dispatch($createReq)->getContent(), true)['data']['room']['id'];

        $propReq = new Request('POST', "/api/v1/rooms/{$roomId}/proposals", [], [
            'proposed_start_at' => date('Y-m-d H:i:s', strtotime('+2 days 18:00:00')),
            'proposed_end_at' => date('Y-m-d H:i:s', strtotime('+2 days 22:00:00')),
        ], ['Authorization' => 'Bearer 3']);
        $this->app->dispatch($propReq);

        // Lấy paymentId của User 3
        $paymentId = (int) $this->pdo->query(
            "SELECT pp.`id` FROM `player_payments` pp
             INNER JOIN `room_participants` rp ON rp.`id` = pp.`room_participant_id`
             WHERE rp.`game_room_id` = {$roomId} AND rp.`user_id` = 3"
        )->fetchColumn();

        // 2. Client cố tình gửi amount = 10,000 VNĐ (giả mạo số tiền cọc)
        $tamperReq = new Request('POST', "/api/v1/payments/{$paymentId}/deposit", [], [
            'amount' => 10000,
        ], ['Authorization' => 'Bearer 3']);
        $tamperRes = $this->app->dispatch($tamperReq);
        $this->assertSame(200, $tamperRes->getStatusCode());
        $tamperData = json_decode($tamperRes->getContent(), true)['data'];

        // Backend phải tự xác định số tiền cọc đúng chuẩn 50.000 VNĐ
        $this->assertSame(50000, (int) $tamperData['transaction']['amount']);
        $this->assertSame(50000, (int) $tamperData['payment']['paid_amount']);
        $this->assertSame('PAID', $tamperData['payment']['status']);

        // Trạng thái participant phải chuyển sang DEPOSITED
        $partStatus = $this->pdo->query("SELECT `status` FROM `room_participants` WHERE `game_room_id` = {$roomId} AND `user_id` = 3")->fetchColumn();
        $this->assertSame('DEPOSITED', $partStatus);
    }

    /**
     * P4 & P7: Chống IDOR/BOLA - Không được thanh toán thay người khác
     */
    public function testIDORPreventionOnPayment(): void
    {
        // 1. Tạo phòng với User 3 và mở proposal
        $createReq = new Request('POST', '/api/v1/rooms', [], [
            'game_id' => 1,
            'title' => 'Phòng test IDOR Guard',
        ], ['Authorization' => 'Bearer 3']);
        $roomId = (int) json_decode($this->app->dispatch($createReq)->getContent(), true)['data']['room']['id'];

        $propReq = new Request('POST', "/api/v1/rooms/{$roomId}/proposals", [], [
            'proposed_start_at' => date('Y-m-d H:i:s', strtotime('+2 days 18:00:00')),
            'proposed_end_at' => date('Y-m-d H:i:s', strtotime('+2 days 22:00:00')),
        ], ['Authorization' => 'Bearer 3']);
        $this->app->dispatch($propReq);

        $payIdUser3 = (int) $this->pdo->query(
            "SELECT pp.`id` FROM `player_payments` pp
             INNER JOIN `room_participants` rp ON rp.`id` = pp.`room_participant_id`
             WHERE rp.`game_room_id` = {$roomId} AND rp.`user_id` = 3"
        )->fetchColumn();

        // 2. User 4 (không phải chủ sở hữu) cố tình thanh toán hóa đơn của User 3 -> 403 Forbidden
        $idorReq = new Request('POST', "/api/v1/payments/{$payIdUser3}/deposit", [], [], ['Authorization' => 'Bearer 4']);
        $idorRes = $this->app->dispatch($idorReq);
        $this->assertSame(403, $idorRes->getStatusCode());
        $idorData = json_decode($idorRes->getContent(), true);
        $this->assertSame('FORBIDDEN', $idorData['error']['code']);
    }

    /**
     * P4: Không thể thanh toán hai lần (Double Payment Prevention)
     */
    public function testCannotPayTwiceWhenAlreadyPaid(): void
    {
        // 1. Tạo phòng và thanh toán cọc thành công
        $createReq = new Request('POST', '/api/v1/rooms', [], [
            'game_id' => 1,
            'title' => 'Phòng test Double Payment Guard',
        ], ['Authorization' => 'Bearer 3']);
        $roomId = (int) json_decode($this->app->dispatch($createReq)->getContent(), true)['data']['room']['id'];

        $propReq = new Request('POST', "/api/v1/rooms/{$roomId}/proposals", [], [
            'proposed_start_at' => date('Y-m-d H:i:s', strtotime('+2 days 18:00:00')),
            'proposed_end_at' => date('Y-m-d H:i:s', strtotime('+2 days 22:00:00')),
        ], ['Authorization' => 'Bearer 3']);
        $this->app->dispatch($propReq);

        $paymentId = (int) $this->pdo->query(
            "SELECT pp.`id` FROM `player_payments` pp
             INNER JOIN `room_participants` rp ON rp.`id` = pp.`room_participant_id`
             WHERE rp.`game_room_id` = {$roomId} AND rp.`user_id` = 3"
        )->fetchColumn();

        // Lần 1: Thành công
        $pay1 = new Request('POST', "/api/v1/payments/{$paymentId}/deposit", [], [], ['Authorization' => 'Bearer 3']);
        $res1 = $this->app->dispatch($pay1);
        $this->assertSame(200, $res1->getStatusCode());

        // Lần 2 (với idempotency key mới): Phải bị từ chối 400 PAYMENT_ALREADY_COMPLETED
        $pay2 = new Request('POST', "/api/v1/payments/{$paymentId}/deposit", [], [
            'idempotency_key' => 'NEW_KEY_' . uniqid(),
        ], ['Authorization' => 'Bearer 3']);
        $res2 = $this->app->dispatch($pay2);
        $this->assertSame(400, $res2->getStatusCode());
        $data2 = json_decode($res2->getContent(), true);
        $this->assertSame('PAYMENT_ALREADY_COMPLETED', $data2['error']['code']);
    }

    /**
     * P4: Idempotency Key bảo vệ gửi lại request mà không trừ tiền hai lần
     */
    public function testIdempotencyKeyReturnsSameTransaction(): void
    {
        $createReq = new Request('POST', '/api/v1/rooms', [], [
            'game_id' => 1,
            'title' => 'Phòng test Idempotency Repeat',
        ], ['Authorization' => 'Bearer 3']);
        $roomId = (int) json_decode($this->app->dispatch($createReq)->getContent(), true)['data']['room']['id'];

        $propReq = new Request('POST', "/api/v1/rooms/{$roomId}/proposals", [], [
            'proposed_start_at' => date('Y-m-d H:i:s', strtotime('+2 days 18:00:00')),
            'proposed_end_at' => date('Y-m-d H:i:s', strtotime('+2 days 22:00:00')),
        ], ['Authorization' => 'Bearer 3']);
        $this->app->dispatch($propReq);

        $paymentId = (int) $this->pdo->query(
            "SELECT pp.`id` FROM `player_payments` pp
             INNER JOIN `room_participants` rp ON rp.`id` = pp.`room_participant_id`
             WHERE rp.`game_room_id` = {$roomId} AND rp.`user_id` = 3"
        )->fetchColumn();

        $idempKey = 'FIXED_IDEMP_' . uniqid();

        // Gửi lần 1
        $req1 = new Request('POST', "/api/v1/payments/{$paymentId}/deposit", [], [
            'idempotency_key' => $idempKey,
        ], ['Authorization' => 'Bearer 3']);
        $res1 = $this->app->dispatch($req1);
        $this->assertSame(200, $res1->getStatusCode());
        $data1 = json_decode($res1->getContent(), true)['data'];

        // Gửi lần 2 với cùng key -> Trả về cùng kết quả thành công, không tạo thêm transaction
        $res2 = $this->app->dispatch($req1);
        $this->assertSame(200, $res2->getStatusCode());
        $data2 = json_decode($res2->getContent(), true)['data'];

        $this->assertSame($data1['transaction']['id'], $data2['transaction']['id']);
        $this->assertSame($data1['transaction']['transaction_reference'], $data2['transaction']['transaction_reference']);
    }

    /**
     * P5: Cron xử lý participant quá hạn cọc và hết hạn hóa đơn
     */
    public function testCronDepositTimeoutKicksUnpaidAndExpiresPayment(): void
    {
        // 1. Tạo phòng và 1 participant quá hạn cọc (deposit_deadline_at trong quá khứ)
        $this->pdo->exec("INSERT INTO `game_rooms` (`game_id`, `game_configuration_id`, `creator_id`, `title`, `min_players`, `max_players`, `status`, `formation_deadline_at`, `created_at`, `updated_at`)
                         VALUES (1, 1, 3, 'Phòng test Cron Deposit Timeout', 6, 8, 'FORMING', DATE_ADD(NOW(), INTERVAL 4 HOUR), NOW(), NOW())");
        $roomId = (int) $this->pdo->lastInsertId();

        $pastDeadline = date('Y-m-d H:i:s', time() - 3600); // 1 giờ trước
        $this->pdo->exec("INSERT INTO `room_participants` (`game_room_id`, `user_id`, `status`, `deposit_deadline_at`, `joined_at`, `created_at`, `updated_at`)
                         VALUES ({$roomId}, 4, 'PENDING_DEPOSIT', '{$pastDeadline}', NOW(), NOW(), NOW())");
        $partId = (int) $this->pdo->lastInsertId();

        // Tạo hóa đơn cọc PENDING quá hạn
        $this->pdo->exec("INSERT INTO `player_payments` (`room_participant_id`, `payment_type`, `amount`, `paid_amount`, `status`, `deadline_at`, `created_at`, `updated_at`)
                         VALUES ({$partId}, 'DEPOSIT', 50000, 0, 'PENDING', '{$pastDeadline}', NOW(), NOW())");
        $payId = (int) $this->pdo->lastInsertId();

        // 2. Chạy Background Job runDepositTimeout
        $jobService = new BackgroundJobService($this->pdo);
        $kicked = $jobService->runDepositTimeout();
        $this->assertGreaterThanOrEqual(1, $kicked);

        // 3. Kiểm tra DB:
        // - room_participants phải thành 'CANCELLED_UNPAID'
        $partStatus = $this->pdo->query("SELECT `status` FROM `room_participants` WHERE `id` = {$partId}")->fetchColumn();
        $this->assertSame('CANCELLED_UNPAID', $partStatus);

        // - player_payments phải thành 'EXPIRED'
        $payStatus = $this->pdo->query("SELECT `status` FROM `player_payments` WHERE `id` = {$payId}")->fetchColumn();
        $this->assertSame('EXPIRED', $payStatus);

        // - game_rooms FORMING không được mở replacement_status = 'OPEN' (DEF-21 fix)
        $rwStatus = $this->pdo->query("SELECT `replacement_status` FROM `game_rooms` WHERE `id` = {$roomId}")->fetchColumn();
        $this->assertNull($rwStatus);

        // 4. Chạy lại job lần 2: Không xử lý lại (idempotent, kicked = 0 cho bản ghi này)
        $kickedAgain = $jobService->runDepositTimeout();
        $this->assertSame(0, $kickedAgain);
    }

    /**
     * P5: Người chơi đã cọc (DEPOSITED) không bị kick khi Cron chạy
     */
    public function testCronDoesNotKickDepositedParticipant(): void
    {
        $this->pdo->exec("INSERT INTO `game_rooms` (`game_id`, `game_configuration_id`, `creator_id`, `title`, `min_players`, `max_players`, `status`, `formation_deadline_at`, `created_at`, `updated_at`)
                         VALUES (1, 1, 3, 'Phòng test Cron Deposited Immunity', 6, 8, 'FORMING', DATE_ADD(NOW(), INTERVAL 4 HOUR), NOW(), NOW())");
        $roomId = (int) $this->pdo->lastInsertId();

        // Participant đã nộp cọc DEPOSITED dù deadline_at trong quá khứ
        $pastDeadline = date('Y-m-d H:i:s', time() - 3600);
        $this->pdo->exec("INSERT INTO `room_participants` (`game_room_id`, `user_id`, `status`, `deposit_deadline_at`, `deposited_at`, `joined_at`, `created_at`, `updated_at`)
                         VALUES ({$roomId}, 4, 'DEPOSITED', '{$pastDeadline}', NOW(), NOW(), NOW(), NOW())");
        $partId = (int) $this->pdo->lastInsertId();

        $jobService = new BackgroundJobService($this->pdo);
        $kicked = $jobService->runDepositTimeout();

        $status = $this->pdo->query("SELECT `status` FROM `room_participants` WHERE `id` = {$partId}")->fetchColumn();
        $this->assertSame('DEPOSITED', $status, 'Thành viên đã nộp cọc tuyệt đối không bị kick');
    }

    /**
     * P6: Thanh toán số tiền còn lại (Remaining Payment)
     */
    public function testRemainingPaymentOperation(): void
    {
        // 1. Tạo phòng, đề xuất lịch và cọc thành công
        $createReq = new Request('POST', '/api/v1/rooms', [], [
            'game_id' => 1,
            'title' => 'Phòng test Remaining Payment Flow',
        ], ['Authorization' => 'Bearer 3']);
        $roomId = (int) json_decode($this->app->dispatch($createReq)->getContent(), true)['data']['room']['id'];

        $propReq = new Request('POST', "/api/v1/rooms/{$roomId}/proposals", [], [
            'proposed_start_at' => date('Y-m-d H:i:s', strtotime('+2 days 18:00:00')),
            'proposed_end_at' => date('Y-m-d H:i:s', strtotime('+2 days 22:00:00')),
        ], ['Authorization' => 'Bearer 3']);
        $this->app->dispatch($propReq);

        $depPayId = (int) $this->pdo->query(
            "SELECT pp.`id` FROM `player_payments` pp
             INNER JOIN `room_participants` rp ON rp.`id` = pp.`room_participant_id`
             WHERE rp.`game_room_id` = {$roomId} AND rp.`user_id` = 3"
        )->fetchColumn();

        // Thanh toán cọc 50k
        $depRes = $this->app->dispatch(new Request('POST', "/api/v1/payments/{$depPayId}/deposit", [], [], ['Authorization' => 'Bearer 3']));
        $this->assertSame(200, $depRes->getStatusCode());

        // 2. Chốt snapshot bảng giá cho phòng (ví dụ game 250k -> còn lại 200k)
        $pricingService = new PricingService($this->pdo);
        $snapshot = $pricingService->calculateAndSnapshot($roomId, date('Y-m-d 18:00:00'));
        $expectedRemaining = $snapshot['per_player_summary']['remaining_due'];
        $this->assertGreaterThan(0, $expectedRemaining);

        // 3. User 3 gọi API thanh toán tiền còn lại
        $remReq = new Request('POST', "/api/v1/payments/{$depPayId}/remaining", [], [], ['Authorization' => 'Bearer 3']);
        $remRes = $this->app->dispatch($remReq);
        $this->assertSame(200, $remRes->getStatusCode());
        $remData = json_decode($remRes->getContent(), true)['data'];

        $this->assertTrue($remData['success']);
        $this->assertSame('PAID', $remData['payment']['status']);
        $this->assertSame($expectedRemaining, (int) $remData['transaction']['amount']);

        // 4. Thử thanh toán tiếp -> Báo lỗi PAYMENT_ALREADY_COMPLETED
        $repeatRem = $this->app->dispatch($remReq);
        $this->assertSame(400, $repeatRem->getStatusCode());
        $repData = json_decode($repeatRem->getContent(), true);
        $this->assertSame('PAYMENT_ALREADY_COMPLETED', $repData['error']['code']);
    }

    /**
     * DEC-02: Thay thế slot không tự động duyệt hoàn tiền (vẫn yêu cầu Admin duyệt thủ công)
     */
    public function testReplacementTakeoverRequiresManualApprovalForRefund(): void
    {
        // 1. Tạo phòng và 2 thành viên
        $createReq = new Request('POST', '/api/v1/rooms', [], [
            'game_id' => 1,
            'title' => 'Phòng test DEC-02 Manual Refund',
        ], ['Authorization' => 'Bearer 3']);
        $roomId = (int) json_decode($this->app->dispatch($createReq)->getContent(), true)['data']['room']['id'];

        $join4 = new Request('POST', "/api/v1/rooms/{$roomId}/join", [], [], ['Authorization' => 'Bearer 4']);
        $this->app->dispatch($join4);

        // 2. Mở proposal và thanh toán cọc cho User 4
        $propReq = new Request('POST', "/api/v1/rooms/{$roomId}/proposals", [], [
            'proposed_start_at' => date('Y-m-d H:i:s', strtotime('+3 days 18:00:00')),
            'proposed_end_at' => date('Y-m-d H:i:s', strtotime('+3 days 22:00:00')),
        ], ['Authorization' => 'Bearer 3']);
        $this->app->dispatch($propReq);

        $payIdUser4 = (int) $this->pdo->query(
            "SELECT pp.`id` FROM `player_payments` pp
             INNER JOIN `room_participants` rp ON rp.`id` = pp.`room_participant_id`
             WHERE rp.`game_room_id` = {$roomId} AND rp.`user_id` = 4"
        )->fetchColumn();
        $this->app->dispatch(new Request('POST', "/api/v1/payments/{$payIdUser4}/deposit", [], [], ['Authorization' => 'Bearer 4']));

        // Chuyển phòng sang CONFIRMED và User 4 xin rút phòng
        $this->pdo->exec("UPDATE `game_rooms` SET `status` = 'CONFIRMED' WHERE `id` = {$roomId}");
        $leave4 = new Request('POST', "/api/v1/rooms/{$roomId}/leave", [], [], ['Authorization' => 'Bearer 4']);
        $this->app->dispatch($leave4);

        // 3. User 1 (Admin/người chơi khác) nhận thay thế slot
        $takeover = new Request('POST', "/api/v1/rooms/{$roomId}/join", [], [], ['Authorization' => 'Bearer 1']);
        $resTakeover = $this->app->dispatch($takeover);
        $this->assertSame(200, $resTakeover->getStatusCode());

        // 4. Kiểm tra: Tiền cọc của User 4 KHÔNG được tự động chuyển sang REFUNDED
        // và KHÔNG có bản ghi refund nào được tự động đánh dấu SUCCESS
        $u4PaymentStatus = $this->pdo->query("SELECT `status` FROM `player_payments` WHERE `id` = {$payIdUser4}")->fetchColumn();
        $this->assertSame('PAID', $u4PaymentStatus, 'Hóa đơn cọc của người rút vẫn giữ nguyên PAID cho tới khi Admin duyệt');

        $autoApprovedRefunds = (int) $this->pdo->query("SELECT COUNT(*) FROM `refunds` WHERE `player_payment_id` = {$payIdUser4} AND `status` = 'APPROVED'")->fetchColumn();
        $this->assertSame(0, $autoApprovedRefunds, 'DEC-02: Tuyệt đối không tự động duyệt hoàn tiền khi có người thế chỗ');
    }
}

