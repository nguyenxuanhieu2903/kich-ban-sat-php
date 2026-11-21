<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Application;
use App\Core\Request;
use App\Infrastructure\Config\Config;
use PHPUnit\Framework\TestCase;

class GameApiTest extends TestCase
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

    public function testCanListActiveGames(): void
    {
        $request = new Request('GET', '/api/games');
        $response = $this->app->dispatch($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertNotEmpty($data['data']['games']);
        $this->assertGreaterThanOrEqual(1, count($data['data']['games']));
    }

    public function testCanFilterGamesByPlayerCount(): void
    {
        $request = new Request('GET', '/api/games', ['players' => 6]);
        $response = $this->app->dispatch($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        foreach ($data['data']['games'] as $g) {
            $this->assertLessThanOrEqual(6, (int) $g['min_players']);
            $this->assertGreaterThanOrEqual(6, (int) $g['max_players']);
        }
    }

    public function testCanSearchGamesByName(): void
    {
        $request = new Request('GET', '/api/games', ['search' => 'Tuyết']);
        $response = $this->app->dispatch($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertNotEmpty($data['data']['games']);
        $this->assertStringContainsString('Tuyết', $data['data']['games'][0]['name']);
        $this->assertNotNull($data['data']['games'][0]['min_price']);
    }

    public function testCanGetGameDetailById(): void
    {
        $request = new Request('GET', '/api/games/1');
        $response = $this->app->dispatch($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertSame('Đêm Đoạt Mệnh Tại Trang Viên', $data['data']['game']['name']);
        $this->assertNotEmpty($data['data']['game']['prices']);
    }

    public function testCanGetGameDetailBySlug(): void
    {
        $request = new Request('GET', '/api/games/dem-doat-menh-tai-trang-vien');
        $response = $this->app->dispatch($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertSame(1, (int) $data['data']['game']['id']);
    }

    public function testCanGetGamePrices(): void
    {
        $request = new Request('GET', '/api/games/1/prices');
        $response = $this->app->dispatch($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertSame(1, (int) $data['data']['game_id']);
        $this->assertNotEmpty($data['data']['prices']);
    }

    public function testNonExistentGameReturns404(): void
    {
        $request = new Request('GET', '/api/games/999999');
        $response = $this->app->dispatch($request);

        $this->assertSame(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
    }
}
