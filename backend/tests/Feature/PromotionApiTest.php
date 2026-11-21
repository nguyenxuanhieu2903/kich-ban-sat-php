<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Application;
use App\Core\Request;
use App\Infrastructure\Config\Config;
use PHPUnit\Framework\TestCase;

class PromotionApiTest extends TestCase
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

    public function testCanListActivePromotions(): void
    {
        $request = new Request('GET', '/api/v1/promotions');
        $response = $this->app->dispatch($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertNotEmpty($data['data']['promotions']);
        $this->assertGreaterThanOrEqual(2, count($data['data']['promotions']));

        $codes = array_column($data['data']['promotions'], 'code');
        $this->assertContains('TUESDAY_30', $codes);
        $this->assertContains('HSSV_20000', $codes);

        // Verify rules JSON is parsed as an array
        $first = $data['data']['promotions'][0];
        $this->assertIsArray($first['rules']);
    }

    public function testLegacyPrefixAlsoWorks(): void
    {
        $request = new Request('GET', '/api/promotions');
        $response = $this->app->dispatch($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
    }
}
