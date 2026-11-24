<?php

declare(strict_types=1);

namespace App\Infrastructure\Logging;

class JobLogger
{
    protected string $logDir;

    public function __construct(?string $logDir = null)
    {
        $this->logDir = $logDir ?? (dirname(__DIR__, 3) . '/storage/logs');
        if (!is_dir($this->logDir)) {
            @mkdir($this->logDir, 0775, true);
        }
    }

    public function getLogFilePath(): string
    {
        return $this->logDir . '/cron-' . date('Y-m-d') . '.log';
    }

    public function log(string $level, string $message, array $context = []): void
    {
        $timestamp = date('Y-m-d H:i:s.v');
        $contextStr = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
        $line = "[{$timestamp}] [{$level}] {$message}{$contextStr}" . PHP_EOL;

        @file_put_contents($this->getLogFilePath(), $line, FILE_APPEND | LOCK_EX);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('INFO', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log('WARNING', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('ERROR', $message, $context);
    }

    /**
     * Log structured execution statistics of a cron job.
     */
    public function logJob(
        string $jobName,
        string $status,
        float $durationMs,
        int $processedCount,
        int $retryAttempts = 0,
        ?string $errorMessage = null
    ): void {
        $data = [
            'job' => $jobName,
            'status' => $status,
            'duration_ms' => round($durationMs, 2),
            'processed' => $processedCount,
            'retries' => $retryAttempts,
        ];

        if ($errorMessage !== null) {
            $data['error'] = $errorMessage;
        }

        $level = $status === 'SUCCESS' ? 'INFO' : 'ERROR';
        $this->log($level, "Cron Job Execution: {$jobName} [{$status}]", $data);
    }
}
