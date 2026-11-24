<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Application;
use App\Core\Request;
use App\Infrastructure\Config\Config;
use PHPUnit\Framework\TestCase;

class PhaseCBusinessFlowTest extends TestCase
{
    protected Application $app;

    protected function setUp(): void
    {
        parent::setUp();
        $basePath = dirname(__DIR__, 2);
        Config::load($basePath);
        $this->app = new Application($basePath);

        $routesFile = $basePath . '/routes/api.php';
        $router = $this->app->getRouter();
        require $routesFile;
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $pdo = \App\Infrastructure\Database\DatabaseConnection::getInstance();
        $pdo->exec('DELETE FROM `game_prices` WHERE `game_id` >= 90');
        $pdo->exec('DELETE FROM `games` WHERE `id` >= 90');
        $pdo->exec('DELETE FROM `promotions` WHERE `code` LIKE "PROMO_%"');
        $pdo->exec('DELETE FROM `physical_rooms` WHERE `name` LIKE "Phòng Mật Đạo 09%"');
        $pdo->exec('DELETE FROM `venues` WHERE `name` LIKE "Chi Nhánh Test%"');
        parent::tearDown();
    }

    public function testProfileUpdateRequiresAuthentication(): void
    {
        $_SESSION = [];
        $req = new Request('PATCH', '/api/v1/auth/profile', [], ['full_name' => 'New Name']);
        $res = $this->app->dispatch($req);
        $this->assertSame(401, $res->getStatusCode());
    }

