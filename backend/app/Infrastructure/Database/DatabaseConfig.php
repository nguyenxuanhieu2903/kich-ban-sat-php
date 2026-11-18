<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use App\Infrastructure\Config\Config;
use App\Infrastructure\Database\Exceptions\InvalidConfigurationException;

/**
 * Immutable value object holding validated database connection configuration.
 */
final readonly class DatabaseConfig
{
    public const DEFAULT_DRIVER = 'mysql';
    public const DEFAULT_HOST = '127.0.0.1';
    public const DEFAULT_PORT = 3306;
    public const DEFAULT_CHARSET = 'utf8mb4';
    public const DEFAULT_COLLATION = 'utf8mb4_unicode_ci';

    /**
     * Allowlist of supported MySQL charsets and their compatible collations.
     *
     * @var array<string, array{default_collation: string, collations: list<string>}>
     */
    public const SUPPORTED_CHARSETS = [
        'utf8mb4' => [
            'default_collation' => 'utf8mb4_unicode_ci',
            'collations' => [
                'utf8mb4_unicode_ci',
                'utf8mb4_0900_ai_ci',
                'utf8mb4_general_ci',
                'utf8mb4_bin',
            ],
        ],
        'utf8' => [
            'default_collation' => 'utf8_unicode_ci',
            'collations' => [
                'utf8_unicode_ci',
                'utf8_general_ci',
                'utf8_bin',
            ],
        ],
        'latin1' => [
            'default_collation' => 'latin1_swedish_ci',
            'collations' => [
                'latin1_swedish_ci',
                'latin1_general_ci',
                'latin1_bin',
            ],
        ],
    ];

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
        ?string $collation = null
    ) {
        $trimmedDriver = strtolower(trim($driver));
        $trimmedHost = trim($host);
        $trimmedDatabase = trim($database);
        $trimmedUsername = trim($username);
        $normalizedCharset = strtolower(trim($charset));

        // 1. Validate driver
        if ($trimmedDriver !== 'mysql') {
            throw InvalidConfigurationException::invalidDriver($driver);
        }

        // 2. Validate host (prohibit control chars, delimiters, whitespace; validate IPv4, IPv6, RFC hostname)
        self::validateHost($trimmedHost);

        // 3. Validate port (1..65535)
        if ($port < 1 || $port > 65535) {
            throw InvalidConfigurationException::invalidPort($port);
        }

        // 4. Validate database name (MySQL identifier 1..64 chars, no delimiters/control chars)
        self::validateDatabaseName($trimmedDatabase);

        // 5. Validate username
        if ($trimmedUsername === '') {
            throw InvalidConfigurationException::missingRequired('DB_USERNAME');
        }
        if (preg_match('/[\x00-\x1F\x7F]/', $trimmedUsername)) {
            throw InvalidConfigurationException::invalidFormat('DB_USERNAME', 'cannot contain control characters');
        }

        // 6. Validate charset against allowlist
        if (!array_key_exists($normalizedCharset, self::SUPPORTED_CHARSETS)) {
            throw InvalidConfigurationException::unsupportedCharset(
                $charset,
                array_keys(self::SUPPORTED_CHARSETS)
            );
        }

        // 7. Resolve and validate collation compatibility
        $charsetMeta = self::SUPPORTED_CHARSETS[$normalizedCharset];
        $effectiveCollation = ($collation === null || trim($collation) === '')
            ? $charsetMeta['default_collation']
            : strtolower(trim($collation));

        if (!in_array($effectiveCollation, $charsetMeta['collations'], true)) {
            throw InvalidConfigurationException::incompatibleCollation(
                $collation ?? $effectiveCollation,
                $normalizedCharset,
                $charsetMeta['collations']
            );
        }

        $this->driver = $trimmedDriver;
        $this->host = $trimmedHost;
        $this->port = $port;
        $this->database = $trimmedDatabase;
        $this->username = $trimmedUsername;
        // Password must NEVER be trimmed or modified
        $this->password = $password;
        $this->charset = $normalizedCharset;
        $this->collation = $effectiveCollation;
    }

    /**
     * Create DatabaseConfig from environment variables via central Config loader.
     *
     * @throws InvalidConfigurationException
     */
    public static function fromEnvironment(): self
    {
        $rawDriver = Config::get('DB_CONNECTION', self::DEFAULT_DRIVER);
        if (!is_string($rawDriver)) {
            throw InvalidConfigurationException::invalidType('DB_CONNECTION', 'string', gettype($rawDriver));
        }

        $rawHost = Config::get('DB_HOST', self::DEFAULT_HOST);
        if (!is_string($rawHost)) {
            throw InvalidConfigurationException::invalidType('DB_HOST', 'string', gettype($rawHost));
        }

        $rawPort = Config::get('DB_PORT', self::DEFAULT_PORT);
        $port = self::parseAndValidatePort($rawPort);

        $rawDatabase = Config::get('DB_DATABASE', '');
        if (!is_string($rawDatabase)) {
            throw InvalidConfigurationException::invalidType('DB_DATABASE', 'string', gettype($rawDatabase));
        }

        $rawUsername = Config::get('DB_USERNAME', '');
        if (!is_string($rawUsername)) {
            throw InvalidConfigurationException::invalidType('DB_USERNAME', 'string', gettype($rawUsername));
        }

        $rawPassword = Config::get('DB_PASSWORD', '');
        if (!is_string($rawPassword)) {
            throw InvalidConfigurationException::invalidType('DB_PASSWORD', 'string', gettype($rawPassword));
        }

        $rawCharset = Config::get('DB_CHARSET', self::DEFAULT_CHARSET);
        if (!is_string($rawCharset)) {
            throw InvalidConfigurationException::invalidType('DB_CHARSET', 'string', gettype($rawCharset));
        }

        $rawCollation = Config::get('DB_COLLATION');
        if ($rawCollation !== null && !is_string($rawCollation)) {
            throw InvalidConfigurationException::invalidType('DB_COLLATION', 'string', gettype($rawCollation));
        }

        return new self(
            driver: $rawDriver,
            host: $rawHost,
            port: $port,
            database: $rawDatabase,
            username: $rawUsername,
            password: $rawPassword,
            charset: $rawCharset,
            collation: $rawCollation
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
        $driver = self::resolveString($data, 'driver', 'DB_CONNECTION', (string) Config::get('DB_CONNECTION', self::DEFAULT_DRIVER));
        $host = self::resolveString($data, 'host', 'DB_HOST', (string) Config::get('DB_HOST', self::DEFAULT_HOST));

        $rawPort = self::resolveValue($data, 'port', 'DB_PORT', Config::get('DB_PORT', self::DEFAULT_PORT));
        $port = self::parseAndValidatePort($rawPort);

        $database = self::resolveString($data, 'database', 'DB_DATABASE', (string) Config::get('DB_DATABASE', ''));
        $username = self::resolveString($data, 'username', 'DB_USERNAME', (string) Config::get('DB_USERNAME', ''));
        $password = self::resolvePassword($data, (string) Config::get('DB_PASSWORD', ''));

        $charset = self::resolveString($data, 'charset', 'DB_CHARSET', (string) Config::get('DB_CHARSET', self::DEFAULT_CHARSET));

        $rawCollation = self::resolveOptionalString($data, 'collation', 'DB_COLLATION', Config::get('DB_COLLATION'));

        return new self(
            driver: $driver,
            host: $host,
            port: $port,
            database: $database,
            username: $username,
            password: $password,
            charset: $charset,
            collation: $rawCollation
        );
    }

    /**
     * Parse and strictly validate port values.
     * Only accepts true integers or clean numeric digit strings within 1..65535.
     *
     * @throws InvalidConfigurationException
     */
    public static function parseAndValidatePort(mixed $port): int
    {
        if (is_int($port)) {
            $intPort = $port;
        } elseif (is_string($port) && preg_match('/^[0-9]+$/', $port) === 1) {
            $intPort = (int) $port;
        } else {
            throw InvalidConfigurationException::invalidPort($port);
        }

        if ($intPort < 1 || $intPort > 65535) {
            throw InvalidConfigurationException::invalidPort($intPort);
        }

        return $intPort;
    }

    /**
     * Validate host against DSN injection, control characters, and verify IP or hostname format.
     *
     * @throws InvalidConfigurationException
     */
    private static function validateHost(string $host): void
    {
        if ($host === '') {
            throw InvalidConfigurationException::missingRequired('DB_HOST');
        }

        // Prohibit null bytes, control characters, whitespace, and DSN delimiters
        if (preg_match('/[\x00-\x1F\x7F\s;=]/', $host)) {
            throw InvalidConfigurationException::invalidHost($host, 'contains invalid or control characters');
        }

        if (strlen($host) > 255) {
            throw InvalidConfigurationException::invalidHost($host, 'exceeds maximum length of 255 characters');
        }

        // 1. IPv4 check
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return;
        }

        // 2. IPv6 check (supports [::1] and ::1)
        $unbracketed = (str_starts_with($host, '[') && str_ends_with($host, ']'))
            ? substr($host, 1, -1)
            : $host;

        if (filter_var($unbracketed, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            return;
        }

        // 3. RFC 1123 / RFC 952 Hostname check
        if (preg_match('/^(?!-)[a-zA-Z0-9-]{1,63}(?<!-)(\.(?!-)[a-zA-Z0-9-]{1,63}(?<!-))*$/', $host)) {
            return;
        }

        throw InvalidConfigurationException::invalidHost($host, 'must be a valid hostname, IPv4, or IPv6 address');
    }

    /**
     * Validate database name against DSN injection and MySQL identifier rules.
     *
     * @throws InvalidConfigurationException
     */
    private static function validateDatabaseName(string $database): void
    {
        if ($database === '') {
            throw InvalidConfigurationException::missingRequired('DB_DATABASE');
        }

        // Prohibit null bytes, control characters, whitespace, DSN delimiters, quotes, and path separators
        if (preg_match('/[\x00-\x1F\x7F\s;=\/\\\.]/', $database)) {
            throw InvalidConfigurationException::invalidDatabase($database, 'contains invalid or control characters');
        }

        if (strlen($database) > 64) {
            throw InvalidConfigurationException::invalidDatabase($database, 'exceeds maximum length of 64 characters');
        }

        if (!preg_match('/^[a-zA-Z0-9_$-]{1,64}$/', $database)) {
            throw InvalidConfigurationException::invalidDatabase($database, 'contains disallowed characters for database name');
        }
    }

    private static function resolveValue(array $data, string $key, string $envKey, mixed $fallback): mixed
    {
        if (array_key_exists($key, $data)) {
            return $data[$key];
        }
        if (array_key_exists($envKey, $data)) {
            return $data[$envKey];
        }
        return $fallback;
    }

    private static function resolveString(array $data, string $key, string $envKey, ?string $fallback): string
    {
        $hasKey = array_key_exists($key, $data);
        $hasEnvKey = array_key_exists($envKey, $data);

        if (!$hasKey && !$hasEnvKey) {
            if ($fallback === null) {
                throw InvalidConfigurationException::missingRequired($envKey);
            }
            return $fallback;
        }

        $activeKey = $hasKey ? $key : $envKey;
        $val = $data[$activeKey];

        if (!is_string($val)) {
            throw InvalidConfigurationException::invalidType($envKey, 'string', gettype($val));
        }

        return $val;
    }

    private static function resolveOptionalString(array $data, string $key, string $envKey, mixed $fallback): ?string
    {
        $hasKey = array_key_exists($key, $data);
        $hasEnvKey = array_key_exists($envKey, $data);

        if (!$hasKey && !$hasEnvKey) {
            if ($fallback === null) {
                return null;
            }
            if (is_string($fallback)) {
                return $fallback;
            }
            throw InvalidConfigurationException::invalidType($envKey, 'string', gettype($fallback));
        }

        $activeKey = $hasKey ? $key : $envKey;
        $val = $data[$activeKey];

        if ($val === null) {
            return null;
        }

        if (!is_string($val)) {
            throw InvalidConfigurationException::invalidType($envKey, 'string', gettype($val));
        }

        return $val;
    }

    private static function resolvePassword(array $data, string $fallback): string
    {
        $hasKey = array_key_exists('password', $data);
        $hasEnvKey = array_key_exists('DB_PASSWORD', $data);

        if (!$hasKey && !$hasEnvKey) {
            return $fallback;
        }

        $activeKey = $hasKey ? 'password' : 'DB_PASSWORD';
        $val = $data[$activeKey];

        if (!is_string($val)) {
            throw InvalidConfigurationException::invalidType('DB_PASSWORD', 'string', gettype($val));
        }

        // Retain raw password without any trimming or mutation
        return $val;
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
     * Build standard PDO DSN string using validated parameters.
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
