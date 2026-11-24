<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Application;
use App\Core\Request;
use App\Infrastructure\Config\Config;
use PHPUnit\Framework\TestCase;

class SchedulingApiTest extends TestCase
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
    }

    public function testCanListVenuesAndPhysicalRooms(): void
    {
        $request = new Request('GET', '/api/venues');
        $response = $this->app->dispatch($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertNotEmpty($data['data']['venues']);
        $this->assertNotEmpty($data['data']['venues'][0]['rooms']);
    }

    public function testCanProposeAndVoteSchedule(): void
    {
        // 1. Tạo phòng mới với User 3 (Nguyễn Xuân Hiếu)
        $_SESSION['user_id'] = 3;
        $createReq = new Request(
            method: 'POST',
            path: '/api/rooms',
            queryParams: [],
            bodyParams: [
                'game_id' => 1,
                'title' => 'Phòng test biểu quyết lịch',
            ],
            headers: [
                'Authorization' => 'Bearer 3',
            ]
        );
        $createRes = $this->app->dispatch($createReq);
        $this->assertSame(201, $createRes->getStatusCode());
        $createData = json_decode($createRes->getContent(), true);
        $roomId = (int) $createData['data']['room']['id'];

        // 2. User 4 (Trần Minh Anh) tham gia phòng
        $_SESSION['user_id'] = 4;
        $joinReq = new Request(
            method: 'POST',
            path: "/api/rooms/{$roomId}/join",
            queryParams: [],
            bodyParams: [],
            headers: [
                'Authorization' => 'Bearer 4',
            ]
        );
        $joinRes = $this->app->dispatch($joinReq);
        $this->assertSame(200, $joinRes->getStatusCode());

        // 3. User 3 tạo đề xuất lịch
        $start = date('Y-m-d H:i:s', strtotime('+2 days 18:00:00'));
        $end = date('Y-m-d H:i:s', strtotime('+2 days 22:00:00'));

        $_SESSION['user_id'] = 3;
        $propReq = new Request(
            method: 'POST',
            path: "/api/rooms/{$roomId}/proposals",
            queryParams: [],
            bodyParams: [
                'proposed_start_at' => $start,
                'proposed_end_at' => $end,
            ],
            headers: [
                'Authorization' => 'Bearer 3',
            ]
        );
        $propRes = $this->app->dispatch($propReq);
        $this->assertSame(201, $propRes->getStatusCode());
        $propData = json_decode($propRes->getContent(), true);
        $proposalId = (int) $propData['data']['proposal_id'];

        // 4. Xem chi tiết đề xuất lịch
        $showReq = new Request('GET', "/api/proposals/{$proposalId}");
        $showRes = $this->app->dispatch($showReq);
        $this->assertSame(200, $showRes->getStatusCode());
        $showData = json_decode($showRes->getContent(), true);
        $this->assertSame('PROPOSED', $showData['data']['proposal']['status']);
        $this->assertCount(1, $showData['data']['proposal']['votes']); // User 3 tự động vote ACCEPTED

        // 5. User 4 vote ACCEPTED -> Đủ 2/2 phiếu -> Tự động chốt Session!
        $_SESSION['user_id'] = 4;
        $voteReq = new Request(
            method: 'POST',
            path: "/api/proposals/{$proposalId}/vote",
            queryParams: [],
            bodyParams: [
                'response' => 'ACCEPTED',
            ],
            headers: [
                'Authorization' => 'Bearer 4',
            ]
        );
        $voteRes = $this->app->dispatch($voteReq);
        $this->assertSame(200, $voteRes->getStatusCode());
        $voteData = json_decode($voteRes->getContent(), true);
        $this->assertTrue($voteData['success']);
        $this->assertTrue($voteData['data']['proposal_confirmed']);
        $this->assertNotNull($voteData['data']['session_id']);

        // 6. DEF-01: Đồng bộ trạng thái game_rooms sang SCHEDULED
        $pdo = \App\Infrastructure\Database\DatabaseConnection::getInstance();
        $roomStatus = $pdo->query("SELECT `status` FROM `game_rooms` WHERE `id` = {$roomId}")->fetchColumn();
        $this->assertSame('SCHEDULED', $roomStatus);

        // 7. DEF-01: Ngăn người ngoài tiếp tục Join GameRoom đã chốt lịch
        $_SESSION['user_id'] = 1;
        $joinAfterScheduled = new Request(
            method: 'POST',
            path: "/api/rooms/{$roomId}/join",
            queryParams: [],
            bodyParams: [],
            headers: [
                'Authorization' => 'Bearer 1',
            ]
        );
        $resAfterScheduled = $this->app->dispatch($joinAfterScheduled);
        $this->assertSame(400, $resAfterScheduled->getStatusCode());
        $bodyAfterScheduled = json_decode($resAfterScheduled->getContent(), true);
        $this->assertSame('ROOM_NOT_FORMING', $bodyAfterScheduled['error']['code']);
    }

    /**
     * DEF-25: Xác minh phòng chốt lịch khi DM đang ở trạng thái PENDING
     * Vẫn tạo Session liên kết thành công (không để mồ côi) và KHÔNG tự động chuyển DM sang ACCEPTED.
     */
    public function testDEF25ScheduleConfirmationWithPendingDMAssignmentCreatesSessionAndKeepsDMPending(): void
    {
        // 1. Tạo phòng mới với User 3
        $_SESSION['user_id'] = 3;
        $createReq = new Request(
            method: 'POST',
            path: '/api/v1/rooms',
            queryParams: [],
            bodyParams: [
                'game_id' => 1,
                'title' => 'Phòng test DEF-25 pending DM',
            ],
            headers: [
                'Authorization' => 'Bearer 3',
            ]
        );
        $createRes = $this->app->dispatch($createReq);
        $this->assertSame(201, $createRes->getStatusCode());
        $roomId = (int) json_decode($createRes->getContent(), true)['data']['room']['id'];

        // 2. User 4 tham gia phòng
        $_SESSION['user_id'] = 4;
        $joinReq = new Request('POST', "/api/v1/rooms/{$roomId}/join", [], [], ['Authorization' => 'Bearer 4']);
        $joinRes = $this->app->dispatch($joinReq);
        $this->assertSame(200, $joinRes->getStatusCode());

        // 3. Admin (User 1) phân công DM 2 -> assignment ở trạng thái PENDING
        $_SESSION['user_id'] = 1;
        $assignReq = new Request(
            method: 'POST',
            path: "/api/v1/admin/rooms/{$roomId}/assign-dm",
            queryParams: [],
            bodyParams: ['dm_user_id' => 2, 'notes' => 'Admin phân công trước khi chốt lịch'],
            headers: ['Authorization' => 'Bearer 1']
        );
        $assignRes = $this->app->dispatch($assignReq);
        $this->assertSame(201, $assignRes->getStatusCode());
        $assignId = (int) json_decode($assignRes->getContent(), true)['data']['assignment']['id'];

        // 4. User 3 tạo đề xuất lịch
        $start = date('Y-m-d H:i:s', strtotime('+3 days 14:00:00'));
        $end = date('Y-m-d H:i:s', strtotime('+3 days 18:00:00'));
        $_SESSION['user_id'] = 3;
        $propReq = new Request('POST', "/api/v1/rooms/{$roomId}/proposals", [], [
            'proposed_start_at' => $start,
            'proposed_end_at' => $end,
        ], ['Authorization' => 'Bearer 3']);
        $propRes = $this->app->dispatch($propReq);
        $this->assertSame(201, $propRes->getStatusCode());
        $propId = (int) json_decode($propRes->getContent(), true)['data']['proposal_id'];

        // 5. User 4 vote ACCEPTED -> 100% đồng ý
        $_SESSION['user_id'] = 4;
        $voteReq = new Request('POST', "/api/v1/proposals/{$propId}/vote", [], ['response' => 'ACCEPTED'], ['Authorization' => 'Bearer 4']);
        $voteRes = $this->app->dispatch($voteReq);
        $this->assertSame(200, $voteRes->getStatusCode());
        $voteData = json_decode($voteRes->getContent(), true);

        // Khẳng định 1: Proposal được confirm và session_id được tạo
        $this->assertTrue($voteData['success']);
        $this->assertTrue($voteData['data']['proposal_confirmed']);
        $this->assertNotNull($voteData['data']['session_id']);
        $sessionId = (int) $voteData['data']['session_id'];

        $pdo = \App\Infrastructure\Database\DatabaseConnection::getInstance();

        // Khẳng định 2: game_rooms.status chuyển sang SCHEDULED
        $roomStatus = $pdo->query("SELECT `status` FROM `game_rooms` WHERE `id` = {$roomId}")->fetchColumn();
        $this->assertSame('SCHEDULED', $roomStatus);

        // Khẳng định 3: Session tồn tại trong DB, liên kết đúng game_room_id và dm_assignment_id
        $sessRow = $pdo->query("SELECT `id`, `game_room_id`, `dm_assignment_id`, `status` FROM `sessions` WHERE `id` = {$sessionId}")->fetch(\PDO::FETCH_ASSOC);
        $this->assertNotEmpty($sessRow);
        $this->assertSame($roomId, (int) $sessRow['game_room_id']);
        $this->assertSame($assignId, (int) $sessRow['dm_assignment_id']);
        $this->assertSame('SCHEDULED', $sessRow['status']);

        // Khẳng định 4: DM assignment VẪN Ở TRẠNG THÁI PENDING (tuyệt đối không auto-accept)
        $dmStatus = $pdo->query("SELECT `status` FROM `dm_assignments` WHERE `id` = {$assignId}")->fetchColumn();
        $this->assertSame('PENDING', $dmStatus);
    }
}
