<?php

declare(strict_types=1);

namespace App\Features\PaymentRefund\Controllers;

use App\Core\Middleware\AuthMiddleware;
use App\Core\Request;
use App\Core\Response;
use App\Features\Notification\Repositories\NotificationRepository;
use App\Features\PaymentRefund\Repositories\PaymentRepository;
use App\Infrastructure\Database\DatabaseConnection;
use PDO;

class PaymentController
{
    protected PaymentRepository $paymentRepo;
    protected NotificationRepository $notiRepo;
    protected PDO $pdo;

    public function __construct(
        ?PaymentRepository $paymentRepo = null,
        ?NotificationRepository $notiRepo = null,
        ?PDO $pdo = null
    ) {
        $this->pdo = $pdo ?? DatabaseConnection::getInstance();
        $this->paymentRepo = $paymentRepo ?? new PaymentRepository($this->pdo);
        $this->notiRepo = $notiRepo ?? new NotificationRepository($this->pdo);

        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }
    }

    /**
     * POST /api/v1/payments/{paymentId}/deposit
     */
    public function deposit(Request $request, string $paymentId): Response
    {
        $auth = AuthMiddleware::authenticate($request);
        if ($auth instanceof Response) {
            return $auth;
        }

        $id = (int) $paymentId;
        $payment = $this->paymentRepo->findPaymentWithDetails($id);

        if ($payment === null) {
            return Response::fail('Không tìm thấy thông tin thanh toán', 'PAYMENT_NOT_FOUND', 404);
        }

        $userRole = strtoupper((string) ($auth['role_code'] ?? ''));

        // Chống IDOR: Chỉ chính người chơi hoặc Admin mới được thanh toán
        if ($userRole !== 'ADMIN' && (int) $payment['user_id'] !== (int) $auth['id']) {
            return Response::fail('Bạn không có quyền thực hiện thanh toán cho người khác', 'FORBIDDEN', 403);
        }

        if ($payment['status'] === 'PAID') {
            return Response::fail('Khoản thanh toán này đã được thanh toán đầy đủ', 'PAYMENT_ALREADY_COMPLETED', 400);
        }

        $idempotencyKey = $request->getHeader('x-idempotency-key')
            ?? (string) ($request->input('idempotency_key') ?? ('DEP_' . $id . '_' . bin2hex(random_bytes(8))));

        $totalAmount = (int) $payment['amount'];
        $alreadyPaid = (int) $payment['paid_amount'];
        $remaining = max(0, $totalAmount - $alreadyPaid);

        $bodyAmount = $request->input('amount');
        if ($bodyAmount !== null && (int) $bodyAmount > 0) {
            $depositAmount = min((int) $bodyAmount, $remaining);
        } else {
            // Mặc định cọc 50% tổng số tiền
            $depositAmount = min((int) ceil($totalAmount * 0.5), $remaining);
        }

        $method = (string) ($request->input('payment_method') ?? 'SIMULATED_MOMO');

        $result = $this->paymentRepo->processPayment($id, $depositAmount, $idempotencyKey, $method);

        // Tạo thông báo cho người dùng
        $this->notiRepo->createNotification(
            'Thanh toán cọc thành công',
            "Bạn đã thanh toán cọc thành công số tiền " . number_format($depositAmount) . " VNĐ cho phòng " . ($payment['room_title'] ?? ''),
            'PAYMENT',
            null,
            [(int) $payment['user_id']]
        );

        return Response::success($result, 'Thanh toán tiền cọc thành công');
    }

    /**
     * POST /api/v1/payments/{paymentId}/remaining
     */
    public function remaining(Request $request, string $paymentId): Response
    {
        $auth = AuthMiddleware::authenticate($request);
        if ($auth instanceof Response) {
            return $auth;
        }

        $id = (int) $paymentId;
        $payment = $this->paymentRepo->findPaymentWithDetails($id);

        if ($payment === null) {
            return Response::fail('Không tìm thấy thông tin thanh toán', 'PAYMENT_NOT_FOUND', 404);
        }

        $userRole = strtoupper((string) ($auth['role_code'] ?? ''));

        // Chống IDOR
        if ($userRole !== 'ADMIN' && (int) $payment['user_id'] !== (int) $auth['id']) {
            return Response::fail('Bạn không có quyền thực hiện thanh toán cho người khác', 'FORBIDDEN', 403);
        }

        $totalAmount = (int) $payment['amount'];
        $alreadyPaid = (int) $payment['paid_amount'];
        $remaining = max(0, $totalAmount - $alreadyPaid);

        if ($remaining <= 0 || $payment['status'] === 'PAID') {
            return Response::fail('Khoản phí này đã được thanh toán toàn bộ', 'PAYMENT_ALREADY_COMPLETED', 400);
        }

        $idempotencyKey = $request->getHeader('x-idempotency-key')
            ?? (string) ($request->input('idempotency_key') ?? ('REM_' . $id . '_' . bin2hex(random_bytes(8))));

        $method = (string) ($request->input('payment_method') ?? 'SIMULATED_VNPAY');

        $result = $this->paymentRepo->processPayment($id, $remaining, $idempotencyKey, $method);

        // Tạo thông báo cho người dùng
        $this->notiRepo->createNotification(
            'Thanh toán hoàn tất',
            "Bạn đã thanh toán thành công số tiền còn lại " . number_format($remaining) . " VNĐ cho phòng " . ($payment['room_title'] ?? ''),
            'PAYMENT',
            null,
            [(int) $payment['user_id']]
        );

        return Response::success($result, 'Thanh toán số tiền còn lại thành công');
    }
}
