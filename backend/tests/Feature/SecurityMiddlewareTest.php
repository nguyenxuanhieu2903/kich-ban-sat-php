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

    /**
     * SEC-01 Test 1: Forged Bearer ID 1 cannot authenticate as Admin.
     */
    public function testForgedBearerAdminIdCannotAuthenticateAsAdmin(): void
    {
        $_SESSION = [];

        $request = new Request(
            method: 'GET',
            path: '/api/v1/admin/users',
            queryParams: [],
            bodyParams: [],
            headers: [
                'Authorization' => 'Bearer 1',
            ]
        );

        $response = $this->app->dispatch($request);
        $this->assertSame(401, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertSame('UNAUTHORIZED', $data['error']['code']);
    }

    /**
     * SEC-01 Test 2: Forged Bearer ID 2 cannot authenticate as Player/DM.
     */
    public function testForgedBearerPlayerIdCannotAuthenticateAsPlayer(): void
    {
        $_SESSION = [];

        $request = new Request(
            method: 'GET',
            path: '/api/v1/auth/me',
            queryParams: [],
            bodyParams: [],
            headers: [
                'Authorization' => 'Bearer 2',
            ]
        );

        $response = $this->app->dispatch($request);
        $this->assertSame(401, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertSame('UNAUTHORIZED', $data['error']['code']);
    }

    /**
     * SEC-01 Test 3: Arbitrary Bearer IDs are rejected.
     */
    public function testArbitraryBearerIdsAreRejected(): void
    {
        $_SESSION = [];

        $arbitraryIds = [999999, 88888, 12345, 0, -1];
        foreach ($arbitraryIds as $id) {
            $request = new Request(
                method: 'GET',
                path: '/api/v1/auth/me',
                queryParams: [],
                bodyParams: [],
                headers: [
                    'Authorization' => "Bearer {$id}",
                ]
            );

            $response = $this->app->dispatch($request);
            $this->assertSame(401, $response->getStatusCode(), "Bearer {$id} must be rejected with 401");

            $data = json_decode($response->getContent(), true);
            $this->assertFalse($data['success']);
            $this->assertSame('UNAUTHORIZED', $data['error']['code']);
        }
    }

    /**
     * SEC-01 Test 4: Malformed Authorization headers are rejected or ignored safely.
     */
    public function testMalformedAuthorizationHeadersAreRejectedOrIgnoredSafely(): void
    {
        $_SESSION = [];

        $malformedHeaders = [
            'Bearer',
            'Bearer   ',
            'Bearer abc',
            'Bearer 1; DROP TABLE users;',
            'Basic YWRtaW46cGFzc3dvcmQ=',
            'Token 12345',
            'InvalidFormat',
        ];

        // Without session: must be rejected with 401 safely
        foreach ($malformedHeaders as $headerVal) {
            $request = new Request(
                method: 'GET',
                path: '/api/v1/auth/me',
                queryParams: [],
                bodyParams: [],
                headers: [
                    'Authorization' => $headerVal,
                ]
            );

            $response = $this->app->dispatch($request);
            $this->assertSame(401, $response->getStatusCode(), "Malformed header '{$headerVal}' must return 401 without session");

            $data = json_decode($response->getContent(), true);
            $this->assertFalse($data['success']);
            $this->assertSame('UNAUTHORIZED', $data['error']['code']);
        }

        // With valid session (User 3 = Player): malformed header must be ignored safely without crash
        $_SESSION['user_id'] = 3;
        foreach ($malformedHeaders as $headerVal) {
            $request = new Request(
                method: 'GET',
                path: '/api/v1/auth/me',
                queryParams: [],
                bodyParams: [],
                headers: [
                    'Authorization' => $headerVal,
                ]
            );

            $response = $this->app->dispatch($request);
            $this->assertSame(200, $response->getStatusCode(), "Valid session must remain authenticated despite malformed header '{$headerVal}'");

            $data = json_decode($response->getContent(), true);
            $this->assertTrue($data['success']);
            $this->assertSame(3, (int) $data['data']['user']['id']);
        }
    }

    /**
     * SEC-01 Test 5: Missing Session is rejected on protected endpoints.
     */
    public function testMissingSessionIsRejectedOnProtectedEndpoints(): void
    {
        $_SESSION = [];

        $protectedRoutes = [
            ['GET', '/api/v1/auth/me'],
            ['POST', '/api/v1/rooms', ['game_id' => 1, 'title' => 'Test Room']],
            ['GET', '/api/v1/admin/users'],
            ['GET', '/api/v1/dm/assignments'],
            ['GET', '/api/v1/notifications'],
        ];

        foreach ($protectedRoutes as $route) {
            [$method, $path] = $route;
            $body = $route[2] ?? [];

            $request = new Request($method, $path, [], $body);
            $response = $this->app->dispatch($request);

            $this->assertSame(401, $response->getStatusCode(), "Missing session on {$method} {$path} must return 401");
            $data = json_decode($response->getContent(), true);
            $this->assertFalse($data['success']);
            $this->assertSame('UNAUTHORIZED', $data['error']['code']);
        }
    }

    /**
     * SEC-01 Test 6: Valid Session remains authenticated and prevents User ID spoofing.
     */
    public function testValidSessionRemainsAuthenticatedAndPreventsUserIdSpoofing(): void
    {
        // User 3 is PLAYER
        $_SESSION['user_id'] = 3;

        // Player tries to spoof Admin by injecting "Bearer 1"
        $request = new Request(
            method: 'GET',
            path: '/api/v1/auth/me',
            queryParams: [],
            bodyParams: [],
            headers: [
                'Authorization' => 'Bearer 1',
            ]
        );

        $response = $this->app->dispatch($request);
        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        // Authenticated user MUST be User 3 from session, NOT User 1 from header!
        $this->assertSame(3, (int) $data['data']['user']['id']);
        $this->assertSame('PLAYER', strtoupper((string) $data['data']['user']['role_code']));

        // Player sending "Bearer 1" to Admin-only route must still get 403 FORBIDDEN
        $adminRequest = new Request(
            method: 'GET',
            path: '/api/v1/admin/users',
            queryParams: [],
            bodyParams: [],
            headers: [
                'Authorization' => 'Bearer 1',
            ]
        );

        $adminResponse = $this->app->dispatch($adminRequest);
        $this->assertSame(403, $adminResponse->getStatusCode(), 'Player cannot escalate privileges via spoofed Bearer header');
    }

    /**
     * SEC-01 Test 7: Logout invalidates the Session.
     */
    public function testLogoutInvalidatesSession(): void
    {
        $_SESSION['user_id'] = 3;

        $logoutReq = new Request('POST', '/api/v1/auth/logout');
        $logoutRes = $this->app->dispatch($logoutReq);

        $this->assertSame(200, $logoutRes->getStatusCode());
        $this->assertArrayNotHasKey('user_id', $_SESSION);

        // Subsequent access to protected route must be rejected
        $meReq = new Request('GET', '/api/v1/auth/me');
        $meRes = $this->app->dispatch($meReq);
        $this->assertSame(401, $meRes->getStatusCode());
    }

    /**
     * SEC-01 Test 8: Public endpoints remain accessible to Guests.
     */
    public function testPublicEndpointsRemainAccessibleToGuests(): void
    {
        $_SESSION = [];

        $publicRoutes = [
            ['GET', '/api/v1/health'],
            ['GET', '/api/v1/games'],
            ['GET', '/api/v1/promotions'],
            ['GET', '/api/v1/auth/csrf-token'],
        ];

        foreach ($publicRoutes as [$method, $path]) {
            $request = new Request($method, $path);
            $response = $this->app->dispatch($request);

            $this->assertSame(200, $response->getStatusCode(), "Guest must be able to access {$method} {$path}");
            $data = json_decode($response->getContent(), true);
            $this->assertTrue($data['success']);
        }
    }

    /**
     * SEC-01 Test 9: Player cannot access Admin-only APIs.
     */
    public function testPlayerCannotAccessAdminOnlyApis(): void
    {
        // User 3 is PLAYER
        $_SESSION['user_id'] = 3;

        $adminRoutes = [
            ['GET', '/api/v1/admin/users'],
            ['POST', '/api/v1/admin/rooms/1/cancel', ['reason' => 'Test reason']],
            ['GET', '/api/v1/admin/refunds'],
        ];

        foreach ($adminRoutes as $route) {
            [$method, $path] = $route;
            $body = $route[2] ?? [];

            $request = new Request($method, $path, [], $body);
            $response = $this->app->dispatch($request);

            $this->assertSame(403, $response->getStatusCode(), "Player must be forbidden on {$method} {$path}");
            $data = json_decode($response->getContent(), true);
            $this->assertFalse($data['success']);
            $this->assertSame('FORBIDDEN', $data['error']['code']);
        }
    }

    /**
     * SEC-01 Test 10: Authentication behavior is consistent across relevant protected endpoints.
     */
    public function testAuthenticationBehaviorIsConsistentAcrossRelevantProtectedEndpoints(): void
    {
        $_SESSION = [];

        $endpoints = [
            ['GET', '/api/v1/auth/me'],
            ['POST', '/api/v1/rooms', ['game_id' => 1, 'title' => 'Room']],
            ['GET', '/api/v1/admin/users'],
            ['GET', '/api/v1/dm/assignments'],
            ['GET', '/api/v1/notifications'],
        ];

        foreach ($endpoints as $ep) {
            [$method, $path] = $ep;
            $body = $ep[2] ?? [];

            // Unauthenticated with forged Bearer 1
            $request = new Request($method, $path, [], $body, ['Authorization' => 'Bearer 1']);
            $response = $this->app->dispatch($request);

            $this->assertSame(401, $response->getStatusCode(), "Endpoint {$method} {$path} must consistently return 401");
            $data = json_decode($response->getContent(), true);
            $this->assertFalse($data['success']);
            $this->assertSame('UNAUTHORIZED', $data['error']['code']);
            $this->assertArrayHasKey('message', $data);
        }
    }

    /**
     * SEC-04 Test 1: In production environment, X-Testing header cannot bypass CSRF verification.
     */
    public function testCsrfMiddlewareBlocksStateChangeEvenWithTestingHeaderInProduction(): void
    {
        $app = new Application(dirname(__DIR__, 2));
        $app->addMiddleware(new CsrfMiddleware());

        $app->getRouter()->post('/api/v1/test-mutation-bypass', function (Request $req) {
            return Response::success(['ok' => true]);
        });

        Config::set('APP_ENV', 'production');

        $request = new Request(
            method: 'POST',
            path: '/api/v1/test-mutation-bypass',
            queryParams: [],
            bodyParams: [],
            headers: [
                'X-Testing' => '1',
            ]
        );
        $response = $app->dispatch($request);

        $this->assertSame(403, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertSame('CSRF_TOKEN_INVALID', $data['error']['code']);

        Config::set('APP_ENV', 'testing');
    }

    /**
     * SEC-04 Test 2: In testing environment, state change mutations can bypass CSRF.
     */
    public function testCsrfMiddlewareAllowsBypassWhenEnvIsTesting(): void
    {
        $app = new Application(dirname(__DIR__, 2));
        $app->addMiddleware(new CsrfMiddleware());

        $app->getRouter()->post('/api/v1/test-mutation-testing-env', function (Request $req) {
            return Response::success(['ok' => true]);
        });

        Config::set('APP_ENV', 'testing');

        $request = new Request(
            method: 'POST',
            path: '/api/v1/test-mutation-testing-env'
        );
        $response = $app->dispatch($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
    }

    /**
     * SEC-04 Test 3: Session configuration applies secure parameters from config/session.php.
     */
    public function testSessionConfigurationAppliesSecureParametersFromConfig(): void
    {
        $params = session_get_cookie_params();

        $this->assertTrue((bool) $params['httponly'], 'Session cookie must have HttpOnly set to true');
        $this->assertSame('Lax', $params['samesite'], 'Session cookie must have SameSite set to Lax');
        $this->assertSame(7200, (int) $params['lifetime'], 'Session lifetime must match configuration');
        $this->assertSame('kbs_session_id', session_name(), 'Session name must match configured cookie_name');
    }
}
