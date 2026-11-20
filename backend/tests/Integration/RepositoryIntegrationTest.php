<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Features\Booking\Repositories\BookingRepository;
use App\Features\DMManagement\Repositories\DMRepository;
use App\Features\GameCatalog\Repositories\GameRepository;
use App\Features\GameroomManagement\Repositories\GameRoomRepository;
use App\Features\Identity\Repositories\UserRepository;
use App\Features\Notification\Repositories\NotificationRepository;
use App\Features\PaymentRefund\Repositories\PaymentRepository;
use App\Features\Pricing\Repositories\PricingRepository;
use App\Features\Promotion\Repositories\PromotionRepository;
use App\Features\VenueScheduling\Repositories\SchedulingRepository;
use App\Infrastructure\Config\Config;
use App\Infrastructure\Database\ConnectionFactory;
use App\Infrastructure\Database\DatabaseConnection;
use PDO;
use PHPUnit\Framework\TestCase;

class RepositoryIntegrationTest extends TestCase
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
        $pass = Config::get('DB_PASSWORD', '');

        self::$pdo = new PDO("mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        DatabaseConnection::setInstance(self::$pdo);
    }

    protected function tearDown(): void
    {
        // Clean test data after tests
        self::$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        self::$pdo->exec("DELETE FROM `users` WHERE `email` LIKE '%@test-repo.local'");
        self::$pdo->exec("DELETE FROM `games` WHERE `slug` LIKE 'test-repo-%'");
        self::$pdo->exec("DELETE FROM `promotions` WHERE `code` LIKE 'TEST_PROMO_%'");
        self::$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        parent::tearDown();
    }

    public function testUserRepositoryCanCreateAndFindUser(): void
    {
        $repo = new UserRepository(self::$pdo);
        $email = 'repo_user_' . uniqid() . '@test-repo.local';

        $userId = $repo->create([
            'name' => 'Test User',
            'email' => $email,
            'password_hash' => password_hash('SecretPassword123!', PASSWORD_DEFAULT),
            'phone' => '0987654321',
        ]);

        $this->assertGreaterThan(0, $userId);

        $found = $repo->findById($userId);
        $this->assertNotNull($found);
        $this->assertSame($email, $found['email']);
        $this->assertSame('Test User', $found['name']);
        $this->assertSame('ACTIVE', $found['status']);

        $byEmail = $repo->findByEmail($email);
        $this->assertNotNull($byEmail);
        $this->assertSame($userId, (int) $byEmail['id']);
    }

    public function testGameRepositoryCanFindGame(): void
    {
        $repo = new GameRepository(self::$pdo);
        $slug = 'test-repo-game-' . uniqid();

        $stmt = self::$pdo->prepare(
            'INSERT INTO `games` (`title`, `slug`, `description`, `min_players`, `max_players`, `duration_minutes`, `is_active`)
             VALUES (:title, :slug, :desc, 4, 8, 240, 1)'
        );
        $stmt->execute([
            ':title' => 'Test Repo Game',
            ':slug' => $slug,
            ':desc' => 'Test Description',
        ]);
        $gameId = (int) self::$pdo->lastInsertId();

        $found = $repo->findById($gameId);
        $this->assertNotNull($found);
        $this->assertSame($slug, $found['slug']);

        $bySlug = $repo->findBySlug($slug);
        $this->assertNotNull($bySlug);
        $this->assertSame($gameId, (int) $bySlug['id']);

        $activeGames = $repo->listActive(50, 0);
        $this->assertNotEmpty($activeGames);
    }

    public function testPromotionRepositoryCanFindPromotion(): void
    {
        $repo = new PromotionRepository(self::$pdo);
        $code = 'TEST_PROMO_' . strtoupper(uniqid());

        $stmt = self::$pdo->prepare(
            'INSERT INTO `promotions` (`code`, `name`, `discount_type`, `discount_value`, `is_active`, `valid_from`, `valid_to`)
             VALUES (:code, :name, :type, :val, 1, NOW() - INTERVAL 1 DAY, NOW() + INTERVAL 7 DAY)'
        );
        $stmt->execute([
            ':code' => $code,
            ':name' => 'Special Test Discount',
            ':type' => 'FIXED_AMOUNT',
            ':val' => 20000,
        ]);
        $promoId = (int) self::$pdo->lastInsertId();

        $found = $repo->findByCode($code);
        $this->assertNotNull($found);
        $this->assertSame($code, $found['code']);
        $this->assertSame(20000, (int) $found['discount_value']);

        $byId = $repo->findById($promoId);
        $this->assertNotNull($byId);
        $this->assertSame($code, $byId['code']);
    }

    public function testBookingRepositoryOverlapDetection(): void
    {
        $repo = new BookingRepository(self::$pdo);

        $isBooked = $repo->isPhysicalRoomBooked(999999, '2026-11-20', '09:00:00', '12:00:00');
        $this->assertFalse($isBooked);
    }

    public function testNotificationRepositoryCanCreateAndFetchUnread(): void
    {
        $userRepo = new UserRepository(self::$pdo);
        $userId = $userRepo->create([
            'name' => 'Notification Recipient',
            'email' => 'noti_' . uniqid() . '@test-repo.local',
            'password_hash' => password_hash('Pass123!', PASSWORD_DEFAULT),
        ]);

        $repo = new NotificationRepository(self::$pdo);
        $notiId = $repo->createNotification(
            title: 'Welcome to Kịch Bản Sát',
            message: 'Your account has been verified successfully.',
            notificationType: 'ROOM_EVENT',
            senderId: null,
            recipientUserIds: [$userId]
        );

        $this->assertGreaterThan(0, $notiId);

        $unread = $repo->getUnreadByUserId($userId);
        $this->assertNotEmpty($unread);
        $this->assertSame('Welcome to Kịch Bản Sát', $unread[0]['title']);
    }
}
