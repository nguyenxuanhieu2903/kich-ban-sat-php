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
                'message' => 'Failed to encode JSON response',
                'data' => null,
                'error' => [
                    'code' => 'JSON_ENCODE_ERROR',
                    'message' => 'Failed to encode JSON response',
                    'details' => null,
                ],
                'meta' => null,
            ]);
            $statusCode = 500;
        }

        return new self($encoded, $statusCode, $headers);
    }

    /**
     * Create an HTML response.
     *
     * @param array<string, string> $headers
     */
    public static function html(string $html, int $statusCode = 200, array $headers = []): self
    {
        return new self($html, $statusCode, array_merge(['Content-Type' => 'text/html; charset=utf-8'], $headers));
    }

    /**
     * Create a raw response with custom content type.
     *
     * @param array<string, string> $headers
     */
    public static function raw(string $content, int $statusCode = 200, array $headers = []): self
    {
        return new self($content, $statusCode, $headers);
    }

    /**
     * Standard Success Response Envelope (Kịch Bản Sát V16 Convention).
     */
    public static function success(
        mixed $data = null,
        string $message = 'Request completed',
        mixed $meta = null,
        int $statusCode = 200,
        array $headers = []
    ): self {
        return self::json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'error' => null,
            'meta' => $meta,
        ], $statusCode, $headers);
    }

    /**
     * Standard Paginated Response Envelope (Kịch Bản Sát V16 Convention).
     */
    public static function paginated(
        array $items,
        int $page,
        int $perPage,
        int $total,
        string $message = 'Retrieved successfully',
        array $headers = []
    ): self {
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        return self::json([
            'success' => true,
            'message' => $message,
            'data' => $items,
            'error' => null,
            'meta' => [
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'total_pages' => $totalPages,
                ],
            ],
        ], 200, $headers);
    }

    /**
     * Standard Error Response Envelope (Kịch Bản Sát V16 Convention).
     */
    public static function fail(
        string $message,
        string $code = 'BAD_REQUEST',
        int $statusCode = 400,
        ?array $details = null,
        mixed $meta = null,
        array $headers = []
    ): self {
        $errorObj = [
            'code' => $code,
            'message' => $message,
        ];
        if ($details !== null) {
            $errorObj['details'] = $details;
        }

        return self::json([
            'success' => false,
            'message' => $message,
            'data' => null,
            'error' => $errorObj,
            'meta' => $meta,
        ], $statusCode, $headers);
    }

    /**
     * Backward-compatible structured error response.
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
        $errCode = $code ?? self::getDefaultErrorCode($statusCode);
        $details = !empty($errors) ? $errors : null;

        return self::fail($message, $errCode, $statusCode, $details, null, $headers);
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
            422 => 'VALIDATION_ERROR',
            429 => 'TOO_MANY_REQUESTS',
            default => 'INTERNAL_SERVER_ERROR',
        };
    }
}
