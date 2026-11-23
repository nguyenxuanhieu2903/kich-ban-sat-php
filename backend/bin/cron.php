<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Features\GameroomManagement\Services\BackgroundJobService;
use App\Infrastructure\Config\Config;
use App\Infrastructure\Database\DatabaseConnection;

Config::load(dirname(__DIR__));

$pdo = DatabaseConnection::getInstance();
$service = new BackgroundJobService($pdo);

// Parse CLI options
$options = getopt('', ['job:']);
$targetJob = strtolower((string) ($options['job'] ?? 'all'));

echo "=====================================================\n";
echo " KỊCH BẢN SÁT - BACKGROUND CRON RUNNER (V16)\n";
echo " Time: " . date('Y-m-d H:i:s') . "\n";
echo " Target Job: " . strtoupper($targetJob) . "\n";
echo "=====================================================\n\n";

switch ($targetJob) {
    case 'formation-timeout':
        $count = $service->runFormationTimeout();
        echo "[SUCCESS] Processed {$count} expired forming rooms.\n";
        break;

    case 'deposit-timeout':
        $count = $service->runDepositTimeout();
        echo "[SUCCESS] Processed {$count} deposit timeout participants.\n";
        break;

    case 'replacement-timeout':
        $count = $service->runReplacementTimeout();
        echo "[SUCCESS] Processed {$count} expired replacement windows.\n";
        break;

    case 'proposal-timeout':
        $count = $service->runProposalTimeout();
        echo "[SUCCESS] Processed {$count} expired schedule proposals.\n";
        break;

    case 'all':
    default:
        $stats = $service->runAll();
        echo "[SUMMARY] Background jobs completed successfully:\n";
        echo "  - Formation Timeouts: {$stats['formation_timeouts']} room(s) cancelled.\n";
        echo "  - Deposit Timeouts: {$stats['deposit_timeouts']} participant(s) kicked.\n";
        echo "  - Replacement Timeouts: {$stats['replacement_timeouts']} window(s) expired.\n";
        echo "  - Proposal Timeouts: {$stats['proposal_timeouts']} proposal(s) expired.\n";
        echo "  - DM Assignment Timeouts: {$stats['dm_assignment_timeouts']} assignment(s) revoked.\n";
        break;
}

echo "\n[DONE] Finished maintenance cycle.\n";
