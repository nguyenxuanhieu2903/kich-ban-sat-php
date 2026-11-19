<?php

declare(strict_types=1);

namespace App\Features\Game;

use App\Infrastructure\Database\DatabaseConnection;
use PDO;

class GameRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DatabaseConnection::getInstance();
    }

    /** @return array<int, array<string, mixed>> */
    public function findAll(array $filters = []): array
    {
        $sql = 'SELECT * FROM games WHERE is_active = 1';
        $params = [];

        if (!empty($filters['q'])) {
            $sql .= ' AND title LIKE :q';
            $params[':q'] = '%' . trim((string) $filters['q']) . '%';
        }

        if (!empty($filters['category'])) {
            $sql .= ' AND category = :category';
            $params[':category'] = trim((string) $filters['category']);
        }

        if (isset($filters['is_18_plus']) && $filters['is_18_plus'] !== '') {
            $sql .= ' AND is_18_plus = :is_18_plus';
            $params[':is_18_plus'] = (int) $filters['is_18_plus'];
        }

        $sql .= ' ORDER BY id ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM games WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $sql = 'INSERT INTO games (title, slug, category, description, min_players, max_players, duration_minutes, is_18_plus, price_day_vnd, price_evening_vnd, deposit_vnd)
                VALUES (:title, :slug, :category, :description, :min_players, :max_players, :duration_minutes, :is_18_plus, :price_day_vnd, :price_evening_vnd, 50000)';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':title' => $data['title'],
            ':slug' => $data['slug'],
            ':category' => $data['category'],
            ':description' => $data['description'] ?? null,
            ':min_players' => (int) $data['min_players'],
            ':max_players' => (int) $data['max_players'],
            ':duration_minutes' => (int) ($data['duration_minutes'] ?? 210),
            ':is_18_plus' => !empty($data['is_18_plus']) ? 1 : 0,
            ':price_day_vnd' => (int) $data['price_day_vnd'],
            ':price_evening_vnd' => (int) $data['price_evening_vnd'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}