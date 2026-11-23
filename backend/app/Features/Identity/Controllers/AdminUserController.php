<?php

declare(strict_types=1);

namespace App\Features\Identity\Controllers;

use App\Core\Middleware\AuthMiddleware;
use App\Core\Request;
use App\Core\Response;
use App\Features\Identity\Repositories\UserRepository;
use App\Infrastructure\Database\DatabaseConnection;
use PDO;

class AdminUserController
{
    protected UserRepository $userRepo;
    protected PDO $pdo;

    public function __construct(?UserRepository $userRepo = null, ?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DatabaseConnection::getInstance();
        $this->userRepo = $userRepo ?? new UserRepository($this->pdo);

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

            // Xử lý hệ quả khi khóa tài khoản (Account Suspension Lifecycle - Điểm 2)
            if ($newStatus === 'SUSPENDED' || $newStatus === 'INACTIVE') {
                $this->handleAccountSuspension($targetUserId);
            }
        }

        if (!empty($updateData)) {
            $this->userRepo->update($targetUserId, $updateData);
            (new \App\Shared\Services\AuditLogService($this->pdo))->log(
                (int) $auth['id'],
                'UPDATE_USER',
                'USER',
                $targetUserId,
                $updateData
            );
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

        (new \App\Shared\Services\AuditLogService($this->pdo))->log(
            (int) $auth['id'],
            'ASSIGN_ROLE',
            'USER',
            $targetUserId,
            ['role_id' => (int) $role['id'], 'role_code' => $role['code']]
        );

        $updated = $this->userRepo->findById($targetUserId);
        if ($updated !== null) {
            unset($updated['password_hash']);
        }

        return Response::success([
            'user' => $updated,
            'assigned_role' => $role,
        ], 'Gán vai trò cho người dùng thành công');
    }

    /**
     * Xử lý dọn dẹp và bảo toàn trạng thái khi tài khoản bị khóa (Account Suspension Lifecycle - Điểm 2)
     */
    protected function handleAccountSuspension(int $targetUserId): void
    {
        // 1. Kiểm tra các phòng FORMING mà user này tạo (Host)
        $hostRoomsStmt = $this->pdo->prepare('SELECT `id` FROM `game_rooms` WHERE `creator_id` = :uid AND `status` = \'FORMING\'');
        $hostRoomsStmt->execute([':uid' => $targetUserId]);
        $hostRooms = $hostRoomsStmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($hostRooms as $roomId) {
            $rId = (int) $roomId;
            // Tìm thành viên tiếp theo vào sớm nhất
            $nextMemberStmt = $this->pdo->prepare(
                'SELECT `user_id` FROM `room_participants` 
                 WHERE `game_room_id` = :rid AND `user_id` != :uid AND `status` IN (\'PENDING_DEPOSIT\', \'DEPOSITED\', \'LEAVE_REQUESTED\')
                 ORDER BY `joined_at` ASC, `id` ASC LIMIT 1'
            );
            $nextMemberStmt->execute([':rid' => $rId, ':uid' => $targetUserId]);
            $nextHostId = $nextMemberStmt->fetchColumn();

            if ($nextHostId !== false) {
                // Chuyển quyền Host
                $this->pdo->prepare('UPDATE `game_rooms` SET `creator_id` = :new_host, `updated_at` = NOW() WHERE `id` = :rid')
                    ->execute([':new_host' => (int) $nextHostId, ':rid' => $rId]);
                // Xóa tài khoản bị khóa khỏi phòng
                $this->pdo->prepare('DELETE FROM `room_participants` WHERE `game_room_id` = :rid AND `user_id` = :uid')
                    ->execute([':rid' => $rId, ':uid' => $targetUserId]);
            } else {
                // Không còn ai trong phòng -> Giải tán phòng
                $this->pdo->prepare('UPDATE `game_rooms` SET `status` = \'DISBANDED\', `updated_at` = NOW() WHERE `id` = :rid')
                    ->execute([':rid' => $rId]);
            }
        }

        // 2. Kiểm tra các phòng FORMING mà user này chỉ là Member (không phải Host)
        $memberRoomsStmt = $this->pdo->prepare(
            'SELECT gr.`id` FROM `game_rooms` gr
             INNER JOIN `room_participants` rp ON rp.`game_room_id` = gr.`id`
             WHERE rp.`user_id` = :uid1 AND gr.`creator_id` != :uid2 AND gr.`status` = \'FORMING\''
        );
        $memberRoomsStmt->execute([':uid1' => $targetUserId, ':uid2' => $targetUserId]);
        $memberRooms = $memberRoomsStmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($memberRooms as $roomId) {
            $rId = (int) $roomId;
            // Chỉ loại khỏi phòng, giảm confirmed_players và mở lại slot trống trên sảnh
            $this->pdo->prepare('DELETE FROM `room_participants` WHERE `game_room_id` = :rid AND `user_id` = :uid')
                ->execute([':rid' => $rId, ':uid' => $targetUserId]);
            $this->pdo->prepare('UPDATE `game_rooms` SET `confirmed_players` = GREATEST(1, `confirmed_players` - 1), `updated_at` = NOW() WHERE `id` = :rid')
                ->execute([':rid' => $rId]);
        }
    }
}
