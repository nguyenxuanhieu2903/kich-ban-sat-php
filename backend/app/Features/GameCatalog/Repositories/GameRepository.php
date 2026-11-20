<?php

declare(strict_types=1);

namespace App\Features\GameCatalog\Repositories;

use App\Infrastructure\Database\BaseRepository;
use PDO;

class GameRepository extends BaseRepository
{
    /**
     * Find game by ID.
     *
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        return $this->executeQuery(function () use ($id): ?array {
            $stmt = $this->pdo->prepare(
                'SELECT `id`, `title`, `slug`, `description`, `min_players`, `max_players`, `duration_minutes`, `is_active`, `created_at`, `updated_at`
                 FROM `games`
                 WHERE `id` = :id
                 LIMIT 1'
            );
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result !== false ? $result : null;
        });
    }

    /**
     * Find game by unique slug.
     *
     * @return array<string, mixed>|null
     */
    public function findBySlug(string $slug): ?array
    {
        return $this->executeQuery(function () use ($slug): ?array {
            $stmt = $this->pdo->prepare(
                'SELECT `id`, `title`, `slug`, `description`, `min_players`, `max_players`, `duration_minutes`, `is_active`, `created_at`, `updated_at`
                 FROM `games`
                 WHERE `slug` = :slug
                 LIMIT 1'
            );
            $stmt->bindValue(':slug', $slug, PDO::PARAM_STR);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result !== false ? $result : null;
        });
    }

    /**
     * List active games with pagination.
     *
     * @return list<array<string, mixed>>
     */
    public function listActive(int $limit = 20, int $offset = 0): array
    {
        return $this->executeQuery(function () use ($limit, $offset): array {
            $stmt = $this->pdo->prepare(
                'SELECT `id`, `title`, `slug`, `description`, `min_players`, `max_players`, `duration_minutes`, `is_active`, `created_at`, `updated_at`
                 FROM `games`
                 WHERE `is_active` = 1
                 ORDER BY `title` ASC
                 LIMIT :limit OFFSET :offset'
            );
            $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
            $stmt->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
            $stmt->execute();

            /** @var list<array<string, mixed>> $rows */
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $rows;
        });
    }

    /**
     * Get active prices for a game.
     *
     * @return list<array<string, mixed>>
     */
    public function getActivePrices(int $gameId): array
    {
        return $this->executeQuery(function () use ($gameId): array {
            $stmt = $this->pdo->prepare(
                'SELECT `id`, `game_id`, `price_tier_name`, `day_type`, `time_from`, `time_to`,
                        `base_price_per_player`, `is_active`, `created_at`, `updated_at`
                 FROM `game_prices`
                 WHERE `game_id` = :game_id AND `is_active` = 1
                 ORDER BY `price_tier_name` ASC'
            );
            $stmt->bindValue(':game_id', $gameId, PDO::PARAM_INT);
            $stmt->execute();

            /** @var list<array<string, mixed>> $rows */
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $rows;
        });
    }

    /**
     * Create a new game.
     *
     * @param array{
     *     title: string,
     *     slug: string,
     *     description?: string|null,
     *     min_players?: int,
     *     max_players?: int,
     *     duration_minutes?: int,
     *     is_active?: bool
     * } $data
     */
    public function create(array $data): int
    {
        return $this->executeQuery(function () use ($data): int {
            $stmt = $this->pdo->prepare(
                'INSERT INTO `games` (`title`, `slug`, `description`, `min_players`, `max_players`, `duration_minutes`, `is_active`, `created_at`, `updated_at`)
                 VALUES (:title, :slug, :description, :min_players, :max_players, :duration_minutes, :is_active, NOW(), NOW())'
            );

            $stmt->bindValue(':title', $data['title'], PDO::PARAM_STR);
            $stmt->bindValue(':slug', $data['slug'], PDO::PARAM_STR);
            $stmt->bindValue(':description', $data['description'] ?? null, ($data['description'] ?? null) === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':min_players', $data['min_players'] ?? 4, PDO::PARAM_INT);
            $stmt->bindValue(':max_players', $data['max_players'] ?? 8, PDO::PARAM_INT);
            $stmt->bindValue(':duration_minutes', $data['duration_minutes'] ?? 240, PDO::PARAM_INT);
            $stmt->bindValue(':is_active', ($data['is_active'] ?? true) ? 1 : 0, PDO::PARAM_INT);

            $stmt->execute();

            return (int) $this->pdo->lastInsertId();
        });
    }
}
