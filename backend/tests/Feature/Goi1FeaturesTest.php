<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Application;
use App\Core\Request;
use App\Core\Response;
use App\Infrastructure\Database\DatabaseConnection;
use PDO;
use PHPUnit\Framework\TestCase;

class Goi1FeaturesTest extends TestCase
{
    protected Application $app;
    protected PDO $pdo;
    protected int $playerId = 0;
    protected int $player2Id = 0;
    protected int $adminId = 0;
    protected int $dmId = 0;
    protected int $roomId = 0;
    protected int $sessionId = 0;
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

        // Lấy role IDs chuẩn
        $adminRoleId = (int) $this->pdo->query("SELECT `id` FROM `roles` WHERE `code` = 'ADMIN' LIMIT 1")->fetchColumn();
        $dmRoleId = (int) $this->pdo->query("SELECT `id` FROM `roles` WHERE `code` = 'DM' LIMIT 1")->fetchColumn();
        $playerRoleId = (int) $this->pdo->query("SELECT `id` FROM `roles` WHERE `code` = 'PLAYER' LIMIT 1")->fetchColumn();

        // Lấy hoặc tạo game hợp lệ
        $gStmt = $this->pdo->query("SELECT `id` FROM `games` WHERE `status` = 'ACTIVE' LIMIT 1");
        $existingGameId = $gStmt->fetchColumn();
        if ($existingGameId !== false && $existingGameId !== null) {
            $this->gameId = (int) $existingGameId;
        } else {
            $this->pdo->exec("INSERT INTO `games` (`name`, `slug`, `min_players`, `max_players`, `duration_minutes`, `status`, `created_at`, `updated_at`) 
                VALUES ('Goi1 Test Game', 'goi1-test-game', 4, 8, 120, 'ACTIVE', NOW(), NOW())");
            $this->gameId = (int) $this->pdo->lastInsertId();
        }

        $cStmt = $this->pdo->prepare("SELECT `id` FROM `game_configurations` WHERE `game_id` = :game_id LIMIT 1");
        $cStmt->execute([':game_id' => $this->gameId]);
        $existingConfId = $cStmt->fetchColumn();
        if ($existingConfId !== false && $existingConfId !== null) {
            $this->configId = (int) $existingConfId;
        } else {
            $this->pdo->exec("INSERT INTO `game_configurations` (`game_id`, `version`, `configuration`, `created_by`, `created_at`) 
                VALUES ({$this->gameId}, 1, '{\"props\":[]}', 1, NOW())");
            $this->configId = (int) $this->pdo->lastInsertId();
        }

        $passHash = password_hash('Secret123', PASSWORD_BCRYPT);

        // Tạo Admin user
        $stmt = $this->pdo->prepare("INSERT INTO `users` (`role_id`, `full_name`, `email`, `password_hash`, `status`, `created_at`, `updated_at`) 
            VALUES (:role_id, 'Admin Goi1', :email, :pass, 'ACTIVE', NOW(), NOW())");
        $adminEmail = 'admin_goi1_' . uniqid() . '@test.vn';
        $stmt->execute([':role_id' => $adminRoleId, ':email' => $adminEmail, ':pass' => $passHash]);
        $this->adminId = (int) $this->pdo->lastInsertId();

        // Tạo DM user
        $stmt = $this->pdo->prepare("INSERT INTO `users` (`role_id`, `full_name`, `email`, `password_hash`, `status`, `created_at`, `updated_at`) 
            VALUES (:role_id, 'DM Goi1', :email, :pass, 'ACTIVE', NOW(), NOW())");
        $dmEmail = 'dm_goi1_' . uniqid() . '@test.vn';
        $stmt->execute([':role_id' => $dmRoleId, ':email' => $dmEmail, ':pass' => $passHash]);
        $this->dmId = (int) $this->pdo->lastInsertId();

        // Tạo Player 1 (Host)
        $stmt = $this->pdo->prepare("INSERT INTO `users` (`role_id`, `full_name`, `email`, `password_hash`, `status`, `student_card_status`, `created_at`, `updated_at`) 
            VALUES (:role_id, 'Host Player', :email, :pass, 'ACTIVE', 'NONE', NOW(), NOW())");
        $p1Email = 'host_goi1_' . uniqid() . '@test.vn';
        $stmt->execute([':role_id' => $playerRoleId, ':email' => $p1Email, ':pass' => $passHash]);
        $this->playerId = (int) $this->pdo->lastInsertId();

        // Tạo Player 2
        $stmt = $this->pdo->prepare("INSERT INTO `users` (`role_id`, `full_name`, `email`, `password_hash`, `status`, `student_card_status`, `created_at`, `updated_at`) 
            VALUES (:role_id, 'Member Player', :email, :pass, 'ACTIVE', 'NONE', NOW(), NOW())");
        $p2Email = 'member_goi1_' . uniqid() . '@test.vn';
        $stmt->execute([':role_id' => $playerRoleId, ':email' => $p2Email, ':pass' => $passHash]);
        $this->player2Id = (int) $this->pdo->lastInsertId();

        // Khởi tạo phòng test
        $this->pdo->exec("INSERT INTO `game_rooms` (`game_id`, `game_configuration_id`, `creator_id`, `title`, `min_players`, `max_players`, `status`, `formation_deadline_at`, `created_at`, `updated_at`) 
            VALUES ({$this->gameId}, {$this->configId}, {$this->playerId}, 'Phòng Test Kick', 4, 8, 'FORMING', DATE_ADD(NOW(), INTERVAL 24 HOUR), NOW(), NOW())");
        $this->roomId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO `room_participants` (`game_room_id`, `user_id`, `status`, `joined_at`, `created_at`, `updated_at`) 
            VALUES ({$this->roomId}, {$this->playerId}, 'PENDING_DEPOSIT', NOW(), NOW(), NOW())");

        $this->pdo->exec("INSERT INTO `room_participants` (`game_room_id`, `user_id`, `status`, `joined_at`, `created_at`, `updated_at`) 
            VALUES ({$this->roomId}, {$this->player2Id}, 'PENDING_DEPOSIT', NOW(), NOW(), NOW())");
        $memberPartId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO `player_payments` (`room_participant_id`, `payment_type`, `amount`, `paid_amount`, `status`, `created_at`, `updated_at`) 
            VALUES ({$memberPartId}, 'DEPOSIT', 50000, 0, 'PENDING', NOW(), NOW())");
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }

        if (!empty($this->roomId)) {
            $this->pdo->exec("DELETE FROM `sessions` WHERE `game_room_id` = {$this->roomId}");
            $this->pdo->exec("DELETE FROM `refunds` WHERE `player_payment_id` IN (SELECT `id` FROM `player_payments` WHERE `room_participant_id` IN (SELECT `id` FROM `room_participants` WHERE `game_room_id` = {$this->roomId}))");
            $this->pdo->exec("DELETE FROM `player_payments` WHERE `room_participant_id` IN (SELECT `id` FROM `room_participants` WHERE `game_room_id` = {$this->roomId})");
            $this->pdo->exec("DELETE FROM `room_participants` WHERE `game_room_id` = {$this->roomId}");
            $this->pdo->exec("DELETE FROM `dm_assignments` WHERE `game_room_id` = {$this->roomId}");
            $this->pdo->exec("DELETE FROM `schedule_proposals` WHERE `game_room_id` = {$this->roomId}");
            $this->pdo->exec("DELETE FROM `game_rooms` WHERE `id` = {$this->roomId}");
        }

        $userIds = array_filter([$this->adminId, $this->dmId, $this->playerId, $this->player2Id]);
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

    protected function postJson(string $path, array $data): Response
    {
        $raw = json_encode($data);
        $req = new Request('POST', $path, [], $data, ['content-type' => 'application/json'], $raw);
        return $this->app->dispatch($req);
    }

    public function testPlayerCanSubmitStudentCard(): void
    {
        $this->authenticateAs($this->playerId, 'PLAYER');

        $res = $this->postJson('/api/v1/auth/student-card', [
            'file_name' => 'the_sinh_vien_hieu.jpg',
            'card_url' => '/uploads/students/the_sinh_vien_hieu.jpg'
        ]);

        $this->assertEquals(200, $res->getStatusCode());

        $data = json_decode($res->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('PENDING', $data['data']['student_card_status']);
        $this->assertEquals('/uploads/students/the_sinh_vien_hieu.jpg', $data['data']['student_card_url']);

        $stmt = $this->pdo->query("SELECT `student_card_status`, `student_card_url` FROM `users` WHERE `id` = {$this->playerId}");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals('PENDING', $row['student_card_status']);
    }

    public function testAdminCanApproveStudentCard(): void
    {
        $this->pdo->exec("UPDATE `users` SET `student_card_status` = 'PENDING', `student_card_url` = '/uploads/students/the.jpg' WHERE `id` = {$this->playerId}");

        $this->authenticateAs($this->adminId, 'ADMIN');

        $res = $this->postJson("/api/v1/admin/users/{$this->playerId}/verify-student", [
            'status' => 'APPROVED'
        ]);

        $this->assertEquals(200, $res->getStatusCode());

        $data = json_decode($res->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('APPROVED', $data['data']['student_card_status']);

        $stmt = $this->pdo->query("SELECT `student_card_status`, `student_verified_at` FROM `users` WHERE `id` = {$this->playerId}");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals('APPROVED', $row['student_card_status']);
        $this->assertNotNull($row['student_verified_at']);
    }

    public function testAdminCanRejectStudentCardWithReason(): void
    {
        $this->pdo->exec("UPDATE `users` SET `student_card_status` = 'PENDING' WHERE `id` = {$this->playerId}");

        $this->authenticateAs($this->adminId, 'ADMIN');

        $res = $this->postJson("/api/v1/admin/users/{$this->playerId}/verify-student", [
            'status' => 'REJECTED',
            'reason' => 'Ảnh thẻ mờ, không thấy ngày hết hạn'
        ]);

        $this->assertEquals(200, $res->getStatusCode());

        $data = json_decode($res->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('REJECTED', $data['data']['student_card_status']);

        $stmt = $this->pdo->query("SELECT `student_card_status`, `student_reject_reason` FROM `users` WHERE `id` = {$this->playerId}");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals('REJECTED', $row['student_card_status']);
        $this->assertEquals('Ảnh thẻ mờ, không thấy ngày hết hạn', $row['student_reject_reason']);
    }

    public function testNonAdminCannotVerifyStudentCard(): void
    {
        $this->authenticateAs($this->playerId, 'PLAYER');

        $res = $this->postJson("/api/v1/admin/users/{$this->player2Id}/verify-student", [
            'status' => 'APPROVED'
        ]);

        $this->assertEquals(403, $res->getStatusCode());
    }

    public function testHostCanKickMemberFromFormingRoom(): void
    {
        $this->authenticateAs($this->playerId, 'PLAYER');

        $res = $this->postJson("/api/v1/rooms/{$this->roomId}/kick", [
            'user_id' => $this->player2Id,
            'reason' => 'Không nộp cọc đúng hạn'
        ]);

        $this->assertEquals(200, $res->getStatusCode());

        $data = json_decode($res->getContent(), true);
        $this->assertTrue($data['success']);

        $stmt = $this->pdo->query("SELECT `status`, `cancel_reason` FROM `room_participants` WHERE `game_room_id` = {$this->roomId} AND `user_id` = {$this->player2Id}");
        $part = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals('CANCELLED_BY_ADMIN', $part['status']);
        $this->assertEquals('Không nộp cọc đúng hạn', $part['cancel_reason']);
    }

    public function testHostCannotKickSelf(): void
    {
        $this->authenticateAs($this->playerId, 'PLAYER');

        $res = $this->postJson("/api/v1/rooms/{$this->roomId}/kick", [
            'user_id' => $this->playerId
        ]);

        $this->assertEquals(400, $res->getStatusCode());
    }

    public function testNonHostCannotKickMember(): void
    {
        $this->authenticateAs($this->player2Id, 'PLAYER');

        $res = $this->postJson("/api/v1/rooms/{$this->roomId}/kick", [
            'user_id' => $this->playerId
        ]);

        $this->assertEquals(403, $res->getStatusCode());
    }

    public function testDmCanCompleteSessionWithNotesAndIncident(): void
    {
        $vStmt = $this->pdo->query("SELECT `id` FROM `venues` WHERE `status` = 'ACTIVE' LIMIT 1");
        $venueId = (int) $vStmt->fetchColumn();

        $prStmt = $this->pdo->prepare("SELECT `id` FROM `physical_rooms` WHERE `venue_id` = :vid LIMIT 1");
        $prStmt->execute([':vid' => $venueId]);
        $phyRoomId = (int) $prStmt->fetchColumn();

        $this->pdo->exec("INSERT INTO `dm_assignments` (`game_room_id`, `dm_user_id`, `assigned_by`, `status`, `assigned_at`, `created_at`, `updated_at`) 
            VALUES ({$this->roomId}, {$this->dmId}, {$this->adminId}, 'ACCEPTED', NOW(), NOW(), NOW())");
        $dmAssignId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO `schedule_proposals` (`game_room_id`, `proposed_by`, `proposed_start_at`, `proposed_end_at`, `status`, `expires_at`, `created_at`) 
            VALUES ({$this->roomId}, {$this->playerId}, NOW(), DATE_ADD(NOW(), INTERVAL 3 HOUR), 'CONFIRMED', DATE_ADD(NOW(), INTERVAL 2 HOUR), NOW())");
        $propId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO `sessions` (`game_room_id`, `schedule_proposal_id`, `dm_assignment_id`, `venue_id`, `physical_room_id`, `start_at`, `end_at`, `status`, `started_at`, `created_at`, `updated_at`) 
            VALUES ({$this->roomId}, {$propId}, {$dmAssignId}, {$venueId}, {$phyRoomId}, NOW(), DATE_ADD(NOW(), INTERVAL 3 HOUR), 'IN_PROGRESS', NOW(), NOW(), NOW())");
        $this->sessionId = (int) $this->pdo->lastInsertId();

        $this->authenticateAs($this->dmId, 'DM');

        $res = $this->postJson("/api/v1/sessions/{$this->sessionId}/complete", [
            'session_notes' => 'Người chơi phá án thành công trong 2h30m.',
            'incident_report' => 'Lá bài số 7 bị gãy nhẹ, cần ép plastic lại.'
        ]);

        $this->assertEquals(200, $res->getStatusCode());

        $data = json_decode($res->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('COMPLETED', $data['data']['session']['status']);
        $this->assertEquals('Người chơi phá án thành công trong 2h30m.', $data['data']['session']['session_notes']);
        $this->assertEquals('Lá bài số 7 bị gãy nhẹ, cần ép plastic lại.', $data['data']['session']['incident_report']);

        $sStmt = $this->pdo->query("SELECT `status`, `session_notes`, `incident_report` FROM `sessions` WHERE `id` = {$this->sessionId}");
        $row = $sStmt->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals('COMPLETED', $row['status']);
        $this->assertEquals('Người chơi phá án thành công trong 2h30m.', $row['session_notes']);
        $this->assertEquals('Lá bài số 7 bị gãy nhẹ, cần ép plastic lại.', $row['incident_report']);
    }
}
