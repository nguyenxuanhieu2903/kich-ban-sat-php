<?php

declare(strict_types=1);

namespace App\Features\Identity\Repositories;

use App\Infrastructure\Database\BaseRepository;
use PDO;

class UserRepository extends BaseRepository
{
    /**
     * Find user by primary key ID.
     *
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        return $this->executeQuery(function () use ($id): ?array {
            $stmt = $this->pdo->prepare(
                'SELECT u.`id`, u.`role_id`, r.`name` AS `role_name`, u.`name`, u.`email`, u.`password_hash`,
                        u.`phone`, u.`avatar_url`, u.`bio`, u.`student_card_verified`,
                        u.`dm_experience_years`, u.`dm_rating_avg`, u.`status`, u.`email_verified_at`,
                        u.`created_at`, u.`updated_at`
                 FROM `users` u
                 LEFT JOIN `roles` r ON r.`id` = u.`role_id`
                 WHERE u.`id` = :id
                 LIMIT 1'
            );
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result !== false ? $result : null;
        });
    }

    /**
     * Find user by unique email.
     *
     * @return array<string, mixed>|null
     */
    public function findByEmail(string $email): ?array
    {
        return $this->executeQuery(function () use ($email): ?array {
            $stmt = $this->pdo->prepare(
                'SELECT u.`id`, u.`role_id`, r.`name` AS `role_name`, u.`name`, u.`email`, u.`password_hash`,
                        u.`phone`, u.`avatar_url`, u.`bio`, u.`student_card_verified`,
                        u.`dm_experience_years`, u.`dm_rating_avg`, u.`status`, u.`email_verified_at`,
                        u.`created_at`, u.`updated_at`
                 FROM `users` u
                 LEFT JOIN `roles` r ON r.`id` = u.`role_id`
                 WHERE u.`email` = :email
                 LIMIT 1'
            );
            $stmt->bindValue(':email', $email, PDO::PARAM_STR);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result !== false ? $result : null;
        });
    }

    /**
     * Create a new user record.
     *
     * @param array{
     *     role_id?: int,
     *     name: string,
     *     email: string,
     *     password_hash: string,
     *     phone?: string|null,
     *     status?: string
     * } $data
     * @return int Inserted ID
     */
    public function create(array $data): int
    {
        return $this->executeQuery(function () use ($data): int {
            $roleId = $data['role_id'] ?? null;
            if ($roleId === null) {
                $roleStmt = $this->pdo->query("SELECT `id` FROM `roles` WHERE `name` = 'PLAYER' LIMIT 1");
                $resolved = $roleStmt !== false ? $roleStmt->fetchColumn() : false;
                if ($resolved !== false && $resolved !== null) {
                    $roleId = (int) $resolved;
                } else {
                    $this->pdo->exec("INSERT INTO `roles` (`name`, `label`, `description`) VALUES ('PLAYER', 'Player', 'Standard Player Role')");
                    $roleId = (int) $this->pdo->lastInsertId();
                }
            }

            $stmt = $this->pdo->prepare(
                'INSERT INTO `users` (`role_id`, `name`, `email`, `password_hash`, `phone`, `status`, `created_at`, `updated_at`)
                 VALUES (:role_id, :name, :email, :password_hash, :phone, :status, NOW(), NOW())'
            );

            $stmt->bindValue(':role_id', $roleId, PDO::PARAM_INT);
            $stmt->bindValue(':name', $data['name'], PDO::PARAM_STR);
            $stmt->bindValue(':email', $data['email'], PDO::PARAM_STR);
            $stmt->bindValue(':password_hash', $data['password_hash'], PDO::PARAM_STR);
            $stmt->bindValue(':phone', $data['phone'] ?? null, ($data['phone'] ?? null) === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':status', $data['status'] ?? 'ACTIVE', PDO::PARAM_STR);

            $stmt->execute();

            return (int) $this->pdo->lastInsertId();
        });
    }

    /**
     * Update user by ID.
     *
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): bool
    {
        return $this->executeQuery(function () use ($id, $data): bool {
            $allowedColumns = ['name', 'email', 'password_hash', 'phone', 'status', 'email_verified_at'];
            $setParts = [];
            $bindings = [':id' => [$id, PDO::PARAM_INT]];

            foreach ($data as $column => $value) {
                if (!in_array($column, $allowedColumns, true)) {
                    continue;
                }
                $param = ':' . $column;
                $setParts[] = "`{$column}` = {$param}";

                if ($value === null) {
                    $bindings[$param] = [null, PDO::PARAM_NULL];
                } else {
                    $bindings[$param] = [(string) $value, PDO::PARAM_STR];
                }
            }

            if (empty($setParts)) {
                return false;
            }

            $setParts[] = '`updated_at` = NOW()';
            $sql = 'UPDATE `users` SET ' . implode(', ', $setParts) . ' WHERE `id` = :id';

            $stmt = $this->pdo->prepare($sql);
            foreach ($bindings as $param => [$val, $type]) {
                $stmt->bindValue($param, $val, $type);
            }

            $stmt->execute();
            return $stmt->rowCount() > 0;
        });
    }

    /**
     * Get user roles by user ID.
     *
     * @return list<array<string, mixed>>
     */
    public function getRoles(int $userId): array
    {
        return $this->executeQuery(function () use ($userId): array {
            $stmt = $this->pdo->prepare(
                'SELECT r.`id`, r.`name`, r.`label`, r.`description`, u.`created_at` AS `assigned_at`
                 FROM `roles` r
                 INNER JOIN `users` u ON u.`role_id` = r.`id`
                 WHERE u.`id` = :user_id
                 ORDER BY r.`name` ASC'
            );
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();

            /** @var list<array<string, mixed>> $rows */
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $rows;
        });
    }

    /**
     * Assign role to user.
     */
    public function assignRole(int $userId, int $roleId): bool
    {
        return $this->executeQuery(function () use ($userId, $roleId): bool {
            $stmt = $this->pdo->prepare(
                'UPDATE `users`
                 SET `role_id` = :role_id, `updated_at` = NOW()
                 WHERE `id` = :user_id'
            );
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':role_id', $roleId, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->rowCount() > 0;
        });
    }

    /**
     * Remove role from user.
     */
    public function removeRole(int $userId, int $roleId): bool
    {
        return $this->executeQuery(function () use ($userId, $roleId): bool {
            // Revert back to PLAYER role if target role matches
            $stmt = $this->pdo->prepare(
                "UPDATE `users`
                 SET `role_id` = (SELECT `id` FROM `roles` WHERE `name` = 'PLAYER' LIMIT 1), `updated_at` = NOW()
                 WHERE `id` = :user_id AND `role_id` = :role_id"
            );
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':role_id', $roleId, PDO::PARAM_INT);
            $stmt->execute();

            return true;
        });
    }
}
