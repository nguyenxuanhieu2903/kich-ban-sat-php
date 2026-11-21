<?php

declare(strict_types=1);

namespace App\Features\Payment;

use App\Core\Exceptions\BadRequestException;
use App\Core\Exceptions\HttpException;
use App\Core\Exceptions\NotFoundException;
use App\Infrastructure\Database\DatabaseConnection;
use DateTimeImmutable;
use PDO;

class PaymentService
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DatabaseConnection::getInstance();
    }

    /** @return array<string, mixed> */
    public function payDeposit(int $roomId, int $userId, string $provider = 'VNPAY', ?string $idempotencyKey = null): array
    {
        $this->requireActiveUser($userId, ['PLAYER', 'ADMIN']);
        $cleanKey = ($idempotencyKey !== null && trim($idempotencyKey) !== '') ? mb_substr(trim($idempotencyKey), 0, 100) : null;

        if ($cleanKey !== null) {
            $existing = $this->findByIdempotencyKey($cleanKey);
            if ($existing !== null) {
                if ((int) $existing['user_id'] !== $userId || (int) $existing['room_id'] !== $roomId || (string) $existing['payment_type'] !== 'DEPOSIT') {
                    throw new HttpException('Idempotency key đã được sử dụng cho giao dịch khác', 409);
                }
                return $this->formatTransaction($existing, true);
            }
        }

        $now = new DateTimeImmutable();
        $lockSql = $this->getRowLockSql();

        $this->pdo->beginTransaction();
        try {
            $room = $this->fetchRoomOrFail($roomId, $lockSql);
            $status = (string) $room['status'];
            if (in_array($status, ['CANCELLED', 'COMPLETED', 'EXPIRED'], true)) {
                throw new HttpException("Phòng đang ở trạng thái {$status}, không thể đặt cọc", 409);
            }

            $participant = $this->fetchParticipantOrFail($roomId, $userId, $lockSql);
            if ((string) $participant['status'] === 'LEFT') {
                throw new HttpException('Người chơi đã rời phòng', 409);
            }
            if (in_array((string) $participant['status'], ['DEPOSITED', 'PAID_FULL'], true)) {
                throw new HttpException('Người chơi đã đặt cọc cho phòng này rồi', 409);
            }

            if (!empty($participant['deposit_deadline_at'])) {
                $deadline = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string) $participant['deposit_deadline_at']);
                if ($deadline !== false && $now > $deadline) {
                    throw new HttpException('Đã quá hạn đặt cọc cho phòng này', 409);
                }
            }

            $rule = json_decode((string) $room['snapshot_game_rule'], true);
            $amount = (is_array($rule) && isset($rule['deposit_vnd'])) ? (int) $rule['deposit_vnd'] : 50000;
            if ($amount <= 0) {
                throw new BadRequestException('Số tiền đặt cọc không hợp lệ');
            }

            $txKey = $cleanKey ?? ('DEP-' . $roomId . '-' . $userId . '-' . bin2hex(random_bytes(4)));
            $ins = $this->pdo->prepare(
                "INSERT INTO payment_transactions
                    (room_id, participant_id, user_id, idempotency_key, payment_type, amount_vnd, status, note)
                 VALUES
                    (:room_id, :part_id, :user_id, :idem_key, 'DEPOSIT', :amount, 'DEPOSIT_PENDING', :note)"
            );
            $ins->execute([
                ':room_id' => $roomId,
                ':part_id' => (int) $participant['id'],
                ':user_id' => $userId,
                ':idem_key' => $txKey,
                ':amount' => $amount,
                ':note' => 'Provider: ' . strtoupper(trim($provider)),
            ]);
            $txId = (int) $this->pdo->lastInsertId();

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return $this->formatTransaction($this->fetchTransactionOrFail($txId), false);
    }

    /** @return array<string, mixed> */
    public function payRemaining(int $roomId, int $userId, string $provider = 'VNPAY', ?string $idempotencyKey = null): array
    {
        $this->requireActiveUser($userId, ['PLAYER', 'ADMIN']);
        $cleanKey = ($idempotencyKey !== null && trim($idempotencyKey) !== '') ? mb_substr(trim($idempotencyKey), 0, 100) : null;

        if ($cleanKey !== null) {
            $existing = $this->findByIdempotencyKey($cleanKey);
            if ($existing !== null) {
                if ((int) $existing['user_id'] !== $userId || (int) $existing['room_id'] !== $roomId || (string) $existing['payment_type'] !== 'REMAINING') {
                    throw new HttpException('Idempotency key đã được sử dụng cho giao dịch khác', 409);
                }
                return $this->formatTransaction($existing, true);
            }
        }

        $lockSql = $this->getRowLockSql();
        $this->pdo->beginTransaction();
        try {
            $room = $this->fetchRoomOrFail($roomId, $lockSql);
            $status = (string) $room['status'];
            if (!in_array($status, ['CONFIRMED', 'COMPLETED'], true)) {
                throw new HttpException('Chỉ có thể thanh toán phần còn lại khi phòng đã CONFIRMED hoặc COMPLETED', 409);
            }

            $participant = $this->fetchParticipantOrFail($roomId, $userId, $lockSql);
            $partStatus = (string) $participant['status'];
            if ($partStatus === 'PAID_FULL') {
                throw new HttpException('Người chơi đã thanh toán đủ toàn bộ tiền vé', 409);
            }
            if ($partStatus !== 'DEPOSITED') {
                throw new HttpException('Người chơi cần hoàn tất đặt cọc trước khi thanh toán phần còn lại', 409);
            }

            $rule = json_decode((string) $room['snapshot_game_rule'], true);
            $ticketPrice = is_array($rule) ? (int) ($rule['ticket_price_vnd'] ?? 0) : 0;
            $depositRule = is_array($rule) ? (int) ($rule['deposit_vnd'] ?? 50000) : 50000;

            $depSumStmt = $this->pdo->prepare(
                "SELECT COALESCE(SUM(amount_vnd), 0) FROM payment_transactions
                 WHERE participant_id = :part_id AND payment_type = 'DEPOSIT' AND status = 'SUCCESS'"
            );
            $depSumStmt->execute([':part_id' => (int) $participant['id']]);
            $depositPaid = (int) $depSumStmt->fetchColumn();
            if ($depositPaid <= 0) {
                $depositPaid = $depositRule;
            }

            $remainingAmount = $ticketPrice - $depositPaid;
            if ($remainingAmount <= 0) {
                throw new BadRequestException('Không còn số tiền cần thanh toán thêm');
            }

            $txKey = $cleanKey ?? ('REM-' . $roomId . '-' . $userId . '-' . bin2hex(random_bytes(4)));
            $ins = $this->pdo->prepare(
                "INSERT INTO payment_transactions
                    (room_id, participant_id, user_id, idempotency_key, payment_type, amount_vnd, status, note)
                 VALUES
                    (:room_id, :part_id, :user_id, :idem_key, 'REMAINING', :amount, 'REMAINING_PENDING', :note)"
            );
            $ins->execute([
                ':room_id' => $roomId,
                ':part_id' => (int) $participant['id'],
                ':user_id' => $userId,
                ':idem_key' => $txKey,
                ':amount' => $remainingAmount,
                ':note' => 'Provider: ' . strtoupper(trim($provider)),
            ]);
            $txId = (int) $this->pdo->lastInsertId();

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return $this->formatTransaction($this->fetchTransactionOrFail($txId), false);
    }

    /** @return array<string, mixed> */
    public function confirmPayment(int $paymentId, int $actorUserId, string $actorRole, ?string $note = null): array
    {
        if (strtoupper($actorRole) !== 'ADMIN') {
            throw new HttpException('Chỉ quản trị viên mới có quyền xác nhận thanh toán', 403);
        }
        $this->requireActiveUser($actorUserId, ['ADMIN']);

        $nowStr = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $lockSql = $this->getRowLockSql();

        $this->pdo->beginTransaction();
        try {
            $tx = $this->fetchTransactionOrFail($paymentId, $lockSql);
            $txStatus = (string) $tx['status'];
            if (!in_array($txStatus, ['DEPOSIT_PENDING', 'REMAINING_PENDING'], true)) {
                throw new HttpException("Không thể xác nhận giao dịch đang ở trạng thái {$txStatus}", 409);
            }

            $updTx = $this->pdo->prepare(
                "UPDATE payment_transactions
                 SET status = 'SUCCESS', confirmed_by = :confirmed_by, paid_at = :paid_at, note = COALESCE(:note, note)
                 WHERE id = :id"
            );
            $updTx->execute([
                ':confirmed_by' => $actorUserId,
                ':paid_at' => $nowStr,
                ':note' => ($note !== null && trim($note) !== '') ? mb_substr(trim($note), 0, 255) : null,
                ':id' => $paymentId,
            ]);

            $newPartStatus = ((string) $tx['payment_type'] === 'DEPOSIT') ? 'DEPOSITED' : 'PAID_FULL';
            $updPart = $this->pdo->prepare('UPDATE room_participants SET status = :status WHERE id = :id');
            $updPart->execute([
                ':status' => $newPartStatus,
                ':id' => (int) $tx['participant_id'],
            ]);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return $this->formatTransaction($this->fetchTransactionOrFail($paymentId), false);
    }

    /** @return array<string, mixed> */
    public function failPayment(int $paymentId, int $actorUserId, string $actorRole, ?string $reason = null): array
    {
        if (strtoupper($actorRole) !== 'ADMIN') {
            throw new HttpException('Chỉ quản trị viên mới có quyền đánh dấu giao dịch thất bại', 403);
        }
        $this->requireActiveUser($actorUserId, ['ADMIN']);

        $lockSql = $this->getRowLockSql();
        $this->pdo->beginTransaction();
        try {
            $tx = $this->fetchTransactionOrFail($paymentId, $lockSql);
            if (!in_array((string) $tx['status'], ['DEPOSIT_PENDING', 'REMAINING_PENDING'], true)) {
                throw new HttpException('Chỉ có thể đánh dấu thất bại giao dịch đang chờ xử lý', 409);
            }

            $upd = $this->pdo->prepare(
                "UPDATE payment_transactions SET status = 'FAILED', note = :note WHERE id = :id"
            );
            $upd->execute([
                ':note' => ($reason !== null && trim($reason) !== '') ? mb_substr(trim($reason), 0, 255) : 'Thanh toán thất bại',
                ':id' => $paymentId,
            ]);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return $this->formatTransaction($this->fetchTransactionOrFail($paymentId), false);
    }

    /** @return array<string, mixed> */
    public function retryPayment(int $paymentId, int $actorUserId, string $actorRole, ?string $newIdempotencyKey = null): array
    {
        $this->requireActiveUser($actorUserId, ['PLAYER', 'ADMIN']);

        $lockSql = $this->getRowLockSql();
        $this->pdo->beginTransaction();
        try {
            $oldTx = $this->fetchTransactionOrFail($paymentId, $lockSql);
            $isAdmin = strtoupper($actorRole) === 'ADMIN';
            if (!$isAdmin && (int) $oldTx['user_id'] !== $actorUserId) {
                throw new HttpException('Bạn không có quyền thử lại giao dịch của người khác', 403);
            }
            if ((string) $oldTx['status'] !== 'FAILED') {
                throw new HttpException('Chỉ có thể thử lại giao dịch đang ở trạng thái FAILED', 409);
            }

            $type = (string) $oldTx['payment_type'];
            $pendingStatus = ($type === 'DEPOSIT') ? 'DEPOSIT_PENDING' : 'REMAINING_PENDING';
            $cleanKey = ($newIdempotencyKey !== null && trim($newIdempotencyKey) !== '')
                ? mb_substr(trim($newIdempotencyKey), 0, 100)
                : ('RETRY-' . $paymentId . '-' . bin2hex(random_bytes(4)));

            $ins = $this->pdo->prepare(
                "INSERT INTO payment_transactions
                    (room_id, participant_id, user_id, idempotency_key, payment_type, amount_vnd, status, note)
                 VALUES
                    (:room_id, :part_id, :user_id, :idem_key, :type, :amount, :status, :note)"
            );
            $ins->execute([
                ':room_id' => (int) $oldTx['room_id'],
                ':part_id' => (int) $oldTx['participant_id'],
                ':user_id' => (int) $oldTx['user_id'],
                ':idem_key' => $cleanKey,
                ':type' => $type,
                ':amount' => (int) $oldTx['amount_vnd'],
                ':status' => $pendingStatus,
                ':note' => 'Retry of payment #' . $paymentId,
            ]);
            $newId = (int) $this->pdo->lastInsertId();

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return $this->formatTransaction($this->fetchTransactionOrFail($newId), false);
    }

    /** @return array<string, mixed> */
    public function requestRefund(int $paymentId, int $actorUserId, string $actorRole, string $reason): array
    {
        $this->requireActiveUser($actorUserId, ['PLAYER', 'ADMIN']);
        $cleanReason = trim($reason);
        if ($cleanReason === '') {
            throw new BadRequestException('Vui lòng nhập lý do yêu cầu hoàn tiền');
        }

        $lockSql = $this->getRowLockSql();
        $this->pdo->beginTransaction();
        try {
            $tx = $this->fetchTransactionOrFail($paymentId, $lockSql);
            $isAdmin = strtoupper($actorRole) === 'ADMIN';
            if (!$isAdmin && (int) $tx['user_id'] !== $actorUserId) {
                throw new HttpException('Bạn không có quyền yêu cầu hoàn tiền cho giao dịch này', 403);
            }
            if ((string) $tx['status'] !== 'SUCCESS') {
                throw new HttpException('Chỉ có thể yêu cầu hoàn tiền cho giao dịch đã SUCCESS', 409);
            }

            $dupStmt = $this->pdo->prepare(
                "SELECT id FROM refunds WHERE payment_id = :pay_id AND status IN ('REQUESTED', 'PROCESSED') LIMIT 1{$lockSql}"
            );
            $dupStmt->execute([':pay_id' => $paymentId]);
            if ($dupStmt->fetch() !== false) {
                throw new HttpException('Giao dịch này đã có yêu cầu hoàn tiền đang xử lý hoặc đã hoàn tất', 409);
            }

            $ins = $this->pdo->prepare(
                "INSERT INTO refunds (payment_id, participant_id, amount_vnd, status, reason)
                 VALUES (:pay_id, :part_id, :amount, 'REQUESTED', :reason)"
            );
            $ins->execute([
                ':pay_id' => $paymentId,
                ':part_id' => (int) $tx['participant_id'],
                ':amount' => (int) $tx['amount_vnd'],
                ':reason' => mb_substr($cleanReason, 0, 500),
            ]);
            $refundId = (int) $this->pdo->lastInsertId();

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return $this->fetchRefundOrFail($refundId);
    }

    /** @return array<string, mixed> */
    public function processRefund(
        int $refundId,
        int $actorUserId,
        string $actorRole,
        string $decision,
        ?string $referenceCode = null
    ): array {
        if (strtoupper($actorRole) !== 'ADMIN') {
            throw new HttpException('Chỉ quản trị viên mới có quyền duyệt hoàn tiền', 403);
        }
        $this->requireActiveUser($actorUserId, ['ADMIN']);

        $normalized = strtoupper(trim($decision));
        if (!in_array($normalized, ['PROCESSED', 'REJECTED'], true)) {
            throw new BadRequestException('Quyết định hoàn tiền chỉ chấp nhận PROCESSED hoặc REJECTED');
        }

        $lockSql = $this->getRowLockSql();
        $this->pdo->beginTransaction();
        try {
            $rStmt = $this->pdo->prepare("SELECT * FROM refunds WHERE id = :id{$lockSql}");
            $rStmt->execute([':id' => $refundId]);
            $refund = $rStmt->fetch();
            if ($refund === false) {
                throw new NotFoundException('Không tìm thấy yêu cầu hoàn tiền');
            }
            if ((string) $refund['status'] !== 'REQUESTED') {
                throw new HttpException('Yêu cầu hoàn tiền này đã được xử lý trước đó', 409);
            }

            $refCode = ($referenceCode !== null && trim($referenceCode) !== '')
                ? mb_substr(trim($referenceCode), 0, 100)
                : ($normalized === 'PROCESSED' ? 'REF-' . $refundId . '-' . strtoupper(bin2hex(random_bytes(3))) : null);

            $updRef = $this->pdo->prepare(
                'UPDATE refunds SET status = :status, reference_code = :ref_code, processed_by = :processed_by WHERE id = :id'
            );
            $updRef->execute([
                ':status' => $normalized,
                ':ref_code' => $refCode,
                ':processed_by' => $actorUserId,
                ':id' => $refundId,
            ]);

            if ($normalized === 'PROCESSED') {
                $updPay = $this->pdo->prepare("UPDATE payment_transactions SET status = 'REFUNDED' WHERE id = :id");
                $updPay->execute([':id' => (int) $refund['payment_id']]);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return $this->fetchRefundOrFail($refundId);
    }

    /** @return array<int, array<string, mixed>> */
    public function listRoomPayments(int $roomId, int $actorUserId, string $actorRole): array
    {
        $this->requireActiveUser($actorUserId, ['PLAYER', 'ADMIN', 'DM']);

        $isAdmin = strtoupper($actorRole) === 'ADMIN';
        if ($isAdmin) {
            $stmt = $this->pdo->prepare('SELECT * FROM payment_transactions WHERE room_id = :room_id ORDER BY id ASC');
            $stmt->execute([':room_id' => $roomId]);
        } else {
            $stmt = $this->pdo->prepare('SELECT * FROM payment_transactions WHERE room_id = :room_id AND user_id = :user_id ORDER BY id ASC');
            $stmt->execute([':room_id' => $roomId, ':user_id' => $actorUserId]);
        }

        return array_map(fn (array $row): array => $this->formatTransaction($row, false), $stmt->fetchAll());
    }

    /** @return array<string, mixed>|null */
    private function findByIdempotencyKey(string $key): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM payment_transactions WHERE idempotency_key = :key LIMIT 1');
        $stmt->execute([':key' => $key]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed> */
    private function fetchRoomOrFail(int $roomId, string $lockSql = ''): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM game_rooms WHERE id = :id{$lockSql}");
        $stmt->execute([':id' => $roomId]);
        $room = $stmt->fetch();
        if ($room === false) {
            throw new NotFoundException('Không tìm thấy phòng chơi');
        }

        return $room;
    }

    /** @return array<string, mixed> */
    private function fetchParticipantOrFail(int $roomId, int $userId, string $lockSql = ''): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM room_participants WHERE room_id = :room_id AND user_id = :user_id ORDER BY id DESC LIMIT 1{$lockSql}"
        );
        $stmt->execute([':room_id' => $roomId, ':user_id' => $userId]);
        $part = $stmt->fetch();
        if ($part === false) {
            throw new NotFoundException('Người chơi không thuộc phòng này');
        }

        return $part;
    }

    /** @return array<string, mixed> */
    private function fetchTransactionOrFail(int $paymentId, string $lockSql = ''): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM payment_transactions WHERE id = :id{$lockSql}");
        $stmt->execute([':id' => $paymentId]);
        $tx = $stmt->fetch();
        if ($tx === false) {
            throw new NotFoundException('Không tìm thấy giao dịch thanh toán');
        }

        return $tx;
    }

    /** @return array<string, mixed> */
    private function fetchRefundOrFail(int $refundId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM refunds WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $refundId]);
        $row = $stmt->fetch();
        if ($row === false) {
            throw new NotFoundException('Không tìm thấy yêu cầu hoàn tiền');
        }

        return [
            'id' => (int) $row['id'],
            'payment_id' => (int) $row['payment_id'],
            'participant_id' => (int) $row['participant_id'],
            'amount_vnd' => (int) $row['amount_vnd'],
            'status' => (string) $row['status'],
            'reference_code' => $row['reference_code'] !== null ? (string) $row['reference_code'] : null,
            'reason' => (string) $row['reason'],
            'processed_by' => $row['processed_by'] !== null ? (int) $row['processed_by'] : null,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function formatTransaction(array $row, bool $idempotentReplay): array
    {
        return [
            'id' => (int) $row['id'],
            'room_id' => (int) $row['room_id'],
            'participant_id' => (int) $row['participant_id'],
            'user_id' => (int) $row['user_id'],
            'idempotency_key' => $row['idempotency_key'] !== null ? (string) $row['idempotency_key'] : null,
            'payment_type' => (string) $row['payment_type'],
            'amount_vnd' => (int) $row['amount_vnd'],
            'status' => (string) $row['status'],
            'confirmed_by' => $row['confirmed_by'] !== null ? (int) $row['confirmed_by'] : null,
            'note' => $row['note'] !== null ? (string) $row['note'] : null,
            'paid_at' => $row['paid_at'] !== null ? (string) $row['paid_at'] : null,
            'idempotent_replay' => $idempotentReplay,
        ];
    }

    /**
     * @param string[]|null $allowedRoles
     * @return array<string, mixed>
     */
    private function requireActiveUser(int $userId, ?array $allowedRoles = null): array
    {
        $stmt = $this->pdo->prepare('SELECT id, role, is_active FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch();

        if ($user === false || (int) $user['is_active'] !== 1) {
            throw new HttpException('Tài khoản không tồn tại hoặc đã bị khóa', 403);
        }

        if ($allowedRoles !== null) {
            $role = strtoupper((string) $user['role']);
            if (!in_array($role, $allowedRoles, true)) {
                throw new HttpException('Tài khoản không có quyền thực hiện thao tác này', 403);
            }
        }

        return $user;
    }

    private function getRowLockSql(): string
    {
        return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
    }
}