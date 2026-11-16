<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Exceptions\BadRequestException;
use App\Core\Exceptions\ExceptionHandler;
use App\Core\Exceptions\NotFoundException;
use App\Core\Request;
use App\Infrastructure\Config\Config;
use App\Infrastructure\Database\DatabaseException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ExceptionHandlerTest extends TestCase
{
    private array $capturedLogs = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->capturedLogs = [];
        ExceptionHandler::setLogger(function (string $message) {
            $this->capturedLogs[] = $message;
        });
    }

    protected function tearDown(): void
    {
        ExceptionHandler::setLogger(null);
        Config::reset();
        parent::tearDown();
    }

    public function testSanitizeRedactsPasswordInVariousFormats(): void
    {
        $input1 = 'Failed connecting with password=MySecretPass123! to host';
        $sanitized1 = ExceptionHandler::sanitize($input1);
        $this->assertStringNotContainsString('MySecretPass123!', $sanitized1);
        $this->assertStringContainsString('password=***', $sanitized1);

        $input2 = '{"user": "admin", "password": "SuperSecretPassword!", "token": "xyz789"}';
        $sanitized2 = ExceptionHandler::sanitize($input2);
        $this->assertStringNotContainsString('SuperSecretPassword!', $sanitized2);
        $this->assertStringNotContainsString('xyz789', $sanitized2);
        $this->assertStringContainsString('"password": "***"', $sanitized2);
        $this->assertStringContainsString('"token": "***"', $sanitized2);

        $input3 = 'Connection error on mysql://root:SuperSecret123@127.0.0.1:3306/db';
        $sanitized3 = ExceptionHandler::sanitize($input3);
        $this->assertStringNotContainsString('SuperSecret123', $sanitized3);
        $this->assertStringContainsString('mysql://root:***@127.0.0.1:3306/db', $sanitized3);
    }

    public function testLogErrorRedactsSecretsFromMessageAndTrace(): void
    {
        $sensitiveSecret = 'TopSecretTokenValue456';
        $sensitivePass = 'MyDbSecretPass999!';

        $exception = new RuntimeException("DB error with secret={$sensitiveSecret} and password={$sensitivePass}");

        Config::set('APP_DEBUG', false);
        $response = ExceptionHandler::handle($exception);

        // Client response in production must be generic
        $this->assertSame(500, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertFalse($body['success']);
        $this->assertSame('Internal Server Error', $body['error']['message']);
        $this->assertStringNotContainsString($sensitiveSecret, $response->getContent());
        $this->assertStringNotContainsString($sensitivePass, $response->getContent());

        // Captured log must exist and be sanitized
        $this->assertCount(1, $this->capturedLogs);
        $log = $this->capturedLogs[0];
        $this->assertStringNotContainsString($sensitiveSecret, $log);
        $this->assertStringNotContainsString($sensitivePass, $log);
        $this->assertStringContainsString('secret=***', $log);
        $this->assertStringContainsString('password=***', $log);
        $this->assertStringContainsString('RuntimeException', $log);
    }

    public function testDebugModeSanitizesClientFacingMessage(): void
    {
        $sensitivePassword = 'UnrevealedPassSecret123!';
        $exception = new DatabaseException("Failed connection for password={$sensitivePassword}");

        Config::set('APP_DEBUG', true);
        $response = ExceptionHandler::handle($exception);

        $this->assertSame(500, $response->getStatusCode());
        $content = $response->getContent();
        $this->assertStringNotContainsString($sensitivePassword, $content);
        $this->assertStringContainsString('password=***', $content);
    }

    public function testHandlesHttpExceptionsWithoutLogging(): void
    {
        $httpException = new NotFoundException('Resource was not found');
        $response = ExceptionHandler::handle($httpException);

        $this->assertSame(404, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertSame('NOT_FOUND', $body['error']['code']);
        // Client-side 4xx exceptions do not trigger server error logging
        $this->assertEmpty($this->capturedLogs);
    }
}
