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
                'SELECT `id`, `name` AS `title`, `name`, `slug`, `description`, `image_url`,
                        `min_players`, `max_players`, `duration_minutes`, `status`,
                        COALESCE(`is_18_plus`, 0) AS `is_18_plus`,
                        COALESCE(`theme_code`, \'GENERAL\') AS `theme_code`,
                        (CASE WHEN `status` = \'ACTIVE\' THEN 1 ELSE 0 END) AS `is_active`,
                        `display_order`, `created_at`, `updated_at`
                 FROM `games`
                 WHERE `id` = :id
                 LIMIT 1'
            );
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result !== false) {
                $result['is_18_plus'] = (int) $result['is_18_plus'];
                return $result;
            }
            return null;
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
                'SELECT `id`, `name` AS `title`, `name`, `slug`, `description`, `image_url`,
                        `min_players`, `max_players`, `duration_minutes`, `status`,
                        COALESCE(`is_18_plus`, 0) AS `is_18_plus`,
                        COALESCE(`theme_code`, \'GENERAL\') AS `theme_code`,
                        (CASE WHEN `status` = \'ACTIVE\' THEN 1 ELSE 0 END) AS `is_active`,
                        `display_order`, `created_at`, `updated_at`
                 FROM `games`
                 WHERE `slug` = :slug
                 LIMIT 1'
            );
            $stmt->bindValue(':slug', $slug, PDO::PARAM_STR);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result !== false) {
                $result['is_18_plus'] = (int) $result['is_18_plus'];
                return $result;
            }
            return null;
        });
    }

    public function countActive(): int
    {
        return $this->executeQuery(function (): int {
            return (int) $this->pdo->query("SELECT COUNT(*) FROM `games` WHERE `status` = 'ACTIVE'")->fetchColumn();
        });
    }

    /**
     * List active games with pagination, optional player filtering, search, theme, and 18+ filter.
     *
     * @return list<array<string, mixed>>
     */
    public function listActive(
        int $limit = 50,
        int $offset = 0,
        ?int $players = null,
        ?string $search = null,
        ?string $theme = null,
        ?int $is18Plus = null
    ): array {
        return $this->executeQuery(function () use ($limit, $offset, $players, $search, $theme, $is18Plus): array {
            $where = ['g.`status` = \'ACTIVE\''];
            $params = [];

            if ($players !== null && $players > 0) {
                $where[] = 'g.`min_players` <= :p1 AND g.`max_players` >= :p2';
                $params[':p1'] = $players;
                $params[':p2'] = $players;
            }

            if ($search !== null && trim($search) !== '') {
                $where[] = '(g.`name` LIKE :s1 OR g.`description` LIKE :s2 OR EXISTS (SELECT 1 FROM `game_configurations` gc2 WHERE gc2.`game_id` = g.`id` AND gc2.`is_active` = 1 AND gc2.`configuration` LIKE :s3))';
                $searchTerm = '%' . trim($search) . '%';
                $params[':s1'] = $searchTerm;
                $params[':s2'] = $searchTerm;
                $params[':s3'] = $searchTerm;
            }

            if ($theme !== null && $theme !== '' && $theme !== 'ALL') {
                $where[] = 'g.`theme_code` = :theme';
                $params[':theme'] = $theme;
            }

            if ($is18Plus !== null) {
                $where[] = 'g.`is_18_plus` = :is18';
                $params[':is18'] = $is18Plus;
            }

            $sql = 'SELECT g.`id`, g.`name` AS `title`, g.`name`, g.`slug`, g.`description`, g.`image_url`,
                           g.`min_players`, g.`max_players`, g.`duration_minutes`, g.`status`,
                           COALESCE(g.`is_18_plus`, 0) AS `is_18_plus`,
                           COALESCE(g.`theme_code`, \'GENERAL\') AS `theme_code`,
                           1 AS `is_active`, g.`display_order`, g.`created_at`, g.`updated_at`,
                           MIN(gp.`price_per_person`) AS `min_price`,
                           MAX(gp.`price_per_person`) AS `max_price`,
                           MAX(CASE WHEN gp.`start_time` < \'20:00:00\' THEN gp.`price_per_person` END) AS `day_price`,
                           MAX(CASE WHEN gp.`start_time` >= \'20:00:00\' THEN gp.`price_per_person` END) AS `night_price`,
                           (SELECT gc.`configuration` FROM `game_configurations` gc
                            WHERE gc.`game_id` = g.`id` AND gc.`is_active` = 1
                            ORDER BY gc.`version` DESC LIMIT 1) AS `raw_configuration`
                    FROM `games` g
                    LEFT JOIN `game_prices` gp ON gp.`game_id` = g.`id` AND gp.`status` = \'ACTIVE\'
                    WHERE ' . implode(' AND ', $where) . '
                    GROUP BY g.`id`
                    ORDER BY g.`display_order` ASC, g.`id` ASC
                    LIMIT :limit OFFSET :offset';

            $stmt = $this->pdo->prepare($sql);
            foreach ($params as $param => $val) {
                $stmt->bindValue($param, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
            $stmt->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
            $stmt->execute();

            /** @var list<array<string, mixed>> $rows */
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as &$row) {
                $row['min_price'] = $row['min_price'] !== null ? (int) $row['min_price'] : null;
                $row['max_price'] = $row['max_price'] !== null ? (int) $row['max_price'] : null;
                $row['day_price'] = $row['day_price'] !== null ? (int) $row['day_price'] : $row['min_price'];
                $row['night_price'] = $row['night_price'] !== null ? (int) $row['night_price'] : $row['max_price'];
                $row['is_18_plus'] = (int) ($row['is_18_plus'] ?? 0);
                $cfg = !empty($row['raw_configuration']) ? json_decode((string) $row['raw_configuration'], true) : null;
                unset($row['raw_configuration']);
                $row['genre'] = is_array($cfg) && !empty($cfg['genre']) ? (string) $cfg['genre'] : null;
                $row['difficulty'] = is_array($cfg) && !empty($cfg['difficulty']) ? (string) $cfg['difficulty'] : null;
                $row['difficulty_level'] = is_array($cfg) && isset($cfg['difficulty_level']) ? (int) $cfg['difficulty_level'] : 4;
                $row['subtitle'] = is_array($cfg) && !empty($cfg['subtitle']) ? (string) $cfg['subtitle'] : null;
                $row['tagline'] = is_array($cfg) && !empty($cfg['tagline']) ? (string) $cfg['tagline'] : null;
                $row['badge'] = is_array($cfg) && !empty($cfg['badge']) ? (string) $cfg['badge'] : null;
                $row['props'] = is_array($cfg) && !empty($cfg['props']) ? $cfg['props'] : [];
            }
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

            /** @var list<array<string, mixed>> $rows */
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $rows;
        });
    }

    /**
     * Create a new game.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        return $this->executeQuery(function () use ($data): int {
            $name = $data['name'] ?? $data['title'] ?? 'Kịch bản mới';
            $status = $data['status'] ?? (($data['is_active'] ?? true) ? 'ACTIVE' : 'INACTIVE');

            $stmt = $this->pdo->prepare(
                'INSERT INTO `games` (`name`, `slug`, `description`, `image_url`, `min_players`, `max_players`, `duration_minutes`, `status`, `is_18_plus`, `theme_code`, `created_at`, `updated_at`)
                 VALUES (:name, :slug, :description, NULL, :min_players, :max_players, :duration_minutes, :status, :is_18_plus, :theme_code, NOW(), NOW())'
            );

            $stmt->bindValue(':name', (string) $name, PDO::PARAM_STR);
            $stmt->bindValue(':slug', (string) $data['slug'], PDO::PARAM_STR);
            $stmt->bindValue(':description', $data['description'] ?? null, ($data['description'] ?? null) === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':min_players', (int) ($data['min_players'] ?? 4), PDO::PARAM_INT);
            $stmt->bindValue(':max_players', (int) ($data['max_players'] ?? 8), PDO::PARAM_INT);
            $stmt->bindValue(':duration_minutes', (int) ($data['duration_minutes'] ?? 240), PDO::PARAM_INT);
            $stmt->bindValue(':status', (string) $status, PDO::PARAM_STR);
            $stmt->bindValue(':is_18_plus', !empty($data['is_18_plus']) ? 1 : 0, PDO::PARAM_INT);
            $stmt->bindValue(':theme_code', (string) ($data['theme_code'] ?? 'GENERAL'), PDO::PARAM_STR);

            $stmt->execute();

            return (int) $this->pdo->lastInsertId();
        });
    }
}
