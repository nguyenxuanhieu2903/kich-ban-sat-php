<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Infrastructure\Config\Config;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

/**
 * Class DatabaseIntegrityTest
 *
 * Exhaustive database verification test suite for the canonical 21-table schema.
 * Tests against MySQL 8.0/8.4 InnoDB schema (booking_project_php_test).
 * Validates ACID properties, referential integrity, check constraints,
 * virtual generated conditional unique constraints, idempotency keys, and transaction rollback.
 */
class DatabaseIntegrityTest extends TestCase
{
    private static PDO $pdo;

    public static function setUpBeforeClass(): void
    {
        $basePath = dirname(__DIR__, 2);
        Config::load($basePath);

        $host = Config::get('DB_HOST', '127.0.0.1');
        $port = (int) Config::get('DB_PORT', 3306);
        $db = Config::get('DB_TEST_DATABASE', 'booking_project_php_test');
        $user = Config::get('DB_USERNAME', 'root');
        $pass = Config::get('DB_PASSWORD', 'Hieu29032006@');

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
            'notifications',
            'refunds',
            'payment_transactions',
            'player_payments',
            'participant_price_snapshots',
            'booking_price_snapshots',
            'promotions',
            'game_prices',
            'sessions',
            'schedule_responses',
            'schedule_proposals',
            'physical_rooms',
            'venues',
            'dm_assignments',
            'replacement_windows',
            'room_participants',
            'game_rooms',
            'game_configurations',
            'games',
            'users',
            'roles',
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
        // 1. Insert Role
        $stmt = self::$pdo->prepare("
            INSERT INTO roles (name, label, description) 
            VALUES ('PLAYER', 'Standard Player', 'Default role for booked players')
        ");
        $stmt->execute();
        $roleId = (int) self::$pdo->lastInsertId();
        $this->assertGreaterThan(0, $roleId);

        // 2. Insert User with role_id
        $stmt = self::$pdo->prepare("
            INSERT INTO users (role_id, name, email, password_hash, phone, status) 
            VALUES (:role_id, :name, :email, :hash, :phone, 'ACTIVE')
        ");
        $stmt->execute([
            'role_id' => $roleId,
            'name' => 'Alice Nguyen',
            'email' => 'alice@example.com',
            'hash' => password_hash('Secret123!', PASSWORD_BCRYPT),
            'phone' => '0901234567',
        ]);
        $userId = (int) self::$pdo->lastInsertId();
        $this->assertGreaterThan(0, $userId);
    }

    public function testA02ValidGameCatalogInsert(): void
    {
        // Game
        $stmt = self::$pdo->prepare("
            INSERT INTO games (title, slug, category, min_players, max_players, duration_minutes, is_active) 
            VALUES ('Nightmare at the Manor', 'nightmare-manor', 'Horror Mystery', 6, 8, 240, 1)
        ");
        $stmt->execute();
        $gameId = (int) self::$pdo->lastInsertId();

        // Game Config
        $stmt = self::$pdo->prepare("INSERT INTO game_configurations (game_id, config_key, config_value) VALUES (:gid, 'soundtrack_url', 'https://music.example.com/spooky')");
        $stmt->execute(['gid' => $gameId]);
        $configId = (int) self::$pdo->lastInsertId();

        $this->assertGreaterThan(0, $gameId);
        $this->assertGreaterThan(0, $configId);
    }

    public function testA03ValidPricingAndPromotionInsert(): void
    {
        self::$pdo->exec("INSERT INTO games (title, slug, min_players, max_players, duration_minutes) VALUES ('Game Price', 'game-price', 4, 6, 120)");
        $gid = (int) self::$pdo->lastInsertId();

        self::$pdo->exec("INSERT INTO game_prices (game_id, price_tier_name, day_type, time_from, time_to, base_price_per_player) VALUES ({$gid}, 'Standard Weekday', 'WEEKDAY', '08:00:00', '17:00:00', 150000.00)");
        $priceId = (int) self::$pdo->lastInsertId();
        $this->assertGreaterThan(0, $priceId);

        self::$pdo->exec("INSERT INTO promotions (code, name, discount_type, discount_value, valid_from, valid_to, is_active) VALUES ('SUMMER2026', 'Summer Promo', 'FIXED_AMOUNT', 20000.00, '2026-06-01 00:00:00', '2026-08-31 23:59:59', 1)");
        $promoId = (int) self::$pdo->lastInsertId();
        $this->assertGreaterThan(0, $promoId);
    }

    public function testA04ValidVenueAndPhysicalRoomInsert(): void
    {
        self::$pdo->exec("INSERT INTO venues (name, code, address, is_active) VALUES ('Cyber Hub', 'CYBER_HUB', '123 Nguyen Hue', 1)");
        $venueId = (int) self::$pdo->lastInsertId();

        self::$pdo->exec("INSERT INTO physical_rooms (venue_id, name, code, capacity, status) VALUES ({$venueId}, 'Room 101', 'R101', 8, 'AVAILABLE')");
        $roomId = (int) self::$pdo->lastInsertId();

        $this->assertGreaterThan(0, $venueId);
        $this->assertGreaterThan(0, $roomId);
    }

    // =========================================================================
    // SECTION B: INVALID INSERT TESTS (CONSTRAINTS ENFORCEMENT)
    // =========================================================================

    public function testB01NotNullConstraintViolation(): void
    {
        self::$pdo->exec("INSERT INTO roles (name, label) VALUES ('PLAYER', 'Player')");
        $roleId = (int) self::$pdo->lastInsertId();

        $this->expectException(PDOException::class);
        // Column 'email' cannot be null
        self::$pdo->exec("INSERT INTO users (role_id, name, email, password_hash) VALUES ({$roleId}, 'Bob', NULL, 'hash123')");
    }

    public function testB02ForeignKeyViolationOnInsert(): void
    {
        $this->expectException(PDOException::class);
        // users referencing non-existent role_id=999999
        self::$pdo->exec("INSERT INTO users (role_id, name, email, password_hash) VALUES (999999, 'Bob', 'bob@example.com', 'hash123')");
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
        self::$pdo->exec("INSERT INTO games (title, slug, min_players, max_players, duration_minutes) VALUES ('Valid Game', 'valid-game', 4, 6, 120)");
        $gameId = (int) self::$pdo->lastInsertId();

        $this->expectException(PDOException::class);
        // chk_game_prices_times: time_from < time_to (Violated: 18:00:00 < 14:00:00 is false)
        self::$pdo->exec("
            INSERT INTO game_prices (game_id, price_tier_name, day_type, time_from, time_to, base_price_per_player) 
            VALUES ({$gameId}, 'Invalid Tier', 'WEEKEND', '18:00:00', '14:00:00', 250000.00)
        ");
    }

    public function testB05CheckConstraintViolationOnScheduleProposalsTimes(): void
    {
        self::$pdo->exec("INSERT INTO roles (name, label) VALUES ('PLAYER', 'Player')");
        $roleId = (int) self::$pdo->lastInsertId();
        self::$pdo->exec("INSERT INTO users (role_id, name, email, password_hash) VALUES ({$roleId}, 'Proposer', 'prop@example.com', 'h')");
        $uid = (int) self::$pdo->lastInsertId();

        self::$pdo->exec("INSERT INTO games (title, slug, min_players, max_players, duration_minutes) VALUES ('Game Prop', 'game-prop', 4, 6, 120)");
        $gid = (int) self::$pdo->lastInsertId();
        self::$pdo->exec("INSERT INTO game_rooms (creator_id, game_id, title, room_code, min_players, max_players, formation_deadline, status) VALUES ({$uid}, {$gid}, 'Room Prop', 'ROOM_PROP', 4, 6, '2026-12-01 12:00:00', 'FORMING')");
        $roomId = (int) self::$pdo->lastInsertId();

        $this->expectException(PDOException::class);
        // chk_schedule_proposals_times: start_time < end_time (Violated: 18:00:00 < 14:00:00)
        self::$pdo->exec("
            INSERT INTO schedule_proposals (game_room_id, proposed_by_user_id, proposed_date, start_time, end_time, deadline) 
            VALUES ({$roomId}, {$uid}, '2026-12-01', '18:00:00', '14:00:00', '2026-11-30 12:00:00')
        ");
    }

    public function testB06UniqueConstraintViolationSingleColumn(): void
    {
        self::$pdo->exec("INSERT INTO roles (name, label) VALUES ('PLAYER', 'Player')");
        $roleId = (int) self::$pdo->lastInsertId();

        self::$pdo->exec("INSERT INTO users (role_id, name, email, password_hash) VALUES ({$roleId}, 'User 1', 'unique@example.com', 'h1')");

        $this->expectException(PDOException::class);
        // Duplicate entry 'unique@example.com' for key 'users.uq_users_email'
        self::$pdo->exec("INSERT INTO users (role_id, name, email, password_hash) VALUES ({$roleId}, 'User 2', 'unique@example.com', 'h2')");
    }

    public function testB07UniqueConstraintViolationComposite(): void
    {
        self::$pdo->exec("INSERT INTO roles (name, label) VALUES ('PLAYER', 'Player')");
        $roleId = (int) self::$pdo->lastInsertId();
        self::$pdo->exec("INSERT INTO users (role_id, name, email, password_hash) VALUES ({$roleId}, 'User Vote', 'vote@example.com', 'h')");
        $uid = (int) self::$pdo->lastInsertId();

        self::$pdo->exec("INSERT INTO games (title, slug, min_players, max_players, duration_minutes) VALUES ('Game V', 'game-v', 4, 6, 120)");
        $gid = (int) self::$pdo->lastInsertId();
        self::$pdo->exec("INSERT INTO game_rooms (creator_id, game_id, title, room_code, min_players, max_players, formation_deadline, status) VALUES ({$uid}, {$gid}, 'Room V', 'ROOM_V', 4, 6, '2026-12-01 12:00:00', 'FORMING')");
        $roomId = (int) self::$pdo->lastInsertId();

        self::$pdo->exec("INSERT INTO room_participants (game_room_id, user_id, status) VALUES ({$roomId}, {$uid}, 'JOINED')");
        $partId = (int) self::$pdo->lastInsertId();

        self::$pdo->exec("INSERT INTO schedule_proposals (game_room_id, proposed_by_user_id, proposed_date, start_time, end_time, deadline) VALUES ({$roomId}, {$uid}, '2026-12-01', '14:00:00', '18:00:00', '2026-11-30 12:00:00')");
        $propId = (int) self::$pdo->lastInsertId();

        // First vote
        self::$pdo->exec("INSERT INTO schedule_responses (schedule_proposal_id, room_participant_id, response) VALUES ({$propId}, {$partId}, 'ACCEPTED')");

        $this->expectException(PDOException::class);
        // Duplicate vote on composite key uq_schedule_responses_vote (schedule_proposal_id, room_participant_id)
        self::$pdo->exec("INSERT INTO schedule_responses (schedule_proposal_id, room_participant_id, response) VALUES ({$propId}, {$partId}, 'DECLINED')");
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
        self::$pdo->exec("INSERT INTO roles (name, label) VALUES ('PLAYER', 'Player')");
        $roleId = (int) self::$pdo->lastInsertId();

        self::$pdo->exec("INSERT INTO users (role_id, name, email, password_hash) VALUES ({$roleId}, 'User 1', 'one@example.com', 'h1')");
        self::$pdo->exec("INSERT INTO users (role_id, name, email, password_hash) VALUES ({$roleId}, 'User 2', 'two@example.com', 'h2')");
        $u2Id = (int) self::$pdo->lastInsertId();

        $this->expectException(PDOException::class);
        // UPDATE user 2 email to user 1 email
        self::$pdo->exec("UPDATE users SET email = 'one@example.com' WHERE id = {$u2Id}");
    }

    public function testC03ForeignKeyViolationOnUpdate(): void
    {
        self::$pdo->exec("INSERT INTO roles (name, label) VALUES ('PLAYER', 'Player')");
        $roleId = (int) self::$pdo->lastInsertId();

        self::$pdo->exec("INSERT INTO users (role_id, name, email, password_hash) VALUES ({$roleId}, 'User 1', 'one@example.com', 'h1')");
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
        // games -> game_configurations (ON DELETE CASCADE)
        self::$pdo->exec("INSERT INTO games (title, slug, min_players, max_players, duration_minutes) VALUES ('Game Cascade', 'game-casc', 4, 6, 120)");
        $gid = (int) self::$pdo->lastInsertId();
        self::$pdo->exec("INSERT INTO game_configurations (game_id, config_key, config_value) VALUES ({$gid}, 'clue_count', '12')");

        $this->assertSame(1, (int) self::$pdo->query("SELECT COUNT(*) FROM game_configurations WHERE game_id = {$gid}")->fetchColumn());

        // Delete parent game
        self::$pdo->exec("DELETE FROM games WHERE id = {$gid}");

        // Child configuration must be cascaded away
        $this->assertSame(0, (int) self::$pdo->query("SELECT COUNT(*) FROM game_configurations WHERE game_id = {$gid}")->fetchColumn());
    }

    public function testD02ForeignKeyOnDeleteRestrict(): void
    {
        // users -> game_rooms (creator_id ON DELETE RESTRICT)
        self::$pdo->exec("INSERT INTO roles (name, label) VALUES ('PLAYER', 'Player')");
        $roleId = (int) self::$pdo->lastInsertId();

        self::$pdo->exec("INSERT INTO users (role_id, name, email, password_hash) VALUES ({$roleId}, 'Creator User', 'creator@example.com', 'h')");
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
        self::$pdo->exec("INSERT INTO roles (name, label) VALUES ('PLAYER', 'Player')");
        $roleId = (int) self::$pdo->lastInsertId();

        self::$pdo->exec("INSERT INTO users (role_id, name, email, password_hash) VALUES ({$roleId}, 'Sender User', 'sender@example.com', 'h')");
        $senderId = (int) self::$pdo->lastInsertId();
        self::$pdo->exec("INSERT INTO users (role_id, name, email, password_hash) VALUES ({$roleId}, 'Recipient User', 'recipient@example.com', 'h')");
        $recipId = (int) self::$pdo->lastInsertId();

        self::$pdo->exec("INSERT INTO notifications (user_id, sender_id, title, message, notification_type) VALUES ({$recipId}, {$senderId}, 'Welcome', 'Hello world', 'ROOM_EVENT')");
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
        // dm_assignments.active_assigned_room_id = CASE WHEN status IN ('ASSIGNED', 'ACCEPTED') THEN game_room_id ELSE NULL END
        // UNIQUE (active_assigned_room_id) prevents room having >1 active DM
        self::$pdo->exec("INSERT INTO roles (name, label) VALUES ('PLAYER', 'Player'), ('DM', 'Dungeon Master')");
        $playerRole = (int) self::$pdo->query("SELECT id FROM roles WHERE name='PLAYER'")->fetchColumn();
        $dmRole = (int) self::$pdo->query("SELECT id FROM roles WHERE name='DM'")->fetchColumn();

        self::$pdo->exec("INSERT INTO users (role_id, name, email, password_hash) VALUES 
            ({$playerRole}, 'Creator', 'c@example.com', 'h'), 
            ({$dmRole}, 'DM1', 'dm1@example.com', 'h'), 
            ({$dmRole}, 'DM2', 'dm2@example.com', 'h')");
        $cid = (int) self::$pdo->query("SELECT id FROM users WHERE email='c@example.com'")->fetchColumn();
        $dm1User = (int) self::$pdo->query("SELECT id FROM users WHERE email='dm1@example.com'")->fetchColumn();
        $dm2User = (int) self::$pdo->query("SELECT id FROM users WHERE email='dm2@example.com'")->fetchColumn();

        self::$pdo->exec("INSERT INTO games (title, slug, min_players, max_players, duration_minutes) VALUES ('Game DM', 'game-dm', 4, 6, 120)");
        $gid = (int) self::$pdo->lastInsertId();
        self::$pdo->exec("INSERT INTO game_rooms (creator_id, game_id, title, room_code, min_players, max_players, formation_deadline, status) VALUES ({$cid}, {$gid}, 'Room DM', 'ROOM_DM', 4, 6, '2026-12-01 12:00:00', 'FORMING')");
        $roomId = (int) self::$pdo->lastInsertId();

        // 1. First DM assignment active (ASSIGNED)
        self::$pdo->exec("INSERT INTO dm_assignments (game_room_id, dm_user_id, status) VALUES ({$roomId}, {$dm1User}, 'ASSIGNED')");

        // 2. Second DM assignment for same room active (ACCEPTED) must fail due to uq_active_dm_assignment
        $failed = false;
        try {
            self::$pdo->exec("INSERT INTO dm_assignments (game_room_id, dm_user_id, status) VALUES ({$roomId}, {$dm2User}, 'ACCEPTED')");
        } catch (PDOException $e) {
            $failed = true;
            $this->assertStringContainsString('uq_active_dm_assignment', $e->getMessage());
        }
        $this->assertTrue($failed, 'Expected duplicate key error on uq_active_dm_assignment');

        // 3. But if first DM is CANCELLED or REJECTED, active_assigned_room_id becomes NULL, allowing a new assignment!
        self::$pdo->exec("UPDATE dm_assignments SET status = 'CANCELLED' WHERE dm_user_id = {$dm1User}");
        self::$pdo->exec("INSERT INTO dm_assignments (game_room_id, dm_user_id, status) VALUES ({$roomId}, {$dm2User}, 'ACCEPTED')");
        $this->assertSame(2, (int) self::$pdo->query("SELECT COUNT(*) FROM dm_assignments WHERE game_room_id = {$roomId}")->fetchColumn());
    }

    public function testE02GeneratedColumnActiveRoomParticipant(): void
    {
        // room_participants: active_user_id conditional unique per room
        self::$pdo->exec("INSERT INTO roles (name, label) VALUES ('PLAYER', 'Player')");
        $roleId = (int) self::$pdo->lastInsertId();

        self::$pdo->exec("INSERT INTO users (role_id, name, email, password_hash) VALUES 
            ({$roleId}, 'CreatorP', 'cp@example.com', 'h'), 
            ({$roleId}, 'Player 1', 'p1@example.com', 'h')");
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
            self::$pdo->exec("INSERT INTO room_participants (game_room_id, user_id, status) VALUES ({$roomId}, {$p1}, 'CONFIRMED')");
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

    public function testE03GeneratedColumnProposedSchedule(): void
    {
        self::$pdo->exec("INSERT INTO roles (name, label) VALUES ('PLAYER', 'Player')");
        $roleId = (int) self::$pdo->lastInsertId();

        self::$pdo->exec("INSERT INTO users (role_id, name, email, password_hash) VALUES ({$roleId}, 'CreatorS', 'cs@example.com', 'h')");
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

    // =========================================================================
    // SECTION F: IDEMPOTENCY KEY ENFORCEMENT
    // =========================================================================

    public function testF01PaymentTransactionIdempotencyKey(): void
    {
        self::$pdo->exec("INSERT INTO roles (name, label) VALUES ('PLAYER', 'Player')");
        $roleId = (int) self::$pdo->lastInsertId();

        self::$pdo->exec("INSERT INTO users (role_id, name, email, password_hash) VALUES ({$roleId}, 'PayUser', 'pay@example.com', 'h')");
        $uid = (int) self::$pdo->lastInsertId();
        self::$pdo->exec("INSERT INTO games (title, slug, min_players, max_players, duration_minutes) VALUES ('Game Pay', 'game-pay', 4, 6, 120)");
        $gid = (int) self::$pdo->lastInsertId();
        self::$pdo->exec("INSERT INTO game_rooms (creator_id, game_id, title, room_code, min_players, max_players, formation_deadline, status) VALUES ({$uid}, {$gid}, 'Room Pay', 'ROOM_PAY', 4, 6, '2026-12-01 12:00:00', 'FORMING')");
        $roomId = (int) self::$pdo->lastInsertId();
        self::$pdo->exec("INSERT INTO room_participants (game_room_id, user_id, status) VALUES ({$roomId}, {$uid}, 'JOINED')");
        $partId = (int) self::$pdo->lastInsertId();
        self::$pdo->exec("INSERT INTO sessions (game_room_id, session_code, scheduled_date, start_time, end_time, status) VALUES ({$roomId}, 'SESS_PAY', '2026-12-01', '14:00:00', '18:00:00', 'SCHEDULED')");
        $sessId = (int) self::$pdo->lastInsertId();

        self::$pdo->exec("INSERT INTO player_payments (session_id, room_participant_id, payment_type, amount_due, amount_paid, status) VALUES ({$sessId}, {$partId}, 'FULL', 200000, 0, 'PENDING')");
        $payId = (int) self::$pdo->lastInsertId();

        // 1. Payment transaction with idempotency key
        $idempKey = 'pay_tx_uuid_99999_xyz';
        self::$pdo->exec("INSERT INTO payment_transactions (player_payment_id, transaction_reference, idempotency_key, payment_method, amount, status) VALUES ({$payId}, 'TX_REF_001', '{$idempKey}', 'SIMULATED', 200000, 'SUCCESS')");

        // 2. Duplicate transaction with same idempotency key must be rejected by uq_payment_transactions_idempotency
        $this->expectException(PDOException::class);
        self::$pdo->exec("INSERT INTO payment_transactions (player_payment_id, transaction_reference, idempotency_key, payment_method, amount, status) VALUES ({$payId}, 'TX_REF_002', '{$idempKey}', 'SIMULATED', 200000, 'SUCCESS')");
    }

    public function testF02RefundIdempotencyKey(): void
    {
        self::$pdo->exec("INSERT INTO roles (name, label) VALUES ('PLAYER', 'Player')");
        $roleId = (int) self::$pdo->lastInsertId();

        self::$pdo->exec("INSERT INTO users (role_id, name, email, password_hash) VALUES ({$roleId}, 'RefUser', 'ref@example.com', 'h')");
        $uid = (int) self::$pdo->lastInsertId();
        self::$pdo->exec("INSERT INTO games (title, slug, min_players, max_players, duration_minutes) VALUES ('Game Ref', 'game-ref', 4, 6, 120)");
        $gid = (int) self::$pdo->lastInsertId();
        self::$pdo->exec("INSERT INTO game_rooms (creator_id, game_id, title, room_code, min_players, max_players, formation_deadline, status) VALUES ({$uid}, {$gid}, 'Room Ref', 'ROOM_REF', 4, 6, '2026-12-01 12:00:00', 'FORMING')");
        $roomId = (int) self::$pdo->lastInsertId();
        self::$pdo->exec("INSERT INTO room_participants (game_room_id, user_id, status) VALUES ({$roomId}, {$uid}, 'JOINED')");
        $partId = (int) self::$pdo->lastInsertId();
        self::$pdo->exec("INSERT INTO sessions (game_room_id, session_code, scheduled_date, start_time, end_time, status) VALUES ({$roomId}, 'SESS_REF', '2026-12-01', '14:00:00', '18:00:00', 'SCHEDULED')");
        $sessId = (int) self::$pdo->lastInsertId();

        self::$pdo->exec("INSERT INTO player_payments (session_id, room_participant_id, payment_type, amount_due, amount_paid, status) VALUES ({$sessId}, {$partId}, 'FULL', 200000, 200000, 'PAID')");
        $payId = (int) self::$pdo->lastInsertId();

        // 1. Refund with idempotency key
        $idempKey = 'ref_uuid_11111_abc';
        self::$pdo->exec("INSERT INTO refunds (player_payment_id, room_participant_id, idempotency_key, reason, refund_amount, status) VALUES ({$payId}, {$partId}, '{$idempKey}', 'Session cancelled', 200000, 'PENDING')");

        // 2. Duplicate refund with same idempotency key must fail
        $this->expectException(PDOException::class);
        self::$pdo->exec("INSERT INTO refunds (player_payment_id, room_participant_id, idempotency_key, reason, refund_amount, status) VALUES ({$payId}, {$partId}, '{$idempKey}', 'Duplicate request', 200000, 'PENDING')");
    }

    // =========================================================================
    // SECTION G: TRANSACTION ROLLBACK TESTS (ACID ATOMICITY)
    // =========================================================================

    public function testG01TransactionRollbackRestoresState(): void
    {
        self::$pdo->beginTransaction();

        try {
            self::$pdo->exec("INSERT INTO roles (name, label) VALUES ('TMP_ROLE', 'Temporary')");
            $rid = (int) self::$pdo->lastInsertId();

            self::$pdo->exec("INSERT INTO users (role_id, name, email, password_hash) VALUES ({$rid}, 'RollbackUser', 'rb@example.com', 'h')");

            // Trigger failure (violating NOT NULL constraint on roles.name)
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
     * Demonstrates Business Integrity Analysis: Physical Room Overlap in sessions.
     * MySQL schema only checks session reservation uniqueness, NOT interval overlap [start_time, end_time).
     * Application-level check (BookingRepository::isPhysicalRoomBooked) with transaction is mandatory!
     */
    public function testH01PhysicalRoomOverlapInSessionsDemonstration(): void
    {
        // Setup room, game, physical room
        self::$pdo->exec("INSERT INTO roles (name, label) VALUES ('PLAYER', 'Player')");
        $roleId = (int) self::$pdo->lastInsertId();

        self::$pdo->exec("INSERT INTO users (role_id, name, email, password_hash) VALUES ({$roleId}, 'CreatorH1', 'ch1@example.com', 'h')");
        $cid = (int) self::$pdo->lastInsertId();
        self::$pdo->exec("INSERT INTO games (title, slug, min_players, max_players, duration_minutes) VALUES ('Game H1', 'game-h1', 4, 6, 120)");
        $gid = (int) self::$pdo->lastInsertId();
        self::$pdo->exec("INSERT INTO game_rooms (creator_id, game_id, title, room_code, min_players, max_players, formation_deadline, status) VALUES ({$cid}, {$gid}, 'Room H1', 'ROOM_H1', 4, 6, '2026-12-01 12:00:00', 'FORMING')");
        $roomId = (int) self::$pdo->lastInsertId();

        self::$pdo->exec("INSERT INTO venues (name, code, address) VALUES ('Venue Main', 'VMAIN', '456 Le Loi')");
        $venueId = (int) self::$pdo->lastInsertId();
        self::$pdo->exec("INSERT INTO physical_rooms (venue_id, name, code, capacity) VALUES ({$venueId}, 'VIP Room 1', 'VIP1', 10)");
        $vipRoomId = (int) self::$pdo->lastInsertId();

        // Session A reserves VIP1 from 14:00 to 18:00
        self::$pdo->exec("INSERT INTO sessions (game_room_id, physical_room_id, session_code, scheduled_date, start_time, end_time, status) VALUES ({$roomId}, {$vipRoomId}, 'SESS_A', '2026-12-05', '14:00:00', '18:00:00', 'SCHEDULED')");

        // Session B reserves VIP1 from 16:00 to 20:00 (OVERLAP: 16:00 - 18:00)
        self::$pdo->exec("INSERT INTO sessions (game_room_id, physical_room_id, session_code, scheduled_date, start_time, end_time, status) VALUES ({$roomId}, {$vipRoomId}, 'SESS_B', '2026-12-05', '16:00:00', '20:00:00', 'SCHEDULED')");

        // BOTH rows exist in the DB!
        $count = (int) self::$pdo->query("SELECT COUNT(*) FROM sessions WHERE physical_room_id = {$vipRoomId} AND status = 'SCHEDULED'")->fetchColumn();
        $this->assertSame(2, $count, 'Schema allows temporal overlap. Application-level check (BookingRepository::isPhysicalRoomBooked) is MANDATORY to prevent double-booking!');
    }

    /**
     * Demonstrates Business Integrity Analysis: DM Concurrent Scheduling Conflict.
     * Schema has uq_active_dm_assignment on active_assigned_room_id (preventing >1 DM in same room),
     * but does NOT prevent 1 DM from being assigned to 2 different rooms with overlapping sessions!
     */
    public function testH02DmOverlappingAssignmentDemonstration(): void
    {
        self::$pdo->exec("INSERT INTO roles (name, label) VALUES ('PLAYER', 'Player'), ('DM', 'Dungeon Master')");
        $playerRole = (int) self::$pdo->query("SELECT id FROM roles WHERE name='PLAYER'")->fetchColumn();
        $dmRole = (int) self::$pdo->query("SELECT id FROM roles WHERE name='DM'")->fetchColumn();

        self::$pdo->exec("INSERT INTO users (role_id, name, email, password_hash) VALUES 
            ({$dmRole}, 'DM Busy', 'busy@example.com', 'h'), 
            ({$playerRole}, 'Creator 1', 'cr1@example.com', 'h'), 
            ({$playerRole}, 'Creator 2', 'cr2@example.com', 'h')");
        $dmUser = (int) self::$pdo->query("SELECT id FROM users WHERE email='busy@example.com'")->fetchColumn();
        $cr1 = (int) self::$pdo->query("SELECT id FROM users WHERE email='cr1@example.com'")->fetchColumn();
        $cr2 = (int) self::$pdo->query("SELECT id FROM users WHERE email='cr2@example.com'")->fetchColumn();

        self::$pdo->exec("INSERT INTO games (title, slug, min_players, max_players, duration_minutes) VALUES ('Game H2', 'game-h2', 4, 6, 120)");
        $gid = (int) self::$pdo->lastInsertId();

        self::$pdo->exec("INSERT INTO game_rooms (creator_id, game_id, title, room_code, min_players, max_players, formation_deadline, status) VALUES ({$cr1}, {$gid}, 'Room DM 1', 'ROOM_DM_1', 4, 6, '2026-12-01 12:00:00', 'FORMING')");
        $room1 = (int) self::$pdo->lastInsertId();
        self::$pdo->exec("INSERT INTO game_rooms (creator_id, game_id, title, room_code, min_players, max_players, formation_deadline, status) VALUES ({$cr2}, {$gid}, 'Room DM 2', 'ROOM_DM_2', 4, 6, '2026-12-01 12:00:00', 'FORMING')");
        $room2 = (int) self::$pdo->lastInsertId();

        // Assign same DM to Room 1 and Room 2 simultaneously
        self::$pdo->exec("INSERT INTO dm_assignments (game_room_id, dm_user_id, status) VALUES ({$room1}, {$dmUser}, 'ACCEPTED')");
        self::$pdo->exec("INSERT INTO dm_assignments (game_room_id, dm_user_id, status) VALUES ({$room2}, {$dmUser}, 'ACCEPTED')");

        $dmAssignedCount = (int) self::$pdo->query("SELECT COUNT(*) FROM dm_assignments WHERE dm_user_id = {$dmUser} AND status = 'ACCEPTED'")->fetchColumn();
        $this->assertSame(2, $dmAssignedCount, 'Schema allows DM assigned to multiple rooms. Application service layer must query schedule overlap before assigning DM!');
    }

    /**
     * Demonstrates Business Integrity Analysis: Payment amount_paid exceeding amount_due.
     */
    public function testH03OverpaymentProtectionConstraintScope(): void
    {
        self::$pdo->exec("INSERT INTO roles (name, label) VALUES ('PLAYER', 'Player')");
        $roleId = (int) self::$pdo->lastInsertId();

        self::$pdo->exec("INSERT INTO users (role_id, name, email, password_hash) VALUES ({$roleId}, 'Payer', 'payer@example.com', 'h')");
        $uid = (int) self::$pdo->lastInsertId();
        self::$pdo->exec("INSERT INTO games (title, slug, min_players, max_players, duration_minutes) VALUES ('Game P3', 'game-p3', 4, 6, 120)");
        $gid = (int) self::$pdo->lastInsertId();
        self::$pdo->exec("INSERT INTO game_rooms (creator_id, game_id, title, room_code, min_players, max_players, formation_deadline, status) VALUES ({$uid}, {$gid}, 'Room P3', 'ROOM_P3', 4, 6, '2026-12-01 12:00:00', 'FORMING')");
        $roomId = (int) self::$pdo->lastInsertId();
        self::$pdo->exec("INSERT INTO room_participants (game_room_id, user_id, status) VALUES ({$roomId}, {$uid}, 'JOINED')");
        $partId = (int) self::$pdo->lastInsertId();
        self::$pdo->exec("INSERT INTO sessions (game_room_id, session_code, scheduled_date, start_time, end_time, status) VALUES ({$roomId}, 'SESS_P3', '2026-12-01', '14:00:00', '18:00:00', 'SCHEDULED')");
        $sessId = (int) self::$pdo->lastInsertId();

        // chk_player_payments_amounts checks: amount_due >= 0 AND amount_paid >= 0.
        // It does NOT restrict amount_paid <= amount_due (to accommodate tips/overpayments).
        self::$pdo->exec("INSERT INTO player_payments (session_id, room_participant_id, payment_type, amount_due, amount_paid, status) VALUES ({$sessId}, {$partId}, 'FULL', 200000, 500000, 'PAID')");
        $paid = (int) self::$pdo->query("SELECT amount_paid FROM player_payments WHERE session_id = {$sessId}")->fetchColumn();
        $due = (int) self::$pdo->query("SELECT amount_due FROM player_payments WHERE session_id = {$sessId}")->fetchColumn();

        $this->assertGreaterThan($due, $paid, 'Schema does NOT enforce amount_paid <= amount_due (to accommodate tips/overpayments). Service layer must validate exact payment match!');
    }
}
