<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Infrastructure\Config\Config;

class RateLimitMiddleware implements MiddlewareInterface
{
    protected string $storageDir;
    protected int $defaultLimit;
    protected int $defaultDecaySeconds;
    protected array $routeLimits;
    protected bool $forceEnabled;

    public function __construct(
        int $defaultLimit = 120,
        int $defaultDecaySeconds = 60,
        ?string $storageDir = null,
        array $routeLimits = [],
        bool $forceEnabled = false
    ) {
        $this->defaultLimit = $defaultLimit;
        $this->defaultDecaySeconds = $defaultDecaySeconds;
        $this->storageDir = $storageDir ?? (dirname(__DIR__, 3) . '/storage/rate_limits');
        $this->routeLimits = $routeLimits ?: [
            '/auth/login' => ['limit' => 15, 'decay' => 60],
            '/auth/register' => ['limit' => 10, 'decay' => 60],
        ];
        $this->forceEnabled = $forceEnabled;

        if (!is_dir($this->storageDir)) {
            @mkdir($this->storageDir, 0775, true);
        }
    }

    public function handle(Request $request, callable $next): Response
    {
        // Allow disabling rate limiting in testing environment if configured
        if (!$this->forceEnabled) {
            $enabled = Config::get('RATE_LIMIT_ENABLED', true);
            if ($enabled === false || $enabled === 'false' || (isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'testing' && Config::get('RATE_LIMIT_TEST_ENABLED', false) !== true)) {
                return $next($request);
            }
        }

        $path = $request->getPath();
        $limitConfig = $this->resolveLimit($path);
        $maxAttempts = $limitConfig['limit'];
        $decaySeconds = $limitConfig['decay'];

        $clientIp = $this->resolveClientIp($request);
        $cacheKey = md5($clientIp . '_' . $path);
        $cacheFile = $this->storageDir . '/' . $cacheKey . '.json';

        $now = time();
        $rateData = $this->loadRateData($cacheFile, $now, $decaySeconds);

        if ($rateData['count'] >= $maxAttempts) {
            $retryAfter = max(1, $rateData['reset_at'] - $now);
            $response = Response::fail(
                'Bạn đã gửi quá nhiều yêu cầu. Vui lòng thử lại sau giây lát.',
                'TOO_MANY_REQUESTS',
                429
            );

            return $response->withHeaders([
                'X-RateLimit-Limit' => (string) $maxAttempts,
                'X-RateLimit-Remaining' => '0',
                'X-RateLimit-Reset' => (string) $rateData['reset_at'],
                'Retry-After' => (string) $retryAfter,
            ]);
        }

        // Increment count and persist
        $rateData['count']++;
        $this->saveRateData($cacheFile, $rateData);

        /** @var Response $response */
        $response = $next($request);

        $remaining = max(0, $maxAttempts - $rateData['count']);

        return $response->withHeaders([
            'X-RateLimit-Limit' => (string) $maxAttempts,
            'X-RateLimit-Remaining' => (string) $remaining,
            'X-RateLimit-Reset' => (string) $rateData['reset_at'],
        ]);
    }

    protected function resolveLimit(string $path): array
    {
        foreach ($this->routeLimits as $route => $config) {
            if (str_contains($path, $route)) {
                return $config;
            }
        }

        return [
            'limit' => $this->defaultLimit,
            'decay' => $this->defaultDecaySeconds,
        ];
    }

    protected function resolveClientIp(Request $request): string
    {
        $forwarded = $request->getHeader('X-Forwarded-For');
        if ($forwarded !== null && $forwarded !== '') {
            $parts = explode(',', $forwarded);
            return trim($parts[0]);
        }

        return $request->getHeader('Remote-Addr') ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    protected function loadRateData(string $cacheFile, int $now, int $decaySeconds): array
    {
        if (file_exists($cacheFile)) {
            $raw = @file_get_contents($cacheFile);
            if ($raw !== false) {
                $data = json_decode($raw, true);
                if (is_array($data) && isset($data['reset_at']) && $data['reset_at'] > $now) {
                    return $data;
                }
            }
        }

        return [
            'count' => 0,
            'reset_at' => $now + $decaySeconds,
        ];
    }

    protected function saveRateData(string $cacheFile, array $data): void
    {
        @file_put_contents($cacheFile, json_encode($data), LOCK_EX);
    }
}
