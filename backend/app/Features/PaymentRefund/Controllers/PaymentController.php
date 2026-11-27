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

        $actorRole = strtoupper((string) ($auth['role_code'] ?? $auth['role'] ?? 'PLAYER'));
        if (in_array($actorRole, ['DM', 'ADMIN'], true)) {
            return Response::fail(
                'Tài khoản Quản trò (DM) và Quản trị viên (Admin) chỉ điều phối/đối soát, không thực hiện đặt cọc người chơi',
                'FORBIDDEN_ROLE',
                403
            );
        }

        $body = $request->getBodyParams();
        $roomId = (int) ($body['room_id'] ?? $body['game_room_id'] ?? 0);
        if ($roomId <= 0) {
            return Response::fail('Thiếu mã phòng chơi (room_id) để thực hiện thanh toán cọc', 'ROOM_ID_REQUIRED', 400);
        }

        // Xác nhận người gọi là thành viên đang hoạt động trong phòng (chống thanh toán nhầm phòng chưa tham gia)
        $memChk = $this->pdo->prepare(
            'SELECT `id` FROM `room_participants`
             WHERE `game_room_id` = :rid AND `user_id` = :uid
               AND `status` IN (\'PENDING_DEPOSIT\', \'DEPOSITED\', \'LEAVE_REQUESTED\')
             LIMIT 1'
        );
        $memChk->execute([':rid' => $roomId, ':uid' => (int) $auth['id']]);
        if ($memChk->fetchColumn() === false) {
            return Response::fail('Bạn chưa tham gia phòng chơi này nên không thể thực hiện thanh toán cọc', 'NOT_ROOM_MEMBER', 403);
        }
        $amount = (int) ($body['amount'] ?? 50000);

        // Hỗ trợ nộp cọc gộp cho thành viên đại diện nhiều chỗ (seats > 1)
        $isLumpSum = !empty($body['is_lump_sum']) || !empty($body['is_group_lump_sum']) || ($body['deposit_type'] ?? '') === 'LUMP_SUM';
        $rStmt = $this->pdo->prepare('SELECT `id`, `booking_type`, `total_players`, `max_players`, `guest_slots`, `creator_id`, `price_details` FROM `game_rooms` WHERE `id` = :rid LIMIT 1');
        $rStmt->execute([':rid' => $roomId]);
        $roomData = $rStmt->fetch(PDO::FETCH_ASSOC);
        $activeUidsStmt = $this->pdo->prepare(
            'SELECT `user_id` FROM `room_participants`
             WHERE `game_room_id` = :rid AND `status` IN (\'PENDING_DEPOSIT\', \'DEPOSITED\', \'LEAVE_REQUESTED\')'
        );
        $activeUidsStmt->execute([':rid' => $roomId]);
        $activeUidsList = array_map('intval', $activeUidsStmt->fetchAll(PDO::FETCH_COLUMN));
        $mySeatsCount = $roomData ? \App\Shared\Services\RoomSeatHelper::getSeatsForUser($roomData, $activeUidsList, (int) $auth['id']) : 1;

        if ($roomData && ($isLumpSum || $mySeatsCount > 1)) {
            $groupSize = max(1, $mySeatsCount);
            $lumpSumAmount = $groupSize * PricingService::DEPOSIT_PER_PERSON;

            $this->paymentRepo->ensureDepositPaymentsForRoom($roomId, PricingService::DEPOSIT_PER_PERSON, 2);

            $this->pdo->prepare('UPDATE `room_participants` SET `status` = \'DEPOSITED\', `deposited_at` = COALESCE(`deposited_at`, NOW()), `updated_at` = NOW() WHERE `game_room_id` = :rid AND `user_id` = :uid AND `status` IN (\'PENDING_DEPOSIT\', \'DEPOSITED\')')
                ->execute([':rid' => $roomId, ':uid' => (int) $auth['id']]);

            $hostPayStmt = $this->pdo->prepare(
                'SELECT p.`id` FROM `player_payments` p
                 INNER JOIN `room_participants` rp ON rp.`id` = p.`room_participant_id`
                 WHERE rp.`game_room_id` = :rid AND rp.`user_id` = :uid
                   AND rp.`status` IN (\'PENDING_DEPOSIT\', \'DEPOSITED\', \'LEAVE_REQUESTED\')
                   AND p.`payment_type` = \'DEPOSIT\'
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
                    ':meta' => json_encode(['method' => 'GROUP_DEPOSIT', 'seats' => $groupSize]),
                ]);
            }

            $this->notiRepo->createNotification(
                'Thanh toán cọc giữ chỗ thành công',
                "Bạn đã thanh toán cọc giữ {$groupSize} chỗ (" . number_format($lumpSumAmount) . " đ) cho phòng #{$roomId}.",
                'PAYMENT',
                null,
                [(int) $auth['id']],
                'ROOM',
                $roomId
            );

            return Response::success([
                'room_id' => $roomId,
                'seats' => $groupSize,
                'amount' => $lumpSumAmount,
                'status' => 'DEPOSITED',
            ], "Thanh toán cọc giữ {$groupSize} chỗ thành công");
        }

        $paymentType = strtoupper(trim((string) ($body['payment_type'] ?? 'DEPOSIT')));

        // Xử lý thanh toán phần còn lại (REMAINING): tìm hoặc tự khởi tạo dòng REMAINING theo đúng giá vé trừ cọc đã đóng
        if ($paymentType === 'REMAINING') {
            $partStmt = $this->pdo->prepare(
                'SELECT rp.`id` AS `participant_id`, gr.`game_id`, gr.`creator_id`, gr.`total_players`, gr.`target_time_slot`, gr.`status` AS `room_status`
                 FROM `room_participants` rp
                 INNER JOIN `game_rooms` gr ON gr.`id` = rp.`game_room_id`
                 WHERE rp.`game_room_id` = :rid AND rp.`user_id` = :uid
                   AND rp.`status` IN (\'PENDING_DEPOSIT\', \'DEPOSITED\', \'LEAVE_REQUESTED\')
                 LIMIT 1'
            );
            $partStmt->execute([':rid' => $roomId, ':uid' => (int) $auth['id']]);
            $partInfo = $partStmt->fetch(PDO::FETCH_ASSOC);
            if ($partInfo === false) {
                return Response::fail('Bạn không phải thành viên đang hoạt động trong phòng này', 'PARTICIPANT_NOT_FOUND', 404);
            }
            $roomStatusUpper = strtoupper((string) ($partInfo['room_status'] ?? ''));
            if (!in_array($roomStatusUpper, ['SCHEDULED', 'CONFIRMED', 'IN_PROGRESS'], true)) {
                return Response::fail(
                    'Phòng chưa chốt ca chơi (Trạng thái: ' . $roomStatusUpper . '). Vui lòng chỉ đóng cọc giữ chỗ; phần còn lại sẽ mở thanh toán sau khi chốt lịch hoặc tại quán.',
                    'ROOM_NOT_SCHEDULED',
                    422
                );
            }
            $participantId = (int) $partInfo['participant_id'];

            $remChk = $this->pdo->prepare(
                'SELECT `id`, `status` FROM `player_payments`
                 WHERE `room_participant_id` = :pid AND `payment_type` = \'REMAINING\'
                 ORDER BY `id` DESC LIMIT 1'
            );
            $remChk->execute([':pid' => $participantId]);
            $existingRem = $remChk->fetch(PDO::FETCH_ASSOC);

            if ($existingRem !== false) {
                return $this->remaining($request, (string) $existingRem['id']);
            }

            // Tính số tiền đã đóng (DEPOSIT)
            $paidStmt = $this->pdo->prepare(
                'SELECT COALESCE(SUM(`paid_amount`), 0) FROM `player_payments` WHERE `room_participant_id` = :pid'
            );
            $paidStmt->execute([':pid' => $participantId]);
            $alreadyPaid = (int) $paidStmt->fetchColumn();

            $seatCount = $mySeatsCount;

            $pricingService = new PricingService($this->pdo);
            $slotRaw = trim((string) ($partInfo['target_time_slot'] ?? ''));
            $playDt = $slotRaw !== '' ? date('Y-m-d H:i:s', strtotime($slotRaw) ?: time()) : date('Y-m-d H:i:s');
            $isStu = !empty($auth['student_verified']);
            $quote = $pricingService->calculateQuote((int) $partInfo['game_id'], $seatCount, $playDt, $isStu);
            $serverFinal = (int) ($quote['final_total_vnd'] ?? 0);
            $serverRemaining = max(0, $serverFinal - $alreadyPaid);
            $requestedRemaining = isset($body['amount']) && (int) $body['amount'] > 0 ? (int) $body['amount'] : $serverRemaining;
            $effectiveRemaining = $serverRemaining > 0 ? min($serverRemaining, $requestedRemaining) : $requestedRemaining;

            if ($effectiveRemaining <= 0) {
                return Response::fail('Khoản phí này đã được thanh toán toàn bộ', 'PAYMENT_ALREADY_COMPLETED', 400);
            }

            $insRem = $this->pdo->prepare(
                'INSERT INTO `player_payments` (`room_participant_id`, `payment_type`, `amount`, `paid_amount`, `status`, `created_at`, `updated_at`)
                 VALUES (:pid, \'REMAINING\', :amt, 0, \'PENDING\', NOW(), NOW())'
            );
            $insRem->execute([':pid' => $participantId, ':amt' => $effectiveRemaining]);
            $remPaymentId = (int) $this->pdo->lastInsertId();

            return $this->remaining($request, (string) $remPaymentId);
        }

        $stmt = $this->pdo->prepare('SELECT p.`id` FROM `player_payments` p
            INNER JOIN `room_participants` rp ON rp.`id` = p.`room_participant_id`
            WHERE rp.`game_room_id` = :room_id AND rp.`user_id` = :user_id
              AND rp.`status` IN (\'PENDING_DEPOSIT\', \'DEPOSITED\', \'LEAVE_REQUESTED\')
              AND p.`payment_type` = \'DEPOSIT\'
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