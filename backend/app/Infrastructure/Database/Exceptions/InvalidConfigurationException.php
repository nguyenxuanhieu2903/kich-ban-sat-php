<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Exceptions;

use App\Infrastructure\Database\DatabaseException;

class InvalidConfigurationException extends DatabaseException
{
    public static function missingRequired(string $field): self
    {
        return new self(sprintf('Database configuration error: [%s] is required and cannot be empty.', $field));
    }

    public static function invalidPort(mixed $port): self
    {
        $displayPort = is_scalar($port) ? (string) $port : gettype($port);
        return new self(sprintf('Database configuration error: Invalid DB_PORT [%s]. Must be an integer between 1 and 65535.', $displayPort));
    }

    public static function invalidType(string $field, string $expectedType, string $actualType): self
    {
        return new self(sprintf('Database configuration error: [%s] must be of type %s, %s given.', $field, $expectedType, $actualType));
    }

    public static function invalidHost(string $host, string $reason): self
    {
        return new self(sprintf('Database configuration error: Invalid DB_HOST [%s] - %s.', $host, $reason));
    }

    public static function invalidDatabase(string $database, string $reason): self
    {
        return new self(sprintf('Database configuration error: Invalid DB_DATABASE [%s] - %s.', $database, $reason));
    }

    public static function invalidCharset(string $charset): self
    {
        return new self(sprintf('Database configuration error: Invalid or unsupported DB_CHARSET [%s].', $charset));
    }

    /**
     * @param list<string> $supported
     */
    public static function unsupportedCharset(string $charset, array $supported): self
    {
        return new self(sprintf(
            'Database configuration error: Unsupported DB_CHARSET [%s]. Supported charsets: %s.',
            $charset,
            implode(', ', $supported)
        ));
    }

    /**
     * @param list<string> $supported
     */
    public static function incompatibleCollation(string $collation, string $charset, array $supported): self
    {
        return new self(sprintf(
            'Database configuration error: Incompatible collation [%s] for charset [%s]. Supported collations: %s.',
            $collation,
            $charset,
            implode(', ', $supported)
        ));
    }

    public static function invalidDriver(string $driver): self
    {
        return new self(sprintf('Database configuration error: Unsupported database driver [%s]. Only "mysql" is supported.', $driver));
    }

    public static function invalidFormat(string $field, string $reason): self
    {
        return new self(sprintf('Database configuration error: [%s] %s.', $field, $reason));
    }
}
