<?php

declare(strict_types=1);

use App\Infrastructure\Config\Config;

$rawOrigins = Config::get('ALLOWED_ORIGINS', 'http://127.0.0.1:3000,http://localhost:3000');
$allowedOrigins = array_values(array_filter(array_map('trim', explode(',', (string) $rawOrigins))));

return [
    'allowed_origins' => $allowedOrigins,
    'supports_credentials' => (bool) Config::get('CORS_SUPPORTS_CREDENTIALS', true),
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'Accept', 'X-CSRF-Token', 'X-Idempotency-Key', 'Idempotency-Key'],
    'exposed_headers' => [],
    'max_age' => 86400,
];
