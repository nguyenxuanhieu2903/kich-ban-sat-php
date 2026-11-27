<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Application;
use App\Core\Request;
use App\Infrastructure\Config\Config;
use App\Infrastructure\Database\DatabaseConnection;
use PHPUnit\Framework\TestCase;

class Stage3ApiTest extends TestCase
{
    protected Application $app;

    protected function setUp(): void
    {
        parent::setUp();
        $basePath = dirname(__DIR__, 2);
        Config::load($basePath);
        $this->app = new Application($basePath);
        $_SESSION = [];

        $routesFile = $basePath . '/routes/api.php';
        $router = $this->app->getRouter();
        require $routesFile;
    }

    public function testAdminAssignAndRevokeDmAndListAuditLogs(): void
    {
        $pdo = DatabaseConnection::getInstance();
        $pd = json_encode(['participant_seats' => ['7' => 4]]);
        $st = $pdo->prepare("INSERT INTO `game_rooms` (`game_id`, `game_configuration_id`, `creator_id`, `title`, `min_players`, `max_players`, `total_players`, `status`, `price_details`, `formation_deadline_at`, `created_at`, `updated_at`) VALUES (1, 1, 7, 'Phòng Test Stage3 DM', 4, 8, 4, 'FORMING', :pd, DATE_ADD(NOW(), INTERVAL 3 DAY), NOW(), NOW())");
        $st->execute([':pd' => $pd]);
        $roomId = (int) $pdo->lastInsertId();
        $pdo->exec("INSERT INTO `room_participants` (`game_room_id`, `user_id`, `status`, `joined_at`, `created_at`, `updated_at`) VALUES ({$roomId}, 7, 'DEPOSITED', NOW(), NOW(), NOW())");
        $pdo->exec("INSERT INTO `dm_assignments` (`game_room_id`, `dm_user_id`, `assigned_by`, `status`, `assigned_at`, `created_at`, `updated_at`) VALUES ({$roomId}, 3, 1, 'ACCEPTED', NOW(), NOW(), NOW())");

        $assignReq = new Request(
            method: 'POST',
            path: "/api/v1/admin/rooms/{$roomId}/assign-dm",
            queryParams: [],
            bodyParams: ['dm_user_id' => 3],
            headers: ['Authorization' => 'Bearer 1']
        );
        $assignRes = $this->app->dispatch($assignReq);
        $this->assertSame(201, $assignRes->getStatusCode());
        $assignData = json_decode($assignRes->getContent(), true);
        $assignmentId = (int) $assignData['data']['assignment_id'];

        $revokeReq = new Request(
            method: 'POST',
            path: "/api/v1/dm/assignments/{$assignmentId}/revoke",
            queryParams: [],
            bodyParams: ['reason' => 'Đổi ca phân công cho DM khác'],
            headers: ['Authorization' => 'Bearer 1']
        );
        $revokeRes = $this->app->dispatch($revokeReq);
        $this->assertSame(200, $revokeRes->getStatusCode());

        $auditReq = new Request(
            method: 'GET',
            path: '/api/v1/admin/audit-logs',
            queryParams: ['page' => 1, 'per_page' => 10],
            bodyParams: [],
            headers: ['Authorization' => 'Bearer 1']
        );
        $auditRes = $this->app->dispatch($auditReq);
        $this->assertSame(200, $auditRes->getStatusCode());
        $auditData = json_decode($auditRes->getContent(), true);
        $this->assertTrue($auditData['success']);
        $this->assertNotEmpty($auditData['data']);
    }

