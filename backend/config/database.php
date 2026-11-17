<?php

declare(strict_types=1);

use App\Infrastructure\Config\Config;

return [
    'default' => Config::get('DB_CONNECTION', 'mysql'),
    'connections' => [
        'mysql' => [
            'driver' => Config::get('DB_CONNECTION', 'mysql'),
            'host' => Config::get('DB_HOST', '127.0.0.1'),
            'port' => (int) Config::get('DB_PORT', 3306),
            'database' => Config::get('DB_DATABASE', 'booking_v16'),
            'username' => Config::get('DB_USERNAME', 'root'),
            'password' => Config::get('DB_PASSWORD', ''),
            'charset' => Config::get('DB_CHARSET', 'utf8mb4'),
            'collation' => 'utf8mb4_unicode_ci',
        ],
    ],
];
