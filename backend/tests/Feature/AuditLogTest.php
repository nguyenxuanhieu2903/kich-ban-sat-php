<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Application;
use App\Core\Request;
use App\Infrastructure\Config\Config;
use App\Infrastructure\Database\DatabaseConnection;
use App\Infrastructure\Logging\AuditLogger;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Feature Test for Sensitive Action Audit Logging (Phase D)
 */
class AuditLogTest extends TestCase
{
    protected Application $app;
    protected PDO $pdo;
    protected AuditLogger $logger;

    protected function setUp(): void
    {
        parent::setUp();
        $basePath = dirname(__DIR__, 2);
        Config::load($basePath);
        $this->app = new Application($basePath);
        $_SESSION = [];

        $this->pdo = DatabaseConnection::getInstance();
        $this->logger = new AuditLogger($this->pdo);

        $routesFile = $basePath . '/routes/api.php';
        $router = $this->app->getRouter();
        require $routesFile;
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $this->pdo->exec('DELETE FROM `audit_logs` WHERE `action` LIKE "TEST_%"');
        parent::tearDown();
    }

    public function testAuditLoggerRecordsToDatabaseAndDisk(): void
    {
        $action = 'TEST_SECURITY_ACTION_' . bin2hex(random_bytes(3));
        $this->logger->log(
            $action,
            'TEST_ENTITY',
            999,
            ['old_role' => 'PLAYER'],
            ['new_role' => 'DM'],
            1
        );

        // Verify DB entry
        $stmt = $this->pdo->prepare('SELECT * FROM `audit_logs` WHERE `action` = :act LIMIT 1');
        $stmt->bindValue(':act', $action);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNotEmpty($row, 'Audit record must be saved in audit_logs DB table');
        $this->assertSame('TEST_ENTITY', $row['entity_type']);
        $this->assertSame(999, (int) $row['entity_id']);
        $this->assertSame(1, (int) $row['user_id']);

        // Verify Disk Log entry
        $filePath = $this->logger->getLogFilePath();
        $this->assertFileExists($filePath);
        $content = file_get_contents($filePath);
        $this->assertStringContainsString($action, $content);
        $this->assertStringContainsString('TEST_ENTITY', $content);
    }

    public function testAdminRoleChangeGeneratesAuditLog(): void
    {
        $_SESSION = ['user_id' => 1, 'role' => 'ADMIN'];

        // Dispatch role assignment request
        $req = new Request('PUT', '/api/v1/admin/users/4/role', [], ['role_code' => 'PLAYER']);
        $res = $this->app->dispatch($req);
        $this->assertSame(200, $res->getStatusCode());

        // Check that USER_ROLE_CHANGED was logged
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM `audit_logs` WHERE `action` = "USER_ROLE_CHANGED" AND `entity_id` = 4');
        $stmt->execute();
        $this->assertGreaterThan(0, (int) $stmt->fetchColumn(), 'USER_ROLE_CHANGED must be recorded in audit log');
    }
}
