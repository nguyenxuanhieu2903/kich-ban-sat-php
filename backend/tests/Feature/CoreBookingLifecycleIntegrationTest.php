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

class CoreBookingLifecycleIntegrationTest extends TestCase
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
        $_SESSION = [];

        $this->pdo = DatabaseConnection::getInstance();
        $this->jobService = new BackgroundJobService($this->pdo);

        $routesFile = $basePath . '/routes/api.php';
        $router = $this->app->getRouter();
        require $routesFile;

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
            $stmt = $this->pdo->query("SELECT `id` FROM `game_rooms` WHERE `title` LIKE 'LIFECYCLE_TEST_%'");
            $roomIds = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
            if (!empty($roomIds)) {
                $in = implode(',', array_map('intval', $roomIds));
                $this->pdo->exec("DELETE r FROM `refunds` r INNER JOIN `player_payments` p ON p.`id` = r.`player_payment_id` INNER JOIN `room_participants` rp ON rp.`id` = p.`room_participant_id` WHERE rp.`game_room_id` IN ({$in})");
                $this->pdo->exec("DELETE tx FROM `payment_transactions` tx INNER JOIN `player_payments` p ON p.`id` = tx.`player_payment_id` INNER JOIN `room_participants` rp ON rp.`id` = p.`room_participant_id` WHERE rp.`game_room_id` IN ({$in})");
                $this->pdo->exec("DELETE FROM `participant_price_snapshots` WHERE `game_room_id` IN ({$in})");
                $this->pdo->exec("DELETE p FROM `player_payments` p INNER JOIN `room_participants` rp ON rp.`id` = p.`room_participant_id` WHERE rp.`game_room_id` IN ({$in})");
                $this->pdo->exec("DELETE FROM `schedule_responses` WHERE `game_room_id` IN ({$in})");
                $this->pdo->exec("DELETE FROM `sessions` WHERE `game_room_id` IN ({$in})");
                $this->pdo->exec("DELETE FROM `dm_assignments` WHERE `game_room_id` IN ({$in})");
                $this->pdo->exec("DELETE FROM `schedule_proposals` WHERE `game_room_id` IN ({$in})");
                $this->pdo->exec("DELETE FROM `room_participants` WHERE `game_room_id` IN ({$in})");
                $this->pdo->exec("DELETE FROM `game_rooms` WHERE `id` IN ({$in})");
            }
        } catch (\Throwable) {
        }
    }

    private function createTestRoom(int $creatorId, string $status = 'FORMING', int $minPlayers = 2, int $maxPlayers = 4): int
    {
        $title = 'LIFECYCLE_TEST_' . bin2hex(random_bytes(4));
        $deadline = date('Y-m-d H:i:s', time() + 86400);
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

    private function makeRequest(string $method, string $path, array $body = [], int $userId = 3, array $extraHeaders = []): Request
    {
        $_SESSION['user_id'] = $userId;
        $_SESSION['role_code'] = $userId === 1 ? 'ADMIN' : 'USER';
        $_SESSION['user'] = [
            'id' => $userId,
            'role_code' => $userId === 1 ? 'ADMIN' : 'USER',
            'email' => "user{$userId}@test.com",
            'full_name' => "User {$userId}",
        ];

        $headers = array_merge([
            'Authorization' => "Bearer {$userId}",
            'Content-Type' => 'application/json',
        ], $extraHeaders);

        return new Request($method, $path, [], $body, $headers, json_encode($body));
    }

    /**
     * STEP 4 / DEF-24: Late joiner receives deposit deadline and 50,000 VND invoice
     */
    public function testLateJoinerReceivesDepositDeadlineAndInvoiceWhenProposalExists(): void
    {
        $creatorId = 3; // Player user 3
        $roomId = $this->createTestRoom($creatorId, 'FORMING', 2, 4);

        // Creator joins room as participant
        $this->pdo->exec("INSERT INTO `room_participants` (`game_room_id`, `user_id`, `status`, `joined_at`, `created_at`, `updated_at`) VALUES ({$roomId}, {$creatorId}, 'PENDING_DEPOSIT', NOW(), NOW(), NOW())");

        // Creator proposes a schedule
        $startAt = date('Y-m-d 18:00:00', strtotime('+2 days'));
        $endAt = date('Y-m-d 22:00:00', strtotime('+2 days'));
        $propReq = $this->makeRequest('POST', "/api/v1/rooms/{$roomId}/proposals", [
            'proposed_start_at' => $startAt,
            'proposed_end_at' => $endAt,
        ], $creatorId);
        $propRes = $this->app->dispatch($propReq);
        $this->assertSame(201, $propRes->getStatusCode(), 'Proposal creation should succeed: ' . $propRes->getContent());

        // Verify active proposal exists
        $propCheck = $this->pdo->query("SELECT `id`, `status` FROM `schedule_proposals` WHERE `game_room_id` = {$roomId} AND `status` IN ('PROPOSED', 'PENDING')")->fetch(PDO::FETCH_ASSOC);
        $this->assertNotFalse($propCheck);

        // Late joiner (User 4) joins the room
        $joinReq = $this->makeRequest('POST', "/api/v1/rooms/{$roomId}/join", [], 4);
        $joinRes = $this->app->dispatch($joinReq);
        $this->assertSame(200, $joinRes->getStatusCode(), 'Late joiner should join room successfully: ' . $joinRes->getContent());

        $joinData = json_decode($joinRes->getContent(), true)['data'];
        $latePartId = (int) $joinData['participant_id'];

        // DEF-24 verification: late joiner MUST have deposit_deadline_at set (around NOW + 2h)
        $partRow = $this->pdo->query("SELECT `status`, `deposit_deadline_at` FROM `room_participants` WHERE `id` = {$latePartId}")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('PENDING_DEPOSIT', $partRow['status']);
        $this->assertNotNull($partRow['deposit_deadline_at'], 'Late joiner must receive deposit_deadline_at');
        $deadlineTs = strtotime((string) $partRow['deposit_deadline_at']);
        $this->assertGreaterThan(time() + 7000, $deadlineTs, 'Deadline should be ~2 hours in the future');

        // DEF-24 verification: late joiner MUST have a player_payments record for DEPOSIT of 50,000 VND
        $payRow = $this->pdo->query("SELECT `id`, `amount`, `paid_amount`, `status`, `payment_type`, `deadline_at` FROM `player_payments` WHERE `room_participant_id` = {$latePartId} AND `payment_type` = 'DEPOSIT'")->fetch(PDO::FETCH_ASSOC);
        $this->assertNotFalse($payRow, 'Late joiner must receive DEPOSIT payment record');
        $this->assertSame(50000, (int) $payRow['amount']);
        $this->assertSame(0, (int) $payRow['paid_amount']);
        $this->assertSame('PENDING', $payRow['status']);
        $this->assertNotNull($payRow['deadline_at']);
    }

    /**
     * STEP 2 / DEF-22: 100% vote confirmation automatically invokes PricingService
     * snapshots pricing for each participant, and updates game room fields.
     */
    public function testScheduleConfirmationAutomaticallyCreatesPriceSnapshotAndUpdatesRoom(): void
    {
        $creatorId = 3;
        $roomId = $this->createTestRoom($creatorId, 'FORMING', 2, 2);

        // Add 2 participants and mark them DEPOSITED
        $this->pdo->exec("INSERT INTO `room_participants` (`game_room_id`, `user_id`, `status`, `deposit_deadline_at`, `joined_at`, `created_at`, `updated_at`) VALUES ({$roomId}, 3, 'DEPOSITED', DATE_ADD(NOW(), INTERVAL 2 HOUR), NOW(), NOW(), NOW())");
        $part1Id = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO `player_payments` (`room_participant_id`, `payment_type`, `amount`, `paid_amount`, `status`, `paid_at`, `created_at`, `updated_at`) VALUES ({$part1Id}, 'DEPOSIT', 50000, 50000, 'PAID', NOW(), NOW(), NOW())");

        $this->pdo->exec("INSERT INTO `room_participants` (`game_room_id`, `user_id`, `status`, `deposit_deadline_at`, `joined_at`, `created_at`, `updated_at`) VALUES ({$roomId}, 4, 'DEPOSITED', DATE_ADD(NOW(), INTERVAL 2 HOUR), NOW(), NOW(), NOW())");
        $part2Id = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO `player_payments` (`room_participant_id`, `payment_type`, `amount`, `paid_amount`, `status`, `paid_at`, `created_at`, `updated_at`) VALUES ({$part2Id}, 'DEPOSIT', 50000, 50000, 'PAID', NOW(), NOW(), NOW())");

        // Creator proposes schedule
        $startAt = date('Y-m-d 18:00:00', strtotime('+2 days'));
        $endAt = date('Y-m-d 22:00:00', strtotime('+2 days'));
        $propReq = $this->makeRequest('POST', "/api/v1/rooms/{$roomId}/proposals", [
            'proposed_start_at' => $startAt,
            'proposed_end_at' => $endAt,
        ], 3);
        $propRes = $this->app->dispatch($propReq);
        $this->assertSame(201, $propRes->getStatusCode(), 'Proposal creation should succeed: ' . $propRes->getContent());
        $propData = json_decode($propRes->getContent(), true)['data'];
        $propId = (int) $propData['proposal_id'];

        // User 4 votes ACCEPTED (100% accepted)
        $voteReq = $this->makeRequest('POST', "/api/v1/proposals/{$propId}/vote", [
            'response' => 'ACCEPTED',
        ], 4);
        $voteRes = $this->app->dispatch($voteReq);
        $this->assertSame(200, $voteRes->getStatusCode(), 'Voting ACCEPTED should succeed: ' . $voteRes->getContent());

        // Verify room is now SCHEDULED
        $roomRow = $this->pdo->query("SELECT `status`, `price_subtotal`, `price_final_amount`, `price_deposit_total`, `price_remaining_total`, `price_total_participants` FROM `game_rooms` WHERE `id` = {$roomId}")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('SCHEDULED', $roomRow['status']);

        // DEF-22 verification: game room pricing columns MUST be populated
        $this->assertNotNull($roomRow['price_subtotal']);
        $this->assertNotNull($roomRow['price_final_amount']);
        $this->assertSame(2, (int) $roomRow['price_total_participants']);
        $this->assertSame(100000, (int) $roomRow['price_deposit_total']); // 50k * 2
        $this->assertGreaterThan(0, (int) $roomRow['price_remaining_total']);

        // DEF-22 verification: participant_price_snapshots MUST exist for both participants
        $snap1 = $this->pdo->query("SELECT * FROM `participant_price_snapshots` WHERE `room_participant_id` = {$part1Id}")->fetch(PDO::FETCH_ASSOC);
        $this->assertNotFalse($snap1, 'Price snapshot for participant 1 must exist');
        $this->assertSame(50000, (int) $snap1['deposit_amount']);
        $this->assertGreaterThan(0, (int) $snap1['remaining_amount']);
        $this->assertSame((int) $snap1['base_price'] - (int) $snap1['discount_amount'], (int) $snap1['final_amount']);
        $this->assertSame((int) $snap1['deposit_amount'] + (int) $snap1['remaining_amount'], (int) $snap1['final_amount']);

        $snap2 = $this->pdo->query("SELECT * FROM `participant_price_snapshots` WHERE `room_participant_id` = {$part2Id}")->fetch(PDO::FETCH_ASSOC);
        $this->assertNotFalse($snap2, 'Price snapshot for participant 2 must exist');
        $this->assertSame(50000, (int) $snap2['deposit_amount']);
        $this->assertGreaterThan(0, (int) $snap2['remaining_amount']);
    }

    /**
     * STEP 3 / DEF-23: Remaining payment unblocked with persisted price snapshot
     */
    public function testRemainingPaymentSucceedsWithPersistedSnapshotAndRejectsSubsequentPay(): void
    {
        $creatorId = 3;
        $roomId = $this->createTestRoom($creatorId, 'FORMING', 2, 2);

        $this->pdo->exec("INSERT INTO `room_participants` (`game_room_id`, `user_id`, `status`, `deposit_deadline_at`, `joined_at`, `created_at`, `updated_at`) VALUES ({$roomId}, 3, 'DEPOSITED', DATE_ADD(NOW(), INTERVAL 2 HOUR), NOW(), NOW(), NOW())");
        $part1Id = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO `player_payments` (`room_participant_id`, `payment_type`, `amount`, `paid_amount`, `status`, `paid_at`, `created_at`, `updated_at`) VALUES ({$part1Id}, 'DEPOSIT', 50000, 50000, 'PAID', NOW(), NOW(), NOW())");
        $depositPayId1 = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO `room_participants` (`game_room_id`, `user_id`, `status`, `deposit_deadline_at`, `joined_at`, `created_at`, `updated_at`) VALUES ({$roomId}, 4, 'DEPOSITED', DATE_ADD(NOW(), INTERVAL 2 HOUR), NOW(), NOW(), NOW())");
        $part2Id = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO `player_payments` (`room_participant_id`, `payment_type`, `amount`, `paid_amount`, `status`, `paid_at`, `created_at`, `updated_at`) VALUES ({$part2Id}, 'DEPOSIT', 50000, 50000, 'PAID', NOW(), NOW(), NOW())");

        // Propose and vote 100% to schedule and snapshot prices
        $startAt = date('Y-m-d 18:00:00', strtotime('+2 days'));
        $endAt = date('Y-m-d 22:00:00', strtotime('+2 days'));
        $propReq = $this->makeRequest('POST', "/api/v1/rooms/{$roomId}/proposals", [
            'proposed_start_at' => $startAt,
            'proposed_end_at' => $endAt,
        ], 3);
        $propRes = $this->app->dispatch($propReq);
        $this->assertSame(201, $propRes->getStatusCode(), 'Proposal creation should succeed: ' . $propRes->getContent());
        $propId = (int) json_decode($propRes->getContent(), true)['data']['proposal_id'];

        $voteReq = $this->makeRequest('POST', "/api/v1/proposals/{$propId}/vote", ['response' => 'ACCEPTED'], 4);
        $voteRes = $this->app->dispatch($voteReq);
        $this->assertSame(200, $voteRes->getStatusCode());

        // User 3 pays remaining balance referencing their DEPOSIT payment id
        $remReq = $this->makeRequest('POST', "/api/v1/payments/{$depositPayId1}/remaining", [
            'payment_method' => 'SIMULATED_VNPAY',
        ], 3, [
            'x-idempotency-key' => 'IDEM_REM_' . bin2hex(random_bytes(6)),
        ]);
        $remRes = $this->app->dispatch($remReq);

        // DEF-23 verification: Payment MUST succeed (HTTP 200) instead of failing with PAYMENT_ALREADY_COMPLETED
        $this->assertSame(200, $remRes->getStatusCode(), 'Remaining payment should succeed: ' . $remRes->getContent());
        $remData = json_decode($remRes->getContent(), true);
        $this->assertTrue($remData['success']);

        // Verify REMAINING payment row was created and marked PAID
        $remPayRow = $this->pdo->query("SELECT `id`, `payment_type`, `amount`, `paid_amount`, `status` FROM `player_payments` WHERE `room_participant_id` = {$part1Id} AND `payment_type` = 'REMAINING'")->fetch(PDO::FETCH_ASSOC);
        $this->assertNotFalse($remPayRow);
        $this->assertSame('PAID', $remPayRow['status']);
        $this->assertSame((int) $remPayRow['amount'], (int) $remPayRow['paid_amount']);

        // Verify transaction recorded
        $txCount = (int) $this->pdo->query("SELECT COUNT(*) FROM `payment_transactions` WHERE `player_payment_id` = {$remPayRow['id']} AND `status` = 'SUCCESS'")->fetchColumn();
        $this->assertSame(1, $txCount);

        // Subsequent payment attempt must fail with PAYMENT_ALREADY_COMPLETED
        $dupReq = $this->makeRequest('POST', "/api/v1/payments/{$depositPayId1}/remaining", [
            'payment_method' => 'SIMULATED_VNPAY',
        ], 3);
        $dupRes = $this->app->dispatch($dupReq);
        $this->assertSame(400, $dupRes->getStatusCode());
        $dupData = json_decode($dupRes->getContent(), true);
        $this->assertSame('PAYMENT_ALREADY_COMPLETED', $dupData['error']['code']);
    }

    /**
     * STEP 1 / DEF-21: Deposit timeout in FORMING room frees slot and does NOT trigger phantom takeover loop
     */
    public function testDepositTimeoutInFormingRoomFreesSlotWithoutPhantomReplacementLoop(): void
    {
        $creatorId = 3;
        $roomId = $this->createTestRoom($creatorId, 'FORMING', 2, 2);

        // Participant 1 (Creator) - DEPOSITED
        $this->pdo->exec("INSERT INTO `room_participants` (`game_room_id`, `user_id`, `status`, `joined_at`, `created_at`, `updated_at`) VALUES ({$roomId}, 3, 'DEPOSITED', NOW(), NOW(), NOW())");

        // Participant 2 (User 4) - PENDING_DEPOSIT with expired deadline
        $pastDeadline = date('Y-m-d H:i:s', time() - 3600);
        $this->pdo->exec("INSERT INTO `room_participants` (`game_room_id`, `user_id`, `status`, `deposit_deadline_at`, `joined_at`, `created_at`, `updated_at`) VALUES ({$roomId}, 4, 'PENDING_DEPOSIT', '{$pastDeadline}', NOW(), NOW(), NOW())");
        $part4Id = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO `player_payments` (`room_participant_id`, `payment_type`, `amount`, `paid_amount`, `status`, `deadline_at`, `created_at`, `updated_at`) VALUES ({$part4Id}, 'DEPOSIT', 50000, 0, 'PENDING', '{$pastDeadline}', NOW(), NOW())");

        // Run deposit timeout job
        $kicked = $this->jobService->runDepositTimeout();
        $this->assertGreaterThanOrEqual(1, $kicked);

        // User 4 is CANCELLED_UNPAID
        $status4 = $this->pdo->query("SELECT `status` FROM `room_participants` WHERE `id` = {$part4Id}")->fetchColumn();
        $this->assertSame('CANCELLED_UNPAID', $status4);

        // DEF-21 check: game_rooms replacement_status MUST NOT be 'OPEN'
        $rwStatus = $this->pdo->query("SELECT `replacement_status` FROM `game_rooms` WHERE `id` = {$roomId}")->fetchColumn();
        $this->assertNull($rwStatus, 'FORMING room must NOT open replacement window on deposit timeout');

        // New player (User 13) attempts to join room
        // Before DEF-21 fix, this would fail with 400 NO_REPLACEMENT_SLOT because room had replacement_status = 'OPEN'
        $joinReq = $this->makeRequest('POST', "/api/v1/rooms/{$roomId}/join", [], 13);
        $joinRes = $this->app->dispatch($joinReq);

        $this->assertSame(200, $joinRes->getStatusCode(), 'New player must be able to join room into the freed slot: ' . $joinRes->getContent());
        $joinData = json_decode($joinRes->getContent(), true)['data'];
        $this->assertSame(2, (int) $joinData['current_players']);
    }

    /**
     * IDOR protection on remaining payment
     */
    public function testRemainingPaymentRejectsIdorAccess(): void
    {
        $creatorId = 3;
        $roomId = $this->createTestRoom($creatorId, 'FORMING', 2, 2);

        $this->pdo->exec("INSERT INTO `room_participants` (`game_room_id`, `user_id`, `status`, `deposit_deadline_at`, `joined_at`, `created_at`, `updated_at`) VALUES ({$roomId}, 3, 'DEPOSITED', DATE_ADD(NOW(), INTERVAL 2 HOUR), NOW(), NOW(), NOW())");
        $part1Id = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO `player_payments` (`room_participant_id`, `payment_type`, `amount`, `paid_amount`, `status`, `paid_at`, `created_at`, `updated_at`) VALUES ({$part1Id}, 'DEPOSIT', 50000, 50000, 'PAID', NOW(), NOW(), NOW())");
        $payId1 = (int) $this->pdo->lastInsertId();

        // User 4 tries to pay User 3's payment
        $req = $this->makeRequest('POST', "/api/v1/payments/{$payId1}/remaining", [
            'payment_method' => 'SIMULATED_VNPAY',
        ], 4);
        $res = $this->app->dispatch($req);

        $this->assertSame(403, $res->getStatusCode(), 'User 4 must be forbidden from paying User 3 payment');
    }
}
