<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Application;
use App\Core\Request;
use App\Infrastructure\Config\Config;
use PHPUnit\Framework\TestCase;

class AuthApiTest extends TestCase
{
    protected Application $app;

    protected function setUp(): void
    {
        parent::setUp();
        $basePath = dirname(__DIR__, 2);
        Config::load($basePath);
        $this->app = new Application($basePath);

        // Clear session between tests
        $_SESSION = [];

        $routesFile = $basePath . '/routes/api.php';
        $router = $this->app->getRouter();
        require $routesFile;
    }

    public function testCanRegisterNewUser(): void
    {
        $uniqueEmail = 'player_' . uniqid() . '@test.vn';
        $request = new Request(
            method: 'POST',
            path: '/api/auth/register',
            queryParams: [],
            bodyParams: [
                'full_name' => 'Nguyễn Văn Test',
                'email' => $uniqueEmail,
                'password' => 'password123',
                'phone' => '0912345678',
            ]
        );

        $response = $this->app->dispatch($request);
        $this->assertSame(201, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame('Nguyễn Văn Test', $data['data']['user']['full_name']);
        $this->assertSame($uniqueEmail, $data['data']['user']['email']);
        $this->assertArrayNotHasKey('password_hash', $data['data']['user']);
    }

    public function testCannotRegisterWithDuplicateEmail(): void
    {
        $request = new Request(
            method: 'POST',
            path: '/api/auth/register',
            queryParams: [],
            bodyParams: [
                'full_name' => 'Trùng Email',
                'email' => 'admin@kichbansat.vn',
                'password' => 'password123',
            ]
        );

        $response = $this->app->dispatch($request);
        $this->assertSame(409, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
    }

    public function testCanLoginWithRegisteredUser(): void
    {
        // First register
        $email = 'login_user_' . uniqid() . '@test.vn';
        $regRequest = new Request(
            method: 'POST',
            path: '/api/auth/register',
            queryParams: [],
            bodyParams: [
                'full_name' => 'Login Tester',
                'email' => $email,
                'password' => 'mysecret123',
            ]
        );
        $this->app->dispatch($regRequest);

        // Clear session to simulate fresh login
        $_SESSION = [];

        // Now login
        $loginRequest = new Request(
            method: 'POST',
            path: '/api/auth/login',
            queryParams: [],
            bodyParams: [
                'email' => $email,
                'password' => 'mysecret123',
            ]
        );

        $response = $this->app->dispatch($loginRequest);
        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame($email, $data['data']['user']['email']);
        $this->assertSame('Login Tester', $data['data']['user']['full_name']);
        $this->assertArrayNotHasKey('password_hash', $data['data']['user']);
    }

    public function testCannotLoginWithWrongPassword(): void
    {
        $request = new Request(
            method: 'POST',
            path: '/api/auth/login',
            queryParams: [],
            bodyParams: [
                'email' => 'admin@kichbansat.vn',
                'password' => 'definitely_wrong_password',
            ]
        );

        $response = $this->app->dispatch($request);
        $this->assertSame(401, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
    }

    public function testCannotGetProfileViaBearerHeaderWhenSessionIsMissing(): void
    {
        // Register a dedicated user
        $email = 'bearer_user_' . uniqid() . '@test.vn';
        $regRequest = new Request(
            method: 'POST',
            path: '/api/auth/register',
            queryParams: [],
            bodyParams: [
                'full_name' => 'Bearer User',
                'email' => $email,
                'password' => 'pass123456',
            ]
        );
        $regResponse = $this->app->dispatch($regRequest);
        $regData = json_decode($regResponse->getContent(), true);
        $userId = $regData['data']['user']['id'];

        // Clear session: client-supplied Bearer User ID must NOT authenticate
        $_SESSION = [];

        $request = new Request(
            method: 'GET',
            path: '/api/auth/me',
            queryParams: [],
            bodyParams: [],
            headers: [
                'Authorization' => "Bearer {$userId}",
            ]
        );

        $response = $this->app->dispatch($request);
        $this->assertSame(401, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertSame('UNAUTHORIZED', $data['error']['code']);
    }

    public function testCanGetProfileViaSession(): void
    {
        // Register a dedicated user
        $email = 'session_user_' . uniqid() . '@test.vn';
        $regRequest = new Request(
            method: 'POST',
            path: '/api/auth/register',
            queryParams: [],
            bodyParams: [
                'full_name' => 'Session User',
                'email' => $email,
                'password' => 'pass123456',
            ]
        );
        $regResponse = $this->app->dispatch($regRequest);
        $regData = json_decode($regResponse->getContent(), true);
        $userId = $regData['data']['user']['id'];

        // Set valid server-side session
        $_SESSION['user_id'] = $userId;

        $request = new Request(
            method: 'GET',
            path: '/api/auth/me'
        );

        $response = $this->app->dispatch($request);
        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame('Session User', $data['data']['user']['full_name']);
        $this->assertSame($email, $data['data']['user']['email']);
    }

    public function testCanLogout(): void
    {
        $_SESSION['user_id'] = 1;

        $request = new Request(
            method: 'POST',
            path: '/api/auth/logout'
        );

        $response = $this->app->dispatch($request);
        $this->assertSame(200, $response->getStatusCode());

        $this->assertArrayNotHasKey('user_id', $_SESSION);
    }
}
