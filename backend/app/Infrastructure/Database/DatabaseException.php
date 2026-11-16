<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use App\Core\Exceptions\ExceptionHandler;
use RuntimeException;
use Throwable;

class DatabaseException extends RuntimeException
{
    public function __construct(string $message = 'Database error occurred', int $code = 0, ?Throwable $previous = null)
    {
        $sanitizedMessage = ExceptionHandler::sanitize($message);
        parent::__construct($sanitizedMessage, $code, $previous);
    }
}
