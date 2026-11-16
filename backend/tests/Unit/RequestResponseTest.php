<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Exceptions\BadRequestException;
use App\Core\Request;
use App\Core\Response;
use PHPUnit\Framework\TestCase;

class RequestResponseTest extends TestCase
{
    private array $savedServer;
    private array $savedGet;
    private array $savedPost;

    protected function setUp(): void
    {
        parent::setUp();
        $this->savedServer = $_SERVER;
        $this->savedGet = $_GET;
        $this->savedPost = $_POST;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->savedServer;
        $_GET = $this->savedGet;
        $_POST = $this->savedPost;
        parent::tearDown();
    }

    public function testRequestNormalizesMethodAndPath(): void
    {
        $request = new Request('post', '/api/test/');
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/api/test', $request->getPath());
    }

    public function testResponseGeneratesValidJson(): void
    {
        $response = Response::json(['name' => 'KBS V16', 'count' => 5], 200);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('application/json', $response->getHeaders()['Content-Type']);
        $this->assertSame('nosniff', $response->getHeaders()['X-Content-Type-Options']);

        $decoded = json_decode($response->getContent(), true);
        $this->assertSame(['name' => 'KBS V16', 'count' => 5], $decoded);
    }

    public function testResponseErrorFormatting(): void
    {
        $response = Response::error('Invalid input', 422, ['field' => 'Required'], 'VALIDATION_FAILED');

        $this->assertSame(422, $response->getStatusCode());
        $decoded = json_decode($response->getContent(), true);

        $this->assertFalse($decoded['success']);
        $this->assertSame('VALIDATION_FAILED', $decoded['error']['code']);
        $this->assertSame('Invalid input', $decoded['error']['message']);
        $this->assertSame(['field' => 'Required'], $decoded['error']['details']);
    }

    public function testParseJsonPayloadValidObject(): void
    {
        $payload = '{"title":"Mystery Game","maxPlayers":6}';
        $result = Request::parseJsonPayload($payload);

        $this->assertSame(['title' => 'Mystery Game', 'maxPlayers' => 6], $result);
    }

    public function testParseJsonPayloadValidArray(): void
    {
        $payload = '[{"id":1},{"id":2}]';
        $result = Request::parseJsonPayload($payload);

        $this->assertSame([['id' => 1], ['id' => 2]], $result);
    }

    public function testParseJsonPayloadEmptyBodyReturnsEmptyArray(): void
    {
        $this->assertSame([], Request::parseJsonPayload(''));
        $this->assertSame([], Request::parseJsonPayload('   '));
    }

    public function testParseJsonPayloadInvalidSyntaxThrowsBadRequestException(): void
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Malformed JSON in request payload');

        Request::parseJsonPayload('{"key": "unclosed');
    }

    public function testParseJsonPayloadScalarThrowsBadRequestException(): void
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Payload must be a JSON object or array');

        Request::parseJsonPayload('12345');
    }

    public function testCaptureParsesValidJsonBody(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/rooms';
        $_SERVER['CONTENT_TYPE'] = 'application/json; charset=utf-8';

        $rawJson = '{"roomName":"Sherlock Room","capacity":8}';
        $request = Request::capture($rawJson);

        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/api/rooms', $request->getPath());
        $this->assertSame('Sherlock Room', $request->input('roomName'));
        $this->assertSame(8, $request->input('capacity'));
        $this->assertSame($rawJson, $request->getRawBody());
    }

    public function testCaptureThrowsBadRequestExceptionOnMalformedJson(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/rooms';
        $_SERVER['CONTENT_TYPE'] = 'application/json';

        $this->expectException(BadRequestException::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('Malformed JSON in request payload');

        $malformedJson = '{"roomName": "Broken", }';
        Request::capture($malformedJson);
    }

    public function testCaptureIgnoresNonJsonContentType(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/upload';
        $_SERVER['CONTENT_TYPE'] = 'text/plain';
        $_POST = ['plainField' => 'textValue'];

        $request = Request::capture('raw unparsed text');

        $this->assertSame('textValue', $request->input('plainField'));
        $this->assertSame('raw unparsed text', $request->getRawBody());
    }
}
