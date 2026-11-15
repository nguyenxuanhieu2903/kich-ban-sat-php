<?php

declare(strict_types=1);

/**
 * CLI script to verify database connectivity safely without exposing passwords.
 * Usage: php database/verify-connection.php
 */

$basePath = dirname(__DIR__);
require_once $basePath . '/vendor/autoload.php';

use App\Infrastructure\Config\Config;
use App\Infrastructure\Database\DatabaseConnection;
use App\Infrastructure\Database\DatabaseException;

Config::load($basePath);

$host = Config::get('DB_HOST', '127.0.0.1');
$port = Config::get('DB_PORT', 3306);
$database = Config::get('DB_DATABASE', 'booking_v16');
$username = Config::get('DB_USERNAME', 'root');

echo "============================================\n";
echo "  Kịch Bản Sát V16 - DB Connectivity Check  \n";
echo "============================================\n";
echo "Target Host: {$host}:{$port}\n";
echo "Database:    {$database}\n";
echo "User:        {$username}\n";
echo "Status:      Connecting...\n";

try {
    $pdo = DatabaseConnection::getInstance();
    $connected = DatabaseConnection::checkConnectivity($pdo);

    if ($connected) {
        $serverVersion = $pdo->query('SELECT VERSION()')->fetchColumn();
        echo "RESULT:      SUCCESS\n";
        echo "Server:      MySQL {$serverVersion}\n";
        echo "PDO Mode:    ERRMODE_EXCEPTION enabled\n";
        echo "Emulation:   PDO::ATTR_EMULATE_PREPARES disabled\n";
        exit(0);
    } else {
        echo "RESULT:      FAILED (ping returned false)\n";
        exit(1);
    }
} catch (DatabaseException $e) {
    echo "RESULT:      FAILED\n";
    echo "Error:       " . $e->getMessage() . "\n";
    exit(1);
} catch (Throwable $e) {
    echo "RESULT:      ERROR\n";
    echo "Error:       " . $e->getMessage() . "\n";
    exit(1);
}
