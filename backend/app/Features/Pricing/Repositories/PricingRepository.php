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
                'SELECT `id`, `game_id`, `price_tier_name`, `day_type`, `time_from`, `time_to`,
                        `base_price_per_player`, `is_active`, `created_at`, `updated_at`
                 FROM `game_prices`
                 WHERE `game_id` = :game_id AND `is_active` = 1
                 ORDER BY `time_from` ASC'
            );
            $stmt->bindValue(':game_id', $gameId, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        });
    }

    /**
     * Find booking price snapshot by session ID.
     *
     * @return array<string, mixed>|null
     */
    public function findSnapshotBySessionId(int $sessionId): ?array
    {
        return $this->executeQuery(function () use ($sessionId): ?array {
            $stmt = $this->pdo->prepare(
                'SELECT `id`, `session_id`, `game_price_id`, `promotion_id`, `original_base_price`,
                        `discount_amount`, `final_price`, `required_deposit_per_player`,
                        `total_deposit_expected`, `remaining_balance_expected`, `snapshot_at`, `created_at`
                 FROM `booking_price_snapshots`
                 WHERE `session_id` = :session_id
                 LIMIT 1'
            );
            $stmt->bindValue(':session_id', $sessionId, PDO::PARAM_INT);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result !== false ? $result : null;
        });
    }

    /**
     * Store an immutable booking price snapshot for a session.
     *
     * @param array{
     *     session_id: int,
     *     game_price_id?: int|null,
     *     promotion_id?: int|null,
     *     original_base_price: int,
     *     discount_amount?: int,
     *     final_price: int,
     *     required_deposit_per_player?: int,
     *     total_deposit_expected: int,
     *     remaining_balance_expected: int
     * } $data
     */
    public function createSnapshot(array $data): int
    {
        return $this->executeQuery(function () use ($data): int {
            $stmt = $this->pdo->prepare(
                'INSERT INTO `booking_price_snapshots`
                    (`session_id`, `game_price_id`, `promotion_id`, `original_base_price`,
                     `discount_amount`, `final_price`, `required_deposit_per_player`,
                     `total_deposit_expected`, `remaining_balance_expected`, `snapshot_at`, `created_at`)
                 VALUES
                    (:session_id, :game_price_id, :promotion_id, :original_base_price,
                     :discount_amount, :final_price, :required_deposit_per_player,
                     :total_deposit_expected, :remaining_balance_expected, NOW(), NOW())'
            );

            $stmt->bindValue(':session_id', $data['session_id'], PDO::PARAM_INT);
            $stmt->bindValue(':game_price_id', $data['game_price_id'] ?? null, ($data['game_price_id'] ?? null) !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $stmt->bindValue(':promotion_id', $data['promotion_id'] ?? null, ($data['promotion_id'] ?? null) !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $stmt->bindValue(':original_base_price', $data['original_base_price'], PDO::PARAM_INT);
            $stmt->bindValue(':discount_amount', $data['discount_amount'] ?? 0, PDO::PARAM_INT);
            $stmt->bindValue(':final_price', $data['final_price'], PDO::PARAM_INT);
            $stmt->bindValue(':required_deposit_per_player', $data['required_deposit_per_player'] ?? 50000, PDO::PARAM_INT);
            $stmt->bindValue(':total_deposit_expected', $data['total_deposit_expected'], PDO::PARAM_INT);
            $stmt->bindValue(':remaining_balance_expected', $data['remaining_balance_expected'], PDO::PARAM_INT);

            $stmt->execute();
            return (int) $this->pdo->lastInsertId();
        });
    }
}
