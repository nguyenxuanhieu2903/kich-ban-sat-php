<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreatePaymentTables extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<SQL
        SET FOREIGN_KEY_CHECKS = 0;

        -- 18. player_payments: Player financial liabilities & payment tracking
        CREATE TABLE IF NOT EXISTS `player_payments` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `room_participant_id` BIGINT UNSIGNED NOT NULL,
            `session_id` BIGINT UNSIGNED NOT NULL,
            `payment_type` VARCHAR(30) NOT NULL,
            `amount_due` BIGINT UNSIGNED NOT NULL,
            `amount_paid` BIGINT UNSIGNED NOT NULL DEFAULT 0,
            `status` VARCHAR(30) NOT NULL DEFAULT 'PENDING',
            `deadline` TIMESTAMP NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `idx_player_payments_participant` (`room_participant_id`, `status`),
            INDEX `idx_player_payments_session` (`session_id`, `payment_type`),
            CONSTRAINT `fk_player_payments_participant` FOREIGN KEY (`room_participant_id`) REFERENCES `room_participants` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT `fk_player_payments_session` FOREIGN KEY (`session_id`) REFERENCES `sessions` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT `chk_player_payments_amounts` CHECK (`amount_due` >= 0 AND `amount_paid` >= 0)
        ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

        -- 19. payment_transactions: Accounting ledger entries for confirmed payments
        CREATE TABLE IF NOT EXISTS `payment_transactions` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `player_payment_id` BIGINT UNSIGNED NOT NULL,
            `transaction_reference` VARCHAR(64) NOT NULL,
            `idempotency_key` VARCHAR(64) NOT NULL,
            `payment_method` VARCHAR(30) NOT NULL DEFAULT 'SIMULATED',
            `amount` BIGINT UNSIGNED NOT NULL,
            `currency` VARCHAR(10) NOT NULL DEFAULT 'VND',
            `status` VARCHAR(30) NOT NULL DEFAULT 'SUCCESS',
            `transacted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `metadata` JSON NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_payment_transactions_reference` (`transaction_reference`),
            UNIQUE KEY `uq_payment_transactions_idempotency` (`idempotency_key`),
            INDEX `idx_payment_transactions_payment` (`player_payment_id`),
            CONSTRAINT `fk_payment_transactions_payment` FOREIGN KEY (`player_payment_id`) REFERENCES `player_payments` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT `chk_payment_transactions_amount` CHECK (`amount` > 0)
        ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

        -- 20. refunds: Refund requests and ledger
        CREATE TABLE IF NOT EXISTS `refunds` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `player_payment_id` BIGINT UNSIGNED NOT NULL,
            `room_participant_id` BIGINT UNSIGNED NOT NULL,
            `processed_by_user_id` BIGINT UNSIGNED NULL,
            `idempotency_key` VARCHAR(64) NOT NULL,
            `reason` TEXT NOT NULL,
            `refund_type` VARCHAR(30) NOT NULL DEFAULT 'FULL',
            `refund_amount` BIGINT UNSIGNED NOT NULL,
            `status` VARCHAR(30) NOT NULL DEFAULT 'PENDING',
            `processed_at` TIMESTAMP NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_refunds_idempotency` (`idempotency_key`),
            INDEX `idx_refunds_payment_status` (`player_payment_id`, `status`),
            INDEX `idx_refunds_participant` (`room_participant_id`),
            INDEX `idx_refunds_processor` (`processed_by_user_id`),
            CONSTRAINT `fk_refunds_payment` FOREIGN KEY (`player_payment_id`) REFERENCES `player_payments` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT `fk_refunds_participant` FOREIGN KEY (`room_participant_id`) REFERENCES `room_participants` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT `fk_refunds_processor` FOREIGN KEY (`processed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
            CONSTRAINT `chk_refunds_amount` CHECK (`refund_amount` > 0)
        ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

        SET FOREIGN_KEY_CHECKS = 1;
        SQL);
    }

    public function down(): void
    {
        $this->execute(<<<SQL
        SET FOREIGN_KEY_CHECKS = 0;
        DROP TABLE IF EXISTS `refunds`;
        DROP TABLE IF EXISTS `payment_transactions`;
        DROP TABLE IF EXISTS `player_payments`;
        SET FOREIGN_KEY_CHECKS = 1;
        SQL);
    }
}
