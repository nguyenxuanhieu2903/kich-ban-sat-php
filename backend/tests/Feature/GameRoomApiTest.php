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

        $pdo = \App\Infrastructure\Database\DatabaseConnection::getInstance();
        $pdo->exec("UPDATE `game_rooms` SET `status` = 'CANCELLED' WHERE `title` LIKE 'Phòng test%' AND `status` = 'FORMING'");
        $pdo->exec("UPDATE `game_rooms` SET `status` = 'FORMING', `formation_deadline_at` = DATE_ADD(NOW(), INTERVAL 4 HOUR) WHERE `id` = 1");
        $pdo->exec("DELETE FROM `room_participants` WHERE `game_room_id` = 1 AND `user_id` != 3");
        $pdo->exec("UPDATE `room_participants` SET `status` = 'PENDING_DEPOSIT' WHERE `game_room_id` = 1 AND `user_id` = 3");
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

    public function testJoinConcurrencyEnforcesCapacityOnLastSlot(): void
    {
        $pdo = \App\Infrastructure\Database\DatabaseConnection::getInstance();

        // 1. User 3 tạo phòng với min_players = 2, max_players = 2
        $createReq = new Request(
            method: 'POST',
            path: '/api/v1/rooms',
            queryParams: [],
            bodyParams: [
                'game_id' => 1,
                'title' => 'Phòng test Join Capacity Concurrency',
                'min_players' => 2,
                'max_players' => 2,
            ],
            headers: [
                'Authorization' => 'Bearer 3',
            ]
        );
        $createRes = $this->app->dispatch($createReq);
        $this->assertSame(201, $createRes->getStatusCode());
        $roomId = (int) json_decode($createRes->getContent(), true)['data']['room']['id'];

        // 2. User 4 tham gia slot thứ 2 (slot cuối)
        $join4 = new Request('POST', "/api/v1/rooms/{$roomId}/join", [], [], ['Authorization' => 'Bearer 4']);
        $res4 = $this->app->dispatch($join4);
        $this->assertSame(200, $res4->getStatusCode());

        // 3. User 1 (Admin) cố tham gia khi phòng đã đầy (2/2) -> Báo lỗi ROOM_FULL 400
        $join1 = new Request('POST', "/api/v1/rooms/{$roomId}/join", [], [], ['Authorization' => 'Bearer 1']);
        $res1 = $this->app->dispatch($join1);
        $this->assertSame(400, $res1->getStatusCode());
        $data1 = json_decode($res1->getContent(), true);
        $this->assertSame('ROOM_FULL', $data1['error']['code']);

        // 4. Kiểm tra trong DB: chính xác 2 active participants, không có user 1
        $activeCount = (int) $pdo->query(
            "SELECT COUNT(*) FROM `room_participants` WHERE `game_room_id` = {$roomId} AND `status` IN ('PENDING_DEPOSIT', 'DEPOSITED', 'LEAVE_REQUESTED')"
        )->fetchColumn();
        $this->assertSame(2, $activeCount);

        $hasUser1 = $pdo->query(
            "SELECT COUNT(*) FROM `room_participants` WHERE `game_room_id` = {$roomId} AND `user_id` = 1"
        )->fetchColumn();
        $this->assertSame(0, (int) $hasUser1);
    }

    public function testCannotJoinRoomWhenStatusIsScheduled(): void
    {
        $pdo = \App\Infrastructure\Database\DatabaseConnection::getInstance();

        // 1. User 3 tạo phòng
        $createReq = new Request('POST', '/api/v1/rooms', [], [
            'game_id' => 1,
            'title' => 'Phòng test Scheduled Room Rejection',
        ], ['Authorization' => 'Bearer 3']);
        $createRes = $this->app->dispatch($createReq);
        $this->assertSame(201, $createRes->getStatusCode());
        $roomId = (int) json_decode($createRes->getContent(), true)['data']['room']['id'];

        // 2. Chuyển trạng thái phòng sang SCHEDULED
        $pdo->exec("UPDATE `game_rooms` SET `status` = 'SCHEDULED' WHERE `id` = {$roomId}");

        // 3. User 4 cố tham gia phòng đã SCHEDULED -> Báo lỗi ROOM_NOT_FORMING 400
        $joinReq = new Request('POST', "/api/v1/rooms/{$roomId}/join", [], [], ['Authorization' => 'Bearer 4']);
        $joinRes = $this->app->dispatch($joinReq);
        $this->assertSame(400, $joinRes->getStatusCode());
        $joinData = json_decode($joinRes->getContent(), true);
        $this->assertSame('ROOM_NOT_FORMING', $joinData['error']['code']);
    }

    public function testCreatorActiveRoomLimitCannotExceedThree(): void
    {
        $pdo = \App\Infrastructure\Database\DatabaseConnection::getInstance();
        // Hủy các phòng cũ của User 3
        $pdo->exec("UPDATE `game_rooms` SET `status` = 'CANCELLED' WHERE `creator_id` = 3 AND `status` = 'FORMING'");

        // Tạo 3 phòng thành công
        for ($i = 1; $i <= 3; $i++) {
            $req = new Request('POST', '/api/v1/rooms', [], [
                'game_id' => 1,
                'title' => "Phòng test Active Room Limit {$i}",
            ], ['Authorization' => 'Bearer 3']);
            $res = $this->app->dispatch($req);
            $this->assertSame(201, $res->getStatusCode(), "Room {$i} should be created successfully");
        }

        // Tạo phòng thứ 4 -> Báo lỗi CREATOR_ACTIVE_ROOM_LIMIT_EXCEEDED (400)
        $req4 = new Request('POST', '/api/v1/rooms', [], [
            'game_id' => 1,
            'title' => 'Phòng test Active Room Limit 4 (Should Fail)',
        ], ['Authorization' => 'Bearer 3']);
        $res4 = $this->app->dispatch($req4);
        $this->assertSame(400, $res4->getStatusCode());
        $data4 = json_decode($res4->getContent(), true);
        $this->assertSame('CREATOR_ACTIVE_ROOM_LIMIT_EXCEEDED', $data4['error']['code']);
    }

    public function testFormationTimeoutDefaultsToFourHours(): void
    {
        $pdo = \App\Infrastructure\Database\DatabaseConnection::getInstance();

        $createReq = new Request('POST', '/api/v1/rooms', [], [
            'game_id' => 1,
            'title' => 'Phòng test Default Formation Deadline 4 Hours',
        ], ['Authorization' => 'Bearer 3']);
        $createRes = $this->app->dispatch($createReq);
        $this->assertSame(201, $createRes->getStatusCode());
        $roomId = (int) json_decode($createRes->getContent(), true)['data']['room']['id'];

        $deadlineStr = $pdo->query("SELECT `formation_deadline_at` FROM `game_rooms` WHERE `id` = {$roomId}")->fetchColumn();
        $this->assertNotEmpty($deadlineStr);

        $deadlineTs = strtotime((string) $deadlineStr);
        $now = time();
        $diffHours = ($deadlineTs - $now) / 3600;

        // V16: Default là 4 tiếng (trong khoảng 3.9h đến 4.1h)
        $this->assertGreaterThan(3.9, $diffHours);
        $this->assertLessThan(4.1, $diffHours);
    }

    public function testCannotLeaveWhenSessionStartsInLessThanThirtyMinutes(): void
    {
        $pdo = \App\Infrastructure\Database\DatabaseConnection::getInstance();

        // 1. Tạo phòng với User 3
        $createReq = new Request('POST', '/api/v1/rooms', [], [
            'game_id' => 1,
            'title' => 'Phòng test Leave Within 30 Mins',
        ], ['Authorization' => 'Bearer 3']);
        $createRes = $this->app->dispatch($createReq);
        $this->assertSame(201, $createRes->getStatusCode());
        $roomId = (int) json_decode($createRes->getContent(), true)['data']['room']['id'];

        // 2. User 4 tham gia
        $join4 = new Request('POST', "/api/v1/rooms/{$roomId}/join", [], [], ['Authorization' => 'Bearer 4']);
        $this->assertSame(200, $this->app->dispatch($join4)->getStatusCode());

        // 3. Chuyển phòng sang SCHEDULED và thêm Session bắt đầu sau 20 phút
        $pdo->exec("UPDATE `game_rooms` SET `status` = 'SCHEDULED' WHERE `id` = {$roomId}");
        $sessionStart = date('Y-m-d H:i:s', time() + 20 * 60);
        $sessionEnd = date('Y-m-d H:i:s', time() + 140 * 60);
        $propStmt = $pdo->prepare("INSERT INTO `schedule_proposals` (`game_room_id`, `proposed_by`, `proposed_start_at`, `proposed_end_at`, `status`, `expires_at`, `created_at`) VALUES (:room_id, 3, :start_at, :end_at, 'CONFIRMED', DATE_ADD(NOW(), INTERVAL 24 HOUR), NOW())");
        $propStmt->execute([':room_id' => $roomId, ':start_at' => $sessionStart, ':end_at' => $sessionEnd]);
        $propId = (int) $pdo->lastInsertId();

        $pdo->exec("INSERT INTO `dm_assignments` (`game_room_id`, `dm_user_id`, `assigned_by`, `status`, `assigned_at`, `created_at`, `updated_at`) VALUES ({$roomId}, 2, 1, 'ACCEPTED', NOW(), NOW(), NOW())");
        $dmAssignId = (int) $pdo->lastInsertId();

        $pdo->exec("INSERT INTO `sessions` (`game_room_id`, `schedule_proposal_id`, `dm_assignment_id`, `venue_id`, `physical_room_id`, `start_at`, `end_at`, `status`, `created_at`, `updated_at`)
                    VALUES ({$roomId}, {$propId}, {$dmAssignId}, 1, 1, '{$sessionStart}', '{$sessionEnd}', 'SCHEDULED', NOW(), NOW())");

        // 4. User 4 xin rút phòng -> Bị từ chối vì cách giờ chơi < 30 phút
        $leaveReq = new Request('POST', "/api/v1/rooms/{$roomId}/leave", [], [], ['Authorization' => 'Bearer 4']);
        $leaveRes = $this->app->dispatch($leaveReq);
        $this->assertSame(400, $leaveRes->getStatusCode());
        $leaveData = json_decode($leaveRes->getContent(), true);
        $this->assertSame('REPLACEMENT_WINDOW_TOO_SHORT', $leaveData['error']['code']);
    }

    public function testReplacementDeadlineBoundedBySessionStart(): void
    {
        $pdo = \App\Infrastructure\Database\DatabaseConnection::getInstance();

        // 1. Tạo phòng với User 3
        $createReq = new Request('POST', '/api/v1/rooms', [], [
            'game_id' => 1,
            'title' => 'Phòng test Replacement Deadline Capped By Session',
        ], ['Authorization' => 'Bearer 3']);
        $createRes = $this->app->dispatch($createReq);
        $this->assertSame(201, $createRes->getStatusCode());
        $roomId = (int) json_decode($createRes->getContent(), true)['data']['room']['id'];

        // 2. User 4 tham gia
        $join4 = new Request('POST', "/api/v1/rooms/{$roomId}/join", [], [], ['Authorization' => 'Bearer 4']);
        $this->assertSame(200, $this->app->dispatch($join4)->getStatusCode());

        // 3. Chuyển phòng sang SCHEDULED và thêm Session bắt đầu sau 2 tiếng (< 4h max window)
        $pdo->exec("UPDATE `game_rooms` SET `status` = 'SCHEDULED' WHERE `id` = {$roomId}");
        $sessionStart = date('Y-m-d H:i:s', time() + 2 * 3600);
        $sessionEnd = date('Y-m-d H:i:s', time() + 4 * 3600);
        $propStmt = $pdo->prepare("INSERT INTO `schedule_proposals` (`game_room_id`, `proposed_by`, `proposed_start_at`, `proposed_end_at`, `status`, `expires_at`, `created_at`) VALUES (:room_id, 3, :start_at, :end_at, 'CONFIRMED', DATE_ADD(NOW(), INTERVAL 24 HOUR), NOW())");
        $propStmt->execute([':room_id' => $roomId, ':start_at' => $sessionStart, ':end_at' => $sessionEnd]);
        $propId = (int) $pdo->lastInsertId();

        $pdo->exec("INSERT INTO `dm_assignments` (`game_room_id`, `dm_user_id`, `assigned_by`, `status`, `assigned_at`, `created_at`, `updated_at`) VALUES ({$roomId}, 2, 1, 'ACCEPTED', NOW(), NOW(), NOW())");
        $dmAssignId = (int) $pdo->lastInsertId();

        $pdo->exec("INSERT INTO `sessions` (`game_room_id`, `schedule_proposal_id`, `dm_assignment_id`, `venue_id`, `physical_room_id`, `start_at`, `end_at`, `status`, `created_at`, `updated_at`)
                    VALUES ({$roomId}, {$propId}, {$dmAssignId}, 1, 1, '{$sessionStart}', '{$sessionEnd}', 'SCHEDULED', NOW(), NOW())");

        // 4. User 4 xin rút phòng -> Thành công, deadline capped ở sessionStart
        $leaveReq = new Request('POST', "/api/v1/rooms/{$roomId}/leave", [], [], ['Authorization' => 'Bearer 4']);
        $leaveRes = $this->app->dispatch($leaveReq);
        $this->assertSame(200, $leaveRes->getStatusCode());

        $repDeadline = $pdo->query("SELECT `replacement_deadline_at` FROM `game_rooms` WHERE `id` = {$roomId}")->fetchColumn();
        $this->assertSame(strtotime($sessionStart), strtotime((string) $repDeadline));
    }
}
