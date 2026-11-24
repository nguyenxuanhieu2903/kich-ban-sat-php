<?php

declare(strict_types=1);

namespace App\Features\GameCatalog\Controllers;

use App\Core\Middleware\AuthMiddleware;
use App\Core\Request;
use App\Core\Response;
use App\Infrastructure\Database\DatabaseConnection;
use PDO;

class AdminGameController
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
     * GET /api/v1/admin/games
     */
    public function index(Request $request): Response
    {
        $check = $this->requireAdmin($request);
        if ($check !== null) return $check;

        $stmt = $this->pdo->query(
            'SELECT g.`id`, g.`name`, g.`slug`, g.`description`, g.`image_url`,
                    g.`min_players`, g.`max_players`, g.`duration_minutes`, g.`status`,
                    g.`display_order`, g.`created_at`, g.`updated_at`
             FROM `games` g
             ORDER BY g.`display_order` ASC, g.`id` DESC'
        );
        $games = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($games as &$game) {
            $pStmt = $this->pdo->prepare(
                'SELECT `id`, `game_id`, `price_per_person`, `start_time`, `end_time`,
                        `effective_from`, `effective_until`, `status`
                 FROM `game_prices`
                 WHERE `game_id` = :gid
                 ORDER BY `id` ASC'
            );
            $pStmt->bindValue(':gid', (int) $game['id'], PDO::PARAM_INT);
            $pStmt->execute();
            $game['prices'] = $pStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        return Response::success([
            'games' => $games,
            'count' => count($games),
        ], 'Danh sách toàn bộ kịch bản (Admin)');
    }

    /**
     * POST /api/v1/admin/games
     */
    public function create(Request $request): Response
    {
        $check = $this->requireAdmin($request);
        if ($check !== null) return $check;

        $body = $request->getBodyParams();
        $name = trim((string) ($body['name'] ?? $body['title'] ?? ''));
        $slug = trim((string) ($body['slug'] ?? ''));
        $description = isset($body['description']) ? trim((string) $body['description']) : null;
        $imageUrl = isset($body['image_url']) ? trim((string) $body['image_url']) : null;
        $minPlayers = max(1, (int) ($body['min_players'] ?? 4));
        $maxPlayers = max($minPlayers, (int) ($body['max_players'] ?? 8));
        $durationMinutes = max(30, (int) ($body['duration_minutes'] ?? 240));
        $status = strtoupper((string) ($body['status'] ?? 'ACTIVE'));
        $displayOrder = (int) ($body['display_order'] ?? 0);

        if ($name === '') {
            return Response::fail('Tên kịch bản không được để trống', 'VALIDATION_ERROR', 422);
        }

        if ($slug === '') {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
        }

        // Kiểm tra trùng slug
        $chk = $this->pdo->prepare('SELECT `id` FROM `games` WHERE `slug` = :slug LIMIT 1');
        $chk->bindValue(':slug', $slug, PDO::PARAM_STR);
        $chk->execute();
        if ($chk->fetchColumn() !== false) {
            $slug .= '-' . bin2hex(random_bytes(3));
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO `games` (`name`, `slug`, `description`, `image_url`, `min_players`, `max_players`,
                                 `duration_minutes`, `status`, `display_order`, `created_at`, `updated_at`)
             VALUES (:name, :slug, :description, :image_url, :min_players, :max_players,
                     :duration_minutes, :status, :display_order, NOW(), NOW())'
        );
        $stmt->bindValue(':name', $name, PDO::PARAM_STR);
        $stmt->bindValue(':slug', $slug, PDO::PARAM_STR);
        $stmt->bindValue(':description', $description, $description === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':image_url', $imageUrl, $imageUrl === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':min_players', $minPlayers, PDO::PARAM_INT);
        $stmt->bindValue(':max_players', $maxPlayers, PDO::PARAM_INT);
        $stmt->bindValue(':duration_minutes', $durationMinutes, PDO::PARAM_INT);
        $stmt->bindValue(':status', in_array($status, ['ACTIVE', 'INACTIVE'], true) ? $status : 'ACTIVE', PDO::PARAM_STR);
        $stmt->bindValue(':display_order', $displayOrder, PDO::PARAM_INT);
        $stmt->execute();

        $gameId = (int) $this->pdo->lastInsertId();

        // Tạo giá mặc định nếu có truyền vào
        if (isset($body['price_per_person']) && (int) $body['price_per_person'] > 0) {
            $pPrice = (int) $body['price_per_person'];
            $pStmt = $this->pdo->prepare(
                'INSERT INTO `game_prices` (`game_id`, `price_per_person`, `start_time`, `end_time`, `effective_from`, `effective_until`, `status`, `created_at`, `updated_at`)
                 VALUES (:gid, :price, \'00:00:00\', \'23:59:59\', CURDATE(), \'2035-12-31\', \'ACTIVE\', NOW(), NOW())'
            );
            $pStmt->bindValue(':gid', $gameId, PDO::PARAM_INT);
            $pStmt->bindValue(':price', $pPrice, PDO::PARAM_INT);
            $pStmt->execute();
        }

        $resStmt = $this->pdo->prepare('SELECT * FROM `games` WHERE `id` = :id');
        $resStmt->bindValue(':id', $gameId, PDO::PARAM_INT);
        $resStmt->execute();
        $game = $resStmt->fetch(PDO::FETCH_ASSOC);

        return Response::success(['game' => $game], 'Thêm kịch bản mới thành công', null, 201);
    }

    /**
     * PATCH /api/v1/admin/games/{id}
     */
    public function update(Request $request, string $id): Response
    {
        $check = $this->requireAdmin($request);
        if ($check !== null) return $check;

        $gameId = (int) $id;
        $body = $request->getBodyParams();

        $allowed = ['name', 'slug', 'description', 'image_url', 'min_players', 'max_players', 'duration_minutes', 'status', 'display_order'];
        $sets = [];
        $params = [':id' => $gameId];

        foreach ($allowed as $col) {
            if (array_key_exists($col, $body)) {
                $val = $body[$col];
                if (in_array($col, ['min_players', 'max_players', 'duration_minutes', 'display_order'], true)) {
                    $val = (int) $val;
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
        $sql = 'UPDATE `games` SET ' . implode(', ', $sets) . ' WHERE `id` = :id';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();

        $resStmt = $this->pdo->prepare('SELECT * FROM `games` WHERE `id` = :id');
        $resStmt->bindValue(':id', $gameId, PDO::PARAM_INT);
        $resStmt->execute();
        $game = $resStmt->fetch(PDO::FETCH_ASSOC);

        if (!$game) {
            return Response::fail('Không tìm thấy kịch bản', 'NOT_FOUND', 404);
        }

        return Response::success(['game' => $game], 'Cập nhật kịch bản thành công');
    }

    /**
     * DELETE /api/v1/admin/games/{id}
     */
    public function delete(Request $request, string $id): Response
    {
        $check = $this->requireAdmin($request);
        if ($check !== null) return $check;

        $gameId = (int) $id;
        $stmt = $this->pdo->prepare('UPDATE `games` SET `status` = \'INACTIVE\', `updated_at` = NOW() WHERE `id` = :id');
        $stmt->bindValue(':id', $gameId, PDO::PARAM_INT);
        $stmt->execute();

        return Response::success(['game_id' => $gameId], 'Đã ngưng phát hành kịch bản');
    }

    /**
     * POST /api/v1/admin/games/{id}/prices
     */
    public function addPrice(Request $request, string $id): Response
    {
        $check = $this->requireAdmin($request);
        if ($check !== null) return $check;

        $gameId = (int) $id;
        $body = $request->getBodyParams();

        $price = (int) ($body['price_per_person'] ?? $body['price'] ?? 0);
        $startTime = trim((string) ($body['start_time'] ?? '00:00:00'));
        $endTime = trim((string) ($body['end_time'] ?? '23:59:59'));
        $status = strtoupper((string) ($body['status'] ?? 'ACTIVE'));

        if ($price <= 0) {
            return Response::fail('Giá vé phải lớn hơn 0', 'VALIDATION_ERROR', 422);
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO `game_prices` (`game_id`, `price_per_person`, `start_time`, `end_time`, `effective_from`, `effective_until`, `status`, `created_at`, `updated_at`)
             VALUES (:gid, :price, :st, :et, CURDATE(), \'2035-12-31\', :status, NOW(), NOW())'
        );
        $stmt->bindValue(':gid', $gameId, PDO::PARAM_INT);
        $stmt->bindValue(':price', $price, PDO::PARAM_INT);
        $stmt->bindValue(':st', $startTime, PDO::PARAM_STR);
        $stmt->bindValue(':et', $endTime, PDO::PARAM_STR);
        $stmt->bindValue(':status', in_array($status, ['ACTIVE', 'INACTIVE'], true) ? $status : 'ACTIVE', PDO::PARAM_STR);
        $stmt->execute();

        $priceId = (int) $this->pdo->lastInsertId();

        return Response::success(['price_id' => $priceId], 'Thêm mốc giá thành công', null, 201);
    }

    /**
     * PATCH /api/v1/admin/prices/{id}
     */
    public function updatePrice(Request $request, string $id): Response
    {
        $check = $this->requireAdmin($request);
        if ($check !== null) return $check;

        $priceId = (int) $id;
        $body = $request->getBodyParams();

        $sets = [];
        $params = [':id' => $priceId];

        if (isset($body['price_per_person'])) {
            $sets[] = '`price_per_person` = :price';
            $params[':price'] = (int) $body['price_per_person'];
        }
        if (isset($body['status'])) {
            $sets[] = '`status` = :status';
            $params[':status'] = strtoupper((string) $body['status']) === 'INACTIVE' ? 'INACTIVE' : 'ACTIVE';
        }

        if (empty($sets)) {
            return Response::fail('Không có dữ liệu cập nhật', 'VALIDATION_ERROR', 422);
        }

        $sets[] = '`updated_at` = NOW()';
        $stmt = $this->pdo->prepare('UPDATE `game_prices` SET ' . implode(', ', $sets) . ' WHERE `id` = :id');
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();

        return Response::success(['price_id' => $priceId], 'Cập nhật bảng giá thành công');
    }

    /**
     * DELETE /api/v1/admin/prices/{id}
     */
    public function deletePrice(Request $request, string $id): Response
    {
        $check = $this->requireAdmin($request);
        if ($check !== null) return $check;

        $priceId = (int) $id;
        $stmt = $this->pdo->prepare('UPDATE `game_prices` SET `status` = \'INACTIVE\', `updated_at` = NOW() WHERE `id` = :id');
        $stmt->bindValue(':id', $priceId, PDO::PARAM_INT);
        $stmt->execute();

        return Response::success(['price_id' => $priceId], 'Đã ngưng áp dụng mốc giá');
    }
}
