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

    $serveFile = function (string $fullPath, string $contentType) {
        if (is_file($fullPath)) {
            header("Content-Type: {$contentType}; charset=utf-8");
            header("Content-Length: " . (string) filesize($fullPath));
            header("Cache-Control: no-cache");
            readfile($fullPath);
            exit;
        }
    };

    if ($path === '/' || $path === '/index.html') {
        $serveFile($frontendDir . '/index.html', 'text/html');
    }

    if (in_array($path, ['/admin', '/admin.html'])) {
        $serveFile($frontendDir . '/admin.html', 'text/html');
    }

    if (in_array($path, ['/dm', '/dm.html'])) {
        $serveFile($frontendDir . '/dm.html', 'text/html');
    }

    if (in_array($path, ['/player', '/player.html'])) {
        $serveFile($frontendDir . '/player.html', 'text/html');
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
        $serveFile($filePath, $contentType);
    }
}

// Delegate API requests to application router
$app = require_once __DIR__ . '/../bootstrap/app.php';

$request = App\Core\Request::capture();
$response = $app->dispatch($request);
$response->send();
