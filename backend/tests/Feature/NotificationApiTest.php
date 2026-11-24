<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Application;
use App\Core\Request;
use App\Features\Notification\Repositories\NotificationRepository;
use App\Infrastructure\Config\Config;
use App\Infrastructure\Database\DatabaseConnection;
use PHPUnit\Framework\TestCase;

class NotificationApiTest extends TestCase
{
    protected Application $app;
    protected NotificationRepository $notiRepo;

    protected function setUp(): void
    {
        parent::setUp();
        $basePath = dirname(__DIR__, 2);
        Config::load($basePath);
        $this->app = new Application($basePath);
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SESSION = ['user_id' => 3];

        $pdo = DatabaseConnection::getInstance();
        $this->notiRepo = new NotificationRepository($pdo);

        $routesFile = $basePath . '/routes/api.php';
        $router = $this->app->getRouter();
        require $routesFile;
    }

    public function testCanListNotificationsAndMarkAsReadWithIDORGuard(): void
    {
        // 1. Tạo 2 thông báo cho User 3
        $n1 = $this->notiRepo->createNotification('Phòng chơi mới', 'Phòng #1 đang mở tuyển thành viên', 'ROOM', null, [3]);
        $n2 = $this->notiRepo->createNotification('Lịch chơi', 'Đã có đề xuất lịch mới trong phòng bạn tham gia', 'SCHEDULE', null, [3]);

        // Tạo 1 thông báo cho User 4
        $n4 = $this->notiRepo->createNotification('Riêng tư User 4', 'Thông báo bí mật', 'SYSTEM', null, [4]);

        // 2. User 3 lấy danh sách thông báo
        $listReq = new Request(
            method: 'GET',
            path: '/api/v1/notifications',
            queryParams: [],
            bodyParams: [],
            headers: [
                'Authorization' => 'Bearer 3',
            ]
        );
        $listRes = $this->app->dispatch($listReq);
        $this->assertSame(200, $listRes->getStatusCode());

        $listData = json_decode($listRes->getContent(), true);
        $this->assertTrue($listData['success']);
        $this->assertNotEmpty($listData['data']['notifications']);

        // Xác nhận thông báo của User 4 không xuất hiện trong danh sách của User 3
        foreach ($listData['data']['notifications'] as $item) {
            $this->assertSame(3, (int) $item['user_id']);
            $this->assertNotSame($n4, (int) $item['id']);
        }

        // 3. Chống IDOR: User 3 cố tình đánh dấu đã đọc thông báo của User 4 -> 404
        $idorReq = new Request(
            method: 'POST',
            path: "/api/v1/notifications/{$n4}/read",
            queryParams: [],
            bodyParams: [],
            headers: [
                'Authorization' => 'Bearer 3',
            ]
        );
        $idorRes = $this->app->dispatch($idorReq);
        $this->assertSame(404, $idorRes->getStatusCode());

        // 4. User 3 đánh dấu đã đọc thông báo n1 của chính mình -> 200
        $readReq = new Request(
            method: 'POST',
            path: "/api/v1/notifications/{$n1}/read",
            queryParams: [],
            bodyParams: [],
            headers: [
                'Authorization' => 'Bearer 3',
            ]
        );
        $readRes = $this->app->dispatch($readReq);
        $this->assertSame(200, $readRes->getStatusCode());
        $readData = json_decode($readRes->getContent(), true);
        $this->assertTrue($readData['success']);

        // 5. User 3 filter unread_only=1 -> n1 không xuất hiện
        $unreadReq = new Request(
            method: 'GET',
            path: '/api/v1/notifications',
            queryParams: [
                'unread_only' => 1,
            ],
            bodyParams: [],
            headers: [
                'Authorization' => 'Bearer 3',
            ]
        );
        $unreadRes = $this->app->dispatch($unreadReq);
        $this->assertSame(200, $unreadRes->getStatusCode());
        $unreadData = json_decode($unreadRes->getContent(), true);

        $unreadIds = array_column($unreadData['data']['notifications'], 'id');
        $this->assertNotContains($n1, $unreadIds);
    }
}
