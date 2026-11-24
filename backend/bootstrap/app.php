<?php

declare(strict_types=1);

$basePath = dirname(__DIR__);

// Load Composer autoloader
$autoloader = $basePath . '/vendor/autoload.php';
if (file_exists($autoloader)) {
    require_once $autoloader;
}

// Initialize environment configuration
App\Infrastructure\Config\Config::load($basePath);

// Ensure session is configured and started for application lifecycle
App\Core\Application::configureSession($basePath);
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    @session_start();
}

// Instantiate application
$app = new App\Core\Application($basePath);

// Register global middleware
$app->addMiddleware(new App\Core\Middleware\CorsMiddleware());
$app->addMiddleware(new App\Core\Middleware\CsrfMiddleware());

// Register routes (use require so multiple instantiations in testing register routes)
$router = $app->getRouter();
$routesFile = $basePath . '/routes/api.php';
if (file_exists($routesFile)) {
    require $routesFile;
}

return $app;
