<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use RuntimeException;
use Throwable;

class DatabaseException extends RuntimeException
{
    public function __construct(string $message = 'Database error occurred', int $code = 0, ?Throwable $previous = null)
    {
        // Ensure sensitive credentials are stripped from message
        $sanitizedMessage = preg_replace('/(password|pwd)=([^;\\s]+)/i', '$1=***', $message);
        parent::__construct($sanitizedMessage ?? 'Database error occurred', $code, $previous);
    }
}
