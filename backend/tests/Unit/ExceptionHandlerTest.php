<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Exceptions\BadRequestException;
use App\Core\Exceptions\ExceptionHandler;
use App\Core\Exceptions\NotFoundException;
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

    public function testSanitizePasswordEqualsValue(): void
    {
        $input = 'Failed connecting with password=MySecretPass123! to host 127.0.0.1';
        $sanitized = ExceptionHandler::sanitize($input);
        $this->assertStringNotContainsString('MySecretPass123!', $sanitized);
        $this->assertStringContainsString('password=***', $sanitized);
    }

    public function testSanitizePasswordFatCommaInArrayDumps(): void
    {
        // PHP array dump format
        $input1 = "Array ( [password] => MySecret123 )";
        $sanitized1 = ExceptionHandler::sanitize($input1);
        $this->assertStringNotContainsString('MySecret123', $sanitized1);
        $this->assertStringContainsString('[password] => ***', $sanitized1);

        // Quoted array format
        $input2 = "['password' => 'secret_pass_456']";
        $sanitized2 = ExceptionHandler::sanitize($input2);
        $this->assertStringNotContainsString('secret_pass_456', $sanitized2);
        $this->assertStringContainsString("['password' => '***']", $sanitized2);
    }

    public function testSanitizeTokenInUrlQuery(): void
    {
        $input = 'GET https://api.example.com/oauth/callback?token=eyJhbGciOiJIUzI1NiJ9.secretToken&user=admin';
        $sanitized = ExceptionHandler::sanitize($input);
        $this->assertStringNotContainsString('eyJhbGciOiJIUzI1NiJ9.secretToken', $sanitized);
        $this->assertStringContainsString('?token=***&user=admin', $sanitized);

        $input2 = '/api/reset-password?userId=42&api_key=SuperSecretApiKey999';
        $sanitized2 = ExceptionHandler::sanitize($input2);
        $this->assertStringNotContainsString('SuperSecretApiKey999', $sanitized2);
        $this->assertStringContainsString('&api_key=***', $sanitized2);
    }

    public function testSanitizeSecretWithSpaces(): void
    {
        // JSON format with spaces
        $input1 = '{"user": "admin", "password": "my secret phrase with spaces"}';
        $sanitized1 = ExceptionHandler::sanitize($input1);
        $this->assertStringNotContainsString('my secret phrase with spaces', $sanitized1);
        $this->assertStringContainsString('"password": "***"', $sanitized1);

        // Single-quoted format with spaces
        $input2 = "'secret' => 'phrase with spaces and words'";
        $sanitized2 = ExceptionHandler::sanitize($input2);
        $this->assertStringNotContainsString('phrase with spaces and words', $sanitized2);
        $this->assertStringContainsString("'secret' => '***'", $sanitized2);
    }

    public function testSanitizeSecretWithSpecialCharacters(): void
    {
        $input = 'Failed DB auth: password=P@$$w0rd!#%^&*()_+-=[]{};:,.<>?/ for user root';
        $sanitized = ExceptionHandler::sanitize($input);
        $this->assertStringNotContainsString('P@$$w0rd!#%^&*', $sanitized);
        $this->assertStringContainsString('password=***', $sanitized);
    }

    public function testSanitizeAuthorizationBearerToken(): void
    {
        $input = 'HTTP Request failed with header Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.e30.signature';
        $sanitized = ExceptionHandler::sanitize($input);
        $this->assertStringNotContainsString('eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.e30.signature', $sanitized);
        $this->assertStringContainsString('Authorization: Bearer ***', $sanitized);

        $inputBasic = 'Authorization: Basic dXNlcjpwYXNzd29yZA==';
        $sanitizedBasic = ExceptionHandler::sanitize($inputBasic);
        $this->assertStringNotContainsString('dXNlcjpwYXNzd29yZA==', $sanitizedBasic);
        $this->assertStringContainsString('Authorization: Basic ***', $sanitizedBasic);
    }

    public function testSanitizeUriDsnCredentials(): void
    {
        $input = 'PDO connection to mysql://admin:SecretDbPassword42@127.0.0.1:3306/booking_v16 failed';
        $sanitized = ExceptionHandler::sanitize($input);
        $this->assertStringNotContainsString('SecretDbPassword42', $sanitized);
        $this->assertStringContainsString('mysql://admin:***@127.0.0.1:3306/booking_v16', $sanitized);
    }

    public function testNonSensitiveExceptionPreservesDiagnosticDetails(): void
    {
        $input = "Table 'booking_v16.game_rooms' does not exist in schema";
        $sanitized = ExceptionHandler::sanitize($input);
        $this->assertSame($input, $sanitized);
    }

    public function testLogErrorSanitizesMessageTraceAndChainedExceptions(): void
    {
        $innerSecret = 'InnerSecretKey999';
        $outerSecret = 'OuterDbPassword888';

        $innerException = new RuntimeException("Inner error with secret={$innerSecret}");
        $outerException = new RuntimeException("Outer error with password={$outerSecret}", 500, $innerException);

        Config::set('APP_DEBUG', false);
        $response = ExceptionHandler::handle($outerException);

        // Production client response must be generic
        $this->assertSame(500, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertFalse($body['success']);
        $this->assertSame('Internal Server Error', $body['error']['message']);
        $this->assertStringNotContainsString($innerSecret, $response->getContent());
        $this->assertStringNotContainsString($outerSecret, $response->getContent());

        // Captured log must redact secrets across both outer and chained inner exceptions
        $this->assertCount(1, $this->capturedLogs);
        $log = $this->capturedLogs[0];
        $this->assertStringNotContainsString($innerSecret, $log);
        $this->assertStringNotContainsString($outerSecret, $log);
        $this->assertStringContainsString('Caused by:', $log);
        $this->assertStringContainsString('secret=***', $log);
        $this->assertStringContainsString('password=***', $log);
    }

    public function testDebugModeSanitizesClientFacingMessageWithoutExposingInternalTraces(): void
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
