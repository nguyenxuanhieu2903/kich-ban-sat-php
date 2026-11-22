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

        // Dọn dẹp dữ liệu test cũ để tránh va chạm xung đột lịch trình giữa các lần chạy
        $pdo->exec("UPDATE `sessions` SET `status` = 'CANCELLED' WHERE `game_room_id` IN (SELECT `id` FROM `game_rooms` WHERE `title` LIKE 'Phòng test%' OR `title` LIKE 'Phòng 1%' OR `title` LIKE 'Phòng 2%')");
        $pdo->exec("UPDATE `dm_assignments` SET `status` = 'REVOKED' WHERE `game_room_id` IN (SELECT `id` FROM `game_rooms` WHERE `title` LIKE 'Phòng test%' OR `title` LIKE 'Phòng 1%' OR `title` LIKE 'Phòng 2%')");
        $pdo->exec("UPDATE `game_rooms` SET `status` = 'CANCELLED' WHERE `title` LIKE 'Phòng test%' OR `title` LIKE 'Phòng 1%' OR `title` LIKE 'Phòng 2%'");

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

    public function testAdminCanAssignDMToRoomAndPreventsNonAdmin(): void
    {
        $pdo = DatabaseConnection::getInstance();
        $pdo->exec("INSERT INTO `game_rooms` (`game_id`, `game_configuration_id`, `creator_id`, `title`, `min_players`, `max_players`, `status`, `formation_deadline_at`, `created_at`, `updated_at`) VALUES (1, 1, 3, 'Phòng test admin assign DM', 6, 8, 'FORMING', DATE_ADD(NOW(), INTERVAL 3 DAY), NOW(), NOW())");
        $roomId = (int) $pdo->lastInsertId();

        // 1. Player (User 3) cố tình phân công DM -> 403
        $forbiddenReq = new Request(
            method: 'POST',
            path: "/api/v1/admin/rooms/{$roomId}/assign-dm",
            queryParams: [],
            bodyParams: [
                'dm_user_id' => 2,
                'notes' => 'Tự gán DM',
            ],
            headers: [
                'Authorization' => 'Bearer 3',
            ]
        );
        $forbiddenRes = $this->app->dispatch($forbiddenReq);
        $this->assertSame(403, $forbiddenRes->getStatusCode());

        // 2. Admin (User 1) phân công DM 2 -> 201
        $adminReq = new Request(
            method: 'POST',
            path: "/api/v1/admin/rooms/{$roomId}/assign-dm",
            queryParams: [],
            bodyParams: [
                'dm_user_id' => 2,
                'notes' => 'Quản trị viên phân công Host Hoàng Nam',
            ],
            headers: [
                'Authorization' => 'Bearer 1',
            ]
        );
        $adminRes = $this->app->dispatch($adminReq);
        $this->assertSame(201, $adminRes->getStatusCode());

        $data = json_decode($adminRes->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame('PENDING', $data['data']['assignment']['status']);
        $this->assertSame(2, (int) $data['data']['assignment']['dm_user_id']);

        // 3. Kiểm tra thông báo được gửi cho DM 2
        $notiStmt = $pdo->prepare("SELECT COUNT(*) FROM `notifications` WHERE `user_id` = 2 AND `type` = 'DM_ASSIGNMENT' AND `title` LIKE '%quản trò%'");
        $notiStmt->execute();
        $this->assertGreaterThan(0, (int) $notiStmt->fetchColumn());
    }

    public function testAdminCannotAssignInvalidDMOrToInvalidRoom(): void
    {
        // 1. Phân công cho phòng không tồn tại -> 404
        $notFoundReq = new Request(
            method: 'POST',
            path: '/api/v1/admin/rooms/999999/assign-dm',
            queryParams: [],
            bodyParams: ['dm_user_id' => 2],
            headers: ['Authorization' => 'Bearer 1']
        );
        $notFoundRes = $this->app->dispatch($notFoundReq);
        $this->assertSame(404, $notFoundRes->getStatusCode());

        // 2. Phân công một người chơi không phải DM (User 3 là PLAYER) -> 422
        $invalidDmReq = new Request(
            method: 'POST',
            path: '/api/v1/admin/rooms/1/assign-dm',
            queryParams: [],
            bodyParams: ['dm_user_id' => 3],
            headers: ['Authorization' => 'Bearer 1']
        );
        $invalidDmRes = $this->app->dispatch($invalidDmReq);
        $this->assertSame(422, $invalidDmRes->getStatusCode());
    }

    public function testReassignmentRevokesOldPendingAssignment(): void
    {
        $pdo = DatabaseConnection::getInstance();
        $pdo->exec("INSERT INTO `game_rooms` (`game_id`, `game_configuration_id`, `creator_id`, `title`, `min_players`, `max_players`, `status`, `formation_deadline_at`, `created_at`, `updated_at`) VALUES (1, 1, 3, 'Phòng test revoke old assignment', 6, 8, 'FORMING', DATE_ADD(NOW(), INTERVAL 3 DAY), NOW(), NOW())");
        $roomId = (int) $pdo->lastInsertId();

        // 1. Phân công DM 2 lần 1 (PENDING)
        $a1Id = $this->dmRepo->assignToRoom($roomId, 2, 'Lần 1', 1);

        $status1 = $pdo->query("SELECT `status` FROM `dm_assignments` WHERE `id` = {$a1Id}")->fetchColumn();
        $this->assertSame('PENDING', $status1);

        // 2. Admin phân công lại qua API
        $reassignReq = new Request(
            method: 'POST',
            path: "/api/v1/admin/rooms/{$roomId}/assign-dm",
            queryParams: [],
            bodyParams: [
                'dm_user_id' => 2,
                'notes' => 'Phân công lại lần 2',
            ],
            headers: [
                'Authorization' => 'Bearer 1',
            ]
        );
        $reassignRes = $this->app->dispatch($reassignReq);
        $this->assertSame(201, $reassignRes->getStatusCode());

        // 3. Assignment cũ (a1Id) phải tự động chuyển thành 'REVOKED'
        $oldStatus = $pdo->query("SELECT `status` FROM `dm_assignments` WHERE `id` = {$a1Id}")->fetchColumn();
        $this->assertSame('REVOKED', $oldStatus);

        $oldRevokedAt = $pdo->query("SELECT `revoked_at` FROM `dm_assignments` WHERE `id` = {$a1Id}")->fetchColumn();
        $this->assertNotNull($oldRevokedAt);
    }

    public function testDMRejectionNotifiesAdminAndEnsuresNoOrphanedSession(): void
    {
        $pdo = DatabaseConnection::getInstance();
        $pdo->exec("INSERT INTO `game_rooms` (`game_id`, `game_configuration_id`, `creator_id`, `title`, `min_players`, `max_players`, `status`, `formation_deadline_at`, `created_at`, `updated_at`) VALUES (1, 1, 3, 'Phòng test reject unlinks session', 6, 8, 'SCHEDULED', DATE_ADD(NOW(), INTERVAL 3 DAY), NOW(), NOW())");
        $roomId = (int) $pdo->lastInsertId();

        // Tạo proposal CONFIRMED
        $start = date('Y-m-d H:i:s', strtotime('+3 days 18:00:00'));
        $end = date('Y-m-d H:i:s', strtotime('+3 days 22:00:00'));
        $pdo->exec("INSERT INTO `schedule_proposals` (`game_room_id`, `proposed_by`, `proposed_start_at`, `proposed_end_at`, `status`, `expires_at`, `confirmed_at`, `created_at`) VALUES ({$roomId}, 3, '{$start}', '{$end}', 'CONFIRMED', DATE_ADD(NOW(), INTERVAL 24 HOUR), NOW(), NOW())");
        $propId = (int) $pdo->lastInsertId();

        // Tạo assignment PENDING cho DM 2
        $assignId = $this->dmRepo->assignToRoom($roomId, 2, 'Ca tối thứ 7', 1);

        // Tạo Session tạm gắn với ca này
        $pdo->exec("INSERT INTO `sessions` (`game_room_id`, `schedule_proposal_id`, `dm_assignment_id`, `venue_id`, `physical_room_id`, `start_at`, `end_at`, `status`, `created_at`, `updated_at`) VALUES ({$roomId}, {$propId}, {$assignId}, 1, 1, '{$start}', '{$end}', 'SCHEDULED', NOW(), NOW())");
        $sessId = (int) $pdo->lastInsertId();

        // DM 2 từ chối ca với lý do
        $rejectReq = new Request(
            method: 'POST',
            path: "/api/v1/dm/assignments/{$assignId}/reject",
            queryParams: [],
            bodyParams: [
                'reason' => 'Bận đột xuất việc gia đình không thể nhận ca',
            ],
            headers: [
                'Authorization' => 'Bearer 2',
            ]
        );
        $rejectRes = $this->app->dispatch($rejectReq);
        $this->assertSame(200, $rejectRes->getStatusCode());

        // Kiểm tra assignment là REJECTED
        $assignStatus = $pdo->query("SELECT `status` FROM `dm_assignments` WHERE `id` = {$assignId}")->fetchColumn();
        $this->assertSame('REJECTED', $assignStatus);

        // KHÔNG TỒN TẠI Session nào tham chiếu đến assignment đã bị từ chối
        $orphanedSessionCount = (int) $pdo->query("SELECT COUNT(*) FROM `sessions` WHERE `dm_assignment_id` = {$assignId}")->fetchColumn();
        $this->assertSame(0, $orphanedSessionCount, 'Không được tồn tại Session tham chiếu đến assignment đã bị từ chối');

        // Admin nhận được thông báo yêu cầu phân công lại
        $notiStmt = $pdo->query("SELECT COUNT(*) FROM `notifications` WHERE `type` = 'DM_REASSIGNMENT_REQUIRED' AND `title` LIKE '%từ chối%'");
        $this->assertGreaterThan(0, (int) $notiStmt->fetchColumn());

        // Admin thực hiện phân công lại DM qua endpoint reassignSession
        $reassignReq = new Request(
            method: 'POST',
            path: "/api/v1/admin/sessions/{$roomId}/reassign-dm",
            queryParams: [],
            bodyParams: [
                'dm_user_id' => 2,
                'notes' => 'Phân công lại DM sau khi giải quyết việc',
            ],
            headers: [
                'Authorization' => 'Bearer 1',
            ]
        );
        $reassignRes = $this->app->dispatch($reassignReq);
        $this->assertSame(201, $reassignRes->getStatusCode());
        $reassignData = json_decode($reassignRes->getContent(), true);
        $newAssignId = (int) $reassignData['data']['assignment']['id'];

        // DM mới accept -> Session được kết nối lại với assignment mới
        $acceptReq = new Request(
            method: 'POST',
            path: "/api/v1/dm/assignments/{$newAssignId}/accept",
            queryParams: [],
            bodyParams: [],
            headers: [
                'Authorization' => 'Bearer 2',
            ]
        );
        $acceptRes = $this->app->dispatch($acceptReq);
        $this->assertSame(200, $acceptRes->getStatusCode());

        // Session hiện tại liên kết với assignment mới
        $linkedAssignId = (int) $pdo->query("SELECT `dm_assignment_id` FROM `sessions` WHERE `game_room_id` = {$roomId}")->fetchColumn();
        $this->assertSame($newAssignId, $linkedAssignId);
    }

    public function testCannotAcceptAssignmentWithScheduleConflict(): void
    {
        $pdo = DatabaseConnection::getInstance();

        // 1. Tạo phòng 1 với Session đã ACCEPTED từ 18:00 đến 22:00
        $pdo->exec("INSERT INTO `game_rooms` (`game_id`, `game_configuration_id`, `creator_id`, `title`, `min_players`, `max_players`, `status`, `formation_deadline_at`, `created_at`, `updated_at`) VALUES (1, 1, 3, 'Phòng 1 đã có DM', 6, 8, 'SCHEDULED', DATE_ADD(NOW(), INTERVAL 3 DAY), NOW(), NOW())");
        $room1Id = (int) $pdo->lastInsertId();

        $start1 = date('Y-m-d H:i:s', strtotime('+5 days 18:00:00'));
        $end1 = date('Y-m-d H:i:s', strtotime('+5 days 22:00:00'));
        $pdo->exec("INSERT INTO `schedule_proposals` (`game_room_id`, `proposed_by`, `proposed_start_at`, `proposed_end_at`, `status`, `expires_at`, `confirmed_at`, `created_at`) VALUES ({$room1Id}, 3, '{$start1}', '{$end1}', 'CONFIRMED', DATE_ADD(NOW(), INTERVAL 24 HOUR), NOW(), NOW())");
        $prop1Id = (int) $pdo->lastInsertId();

        $pdo->exec("INSERT INTO `dm_assignments` (`game_room_id`, `dm_user_id`, `assigned_by`, `status`, `assigned_at`, `responded_at`, `created_at`, `updated_at`) VALUES ({$room1Id}, 2, 1, 'ACCEPTED', NOW(), NOW(), NOW(), NOW())");
        $assign1Id = (int) $pdo->lastInsertId();

        $pdo->exec("INSERT INTO `sessions` (`game_room_id`, `schedule_proposal_id`, `dm_assignment_id`, `venue_id`, `physical_room_id`, `start_at`, `end_at`, `status`, `created_at`, `updated_at`) VALUES ({$room1Id}, {$prop1Id}, {$assign1Id}, 1, 1, '{$start1}', '{$end1}', 'SCHEDULED', NOW(), NOW())");

        // 2. Tạo phòng 2 có khung giờ trùng lặp (19:00 đến 23:00)
        $pdo->exec("INSERT INTO `game_rooms` (`game_id`, `game_configuration_id`, `creator_id`, `title`, `min_players`, `max_players`, `status`, `formation_deadline_at`, `created_at`, `updated_at`) VALUES (1, 1, 3, 'Phòng 2 trùng lịch', 6, 8, 'SCHEDULED', DATE_ADD(NOW(), INTERVAL 3 DAY), NOW(), NOW())");
        $room2Id = (int) $pdo->lastInsertId();

        $start2 = date('Y-m-d H:i:s', strtotime('+5 days 19:00:00'));
        $end2 = date('Y-m-d H:i:s', strtotime('+5 days 23:00:00'));
        $pdo->exec("INSERT INTO `schedule_proposals` (`game_room_id`, `proposed_by`, `proposed_start_at`, `proposed_end_at`, `status`, `expires_at`, `confirmed_at`, `created_at`) VALUES ({$room2Id}, 3, '{$start2}', '{$end2}', 'CONFIRMED', DATE_ADD(NOW(), INTERVAL 24 HOUR), NOW(), NOW())");

        // Phân công DM 2 vào phòng 2
        $assign2Id = $this->dmRepo->assignToRoom($room2Id, 2, 'Ca trùng lịch', 1);

        // 3. DM 2 cố tình Accept ca trùng lịch -> 400 DM_SCHEDULE_CONFLICT
        $acceptConflictReq = new Request(
            method: 'POST',
            path: "/api/v1/dm/assignments/{$assign2Id}/accept",
            queryParams: [],
            bodyParams: [],
            headers: [
                'Authorization' => 'Bearer 2',
            ]
        );
        $acceptConflictRes = $this->app->dispatch($acceptConflictReq);
        $this->assertSame(400, $acceptConflictRes->getStatusCode());

        $conflictData = json_decode($acceptConflictRes->getContent(), true);
        $this->assertFalse($conflictData['success']);
        $this->assertSame('DM_SCHEDULE_CONFLICT', $conflictData['error']['code']);
    }

    public function testRoomCancellationByAdminRevokesActiveDMAssignmentAndNotifiesDM(): void
    {
        $pdo = DatabaseConnection::getInstance();
        $pdo->exec("INSERT INTO `game_rooms` (`game_id`, `game_configuration_id`, `creator_id`, `title`, `min_players`, `max_players`, `status`, `formation_deadline_at`, `created_at`, `updated_at`) VALUES (1, 1, 3, 'Phòng test cancel revokes DM', 6, 8, 'SCHEDULED', DATE_ADD(NOW(), INTERVAL 3 DAY), NOW(), NOW())");
        $roomId = (int) $pdo->lastInsertId();

        $assignId = $this->dmRepo->assignToRoom($roomId, 2, 'Phân công trước khi hủy', 1);

        // Admin hủy phòng
        $cancelReq = new Request(
            method: 'POST',
            path: "/api/v1/admin/rooms/{$roomId}/cancel",
            queryParams: [],
            bodyParams: [
                'reason' => 'Sự cố bảo trì cơ sở vật chất đột xuất',
            ],
            headers: [
                'Authorization' => 'Bearer 1',
            ]
        );
        $cancelRes = $this->app->dispatch($cancelReq);
        $this->assertSame(200, $cancelRes->getStatusCode());

        // Kiểm tra assignment đã được REVOKED
        $status = $pdo->query("SELECT `status` FROM `dm_assignments` WHERE `id` = {$assignId}")->fetchColumn();
        $this->assertSame('REVOKED', $status);

        // DM nhận được thông báo
        $notiStmt = $pdo->prepare("SELECT COUNT(*) FROM `notifications` WHERE `user_id` = 2 AND `title` LIKE '%hủy%'");
        $notiStmt->execute();
        $this->assertGreaterThan(0, (int) $notiStmt->fetchColumn());
    }
}
