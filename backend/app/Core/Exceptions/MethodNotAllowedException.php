<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

class MethodNotAllowedException extends HttpException
{
    /**
     * @param string[] $allowedMethods
     */
    public function __construct(array $allowedMethods = [], string $message = 'Method not allowed', ?\Throwable $previous = null)
    {
        $headers = [];
        if (!empty($allowedMethods)) {
            $headers['Allow'] = implode(', ', $allowedMethods);
        }
        parent::__construct($message, 405, $headers, $previous);
    }
}
