<?php

declare(strict_types=1);

$basePath = __DIR__;
require_once $basePath . '/vendor/autoload.php';

use App\Infrastructure\Config\Config;

Config::load($basePath);

$host = Config::get('DB_HOST', '127.0.0.1');
$port = (int) Config::get('DB_PORT', 3306);
$database = Config::get('DB_DATABASE', 'booking_project_php');
$username = Config::get('DB_USERNAME', 'root');
$password = Config::get('DB_PASSWORD', '');
$charset = Config::get('DB_CHARSET', 'utf8mb4');

return [
    'paths' => [
        'migrations' => '%%PHINX_CONFIG_DIR%%/database/migrations',
        'seeds' => '%%PHINX_CONFIG_DIR%%/database/seeders',
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => 'development',
        'development' => [
            'adapter' => 'mysql',
            'host' => $host,
            'name' => $database,
            'user' => $username,
            'pass' => $password,
            'port' => $port,
            'charset' => $charset,
            'collation' => 'utf8mb4_unicode_ci',
        ],
        'testing' => [
            'adapter' => 'mysql',
            'host' => $host,
            'name' => Config::get('DB_TEST_DATABASE', 'booking_project_php_test'),
            'user' => $username,
            'pass' => $password,
            'port' => $port,
            'charset' => $charset,
            'collation' => 'utf8mb4_unicode_ci',
        ],
    ],
    'version_order' => 'creation',
];
