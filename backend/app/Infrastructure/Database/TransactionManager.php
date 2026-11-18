<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use App\Infrastructure\Database\Exceptions\TransactionException;
use PDO;
use Throwable;
use WeakMap;

/**
 * Manages database transaction boundaries with nested savepoint support.
 *
 * Tracks transaction depth per PDO instance using WeakMap so that
 * different connections maintain independent transaction states without
 * memory leaks or state collision.
 */
class TransactionManager
{
    private PDO|DatabaseConnection $connectionSource;

    /**
     * @var WeakMap<PDO, int>|null
     */
    private static ?WeakMap $depthRegistry = null;

    /**
     * @param PDO|DatabaseConnection $connection
     */
    public function __construct(PDO|DatabaseConnection $connection)
    {
        $this->connectionSource = $connection;
        self::initRegistry();
    }

    /**
     * Execute a callable within a managed transaction boundary.
     *
     * Supports nested transactions via SAVEPOINTs:
     * - Outermost level uses beginTransaction / commit / rollBack.
     * - Nested levels use SAVEPOINT, RELEASE SAVEPOINT, and ROLLBACK TO SAVEPOINT.
     *
     * @template T
     * @param callable(PDO): T $callback
     * @return T
     * @throws Throwable
     */
    public function transaction(callable $callback): mixed
    {
        $pdo = $this->getPdo();

        $this->beginTransactionLevel($pdo);

        try {
            $result = $callback($pdo);
            $this->commitLevel($pdo);
            return $result;
        } catch (Throwable $e) {
            $this->rollBackLevel($pdo);
            throw $e;
        }
    }

    /**
     * Get the current active transaction nesting depth for this connection.
     */
    public function getTransactionDepth(): int
    {
        $pdo = $this->getPdo();
        return self::$depthRegistry[$pdo] ?? 0;
    }

    /**
     * Resolve the underlying PDO instance.
     */
    public function getPdo(): PDO
    {
        if ($this->connectionSource instanceof DatabaseConnection) {
            return $this->connectionSource->getConnection();
        }

        return $this->connectionSource;
    }

    /**
     * Initialize the shared connection depth registry.
     */
    private static function initRegistry(): void
    {
        if (self::$depthRegistry === null) {
            self::$depthRegistry = new WeakMap();
        }
    }

    /**
     * Set the current depth for a PDO instance.
     */
    private function setDepth(PDO $pdo, int $depth): void
    {
        self::initRegistry();
        if ($depth <= 0) {
            unset(self::$depthRegistry[$pdo]);
        } else {
            self::$depthRegistry[$pdo] = $depth;
        }
    }

    /**
     * Get the current depth for a PDO instance.
     */
    private function getDepth(PDO $pdo): int
    {
        self::initRegistry();
        return self::$depthRegistry[$pdo] ?? 0;
    }

    /**
     * Begin a transaction or create an internal savepoint.
     *
     * @throws TransactionException
     */
    private function beginTransactionLevel(PDO $pdo): void
    {
        $depth = $this->getDepth($pdo);

        if ($depth === 0) {
            try {
                $pdo->beginTransaction();
            } catch (Throwable $e) {
                throw new TransactionException('Failed to start database transaction', 0, $e);
            }
        } else {
            $savepointName = $this->getSavepointName($depth);
            try {
                $pdo->exec("SAVEPOINT {$savepointName}");
            } catch (Throwable $e) {
                throw new TransactionException(
                    "Failed to create savepoint at depth {$depth}",
                    0,
                    $e
                );
            }
        }

        $this->setDepth($pdo, $depth + 1);
    }

    /**
     * Commit the current transaction level or release its savepoint.
     *
     * @throws TransactionException
     */
    private function commitLevel(PDO $pdo): void
    {
        $currentDepth = $this->getDepth($pdo);
        if ($currentDepth <= 0) {
            return;
        }

        $newDepth = $currentDepth - 1;
        $this->setDepth($pdo, $newDepth);

        if ($newDepth === 0) {
            try {
                $pdo->commit();
            } catch (Throwable $e) {
                try {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                } catch (Throwable) {
                    // Suppress secondary rollback failure so the original commit exception is preserved
                }

                throw new TransactionException('Failed to commit database transaction', 0, $e);
            }
        } else {
            $savepointName = $this->getSavepointName($newDepth);
            try {
                $pdo->exec("RELEASE SAVEPOINT {$savepointName}");
            } catch (Throwable $e) {
                throw new TransactionException(
                    "Failed to release savepoint at depth {$newDepth}",
                    0,
                    $e
                );
            }
        }
    }

    /**
     * Rollback the current transaction level to its savepoint or abort outermost transaction.
     * Ensures depth is always decremented and does not mask original exception if rollback fails.
     */
    private function rollBackLevel(PDO $pdo): void
    {
        $currentDepth = $this->getDepth($pdo);
        if ($currentDepth <= 0) {
            return;
        }

        $newDepth = $currentDepth - 1;
        $this->setDepth($pdo, $newDepth);

        if ($newDepth === 0) {
            try {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            } catch (Throwable) {
                // Secondary rollback failure must not overwrite primary exception
            }
        } else {
            $savepointName = $this->getSavepointName($newDepth);
            try {
                $pdo->exec("ROLLBACK TO SAVEPOINT {$savepointName}");
            } catch (Throwable) {
                // Secondary rollback failure must not overwrite primary exception
            }
        }
    }

    /**
     * Generate an internal, safe savepoint identifier based on depth.
     */
    private function getSavepointName(int $depth): string
    {
        return 'sp_depth_' . $depth;
    }
}
