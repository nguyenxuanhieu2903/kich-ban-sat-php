<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Application;
use App\Core\Request;
use App\Features\PaymentRefund\Repositories\PaymentRepository;
use App\Infrastructure\Config\Config;
use App\Infrastructure\Database\DatabaseConnection;
use PHPUnit\Framework\TestCase;

class AdminRoomCancelRefundApiTest extends TestCase
{
    protected Application $app;
    protected PaymentRepository $paymentRepo;

    protected function setUp(): void
    {
        parent::setUp();
        $basePath = dirname(__DIR__, 2);
        Config::load($basePath);
        $this->app = new Application($basePath);
        $_SESSION = [];

        $pdo = DatabaseConnection::getInstance();
        $this->paymentRepo = new PaymentRepository($pdo);

        $routesFile = $basePath . '/routes/api.php';
        $router = $this->app->getRouter();
        require $routesFile;
    }

    public function testNonAdminCannotCancelRoom(): void
    {
        $req = new Request('POST', '/api/v1/admin/rooms/1/cancel', [], ['reason' => 'Bận đột xuất'], ['Authorization' => 'Bearer 3']);
        $res = $this->app->dispatch($req);
        $this->assertSame(403, $res->getStatusCode());
    }

    public function testCancelRoomRequiresReason(): void
    {
        $req = new Request('POST', '/api/v1/admin/rooms/1/cancel', [], ['reason' => '   '], ['Authorization' => 'Bearer 1']);
        $res = $this->app->dispatch($req);
        $this->assertSame(422, $res->getStatusCode());
    }

    public function testCancelRoomEligibleForRefundWhenMoreThan24Hours(): void
    {
        $pdo = DatabaseConnection::getInstance();

        // 1. Tạo phòng test
        $pdo->exec("INSERT INTO `game_rooms` (`game_id`, `game_configuration_id`, `creator_id`, `title`, `min_players`, `max_players`, `status`, `formation_deadline_at`, `created_at`, `updated_at`) VALUES (1, 1, 3, 'Phòng test hoàn cọc trước 24h', 6, 8, 'SCHEDULED', DATE_ADD(NOW(), INTERVAL 3 DAY), NOW(), NOW())");
        $roomId = (int) $pdo->lastInsertId();

        // 2. Tạo participant và payment cho User 3 (đã nạp 50k)
        $pdo->exec("INSERT INTO `room_participants` (`game_room_id`, `user_id`, `status`, `joined_at`, `created_at`, `updated_at`) VALUES ({$roomId}, 3, 'DEPOSITED', NOW(), NOW(), NOW())");
        $partId = (int) $pdo->lastInsertId();

        $payId = $this->paymentRepo->createPayment([
            'room_participant_id' => $partId,
            'payment_type' => 'DEPOSIT',
            'amount' => 50000,
            'paid_amount' => 50000,
            'status' => 'PAID',
        ]);

        // 3. Tạo Proposal và Session bắt đầu sau 48 tiếng (> 24h)
        $startAt = date('Y-m-d H:i:s', strtotime('+48 hours'));
        $endAt = date('Y-m-d H:i:s', strtotime('+52 hours'));

        $pdo->exec("INSERT INTO `schedule_proposals` (`game_room_id`, `proposed_by`, `proposed_start_at`, `proposed_end_at`, `status`, `expires_at`, `created_at`) VALUES ({$roomId}, 3, '{$startAt}', '{$endAt}', 'CONFIRMED', DATE_ADD(NOW(), INTERVAL 24 HOUR), NOW())");
        $propId = (int) $pdo->lastInsertId();

        $pdo->exec("INSERT INTO `dm_assignments` (`game_room_id`, `dm_user_id`, `assigned_by`, `status`, `assigned_at`, `created_at`, `updated_at`) VALUES ({$roomId}, 2, 1, 'ACCEPTED', NOW(), NOW(), NOW())");
        $dmAssignId = (int) $pdo->lastInsertId();

        $pdo->exec("INSERT INTO `sessions` (`game_room_id`, `schedule_proposal_id`, `dm_assignment_id`, `venue_id`, `physical_room_id`, `start_at`, `end_at`, `status`, `created_at`, `updated_at`) VALUES ({$roomId}, {$propId}, {$dmAssignId}, 1, 1, '{$startAt}', '{$endAt}', 'SCHEDULED', NOW(), NOW())");

        // 4. Admin hủy phòng
        $cancelReq = new Request(
            method: 'POST',
            path: "/api/v1/admin/rooms/{$roomId}/cancel",
            queryParams: [],
            bodyParams: [
                'reason' => 'Bảo trì chi nhánh đột xuất',
            ],
            headers: [
                'Authorization' => 'Bearer 1',
            ]
        );

        $cancelRes = $this->app->dispatch($cancelReq);
        $this->assertSame(200, $cancelRes->getStatusCode());

        $cancelData = json_decode($cancelRes->getContent(), true);
        $this->assertTrue($cancelData['success']);
        $this->assertTrue($cancelData['data']['is_eligible_for_refund']);
        $this->assertNotEmpty($cancelData['data']['refunds']);
        $this->assertSame('APPROVED', $cancelData['data']['refunds'][0]['status']);
    }

