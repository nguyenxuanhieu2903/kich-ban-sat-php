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

    /**
     * @param string[]|null $allowedOrigins
     */
    public function __construct(?array $allowedOrigins = null)
    {
        if ($allowedOrigins !== null) {
            $this->allowedOrigins = $allowedOrigins;
        } else {
            $rawOrigins = Config::get('ALLOWED_ORIGINS', 'http://127.0.0.1:3000,http://localhost:3000');
            $this->allowedOrigins = array_filter(array_map('trim', explode(',', (string) $rawOrigins)));
        }
    }

    public function handle(Request $request, callable $next): Response
    {
        $origin = $request->getHeader('origin');
        $isAllowed = false;
        $matchedOrigin = '';

        if ($origin !== null) {
            foreach ($this->allowedOrigins as $allowed) {
                if ($allowed === '*' || strcasecmp($allowed, $origin) === 0) {
                    $isAllowed = true;
                    $matchedOrigin = $origin;
                    break;
                }
            }
        }

        // Handle preflight OPTIONS request
        if ($request->getMethod() === 'OPTIONS') {
            $headers = [
                'Access-Control-Allow-Methods' => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, Accept',
                'Access-Control-Max-Age' => '86400',
            ];

            if ($isAllowed) {
                $headers['Access-Control-Allow-Origin'] = $matchedOrigin;
                $headers['Access-Control-Allow-Credentials'] = 'true';
            }

            return new Response('', 204, $headers);
        }

        /** @var Response $response */
        $response = $next($request);

        if ($isAllowed) {
            $response->withHeader('Access-Control-Allow-Origin', $matchedOrigin);
            $response->withHeader('Access-Control-Allow-Credentials', 'true');
        }

        return $response;
    }
}
