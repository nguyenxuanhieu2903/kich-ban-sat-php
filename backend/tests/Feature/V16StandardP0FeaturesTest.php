<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Application;
use App\Core\Request;
use App\Infrastructure\Config\Config;
use App\Infrastructure\Database\DatabaseConnection;
use PDO;
use PHPUnit\Framework\TestCase;

class V16StandardP0FeaturesTest extends TestCase
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

    public function testCreatePrivateRoomWithCustomCapacity(): void
    {
        $futureSlot = date('Y-m-d 14:00', strtotime('+5 days'));
        $request = new Request(
            method: 'POST',
            path: '/api/rooms',
            queryParams: [],
            bodyParams: [
                'game_id' => 1,
                'title' => 'Phòng riêng nhóm bạn thân',
                'booking_type' => 'PRIVATE',
                'total_players' => 6,
                'guest_slots' => 0,
                'target_venue_id' => 1,
                'target_time_slot' => $futureSlot,
                'is_18_plus_acknowledged' => 1,
            ],
            headers: [
                'Authorization' => 'Bearer 7',
            ]
        );

        $response = $this->app->dispatch($request);
        $this->assertSame(201, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame('PUBLIC', $data['data']['room']['booking_type']);
        $this->assertSame(6, (int) $data['data']['room']['total_players']);
        $this->assertSame(1, (int) $data['data']['room']['target_venue_id']);
        $this->assertSame($futureSlot, $data['data']['room']['target_time_slot']);
    }

    public function testAdminCanCreateOfflineRoomBlock(): void
    {
        $this->pdo->exec("DELETE FROM offline_room_blocks WHERE reason LIKE '%Khách đoàn công ty đặt trước qua hotline%'");

        $startAt = date('Y-m-d 09:00:00', strtotime('+40 days'));
        $endAt = date('Y-m-d 13:00:00', strtotime('+40 days'));

        $request = new Request(
            method: 'POST',
            path: '/api/admin/rooms/offline-block',
            queryParams: [],
            bodyParams: [
                'venue_id' => 1,
                'physical_room_id' => 2,
                'start_at' => $startAt,
                'end_at' => $endAt,
                'customer_name' => 'Anh Hoàng (Khách Hotline)',
                'customer_phone' => '0912345678',
                'reason' => 'Khách đoàn công ty đặt trước qua hotline',
                'notes' => 'Khách đoàn công ty đặt trước qua hotline',
            ],
            headers: [
                'Authorization' => 'Bearer 1', // Admin ID
            ]
        );

        $response = $this->app->dispatch($request);
        $this->assertSame(201, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertNotEmpty($data['data']['block_id']);

        // Gọi lại lần 2 cùng khung giờ -> Bị từ chối do xung đột (409)
        $conflictRes = $this->app->dispatch($request);
        $this->assertSame(409, $conflictRes->getStatusCode());
    }

    public function testHostTransferWhenHostLeavesFormingRoom(): void
    {
        // 1. Player 7 tạo phòng
        $createReq = new Request(
            method: 'POST',
            path: '/api/rooms',
            queryParams: [],
            bodyParams: [
                'game_id' => 1,
                'title' => 'Phòng test chuyển quyền chủ phòng',
            ],
            headers: ['Authorization' => 'Bearer 7']
        );
        $roomId = (int) json_decode($this->app->dispatch($createReq)->getContent(), true)['data']['room']['id'];

        // 2. Player 8 tham gia phòng
        $joinReq = new Request(
            method: 'POST',
            path: "/api/rooms/{$roomId}/join",
            queryParams: [],
            bodyParams: [],
            headers: ['Authorization' => 'Bearer 8']
        );
        $this->assertSame(200, $this->app->dispatch($joinReq)->getStatusCode());

        // 3. Player 7 (Host ban đầu) rời phòng
        $leaveReq = new Request(
            method: 'POST',
            path: "/api/rooms/{$roomId}/leave",
            queryParams: [],
            bodyParams: [],
            headers: ['Authorization' => 'Bearer 7']
        );
        $this->assertSame(200, $this->app->dispatch($leaveReq)->getStatusCode());

        // 4. Kiểm tra phòng: quyền Host (creator_id) đã được tự động chuyển cho Player 8
        $roomStmt = $this->pdo->prepare('SELECT `creator_id` FROM `game_rooms` WHERE `id` = :id');
        $roomStmt->bindValue(':id', $roomId, PDO::PARAM_INT);
        $roomStmt->execute();
        $newCreatorId = (int) $roomStmt->fetchColumn();

        $this->assertSame(8, $newCreatorId);
    }
}
