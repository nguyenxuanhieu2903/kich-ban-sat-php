<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Exceptions\HttpException;
use App\Features\GameRoom\GameRoomService;
use App\Features\GameRoom\RoomWorkflowService;
use PDO;
use PHPUnit\Framework\TestCase;

class RoomWorkflowTest extends TestCase
{
    private PDO $pdo;
    private GameRoomService $roomService;
    private RoomWorkflowService $workflow;

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
            CREATE TABLE games (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                min_players INTEGER NOT NULL DEFAULT 2,
                max_players INTEGER NOT NULL DEFAULT 6,
                duration_minutes INTEGER NOT NULL DEFAULT 180,
                price_day_vnd INTEGER NOT NULL DEFAULT 150000,
                price_evening_vnd INTEGER NOT NULL DEFAULT 180000,
                is_active INTEGER NOT NULL DEFAULT 1
            );
            CREATE TABLE physical_rooms (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                capacity INTEGER NOT NULL DEFAULT 8,
                status TEXT NOT NULL DEFAULT "AVAILABLE"
            );
            CREATE TABLE game_rooms (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                room_code TEXT NOT NULL,
                game_id INTEGER NOT NULL,
                host_user_id INTEGER NOT NULL,
                scheduled_start_at TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT "FORMING",
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
            CREATE TABLE dm_assignments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                room_id INTEGER NOT NULL,
                dm_user_id INTEGER NOT NULL,
                assigned_by INTEGER NOT NULL,
                status TEXT NOT NULL DEFAULT "PENDING",
                reason TEXT NULL,
                responded_at TEXT NULL
            );
            CREATE TABLE schedule_proposals (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                room_id INTEGER NOT NULL,
                proposed_by INTEGER NOT NULL,
                old_start_at TEXT NOT NULL,
                new_start_at TEXT NOT NULL,
                expires_at TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT "PROPOSED"
            );
            CREATE TABLE game_sessions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                room_id INTEGER NOT NULL,
                dm_user_id INTEGER NOT NULL,
                physical_room_id INTEGER NOT NULL,
                start_at TEXT NOT NULL,
                end_at TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT "SCHEDULED",
                cancel_reason TEXT NULL
            );'
        );

        $this->pdo->exec(
            "INSERT INTO users (id, full_name, email, role, is_active) VALUES
                (1, 'Host', 'host@kbs.local', 'PLAYER', 1),
                (2, 'Player 2', 'p2@kbs.local', 'PLAYER', 1),
                (3, 'DM One', 'dm@kbs.local', 'DM', 1),
                (4, 'Admin', 'admin@kbs.local', 'ADMIN', 1);
             INSERT INTO games (id, title) VALUES (1, 'Mật Vụ Đêm');
             INSERT INTO physical_rooms (id, name, capacity, status) VALUES (1, 'Phòng A', 8, 'AVAILABLE');"
        );

        $snap = json_encode([
            'game_id' => 1,
            'title' => 'Mật Vụ Đêm',
            'min_players' => 2,
            'max_players' => 6,
            'duration_minutes' => 180,
        ]);

        $stmt = $this->pdo->prepare(
            "INSERT INTO game_rooms (id, room_code, game_id, host_user_id, scheduled_start_at, status, formation_deadline_at, reopen_count, snapshot_game_rule)
             VALUES
                (1, 'KBS-E1', 1, 1, '2026-12-20 19:00:00', 'FORMING', '2026-11-01 10:00:00', 0, :snap),
                (2, 'KBS-W1', 1, 1, '2026-12-21 19:00:00', 'WAITING_FOR_DM', '2026-12-21 15:00:00', 0, :snap)"
        );
        $stmt->execute([':snap' => $snap]);

        $this->pdo->exec(
            "INSERT INTO room_participants (room_id, user_id, status) VALUES
                (1, 1, 'JOINED'),
                (2, 1, 'JOINED'),
                (2, 2, 'JOINED');"
        );

        $this->roomService = new GameRoomService($this->pdo);
        $this->workflow = new RoomWorkflowService($this->pdo);
    }

    public function testExpireAndReopenAndReplenishToWaitingForDm(): void
    {
        $expired = $this->workflow->expireRoom(1, 4, 'ADMIN');
        $this->assertSame('EXPIRED', $expired['status']);

        $reopened = $this->workflow->reopenRoom(1, 1, 'PLAYER');
        $this->assertSame('REPLENISHING', $reopened['status']);
        $this->assertSame(1, $reopened['reopen_count']);

        $joined = $this->roomService->joinRoom(1, 2);
        $this->assertSame('WAITING_FOR_DM', $joined['status']);
    }

    public function testDmCanRejectThenAcceptNewProposal(): void
    {
        $first = $this->workflow->proposeDmAssignment(2, 3, 4);
        $this->assertSame('PENDING', $first['status']);

        $rejected = $this->workflow->respondDmAssignment($first['id'], 3, 'REJECTED', null, 'Bận lịch đột xuất');
        $this->assertSame('REJECTED', $rejected['status']);

        $second = $this->workflow->proposeDmAssignment(2, 3, 4);
        $accepted = $this->workflow->respondDmAssignment($second['id'], 3, 'ACCEPTED', 1);

        $this->assertSame('ACCEPTED', $accepted['status']);
        $this->assertSame('CONFIRMED', $accepted['room']['status']);

        $sessionCount = (int) $this->pdo->query('SELECT COUNT(*) FROM game_sessions WHERE room_id = 2')->fetchColumn();
        $this->assertSame(1, $sessionCount);
    }

    public function testScheduleProposalApprovalUpdatesRoomAndSession(): void
    {
        $propDm = $this->workflow->proposeDmAssignment(2, 3, 4);
        $this->workflow->respondDmAssignment($propDm['id'], 3, 'ACCEPTED', 1);

        $proposal = $this->workflow->proposeSchedule(2, 1, 'PLAYER', '2026-12-22 20:30:00');
        $this->assertSame('PROPOSED', $proposal['status']);

        $approved = $this->workflow->respondScheduleProposal($proposal['id'], 4, 'ADMIN', 'APPROVED');
        $this->assertSame('APPROVED', $approved['status']);
        $this->assertSame('2026-12-22 20:30:00', $approved['room']['scheduled_start_at']);

        $sessionStart = $this->pdo->query('SELECT start_at FROM game_sessions WHERE room_id = 2')->fetchColumn();
        $this->assertSame('2026-12-22 20:30:00', $sessionStart);
    }

    public function testReopenFailsWhenExceedingMaxReopenCount(): void
    {
        $this->pdo->exec("UPDATE game_rooms SET status = 'EXPIRED', reopen_count = 2 WHERE id = 1");

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(409);

        $this->workflow->reopenRoom(1, 1, 'PLAYER');
    }
}