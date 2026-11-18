<?php

declare(strict_types=1);

namespace App\Features\PaymentRefund\Repositories;

use App\Infrastructure\Database\BaseRepository;
use PDO;

class PaymentRepository extends BaseRepository
{
    /**
     * Find payment record by ID.
     *
     * @return array<string, mixed>|null
     */
    public function findPaymentById(int $id): ?array
    {
        return $this->executeQuery(function () use ($id): ?array {
            $stmt = $this->pdo->prepare(
                'SELECT `id`, `room_participant_id`, `session_id`, `payment_type`, `amount_due`,
                        `amount_paid`, `status`, `deadline`, `created_at`, `updated_at`
                 FROM `player_payments`
                 WHERE `id` = :id
                 LIMIT 1'
            );
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result !== false ? $result : null;
        });
    }

    /**
     * Create player payment record.
     *
     * @param array{
     *     room_participant_id: int,
     *     session_id: int,
     *     payment_type: string,
     *     amount_due: int,
     *     amount_paid?: int,
     *     status?: string,
     *     deadline?: string|null
     * } $data
     */
    public function createPayment(array $data): int
    {
        return $this->executeQuery(function () use ($data): int {
            $stmt = $this->pdo->prepare(
                'INSERT INTO `player_payments` (`room_participant_id`, `session_id`, `payment_type`,
                                                `amount_due`, `amount_paid`, `status`, `deadline`,
                                                `created_at`, `updated_at`)
                 VALUES (:room_participant_id, :session_id, :payment_type,
                         :amount_due, :amount_paid, :status, :deadline,
                         NOW(), NOW())'
            );

            $stmt->bindValue(':room_participant_id', $data['room_participant_id'], PDO::PARAM_INT);
            $stmt->bindValue(':session_id', $data['session_id'], PDO::PARAM_INT);
            $stmt->bindValue(':payment_type', $data['payment_type'], PDO::PARAM_STR);
            $stmt->bindValue(':amount_due', $data['amount_due'], PDO::PARAM_INT);
            $stmt->bindValue(':amount_paid', $data['amount_paid'] ?? 0, PDO::PARAM_INT);
            $stmt->bindValue(':status', $data['status'] ?? 'PENDING', PDO::PARAM_STR);
            $stmt->bindValue(':deadline', $data['deadline'] ?? null, ($data['deadline'] ?? null) === null ? PDO::PARAM_NULL : PDO::PARAM_STR);

            $stmt->execute();

            return (int) $this->pdo->lastInsertId();
        });
    }

    /**
     * Create an idempotent payment attempt.
     */
    public function createAttempt(int $playerPaymentId, string $idempotencyKey, int $amount, string $paymentMethod = 'SIMULATED'): int
    {
        return $this->executeQuery(function () use ($playerPaymentId, $idempotencyKey, $amount, $paymentMethod): int {
            $stmt = $this->pdo->prepare(
                'INSERT INTO `payment_attempts` (`player_payment_id`, `attempt_number`, `idempotency_key`,
                                                `payment_method`, `amount`, `status`, `created_at`, `updated_at`)
                 VALUES (:player_payment_id, 1, :idempotency_key, :payment_method, :amount, \'INITIATED\', NOW(), NOW())'
            );

            $stmt->bindValue(':player_payment_id', $playerPaymentId, PDO::PARAM_INT);
            $stmt->bindValue(':idempotency_key', $idempotencyKey, PDO::PARAM_STR);
            $stmt->bindValue(':payment_method', $paymentMethod, PDO::PARAM_STR);
            $stmt->bindValue(':amount', $amount, PDO::PARAM_INT);

            $stmt->execute();

            return (int) $this->pdo->lastInsertId();
        });
    }

    /**
     * Find payment attempt by idempotency key.
     *
     * @return array<string, mixed>|null
     */
    public function findAttemptByIdempotencyKey(string $idempotencyKey): ?array
    {
        return $this->executeQuery(function () use ($idempotencyKey): ?array {
            $stmt = $this->pdo->prepare(
                'SELECT `id`, `player_payment_id`, `attempt_number`, `idempotency_key`,
                        `payment_method`, `amount`, `status`, `created_at`, `updated_at`
                 FROM `payment_attempts`
                 WHERE `idempotency_key` = :idempotency_key
                 LIMIT 1'
            );
            $stmt->bindValue(':idempotency_key', $idempotencyKey, PDO::PARAM_STR);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result !== false ? $result : null;
        });
    }

    /**
     * Record a confirmed payment transaction ledger entry.
     */
    public function recordTransaction(int $paymentAttemptId, string $reference, int $amount, string $currency = 'VND'): int
    {
        return $this->executeQuery(function () use ($paymentAttemptId, $reference, $amount, $currency): int {
            $stmt = $this->pdo->prepare(
                'INSERT INTO `payment_transactions` (`payment_attempt_id`, `transaction_reference`, `amount`, `currency`, `transacted_at`, `created_at`)
                 VALUES (:payment_attempt_id, :transaction_reference, :amount, :currency, NOW(), NOW())'
            );

            $stmt->bindValue(':payment_attempt_id', $paymentAttemptId, PDO::PARAM_INT);
            $stmt->bindValue(':transaction_reference', $reference, PDO::PARAM_STR);
            $stmt->bindValue(':amount', $amount, PDO::PARAM_INT);
            $stmt->bindValue(':currency', $currency, PDO::PARAM_STR);

            $stmt->execute();

            return (int) $this->pdo->lastInsertId();
        });
    }

    /**
     * Create refund record.
     */
    public function createRefund(
        int $playerPaymentId,
        int $roomParticipantId,
        int $refundAmount,
        string $reason,
        string $refundType = 'FULL',
        ?int $processedByUserId = null
    ): int {
        return $this->executeQuery(function () use (
            $playerPaymentId,
            $roomParticipantId,
            $refundAmount,
            $reason,
            $refundType,
            $processedByUserId
        ): int {
            $stmt = $this->pdo->prepare(
                'INSERT INTO `refunds` (`player_payment_id`, `room_participant_id`, `processed_by_user_id`,
                                        `reason`, `refund_type`, `refund_amount`, `status`, `created_at`, `updated_at`)
                 VALUES (:player_payment_id, :room_participant_id, :processed_by_user_id,
                         :reason, :refund_type, :refund_amount, \'PENDING\', NOW(), NOW())'
            );

            $stmt->bindValue(':player_payment_id', $playerPaymentId, PDO::PARAM_INT);
            $stmt->bindValue(':room_participant_id', $roomParticipantId, PDO::PARAM_INT);
            $stmt->bindValue(':processed_by_user_id', $processedByUserId, $processedByUserId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':reason', $reason, PDO::PARAM_STR);
            $stmt->bindValue(':refund_type', $refundType, PDO::PARAM_STR);
            $stmt->bindValue(':refund_amount', $refundAmount, PDO::PARAM_INT);

            $stmt->execute();

            return (int) $this->pdo->lastInsertId();
        });
    }
}
