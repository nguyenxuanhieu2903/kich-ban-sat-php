<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Features\GameroomManagement\Services\BackgroundJobService;
use App\Infrastructure\Config\Config;
use App\Infrastructure\Database\DatabaseConnection;
use PHPUnit\Framework\TestCase;

class BackgroundJobTest extends TestCase
{
    protected BackgroundJobService $jobService;
    protected \PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();
        $basePath = dirname(__DIR__, 2);
        Config::load($basePath);
        $this->pdo = DatabaseConnection::getInstance();
        $this->jobService = new BackgroundJobService($this->pdo);
    }

    public function testFormationTimeoutCancelsUnderpopulatedRoom(): void
    {
        // 1. Tạo phòng đã quá hạn gom người và chưa đủ min_players (6 người)
        $this->pdo->exec("INSERT INTO `game_rooms` (`game_id`, `game_configuration_id`, `creator_id`, `title`, `min_players`, `max_players`, `status`, `formation_deadline_at`, `created_at`, `updated_at`) VALUES (1, 1, 3, 'Phòng test formation timeout', 6, 8, 'FORMING', DATE_SUB(NOW(), INTERVAL 2 HOUR), NOW(), NOW())");
        $roomId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO `room_participants` (`game_room_id`, `user_id`, `status`, `joined_at`, `created_at`, `updated_at`) VALUES ({$roomId}, 3, 'PENDING_DEPOSIT', NOW(), NOW(), NOW())");

        // 2. Chạy job
        $cancelled = $this->jobService->runFormationTimeout();
        $this->assertGreaterThanOrEqual(1, $cancelled);

        // 3. Kiểm tra DB: phòng phải là 'CANCELLED', participant là 'CANCELLED_UNPAID'
        $roomStatus = $this->pdo->query("SELECT `status` FROM `game_rooms` WHERE `id` = {$roomId}")->fetchColumn();
        $this->assertSame('CANCELLED', $roomStatus);

        $partStatus = $this->pdo->query("SELECT `status` FROM `room_participants` WHERE `game_room_id` = {$roomId}")->fetchColumn();
        $this->assertSame('CANCELLED_UNPAID', $partStatus);
    }

    public function testDepositTimeoutKicksUnpaidParticipantAndDoesNotOpenReplacementInFormingRoom(): void
    {
        // 1. Tạo phòng FORMING và participant đã quá hạn nộp tiền cọc
        $this->pdo->exec("INSERT INTO `game_rooms` (`game_id`, `game_configuration_id`, `creator_id`, `title`, `min_players`, `max_players`, `status`, `formation_deadline_at`, `created_at`, `updated_at`) VALUES (1, 1, 3, 'Phòng test deposit timeout', 6, 8, 'FORMING', DATE_ADD(NOW(), INTERVAL 3 DAY), NOW(), NOW())");
        $roomId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO `room_participants` (`game_room_id`, `user_id`, `status`, `deposit_deadline_at`, `joined_at`, `created_at`, `updated_at`) VALUES ({$roomId}, 4, 'PENDING_DEPOSIT', DATE_SUB(NOW(), INTERVAL 1 HOUR), NOW(), NOW(), NOW())");
        $partId = (int) $this->pdo->lastInsertId();

        // 2. Chạy job
        $kicked = $this->jobService->runDepositTimeout();
        $this->assertGreaterThanOrEqual(1, $kicked);

        // 3. Participant bị chuyển thành 'CANCELLED_UNPAID'
        $partStatus = $this->pdo->query("SELECT `status` FROM `room_participants` WHERE `id` = {$partId}")->fetchColumn();
        $this->assertSame('CANCELLED_UNPAID', $partStatus);

        // 4. DEF-21: Phòng FORMING tuyệt đối không được mở replacement_status = 'OPEN'
        $rwStatus = $this->pdo->query("SELECT `replacement_status` FROM `game_rooms` WHERE `id` = {$roomId}")->fetchColumn();
        $this->assertNull($rwStatus);
    }

    public function testDepositTimeoutInScheduledRoomWithLeavingParticipantPreservesReplacement(): void
    {
        // 1. Tạo phòng SCHEDULED có 1 người xin rút lui (LEAVE_REQUESTED) và 1 người thay thế quá hạn cọc
        $this->pdo->exec("INSERT INTO `game_rooms` (`game_id`, `game_configuration_id`, `creator_id`, `title`, `min_players`, `max_players`, `status`, `formation_deadline_at`, `created_at`, `updated_at`) VALUES (1, 1, 3, 'Phòng scheduled test deposit timeout', 6, 8, 'SCHEDULED', DATE_ADD(NOW(), INTERVAL 3 DAY), NOW(), NOW())");
        $roomId = (int) $this->pdo->lastInsertId();

        // Thành viên xin rút lui
        $this->pdo->exec("INSERT INTO `room_participants` (`game_room_id`, `user_id`, `status`, `joined_at`, `created_at`, `updated_at`) VALUES ({$roomId}, 3, 'LEAVE_REQUESTED', NOW(), NOW(), NOW())");
        // Thành viên thay thế quá hạn cọc
        $this->pdo->exec("INSERT INTO `room_participants` (`game_room_id`, `user_id`, `status`, `deposit_deadline_at`, `joined_at`, `created_at`, `updated_at`) VALUES ({$roomId}, 4, 'PENDING_DEPOSIT', DATE_SUB(NOW(), INTERVAL 1 HOUR), NOW(), NOW(), NOW())");
        $partId = (int) $this->pdo->lastInsertId();

        // 2. Chạy job
        $kicked = $this->jobService->runDepositTimeout();
        $this->assertGreaterThanOrEqual(1, $kicked);

        // 3. Participant thay thế bị chuyển thành 'CANCELLED_UNPAID'
        $partStatus = $this->pdo->query("SELECT `status` FROM `room_participants` WHERE `id` = {$partId}")->fetchColumn();
        $this->assertSame('CANCELLED_UNPAID', $partStatus);

        // 4. Do phòng SCHEDULED và có người LEAVE_REQUESTED, replacement_status được mở 'OPEN'
        $rwStatus = $this->pdo->query("SELECT `replacement_status` FROM `game_rooms` WHERE `id` = {$roomId}")->fetchColumn();
        $this->assertSame('OPEN', $rwStatus);
    }

    public function testReplacementTimeoutClosesExpiredWindow(): void
    {
        // 1. Tạo phòng có Replacement Window đã quá hạn 24 tiếng
        $this->pdo->exec("INSERT INTO `game_rooms` (`game_id`, `game_configuration_id`, `creator_id`, `title`, `min_players`, `max_players`, `status`, `formation_deadline_at`, `replacement_status`, `replacement_deadline_at`, `created_at`, `updated_at`) VALUES (1, 1, 3, 'Phòng test replacement window timeout', 6, 8, 'CONFIRMED', DATE_ADD(NOW(), INTERVAL 3 DAY), 'OPEN', DATE_SUB(NOW(), INTERVAL 2 HOUR), NOW(), NOW())");
        $roomId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO `room_participants` (`game_room_id`, `user_id`, `status`, `joined_at`, `created_at`, `updated_at`) VALUES ({$roomId}, 4, 'LEAVE_REQUESTED', NOW(), NOW(), NOW())");

        // 2. Chạy job
        $closed = $this->jobService->runReplacementTimeout();
        $this->assertGreaterThanOrEqual(1, $closed);

        // 3. Phòng chuyển thành 'EXPIRED', participant thành 'LEFT'
        $rwStatus = $this->pdo->query("SELECT `replacement_status` FROM `game_rooms` WHERE `id` = {$roomId}")->fetchColumn();
        $this->assertSame('EXPIRED', $rwStatus);

        $partStatus = $this->pdo->query("SELECT `status` FROM `room_participants` WHERE `game_room_id` = {$roomId} AND `user_id` = 4")->fetchColumn();
        $this->assertSame('LEFT', $partStatus);
    }

    public function testDMAssignmentTimeoutRevokesPendingAssignmentAndNotifiesAdmin(): void
    {
        // 1. Tạo phòng và assignment PENDING đã quá 24h
        $this->pdo->exec("INSERT INTO `game_rooms` (`game_id`, `game_configuration_id`, `creator_id`, `title`, `min_players`, `max_players`, `status`, `formation_deadline_at`, `created_at`, `updated_at`) VALUES (1, 1, 3, 'Phòng test DM assignment timeout', 6, 8, 'SCHEDULED', DATE_ADD(NOW(), INTERVAL 3 DAY), NOW(), NOW())");
        $roomId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO `dm_assignments` (`game_room_id`, `dm_user_id`, `assigned_by`, `status`, `assigned_at`, `created_at`, `updated_at`) VALUES ({$roomId}, 2, 1, 'PENDING', DATE_SUB(NOW(), INTERVAL 25 HOUR), NOW(), NOW())");
        $assignId = (int) $this->pdo->lastInsertId();

        // 2. Chạy job timeout DM
        $revoked = $this->jobService->runDMAssignmentTimeout();
        $this->assertGreaterThanOrEqual(1, $revoked);

        // 3. Trạng thái phải là REVOKED
        $status = $this->pdo->query("SELECT `status` FROM `dm_assignments` WHERE `id` = {$assignId}")->fetchColumn();
        $this->assertSame('REVOKED', $status);

        $reason = $this->pdo->query("SELECT `reason` FROM `dm_assignments` WHERE `id` = {$assignId}")->fetchColumn();
        $this->assertStringContainsString('Timeout', (string) $reason);

        // 4. Admin nhận được thông báo
        $notiStmt = $this->pdo->query("SELECT COUNT(*) FROM `notifications` WHERE `type` = 'DM_TIMEOUT' AND `title` LIKE '%quá hạn%'");
        $this->assertGreaterThan(0, (int) $notiStmt->fetchColumn());

        // 5. Không tồn tại session tham chiếu đến assignment này
        $sessCount = (int) $this->pdo->query("SELECT COUNT(*) FROM `sessions` WHERE `dm_assignment_id` = {$assignId}")->fetchColumn();
        $this->assertSame(0, $sessCount);
    }
}
