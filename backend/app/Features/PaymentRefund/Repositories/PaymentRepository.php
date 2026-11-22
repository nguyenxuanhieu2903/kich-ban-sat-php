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

    /**
     * Find payment with participant and room details.
     *
     * @return array<string, mixed>|null
     */
    public function findPaymentWithDetails(int $id): ?array
    {
        return $this->executeQuery(function () use ($id): ?array {
            $stmt = $this->pdo->prepare(
                'SELECT p.`id`, p.`room_participant_id`, p.`payment_type`, p.`amount`, p.`paid_amount`,
                        p.`status`, p.`deadline_at`, p.`paid_at`, p.`expired_at`, p.`created_at`, p.`updated_at`,
                        rp.`user_id`, rp.`game_room_id`, rp.`status` AS `participant_status`,
                        u.`full_name` AS `user_name`, u.`email` AS `user_email`,
                        gr.`title` AS `room_title`
                 FROM `player_payments` p
                 INNER JOIN `room_participants` rp ON rp.`id` = p.`room_participant_id`
                 INNER JOIN `users` u ON u.`id` = rp.`user_id`
                 INNER JOIN `game_rooms` gr ON gr.`id` = rp.`game_room_id`
                 WHERE p.`id` = :id
                 LIMIT 1'
            );
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result !== false ? $result : null;
        });
    }

    /**
     * Process an idempotent payment.
     *
     * @return array{success: bool, payment: array<string, mixed>, transaction: array<string, mixed>}
     */
    public function processPayment(int $paymentId, int $payAmount, string $idempotencyKey, string $method = 'SIMULATED'): array
    {
        return $this->executeQuery(function () use ($paymentId, $payAmount, $idempotencyKey, $method): array {
            $this->pdo->beginTransaction();
            try {
                // 1. Kiểm tra Idempotency Key trong transaction
                $existingTx = $this->findAttemptByIdempotencyKey($idempotencyKey);
                if ($existingTx !== null) {
                    $payment = $this->findPaymentWithDetails($paymentId);
                    $this->pdo->commit();
                    return [
                        'success' => $existingTx['status'] === 'SUCCESS',
                        'payment' => $payment ?? [],
                        'transaction' => $existingTx,
                    ];
                }

                // 2. Khóa dòng bản ghi player_payments để bảo vệ concurrency
                $lockStmt = $this->pdo->prepare(
                    'SELECT `id`, `room_participant_id`, `payment_type`, `amount`, `paid_amount`, `status`, `deadline_at`
                     FROM `player_payments`
                     WHERE `id` = :id
                     FOR UPDATE'
                );
                $lockStmt->bindValue(':id', $paymentId, PDO::PARAM_INT);
                $lockStmt->execute();
                $payment = $lockStmt->fetch(PDO::FETCH_ASSOC);

                if ($payment === false) {
                    throw new \InvalidArgumentException('Không tìm thấy thông tin khoản thanh toán');
                }

                if ($payment['status'] === 'PAID') {
                    $this->pdo->commit();
                    $fullPayment = $this->findPaymentWithDetails($paymentId);
                    return [
                        'success' => true,
                        'payment' => $fullPayment ?? [],
                        'transaction' => [
                            'id' => 0,
                            'transaction_reference' => 'TX_ALREADY_PAID',
                            'amount' => 0,
                            'status' => 'SUCCESS',
                            'idempotency_key' => $idempotencyKey,
                        ],
                    ];
                }

                if ($payment['status'] === 'EXPIRED') {
                    throw new \DomainException('Khoản thanh toán này đã hết hạn');
                }

                $currentPaid = (int) $payment['paid_amount'];
                $totalAmount = (int) $payment['amount'];
                $remainingToPay = max(0, $totalAmount - $currentPaid);
                $actualPay = min($payAmount, $remainingToPay);

                if ($actualPay <= 0) {
                    $this->pdo->commit();
                    $fullPayment = $this->findPaymentWithDetails($paymentId);
                    return [
                        'success' => true,
                        'payment' => $fullPayment ?? [],
                        'transaction' => [
                            'id' => 0,
                            'transaction_reference' => 'TX_ALREADY_PAID',
                            'amount' => 0,
                            'status' => 'SUCCESS',
                            'idempotency_key' => $idempotencyKey,
                        ],
                    ];
                }

                $newPaid = $currentPaid + $actualPay;
                $newStatus = $newPaid >= $totalAmount ? 'PAID' : 'PARTIALLY_PAID';
                $txRef = 'TX_' . strtoupper(bin2hex(random_bytes(8)));
                $metadata = json_encode(['method' => $method, 'pay_amount' => $actualPay]);

                // 3. Ghi nhận giao dịch thanh toán
                $txStmt = $this->pdo->prepare(
                    'INSERT INTO `payment_transactions` (`player_payment_id`, `idempotency_key`, `transaction_reference`,
                                                         `amount`, `status`, `provider_response`, `requested_at`, `processed_at`, `created_at`)
                     VALUES (:payment_id, :idempotency_key, :tx_ref,
                             :amount, \'SUCCESS\', :provider_response, NOW(), NOW(), NOW())'
                );
                $txStmt->bindValue(':payment_id', $paymentId, PDO::PARAM_INT);
                $txStmt->bindValue(':idempotency_key', $idempotencyKey, PDO::PARAM_STR);
                $txStmt->bindValue(':tx_ref', $txRef, PDO::PARAM_STR);
                $txStmt->bindValue(':amount', $actualPay, PDO::PARAM_INT);
                $txStmt->bindValue(':provider_response', $metadata, PDO::PARAM_STR);
                $txStmt->execute();
                $txId = (int) $this->pdo->lastInsertId();

                // 4. Cập nhật player_payments
                $upStmt = $this->pdo->prepare(
                    'UPDATE `player_payments`
                     SET `paid_amount` = :paid_amount,
                         `status` = :status,
                         `paid_at` = CASE WHEN :is_paid = 1 THEN NOW() ELSE `paid_at` END,
                         `updated_at` = NOW()
                     WHERE `id` = :id'
                );
                $upStmt->bindValue(':paid_amount', $newPaid, PDO::PARAM_INT);
                $upStmt->bindValue(':status', $newStatus, PDO::PARAM_STR);
                $upStmt->bindValue(':is_paid', $newStatus === 'PAID' ? 1 : 0, PDO::PARAM_INT);
                $upStmt->bindValue(':id', $paymentId, PDO::PARAM_INT);
                $upStmt->execute();

                // 5. Cập nhật participant status sang DEPOSITED nếu là cọc thành công
                if ($payment['payment_type'] === 'DEPOSIT' && $newStatus === 'PAID') {
                    $partId = (int) $payment['room_participant_id'];
                    $partUp = $this->pdo->prepare(
                        'UPDATE `room_participants`
                         SET `status` = \'DEPOSITED\', `deposited_at` = NOW(), `updated_at` = NOW()
                         WHERE `id` = :part_id AND `status` = \'PENDING_DEPOSIT\''
                    );
                    $partUp->bindValue(':part_id', $partId, PDO::PARAM_INT);
                    $partUp->execute();
                }

                $this->pdo->commit();

                $updatedPayment = $this->findPaymentWithDetails($paymentId);
                $txRecord = [
                    'id' => $txId,
                    'transaction_reference' => $txRef,
                    'amount' => $actualPay,
                    'status' => 'SUCCESS',
                    'idempotency_key' => $idempotencyKey,
                ];

                return [
                    'success' => true,
                    'payment' => $updatedPayment ?? [],
                    'transaction' => $txRecord,
                ];
            } catch (\PDOException $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'uq_payment_transaction_idempotency')) {
                    $existingTx = $this->findAttemptByIdempotencyKey($idempotencyKey);
                    $payment = $this->findPaymentWithDetails($paymentId);
                    return [
                        'success' => ($existingTx['status'] ?? '') === 'SUCCESS',
                        'payment' => $payment ?? [],
                        'transaction' => $existingTx ?? [],
                    ];
                }
                throw $e;
            } catch (\Throwable $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                throw $e;
            }
        });
    }
}
