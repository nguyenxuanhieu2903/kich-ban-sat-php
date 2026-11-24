<?php

declare(strict_types=1);

namespace App\Infrastructure\Logging;

use App\Core\Request;
use App\Infrastructure\Database\DatabaseConnection;
use PDO;

class AuditLogger
{
    protected ?PDO $pdo;
    protected string $logDir;
    protected static bool $tableEnsured = false;

    public function __construct(?PDO $pdo = null, ?string $logDir = null)
    {
        $this->pdo = $pdo ?? DatabaseConnection::getInstance();
        $this->logDir = $logDir ?? (dirname(__DIR__, 3) . '/storage/logs');
        if (!is_dir($this->logDir)) {
            @mkdir($this->logDir, 0775, true);
        }

        $this->ensureTable();
    }

    protected function ensureTable(): void
    {
        if (self::$tableEnsured || $this->pdo === null) {
            return;
        }

        try {
            $sql = 'CREATE TABLE IF NOT EXISTS `audit_logs` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id` BIGINT UNSIGNED NULL,
                `action` VARCHAR(100) NOT NULL,
                `entity_type` VARCHAR(80) NOT NULL,
                `entity_id` BIGINT UNSIGNED NULL,
                `old_values` JSON NULL,
                `new_values` JSON NULL,
                `ip_address` VARCHAR(45) NULL,
                `user_agent` TEXT NULL,
                `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
                PRIMARY KEY (`id`),
                KEY `idx_audit_user_action` (`user_id`, `action`),
                KEY `idx_audit_entity` (`entity_type`, `entity_id`),
                KEY `idx_audit_created` (`created_at`)
            ) ENGINE=InnoDB';

            $this->pdo->exec($sql);
            self::$tableEnsured = true;
        } catch (\Throwable) {
            // Fail safely to file logging if DB DDL is restricted
        }
    }

    public function getLogFilePath(): string
    {
        return $this->logDir . '/audit-' . date('Y-m-d') . '.log';
    }

    /**
     * Record an audit event to both database and audit log file.
     */
    public function log(
        string $action,
        string $entityType,
        ?int $entityId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $userId = null,
        ?Request $request = null
    ): void {
        $actorId = $userId ?? ($_SESSION['user_id'] ?? null);
        if ($actorId !== null) {
            $actorId = (int) $actorId;
        }

        $ip = $request ? ($request->getHeader('X-Forwarded-For') ?? $request->getHeader('Remote-Addr') ?? '127.0.0.1') : ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        $userAgent = $request ? $request->getHeader('User-Agent') : ($_SERVER['HTTP_USER_AGENT'] ?? 'CLI/System');

        // 1. Log to database
        if ($this->pdo !== null) {
            try {
                $stmt = $this->pdo->prepare('
                    INSERT INTO `audit_logs` (`user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`)
                    VALUES (:user_id, :action, :entity_type, :entity_id, :old_values, :new_values, :ip_address, :user_agent, NOW())
                ');
                $stmt->bindValue(':user_id', $actorId, $actorId !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
                $stmt->bindValue(':action', $action, PDO::PARAM_STR);
                $stmt->bindValue(':entity_type', $entityType, PDO::PARAM_STR);
                $stmt->bindValue(':entity_id', $entityId, $entityId !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
                $stmt->bindValue(':old_values', $oldValues !== null ? json_encode($oldValues, JSON_UNESCAPED_UNICODE) : null, $oldValues !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
                $stmt->bindValue(':new_values', $newValues !== null ? json_encode($newValues, JSON_UNESCAPED_UNICODE) : null, $newValues !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
                $stmt->bindValue(':ip_address', $ip, PDO::PARAM_STR);
                $stmt->bindValue(':user_agent', substr((string) $userAgent, 0, 500), PDO::PARAM_STR);
                $stmt->execute();
            } catch (\Throwable) {
            }
        }

        // 2. Log to disk audit file
        $entry = [
            'timestamp' => date('Y-m-d H:i:s.v'),
            'user_id' => $actorId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old' => $oldValues,
            'new' => $newValues,
            'ip' => $ip,
            'user_agent' => $userAgent,
        ];
        $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        @file_put_contents($this->getLogFilePath(), $line, FILE_APPEND | LOCK_EX);
    }
}
