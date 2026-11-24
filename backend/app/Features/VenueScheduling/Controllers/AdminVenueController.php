<?php

declare(strict_types=1);

namespace App\Features\VenueScheduling\Controllers;

use App\Core\Middleware\AuthMiddleware;
use App\Core\Request;
use App\Core\Response;
use App\Infrastructure\Database\DatabaseConnection;
use PDO;

class AdminVenueController
{
    protected PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DatabaseConnection::getInstance();

        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }
    }

    protected function requireAdmin(Request $request): ?Response
    {
        $auth = AuthMiddleware::authenticate($request);
        if ($auth instanceof Response) {
            return $auth;
        }

        if (strtoupper((string) ($auth['role_code'] ?? '')) !== 'ADMIN') {
            return Response::fail('Chỉ Quản Trị Viên mới có quyền thực hiện thao tác này', 'FORBIDDEN', 403);
        }

        return null;
    }

    /**
     * GET /api/v1/admin/venues
     */
    public function index(Request $request): Response
    {
        $check = $this->requireAdmin($request);
        if ($check !== null) return $check;

        $stmt = $this->pdo->query('SELECT `id`, `name`, `address`, `phone`, `description`, `status`, `created_at`, `updated_at` FROM `venues` ORDER BY `id` ASC');
        $venues = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($venues as &$v) {
            $rStmt = $this->pdo->prepare('SELECT `id`, `venue_id`, `name`, `capacity`, `description`, `status`, `created_at`, `updated_at` FROM `physical_rooms` WHERE `venue_id` = :vid ORDER BY `id` ASC');
            $rStmt->bindValue(':vid', (int) $v['id'], PDO::PARAM_INT);
            $rStmt->execute();
            $v['rooms'] = $rStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        return Response::success([
            'venues' => $venues,
            'count' => count($venues),
        ], 'Danh sách toàn bộ chi nhánh và phòng chơi (Admin)');
    }

    /**
     * POST /api/v1/admin/venues
     */
    public function create(Request $request): Response
    {
        $check = $this->requireAdmin($request);
        if ($check !== null) return $check;

        $body = $request->getBodyParams();
        $name = trim((string) ($body['name'] ?? ''));
        $address = trim((string) ($body['address'] ?? ''));
        $phone = isset($body['phone']) ? trim((string) $body['phone']) : null;
        $description = isset($body['description']) ? trim((string) $body['description']) : null;
        $status = strtoupper((string) ($body['status'] ?? 'ACTIVE')) === 'INACTIVE' ? 'INACTIVE' : 'ACTIVE';

        if ($name === '' || $address === '') {
            return Response::fail('Tên và địa chỉ chi nhánh không được để trống', 'VALIDATION_ERROR', 422);
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO `venues` (`name`, `address`, `phone`, `description`, `status`, `created_at`, `updated_at`)
             VALUES (:name, :address, :phone, :desc, :status, NOW(), NOW())'
        );
        $stmt->bindValue(':name', $name, PDO::PARAM_STR);
        $stmt->bindValue(':address', $address, PDO::PARAM_STR);
        $stmt->bindValue(':phone', $phone, $phone === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':desc', $description, $description === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':status', $status, PDO::PARAM_STR);
        $stmt->execute();

        $venueId = (int) $this->pdo->lastInsertId();

        return Response::success(['venue_id' => $venueId], 'Tạo chi nhánh mới thành công', null, 201);
    }

    /**
     * PATCH /api/v1/admin/venues/{id}
     */
    public function update(Request $request, string $id): Response
    {
        $check = $this->requireAdmin($request);
        if ($check !== null) return $check;

        $venueId = (int) $id;
        $body = $request->getBodyParams();

        $allowed = ['name', 'address', 'phone', 'description', 'status'];
        $sets = [];
        $params = [':id' => $venueId];

        foreach ($allowed as $col) {
            if (array_key_exists($col, $body)) {
                $val = $body[$col];
                if ($col === 'status') {
                    $val = strtoupper((string) $val) === 'INACTIVE' ? 'INACTIVE' : 'ACTIVE';
                }
                $sets[] = "`{$col}` = :{$col}";
                $params[":{$col}"] = $val;
            }
        }

        if (empty($sets)) {
            return Response::fail('Không có dữ liệu cập nhật', 'VALIDATION_ERROR', 422);
        }

        $sets[] = '`updated_at` = NOW()';
        $stmt = $this->pdo->prepare('UPDATE `venues` SET ' . implode(', ', $sets) . ' WHERE `id` = :id');
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();

        return Response::success(['venue_id' => $venueId], 'Cập nhật chi nhánh thành công');
    }

    /**
     * DELETE /api/v1/admin/venues/{id}
     */
    public function delete(Request $request, string $id): Response
    {
        $check = $this->requireAdmin($request);
        if ($check !== null) return $check;

        $venueId = (int) $id;
        $stmt = $this->pdo->prepare('UPDATE `venues` SET `status` = \'INACTIVE\', `updated_at` = NOW() WHERE `id` = :id');
        $stmt->bindValue(':id', $venueId, PDO::PARAM_INT);
        $stmt->execute();

        return Response::success(['venue_id' => $venueId], 'Đã tạm ngưng hoạt động chi nhánh');
    }

    /**
     * POST /api/v1/admin/venues/{id}/rooms
     */
    public function createPhysicalRoom(Request $request, string $id): Response
    {
        $check = $this->requireAdmin($request);
        if ($check !== null) return $check;

        $venueId = (int) $id;
        $body = $request->getBodyParams();

        $name = trim((string) ($body['name'] ?? ''));
        $capacity = max(1, (int) ($body['capacity'] ?? 8));
        $description = isset($body['description']) ? trim((string) $body['description']) : null;
        $status = strtoupper((string) ($body['status'] ?? 'ACTIVE')) === 'INACTIVE' ? 'INACTIVE' : 'ACTIVE';

        if ($name === '') {
            return Response::fail('Tên phòng vật lý không được để trống', 'VALIDATION_ERROR', 422);
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO `physical_rooms` (`venue_id`, `name`, `capacity`, `description`, `status`, `created_at`, `updated_at`)
             VALUES (:vid, :name, :cap, :desc, :status, NOW(), NOW())'
        );
        $stmt->bindValue(':vid', $venueId, PDO::PARAM_INT);
        $stmt->bindValue(':name', $name, PDO::PARAM_STR);
        $stmt->bindValue(':cap', $capacity, PDO::PARAM_INT);
        $stmt->bindValue(':desc', $description, $description === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':status', $status, PDO::PARAM_STR);
        $stmt->execute();

        $roomId = (int) $this->pdo->lastInsertId();

        return Response::success(['physical_room_id' => $roomId], 'Tạo phòng vật lý thành công', null, 201);
    }

    /**
     * PATCH /api/v1/admin/physical-rooms/{id}
     */
    public function updatePhysicalRoom(Request $request, string $id): Response
    {
        $check = $this->requireAdmin($request);
        if ($check !== null) return $check;

        $roomId = (int) $id;
        $body = $request->getBodyParams();

        $allowed = ['name', 'capacity', 'description', 'status'];
        $sets = [];
        $params = [':id' => $roomId];

        foreach ($allowed as $col) {
            if (array_key_exists($col, $body)) {
                $val = $body[$col];
                if ($col === 'capacity') {
                    $val = max(1, (int) $val);
                } elseif ($col === 'status') {
                    $val = strtoupper((string) $val) === 'INACTIVE' ? 'INACTIVE' : 'ACTIVE';
                }
                $sets[] = "`{$col}` = :{$col}";
                $params[":{$col}"] = $val;
            }
        }

        if (empty($sets)) {
            return Response::fail('Không có dữ liệu cập nhật', 'VALIDATION_ERROR', 422);
        }

        $sets[] = '`updated_at` = NOW()';
        $stmt = $this->pdo->prepare('UPDATE `physical_rooms` SET ' . implode(', ', $sets) . ' WHERE `id` = :id');
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();

        return Response::success(['physical_room_id' => $roomId], 'Cập nhật phòng vật lý thành công');
    }

    /**
     * DELETE /api/v1/admin/physical-rooms/{id}
     */
    public function deletePhysicalRoom(Request $request, string $id): Response
    {
        $check = $this->requireAdmin($request);
        if ($check !== null) return $check;

        $roomId = (int) $id;
        $stmt = $this->pdo->prepare('UPDATE `physical_rooms` SET `status` = \'INACTIVE\', `updated_at` = NOW() WHERE `id` = :id');
        $stmt->bindValue(':id', $roomId, PDO::PARAM_INT);
        $stmt->execute();

        return Response::success(['physical_room_id' => $roomId], 'Đã ngưng sử dụng phòng vật lý');
    }
}
