<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Migration khởi tạo toàn bộ CSDL Kịch Bản Sát V3 (19 bảng nghiệp vụ + 2 bảng hạ tầng).
 */
final class InitKichBanSatV3Schema extends AbstractMigration
{
    public function up(): void
    {
        $sqlPath = dirname(__DIR__) . '/kich_ban_sat_v3_clean.sql';
        if (!file_exists($sqlPath)) {
            throw new RuntimeException("SQL file not found at: {$sqlPath}");
        }

        $sql = file_get_contents($sqlPath);
        if ($sql === false) {
            throw new RuntimeException("Cannot read SQL file at: {$sqlPath}");
        }

        // Thực thi toàn bộ schema V3
        $this->execute($sql);
    }

    public function down(): void
    {
        $this->execute(<<<SQL
        SET FOREIGN_KEY_CHECKS = 0;
        DROP TABLE IF EXISTS `outbox_events`;
        DROP TABLE IF EXISTS `idempotency_records`;
        DROP TABLE IF EXISTS `notifications`;
        DROP TABLE IF EXISTS `refunds`;
        DROP TABLE IF EXISTS `payment_transactions`;
        DROP TABLE IF EXISTS `player_payments`;
        DROP TABLE IF EXISTS `participant_price_snapshots`;
        DROP TABLE IF EXISTS `promotions`;
        DROP TABLE IF EXISTS `game_prices`;
        DROP TABLE IF EXISTS `sessions`;
        DROP TABLE IF EXISTS `schedule_responses`;
        DROP TABLE IF EXISTS `schedule_proposals`;
        DROP TABLE IF EXISTS `physical_rooms`;
        DROP TABLE IF EXISTS `venues`;
        DROP TABLE IF EXISTS `dm_assignments`;
        DROP TABLE IF EXISTS `room_participants`;
        DROP TABLE IF EXISTS `game_rooms`;
        DROP TABLE IF EXISTS `game_configurations`;
        DROP TABLE IF EXISTS `games`;
        DROP TABLE IF EXISTS `users`;
        DROP TABLE IF EXISTS `roles`;
        SET FOREIGN_KEY_CHECKS = 1;
        SQL);
    }
}
