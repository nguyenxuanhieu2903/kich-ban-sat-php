<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Application;
use App\Core\Request;
use App\Infrastructure\Config\Config;
use PHPUnit\Framework\TestCase;

class GameRoomApiTest extends TestCase
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

    public function testCanListRooms(): void
    {
        $request = new Request('GET', '/api/rooms', [], [], ['Authorization' => 'Bearer 7']);
        $response = $this->app->dispatch($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertNotEmpty($data['data']['rooms']);
    }

    public function testCanGetRoomDetail(): void
    {
        $request = new Request('GET', '/api/rooms/4', [], [], ['Authorization' => 'Bearer 7']);
        $response = $this->app->dispatch($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertSame(4, (int) $data['data']['room']['id']);
        $this->assertNotEmpty($data['data']['room']['participants']);
    }

    public function testCanCreateRoomWhenLoggedIn(): void
    {
        $request = new Request(
            method: 'POST',
            path: '/api/rooms',
            queryParams: [],
            bodyParams: [
                'game_id' => 1,
                'title' => 'Phòng test tích hợp API V3',
                'description' => 'Mô tả phòng test',
                'min_players' => 6,
                'max_players' => 8,
            ],
            headers: [
                'Authorization' => 'Bearer 7', // Player ID
            ]
        );

        $response = $this->app->dispatch($request);
        $this->assertSame(201, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame('Phòng test tích hợp API V3', $data['data']['room']['title']);
        $this->assertSame('FORMING', $data['data']['room']['status']);
    }

    public function testCannotCreateRoomWhenNotLoggedIn(): void
    {
        $request = new Request(
            method: 'POST',
            path: '/api/rooms',
            queryParams: [],
            bodyParams: [
                'game_id' => 1,
                'title' => 'Phòng vô danh',
            ]
        );

        $response = $this->app->dispatch($request);
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testCanJoinAndLeaveRoom(): void
    {
        // Tạo phòng FORMING sạch với Player 7
        $createRes = $this->app->dispatch(new Request(
            method: 'POST',
            path: '/api/rooms',
            queryParams: [],
            bodyParams: ['game_id' => 1, 'title' => 'Phòng test Join & Leave'],
            headers: ['Authorization' => 'Bearer 7']
        ));
        $this->assertSame(201, $createRes->getStatusCode());
        $roomId = (int) json_decode($createRes->getContent(), true)['data']['room']['id'];

        // 1. Player 8 join phòng
        $joinReq = new Request(
            method: 'POST',
            path: "/api/rooms/{$roomId}/join",
            queryParams: [],
            bodyParams: [],
            headers: [
                'Authorization' => 'Bearer 8',
            ]
        );

        $joinRes = $this->app->dispatch($joinReq);
        $this->assertSame(200, $joinRes->getStatusCode());
        $joinData = json_decode($joinRes->getContent(), true);
        $this->assertTrue($joinData['success']);

        // 2. Player 8 cố tình join lại -> Báo lỗi 409
        $dupReq = new Request(
            method: 'POST',
            path: "/api/rooms/{$roomId}/join",
            queryParams: [],
            bodyParams: [],
            headers: [
                'Authorization' => 'Bearer 8',
            ]
        );
        $dupRes = $this->app->dispatch($dupReq);
        $this->assertSame(409, $dupRes->getStatusCode());

        // 3. Player 8 rời phòng -> Trả về 200
        $leaveReq = new Request(
            method: 'POST',
            path: "/api/rooms/{$roomId}/leave",
            queryParams: [],
            bodyParams: [],
            headers: [
                'Authorization' => 'Bearer 8',
            ]
        );
        $leaveRes = $this->app->dispatch($leaveReq);
        $this->assertSame(200, $leaveRes->getStatusCode());
        $leaveData = json_decode($leaveRes->getContent(), true);
        $this->assertTrue($leaveData['success']);
    }

    public function testReplacementSlotTakeoverWhenWindowIsOpen(): void
    {
        $pdo = \App\Infrastructure\Database\DatabaseConnection::getInstance();

        // 1. Tạo phòng mới với Player 7
        $createReq = new Request(
            method: 'POST',
            path: '/api/v1/rooms',
            queryParams: [],
            bodyParams: [
                'game_id' => 1,
                'title' => 'Phòng test Replacement Takeover',
            ],
            headers: [
                'Authorization' => 'Bearer 7',
            ]
        );
        $createRes = $this->app->dispatch($createReq);
        $this->assertSame(201, $createRes->getStatusCode());
        $createData = json_decode($createRes->getContent(), true);
        $roomId = (int) $createData['data']['room']['id'];

        // 2. Player 8 join vào phòng
        $join8 = new Request('POST', "/api/v1/rooms/{$roomId}/join", [], [], ['Authorization' => 'Bearer 8']);
        $res8 = $this->app->dispatch($join8);
        $this->assertSame(200, $res8->getStatusCode());

        // 3. Giả lập phòng đã chốt lịch (status = CONFIRMED)
        $pdo->exec("UPDATE `game_rooms` SET `status` = 'CONFIRMED' WHERE `id` = {$roomId}");

        // 4. Player 8 xin rút phòng -> Mở Replacement Window (OPEN)
        $leave8 = new Request('POST', "/api/v1/rooms/{$roomId}/leave", [], [], ['Authorization' => 'Bearer 8']);
        $leaveRes8 = $this->app->dispatch($leave8);
        $this->assertSame(200, $leaveRes8->getStatusCode());
        $leaveData8 = json_decode($leaveRes8->getContent(), true);
        $this->assertTrue($leaveData8['success']);
        $this->assertSame('OPEN', $leaveData8['data']['replacement_status']);

        // 5. Player 9 tham gia thế chỗ slot của Player 8
        $takeoverReq = new Request('POST', "/api/v1/rooms/{$roomId}/join", [], [], ['Authorization' => 'Bearer 9']);
        $takeoverRes = $this->app->dispatch($takeoverReq);
        $this->assertSame(200, $takeoverRes->getStatusCode());

        $takeoverData = json_decode($takeoverRes->getContent(), true);
        $this->assertTrue($takeoverData['success']);
        $this->assertTrue($takeoverData['data']['is_replacement']);
        $this->assertTrue($takeoverData['data']['replacement_window_closed']);

        // 6. Kiểm tra trạng thái trong DB:
        // Player 8 phải là 'LEFT'
        $u8Status = $pdo->query("SELECT `status` FROM `room_participants` WHERE `game_room_id` = {$roomId} AND `user_id` = 8")->fetchColumn();
        $this->assertSame('LEFT', $u8Status);

        // Player 9 phải là 'PENDING_DEPOSIT'
        $u9Status = $pdo->query("SELECT `status` FROM `room_participants` WHERE `game_room_id` = {$roomId} AND `user_id` = 9")->fetchColumn();
        $this->assertSame('PENDING_DEPOSIT', $u9Status);

        // game_rooms.replacement_status phải là 'CLOSED'
        $rwStatus = $pdo->query("SELECT `replacement_status` FROM `game_rooms` WHERE `id` = {$roomId}")->fetchColumn();
        $this->assertSame('CLOSED', $rwStatus);
    }
}
