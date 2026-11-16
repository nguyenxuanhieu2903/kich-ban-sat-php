<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

use App\Core\Request;
use App\Core\Response;
use App\Infrastructure\Config\Config;
use Throwable;

class ExceptionHandler
{
    /** @var (callable(string): void)|null */
    protected static $logger = null;

    /**
     * Set a custom logger callback (useful for testing or custom log channels).
     *
     * @param (callable(string): void)|null $logger
     */
    public static function setLogger(?callable $logger): void
    {
        self::$logger = $logger;
    }

    /**
     * Convert an uncaught Throwable into a clean JSON Response.
     */
    public static function handle(Throwable $e, ?Request $request = null): Response
    {
        $statusCode = 500;
        $headers = [];
        $errors = [];
        $errorCode = 'INTERNAL_SERVER_ERROR';

        if ($e instanceof HttpException) {
            $statusCode = $e->getStatusCode();
            $headers = $e->getHeaders();
            $message = $e->getMessage();

            if ($e instanceof BadRequestException) {
                $errorCode = 'BAD_REQUEST';
                $errors = $e->getErrors();
            } elseif ($e instanceof NotFoundException) {
                $errorCode = 'NOT_FOUND';
            } elseif ($e instanceof MethodNotAllowedException) {
                $errorCode = 'METHOD_NOT_ALLOWED';
            }
        } else {
            $isDebug = (bool) Config::get('APP_DEBUG', false);
            if ($isDebug) {
                $message = self::sanitize($e->getMessage());
                $errors = [
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ];
            } else {
                $message = 'Internal Server Error';
            }

            self::logError($e);
        }

        return Response::error($message, $statusCode, $errors, $errorCode, $headers);
    }

    /**
     * Format a safe log message with structured metadata and sanitized secrets.
     */
    public static function formatLogMessage(Throwable $e): string
    {
        $safeMessage = self::sanitize($e->getMessage());
        $safeTrace = self::sanitize($e->getTraceAsString());

        return sprintf(
            "[%s] %s: %s in %s:%d\nTrace:\n%s\n",
            date('Y-m-d H:i:s'),
            get_class($e),
            $safeMessage,
            $e->getFile(),
            $e->getLine(),
            $safeTrace
        );
    }

    /**
     * Sanitize sensitive keywords and patterns from string output.
     */
    public static function sanitize(string $input): string
    {
        // 1. Redact key=value, key: value, key is value, or key value
        $sanitized = preg_replace(
            '/(password|token|secret|key|pwd|credential|auth|bearer|access_token|refresh_token)\s*(?:[:=]|\s+is\s+|\s+)\s*([^\s,;&"\'\(\)]+)/i',
            '$1=***',
            $input
        );

        if (!is_string($sanitized)) {
            $sanitized = $input;
        }

        // 2. Redact JSON quoted properties: "password": "value"
        $sanitized = preg_replace(
            '/("(?:password|token|secret|key|pwd|credential|access_token|refresh_token)"\s*:\s*)"(?:[^"\\\\]|\\\\.)*"/i',
            '$1"***"',
            $sanitized
        );

        if (!is_string($sanitized)) {
            $sanitized = $input;
        }

        // 3. Redact URI/DSN passwords: scheme://user:password@host
        $sanitized = preg_replace(
            '#://([^:]+):([^@]+)@#',
            '://$1:***@',
            $sanitized
        );

        return is_string($sanitized) ? $sanitized : $input;
    }

    /**
     * Log unexpected server errors safely.
     */
    protected static function logError(Throwable $e): void
    {
        $logMessage = self::formatLogMessage($e);

        if (self::$logger !== null) {
            (self::$logger)($logMessage);
        } else {
            error_log($logMessage);
        }
    }
}
