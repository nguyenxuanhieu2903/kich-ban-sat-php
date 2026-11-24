<?php

declare(strict_types=1);

namespace App\Features\Identity\Controllers;

use App\Core\Middleware\AuthMiddleware;
use App\Core\Request;
use App\Core\Response;
use App\Features\Identity\Repositories\UserRepository;
use App\Infrastructure\Database\DatabaseConnection;
use App\Infrastructure\Logging\AuditLogger;
use PDO;

class AdminUserController
{
    protected UserRepository $userRepo;
    protected PDO $pdo;
    protected AuditLogger $auditLogger;

    public function __construct(?UserRepository $userRepo = null, ?PDO $pdo = null, ?AuditLogger $auditLogger = null)
    {
        $this->pdo = $pdo ?? DatabaseConnection::getInstance();
        $this->userRepo = $userRepo ?? new UserRepository($this->pdo);
        $this->auditLogger = $auditLogger ?? new AuditLogger($this->pdo);

        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }
    }

    /**
     * GET /api/v1/admin/users
     * Query: page, per_page, search, role, status
     */
    public function index(Request $request): Response
    {
        $auth = AuthMiddleware::authenticate($request, ['ADMIN']);
        if ($auth instanceof Response) {
            return $auth;
        }

        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(100, (int) $request->query('per_page', 20)));
        $search = $request->query('search') !== null ? (string) $request->query('search') : null;
        $roleCode = $request->query('role') !== null ? (string) $request->query('role') : null;
        $status = $request->query('status') !== null ? (string) $request->query('status') : null;

        $result = $this->userRepo->listUsers($page, $perPage, $search, $roleCode, $status);

        return Response::paginated(
            $result['items'],
            $page,
            $perPage,
            $result['total'],
            'Danh sách người dùng hệ thống'
        );
    }

    /**
     * GET /api/v1/admin/users/{id}
     */
    public function show(Request $request, string $id): Response
    {
        $auth = AuthMiddleware::authenticate($request, ['ADMIN']);
        if ($auth instanceof Response) {
            return $auth;
        }

        $userId = (int) $id;
        $user = $this->userRepo->findById($userId);

        if ($user === null) {
            return Response::fail('Không tìm thấy người dùng yêu cầu', 'USER_NOT_FOUND', 404);
        }

        unset($user['password_hash']);

        return Response::success([
            'user' => $user,
        ], 'Chi tiết người dùng');
    }

    /**
     * PATCH /api/v1/admin/users/{id}
     * Body: full_name, phone, status (ACTIVE, INACTIVE, SUSPENDED)
     */
    public function update(Request $request, string $id): Response
    {
        $auth = AuthMiddleware::authenticate($request, ['ADMIN']);
        if ($auth instanceof Response) {
            return $auth;
        }

        $targetUserId = (int) $id;
        $user = $this->userRepo->findById($targetUserId);

        if ($user === null) {
            return Response::fail('Không tìm thấy người dùng để cập nhật', 'USER_NOT_FOUND', 404);
        }

        $body = $request->getBodyParams();
        $updateData = [];

        if (isset($body['full_name'])) {
            $name = trim((string) $body['full_name']);
            if ($name === '') {
                return Response::fail('Họ và tên không được để trống', 'VALIDATION_ERROR', 422);
            }
            $updateData['full_name'] = $name;
        }

        if (isset($body['phone'])) {
            $updateData['phone'] = trim((string) $body['phone']);
        }

        if (isset($body['status'])) {
            $newStatus = strtoupper(trim((string) $body['status']));
            if (!in_array($newStatus, ['ACTIVE', 'INACTIVE', 'SUSPENDED'], true)) {
                return Response::fail('Trạng thái không hợp lệ (phải là ACTIVE, INACTIVE, SUSPENDED)', 'VALIDATION_ERROR', 422);
            }

            // Chống tự khóa tài khoản Admin của chính mình
            if ((int) $auth['id'] === $targetUserId && $newStatus !== 'ACTIVE') {
                return Response::fail('Admin không thể tự khóa hoặc vô hiệu hóa tài khoản của chính mình', 'CANNOT_SUSPEND_SELF', 400);
            }

            $updateData['status'] = $newStatus;
        }

        if (!empty($updateData)) {
            $this->userRepo->update($targetUserId, $updateData);
            $this->auditLogger->log('USER_STATUS_CHANGED', 'USER', $targetUserId, ['status' => $user['status'] ?? null], $updateData, (int) $auth['id'], $request);
        }

        $updated = $this->userRepo->findById($targetUserId);
        if ($updated !== null) {
            unset($updated['password_hash']);
        }

        return Response::success([
            'user' => $updated,
        ], 'Cập nhật thông tin người dùng thành công');
    }

    /**
     * PUT /api/v1/admin/users/{id}/role
     * Body: role_id OR role_code
     */
    public function assignRole(Request $request, string $id): Response
    {
        $auth = AuthMiddleware::authenticate($request, ['ADMIN']);
        if ($auth instanceof Response) {
            return $auth;
        }

        $targetUserId = (int) $id;
        $user = $this->userRepo->findById($targetUserId);

        if ($user === null) {
            return Response::fail('Không tìm thấy người dùng', 'USER_NOT_FOUND', 404);
        }

        $body = $request->getBodyParams();
        $roleTarget = $body['role_id'] ?? $body['role_code'] ?? $body['role'] ?? null;

        if ($roleTarget === null) {
            return Response::fail('Vui lòng cung cấp role_id hoặc role_code cần gán', 'VALIDATION_ERROR', 422);
        }

        $role = $this->userRepo->findRole($roleTarget);
        if ($role === null) {
            return Response::fail('Vai trò được chỉ định không tồn tại trên hệ thống', 'ROLE_NOT_FOUND', 404);
        }

        // Chống tự giáng chức Admin của chính mình nếu không còn admin nào khác
        if ((int) $auth['id'] === $targetUserId && strtoupper((string) $role['code']) !== 'ADMIN') {
            return Response::fail('Admin không thể tự tước quyền Quản trị viên của chính mình', 'CANNOT_DEMOTE_SELF', 400);
        }

        $this->userRepo->assignRole($targetUserId, (int) $role['id']);
        $this->auditLogger->log('USER_ROLE_CHANGED', 'USER', $targetUserId, ['role_id' => $user['role_id'] ?? null], ['role_id' => (int) $role['id'], 'role_code' => $role['code']], (int) $auth['id'], $request);

        $updated = $this->userRepo->findById($targetUserId);
        if ($updated !== null) {
            unset($updated['password_hash']);
        }

        return Response::success([
            'user' => $updated,
            'assigned_role' => $role,
        ], 'Gán vai trò cho người dùng thành công');
    }
}
