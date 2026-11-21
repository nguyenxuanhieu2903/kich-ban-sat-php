<?php

declare(strict_types=1);

namespace App\Features\Payment;

use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;

class PaymentController
{
    private PaymentService $service;

    public function __construct(?PaymentService $service = null)
    {
        $this->service = $service ?? new PaymentService();
    }

    public function listByRoom(Request $request, string $id): Response
    {
        $auth = $this->requireRole(['PLAYER', 'ADMIN', 'DM']);
        $roomId = $this->parsePositiveInt($id, 'id');

        return Response::json([
            'success' => true,
            'data' => $this->service->listRoomPayments($roomId, $auth['user_id'], $auth['role']),
            'serverTime' => date('c'),
        ]);
    }

    public function createDeposit(Request $request, string $id): Response
    {
        $auth = $this->requireRole(['PLAYER', 'ADMIN']);
        $roomId = $this->parsePositiveInt($id, 'id');
        $body = $request->getBodyParams();
        $provider = (string) ($body['provider'] ?? 'VNPAY');
        $idemKey = $this->extractIdempotencyKey($request, $body);

        $tx = $this->service->payDeposit($roomId, $auth['user_id'], $provider, $idemKey);

        return Response::json([
            'success' => true,
            'message' => $tx['idempotent_replay'] ? 'Giao dịch cọc đã tồn tại (idempotent)' : 'Tạo giao dịch đặt cọc thành công',
            'data' => $tx,
            'serverTime' => date('c'),
        ], $tx['idempotent_replay'] ? 200 : 201);
    }

    public function payRemaining(Request $request, string $id): Response
    {
        $auth = $this->requireRole(['PLAYER', 'ADMIN']);
        $roomId = $this->parsePositiveInt($id, 'id');
        $body = $request->getBodyParams();
        $provider = (string) ($body['provider'] ?? 'VNPAY');
        $idemKey = $this->extractIdempotencyKey($request, $body);

        $tx = $this->service->payRemaining($roomId, $auth['user_id'], $provider, $idemKey);

        return Response::json([
            'success' => true,
            'message' => $tx['idempotent_replay'] ? 'Giao dịch thanh toán còn lại đã tồn tại (idempotent)' : 'Tạo giao dịch thanh toán phần còn lại thành công',
            'data' => $tx,
            'serverTime' => date('c'),
        ], $tx['idempotent_replay'] ? 200 : 201);
    }

    public function confirm(Request $request, string $id): Response
    {
        $auth = $this->requireRole(['ADMIN']);
        $paymentId = $this->parsePositiveInt($id, 'id');
        $body = $request->getBodyParams();
        $note = isset($body['note']) && is_string($body['note']) ? $body['note'] : null;

        $tx = $this->service->confirmPayment($paymentId, $auth['user_id'], $auth['role'], $note);

        return Response::json([
            'success' => true,
            'message' => 'Xác nhận thanh toán thành công',
            'data' => $tx,
            'serverTime' => date('c'),
        ]);
    }

    public function fail(Request $request, string $id): Response
    {
        $auth = $this->requireRole(['ADMIN']);
        $paymentId = $this->parsePositiveInt($id, 'id');
        $body = $request->getBodyParams();
        $reason = isset($body['reason']) && is_string($body['reason']) ? $body['reason'] : null;

        $tx = $this->service->failPayment($paymentId, $auth['user_id'], $auth['role'], $reason);

        return Response::json([
            'success' => true,
            'message' => 'Đã cập nhật giao dịch thất bại',
            'data' => $tx,
            'serverTime' => date('c'),
        ]);
    }

    public function retry(Request $request, string $id): Response
    {
        $auth = $this->requireRole(['PLAYER', 'ADMIN']);
        $paymentId = $this->parsePositiveInt($id, 'id');
        $body = $request->getBodyParams();
        $idemKey = $this->extractIdempotencyKey($request, $body);

        $tx = $this->service->retryPayment($paymentId, $auth['user_id'], $auth['role'], $idemKey);

        return Response::json([
            'success' => true,
            'message' => 'Tạo lại giao dịch thanh toán thành công',
            'data' => $tx,
            'serverTime' => date('c'),
        ], 201);
    }

    public function requestRefund(Request $request, string $id): Response
    {
        $auth = $this->requireRole(['PLAYER', 'ADMIN']);
        $paymentId = $this->parsePositiveInt($id, 'id');
        $body = $request->getBodyParams();
        $reason = (string) ($body['reason'] ?? '');

        $refund = $this->service->requestRefund($paymentId, $auth['user_id'], $auth['role'], $reason);

        return Response::json([
            'success' => true,
            'message' => 'Gửi yêu cầu hoàn tiền thành công',
            'data' => $refund,
            'serverTime' => date('c'),
        ], 201);
    }

    public function processRefund(Request $request, string $id): Response
    {
        $auth = $this->requireRole(['ADMIN']);
        $refundId = $this->parsePositiveInt($id, 'id');
        $body = $request->getBodyParams();
        $decision = (string) ($body['decision'] ?? '');
        $refCode = isset($body['reference_code']) && is_string($body['reference_code']) ? $body['reference_code'] : null;

        $refund = $this->service->processRefund($refundId, $auth['user_id'], $auth['role'], $decision, $refCode);

        return Response::json([
            'success' => true,
            'message' => 'Xử lý hoàn tiền thành công',
            'data' => $refund,
            'serverTime' => date('c'),
        ]);
    }

    /** @param array<string, mixed> $body */
    private function extractIdempotencyKey(Request $request, array $body): ?string
    {
        $headerKey = $request->getHeader('Idempotency-Key');
        if (is_string($headerKey) && trim($headerKey) !== '') {
            return trim($headerKey);
        }
        if (isset($body['idempotency_key']) && is_string($body['idempotency_key']) && trim($body['idempotency_key']) !== '') {
            return trim($body['idempotency_key']);
        }

        return null;
    }

    /** @return array{user_id: int, role: string} */
    private function requireAuth(): array
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $role = strtoupper((string) ($_SESSION['role'] ?? ''));

        if ($userId <= 0 || $role === '') {
            throw new HttpException('Bạn cần đăng nhập để thực hiện thao tác này', 401);
        }

        return ['user_id' => $userId, 'role' => $role];
    }

    /**
     * @param string[] $allowedRoles
     * @return array{user_id: int, role: string}
     */
    private function requireRole(array $allowedRoles): array
    {
        $auth = $this->requireAuth();
        if (!in_array($auth['role'], $allowedRoles, true)) {
            throw new HttpException('Bạn không có quyền thực hiện thao tác này', 403);
        }

        return $auth;
    }

    private function parsePositiveInt(mixed $value, string $field): int
    {
        $parsed = filter_var($value, FILTER_VALIDATE_INT);
        if ($parsed === false || $parsed <= 0) {
            throw new HttpException("Tham số {$field} phải là số nguyên dương", 400);
        }

        return (int) $parsed;
    }
}