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

        $idempotencyKey = $request->getHeader('x-idempotency-key')
            ?? (string) ($request->input('idempotency_key') ?? '');

        // P4: Kiểm tra Idempotent replay trước khi kiểm tra trạng thái PAID
        if ($idempotencyKey !== '') {
            $existingTx = $this->paymentRepo->findAttemptByIdempotencyKey($idempotencyKey);
            if ($existingTx !== null) {
                return Response::success([
                    'payment' => $payment,
                    'transaction' => $existingTx,
                ], 'Thanh toán tiền cọc thành công (Idempotent replay)');
            }
        } else {
            $idempotencyKey = 'DEP_' . $id . '_' . bin2hex(random_bytes(8));
        }

        if ($payment['status'] === 'PAID') {
            return Response::fail('Khoản thanh toán này đã được thanh toán đầy đủ', 'PAYMENT_ALREADY_COMPLETED', 400);
        }

        if ($payment['status'] === 'EXPIRED') {
            return Response::fail('Khoản thanh toán này đã hết hạn', 'PAYMENT_EXPIRED', 400);
        }

        $totalAmount = (int) $payment['amount'];
        $alreadyPaid = (int) $payment['paid_amount'];
        $remaining = max(0, $totalAmount - $alreadyPaid);

        if ($remaining <= 0) {
            return Response::fail('Khoản thanh toán này đã được thanh toán đầy đủ', 'PAYMENT_ALREADY_COMPLETED', 400);
        }

        // P3: Tiền cọc cố định 50.000 VNĐ / người theo V16 (Backend tự xác định, không tin client amount)
        if ($payment['payment_type'] === 'DEPOSIT') {
            $depositAmount = min(50000, $remaining);
        } else {
            $depositAmount = $remaining;
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

        $targetPaymentId = $id;
        $totalAmount = (int) $payment['amount'];
        $alreadyPaid = (int) $payment['paid_amount'];
        $remaining = max(0, $totalAmount - $alreadyPaid);

        // Nếu gọi remaining trên hóa đơn DEPOSIT đã PAID: tìm hoặc tạo hóa đơn REMAINING của participant
        if ($payment['payment_type'] === 'DEPOSIT' && ($remaining <= 0 || $payment['status'] === 'PAID')) {
            $partId = (int) $payment['room_participant_id'];
            $remStmt = $this->pdo->prepare('SELECT `id`, `amount`, `paid_amount`, `status` FROM `player_payments` WHERE `room_participant_id` = :part_id AND `payment_type` = \'REMAINING\' LIMIT 1');
            $remStmt->bindValue(':part_id', $partId, PDO::PARAM_INT);
            $remStmt->execute();
            $remRow = $remStmt->fetch(PDO::FETCH_ASSOC);

            if ($remRow !== false) {
                $targetPaymentId = (int) $remRow['id'];
                $totalAmount = (int) $remRow['amount'];
                $alreadyPaid = (int) $remRow['paid_amount'];
                $remaining = max(0, $totalAmount - $alreadyPaid);
                $payment['status'] = $remRow['status'];
            } else {
                $snapStmt = $this->pdo->prepare('SELECT `remaining_amount` FROM `participant_price_snapshots` WHERE `room_participant_id` = :part_id LIMIT 1');
                $snapStmt->bindValue(':part_id', $partId, PDO::PARAM_INT);
                $snapStmt->execute();
                $snapRem = $snapStmt->fetchColumn();

                if ($snapRem !== false && (int) $snapRem > 0) {
                    $newRemAmount = (int) $snapRem;
                    $insRem = $this->pdo->prepare(
                        'INSERT INTO `player_payments` (`room_participant_id`, `payment_type`, `amount`, `paid_amount`, `status`, `created_at`, `updated_at`)
                         VALUES (:part_id, \'REMAINING\', :amount, 0, \'PENDING\', NOW(), NOW())'
                    );
                    $insRem->bindValue(':part_id', $partId, PDO::PARAM_INT);
                    $insRem->bindValue(':amount', $newRemAmount, PDO::PARAM_INT);
                    $insRem->execute();
                    $targetPaymentId = (int) $this->pdo->lastInsertId();
                    $totalAmount = $newRemAmount;
                    $alreadyPaid = 0;
                    $remaining = $newRemAmount;
                    $payment['status'] = 'PENDING';
                }
            }
        }

        $idempotencyKey = $request->getHeader('x-idempotency-key')
            ?? (string) ($request->input('idempotency_key') ?? '');

        // P4: Kiểm tra Idempotent replay trước khi kiểm tra trạng thái PAID
        if ($idempotencyKey !== '') {
            $existingTx = $this->paymentRepo->findAttemptByIdempotencyKey($idempotencyKey);
            if ($existingTx !== null) {
                $targetPayment = $this->paymentRepo->findPaymentWithDetails($targetPaymentId);
                return Response::success([
                    'payment' => $targetPayment ?? $payment,
                    'transaction' => $existingTx,
                ], 'Thanh toán số tiền còn lại thành công (Idempotent replay)');
            }
        } else {
            $idempotencyKey = 'REM_' . $targetPaymentId . '_' . bin2hex(random_bytes(8));
        }

        if ($payment['status'] === 'EXPIRED') {
            return Response::fail('Khoản thanh toán này đã hết hạn', 'PAYMENT_EXPIRED', 400);
        }

        if ($remaining <= 0 || $payment['status'] === 'PAID') {
            return Response::fail('Khoản phí này đã được thanh toán toàn bộ', 'PAYMENT_ALREADY_COMPLETED', 400);
        }

        $method = (string) ($request->input('payment_method') ?? 'SIMULATED_VNPAY');

        $result = $this->paymentRepo->processPayment($targetPaymentId, $remaining, $idempotencyKey, $method);

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
