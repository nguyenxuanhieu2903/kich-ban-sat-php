<?php

declare(strict_types=1);

namespace App\Features\Audit;

use App\Core\Exceptions\HttpException;
use App\Infrastructure\Database\DatabaseConnection;
use PDO;

class AuditLogService
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DatabaseConnection::getInstance();
    }

    /**
     * @param array<string, mixed>|null $metadata
     * @return array<string, mixed>
     */
    public function record(
        ?int $actorUserId,
        string $action,
        string $targetType,
        int $targetId,
        ?string $reason = null,
        ?array $metadata = null
    ): array {
        $stmt = $this->pdo->prepare(
            "INSERT INTO audit_logs (actor_user_id, action, target_type, target_id, reason, metadata)
             VALUES (:actor_id, :action, :target_type, :target_id, :reason, :metadata)"
        );
        $stmt->execute([
            ':actor_id' => $actorUserId,
            ':action' => strtoupper(trim($action)),
            ':target_type' => strtoupper(trim($targetType)),
            ':target_id' => $targetId,
            ':reason' => ($reason !== null && trim($reason) !== '') ? mb_substr(trim($reason), 0, 500) : null,
            ':metadata' => $metadata !== null ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null,
        ]);

        $id = (int) $this->pdo->lastInsertId();
        $row = $this->pdo->query("SELECT * FROM audit_logs WHERE id = {$id} LIMIT 1")->fetch();

        return $this->formatLog(is_array($row) ? $row : [
            'id' => $id,
            'actor_user_id' => $actorUserId,
            'action' => strtoupper(trim($action)),
            'target_type' => strtoupper(trim($targetType)),
            'target_id' => $targetId,
            'reason' => $reason,
            'metadata' => $metadata !== null ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function listLogs(int $actorUserId, string $actorRole, array $filters = []): array
    {
        if (strtoupper($actorRole) !== 'ADMIN') {
            throw new HttpException('Chỉ quản trị viên mới có quyền xem nhật ký kiểm toán', 403);
        }
        $this->requireActiveAdmin($actorUserId);

        $where = [];
        $params = [];

        if (!empty($filters['target_type']) && is_string($filters['target_type'])) {
            $where[] = 'target_type = :target_type';
            $params[':target_type'] = strtoupper(trim($filters['target_type']));
        }
        if (!empty($filters['target_id']) && is_numeric($filters['target_id'])) {
            $where[] = 'target_id = :target_id';
            $params[':target_id'] = (int) $filters['target_id'];
        }
        if (!empty($filters['action']) && is_string($filters['action'])) {
            $where[] = 'action = :action';
            $params[':action'] = strtoupper(trim($filters['action']));
        }
        if (!empty($filters['actor_user_id']) && is_numeric($filters['actor_user_id'])) {
            $where[] = 'actor_user_id = :actor_user_id';
            $params[':actor_user_id'] = (int) $filters['actor_user_id'];
        }

        $limit = isset($filters['limit']) && is_numeric($filters['limit']) ? (int) $filters['limit'] : 50;
        $limit = max(1, min(200, $limit));

        $sql = 'SELECT * FROM audit_logs';
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= " ORDER BY id DESC LIMIT {$limit}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return array_map(fn (array $row): array => $this->formatLog($row), $stmt->fetchAll());
    }

    private function requireActiveAdmin(int $userId): void
    {
        $stmt = $this->pdo->prepare('SELECT id, role, is_active FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch();

        if ($user === false || (int) $user['is_active'] !== 1 || strtoupper((string) $user['role']) !== 'ADMIN') {
            throw new HttpException('Tài khoản quản trị không hợp lệ hoặc đã bị khóa', 403);
        }
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function formatLog(array $row): array
    {
        $meta = $row['metadata'] !== null ? json_decode((string) $row['metadata'], true) : null;

        return [
            'id' => (int) $row['id'],
            'actor_user_id' => $row['actor_user_id'] !== null ? (int) $row['actor_user_id'] : null,
            'action' => (string) $row['action'],
            'target_type' => (string) $row['target_type'],
            'target_id' => (int) $row['target_id'],
            'reason' => $row['reason'] !== null ? (string) $row['reason'] : null,
            'metadata' => is_array($meta) ? $meta : null,
            'created_at' => (string) ($row['created_at'] ?? ''),
        ];
    }
}