<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Infrastructure\Config\Config;

class CsrfMiddleware implements MiddlewareInterface
{
    /** @var string[] */
    protected array $excludedPaths = [
        '/api/v1/auth/csrf-token',
        '/api/auth/csrf-token',
        '/api/health',
    ];

    /**
     * Get or initialize CSRF token for the active session.
     */
    public static function getToken(): string
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }

        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION['_csrf_token'];
    }

    /**
     * Verify whether a submitted token matches session CSRF token.
     */
    public static function verifyToken(?string $submittedToken): bool
    {
        if ($submittedToken === null || $submittedToken === '') {
            return false;
        }

        $sessionToken = self::getToken();
        return hash_equals($sessionToken, $submittedToken);
    }

    public function handle(Request $request, callable $next): Response
    {
        $method = $request->getMethod();

        // Safe methods (GET, HEAD, OPTIONS) do not require CSRF validation
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        // Skip in testing environment or test header
        $isTesting = Config::get('APP_ENV') === 'testing' || $request->getHeader('x-testing') === '1';
        if ($isTesting) {
            return $next($request);
        }

        $path = $request->getPath();
        foreach ($this->excludedPaths as $excluded) {
            if (str_starts_with($path, $excluded)) {
                return $next($request);
            }
        }

        $token = $request->getHeader('x-csrf-token') ?? $request->input('_csrf_token');
        if (!is_string($token) || !self::verifyToken($token)) {
            return Response::fail(
                'Invalid or missing CSRF token',
                'CSRF_TOKEN_INVALID',
                403
            );
        }

        return $next($request);
    }
}
