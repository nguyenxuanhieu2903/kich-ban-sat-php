<?php

declare(strict_types=1);

/**
 * Kịch Bản Sát V16 - Front Controller
 */

// If running with PHP's built-in web server, serve static files directly if they exist
if (PHP_SAPI === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    if ($path !== '/' && file_exists(__DIR__ . $path)) {
        return false;
    }
}

$app = require_once __DIR__ . '/../bootstrap/app.php';

$request = App\Core\Request::capture();
$response = $app->dispatch($request);
$response->send();
