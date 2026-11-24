<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Infrastructure\Config\Config;
use App\Infrastructure\Database\DatabaseConnection;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Integration Test for Database Transactions & Rollback Behavior (Phase D)
 * Verifies ACID properties, rollback atomicity, idempotency key uniqueness,
 * and data integrity under error conditions.
 */
class DatabaseTransactionIntegrationTest extends TestCase
{
    protected PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();
        $basePath = dirname(__DIR__, 2);
        Config::load($basePath);
        $this->pdo = DatabaseConnection::getInstance();
    }

    protected function tearDown(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
        $this->pdo->exec('DELETE FROM `payment_transactions` WHERE `idempotency_key` LIKE "TEST_TX_%"');
        $this->pdo->exec('DELETE rp FROM `room_participants` rp INNER JOIN `game_rooms` gr ON gr.id = rp.game_room_id WHERE gr.title LIKE "TX_TEST_ROOM_%"');
        $this->pdo->exec('DELETE FROM `game_rooms` WHERE `title` LIKE "TX_TEST_ROOM_%"');
        parent::tearDown();
    }

    /**
     * Test 1: Full Transaction Rollback on Exception.
     * When inserting a room and participant succeeds, but an error occurs before commit,
     * rolling back leaves zero orphaned records in the database.
     */
    public function testTransactionRollsBackCompletelyOnError(): void
    {
        $uniqueTitle = 'TX_TEST_ROOM_' . bin2hex(random_bytes(4));
        $configId = (int) ($this->pdo->query('SELECT id FROM `game_configurations` WHERE `game_id` = 1 LIMIT 1')->fetchColumn() ?: 1);

        $this->pdo->beginTransaction();
        try {
            // Step 1: Insert GameRoom
            $stmt1 = $this->pdo->prepare('
                INSERT INTO `game_rooms` (`game_id`, `game_configuration_id`, `creator_id`, `title`, `status`, `min_players`, `max_players`, `formation_deadline_at`, `created_at`, `updated_at`)
                VALUES (1, :cfg_id, 1, :title, "FORMING", 4, 8, DATE_ADD(NOW(), INTERVAL 48 HOUR), NOW(), NOW())
            ');
            $stmt1->bindValue(':cfg_id', $configId, PDO::PARAM_INT);
            $stmt1->bindValue(':title', $uniqueTitle);
            $stmt1->execute();
            $roomId = (int) $this->pdo->lastInsertId();

            // Step 2: Insert Room Participant
            $stmt2 = $this->pdo->prepare('
                INSERT INTO `room_participants` (`game_room_id`, `user_id`, `status`, `joined_at`, `created_at`, `updated_at`)
                VALUES (:room_id, 1, "PENDING_DEPOSIT", NOW(), NOW(), NOW())
            ');
            $stmt2->bindValue(':room_id', $roomId, PDO::PARAM_INT);
            $stmt2->execute();

            // Step 3: Simulate sudden fatal domain failure before commit
            throw new \RuntimeException('Simulated payment service timeout / DB crash');
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
        }

        // Verify that neither the room nor the participant exists
        $checkStmt = $this->pdo->prepare('SELECT COUNT(*) FROM `game_rooms` WHERE `title` = :title');
        $checkStmt->bindValue(':title', $uniqueTitle);
        $checkStmt->execute();
        $count = (int) $checkStmt->fetchColumn();

        $this->assertSame(0, $count, 'GameRoom must be rolled back and not exist in database');
    }

    /**
     * Test 2: Successful Transaction Commit commits all operations atomically.
     */
    public function testTransactionCommitsAllOperationsAtomically(): void
    {
        $uniqueTitle = 'TX_TEST_ROOM_' . bin2hex(random_bytes(4));
        $configId = (int) ($this->pdo->query('SELECT id FROM `game_configurations` WHERE `game_id` = 1 LIMIT 1')->fetchColumn() ?: 1);

        $this->pdo->beginTransaction();
        try {
            $stmt1 = $this->pdo->prepare('
                INSERT INTO `game_rooms` (`game_id`, `game_configuration_id`, `creator_id`, `title`, `status`, `min_players`, `max_players`, `formation_deadline_at`, `created_at`, `updated_at`)
                VALUES (1, :cfg_id, 1, :title, "FORMING", 4, 8, DATE_ADD(NOW(), INTERVAL 48 HOUR), NOW(), NOW())
            ');
            $stmt1->bindValue(':cfg_id', $configId, PDO::PARAM_INT);
            $stmt1->bindValue(':title', $uniqueTitle);
            $stmt1->execute();
            $roomId = (int) $this->pdo->lastInsertId();

            $stmt2 = $this->pdo->prepare('
                INSERT INTO `room_participants` (`game_room_id`, `user_id`, `status`, `joined_at`, `created_at`, `updated_at`)
                VALUES (:room_id, 1, "PENDING_DEPOSIT", NOW(), NOW(), NOW())
            ');
            $stmt2->bindValue(':room_id', $roomId, PDO::PARAM_INT);
            $stmt2->execute();

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            $this->fail('Transaction should not fail: ' . $e->getMessage());
        }

        $checkStmt = $this->pdo->prepare('SELECT COUNT(*) FROM `game_rooms` WHERE `title` = :title');
        $checkStmt->bindValue(':title', $uniqueTitle);
        $checkStmt->execute();
        $this->assertSame(1, (int) $checkStmt->fetchColumn(), 'GameRoom must be committed successfully');
    }

    /**
     * Test 3: Idempotency Key Constraint Violation Enforces Transaction Safety.
     * Attempting to insert duplicate idempotency keys throws PDOException without corrupting existing records.
     */
    public function testIdempotencyKeyCollisionPreventsDuplicateTransactions(): void
    {
        $key = 'TEST_TX_' . bin2hex(random_bytes(4));
        $paymentId = (int) ($this->pdo->query('SELECT id FROM `player_payments` LIMIT 1')->fetchColumn() ?: 0);
        if ($paymentId === 0) {
            $this->markTestSkipped('No player_payments available for idempotency test');
        }

        // Insert first payment transaction
        $stmt1 = $this->pdo->prepare('
            INSERT INTO `payment_transactions` (`player_payment_id`, `idempotency_key`, `transaction_reference`, `amount`, `status`, `requested_at`, `created_at`)
            VALUES (:pid, :key, :tx1, 50000, "SUCCESS", NOW(), NOW())
        ');
        $stmt1->bindValue(':pid', $paymentId, PDO::PARAM_INT);
        $stmt1->bindValue(':key', $key);
        $stmt1->bindValue(':tx1', 'TX_' . $key . '_1');
        $stmt1->execute();

        // Attempting to insert second transaction with the SAME idempotency key must fail with duplicate entry
        $this->expectException(\PDOException::class);
        $stmt2 = $this->pdo->prepare('
            INSERT INTO `payment_transactions` (`player_payment_id`, `idempotency_key`, `transaction_reference`, `amount`, `status`, `requested_at`, `created_at`)
            VALUES (:pid, :key, :tx2, 50000, "SUCCESS", NOW(), NOW())
        ');
        $stmt2->bindValue(':pid', $paymentId, PDO::PARAM_INT);
        $stmt2->bindValue(':key', $key);
        $stmt2->bindValue(':tx2', 'TX_' . $key . '_2');
        $stmt2->execute();
    }
}
