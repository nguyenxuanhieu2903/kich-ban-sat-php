<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use App\Infrastructure\Config\Config;
use App\Infrastructure\Database\Exceptions\InvalidConfigurationException;

/**
 * Immutable value object holding validated database connection configuration.
 */
final class DatabaseConfig
{
    public const DEFAULT_DRIVER = 'mysql';
    public const DEFAULT_HOST = '127.0.0.1';
    public const DEFAULT_PORT = 3306;
    public const DEFAULT_CHARSET = 'utf8mb4';
    public const DEFAULT_COLLATION = 'utf8mb4_unicode_ci';

    private string $driver;
    private string $host;
    private int $port;
    private string $database;
    private string $username;
    private string $password;
    private string $charset;
    private string $collation;

    public function __construct(
        string $driver,
        string $host,
        int $port,
        string $database,
        string $username,
        string $password,
        string $charset = self::DEFAULT_CHARSET,
        string $collation = self::DEFAULT_COLLATION
    ) {
        $this->driver = trim($driver) !== '' ? trim($driver) : self::DEFAULT_DRIVER;
        $this->host = trim($host);
        $this->port = $port;
        $this->database = trim($database);
        $this->username = trim($username);
        $this->password = $password;
        $this->charset = trim($charset) !== '' ? trim($charset) : self::DEFAULT_CHARSET;
        $this->collation = trim($collation) !== '' ? trim($collation) : self::DEFAULT_COLLATION;

        $this->validate();
    }

    /**
     * Create DatabaseConfig from environment variables via central Config loader.
     *
     * @throws InvalidConfigurationException
     */
    public static function fromEnvironment(): self
    {
        $driver = (string) Config::get('DB_CONNECTION', self::DEFAULT_DRIVER);
        $host = (string) Config::get('DB_HOST', self::DEFAULT_HOST);

        $rawPort = Config::get('DB_PORT', self::DEFAULT_PORT);
        if (!is_numeric($rawPort)) {
            throw InvalidConfigurationException::invalidPort($rawPort);
        }
        $port = (int) $rawPort;

        $database = (string) Config::get('DB_DATABASE', '');
        $username = (string) Config::get('DB_USERNAME', '');
        $password = (string) Config::get('DB_PASSWORD', '');
        $charset = (string) Config::get('DB_CHARSET', self::DEFAULT_CHARSET);
        $collation = (string) Config::get('DB_COLLATION', self::DEFAULT_COLLATION);

        return new self(
            driver: $driver,
            host: $host,
            port: $port,
            database: $database,
            username: $username,
            password: $password,
            charset: $charset,
            collation: $collation
        );
    }

    /**
     * Create DatabaseConfig from an explicit associative array with environment fallbacks.
     *
     * @param array<string, mixed> $data
     * @throws InvalidConfigurationException
     */
    public static function fromArray(array $data): self
    {
        $driver = (string) ($data['driver'] ?? $data['DB_CONNECTION'] ?? Config::get('DB_CONNECTION', self::DEFAULT_DRIVER));
        $host = (string) ($data['host'] ?? $data['DB_HOST'] ?? Config::get('DB_HOST', self::DEFAULT_HOST));

        $rawPort = $data['port'] ?? $data['DB_PORT'] ?? Config::get('DB_PORT', self::DEFAULT_PORT);
        if (!is_numeric($rawPort)) {
            throw InvalidConfigurationException::invalidPort($rawPort);
        }
        $port = (int) $rawPort;

        $database = (string) ($data['database'] ?? $data['DB_DATABASE'] ?? Config::get('DB_DATABASE', ''));
        $username = (string) ($data['username'] ?? $data['DB_USERNAME'] ?? Config::get('DB_USERNAME', ''));
        $password = (string) ($data['password'] ?? $data['DB_PASSWORD'] ?? Config::get('DB_PASSWORD', ''));
        $charset = (string) ($data['charset'] ?? $data['DB_CHARSET'] ?? Config::get('DB_CHARSET', self::DEFAULT_CHARSET));
        $collation = (string) ($data['collation'] ?? $data['DB_COLLATION'] ?? Config::get('DB_COLLATION', self::DEFAULT_COLLATION));

        return new self(
            driver: $driver,
            host: $host,
            port: $port,
            database: $database,
            username: $username,
            password: $password,
            charset: $charset,
            collation: $collation
        );
    }

    /**
     * Validate mandatory fields, types, and value formats.
     *
     * @throws InvalidConfigurationException
     */
    private function validate(): void
    {
        if ($this->driver !== 'mysql') {
            throw InvalidConfigurationException::invalidDriver($this->driver);
        }

        if ($this->host === '') {
            throw InvalidConfigurationException::missingRequired('DB_HOST');
        }

        if ($this->port < 1 || $this->port > 65535) {
            throw InvalidConfigurationException::invalidPort($this->port);
        }

        if ($this->database === '') {
            throw InvalidConfigurationException::missingRequired('DB_DATABASE');
        }

        if ($this->username === '') {
            throw InvalidConfigurationException::missingRequired('DB_USERNAME');
        }

        // Validate charset: non-empty string, valid identifier pattern (flexible for utf8mb4, utf8, latin1, etc.)
        if ($this->charset === '' || !preg_match('/^[a-zA-Z0-9_-]+$/', $this->charset)) {
            throw InvalidConfigurationException::invalidCharset($this->charset);
        }

        // Validate collation: non-empty string, valid identifier pattern
        if ($this->collation === '' || !preg_match('/^[a-zA-Z0-9_-]+$/', $this->collation)) {
            throw InvalidConfigurationException::invalidFormat('DB_COLLATION', 'must be a valid identifier');
        }
    }

    public function getDriver(): string
    {
        return $this->driver;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    public function getDatabase(): string
    {
        return $this->database;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getCharset(): string
    {
        return $this->charset;
    }

    public function getCollation(): string
    {
        return $this->collation;
    }

    /**
     * Build standard PDO DSN string.
     */
    public function getDsn(): string
    {
        return sprintf(
            '%s:host=%s;port=%d;dbname=%s;charset=%s',
            $this->driver,
            $this->host,
            $this->port,
            $this->database,
            $this->charset
        );
    }

    /**
     * Convert configuration to associative array for connection drivers.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'driver' => $this->driver,
            'host' => $this->host,
            'port' => $this->port,
            'database' => $this->database,
            'username' => $this->username,
            'password' => $this->password,
            'charset' => $this->charset,
            'collation' => $this->collation,
        ];
    }

    /**
     * Export safe configuration array with masked password for logging/debug output.
     *
     * @return array<string, mixed>
     */
    public function toSafeArray(): array
    {
        $safe = $this->toArray();
        $safe['password'] = '***';
        return $safe;
    }

    /**
     * Prevent leaking plain-text database credentials in var_dump, print_r, or stack trace inspectors.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return $this->toSafeArray();
    }
}
