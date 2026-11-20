<?php

declare(strict_types=1);

namespace App\Features\Identity\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Features\Identity\Repositories\UserRepository;
use App\Infrastructure\Database\DatabaseConnection;
use PDO;

class AuthController
{
    protected UserRepository $userRepo;

    public function __construct(?UserRepository $userRepo = null)
    {
        $pdo = DatabaseConnection::getInstance();
        $this->userRepo = $userRepo ?? new UserRepository($pdo);

        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }
    }

    /**
     * POST /api/auth/register
     */
    public function register(Request $request): Response
    {
        $body = $request->getBodyParams();

        $fullName = trim((string) ($body['full_name'] ?? $body['name'] ?? ''));
        $email = trim(strtolower((string) ($body['email'] ?? '')));
        $password = (string) ($body['password'] ?? '');
        $phone = isset($body['phone']) ? trim((string) $body['phone']) : null;

        // Validation
        if ($fullName === '') {
            return Response::json([
                'success' => false,
                'message' => 'Họ và tên không được để trống',
            ], 422);
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return Response::json([
                'success' => false,
                'message' => 'Email không đúng định dạng',
            ], 422);
        }

        if (strlen($password) < 6) {
            return Response::json([
                'success' => false,
                'message' => 'Mật khẩu phải có ít nhất 6 ký tự',
            ], 422);
        }

        // Check duplicate email
        $existing = $this->userRepo->findByEmail($email);
        if ($existing !== null) {
            return Response::json([
                'success' => false,
                'message' => 'Email này đã được sử dụng trên hệ thống',
            ], 409);
        }

        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        $userId = $this->userRepo->create([
            'full_name' => $fullName,
            'email' => $email,
            'password_hash' => $passwordHash,
            'phone' => $phone,
            'status' => 'ACTIVE',
        ]);

        $user = $this->userRepo->findById($userId);
        if ($user !== null) {
            unset($user['password_hash']);
        }

        $_SESSION['user_id'] = $userId;

        return Response::json([
            'success' => true,
            'message' => 'Đăng ký tài khoản thành công',
            'data' => [
                'user' => $user,
            ],
        ], 201);
    }

    /**
     * POST /api/auth/login
     */
    public function login(Request $request): Response
    {
        $body = $request->getBodyParams();

        $email = trim(strtolower((string) ($body['email'] ?? '')));
        $password = (string) ($body['password'] ?? '');

        if ($email === '' || $password === '') {
            return Response::json([
                'success' => false,
                'message' => 'Vui lòng nhập đầy đủ email và mật khẩu',
            ], 422);
        }

        $user = $this->userRepo->findByEmail($email);
        if ($user === null) {
            return Response::json([
                'success' => false,
                'message' => 'Email hoặc mật khẩu không chính xác',
            ], 401);
        }

        if (($user['status'] ?? '') !== 'ACTIVE') {
            return Response::json([
                'success' => false,
                'message' => 'Tài khoản của bạn đang bị tạm khóa hoặc chưa kích hoạt',
            ], 403);
        }

        if (!password_verify($password, (string) $user['password_hash'])) {
            return Response::json([
                'success' => false,
                'message' => 'Email hoặc mật khẩu không chính xác',
            ], 401);
        }

        // Update last login
        $this->userRepo->update((int) $user['id'], [
            'email' => $user['email'],
        ]);

        $_SESSION['user_id'] = (int) $user['id'];
        unset($user['password_hash']);

        return Response::json([
            'success' => true,
            'message' => 'Đăng nhập thành công',
            'data' => [
                'user' => $user,
            ],
        ]);
    }

    /**
     * GET /api/auth/me
     */
    public function me(Request $request): Response
    {
        $userId = $this->resolveCurrentUserId($request);

        if ($userId === null) {
            return Response::json([
                'success' => false,
                'message' => 'Chưa đăng nhập hoặc phiên làm việc đã hết hạn',
            ], 401);
        }

        $user = $this->userRepo->findById($userId);
        if ($user === null) {
            return Response::json([
                'success' => false,
                'message' => 'Người dùng không tồn tại',
            ], 404);
        }

        unset($user['password_hash']);

        return Response::json([
            'success' => true,
            'data' => [
                'user' => $user,
            ],
        ]);
    }

    /**
     * POST /api/auth/logout
     */
    public function logout(Request $request): Response
    {
        if (isset($_SESSION['user_id'])) {
            unset($_SESSION['user_id']);
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_destroy();
        }

        return Response::json([
            'success' => true,
            'message' => 'Đăng xuất thành công',
        ]);
    }

    /**
     * Resolve user ID from session or Authorization Bearer header.
     */
    protected function resolveCurrentUserId(Request $request): ?int
    {
        if (!empty($_SESSION['user_id'])) {
            return (int) $_SESSION['user_id'];
        }

        $authHeader = $request->getHeader('authorization');
        if ($authHeader !== null && preg_match('/Bearer\s+(\d+)/i', $authHeader, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }
}
