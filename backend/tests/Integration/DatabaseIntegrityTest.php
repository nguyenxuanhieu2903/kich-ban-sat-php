<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

/**
 * Class DatabaseIntegrityTest
 *
 * Exhaustive database verification test suite for Task 3.4.
 * Tests against MySQL 8.0/8.4 InnoDB schema (booking_project_php_test).
 * Validates ACID properties, referential integrity, check constraints,
 * virtual generated conditional unique constraints, and transaction rollback.
 */
class DatabaseIntegrityTest extends TestCase
{
    private static PDO $pdo;

    public static function setUpBeforeClass(): void
    {
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $port = getenv('DB_PORT') ?: '3306';
        $db = 'booking_project_php_test';
        $user = getenv('DB_USERNAME') ?: 'root';
        $pass = getenv('DB_PASSWORD') ?: 'Hieu29032006@';

        self::$pdo = new PDO("mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        self::cleanAllTables();
    }

    protected function setUp(): void
    {
        parent::setUp();
        self::cleanAllTables();
    }

    private static function cleanAllTables(): void
    {
        self::$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
        $tables = [
            'audit_logs', 'booking_price_snapshots', 'categories', 'dm_assignments',
            'dm_availabilities', 'dm_profiles', 'game_category_assignments',
            'game_configurations', 'game_prices', 'game_room_status_histories',
            'game_rooms', 'game_rules', 'games', 'notification_recipient_states',
            'notifications', 'participant_leave_requests', 'participant_status_histories',
            'payment_attempts', 'payment_transactions', 'physical_room_reservations',
            'physical_rooms', 'player_payments', 'promotions', 'refund_attempts',
            'refunds', 'replacement_windows', 'roles', 'room_participants',
            'schedule_proposals', 'schedule_responses', 'sessions', 'user_profiles',
            'user_roles', 'users'
        ];
        foreach ($tables as $t) {
            self::$pdo->exec("DELETE FROM `{$t}`");
        }
        self::$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
    }

    // =========================================================================
    // SECTION A: VALID INSERT TESTS
    // =========================================================================

    public function testA01ValidUserAndRoleInsert(): void
    {
        // 1. Insert User
        $stmt = self::$pdo->prepare("
            INSERT INTO users (name, email, password_hash, phone, status) 
            VALUES (:name, :email, :hash, :phone, 'ACTIVE')
        ");
        $stmt->execute([
            'name' => 'Alice Nguyen',
            'email' => 'alice@example.com',
            'hash' => password_hash('Secret123!', PASSWORD_BCRYPT),
            'phone' => '0901234567',
        ]);
        $userId = (int) self::$pdo->lastInsertId();
        $this->assertGreaterThan(0, $userId);

        // 2. Insert Role
        $stmt = self::$pdo->prepare("
            INSERT INTO roles (name, label, description) 
            VALUES ('PLAYER', 'Standard Player', 'Default role for booked players')
        ");
        $stmt->execute();
        $roleId = (int) self::$pdo->lastInsertId();
        $this->assertGreaterThan(0, $roleId);

        // 3. Insert User Profile (1:1)
        $stmt = self::$pdo->prepare("
            INSERT INTO user_profiles (user_id, display_name, phone, gender) 
            VALUES (:uid, 'AliceInWonderland', '0901234567', 'FEMALE')
        ");
        $stmt->execute(['uid' => $userId]);
        $this->assertGreaterThan(0, (int) self::$pdo->lastInsertId());

        // 4. Insert User Role Pivot (N:M)
        $stmt = self::$pdo->prepare("
            INSERT INTO user_roles (user_id, role_id) 
            VALUES (:uid, :rid)
        ");
        $stmt->execute(['uid' => $userId, 'rid' => $roleId]);
        $this->assertGreaterThan(0, (int) self::$pdo->lastInsertId());
    }

    public function testA02ValidGameCatalogInsert(): void
    {
        // Category
        $stmt = self::$pdo->prepare("INSERT INTO categories (name, slug, description) VALUES ('Horror Mystery', 'horror-mystery', 'Creepy mystery scripts')");
        $stmt->execute();
        $catId = (int) self::$pdo->lastInsertId();

        // Game
        $stmt = self::$pdo->prepare("
            INSERT INTO games (title, slug, min_players, max_players, duration_minutes, is_active) 
            VALUES ('Nightmare at the Manor', 'nightmare-manor', 6, 8, 240, 1)
        ");
        $stmt->execute();
        $gameId = (int) self::$pdo->lastInsertId();

        // Game Rule
        $stmt = self::$pdo->prepare("INSERT INTO game_rules (game_id, rule_name, rule_content) VALUES (:gid, 'General', 'No looking at others clue sheets.')");
        $stmt->execute(['gid' => $gameId]);

        // Game Config
        $stmt = self::$pdo->prepare("INSERT INTO game_configurations (game_id, config_key, config_value) VALUES (:gid, 'soundtrack_url', 'https://music.example.com/spooky')");
        $stmt->execute(['gid' => $gameId]);

        // Game Category Pivot
        $stmt = self::$pdo->prepare("INSERT INTO game_category_assignments (game_id, category_id) VALUES (:gid, :cid)");
        $stmt->execute(['gid' => $gameId, 'cid' => $catId]);

        $this->assertGreaterThan(0, $gameId);
        $this->assertGreaterThan(0, $catId);
    }

    public function testA03ValidPricingAndPromotionInsert(): void
    {
        self::$pdo->exec("INSERT INTO games (title, slug, min_players, max_players, duration_minutes) VALUES ('Game Price', 'game-price', 4, 6, 120)");
        $gid = (int) self::$pdo->lastInsertId();

        self::$pdo->exec("INSERT INTO game_prices (game_id, price_tier_name, day_type, time_from, time_to, base_price_per_player) VALUES ({$gid}, 'Standard Weekday', 'WEEKDAY', '08:00:00', '17:00:00', 150000.00)");
        $priceId = (int) self::$pdo->lastInsertId();
        $this->assertGreaterThan(0, $priceId);

        self::$pdo->exec("INSERT INTO promotions (code, name, discount_type, discount_value, valid_from, valid_to, is_active) VALUES ('SUMMER2026', 'Summer Promo', 'PERCENTAGE', 10.00, '2026-06-01', '2026-08-31', 1)");
        $promoId = (int) self::$pdo->lastInsertId();
        $this->assertGreaterThan(0, $promoId);
    }

    // =========================================================================
    // SECTION B: INVALID INSERT TESTS (CONSTRAINTS ENFORCEMENT)
    // =========================================================================

    public function testB01NotNullConstraintViolation(): void
    {
        $this->expectException(PDOException::class);
        // Column 'email' cannot be null (HY000 / 1048)
        self::$pdo->exec("INSERT INTO users (name, email, password_hash) VALUES ('Bob', NULL, 'hash123')");
    }

    public function testB02ForeignKeyViolationOnInsert(): void
    {
        $this->expectException(PDOException::class);
        // user_roles referencing non-existent user_id=999999 (23000 / 1452)
        self::$pdo->exec("INSERT INTO user_roles (user_id, role_id) VALUES (999999, 1)");
    }

    public function testB03CheckConstraintViolationOnGamesPlayerCount(): void
    {
        $this->expectException(PDOException::class);
        // chk_games_players: min_players > 0 AND max_players >= min_players (Violated: 8 > 4)
        self::$pdo->exec("
            INSERT INTO games (title, slug, min_players, max_players, duration_minutes) 
            VALUES ('Bad Game', 'bad-game', 8, 4, 180)
        ");
    }

    public function testB04CheckConstraintViolationOnGamePricesTimes(): void
    {
        // Create prerequisite game
        self::$pdo->exec("INSERT INTO games (title, slug, min_players, max_players, duration_minutes) VALUES ('Valid Game', 'valid-game', 4, 6, 120)");
        $gameId = (int) self::$pdo->lastInsertId();

        $this->expectException(PDOException::class);
        // chk_game_prices_times: time_from < time_to (Violated: 18:00:00 < 14:00:00 is false)
        self::$pdo->exec("
            INSERT INTO game_prices (game_id, price_tier_name, day_type, time_from, time_to, base_price_per_player) 
            VALUES ({$gameId}, 'Invalid Tier', 'WEEKEND', '18:00:00', '14:00:00', 250000.00)
        ");
    }

    public function testB05CheckConstraintViolationOnDmAvailableDay(): void
    {
        // Prerequisite user and DM profile
        self::$pdo->exec("INSERT INTO users (name, email, password_hash) VALUES ('DM Dan', 'dan@example.com', 'hash')");
        $uid = (int) self::$pdo->lastInsertId();
        self::$pdo->exec("INSERT INTO dm_profiles (user_id, experience_years, status) VALUES ({$uid}, 3, 'ACTIVE')");
        $dmId = (int) self::$pdo->lastInsertId();

        $this->expectException(PDOException::class);
        // chk_dm_avail_day: day_of_week BETWEEN 0 AND 6 (Violated: day_of_week = 7)
        self::$pdo->exec("
            INSERT INTO dm_availabilities (dm_profile_id, day_of_week, start_time, end_time) 
            VALUES ({$dmId}, 7, '09:00:00', '12:00:00')
        ");
    }

    public function testB06UniqueConstraintViolationSingleColumn(): void
    {
        self::$pdo->exec("INSERT INTO users (name, email, password_hash) VALUES ('User 1', 'unique@example.com', 'h1')");

        $this->expectException(PDOException::class);
        // Duplicate entry 'unique@example.com' for key 'users.uq_users_email' (23000 / 1062)
        self::$pdo->exec("INSERT INTO users (name, email, password_hash) VALUES ('User 2', 'unique@example.com', 'h2')");
    }

    public function testB07UniqueConstraintViolationComposite(): void
    {
        self::$pdo->exec("INSERT INTO games (title, slug, min_players, max_players, duration_minutes) VALUES ('Game A', 'game-a', 4, 6, 120)");
        $gid = (int) self::$pdo->lastInsertId();
        self::$pdo->exec("INSERT INTO categories (name, slug) VALUES ('Cat A', 'cat-a')");
        $cid = (int) self::$pdo->lastInsertId();

        self::$pdo->exec("INSERT INTO game_category_assignments (game_id, category_id) VALUES ({$gid}, {$cid})");

        $this->expectException(PDOException::class);
        // Duplicate entry for composite key uq_game_category_assignments (game_id, category_id)
        self::$pdo->exec("INSERT INTO game_category_assignments (game_id, category_id) VALUES ({$gid}, {$cid})");
    }

    // =========================================================================
    // SECTION C: INVALID UPDATE TESTS
    // =========================================================================

    public function testC01CheckConstraintViolationOnUpdate(): void
    {
        self::$pdo->exec("INSERT INTO games (title, slug, min_players, max_players, duration_minutes) VALUES ('Game B', 'game-b', 4, 8, 120)");
        $gid = (int) self::$pdo->lastInsertId();

        $this->expectException(PDOException::class);
        // UPDATE makes min_players (10) > max_players (8)
        self::$pdo->exec("UPDATE games SET min_players = 10 WHERE id = {$gid}");
    }

    public function testC02UniqueConstraintViolationOnUpdate(): void
    {
        self::$pdo->exec("INSERT INTO users (name, email, password_hash) VALUES ('User 1', 'one@example.com', 'h1')");
        self::$pdo->exec("INSERT INTO users (name, email, password_hash) VALUES ('User 2', 'two@example.com', 'h2')");
        $u2Id = (int) self::$pdo->lastInsertId();

        $this->expectException(PDOException::class);
        // UPDATE user 2 email to user 1 email
        self::$pdo->exec("UPDATE users SET email = 'one@example.com' WHERE id = {$u2Id}");
    }

    public function testC03ForeignKeyViolationOnUpdate(): void
    {
        self::$pdo->exec("INSERT INTO users (name, email, password_hash) VALUES ('User 1', 'one@example.com', 'h1')");
        $uid = (int) self::$pdo->lastInsertId();
        self::$pdo->exec("INSERT INTO games (title, slug, min_players, max_players, duration_minutes) VALUES ('Game C', 'game-c', 4, 6, 120)");
        $gid = (int) self::$pdo->lastInsertId();

        self::$pdo->exec("INSERT INTO game_rooms (creator_id, game_id, title, room_code, min_players, max_players, formation_deadline, status) VALUES ({$uid}, {$gid}, 'Room C', 'ROOM1', 4, 6, '2026-12-01 12:00:00', 'FORMING')");
        $roomId = (int) self::$pdo->lastInsertId();

        $this->expectException(PDOException::class);
        // UPDATE creator_id to nonexistent user 999999
        self::$pdo->exec("UPDATE game_rooms SET creator_id = 999999 WHERE id = {$roomId}");
    }

    // =========================================================================
    // SECTION D: FOREIGN KEY DELETE BEHAVIOR
    // =========================================================================

    public function testD01ForeignKeyOnDeleteCascade(): void
    {
        // 1. users -> user_profiles (ON DELETE CASCADE)
        self::$pdo->exec("INSERT INTO users (name, email, password_hash) VALUES ('Cascade User', 'cascade@example.com', 'h')");
        $uid = (int) self::$pdo->lastInsertId();
        self::$pdo->exec("INSERT INTO user_profiles (user_id, display_name) VALUES ({$uid}, 'CasProfile')");

        $this->assertSame(1, (int) self::$pdo->query("SELECT COUNT(*) FROM user_profiles WHERE user_id = {$uid}")->fetchColumn());

        // Delete parent user
        self::$pdo->exec("DELETE FROM users WHERE id = {$uid}");

        // Child user_profile must be cascaded away
        $this->assertSame(0, (int) self::$pdo->query("SELECT COUNT(*) FROM user_profiles WHERE user_id = {$uid}")->fetchColumn());
    }

    public function testD02ForeignKeyOnDeleteRestrict(): void
    {
        // users -> game_rooms (creator_id ON DELETE RESTRICT)
        self::$pdo->exec("INSERT INTO users (name, email, password_hash) VALUES ('Creator User', 'creator@example.com', 'h')");
        $uid = (int) self::$pdo->lastInsertId();
        self::$pdo->exec("INSERT INTO games (title, slug, min_players, max_players, duration_minutes) VALUES ('Game D', 'game-d', 4, 6, 120)");
        $gid = (int) self::$pdo->lastInsertId();
        self::$pdo->exec("INSERT INTO game_rooms (creator_id, game_id, title, room_code, min_players, max_players, formation_deadline, status) VALUES ({$uid}, {$gid}, 'Room D', 'ROOM_R', 4, 6, '2026-12-01 12:00:00', 'FORMING')");

        $this->expectException(PDOException::class);
        // Attempting to delete creator user must fail with 1451 Cannot delete or update a parent row
        self::$pdo->exec("DELETE FROM users WHERE id = {$uid}");
    }

    public function testD03ForeignKeyOnDeleteSetNull(): void
    {
        // users -> notifications (sender_id ON DELETE SET NULL)
        self::$pdo->exec("INSERT INTO users (name, email, password_hash) VALUES ('Sender User', 'sender@example.com', 'h')");
        $senderId = (int) self::$pdo->lastInsertId();
        self::$pdo->exec("INSERT INTO notifications (sender_id, title, message, notification_type) VALUES ({$senderId}, 'Welcome', 'Hello world', 'BROADCAST')");
        $notifId = (int) self::$pdo->lastInsertId();

        // Delete sender user
        self::$pdo->exec("DELETE FROM users WHERE id = {$senderId}");

        // Notification must still exist, with sender_id set to NULL
        $sender = self::$pdo->query("SELECT sender_id FROM notifications WHERE id = {$notifId}")->fetchColumn();
        $this->assertNull($sender);
    }

    // =========================================================================
    // SECTION E: VIRTUAL GENERATED COLUMN CONDITIONAL UNIQUE CONSTRAINTS
    // =========================================================================

    public function testE01GeneratedColumnActiveDmAssignment(): void
    {
        // dm_assignments.active_assigned_room_id = CASE WHEN status IN ('ASSIGNED', 'CONFIRMED') THEN game_room_id ELSE NULL END
        // UNIQUE (active_assigned_room_id) prevents room having >1 active DM
        self::$pdo->exec("INSERT INTO users (name, email, password_hash) VALUES ('Creator', 'c@example.com', 'h'), ('DM1', 'dm1@example.com', 'h'), ('DM2', 'dm2@example.com', 'h')");
        $cid = (int) self::$pdo->query("SELECT id FROM users WHERE email='c@example.com'")->fetchColumn();
        $dm1User = (int) self::$pdo->query("SELECT id FROM users WHERE email='dm1@example.com'")->fetchColumn();
        $dm2User = (int) self::$pdo->query("SELECT id FROM users WHERE email='dm2@example.com'")->fetchColumn();

        self::$pdo->exec("INSERT INTO dm_profiles (user_id, experience_years, status) VALUES ({$dm1User}, 2, 'ACTIVE'), ({$dm2User}, 5, 'ACTIVE')");
        $dm1Id = (int) self::$pdo->query("SELECT id FROM dm_profiles WHERE user_id={$dm1User}")->fetchColumn();
        $dm2Id = (int) self::$pdo->query("SELECT id FROM dm_profiles WHERE user_id={$dm2User}")->fetchColumn();

        self::$pdo->exec("INSERT INTO games (title, slug, min_players, max_players, duration_minutes) VALUES ('Game DM', 'game-dm', 4, 6, 120)");
        $gid = (int) self::$pdo->lastInsertId();
        self::$pdo->exec("INSERT INTO game_rooms (creator_id, game_id, title, room_code, min_players, max_players, formation_deadline, status) VALUES ({$cid}, {$gid}, 'Room DM', 'ROOM_DM', 4, 6, '2026-12-01 12:00:00', 'FORMING')");
        $roomId = (int) self::$pdo->lastInsertId();

        // 1. First DM assignment active (ASSIGNED)
        self::$pdo->exec("INSERT INTO dm_assignments (game_room_id, dm_profile_id, status) VALUES ({$roomId}, {$dm1Id}, 'ASSIGNED')");

        // 2. Second DM assignment for same room active (CONFIRMED) must fail due to uq_active_dm_assignment
        $failed = false;
        try {
            self::$pdo->exec("INSERT INTO dm_assignments (game_room_id, dm_profile_id, status) VALUES ({$roomId}, {$dm2Id}, 'CONFIRMED')");
        } catch (PDOException $e) {
            $failed = true;
            $this->assertStringContainsString('uq_active_dm_assignment', $e->getMessage());
        }
        $this->assertTrue($failed, 'Expected duplicate key error on uq_active_dm_assignment');

        // 3. But if first DM is REJECTED or CANCELLED, active_assigned_room_id becomes NULL, allowing a new assignment!
        self::$pdo->exec("UPDATE dm_assignments SET status = 'CANCELLED' WHERE dm_profile_id = {$dm1Id}");
        self::$pdo->exec("INSERT INTO dm_assignments (game_room_id, dm_profile_id, status) VALUES ({$roomId}, {$dm2Id}, 'CONFIRMED')");
        $this->assertSame(2, (int) self::$pdo->query("SELECT COUNT(*) FROM dm_assignments WHERE game_room_id = {$roomId}")->fetchColumn());
    }

    public function testE02GeneratedColumnActiveRoomParticipant(): void
    {
        // room_participants: active_user_id conditional unique per room
        self::$pdo->exec("INSERT INTO users (name, email, password_hash) VALUES ('CreatorP', 'cp@example.com', 'h'), ('Player 1', 'p1@example.com', 'h')");
        $cid = (int) self::$pdo->query("SELECT id FROM users WHERE email='cp@example.com'")->fetchColumn();
        $p1 = (int) self::$pdo->query("SELECT id FROM users WHERE email='p1@example.com'")->fetchColumn();

        self::$pdo->exec("INSERT INTO games (title, slug, min_players, max_players, duration_minutes) VALUES ('Game P', 'game-p', 4, 6, 120)");
        $gid = (int) self::$pdo->lastInsertId();
        self::$pdo->exec("INSERT INTO game_rooms (creator_id, game_id, title, room_code, min_players, max_players, formation_deadline, status) VALUES ({$cid}, {$gid}, 'Room P', 'ROOM_P', 4, 6, '2026-12-01 12:00:00', 'FORMING')");
        $roomId = (int) self::$pdo->lastInsertId();

        // 1. Join room as active (JOINED)
        self::$pdo->exec("INSERT INTO room_participants (game_room_id, user_id, status) VALUES ({$roomId}, {$p1}, 'JOINED')");

        // 2. Cannot join same room again while active
        $failed = false;
        try {
            self::$pdo->exec("INSERT INTO room_participants (game_room_id, user_id, status) VALUES ({$roomId}, {$p1}, 'DEPOSIT_PENDING')");
        } catch (PDOException $e) {
            $failed = true;
            $this->assertStringContainsString('uq_room_active_participant', $e->getMessage());
        }
        $this->assertTrue($failed, 'Expected duplicate key error on uq_room_active_participant');

        // 3. When player EXITED or KICKED, active_user_id becomes NULL, allowing rejoin
        self::$pdo->exec("UPDATE room_participants SET status = 'EXITED' WHERE user_id = {$p1}");
        self::$pdo->exec("INSERT INTO room_participants (game_room_id, user_id, status) VALUES ({$roomId}, {$p1}, 'JOINED')");
        $this->assertSame(2, (int) self::$pdo->query("SELECT COUNT(*) FROM room_participants WHERE user_id = {$p1}")->fetchColumn());
    }

    public function testE03GeneratedColumnPendingParticipantLeaveRequest(): void
    {
        self::$pdo->exec("INSERT INTO users (name, email, password_hash) VALUES ('CreatorL', 'cl@example.com', 'h'), ('Player L', 'pl@example.com', 'h')");
        $cid = (int) self::$pdo->query("SELECT id FROM users WHERE email='cl@example.com'")->fetchColumn();
        $pl = (int) self::$pdo->query("SELECT id FROM users WHERE email='pl@example.com'")->fetchColumn();

        self::$pdo->exec("INSERT INTO games (title, slug, min_players, max_players, duration_minutes) VALUES ('Game L', 'game-l', 4, 6, 120)");
        $gid = (int) self::$pdo->lastInsertId();
        self::$pdo->exec("INSERT INTO game_rooms (creator_id, game_id, title, room_code, min_players, max_players, formation_deadline, status) VALUES ({$cid}, {$gid}, 'Room L', 'ROOM_L', 4, 6, '2026-12-01 12:00:00', 'FORMING')");
        $roomId = (int) self::$pdo->lastInsertId();

        self::$pdo->exec("INSERT INTO room_participants (game_room_id, user_id, status) VALUES ({$roomId}, {$pl}, 'JOINED')");
        $partId = (int) self::$pdo->lastInsertId();

        // 1. Submit PENDING leave request
        self::$pdo->exec("INSERT INTO participant_leave_requests (game_room_id, room_participant_id, reason, status) VALUES ({$roomId}, {$partId}, 'Need to leave', 'PENDING')");

        // 2. Second PENDING request for same participant must be blocked by uq_pending_leave_request
        $failed = false;
        try {
            self::$pdo->exec("INSERT INTO participant_leave_requests (game_room_id, room_participant_id, reason, status) VALUES ({$roomId}, {$partId}, 'Second request', 'PENDING')");
        } catch (PDOException $e) {
            $failed = true;
            $this->assertStringContainsString('uq_pending_leave_request', $e->getMessage());
        }
        $this->assertTrue($failed, 'Expected duplicate key error on uq_pending_leave_request');
    }

    public function testE04GeneratedColumnProposedSchedule(): void
    {
        self::$pdo->exec("INSERT INTO users (name, email, password_hash) VALUES ('CreatorS', 'cs@example.com', 'h')");
        $cid = (int) self::$pdo->lastInsertId();
        self::$pdo->exec("INSERT INTO games (title, slug, min_players, max_players, duration_minutes) VALUES ('Game S', 'game-s', 4, 6, 120)");
        $gid = (int) self::$pdo->lastInsertId();
        self::$pdo->exec("INSERT INTO game_rooms (creator_id, game_id, title, room_code, min_players, max_players, formation_deadline, status) VALUES ({$cid}, {$gid}, 'Room S', 'ROOM_S', 4, 6, '2026-12-01 12:00:00', 'FORMING')");
        $roomId = (int) self::$pdo->lastInsertId();

        // 1. Propose schedule
        self::$pdo->exec("INSERT INTO schedule_proposals (game_room_id, proposed_by_user_id, proposed_date, start_time, end_time, deadline, status) VALUES ({$roomId}, {$cid}, '2026-12-01', '14:00:00', '18:00:00', '2026-11-30 23:59:59', 'PROPOSED')");

        // 2. Second PROPOSED schedule for same room blocked by uq_active_proposed_schedule
        $failed = false;
        try {
            self::$pdo->exec("INSERT INTO schedule_proposals (game_room_id, proposed_by_user_id, proposed_date, start_time, end_time, deadline, status) VALUES ({$roomId}, {$cid}, '2026-12-02', '14:00:00', '18:00:00', '2026-11-30 23:59:59', 'PROPOSED')");
        } catch (PDOException $e) {
            $failed = true;
            $this->assertStringContainsString('uq_active_proposed_schedule', $e->getMessage());
        }
        $this->assertTrue($failed, 'Expected duplicate key error on uq_active_proposed_schedule');
    }

    public function testE05GeneratedColumnActivePhysicalRoomReservation(): void
    {
        // Setup room, game, session, physical room
        self::$pdo->exec("INSERT INTO users (name, email, password_hash) VALUES ('CreatorRes', 'cres@example.com', 'h')");
        $cid = (int) self::$pdo->lastInsertId();
        self::$pdo->exec("INSERT INTO games (title, slug, min_players, max_players, duration_minutes) VALUES ('Game Res', 'game-res', 4, 6, 120)");
        $gid = (int) self::$pdo->lastInsertId();
        self::$pdo->exec("INSERT INTO game_rooms (creator_id, game_id, title, room_code, min_players, max_players, formation_deadline, status) VALUES ({$cid}, {$gid}, 'Room Res', 'ROOM_RES', 4, 6, '2026-12-01 12:00:00', 'FORMING')");
        $roomId = (int) self::$pdo->lastInsertId();

        self::$pdo->exec("INSERT INTO sessions (game_room_id, session_code, scheduled_date, start_time, end_time, status) VALUES ({$roomId}, 'SESS001', '2026-12-01', '14:00:00', '18:00:00', 'SCHEDULED')");
        $sessId = (int) self::$pdo->lastInsertId();

        self::$pdo->exec("INSERT INTO physical_rooms (name, code, capacity) VALUES ('Room Alpha', 'ALPHA', 8)");
        $prId = (int) self::$pdo->lastInsertId();

        // 1. Create RESERVED reservation for session
        self::$pdo->exec("INSERT INTO physical_room_reservations (physical_room_id, session_id, reservation_date, start_time, end_time, status) VALUES ({$prId}, {$sessId}, '2026-12-01', '14:00:00', '18:00:00', 'RESERVED')");

        // 2. Second RESERVED or CONFIRMED reservation for same session blocked by uq_session_active_reservation
        $failed = false;
        try {
            self::$pdo->exec("INSERT INTO physical_room_reservations (physical_room_id, session_id, reservation_date, start_time, end_time, status) VALUES ({$prId}, {$sessId}, '2026-12-01', '14:00:00', '18:00:00', 'CONFIRMED')");
        } catch (PDOException $e) {
            $failed = true;
            $this->assertStringContainsString('uq_session_active_reservation', $e->getMessage());
        }
        $this->assertTrue($failed, 'Expected duplicate key error on uq_session_active_reservation');
    }

    // =========================================================================
    // SECTION F: IDEMPOTENCY KEY ENFORCEMENT
    // =========================================================================

    public function testF01PaymentAttemptIdempotencyKey(): void
    {
        // Setup participant and payment
        self::$pdo->exec("INSERT INTO users (name, email, password_hash) VALUES ('PayUser', 'pay@example.com', 'h')");
        $uid = (int) self::$pdo->lastInsertId();
        self::$pdo->exec("INSERT INTO games (title, slug, min_players, max_players, duration_minutes) VALUES ('Game Pay', 'game-pay', 4, 6, 120)");
        $gid = (int) self::$pdo->lastInsertId();
        self::$pdo->exec("INSERT INTO game_rooms (creator_id, game_id, title, room_code, min_players, max_players, formation_deadline, status) VALUES ({$uid}, {$gid}, 'Room Pay', 'ROOM_PAY', 4, 6, '2026-12-01 12:00:00', 'FORMING')");
        $roomId = (int) self::$pdo->lastInsertId();
        self::$pdo->exec("INSERT INTO room_participants (game_room_id, user_id, status) VALUES ({$roomId}, {$uid}, 'JOINED')");
        $partId = (int) self::$pdo->lastInsertId();
        self::$pdo->exec("INSERT INTO sessions (game_room_id, session_code, scheduled_date, start_time, end_time, status) VALUES ({$roomId}, 'SESS_PAY', '2026-12-01', '14:00:00', '18:00:00', 'SCHEDULED')");
        $sessId = (int) self::$pdo->lastInsertId();

        self::$pdo->exec("INSERT INTO player_payments (session_id, room_participant_id, payment_type, amount_due, amount_paid, status) VALUES ({$sessId}, {$partId}, 'FULL', 200000.00, 0, 'PENDING')");
        $payId = (int) self::$pdo->lastInsertId();

        // 1. Payment attempt with idempotency key
        $idempKey = 'pay_attempt_uuid_99999_xyz';
        self::$pdo->exec("INSERT INTO payment_attempts (player_payment_id, idempotency_key, payment_method, amount, status) VALUES ({$payId}, '{$idempKey}', 'VNPAY', 200000.00, 'INITIATED')");

        // 2. Duplicate attempt with same idempotency key must be rejected by uq_payment_attempts_idempotency
        $this->expectException(PDOException::class);
        self::$pdo->exec("INSERT INTO payment_attempts (player_payment_id, idempotency_key, payment_method, amount, status) VALUES ({$payId}, '{$idempKey}', 'VNPAY', 200000.00, 'INITIATED')");
    }

    public function testF02RefundAttemptIdempotencyKey(): void
    {
        // Setup participant, session, payment, refund
        self::$pdo->exec("INSERT INTO users (name, email, password_hash) VALUES ('RefUser', 'ref@example.com', 'h')");
        $uid = (int) self::$pdo->lastInsertId();
        self::$pdo->exec("INSERT INTO games (title, slug, min_players, max_players, duration_minutes) VALUES ('Game Ref', 'game-ref', 4, 6, 120)");
        $gid = (int) self::$pdo->lastInsertId();
        self::$pdo->exec("INSERT INTO game_rooms (creator_id, game_id, title, room_code, min_players, max_players, formation_deadline, status) VALUES ({$uid}, {$gid}, 'Room Ref', 'ROOM_REF', 4, 6, '2026-12-01 12:00:00', 'FORMING')");
        $roomId = (int) self::$pdo->lastInsertId();
        self::$pdo->exec("INSERT INTO room_participants (game_room_id, user_id, status) VALUES ({$roomId}, {$uid}, 'JOINED')");
        $partId = (int) self::$pdo->lastInsertId();
        self::$pdo->exec("INSERT INTO sessions (game_room_id, session_code, scheduled_date, start_time, end_time, status) VALUES ({$roomId}, 'SESS_REF', '2026-12-01', '14:00:00', '18:00:00', 'SCHEDULED')");
        $sessId = (int) self::$pdo->lastInsertId();

        self::$pdo->exec("INSERT INTO player_payments (session_id, room_participant_id, payment_type, amount_due, amount_paid, status) VALUES ({$sessId}, {$partId}, 'FULL', 200000.00, 200000.00, 'PAID')");
        $payId = (int) self::$pdo->lastInsertId();

        self::$pdo->exec("INSERT INTO refunds (player_payment_id, room_participant_id, reason, refund_amount, status) VALUES ({$payId}, {$partId}, 'Session cancelled', 200000.00, 'PENDING')");
        $refId = (int) self::$pdo->lastInsertId();

        // 1. Refund attempt with idempotency key
        $idempKey = 'ref_attempt_uuid_11111_abc';
        self::$pdo->exec("INSERT INTO refund_attempts (refund_id, idempotency_key, amount, status) VALUES ({$refId}, '{$idempKey}', 200000.00, 'INITIATED')");

        // 2. Duplicate refund attempt with same idempotency key must fail
        $this->expectException(PDOException::class);
        self::$pdo->exec("INSERT INTO refund_attempts (refund_id, idempotency_key, amount, status) VALUES ({$refId}, '{$idempKey}', 200000.00, 'INITIATED')");
    }

    // =========================================================================
    // SECTION G: TRANSACTION ROLLBACK TESTS (ACID ATOMICITY)
    // =========================================================================

    public function testG01TransactionRollbackRestoresState(): void
    {
        self::$pdo->beginTransaction();

        try {
            self::$pdo->exec("INSERT INTO users (name, email, password_hash) VALUES ('RollbackUser', 'rb@example.com', 'h')");
            $uid = (int) self::$pdo->lastInsertId();

            // Trigger failure (violating NOT NULL or check constraint)
            self::$pdo->exec("INSERT INTO roles (name, label) VALUES (NULL, 'Invalid Role')");

            self::$pdo->commit();
            $this->fail('Transaction should have thrown exception');
        } catch (\Throwable $e) {
            self::$pdo->rollBack();
        }

        // Verify that RollbackUser was NOT committed
        $count = (int) self::$pdo->query("SELECT COUNT(*) FROM users WHERE email = 'rb@example.com'")->fetchColumn();
        $this->assertSame(0, $count, 'Uncommitted row must be wiped by rollback');
    }

    // =========================================================================
    // SECTION H: CONCURRENCY & BUSINESS INTEGRITY SIMULATION
    // =========================================================================

    /**
     * Demonstrates Business Integrity Analysis A: Physical Room Overlap.
     * MySQL schema only checks session reservation uniqueness, NOT interval overlap [start_time, end_time).
     * Two different sessions CAN reserve the same physical room at overlapping times unless application locks!
     */
    public function testH01PhysicalRoomOverlapVulnerabilityDemonstration(): void
    {
        // Prerequisite setup
        self::$pdo->exec("INSERT INTO users (name, email, password_hash) VALUES ('CreatorH1', 'ch1@example.com', 'h')");
        $cid = (int) self::$pdo->lastInsertId();
        self::$pdo->exec("INSERT INTO games (title, slug, min_players, max_players, duration_minutes) VALUES ('Game H1', 'game-h1', 4, 6, 120)");
        $gid = (int) self::$pdo->lastInsertId();
        self::$pdo->exec("INSERT INTO game_rooms (creator_id, game_id, title, room_code, min_players, max_players, formation_deadline, status) VALUES ({$cid}, {$gid}, 'Room H1', 'ROOM_H1', 4, 6, '2026-12-01 12:00:00', 'FORMING')");
        $roomId = (int) self::$pdo->lastInsertId();

        self::$pdo->exec("INSERT INTO sessions (game_room_id, session_code, scheduled_date, start_time, end_time, status) VALUES ({$roomId}, 'SESS_A', '2026-12-05', '14:00:00', '18:00:00', 'SCHEDULED')");
        $sessA = (int) self::$pdo->lastInsertId();
        self::$pdo->exec("INSERT INTO sessions (game_room_id, session_code, scheduled_date, start_time, end_time, status) VALUES ({$roomId}, 'SESS_B', '2026-12-05', '16:00:00', '20:00:00', 'SCHEDULED')");
        $sessB = (int) self::$pdo->lastInsertId();

        self::$pdo->exec("INSERT INTO physical_rooms (name, code, capacity) VALUES ('VIP Room 1', 'VIP1', 10)");
        $vipRoomId = (int) self::$pdo->lastInsertId();

        // Session A reserves VIP1 from 14:00 to 18:00
        self::$pdo->exec("INSERT INTO physical_room_reservations (physical_room_id, session_id, reservation_date, start_time, end_time, status) VALUES ({$vipRoomId}, {$sessA}, '2026-12-05', '14:00:00', '18:00:00', 'CONFIRMED')");

        // Session B attempts to reserve VIP1 from 16:00 to 20:00 (OVERLAP: 16:00 - 18:00)
        // In MySQL schema, there is NO temporal exclusion constraint!
        self::$pdo->exec("INSERT INTO physical_room_reservations (physical_room_id, session_id, reservation_date, start_time, end_time, status) VALUES ({$vipRoomId}, {$sessB}, '2026-12-05', '16:00:00', '20:00:00', 'CONFIRMED')");

        // BOTH rows exist in the DB!
        $count = (int) self::$pdo->query("SELECT COUNT(*) FROM physical_room_reservations WHERE physical_room_id = {$vipRoomId} AND status = 'CONFIRMED'")->fetchColumn();
        $this->assertSame(2, $count, 'Schema allows temporal overlap. Application-level pessimistic locking (SELECT FOR UPDATE) is MANDATORY to prevent double-booking!');
    }

    /**
     * Demonstrates Business Integrity Analysis B: DM Concurrent Scheduling Conflict.
     * Schema has uq_active_dm_assignment on active_assigned_room_id (preventing >1 DM in same room),
     * but does NOT prevent 1 DM from being assigned to 2 different rooms with overlapping sessions!
     */
    public function testH02DmOverlappingAssignmentDemonstration(): void
    {
        self::$pdo->exec("INSERT INTO users (name, email, password_hash) VALUES ('DM Busy', 'busy@example.com', 'h'), ('Creator 1', 'cr1@example.com', 'h'), ('Creator 2', 'cr2@example.com', 'h')");
        $dmUser = (int) self::$pdo->query("SELECT id FROM users WHERE email='busy@example.com'")->fetchColumn();
        $cr1 = (int) self::$pdo->query("SELECT id FROM users WHERE email='cr1@example.com'")->fetchColumn();
        $cr2 = (int) self::$pdo->query("SELECT id FROM users WHERE email='cr2@example.com'")->fetchColumn();

        self::$pdo->exec("INSERT INTO dm_profiles (user_id, experience_years, status) VALUES ({$dmUser}, 3, 'ACTIVE')");
        $dmProfileId = (int) self::$pdo->lastInsertId();

        self::$pdo->exec("INSERT INTO games (title, slug, min_players, max_players, duration_minutes) VALUES ('Game H2', 'game-h2', 4, 6, 120)");
        $gid = (int) self::$pdo->lastInsertId();

        self::$pdo->exec("INSERT INTO game_rooms (creator_id, game_id, title, room_code, min_players, max_players, formation_deadline, status) VALUES ({$cr1}, {$gid}, 'Room DM 1', 'ROOM_DM_1', 4, 6, '2026-12-01 12:00:00', 'FORMING')");
        $room1 = (int) self::$pdo->lastInsertId();
        self::$pdo->exec("INSERT INTO game_rooms (creator_id, game_id, title, room_code, min_players, max_players, formation_deadline, status) VALUES ({$cr2}, {$gid}, 'Room DM 2', 'ROOM_DM_2', 4, 6, '2026-12-01 12:00:00', 'FORMING')");
        $room2 = (int) self::$pdo->lastInsertId();

        // Assign same DM to Room 1 and Room 2 simultaneously
        self::$pdo->exec("INSERT INTO dm_assignments (game_room_id, dm_profile_id, status) VALUES ({$room1}, {$dmProfileId}, 'CONFIRMED')");
        self::$pdo->exec("INSERT INTO dm_assignments (game_room_id, dm_profile_id, status) VALUES ({$room2}, {$dmProfileId}, 'CONFIRMED')");

        $dmAssignedCount = (int) self::$pdo->query("SELECT COUNT(*) FROM dm_assignments WHERE dm_profile_id = {$dmProfileId} AND status = 'CONFIRMED'")->fetchColumn();
        $this->assertSame(2, $dmAssignedCount, 'Schema allows DM assigned to multiple rooms. Application service layer must query schedule overlap before assigning DM!');
    }

    /**
     * Demonstrates Business Integrity Analysis D: Payment amount_paid exceeding amount_due.
     */
    public function testH03OverpaymentProtectionConstraintScope(): void
    {
        // Setup
        self::$pdo->exec("INSERT INTO users (name, email, password_hash) VALUES ('Payer', 'payer@example.com', 'h')");
        $uid = (int) self::$pdo->lastInsertId();
        self::$pdo->exec("INSERT INTO games (title, slug, min_players, max_players, duration_minutes) VALUES ('Game P3', 'game-p3', 4, 6, 120)");
        $gid = (int) self::$pdo->lastInsertId();
        self::$pdo->exec("INSERT INTO game_rooms (creator_id, game_id, title, room_code, min_players, max_players, formation_deadline, status) VALUES ({$uid}, {$gid}, 'Room P3', 'ROOM_P3', 4, 6, '2026-12-01 12:00:00', 'FORMING')");
        $roomId = (int) self::$pdo->lastInsertId();
        self::$pdo->exec("INSERT INTO room_participants (game_room_id, user_id, status) VALUES ({$roomId}, {$uid}, 'JOINED')");
        $partId = (int) self::$pdo->lastInsertId();
        self::$pdo->exec("INSERT INTO sessions (game_room_id, session_code, scheduled_date, start_time, end_time, status) VALUES ({$roomId}, 'SESS_P3', '2026-12-01', '14:00:00', '18:00:00', 'SCHEDULED')");
        $sessId = (int) self::$pdo->lastInsertId();

        // Insert player payment where amount_paid (500000) > amount_due (200000)
        // chk_player_payments_amounts only checks: amount_due >= 0 AND amount_paid >= 0.
        // It does NOT check amount_paid <= amount_due!
        self::$pdo->exec("INSERT INTO player_payments (session_id, room_participant_id, payment_type, amount_due, amount_paid, status) VALUES ({$sessId}, {$partId}, 'FULL', 200000.00, 500000.00, 'PAID')");
        $paid = (float) self::$pdo->query("SELECT amount_paid FROM player_payments WHERE session_id = {$sessId}")->fetchColumn();
        $due = (float) self::$pdo->query("SELECT amount_due FROM player_payments WHERE session_id = {$sessId}")->fetchColumn();

        $this->assertGreaterThan($due, $paid, 'Schema does NOT enforce amount_paid <= amount_due (to accommodate potential tips/overpayments). Service layer must validate exact payment match!');
    }
}
