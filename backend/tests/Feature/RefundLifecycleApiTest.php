<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Application;
use App\Core\Request;
use App\Features\GameroomManagement\Services\BackgroundJobService;
use App\Infrastructure\Config\Config;
use App\Infrastructure\Database\DatabaseConnection;
use PDO;
use PHPUnit\Framework\TestCase;

class RefundLifecycleApiTest extends TestCase
{
    protected Application $app;
    protected PDO $pdo;
    protected BackgroundJobService $jobService;

    protected function setUp(): void
    {
        parent::setUp();
        $basePath = dirname(__DIR__, 2);
        Config::load($basePath);
        $this->app = new Application($basePath);
        $_SESSION = ['user_id' => 3];

        $this->pdo = DatabaseConnection::getInstance();
        $this->jobService = new BackgroundJobService($this->pdo);

        $routesFile = $basePath . '/routes/api.php';
        $router = $this->app->getRouter();
        require $routesFile;

        // Clean up any test rooms with prefix 'REFUND_TEST_'
        $this->cleanupTestData();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestData();
        parent::tearDown();
    }

    private function cleanupTestData(): void
    {
        try {
            $stmt = $this->pdo->query("SELECT `id` FROM `game_rooms` WHERE `title` LIKE 'REFUND_TEST_%'");
            $roomIds = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
            if (!empty($roomIds)) {
                $in = implode(',', array_map('intval', $roomIds));
                $this->pdo->exec("DELETE r FROM `refunds` r INNER JOIN `player_payments` p ON p.`id` = r.`player_payment_id` INNER JOIN `room_participants` rp ON rp.`id` = p.`room_participant_id` WHERE rp.`game_room_id` IN ({$in})");
                $this->pdo->exec("DELETE tx FROM `payment_transactions` tx INNER JOIN `player_payments` p ON p.`id` = tx.`player_payment_id` INNER JOIN `room_participants` rp ON rp.`id` = p.`room_participant_id` WHERE rp.`game_room_id` IN ({$in})");
                $this->pdo->exec("DELETE p FROM `player_payments` p INNER JOIN `room_participants` rp ON rp.`id` = p.`room_participant_id` WHERE rp.`game_room_id` IN ({$in})");
                $this->pdo->exec("DELETE FROM `room_participants` WHERE `game_room_id` IN ({$in})");
                $this->pdo->exec("DELETE FROM `sessions` WHERE `game_room_id` IN ({$in})");
                $this->pdo->exec("DELETE FROM `dm_assignments` WHERE `game_room_id` IN ({$in})");
                $this->pdo->exec("DELETE FROM `schedule_proposals` WHERE `game_room_id` IN ({$in})");
                $this->pdo->exec("DELETE FROM `game_rooms` WHERE `id` IN ({$in})");
            }
        } catch (\Throwable) {
        }
    }

