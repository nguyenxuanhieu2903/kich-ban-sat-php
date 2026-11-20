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
        $start = date('Y-m-d H:i:s', strtotime('+2 days 18:00:00'));
        $end = date('Y-m-d H:i:s', strtotime('+2 days 22:00:00'));

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
}
