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
     * Format a safe log message with structured metadata and sanitized secrets across the entire exception chain.
     */
    public static function formatLogMessage(Throwable $e): string
    {
        $frames = [];
        $current = $e;

        while ($current !== null) {
            $safeMessage = self::sanitize($current->getMessage());
            $safeTrace = self::sanitize($current->getTraceAsString());

            $frames[] = sprintf(
                "[%s] %s: %s in %s:%d\nTrace:\n%s",
                date('Y-m-d H:i:s'),
                get_class($current),
                $safeMessage,
                $current->getFile(),
                $current->getLine(),
                $safeTrace
            );

            $current = $current->getPrevious();
        }

        return implode("\nCaused by: ", $frames) . "\n";
    }

    /**
     * Sanitize sensitive keywords and patterns from string output.
     */
    public static function sanitize(string $input): string
    {
        // 1. Redact URI/DSN passwords: scheme://user:password@host
        $sanitized = preg_replace(
            '#://([^:]+):([^@]+)@#',
            '://$1:***@',
            $input
        );
        if (!is_string($sanitized)) {
            $sanitized = $input;
        }

        // 2. Redact Authorization headers: Authorization: Bearer <token> or Basic <token>
        $sanitized = preg_replace(
            '/((?:authorization|proxy-authorization)\s*:\s*(?:bearer|basic|token)\s+)[^\s,;\r\n]+/i',
            '$1***',
            $sanitized
        );
        if (!is_string($sanitized)) {
            $sanitized = $input;
        }

        // 3. Redact URL query parameters: ?token=xyz or &password=xyz or &key=xyz
        $sanitized = preg_replace(
            '/([?&](?:password|pwd|token|client_secret|secret|api_?key|key|access_token|refresh_token|auth)=)[^&\s#]+/i',
            '$1***',
            $sanitized
        );
        if (!is_string($sanitized)) {
            $sanitized = $input;
        }

        // 4. Redact key-value pairs (JSON quoted, array dumps, key=val, key: val, or key val)
        // Matches:
        // - Prefix: optional [ or [' or [" then keyword then optional ] or '] or "]
        // - Separator: => or : or is or = or whitespace
        // - Value: single-quoted string, double-quoted string, or unquoted value up to delimiter/whitespace
        $pattern = '/(?P<prefix>\[?(?:\'|")?\b(?:password|pwd|token|client_secret|secret|api_?key|access_token|refresh_token|auth|credential)\b(?:\'|")?\]?)(?P<sep>\s*(?:=>|:|is|=|\s+)\s*)(?P<val>\'[^\']*\'|"[^"]*"|(?!(?:password|pwd|token|client_secret|secret|api_?key|access_token|refresh_token|auth|credential)\s*(?:=|:|=>))[^\s,\r\n]+)/i';
        $sanitized = preg_replace_callback($pattern, function (array $m): string {
            $val = $m['val'];
            // If already redacted, avoid altering
            if ($val === '***' || $val === '"***"' || $val === "'***'" || str_starts_with($val, '***')) {
                return $m[0];
            }

            if (str_starts_with($val, '"') && str_ends_with($val, '"')) {
                $replacementVal = '"***"';
            } elseif (str_starts_with($val, "'") && str_ends_with($val, "'")) {
                $replacementVal = "'***'";
            } else {
                $replacementVal = '***';
            }

            return $m['prefix'] . $m['sep'] . $replacementVal;
        }, $sanitized);

        if (!is_string($sanitized)) {
            $sanitized = $input;
        }

        return $sanitized;
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
