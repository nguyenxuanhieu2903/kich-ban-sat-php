<?php

declare(strict_types=1);

namespace App\Features\Auth;

use App\Infrastructure\Database\DatabaseConnection;
use PDO;

class AuthRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DatabaseConnection::getInstance();
    }

    /** @return array<string, mixed>|null */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function create(string $fullName, string $email, string $passwordHash, ?string $phone): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO users (full_name, email, password_hash, phone, role, student_verified, is_active)
             VALUES (:full_name, :email, :password_hash, :phone, 'PLAYER', 0, 1)"
        );

        $stmt->execute([
            ':full_name' => $fullName,
            ':email' => $email,
            ':password_hash' => $passwordHash,
            ':phone' => $phone,
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}