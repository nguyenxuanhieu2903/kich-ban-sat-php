<?php

declare(strict_types=1);

use App\Infrastructure\Config\Config;

return [
    'lifetime' => (int) Config::get('SESSION_LIFETIME', 7200),
    'cookie_name' => Config::get('SESSION_COOKIE_NAME', 'kbs_session_id'),
    'secure' => (bool) Config::get('SESSION_COOKIE_SECURE', false),
    'httponly' => (bool) Config::get('SESSION_COOKIE_HTTPONLY', true),
    'samesite' => Config::get('SESSION_COOKIE_SAMESITE', 'Lax'),
];
