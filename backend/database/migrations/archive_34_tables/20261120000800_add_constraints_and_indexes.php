<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddConstraintsAndIndexes extends AbstractMigration
{
    public function up(): void
    {
        // Proposal CP-01: Add covering index for physical room overlap check if not already present
        $exists = $this->fetchRow(
            "SELECT COUNT(1) as cnt FROM information_schema.statistics
             WHERE table_schema = DATABASE()
               AND table_name = 'physical_room_reservations'
               AND index_name = 'idx_prr_overlap_covering'"
        );

        if ((int) ($exists['cnt'] ?? 0) === 0) {
            $this->execute(<<<SQL
            ALTER TABLE `physical_room_reservations`
            ADD INDEX `idx_prr_overlap_covering` (
                `physical_room_id`,
                `reservation_date`,
                `status`,
                `start_time`,
                `end_time`
            );
            SQL);
        }
    }

    public function down(): void
    {
        $exists = $this->fetchRow(
            "SELECT COUNT(1) as cnt FROM information_schema.statistics
             WHERE table_schema = DATABASE()
               AND table_name = 'physical_room_reservations'
               AND index_name = 'idx_prr_overlap_covering'"
        );

        if ((int) ($exists['cnt'] ?? 0) > 0) {
            $this->execute("ALTER TABLE `physical_room_reservations` DROP INDEX `idx_prr_overlap_covering`");
        }
    }
}
