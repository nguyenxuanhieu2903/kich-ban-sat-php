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

        // Kiểm tra mốc 24h trước giờ chơi nếu có session (chặn hoàn cọc nếu buổi chơi đã COMPLETED)
        $roomId = (int) $payment['game_room_id'];
        $sessStmt = $this->pdo->prepare(
            'SELECT `start_at`, `status` FROM `sessions` WHERE `game_room_id` = :rid AND `status` IN (\'SCHEDULED\', \'IN_PROGRESS\', \'COMPLETED\') ORDER BY `id` DESC LIMIT 1'
        );
        $sessStmt->bindValue(':rid', $roomId, PDO::PARAM_INT);
        $sessStmt->execute();
        $sessRow = $sessStmt->fetch(PDO::FETCH_ASSOC);

        if ($sessRow !== false) {
            $allowForceRefund = ($roleCode === 'ADMIN' && !empty($body['force_refund']));
            if (strtoupper((string) ($sessRow['status'] ?? '')) === 'COMPLETED' && !$allowForceRefund) {
                return Response::fail(
                    'Không thể yêu cầu hoàn tiền cho buổi chơi đã hoàn tất',
                    'SESSION_ALREADY_COMPLETED',
                    422
                );
            }
            $startAt = $sessRow['start_at'] ?? null;
            if ($startAt !== null) {
                $diffHours = (strtotime((string) $startAt) - time()) / 3600;
                if ($diffHours < 24 && !$allowForceRefund) {
                    return Response::fail(
                        'Chỉ có thể yêu cầu hoàn tiền trước giờ chơi ít nhất 24 giờ',
                        'REFUND_WINDOW_EXPIRED',
                        422
                    );
                }
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

    /**
     * POST /api/v1/payments/refund-request
     * Hỗ trợ yêu cầu hoàn tiền cọc theo room_id trực tiếp từ giao diện chi tiết phòng
     */
    public function requestRefundByRoom(Request $request): Response
    {
        $auth = AuthMiddleware::authenticate($request);
        if ($auth instanceof Response) {
            return $auth;
        }

        $actorRole = strtoupper((string) ($auth['role_code'] ?? $auth['role'] ?? 'PLAYER'));
        if (in_array($actorRole, ['DM', 'ADMIN'], true)) {
            return Response::fail('Tài khoản Quản trò (DM) và Quản trị viên (Admin) không gửi yêu cầu hoàn cọc cá nhân', 'FORBIDDEN_ROLE', 403);
        }

        $body = $request->getBodyParams();
        $roomId = (int) ($body['room_id'] ?? $body['game_room_id'] ?? 0);

        if ($roomId > 0) {
            $stmt = $this->pdo->prepare('SELECT p.`id` FROM `player_payments` p
                INNER JOIN `room_participants` rp ON rp.`id` = p.`room_participant_id`
                WHERE rp.`game_room_id` = :room_id AND rp.`user_id` = :user_id
                  AND rp.`status` IN (\'PENDING_DEPOSIT\', \'DEPOSITED\', \'LEAVE_REQUESTED\')
                  AND p.`paid_amount` > 0
                  AND NOT EXISTS (
                      SELECT 1 FROM `refunds` r
                      WHERE r.`player_payment_id` = p.`id`
                        AND r.`status` IN (\'PENDING\', \'APPROVED\', \'PROCESSING\', \'COMPLETED\')
                  )
                ORDER BY p.`id` ASC');
            $stmt->bindValue(':room_id', $roomId, PDO::PARAM_INT);
            $stmt->bindValue(':user_id', (int) $auth['id'], PDO::PARAM_INT);
            $stmt->execute();
            $paymentIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

            if (!empty($paymentIds)) {
                $createdRefunds = [];
                $totalAmount = 0;
                $lastResponse = null;
                foreach ($paymentIds as $pid) {
                    $res = $this->requestRefund($request, (string) $pid);
                    $lastResponse = $res;
                    if ($res->getStatusCode() >= 400) {
                        return $res;
                    }
                    $decoded = json_decode($res->getContent(), true);
                    if (!empty($decoded['data'])) {
                        $createdRefunds[] = $decoded['data'];
                        $totalAmount += (int) ($decoded['data']['amount'] ?? 0);
                    }
                }
                if (count($createdRefunds) === 1 && $lastResponse !== null) {
                    return $lastResponse;
                }
                return Response::success([
                    'refund_id' => $createdRefunds[0]['refund_id'] ?? null,
                    'refunds' => $createdRefunds,
                    'amount' => $totalAmount,
                    'status' => 'PENDING',
                ], 'Đã gửi yêu cầu hoàn tiền cho toàn bộ khoản đã thanh toán thành công', null, 201);
            }
        }

        return Response::fail(
            'Không tìm thấy khoản tiền cọc đã thanh toán cho phòng chơi này để thực hiện hoàn tiền',
            'NO_PAID_AMOUNT',
            400
        );
    }
}