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
            $stmt = $this->pdo->prepare(
                'INSERT INTO `notifications` (`sender_id`, `title`, `message`, `notification_type`, `created_at`)
                 VALUES (:sender_id, :title, :message, :notification_type, NOW())'
            );
            $stmt->bindValue(':sender_id', $senderId, $senderId !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $stmt->bindValue(':title', $title, PDO::PARAM_STR);
            $stmt->bindValue(':message', $message, PDO::PARAM_STR);
            $stmt->bindValue(':notification_type', $notificationType, PDO::PARAM_STR);
            $stmt->execute();

            $notificationId = (int) $this->pdo->lastInsertId();

            if (!empty($recipientUserIds)) {
                $recipientStmt = $this->pdo->prepare(
                    'INSERT INTO `notification_recipient_states` (`notification_id`, `user_id`, `is_read`, `created_at`)
                     VALUES (:notification_id, :user_id, 0, NOW())'
                );

                foreach ($recipientUserIds as $userId) {
                    $recipientStmt->bindValue(':notification_id', $notificationId, PDO::PARAM_INT);
                    $recipientStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
                    $recipientStmt->execute();
                }
            }

            return $notificationId;
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
                'SELECT n.`id`, n.`title`, n.`message`, n.`notification_type`, n.`created_at`, s.`is_read`, s.`read_at`
                 FROM `notifications` n
                 INNER JOIN `notification_recipient_states` s ON n.`id` = s.`notification_id`
                 WHERE s.`user_id` = :user_id AND s.`is_read` = 0
                 ORDER BY n.`created_at` DESC
                 LIMIT :limit'
            );
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        });
    }
}
