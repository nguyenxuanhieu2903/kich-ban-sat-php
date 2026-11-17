<?php

declare(strict_types=1);

use App\Infrastructure\Config\Config;

return [
    'name' => Config::get('APP_NAME', 'Kich Ban Sat'),
    'env' => Config::get('APP_ENV', 'local'),
    'debug' => (bool) Config::get('APP_DEBUG', true),
    'url' => Config::get('APP_URL', 'http://127.0.0.1:8080'),
    'timezone' => Config::get('APP_TIMEZONE', 'Asia/Ho_Chi_Minh'),
];
