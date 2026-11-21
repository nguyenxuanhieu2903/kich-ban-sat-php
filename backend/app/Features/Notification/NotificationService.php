<?php

declare(strict_types=1);

namespace App\Features\Notification;

use App\Core\Exceptions\HttpException;
use App\Core\Exceptions\NotFoundException;
use App\Infrastructure\Database\DatabaseConnection;
use PDO;

class NotificationService
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DatabaseConnection::getInstance();
    }

    /**
     * @param array<string, mixed>|null $payload
     * @return array<string, mixed>
     */
    public function create(
        int $userId,
        string $eventType,
        string $title,
        string $body,
        ?array $payload = null
    ): array {
        $stmt = $this->pdo->prepare(
            "INSERT INTO notifications (user_id, event_type, title, body, payload, is_read)
             VALUES (:user_id, :event_type, :title, :body, :payload, 0)"
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':event_type' => strtoupper(trim($eventType)),
            ':title' => mb_substr(trim($title), 0, 180),
            ':body' => trim($body),
            ':payload' => $payload !== null ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
        ]);

        $id = (int) $this->pdo->lastInsertId();
        return $this->fetchByIdOrFail($id);
    }

    /** @return array<int, array<string, mixed>> */
    public function listForUser(int $userId, bool $unreadOnly = false, int $limit = 50): array
    {
        $limit = max(1, min(100, $limit));
        $sql = 'SELECT * FROM notifications WHERE user_id = :user_id';
        if ($unreadOnly) {
            $sql .= ' AND is_read = 0';
        }
        $sql .= " ORDER BY id DESC LIMIT {$limit}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':user_id' => $userId]);

        return array_map(fn (array $row): array => $this->formatNotification($row), $stmt->fetchAll());
    }

    /** @return array<string, mixed> */
    public function markAsRead(int $notificationId, int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM notifications WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $notificationId]);
        $row = $stmt->fetch();

        if ($row === false) {
            throw new NotFoundException('Không tìm thấy thông báo');
        }
        if ((int) $row['user_id'] !== $userId) {
            throw new HttpException('Bạn không có quyền thao tác trên thông báo của người khác', 403);
        }

        $upd = $this->pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = :id');
        $upd->execute([':id' => $notificationId]);

        return $this->fetchByIdOrFail($notificationId);
    }

    public function markAllAsRead(int $userId): int
    {
        $upd = $this->pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = :user_id AND is_read = 0');
        $upd->execute([':user_id' => $userId]);

        return $upd->rowCount();
    }

    /** @return array<string, mixed> */
    private function fetchByIdOrFail(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM notifications WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        if ($row === false) {
            throw new NotFoundException('Không tìm thấy thông báo');
        }

        return $this->formatNotification($row);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function formatNotification(array $row): array
    {
        $payload = $row['payload'] !== null ? json_decode((string) $row['payload'], true) : null;

        return [
            'id' => (int) $row['id'],
            'user_id' => (int) $row['user_id'],
            'event_type' => (string) $row['event_type'],
            'title' => (string) $row['title'],
            'body' => (string) $row['body'],
            'payload' => is_array($payload) ? $payload : null,
            'is_read' => (int) $row['is_read'] === 1,
            'created_at' => (string) ($row['created_at'] ?? ''),
        ];
    }
}