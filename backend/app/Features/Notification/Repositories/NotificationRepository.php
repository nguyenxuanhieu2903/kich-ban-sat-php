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
        array $recipientUserIds,
        ?string $referenceType = null,
        ?int $referenceId = null
    ): int {
        return $this->executeQuery(function () use ($title, $message, $notificationType, $senderId, $recipientUserIds, $referenceType, $referenceId): int {
            $lastId = 0;
            $targets = !empty($recipientUserIds) ? array_values(array_unique(array_map('intval', $recipientUserIds))) : ($senderId !== null ? [$senderId] : []);

            $stmt = $this->pdo->prepare(
                'INSERT INTO `notifications` (`user_id`, `type`, `title`, `content`, `reference_type`, `reference_id`, `is_read`, `created_at`)
                 VALUES (:user_id, :type, :title, :content, :ref_type, :ref_id, 0, NOW())'
            );

            foreach ($targets as $uid) {
                $stmt->bindValue(':user_id', $uid, PDO::PARAM_INT);
                $stmt->bindValue(':type', $notificationType, PDO::PARAM_STR);
                $stmt->bindValue(':title', $title, PDO::PARAM_STR);
                $stmt->bindValue(':content', $message, PDO::PARAM_STR);
                $stmt->bindValue(':ref_type', $referenceType, $referenceType !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
                $stmt->bindValue(':ref_id', $referenceId, $referenceId !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
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
                        `content` AS `message`, `content`, `reference_type`, `reference_id`, `is_read`, `read_at`, `created_at`
                 FROM `notifications`
                 WHERE `user_id` = :user_id AND `is_read` = 0
                 ORDER BY `created_at` DESC, `id` DESC
                 LIMIT :limit'
            );
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        });
    }

    /**
     * Get all notifications for user (strictly scoped by user_id and role visibility rules).
     *
     * @return array<int, array<string, mixed>>
     */
    public function listByUserId(int $userId, int $limit = 50, bool $unreadOnly = false, ?string $roleCode = null): array
    {
        return $this->executeQuery(function () use ($userId, $limit, $unreadOnly, $roleCode): array {
            $sql = 'SELECT `id`, `user_id`, `type`, `title`, `content`, `reference_type`, `reference_id`, `is_read`, `read_at`, `created_at`
                    FROM `notifications`
                    WHERE `user_id` = :user_id';

            $role = strtoupper(trim((string) ($roleCode ?? '')));
            if ($role === 'PLAYER') {
                // Người chơi chỉ thấy thông báo của chính mình, thông báo chung (BROADCAST_ALL) và quyền lợi người chơi (BROADCAST_PLAYER / PROMOTION)
                $sql .= " AND UPPER(`type`) NOT IN ('DM_ASSIGNMENT', 'ADMIN', 'BROADCAST_DM')";
            } elseif ($role === 'DM') {
                // Quản trò chỉ thấy thông báo ca trực của chính mình, thông báo chung (BROADCAST_ALL) và thông báo chung cho Quản trò (BROADCAST_DM)
                $sql .= " AND UPPER(`type`) NOT IN ('ADMIN', 'PROMOTION', 'BROADCAST_PLAYER', 'REFUND', 'HSSV')";
            } elseif ($role === 'ADMIN') {
                // Quản trị viên chỉ thấy thông báo điều hành/hệ thống của mình và thông báo chung
                $sql .= " AND UPPER(`type`) NOT IN ('DM_ASSIGNMENT', 'BROADCAST_DM')";
            }

            if ($unreadOnly) {
                $sql .= ' AND `is_read` = 0';
            }

            $sql .= ' ORDER BY `created_at` DESC, `id` DESC LIMIT :limit';

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', max(1, min(100, $limit)), PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        });
    }

    /**
     * Broadcast a notification to ALL users, all PLAYERs, or all DMs.
     *
     * @return array{type: string, recipient_count: int}
     */
    public function broadcastToAudience(
        string $audience,
        string $title,
        string $message,
        int $senderId,
        ?string $referenceType = null,
        ?int $referenceId = null
    ): array {
        return $this->executeQuery(function () use ($audience, $title, $message, $senderId, $referenceType, $referenceId): array {
            $aud = strtoupper(trim($audience));
            if ($aud === 'PLAYER') {
                $type = 'BROADCAST_PLAYER';
                $stmt = $this->pdo->query("SELECT u.`id` FROM `users` u INNER JOIN `roles` r ON r.`id` = u.`role_id` WHERE r.`code` = 'PLAYER' AND u.`status` = 'ACTIVE'");
            } elseif ($aud === 'DM') {
                $type = 'BROADCAST_DM';
                $stmt = $this->pdo->query("SELECT u.`id` FROM `users` u INNER JOIN `roles` r ON r.`id` = u.`role_id` WHERE r.`code` = 'DM' AND u.`status` = 'ACTIVE'");
            } else {
                $type = 'BROADCAST_ALL';
                $stmt = $this->pdo->query("SELECT `id` FROM `users` WHERE `status` = 'ACTIVE'");
            }

            $recipientIds = array_values(array_unique(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN))));
            if (!empty($recipientIds)) {
                $this->createNotification($title, $message, $type, $senderId, $recipientIds, $referenceType, $referenceId);
            }

            return [
                'type' => $type,
                'recipient_count' => count($recipientIds),
            ];
        });
    }

    /**
     * Mark a notification as read (with user ID IDOR guard).
     */
    public function markAsRead(int $notificationId, int $userId): bool
    {
        return $this->executeQuery(function () use ($notificationId, $userId): bool {
            $stmt = $this->pdo->prepare(
                'UPDATE `notifications`
                 SET `is_read` = 1, `read_at` = NOW()
                 WHERE `id` = :id AND `user_id` = :user_id'
            );
            $stmt->bindValue(':id', $notificationId, PDO::PARAM_INT);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->rowCount() > 0;
        });
    }

    /**
     * Mark all unread notifications for a user as read.
     */
    public function markAllAsRead(int $userId): int
    {
        return $this->executeQuery(function () use ($userId): int {
            $stmt = $this->pdo->prepare(
                'UPDATE `notifications` SET `is_read` = 1, `read_at` = NOW() WHERE `user_id` = :user_id AND `is_read` = 0'
            );
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->rowCount();
        });
    }
}
