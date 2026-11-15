<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

class HttpException extends \Exception
{
    protected int $statusCode;
    /** @var array<string, string> */
    protected array $headers;

    /**
     * @param array<string, string> $headers
     */
    public function __construct(string $message, int $statusCode = 500, array $headers = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, $statusCode, $previous);
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }
}
