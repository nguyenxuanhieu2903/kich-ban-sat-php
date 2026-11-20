<?php

declare(strict_types=1);

namespace App\Features\GameroomManagement\Controllers;

use App\Core\Middleware\AuthMiddleware;
use App\Core\Request;
use App\Core\Response;
use App\Features\Notification\Repositories\NotificationRepository;
use App\Infrastructure\Database\DatabaseConnection;
use PDO;

class AdminGameRoomController
{
    protected PDO $pdo;
    protected NotificationRepository $notiRepo;

    public function __construct(?PDO $pdo = null, ?NotificationRepository $notiRepo = null)
    {
        $this->pdo = $pdo ?? DatabaseConnection::getInstance();
        $this->notiRepo = $notiRepo ?? new NotificationRepository($this->pdo);

        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }
    }

    /**
     * POST /api/v1/admin/rooms/{id}/cancel
     * Hủy phòng chơi bởi Quản trị viên và tự động kích hoạt luồng đánh giá hoàn cọc 24 giờ
     * Body: reason (string, required)
     */
    public function cancel(Request $request, string $id): Response
    {
        $auth = AuthMiddleware::authenticate($request, ['ADMIN']);
        if ($auth instanceof Response) {
            return $auth;
        }

        $roomId = (int) $id;
        $body = $request->getBodyParams();
        $reason = trim((string) ($body['reason'] ?? ''));

        if ($reason === '') {
            return Response::fail('Vui lòng nêu rõ lý do hủy phòng chơi', 'VALIDATION_ERROR', 422);
        }

        // 1. Lấy thông tin phòng
        $rStmt = $this->pdo->prepare('SELECT `id`, `title`, `status` FROM `game_rooms` WHERE `id` = :id LIMIT 1');
        $rStmt->bindValue(':id', $roomId, PDO::PARAM_INT);
        $rStmt->execute();
        $room = $rStmt->fetch(PDO::FETCH_ASSOC);

        if ($room === false) {
            return Response::fail('Phòng chơi không tồn tại', 'ROOM_NOT_FOUND', 404);
        }

        if (in_array($room['status'], ['CANCELLED', 'COMPLETED'], true)) {
            return Response::fail('Phòng chơi đã ở trạng thái kết thúc (Trạng thái: ' . $room['status'] . ')', 'INVALID_ROOM_STATUS', 400);
        }

        // 2. Lấy thông tin Session nếu có để đánh giá mốc 24 giờ
        $sStmt = $this->pdo->prepare('SELECT `id`, `start_at`, `status` FROM `sessions` WHERE `game_room_id` = :room_id LIMIT 1');
        $sStmt->bindValue(':room_id', $roomId, PDO::PARAM_INT);
        $sStmt->execute();
        $session = $sStmt->fetch(PDO::FETCH_ASSOC);

        $isEligibleForRefund = true;
        $hoursUntilSession = null;
        $sessionStartAt = null;

        if ($session !== false && !empty($session['start_at'])) {
            $sessionStartAt = (string) $session['start_at'];
            $diffSeconds = strtotime($sessionStartAt) - time();
            $hoursUntilSession = round($diffSeconds / 3600, 2);

            // Quy tắc V16: Ít nhất 24 giờ trước giờ chơi mới đủ điều kiện hoàn tiền
            if ($diffSeconds < (24 * 3600)) {
                $isEligibleForRefund = false;
            }
        }

        $this->pdo->beginTransaction();
        try {
            // 3. Cập nhật game_rooms
            $upRoom = $this->pdo->prepare(
                'UPDATE `game_rooms`
                 SET `status` = \'CANCELLED\', `cancelled_at` = NOW(), `cancel_reason` = :reason, `updated_at` = NOW()
                 WHERE `id` = :id'
            );
            $upRoom->bindValue(':reason', $reason, PDO::PARAM_STR);
            $upRoom->bindValue(':id', $roomId, PDO::PARAM_INT);
            $upRoom->execute();

            // 4. Cập nhật sessions nếu có
            if ($session !== false) {
                $upSess = $this->pdo->prepare(
                    'UPDATE `sessions`
                     SET `status` = \'CANCELLED\', `cancelled_at` = NOW(), `cancel_reason` = :reason, `updated_at` = NOW()
                     WHERE `game_room_id` = :room_id'
                );
                $upSess->bindValue(':reason', $reason, PDO::PARAM_STR);
                $upSess->bindValue(':room_id', $roomId, PDO::PARAM_INT);
                $upSess->execute();
            }

            // 5. Cập nhật room_participants
            $upParts = $this->pdo->prepare(
                'UPDATE `room_participants`
                 SET `status` = \'CANCELLED_BY_ADMIN\', `cancelled_at` = NOW(), `cancel_reason` = :reason, `updated_at` = NOW()
                 WHERE `game_room_id` = :room_id AND `status` IN (\'PENDING_DEPOSIT\', \'DEPOSITED\', \'LEAVE_REQUESTED\')'
            );
            $upParts->bindValue(':reason', $reason, PDO::PARAM_STR);
            $upParts->bindValue(':room_id', $roomId, PDO::PARAM_INT);
            $upParts->execute();

            // 6. Xử lý hoàn tiền cho các khoản thanh toán đã nạp tiền (paid_amount > 0)
            $payStmt = $this->pdo->prepare(
                'SELECT p.`id`, p.`room_participant_id`, p.`paid_amount`, rp.`user_id`
                 FROM `player_payments` p
                 INNER JOIN `room_participants` rp ON rp.`id` = p.`room_participant_id`
                 WHERE rp.`game_room_id` = :room_id AND p.`paid_amount` > 0'
            );
            $payStmt->bindValue(':room_id', $roomId, PDO::PARAM_INT);
            $payStmt->execute();
            $paidList = $payStmt->fetchAll(PDO::FETCH_ASSOC);

            $refundRecords = [];
            $insRef = $this->pdo->prepare(
                'INSERT INTO `refunds` (`player_payment_id`, `amount`, `reason`, `status`, `requested_at`, `reviewed_by`, `reviewed_at`, `review_reason`, `created_at`, `updated_at`)
                 VALUES (:payment_id, :amount, :reason, :status, NOW(), :reviewed_by, NOW(), :review_reason, NOW(), NOW())'
            );

            $refundStatus = $isEligibleForRefund ? 'APPROVED' : 'REJECTED';
            $reviewNote = $isEligibleForRefund
                ? 'Đủ điều kiện hoàn cọc (hủy trước mốc 24h so với giờ chơi)'
                : 'Không đủ điều kiện hoàn cọc (hủy muộn hơn mốc 24h trước giờ chơi)';

            foreach ($paidList as $payment) {
                $payId = (int) $payment['id'];
                $amount = (int) $payment['paid_amount'];

                $insRef->bindValue(':payment_id', $payId, PDO::PARAM_INT);
                $insRef->bindValue(':amount', $amount, PDO::PARAM_INT);
                $insRef->bindValue(':reason', $reason, PDO::PARAM_STR);
                $insRef->bindValue(':status', $refundStatus, PDO::PARAM_STR);
                $insRef->bindValue(':reviewed_by', (int) $auth['id'], PDO::PARAM_INT);
                $insRef->bindValue(':review_reason', $reviewNote, PDO::PARAM_STR);
                $insRef->execute();

                $refundRecords[] = [
                    'refund_id' => (int) $this->pdo->lastInsertId(),
                    'payment_id' => $payId,
                    'user_id' => (int) $payment['user_id'],
                    'amount' => $amount,
                    'status' => $refundStatus,
                    'note' => $reviewNote,
                ];

                // Thông báo hoàn tiền cho từng user
                $this->notiRepo->createNotification(
                    'Thông báo hủy phòng & hoàn tiền',
                    "Phòng '{$room['title']}' đã bị hủy bởi quản trị viên. Kết quả hoàn tiền: {$reviewNote} (Số tiền: " . number_format($amount) . " VNĐ).",
                    'REFUND',
                    null,
                    [(int) $payment['user_id']]
                );
            }

            $this->pdo->commit();

            return Response::success([
                'game_room_id' => $roomId,
                'status' => 'CANCELLED',
                'cancel_reason' => $reason,
                'session_start_at' => $sessionStartAt,
                'hours_until_session' => $hoursUntilSession,
                'is_eligible_for_refund' => $isEligibleForRefund,
                'refunds' => $refundRecords,
            ], 'Đã hủy phòng chơi và hoàn tất quy trình đánh giá hoàn tiền');
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
