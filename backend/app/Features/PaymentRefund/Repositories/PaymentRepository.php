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
                'SELECT `id`, `room_participant_id`, `payment_type`, `amount` AS `amount_due`, `amount`,
                        `paid_amount` AS `amount_paid`, `paid_amount`, `status`,
                        `deadline_at` AS `deadline`, `deadline_at`, `paid_at`, `expired_at`,
                        `created_at`, `updated_at`
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
     *     session_id?: int,
     *     payment_type: string,
     *     amount_due?: int,
     *     amount?: int,
     *     paid_amount?: int,
     *     status?: string,
     *     deadline?: string|null,
     *     deadline_at?: string|null
     * } $data
     */
    public function createPayment(array $data): int
    {
        return $this->executeQuery(function () use ($data): int {
            $amount = $data['amount'] ?? $data['amount_due'] ?? 0;
            $deadline = $data['deadline_at'] ?? $data['deadline'] ?? null;

            $stmt = $this->pdo->prepare(
                'INSERT INTO `player_payments` (`room_participant_id`, `payment_type`,
                                                `amount`, `paid_amount`, `status`, `deadline_at`,
                                                `created_at`, `updated_at`)
                 VALUES (:room_participant_id, :payment_type,
                         :amount, :paid_amount, :status, :deadline_at,
                         NOW(), NOW())'
            );

            $stmt->bindValue(':room_participant_id', $data['room_participant_id'], PDO::PARAM_INT);
            $stmt->bindValue(':payment_type', $data['payment_type'], PDO::PARAM_STR);
            $stmt->bindValue(':amount', $amount, PDO::PARAM_INT);
            $stmt->bindValue(':paid_amount', $data['paid_amount'] ?? 0, PDO::PARAM_INT);
            $stmt->bindValue(':status', $data['status'] ?? 'PENDING', PDO::PARAM_STR);
            $stmt->bindValue(':deadline_at', $deadline, $deadline === null ? PDO::PARAM_NULL : PDO::PARAM_STR);

            $stmt->execute();

            return (int) $this->pdo->lastInsertId();
        });
    }

    /**
     * Create an idempotent payment transaction.
     */
    public function createAttempt(int $playerPaymentId, string $idempotencyKey, int $amount, string $paymentMethod = 'SIMULATED'): int
    {
        return $this->executeQuery(function () use ($playerPaymentId, $idempotencyKey, $amount, $paymentMethod): int {
            $stmt = $this->pdo->prepare(
                'INSERT INTO `payment_transactions` (`player_payment_id`, `idempotency_key`, `transaction_reference`,
                                                     `amount`, `status`, `provider_response`, `requested_at`, `created_at`)
                 VALUES (:player_payment_id, :idempotency_key, :tx_ref,
                         :amount, \'PENDING\', :provider_response, NOW(), NOW())'
            );

            $txRef = 'TX_' . strtoupper(bin2hex(random_bytes(8)));
            $metadata = json_encode(['method' => $paymentMethod]);

            $stmt->bindValue(':player_payment_id', $playerPaymentId, PDO::PARAM_INT);
            $stmt->bindValue(':idempotency_key', $idempotencyKey, PDO::PARAM_STR);
            $stmt->bindValue(':tx_ref', $txRef, PDO::PARAM_STR);
            $stmt->bindValue(':amount', $amount, PDO::PARAM_INT);
            $stmt->bindValue(':provider_response', $metadata, PDO::PARAM_STR);

            $stmt->execute();

            return (int) $this->pdo->lastInsertId();
        });
    }

    /**
     * Find payment transaction by idempotency key.
     *
     * @return array<string, mixed>|null
     */
    public function findAttemptByIdempotencyKey(string $idempotencyKey): ?array
    {
        return $this->executeQuery(function () use ($idempotencyKey): ?array {
            $stmt = $this->pdo->prepare(
                'SELECT `id`, `player_payment_id`, `transaction_reference`, `idempotency_key`,
                        `amount`, `status`, `provider_response`, `requested_at`, `processed_at`, `created_at`
                 FROM `payment_transactions`
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
            $refundAmount,
            $reason,
            $processedByUserId
        ): int {
            $stmt = $this->pdo->prepare(
                'INSERT INTO `refunds` (`player_payment_id`, `amount`, `reason`, `status`, `requested_at`, `reviewed_by`, `created_at`, `updated_at`)
                 VALUES (:player_payment_id, :amount, :reason, \'PENDING\', NOW(), :reviewed_by, NOW(), NOW())'
            );

            $stmt->bindValue(':player_payment_id', $playerPaymentId, PDO::PARAM_INT);
            $stmt->bindValue(':amount', $refundAmount, PDO::PARAM_INT);
            $stmt->bindValue(':reason', $reason, PDO::PARAM_STR);
            $stmt->bindValue(':reviewed_by', $processedByUserId, $processedByUserId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);

            $stmt->execute();

            return (int) $this->pdo->lastInsertId();
        });
    }
}
