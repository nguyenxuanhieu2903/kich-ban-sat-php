<?php

declare(strict_types=1);

namespace App\Features\Identity\Controllers;

use App\Core\Middleware\AuthMiddleware;
use App\Core\Middleware\CsrfMiddleware;
use App\Core\Request;
use App\Core\Response;
use App\Features\Identity\Repositories\UserRepository;
use App\Infrastructure\Database\DatabaseConnection;

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
     * GET /api/v1/auth/csrf-token
     */
    public function csrfToken(Request $request): Response
    {
        $token = CsrfMiddleware::getToken();
        return Response::success(['csrf_token' => $token], 'CSRF token retrieved successfully');
    }

    /**
     * POST /api/v1/auth/register
     */
    public function register(Request $request): Response
    {
        $body = $request->getBodyParams();

        $fullName = trim((string) ($body['full_name'] ?? $body['name'] ?? ''));
        $email = trim(strtolower((string) ($body['email'] ?? '')));
        $password = (string) ($body['password'] ?? '');
        $phone = isset($body['phone']) ? trim((string) $body['phone']) : null;

        $errors = [];
        if ($fullName === '') {
            $errors['full_name'] = ['Họ và tên không được để trống'];
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = ['Email không đúng định dạng'];
        }

        if (strlen($password) < 6) {
            $errors['password'] = ['Mật khẩu phải có ít nhất 6 ký tự'];
        }

        if (!empty($errors)) {
            return Response::fail('Dữ liệu không hợp lệ', 'VALIDATION_ERROR', 422, $errors);
        }

        // Check duplicate email
        $existing = $this->userRepo->findByEmail($email);
        if ($existing !== null) {
            return Response::fail('Email này đã được sử dụng trên hệ thống', 'EMAIL_ALREADY_EXISTS', 409);
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

        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_regenerate_id(true);
        }
        $_SESSION['user_id'] = $userId;
        $_SESSION['role_id'] = $user['role_id'] ?? 1;
        $_SESSION['role_code'] = $user['role_code'] ?? 'PLAYER';

        return Response::success([
            'user' => $user,
        ], 'Đăng ký tài khoản thành công', null, 201);
    }

    /**
     * POST /api/v1/auth/login
     */
    public function login(Request $request): Response
    {
        $body = $request->getBodyParams();

        $email = trim(strtolower((string) ($body['email'] ?? '')));
        $password = (string) ($body['password'] ?? '');

        if ($email === '' || $password === '') {
            return Response::fail('Vui lòng nhập đầy đủ email và mật khẩu', 'VALIDATION_ERROR', 422);
        }

        $user = $this->userRepo->findByEmail($email);
        if ($user === null) {
            return Response::fail('Email hoặc mật khẩu không chính xác', 'INVALID_CREDENTIALS', 401);
        }

        if (($user['status'] ?? '') !== 'ACTIVE') {
            return Response::fail('Tài khoản của bạn đang bị tạm khóa hoặc chưa kích hoạt', 'ACCOUNT_INACTIVE', 403);
        }

        if (!password_verify($password, (string) $user['password_hash'])) {
            return Response::fail('Email hoặc mật khẩu không chính xác', 'INVALID_CREDENTIALS', 401);
        }

        // Update last login
        $this->userRepo->update((int) $user['id'], [
            'last_login_at' => date('Y-m-d H:i:s'),
        ]);

        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_regenerate_id(true);
        }
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['role_id'] = (int) ($user['role_id'] ?? 1);
        $_SESSION['role_code'] = (string) ($user['role_code'] ?? 'PLAYER');

        unset($user['password_hash']);
        $user['role'] = $user['role_code'] ?? 'PLAYER';

        return Response::success([
            'user' => $user,
        ], 'Đăng nhập thành công');
    }

    /**
     * GET /api/v1/auth/me
     */
    public function me(Request $request): Response
    {
        $authUser = AuthMiddleware::authenticate($request);
        if ($authUser instanceof Response) {
            return $authUser;
        }

        unset($authUser['password_hash']);
        $authUser['role'] = $authUser['role_code'] ?? 'PLAYER';

        return Response::success([
            'user' => $authUser,
        ], 'Thông tin tài khoản');
    }

    /**
     * POST /api/v1/auth/logout
     */
    public function logout(Request $request): Response
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies') && !headers_sent()) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_destroy();
        }

        return Response::success(null, 'Đăng xuất thành công');
    }

    /**
     * PATCH /api/v1/auth/profile (UC-P01: Quản lý hồ sơ cá nhân)
     */
    public function updateProfile(Request $request): Response
    {
        $authUser = AuthMiddleware::authenticate($request);
        if ($authUser instanceof Response) {
            return $authUser;
        }

        $body = $request->getBodyParams();
        $fields = [];
        if (isset($body['full_name'])) {
            $fullName = trim((string) $body['full_name']);
            if ($fullName === '') {
                return Response::fail('Họ và tên không được để trống', 'VALIDATION_ERROR', 422);
            }
            $fields['full_name'] = $fullName;
        }

        if (isset($body['phone'])) {
            $fields['phone'] = trim((string) $body['phone']);
        }

        if (empty($fields)) {
            return Response::fail('Không có dữ liệu cập nhật', 'BAD_REQUEST', 400);
        }

        $this->userRepo->update((int) $authUser['id'], $fields);
        $updatedUser = $this->userRepo->findById((int) $authUser['id']);
        if ($updatedUser !== null) {
            unset($updatedUser['password_hash']);
        }

        return Response::success(['user' => $updatedUser], 'Cập nhật thông tin cá nhân thành công');
    }

    /**
     * POST /api/v1/auth/student-verification (UC-P02: Cập nhật thông tin xác minh HSSV)
     */
    public function requestStudentVerification(Request $request): Response
    {
        $authUser = AuthMiddleware::authenticate($request);
        if ($authUser instanceof Response) {
            return $authUser;
        }

        $body = $request->getBodyParams();
        $studentId = trim((string) ($body['student_id'] ?? $body['student_card_number'] ?? $body['student_card_id'] ?? ''));
        $schoolName = trim((string) ($body['school_name'] ?? $body['university'] ?? ''));

        if ($studentId === '') {
            return Response::fail('Mã số sinh viên không được để trống', 'VALIDATION_ERROR', 422);
        }

        $this->userRepo->update((int) $authUser['id'], [
            'student_verified' => 0,
        ]);

        return Response::success([
            'user_id' => (int) $authUser['id'],
            'student_id' => $studentId,
            'school_name' => $schoolName,
            'status' => 'PENDING_APPROVAL',
        ], 'Yêu cầu xác thực sinh viên đã được gửi tới quản trị viên');
    }
}
