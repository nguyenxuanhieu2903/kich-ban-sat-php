<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Features\GameroomManagement\Services\BackgroundJobService;
use App\Infrastructure\Config\Config;
use App\Infrastructure\Database\DatabaseConnection;
use App\Infrastructure\Logging\JobLogger;

Config::load(dirname(__DIR__));

$pdo = DatabaseConnection::getInstance();
$logger = new JobLogger();
$service = new BackgroundJobService($pdo, null, $logger);

// Parse CLI options
$options = getopt('', ['job:', 'retry:']);
$targetJob = strtolower((string) ($options['job'] ?? 'all'));
$maxRetries = max(1, min(10, (int) ($options['retry'] ?? 3)));

echo "=====================================================\n";
echo " KỊCH BẢN SÁT - BACKGROUND CRON RUNNER (V16)\n";
echo " Time: " . date('Y-m-d H:i:s') . "\n";
echo " Target Job: " . strtoupper($targetJob) . "\n";
echo " Max Retries: {$maxRetries}\n";
echo " Log file: " . $logger->getLogFilePath() . "\n";
echo "=====================================================\n\n";

$logger->info("CLI cron started: job={$targetJob}, retries={$maxRetries}");

try {
    switch ($targetJob) {
        case 'formation-timeout':
            $count = $service->executeWithRetry(fn () => $service->runFormationTimeout(), 'runFormationTimeout', $maxRetries);
            echo "[SUCCESS] Processed {$count} expired forming rooms.\n";
            break;

        case 'deposit-timeout':
            $count = $service->executeWithRetry(fn () => $service->runDepositTimeout(), 'runDepositTimeout', $maxRetries);
            echo "[SUCCESS] Processed {$count} deposit timeout participants.\n";
            break;

        case 'replacement-timeout':
            $count = $service->executeWithRetry(fn () => $service->runReplacementTimeout(), 'runReplacementTimeout', $maxRetries);
            echo "[SUCCESS] Processed {$count} expired replacement windows.\n";
            break;

        case 'dm-timeout':
            $count = $service->executeWithRetry(fn () => $service->runDMAssignmentTimeout(), 'runDMAssignmentTimeout', $maxRetries);
            echo "[SUCCESS] Processed {$count} expired DM assignments.\n";
            break;

        case 'all':
        default:
            $stats = $service->runAll();
            echo "[SUMMARY] Background jobs completed successfully:\n";
            echo "  - Formation Timeouts: {$stats['formation_timeouts']} room(s) cancelled.\n";
            echo "  - Deposit Timeouts: {$stats['deposit_timeouts']} participant(s) kicked.\n";
            echo "  - Replacement Timeouts: {$stats['replacement_timeouts']} window(s) expired.\n";
            echo "  - DM Assignment Timeouts: {$stats['dm_assignment_timeouts']} assignment(s) revoked.\n";
            break;
    }
} catch (\Throwable $e) {
    echo "[ERROR] Cron job failed: " . $e->getMessage() . "\n";
    $logger->error("CLI cron failed with exception", ['error' => $e->getMessage()]);
    exit(1);
}

echo "\n[DONE] Finished maintenance cycle.\n";
