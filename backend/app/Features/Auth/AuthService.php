<?php

declare(strict_types=1);

namespace App\Features\Auth;

use App\Core\Exceptions\HttpException;

class AuthService
{
    private AuthRepository $repository;

    public function __construct(?AuthRepository $repository = null)
    {
        $this->repository = $repository ?? new AuthRepository();
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function register(array $input): array
    {
        $fullName = trim((string) ($input['full_name'] ?? ''));
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $password = (string) ($input['password'] ?? '');
        $phoneRaw = $input['phone'] ?? null;
        $phone = is_string($phoneRaw) && trim($phoneRaw) !== '' ? trim($phoneRaw) : null;

        if ($fullName === '' || mb_strlen($fullName) < 2 || mb_strlen($fullName) > 120) {
            throw new HttpException('Họ tên phải từ 2 đến 120 ký tự', 400);
        }

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($email) > 160) {
            throw new HttpException('Email không hợp lệ', 400);
        }

        if (strlen($password) < 8 || strlen($password) > 72) {
            throw new HttpException('Mật khẩu phải từ 8 đến 72 ký tự', 400);
        }

        if ($phone !== null && preg_match('/^[0-9+\-\s]{9,15}$/', $phone) !== 1) {
            throw new HttpException('Số điện thoại không hợp lệ', 400);
        }

        if ($this->repository->findByEmail($email) !== null) {
            throw new HttpException('Email đã được đăng ký', 409);
        }

        $passwordHash = password_hash($password, PASSWORD_BCRYPT);
        $userId = $this->repository->create($fullName, $email, $passwordHash, $phone);
        $user = $this->repository->findById($userId);

        if ($user === null) {
            throw new HttpException('Không thể khởi tạo tài khoản', 500);
        }

        $this->startSessionForUser($user);

        return $this->formatUser($user);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function login(array $input): array
    {
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $password = (string) ($input['password'] ?? '');

        if ($email === '' || $password === '') {
            throw new HttpException('Vui lòng nhập đầy đủ email và mật khẩu', 400);
        }

        $user = $this->repository->findByEmail($email);

        // Trả thông báo chung để tránh lộ email nào có tồn tại trong hệ thống
        if ($user === null || !password_verify($password, (string) $user['password_hash'])) {
            throw new HttpException('Email hoặc mật khẩu không chính xác', 401);
        }

        if ((int) $user['is_active'] !== 1) {
            throw new HttpException('Tài khoản của bạn đã bị khóa', 403);
        }

        $this->startSessionForUser($user);

        return $this->formatUser($user);
    }

    public function logout(): void
    {
        $_SESSION = [];

        if (session_status() === PHP_SESSION_ACTIVE) {
            if (ini_get('session.use_cookies') && !headers_sent()) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    [
                        'expires' => time() - 42000,
                        'path' => $params['path'],
                        'domain' => $params['domain'],
                        'secure' => (bool) $params['secure'],
                        'httponly' => (bool) $params['httponly'],
                        'samesite' => $params['samesite'] ?: 'Lax',
                    ]
                );
            }

            session_destroy();
        }
    }

    /** @return array<string, mixed> */
    public function me(): array
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            throw new HttpException('Bạn chưa đăng nhập', 401);
        }

        $user = $this->repository->findById($userId);
        if ($user === null || (int) $user['is_active'] !== 1) {
            $this->logout();
            throw new HttpException('Phiên đăng nhập không hợp lệ hoặc tài khoản đã bị khóa', 401);
        }

        // Đồng bộ lại role mới nhất từ DB vào session
        $_SESSION['role'] = (string) $user['role'];

        return $this->formatUser($user);
    }

    /** @param array<string, mixed> $user */
    private function startSessionForUser(array $user): void
    {
        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            session_regenerate_id(true);
        }

        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['role'] = (string) $user['role'];
        $_SESSION['email'] = (string) $user['email'];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function formatUser(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'full_name' => (string) $row['full_name'],
            'email' => (string) $row['email'],
            'phone' => $row['phone'] !== null ? (string) $row['phone'] : null,
            'role' => (string) $row['role'],
            'student_verified' => (bool) ((int) $row['student_verified']),
            'is_active' => (bool) ((int) $row['is_active']),
            'created_at' => (string) $row['created_at'],
        ];
    }
}