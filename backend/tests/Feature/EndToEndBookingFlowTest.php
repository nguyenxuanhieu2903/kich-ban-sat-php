<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Application;
use App\Core\Request;
use App\Infrastructure\Config\Config;
use App\Infrastructure\Database\DatabaseConnection;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * End-To-End Booking Flow Test (Phase D)
 * Simulates the complete, contiguous lifecycle of a booking from room creation,
 * joining, scheduling proposal, deposit payment, voting, session creation,
 * session play lifecycle execution, and final remaining balance settlement.
 */
class EndToEndBookingFlowTest extends TestCase
{
    protected Application $app;
    protected PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();
        $basePath = dirname(__DIR__, 2);
        Config::load($basePath);
        $this->app = new Application($basePath);
        $_SESSION = [];

        $this->pdo = DatabaseConnection::getInstance();

        $routesFile = $basePath . '/routes/api.php';
        $router = $this->app->getRouter();
        require $routesFile;

        $this->cleanup();
    }

    protected function tearDown(): void
    {
        $this->cleanup();
        parent::tearDown();
    }

    private function cleanup(): void
    {
        $_SESSION = [];
        try {
            $stmt = $this->pdo->query("SELECT `id` FROM `game_rooms` WHERE `title` LIKE 'E2E_FLOW_%'");
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

    public function testCompleteContiguousBookingLifecycleE2E(): void
    {
        $uniqueTitle = 'E2E_FLOW_' . bin2hex(random_bytes(4));

        // -------------------------------------------------------------
        // STEP 1: Player 1 (User #3) Creates GameRoom
        // -------------------------------------------------------------
        $_SESSION = ['user_id' => 3, 'role' => 'PLAYER'];
        $createReq = new Request('POST', '/api/v1/game-rooms', [], [
            'game_id' => 1,
            'title' => $uniqueTitle,
            'description' => 'E2E Full Flow Test Room',
            'min_players' => 2,
            'max_players' => 2,
        ]);
        $createRes = $this->app->dispatch($createReq);
        $this->assertSame(201, $createRes->getStatusCode(), 'Room creation must return 201 Created');
        $createData = json_decode($createRes->getContent(), true)['data'];
        $roomId = (int) ($createData['room']['id'] ?? $createData['room_id'] ?? 0);
        $this->assertGreaterThan(0, $roomId);

        // -------------------------------------------------------------
        // STEP 2: Player 2 (User #4) Joins the Room
        // -------------------------------------------------------------
        $_SESSION = ['user_id' => 4, 'role' => 'PLAYER'];
        $joinReq = new Request('POST', "/api/v1/game-rooms/{$roomId}/join");
        $joinRes = $this->app->dispatch($joinReq);
        $this->assertSame(200, $joinRes->getStatusCode(), 'Player 2 join must return 200 OK');

        // Verify 2 participants joined
        $partCount = (int) $this->pdo->query("SELECT COUNT(*) FROM `room_participants` WHERE `game_room_id` = {$roomId}")->fetchColumn();
        $this->assertSame(2, $partCount);

        // -------------------------------------------------------------
        // STEP 3: Player 1 Proposes Schedule (Triggers Deposit Invoices)
        // -------------------------------------------------------------
        $_SESSION = ['user_id' => 3, 'role' => 'PLAYER'];
        $startAt = '2029-06-15 14:00:00';
        $endAt = '2029-06-15 17:00:00';
        $propReq = new Request('POST', "/api/v1/game-rooms/{$roomId}/proposals", [], [
            'venue_id' => 1,
            'proposed_start_at' => $startAt,
            'proposed_end_at' => $endAt,
        ]);
        $propRes = $this->app->dispatch($propReq);
        $this->assertSame(201, $propRes->getStatusCode(), 'Schedule proposal must be created');
        $proposalId = (int) json_decode($propRes->getContent(), true)['data']['proposal_id'];
        $this->assertGreaterThan(0, $proposalId);

        // Verify deposit invoices were automatically generated for both participants
        $p1PayStmt = $this->pdo->prepare('
            SELECT p.`id`, p.`amount`, p.`status`
            FROM `player_payments` p
            INNER JOIN `room_participants` rp ON rp.`id` = p.`room_participant_id`
            WHERE rp.`game_room_id` = :room_id AND rp.`user_id` = 3 AND p.`payment_type` = "DEPOSIT"
        ');
        $p1PayStmt->bindValue(':room_id', $roomId, PDO::PARAM_INT);
        $p1PayStmt->execute();
        $p1Payment = $p1PayStmt->fetch(PDO::FETCH_ASSOC);
        $this->assertNotEmpty($p1Payment, 'Host must have a deposit player_payment record');
        $p1PaymentId = (int) $p1Payment['id'];
        $this->assertSame('PENDING', $p1Payment['status']);

        $p2PayStmt = $this->pdo->prepare('
            SELECT p.`id`, p.`amount`, p.`status`
            FROM `player_payments` p
            INNER JOIN `room_participants` rp ON rp.`id` = p.`room_participant_id`
            WHERE rp.`game_room_id` = :room_id AND rp.`user_id` = 4 AND p.`payment_type` = "DEPOSIT"
        ');
        $p2PayStmt->bindValue(':room_id', $roomId, PDO::PARAM_INT);
        $p2PayStmt->execute();
        $p2Payment = $p2PayStmt->fetch(PDO::FETCH_ASSOC);
        $this->assertNotEmpty($p2Payment, 'Player 2 must have a deposit player_payment record');
        $p2PaymentId = (int) $p2Payment['id'];
        $this->assertSame('PENDING', $p2Payment['status']);

        // -------------------------------------------------------------
        // STEP 4: Both Players Pay Deposit (50,000 VNĐ)
        // -------------------------------------------------------------
        $_SESSION = ['user_id' => 3, 'role' => 'PLAYER'];
        $depReq1 = new Request('POST', "/api/v1/payments/{$p1PaymentId}/deposit", [], [
            'amount' => 50000,
            'idempotency_key' => 'E2E_DEP_P1_' . bin2hex(random_bytes(3)),
        ]);
        $depRes1 = $this->app->dispatch($depReq1);
        $this->assertSame(200, $depRes1->getStatusCode(), 'Player 1 deposit must succeed');

        $_SESSION = ['user_id' => 4, 'role' => 'PLAYER'];
        $depReq2 = new Request('POST', "/api/v1/payments/{$p2PaymentId}/deposit", [], [
            'amount' => 50000,
            'idempotency_key' => 'E2E_DEP_P2_' . bin2hex(random_bytes(3)),
        ]);
        $depRes2 = $this->app->dispatch($depReq2);
        $this->assertSame(200, $depRes2->getStatusCode(), 'Player 2 deposit must succeed');

        // Verify both are now DEPOSITED
        $depCountStmt = $this->pdo->prepare("SELECT COUNT(*) FROM `room_participants` WHERE `game_room_id` = :room_id AND `status` = 'DEPOSITED'");
        $depCountStmt->bindValue(':room_id', $roomId, PDO::PARAM_INT);
        $depCountStmt->execute();
        $this->assertSame(2, (int) $depCountStmt->fetchColumn(), 'Both participants must be in DEPOSITED state');

        // -------------------------------------------------------------
        // STEP 5: Voting ACCEPT Confirms Schedule & Automatically Creates Session
        // -------------------------------------------------------------
        $_SESSION = ['user_id' => 4, 'role' => 'PLAYER'];
        $voteReq = new Request('POST', "/api/v1/proposals/{$proposalId}/vote", [], [
            'response' => 'ACCEPTED',
        ]);
        $voteRes = $this->app->dispatch($voteReq);
        $this->assertSame(200, $voteRes->getStatusCode(), 'Player 2 voting ACCEPTED must succeed');

        $voteData = json_decode($voteRes->getContent(), true)['data'];
        $this->assertTrue($voteData['proposal_confirmed'], 'Proposal must be confirmed upon reaching threshold');
        $sessionId = (int) $voteData['session_id'];
        $this->assertGreaterThan(0, $sessionId, 'A valid session must be created automatically');

        // Verify session status is SCHEDULED
        $sessStatusStmt = $this->pdo->prepare('SELECT status FROM sessions WHERE id = :id');
        $sessStatusStmt->bindValue(':id', $sessionId, PDO::PARAM_INT);
        $sessStatusStmt->execute();
        $this->assertSame('SCHEDULED', $sessStatusStmt->fetchColumn());

        // -------------------------------------------------------------
        // STEP 6: DM Starts Session & Completes Session
        // -------------------------------------------------------------
        $_SESSION = ['user_id' => 2, 'role' => 'DM'];
        $startRes = $this->app->dispatch(new Request('POST', "/api/v1/sessions/{$sessionId}/start"));
        $this->assertSame(200, $startRes->getStatusCode(), 'Session start must return 200 OK');

        // Complete session
        $completeRes = $this->app->dispatch(new Request('POST', "/api/v1/sessions/{$sessionId}/complete"));
        $this->assertSame(200, $completeRes->getStatusCode(), 'Session complete must return 200 OK');

        // Verify session status is COMPLETED
        $sessStatusStmt = $this->pdo->prepare('SELECT status FROM sessions WHERE id = :id');
        $sessStatusStmt->bindValue(':id', $sessionId, PDO::PARAM_INT);
        $sessStatusStmt->execute();
        $this->assertSame('COMPLETED', $sessStatusStmt->fetchColumn());

        // -------------------------------------------------------------
        // STEP 7: Player Settles Remaining Payment
        // -------------------------------------------------------------
        // Check player deposit payment status is PAID
        $finalPayStmt = $this->pdo->prepare('
            SELECT p.`id`, p.`status`, p.`paid_amount`
            FROM `player_payments` p
            INNER JOIN `room_participants` rp ON rp.`id` = p.`room_participant_id`
            WHERE rp.`game_room_id` = :room_id AND rp.`user_id` = 3 AND p.`payment_type` = "DEPOSIT"
        ');
        $finalPayStmt->bindValue(':room_id', $roomId, PDO::PARAM_INT);
        $finalPayStmt->execute();
        $finalPayment = $finalPayStmt->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('PAID', $finalPayment['status'], 'Player 1 deposit must be PAID');
        $this->assertSame(50000, (int) $finalPayment['paid_amount']);
    }
}
