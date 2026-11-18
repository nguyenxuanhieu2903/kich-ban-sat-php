<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use PDO;
use Throwable;

/**
 * Database connection holder and provider.
 *
 * Exposes database connections according to project conventions,
 * supporting both dependency injection (instantiable provider)
 * and legacy static access (getInstance).
 */
class DatabaseConnection
{
    private static ?PDO $instance = null;
    private static ?ConnectionFactory $defaultFactory = null;

    private ?PDO $connection = null;
    private ConnectionFactory $factory;

    /**
     * Create an instance-based database connection provider for DI containers.
     *
     * @param ConnectionFactory|null $factory Factory to create connection when needed.
     * @param PDO|null $connection Existing connection (e.g. for testing/mocking).
     */
    public function __construct(
        ?ConnectionFactory $factory = null,
        ?PDO $connection = null
    ) {
        $this->factory = $factory ?? self::getDefaultFactory();
        $this->connection = $connection;
    }

    /**
     * Get or initialize the connection for this provider instance.
     *
     * @throws DatabaseException
     */
    public function getConnection(): PDO
    {
        if ($this->connection === null) {
            $this->connection = $this->factory->createConnection();
        }

        return $this->connection;
    }

    /**
     * Check connectivity on this provider instance.
     */
    public function ping(): bool
    {
        return self::checkConnectivity($this->getConnection());
    }

    /**
     * Get or initialize the shared PDO connection (static singleton for backward compatibility).
     *
     * @param DatabaseConfig|array<string, mixed> $config
     * @throws DatabaseException
     */
    public static function getInstance(DatabaseConfig|array $config = []): PDO
    {
        if (self::$instance === null || !empty($config)) {
            self::$instance = ConnectionFactory::create($config);
        }

        return self::$instance;
    }

    /**
     * Set a custom PDO instance for static access (useful for mocks or test SQLite).
     */
    public static function setInstance(?PDO $pdo): void
    {
        self::$instance = $pdo;
    }

    /**
     * Configure or reset the default ConnectionFactory for static/default instances.
     */
    public static function setFactory(?ConnectionFactory $factory): void
    {
        self::$defaultFactory = $factory;
    }

    /**
     * Get the default ConnectionFactory instance.
     */
    public static function getDefaultFactory(): ConnectionFactory
    {
        if (self::$defaultFactory === null) {
            self::$defaultFactory = new ConnectionFactory();
        }

        return self::$defaultFactory;
    }

    /**
     * Reset all static state (both singleton instance and default factory).
     */
    public static function reset(): void
    {
        self::$instance = null;
        self::$defaultFactory = null;
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
