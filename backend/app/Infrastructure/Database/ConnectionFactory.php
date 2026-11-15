<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use App\Infrastructure\Config\Config;
use PDO;
use PDOException;

class ConnectionFactory
{
    /**
     * Create a new PDO instance from environment configuration or explicit options.
     *
     * @param array<string, mixed> $overrideConfig
     * @throws DatabaseException
     */
    public static function create(array $overrideConfig = []): PDO
    {
        $driver = (string) ($overrideConfig['driver'] ?? Config::get('DB_CONNECTION', 'mysql'));
        $host = (string) ($overrideConfig['host'] ?? Config::get('DB_HOST', '127.0.0.1'));
        $port = (int) ($overrideConfig['port'] ?? Config::get('DB_PORT', 3306));
        $database = (string) ($overrideConfig['database'] ?? Config::get('DB_DATABASE', 'booking_v16'));
        $username = (string) ($overrideConfig['username'] ?? Config::get('DB_USERNAME', 'root'));
        $password = (string) ($overrideConfig['password'] ?? Config::get('DB_PASSWORD', ''));
        $charset = (string) ($overrideConfig['charset'] ?? Config::get('DB_CHARSET', 'utf8mb4'));

        $dsn = sprintf('%s:host=%s;port=%d;dbname=%s;charset=%s', $driver, $host, $port, $database, $charset);

        $defaultOptions = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => false,
        ];

        // Ensure proper collation on MySQL
        if ($driver === 'mysql') {
            $defaultOptions[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES '{$charset}' COLLATE 'utf8mb4_unicode_ci'";
        }

        try {
            return new PDO($dsn, $username, $password, $defaultOptions);
        } catch (PDOException $e) {
            // Mask password and internal details from exception message
            $safeHost = htmlspecialchars($host, ENT_QUOTES, 'UTF-8');
            $safeDb = htmlspecialchars($database, ENT_QUOTES, 'UTF-8');
            throw new DatabaseException(
                sprintf('Failed to connect to database [%s] on host [%s:%d]: %s', $safeDb, $safeHost, $port, $e->getMessage()),
                (int) $e->getCode(),
                $e
            );
        }
    }
}