    public function testStudentVerificationAndNotificationsReadAll(): void
    {
        $verifyReq = new Request(
            method: 'PUT',
            path: '/api/v1/admin/users/7/student-verification',
            queryParams: [],
            bodyParams: ['student_verified' => true],
            headers: ['Authorization' => 'Bearer 1']
        );
        $verifyRes = $this->app->dispatch($verifyReq);
        $this->assertSame(200, $verifyRes->getStatusCode());
        $this->assertTrue(json_decode($verifyRes->getContent(), true)['data']['student_verified']);

        $pdo = DatabaseConnection::getInstance();
        $user = $pdo->query('SELECT `student_verified` FROM `users` WHERE `id` = 7')->fetch();
        $this->assertSame(1, (int) $user['student_verified']);

        $readAllReq = new Request(
            method: 'POST',
            path: '/api/v1/notifications/read-all',
            queryParams: [],
            bodyParams: [],
            headers: ['Authorization' => 'Bearer 7']
        );
        $readAllRes = $this->app->dispatch($readAllReq);
        $this->assertSame(200, $readAllRes->getStatusCode());
    }

    public function testAdminAssignVenue(): void
    {
        $pdo = DatabaseConnection::getInstance();
        $pr = $pdo->query("SELECT `id`, `venue_id` FROM `physical_rooms` WHERE `status` = 'ACTIVE' AND COALESCE(`is_maintenance`, 0) = 0 AND `capacity` >= 4 ORDER BY `id` ASC LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
        $this->assertNotFalse($pr);
        $targetPrId = (int) $pr['id'];
        $targetVenueId = (int) $pr['venue_id'];

        $pd = json_encode(['participant_seats' => ['7' => 4]]);
        $futureSlot = date('Y-m-d 10:00:00', strtotime('+45 days'));
        $st = $pdo->prepare("INSERT INTO `game_rooms` (`game_id`, `game_configuration_id`, `creator_id`, `title`, `min_players`, `max_players`, `total_players`, `status`, `target_time_slot`, `price_details`, `formation_deadline_at`, `created_at`, `updated_at`) VALUES (1, 1, 7, 'Phòng Test Stage3 Venue', 4, 8, 4, 'FORMING', :slot, :pd, DATE_ADD(NOW(), INTERVAL 3 DAY), NOW(), NOW())");
        $st->execute([':slot' => $futureSlot, ':pd' => $pd]);
        $roomId = (int) $pdo->lastInsertId();
        $pdo->exec("INSERT INTO `room_participants` (`game_room_id`, `user_id`, `status`, `joined_at`, `created_at`, `updated_at`) VALUES ({$roomId}, 7, 'DEPOSITED', NOW(), NOW(), NOW())");
        $pdo->exec("INSERT INTO `dm_assignments` (`game_room_id`, `dm_user_id`, `assigned_by`, `status`, `assigned_at`, `created_at`, `updated_at`) VALUES ({$roomId}, 3, 1, 'ACCEPTED', NOW(), NOW(), NOW())");

        $venueReq = new Request(
            method: 'POST',
            path: "/api/v1/admin/rooms/{$roomId}/assign-venue",
            queryParams: [],
            bodyParams: [
                'venue_id' => $targetVenueId,
                'physical_room_id' => $targetPrId,
            ],
            headers: ['Authorization' => 'Bearer 1']
        );
        $venueRes = $this->app->dispatch($venueReq);
        $this->assertSame(200, $venueRes->getStatusCode());
        $venueData = json_decode($venueRes->getContent(), true);
        $this->assertTrue($venueData['success']);
        $this->assertSame($targetVenueId, (int) $venueData['data']['venue_id']);
        $this->assertSame($targetPrId, (int) $venueData['data']['physical_room_id']);
    }

    public function testCanGetSessionDetail(): void
    {
        $req = new Request(
            method: 'GET',
            path: '/api/v1/sessions/1',
            queryParams: [],
            bodyParams: [],
            headers: ['Authorization' => 'Bearer 1']
        );
        $res = $this->app->dispatch($req);
        $this->assertSame(200, $res->getStatusCode());
        $data = json_decode($res->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('session', $data['data']);
        $this->assertSame(1, (int) $data['data']['session']['id']);
    }
}