    /**
     * Helper to create a test session with proposal and DM assignment
     */
    private function createTestSession(int $roomId, string $startAt, string $endAt): int
    {
        $this->pdo->exec("INSERT INTO `schedule_proposals` (`game_room_id`, `proposed_by`, `proposed_start_at`, `proposed_end_at`, `status`, `expires_at`, `created_at`) VALUES ({$roomId}, 3, '{$startAt}', '{$endAt}', 'CONFIRMED', DATE_ADD(NOW(), INTERVAL 24 HOUR), NOW())");
        $propId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO `dm_assignments` (`game_room_id`, `dm_user_id`, `assigned_by`, `status`, `assigned_at`, `created_at`, `updated_at`) VALUES ({$roomId}, 2, 1, 'ACCEPTED', NOW(), NOW(), NOW())");
        $dmAssignId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO `sessions` (`game_room_id`, `schedule_proposal_id`, `dm_assignment_id`, `venue_id`, `physical_room_id`, `start_at`, `end_at`, `status`, `created_at`, `updated_at`) VALUES ({$roomId}, {$propId}, {$dmAssignId}, 1, 1, '{$startAt}', '{$endAt}', 'SCHEDULED', NOW(), NOW())");
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Helper to create a test room
     */
    private function createTestRoom(int $creatorId, string $status = 'FORMING', int $minPlayers = 2, int $maxPlayers = 4, ?string $formationDeadline = null): int
    {
        $deadline = $formationDeadline ?? date('Y-m-d H:i:s', time() + 86400);
        $title = 'REFUND_TEST_' . bin2hex(random_bytes(4));
        $stmt = $this->pdo->prepare(
            'INSERT INTO `game_rooms` (`game_id`, `game_configuration_id`, `creator_id`, `title`, `min_players`, `max_players`, `status`, `formation_deadline_at`, `created_at`, `updated_at`)
             VALUES (1, 1, :creator_id, :title, :min, :max, :status, :deadline, NOW(), NOW())'
        );
        $stmt->bindValue(':creator_id', $creatorId, PDO::PARAM_INT);
        $stmt->bindValue(':title', $title, PDO::PARAM_STR);
        $stmt->bindValue(':min', $minPlayers, PDO::PARAM_INT);
        $stmt->bindValue(':max', $maxPlayers, PDO::PARAM_INT);
        $stmt->bindValue(':status', $status, PDO::PARAM_STR);
        $stmt->bindValue(':deadline', $deadline, PDO::PARAM_STR);
        $stmt->execute();

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Helper to create participant and deposit payment
     */
    private function addParticipant(int $roomId, int $userId, string $partStatus = 'PENDING_DEPOSIT', int $paidAmount = 0, string $payStatus = 'PENDING'): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO `room_participants` (`game_room_id`, `user_id`, `status`, `joined_at`, `created_at`, `updated_at`)
             VALUES (:room_id, :user_id, :status, NOW(), NOW(), NOW())'
        );
        $stmt->bindValue(':room_id', $roomId, PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':status', $partStatus, PDO::PARAM_STR);
        $stmt->execute();
        $partId = (int) $this->pdo->lastInsertId();

        $payStmt = $this->pdo->prepare(
            'INSERT INTO `player_payments` (`room_participant_id`, `payment_type`, `amount`, `paid_amount`, `status`, `created_at`, `updated_at`)
             VALUES (:part_id, \'DEPOSIT\', 50000, :paid_amount, :pay_status, NOW(), NOW())'
        );
        $payStmt->bindValue(':part_id', $partId, PDO::PARAM_INT);
        $payStmt->bindValue(':paid_amount', $paidAmount, PDO::PARAM_INT);
        $payStmt->bindValue(':pay_status', $payStatus, PDO::PARAM_STR);
        $payStmt->execute();
        $payId = (int) $this->pdo->lastInsertId();

        return ['part_id' => $partId, 'pay_id' => $payId];
    }

    // =========================================================================
    // FLOW 1: PLAYER LEAVE DURING FORMING
    // =========================================================================

    public function testPlayerLeaveFormingCancelsUnpaidPayment(): void
    {
        $roomId = $this->createTestRoom(1, 'FORMING');
        $part = $this->addParticipant($roomId, 3, 'PENDING_DEPOSIT', 0, 'PENDING');

        $req = new Request('POST', "/api/v1/rooms/{$roomId}/leave", [], [], ['Authorization' => 'Bearer 3']);
        $res = $this->app->dispatch($req);

        $this->assertSame(200, $res->getStatusCode());

        // Participant is LEFT
        $pStmt = $this->pdo->query("SELECT `status` FROM `room_participants` WHERE `id` = {$part['part_id']}");
        $this->assertSame('LEFT', $pStmt->fetchColumn());

        // Payment invoice is CANCELLED
        $payStmt = $this->pdo->query("SELECT `status` FROM `player_payments` WHERE `id` = {$part['pay_id']}");
        $this->assertSame('CANCELLED', $payStmt->fetchColumn());

        // No refund created since paid_amount was 0
        $refStmt = $this->pdo->query("SELECT COUNT(*) FROM `refunds` WHERE `player_payment_id` = {$part['pay_id']}");
        $this->assertSame(0, (int) $refStmt->fetchColumn());
    }

    public function testPlayerLeaveFormingCreatesPendingRefundIfDepositPaid(): void
    {
        $roomId = $this->createTestRoom(1, 'FORMING');
        $part = $this->addParticipant($roomId, 3, 'DEPOSITED', 50000, 'PAID');

        $req = new Request('POST', "/api/v1/rooms/{$roomId}/leave", [], [], ['Authorization' => 'Bearer 3']);
        $res = $this->app->dispatch($req);

        $this->assertSame(200, $res->getStatusCode());

        // Participant is LEFT
        $pStmt = $this->pdo->query("SELECT `status` FROM `room_participants` WHERE `id` = {$part['part_id']}");
        $this->assertSame('LEFT', $pStmt->fetchColumn());

        // A PENDING refund record was created for 50,000 VNĐ
        $refStmt = $this->pdo->query("SELECT `status`, `amount` FROM `refunds` WHERE `player_payment_id` = {$part['pay_id']}");
        $refund = $refStmt->fetch(PDO::FETCH_ASSOC);
        $this->assertNotFalse($refund);
        $this->assertSame('PENDING', $refund['status']);
        $this->assertEquals(50000, (int) $refund['amount']);
    }

    // =========================================================================
    // FLOW 2: CREATOR CANCELLATION DURING FORMING
    // =========================================================================

    public function testCreatorLeaveFormingCancelsRoomAndParticipants(): void
    {
        // Creator is user 3
        $roomId = $this->createTestRoom(3, 'FORMING');
        $creatorPart = $this->addParticipant($roomId, 3, 'DEPOSITED', 50000, 'PAID');
        $otherPart = $this->addParticipant($roomId, 4, 'PENDING_DEPOSIT', 0, 'PENDING');

        // Creator calls leave
        $req = new Request('POST', "/api/v1/rooms/{$roomId}/leave", [], [], ['Authorization' => 'Bearer 3']);
        $res = $this->app->dispatch($req);

        $this->assertSame(200, $res->getStatusCode());

        // Room is CANCELLED
        $rStmt = $this->pdo->query("SELECT `status` FROM `game_rooms` WHERE `id` = {$roomId}");
        $this->assertSame('CANCELLED', $rStmt->fetchColumn());

        // Other participant is CANCELLED_UNPAID
        $opStmt = $this->pdo->query("SELECT `status` FROM `room_participants` WHERE `id` = {$otherPart['part_id']}");
        $this->assertSame('CANCELLED_UNPAID', $opStmt->fetchColumn());

        // Other participant's unpaid payment is CANCELLED
        $payStmt = $this->pdo->query("SELECT `status` FROM `player_payments` WHERE `id` = {$otherPart['pay_id']}");
        $this->assertSame('CANCELLED', $payStmt->fetchColumn());

        // Creator's paid deposit gets a PENDING refund
        $refStmt = $this->pdo->query("SELECT `status`, `amount` FROM `refunds` WHERE `player_payment_id` = {$creatorPart['pay_id']}");
        $refund = $refStmt->fetch(PDO::FETCH_ASSOC);
        $this->assertNotFalse($refund);
        $this->assertSame('PENDING', $refund['status']);
    }

    public function testCreatorCanCancelFormingRoomViaCancelEndpoint(): void
    {
        $roomId = $this->createTestRoom(3, 'FORMING');
        $part = $this->addParticipant($roomId, 3, 'PENDING_DEPOSIT', 0, 'PENDING');

        $req = new Request('POST', "/api/v1/rooms/{$roomId}/cancel", [], ['reason' => 'Đổi kế hoạch'], ['Authorization' => 'Bearer 3']);
        $res = $this->app->dispatch($req);

        $this->assertSame(200, $res->getStatusCode());
        $data = json_decode($res->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame('CANCELLED', $data['data']['status']);

        // Check room status
        $rStmt = $this->pdo->query("SELECT `status` FROM `game_rooms` WHERE `id` = {$roomId}");
        $this->assertSame('CANCELLED', $rStmt->fetchColumn());
    }

    public function testNonCreatorNonAdminCannotCancelRoom(): void
    {
        // Creator is user 3
        $roomId = $this->createTestRoom(3, 'FORMING');

        // User 4 attempts to cancel
        $_SESSION['user_id'] = 4;
        $req = new Request('POST', "/api/v1/rooms/{$roomId}/cancel", [], ['reason' => 'Phá đám'], ['Authorization' => 'Bearer 4']);
        $res = $this->app->dispatch($req);
        $_SESSION['user_id'] = 3;

        $this->assertSame(403, $res->getStatusCode());
    }

    public function testCreatorCannotCancelRoomNotInFormingState(): void
    {
        // Creator is user 3, room is SCHEDULED
        $roomId = $this->createTestRoom(3, 'SCHEDULED');

        $req = new Request('POST', "/api/v1/rooms/{$roomId}/cancel", [], ['reason' => 'Muốn hủy'], ['Authorization' => 'Bearer 3']);
        $res = $this->app->dispatch($req);

        $this->assertSame(400, $res->getStatusCode());
    }

    // =========================================================================
    // FLOW 4: REPLACEMENT TAKEOVER REFUND (DEC-01 & DEC-02: PENDING, ADMIN APPROVAL REQUIRED)
    // =========================================================================

    public function testReplacementTakeoverCreatesPendingRefundForReplacedParticipant(): void
    {
        $roomId = $this->createTestRoom(1, 'SCHEDULED', 2, 4);

        // Session starts in 2 hours
        $startAt = date('Y-m-d H:i:s', strtotime('+2 hours'));
        $endAt = date('Y-m-d H:i:s', strtotime('+5 hours'));
        $this->createTestSession($roomId, $startAt, $endAt);

        // Old player (User 3) has paid deposit (50k)
        $oldPart = $this->addParticipant($roomId, 3, 'DEPOSITED', 50000, 'PAID');

        // Old player requests leave (Replacement Window opens)
        $leaveReq = new Request('POST', "/api/v1/rooms/{$roomId}/leave", [], [], ['Authorization' => 'Bearer 3']);
        $leaveRes = $this->app->dispatch($leaveReq);
        $this->assertSame(200, $leaveRes->getStatusCode());

        // Verify status is LEAVE_REQUESTED and replacement is OPEN
        $pStmt = $this->pdo->query("SELECT `status` FROM `room_participants` WHERE `id` = {$oldPart['part_id']}");
        $this->assertSame('LEAVE_REQUESTED', $pStmt->fetchColumn());

        // New player (User 4) joins to take over slot
        $_SESSION['user_id'] = 4;
        $joinReq = new Request('POST', "/api/v1/rooms/{$roomId}/join", [], [], ['Authorization' => 'Bearer 4']);
        $joinRes = $this->app->dispatch($joinReq);
        $_SESSION['user_id'] = 3;
        $this->assertSame(200, $joinRes->getStatusCode());
        $joinData = json_decode($joinRes->getContent(), true);
        $this->assertTrue($joinData['data']['is_replacement']);

        // 1. Old player status becomes LEFT
        $oldPStmt = $this->pdo->query("SELECT `status` FROM `room_participants` WHERE `id` = {$oldPart['part_id']}");
        $this->assertSame('LEFT', $oldPStmt->fetchColumn());

        // 2. CRITICAL: A refund record is created for old player's payment with status = PENDING
        $refStmt = $this->pdo->query("SELECT `status`, `amount` FROM `refunds` WHERE `player_payment_id` = {$oldPart['pay_id']}");
        $refund = $refStmt->fetch(PDO::FETCH_ASSOC);
        $this->assertNotFalse($refund);
        $this->assertSame('PENDING', $refund['status'], 'Takeover refund MUST remain PENDING per DEC-01 & DEC-02');
        $this->assertEquals(50000, (int) $refund['amount']);
    }

    public function testTakeoverDoesNotCreateRefundIfLeavingPlayerNeverPaid(): void
    {
        $roomId = $this->createTestRoom(1, 'SCHEDULED', 2, 4);
        $startAt = date('Y-m-d H:i:s', strtotime('+2 hours'));
        $endAt = date('Y-m-d H:i:s', strtotime('+5 hours'));
        $this->createTestSession($roomId, $startAt, $endAt);

        // Old player (User 3) has unpaid deposit (paid_amount = 0)
        $oldPart = $this->addParticipant($roomId, 3, 'PENDING_DEPOSIT', 0, 'PENDING');

        // Request leave
        $leaveReq = new Request('POST', "/api/v1/rooms/{$roomId}/leave", [], [], ['Authorization' => 'Bearer 3']);
        $this->assertSame(200, $this->app->dispatch($leaveReq)->getStatusCode());

        // New player joins
        $_SESSION['user_id'] = 4;
        $joinReq = new Request('POST', "/api/v1/rooms/{$roomId}/join", [], [], ['Authorization' => 'Bearer 4']);
        $this->assertSame(200, $this->app->dispatch($joinReq)->getStatusCode());
        $_SESSION['user_id'] = 3;

        // Old player is LEFT, but NO refund is created
        $refStmt = $this->pdo->query("SELECT COUNT(*) FROM `refunds` WHERE `player_payment_id` = {$oldPart['pay_id']}");
        $this->assertSame(0, (int) $refStmt->fetchColumn());
    }

    // =========================================================================
    // FLOW 5: ADMIN REFUND APPROVE & REJECT
    // =========================================================================

    public function testAdminCanApprovePendingRefund(): void
    {
        $roomId = $this->createTestRoom(1, 'FORMING');
        $part = $this->addParticipant($roomId, 3, 'DEPOSITED', 50000, 'PAID');

        // Create a PENDING refund
        $this->pdo->exec("INSERT INTO `refunds` (`player_payment_id`, `amount`, `reason`, `status`, `requested_at`, `created_at`, `updated_at`) VALUES ({$part['pay_id']}, 50000, 'Lý do hoàn tiền', 'PENDING', NOW(), NOW(), NOW())");
        $refundId = (int) $this->pdo->lastInsertId();

        // Admin approves
        $_SESSION['user_id'] = 1;
        $req = new Request('POST', "/api/v1/admin/refunds/{$refundId}/approve", [], [], ['Authorization' => 'Bearer 1']);
        $res = $this->app->dispatch($req);

        $this->assertSame(200, $res->getStatusCode());

        // Check refund status
        $rStmt = $this->pdo->query("SELECT `status`, `completed_at`, `reviewed_by` FROM `refunds` WHERE `id` = {$refundId}");
        $refund = $rStmt->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('APPROVED', $refund['status']);
        $this->assertNotNull($refund['completed_at']);
        $this->assertEquals(1, (int) $refund['reviewed_by']);

        // Check player_payments status synchronized to CANCELLED
        $payStmt = $this->pdo->query("SELECT `status` FROM `player_payments` WHERE `id` = {$part['pay_id']}");
        $this->assertSame('CANCELLED', $payStmt->fetchColumn());

        // Check payment_transactions logged
        $txStmt = $this->pdo->query("SELECT `status`, `amount`, `idempotency_key` FROM `payment_transactions` WHERE `player_payment_id` = {$part['pay_id']}");
        $tx = $txStmt->fetch(PDO::FETCH_ASSOC);
        $this->assertNotFalse($tx);
        $this->assertSame('SUCCESS', $tx['status']);
        $this->assertEquals(50000, (int) $tx['amount']);
        $this->assertSame('REFUND_APPROVE_' . $refundId, $tx['idempotency_key']);
    }

    public function testAdminApproveIsIdempotent(): void
    {
        $roomId = $this->createTestRoom(1, 'FORMING');
        $part = $this->addParticipant($roomId, 3, 'DEPOSITED', 50000, 'PAID');

        $this->pdo->exec("INSERT INTO `refunds` (`player_payment_id`, `amount`, `reason`, `status`, `requested_at`, `created_at`, `updated_at`) VALUES ({$part['pay_id']}, 50000, 'Lý do hoàn tiền', 'PENDING', NOW(), NOW(), NOW())");
        $refundId = (int) $this->pdo->lastInsertId();

        // First approval
        $_SESSION['user_id'] = 1;
        $req1 = new Request('POST', "/api/v1/admin/refunds/{$refundId}/approve", [], [], ['Authorization' => 'Bearer 1']);
        $this->assertSame(200, $this->app->dispatch($req1)->getStatusCode());

        // Second approval (idempotent replay)
        $req2 = new Request('POST', "/api/v1/admin/refunds/{$refundId}/approve", [], [], ['Authorization' => 'Bearer 1']);
        $res2 = $this->app->dispatch($req2);
        $this->assertSame(200, $res2->getStatusCode());

        // Only 1 transaction recorded
        $cntStmt = $this->pdo->query("SELECT COUNT(*) FROM `payment_transactions` WHERE `idempotency_key` = 'REFUND_APPROVE_{$refundId}'");
        $this->assertSame(1, (int) $cntStmt->fetchColumn());
    }

    public function testAdminCanRejectPendingRefundWithReason(): void
    {
        $roomId = $this->createTestRoom(1, 'FORMING');
        $part = $this->addParticipant($roomId, 3, 'DEPOSITED', 50000, 'PAID');

        $this->pdo->exec("INSERT INTO `refunds` (`player_payment_id`, `amount`, `reason`, `status`, `requested_at`, `created_at`, `updated_at`) VALUES ({$part['pay_id']}, 50000, 'Lý do hoàn tiền', 'PENDING', NOW(), NOW(), NOW())");
        $refundId = (int) $this->pdo->lastInsertId();

        // Admin rejects with valid reason
        $_SESSION['user_id'] = 1;
        $req = new Request('POST', "/api/v1/admin/refunds/{$refundId}/reject", [], ['reason' => 'Không đủ điều kiện theo chính sách'], ['Authorization' => 'Bearer 1']);
        $res = $this->app->dispatch($req);

        $this->assertSame(200, $res->getStatusCode());

        $rStmt = $this->pdo->query("SELECT `status`, `review_reason` FROM `refunds` WHERE `id` = {$refundId}");
        $refund = $rStmt->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('REJECTED', $refund['status']);
        $this->assertSame('Không đủ điều kiện theo chính sách', $refund['review_reason']);
    }

    public function testAdminRejectFailsWithShortOrEmptyReason(): void
    {
        $_SESSION['user_id'] = 1;
        $roomId = $this->createTestRoom(1, 'FORMING');
        $part = $this->addParticipant($roomId, 3, 'DEPOSITED', 50000, 'PAID');

        $this->pdo->exec("INSERT INTO `refunds` (`player_payment_id`, `amount`, `reason`, `status`, `requested_at`, `created_at`, `updated_at`) VALUES ({$part['pay_id']}, 50000, 'Lý do hoàn tiền', 'PENDING', NOW(), NOW(), NOW())");
        $refundId = (int) $this->pdo->lastInsertId();

        // Empty reason
        $reqEmpty = new Request('POST', "/api/v1/admin/refunds/{$refundId}/reject", [], ['reason' => ''], ['Authorization' => 'Bearer 1']);
        $this->assertSame(422, $this->app->dispatch($reqEmpty)->getStatusCode());

        // Too short reason (< 5 chars)
        $reqShort = new Request('POST', "/api/v1/admin/refunds/{$refundId}/reject", [], ['reason' => 'ko'], ['Authorization' => 'Bearer 1']);
        $this->assertSame(422, $this->app->dispatch($reqShort)->getStatusCode());
    }

    public function testNonAdminCannotApproveOrRejectRefund(): void
    {
        $roomId = $this->createTestRoom(1, 'FORMING');
        $part = $this->addParticipant($roomId, 3, 'DEPOSITED', 50000, 'PAID');

        $this->pdo->exec("INSERT INTO `refunds` (`player_payment_id`, `amount`, `reason`, `status`, `requested_at`, `created_at`, `updated_at`) VALUES ({$part['pay_id']}, 50000, 'Lý do hoàn tiền', 'PENDING', NOW(), NOW(), NOW())");
        $refundId = (int) $this->pdo->lastInsertId();

        // Regular player (User 3) tries to approve
        $_SESSION['user_id'] = 3;
        $appReq = new Request('POST', "/api/v1/admin/refunds/{$refundId}/approve", [], [], ['Authorization' => 'Bearer 3']);
        $this->assertSame(403, $this->app->dispatch($appReq)->getStatusCode());

        // Regular player (User 3) tries to reject
        $rejReq = new Request('POST', "/api/v1/admin/refunds/{$refundId}/reject", [], ['reason' => 'Lý do hợp lệ nhưng không có quyền'], ['Authorization' => 'Bearer 3']);
        $this->assertSame(403, $this->app->dispatch($rejReq)->getStatusCode());
    }

    // =========================================================================
    // FLOW 6: FORMATION TIMEOUT AUTO-REFUND (OPTION A FINAL)
    // =========================================================================

    public function testFormationTimeoutOptionAAutoRefunds(): void
    {
        // Expired room (deadline 1 hour ago), min_players = 3
        $pastDeadline = date('Y-m-d H:i:s', time() - 3600);
        $roomId = $this->createTestRoom(1, 'FORMING', 3, 5, $pastDeadline);

        // Player 1 paid deposit (50k)
        $partPaid = $this->addParticipant($roomId, 3, 'DEPOSITED', 50000, 'PAID');

        // Player 2 did not pay deposit (0k)
        $partUnpaid = $this->addParticipant($roomId, 4, 'PENDING_DEPOSIT', 0, 'PENDING');

        // Run background job
        $cancelledCount = $this->jobService->runFormationTimeout();
        $this->assertGreaterThanOrEqual(1, $cancelledCount);

        // Room is CANCELLED
        $rStmt = $this->pdo->query("SELECT `status` FROM `game_rooms` WHERE `id` = {$roomId}");
        $this->assertSame('CANCELLED', $rStmt->fetchColumn());

        // Participants are CANCELLED_UNPAID
        $p1Stmt = $this->pdo->query("SELECT `status` FROM `room_participants` WHERE `id` = {$partPaid['part_id']}");
        $this->assertSame('CANCELLED_UNPAID', $p1Stmt->fetchColumn());

        // Paid participant gets COMPLETED refund
        $refStmt = $this->pdo->query("SELECT `status`, `amount`, `completed_at` FROM `refunds` WHERE `player_payment_id` = {$partPaid['pay_id']}");
        $refund = $refStmt->fetch(PDO::FETCH_ASSOC);
        $this->assertNotFalse($refund);
        $this->assertSame('COMPLETED', $refund['status']);
        $this->assertEquals(50000, (int) $refund['amount']);
        $this->assertNotNull($refund['completed_at']);

        // Paid participant's payment is CANCELLED
        $pay1Stmt = $this->pdo->query("SELECT `status` FROM `player_payments` WHERE `id` = {$partPaid['pay_id']}");
        $this->assertSame('CANCELLED', $pay1Stmt->fetchColumn());

        // Payment transaction recorded with deterministic idempotency key
        $txStmt = $this->pdo->query("SELECT `status`, `amount`, `idempotency_key` FROM `payment_transactions` WHERE `player_payment_id` = {$partPaid['pay_id']}");
        $tx = $txStmt->fetch(PDO::FETCH_ASSOC);
        $this->assertNotFalse($tx);
        $this->assertSame('SUCCESS', $tx['status']);
        $this->assertSame('TIMEOUT_REFUND_' . $partPaid['pay_id'], $tx['idempotency_key']);

        // Unpaid participant's payment is CANCELLED with NO refund record
        $pay2Stmt = $this->pdo->query("SELECT `status` FROM `player_payments` WHERE `id` = {$partUnpaid['pay_id']}");
        $this->assertSame('CANCELLED', $pay2Stmt->fetchColumn());

        $ref2Stmt = $this->pdo->query("SELECT COUNT(*) FROM `refunds` WHERE `player_payment_id` = {$partUnpaid['pay_id']}");
        $this->assertSame(0, (int) $ref2Stmt->fetchColumn());

        // Repeated run of formation timeout is idempotent (0 additional rooms cancelled)
        $repeatCount = $this->jobService->runFormationTimeout();
        $this->assertSame(0, $repeatCount);
    }

    // =========================================================================
    // FLOW 3: ADMIN CANCEL ROOM (24-HOUR POLICY & INVOICE CANCELLATION)
    // =========================================================================

    public function testAdminCancelRoomCancelsPendingInvoicesAndPreventsDuplicateRefunds(): void
    {
        $roomId = $this->createTestRoom(1, 'SCHEDULED', 2, 4);

        // Session starts in 3 days (> 24 hours, eligible for refund)
        $startAt = date('Y-m-d H:i:s', strtotime('+72 hours'));
        $endAt = date('Y-m-d H:i:s', strtotime('+75 hours'));
        $this->createTestSession($roomId, $startAt, $endAt);

        // Player 1 paid deposit
        $partPaid = $this->addParticipant($roomId, 3, 'DEPOSITED', 50000, 'PAID');

        // Player 2 has pending unpaid deposit
        $partUnpaid = $this->addParticipant($roomId, 4, 'PENDING_DEPOSIT', 0, 'PENDING');

        // Admin cancels room
        $_SESSION['user_id'] = 1;
        $cancelReq = new Request('POST', "/api/v1/admin/rooms/{$roomId}/cancel", [], ['reason' => 'Hủy do sự cố địa điểm'], ['Authorization' => 'Bearer 1']);
        $cancelRes = $this->app->dispatch($cancelReq);

        $this->assertSame(200, $cancelRes->getStatusCode());
        $cancelData = json_decode($cancelRes->getContent(), true);
        $this->assertTrue($cancelData['data']['is_eligible_for_refund']);

        // Paid participant gets APPROVED refund
        $refStmt = $this->pdo->query("SELECT `status`, `amount` FROM `refunds` WHERE `player_payment_id` = {$partPaid['pay_id']}");
        $refund = $refStmt->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('APPROVED', $refund['status']);

        // Paid participant payment is CANCELLED
        $pay1Stmt = $this->pdo->query("SELECT `status` FROM `player_payments` WHERE `id` = {$partPaid['pay_id']}");
        $this->assertSame('CANCELLED', $pay1Stmt->fetchColumn());

        // Unpaid participant payment is CANCELLED
        $pay2Stmt = $this->pdo->query("SELECT `status` FROM `player_payments` WHERE `id` = {$partUnpaid['pay_id']}");
        $this->assertSame('CANCELLED', $pay2Stmt->fetchColumn());

        // Idempotency check: Calling cancel again returns 400 (room already cancelled), no duplicate refund
        $repeatReq = new Request('POST', "/api/v1/admin/rooms/{$roomId}/cancel", [], ['reason' => 'Hủy lại'], ['Authorization' => 'Bearer 1']);
        $repeatRes = $this->app->dispatch($repeatReq);
        $this->assertSame(400, $repeatRes->getStatusCode());

        // Still only 1 refund record
        $cntRef = $this->pdo->query("SELECT COUNT(*) FROM `refunds` WHERE `player_payment_id` = {$partPaid['pay_id']}");
        $this->assertSame(1, (int) $cntRef->fetchColumn());
    }
}
