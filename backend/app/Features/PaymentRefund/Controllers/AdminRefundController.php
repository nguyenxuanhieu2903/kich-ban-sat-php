<?php

declare(strict_types=1);

namespace App\Features\PaymentRefund\Controllers;

use App\Core\Middleware\AuthMiddleware;
use App\Core\Request;
use App\Core\Response;
use App\Features\Notification\Repositories\NotificationRepository;
use App\Infrastructure\Database\DatabaseConnection;
use PDO;

class AdminRefundController
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
     * GET /api/v1/admin/refunds
     * Query: page, per_page, status
     */
    public function index(Request $request): Response
    {
        $auth = AuthMiddleware::authenticate($request, ['ADMIN']);
        if ($auth instanceof Response) {
            return $auth;
        }

        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(100, (int) $request->query('per_page', 20)));
        $offset = ($page - 1) * $perPage;
        $status = $request->query('status');

        $where = ['1 = 1'];
        $params = [];

        if ($status !== null && trim((string) $status) !== '' && $status !== 'ALL') {
            $where[] = 'r.`status` = :status';
            $params[':status'] = strtoupper(trim((string) $status));
        }

        $whereSql = implode(' AND ', $where);

        $cntStmt = $this->pdo->prepare("SELECT COUNT(*) FROM `refunds` r WHERE {$whereSql}");
        foreach ($params as $k => $v) {
            $cntStmt->bindValue($k, $v, PDO::PARAM_STR);
        }
        $cntStmt->execute();
        $total = (int) $cntStmt->fetchColumn();

        $sql = "SELECT r.`id`, r.`player_payment_id`, r.`amount`, r.`reason`, r.`status`,
                       r.`requested_at`, r.`reviewed_by`, r.`reviewed_at`, r.`review_reason`,
                       p.`paid_amount`, rp.`user_id`, u.`full_name` AS `user_name`, u.`email` AS `user_email`,
                       gr.`id` AS `game_room_id`, gr.`title` AS `room_title`
                FROM `refunds` r
                INNER JOIN `player_payments` p ON p.`id` = r.`player_payment_id`
                INNER JOIN `room_participants` rp ON rp.`id` = p.`room_participant_id`
                INNER JOIN `users` u ON u.`id` = rp.`user_id`
                INNER JOIN `game_rooms` gr ON gr.`id` = rp.`game_room_id`
                WHERE {$whereSql}
                ORDER BY r.`id` DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return Response::paginated($items, $page, $perPage, $total, 'Danh sách yêu cầu hoàn tiền');
    }

    /**
     * POST /api/v1/admin/refunds/{id}/approve
     */
    public function approve(Request $request, string $id): Response
    {
        $auth = AuthMiddleware::authenticate($request, ['ADMIN']);
        if ($auth instanceof Response) {
            return $auth;
        }

        $refundId = (int) $id;

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'SELECT r.`id`, r.`player_payment_id`, r.`amount`, r.`status`,
                        p.`room_participant_id`, p.`status` AS `payment_status`,
                        rp.`user_id`, gr.`id` AS `game_room_id`, gr.`title` AS `room_title`
                 FROM `refunds` r
                 INNER JOIN `player_payments` p ON p.`id` = r.`player_payment_id`
                 INNER JOIN `room_participants` rp ON rp.`id` = p.`room_participant_id`
                 INNER JOIN `game_rooms` gr ON gr.`id` = rp.`game_room_id`
                 WHERE r.`id` = :id
                 LIMIT 1 FOR UPDATE'
            );
            $stmt->bindValue(':id', $refundId, PDO::PARAM_INT);
            $stmt->execute();
            $refund = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($refund === false) {
                $this->pdo->rollBack();
                return Response::fail('Không tìm thấy yêu cầu hoàn tiền', 'REFUND_NOT_FOUND', 404);
            }

            // 1. Kiểm tra tính lũy kế (Idempotency) nếu đã duyệt / hoàn tất
            if ($refund['status'] === 'APPROVED' || $refund['status'] === 'COMPLETED') {
                $this->pdo->commit();
                return Response::success(['refund_id' => $refundId, 'status' => $refund['status']], 'Yêu cầu hoàn tiền đã được xử lý trước đó');
            }

            // 2. Cập nhật trạng thái refunds sang APPROVED (cho phép duyệt từ PENDING hoặc xem xét lại từ REJECTED)
            $upStmt = $this->pdo->prepare(
                'UPDATE `refunds`
                 SET `status` = \'APPROVED\',
                     `reviewed_by` = :reviewer,
                     `reviewed_at` = NOW(),
                     `completed_at` = NOW(),
                     `updated_at` = NOW()
                 WHERE `id` = :id'
            );
            $upStmt->bindValue(':reviewer', (int) $auth['id'], PDO::PARAM_INT);
            $upStmt->bindValue(':id', $refundId, PDO::PARAM_INT);
            $upStmt->execute();

            // 4. Đồng bộ trạng thái hóa đơn player_payments sang CANCELLED (Database V3 không có trạng thái REFUNDED)
            $paymentId = (int) $refund['player_payment_id'];
            $upPay = $this->pdo->prepare(
                'UPDATE `player_payments`
                 SET `status` = \'CANCELLED\',
                     `updated_at` = NOW()
                 WHERE `id` = :payment_id'
            );
            $upPay->bindValue(':payment_id', $paymentId, PDO::PARAM_INT);
            $upPay->execute();

            // 5. Ghi nhận giao dịch hoàn tiền thành công vào payment_transactions với Idempotency Key
            $idempotencyKey = 'REFUND_APPROVE_' . $refundId;
            $chkTx = $this->pdo->prepare('SELECT COUNT(*) FROM `payment_transactions` WHERE `idempotency_key` = :idempotency_key');
            $chkTx->bindValue(':idempotency_key', $idempotencyKey, PDO::PARAM_STR);
            $chkTx->execute();
            if ((int) $chkTx->fetchColumn() === 0) {
                $txRef = 'RF_' . strtoupper(bin2hex(random_bytes(8)));
                $metadata = json_encode([
                    'action' => 'ADMIN_REFUND_APPROVED',
                    'refund_id' => $refundId,
                    'admin_user_id' => (int) $auth['id'],
                    'amount' => (int) $refund['amount'],
                ]);
                $insTx = $this->pdo->prepare(
                    'INSERT INTO `payment_transactions` (`player_payment_id`, `idempotency_key`, `transaction_reference`,
                                                         `amount`, `status`, `provider_response`, `requested_at`, `processed_at`, `created_at`)
                     VALUES (:payment_id, :idempotency_key, :tx_ref,
                             :amount, \'SUCCESS\', :provider_response, NOW(), NOW(), NOW())'
                );
                $insTx->bindValue(':payment_id', $paymentId, PDO::PARAM_INT);
                $insTx->bindValue(':idempotency_key', $idempotencyKey, PDO::PARAM_STR);
                $insTx->bindValue(':tx_ref', $txRef, PDO::PARAM_STR);
                $insTx->bindValue(':amount', (int) $refund['amount'], PDO::PARAM_INT);
                $insTx->bindValue(':provider_response', $metadata, PDO::PARAM_STR);
                $insTx->execute();
            }

            $this->pdo->commit();

            // 6. Gửi thông báo cho khách hàng
            try {
                $amountFormatted = number_format((int) $refund['amount']);
                $this->notiRepo->createNotification(
                    'Hoàn tiền đã được phê duyệt',
                    "Yêu cầu hoàn cọc {$amountFormatted} VNĐ cho phòng '{$refund['room_title']}' đã được Admin phê duyệt thành công.",
                    'REFUND',
                    (int) $auth['id'],
                    [(int) $refund['user_id']]
                );
            } catch (\Throwable) {
                // Notification failure does not rollback committed transaction
            }

            return Response::success(['refund_id' => $refundId, 'status' => 'APPROVED'], 'Duyệt hoàn tiền thành công');
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * POST /api/v1/admin/refunds/{id}/reject
     * Body: reason (string, required, min 5 chars)
     */
    public function reject(Request $request, string $id): Response
    {
        $auth = AuthMiddleware::authenticate($request, ['ADMIN']);
        if ($auth instanceof Response) {
            return $auth;
        }

        $refundId = (int) $id;
        $body = $request->getBodyParams();
        $reason = trim((string) ($body['reason'] ?? ''));

        if ($reason === '' || mb_strlen($reason) < 5) {
            return Response::fail('Vui lòng nêu rõ lý do từ chối hoàn tiền (tối thiểu 5 ký tự)', 'VALIDATION_ERROR', 422);
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'SELECT r.`id`, r.`player_payment_id`, r.`amount`, r.`status`,
                        rp.`user_id`, gr.`title` AS `room_title`
                 FROM `refunds` r
                 INNER JOIN `player_payments` p ON p.`id` = r.`player_payment_id`
                 INNER JOIN `room_participants` rp ON rp.`id` = p.`room_participant_id`
                 INNER JOIN `game_rooms` gr ON gr.`id` = rp.`game_room_id`
                 WHERE r.`id` = :id
                 LIMIT 1 FOR UPDATE'
            );
            $stmt->bindValue(':id', $refundId, PDO::PARAM_INT);
            $stmt->execute();
            $refund = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($refund === false) {
                $this->pdo->rollBack();
                return Response::fail('Không tìm thấy yêu cầu hoàn tiền', 'REFUND_NOT_FOUND', 404);
            }

            // 1. Kiểm tra Idempotency nếu đã bị từ chối trước đó
            if ($refund['status'] === 'REJECTED') {
                $this->pdo->commit();
                return Response::success(['refund_id' => $refundId, 'status' => 'REJECTED'], 'Yêu cầu hoàn tiền đã bị từ chối trước đó');
            }

            // 2. Không cho phép từ chối đơn đã hoàn tất chi trả
            if ($refund['status'] === 'COMPLETED') {
                $this->pdo->rollBack();
                return Response::fail('Không thể từ chối yêu cầu hoàn tiền đã hoàn tất chi trả', 'REFUND_ALREADY_COMPLETED', 400);
            }

            // 3. Cập nhật trạng thái REJECTED
            $upStmt = $this->pdo->prepare(
                'UPDATE `refunds`
                 SET `status` = \'REJECTED\',
                     `reviewed_by` = :reviewer,
                     `reviewed_at` = NOW(),
                     `review_reason` = :reason,
                     `updated_at` = NOW()
                 WHERE `id` = :id'
            );
            $upStmt->bindValue(':reviewer', (int) $auth['id'], PDO::PARAM_INT);
            $upStmt->bindValue(':reason', $reason, PDO::PARAM_STR);
            $upStmt->bindValue(':id', $refundId, PDO::PARAM_INT);
            $upStmt->execute();

            $this->pdo->commit();

            // 4. Gửi thông báo cho khách hàng
            try {
                $this->notiRepo->createNotification(
                    'Yêu cầu hoàn tiền bị từ chối',
                    "Yêu cầu hoàn tiền của bạn cho phòng '{$refund['room_title']}' đã bị từ chối. Lý do: {$reason}.",
                    'REFUND',
                    (int) $auth['id'],
                    [(int) $refund['user_id']]
                );
            } catch (\Throwable) {
                // Ignore notification failure
            }

            return Response::success(['refund_id' => $refundId, 'status' => 'REJECTED'], 'Đã từ chối hoàn tiền');
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
