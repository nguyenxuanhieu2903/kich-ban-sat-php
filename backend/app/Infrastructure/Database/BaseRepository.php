<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use PDO;
use PDOException;
use Throwable;

/**
 * Base Repository providing unified PDO execution, error translation,
 * and security-hardened statement execution.
 */
abstract class BaseRepository
{
    public function __construct(
        protected readonly PDO $pdo
    ) {
    }

    /**
     * Get the underlying PDO connection.
     */
    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Executes a callback within repository try-catch block, translating
     * PDOException to DatabaseException with sanitized error messages.
     *
     * @template T
     * @param callable(): T $operation
     * @return T
     * @throws DatabaseException
     */
    protected function executeQuery(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (PDOException $e) {
            throw new DatabaseException(
                message: 'Database query execution failed: ' . $e->getMessage(),
                code: (int) ($e->getCode() !== 0 && is_numeric($e->getCode()) ? $e->getCode() : 0),
                previous: $e
            );
        } catch (Throwable $e) {
            if ($e instanceof DatabaseException) {
                throw $e;
            }
            throw new DatabaseException(
                message: 'Repository operation failed: ' . $e->getMessage(),
                code: (int) ($e->getCode() !== 0 && is_numeric($e->getCode()) ? $e->getCode() : 0),
                previous: $e
            );
        }
    }
}
