<?php

declare(strict_types=1);

namespace App\Features\Admin;

use App\Core\Exceptions\BadRequestException;
use App\Core\Exceptions\HttpException;
use App\Core\Exceptions\NotFoundException;
use App\Features\Audit\AuditLogService;
use App\Features\Notification\NotificationService;
use App\Infrastructure\Database\DatabaseConnection;
use PDO;

class AdminService
{
    private const ALLOWED_ROOM_STATUSES = ['AVAILABLE', 'MAINTENANCE', 'INACTIVE'];
    private const ALLOWED_USER_ROLES = ['PLAYER', 'DM', 'ADMIN'];

    private PDO $pdo;
    private AuditLogService $audit;
    private NotificationService $notifications;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DatabaseConnection::getInstance();
        $this->audit = new AuditLogService($this->pdo);
        $this->notifications = new NotificationService($this->pdo);
    }

    /** @return array<int, array<string, mixed>> */
    public function listPhysicalRooms(?string $status = null): array
    {
        $sql = 'SELECT * FROM physical_rooms';
        $params = [];

        if ($status !== null && trim($status) !== '') {
            $sql .= ' WHERE status = :status';
            $params[':status'] = strtoupper(trim($status));
        }
        $sql .= ' ORDER BY id ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return array_map(fn (array $row): array => $this->formatPhysicalRoom($row), $stmt->fetchAll());
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function createPhysicalRoom(int $actorUserId, string $actorRole, array $input): array
    {
        $this->requireAdmin($actorUserId, $actorRole);

        $name = trim((string) ($input['name'] ?? ''));
        $capacity = (int) ($input['capacity'] ?? 0);
        $theme = isset($input['theme']) && is_string($input['theme']) && trim($input['theme']) !== ''
            ? mb_substr(trim($input['theme']), 0, 100)
            : null;
        $status = strtoupper(trim((string) ($input['status'] ?? 'AVAILABLE')));

        if ($name === '') {
            throw new BadRequestException('Tên phòng vật lý không được để trống');
        }
        if ($capacity < 2 || $capacity > 30) {
            throw new BadRequestException('Sức chứa phòng vật lý phải từ 2 đến 30 người (tính cả DM)');
        }
        if (!in_array($status, self::ALLOWED_ROOM_STATUSES, true)) {
            throw new BadRequestException('Trạng thái phòng chỉ chấp nhận AVAILABLE, MAINTENANCE hoặc INACTIVE');
        }

        $dup = $this->pdo->prepare('SELECT id FROM physical_rooms WHERE name = :name LIMIT 1');
        $dup->execute([':name' => $name]);
        if ($dup->fetch() !== false) {
            throw new HttpException('Tên phòng vật lý đã tồn tại', 409);
        }

        $this->pdo->beginTransaction();
        try {
            $ins = $this->pdo->prepare(
                'INSERT INTO physical_rooms (name, capacity, theme, status) VALUES (:name, :capacity, :theme, :status)'
            );
            $ins->execute([
                ':name' => mb_substr($name, 0, 100),
                ':capacity' => $capacity,
                ':theme' => $theme,
                ':status' => $status,
            ]);
            $roomId = (int) $this->pdo->lastInsertId();

            $this->audit->record($actorUserId, 'PHYSICAL_ROOM_CREATED', 'PHYSICAL_ROOM', $roomId, null, [
                'name' => $name,
                'capacity' => $capacity,
                'status' => $status,
            ]);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return $this->fetchPhysicalRoomOrFail($roomId);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function updatePhysicalRoom(int $roomId, int $actorUserId, string $actorRole, array $input): array
    {
        $this->requireAdmin($actorUserId, $actorRole);

        $existing = $this->fetchPhysicalRoomOrFail($roomId);
        $capacity = array_key_exists('capacity', $input) ? (int) $input['capacity'] : $existing['capacity'];
        $theme = array_key_exists('theme', $input)
            ? (is_string($input['theme']) && trim($input['theme']) !== '' ? mb_substr(trim($input['theme']), 0, 100) : null)
            : $existing['theme'];
        $status = array_key_exists('status', $input)
            ? strtoupper(trim((string) $input['status']))
            : $existing['status'];
        $reason = isset($input['reason']) && is_string($input['reason']) ? $input['reason'] : null;

        if ($capacity < 2 || $capacity > 30) {
            throw new BadRequestException('Sức chứa phòng vật lý phải từ 2 đến 30 người (tính cả DM)');
        }
        if (!in_array($status, self::ALLOWED_ROOM_STATUSES, true)) {
            throw new BadRequestException('Trạng thái phòng chỉ chấp nhận AVAILABLE, MAINTENANCE hoặc INACTIVE');
        }

        $this->pdo->beginTransaction();
        try {
            $upd = $this->pdo->prepare(
                'UPDATE physical_rooms SET capacity = :capacity, theme = :theme, status = :status WHERE id = :id'
            );
            $upd->execute([
                ':capacity' => $capacity,
                ':theme' => $theme,
                ':status' => $status,
                ':id' => $roomId,
            ]);

            $this->audit->record($actorUserId, 'PHYSICAL_ROOM_UPDATED', 'PHYSICAL_ROOM', $roomId, $reason, [
                'old_status' => $existing['status'],
                'new_status' => $status,
                'old_capacity' => $existing['capacity'],
                'new_capacity' => $capacity,
            ]);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return $this->fetchPhysicalRoomOrFail($roomId);
    }

    /** @return array<int, array<string, mixed>> */
    public function listUsers(int $actorUserId, string $actorRole, ?string $role = null, ?int $isActive = null): array
    {
        $this->requireAdmin($actorUserId, $actorRole);

        $where = [];
        $params = [];

        if ($role !== null && trim($role) !== '') {
            $where[] = 'role = :role';
            $params[':role'] = strtoupper(trim($role));
        }
        if ($isActive !== null) {
            $where[] = 'is_active = :is_active';
            $params[':is_active'] = $isActive === 1 ? 1 : 0;
        }

        $sql = 'SELECT id, full_name, email, phone, role, student_verified, is_active FROM users';
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY id ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return array_map(fn (array $row): array => $this->formatUser($row), $stmt->fetchAll());
    }

    /** @return array<string, mixed> */
    public function updateUserStatus(int $targetUserId, int $actorUserId, string $actorRole, bool $isActive, ?string $reason = null): array
    {
        $this->requireAdmin($actorUserId, $actorRole);

        if ($targetUserId === $actorUserId && !$isActive) {
            throw new HttpException('Quản trị viên không thể tự khóa tài khoản của chính mình', 409);
        }

        $target = $this->fetchUserOrFail($targetUserId);

        $this->pdo->beginTransaction();
        try {
            $upd = $this->pdo->prepare('UPDATE users SET is_active = :is_active WHERE id = :id');
            $upd->execute([
                ':is_active' => $isActive ? 1 : 0,
                ':id' => $targetUserId,
            ]);

            $action = $isActive ? 'USER_ACTIVATED' : 'USER_DEACTIVATED';
            $this->audit->record($actorUserId, $action, 'USER', $targetUserId, $reason, [
                'old_is_active' => $target['is_active'],
                'new_is_active' => $isActive,
            ]);

            $this->notifications->create(
                $targetUserId,
                $action,
                $isActive ? 'Tài khoản đã được kích hoạt lại' : 'Tài khoản của bạn đã bị tạm khóa',
                $reason !== null && trim($reason) !== '' ? trim($reason) : ($isActive ? 'Bạn có thể tiếp tục sử dụng hệ thống.' : 'Vui lòng liên hệ quản trị viên để biết thêm chi tiết.'),
                ['is_active' => $isActive]
            );

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return $this->fetchUserOrFail($targetUserId);
    }

    /** @return array<string, mixed> */
    public function updateUserRole(int $targetUserId, int $actorUserId, string $actorRole, string $newRole, ?string $reason = null): array
    {
        $this->requireAdmin($actorUserId, $actorRole);

        $normalized = strtoupper(trim($newRole));
        if (!in_array($normalized, self::ALLOWED_USER_ROLES, true)) {
            throw new BadRequestException('Vai trò chỉ chấp nhận PLAYER, DM hoặc ADMIN');
        }
        if ($targetUserId === $actorUserId && $normalized !== 'ADMIN') {
            throw new HttpException('Quản trị viên không thể tự hạ vai trò của chính mình', 409);
        }

        $target = $this->fetchUserOrFail($targetUserId);

        $this->pdo->beginTransaction();
        try {
            $upd = $this->pdo->prepare('UPDATE users SET role = :role WHERE id = :id');
            $upd->execute([
                ':role' => $normalized,
                ':id' => $targetUserId,
            ]);

            $this->audit->record($actorUserId, 'USER_ROLE_CHANGED', 'USER', $targetUserId, $reason, [
                'old_role' => $target['role'],
                'new_role' => $normalized,
            ]);

            $this->notifications->create(
                $targetUserId,
                'USER_ROLE_CHANGED',
                'Vai trò tài khoản đã được cập nhật',
                "Vai trò của bạn đã được đổi từ {$target['role']} sang {$normalized}.",
                ['old_role' => $target['role'], 'new_role' => $normalized]
            );

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return $this->fetchUserOrFail($targetUserId);
    }

    private function requireAdmin(int $userId, string $role): void
    {
        if (strtoupper($role) !== 'ADMIN') {
            throw new HttpException('Chỉ quản trị viên mới có quyền thực hiện thao tác này', 403);
        }

        $stmt = $this->pdo->prepare('SELECT id, role, is_active FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch();

        if ($user === false || (int) $user['is_active'] !== 1 || strtoupper((string) $user['role']) !== 'ADMIN') {
            throw new HttpException('Tài khoản quản trị không hợp lệ hoặc đã bị khóa', 403);
        }
    }

    /** @return array<string, mixed> */
    private function fetchPhysicalRoomOrFail(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM physical_rooms WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if ($row === false) {
            throw new NotFoundException('Không tìm thấy phòng vật lý');
        }

        return $this->formatPhysicalRoom($row);
    }

    /** @return array<string, mixed> */
    private function fetchUserOrFail(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT id, full_name, email, phone, role, student_verified, is_active FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if ($row === false) {
            throw new NotFoundException('Không tìm thấy người dùng');
        }

        return $this->formatUser($row);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function formatPhysicalRoom(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'capacity' => (int) $row['capacity'],
            'theme' => $row['theme'] !== null ? (string) $row['theme'] : null,
            'status' => (string) $row['status'],
        ];
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
            'phone' => isset($row['phone']) && $row['phone'] !== null ? (string) $row['phone'] : null,
            'role' => (string) $row['role'],
            'student_verified' => isset($row['student_verified']) && (int) $row['student_verified'] === 1,
            'is_active' => (int) $row['is_active'] === 1,
        ];
    }
}