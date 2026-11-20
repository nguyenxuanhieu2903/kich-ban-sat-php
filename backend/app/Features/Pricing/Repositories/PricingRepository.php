<?php

declare(strict_types=1);

namespace App\Features\Pricing\Repositories;

use App\Infrastructure\Database\BaseRepository;
use PDO;

class PricingRepository extends BaseRepository
{
    /**
     * Find active prices for a game.
     *
     * @return list<array<string, mixed>>
     */
    public function findPricesByGame(int $gameId): array
    {
        return $this->executeQuery(function () use ($gameId): array {
            $stmt = $this->pdo->prepare(
                'SELECT `id`, `game_id`, `price_per_person` AS `base_price_per_player`, `price_per_person`,
                        `start_time` AS `time_from`, `end_time` AS `time_to`, `start_time`, `end_time`,
                        `effective_from`, `effective_until`, `status`,
                        (CASE WHEN `status` = \'ACTIVE\' THEN 1 ELSE 0 END) AS `is_active`,
                        `created_at`, `updated_at`
                 FROM `game_prices`
                 WHERE `game_id` = :game_id AND `status` = \'ACTIVE\'
                 ORDER BY `start_time` ASC'
            );
            $stmt->bindValue(':game_id', $gameId, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        });
    }

    /**
     * Find booking price snapshot by game room ID.
     *
     * @return array<string, mixed>|null
     */
    public function findSnapshotByGameRoomId(int $gameRoomId): ?array
    {
        return $this->executeQuery(function () use ($gameRoomId): ?array {
            $stmt = $this->pdo->prepare(
                'SELECT `id` AS `game_room_id`, `price_total_participants`, `price_subtotal`,
                        `price_discount_amount`, `price_final_amount`, `price_deposit_total`,
                        `price_remaining_total`, `price_promotion_id`, `price_details`, `price_calculated_at`
                 FROM `game_rooms`
                 WHERE `id` = :game_room_id
                 LIMIT 1'
            );
            $stmt->bindValue(':game_room_id', $gameRoomId, PDO::PARAM_INT);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result !== false ? $result : null;
        });
    }
}
