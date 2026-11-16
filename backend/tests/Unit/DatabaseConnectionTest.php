<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Infrastructure\Database\ConnectionFactory;
use App\Infrastructure\Database\DatabaseConnection;
use App\Infrastructure\Database\DatabaseException;
use PDO;
use PHPUnit\Framework\TestCase;

class DatabaseConnectionTest extends TestCase
{
    protected function tearDown(): void
    {
        DatabaseConnection::setInstance(null);
        parent::tearDown();
    }

    public function testConnectionFailureThrowsDatabaseExceptionWithoutLeakingCredentials(): void
    {
        $sensitivePassword = 'SuperSecretUnrevealedPassword123!';

        try {
            ConnectionFactory::create([
                'host' => '127.0.0.1',
                'port' => 65530, // Unreachable port
                'database' => 'nonexistent_db',
                'username' => 'invalid_user',
                'password' => $sensitivePassword,
            ]);
            $this->fail('Expected DatabaseException was not thrown');
        } catch (DatabaseException $e) {
            // 1. Password must not appear in exception message
            $this->assertStringNotContainsString($sensitivePassword, $e->getMessage());
            $this->assertStringContainsString('Failed to connect to database [nonexistent_db]', $e->getMessage());

            // 2. Password must not appear in stringified exception / stack trace
            $this->assertStringNotContainsString($sensitivePassword, (string) $e);
            $this->assertStringNotContainsString($sensitivePassword, $e->getTraceAsString());

            // 3. Raw PDOException must not be chained as previous to prevent leaking constructor arguments
            $this->assertNull($e->getPrevious(), 'Chained PDOException must be omitted to prevent argument trace leakage');
        }
    }

    public function testTransactionHelperCommitsSuccessfully(): void
    {
        $sqlite = new PDO('sqlite::memory:');
        $sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $sqlite->exec('CREATE TABLE test_tx (id INTEGER PRIMARY KEY, name TEXT)');

        DatabaseConnection::setInstance($sqlite);

        $result = DatabaseConnection::transaction(function (PDO $pdo) {
            $stmt = $pdo->prepare('INSERT INTO test_tx (name) VALUES (:name)');
            $stmt->execute(['name' => 'committed_item']);
            return 'success_val';
        }, $sqlite);

        $this->assertSame('success_val', $result);
        $count = (int) $sqlite->query('SELECT COUNT(*) FROM test_tx')->fetchColumn();
        $this->assertSame(1, $count);
    }

    public function testTransactionHelperRollsBackOnException(): void
    {
        $sqlite = new PDO('sqlite::memory:');
        $sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $sqlite->exec('CREATE TABLE test_tx (id INTEGER PRIMARY KEY, name TEXT)');

        DatabaseConnection::setInstance($sqlite);

        try {
            DatabaseConnection::transaction(function (PDO $pdo) {
                $stmt = $pdo->prepare('INSERT INTO test_tx (name) VALUES (:name)');
                $stmt->execute(['name' => 'rollback_item']);
                throw new \RuntimeException('Triggering rollback');
            }, $sqlite);
            $this->fail('Exception was not re-thrown');
        } catch (\RuntimeException $e) {
            $this->assertSame('Triggering rollback', $e->getMessage());
        }

        $count = (int) $sqlite->query('SELECT COUNT(*) FROM test_tx')->fetchColumn();
        $this->assertSame(0, $count);
    }

    public function testCheckConnectivityReturnsFalseOnBrokenConnection(): void
    {
        $brokenMock = $this->createMock(PDO::class);
        $brokenMock->method('query')->willThrowException(new \PDOException('Connection broken'));

        $this->assertFalse(DatabaseConnection::checkConnectivity($brokenMock));
    }
}
