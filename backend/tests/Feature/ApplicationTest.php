<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Application;
use App\Core\Exceptions\BadRequestException;
use App\Core\Request;
use App\Core\Response;
use App\Infrastructure\Config\Config;
use PHPUnit\Framework\TestCase;

class ApplicationTest extends TestCase
{
    private Application $app;
    private array $capturedLogs = [];

    protected function setUp(): void
    {
        parent::setUp();
        Config::reset();
        Config::set('APP_DEBUG', false);
        $this->capturedLogs = [];
        \App\Core\Exceptions\ExceptionHandler::setLogger(function (string $message) {
            $this->capturedLogs[] = $message;
        });
        $this->app = require __DIR__ . '/../../bootstrap/app.php';
    }

    protected function tearDown(): void
    {
        \App\Core\Exceptions\ExceptionHandler::setLogger(null);
        Config::reset();
        parent::tearDown();
    }

    public function testHealthEndpointReturnsSuccessJson(): void
    {
        $request = new Request('GET', '/api/health');
        $response = $this->app->dispatch($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);

        $this->assertTrue($body['success']);
        $this->assertSame('ok', $body['data']['status']);
        $this->assertSame('Kich Ban Sat', $body['data']['application']);
        $this->assertSame('16', $body['data']['version']);
    }

    public function testUnknownRouteReturns404Json(): void
    {
        $request = new Request('GET', '/api/unknown-endpoint');
        $response = $this->app->dispatch($request);

        $this->assertSame(404, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);

        $this->assertFalse($body['success']);
        $this->assertSame('NOT_FOUND', $body['error']['code']);
    }

    public function testUnsupportedMethodReturns405WithAllowHeader(): void
    {
        $request = new Request('POST', '/api/health');
        $response = $this->app->dispatch($request);

        $this->assertSame(405, $response->getStatusCode());
        $headers = $response->getHeaders();
        $this->assertArrayHasKey('Allow', $headers);
        $this->assertSame('GET', $headers['Allow']);

        $body = json_decode($response->getContent(), true);
        $this->assertFalse($body['success']);
        $this->assertSame('METHOD_NOT_ALLOWED', $body['error']['code']);
    }

    public function testExceptionHandlerHandlesCustomBadRequest(): void
    {
        $this->app->getRouter()->post('/api/test-bad-request', function () {
            throw new BadRequestException('Invalid payload provided', ['field' => 'email is required']);
        });

        $request = new Request('POST', '/api/test-bad-request');
        $response = $this->app->dispatch($request);

        $this->assertSame(400, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);

        $this->assertFalse($body['success']);
        $this->assertSame('BAD_REQUEST', $body['error']['code']);
        $this->assertSame('Invalid payload provided', $body['error']['message']);
        $this->assertSame(['field' => 'email is required'], $body['error']['details']);
    }

    public function testUncaughtExceptionInProductionHidesDetails(): void
    {
        Config::set('APP_DEBUG', false);

        $this->app->getRouter()->get('/api/server-error', function () {
            throw new \RuntimeException('Database password secret revealed');
        });

        $request = new Request('GET', '/api/server-error');
        $response = $this->app->dispatch($request);

        $this->assertSame(500, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);

        $this->assertFalse($body['success']);
        $this->assertSame('INTERNAL_SERVER_ERROR', $body['error']['code']);
        $this->assertSame('Internal Server Error', $body['error']['message']);
        $this->assertArrayNotHasKey('details', $body['error']);
        $this->assertStringNotContainsString('password', $response->getContent());

        // Verify captured log output is sanitized and did not leak secret
        $this->assertNotEmpty($this->capturedLogs);
        $this->assertStringNotContainsString('Database password secret revealed', $this->capturedLogs[0]);
    }
}
