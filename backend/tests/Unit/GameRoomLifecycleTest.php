<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Exceptions\HttpException;
use App\Core\Exceptions\NotFoundException;
use App\Features\GameRoom\GameRoomService;
use PDO;
use PHPUnit\Framework\TestCase;

class GameRoomLifecycleTest extends TestCase
{
    private PDO $pdo;
    private GameRoomService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $this->createSchemaAndSeed();
        $this->service = new GameRoomService($this->pdo);
    }

    private function createSchemaAndSeed(): void
    {
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
                min_players INTEGER NOT NULL DEFAULT 5,
                max_players INTEGER NOT NULL DEFAULT 6,
                duration_minutes INTEGER NOT NULL DEFAULT 210,
                price_day_vnd INTEGER NOT NULL DEFAULT 150000,
                price_evening_vnd INTEGER NOT NULL DEFAULT 180000,
                is_active INTEGER NOT NULL DEFAULT 1
            );
            CREATE TABLE physical_rooms (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
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
                (1, 'Host Player', 'host@kbs.local', 'PLAYER', 1),
                (2, 'Other Player', 'other@kbs.local', 'PLAYER', 1),
                (3, 'Game Master', 'dm@kbs.local', 'DM', 1),
                (4, 'Admin User', 'admin@kbs.local', 'ADMIN', 1);
             INSERT INTO games (id, title) VALUES (1, 'Cô Gái Bên Cửa Sổ');
             INSERT INTO physical_rooms (id, name, status) VALUES (1, 'Phòng VIP', 'AVAILABLE');"
        );

        $snapshot = json_encode([
            'game_id' => 1,
            'title' => 'Cô Gái Bên Cửa Sổ',
            'min_players' => 5,
            'max_players' => 6,
            'duration_minutes' => 210,
        ]);

        $stmt = $this->pdo->prepare(
            "INSERT INTO game_rooms (id, room_code, game_id, host_user_id, scheduled_start_at, status, formation_deadline_at, snapshot_game_rule)
             VALUES
                (1, 'KBS-001', 1, 1, '2026-12-10 19:00:00', 'FORMING', '2026-12-10 18:00:00', :snap),
                (2, 'KBS-002', 1, 1, '2026-12-11 19:00:00', 'CONFIRMED', '2026-12-11 18:00:00', :snap),
                (3, 'KBS-003', 1, 1, '2026-12-01 19:00:00', 'COMPLETED', '2026-12-01 18:00:00', :snap)"
        );
        $stmt->execute([':snap' => $snapshot]);

        $this->pdo->exec(
            "INSERT INTO room_participants (room_id, user_id, status) VALUES (1, 1, 'JOINED'), (2, 1, 'JOINED');
             INSERT INTO game_sessions (id, room_id, dm_user_id, physical_room_id, start_at, end_at, status)
             VALUES (1, 2, 3, 1, '2026-12-11 19:00:00', '2026-12-11 22:30:00', 'SCHEDULED');"
        );
    }

    public function testHostCanCancelFormingRoom(): void
    {
        $result = $this->service->cancelRoom(1, 1, 'PLAYER', 'Bận việc đột xuất');

        $this->assertSame('CANCELLED', $result['status']);
        $this->assertSame('Bận việc đột xuất', $result['cancel_reason']);
    }

    public function testNonHostPlayerCannotCancelRoom(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(403);

        $this->service->cancelRoom(1, 2, 'PLAYER', 'Cố tình hủy');
    }

    public function testHostCannotCancelConfirmedRoom(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(409);

        $this->service->cancelRoom(2, 1, 'PLAYER', 'Chủ phòng tự hủy phòng đã chốt');
    }

    public function testAdminCanCancelConfirmedRoomAndCancelsScheduledSession(): void
    {
        $result = $this->service->cancelRoom(2, 4, 'ADMIN', 'Bảo trì đột xuất');

        $this->assertSame('CANCELLED', $result['status']);
        $this->assertSame('Bảo trì đột xuất', $result['cancel_reason']);

        $session = $this->pdo->query('SELECT status, cancel_reason FROM game_sessions WHERE id = 1')->fetch();
        $this->assertSame('CANCELLED', $session['status']);
        $this->assertSame('Bảo trì đột xuất', $session['cancel_reason']);
    }

    public function testCannotCancelAlreadyCompletedRoom(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(409);

        $this->service->cancelRoom(3, 4, 'ADMIN', 'Hủy phòng đã xong');
    }

    public function testCancelRoomNotFoundThrowsNotFoundException(): void
    {
        $this->expectException(NotFoundException::class);

        $this->service->cancelRoom(999, 4, 'ADMIN');
    }

    public function testAssignedDmCanCompleteConfirmedRoom(): void
    {
        $result = $this->service->completeRoom(2, 3, 'DM');

        $this->assertSame('COMPLETED', $result['status']);

        $sessionStatus = $this->pdo->query('SELECT status FROM game_sessions WHERE id = 1')->fetchColumn();
        $this->assertSame('COMPLETED', $sessionStatus);
    }

    public function testAdminCanCompleteConfirmedRoom(): void
    {
        $result = $this->service->completeRoom(2, 4, 'ADMIN');

        $this->assertSame('COMPLETED', $result['status']);

        $sessionStatus = $this->pdo->query('SELECT status FROM game_sessions WHERE id = 1')->fetchColumn();
        $this->assertSame('COMPLETED', $sessionStatus);
    }

    public function testUnassignedUserCannotCompleteRoom(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(403);

        $this->service->completeRoom(2, 2, 'PLAYER');
    }

    public function testCannotCompleteRoomWhenNotConfirmed(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(409);

        $this->service->completeRoom(1, 4, 'ADMIN');
    }
}