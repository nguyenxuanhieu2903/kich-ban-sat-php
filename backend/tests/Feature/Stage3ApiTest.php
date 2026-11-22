<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Application;
use App\Core\Request;
use App\Infrastructure\Config\Config;
use App\Infrastructure\Database\DatabaseConnection;
use PHPUnit\Framework\TestCase;

class Stage3ApiTest extends TestCase
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

    public function testAdminAssignAndRevokeDmAndListAuditLogs(): void
    {
        $assignReq = new Request(
            method: 'POST',
            path: '/api/v1/admin/rooms/1/assign-dm',
            queryParams: [],
            bodyParams: ['dm_user_id' => 2],
            headers: ['Authorization' => 'Bearer 1']
        );
        $assignRes = $this->app->dispatch($assignReq);
        $this->assertSame(201, $assignRes->getStatusCode());
        $assignData = json_decode($assignRes->getContent(), true);
        $assignmentId = (int) $assignData['data']['assignment_id'];

        $revokeReq = new Request(
            method: 'POST',
            path: "/api/v1/dm/assignments/{$assignmentId}/revoke",
            queryParams: [],
            bodyParams: ['reason' => 'Đổi ca phân công cho DM khác'],
            headers: ['Authorization' => 'Bearer 1']
        );
        $revokeRes = $this->app->dispatch($revokeReq);
        $this->assertSame(200, $revokeRes->getStatusCode());

        $auditReq = new Request(
            method: 'GET',
            path: '/api/v1/admin/audit-logs',
            queryParams: ['page' => 1, 'per_page' => 10],
            bodyParams: [],
            headers: ['Authorization' => 'Bearer 1']
        );
        $auditRes = $this->app->dispatch($auditReq);
        $this->assertSame(200, $auditRes->getStatusCode());
        $auditData = json_decode($auditRes->getContent(), true);
        $this->assertTrue($auditData['success']);
        $this->assertNotEmpty($auditData['data']);
    }

    public function testStudentVerificationAndNotificationsReadAll(): void
    {
        $verifyReq = new Request(
            method: 'PUT',
            path: '/api/v1/admin/users/3/student-verification',
            queryParams: [],
            bodyParams: ['student_verified' => true],
            headers: ['Authorization' => 'Bearer 1']
        );
        $verifyRes = $this->app->dispatch($verifyReq);
        $this->assertSame(200, $verifyRes->getStatusCode());
        $this->assertTrue(json_decode($verifyRes->getContent(), true)['data']['student_verified']);

        $pdo = DatabaseConnection::getInstance();
        $user = $pdo->query('SELECT `student_verified` FROM `users` WHERE `id` = 3')->fetch();
        $this->assertSame(1, (int) $user['student_verified']);

        $readAllReq = new Request(
            method: 'POST',
            path: '/api/v1/notifications/read-all',
            queryParams: [],
            bodyParams: [],
            headers: ['Authorization' => 'Bearer 3']
        );
        $readAllRes = $this->app->dispatch($readAllReq);
        $this->assertSame(200, $readAllRes->getStatusCode());
    }
}