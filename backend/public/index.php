<?php

declare(strict_types=1);

/**
 * Kịch Bản Sát V16 - Front Controller & Same-Origin Static Asset Server
 */

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH) ?? '/';

// Serve OpenAPI spec directly if requested at root
if ($path === '/openapi.json') {
    $openapiFile = __DIR__ . '/openapi.json';
    if (file_exists($openapiFile)) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        readfile($openapiFile);
        exit;
    }
}

if ($path === '/docs') {
    header('Location: /api/v1/docs');
    exit;
}

// Serve uploaded student card images with strict session RBAC (Admin, DM, or owning Player only)
if (preg_match('#^/storage/uploads/student_cards/([a-zA-Z0-9._-]+)$#', $path, $cardMatch)) {
    require_once __DIR__ . '/../bootstrap/app.php';
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    $sessionUserId = (int) ($_SESSION['user_id'] ?? 0);
    $sessionRole = strtoupper((string) ($_SESSION['role_code'] ?? ''));
    if ($sessionUserId <= 0) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Yêu cầu đăng nhập để xem ảnh thẻ HSSV', 'error_code' => 'UNAUTHORIZED'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $cardFileName = basename($cardMatch[1]);
    $isStaff = in_array($sessionRole, ['ADMIN', 'DM'], true);
    $isOwner = str_starts_with($cardFileName, 'hssv_' . $sessionUserId . '_');
    if (!$isStaff && !$isOwner) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Bạn không có quyền xem ảnh thẻ HSSV này', 'error_code' => 'FORBIDDEN'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $cardsDir = realpath(dirname(__DIR__) . '/storage/uploads/student_cards');
    $cardFilePath = $cardsDir ? realpath($cardsDir . DIRECTORY_SEPARATOR . $cardFileName) : false;
    $cardExt = strtolower(pathinfo($cardFileName, PATHINFO_EXTENSION));
    $cardMimes = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];

    if ($cardFilePath && $cardsDir && str_starts_with($cardFilePath, $cardsDir) && is_file($cardFilePath) && isset($cardMimes[$cardExt])) {
        header('Content-Type: ' . $cardMimes[$cardExt]);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=300');
        readfile($cardFilePath);
        exit;
    }

    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Không tìm thấy ảnh thẻ HSSV', 'error_code' => 'NOT_FOUND'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Serve frontend static files if request is not an API call
if (!str_starts_with($path, '/api')) {
    $projectRoot = dirname(__DIR__, 2);
    $frontendDir = $projectRoot . '/frontend';

    if ($path === '/' || $path === '/index.html') {
        header('Content-Type: text/html; charset=utf-8');
        readfile($frontendDir . '/index.html');
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
            'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
            'woff2' => 'font/woff2',
            'woff' => 'font/woff',
            'ttf' => 'font/ttf',
        ];
        $contentType = $mimeTypes[$ext] ?? 'text/plain';
        header("Content-Type: {$contentType}; charset=utf-8");
        if (in_array($ext, ['html', 'js', 'mjs', 'css'], true)) {
            header('Cache-Control: no-cache, must-revalidate');
        }
        readfile($filePath);
        exit;
    }
}

// Delegate API requests to application router
$app = require_once __DIR__ . '/../bootstrap/app.php';

try {
    $request = App\Core\Request::capture();
    $response = $app->dispatch($request);
} catch (\Throwable $e) {
    $response = App\Core\Exceptions\ExceptionHandler::handle($e);
}
$response->send();
