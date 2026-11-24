<?php

declare(strict_types=1);

namespace App\Features\PaymentRefund\Controllers;

use App\Core\Middleware\AuthMiddleware;
use App\Core\Request;
use App\Core\Response;
use App\Infrastructure\Database\DatabaseConnection;
use PDO;

class AdminPaymentController
{
    protected PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DatabaseConnection::getInstance();

        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }
    }

    /**
     * GET /api/v1/admin/payments
     * Tra cứu và lọc danh sách toàn bộ hóa đơn thanh toán trên hệ thống.
     * Query: page, per_page, search, status, payment_type, from_date, to_date
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

        $search = $request->query('search') !== null ? trim((string) $request->query('search')) : null;
        $status = $request->query('status') !== null ? strtoupper(trim((string) $request->query('status'))) : null;
        $paymentType = $request->query('payment_type') !== null ? strtoupper(trim((string) $request->query('payment_type'))) : null;
        $fromDate = $request->query('from_date') !== null ? trim((string) $request->query('from_date')) : null;
        $toDate = $request->query('to_date') !== null ? trim((string) $request->query('to_date')) : null;

        $where = ['1 = 1'];
        $params = [];

        if ($search !== null && $search !== '') {
            $where[] = '(u.`full_name` LIKE :search1 OR u.`email` LIKE :search2 OR u.`phone` LIKE :search3 OR gr.`title` LIKE :search4 OR g.`name` LIKE :search5)';
            $searchWildcard = '%' . $search . '%';
            $params[':search1'] = $searchWildcard;
            $params[':search2'] = $searchWildcard;
            $params[':search3'] = $searchWildcard;
            $params[':search4'] = $searchWildcard;
            $params[':search5'] = $searchWildcard;
        }

        if ($status !== null && $status !== '' && $status !== 'ALL') {
            $where[] = 'p.`status` = :status';
            $params[':status'] = $status;
        }

        if ($paymentType !== null && $paymentType !== '' && $paymentType !== 'ALL') {
            $where[] = 'p.`payment_type` = :payment_type';
            $params[':payment_type'] = $paymentType;
        }

        if ($fromDate !== null && $fromDate !== '') {
            $where[] = 'DATE(p.`created_at`) >= :from_date';
            $params[':from_date'] = $fromDate;
        }

        if ($toDate !== null && $toDate !== '') {
            $where[] = 'DATE(p.`created_at`) <= :to_date';
            $params[':to_date'] = $toDate;
        }

        $whereSql = implode(' AND ', $where);

        // 1. Đếm tổng số bản ghi
        $cntStmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM `player_payments` p
             INNER JOIN `room_participants` rp ON rp.`id` = p.`room_participant_id`
             INNER JOIN `users` u ON u.`id` = rp.`user_id`
             INNER JOIN `game_rooms` gr ON gr.`id` = rp.`game_room_id`
             INNER JOIN `games` g ON g.`id` = gr.`game_id`
             WHERE {$whereSql}"
        );
        foreach ($params as $k => $v) {
            $cntStmt->bindValue($k, $v, PDO::PARAM_STR);
        }
        $cntStmt->execute();
        $total = (int) $cntStmt->fetchColumn();

        // 2. Thống kê tổng tiền thu và đang chờ trong tập lọc
        $sumStmt = $this->pdo->prepare(
            "SELECT 
                COALESCE(SUM(p.`paid_amount`), 0) AS `total_paid`,
                COALESCE(SUM(CASE WHEN p.`status` = 'PENDING' THEN p.`amount` ELSE 0 END), 0) AS `total_pending`
             FROM `player_payments` p
             INNER JOIN `room_participants` rp ON rp.`id` = p.`room_participant_id`
             INNER JOIN `users` u ON u.`id` = rp.`user_id`
             INNER JOIN `game_rooms` gr ON gr.`id` = rp.`game_room_id`
             INNER JOIN `games` g ON g.`id` = gr.`game_id`
             WHERE {$whereSql}"
        );
        foreach ($params as $k => $v) {
            $sumStmt->bindValue($k, $v, PDO::PARAM_STR);
        }
        $sumStmt->execute();
        $sumData = $sumStmt->fetch(PDO::FETCH_ASSOC);

        // 3. Lấy danh sách thanh toán
        $stmt = $this->pdo->prepare(
            "SELECT p.`id`, p.`room_participant_id`, p.`payment_type`, p.`amount`, p.`paid_amount`,
                    p.`status`, p.`deadline_at`, p.`paid_at`, p.`created_at`, p.`updated_at`,
                    rp.`user_id`, u.`full_name` AS `user_name`, u.`email` AS `user_email`, u.`phone` AS `user_phone`,
                    gr.`id` AS `game_room_id`, gr.`title` AS `room_title`, gr.`status` AS `room_status`,
                    g.`id` AS `game_id`, g.`name` AS `game_name`
             FROM `player_payments` p
             INNER JOIN `room_participants` rp ON rp.`id` = p.`room_participant_id`
             INNER JOIN `users` u ON u.`id` = rp.`user_id`
             INNER JOIN `game_rooms` gr ON gr.`id` = rp.`game_room_id`
             INNER JOIN `games` g ON g.`id` = gr.`game_id`
             WHERE {$whereSql}
             ORDER BY p.`id` DESC
             LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        return Response::success([
            'payments' => $items,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
            ],
            'meta' => [
                'total_paid_amount' => (int) ($sumData['total_paid'] ?? 0),
                'total_pending_amount' => (int) ($sumData['total_pending'] ?? 0),
            ],
        ], 'Danh sách giao dịch thanh toán', [
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
            ],
            'total_paid' => (int) ($sumData['total_paid'] ?? 0),
            'total_pending' => (int) ($sumData['total_pending'] ?? 0),
        ]);
    }

    /**
     * GET /api/v1/admin/payments/{id}
     * Xem chi tiết một khoản thanh toán kèm lịch sử giao dịch cổng và hoàn tiền liên quan.
     */
    public function show(Request $request, string $id): Response
    {
        $auth = AuthMiddleware::authenticate($request, ['ADMIN']);
        if ($auth instanceof Response) {
            return $auth;
        }

        $paymentId = (int) $id;
        $stmt = $this->pdo->prepare(
            "SELECT p.`id`, p.`room_participant_id`, p.`payment_type`, p.`amount`, p.`paid_amount`,
                    p.`status`, p.`deadline_at`, p.`paid_at`, p.`created_at`, p.`updated_at`,
                    rp.`user_id`, u.`full_name` AS `user_name`, u.`email` AS `user_email`, u.`phone` AS `user_phone`,
                    gr.`id` AS `game_room_id`, gr.`title` AS `room_title`, gr.`status` AS `room_status`,
                    g.`id` AS `game_id`, g.`name` AS `game_name`
             FROM `player_payments` p
             INNER JOIN `room_participants` rp ON rp.`id` = p.`room_participant_id`
             INNER JOIN `users` u ON u.`id` = rp.`user_id`
             INNER JOIN `game_rooms` gr ON gr.`id` = rp.`game_room_id`
             INNER JOIN `games` g ON g.`id` = gr.`game_id`
             WHERE p.`id` = :id
             LIMIT 1"
        );
        $stmt->bindValue(':id', $paymentId, PDO::PARAM_INT);
        $stmt->execute();
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($payment === false) {
            return Response::fail('Không tìm thấy hóa đơn thanh toán', 'PAYMENT_NOT_FOUND', 404);
        }

        // Lấy lịch sử giao dịch cổng
        $txStmt = $this->pdo->prepare(
            "SELECT `id`, `idempotency_key`, `transaction_reference`, `amount`, `status`, `provider_response`, `requested_at`, `processed_at`, `created_at`
             FROM `payment_transactions`
             WHERE `player_payment_id` = :payment_id
             ORDER BY `id` DESC"
        );
        $txStmt->bindValue(':payment_id', $paymentId, PDO::PARAM_INT);
        $txStmt->execute();
        $transactions = $txStmt->fetchAll(PDO::FETCH_ASSOC);

        // Lấy các bản ghi hoàn tiền nếu có
        $refStmt = $this->pdo->prepare(
            "SELECT `id`, `amount`, `reason`, `status`, `requested_at`, `reviewed_at`, `review_reason`
             FROM `refunds`
             WHERE `player_payment_id` = :payment_id
             ORDER BY `id` DESC"
        );
        $refStmt->bindValue(':payment_id', $paymentId, PDO::PARAM_INT);
        $refStmt->execute();
        $refunds = $refStmt->fetchAll(PDO::FETCH_ASSOC);

        $payment['transactions'] = $transactions;
        $payment['refunds'] = $refunds;

        return Response::success([
            'payment' => $payment,
        ], 'Chi tiết hóa đơn thanh toán');
    }
}
