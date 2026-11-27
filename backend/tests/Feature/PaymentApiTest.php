<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Application;
use App\Core\Request;
use App\Features\PaymentRefund\Repositories\PaymentRepository;
use App\Infrastructure\Config\Config;
use App\Infrastructure\Database\DatabaseConnection;
use PHPUnit\Framework\TestCase;

class PaymentApiTest extends TestCase
{
    protected Application $app;
    protected PaymentRepository $paymentRepo;

    protected function setUp(): void
    {
        parent::setUp();
        $basePath = dirname(__DIR__, 2);
        Config::load($basePath);
        $this->app = new Application($basePath);
        $_SESSION = [];

        $pdo = DatabaseConnection::getInstance();
        $this->paymentRepo = new PaymentRepository($pdo);

        $routesFile = $basePath . '/routes/api.php';
        $router = $this->app->getRouter();
        require $routesFile;
    }

    public function testCanProcessDepositAndRemainingPaymentWithIdempotencyAndIDORGuard(): void
    {
        $pdo = DatabaseConnection::getInstance();

        // 1. Tìm hoặc tạo room participant cho User 3 trong Room 1
        $pStmt = $pdo->prepare('SELECT `id` FROM `room_participants` WHERE `game_room_id` = 1 AND `user_id` = 3 LIMIT 1');
        $pStmt->execute();
        $participantId = $pStmt->fetchColumn();

        if ($participantId === false) {
            $insPart = $pdo->prepare('INSERT INTO `room_participants` (`game_room_id`, `user_id`, `status`, `joined_at`, `created_at`, `updated_at`) VALUES (1, 3, \'PENDING_DEPOSIT\', NOW(), NOW(), NOW())');
            $insPart->execute();
            $participantId = (int) $pdo->lastInsertId();
        } else {
            $participantId = (int) $participantId;
            $pdo->exec("UPDATE `room_participants` SET `status` = 'PENDING_DEPOSIT' WHERE `id` = {$participantId}");
        }

        // Dọn sạch payment cũ của participant này nếu có để đảm bảo test độc lập
        $pdo->exec("DELETE FROM `payment_transactions` WHERE `player_payment_id` IN (SELECT `id` FROM `player_payments` WHERE `room_participant_id` = {$participantId})");
        $pdo->exec("DELETE FROM `player_payments` WHERE `room_participant_id` = {$participantId}");

        // 2. Tạo payment record trị giá 300,000 VNĐ
        $paymentId = $this->paymentRepo->createPayment([
            'room_participant_id' => $participantId,
            'payment_type' => 'DEPOSIT',
            'amount' => 300000,
            'paid_amount' => 0,
            'status' => 'PENDING',
        ]);

        // 3. Test IDOR: User 4 thử thanh toán cho User 3 -> 403 Forbidden
        $idorReq = new Request(
            method: 'POST',
            path: "/api/v1/payments/{$paymentId}/deposit",
            queryParams: [],
            bodyParams: [
                'amount' => 150000,
            ],
            headers: [
                'Authorization' => 'Bearer 4', // User 4 (không phải chủ payment)
            ]
        );
        $idorRes = $this->app->dispatch($idorReq);
        $this->assertSame(403, $idorRes->getStatusCode());
        $idorData = json_decode($idorRes->getContent(), true);
        $this->assertFalse($idorData['success']);
        $this->assertSame('FORBIDDEN', $idorData['error']['code']);

        // 4. User 3 thanh toán cọc — chống Price Tampering: dù client gửi amount=150000, server vẫn thu đủ 300000đ -> PAID
        $idempKey = 'DEP_TEST_' . uniqid();
        $depReq = new Request(
            method: 'POST',
            path: "/api/v1/payments/{$paymentId}/deposit",
            queryParams: [],
            bodyParams: [
                'amount' => 150000,
            ],
            headers: [
                'Authorization' => 'Bearer 3',
                'X-Idempotency-Key' => $idempKey,
            ]
        );
        $depRes = $this->app->dispatch($depReq);
        $this->assertSame(200, $depRes->getStatusCode());
        $depData = json_decode($depRes->getContent(), true);

        $this->assertTrue($depData['success']);
        $this->assertSame('PAID', $depData['data']['payment']['status']);
        $this->assertSame(300000, (int) $depData['data']['payment']['paid_amount']);
        $this->assertSame('SUCCESS', $depData['data']['transaction']['status']);

        // 5. Thử gửi lại đúng request với Idempotency-Key cũ -> Khoản phí đã thu đủ (400 PAYMENT_ALREADY_COMPLETED)
        $repeatRes = $this->app->dispatch($depReq);
        $this->assertSame(400, $repeatRes->getStatusCode());

        // 6. Gọi remaining trên khoản đã PAID -> Báo đã thanh toán đủ (400)
        $remReq = new Request(
            method: 'POST',
            path: "/api/v1/payments/{$paymentId}/remaining",
            queryParams: [],
            bodyParams: [],
            headers: [
                'Authorization' => 'Bearer 3',
            ]
        );
        $remRes = $this->app->dispatch($remReq);
        $this->assertSame(400, $remRes->getStatusCode());

        // 7. Cố tình thanh toán tiếp khi đã thanh toán đủ -> Báo lỗi 400
        $extraRes = $this->app->dispatch($remReq);
        $this->assertSame(400, $extraRes->getStatusCode());
        $extraData = json_decode($extraRes->getContent(), true);
        $this->assertFalse($extraData['success']);
        $this->assertSame('PAYMENT_ALREADY_COMPLETED', $extraData['error']['code']);
    }
}
