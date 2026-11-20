<?php

declare(strict_types=1);

namespace App\Features\Notification\Repositories;

use App\Infrastructure\Database\BaseRepository;
use PDO;

class NotificationRepository extends BaseRepository
{
    /**
     * Create notification and fan out to recipients.
     *
     * @param int[] $recipientUserIds
     */
    public function createNotification(
        string $title,
        string $message,
        string $notificationType,
        ?int $senderId,
        array $recipientUserIds
    ): int {
        return $this->executeQuery(function () use ($title, $message, $notificationType, $senderId, $recipientUserIds): int {
            $lastId = 0;
            $targets = !empty($recipientUserIds) ? $recipientUserIds : ($senderId !== null ? [$senderId] : []);

            $stmt = $this->pdo->prepare(
                'INSERT INTO `notifications` (`user_id`, `type`, `title`, `content`, `is_read`, `created_at`)
                 VALUES (:user_id, :type, :title, :content, 0, NOW())'
            );

            foreach ($targets as $uid) {
                $stmt->bindValue(':user_id', $uid, PDO::PARAM_INT);
                $stmt->bindValue(':type', $notificationType, PDO::PARAM_STR);
                $stmt->bindValue(':title', $title, PDO::PARAM_STR);
                $stmt->bindValue(':content', $message, PDO::PARAM_STR);
                $stmt->execute();
                $lastId = (int) $this->pdo->lastInsertId();
            }

            return $lastId;
        });
    }

    /**
     * Get unread notifications for a specific user.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getUnreadByUserId(int $userId, int $limit = 20): array
    {
        return $this->executeQuery(function () use ($userId, $limit): array {
            $stmt = $this->pdo->prepare(
                'SELECT `id`, `user_id`, `type` AS `notification_type`, `type`, `title`,
                        `content` AS `message`, `content`, `is_read`, `read_at`, `created_at`
                 FROM `notifications`
                 WHERE `user_id` = :user_id AND `is_read` = 0
                 ORDER BY `created_at` DESC
                 LIMIT :limit'
            );
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        });
    }
}
