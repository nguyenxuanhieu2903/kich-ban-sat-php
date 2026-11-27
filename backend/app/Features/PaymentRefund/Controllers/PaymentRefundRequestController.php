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

        // Chặn gửi lại nếu khoản thanh toán này đã từng có yêu cầu hoàn tiền (kể cả chờ duyệt, đã duyệt, hoàn tất hay bị từ chối)
        $dupStmt = $this->pdo->prepare(
            'SELECT `id`, `status` FROM `refunds`
             WHERE `player_payment_id` = :pid
               AND `status` IN (\'PENDING\', \'APPROVED\', \'PROCESSING\', \'COMPLETED\', \'REJECTED\')
             ORDER BY `id` DESC LIMIT 1'
        );
        $dupStmt->bindValue(':pid', $paymentId, PDO::PARAM_INT);
        $dupStmt->execute();
        $existingRef = $dupStmt->fetch(PDO::FETCH_ASSOC);
        if ($existingRef !== false) {
            $exStatus = strtoupper((string) ($existingRef['status'] ?? ''));
            $errMsg = match ($exStatus) {
                'REJECTED' => 'Yêu cầu hoàn tiền cho khoản này đã bị Quản trị viên từ chối trước đó, không thể gửi lại.',
                'APPROVED', 'PROCESSING' => 'Yêu cầu hoàn tiền cho khoản này đã được Quản trị viên phê duyệt, không thể gửi thêm.',
                'COMPLETED' => 'Khoản thanh toán này đã được hoàn tiền hoàn tất.',
                default => 'Đã tồn tại yêu cầu hoàn tiền đang chờ Quản trị viên xét duyệt cho khoản này.',
            };
            return Response::fail($errMsg, 'DUPLICATE_REFUND_REQUEST', 409);
        }

        $body = $request->getBodyParams();
        $reason = trim((string) ($body['reason'] ?? ''));
        if ($reason === '') {
            return Response::fail('Vui lòng nhập rõ lý do yêu cầu hoàn tiền.', 'VALIDATION_ERROR', 422);
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

        // Gửi thông báo ngay tới tất cả Quản trị viên kèm rõ lý do hoàn tiền từ phía người chơi
        try {
            $adminStmt = $this->pdo->query(
                "SELECT u.`id` FROM `users` u
                 INNER JOIN `roles` r ON r.`id` = u.`role_id`
                 WHERE r.`code` = 'ADMIN' AND u.`status` = 'ACTIVE'"
            );
            $adminIds = array_map('intval', $adminStmt->fetchAll(PDO::FETCH_COLUMN));
            if (!empty($adminIds)) {
                $playerName = trim((string) ($auth['full_name'] ?? $auth['name'] ?? $payment['player_name'] ?? 'Người chơi'));
                $roomTitle = trim((string) ($payment['room_title'] ?? "Phòng #{$roomId}"));
                $notiRepo = new \App\Features\Notification\Repositories\NotificationRepository($this->pdo);
                $notiRepo->createNotification(
                    'Yêu cầu hoàn tiền mới cần duyệt',
                    "Người chơi {$playerName} yêu cầu hoàn " . number_format($paidAmount) . " đ cho phòng #{$roomId} ('{$roomTitle}'). Lý do: {$reason}",
                    'REFUND',
                    (int) $auth['id'],
                    $adminIds,
                    'REFUND',
                    $refundId
                );
            }
        } catch (\Throwable) {
        }

        return Response::success([
            'refund_id' => $refundId,
            'player_payment_id' => $paymentId,
            'amount' => $paidAmount,
            'reason' => $reason,
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
                        AND r.`status` IN (\'PENDING\', \'APPROVED\', \'PROCESSING\', \'COMPLETED\', \'REJECTED\')
                  )
                ORDER BY p.`id` ASC');
            $stmt->bindValue(':room_id', $roomId, PDO::PARAM_INT);
            $stmt->bindValue(':user_id', (int) $auth['id'], PDO::PARAM_INT);
            $stmt->execute();
            $paymentIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

            if (empty($paymentIds)) {
                $prevRefStmt = $this->pdo->prepare(
                    'SELECT r.`status` FROM `refunds` r
                     INNER JOIN `player_payments` p ON p.`id` = r.`player_payment_id`
                     INNER JOIN `room_participants` rp ON rp.`id` = p.`room_participant_id`
                     WHERE rp.`game_room_id` = :room_id AND rp.`user_id` = :user_id
                     ORDER BY r.`id` DESC LIMIT 1'
                );
                $prevRefStmt->execute([':room_id' => $roomId, ':user_id' => (int) $auth['id']]);
                $prevStatus = $prevRefStmt->fetchColumn();
                if ($prevStatus !== false) {
                    $stUp = strtoupper((string) $prevStatus);
                    $msg = match ($stUp) {
                        'REJECTED' => 'Yêu cầu hoàn tiền cho phòng này đã bị Quản trị viên từ chối trước đó, không thể gửi lại.',
                        'APPROVED', 'PROCESSING' => 'Yêu cầu hoàn tiền cho phòng này đã được Quản trị viên phê duyệt, không thể gửi thêm.',
                        'COMPLETED' => 'Tiền cọc cho phòng này đã được hoàn tất chuyển khoản.',
                        default => 'Bạn đã gửi yêu cầu hoàn tiền cho phòng này và đang chờ Quản trị viên xét duyệt.',
                    };
                    return Response::fail($msg, 'DUPLICATE_REFUND_REQUEST', 409);
                }
            }

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