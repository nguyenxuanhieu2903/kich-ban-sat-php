<?php

declare(strict_types=1);

namespace App\Features\Admin;

use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;

class AdminController
{
    private AdminService $service;

    public function __construct(?AdminService $service = null)
    {
        $this->service = $service ?? new AdminService();
    }

    public function listPhysicalRooms(Request $request): Response
    {
        $status = $request->query('status');

        return Response::json([
            'success' => true,
            'data' => $this->service->listPhysicalRooms(is_string($status) ? $status : null),
            'serverTime' => date('c'),
        ]);
    }

    public function createPhysicalRoom(Request $request): Response
    {
        $auth = $this->requireAdmin();
        $room = $this->service->createPhysicalRoom($auth['user_id'], $auth['role'], $request->getBodyParams());

        return Response::json([
            'success' => true,
            'message' => 'Tạo phòng vật lý thành công',
            'data' => $room,
            'serverTime' => date('c'),
        ], 201);
    }

    public function updatePhysicalRoom(Request $request, string $id): Response
    {
        $auth = $this->requireAdmin();
        $roomId = $this->parsePositiveInt($id, 'id');
        $room = $this->service->updatePhysicalRoom($roomId, $auth['user_id'], $auth['role'], $request->getBodyParams());

        return Response::json([
            'success' => true,
            'message' => 'Cập nhật phòng vật lý thành công',
            'data' => $room,
            'serverTime' => date('c'),
        ]);
    }

    public function listUsers(Request $request): Response
    {
        $auth = $this->requireAdmin();
        $role = $request->query('role');
        $isActiveRaw = $request->query('is_active');
        $isActive = ($isActiveRaw !== null && $isActiveRaw !== '') ? (int) $isActiveRaw : null;

        return Response::json([
            'success' => true,
            'data' => $this->service->listUsers($auth['user_id'], $auth['role'], is_string($role) ? $role : null, $isActive),
            'serverTime' => date('c'),
        ]);
    }

    public function updateUserStatus(Request $request, string $id): Response
    {
        $auth = $this->requireAdmin();
        $targetId = $this->parsePositiveInt($id, 'id');
        $body = $request->getBodyParams();
        if (!array_key_exists('is_active', $body)) {
            throw new HttpException('Thiếu tham số is_active', 400);
        }
        $isActive = filter_var($body['is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($isActive === null) {
            throw new HttpException('Tham số is_active phải là kiểu boolean', 400);
        }
        $reason = isset($body['reason']) && is_string($body['reason']) ? $body['reason'] : null;

        $user = $this->service->updateUserStatus($targetId, $auth['user_id'], $auth['role'], $isActive, $reason);

        return Response::json([
            'success' => true,
            'message' => 'Cập nhật trạng thái tài khoản thành công',
            'data' => $user,
            'serverTime' => date('c'),
        ]);
    }

    public function updateUserRole(Request $request, string $id): Response
    {
        $auth = $this->requireAdmin();
        $targetId = $this->parsePositiveInt($id, 'id');
        $body = $request->getBodyParams();
        $newRole = (string) ($body['role'] ?? '');
        $reason = isset($body['reason']) && is_string($body['reason']) ? $body['reason'] : null;

        $user = $this->service->updateUserRole($targetId, $auth['user_id'], $auth['role'], $newRole, $reason);

        return Response::json([
            'success' => true,
            'message' => 'Cập nhật vai trò người dùng thành công',
            'data' => $user,
            'serverTime' => date('c'),
        ]);
    }

    /** @return array{user_id: int, role: string} */
    private function requireAdmin(): array
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $role = strtoupper((string) ($_SESSION['role'] ?? ''));

        if ($userId <= 0 || $role === '') {
            throw new HttpException('Bạn cần đăng nhập để thực hiện thao tác này', 401);
        }
        if ($role !== 'ADMIN') {
            throw new HttpException('Chỉ quản trị viên mới có quyền thực hiện thao tác này', 403);
        }

        return ['user_id' => $userId, 'role' => $role];
    }

    private function parsePositiveInt(mixed $value, string $field): int
    {
        $parsed = filter_var($value, FILTER_VALIDATE_INT);
        if ($parsed === false || $parsed <= 0) {
            throw new HttpException("Tham số {$field} phải là số nguyên dương", 400);
        }

        return (int) $parsed;
    }
}