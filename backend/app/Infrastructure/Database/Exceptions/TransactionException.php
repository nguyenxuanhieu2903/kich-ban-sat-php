<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Exceptions;

use App\Infrastructure\Database\DatabaseException;
use Throwable;

class TransactionException extends DatabaseException
{
    public function __construct(
        string $message = 'Database transaction failed',
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
