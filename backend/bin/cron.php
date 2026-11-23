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

    case 'dm-assignment-timeout':
        $count = $service->runDmAssignmentTimeout();
        echo "[SUCCESS] Processed {$count} dm assignment timeout(s).\n";
        break;

    case 'session-reminder':
        $count = $service->runUpcomingSession24hReminder();
        echo "[SUCCESS] Sent {$count} 24h session reminder notification(s).\n";
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
        echo "  - Session Reminders: {$stats['session_reminders']} reminder notification(s) sent.\n";
        break;
}

echo "\n[DONE] Finished maintenance cycle.\n";
