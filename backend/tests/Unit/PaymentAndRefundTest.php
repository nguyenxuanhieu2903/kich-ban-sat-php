<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Exceptions\HttpException;
use App\Features\Payment\PaymentService;
use PDO;
use PHPUnit\Framework\TestCase;

class PaymentAndRefundTest extends TestCase
{
    private PDO $pdo;
    private PaymentService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $this->pdo->exec(
            'CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                full_name TEXT NOT NULL,
                email TEXT NOT NULL,
                role TEXT NOT NULL,
                is_active INTEGER NOT NULL DEFAULT 1
            );
            CREATE TABLE game_rooms (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                room_code TEXT NOT NULL,
                game_id INTEGER NOT NULL,
                host_user_id INTEGER NOT NULL,
                scheduled_start_at TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT "CONFIRMED",
                formation_deadline_at TEXT NOT NULL,
                reopen_count INTEGER NOT NULL DEFAULT 0,
                snapshot_game_rule TEXT NOT NULL,
                cancel_reason TEXT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE room_participants (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                room_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                status TEXT NOT NULL DEFAULT "JOINED",
                deposit_deadline_at TEXT NULL,
                joined_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                left_at TEXT NULL
            );
            CREATE TABLE payment_transactions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                room_id INTEGER NOT NULL,
                participant_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                idempotency_key TEXT NULL UNIQUE,
                payment_type TEXT NOT NULL,
                amount_vnd INTEGER NOT NULL,
                status TEXT NOT NULL DEFAULT "DEPOSIT_PENDING",
                confirmed_by INTEGER NULL,
                note TEXT NULL,
                paid_at TEXT NULL
            );
            CREATE TABLE refunds (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                payment_id INTEGER NOT NULL,
                participant_id INTEGER NOT NULL,
                amount_vnd INTEGER NOT NULL,
                status TEXT NOT NULL DEFAULT "REQUESTED",
                reference_code TEXT NULL,
                reason TEXT NOT NULL,
                processed_by INTEGER NULL
            );'
        );

        $this->pdo->exec(
            "INSERT INTO users (id, full_name, email, role, is_active) VALUES
                (1, 'Player 1', 'p1@kbs.local', 'PLAYER', 1),
                (2, 'Admin', 'admin@kbs.local', 'ADMIN', 1);"
        );

        $snap = json_encode([
            'game_id' => 1,
            'title' => 'Án Mạng Đêm Mưa',
            'ticket_price_vnd' => 200000,
            'deposit_vnd' => 60000,
        ]);

        $stmt = $this->pdo->prepare(
            "INSERT INTO game_rooms (id, room_code, game_id, host_user_id, scheduled_start_at, status, formation_deadline_at, snapshot_game_rule)
             VALUES (1, 'KBS-PAY', 1, 1, '2026-12-25 19:00:00', 'CONFIRMED', '2026-12-25 15:00:00', :snap)"
        );
        $stmt->execute([':snap' => $snap]);

        $this->pdo->exec(
            "INSERT INTO room_participants (id, room_id, user_id, status, deposit_deadline_at)
             VALUES (1, 1, 1, 'JOINED', '2026-12-25 18:00:00');"
        );

        $this->service = new PaymentService($this->pdo);
    }

    public function testDepositIdempotencyConfirmAndRemaining70PercentFlow(): void
    {
        $tx1 = $this->service->payDeposit(1, 1, 'VNPAY', 'idem-dep-001');
        $this->assertSame('DEPOSIT_PENDING', $tx1['status']);
        $this->assertSame(60000, $tx1['amount_vnd']);
        $this->assertFalse($tx1['idempotent_replay']);

        // Gửi trùng key phải trả lại đúng giao dịch cũ
        $txReplay = $this->service->payDeposit(1, 1, 'VNPAY', 'idem-dep-001');
        $this->assertSame($tx1['id'], $txReplay['id']);
        $this->assertTrue($txReplay['idempotent_replay']);

        // Admin xác nhận cọc -> participant lên DEPOSITED
        $confirmedDep = $this->service->confirmPayment($tx1['id'], 2, 'ADMIN', 'Đã nhận chuyển khoản');
        $this->assertSame('SUCCESS', $confirmedDep['status']);
        $this->assertSame('DEPOSITED', $this->pdo->query('SELECT status FROM room_participants WHERE id = 1')->fetchColumn());

        // Thanh toán phần còn lại (200.000 - 60.000 = 140.000)
        $remTx = $this->service->payRemaining(1, 1, 'MOMO', 'idem-rem-001');
        $this->assertSame('REMAINING_PENDING', $remTx['status']);
        $this->assertSame(140000, $remTx['amount_vnd']);

        // Admin xác nhận 70% -> participant lên PAID_FULL
        $confirmedRem = $this->service->confirmPayment($remTx['id'], 2, 'ADMIN');
        $this->assertSame('SUCCESS', $confirmedRem['status']);
        $this->assertSame('PAID_FULL', $this->pdo->query('SELECT status FROM room_participants WHERE id = 1')->fetchColumn());
    }

    public function testFailAndRetryPayment(): void
    {
        $tx = $this->service->payDeposit(1, 1, 'VNPAY', 'idem-fail-001');
        $failed = $this->service->failPayment($tx['id'], 2, 'ADMIN', 'Cổng thanh toán timeout');
        $this->assertSame('FAILED', $failed['status']);

        $retried = $this->service->retryPayment($tx['id'], 1, 'PLAYER', 'idem-retry-001');
        $this->assertNotSame($tx['id'], $retried['id']);
        $this->assertSame('DEPOSIT_PENDING', $retried['status']);
        $this->assertSame(60000, $retried['amount_vnd']);
    }

    public function testRequestAndProcessRefundPreventsDoubleRefund(): void
    {
        $tx = $this->service->payDeposit(1, 1, 'VNPAY', 'idem-ref-001');
        $this->service->confirmPayment($tx['id'], 2, 'ADMIN');

        $refund = $this->service->requestRefund($tx['id'], 1, 'PLAYER', 'Phòng hủy bởi quán');
        $this->assertSame('REQUESTED', $refund['status']);
        $this->assertSame(60000, $refund['amount_vnd']);

        $processed = $this->service->processRefund($refund['id'], 2, 'ADMIN', 'PROCESSED', 'VNP-REF-999');
        $this->assertSame('PROCESSED', $processed['status']);
        $this->assertSame('VNP-REF-999', $processed['reference_code']);
        $this->assertSame('REFUNDED', $this->pdo->query("SELECT status FROM payment_transactions WHERE id = {$tx['id']}")->fetchColumn());

        // Không được tạo thêm yêu cầu hoàn tiền lần 2 cho cùng giao dịch
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(409);
        $this->service->requestRefund($tx['id'], 1, 'PLAYER', 'Đòi hoàn lần 2');
    }

    public function testCannotPayRemainingBeforeDeposit(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(409);

        $this->service->payRemaining(1, 1, 'VNPAY');
    }
}