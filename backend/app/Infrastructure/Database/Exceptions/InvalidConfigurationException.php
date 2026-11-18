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

    public static function invalidCharset(string $charset): self
    {
        return new self(sprintf('Database configuration error: Invalid DB_CHARSET [%s]. Must be a valid charset identifier.', $charset));
    }

    public static function invalidDriver(string $driver): self
    {
        return new self(sprintf('Database configuration error: Unsupported database driver [%s].', $driver));
    }

    public static function invalidFormat(string $field, string $reason): self
    {
        return new self(sprintf('Database configuration error: [%s] %s.', $field, $reason));
    }
}
