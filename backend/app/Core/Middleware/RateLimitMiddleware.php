<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Infrastructure\Config\Config;

class RateLimitMiddleware implements MiddlewareInterface
{
    /** @var string[] */
    protected array $limitedPaths = [
        '/api/v1/auth/login',
        '/api/auth/login',
        '/api/v1/auth/register',
        '/api/auth/register',
    ];

    public function __construct(
        protected int $maxAttempts = 100,
        protected int $decaySeconds = 60
    ) {
    }

    public function handle(Request $request, callable $next): Response
    {
        if (Config::get('APP_ENV') === 'testing' || $request->getMethod() !== 'POST') {
            return $next($request);
        }

        $path = $request->getPath();
        if (!in_array($path, $this->limitedPaths, true)) {
            return $next($request);
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $key = hash('sha256', $ip . '|' . $path);
        $dir = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'rate_limits';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $file = $dir . DIRECTORY_SEPARATOR . $key . '.json';
        $now = time();
        $timestamps = [];

        if (is_file($file)) {
            $raw = @file_get_contents($file);
            $decoded = is_string($raw) ? json_decode($raw, true) : null;
            if (is_array($decoded)) {
                $timestamps = array_values(array_filter(
                    $decoded,
                    fn ($ts) => is_int($ts) && ($now - $ts) < $this->decaySeconds
                ));
            }
        }

        if (count($timestamps) >= $this->maxAttempts) {
            return Response::fail(
                'Bạn thao tác quá nhanh. Vui lòng thử lại sau ít phút.',
                'TOO_MANY_REQUESTS',
                429,
                ['retry_after_seconds' => $this->decaySeconds]
            );
        }

        $timestamps[] = $now;
        @file_put_contents($file, json_encode($timestamps), LOCK_EX);

        return $next($request);
    }
}