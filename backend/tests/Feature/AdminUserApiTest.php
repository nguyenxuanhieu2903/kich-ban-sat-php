<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Application;
use App\Core\Request;
use App\Infrastructure\Config\Config;
use PHPUnit\Framework\TestCase;

class AdminUserApiTest extends TestCase
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

    public function testNonAdminCannotListUsers(): void
    {
        // 1. Unauthenticated request
        $req1 = new Request('GET', '/api/v1/admin/users');
        $res1 = $this->app->dispatch($req1);
        $this->assertSame(401, $res1->getStatusCode());

        // 2. Authenticated as PLAYER (User 3)
        $req2 = new Request(
            method: 'GET',
            path: '/api/v1/admin/users',
            queryParams: [],
            bodyParams: [],
            headers: [
                'Authorization' => 'Bearer 3',
            ]
        );
        $res2 = $this->app->dispatch($req2);
        $this->assertSame(403, $res2->getStatusCode());
        $data2 = json_decode($res2->getContent(), true);
        $this->assertFalse($data2['success']);
        $this->assertSame('FORBIDDEN', $data2['error']['code']);
    }

    public function testAdminCanListUsersWithPaginationAndFilters(): void
    {
        $request = new Request(
            method: 'GET',
            path: '/api/v1/admin/users',
            queryParams: [
                'page' => 1,
                'per_page' => 5,
            ],
            bodyParams: [],
            headers: [
                'Authorization' => 'Bearer 1', // Admin
            ]
        );

        $response = $this->app->dispatch($request);
        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertIsArray($data['data']);
        $this->assertNotEmpty($data['data']);
        $this->assertNotNull($data['meta']['pagination']);
        $this->assertSame(1, $data['meta']['pagination']['page']);
        $this->assertSame(5, $data['meta']['pagination']['per_page']);
        $this->assertGreaterThanOrEqual(4, $data['meta']['pagination']['total']);
    }

    public function testAdminCanGetUserDetail(): void
    {
        $request = new Request(
            method: 'GET',
            path: '/api/v1/admin/users/1',
            queryParams: [],
            bodyParams: [],
            headers: [
                'Authorization' => 'Bearer 1',
            ]
        );

        $response = $this->app->dispatch($request);
        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame(1, (int) $data['data']['user']['id']);
        $this->assertSame('ADMIN', $data['data']['user']['role_code']);
        $this->assertArrayNotHasKey('password_hash', $data['data']['user']);
    }

    public function testAdminCanUpdateUser(): void
    {
        $request = new Request(
            method: 'PATCH',
            path: '/api/v1/admin/users/3',
            queryParams: [],
            bodyParams: [
                'phone' => '0987654321',
                'status' => 'ACTIVE',
            ],
            headers: [
                'Authorization' => 'Bearer 1',
            ]
        );

        $response = $this->app->dispatch($request);
        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame('0987654321', $data['data']['user']['phone']);
    }

    public function testAdminCannotSuspendSelf(): void
    {
        $request = new Request(
            method: 'PATCH',
            path: '/api/v1/admin/users/1',
            queryParams: [],
            bodyParams: [
                'status' => 'SUSPENDED',
            ],
            headers: [
                'Authorization' => 'Bearer 1',
            ]
        );

        $response = $this->app->dispatch($request);
        $this->assertSame(400, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertSame('CANNOT_SUSPEND_SELF', $data['error']['code']);
    }

    public function testAdminCanAssignRoleToUser(): void
    {
        // Gán role DM cho Player 7 (nguoichoi01)
        $request = new Request(
            method: 'PUT',
            path: '/api/v1/admin/users/7/role',
            queryParams: [],
            bodyParams: [
                'role_code' => 'DM',
            ],
            headers: [
                'Authorization' => 'Bearer 1',
            ]
        );

        $response = $this->app->dispatch($request);
        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame('DM', $data['data']['user']['role_code']);

        // Khôi phục lại role PLAYER cho Player 7
        $revert = new Request(
            method: 'PUT',
            path: '/api/v1/admin/users/7/role',
            queryParams: [],
            bodyParams: [
                'role_code' => 'PLAYER',
            ],
            headers: [
                'Authorization' => 'Bearer 1',
            ]
        );
        $this->app->dispatch($revert);
    }

    public function testAdminCannotDemoteSelf(): void
    {
        $request = new Request(
            method: 'PUT',
            path: '/api/v1/admin/users/1/role',
            queryParams: [],
            bodyParams: [
                'role_code' => 'PLAYER',
            ],
            headers: [
                'Authorization' => 'Bearer 1',
            ]
        );

        $response = $this->app->dispatch($request);
        $this->assertSame(400, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertSame('CANNOT_DEMOTE_SELF', $data['error']['code']);
    }

    public function testAdminCanViewRevenueReport(): void
    {
        $request = new Request(
            method: 'GET',
            path: '/api/v1/admin/reports/revenue',
            queryParams: [],
            bodyParams: [],
            headers: [
                'Authorization' => 'Bearer 1',
            ]
        );

        $response = $this->app->dispatch($request);
        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('revenue', $data['data']);
        $this->assertArrayHasKey('statistics', $data['data']);
        $this->assertArrayHasKey('total_collected', $data['data']['revenue']);
    }

    public function testAdminCanListPayments(): void
    {
        $request = new Request(
            method: 'GET',
            path: '/api/v1/admin/payments',
            queryParams: ['per_page' => '10'],
            bodyParams: [],
            headers: [
                'Authorization' => 'Bearer 1',
            ]
        );

        $response = $this->app->dispatch($request);
        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('pagination', $data['meta']);
    }
}
