<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Infrastructure\Config\Config;

class CorsMiddleware implements MiddlewareInterface
{
    /** @var string[] */
    protected array $allowedOrigins;
    protected bool $supportsCredentials;

    /**
     * @param string[]|null $allowedOrigins
     * @param bool|null $supportsCredentials
     */
    public function __construct(?array $allowedOrigins = null, ?bool $supportsCredentials = null)
    {
        if ($allowedOrigins !== null) {
            $this->allowedOrigins = array_values(array_filter(array_map('trim', $allowedOrigins)));
        } else {
            $rawOrigins = Config::get('ALLOWED_ORIGINS', 'http://127.0.0.1:3000,http://localhost:3000');
            $this->allowedOrigins = array_values(array_filter(array_map('trim', explode(',', (string) $rawOrigins))));
        }

        if ($supportsCredentials !== null) {
            $this->supportsCredentials = $supportsCredentials;
        } else {
            $this->supportsCredentials = (bool) Config::get('CORS_SUPPORTS_CREDENTIALS', true);
        }
    }

    public function handle(Request $request, callable $next): Response
    {
        $origin = $request->getHeader('origin');
        $isWildcard = in_array('*', $this->allowedOrigins, true);

        // Security requirement: If wildcard is present, credentials MUST NOT be enabled
        $allowCredentials = $this->supportsCredentials && !$isWildcard;

        $isAllowed = false;
        $matchedOrigin = '';

        if ($origin !== null && $origin !== '') {
            if ($isWildcard) {
                // Literal wildcard: never reflect dynamic origin with credentials
                $isAllowed = true;
                $matchedOrigin = '*';
            } else {
                foreach ($this->allowedOrigins as $allowed) {
                    if (strcasecmp($allowed, $origin) === 0) {
                        $isAllowed = true;
                        $matchedOrigin = $origin;
                        break;
                    }
                }
            }
        }

        // Handle preflight OPTIONS request
        if ($request->getMethod() === 'OPTIONS') {
            $headers = [
                'Access-Control-Allow-Methods' => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, Accept, Idempotency-Key',
                'Access-Control-Max-Age' => '86400',
            ];

            if ($isAllowed) {
                $headers['Access-Control-Allow-Origin'] = $matchedOrigin;
                if ($allowCredentials) {
                    $headers['Access-Control-Allow-Credentials'] = 'true';
                }
            }

            $response = new Response('', 204, $headers);
            // RFC 7234: If the response representation varies by Origin allowlist,
            // Vary: Origin must be set consistently on all responses to protect HTTP caches.
            if (!$isWildcard) {
                $response = $this->appendVaryHeader($response, 'Origin');
            }

            return $response;
        }

        /** @var Response $response */
        $response = $next($request);

        // For non-wildcard configurations, response headers depend on the Origin header.
        // Therefore, Vary: Origin MUST be set on all responses (including requests without Origin,
        // or with disallowed origins) so intermediate caches do not serve an origin-specific response
        // to an origin-less or different-origin request.
        if (!$isWildcard) {
            $response = $this->appendVaryHeader($response, 'Origin');
        }

        if ($isAllowed) {
            $response->withHeader('Access-Control-Allow-Origin', $matchedOrigin);
            if ($allowCredentials) {
                $response->withHeader('Access-Control-Allow-Credentials', 'true');
            }
        }

        return $response;
    }

    /**
     * Safely append a field to the Vary header, preserving any existing values.
     */
    protected function appendVaryHeader(Response $response, string $field): Response
    {
        $headers = $response->getHeaders();
        $existingVary = null;

        foreach ($headers as $name => $value) {
            if (strcasecmp($name, 'Vary') === 0) {
                $existingVary = $value;
                break;
            }
        }

        if ($existingVary === null || trim($existingVary) === '') {
            return $response->withHeader('Vary', $field);
        }

        $parts = array_map('trim', explode(',', $existingVary));
        foreach ($parts as $part) {
            if (strcasecmp($part, $field) === 0) {
                return $response;
            }
        }

        return $response->withHeader('Vary', $existingVary . ', ' . $field);
    }
}
