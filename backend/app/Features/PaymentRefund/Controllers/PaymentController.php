<?php

declare(strict_types=1);

namespace App\Features\PaymentRefund\Controllers;

use App\Core\Middleware\AuthMiddleware;
use App\Core\Request;
use App\Core\Response;
use App\Features\Notification\Repositories\NotificationRepository;
use App\Features\PaymentRefund\Repositories\PaymentRepository;
use App\Features\Pricing\Services\PricingService;
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
     * GET /api/v1/payments/{id}
     * Lấy chi tiết một khoản thanh toán (chống IDOR)
     */
    public function show(Request $request, string $id): Response
    {
        $auth = AuthMiddleware::authenticate($request);
        if ($auth instanceof Response) {
            return $auth;
        }

        $payment = $this->paymentRepo->findPaymentWithDetails((int) $id);
        if ($payment === null) {
            return Response::fail('Không tìm thấy thông tin thanh toán', 'PAYMENT_NOT_FOUND', 404);
        }

        $userRole = strtoupper((string) ($auth['role_code'] ?? 'PLAYER'));
        if (!in_array($userRole, ['ADMIN', 'DM'], true) && (int) $payment['user_id'] !== (int) $auth['id']) {
            return Response::fail('Bạn không có quyền xem khoản thanh toán này', 'FORBIDDEN', 403);
        }

        return Response::success(['payment' => $payment], 'Chi tiết khoản thanh toán');
    }

    /**
     * GET /api/v1/rooms/{id}/payments
     * Player chỉ xem hóa đơn của chính mình; Admin/DM xem toàn bộ phòng.
     */
    public function listByRoom(Request $request, string $id): Response
    {
        $auth = AuthMiddleware::authenticate($request);
        if ($auth instanceof Response) {
            return $auth;
        }

        $roomId = (int) $id;
        $userRole = strtoupper((string) ($auth['role_code'] ?? 'PLAYER'));
        $filterUserId = in_array($userRole, ['ADMIN', 'DM'], true) ? null : (int) $auth['id'];

        $payments = $this->paymentRepo->listByRoomId($roomId, $filterUserId);

        return Response::success([
            'room_id' => $roomId,
            'payments' => $payments,
            'count' => count($payments),
        ], 'Danh sách khoản thanh toán của phòng');
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
            ?? $request->getHeader('idempotency-key')
            ?? (string) ($request->input('idempotency_key') ?? ('DEP_' . $id . '_' . bin2hex(random_bytes(8))));

        $totalAmount = (int) $payment['amount'];
        $alreadyPaid = (int) $payment['paid_amount'];
        $remaining = max(0, $totalAmount - $alreadyPaid);

        $bodyAmount = $request->input('amount');
        if ($bodyAmount !== null && (int) $bodyAmount > 0) {
            $depositAmount = min((int) $bodyAmount, $remaining);
        } else {
            // Quy chuẩn V16: Cọc cố định 50.000 VNĐ / người
            $depositAmount = min(PricingService::DEPOSIT_PER_PERSON, $remaining);
        }

        $method = (string) ($request->input('payment_method') ?? 'SIMULATED_MOMO');

        $result = $this->paymentRepo->processPayment($id, $depositAmount, $idempotencyKey, $method);

        // Tạo thông báo cho người dùng (chỉ gửi nếu giao dịch mới thành công)
        if (!empty($result['success']) && empty($result['idempotent_replay'])) {
            $this->notiRepo->createNotification(
                'Thanh toán cọc thành công',
                "Bạn đã thanh toán cọc thành công số tiền " . number_format($depositAmount) . " VNĐ cho phòng " . ($payment['room_title'] ?? ''),
                'PAYMENT',
                null,
                [(int) $payment['user_id']]
            );
        }

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
            ?? $request->getHeader('idempotency-key')
            ?? (string) ($request->input('idempotency_key') ?? ('REM_' . $id . '_' . bin2hex(random_bytes(8))));

        $method = (string) ($request->input('payment_method') ?? 'SIMULATED_VNPAY');

        $result = $this->paymentRepo->processPayment($id, $remaining, $idempotencyKey, $method);

        if (!empty($result['success']) && empty($result['idempotent_replay'])) {
            $this->notiRepo->createNotification(
                'Thanh toán hoàn tất',
                "Bạn đã thanh toán thành công số tiền còn lại " . number_format($remaining) . " VNĐ cho phòng " . ($payment['room_title'] ?? ''),
                'PAYMENT',
                null,
                [(int) $payment['user_id']]
            );
        }

        return Response::success($result, 'Thanh toán số tiền còn lại thành công');
    }

    /**
     * POST /api/v1/payments/deposit
     * Hỗ trợ thanh toán nhanh tiền cọc theo room_id từ frontend
     */
    public function quickDeposit(Request $request): Response
    {
        $auth = AuthMiddleware::authenticate($request);
        if ($auth instanceof Response) {
            return $auth;
        }

        $body = $request->getBodyParams();
        $roomId = (int) ($body['room_id'] ?? $body['game_room_id'] ?? 0);
        if ($roomId <= 0) {
            return Response::fail('Thiếu mã phòng chơi (room_id) để thực hiện thanh toán cọc', 'ROOM_ID_REQUIRED', 400);
        }
        $amount = (int) ($body['amount'] ?? 50000);

        // Hỗ trợ nộp cọc trọn gói cho nhóm bạn riêng (Điểm 3 & Điểm 21)
        $isLumpSum = !empty($body['is_lump_sum']) || !empty($body['is_group_lump_sum']) || ($body['deposit_type'] ?? '') === 'LUMP_SUM';
        if ($isLumpSum) {
            $rStmt = $this->pdo->prepare('SELECT `id`, `booking_type`, `total_players`, `guest_slots`, `creator_id` FROM `game_rooms` WHERE `id` = :rid LIMIT 1');
            $rStmt->execute([':rid' => $roomId]);
            $roomData = $rStmt->fetch(PDO::FETCH_ASSOC);

            if ($roomData && (int) $roomData['creator_id'] === (int) $auth['id']) {
                $totalPlayers = (int) ($roomData['total_players'] ?: 6);
                $guestSlots = (int) ($roomData['guest_slots'] ?: 0);
                $privateGroupSize = max(1, $totalPlayers - $guestSlots);
                $lumpSumAmount = $privateGroupSize * 50000;

                // Đảm bảo có bản ghi player_payments cho các thành viên active để đồng bộ báo cáo doanh thu & hoàn cọc
                $this->paymentRepo->ensureDepositPaymentsForRoom($roomId, PricingService::DEPOSIT_PER_PERSON, 2);

                // Cập nhật toàn bộ thành viên ĐANG HOẠT ĐỘNG thành DEPOSITED (tuyệt đối không hồi sinh người LEFT/CANCELLED)
                $this->pdo->prepare('UPDATE `room_participants` SET `status` = \'DEPOSITED\', `deposited_at` = COALESCE(`deposited_at`, NOW()), `updated_at` = NOW() WHERE `game_room_id` = :rid AND `status` IN (\'PENDING_DEPOSIT\', \'DEPOSITED\')')
                    ->execute([':rid' => $roomId]);

                // Ghi nhận thanh toán vào player_payments và payment_transactions của Chủ phòng (Host)
                $hostPayStmt = $this->pdo->prepare(
                    'SELECT p.`id` FROM `player_payments` p
                     INNER JOIN `room_participants` rp ON rp.`id` = p.`room_participant_id`
                     WHERE rp.`game_room_id` = :rid AND rp.`user_id` = :uid
                       AND rp.`status` IN (\'PENDING_DEPOSIT\', \'DEPOSITED\', \'LEAVE_REQUESTED\')
                     ORDER BY p.`id` DESC LIMIT 1'
                );
                $hostPayStmt->execute([':rid' => $roomId, ':uid' => (int) $auth['id']]);
                $hostPaymentId = $hostPayStmt->fetchColumn();
                if ($hostPaymentId !== false) {
                    $this->pdo->prepare(
                        'UPDATE `player_payments`
                         SET `amount` = GREATEST(`amount`, :amt), `paid_amount` = :paid_amt, `status` = \'PAID\', `paid_at` = NOW(), `updated_at` = NOW()
                         WHERE `id` = :pid'
                    )->execute([
                        ':amt' => $lumpSumAmount,
                        ':paid_amt' => $lumpSumAmount,
                        ':pid' => (int) $hostPaymentId,
                    ]);
                    $txRef = 'TX_LUMP_' . strtoupper(bin2hex(random_bytes(6)));
                    $idemKey = 'LUMP_' . $roomId . '_' . (int) $auth['id'] . '_' . time();
                    $this->pdo->prepare(
                        'INSERT INTO `payment_transactions` (`player_payment_id`, `idempotency_key`, `transaction_reference`, `amount`, `status`, `provider_response`, `requested_at`, `processed_at`, `created_at`)
                         VALUES (:pid, :ikey, :txref, :amt, \'SUCCESS\', :meta, NOW(), NOW(), NOW())'
                    )->execute([
                        ':pid' => (int) $hostPaymentId,
                        ':ikey' => $idemKey,
                        ':txref' => $txRef,
                        ':amt' => $lumpSumAmount,
                        ':meta' => json_encode(['method' => 'LUMP_SUM_PRIVATE', 'group_size' => $privateGroupSize]),
                    ]);
                }

                // Cập nhật trạng thái phòng sang CONFIRMED nếu đang SCHEDULED
                $this->pdo->prepare('UPDATE `game_rooms` SET `status` = \'CONFIRMED\', `updated_at` = NOW() WHERE `id` = :rid AND `status` = \'SCHEDULED\'')
                    ->execute([':rid' => $roomId]);

                // Bắn thông báo BOOKING_CONFIRMED
                $this->notiRepo->createNotification(
                    'Đặt phòng nhóm thành công (BOOKING_CONFIRMED)',
                    "Chủ phòng đã thanh toán cọc trọn gói cho {$privateGroupSize} người (" . number_format($lumpSumAmount) . " VNĐ). Phòng chơi đã được xác nhận chính thức!",
                    'ROOM',
                    null,
                    [(int) $auth['id']]
                );

                return Response::success([
                    'room_id' => $roomId,
                    'deposit_type' => 'LUMP_SUM',
                    'private_group_size' => $privateGroupSize,
                    'amount' => $lumpSumAmount,
                    'status' => 'CONFIRMED',
                ], "Thanh toán cọc trọn gói cho {$privateGroupSize} thành viên nhóm bạn thành công");
            }
        }

        $stmt = $this->pdo->prepare('SELECT p.`id` FROM `player_payments` p
            INNER JOIN `room_participants` rp ON rp.`id` = p.`room_participant_id`
            WHERE rp.`game_room_id` = :room_id AND rp.`user_id` = :user_id
              AND rp.`status` IN (\'PENDING_DEPOSIT\', \'DEPOSITED\', \'LEAVE_REQUESTED\')
            ORDER BY p.`id` DESC
            LIMIT 1');
        $stmt->bindValue(':room_id', $roomId, PDO::PARAM_INT);
        $stmt->bindValue(':user_id', (int) $auth['id'], PDO::PARAM_INT);
        $stmt->execute();
        $paymentId = $stmt->fetchColumn();

        if ($paymentId === false) {
            // Tự động tạo bản ghi thanh toán cọc cho thành viên phòng
            $this->paymentRepo->ensureDepositPaymentsForRoom($roomId, PricingService::DEPOSIT_PER_PERSON, 2);
            $stmt->execute();
            $paymentId = $stmt->fetchColumn();
        }

        if ($paymentId !== false) {
            $paymentType = strtoupper(trim((string) ($body['payment_type'] ?? 'DEPOSIT')));
            if ($paymentType === 'REMAINING') {
                return $this->remaining($request, (string) $paymentId);
            }
            return $this->deposit($request, (string) $paymentId);
        }

        // Cập nhật trực tiếp trạng thái DEPOSITED cho thành viên đang PENDING_DEPOSIT (tuyệt đối không chạm bản ghi LEFT)
        $partStmt = $this->pdo->prepare('UPDATE `room_participants` SET `status` = \'DEPOSITED\', `deposited_at` = NOW(), `updated_at` = NOW() WHERE `game_room_id` = :room_id AND `user_id` = :user_id AND `status` = \'PENDING_DEPOSIT\'');
        $partStmt->bindValue(':room_id', $roomId, PDO::PARAM_INT);
        $partStmt->bindValue(':user_id', (int) $auth['id'], PDO::PARAM_INT);
        $partStmt->execute();

        return Response::success([
            'room_id' => $roomId,
            'amount' => $amount,
            'status' => 'DEPOSITED',
        ], 'Thanh toán cọc 50.000 VNĐ thành công');
    }
}