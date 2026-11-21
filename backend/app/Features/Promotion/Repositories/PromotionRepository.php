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
                'SELECT `id`, `code`, `name`, `promotion_type`, `discount_type`, `discount_value`,
                        `start_at` AS `valid_from`, `end_at` AS `valid_to`, `start_at`, `end_at`,
                        `rules`, `is_active`, `created_at`, `updated_at`
                 FROM `promotions`
                 WHERE `code` = :code AND `is_active` = 1
                   AND (`start_at` <= NOW())
                   AND (`end_at` >= NOW())
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
                'SELECT `id`, `code`, `name`, `promotion_type`, `discount_type`, `discount_value`,
                        `start_at` AS `valid_from`, `end_at` AS `valid_to`, `start_at`, `end_at`,
                        `rules`, `is_active`, `created_at`, `updated_at`
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

    /**
     * List all active promotions with decoded rules.
     *
     * @return list<array<string, mixed>>
     */
    public function listActive(): array
    {
        return $this->executeQuery(function (): array {
            $stmt = $this->pdo->prepare(
                'SELECT `id`, `code`, `name`, `promotion_type`, `discount_type`, `discount_value`,
                        `start_at` AS `valid_from`, `end_at` AS `valid_to`, `start_at`, `end_at`,
                        `rules`, `is_active`, `created_at`, `updated_at`
                 FROM `promotions`
                 WHERE `is_active` = 1
                   AND (`start_at` IS NULL OR `start_at` <= NOW())
                   AND (`end_at` IS NULL OR `end_at` >= NOW())
                 ORDER BY `id` ASC'
            );
            $stmt->execute();

            /** @var list<array<string, mixed>> $rows */
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as &$row) {
                if (!empty($row['rules']) && is_string($row['rules'])) {
                    $row['rules'] = json_decode($row['rules'], true);
                }
            }
            return $rows;
        });
    }
}
