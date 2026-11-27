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

// Apply hardened session cookie flags from .env before any session_start()
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    $sessionLifetime = max(300, (int) App\Infrastructure\Config\Config::get('SESSION_LIFETIME', 7200));
    $sessionName = trim((string) App\Infrastructure\Config\Config::get('SESSION_COOKIE_NAME', 'kbs_session_id'));
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443)
        || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
    $cookieSecure = (bool) App\Infrastructure\Config\Config::get('SESSION_COOKIE_SECURE', false) || $isHttps;
    $cookieHttpOnly = (bool) App\Infrastructure\Config\Config::get('SESSION_COOKIE_HTTPONLY', true);
    $cookieSameSite = trim((string) App\Infrastructure\Config\Config::get('SESSION_COOKIE_SAMESITE', 'Lax'));
    if (!in_array($cookieSameSite, ['Lax', 'Strict', 'None'], true)) {
        $cookieSameSite = 'Lax';
    }

    if ($sessionName !== '' && preg_match('/^[A-Za-z0-9_-]+$/', $sessionName) === 1) {
        @session_name($sessionName);
    }
    @ini_set('session.use_only_cookies', '1');
    @ini_set('session.use_strict_mode', '1');
    @ini_set('session.gc_maxlifetime', (string) $sessionLifetime);
    @session_set_cookie_params([
        'lifetime' => $sessionLifetime,
        'path' => '/',
        'domain' => '',
        'secure' => $cookieSecure,
        'httponly' => $cookieHttpOnly,
        'samesite' => $cookieSameSite,
    ]);
}

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
