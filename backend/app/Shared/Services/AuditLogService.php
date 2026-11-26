<?php

declare(strict_types=1);

namespace App\Shared\Services;

use App\Infrastructure\Database\DatabaseConnection;
use PDO;
use Throwable;

class AuditLogService
{
    protected PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DatabaseConnection::getInstance();
    }

    /**
     * Record an audit event safely (never breaks primary business flow).
     *
     * @param array<string, mixed> $metadata
     */
    public function log(
        ?int $actorId,
        string $action,
        string $entityType,
        ?int $entityId = null,
        array $metadata = [],
        ?string $ipAddress = null
    ): void {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO `audit_logs` (`actor_id`, `user_id`, `action`, `entity_type`, `entity_id`, `metadata`, `ip_address`, `created_at`)
                 VALUES (:actor_id, :user_id, :action, :entity_type, :entity_id, :metadata, :ip_address, NOW())'
            );
            $stmt->bindValue(':actor_id', $actorId, $actorId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':user_id', $actorId, $actorId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':action', $action, PDO::PARAM_STR);
            $stmt->bindValue(':entity_type', $entityType, PDO::PARAM_STR);
            $stmt->bindValue(':entity_id', $entityId, $entityId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':metadata', !empty($metadata) ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null, !empty($metadata) ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $ip = $ipAddress ?? ($_SERVER['REMOTE_ADDR'] ?? null);
            $stmt->bindValue(':ip_address', $ip, $ip === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->execute();
        } catch (Throwable) {
            // Do not fail business request if audit logging fails
        }
    }

    /**
     * List audit logs with pagination and optional filters.
     *
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function listLogs(int $page = 1, int $perPage = 20, ?string $action = null, ?string $entityType = null): array
    {
        $where = ['1 = 1'];
        $params = [];

        if ($action !== null && trim($action) !== '') {
            $where[] = 'al.`action` = :action';
            $params[':action'] = trim($action);
        }

        if ($entityType !== null && trim($entityType) !== '') {
            $where[] = 'al.`entity_type` = :entity_type';
            $params[':entity_type'] = trim($entityType);
        }

        $whereSql = implode(' AND ', $where);

        $cntStmt = $this->pdo->prepare("SELECT COUNT(*) FROM `audit_logs` al WHERE {$whereSql}");
        foreach ($params as $k => $v) {
            $cntStmt->bindValue($k, $v, PDO::PARAM_STR);
        }
        $cntStmt->execute();
        $total = (int) $cntStmt->fetchColumn();

        $offset = max(0, ($page - 1) * $perPage);
        $stmt = $this->pdo->prepare(
            "SELECT al.`id`, COALESCE(al.`actor_id`, al.`user_id`) AS `actor_id`,
                    u.`full_name` AS `actor_name`, u.`email` AS `actor_email`,
                    al.`action`, al.`entity_type`, al.`entity_id`, al.`metadata`, al.`ip_address`, al.`created_at`
             FROM `audit_logs` al
             LEFT JOIN `users` u ON u.`id` = COALESCE(al.`actor_id`, al.`user_id`)
             WHERE {$whereSql}
             ORDER BY al.`id` DESC
             LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($items as &$row) {
            if (!empty($row['metadata']) && is_string($row['metadata'])) {
                $row['metadata'] = json_decode($row['metadata'], true);
            }
        }

        return ['items' => $items, 'total' => $total];
    }
}