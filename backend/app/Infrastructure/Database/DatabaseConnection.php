<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use PDO;
use Throwable;

class DatabaseConnection
{
    private static ?PDO $instance = null;

    /**
     * Get or initialize the shared PDO connection.
     */
    public static function getInstance(array $config = []): PDO
    {
        if (self::$instance === null || !empty($config)) {
            self::$instance = ConnectionFactory::create($config);
        }

        return self::$instance;
    }

    /**
     * Set a custom PDO instance (useful for mocks or test SQLite).
     */
    public static function setInstance(?PDO $pdo): void
    {
        self::$instance = $pdo;
    }

    /**
     * Verify database connectivity by running a lightweight query.
     */
    public static function checkConnectivity(?PDO $pdo = null): bool
    {
        try {
            $connection = $pdo ?? self::getInstance();
            $statement = $connection->query('SELECT 1');
            return $statement !== false && (int) $statement->fetchColumn() === 1;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Execute a callable within a transactional boundary.
     *
     * @template T
     * @param callable(PDO): T $callback
     * @return T
     * @throws Throwable
     */
    public static function transaction(callable $callback, ?PDO $pdo = null): mixed
    {
        $connection = $pdo ?? self::getInstance();

        if ($connection->inTransaction()) {
            return $callback($connection);
        }

        $connection->beginTransaction();

        try {
            $result = $callback($connection);
            $connection->commit();
            return $result;
        } catch (Throwable $e) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }
            throw $e;
        }
    }
}
