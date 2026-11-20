<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Application;
use App\Core\Request;
use App\Infrastructure\Config\Config;
use App\Infrastructure\Database\DatabaseConnection;
use PHPUnit\Framework\TestCase;

class PricingCalculationApiTest extends TestCase
{
    protected Application $app;

    protected function setUp(): void
    {
        parent::setUp();
        $basePath = dirname(__DIR__, 2);
        Config::load($basePath);
        $this->app = new Application($basePath);
        $_SESSION = [];

        $routesFile = $basePath . '/routes/api.php';
        $router = $this->app->getRouter();
        require $routesFile;
    }

    public function testCanCalculateRoomPriceWithFixedDepositAndTimeSlot(): void
    {
        // Tính giá cho Room 1 vào khung giờ ban ngày (14:00)
        $request = new Request(
            method: 'POST',
            path: '/api/v1/rooms/1/calculate-price',
            queryParams: [],
            bodyParams: [
                'play_time' => '2026-11-25 14:00:00', // Thứ 4, 14h chiều
            ]
        );

        $response = $this->app->dispatch($request);
        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $calc = $data['data'];

        // Cọc phải đúng 50.000 VNĐ / người
        $this->assertSame(50000, $calc['deposit_per_person']);
        $this->assertGreaterThan(0, $calc['total_participants']);
        $this->assertSame(50000 * $calc['total_participants'], $calc['deposit_total']);
        $this->assertSame($calc['subtotal'] - $calc['discount_amount'], $calc['final_amount']);
        $this->assertSame($calc['final_amount'] - $calc['deposit_total'], $calc['remaining_total']);
    }

    public function testTuesdayPromotionOnlyAppliesOnTuesday(): void
    {
        // 1. Ngày Thứ Ba: 2026-11-24
        $tueReq = new Request(
            method: 'POST',
            path: '/api/v1/rooms/1/calculate-price',
            queryParams: [],
            bodyParams: [
                'play_time' => '2026-11-24 15:00:00', // Thứ Ba
                'promotion_code' => 'TUESDAY_30',
            ]
        );
        $tueRes = $this->app->dispatch($tueReq);
        $this->assertSame(200, $tueRes->getStatusCode());
        $tueData = json_decode($tueRes->getContent(), true)['data'];

        // Phải được giảm giá > 0 và có applied_promotion
        $this->assertNotNull($tueData['applied_promotion']);
        $this->assertGreaterThan(0, $tueData['discount_amount']);

        // 2. Ngày Thứ Tư: 2026-11-25 (không áp dụng được TUESDAY_30)
        $wedReq = new Request(
            method: 'POST',
            path: '/api/v1/rooms/1/calculate-price',
            queryParams: [],
            bodyParams: [
                'play_time' => '2026-11-25 15:00:00', // Thứ Tư
                'promotion_code' => 'TUESDAY_30',
            ]
        );
        $wedRes = $this->app->dispatch($wedReq);
        $this->assertSame(200, $wedRes->getStatusCode());
        $wedData = json_decode($wedRes->getContent(), true)['data'];

        // Không được giảm giá
        $this->assertNull($wedData['applied_promotion']);
        $this->assertSame(0, $wedData['discount_amount']);
    }

    public function testCanGetPriceSnapshotAfterCalculation(): void
    {
        // Gọi calculate trước
        $calcReq = new Request('POST', '/api/v1/rooms/1/calculate-price', [], ['play_time' => '2026-11-24 15:00:00']);
        $this->app->dispatch($calcReq);

        // Lấy snapshot
        $snapReq = new Request('GET', '/api/v1/rooms/1/price-snapshot');
        $snapRes = $this->app->dispatch($snapReq);
        $this->assertSame(200, $snapRes->getStatusCode());

        $snapData = json_decode($snapRes->getContent(), true);
        $this->assertTrue($snapData['success']);
        $this->assertSame(1, (int) $snapData['data']['snapshot']['id']);
        $this->assertNotNull($snapData['data']['snapshot']['price_calculated_at']);
        $this->assertNotEmpty($snapData['data']['snapshot']['price_details']);
    }
}
