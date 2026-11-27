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
                'SELECT u.`id`, u.`role_id`, r.`code` AS `role_code`, r.`name` AS `role_name`,
                        u.`full_name` AS `name`, u.`full_name`, u.`email`, u.`password_hash`,
                        u.`phone`, u.`avatar_url`, u.`status`, u.`student_verified`, u.`email_verified_at`, u.`last_login_at`,
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
                'SELECT u.`id`, u.`role_id`, r.`code` AS `role_code`, r.`name` AS `role_name`,
                        u.`full_name` AS `name`, u.`full_name`, u.`email`, u.`password_hash`,
                        u.`phone`, u.`avatar_url`, u.`status`, u.`student_verified`, u.`email_verified_at`, u.`last_login_at`,
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
     * Find user by normalized phone number.
     *
     * @return array<string, mixed>|null
     */
    public function findByPhone(string $phone): ?array
    {
        $normalized = preg_replace('/[\s\.\-\(\)]+/', '', trim($phone)) ?? '';
        if ($normalized === '') {
            return null;
        }

        return $this->executeQuery(function () use ($normalized): ?array {
            $stmt = $this->pdo->prepare(
                "SELECT u.`id`, u.`role_id`, r.`code` AS `role_code`, r.`name` AS `role_name`,
                        u.`full_name` AS `name`, u.`full_name`, u.`email`, u.`password_hash`,
                        u.`phone`, u.`avatar_url`, u.`status`, u.`student_verified`, u.`email_verified_at`, u.`last_login_at`,
                        u.`created_at`, u.`updated_at`
                 FROM `users` u
                 LEFT JOIN `roles` r ON r.`id` = u.`role_id`
                 WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(u.`phone`, ''), ' ', ''), '-', ''), '.', ''), '(', ''), ')', '') = :phone
                 LIMIT 1"
            );
            $stmt->bindValue(':phone', $normalized, PDO::PARAM_STR);
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
     *     name?: string,
     *     full_name?: string,
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
                $roleStmt = $this->pdo->query("SELECT `id` FROM `roles` WHERE `code` = 'PLAYER' OR `name` = 'PLAYER' LIMIT 1");
                $resolved = $roleStmt !== false ? $roleStmt->fetchColumn() : false;
                if ($resolved !== false && $resolved !== null) {
                    $roleId = (int) $resolved;
                } else {
                    $this->pdo->exec("INSERT INTO `roles` (`code`, `name`, `description`) VALUES ('PLAYER', 'Player', 'Nguoi choi')");
                    $roleId = (int) $this->pdo->lastInsertId();
                }
            }

            $fullName = $data['full_name'] ?? $data['name'] ?? 'User';

            $stmt = $this->pdo->prepare(
                'INSERT INTO `users` (`role_id`, `full_name`, `email`, `password_hash`, `phone`, `status`, `created_at`, `updated_at`)
                 VALUES (:role_id, :full_name, :email, :password_hash, :phone, :status, NOW(), NOW())'
            );

            $stmt->bindValue(':role_id', $roleId, PDO::PARAM_INT);
            $stmt->bindValue(':full_name', $fullName, PDO::PARAM_STR);
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
            $allowedColumns = ['full_name', 'email', 'password_hash', 'phone', 'status', 'student_verified', 'email_verified_at', 'avatar_url', 'last_login_at'];
            if (isset($data['name']) && !isset($data['full_name'])) {
                $data['full_name'] = $data['name'];
                unset($data['name']);
            }

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
                'SELECT r.`id`, r.`code`, r.`name`, r.`description`, u.`created_at` AS `assigned_at`
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
                 SET `role_id` = (SELECT `id` FROM `roles` WHERE `code` = 'PLAYER' OR `name` = 'PLAYER' LIMIT 1), `updated_at` = NOW()
                 WHERE `id` = :user_id AND `role_id` = :role_id"
            );
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':role_id', $roleId, PDO::PARAM_INT);
            $stmt->execute();

            return true;
        });
    }

    /**
     * List users with pagination and filtering.
     *
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function listUsers(
        int $page = 1,
        int $perPage = 20,
        ?string $search = null,
        ?string $roleCode = null,
        ?string $status = null
    ): array {
        return $this->executeQuery(function () use ($page, $perPage, $search, $roleCode, $status): array {
            $where = ['1 = 1'];
            $params = [];

            if ($search !== null && trim($search) !== '') {
                $where[] = '(u.`full_name` LIKE :search OR u.`email` LIKE :search OR u.`phone` LIKE :search)';
                $params[':search'] = '%' . trim($search) . '%';
            }

            if ($roleCode !== null && trim($roleCode) !== '') {
                $where[] = 'r.`code` = :role_code';
                $params[':role_code'] = strtoupper(trim($roleCode));
            }

            if ($status !== null && trim($status) !== '') {
                $where[] = 'u.`status` = :status';
                $params[':status'] = strtoupper(trim($status));
            }

            $whereSql = implode(' AND ', $where);

            // Count total
            $countStmt = $this->pdo->prepare(
                "SELECT COUNT(*) FROM `users` u
                 LEFT JOIN `roles` r ON r.`id` = u.`role_id`
                 WHERE {$whereSql}"
            );
            foreach ($params as $k => $v) {
                $countStmt->bindValue($k, $v, PDO::PARAM_STR);
            }
            $countStmt->execute();
            $total = (int) $countStmt->fetchColumn();

            // Fetch items
            $offset = max(0, ($page - 1) * $perPage);
            $stmt = $this->pdo->prepare(
                "SELECT u.`id`, u.`role_id`, r.`code` AS `role_code`, r.`name` AS `role_name`,
                        u.`full_name` AS `name`, u.`full_name`, u.`email`,
                        u.`phone`, u.`avatar_url`, u.`status`, u.`student_verified`,
                        u.`student_card_status`, u.`student_card_url`, u.`student_verified_at`, u.`student_reject_reason`,
                        u.`email_verified_at`, u.`last_login_at`,
                        u.`created_at`, u.`updated_at`
                 FROM `users` u
                 LEFT JOIN `roles` r ON r.`id` = u.`role_id`
                 WHERE {$whereSql}
                 ORDER BY u.`id` ASC
                 LIMIT :limit OFFSET :offset"
            );
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v, PDO::PARAM_STR);
            }
            $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'items' => $items,
                'total' => $total,
            ];
        });
    }

    /**
     * Find role by code or ID.
     *
     * @return array<string, mixed>|null
     */
    public function findRole(int|string $roleIdOrCode): ?array
    {
        return $this->executeQuery(function () use ($roleIdOrCode): ?array {
            if (is_int($roleIdOrCode) || ctype_digit((string) $roleIdOrCode)) {
                $stmt = $this->pdo->prepare('SELECT `id`, `code`, `name`, `description` FROM `roles` WHERE `id` = :id LIMIT 1');
                $stmt->bindValue(':id', (int) $roleIdOrCode, PDO::PARAM_INT);
            } else {
                $stmt = $this->pdo->prepare('SELECT `id`, `code`, `name`, `description` FROM `roles` WHERE `code` = :code LIMIT 1');
                $stmt->bindValue(':code', strtoupper((string) $roleIdOrCode), PDO::PARAM_STR);
            }
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result !== false ? $result : null;
        });
    }
}
