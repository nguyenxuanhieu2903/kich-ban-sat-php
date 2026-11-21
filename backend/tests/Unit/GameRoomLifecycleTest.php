<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Exceptions\HttpException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Request;
use App\Features\Game\GameController;
use App\Features\Game\GameRepository;
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

    protected function tearDown(): void
    {
        $_SESSION = [];
        parent::tearDown();
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
                slug TEXT NULL,
                category TEXT NOT NULL DEFAULT "Trinh thám",
                description TEXT NULL,
                min_players INTEGER NOT NULL DEFAULT 5,
                max_players INTEGER NOT NULL DEFAULT 6,
                duration_minutes INTEGER NOT NULL DEFAULT 210,
                is_18_plus INTEGER NOT NULL DEFAULT 0,
                price_day_vnd INTEGER NOT NULL DEFAULT 150000,
                price_evening_vnd INTEGER NOT NULL DEFAULT 180000,
                deposit_vnd INTEGER NOT NULL DEFAULT 50000,
                is_active INTEGER NOT NULL DEFAULT 1,
                menu_verified INTEGER NOT NULL DEFAULT 0
            );
            CREATE TABLE physical_rooms (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                capacity INTEGER NOT NULL DEFAULT 8,
                status TEXT NOT NULL DEFAULT "AVAILABLE"
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
                (4, 'Admin User', 'admin@kbs.local', 'ADMIN', 1),
                (5, 'Player 3', 'p3@kbs.local', 'PLAYER', 1),
                (6, 'Player 4', 'p4@kbs.local', 'PLAYER', 1),
                (7, 'Player 5', 'p5@kbs.local', 'PLAYER', 1),
                (8, 'Locked Player', 'locked@kbs.local', 'PLAYER', 0);
             INSERT INTO games (id, title, slug) VALUES (1, 'Cô Gái Bên Cửa Sổ', 'co-gai-ben-cua-so');
             INSERT INTO physical_rooms (id, name, capacity, status) VALUES
                (1, 'Phòng VIP', 8, 'AVAILABLE'),
                (2, 'Phòng Nhỏ', 5, 'AVAILABLE');"
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
                (3, 'KBS-003', 1, 1, '2026-12-01 19:00:00', 'COMPLETED', '2026-12-01 18:00:00', :snap),
                (4, 'KBS-004', 1, 1, '2026-12-12 19:00:00', 'WAITING_FOR_DM', '2026-12-12 18:00:00', :snap)"
        );
        $stmt->execute([':snap' => $snapshot]);

        $this->pdo->exec(
            "INSERT INTO room_participants (room_id, user_id, status) VALUES
                (1, 1, 'JOINED'),
                (2, 1, 'JOINED'),
                (4, 1, 'JOINED'),
                (4, 2, 'JOINED'),
                (4, 5, 'JOINED'),
                (4, 6, 'JOINED'),
                (4, 7, 'JOINED');
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

    // --- Chặng 1: Vá lỗi lõi & Siết RBAC ---

    public function testAssignDmRecordsAssignedByAndConfirmsRoom(): void
    {
        $result = $this->service->assignDmAndPhysicalRoom(4, 3, 1, 4);

        $this->assertSame('CONFIRMED', $result['status']);

        $assignment = $this->pdo->query('SELECT room_id, dm_user_id, assigned_by, status FROM dm_assignments WHERE room_id = 4')->fetch();
        $this->assertSame(4, (int) $assignment['room_id']);
        $this->assertSame(3, (int) $assignment['dm_user_id']);
        $this->assertSame(4, (int) $assignment['assigned_by']);
        $this->assertSame('ACCEPTED', $assignment['status']);
    }

    public function testAssignDmFailsWhenBelowMinPlayers(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(409);

        // Phòng 1 mới có 1/5 người chơi
        $this->service->assignDmAndPhysicalRoom(1, 3, 1, 4);
    }

    public function testAssignDmFailsWhenPhysicalRoomCapacityTooSmall(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(409);

        // Phòng 4 có 5 người chơi + 1 DM = 6 chỗ, trong khi Phòng Nhỏ (id=2) chỉ có capacity = 5
        $this->service->assignDmAndPhysicalRoom(4, 3, 2, 4);
    }

    public function testPlayerCannotLeaveConfirmedRoom(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(409);

        $this->service->leaveRoom(2, 1);
    }

    public function testInactiveUserCannotJoinRoom(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(403);

        $this->service->joinRoom(1, 8);
    }

    public function testDmRoleCannotJoinRoomAsPlayer(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(403);

        $this->service->joinRoom(1, 3);
    }

    public function testCreateGameRequiresAdminAuthentication(): void
    {
        $_SESSION = [];
        $controller = new GameController(new GameRepository($this->pdo));
        $request = new Request('POST', '/api/games', [], ['title' => 'Test']);

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(401);

        $controller->store($request);
    }

    public function testCreateGameRejectsNonAdminRole(): void
    {
        $_SESSION = ['user_id' => 1, 'role' => 'PLAYER'];
        $controller = new GameController(new GameRepository($this->pdo));
        $request = new Request('POST', '/api/games', [], ['title' => 'Test']);

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(403);

        $controller->store($request);
    }
}