<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use App\Core\Exceptions\ExceptionHandler;
use App\Infrastructure\Database\Exceptions\InvalidConfigurationException;
use PDO;
use PDOException;

/**
 * Factory responsible for creating and configuring secure PDO database connections.
 *
 * Supports both dependency injection (instantiable service) and static creation.
 */
class ConnectionFactory
{
    /**
     * Mandatory secure PDO connection options that cannot be overridden.
     *
     * @var array<int, mixed>
     */
    public const MANDATORY_OPTIONS = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_PERSISTENT => false,
    ];

    /**
     * Default secure PDO connection options.
     *
     * @var array<int, mixed>
     */
    public const DEFAULT_OPTIONS = self::MANDATORY_OPTIONS;

    private DatabaseConfig $config;

    /**
     * @param DatabaseConfig|null $config Validated DatabaseConfig. Falls back to environment config if omitted.
     * @throws InvalidConfigurationException If environment configuration is invalid.
     */
    public function __construct(?DatabaseConfig $config = null)
    {
        $this->config = $config ?? DatabaseConfig::fromEnvironment();
    }

    /**
     * Get the active DatabaseConfig associated with this factory instance.
     */
    public function getConfig(): DatabaseConfig
    {
        return $this->config;
    }

    /**
     * Create a new PDO instance using the configured or provided DatabaseConfig.
     *
     * @param DatabaseConfig|array<string, mixed>|null $overrideConfig Optional override configuration.
     * @param array<int, mixed> $customOptions Optional additional PDO attributes (cannot override mandatory security attributes).
     * @throws DatabaseException If configuration is invalid or database connection fails.
     */
    public function createConnection(
        DatabaseConfig|array|null $overrideConfig = null,
        array $customOptions = []
    ): PDO {
        $resolvedConfig = match (true) {
            $overrideConfig instanceof DatabaseConfig => $overrideConfig,
            is_array($overrideConfig) && !empty($overrideConfig) => DatabaseConfig::fromArray(
                array_merge($this->config->toArray(), $overrideConfig)
            ),
            default => $this->config,
        };

        return self::establishConnection($resolvedConfig, $customOptions);
    }

    /**
     * Static factory method for backward compatibility with existing procedural callers.
     *
     * @param DatabaseConfig|array<string, mixed> $overrideConfig
     * @param array<int, mixed> $customOptions
     * @throws DatabaseException
     */
    public static function create(
        DatabaseConfig|array $overrideConfig = [],
        array $customOptions = []
    ): PDO {
        if ($overrideConfig instanceof DatabaseConfig) {
            return self::establishConnection($overrideConfig, $customOptions);
        }

        $factory = new self();
        return $factory->createConnection($overrideConfig, $customOptions);
    }

    /**
     * Internal connection establishment logic.
     *
     * @param array<int, mixed> $customOptions
     * @throws DatabaseException
     */
    private static function establishConnection(DatabaseConfig $dbConfig, array $customOptions = []): PDO
    {
        // 1. Start with custom options if any
        $options = $customOptions;

        // 2. Enforce MySQL charset and collation init command (cannot be disabled or bypassed by custom options)
        if ($dbConfig->getDriver() === 'mysql' && defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
            $requiredInitCommand = sprintf(
                "SET NAMES '%s' COLLATE '%s'",
                $dbConfig->getCharset(),
                $dbConfig->getCollation()
            );

            if (isset($customOptions[PDO::MYSQL_ATTR_INIT_COMMAND])) {
                $userInit = trim((string) $customOptions[PDO::MYSQL_ATTR_INIT_COMMAND]);
                if ($userInit !== '' && !str_contains($userInit, "SET NAMES '{$dbConfig->getCharset()}'")) {
                    $options[PDO::MYSQL_ATTR_INIT_COMMAND] = $requiredInitCommand . '; ' . $userInit;
                } else {
                    $options[PDO::MYSQL_ATTR_INIT_COMMAND] = $userInit !== '' ? $userInit : $requiredInitCommand;
                }
            } else {
                $options[PDO::MYSQL_ATTR_INIT_COMMAND] = $requiredInitCommand;
            }
        }

        // 3. Strictly enforce mandatory security attributes over any custom options
        foreach (self::MANDATORY_OPTIONS as $attribute => $mandatoryValue) {
            $options[$attribute] = $mandatoryValue;
        }

        try {
            return new PDO(
                $dbConfig->getDsn(),
                $dbConfig->getUsername(),
                $dbConfig->getPassword(),
                $options
            );
        } catch (PDOException $e) {
            $sanitizedMsg = ExceptionHandler::sanitize($e->getMessage());

            $errorInfo = sprintf(
                'Failed to connect to database [%s] on host [%s:%d]: %s',
                $dbConfig->getDatabase(),
                $dbConfig->getHost(),
                $dbConfig->getPort(),
                $sanitizedMsg
            );

            // Wrap in DatabaseException WITHOUT chaining the original PDOException as previous,
            // preventing plain-text credentials in PDO constructor arguments from leaking via getTrace() / exception chaining.
            throw new DatabaseException($errorInfo, (int) $e->getCode());
        }
    }
}
