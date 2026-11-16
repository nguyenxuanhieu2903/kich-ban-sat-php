<?php

declare(strict_types=1);

namespace App\Core;

class Response
{
    protected int $statusCode;
    /** @var array<string, string> */
    protected array $headers;
    protected string $content;

    /**
     * @param array<string, string> $headers
     */
    public function __construct(string $content = '', int $statusCode = 200, array $headers = [])
    {
        $this->content = $content;
        $this->statusCode = $statusCode;
        $this->headers = array_merge([
            'Content-Type' => 'application/json; charset=utf-8',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'X-XSS-Protection' => '1; mode=block',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
        ], $headers);
    }

    /**
     * Create a standard JSON response.
     *
     * @param array<string, string> $headers
     */
    public static function json(mixed $data, int $statusCode = 200, array $headers = []): self
    {
        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            $encoded = json_encode([
                'success' => false,
                'error' => [
                    'code' => 'JSON_ENCODE_ERROR',
                    'message' => 'Failed to encode JSON response',
                ],
            ]);
            $statusCode = 500;
        }

        return new self($encoded, $statusCode, $headers);
    }

    /**
     * Create a structured error response.
     *
     * @param array<string, mixed> $errors
     * @param array<string, string> $headers
     */
    public static function error(
        string $message,
        int $statusCode = 400,
        array $errors = [],
        ?string $code = null,
        array $headers = []
    ): self {
        $body = [
            'success' => false,
            'error' => [
                'code' => $code ?? self::getDefaultErrorCode($statusCode),
                'message' => $message,
            ],
        ];

        if (!empty($errors)) {
            $body['error']['details'] = $errors;
        }

        return self::json($body, $statusCode, $headers);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Send headers and body to HTTP client.
     */
    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->statusCode);
            foreach ($this->headers as $name => $value) {
                header("{$name}: {$value}");
            }
        }

        echo $this->content;
    }

    private static function getDefaultErrorCode(int $statusCode): string
    {
        return match ($statusCode) {
            400 => 'BAD_REQUEST',
            401 => 'UNAUTHORIZED',
            403 => 'FORBIDDEN',
            404 => 'NOT_FOUND',
            405 => 'METHOD_NOT_ALLOWED',
            409 => 'CONFLICT',
            422 => 'UNPROCESSABLE_ENTITY',
            429 => 'TOO_MANY_REQUESTS',
            default => 'INTERNAL_SERVER_ERROR',
        };
    }
}
