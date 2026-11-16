<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Exceptions\BadRequestException;

class Request
{
    protected string $method;
    protected string $path;
    /** @var array<string, mixed> */
    protected array $queryParams;
    /** @var array<string, mixed> */
    protected array $bodyParams;
    /** @var array<string, string> */
    protected array $routeParams = [];
    /** @var array<string, string> */
    protected array $headers;
    protected string $rawBody;

    /**
     * @param array<string, mixed> $queryParams
     * @param array<string, mixed> $bodyParams
     * @param array<string, string> $headers
     */
    public function __construct(
        string $method = 'GET',
        string $path = '/',
        array $queryParams = [],
        array $bodyParams = [],
        array $headers = [],
        string $rawBody = ''
    ) {
        $this->method = strtoupper(trim($method));
        $this->path = self::normalizePath($path);
        $this->queryParams = $queryParams;
        $this->bodyParams = $bodyParams;
        $this->headers = self::normalizeHeaders($headers);
        $this->rawBody = $rawBody;
    }

    /**
     * Parse raw JSON payload into an array of parameters.
     *
     * @return array<string, mixed>
     * @throws BadRequestException
     */
    public static function parseJsonPayload(string $rawBody): array
    {
        if (trim($rawBody) === '') {
            return [];
        }

        $decoded = json_decode($rawBody, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new BadRequestException('Malformed JSON in request payload: ' . json_last_error_msg());
        }

        if (!is_array($decoded)) {
            throw new BadRequestException('Malformed JSON in request payload: Payload must be a JSON object or array');
        }

        return $decoded;
    }

    /**
     * Capture the current HTTP request from superglobals and input stream.
     */
    public static function capture(?string $rawBody = null): self
    {
        $server = $_SERVER;
        $method = $server['REQUEST_METHOD'] ?? 'GET';
        $uri = $server['REQUEST_URI'] ?? '/';

        $parsedUrl = parse_url($uri, PHP_URL_PATH);
        $path = is_string($parsedUrl) ? $parsedUrl : '/';

        $headers = [];
        if (function_exists('getallheaders')) {
            $rawHeaders = getallheaders();
            if (is_array($rawHeaders)) {
                $headers = $rawHeaders;
            }
        } else {
            foreach ($server as $key => $value) {
                if (str_starts_with($key, 'HTTP_')) {
                    $headerName = str_replace('_', '-', substr($key, 5));
                    $headers[$headerName] = (string) $value;
                } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'], true)) {
                    $headerName = str_replace('_', '-', $key);
                    $headers[$headerName] = (string) $value;
                }
            }
        }

        if ($rawBody === null) {
            $input = file_get_contents('php://input');
            $rawBody = $input !== false ? $input : '';
        }

        // Case-insensitive Content-Type detection across server vars and headers
        $contentType = $server['CONTENT_TYPE'] ?? $server['HTTP_CONTENT_TYPE'] ?? '';
        if ($contentType === '') {
            foreach ($headers as $k => $v) {
                if (strcasecmp($k, 'Content-Type') === 0) {
                    $contentType = (string) $v;
                    break;
                }
            }
        }

        $bodyParams = [];

        if (str_contains(strtolower($contentType), 'application/json')) {
            $bodyParams = self::parseJsonPayload($rawBody);
        } elseif (!empty($_POST)) {
            $bodyParams = $_POST;
        }

        return new self($method, $path, $_GET, $bodyParams, $headers, $rawBody);
    }

    public static function normalizePath(string $path): string
    {
        $trimmed = '/' . trim($path, '/');
        return $trimmed === '' ? '/' : $trimmed;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @return array<string, mixed>
     */
    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->queryParams[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function getBodyParams(): array
    {
        return $this->bodyParams;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->bodyParams[$key] ?? $this->queryParams[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return array_merge($this->queryParams, $this->bodyParams);
    }

    public function getRawBody(): string
    {
        return $this->rawBody;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getHeader(string $name, ?string $default = null): ?string
    {
        $lower = strtolower($name);
        return $this->headers[$lower] ?? $default;
    }

    /**
     * @param array<string, string> $params
     */
    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    /**
     * @return array<string, string>
     */
    public function getRouteParams(): array
    {
        return $this->routeParams;
    }

    public function param(string $key, ?string $default = null): ?string
    {
        return $this->routeParams[$key] ?? $default;
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    private static function normalizeHeaders(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $key => $value) {
            $normalized[strtolower((string) $key)] = (string) $value;
        }
        return $normalized;
    }
}
