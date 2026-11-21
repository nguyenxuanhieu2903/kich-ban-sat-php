<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Exceptions\HttpException;
use App\Features\Admin\AdminService;
use App\Features\Audit\AuditLogService;
use App\Features\Notification\NotificationService;
use PDO;
use PHPUnit\Framework\TestCase;

class AdminNotificationAuditTest extends TestCase
{
    private PDO $pdo;
    private AdminService $admin;
    private AuditLogService $audit;
    private NotificationService $notifications;

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
                phone TEXT NULL,
                role TEXT NOT NULL,
                student_verified INTEGER NOT NULL DEFAULT 0,
                is_active INTEGER NOT NULL DEFAULT 1
            );
            CREATE TABLE physical_rooms (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                capacity INTEGER NOT NULL,
                theme TEXT NULL,
                status TEXT NOT NULL DEFAULT "AVAILABLE"
            );
            CREATE TABLE notifications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                event_type TEXT NOT NULL,
                title TEXT NOT NULL,
                body TEXT NOT NULL,
                payload TEXT NULL,
                is_read INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE audit_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                actor_user_id INTEGER NULL,
                action TEXT NOT NULL,
                target_type TEXT NOT NULL,
                target_id INTEGER NOT NULL,
                reason TEXT NULL,
                metadata TEXT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );'
        );

        $this->pdo->exec(
            "INSERT INTO users (id, full_name, email, role, is_active) VALUES
                (1, 'Player One', 'p1@kbs.local', 'PLAYER', 1),
                (2, 'Admin Root', 'admin@kbs.local', 'ADMIN', 1);"
        );

        $this->admin = new AdminService($this->pdo);
        $this->audit = new AuditLogService($this->pdo);
        $this->notifications = new NotificationService($this->pdo);
    }

    public function testCreateAndUpdatePhysicalRoomRecordsAuditLog(): void
    {
        $room = $this->admin->createPhysicalRoom(2, 'ADMIN', [
            'name' => 'Phòng Trinh Thám 01',
            'capacity' => 8,
            'theme' => 'Cổ điển',
        ]);

        $this->assertSame('Phòng Trinh Thám 01', $room['name']);
        $this->assertSame(8, $room['capacity']);
        $this->assertSame('AVAILABLE', $room['status']);

        $updated = $this->admin->updatePhysicalRoom($room['id'], 2, 'ADMIN', [
            'status' => 'MAINTENANCE',
            'reason' => 'Sửa máy lạnh',
        ]);
        $this->assertSame('MAINTENANCE', $updated['status']);

        $logs = $this->audit->listLogs(2, 'ADMIN', ['target_type' => 'PHYSICAL_ROOM', 'target_id' => $room['id']]);
        $this->assertCount(2, $logs);
        $this->assertSame('PHYSICAL_ROOM_UPDATED', $logs[0]['action']);
        $this->assertSame('PHYSICAL_ROOM_CREATED', $logs[1]['action']);
    }

    public function testDuplicatePhysicalRoomNameThrowsConflict(): void
    {
        $this->admin->createPhysicalRoom(2, 'ADMIN', ['name' => 'Phòng VIP', 'capacity' => 8]);

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(409);

        $this->admin->createPhysicalRoom(2, 'ADMIN', ['name' => 'Phòng VIP', 'capacity' => 6]);
    }

    public function testUpdateUserStatusAndRoleCreatesAuditAndNotifications(): void
    {
        $locked = $this->admin->updateUserStatus(1, 2, 'ADMIN', false, 'Vi phạm nội quy');
        $this->assertFalse($locked['is_active']);

        $promoted = $this->admin->updateUserRole(1, 2, 'ADMIN', 'DM', 'Đạt chuẩn Quản trò');
        $this->assertSame('DM', $promoted['role']);

        $userNotifs = $this->notifications->listForUser(1, true);
        $this->assertCount(2, $userNotifs);
        $this->assertSame('USER_ROLE_CHANGED', $userNotifs[0]['event_type']);

        $marked = $this->notifications->markAsRead($userNotifs[0]['id'], 1);
        $this->assertTrue($marked['is_read']);

        $countAll = $this->notifications->markAllAsRead(1);
        $this->assertSame(1, $countAll);
        $this->assertCount(0, $this->notifications->listForUser(1, true));
    }

    public function testAdminCannotDeactivateSelf(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(409);

        $this->admin->updateUserStatus(2, 2, 'ADMIN', false);
    }

    public function testUserCannotMarkAnotherUsersNotificationAsRead(): void
    {
        $notif = $this->notifications->create(1, 'SYSTEM', 'Chào mừng', 'Chào mừng bạn đến với Kịch Bản Sát');

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(403);

        $this->notifications->markAsRead($notif['id'], 2);
    }

    public function testNonAdminCannotViewAuditLogs(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(403);

        $this->audit->listLogs(1, 'PLAYER');
    }
}