<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Application;
use App\Core\Request;
use App\Core\Response;
use App\Infrastructure\Database\DatabaseConnection;
use PDO;
use PHPUnit\Framework\TestCase;

class AdminGoi3SystemAndConfigTest extends TestCase
{
    protected Application $app;
    protected PDO $pdo;
    protected int $adminId = 0;
    protected int $playerId = 0;
    protected int $gameId = 0;
    protected array $createdConfigIds = [];

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

        // Lấy 1 game có sẵn
        $gStmt = $this->pdo->query("SELECT `id` FROM `games` WHERE `status` = 'ACTIVE' LIMIT 1");
        $this->gameId = (int) $gStmt->fetchColumn();

        $passHash = password_hash('Secret123', PASSWORD_BCRYPT);

        // Tạo Admin user
        $stmt = $this->pdo->prepare("INSERT INTO `users` (`role_id`, `full_name`, `email`, `password_hash`, `status`, `created_at`, `updated_at`) 
            VALUES (:role_id, 'Admin Goi3 Test', :email, :pass, 'ACTIVE', NOW(), NOW())");
        $adminEmail = 'admin_goi3_' . uniqid() . '@test.vn';
        $stmt->execute([':role_id' => $adminRoleId, ':email' => $adminEmail, ':pass' => $passHash]);
        $this->adminId = (int) $this->pdo->lastInsertId();

        // Tạo Player user
        $stmt = $this->pdo->prepare("INSERT INTO `users` (`role_id`, `full_name`, `email`, `password_hash`, `status`, `created_at`, `updated_at`) 
            VALUES (:role_id, 'Player Goi3 Test', :email, :pass, 'ACTIVE', NOW(), NOW())");
        $playerEmail = 'player_goi3_' . uniqid() . '@test.vn';
        $stmt->execute([':role_id' => $playerRoleId, ':email' => $playerEmail, ':pass' => $passHash]);
        $this->playerId = (int) $this->pdo->lastInsertId();
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }

        if (!empty($this->createdConfigIds)) {
            $inList = implode(',', $this->createdConfigIds);
            $this->pdo->exec("DELETE FROM `game_configurations` WHERE `id` IN ({$inList})");
        }

        $userIds = array_filter([$this->adminId, $this->playerId]);
        if (!empty($userIds)) {
            $inList = implode(',', $userIds);
            $this->pdo->exec("DELETE FROM `audit_logs` WHERE `user_id` IN ({$inList})");
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

    protected function postJson(string $path, array $data): Response
    {
        $raw = json_encode($data);
        $req = new Request('POST', $path, [], $data, ['content-type' => 'application/json'], $raw);
        return $this->app->dispatch($req);
    }

    protected function patchJson(string $path, array $data): Response
    {
        $raw = json_encode($data);
        $req = new Request('PATCH', $path, [], $data, ['content-type' => 'application/json'], $raw);
        return $this->app->dispatch($req);
    }

    public function testAdminCanListGameConfigurations(): void
    {
        $this->authenticateAs($this->adminId, 'ADMIN');

        $res = $this->getJson("/api/v1/admin/games/{$this->gameId}/configurations");
        $this->assertEquals(200, $res->getStatusCode());

        $body = json_decode($res->getContent(), true);
        $this->assertTrue($body['success'] ?? false);
        $this->assertArrayHasKey('configurations', $body['data']);
        $this->assertArrayHasKey('game', $body['data']);
    }

    public function testAdminCanCreateAndPatchGameConfiguration(): void
    {
        $this->authenticateAs($this->adminId, 'ADMIN');

        // Create new configuration
        $configPayload = [
            'configuration' => [
                'characters' => [
                    ['id' => 1, 'name' => 'Thám tử Mori', 'gender' => 'M', 'role' => 'Detective'],
                    ['id' => 2, 'name' => 'Nữ quý tộc', 'gender' => 'F', 'role' => 'Suspect']
                ],
                'props' => ['Bức thư tình', 'Đồng hồ cát']
            ],
            'description' => 'Bản phát hành test v999',
            'is_active' => 1
        ];

        $resCreate = $this->postJson("/api/v1/admin/games/{$this->gameId}/configurations", $configPayload);
        $this->assertEquals(201, $resCreate->getStatusCode());

        $createData = json_decode($resCreate->getContent(), true);
        $this->assertTrue($createData['success'] ?? false);
        $newId = (int) ($createData['data']['id'] ?? 0);
        $this->assertGreaterThan(0, $newId);
        $this->createdConfigIds[] = $newId;

        // Verify audit log entry
        $logStmt = $this->pdo->prepare("SELECT * FROM `audit_logs` WHERE `action` = 'GAME_CONFIGURATION_CREATED' AND `entity_id` = :id LIMIT 1");
        $logStmt->execute([':id' => $newId]);
        $logEntry = $logStmt->fetch(PDO::FETCH_ASSOC);
        $this->assertNotEmpty($logEntry);

        // Patch configuration
        $resPatch = $this->patchJson("/api/v1/admin/game-configurations/{$newId}", [
            'description' => 'Mô tả đã được cập nhật',
            'is_active' => 0
        ]);
        $this->assertEquals(200, $resPatch->getStatusCode());

        $patchData = json_decode($resPatch->getContent(), true);
        $this->assertTrue($patchData['data']['updated'] ?? false);
    }

    public function testNonAdminCannotManageGameConfigurations(): void
    {
        $this->authenticateAs($this->playerId, 'PLAYER');

        $resGet = $this->getJson("/api/v1/admin/games/{$this->gameId}/configurations");
        $this->assertEquals(403, $resGet->getStatusCode());

        $resPost = $this->postJson("/api/v1/admin/games/{$this->gameId}/configurations", [
            'configuration' => ['props' => []]
        ]);
        $this->assertEquals(403, $resPost->getStatusCode());
    }

    public function testAdminCanFetchAuditLogs(): void
    {
        $this->authenticateAs($this->adminId, 'ADMIN');

        $res = $this->getJson('/api/v1/admin/audit-logs');
        $this->assertEquals(200, $res->getStatusCode());

        $body = json_decode($res->getContent(), true);
        $this->assertTrue($body['success'] ?? false);
        $this->assertArrayHasKey('logs', $body['data']);
        $this->assertArrayHasKey('pagination', $body['data']);

        // Test filtering
        $resFilter = $this->getJson('/api/v1/admin/audit-logs', ['action' => 'USER_ROLE_CHANGED']);
        $this->assertEquals(200, $resFilter->getStatusCode());
    }

    public function testNonAdminCannotFetchAuditLogs(): void
    {
        $this->authenticateAs($this->playerId, 'PLAYER');

        $res = $this->getJson('/api/v1/admin/audit-logs');
        $this->assertEquals(403, $res->getStatusCode());
    }

    public function testAdminCanFetchSystemHealth(): void
    {
        $this->authenticateAs($this->adminId, 'ADMIN');

        $res = $this->getJson('/api/v1/admin/system/health');
        $this->assertEquals(200, $res->getStatusCode());

        $body = json_decode($res->getContent(), true);
        $this->assertTrue($body['success'] ?? false);
        $this->assertEquals('HEALTHY', $body['data']['status']);
        $this->assertArrayHasKey('database', $body['data']);
        $this->assertArrayHasKey('runtime', $body['data']);
        $this->assertArrayHasKey('storage', $body['data']);
        $this->assertEquals('OK', $body['data']['database']['status']);
    }

    public function testNonAdminCannotFetchSystemHealth(): void
    {
        $this->authenticateAs($this->playerId, 'PLAYER');

        $res = $this->getJson('/api/v1/admin/system/health');
        $this->assertEquals(403, $res->getStatusCode());
    }
}
