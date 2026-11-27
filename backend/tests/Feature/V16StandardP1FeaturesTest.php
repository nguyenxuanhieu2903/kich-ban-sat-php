<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Application;
use App\Core\Request;
use App\Infrastructure\Config\Config;
use App\Infrastructure\Database\DatabaseConnection;
use PDO;
use PHPUnit\Framework\TestCase;

class V16StandardP1FeaturesTest extends TestCase
{
    protected Application $app;
    protected PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();
        $basePath = dirname(__DIR__, 2);
        Config::load($basePath);
        $this->app = new Application($basePath);
        $this->pdo = DatabaseConnection::getInstance();
        $_SESSION = [];

        $routesFile = $basePath . '/routes/api.php';
        $router = $this->app->getRouter();
        require $routesFile;
    }

    public function testDMCanVerifyStudentCardAtCounter(): void
    {
        $request = new Request(
            method: 'POST',
            path: '/api/dm/users/7/verify-student',
            queryParams: [],
            bodyParams: [
                'student_verified' => true,
            ],
            headers: [
                'Authorization' => 'Bearer 3', // DM ID thật
            ]
        );

        $response = $this->app->dispatch($request);
        $this->assertSame(403, $response->getStatusCode());

        // Chỉ Admin mới có quyền duyệt thẻ HSSV
        $adminReq = new Request(
            method: 'POST',
            path: '/api/dm/users/7/verify-student',
            queryParams: [],
            bodyParams: [
                'student_verified' => true,
            ],
            headers: [
                'Authorization' => 'Bearer 1', // Admin ID
            ]
        );
        $adminRes = $this->app->dispatch($adminReq);
        $this->assertSame(200, $adminRes->getStatusCode());
        $data = json_decode($adminRes->getContent(), true);
        $this->assertTrue($data['success']);
    }

    public function testSessionSettlementAtCounter(): void
    {
        // 1. Tạo phòng, proposal và session mẫu (IN_PROGRESS chờ tất toán bước 2)
        $this->pdo->exec("INSERT INTO `game_rooms` (`game_id`, `game_configuration_id`, `creator_id`, `title`, `min_players`, `max_players`, `status`, `formation_deadline_at`, `created_at`, `updated_at`) VALUES (1, 1, 7, 'Phòng Test Settle Unit', 6, 8, 'IN_PROGRESS', DATE_ADD(NOW(), INTERVAL 3 DAY), NOW(), NOW())");
        $roomId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO `schedule_proposals` (`game_room_id`, `proposed_by`, `proposed_start_at`, `proposed_end_at`, `status`, `expires_at`, `created_at`) VALUES ({$roomId}, 7, NOW(), DATE_ADD(NOW(), INTERVAL 4 HOUR), 'CONFIRMED', DATE_ADD(NOW(), INTERVAL 24 HOUR), NOW())");
        $propId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO `dm_assignments` (`game_room_id`, `dm_user_id`, `assigned_by`, `status`, `assigned_at`, `created_at`, `updated_at`) VALUES ({$roomId}, 3, 1, 'ACCEPTED', NOW(), NOW(), NOW())");
        $assignId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO `sessions` (`game_room_id`, `schedule_proposal_id`, `dm_assignment_id`, `venue_id`, `physical_room_id`, `start_at`, `end_at`, `status`, `started_at`, `completed_at`, `created_at`, `updated_at`) VALUES ({$roomId}, {$propId}, {$assignId}, 1, 1, NOW(), DATE_ADD(NOW(), INTERVAL 4 HOUR), 'IN_PROGRESS', NOW(), NOW(), NOW(), NOW())");
        $sessId = (int) $this->pdo->lastInsertId();

        // 2. Gọi API xác nhận tất toán tại quầy với DM được phân công (User 3)
        $request = new Request(
            method: 'POST',
            path: "/api/dm/sessions/{$sessId}/settle",
            queryParams: [],
            bodyParams: [
                'notes' => 'Khách đã quét mã QR thanh toán nốt phần tiền tại quầy lễ tân',
            ],
            headers: [
                'Authorization' => 'Bearer 3', // DM ID thật
            ]
        );

        $response = $this->app->dispatch($request);
        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame('SETTLED', $data['data']['payment_status']);
    }

    public function testNoShowSinglePlayerNPCSubstitute(): void
    {
        $this->pdo->exec("INSERT INTO `game_rooms` (`game_id`, `game_configuration_id`, `creator_id`, `title`, `min_players`, `max_players`, `status`, `formation_deadline_at`, `created_at`, `updated_at`) VALUES (1, 1, 3, 'Phòng Test No Show Unit 1', 6, 8, 'CONFIRMED', DATE_ADD(NOW(), INTERVAL 3 DAY), NOW(), NOW())");
        $roomId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO `schedule_proposals` (`game_room_id`, `proposed_by`, `proposed_start_at`, `proposed_end_at`, `status`, `expires_at`, `created_at`) VALUES ({$roomId}, 3, NOW(), DATE_ADD(NOW(), INTERVAL 4 HOUR), 'CONFIRMED', DATE_ADD(NOW(), INTERVAL 24 HOUR), NOW())");
        $propId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO `dm_assignments` (`game_room_id`, `dm_user_id`, `assigned_by`, `status`, `assigned_at`, `created_at`, `updated_at`) VALUES ({$roomId}, 2, 1, 'ACCEPTED', NOW(), NOW(), NOW())");
        $assignId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO `sessions` (`game_room_id`, `schedule_proposal_id`, `dm_assignment_id`, `venue_id`, `physical_room_id`, `start_at`, `end_at`, `status`, `created_at`, `updated_at`) VALUES ({$roomId}, {$propId}, {$assignId}, 1, 1, NOW(), DATE_ADD(NOW(), INTERVAL 4 HOUR), 'IN_PROGRESS', NOW(), NOW())");
        $sessId = (int) $this->pdo->lastInsertId();

        $request = new Request(
            method: 'POST',
            path: "/api/dm/sessions/{$sessId}/no-show",
            queryParams: [],
            bodyParams: [
                'absent_user_ids' => [99999], // 1 người vắng mặt
            ],
            headers: [
                'Authorization' => 'Bearer 2',
            ]
        );

        $response = $this->app->dispatch($request);
        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame(1, $data['data']['absent_count']);
        $this->assertSame('NPC_SUBSTITUTE', $data['data']['action']);
        $this->assertSame('IN_PROGRESS', $data['data']['session_status']);
    }

    public function testNoShowMultiplePlayersCancelsOnSite(): void
    {
        // Tạo phòng test riêng
        $this->pdo->exec("INSERT INTO `game_rooms` (`game_id`, `game_configuration_id`, `creator_id`, `title`, `min_players`, `max_players`, `status`, `formation_deadline_at`, `created_at`, `updated_at`) VALUES (1, 1, 3, 'Phòng Test No Show 2', 6, 8, 'CONFIRMED', DATE_ADD(NOW(), INTERVAL 3 DAY), NOW(), NOW())");
        $roomId = (int) $this->pdo->lastInsertId();

        // Tạo 3 người chơi hợp lệ cho bài kiểm thử
        $pwd = password_hash('password123', PASSWORD_BCRYPT);
        $uIds = [];
        for ($i = 1; $i <= 3; $i++) {
            $email = 'noshow_p1_' . $i . '_' . uniqid() . '@kichbansat.vn';
            $st = $this->pdo->prepare("INSERT INTO `users` (`role_id`, `full_name`, `email`, `password_hash`, `status`, `created_at`, `updated_at`) VALUES (1, :n, :e, :p, 'ACTIVE', NOW(), NOW())");
            $st->execute([':n' => "NoShow User {$i}", ':e' => $email, ':p' => $pwd]);
            $uIds[] = (int) $this->pdo->lastInsertId();
        }

        // 3 thành viên
        $this->pdo->exec("INSERT INTO `room_participants` (`game_room_id`, `user_id`, `status`, `joined_at`) VALUES ({$roomId}, {$uIds[0]}, 'DEPOSITED', NOW()), ({$roomId}, {$uIds[1]}, 'DEPOSITED', NOW()), ({$roomId}, {$uIds[2]}, 'DEPOSITED', NOW())");

        $this->pdo->exec("INSERT INTO `schedule_proposals` (`game_room_id`, `proposed_by`, `proposed_start_at`, `proposed_end_at`, `status`, `expires_at`, `created_at`) VALUES ({$roomId}, {$uIds[0]}, NOW(), DATE_ADD(NOW(), INTERVAL 4 HOUR), 'CONFIRMED', DATE_ADD(NOW(), INTERVAL 24 HOUR), NOW())");
        $propId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO `dm_assignments` (`game_room_id`, `dm_user_id`, `assigned_by`, `status`, `assigned_at`, `created_at`, `updated_at`) VALUES ({$roomId}, 2, 1, 'ACCEPTED', NOW(), NOW(), NOW())");
        $assignId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO `sessions` (`game_room_id`, `schedule_proposal_id`, `dm_assignment_id`, `venue_id`, `physical_room_id`, `start_at`, `end_at`, `status`, `created_at`, `updated_at`) VALUES ({$roomId}, {$propId}, {$assignId}, 1, 1, NOW(), DATE_ADD(NOW(), INTERVAL 4 HOUR), 'IN_PROGRESS', NOW(), NOW())");
        $sessId = (int) $this->pdo->lastInsertId();

        // Báo 2 người vắng mặt
        $request = new Request(
            method: 'POST',
            path: "/api/dm/sessions/{$sessId}/no-show",
            queryParams: [],
            bodyParams: [
                'absent_user_ids' => [$uIds[0], $uIds[1]],
            ],
            headers: [
                'Authorization' => 'Bearer 2',
            ]
        );

        $response = $this->app->dispatch($request);
        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame('CANCELLED', $data['data']['session_status']);
        $this->assertSame('CANCELLED_ON_SITE', $data['data']['action']);
        $this->assertCount(2, $data['data']['forfeited_users']);
        $this->assertContains($uIds[2], $data['data']['refunded_users']);
    }

    public function testSuspensionOfMemberDoesNotDisbandFormingRoom(): void
    {
        // Tạo user member
        $email = 'member_test_' . uniqid() . '@test.vn';
        $this->pdo->exec("INSERT INTO `users` (`full_name`, `email`, `password_hash`, `role_id`, `status`, `created_at`) VALUES ('Member Test Suspend', '{$email}', 'hash', 3, 'ACTIVE', NOW())");
        $memberId = (int) $this->pdo->lastInsertId();

        // Tạo phòng FORMING có Host là user 3, và member là $memberId
        $this->pdo->exec("INSERT INTO `game_rooms` (`game_id`, `game_configuration_id`, `creator_id`, `title`, `min_players`, `max_players`, `status`, `confirmed_players`, `formation_deadline_at`, `created_at`, `updated_at`) VALUES (1, 1, 3, 'Phòng Test Suspend Member', 6, 8, 'FORMING', 2, DATE_ADD(NOW(), INTERVAL 3 DAY), NOW(), NOW())");
        $roomId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO `room_participants` (`game_room_id`, `user_id`, `status`, `joined_at`) VALUES ({$roomId}, 3, 'PENDING_DEPOSIT', NOW()), ({$roomId}, {$memberId}, 'PENDING_DEPOSIT', NOW())");

        // Admin khóa tài khoản của member
        $request = new Request(
            method: 'PATCH',
            path: "/api/admin/users/{$memberId}",
            queryParams: [],
            bodyParams: [
                'status' => 'SUSPENDED',
            ],
            headers: [
                'Authorization' => 'Bearer 1',
            ]
        );

        $response = $this->app->dispatch($request);
        $this->assertSame(200, $response->getStatusCode());

        // Kiểm tra phòng FORMING không bị giải tán
        $chk = $this->pdo->query("SELECT `status`, `confirmed_players` FROM `game_rooms` WHERE `id` = {$roomId}")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('FORMING', $chk['status']);
        $this->assertSame(1, (int) $chk['confirmed_players']);
    }

    public function testLumpSumDepositForPrivateRoom(): void
    {
        $pd = json_encode(['participant_seats' => ['7' => 5]]);
        $stmt = $this->pdo->prepare("INSERT INTO `game_rooms` (`game_id`, `game_configuration_id`, `creator_id`, `title`, `min_players`, `max_players`, `status`, `booking_type`, `total_players`, `guest_slots`, `price_details`, `formation_deadline_at`, `created_at`, `updated_at`) VALUES (1, 1, 7, 'Phòng Test Lump Sum', 6, 8, 'SCHEDULED', 'PRIVATE', 5, 1, :pd, DATE_ADD(NOW(), INTERVAL 3 DAY), NOW(), NOW())");
        $stmt->execute([':pd' => $pd]);
        $roomId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO `room_participants` (`game_room_id`, `user_id`, `status`, `joined_at`, `created_at`, `updated_at`) VALUES ({$roomId}, 7, 'PENDING_DEPOSIT', NOW(), NOW(), NOW())");

        $request = new Request(
            method: 'POST',
            path: '/api/payments/deposit',
            queryParams: [],
            bodyParams: [
                'room_id' => $roomId,
                'deposit_type' => 'LUMP_SUM',
            ],
            headers: [
                'Authorization' => 'Bearer 7', // Host Player ID
            ]
        );

        $response = $this->app->dispatch($request);
        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame(5, (int) $data['data']['seats']);
        $this->assertSame(250000, (int) $data['data']['amount']); // 5 * 50k = 250k
        $this->assertSame('DEPOSITED', $data['data']['status']);
    }
}
