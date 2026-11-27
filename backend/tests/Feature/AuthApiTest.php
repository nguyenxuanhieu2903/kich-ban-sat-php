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
                'email' => 'nguyenxuanhieu@gmail.com',
                'password' => 'password123',
            ]
        );

        $response = $this->app->dispatch($request);
        $this->assertSame(409, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
    }

    public function testForgotAndResetPasswordFlow(): void
    {
        $email = 'forgot_' . uniqid() . '@test.vn';
        $phone = '0908' . random_int(100000, 999999);

        $regRes = $this->app->dispatch(new Request('POST', '/api/v1/auth/register', [], [
            'full_name' => 'Forgot Password Tester',
            'email' => $email,
            'password' => 'oldpass123',
            'phone' => $phone,
        ]));
        $this->assertSame(201, $regRes->getStatusCode());

        // Sai SĐT -> 400
        $wrongPhoneRes = $this->app->dispatch(new Request('POST', '/api/v1/auth/forgot-password', [], [
            'email' => $email,
            'phone' => '0999999999',
        ]));
        $this->assertSame(400, $wrongPhoneRes->getStatusCode());

        // Đúng Email + SĐT -> 200 kèm OTP 6 chữ số
        $fpRes = $this->app->dispatch(new Request('POST', '/api/v1/auth/forgot-password', [], [
            'email' => $email,
            'phone' => $phone,
        ]));
        $this->assertSame(200, $fpRes->getStatusCode());
        $fpData = json_decode($fpRes->getContent(), true);
        $otp = (string) ($fpData['data']['demo_otp'] ?? '');
        $this->assertSame(6, strlen($otp));

        // Sai OTP -> 422
        $badOtpRes = $this->app->dispatch(new Request('POST', '/api/v1/auth/reset-password', [], [
            'email' => $email,
            'otp' => '000000',
            'new_password' => 'newpass456',
            'confirm_password' => 'newpass456',
        ]));
        $this->assertSame(422, $badOtpRes->getStatusCode());

        // Đúng OTP -> 200 & mật khẩu cũ bị từ chối (401), mật khẩu mới đăng nhập thành công (200)
        $resetRes = $this->app->dispatch(new Request('POST', '/api/v1/auth/reset-password', [], [
            'email' => $email,
            'otp' => $otp,
            'new_password' => 'newpass456',
            'confirm_password' => 'newpass456',
        ]));
        $this->assertSame(200, $resetRes->getStatusCode());

        $oldLogin = $this->app->dispatch(new Request('POST', '/api/v1/auth/login', [], [
            'email' => $email,
            'password' => 'oldpass123',
        ]));
        $this->assertSame(401, $oldLogin->getStatusCode());

        $newLogin = $this->app->dispatch(new Request('POST', '/api/v1/auth/login', [], [
            'email' => $email,
            'password' => 'newpass456',
        ]));
        $this->assertSame(200, $newLogin->getStatusCode());
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

    public function testCanGetProfileViaBearerHeader(): void
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

        // Clear session
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
        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame('Bearer User', $data['data']['user']['full_name']);
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

    public function testCanUpdateProfile(): void
    {
        $email = 'profile_' . uniqid() . '@test.vn';
        $regRequest = new Request('POST', '/api/auth/register', [], [
            'full_name' => 'Profile Original',
            'email' => $email,
            'password' => 'password123',
        ]);
        $regResponse = $this->app->dispatch($regRequest);
        $userId = json_decode($regResponse->getContent(), true)['data']['user']['id'];

        $updateRequest = new Request(
            method: 'PATCH',
            path: '/api/v1/auth/profile',
            queryParams: [],
            bodyParams: ['full_name' => 'Profile Updated', 'phone' => '0988776655'],
            headers: ['Authorization' => "Bearer {$userId}"]
        );

        $response = $this->app->dispatch($updateRequest);
        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame('Profile Updated', $data['data']['user']['full_name']);
        $this->assertSame('0988776655', $data['data']['user']['phone']);
    }

    public function testCanRequestStudentVerification(): void
    {
        $email = 'student_' . uniqid() . '@test.vn';
        $regRequest = new Request('POST', '/api/auth/register', [], [
            'full_name' => 'Student User',
            'email' => $email,
            'password' => 'password123',
        ]);
        $regResponse = $this->app->dispatch($regRequest);
        $userId = json_decode($regResponse->getContent(), true)['data']['user']['id'];

        $req = new Request(
            method: 'POST',
            path: '/api/v1/auth/student-verification',
            queryParams: [],
            bodyParams: ['student_id' => 'SV20261199', 'school_name' => 'Đại học Bách Khoa'],
            headers: ['Authorization' => "Bearer {$userId}"]
        );

        $response = $this->app->dispatch($req);
        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame('PENDING_APPROVAL', $data['data']['status']);
    }
}
