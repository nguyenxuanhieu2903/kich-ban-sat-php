<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

class NotFoundException extends HttpException
{
    public function __construct(string $message = 'Route or resource not found', ?\Throwable $previous = null)
    {
        parent::__construct($message, 404, [], $previous);
    }
}
