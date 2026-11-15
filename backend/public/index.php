<?php

declare(strict_types=1);

/**
 * Kịch Bản Sát V16 - Front Controller
 */

$app = require_once __DIR__ . '/../bootstrap/app.php';

$request = App\Core\Request::capture();
$response = $app->dispatch($request);
$response->send();
