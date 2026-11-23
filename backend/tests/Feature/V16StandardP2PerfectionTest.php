<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Application;
use App\Core\Request;
use App\Features\GameroomManagement\Services\BackgroundJobService;
use App\Infrastructure\Config\Config;
use App\Infrastructure\Database\DatabaseConnection;
use PDO;
use PHPUnit\Framework\TestCase;

class V16StandardP2PerfectionTest extends TestCase
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

    protected function createTestUser(string $name): int
    {
        $hash = password_hash('password123', PASSWORD_BCRYPT);
        $email = 'test_' . uniqid() . '@kichbansat.vn';
        $phone = '09' . str_pad((string) mt_rand(10000000, 99999999), 8, '0', STR_PAD_LEFT);
        $stmt = $this->pdo->prepare("INSERT INTO `users` (`role_id`, `full_name`, `email`, `password_hash`, `phone`, `status`, `created_at`, `updated_at`) VALUES (1, :name, :email, :hash, :phone, 'ACTIVE', NOW(), NOW())");
        $stmt->bindValue(':name', $name, PDO::PARAM_STR);
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $stmt->bindValue(':hash', $hash, PDO::PARAM_STR);
        $stmt->bindValue(':phone', $phone, PDO::PARAM_STR);
        $stmt->execute();
        return (int) $this->pdo->lastInsertId();
    }

    public function testDMOvertimeAlert(): void
    {
        $this->pdo->exec("INSERT INTO `game_rooms` (`game_id`, `game_configuration_id`, `creator_id`, `title`, `min_players`, `max_players`, `status`, `formation_deadline_at`, `created_at`, `updated_at`) VALUES (1, 1, 3, 'Phòng Test Overtime Alert', 6, 8, 'CONFIRMED', DATE_ADD(NOW(), INTERVAL 3 DAY), NOW(), NOW())");
        $roomId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO `schedule_proposals` (`game_room_id`, `proposed_by`, `proposed_start_at`, `proposed_end_at`, `status`, `expires_at`, `created_at`) VALUES ({$roomId}, 3, NOW(), DATE_ADD(NOW(), INTERVAL 4 HOUR), 'CONFIRMED', DATE_ADD(NOW(), INTERVAL 24 HOUR), NOW())");
        $propId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO `dm_assignments` (`game_room_id`, `dm_user_id`, `assigned_by`, `status`, `assigned_at`, `created_at`, `updated_at`) VALUES ({$roomId}, 2, 1, 'ACCEPTED', NOW(), NOW(), NOW())");
        $assignId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO `physical_rooms` (`venue_id`, `name`, `capacity`, `status`, `created_at`, `updated_at`) VALUES (1, 'Phòng Test OT " . uniqid() . "', 10, 'ACTIVE', NOW(), NOW())");
        $pRoomId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO `sessions` (`game_room_id`, `schedule_proposal_id`, `dm_assignment_id`, `venue_id`, `physical_room_id`, `start_at`, `end_at`, `status`, `created_at`, `updated_at`) VALUES ({$roomId}, {$propId}, {$assignId}, 1, {$pRoomId}, NOW(), DATE_ADD(NOW(), INTERVAL 4 HOUR), 'IN_PROGRESS', NOW(), NOW())");
        $sessionId = (int) $this->pdo->lastInsertId();

        $request = new Request(
            method: 'POST',
            path: "/api/dm/sessions/{$sessionId}/overtime-alert",
            queryParams: [],
            bodyParams: [
                'estimated_minutes' => 45,
                'reason' => 'Đội chơi tranh luận cao trào, đang phá giải mật mã cuối',
            ],
            headers: [
                'Authorization' => 'Bearer 2',
            ]
        );

        $response = $this->app->dispatch($request);
        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertTrue($data['data']['alert_sent']);
        $this->assertSame(45, $data['data']['estimated_minutes']);
    }

    public function testSettlementWithExtraFeeAtCounter(): void
    {
        $this->pdo->exec("INSERT INTO `game_rooms` (`game_id`, `game_configuration_id`, `creator_id`, `title`, `min_players`, `max_players`, `status`, `formation_deadline_at`, `created_at`, `updated_at`) VALUES (1, 1, 3, 'Phòng Test Extra Fee', 6, 8, 'CONFIRMED', DATE_ADD(NOW(), INTERVAL 3 DAY), NOW(), NOW())");
        $roomId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO `schedule_proposals` (`game_room_id`, `proposed_by`, `proposed_start_at`, `proposed_end_at`, `status`, `expires_at`, `created_at`) VALUES ({$roomId}, 3, NOW(), DATE_ADD(NOW(), INTERVAL 4 HOUR), 'CONFIRMED', DATE_ADD(NOW(), INTERVAL 24 HOUR), NOW())");
        $propId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO `dm_assignments` (`game_room_id`, `dm_user_id`, `assigned_by`, `status`, `assigned_at`, `created_at`, `updated_at`) VALUES ({$roomId}, 2, 1, 'ACCEPTED', NOW(), NOW(), NOW())");
        $assignId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO `physical_rooms` (`venue_id`, `name`, `capacity`, `status`, `created_at`, `updated_at`) VALUES (1, 'Phòng Test Fee " . uniqid() . "', 10, 'ACTIVE', NOW(), NOW())");
        $pRoomId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO `sessions` (`game_room_id`, `schedule_proposal_id`, `dm_assignment_id`, `venue_id`, `physical_room_id`, `start_at`, `end_at`, `status`, `created_at`, `updated_at`) VALUES ({$roomId}, {$propId}, {$assignId}, 1, {$pRoomId}, NOW(), DATE_ADD(NOW(), INTERVAL 4 HOUR), 'COMPLETED', NOW(), NOW())");
        $sessionId = (int) $this->pdo->lastInsertId();

        $request = new Request(
            method: 'POST',
            path: "/api/dm/sessions/{$sessionId}/settle",
            queryParams: [],
            bodyParams: [
                'notes' => 'Tất toán quầy có phát sinh đền bù phụ phí',
                'extra_fee' => 120000,
                'extra_fee_reason' => 'Đền bù rách trang phục hóa trang',
            ],
            headers: [
                'Authorization' => 'Bearer 2',
            ]
        );

        $response = $this->app->dispatch($request);
        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame('SETTLED', $data['data']['payment_status']);
        $this->assertSame(120000, (int) $data['data']['extra_fee']);
        $this->assertSame('Đền bù rách trang phục hóa trang', $data['data']['extra_fee_reason']);
    }

    public function testPostGameReviewsSubmitAndRetrieve(): void
    {
        $this->pdo->exec("INSERT INTO `game_rooms` (`game_id`, `game_configuration_id`, `creator_id`, `title`, `min_players`, `max_players`, `status`, `formation_deadline_at`, `created_at`, `updated_at`) VALUES (1, 1, 3, 'Phòng Test Reviews', 6, 8, 'CONFIRMED', DATE_ADD(NOW(), INTERVAL 3 DAY), NOW(), NOW())");
        $roomId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO `room_participants` (`game_room_id`, `user_id`, `status`, `created_at`, `updated_at`) VALUES ({$roomId}, 3, 'DEPOSITED', NOW(), NOW())");

        $this->pdo->exec("INSERT INTO `schedule_proposals` (`game_room_id`, `proposed_by`, `proposed_start_at`, `proposed_end_at`, `status`, `expires_at`, `created_at`) VALUES ({$roomId}, 3, NOW(), DATE_ADD(NOW(), INTERVAL 4 HOUR), 'CONFIRMED', DATE_ADD(NOW(), INTERVAL 24 HOUR), NOW())");
        $propId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO `dm_assignments` (`game_room_id`, `dm_user_id`, `assigned_by`, `status`, `assigned_at`, `created_at`, `updated_at`) VALUES ({$roomId}, 2, 1, 'ACCEPTED', NOW(), NOW(), NOW())");
        $assignId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO `physical_rooms` (`venue_id`, `name`, `capacity`, `status`, `created_at`, `updated_at`) VALUES (1, 'Phòng Test Rev " . uniqid() . "', 10, 'ACTIVE', NOW(), NOW())");
        $pRoomId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO `sessions` (`game_room_id`, `schedule_proposal_id`, `dm_assignment_id`, `venue_id`, `physical_room_id`, `start_at`, `end_at`, `status`, `created_at`, `updated_at`) VALUES ({$roomId}, {$propId}, {$assignId}, 1, {$pRoomId}, NOW(), DATE_ADD(NOW(), INTERVAL 4 HOUR), 'COMPLETED', NOW(), NOW())");
        $sessionId = (int) $this->pdo->lastInsertId();

        // 1. Submit review
        $subReq = new Request(
            method: 'POST',
            path: "/api/sessions/{$sessionId}/reviews",
            queryParams: [],
            bodyParams: [
                'dm_rating' => 5,
                'scenario_rating' => 4,
                'comment' => 'DM Hoàng Nam dẫn truyện cực kỳ cuốn hút!',
            ],
            headers: [
                'Authorization' => 'Bearer 3',
            ]
        );
        $subRes = $this->app->dispatch($subReq);
        $this->assertSame(200, $subRes->getStatusCode());
        $subData = json_decode($subRes->getContent(), true);
        $this->assertTrue($subData['success']);
        $this->assertSame(5, $subData['data']['dm_rating']);

        // 2. Get reviews
        $getReq = new Request(
            method: 'GET',
            path: "/api/sessions/{$sessionId}/reviews"
        );
        $getRes = $this->app->dispatch($getReq);
        $this->assertSame(200, $getRes->getStatusCode());
        $getData = json_decode($getRes->getContent(), true);
        $this->assertTrue($getData['success']);
        $this->assertGreaterThanOrEqual(1, $getData['data']['total_reviews']);
        $this->assertSame(5, $getData['data']['reviews'][0]['dm_rating']);
        $this->assertSame(4, $getData['data']['reviews'][0]['scenario_rating']);
    }

    public function testSmartFormation24hExtension(): void
    {
        // Tạo phòng FORMING có 5/6 người (đạt 83% >= 70%) và đã quá hạn
        $this->pdo->exec("INSERT INTO `game_rooms` (`game_id`, `game_configuration_id`, `creator_id`, `title`, `min_players`, `max_players`, `status`, `formation_deadline_at`, `created_at`, `updated_at`) VALUES (1, 1, 3, 'Phòng Test Smart Ext " . uniqid() . "', 6, 8, 'FORMING', DATE_SUB(NOW(), INTERVAL 1 HOUR), NOW(), NOW())");
        $roomId = (int) $this->pdo->lastInsertId();

        // Tạo và chèn 5 người dùng hợp lệ
        for ($i = 0; $i < 5; $i++) {
            $uId = $this->createTestUser("User Ext {$i}");
            $this->pdo->exec("INSERT INTO `room_participants` (`game_room_id`, `user_id`, `status`, `created_at`, `updated_at`) VALUES ({$roomId}, {$uId}, 'DEPOSITED', NOW(), NOW())");
        }

        $service = new BackgroundJobService($this->pdo);
        $service->runFormationTimeout();

        // Kiểm tra phòng không bị hủy mà được gia hạn
        $stmt = $this->pdo->prepare('SELECT `status`, `replacement_reason`, `formation_deadline_at` FROM `game_rooms` WHERE `id` = :id');
        $stmt->bindValue(':id', $roomId, PDO::PARAM_INT);
        $stmt->execute();
        $room = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertSame('FORMING', $room['status']);
        $this->assertSame('AUTO_EXTENDED_24H', $room['replacement_reason']);
        $this->assertGreaterThan(time(), strtotime((string) $room['formation_deadline_at']));
    }

    public function testQuorum80PercentConfirmsProposal(): void
    {
        // Tạo phòng có 5 thành viên active (Quorum 80% = ceil(5 * 0.8) = 4 người)
        $this->pdo->exec("INSERT INTO `game_rooms` (`game_id`, `game_configuration_id`, `creator_id`, `title`, `min_players`, `max_players`, `status`, `formation_deadline_at`, `created_at`, `updated_at`) VALUES (1, 1, 3, 'Phòng Test Quorum 80 " . uniqid() . "', 5, 8, 'FORMING', DATE_ADD(NOW(), INTERVAL 3 DAY), NOW(), NOW())");
        $roomId = (int) $this->pdo->lastInsertId();

        $userIds = [];
        for ($i = 0; $i < 5; $i++) {
            $uId = $this->createTestUser("User Quorum {$i}");
            $userIds[] = $uId;
            $this->pdo->exec("INSERT INTO `room_participants` (`game_room_id`, `user_id`, `status`, `created_at`, `updated_at`) VALUES ({$roomId}, {$uId}, 'PENDING_DEPOSIT', NOW(), NOW())");
        }

        // Tạo đề xuất
        $this->pdo->exec("INSERT INTO `schedule_proposals` (`game_room_id`, `proposed_by`, `proposed_start_at`, `proposed_end_at`, `status`, `expires_at`, `created_at`) VALUES ({$roomId}, {$userIds[0]}, '2026-12-05 14:00:00', '2026-12-05 18:00:00', 'PROPOSED', DATE_ADD(NOW(), INTERVAL 12 HOUR), NOW())");
        $propId = (int) $this->pdo->lastInsertId();

        // User 0 tự động vote ACCEPTED (1/5)
        $this->pdo->exec("INSERT INTO `schedule_responses` (`game_room_id`, `schedule_proposal_id`, `room_participant_id`, `response`, `responded_at`, `created_at`)
                          SELECT {$roomId}, {$propId}, `id`, 'ACCEPTED', NOW(), NOW() FROM `room_participants` WHERE `game_room_id` = {$roomId} AND `user_id` = {$userIds[0]}");

        // User 1 vote ACCEPTED (2/5)
        $v1Req = new Request(method: 'POST', path: "/api/proposals/{$propId}/vote", queryParams: [], bodyParams: ['response' => 'ACCEPTED'], headers: ['Authorization' => "Bearer {$userIds[1]}"]);
        $v1Res = $this->app->dispatch($v1Req);
        $this->assertFalse(json_decode($v1Res->getContent(), true)['data']['proposal_confirmed']);

        // User 2 vote ACCEPTED (3/5)
        $v2Req = new Request(method: 'POST', path: "/api/proposals/{$propId}/vote", queryParams: [], bodyParams: ['response' => 'ACCEPTED'], headers: ['Authorization' => "Bearer {$userIds[2]}"]);
        $v2Res = $this->app->dispatch($v2Req);
        $this->assertFalse(json_decode($v2Res->getContent(), true)['data']['proposal_confirmed']);

        // User 3 vote ACCEPTED (4/5 = 80% Quorum) -> TỰ ĐỘNG CHỐT LỊCH NGAY MÀ KHÔNG CẦN CHỜ USER 4!
        $v3Req = new Request(method: 'POST', path: "/api/proposals/{$propId}/vote", queryParams: [], bodyParams: ['response' => 'ACCEPTED'], headers: ['Authorization' => "Bearer {$userIds[3]}"]);
        $v3Res = $this->app->dispatch($v3Req);
        $v3Data = json_decode($v3Res->getContent(), true);

        $this->assertTrue($v3Data['success']);
        $this->assertTrue($v3Data['data']['proposal_confirmed']);
        $this->assertSame(4, $v3Data['data']['accepted_votes']);
        $this->assertSame(4, $v3Data['data']['quorum_threshold']);
        $this->assertNotNull($v3Data['data']['session_id']);
    }
}
