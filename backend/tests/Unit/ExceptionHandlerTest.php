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

    public function testSanitizeApiKeyCamelCaseAndVariants(): void
    {
        // Plain key-value camelCase
        $input1 = 'apiKey=mySecretKey123';
        $sanitized1 = ExceptionHandler::sanitize($input1);
        $this->assertStringNotContainsString('mySecretKey123', $sanitized1);
        $this->assertStringContainsString('apiKey=***', $sanitized1);

        // Uppercase
        $input2 = 'APIKEY=mySecretKey123';
        $sanitized2 = ExceptionHandler::sanitize($input2);
        $this->assertStringNotContainsString('mySecretKey123', $sanitized2);
        $this->assertStringContainsString('APIKEY=***', $sanitized2);

        // JSON format
        $input3 = '{"apiKey": "mySecretKey123"}';
        $sanitized3 = ExceptionHandler::sanitize($input3);
        $this->assertStringNotContainsString('mySecretKey123', $sanitized3);
        $this->assertStringContainsString('{"apiKey": "***"}', $sanitized3);

        // Array dump
        $input4 = 'Array ( [apiKey] => mySecretKey123 )';
        $sanitized4 = ExceptionHandler::sanitize($input4);
        $this->assertStringNotContainsString('mySecretKey123', $sanitized4);
        $this->assertStringContainsString('[apiKey] => ***', $sanitized4);
    }

    public function testSanitizeClientSecret(): void
    {
        // Plain key-value
        $input1 = 'client_secret=topSecret456';
        $sanitized1 = ExceptionHandler::sanitize($input1);
        $this->assertStringNotContainsString('topSecret456', $sanitized1);
        $this->assertStringContainsString('client_secret=***', $sanitized1);

        // Uppercase
        $input2 = 'CLIENT_SECRET=topSecret456';
        $sanitized2 = ExceptionHandler::sanitize($input2);
        $this->assertStringNotContainsString('topSecret456', $sanitized2);
        $this->assertStringContainsString('CLIENT_SECRET=***', $sanitized2);

        // JSON quoted
        $input3 = '{"client_secret": "my-client-secret-val"}';
        $sanitized3 = ExceptionHandler::sanitize($input3);
        $this->assertStringNotContainsString('my-client-secret-val', $sanitized3);
        $this->assertStringContainsString('{"client_secret": "***"}', $sanitized3);

        // Array format with spaces
        $input4 = "['client_secret' => 'phrase with spaces and words']";
        $sanitized4 = ExceptionHandler::sanitize($input4);
        $this->assertStringNotContainsString('phrase with spaces and words', $sanitized4);
        $this->assertStringContainsString("['client_secret' => '***']", $sanitized4);
    }

    public function testSanitizeAuthToken(): void
    {
        // Plain key-value
        $input1 = 'auth=myAuthToken123';
        $sanitized1 = ExceptionHandler::sanitize($input1);
        $this->assertStringNotContainsString('myAuthToken123', $sanitized1);
        $this->assertStringContainsString('auth=***', $sanitized1);

        // Uppercase
        $input2 = 'AUTH=myAuthToken123';
        $sanitized2 = ExceptionHandler::sanitize($input2);
        $this->assertStringNotContainsString('myAuthToken123', $sanitized2);
        $this->assertStringContainsString('AUTH=***', $sanitized2);

        // Colon separator
        $input3 = 'auth: myAuthToken123';
        $sanitized3 = ExceptionHandler::sanitize($input3);
        $this->assertStringNotContainsString('myAuthToken123', $sanitized3);
        $this->assertStringContainsString('auth: ***', $sanitized3);

        // Quoted in array
        $input4 = "'auth' => 'my-auth-secret'";
        $sanitized4 = ExceptionHandler::sanitize($input4);
        $this->assertStringNotContainsString('my-auth-secret', $sanitized4);
        $this->assertStringContainsString("'auth' => '***'", $sanitized4);
    }

    public function testSanitizeNewKeysInUrlQueryParameters(): void
    {
        // apiKey in query
        $input1 = 'https://api.test/cb?apiKey=secret123';
        $sanitized1 = ExceptionHandler::sanitize($input1);
        $this->assertStringNotContainsString('secret123', $sanitized1);
        $this->assertStringContainsString('?apiKey=***', $sanitized1);

        // client_secret in query
        $input2 = 'https://api.test/cb?client_secret=mySecretVal456';
        $sanitized2 = ExceptionHandler::sanitize($input2);
        $this->assertStringNotContainsString('mySecretVal456', $sanitized2);
        $this->assertStringContainsString('?client_secret=***', $sanitized2);

        // auth in query
        $input3 = 'https://api.test/cb?auth=myAuthToken123';
        $sanitized3 = ExceptionHandler::sanitize($input3);
        $this->assertStringNotContainsString('myAuthToken123', $sanitized3);
        $this->assertStringContainsString('?auth=***', $sanitized3);

        // Multiple query params mixed with non-sensitive params
        $input4 = 'https://api.test/cb?apiKey=sec1&client_secret=sec2&auth=sec3&other=safe';
        $sanitized4 = ExceptionHandler::sanitize($input4);
        $this->assertStringNotContainsString('sec1', $sanitized4);
        $this->assertStringNotContainsString('sec2', $sanitized4);
        $this->assertStringNotContainsString('sec3', $sanitized4);
        $this->assertStringContainsString('?apiKey=***&client_secret=***&auth=***&other=safe', $sanitized4);
    }

    public function testPreservesNonSensitiveProseWithAuthAndKeyWords(): void
    {
        $input1 = 'User authentication status is active';
        $this->assertSame($input1, ExceptionHandler::sanitize($input1));

        $input2 = 'Author of the script is John';
        $this->assertSame($input2, ExceptionHandler::sanitize($input2));

        $input3 = 'Authority node responded with code 200';
        $this->assertSame($input3, ExceptionHandler::sanitize($input3));

        $input4 = 'Keyboard layout is standard';
        $this->assertSame($input4, ExceptionHandler::sanitize($input4));
    }

    public function testDbAuthMessagesPreserveStructureWhileRedactingTokens(): void
    {
        $input1 = 'Failed DB auth: password=MySecret123';
        $sanitized1 = ExceptionHandler::sanitize($input1);
        $this->assertStringNotContainsString('MySecret123', $sanitized1);
        $this->assertSame('Failed DB auth: password=***', $sanitized1);

        $input2 = 'Failed DB auth: access_token=MyToken123';
        $sanitized2 = ExceptionHandler::sanitize($input2);
        $this->assertStringNotContainsString('MyToken123', $sanitized2);
        $this->assertSame('Failed DB auth: access_token=***', $sanitized2);
    }

    public function testChainedExceptionsWithNewKeys(): void
    {
        $innerSecret = 'InnerApiKeySecret999';
        $outerSecret = 'OuterClientSecret888';

        $innerException = new RuntimeException("Inner error with apiKey={$innerSecret}");
        $outerException = new RuntimeException("Outer error with client_secret={$outerSecret}", 500, $innerException);

        Config::set('APP_DEBUG', false);
        $response = ExceptionHandler::handle($outerException);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertCount(1, $this->capturedLogs);
        $log = $this->capturedLogs[0];
        $this->assertStringNotContainsString($innerSecret, $log);
        $this->assertStringNotContainsString($outerSecret, $log);
        $this->assertStringContainsString('Caused by:', $log);
        $this->assertStringContainsString('apiKey=***', $log);
        $this->assertStringContainsString('client_secret=***', $log);
    }
}
