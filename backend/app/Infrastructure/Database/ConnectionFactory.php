<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use App\Core\Exceptions\ExceptionHandler;
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
            $sanitizedMsg = ExceptionHandler::sanitize($e->getMessage());

            $errorInfo = sprintf(
                'Failed to connect to database [%s] on host [%s:%d]: %s',
                $database,
                $host,
                $port,
                $sanitizedMsg
            );

            // Raw PDOException is deliberately NOT chained as previous to prevent leaking
            // plain-text password from PDO::__construct() stack trace frame arguments.
            throw new DatabaseException($errorInfo, (int) $e->getCode());
        }
    }
}
