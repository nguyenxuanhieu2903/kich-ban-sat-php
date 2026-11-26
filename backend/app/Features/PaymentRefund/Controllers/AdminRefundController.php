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

            if ($refund['status'] !== 'PENDING') {
                $this->pdo->rollBack();
                return Response::fail(
                    'Yêu cầu hoàn tiền này đã được xử lý trước đó (Trạng thái: ' . $refund['status'] . ')',
                    'INVALID_REFUND_STATUS',
                    409
                );
            }

            $upStmt = $this->pdo->prepare(
                'UPDATE `refunds`
                 SET `status` = \'APPROVED\', `reviewed_by` = :reviewer, `reviewed_at` = NOW(), `updated_at` = NOW()
                 WHERE `id` = :id'
            );
            $upStmt->bindValue(':reviewer', (int) $auth['id'], PDO::PARAM_INT);
            $upStmt->bindValue(':id', $refundId, PDO::PARAM_INT);
            $upStmt->execute();

            // Ghi nhận nhật ký kiểm toán
            $auditService = new \App\Shared\Services\AuditLogService($this->pdo);
            $auditService->log((int) $auth['id'], 'APPROVE_REFUND', 'REFUND', $refundId, [
                'amount' => (int) $refund['amount'],
                'player_payment_id' => (int) $refund['player_payment_id'],
            ]);

            $this->notiRepo->createNotification(
                'Yêu cầu hoàn tiền đã được duyệt',
                "Yêu cầu hoàn tiền " . number_format((int) $refund['amount']) . " VNĐ cho phòng '{$refund['room_title']}' đã được Quản trị viên phê duyệt.",
                'REFUND',
                (int) $auth['id'],
                [(int) $refund['user_id']]
            );

            $this->pdo->commit();

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
     * Body: reason (string, required)
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

        if ($reason === '') {
            return Response::fail('Vui lòng nêu rõ lý do từ chối hoàn tiền', 'VALIDATION_ERROR', 422);
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'SELECT r.`id`, r.`amount`, r.`status`, rp.`user_id`, gr.`title` AS `room_title`
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

            if ($refund['status'] !== 'PENDING') {
                $this->pdo->rollBack();
                return Response::fail(
                    'Yêu cầu hoàn tiền này đã được xử lý trước đó (Trạng thái: ' . $refund['status'] . ')',
                    'INVALID_REFUND_STATUS',
                    409
                );
            }

            $upStmt = $this->pdo->prepare(
                'UPDATE `refunds`
                 SET `status` = \'REJECTED\', `reviewed_by` = :reviewer, `reviewed_at` = NOW(), `review_reason` = :reason, `updated_at` = NOW()
                 WHERE `id` = :id'
            );
            $upStmt->bindValue(':reviewer', (int) $auth['id'], PDO::PARAM_INT);
            $upStmt->bindValue(':reason', $reason, PDO::PARAM_STR);
            $upStmt->bindValue(':id', $refundId, PDO::PARAM_INT);
            $upStmt->execute();

            // Ghi nhận nhật ký kiểm toán
            $auditService = new \App\Shared\Services\AuditLogService($this->pdo);
            $auditService->log((int) $auth['id'], 'REJECT_REFUND', 'REFUND', $refundId, [
                'reason' => $reason,
                'amount' => (int) $refund['amount'],
            ]);

            $this->notiRepo->createNotification(
                'Yêu cầu hoàn tiền bị từ chối',
                "Yêu cầu hoàn tiền cho phòng '{$refund['room_title']}' đã bị từ chối. Lý do: {$reason}",
                'REFUND',
                (int) $auth['id'],
                [(int) $refund['user_id']]
            );

            $this->pdo->commit();

            return Response::success(['refund_id' => $refundId, 'status' => 'REJECTED'], 'Đã từ chối hoàn tiền');
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * POST /api/v1/admin/refunds/{id}/complete
     * Xác nhận kế toán đã chuyển khoản hoàn tất lệnh hoàn tiền (Điểm 8 & Điểm 22)
     */
    public function complete(Request $request, string $id): Response
    {
        $auth = AuthMiddleware::authenticate($request, ['ADMIN']);
        if ($auth instanceof Response) {
            return $auth;
        }

        $refundId = (int) $id;
        $body = $request->getBodyParams();
        $bankRef = trim((string) ($body['bank_reference_code'] ?? ('REF_' . time())));

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

            if (strtoupper((string) ($refund['status'] ?? '')) !== 'APPROVED') {
                $this->pdo->rollBack();
                return Response::fail('Chỉ có thể hoàn tất chuyển khoản cho yêu cầu đã được duyệt (APPROVED)', 'INVALID_REFUND_STATUS', 422);
            }

            $upStmt = $this->pdo->prepare(
                'UPDATE `refunds`
                 SET `status` = \'COMPLETED\', `completed_at` = NOW(), `review_reason` = CONCAT(COALESCE(`review_reason`, \'\'), \' [Ref: \', :ref, \']\'), `updated_at` = NOW()
                 WHERE `id` = :id'
            );
            $upStmt->bindValue(':ref', $bankRef, PDO::PARAM_STR);
            $upStmt->bindValue(':id', $refundId, PDO::PARAM_INT);
            $upStmt->execute();

            // Cập nhật trạng thái player_payments sang CANCELLED (hoàn tiền hoàn tất)
            $upPay = $this->pdo->prepare('UPDATE `player_payments` SET `status` = \'CANCELLED\', `updated_at` = NOW() WHERE `id` = :pid');
            $upPay->bindValue(':pid', (int) $refund['player_payment_id'], PDO::PARAM_INT);
            $upPay->execute();

            $auditService = new \App\Shared\Services\AuditLogService($this->pdo);
            $auditService->log((int) $auth['id'], 'COMPLETE_REFUND', 'REFUND', $refundId, [
                'amount' => (int) $refund['amount'],
                'bank_reference_code' => $bankRef,
                'player_payment_id' => (int) $refund['player_payment_id'],
            ]);

            $this->notiRepo->createNotification(
                'Hoàn tiền đã chuyển khoản thành công',
                "Khoản hoàn tiền " . number_format((int) $refund['amount']) . " VNĐ cho phòng '{$refund['room_title']}' đã được chuyển khoản hoàn tất (Mã GD: {$bankRef}).",
                'REFUND',
                (int) $auth['id'],
                [(int) $refund['user_id']]
            );

            $this->pdo->commit();

            return Response::success([
                'refund_id' => $refundId,
                'status' => 'COMPLETED',
                'bank_reference_code' => $bankRef,
            ], 'Đã hoàn tất chuyển khoản hoàn tiền thành công');
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}