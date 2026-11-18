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
        } catch (Throwable $e) {
            $this->rollBackLevel($pdo);
            throw $e;
        }

        try {
            $this->commitLevel($pdo);
        } catch (Throwable $e) {
            $this->rollBackLevel($pdo);
            throw $e;
        }

        return $result;
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
                // If beginTransaction fails, depth remains 0
                throw new TransactionException('Failed to start database transaction', 0, $e);
            }
        } else {
            $savepointName = $this->getSavepointName($depth);
            try {
                $pdo->exec("SAVEPOINT {$savepointName}");
            } catch (Throwable $e) {
                // If creating savepoint fails, depth remains unchanged
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
     * Note: Depth is decremented ONLY when commit/release succeeds.
     * If an error occurs, depth remains at the current level so rollBackLevel()
     * can roll back this exact level.
     *
     * @throws TransactionException
     */
    private function commitLevel(PDO $pdo): void
    {
        $currentDepth = $this->getDepth($pdo);
        if ($currentDepth <= 0) {
            return;
        }

        if ($currentDepth === 1) {
            try {
                $pdo->commit();
                $this->setDepth($pdo, 0);
            } catch (Throwable $e) {
                throw new TransactionException('Failed to commit database transaction', 0, $e);
            }
        } else {
            // Nested level: release the savepoint that was created for this level
            $savepointLevel = $currentDepth - 1;
            $savepointName = $this->getSavepointName($savepointLevel);

            try {
                $pdo->exec("RELEASE SAVEPOINT {$savepointName}");
                $this->setDepth($pdo, $currentDepth - 1);
            } catch (Throwable $e) {
                throw new TransactionException(
                    "Failed to release savepoint at depth {$savepointLevel}",
                    0,
                    $e
                );
            }
        }
    }

    /**
     * Rollback the current transaction level to its savepoint or abort outermost transaction.
     *
     * Rollback is performed using the current level's savepoint, and depth is decremented.
     * Secondary rollback failures are suppressed so the primary exception is preserved.
     */
    private function rollBackLevel(PDO $pdo): void
    {
        $currentDepth = $this->getDepth($pdo);
        if ($currentDepth <= 0) {
            return;
        }

        if ($currentDepth === 1) {
            try {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            } catch (Throwable) {
                // Secondary rollback failure must not overwrite primary exception
            } finally {
                $this->setDepth($pdo, 0);
            }
        } else {
            $savepointLevel = $currentDepth - 1;
            $savepointName = $this->getSavepointName($savepointLevel);

            try {
                $pdo->exec("ROLLBACK TO SAVEPOINT {$savepointName}");
            } catch (Throwable) {
                // Secondary rollback failure must not overwrite primary exception
            } finally {
                $this->setDepth($pdo, $currentDepth - 1);
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