    public function testPlayerCanUpdateOwnProfile(): void
    {
        $_SESSION = ['user_id' => 3]; // Player user

        $req = new Request('PATCH', '/api/v1/auth/profile', [], [
            'full_name' => 'Player Updated Name',
            'phone' => '0987654321',
        ]);
        $res = $this->app->dispatch($req);
        $this->assertSame(200, $res->getStatusCode());

        $data = json_decode($res->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame('Player Updated Name', $data['data']['user']['name']);
        $this->assertSame('0987654321', $data['data']['user']['phone']);
    }

    public function testProfileUpdateValidationFailsOnEmptyName(): void
    {
        $_SESSION = ['user_id' => 3];

        $req = new Request('PATCH', '/api/v1/auth/profile', [], [
            'full_name' => '',
        ]);
        $res = $this->app->dispatch($req);
        $this->assertSame(422, $res->getStatusCode());
    }

    public function testMyPaymentsRequiresAuthentication(): void
    {
        $_SESSION = [];
        $req = new Request('GET', '/api/v1/payments/my');
        $res = $this->app->dispatch($req);
        $this->assertSame(401, $res->getStatusCode());
    }

    public function testPlayerCanFetchOwnPaymentsHistory(): void
    {
        $_SESSION = ['user_id' => 3];
        $req = new Request('GET', '/api/v1/payments/my');
        $res = $this->app->dispatch($req);
        $this->assertSame(200, $res->getStatusCode());

        $data = json_decode($res->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertIsArray($data['data']['payments']);
    }

    public function testPlayerCannotAccessAdminGameCrud(): void
    {
        $_SESSION = ['user_id' => 3]; // Player
        $req = new Request('GET', '/api/v1/admin/games');
        $res = $this->app->dispatch($req);
        $this->assertSame(403, $res->getStatusCode());

        $postReq = new Request('POST', '/api/v1/admin/games', [], ['name' => 'Forbidden Game']);
        $postRes = $this->app->dispatch($postReq);
        $this->assertSame(403, $postRes->getStatusCode());
    }

    public function testAdminCanManageGamesAndPrices(): void
    {
        $_SESSION = ['user_id' => 1]; // Admin

        // 1. List games
        $listReq = new Request('GET', '/api/v1/admin/games');
        $listRes = $this->app->dispatch($listReq);
        $this->assertSame(200, $listRes->getStatusCode());
        $listData = json_decode($listRes->getContent(), true);
        $this->assertTrue($listData['success']);
        $this->assertIsArray($listData['data']['games']);

        // 2. Create game
        $createReq = new Request('POST', '/api/v1/admin/games', [], [
            'name' => 'Test Murder Script V16 ' . bin2hex(random_bytes(4)),
            'min_players' => 5,
            'max_players' => 7,
            'duration_minutes' => 180,
            'price_per_person' => 150000,
        ]);
        $createRes = $this->app->dispatch($createReq);
        $this->assertSame(201, $createRes->getStatusCode());
        $created = json_decode($createRes->getContent(), true);
        $gameId = (string) $created['data']['game']['id'];

        // 3. Update game
        $updateReq = new Request('PATCH', "/api/v1/admin/games/{$gameId}", [], [
            'description' => 'Updated Description Test',
        ]);
        $updateRes = $this->app->dispatch($updateReq);
        $this->assertSame(200, $updateRes->getStatusCode());

        // 4. Add price tier
        $priceReq = new Request('POST', "/api/v1/admin/games/{$gameId}/prices", [], [
            'price_per_person' => 180000,
            'start_time' => '18:00:00',
            'end_time' => '22:00:00',
        ]);
        $priceRes = $this->app->dispatch($priceReq);
        $this->assertSame(201, $priceRes->getStatusCode());
        $priceData = json_decode($priceRes->getContent(), true);
        $priceId = (string) $priceData['data']['price_id'];

        // 5. Update price tier
        $updatePriceReq = new Request('PATCH', "/api/v1/admin/prices/{$priceId}", [], [
            'price_per_person' => 190000,
        ]);
        $this->assertSame(200, $this->app->dispatch($updatePriceReq)->getStatusCode());

        // 6. Delete price tier
        $delPriceReq = new Request('DELETE', "/api/v1/admin/prices/{$priceId}");
        $this->assertSame(200, $this->app->dispatch($delPriceReq)->getStatusCode());

        // 7. Deactivate game
        $delReq = new Request('DELETE', "/api/v1/admin/games/{$gameId}");
        $this->assertSame(200, $this->app->dispatch($delReq)->getStatusCode());
    }

    public function testAdminCanManagePromotions(): void
    {
        $_SESSION = ['user_id' => 1]; // Admin

        // 1. List promotions
        $listRes = $this->app->dispatch(new Request('GET', '/api/v1/admin/promotions'));
        $this->assertSame(200, $listRes->getStatusCode());

        // 2. Create promotion
        $code = 'PROMO_' . strtoupper(bin2hex(random_bytes(3)));
        $createReq = new Request('POST', '/api/v1/admin/promotions', [], [
            'code' => $code,
            'name' => 'Phase C Test Promotion',
            'discount_type' => 'FIXED_AMOUNT',
            'discount_value' => 20000,
        ]);
        $createRes = $this->app->dispatch($createReq);
        $this->assertSame(201, $createRes->getStatusCode());
        $promoId = (string) json_decode($createRes->getContent(), true)['data']['promotion_id'];

        // 3. Update promotion
        $updateReq = new Request('PATCH', "/api/v1/admin/promotions/{$promoId}", [], [
            'discount_value' => 25000,
        ]);
        $this->assertSame(200, $this->app->dispatch($updateReq)->getStatusCode());

        // 4. Deactivate promotion
        $delReq = new Request('DELETE', "/api/v1/admin/promotions/{$promoId}");
        $this->assertSame(200, $this->app->dispatch($delReq)->getStatusCode());
    }

    public function testAdminCanManageVenuesAndPhysicalRooms(): void
    {
        $_SESSION = ['user_id' => 1]; // Admin

        // 1. List venues
        $listRes = $this->app->dispatch(new Request('GET', '/api/v1/admin/venues'));
        $this->assertSame(200, $listRes->getStatusCode());

        // 2. Create venue
        $venueName = 'Chi Nhánh Test ' . bin2hex(random_bytes(3));
        $createReq = new Request('POST', '/api/v1/admin/venues', [], [
            'name' => $venueName,
            'address' => '999 Đường Test, Quận 10',
            'phone' => '02812345678',
        ]);
        $createRes = $this->app->dispatch($createReq);
        $this->assertSame(201, $createRes->getStatusCode());
        $venueId = (string) json_decode($createRes->getContent(), true)['data']['venue_id'];

        // 3. Update venue
        $updateReq = new Request('PATCH', "/api/v1/admin/venues/{$venueId}", [], [
            'description' => 'Mô tả chi nhánh mới',
        ]);
        $this->assertSame(200, $this->app->dispatch($updateReq)->getStatusCode());

        // 4. Add physical room to venue
        $roomReq = new Request('POST', "/api/v1/admin/venues/{$venueId}/rooms", [], [
            'name' => 'Phòng Mật Đạo 09',
            'capacity' => 8,
        ]);
        $roomRes = $this->app->dispatch($roomReq);
        $this->assertSame(201, $roomRes->getStatusCode());
        $roomId = (string) json_decode($roomRes->getContent(), true)['data']['physical_room_id'];

        // 5. Update physical room
        $upRoomReq = new Request('PATCH', "/api/v1/admin/physical-rooms/{$roomId}", [], [
            'capacity' => 10,
        ]);
        $this->assertSame(200, $this->app->dispatch($upRoomReq)->getStatusCode());

        // 6. Delete physical room
        $delRoomReq = new Request('DELETE', "/api/v1/admin/physical-rooms/{$roomId}");
        $this->assertSame(200, $this->app->dispatch($delRoomReq)->getStatusCode());

        // 7. Deactivate venue
        $delVenueReq = new Request('DELETE', "/api/v1/admin/venues/{$venueId}");
        $this->assertSame(200, $this->app->dispatch($delVenueReq)->getStatusCode());
    }

    public function testGameRoomsEndpointAliasesSupported(): void
    {
        // 1. GET /api/v1/rooms
        $res1 = $this->app->dispatch(new Request('GET', '/api/v1/rooms'));
        $this->assertSame(200, $res1->getStatusCode());
        $data1 = json_decode($res1->getContent(), true);
        $this->assertTrue($data1['success']);
        $this->assertArrayHasKey('rooms', $data1['data']);

        // 2. GET /api/v1/game-rooms (Alias)
        $res2 = $this->app->dispatch(new Request('GET', '/api/v1/game-rooms'));
        $this->assertSame(200, $res2->getStatusCode());
        $data2 = json_decode($res2->getContent(), true);
        $this->assertTrue($data2['success']);
        $this->assertArrayHasKey('rooms', $data2['data']);

        // 3. GET /api/v1/admin/game-rooms (Alias)
        $_SESSION = ['user_id' => 1, 'role' => 'ADMIN'];
        $res3 = $this->app->dispatch(new Request('GET', '/api/v1/admin/game-rooms'));
        $this->assertSame(200, $res3->getStatusCode());
        $data3 = json_decode($res3->getContent(), true);
        $this->assertTrue($data3['success']);
        $this->assertArrayHasKey('rooms', $data3['data']);

        // 4. GET /api/v1/admin/rooms (Alias)
        $res4 = $this->app->dispatch(new Request('GET', '/api/v1/admin/rooms'));
        $this->assertSame(200, $res4->getStatusCode());
    }
}
