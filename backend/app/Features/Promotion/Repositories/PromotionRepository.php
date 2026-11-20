<?php

declare(strict_types=1);

namespace App\Features\Promotion\Repositories;

use App\Infrastructure\Database\BaseRepository;
use PDO;

class PromotionRepository extends BaseRepository
{
    /**
     * Find active promotion by unique promo code.
     *
     * @return array<string, mixed>|null
     */
    public function findByCode(string $code): ?array
    {
        return $this->executeQuery(function () use ($code): ?array {
            $stmt = $this->pdo->prepare(
                'SELECT `id`, `code`, `name`, `discount_type`, `discount_value`, `min_participants`,
                        `valid_from`, `valid_to`, `non_stackable`, `is_active`, `created_at`, `updated_at`
                 FROM `promotions`
                 WHERE `code` = :code AND `is_active` = 1
                   AND (`valid_from` IS NULL OR `valid_from` <= NOW())
                   AND (`valid_to` IS NULL OR `valid_to` >= NOW())
                 LIMIT 1'
            );
            $stmt->bindValue(':code', $code, PDO::PARAM_STR);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result !== false ? $result : null;
        });
    }

    /**
     * Find promotion by ID.
     *
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        return $this->executeQuery(function () use ($id): ?array {
            $stmt = $this->pdo->prepare(
                'SELECT `id`, `code`, `name`, `discount_type`, `discount_value`, `min_participants`,
                        `valid_from`, `valid_to`, `non_stackable`, `is_active`, `created_at`, `updated_at`
                 FROM `promotions`
                 WHERE `id` = :id
                 LIMIT 1'
            );
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result !== false ? $result : null;
        });
    }
}