    public function testCancelRoomNotEligibleForRefundWhenLessThan24Hours(): void
    {
        $pdo = DatabaseConnection::getInstance();

        // 1. Tạo phòng test
        $pdo->exec("INSERT INTO `game_rooms` (`game_id`, `game_configuration_id`, `creator_id`, `title`, `min_players`, `max_players`, `status`, `formation_deadline_at`, `created_at`, `updated_at`) VALUES (1, 1, 3, 'Phòng test không hoàn cọc dưới 24h', 6, 8, 'SCHEDULED', DATE_ADD(NOW(), INTERVAL 3 DAY), NOW(), NOW())");
        $roomId = (int) $pdo->lastInsertId();

        // 2. Tạo participant và payment cho User 3 (đã nạp 50k)
        $pdo->exec("INSERT INTO `room_participants` (`game_room_id`, `user_id`, `status`, `joined_at`, `created_at`, `updated_at`) VALUES ({$roomId}, 3, 'DEPOSITED', NOW(), NOW(), NOW())");
        $partId = (int) $pdo->lastInsertId();

        $payId = $this->paymentRepo->createPayment([
            'room_participant_id' => $partId,
            'payment_type' => 'DEPOSIT',
            'amount' => 50000,
            'paid_amount' => 50000,
            'status' => 'PAID',
        ]);

        // 3. Tạo Proposal và Session bắt đầu sau 6 tiếng (< 24h)
        $startAt = date('Y-m-d H:i:s', strtotime('+6 hours'));
        $endAt = date('Y-m-d H:i:s', strtotime('+10 hours'));

        $pdo->exec("INSERT INTO `schedule_proposals` (`game_room_id`, `proposed_by`, `proposed_start_at`, `proposed_end_at`, `status`, `expires_at`, `created_at`) VALUES ({$roomId}, 3, '{$startAt}', '{$endAt}', 'CONFIRMED', DATE_ADD(NOW(), INTERVAL 24 HOUR), NOW())");
        $propId = (int) $pdo->lastInsertId();

        $pdo->exec("INSERT INTO `dm_assignments` (`game_room_id`, `dm_user_id`, `assigned_by`, `status`, `assigned_at`, `created_at`, `updated_at`) VALUES ({$roomId}, 2, 1, 'ACCEPTED', NOW(), NOW(), NOW())");
        $dmAssignId = (int) $pdo->lastInsertId();

        $pdo->exec("INSERT INTO `sessions` (`game_room_id`, `schedule_proposal_id`, `dm_assignment_id`, `venue_id`, `physical_room_id`, `start_at`, `end_at`, `status`, `created_at`, `updated_at`) VALUES ({$roomId}, {$propId}, {$dmAssignId}, 1, 1, '{$startAt}', '{$endAt}', 'SCHEDULED', NOW(), NOW())");

        // 4. Admin hủy phòng
        $cancelReq = new Request(
            method: 'POST',
            path: "/api/v1/admin/rooms/{$roomId}/cancel",
            queryParams: [],
            bodyParams: [
                'reason' => 'Hủy do sự cố thời tiết cận giờ chơi',
            ],
            headers: [
                'Authorization' => 'Bearer 1',
            ]
        );

        $cancelRes = $this->app->dispatch($cancelReq);
        $this->assertSame(200, $cancelRes->getStatusCode());

        $cancelData = json_decode($cancelRes->getContent(), true);
        $this->assertTrue($cancelData['success']);
        // Vi phạm 24h: không đủ điều kiện
        $this->assertFalse($cancelData['data']['is_eligible_for_refund']);
        $this->assertSame('REJECTED', $cancelData['data']['refunds'][0]['status']);
    }

