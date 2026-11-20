<?php

declare(strict_types=1);
date_default_timezone_set('Asia/Ho_Chi_Minh');

$basePath = dirname(__DIR__);

// Load Composer autoloader
$autoloader = $basePath . '/vendor/autoload.php';
if (file_exists($autoloader)) {
    require_once $autoloader;
}

// Initialize environment configuration
App\Infrastructure\Config\Config::load($basePath);

// Bật PHP Session an toàn cho HTTP request (bỏ qua khi chạy CLI / PHPUnit)
if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE && !headers_sent()) {
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Instantiate application
$app = new App\Core\Application($basePath);

// Register global middleware
$app->addMiddleware(new App\Core\Middleware\CorsMiddleware());

// Register routes (use require so multiple instantiations in testing register routes)
$router = $app->getRouter();
$routesFile = $basePath . '/routes/api.php';
if (file_exists($routesFile)) {
    require $routesFile;
}

return $app;