<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

use App\Core\Request;
use App\Core\Response;
use App\Infrastructure\Config\Config;
use Throwable;

class ExceptionHandler
{
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
                $message = $e->getMessage();
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
     * Log unexpected server errors safely.
     */
    protected static function logError(Throwable $e): void
    {
        // Safe sanitization: strip any potential passwords from logs
        $safeTrace = preg_replace('/(password|token|secret|key)=([^&\\s]+)/i', '$1=***', $e->getTraceAsString());
        $logMessage = sprintf(
            "[%s] %s in %s:%d\nTrace:\n%s\n",
            date('Y-m-d H:i:s'),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $safeTrace
        );

        error_log($logMessage);
    }
}
