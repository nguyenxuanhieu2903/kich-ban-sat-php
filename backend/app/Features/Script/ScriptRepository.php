<?php

declare(strict_types=1);

namespace App\Features\Script;

use App\Infrastructure\Database\ConnectionFactory;
use PDO;

class ScriptRepository
{
    protected PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? ConnectionFactory::create();
    }

    /**
     * Lấy danh sách kịch bản đang hoạt động có hỗ trợ bộ lọc.
     *
     * @param array{genre?: ?string, players?: ?int} $filters
     * @return array<int, array<string, mixed>>
     */
    public function findAll(array $filters = []): array
    {
        $sql = "SELECT id, code, title, genre, description, min_players, max_players, 
                       male_players, female_players, duration_minutes, price_per_player, 
                       difficulty, cover_image, status, created_at 
                FROM scripts 
                WHERE status = 'ACTIVE'";

        $params = [];

        if (!empty($filters['genre'])) {
            $sql .= " AND genre = :genre";
            $params['genre'] = $filters['genre'];
        }

        if (!empty($filters['players']) && is_numeric($filters['players'])) {
            $sql .= " AND min_players <= :players AND max_players >= :players";
            $params['players'] = (int) $filters['players'];
        }

        $sql .= " ORDER BY id ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lấy chi tiết kịch bản theo ID.
     *
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $sql = "SELECT id, code, title, genre, description, min_players, max_players, 
                       male_players, female_players, duration_minutes, price_per_player, 
                       difficulty, cover_image, status, created_at, updated_at 
                FROM scripts 
                WHERE id = :id AND status = 'ACTIVE' 
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /**
     * Lấy chi tiết kịch bản theo mã Code (VD: KBS-01).
     *
     * @return array<string, mixed>|null
     */
    public function findByCode(string $code): ?array
    {
        $sql = "SELECT id, code, title, genre, description, min_players, max_players, 
                       male_players, female_players, duration_minutes, price_per_player, 
                       difficulty, cover_image, status, created_at, updated_at 
                FROM scripts 
                WHERE code = :code 
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['code' => trim($code)]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }
}
