<?php

declare(strict_types=1);

namespace App\Features\PaymentRefund\Controllers;

use App\Core\Middleware\AuthMiddleware;
use App\Core\Request;
use App\Core\Response;
use App\Features\PaymentRefund\Repositories\PaymentRepository;
use App\Infrastructure\Database\DatabaseConnection;
use PDO;

class PaymentRefundRequestController
{
    protected PDO $pdo;
    protected PaymentRepository $paymentRepo;

    public function __construct(?PDO $pdo = null, ?PaymentRepository $paymentRepo = null)
    {
        $this->pdo = $pdo ?? DatabaseConnection::getInstance();
        $this->paymentRepo = $paymentRepo ?? new PaymentRepository($this->pdo);

        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }
    }

    /**
     * POST /api/v1/payments/{id}/refund-request
     */
    public function requestRefund(Request $request, string $id): Response
    {
        $auth = AuthMiddleware::authenticate($request);
        if ($auth instanceof Response) {
            return $auth;
        }

        $paymentId = (int) $id;
        $payment = $this->paymentRepo->findPaymentWithDetails($paymentId);

        if ($payment === null) {
            return Response::fail('Không tìm thấy khoản thanh toán', 'PAYMENT_NOT_FOUND', 404);
        }

        $roleCode = strtoupper((string) ($auth['role_code'] ?? ''));
        if ($roleCode !== 'ADMIN' && (int) $payment['user_id'] !== (int) $auth['id']) {
            return Response::fail('Bạn không có quyền yêu cầu hoàn tiền cho khoản thanh toán này', 'FORBIDDEN', 403);
        }

        $paidAmount = (int) ($payment['paid_amount'] ?? 0);
        if ($paidAmount <= 0) {
            return Response::fail('Khoản thanh toán chưa có tiền đã nộp để hoàn lại', 'NO_PAID_AMOUNT', 400);
        }

        // Kiểm tra đã có yêu cầu hoàn tiền đang PENDING hoặc APPROVED chưa
        $dupStmt = $this->pdo->prepare(
            'SELECT `id` FROM `refunds` WHERE `player_payment_id` = :pid AND `status` IN (\'PENDING\', \'APPROVED\') LIMIT 1'
        );
        $dupStmt->bindValue(':pid', $paymentId, PDO::PARAM_INT);
        $dupStmt->execute();
        if ($dupStmt->fetchColumn() !== false) {
            return Response::fail('Đã tồn tại yêu cầu hoàn tiền đang chờ xử lý hoặc đã duyệt cho khoản này', 'DUPLICATE_REFUND_REQUEST', 409);
        }

        $body = $request->getBodyParams();
        $reason = trim((string) ($body['reason'] ?? 'Yêu cầu hoàn tiền từ người chơi'));
        if ($reason === '') {
            $reason = 'Yêu cầu hoàn tiền từ người chơi';
        }

        // Kiểm tra mốc 24h trước giờ chơi nếu có session
        $roomId = (int) $payment['game_room_id'];
        $sessStmt = $this->pdo->prepare(
            'SELECT `start_at` FROM `sessions` WHERE `game_room_id` = :rid AND `status` IN (\'SCHEDULED\', \'IN_PROGRESS\') ORDER BY `id` DESC LIMIT 1'
        );
        $sessStmt->bindValue(':rid', $roomId, PDO::PARAM_INT);
        $sessStmt->execute();
        $startAt = $sessStmt->fetchColumn();

        if ($startAt !== false && $startAt !== null) {
            $diffHours = (strtotime((string) $startAt) - time()) / 3600;
            $allowForceRefund = ($roleCode === 'ADMIN' && !empty($body['force_refund']));
            if ($diffHours < 24 && !$allowForceRefund) {
                return Response::fail(
                    'Chỉ có thể yêu cầu hoàn tiền trước giờ chơi ít nhất 24 giờ',
                    'REFUND_WINDOW_EXPIRED',
                    422
                );
            }
        }

        $refundId = $this->paymentRepo->createRefund(
            $paymentId,
            (int) $payment['room_participant_id'],
            $paidAmount,
            $reason,
            'FULL',
            (int) $auth['id']
        );

        return Response::success([
            'refund_id' => $refundId,
            'player_payment_id' => $paymentId,
            'amount' => $paidAmount,
            'status' => 'PENDING',
        ], 'Đã gửi yêu cầu hoàn tiền thành công', null, 201);
    }
}