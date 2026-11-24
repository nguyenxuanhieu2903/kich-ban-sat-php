<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Application;
use App\Core\Request;
use App\Core\Response;
use App\Infrastructure\Database\DatabaseConnection;
use PDO;
use PHPUnit\Framework\TestCase;

class AdminDashboardAndPaymentApiTest extends TestCase
{
    protected Application $app;
    protected PDO $pdo;
    protected int $adminId = 0;
    protected int $playerId = 0;
    protected int $roomId = 0;
    protected int $participantId = 0;
    protected int $paymentId = 0;
    protected int $gameId = 0;
    protected int $configId = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app = require __DIR__ . '/../../bootstrap/app.php';
        $this->pdo = DatabaseConnection::getInstance();

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }

        $adminRoleId = (int) $this->pdo->query("SELECT `id` FROM `roles` WHERE `code` = 'ADMIN' LIMIT 1")->fetchColumn();
        $playerRoleId = (int) $this->pdo->query("SELECT `id` FROM `roles` WHERE `code` = 'PLAYER' LIMIT 1")->fetchColumn();

        // Lấy game và config hợp lệ
        $gStmt = $this->pdo->query("SELECT `id` FROM `games` WHERE `status` = 'ACTIVE' LIMIT 1");
        $this->gameId = (int) $gStmt->fetchColumn();

        $cStmt = $this->pdo->prepare("SELECT `id` FROM `game_configurations` WHERE `game_id` = :game_id LIMIT 1");
        $cStmt->execute([':game_id' => $this->gameId]);
        $this->configId = (int) $cStmt->fetchColumn();

        $passHash = password_hash('Secret123', PASSWORD_BCRYPT);

        // Tạo Admin user
        $stmt = $this->pdo->prepare("INSERT INTO `users` (`role_id`, `full_name`, `email`, `phone`, `password_hash`, `status`, `created_at`, `updated_at`) 
            VALUES (:role_id, 'Admin Dashboard Test', :email, '0988776655', :pass, 'ACTIVE', NOW(), NOW())");
        $adminEmail = 'admin_dash_' . uniqid() . '@test.vn';
        $stmt->execute([':role_id' => $adminRoleId, ':email' => $adminEmail, ':pass' => $passHash]);
        $this->adminId = (int) $this->pdo->lastInsertId();

        // Tạo Player user
        $stmt = $this->pdo->prepare("INSERT INTO `users` (`role_id`, `full_name`, `email`, `phone`, `password_hash`, `status`, `created_at`, `updated_at`) 
            VALUES (:role_id, 'Player Payment Test', :email, '0911223344', :pass, 'ACTIVE', NOW(), NOW())");
        $playerEmail = 'player_pay_' . uniqid() . '@test.vn';
        $stmt->execute([':role_id' => $playerRoleId, ':email' => $playerEmail, ':pass' => $passHash]);
        $this->playerId = (int) $this->pdo->lastInsertId();

        // Tạo room và participant để sinh test payment
        $this->pdo->exec("INSERT INTO `game_rooms` (`game_id`, `game_configuration_id`, `creator_id`, `title`, `min_players`, `max_players`, `status`, `formation_deadline_at`, `created_at`, `updated_at`) 
            VALUES ({$this->gameId}, {$this->configId}, {$this->playerId}, 'Phòng Test Sổ Cái', 4, 8, 'FORMING', DATE_ADD(NOW(), INTERVAL 24 HOUR), NOW(), NOW())");
        $this->roomId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO `room_participants` (`game_room_id`, `user_id`, `status`, `joined_at`, `created_at`, `updated_at`) 
            VALUES ({$this->roomId}, {$this->playerId}, 'PENDING_DEPOSIT', NOW(), NOW(), NOW())");
        $this->participantId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO `player_payments` (`room_participant_id`, `payment_type`, `amount`, `paid_amount`, `status`, `created_at`, `updated_at`) 
            VALUES ({$this->participantId}, 'DEPOSIT', 60000, 60000, 'PAID', NOW(), NOW())");
        $this->paymentId = (int) $this->pdo->lastInsertId();

        // Thêm 1 payment_transaction
        $this->pdo->exec("INSERT INTO `payment_transactions` (`player_payment_id`, `idempotency_key`, `transaction_reference`, `amount`, `status`, `provider_response`, `created_at`)
            VALUES ({$this->paymentId}, 'IDEMP-TEST-999', 'TXTEST999', 60000, 'SUCCESS', '{\"code\":\"00\"}', NOW())");
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }

        if (!empty($this->paymentId)) {
            $this->pdo->exec("DELETE FROM `payment_transactions` WHERE `player_payment_id` = {$this->paymentId}");
            $this->pdo->exec("DELETE FROM `refunds` WHERE `player_payment_id` = {$this->paymentId}");
            $this->pdo->exec("DELETE FROM `player_payments` WHERE `id` = {$this->paymentId}");
        }

        if (!empty($this->roomId)) {
            $this->pdo->exec("DELETE FROM `room_participants` WHERE `game_room_id` = {$this->roomId}");
            $this->pdo->exec("DELETE FROM `game_rooms` WHERE `id` = {$this->roomId}");
        }

        $userIds = array_filter([$this->adminId, $this->playerId]);
        if (!empty($userIds)) {
            $inList = implode(',', $userIds);
            $this->pdo->exec("DELETE FROM `notifications` WHERE `user_id` IN ({$inList})");
            $this->pdo->exec("DELETE FROM `users` WHERE `id` IN ({$inList})");
        }

        parent::tearDown();
    }

    protected function authenticateAs(int $userId, string $roleCode): void
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }
        $_SESSION['user_id'] = $userId;
        $_SESSION['role_code'] = $roleCode;
    }

    protected function getJson(string $path, array $queryParams = []): Response
    {
        $req = new Request('GET', $path, $queryParams, [], ['content-type' => 'application/json']);
        return $this->app->dispatch($req);
    }

    public function testAdminCanFetchDashboardMetrics(): void
    {
        $this->authenticateAs($this->adminId, 'ADMIN');

        $res = $this->getJson('/api/v1/admin/dashboard');
        $this->assertEquals(200, $res->getStatusCode());

        $body = json_decode($res->getContent(), true);
        $this->assertTrue($body['success'] ?? false);
        $this->assertArrayHasKey('overview', $body['data']);
        $this->assertArrayHasKey('revenue_chart_7d', $body['data']);
        $this->assertArrayHasKey('rooms_by_status', $body['data']);

        $overview = $body['data']['overview'];
        $this->assertArrayHasKey('revenue_month', $overview);
        $this->assertArrayHasKey('revenue_last_month', $overview);
        $this->assertArrayHasKey('revenue_growth_pct', $overview);
        $this->assertArrayHasKey('sessions_today', $overview);
        $this->assertArrayHasKey('pending_refunds', $overview);
        $this->assertArrayHasKey('pending_students', $overview);
        $this->assertArrayHasKey('active_rooms', $overview);
        $this->assertArrayHasKey('occupancy_rate', $overview);

        $chart = $body['data']['revenue_chart_7d'];
        $this->assertIsArray($chart);
        $this->assertCount(7, $chart);
    }

    public function testNonAdminCannotFetchDashboardMetrics(): void
    {
        $this->authenticateAs($this->playerId, 'PLAYER');

        $res = $this->getJson('/api/v1/admin/dashboard');
        $this->assertEquals(403, $res->getStatusCode());
    }

    public function testAdminCanListAndFilterPayments(): void
    {
        $this->authenticateAs($this->adminId, 'ADMIN');

        // Lấy danh sách không lọc
        $res = $this->getJson('/api/v1/admin/payments');
        $this->assertEquals(200, $res->getStatusCode());

        $body = json_decode($res->getContent(), true);
        $this->assertTrue($body['success'] ?? false);
        $this->assertArrayHasKey('payments', $body['data']);
        $this->assertArrayHasKey('pagination', $body['data']);
        $this->assertArrayHasKey('meta', $body['data']);

        $meta = $body['data']['meta'];
        $this->assertArrayHasKey('total_paid_amount', $meta);
        $this->assertArrayHasKey('total_pending_amount', $meta);

        // Lọc theo search (tên người chơi đã tạo)
        $resFilter = $this->getJson('/api/v1/admin/payments', ['search' => 'Player Payment Test']);
        $this->assertEquals(200, $resFilter->getStatusCode());
        $bodyFilter = json_decode($resFilter->getContent(), true);
        $payments = $bodyFilter['data']['payments'] ?? [];
        $this->assertNotEmpty($payments);
        $this->assertEquals($this->paymentId, $payments[0]['id']);

        // Lọc theo status PAID
        $resPaid = $this->getJson('/api/v1/admin/payments', ['status' => 'PAID']);
        $this->assertEquals(200, $resPaid->getStatusCode());
        $bodyPaid = json_decode($resPaid->getContent(), true);
        $paidList = $bodyPaid['data']['payments'] ?? [];
        foreach ($paidList as $p) {
            $this->assertEquals('PAID', $p['status']);
        }
    }

    public function testNonAdminCannotListPayments(): void
    {
        $this->authenticateAs($this->playerId, 'PLAYER');

        $res = $this->getJson('/api/v1/admin/payments');
        $this->assertEquals(403, $res->getStatusCode());
    }

    public function testAdminCanShowPaymentDetails(): void
    {
        $this->authenticateAs($this->adminId, 'ADMIN');

        $res = $this->getJson("/api/v1/admin/payments/{$this->paymentId}");
        $this->assertEquals(200, $res->getStatusCode());

        $body = json_decode($res->getContent(), true);
        $this->assertTrue($body['success'] ?? false);
        $this->assertArrayHasKey('payment', $body['data']);

        $payment = $body['data']['payment'];
        $this->assertEquals($this->paymentId, $payment['id']);
        $this->assertEquals('PAID', $payment['status']);
        $this->assertArrayHasKey('transactions', $payment);
        $this->assertNotEmpty($payment['transactions']);
        $this->assertEquals('TXTEST999', $payment['transactions'][0]['transaction_reference']);
    }

    public function testAdminShowNonExistentPaymentReturns404(): void
    {
        $this->authenticateAs($this->adminId, 'ADMIN');

        $res = $this->getJson('/api/v1/admin/payments/99999999');
        $this->assertEquals(404, $res->getStatusCode());
    }
}
