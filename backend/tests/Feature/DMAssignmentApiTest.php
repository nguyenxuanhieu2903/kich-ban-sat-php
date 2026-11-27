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
        // Player 7 (nguoichoi01) is PLAYER
        $request = new Request(
            method: 'GET',
            path: '/api/v1/dm/assignments',
            queryParams: [],
            bodyParams: [],
            headers: [
                'Authorization' => 'Bearer 7',
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
        // User 3 is DM (nguyenphamyhuynh)
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
        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertIsArray($data['data']['assignments']);
    }

    public function testDMCanAcceptPendingAssignmentAndPreventsIDOR(): void
    {
        // 1. Tạo assignment PENDING cho DM User 2
        // 1. Tạo phòng FORMING mới và phân công cho DM thật (User 3)
        $pdo = DatabaseConnection::getInstance();
        $pdo->exec("INSERT INTO `game_rooms` (`game_id`, `game_configuration_id`, `creator_id`, `title`, `min_players`, `max_players`, `status`, `formation_deadline_at`, `created_at`, `updated_at`) VALUES (1, 1, 7, 'Phòng Test DM Accept IDOR', 4, 8, 'FORMING', DATE_ADD(NOW(), INTERVAL 3 DAY), NOW(), NOW())");
        $roomId = (int) $pdo->lastInsertId();

        $assignmentId = $this->dmRepo->assignToRoom($roomId, 3, 'Cần DM có kinh nghiệm kịch bản kinh dị', 1);

        // 2. Thử accept bằng DM khác (User 4) -> 403 FORBIDDEN (chống IDOR giữa 2 DM)
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

        // 3. Đúng DM được phân công (User 3) accept
        $acceptReq = new Request(
            method: 'POST',
            path: "/api/v1/dm/assignments/{$assignmentId}/accept",
            queryParams: [],
            bodyParams: [],
            headers: [
                'Authorization' => 'Bearer 3',
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
    }

    public function testDMCanRejectPendingAssignmentWithReason(): void
    {
        // 1. Tạo phòng mới và assignment PENDING cho DM User 3
        $pdo = DatabaseConnection::getInstance();
        $pdo->exec("INSERT INTO `game_rooms` (`game_id`, `game_configuration_id`, `creator_id`, `title`, `min_players`, `max_players`, `status`, `formation_deadline_at`, `created_at`, `updated_at`) VALUES (1, 1, 7, 'Phòng Test DM Reject', 4, 8, 'FORMING', DATE_ADD(NOW(), INTERVAL 3 DAY), NOW(), NOW())");
        $roomId = (int) $pdo->lastInsertId();
        $assignmentId = $this->dmRepo->assignToRoom($roomId, 3, 'Phân công ca tối', 1);

        // 2. Từ chối nhưng thiếu lý do -> 422
        $emptyReasonReq = new Request(
            method: 'POST',
            path: "/api/v1/dm/assignments/{$assignmentId}/reject",
            queryParams: [],
            bodyParams: [
                'reason' => '   ',
            ],
            headers: [
                'Authorization' => 'Bearer 3',
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
                'Authorization' => 'Bearer 3',
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
