<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Middleware\RateLimitMiddleware;
use App\Core\Request;
use App\Core\Response;
use PHPUnit\Framework\TestCase;

/**
 * Unit Test for RateLimitMiddleware (Phase D)
 */
class RateLimitMiddlewareTest extends TestCase
{
    protected string $testStorageDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testStorageDir = dirname(__DIR__, 2) . '/storage/rate_limits/test_' . bin2hex(random_bytes(3));
        if (!is_dir($this->testStorageDir)) {
            @mkdir($this->testStorageDir, 0775, true);
        }
    }

    protected function tearDown(): void
    {
        if (is_dir($this->testStorageDir)) {
            $files = glob($this->testStorageDir . '/*');
            foreach ($files as $file) {
                @unlink($file);
            }
            @rmdir($this->testStorageDir);
        }
        parent::tearDown();
    }

    public function testAllowsRequestsUnderLimitAndAppendsHeaders(): void
    {
        // Setup middleware with limit = 3
        $middleware = new RateLimitMiddleware(3, 60, $this->testStorageDir, [], true);

        $next = function (Request $req): Response {
            return Response::success(['ok' => true]);
        };

        $req = new Request('GET', '/api/v1/games', [], [], ['X-Forwarded-For' => '192.168.1.10']);

        // Request 1: remaining = 2
        $res1 = $middleware->handle($req, $next);
        $this->assertSame(200, $res1->getStatusCode());
        $this->assertSame('3', $res1->getHeader('X-RateLimit-Limit'));
        $this->assertSame('2', $res1->getHeader('X-RateLimit-Remaining'));

        // Request 2: remaining = 1
        $res2 = $middleware->handle($req, $next);
        $this->assertSame(200, $res2->getStatusCode());
        $this->assertSame('1', $res2->getHeader('X-RateLimit-Remaining'));

        // Request 3: remaining = 0
        $res3 = $middleware->handle($req, $next);
        $this->assertSame(200, $res3->getStatusCode());
        $this->assertSame('0', $res3->getHeader('X-RateLimit-Remaining'));
    }

    public function testBlocksRequestsExceedingLimitWith429(): void
    {
        $middleware = new RateLimitMiddleware(
            2,
            60,
            $this->testStorageDir,
            ['/auth/login' => ['limit' => 2, 'decay' => 60]],
            true
        );

        $next = function (Request $req): Response {
            return Response::success(['ok' => true]);
        };

        $req = new Request('POST', '/api/v1/auth/login', [], [], ['X-Forwarded-For' => '10.0.0.5']);

        // First 2 requests succeed
        $this->assertSame(200, $middleware->handle($req, $next)->getStatusCode());
        $this->assertSame(200, $middleware->handle($req, $next)->getStatusCode());

        // 3rd request is blocked with 429 Too Many Requests
        $resBlocked = $middleware->handle($req, $next);
        $this->assertSame(429, $resBlocked->getStatusCode());
        $this->assertSame('0', $resBlocked->getHeader('X-RateLimit-Remaining'));
        $this->assertNotNull($resBlocked->getHeader('Retry-After'));

        $body = json_decode($resBlocked->getContent(), true);
        $this->assertFalse($body['success']);
        $this->assertSame('TOO_MANY_REQUESTS', $body['error']['code']);
    }

    public function testIsolatesDifferentClientsByIp(): void
    {
        $middleware = new RateLimitMiddleware(1, 60, $this->testStorageDir, [], true);

        $next = function (Request $req): Response {
            return Response::success(['ok' => true]);
        };

        $reqA = new Request('GET', '/api/v1/games', [], [], ['X-Forwarded-For' => '1.1.1.1']);
        $reqB = new Request('GET', '/api/v1/games', [], [], ['X-Forwarded-For' => '2.2.2.2']);

        // Client A makes request -> remaining = 0
        $resA1 = $middleware->handle($reqA, $next);
        $this->assertSame(200, $resA1->getStatusCode());

        // Client A makes 2nd request -> 429 Blocked
        $resA2 = $middleware->handle($reqA, $next);
        $this->assertSame(429, $resA2->getStatusCode());

        // Client B is a different IP -> 200 OK (Not blocked!)
        $resB1 = $middleware->handle($reqB, $next);
        $this->assertSame(200, $resB1->getStatusCode());
    }
}
