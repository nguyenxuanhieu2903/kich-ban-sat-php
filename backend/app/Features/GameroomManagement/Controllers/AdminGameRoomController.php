<?php

declare(strict_types=1);

namespace App\Features\GameroomManagement\Controllers;

use App\Core\Middleware\AuthMiddleware;
use App\Core\Request;
use App\Core\Response;
use App\Features\Notification\Repositories\NotificationRepository;
use App\Infrastructure\Database\DatabaseConnection;
use App\Infrastructure\Logging\AuditLogger;
use PDO;

class AdminGameRoomController
{
    protected PDO $pdo;
    protected NotificationRepository $notiRepo;
    protected AuditLogger $auditLogger;

    public function __construct(?PDO $pdo = null, ?NotificationRepository $notiRepo = null, ?AuditLogger $auditLogger = null)
    {
        $this->pdo = $pdo ?? DatabaseConnection::getInstance();
        $this->notiRepo = $notiRepo ?? new NotificationRepository($this->pdo);
        $this->auditLogger = $auditLogger ?? new AuditLogger($this->pdo);

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

            // 5b. Thu hồi phân công quản trò nếu có ca đang PENDING hoặc ACCEPTED
            $upDm = $this->pdo->prepare(
                'UPDATE `dm_assignments`
                 SET `status` = \'REVOKED\', `revoked_at` = NOW(),
                     `reason` = :reason, `updated_at` = NOW()
                 WHERE `game_room_id` = :room_id AND `status` IN (\'PENDING\', \'ACCEPTED\')'
            );
            $dmRevokeReason = 'Phòng chơi đã bị hủy bởi quản trị viên: ' . $reason;
            $upDm->bindValue(':reason', $dmRevokeReason, PDO::PARAM_STR);
            $upDm->bindValue(':room_id', $roomId, PDO::PARAM_INT);
            $upDm->execute();

            // Lấy danh sách DM cần thông báo
            $dmStmt = $this->pdo->prepare('SELECT DISTINCT `dm_user_id` FROM `dm_assignments` WHERE `game_room_id` = :room_id AND `status` = \'REVOKED\' AND `revoked_at` >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)');
            $dmStmt->bindValue(':room_id', $roomId, PDO::PARAM_INT);
            $dmStmt->execute();
            $dmUserIds = $dmStmt->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($dmUserIds)) {
                $this->notiRepo->createNotification(
                    'Ca dẫn kịch bản đã bị hủy',
                    "Phòng '{$room['title']}' đã bị hủy bởi Quản trị viên. Lý do: {$reason}.",
                    'DM_ASSIGNMENT',
                    (int) $auth['id'],
                    array_map('intval', $dmUserIds)
                );
            }

            // 5c. Hủy tất cả các hóa đơn chưa thanh toán (PENDING)
            $upUnpaid = $this->pdo->prepare(
                'UPDATE `player_payments` p
                 INNER JOIN `room_participants` rp ON rp.`id` = p.`room_participant_id`
                 SET p.`status` = \'CANCELLED\', p.`updated_at` = NOW()
                 WHERE rp.`game_room_id` = :room_id AND p.`status` = \'PENDING\''
            );
            $upUnpaid->bindValue(':room_id', $roomId, PDO::PARAM_INT);
            $upUnpaid->execute();

            // 6. Xử lý hoàn tiền cho các khoản thanh toán đã nạp tiền (paid_amount > 0)
            // Có kiểm tra NOT EXISTS để bảo đảm Idempotency chống trùng lặp refund
            $payStmt = $this->pdo->prepare(
                'SELECT p.`id`, p.`room_participant_id`, p.`paid_amount`, rp.`user_id`
                 FROM `player_payments` p
                 INNER JOIN `room_participants` rp ON rp.`id` = p.`room_participant_id`
                 WHERE rp.`game_room_id` = :room_id AND p.`paid_amount` > 0
                   AND NOT EXISTS (SELECT 1 FROM `refunds` r WHERE r.`player_payment_id` = p.`id`)'
            );
            $payStmt->bindValue(':room_id', $roomId, PDO::PARAM_INT);
            $payStmt->execute();
            $paidList = $payStmt->fetchAll(PDO::FETCH_ASSOC);

            $refundRecords = [];
            $insRef = $this->pdo->prepare(
                'INSERT INTO `refunds` (`player_payment_id`, `amount`, `reason`, `status`, `requested_at`, `reviewed_by`, `reviewed_at`, `completed_at`, `review_reason`, `created_at`, `updated_at`)
                 VALUES (:payment_id, :amount, :reason, :status, NOW(), :reviewed_by, NOW(), :completed_at, :review_reason, NOW(), NOW())'
            );

            $refundStatus = $isEligibleForRefund ? 'APPROVED' : 'REJECTED';
            $reviewNote = $isEligibleForRefund
                ? 'Đủ điều kiện hoàn cọc (hủy trước mốc 24h so với giờ chơi)'
                : 'Không đủ điều kiện hoàn cọc (hủy muộn hơn mốc 24h trước giờ chơi)';
            $completedAtValue = $isEligibleForRefund ? date('Y-m-d H:i:s') : null;

            foreach ($paidList as $payment) {
                $payId = (int) $payment['id'];
                $amount = (int) $payment['paid_amount'];

                $insRef->bindValue(':payment_id', $payId, PDO::PARAM_INT);
                $insRef->bindValue(':amount', $amount, PDO::PARAM_INT);
                $insRef->bindValue(':reason', $reason, PDO::PARAM_STR);
                $insRef->bindValue(':status', $refundStatus, PDO::PARAM_STR);
                $insRef->bindValue(':reviewed_by', (int) $auth['id'], PDO::PARAM_INT);
                $insRef->bindValue(':completed_at', $completedAtValue, $completedAtValue === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                $insRef->bindValue(':review_reason', $reviewNote, PDO::PARAM_STR);
                $insRef->execute();
                $refundId = (int) $this->pdo->lastInsertId();

                if ($isEligibleForRefund) {
                    // Cập nhật player_payments sang CANCELLED
                    $upPay = $this->pdo->prepare('UPDATE `player_payments` SET `status` = \'CANCELLED\', `updated_at` = NOW() WHERE `id` = :id');
                    $upPay->bindValue(':id', $payId, PDO::PARAM_INT);
                    $upPay->execute();

                    // Ghi nhận giao dịch hoàn tiền vào payment_transactions
                    $idempotencyKey = 'ADMIN_CANCEL_REFUND_' . $payId;
                    $txRef = 'RF_' . strtoupper(bin2hex(random_bytes(8)));
                    $metadata = json_encode([
                        'action' => 'ADMIN_CANCEL_REFUND',
                        'refund_id' => $refundId,
                        'admin_user_id' => (int) $auth['id'],
                        'amount' => $amount,
                    ]);
                    $insTx = $this->pdo->prepare(
                        'INSERT INTO `payment_transactions` (`player_payment_id`, `idempotency_key`, `transaction_reference`,
                                                             `amount`, `status`, `provider_response`, `requested_at`, `processed_at`, `created_at`)
                         VALUES (:payment_id, :idempotency_key, :tx_ref,
                                 :amount, \'SUCCESS\', :provider_response, NOW(), NOW(), NOW())'
                    );
                    $insTx->bindValue(':payment_id', $payId, PDO::PARAM_INT);
                    $insTx->bindValue(':idempotency_key', $idempotencyKey, PDO::PARAM_STR);
                    $insTx->bindValue(':tx_ref', $txRef, PDO::PARAM_STR);
                    $insTx->bindValue(':amount', $amount, PDO::PARAM_INT);
                    $insTx->bindValue(':provider_response', $metadata, PDO::PARAM_STR);
                    $insTx->execute();
                }

                $refundRecords[] = [
                    'refund_id' => $refundId,
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

            $this->auditLogger->log(
                'ROOM_CANCELLED_BY_ADMIN',
                'GAME_ROOM',
                $roomId,
                ['status' => $room['status']],
                [
                    'status' => 'CANCELLED',
                    'reason' => $reason,
                    'is_eligible_for_refund' => $isEligibleForRefund,
                    'refund_count' => count($refundRecords),
                ],
                (int) $auth['id'],
                $request
            );

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
