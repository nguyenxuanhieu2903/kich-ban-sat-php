<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Application;
use App\Core\Middleware\AuthMiddleware;
use App\Core\Middleware\CsrfMiddleware;
use App\Core\Request;
use App\Core\Response;
use App\Infrastructure\Config\Config;
use PHPUnit\Framework\TestCase;

class SecurityMiddlewareTest extends TestCase
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

    public function testCanRetrieveCsrfToken(): void
    {
        $request = new Request('GET', '/api/v1/auth/csrf-token');
        $response = $this->app->dispatch($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertNotEmpty($data['data']['csrf_token']);
        $this->assertSame($_SESSION['_csrf_token'], $data['data']['csrf_token']);
    }

    public function testCsrfMiddlewareBlocksStateChangeWithoutValidTokenInProduction(): void
    {
        // Add CsrfMiddleware to pipeline
        $app = new Application(dirname(__DIR__, 2));
        $app->addMiddleware(new CsrfMiddleware());

        $app->getRouter()->post('/api/v1/test-mutation', function (Request $req) {
            return Response::success(['ok' => true]);
        });

        // Simulating non-testing request without header/token
        Config::set('APP_ENV', 'production');

        $request = new Request('POST', '/api/v1/test-mutation');
        $response = $app->dispatch($request);

        $this->assertSame(403, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertSame('CSRF_TOKEN_INVALID', $data['error']['code']);

        // Reset config
        Config::set('APP_ENV', 'testing');
    }

    public function testCsrfMiddlewareAllowsValidToken(): void
    {
        $app = new Application(dirname(__DIR__, 2));
        $app->addMiddleware(new CsrfMiddleware());

        $app->getRouter()->post('/api/v1/test-mutation', function (Request $req) {
            return Response::success(['ok' => true]);
        });

        Config::set('APP_ENV', 'production');
        $validToken = CsrfMiddleware::getToken();

        $request = new Request(
            method: 'POST',
            path: '/api/v1/test-mutation',
            queryParams: [],
            bodyParams: [],
            headers: [
                'X-CSRF-Token' => $validToken,
            ]
        );
        $response = $app->dispatch($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);

        Config::set('APP_ENV', 'testing');
    }

    public function testAuthMiddlewareRejectsUnauthorizedAccess(): void
    {
        $req = new Request('GET', '/api/v1/protected');
        $res = AuthMiddleware::authenticate($req);

        $this->assertInstanceOf(Response::class, $res);
        $this->assertSame(401, $res->getStatusCode());

        $data = json_decode($res->getContent(), true);
        $this->assertSame('UNAUTHORIZED', $data['error']['code']);
    }

    public function testAuthMiddlewareRejectsForbiddenRole(): void
    {
        // User 3 is PLAYER
        $_SESSION['user_id'] = 3;

        $req = new Request('GET', '/api/v1/admin-only');
        $res = AuthMiddleware::authenticate($req, ['ADMIN']);

        $this->assertInstanceOf(Response::class, $res);
        $this->assertSame(403, $res->getStatusCode());

        $data = json_decode($res->getContent(), true);
        $this->assertSame('FORBIDDEN', $data['error']['code']);
    }

    public function testAuthMiddlewareAllowsAuthorizedRole(): void
    {
        // User 1 is ADMIN
        $_SESSION['user_id'] = 1;

        $req = new Request('GET', '/api/v1/admin-only');
        $res = AuthMiddleware::authenticate($req, ['ADMIN']);

        $this->assertIsArray($res);
        $this->assertSame(1, (int) $res['id']);
        $this->assertSame('ADMIN', strtoupper((string) $res['role_code']));
    }
}
