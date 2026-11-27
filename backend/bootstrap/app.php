<?php

declare(strict_types=1);

$basePath = dirname(__DIR__);

// Load Composer autoloader if installed, otherwise fall back to native PSR-4 autoloader
// so fresh clones (Docker or XAMPP/Laragon) run out-of-the-box without requiring Composer
$autoloader = $basePath . '/vendor/autoload.php';
if (file_exists($autoloader)) {
    require_once $autoloader;
} else {
    spl_autoload_register(static function (string $class) use ($basePath): void {
        $prefix = 'App\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }
        $relative = substr($class, strlen($prefix));
        $file = $basePath . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    });
}

// Initialize environment configuration
App\Infrastructure\Config\Config::load($basePath);
date_default_timezone_set((string) App\Infrastructure\Config\Config::get('APP_TIMEZONE', 'Asia/Ho_Chi_Minh'));

// Instantiate application
$app = new App\Core\Application($basePath);

// Register global middleware
$app->addMiddleware(new App\Core\Middleware\CorsMiddleware());
$app->addMiddleware(new App\Core\Middleware\CsrfMiddleware());
$app->addMiddleware(new App\Core\Middleware\RateLimitMiddleware());

// Register routes (use require so multiple instantiations in testing register routes)
$router = $app->getRouter();
$routesFile = $basePath . '/routes/api.php';
if (file_exists($routesFile)) {
    require $routesFile;
}

return $app;
