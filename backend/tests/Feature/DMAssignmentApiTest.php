<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Application;
use App\Core\Request;
use App\Features\DMManagement\Repositories\DMRepository;
use App\Infrastructure\Config\Config;
use App\Infrastructure\Database\DatabaseConnection;
use PHPUnit\Framework\TestCase;

class DMAssignmentApiTest extends TestCase
{
    protected Application $app;
    protected DMRepository $dmRepo;

    protected function setUp(): void
    {
        parent::setUp();
        $basePath = dirname(__DIR__, 2);
        Config::load($basePath);
        $this->app = new Application($basePath);
        $_SESSION = [];

        $pdo = DatabaseConnection::getInstance();
        $this->dmRepo = new DMRepository($pdo);

        $routesFile = $basePath . '/routes/api.php';
        $router = $this->app->getRouter();
        require $routesFile;
    }

    public function testPlayerCannotAccessDMAssignments(): void
    {
        // User 3 is PLAYER
        $request = new Request(
            method: 'GET',
            path: '/api/v1/dm/assignments',
            queryParams: [],
            bodyParams: [],
            headers: [
                'Authorization' => 'Bearer 3',
            ]
        );

        $response = $this->app->dispatch($request);
        $this->assertSame(403, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertSame('FORBIDDEN', $data['error']['code']);
    }

    public function testDMCanListTheirAssignments(): void
    {
        // User 2 is DM (Host Hoàng Nam)
        $request = new Request(
            method: 'GET',
            path: '/api/v1/dm/assignments',
            queryParams: [],
            bodyParams: [],
            headers: [
                'Authorization' => 'Bearer 2',
            ]
        );

        $response = $this->app->dispatch($request);
        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertIsArray($data['data']['assignments']);
    }

    public function testDMCanAcceptPendingAssignmentAndPreventsIDOR(): void
    {
        // 1. Tạo assignment PENDING cho DM User 2
        $assignmentId = $this->dmRepo->assignToRoom(1, 2, 'Cần DM có kinh nghiệm kịch bản kinh dị', 1);

        // 2. Thử accept bằng tài khoản khác không phải DM này và không phải Admin (giả sử tạo tạm DM giả hoặc user khác)
        // Set user 3 thành role DM tạm để test IDOR giữa 2 DM
        $pdo = DatabaseConnection::getInstance();
        $pdo->exec("UPDATE `users` SET `role_id` = (SELECT `id` FROM `roles` WHERE `code` = 'DM' LIMIT 1) WHERE `id` = 4");

        $idorReq = new Request(
            method: 'POST',
            path: "/api/v1/dm/assignments/{$assignmentId}/accept",
            queryParams: [],
            bodyParams: [],
            headers: [
                'Authorization' => 'Bearer 4', // DM khác
            ]
        );
        $idorRes = $this->app->dispatch($idorReq);
        $this->assertSame(403, $idorRes->getStatusCode());
        $idorData = json_decode($idorRes->getContent(), true);
        $this->assertFalse($idorData['success']);
        $this->assertSame('FORBIDDEN', $idorData['error']['code']);

        // 3. Đúng DM được phân công (User 2) accept
        $acceptReq = new Request(
            method: 'POST',
            path: "/api/v1/dm/assignments/{$assignmentId}/accept",
            queryParams: [],
            bodyParams: [],
            headers: [
                'Authorization' => 'Bearer 2',
            ]
        );
        $acceptRes = $this->app->dispatch($acceptReq);
        $this->assertSame(200, $acceptRes->getStatusCode());
        $acceptData = json_decode($acceptRes->getContent(), true);
        $this->assertTrue($acceptData['success']);
        $this->assertSame('ACCEPTED', $acceptData['data']['assignment']['status']);

        // 4. Accept lại lần nữa phải báo lỗi
        $repeatRes = $this->app->dispatch($acceptReq);
        $this->assertSame(400, $repeatRes->getStatusCode());

        // Revert user 4
        $pdo->exec("UPDATE `users` SET `role_id` = (SELECT `id` FROM `roles` WHERE `code` = 'PLAYER' LIMIT 1) WHERE `id` = 4");
    }

    public function testDMCanRejectPendingAssignmentWithReason(): void
    {
        // 1. Tạo assignment PENDING cho DM User 2
        $assignmentId = $this->dmRepo->assignToRoom(1, 2, 'Phân công ca tối', 1);

        // 2. Từ chối nhưng thiếu lý do -> 422
        $emptyReasonReq = new Request(
            method: 'POST',
            path: "/api/v1/dm/assignments/{$assignmentId}/reject",
            queryParams: [],
            bodyParams: [
                'reason' => '   ',
            ],
            headers: [
                'Authorization' => 'Bearer 2',
            ]
        );
        $emptyRes = $this->app->dispatch($emptyReasonReq);
        $this->assertSame(422, $emptyRes->getStatusCode());

        // 3. Từ chối có lý do hợp lệ -> 200
        $rejectReq = new Request(
            method: 'POST',
            path: "/api/v1/dm/assignments/{$assignmentId}/reject",
            queryParams: [],
            bodyParams: [
                'reason' => 'Tôi bận lịch cá nhân đột xuất vào khung giờ này',
            ],
            headers: [
                'Authorization' => 'Bearer 2',
            ]
        );
        $rejectRes = $this->app->dispatch($rejectReq);
        $this->assertSame(200, $rejectRes->getStatusCode());

        $rejectData = json_decode($rejectRes->getContent(), true);
        $this->assertTrue($rejectData['success']);
        $this->assertSame('REJECTED', $rejectData['data']['assignment']['status']);
        $this->assertSame('Tôi bận lịch cá nhân đột xuất vào khung giờ này', $rejectData['data']['assignment']['reason']);
    }
}
