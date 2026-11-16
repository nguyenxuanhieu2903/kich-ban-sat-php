<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

class BadRequestException extends HttpException
{
    /** @var array<string, mixed> */
    protected array $errors;

    /**
     * @param array<string, mixed> $errors
     */
    public function __construct(string $message = 'Bad request', array $errors = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, 400, [], $previous);
        $this->errors = $errors;
    }

    /**
     * @return array<string, mixed>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