    public function testAdminCanListAndReviewRefunds(): void
    {
        // 1. List refunds
        $listReq = new Request('GET', '/api/v1/admin/refunds', ['page' => 1, 'per_page' => 5], [], ['Authorization' => 'Bearer 1']);
        $listRes = $this->app->dispatch($listReq);
        $this->assertSame(200, $listRes->getStatusCode());

        $listData = json_decode($listRes->getContent(), true);
        $this->assertTrue($listData['success']);
        $this->assertNotEmpty($listData['data']);

        $paymentId = (int) $listData['data'][0]['player_payment_id'];
        $pdo = DatabaseConnection::getInstance();

        // 2. Tạo 1 yêu cầu PENDING -> Admin reject thành công (200), sau đó cố approve đè bị chặn (409)
        $pdo->exec("INSERT INTO `refunds` (`player_payment_id`, `amount`, `reason`, `status`, `requested_at`, `created_at`, `updated_at`) VALUES ({$paymentId}, 50000, 'Yêu cầu hoàn cọc test reject', 'PENDING', NOW(), NOW(), NOW())");
        $rejectTargetId = (int) $pdo->lastInsertId();

        $rejReq = new Request('POST', "/api/v1/admin/refunds/{$rejectTargetId}/reject", [], ['reason' => 'Từ chối giải ngân'], ['Authorization' => 'Bearer 1']);
        $rejRes = $this->app->dispatch($rejReq);
        $this->assertSame(200, $rejRes->getStatusCode());

        $reApproveReq = new Request('POST', "/api/v1/admin/refunds/{$rejectTargetId}/approve", [], [], ['Authorization' => 'Bearer 1']);
        $reApproveRes = $this->app->dispatch($reApproveReq);
        $this->assertSame(409, $reApproveRes->getStatusCode());

        // 3. Tạo 1 yêu cầu PENDING khác -> Admin approve thành công (200), sau đó cố reject đè bị chặn (409)
        $pdo->exec("INSERT INTO `refunds` (`player_payment_id`, `amount`, `reason`, `status`, `requested_at`, `created_at`, `updated_at`) VALUES ({$paymentId}, 50000, 'Yêu cầu hoàn cọc test approve', 'PENDING', NOW(), NOW(), NOW())");
        $approveTargetId = (int) $pdo->lastInsertId();

        $appReq = new Request('POST', "/api/v1/admin/refunds/{$approveTargetId}/approve", [], [], ['Authorization' => 'Bearer 1']);
        $appRes = $this->app->dispatch($appReq);
        $this->assertSame(200, $appRes->getStatusCode());

        $reRejectReq = new Request('POST', "/api/v1/admin/refunds/{$approveTargetId}/reject", [], ['reason' => 'Từ chối sau khi đã duyệt'], ['Authorization' => 'Bearer 1']);
        $reRejectRes = $this->app->dispatch($reRejectReq);
        $this->assertSame(409, $reRejectRes->getStatusCode());
    }
}
