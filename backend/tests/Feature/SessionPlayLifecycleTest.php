<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Application;
use App\Core\Request;
use App\Infrastructure\Config\Config;
use App\Infrastructure\Database\DatabaseConnection;
use PDO;
use PHPUnit\Framework\TestCase;

class SessionPlayLifecycleTest extends TestCase
{
    protected Application $app;
    protected PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();
        $basePath = dirname(__DIR__, 2);
        Config::load($basePath);
        $this->app = new Application($basePath);
        $_SESSION = [];

        $this->pdo = DatabaseConnection::getInstance();

        $routesFile = $basePath . '/routes/api.php';
        $router = $this->app->getRouter();
        require $routesFile;
    }

    protected function createTestSession(int $dmUserId = 2): array
    {
        // 1. Tạo phòng test
        $stmt = $this->pdo->prepare(
            "INSERT INTO `game_rooms` (`game_id`, `game_configuration_id`, `creator_id`, `title`, `min_players`, `max_players`, `status`, `formation_deadline_at`, `created_at`, `updated_at`)
             VALUES (1, 1, 3, :title, 4, 8, 'SCHEDULED', DATE_ADD(NOW(), INTERVAL 3 DAY), NOW(), NOW())"
        );
        $stmt->bindValue(':title', 'Phòng test session play ' . uniqid(), PDO::PARAM_STR);
        $stmt->execute();
        $roomId = (int) $this->pdo->lastInsertId();

        // 2. Tạo proposal CONFIRMED
        $pStmt = $this->pdo->prepare(
            "INSERT INTO `schedule_proposals` (`game_room_id`, `proposed_by`, `proposed_start_at`, `proposed_end_at`, `status`, `confirmed_at`, `expires_at`, `created_at`)
             VALUES (:room_id, 3, DATE_ADD(NOW(), INTERVAL 1 DAY), DATE_ADD(NOW(), INTERVAL 29 HOUR), 'CONFIRMED', NOW(), DATE_ADD(NOW(), INTERVAL 1 DAY), NOW())"
        );
        $pStmt->bindValue(':room_id', $roomId, PDO::PARAM_INT);
        $pStmt->execute();
        $propId = (int) $this->pdo->lastInsertId();

        // 3. Tạo DM assignment ACCEPTED
        $aStmt = $this->pdo->prepare(
            "INSERT INTO `dm_assignments` (`game_room_id`, `dm_user_id`, `assigned_by`, `status`, `assigned_at`, `responded_at`, `created_at`, `updated_at`)
             VALUES (:room_id, :dm_id, 1, 'ACCEPTED', NOW(), NOW(), NOW(), NOW())"
        );
        $aStmt->bindValue(':room_id', $roomId, PDO::PARAM_INT);
        $aStmt->bindValue(':dm_id', $dmUserId, PDO::PARAM_INT);
        $aStmt->execute();
        $assignId = (int) $this->pdo->lastInsertId();

        // 4. Tạo session SCHEDULED
        $sStmt = $this->pdo->prepare(
            "INSERT INTO `sessions` (`game_room_id`, `schedule_proposal_id`, `dm_assignment_id`, `venue_id`, `physical_room_id`, `start_at`, `end_at`, `status`, `created_at`, `updated_at`)
             VALUES (:room_id, :prop_id, :assign_id, 1, 1, DATE_ADD(NOW(), INTERVAL 1 DAY), DATE_ADD(NOW(), INTERVAL 29 HOUR), 'SCHEDULED', NOW(), NOW())"
        );
        $sStmt->bindValue(':room_id', $roomId, PDO::PARAM_INT);
        $sStmt->bindValue(':prop_id', $propId, PDO::PARAM_INT);
        $sStmt->bindValue(':assign_id', $assignId, PDO::PARAM_INT);
        $sStmt->execute();
        $sessionId = (int) $this->pdo->lastInsertId();

        return [
            'room_id' => $roomId,
            'proposal_id' => $propId,
            'assignment_id' => $assignId,
            'session_id' => $sessionId,
            'dm_user_id' => $dmUserId,
        ];
    }

    public function testAssignedDMCanStartSession(): void
    {
        $fixture = $this->createTestSession(2);
        $sessionId = $fixture['session_id'];

        $_SESSION['user_id'] = 2;
        $req = new Request(
            method: 'POST',
            path: "/api/v1/sessions/{$sessionId}/start",
            queryParams: [],
            bodyParams: [],
            headers: ['Authorization' => 'Bearer 2'] // DM 2
        );

        $res = $this->app->dispatch($req);
        $this->assertSame(200, $res->getStatusCode(), 'DM 2 should be able to start their assigned session: ' . $res->getContent());

        $data = json_decode($res->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame('IN_PROGRESS', $data['data']['session']['status']);
        $this->assertNotNull($data['data']['session']['started_at']);

        // Check DB directly
        $status = $this->pdo->query("SELECT `status` FROM `sessions` WHERE `id` = {$sessionId}")->fetchColumn();
        $this->assertSame('IN_PROGRESS', $status);
    }

    public function testIDORPreventionOnStartSession(): void
    {
        $fixture = $this->createTestSession(2); // Assigned to DM 2
        $sessionId = $fixture['session_id'];

        // Player (User 3) tries to start -> 403
        $_SESSION['user_id'] = 3;
        $playerReq = new Request(
            method: 'POST',
            path: "/api/v1/sessions/{$sessionId}/start",
            queryParams: [],
            bodyParams: [],
            headers: ['Authorization' => 'Bearer 3']
        );
        $playerRes = $this->app->dispatch($playerReq);
        $this->assertSame(403, $playerRes->getStatusCode());

        // Temporarily set User 4 as DM to test cross-DM IDOR
        $this->pdo->exec("UPDATE `users` SET `role_id` = (SELECT `id` FROM `roles` WHERE `code` = 'DM' LIMIT 1) WHERE `id` = 4");

        $_SESSION['user_id'] = 4;
        $otherDmReq = new Request(
            method: 'POST',
            path: "/api/v1/sessions/{$sessionId}/start",
            queryParams: [],
            bodyParams: [],
            headers: ['Authorization' => 'Bearer 4'] // Different DM
        );
        $otherDmRes = $this->app->dispatch($otherDmReq);
        $this->assertSame(403, $otherDmRes->getStatusCode());

        // Revert user 4
        $this->pdo->exec("UPDATE `users` SET `role_id` = (SELECT `id` FROM `roles` WHERE `code` = 'PLAYER' LIMIT 1) WHERE `id` = 4");
    }

    public function testAdminCanOverrideAndStartSession(): void
    {
        $fixture = $this->createTestSession(2);
        $sessionId = $fixture['session_id'];

        $_SESSION['user_id'] = 1;
        $adminReq = new Request(
            method: 'POST',
            path: "/api/v1/sessions/{$sessionId}/start",
            queryParams: [],
            bodyParams: [],
            headers: ['Authorization' => 'Bearer 1'] // Admin
        );
        $adminRes = $this->app->dispatch($adminReq);
        $this->assertSame(200, $adminRes->getStatusCode(), 'Admin should be able to start session: ' . $adminRes->getContent());
        $data = json_decode($adminRes->getContent(), true);
        $this->assertSame('IN_PROGRESS', $data['data']['session']['status']);
    }

    public function testIdempotentStartSession(): void
    {
        $fixture = $this->createTestSession(2);
        $sessionId = $fixture['session_id'];

        $_SESSION['user_id'] = 2;
        $req = new Request(
            method: 'POST',
            path: "/api/v1/sessions/{$sessionId}/start",
            queryParams: [],
            bodyParams: [],
            headers: ['Authorization' => 'Bearer 2']
        );

        $res1 = $this->app->dispatch($req);
        $this->assertSame(200, $res1->getStatusCode());

        // Re-call start on already IN_PROGRESS session
        $res2 = $this->app->dispatch($req);
        $this->assertSame(200, $res2->getStatusCode());
        $data2 = json_decode($res2->getContent(), true);
        $this->assertTrue($data2['success']);
        $this->assertSame('IN_PROGRESS', $data2['data']['session']['status']);
    }

    public function testCannotCompleteSessionBeforeStarting(): void
    {
        $fixture = $this->createTestSession(2);
        $sessionId = $fixture['session_id'];

        $_SESSION['user_id'] = 2;
        $completeReq = new Request(
            method: 'POST',
            path: "/api/v1/sessions/{$sessionId}/complete",
            queryParams: [],
            bodyParams: [],
            headers: ['Authorization' => 'Bearer 2']
        );
        $completeRes = $this->app->dispatch($completeReq);
        $this->assertSame(400, $completeRes->getStatusCode());
        $data = json_decode($completeRes->getContent(), true);
        $this->assertSame('INVALID_SESSION_STATUS', $data['error']['code']);
    }

    public function testAssignedDMCanCompleteSessionAndSyncGameRoom(): void
    {
        $fixture = $this->createTestSession(2);
        $sessionId = $fixture['session_id'];
        $roomId = $fixture['room_id'];

        // 1. Start session first
        $_SESSION['user_id'] = 2;
        $startReq = new Request(
            method: 'POST',
            path: "/api/v1/sessions/{$sessionId}/start",
            queryParams: [],
            bodyParams: [],
            headers: ['Authorization' => 'Bearer 2']
        );
        $startRes = $this->app->dispatch($startReq);
        $this->assertSame(200, $startRes->getStatusCode());

        // 2. Complete session
        $completeReq = new Request(
            method: 'POST',
            path: "/api/v1/sessions/{$sessionId}/complete",
            queryParams: [],
            bodyParams: [],
            headers: ['Authorization' => 'Bearer 2']
        );
        $completeRes = $this->app->dispatch($completeReq);
        $this->assertSame(200, $completeRes->getStatusCode(), 'Complete session should succeed: ' . $completeRes->getContent());

        $data = json_decode($completeRes->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame('COMPLETED', $data['data']['session']['status']);
        $this->assertNotNull($data['data']['session']['completed_at']);

        // 3. Verify sync with game_rooms.status = COMPLETED
        $roomStatus = $this->pdo->query("SELECT `status` FROM `game_rooms` WHERE `id` = {$roomId}")->fetchColumn();
        $this->assertSame('COMPLETED', $roomStatus);

        // 4. Idempotent complete replay
        $repeatRes = $this->app->dispatch($completeReq);
        $this->assertSame(200, $repeatRes->getStatusCode());

        // 5. Cannot start an already completed session
        $restartRes = $this->app->dispatch($startReq);
        $this->assertSame(400, $restartRes->getStatusCode());
    }

    public function testNonExistentSessionReturns404(): void
    {
        $_SESSION['user_id'] = 1;
        $req = new Request(
            method: 'POST',
            path: '/api/v1/sessions/999999/start',
            queryParams: [],
            bodyParams: [],
            headers: ['Authorization' => 'Bearer 1']
        );
        $res = $this->app->dispatch($req);
        $this->assertSame(404, $res->getStatusCode());
    }
}
