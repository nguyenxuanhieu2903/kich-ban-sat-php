<?php

declare(strict_types=1);

/**
 * Kịch Bản Sát V16 - Front Controller & Same-Origin Static Asset Server
 */

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH) ?? '/';

// Serve frontend static files if request is not an API call
if (!str_starts_with($path, '/api')) {
    $projectRoot = dirname(__DIR__, 2);
    $frontendDir = $projectRoot . '/frontend';

    if ($path === '/' || $path === '/index.html') {
        header('Content-Type: text/html; charset=utf-8');
        readfile($frontendDir . '/index.html');
        exit;
    }

    if (in_array($path, ['/admin', '/admin.html'])) {
        header('Content-Type: text/html; charset=utf-8');
        readfile($frontendDir . '/admin.html');
        exit;
    }

    if (in_array($path, ['/dm', '/dm.html'])) {
        header('Content-Type: text/html; charset=utf-8');
        readfile($frontendDir . '/dm.html');
        exit;
    }

    if (in_array($path, ['/player', '/player.html', '/phayer', '/phayer.html'])) {
        header('Content-Type: text/html; charset=utf-8');
        readfile($frontendDir . '/player.html');
        exit;
    }

    $filePath = realpath($frontendDir . $path);
    $realFrontendDir = realpath($frontendDir);

    // Path traversal check
    if ($filePath && $realFrontendDir && str_starts_with($filePath, $realFrontendDir) && is_file($filePath)) {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $mimeTypes = [
            'js' => 'application/javascript',
            'mjs' => 'application/javascript',
            'css' => 'text/css',
            'html' => 'text/html',
            'json' => 'application/json',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
        ];
        $contentType = $mimeTypes[$ext] ?? 'text/plain';
        header("Content-Type: {$contentType}; charset=utf-8");
        readfile($filePath);
        exit;
    }
}

// Delegate API requests to application router
$app = require_once __DIR__ . '/../bootstrap/app.php';

$request = App\Core\Request::capture();
$response = $app->dispatch($request);
$response->send();
