<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Application;
use App\Core\Request;
use App\Infrastructure\Config\Config;
use App\Infrastructure\Database\DatabaseConnection;
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
    }

    protected function tearDown(): void
    {
        try {
            $pdo = DatabaseConnection::getInstance();
            $pdo->exec("DELETE FROM `sessions` WHERE `start_at` >= DATE_ADD(NOW(), INTERVAL 20 DAY)");
        } catch (\Throwable) {
        }
        parent::tearDown();
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
        $start = date('Y-m-d H:i:s', strtotime('+30 days 10:00:00'));
        $end = date('Y-m-d H:i:s', strtotime('+30 days 12:00:00'));

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
    }

    public function testAdminAndDmCanManageAndApproveProposals(): void
    {
        // 1. Tạo phòng mới với User 3 (Player)
        $createReq = new Request(
            method: 'POST',
            path: '/api/rooms',
            queryParams: [],
            bodyParams: [
                'game_id' => 1,
                'title' => 'Phòng test admin & dm duyệt đề xuất',
            ],
            headers: [
                'Authorization' => 'Bearer 3',
            ]
        );
        $createRes = $this->app->dispatch($createReq);
        $createData = json_decode($createRes->getContent(), true);
        $roomId = (int) $createData['data']['room']['id'];

        // 2. User 3 tạo đề xuất lịch
        $start = date('Y-m-d H:i:s', strtotime('+35 days 14:00:00'));
        $end = date('Y-m-d H:i:s', strtotime('+35 days 18:00:00'));

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
        $propData = json_decode($propRes->getContent(), true);
        $proposalId = (int) $propData['data']['proposal_id'];

        // 3. User thường (User 4) không có quyền duyệt đề xuất -> 403 Forbidden
        $unauthApprove = new Request(
            method: 'POST',
            path: "/api/admin/proposals/{$proposalId}/approve",
            queryParams: [],
            bodyParams: [],
            headers: [
                'Authorization' => 'Bearer 4',
            ]
        );
        $unauthRes = $this->app->dispatch($unauthApprove);
        $this->assertSame(403, $unauthRes->getStatusCode());

        // 4. Admin (User 1) lấy danh sách đề xuất đang chờ
        $listReq = new Request(
            method: 'GET',
            path: '/api/admin/proposals',
            queryParams: ['status' => 'PROPOSED'],
            bodyParams: [],
            headers: [
                'Authorization' => 'Bearer 1',
            ]
        );
        $listRes = $this->app->dispatch($listReq);
        $this->assertSame(200, $listRes->getStatusCode());
        $listData = json_decode($listRes->getContent(), true);
        $this->assertTrue($listData['success']);
        $this->assertNotEmpty($listData['data']['proposals']);

        // 5. Admin (User 1) duyệt đề xuất lịch
        $approveReq = new Request(
            method: 'POST',
            path: "/api/admin/proposals/{$proposalId}/approve",
            queryParams: [],
            bodyParams: [],
            headers: [
                'Authorization' => 'Bearer 1',
            ]
        );
        $approveRes = $this->app->dispatch($approveReq);
        $this->assertSame(200, $approveRes->getStatusCode());
        $approveData = json_decode($approveRes->getContent(), true);
        $this->assertTrue($approveData['success']);
        $this->assertSame('CONFIRMED', $approveData['data']['status']);
        $this->assertNotNull($approveData['data']['session_id']);

        // 6. Tạo đề xuất khác để kiểm tra DM từ chối
        $start2 = date('Y-m-d H:i:s', strtotime('+36 days 09:00:00'));
        $end2 = date('Y-m-d H:i:s', strtotime('+36 days 13:00:00'));
        $createReq2 = new Request(
            method: 'POST',
            path: '/api/rooms',
            queryParams: [],
            bodyParams: [
                'game_id' => 1,
                'title' => 'Phòng test DM từ chối đề xuất',
            ],
            headers: ['Authorization' => 'Bearer 3']
        );
        $roomId2 = (int) json_decode($this->app->dispatch($createReq2)->getContent(), true)['data']['room']['id'];

        $propReq2 = new Request(
            method: 'POST',
            path: "/api/rooms/{$roomId2}/proposals",
            queryParams: [],
            bodyParams: [
                'proposed_start_at' => $start2,
                'proposed_end_at' => $end2,
            ],
            headers: ['Authorization' => 'Bearer 3']
        );
        $propId2 = (int) json_decode($this->app->dispatch($propReq2)->getContent(), true)['data']['proposal_id'];

        // DM (User 2) từ chối đề xuất
        $rejectReq = new Request(
            method: 'POST',
            path: "/api/proposals/{$propId2}/reject",
            queryParams: [],
            bodyParams: ['reason' => 'Trùng lịch phòng và kịch bản'],
            headers: ['Authorization' => 'Bearer 2']
        );
        $rejectRes = $this->app->dispatch($rejectReq);
        $this->assertSame(200, $rejectRes->getStatusCode());
        $rejectData = json_decode($rejectRes->getContent(), true);
        $this->assertTrue($rejectData['success']);
        $this->assertSame('REJECTED', $rejectData['data']['status']);
    }
}
