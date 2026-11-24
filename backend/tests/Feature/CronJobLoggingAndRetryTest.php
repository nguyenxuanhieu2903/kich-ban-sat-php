<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Features\GameroomManagement\Services\BackgroundJobService;
use App\Infrastructure\Config\Config;
use App\Infrastructure\Database\DatabaseConnection;
use App\Infrastructure\Logging\JobLogger;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Feature Test for Cron Job Logging & Retry Behavior (Phase D)
 */
class CronJobLoggingAndRetryTest extends TestCase
{
    protected PDO $pdo;
    protected string $testLogDir;
    protected JobLogger $logger;

    protected function setUp(): void
    {
        parent::setUp();
        $basePath = dirname(__DIR__, 2);
        Config::load($basePath);
        $this->pdo = DatabaseConnection::getInstance();

        $this->testLogDir = $basePath . '/storage/logs/test_' . bin2hex(random_bytes(3));
        $this->logger = new JobLogger($this->testLogDir);
    }

    protected function tearDown(): void
    {
        // Cleanup test log files
        if (is_dir($this->testLogDir)) {
            $files = glob($this->testLogDir . '/*');
            foreach ($files as $file) {
                @unlink($file);
            }
            @rmdir($this->testLogDir);
        }
        parent::tearDown();
    }

    public function testJobLoggerWritesFormattedLogLines(): void
    {
        $this->logger->info('Test Info Message', ['foo' => 'bar']);
        $this->logger->logJob('testJob', 'SUCCESS', 45.2, 10, 0);

        $filePath = $this->logger->getLogFilePath();
        $this->assertFileExists($filePath);

        $content = file_get_contents($filePath);
        $this->assertStringContainsString('[INFO] Test Info Message {"foo":"bar"}', $content);
        $this->assertStringContainsString('Cron Job Execution: testJob [SUCCESS]', $content);
        $this->assertStringContainsString('"duration_ms":45.2', $content);
        $this->assertStringContainsString('"processed":10', $content);
    }

    public function testExecuteWithRetryRecoversFromTransientFailure(): void
    {
        $service = new BackgroundJobService($this->pdo, null, $this->logger);

        $attempts = 0;
        $operation = function () use (&$attempts): int {
            $attempts++;
            if ($attempts < 3) {
                // Simulate transient MySQL deadlock (Error 1213)
                $pdoEx = new \PDOException('Deadlock found when trying to get lock; try restarting transaction');
                $pdoEx->errorInfo = ['40001', 1213, 'Deadlock'];
                throw $pdoEx;
            }
            return 42;
        };

        $result = $service->executeWithRetry($operation, 'testTransientRecovery', 3, 5);
        $this->assertSame(42, $result, 'Operation must recover on attempt 3');
        $this->assertSame(3, $attempts, 'Operation must have attempted 3 times');

        $logContent = file_get_contents($this->logger->getLogFilePath());
        $this->assertStringContainsString('failed on attempt 1/3, retrying...', $logContent);
        $this->assertStringContainsString('failed on attempt 2/3, retrying...', $logContent);
        $this->assertStringContainsString('testTransientRecovery [SUCCESS]', $logContent);
    }

    public function testExecuteWithRetryThrowsAfterExceedingMaxRetries(): void
    {
        $service = new BackgroundJobService($this->pdo, null, $this->logger);

        $operation = function (): int {
            $pdoEx = new \PDOException('Deadlock found when trying to get lock');
            $pdoEx->errorInfo = ['40001', 1213, 'Deadlock'];
            throw $pdoEx;
        };

        $this->expectException(\PDOException::class);
        $service->executeWithRetry($operation, 'testFatalDeadlock', 2, 5);
    }
}
