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
        $request = new Request('GET', '/api/rooms');
        $response = $this->app->dispatch($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertNotEmpty($data['data']['rooms']);
    }

    public function testCanGetRoomDetail(): void
    {
        $request = new Request('GET', '/api/rooms/1');
        $response = $this->app->dispatch($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertSame(1, (int) $data['data']['room']['id']);
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
                'Authorization' => 'Bearer 1', // Admin ID
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
        // 1. User 4 (Trần Minh Anh) join Room 1
        $joinReq = new Request(
            method: 'POST',
            path: '/api/rooms/1/join',
            queryParams: [],
            bodyParams: [],
            headers: [
                'Authorization' => 'Bearer 4',
            ]
        );

        $joinRes = $this->app->dispatch($joinReq);
        // Có thể là 200 hoặc 409 nếu trước đó đã join
        if ($joinRes->getStatusCode() === 200) {
            $joinData = json_decode($joinRes->getContent(), true);
            $this->assertTrue($joinData['success']);
        } else {
            $this->assertSame(409, $joinRes->getStatusCode());
        }

        // 2. User 4 cố tình join lại -> Báo lỗi 409
        $dupReq = new Request(
            method: 'POST',
            path: '/api/rooms/1/join',
            queryParams: [],
            bodyParams: [],
            headers: [
                'Authorization' => 'Bearer 4',
            ]
        );
        $dupRes = $this->app->dispatch($dupReq);
        $this->assertSame(409, $dupRes->getStatusCode());

        // 3. User 4 rời phòng -> Trả về 200
        $leaveReq = new Request(
            method: 'POST',
            path: '/api/rooms/1/leave',
            queryParams: [],
            bodyParams: [],
            headers: [
                'Authorization' => 'Bearer 4',
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

        // 1. Tạo phòng mới với User 3
        $createReq = new Request(
            method: 'POST',
            path: '/api/v1/rooms',
            queryParams: [],
            bodyParams: [
                'game_id' => 1,
                'title' => 'Phòng test Replacement Takeover',
            ],
            headers: [
                'Authorization' => 'Bearer 3',
            ]
        );
        $createRes = $this->app->dispatch($createReq);
        $this->assertSame(201, $createRes->getStatusCode());
        $createData = json_decode($createRes->getContent(), true);
        $roomId = (int) $createData['data']['room']['id'];

        // 2. User 4 join vào phòng
        $join4 = new Request('POST', "/api/v1/rooms/{$roomId}/join", [], [], ['Authorization' => 'Bearer 4']);
        $res4 = $this->app->dispatch($join4);
        $this->assertSame(200, $res4->getStatusCode());

        // 3. Giả lập phòng đã chốt lịch (status = CONFIRMED)
        $pdo->exec("UPDATE `game_rooms` SET `status` = 'CONFIRMED' WHERE `id` = {$roomId}");

        // 4. User 4 xin rút phòng -> Mở Replacement Window (OPEN)
        $leave4 = new Request('POST', "/api/v1/rooms/{$roomId}/leave", [], [], ['Authorization' => 'Bearer 4']);
        $leaveRes4 = $this->app->dispatch($leave4);
        $this->assertSame(200, $leaveRes4->getStatusCode());
        $leaveData4 = json_decode($leaveRes4->getContent(), true);
        $this->assertTrue($leaveData4['success']);
        $this->assertSame('OPEN', $leaveData4['data']['replacement_status']);

        // 5. User 1 (Admin) tham gia thế chỗ slot của User 4
        $takeoverReq = new Request('POST', "/api/v1/rooms/{$roomId}/join", [], [], ['Authorization' => 'Bearer 1']);
        $takeoverRes = $this->app->dispatch($takeoverReq);
        $this->assertSame(200, $takeoverRes->getStatusCode());

        $takeoverData = json_decode($takeoverRes->getContent(), true);
        $this->assertTrue($takeoverData['success']);
        $this->assertTrue($takeoverData['data']['is_replacement']);
        $this->assertTrue($takeoverData['data']['replacement_window_closed']);

        // 6. Kiểm tra trạng thái trong DB:
        // User 4 phải là 'LEFT'
        $u4Status = $pdo->query("SELECT `status` FROM `room_participants` WHERE `game_room_id` = {$roomId} AND `user_id` = 4")->fetchColumn();
        $this->assertSame('LEFT', $u4Status);

        // User 1 phải là 'PENDING_DEPOSIT'
        $u1Status = $pdo->query("SELECT `status` FROM `room_participants` WHERE `game_room_id` = {$roomId} AND `user_id` = 1")->fetchColumn();
        $this->assertSame('PENDING_DEPOSIT', $u1Status);

        // game_rooms.replacement_status phải là 'CLOSED'
        $rwStatus = $pdo->query("SELECT `replacement_status` FROM `game_rooms` WHERE `id` = {$roomId}")->fetchColumn();
        $this->assertSame('CLOSED', $rwStatus);
    }
}
