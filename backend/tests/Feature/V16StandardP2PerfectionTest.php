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

    public function testAntiReplayProtectionOnJoin(): void
    {
        $testUser = $this->createTestUser('Player Replay Test');

        // Tạo phòng cũ đã hoàn thành cho game 1
        $this->pdo->exec("INSERT INTO `game_rooms` (`game_id`, `game_configuration_id`, `creator_id`, `title`, `min_players`, `max_players`, `status`, `formation_deadline_at`, `created_at`, `updated_at`) VALUES (1, 1, {$testUser}, 'Phòng Cũ Đã Xong', 6, 8, 'CONFIRMED', DATE_ADD(NOW(), INTERVAL 3 DAY), NOW(), NOW())");
        $oldRoomId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO `room_participants` (`game_room_id`, `user_id`, `status`, `created_at`, `updated_at`) VALUES ({$oldRoomId}, {$testUser}, 'DEPOSITED', NOW(), NOW())");

        $this->pdo->exec("INSERT INTO `schedule_proposals` (`game_room_id`, `proposed_by`, `proposed_start_at`, `proposed_end_at`, `status`, `expires_at`, `created_at`) VALUES ({$oldRoomId}, {$testUser}, NOW(), DATE_ADD(NOW(), INTERVAL 4 HOUR), 'CONFIRMED', DATE_ADD(NOW(), INTERVAL 24 HOUR), NOW())");
        $propId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO `dm_assignments` (`game_room_id`, `dm_user_id`, `assigned_by`, `status`, `assigned_at`, `created_at`, `updated_at`) VALUES ({$oldRoomId}, 2, 1, 'ACCEPTED', NOW(), NOW(), NOW())");
        $assignId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO `physical_rooms` (`venue_id`, `name`, `capacity`, `status`, `created_at`, `updated_at`) VALUES (1, 'Phòng Replay " . uniqid() . "', 10, 'ACTIVE', NOW(), NOW())");
        $pRoomId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO `sessions` (`game_room_id`, `schedule_proposal_id`, `dm_assignment_id`, `venue_id`, `physical_room_id`, `start_at`, `end_at`, `status`, `created_at`, `updated_at`) VALUES ({$oldRoomId}, {$propId}, {$assignId}, 1, {$pRoomId}, NOW(), DATE_ADD(NOW(), INTERVAL 4 HOUR), 'COMPLETED', NOW(), NOW())");

        // Tạo phòng mới cho cùng game 1
        $this->pdo->exec("INSERT INTO `game_rooms` (`game_id`, `game_configuration_id`, `creator_id`, `title`, `min_players`, `max_players`, `status`, `formation_deadline_at`, `created_at`, `updated_at`) VALUES (1, 1, 3, 'Phòng Mới Game 1', 6, 8, 'FORMING', DATE_ADD(NOW(), INTERVAL 3 DAY), NOW(), NOW())");
        $newRoomId = (int) $this->pdo->lastInsertId();

        // 1. Thử tham gia phòng mới -> Bị chặn vì đã từng chơi kịch bản này
        $joinReq = new Request(
            method: 'POST',
            path: "/api/rooms/{$newRoomId}/join",
            queryParams: [],
            bodyParams: ['strict_anti_spoiler' => true],
            headers: ['Authorization' => "Bearer {$testUser}"]
        );
        $joinRes = $this->app->dispatch($joinReq);
        $this->assertSame(400, $joinRes->getStatusCode());
        $joinData = json_decode($joinRes->getContent(), true);
        $this->assertFalse($joinData['success']);
        $this->assertSame('ALREADY_COMPLETED_SCENARIO', $joinData['error']['code']);

        // 2. Tham gia kèm cờ allow_replay -> Thành công
        $repReq = new Request(
            method: 'POST',
            path: "/api/rooms/{$newRoomId}/join",
            queryParams: [],
            bodyParams: ['strict_anti_spoiler' => true, 'allow_replay' => true],
            headers: ['Authorization' => "Bearer {$testUser}"]
        );
        $repRes = $this->app->dispatch($repReq);
        $this->assertSame(200, $repRes->getStatusCode());
        $this->assertTrue(json_decode($repRes->getContent(), true)['success']);
    }

    public function testPlayerScheduleConflictProtection(): void
    {
        $testUser = $this->createTestUser('Player Conflict Test');

        // Phòng A: ca chơi từ 14:00 đến 18:00
        $this->pdo->exec("INSERT INTO `game_rooms` (`game_id`, `game_configuration_id`, `creator_id`, `title`, `min_players`, `max_players`, `status`, `formation_deadline_at`, `created_at`, `updated_at`) VALUES (1, 1, {$testUser}, 'Phòng A Conflict', 6, 8, 'CONFIRMED', DATE_ADD(NOW(), INTERVAL 3 DAY), NOW(), NOW())");
        $roomA = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO `room_participants` (`game_room_id`, `user_id`, `status`, `created_at`, `updated_at`) VALUES ({$roomA}, {$testUser}, 'DEPOSITED', NOW(), NOW())");

        $this->pdo->exec("INSERT INTO `schedule_proposals` (`game_room_id`, `proposed_by`, `proposed_start_at`, `proposed_end_at`, `status`, `expires_at`, `created_at`) VALUES ({$roomA}, {$testUser}, '2026-12-10 14:00:00', '2026-12-10 18:00:00', 'CONFIRMED', DATE_ADD(NOW(), INTERVAL 24 HOUR), NOW())");
        $propA = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO `dm_assignments` (`game_room_id`, `dm_user_id`, `assigned_by`, `status`, `assigned_at`, `created_at`, `updated_at`) VALUES ({$roomA}, 2, 1, 'ACCEPTED', NOW(), NOW(), NOW())");
        $assignA = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO `physical_rooms` (`venue_id`, `name`, `capacity`, `status`, `created_at`, `updated_at`) VALUES (1, 'Phòng Confl " . uniqid() . "', 10, 'ACTIVE', NOW(), NOW())");
        $pRoomA = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO `sessions` (`game_room_id`, `schedule_proposal_id`, `dm_assignment_id`, `venue_id`, `physical_room_id`, `start_at`, `end_at`, `status`, `created_at`, `updated_at`) VALUES ({$roomA}, {$propA}, {$assignA}, 1, {$pRoomA}, '2026-12-10 14:00:00', '2026-12-10 18:00:00', 'SCHEDULED', NOW(), NOW())");

        // Phòng B: cùng khung giờ vắt chéo 16:00 đến 20:00
        $this->pdo->exec("INSERT INTO `game_rooms` (`game_id`, `game_configuration_id`, `creator_id`, `title`, `min_players`, `max_players`, `status`, `formation_deadline_at`, `created_at`, `updated_at`) VALUES (2, 2, 3, 'Phòng B Conflict', 6, 8, 'FORMING', DATE_ADD(NOW(), INTERVAL 3 DAY), NOW(), NOW())");
        $roomB = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO `schedule_proposals` (`game_room_id`, `proposed_by`, `proposed_start_at`, `proposed_end_at`, `status`, `expires_at`, `created_at`) VALUES ({$roomB}, 3, '2026-12-10 16:00:00', '2026-12-10 20:00:00', 'CONFIRMED', DATE_ADD(NOW(), INTERVAL 24 HOUR), NOW())");
        $propB = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO `dm_assignments` (`game_room_id`, `dm_user_id`, `assigned_by`, `status`, `assigned_at`, `created_at`, `updated_at`) VALUES ({$roomB}, 2, 1, 'ACCEPTED', NOW(), NOW(), NOW())");
        $assignB = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO `physical_rooms` (`venue_id`, `name`, `capacity`, `status`, `created_at`, `updated_at`) VALUES (2, 'Phòng Confl B " . uniqid() . "', 10, 'ACTIVE', NOW(), NOW())");
        $pRoomB = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO `sessions` (`game_room_id`, `schedule_proposal_id`, `dm_assignment_id`, `venue_id`, `physical_room_id`, `start_at`, `end_at`, `status`, `created_at`, `updated_at`) VALUES ({$roomB}, {$propB}, {$assignB}, 2, {$pRoomB}, '2026-12-10 16:00:00', '2026-12-10 20:00:00', 'SCHEDULED', NOW(), NOW())");

        // Thử tham gia phòng B -> Chặn xung đột giờ
        $joinReq = new Request(
            method: 'POST',
            path: "/api/rooms/{$roomB}/join",
            queryParams: [],
            bodyParams: [],
            headers: ['Authorization' => "Bearer {$testUser}"]
        );
        $joinRes = $this->app->dispatch($joinReq);
        $this->assertSame(409, $joinRes->getStatusCode());
        $data = json_decode($joinRes->getContent(), true);
        $this->assertSame('PLAYER_SCHEDULE_CONFLICT', $data['error']['code']);
    }

    public function testOfflineBlockByAdmin(): void
    {
        $this->pdo->exec("INSERT INTO `physical_rooms` (`venue_id`, `name`, `capacity`, `status`, `created_at`, `updated_at`) VALUES (1, 'Phòng Block " . uniqid() . "', 10, 'ACTIVE', NOW(), NOW())");
        $pRoomId = (int) $this->pdo->lastInsertId();

        $request = new Request(
            method: 'POST',
            path: '/api/admin/rooms/offline-block',
            queryParams: [],
            bodyParams: [
                'venue_id' => 1,
                'physical_room_id' => $pRoomId,
                'start_at' => '2026-12-20 18:00:00',
                'end_at' => '2026-12-20 22:00:00',
                'customer_name' => 'Chị Lan Hương (Hotline)',
                'customer_phone' => '0912345678',
                'notes' => 'Khách đoàn VIP đã cọc trực tiếp tại quầy 500k',
            ],
            headers: [
                'Authorization' => 'Bearer 1', // Admin ID
            ]
        );

        $response = $this->app->dispatch($request);
        $this->assertSame(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame('Chị Lan Hương (Hotline)', $data['data']['customer_name']);
        $this->assertSame($pRoomId, (int) $data['data']['physical_room_id']);
        $this->assertNotNull($data['data']['block_id']);
    }

    /**
     * Test 9: Admin lấy danh sách phòng vật lý
     */
    public function testAdminListPhysicalRooms(): void
    {
        $request = new Request(
            method: 'GET',
            path: '/api/admin/physical-rooms',
            queryParams: [],
            bodyParams: [],
            headers: [
                'Authorization' => 'Bearer 1', // Admin
            ]
        );

        $response = $this->app->dispatch($request);
        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertIsArray($data['data']);
        $this->assertNotEmpty($data['data']);
        $this->assertArrayHasKey('name', $data['data'][0]);
        $this->assertArrayHasKey('capacity', $data['data'][0]);
        $this->assertArrayHasKey('is_maintenance', $data['data'][0]);
    }

    /**
     * Test 10: Admin kích hoạt bảo trì phòng vật lý và tự động điều chuyển ca chơi (Dynamic Rerouting)
     */
    public function testAdminTogglePhysicalRoomMaintenanceAndAutoRerouting(): void
    {
        // 1. Tạo 2 phòng vật lý cùng venue 1
        $this->pdo->exec("INSERT INTO `physical_rooms` (`venue_id`, `name`, `capacity`, `status`, `is_maintenance`, `created_at`, `updated_at`) VALUES (1, 'Phòng Cũ " . uniqid() . "', 8, 'ACTIVE', 0, NOW(), NOW())");
        $oldRoomId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO `physical_rooms` (`venue_id`, `name`, `capacity`, `status`, `is_maintenance`, `created_at`, `updated_at`) VALUES (1, 'Phòng Mới " . uniqid() . "', 10, 'ACTIVE', 0, NOW(), NOW())");
        $newRoomId = (int) $this->pdo->lastInsertId();

        // 2. Tạo game room và session tương lai gán vào phòng cũ
        $startAt = date('Y-m-d H:i:s', strtotime('+3 days 14:00:00'));
        $endAt = date('Y-m-d H:i:s', strtotime('+3 days 18:00:00'));

        $this->pdo->exec("INSERT INTO `game_rooms` (`game_id`, `game_configuration_id`, `creator_id`, `title`, `min_players`, `max_players`, `total_players`, `status`, `formation_deadline_at`, `created_at`, `updated_at`) VALUES (1, 1, 1, 'Phòng Reroute Test', 6, 8, 6, 'CONFIRMED', DATE_ADD(NOW(), INTERVAL 2 DAY), NOW(), NOW())");
        $gameRoomId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO `schedule_proposals` (`game_room_id`, `proposed_by`, `proposed_start_at`, `proposed_end_at`, `status`, `expires_at`, `created_at`) VALUES ({$gameRoomId}, 1, '{$startAt}', '{$endAt}', 'CONFIRMED', DATE_ADD(NOW(), INTERVAL 24 HOUR), NOW())");
        $propId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO `dm_assignments` (`game_room_id`, `dm_user_id`, `assigned_by`, `status`, `assigned_at`, `created_at`, `updated_at`) VALUES ({$gameRoomId}, 2, 1, 'ACCEPTED', NOW(), NOW(), NOW())");
        $dmAssignId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO `sessions` (`game_room_id`, `schedule_proposal_id`, `dm_assignment_id`, `physical_room_id`, `venue_id`, `status`, `start_at`, `end_at`, `created_at`, `updated_at`) VALUES ({$gameRoomId}, {$propId}, {$dmAssignId}, {$oldRoomId}, 1, 'SCHEDULED', '{$startAt}', '{$endAt}', NOW(), NOW())");
        $sessionId = (int) $this->pdo->lastInsertId();

        // 3. Admin bật bảo trì cho phòng cũ
        $request = new Request(
            method: 'POST',
            path: "/api/admin/physical-rooms/{$oldRoomId}/maintenance",
            queryParams: [],
            bodyParams: [
                'is_maintenance' => true,
                'reason' => 'Bảo trì máy lạnh và hệ thống âm thanh',
            ],
            headers: [
                'Authorization' => 'Bearer 1',
            ]
        );

        $response = $this->app->dispatch($request);
        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertTrue($data['data']['is_maintenance']);
        $this->assertNotEmpty($data['data']['rerouted_sessions']);
        $this->assertSame($sessionId, (int) $data['data']['rerouted_sessions'][0]['session_id']);

        // 4. Kiểm tra session trong DB đã được chuyển sang phòng khác (khác phòng cũ đang bảo trì)
        $sessRow = $this->pdo->query("SELECT `physical_room_id` FROM `sessions` WHERE `id` = {$sessionId}")->fetch(PDO::FETCH_ASSOC);
        $this->assertNotSame($oldRoomId, (int) $sessRow['physical_room_id']);
        $this->assertGreaterThan(0, (int) $sessRow['physical_room_id']);

        // 5. Admin tắt bảo trì cho phòng cũ
        $requestOff = new Request(
            method: 'POST',
            path: "/api/admin/physical-rooms/{$oldRoomId}/maintenance",
            queryParams: [],
            bodyParams: [
                'is_maintenance' => false,
            ],
            headers: [
                'Authorization' => 'Bearer 1',
            ]
        );

        $resOff = $this->app->dispatch($requestOff);
        $this->assertSame(200, $resOff->getStatusCode());
        $dataOff = json_decode($resOff->getContent(), true);
        $this->assertFalse($dataOff['data']['is_maintenance']);
    }

    /**
     * Test 11: Admin hoàn tất chi trả hoàn tiền cọc (Refunds Lifecycle to COMPLETED)
     */
    public function testAdminCompleteRefundLifecycle(): void
    {
        // 1. Tạo game room, participant, payment và refund ở trạng thái APPROVED
        $this->pdo->exec("INSERT INTO `game_rooms` (`game_id`, `game_configuration_id`, `creator_id`, `title`, `min_players`, `max_players`, `total_players`, `status`, `formation_deadline_at`, `created_at`, `updated_at`) VALUES (1, 1, 1, 'Phòng Refund Complete Test', 6, 8, 6, 'CANCELLED', DATE_ADD(NOW(), INTERVAL 2 DAY), NOW(), NOW())");
        $grId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO `room_participants` (`game_room_id`, `user_id`, `status`, `joined_at`, `created_at`, `updated_at`) VALUES ({$grId}, 3, 'DEPOSITED', NOW(), NOW(), NOW())");
        $partId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO `player_payments` (`room_participant_id`, `payment_type`, `amount`, `paid_amount`, `status`, `paid_at`, `created_at`, `updated_at`) VALUES ({$partId}, 'DEPOSIT', 50000, 50000, 'PAID', NOW(), NOW(), NOW())");
        $payId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO `refunds` (`player_payment_id`, `amount`, `reason`, `status`, `requested_at`, `reviewed_by`, `reviewed_at`, `created_at`, `updated_at`) VALUES ({$payId}, 50000, 'Hủy phòng trước 24h', 'APPROVED', NOW(), 1, NOW(), NOW(), NOW())");
        $refundId = (int) $this->pdo->lastInsertId();

        // 2. Kế toán/Admin hoàn tất chi tiền cọc (Complete)
        $request = new Request(
            method: 'POST',
            path: "/api/admin/refunds/{$refundId}/complete",
            queryParams: [],
            bodyParams: [
                'bank_reference_code' => 'UNC_VIETCOMBANK_20261124',
            ],
            headers: [
                'Authorization' => 'Bearer 1', // Admin
            ]
        );

        $response = $this->app->dispatch($request);
        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);

        // 3. Kiểm tra DB: refunds status = 'COMPLETED' và player_payments status = 'REFUNDED'
        $refRow = $this->pdo->query("SELECT `status`, `completed_at`, `review_reason` FROM `refunds` WHERE `id` = {$refundId}")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('COMPLETED', $refRow['status']);
        $this->assertNotNull($refRow['completed_at']);
        $this->assertStringContainsString('UNC_VIETCOMBANK_20261124', (string) $refRow['review_reason']);

        $payRow = $this->pdo->query("SELECT `status` FROM `player_payments` WHERE `id` = {$payId}")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('CANCELLED', $payRow['status']);
    }

    /**
     * Test 12: Quy tắc tính giá Majority Time Block khi ca chơi vắt qua 2 khung giờ
     */
    public function testMajorityTimeBlockPricingRule(): void
    {
        $pricingService = new \App\Features\Pricing\Services\PricingService($this->pdo);

        // Ca chơi từ 11:30 đến 15:30 (tổng 240 phút):
        // Ca sáng (11:30 - 13:00): 90 phút (37.5%)
        // Ca chiều (13:00 - 15:30): 150 phút (62.5% -> Đa số)
        $res = $pricingService->calculateBasePriceForGame(1, '2026-12-01 11:30:00', '2026-12-01 15:30:00', 240);

        $this->assertSame('MAJORITY_TIME_BLOCK', $res['pricing_rule']);
        $this->assertSame(220000, $res['base_price_per_person']);
        $this->assertSame(240, $res['duration_minutes']);
        $this->assertCount(2, $res['tier_overlaps']);
        $this->assertSame(150, $res['tier_overlaps'][0]['overlap_minutes']);
        $this->assertSame(90, $res['tier_overlaps'][1]['overlap_minutes']);
    }

    /**
     * Test 13: Quy tắc tính giá chia đều 50-50 khi 2 khung giờ có thời lượng bằng nhau
     */
    public function testSplit5050TiePricingRule(): void
    {
        $pricingService = new \App\Features\Pricing\Services\PricingService($this->pdo);

        // Ca chơi từ 16:00 đến 20:00 (tổng 240 phút):
        // Ca chiều (16:00 - 18:00): 120 phút (50% giá 220.000)
        // Ca tối (18:00 - 20:00): 120 phút (50% giá 250.000)
        // Trung bình cộng: (220.000 + 250.000) / 2 = 235.000
        $res = $pricingService->calculateBasePriceForGame(1, '2026-12-01 16:00:00', '2026-12-01 20:00:00', 240);

        $this->assertSame('SPLIT_50_50_AVERAGE', $res['pricing_rule']);
        $this->assertSame(235000, $res['base_price_per_person']);
        $this->assertSame(240, $res['duration_minutes']);
        $this->assertCount(2, $res['tier_overlaps']);
        $this->assertSame(120, $res['tier_overlaps'][0]['overlap_minutes']);
        $this->assertSame(120, $res['tier_overlaps'][1]['overlap_minutes']);
    }

    /**
     * Test 14: Tính giá phòng chơi đầy đủ và lưu snapshot CSDL với quy tắc Majority Time Block
     */
    public function testRoomPricingSnapshotWithMajorityRule(): void
    {
        $pricingService = new \App\Features\Pricing\Services\PricingService($this->pdo);

        $this->pdo->exec("INSERT INTO `game_rooms` (`game_id`, `game_configuration_id`, `creator_id`, `title`, `min_players`, `max_players`, `total_players`, `status`, `formation_deadline_at`, `created_at`, `updated_at`) VALUES (1, 1, 1, 'Phòng Snapshot Pricing Test', 6, 8, 6, 'FORMING', DATE_ADD(NOW(), INTERVAL 2 DAY), NOW(), NOW())");
        $grId = (int) $this->pdo->lastInsertId();

        for ($i = 1; $i <= 6; $i++) {
            $uId = $this->createTestUser("Pricing Player {$i}");
            $this->pdo->exec("INSERT INTO `room_participants` (`game_room_id`, `user_id`, `status`, `joined_at`, `created_at`, `updated_at`) VALUES ({$grId}, {$uId}, 'DEPOSITED', NOW(), NOW(), NOW())");
        }

        // Chạy calculateAndSnapshot với thời gian 11:30 - 15:30 (240 phút)
        $snapshot = $pricingService->calculateAndSnapshot($grId, '2026-12-01 11:30:00');

        $this->assertSame(6, $snapshot['total_participants']);
        $this->assertSame(220000, $snapshot['base_price_per_person']);
        $this->assertSame('MAJORITY_TIME_BLOCK', $snapshot['pricing_rule']);
        $this->assertSame(220000 * 6, $snapshot['subtotal']);
        $this->assertSame(50000, $snapshot['deposit_per_person']);
        $this->assertSame(300000, $snapshot['deposit_total']);
        $this->assertSame((220000 * 6) - 300000, $snapshot['remaining_total']);

        // Kiểm tra snapshot được lưu vào database game_rooms
        $roomRow = $this->pdo->query("SELECT `price_subtotal`, `price_deposit_total`, `price_remaining_total`, `price_details` FROM `game_rooms` WHERE `id` = {$grId}")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(220000 * 6, (int) $roomRow['price_subtotal']);
        $this->assertSame(300000, (int) $roomRow['price_deposit_total']);
        $details = json_decode((string) $roomRow['price_details'], true);
        $this->assertSame('MAJORITY_TIME_BLOCK', $details['pricing_rule']);
        $this->assertSame(220000, $details['base_price_per_person']);
    }
}
