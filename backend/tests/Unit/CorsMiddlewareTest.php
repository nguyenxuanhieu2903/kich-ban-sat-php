<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Middleware\CorsMiddleware;
use App\Core\Request;
use App\Core\Response;
use PHPUnit\Framework\TestCase;

class CorsMiddlewareTest extends TestCase
{
    public function testExplicitAllowedOriginSetsCorsHeaders(): void
    {
        $middleware = new CorsMiddleware(['http://127.0.0.1:3000', 'http://localhost:3000'], true);
        $request = new Request('GET', '/api/health', [], [], ['Origin' => 'http://127.0.0.1:3000']);

        $response = $middleware->handle($request, function (Request $req) {
            return Response::json(['status' => 'ok']);
        });

        $headers = $response->getHeaders();
        $this->assertSame('http://127.0.0.1:3000', $headers['Access-Control-Allow-Origin']);
        $this->assertSame('true', $headers['Access-Control-Allow-Credentials']);
        $this->assertSame('Origin', $headers['Vary']);
    }

    public function testUnauthorizedOriginReceivesNoCorsAccess(): void
    {
        $middleware = new CorsMiddleware(['http://127.0.0.1:3000'], true);
        $request = new Request('GET', '/api/health', [], [], ['Origin' => 'http://evil-attacker.com']);

        $response = $middleware->handle($request, function (Request $req) {
            return Response::json(['status' => 'ok']);
        });

        $headers = $response->getHeaders();
        $this->assertArrayNotHasKey('Access-Control-Allow-Origin', $headers);
        $this->assertArrayNotHasKey('Access-Control-Allow-Credentials', $headers);
        // Vary: Origin must still be present to prevent HTTP cache poisoning
        $this->assertSame('Origin', $headers['Vary']);
    }

    public function testRequestWithoutOriginHeaderReceivesVaryOriginForCacheSafety(): void
    {
        $middleware = new CorsMiddleware(['http://127.0.0.1:3000'], true);
        $request = new Request('GET', '/api/health', [], [], []); // No Origin header

        $response = $middleware->handle($request, function (Request $req) {
            return Response::json(['status' => 'ok']);
        });

        $headers = $response->getHeaders();
        $this->assertArrayNotHasKey('Access-Control-Allow-Origin', $headers);
        $this->assertArrayNotHasKey('Access-Control-Allow-Credentials', $headers);
        // RFC 7234: Cache safety requires Vary: Origin even when Origin is absent
        $this->assertSame('Origin', $headers['Vary']);
    }

    public function testMultipleAllowedOriginsVaryCorrectlyAcrossRequests(): void
    {
        $middleware = new CorsMiddleware(['http://127.0.0.1:3000', 'http://localhost:3000'], true);

        // Request 1 from first allowed origin
        $req1 = new Request('GET', '/api/rooms', [], [], ['Origin' => 'http://127.0.0.1:3000']);
        $res1 = $middleware->handle($req1, fn() => Response::json([]));
        $this->assertSame('http://127.0.0.1:3000', $res1->getHeaders()['Access-Control-Allow-Origin']);
        $this->assertSame('Origin', $res1->getHeaders()['Vary']);

        // Request 2 from second allowed origin
        $req2 = new Request('GET', '/api/rooms', [], [], ['Origin' => 'http://localhost:3000']);
        $res2 = $middleware->handle($req2, fn() => Response::json([]));
        $this->assertSame('http://localhost:3000', $res2->getHeaders()['Access-Control-Allow-Origin']);
        $this->assertSame('Origin', $res2->getHeaders()['Vary']);

        // Request 3 from disallowed origin
        $req3 = new Request('GET', '/api/rooms', [], [], ['Origin' => 'http://unauthorized.org']);
        $res3 = $middleware->handle($req3, fn() => Response::json([]));
        $this->assertArrayNotHasKey('Access-Control-Allow-Origin', $res3->getHeaders());
        $this->assertSame('Origin', $res3->getHeaders()['Vary']);
    }

    public function testWildcardOriginWithoutCredentialsReturnsLiteralAsterisk(): void
    {
        $middleware = new CorsMiddleware(['*'], false);
        $request = new Request('GET', '/api/public', [], [], ['Origin' => 'http://some-client.org']);

        $response = $middleware->handle($request, function (Request $req) {
            return Response::json(['public' => true]);
        });

        $headers = $response->getHeaders();
        // Must return literal '*', never reflecting arbitrary incoming origin
        $this->assertSame('*', $headers['Access-Control-Allow-Origin']);
        $this->assertArrayNotHasKey('Access-Control-Allow-Credentials', $headers);
    }

    public function testWildcardWithCredentialsFailsSafelyWithoutAllowingCredentials(): void
    {
        // Even if config attempts to enable credentials with wildcard '*', middleware must fail safe
        $middleware = new CorsMiddleware(['*'], true);
        $request = new Request('GET', '/api/data', [], [], ['Origin' => 'http://untrusted-site.com']);

        $response = $middleware->handle($request, function (Request $req) {
            return Response::json(['data' => 'test']);
        });

        $headers = $response->getHeaders();
        $this->assertSame('*', $headers['Access-Control-Allow-Origin']);
        // Crucial security invariant: credentials must NEVER be asserted alongside wildcard
        $this->assertArrayNotHasKey('Access-Control-Allow-Credentials', $headers);
    }

    public function testOptionsPreflightReturns204WithAllowedHeaders(): void
    {
        $middleware = new CorsMiddleware(['http://127.0.0.1:3000'], true);
        $request = new Request('OPTIONS', '/api/rooms', [], [], [
            'Origin' => 'http://127.0.0.1:3000',
            'Access-Control-Request-Method' => 'POST',
        ]);

        $response = $middleware->handle($request, function () {
            $this->fail('Pipeline should terminate on preflight OPTIONS');
        });

        $this->assertSame(204, $response->getStatusCode());
        $headers = $response->getHeaders();
        $this->assertSame('http://127.0.0.1:3000', $headers['Access-Control-Allow-Origin']);
        $this->assertSame('true', $headers['Access-Control-Allow-Credentials']);
        $this->assertArrayHasKey('Access-Control-Allow-Methods', $headers);
        $this->assertSame('Origin', $headers['Vary']);
    }

    public function testPreservesExistingVaryHeaderValues(): void
    {
        $middleware = new CorsMiddleware(['http://127.0.0.1:3000'], true);
        $request = new Request('GET', '/api/compressed', [], [], ['Origin' => 'http://127.0.0.1:3000']);

        $response = $middleware->handle($request, function (Request $req) {
            return new Response('compressed content', 200, ['Vary' => 'Accept-Encoding']);
        });

        $headers = $response->getHeaders();
        $this->assertSame('Accept-Encoding, Origin', $headers['Vary']);
    }

    public function testDoesNotDuplicateVaryOriginIfAlreadyPresent(): void
    {
        $middleware = new CorsMiddleware(['http://127.0.0.1:3000'], true);
        $request = new Request('GET', '/api/test', [], [], ['Origin' => 'http://127.0.0.1:3000']);

        $response = $middleware->handle($request, function (Request $req) {
            return new Response('body', 200, ['Vary' => 'Origin']);
        });

        $headers = $response->getHeaders();
        $this->assertSame('Origin', $headers['Vary']);
    }
}
