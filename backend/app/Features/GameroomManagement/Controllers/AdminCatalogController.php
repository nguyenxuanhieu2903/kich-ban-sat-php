<?php

declare(strict_types=1);

namespace App\Features\GameroomManagement\Controllers;

use App\Core\Middleware\AuthMiddleware;
use App\Core\Request;
use App\Core\Response;
use App\Infrastructure\Database\DatabaseConnection;
use App\Shared\Services\AuditLogService;
use PDO;

class AdminCatalogController
{
    protected PDO $pdo;
    protected AuditLogService $auditService;

    public function __construct(?PDO $pdo = null, ?AuditLogService $auditService = null)
    {
        $this->pdo = $pdo ?? DatabaseConnection::getInstance();
        $this->auditService = $auditService ?? new AuditLogService($this->pdo);
    }

    /**
     * GET /api/v1/admin/games
     */
    public function listGames(Request $request): Response
    {
        $auth = AuthMiddleware::authenticate($request, ['ADMIN']);
        if ($auth instanceof Response) {
            return $auth;
        }

        $sql = "SELECT g.`id`, g.`name`, g.`description` AS `genre`, g.`min_players`, g.`max_players`,
                       g.`duration_minutes`, g.`is_18_plus`, g.`status`, g.`difficulty`,
                       MIN(CASE WHEN gp.`start_time` < '20:00:00' AND gp.`status` = 'ACTIVE' THEN gp.`price_per_person` END) AS `day_price`,
                       MAX(CASE WHEN gp.`start_time` >= '20:00:00' AND gp.`status` = 'ACTIVE' THEN gp.`price_per_person` END) AS `night_price`
                FROM `games` g
                LEFT JOIN `game_prices` gp ON gp.`game_id` = g.`id`
                GROUP BY g.`id`, g.`name`, g.`description`, g.`min_players`, g.`max_players`, g.`duration_minutes`, g.`is_18_plus`, g.`status`, g.`difficulty`
                ORDER BY g.`id` ASC";
        $games = $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        return Response::success(['games' => $games], 'Danh sách kịch bản và bảng giá');
    }

    /**
     * POST /api/v1/admin/games
     */
    public function createGame(Request $request): Response
    {
        $auth = AuthMiddleware::authenticate($request, ['ADMIN']);
        if ($auth instanceof Response) {
            return $auth;
        }

        $body = $request->getBodyParams();
        $name = trim((string) ($body['name'] ?? ''));
        $genre = trim((string) ($body['genre'] ?? 'Trinh thám'));
        $minPlayers = max(1, (int) ($body['min_players'] ?? 5));
        $maxPlayers = max($minPlayers, (int) ($body['max_players'] ?? 8));
        $duration = max(30, (int) ($body['duration_minutes'] ?? 180));
        $is18 = !empty($body['is_18_plus']) ? 1 : 0;
        $dayPrice = max(50000, (int) ($body['day_price'] ?? 199000));
        $nightPrice = max(50000, (int) ($body['night_price'] ?? 239000));

        if ($name === '') {
            return Response::fail('Tên kịch bản là bắt buộc', 'VALIDATION_ERROR', 422);
        }

        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name) ?? 'game') . '-' . time();
        $ins = $this->pdo->prepare(
            "INSERT INTO `games` (`name`, `slug`, `description`, `min_players`, `max_players`, `duration_minutes`, `is_18_plus`, `status`, `created_at`, `updated_at`)
             VALUES (:name, :slug, :genre, :min_p, :max_p, :dur, :is18, 'ACTIVE', NOW(), NOW())"
        );
        $ins->execute([
            ':name' => $name,
            ':slug' => $slug,
            ':genre' => $genre,
            ':min_p' => $minPlayers,
            ':max_p' => $maxPlayers,
            ':dur' => $duration,
            ':is18' => $is18,
        ]);
        $gameId = (int) $this->pdo->lastInsertId();

        $insPrice = $this->pdo->prepare(
            "INSERT INTO `game_prices` (`game_id`, `price_per_person`, `start_time`, `end_time`, `effective_from`, `effective_until`, `status`, `created_at`, `updated_at`)
             VALUES (:gid, :price, :st, :et, '2026-01-01', '2030-12-31', 'ACTIVE', NOW(), NOW())"
        );
        $insPrice->execute([':gid' => $gameId, ':price' => $dayPrice, ':st' => '09:00:00', ':et' => '19:59:59']);
        $insPrice->execute([':gid' => $gameId, ':price' => $nightPrice, ':st' => '20:00:00', ':et' => '23:59:59']);

        // Tạo cấu hình mặc định v1 trong game_configurations cho kịch bản mới
        $this->pdo->prepare(
            "INSERT INTO `game_configurations` (`game_id`, `version`, `configuration`, `description`, `is_active`, `created_by`, `created_at`)
             VALUES (:gid, 1, :cfg, :desc, 1, :created_by, NOW())"
        )->execute([
            ':gid' => $gameId,
            ':cfg' => json_encode(['min_players' => $minPlayers, 'max_players' => $maxPlayers, 'duration_minutes' => $duration]),
            ':desc' => 'Cấu hình mặc định v1',
            ':created_by' => (int) $auth['id'],
        ]);

        $this->auditService->log((int) $auth['id'], 'ADMIN_CREATE_GAME', 'GAME', $gameId, ['name' => $name]);

        // Thông báo quyền lợi chung cho toàn bộ người chơi biết có kịch bản mới ra mắt
        try {
            $notiRepo = new \App\Features\Notification\Repositories\NotificationRepository($this->pdo);
            $notiRepo->broadcastToAudience(
                'PLAYER',
                'Kịch bản mới vừa ra mắt',
                "Kịch bản '{$name}' ({$minPlayers}–{$maxPlayers} người) đã chính thức mở đặt phòng. Nhấn vào đây để xem chi tiết và đặt lịch!",
                (int) $auth['id'],
                'GAME',
                $gameId
            );
        } catch (\Throwable) {
        }

        return Response::success(['game_id' => $gameId, 'name' => $name], 'Thêm kịch bản mới thành công', null, 201);
    }

    /**
     * PATCH /api/v1/admin/games/{id}
     */
    public function updateGame(Request $request, string $id): Response
    {
        $auth = AuthMiddleware::authenticate($request, ['ADMIN']);
        if ($auth instanceof Response) {
            return $auth;
        }

        $gameId = (int) $id;
        $body = $request->getBodyParams();
        $status = isset($body['status']) ? strtoupper(trim((string) $body['status'])) : null;
        $dayPrice = array_key_exists('day_price', $body) && $body['day_price'] !== '' && $body['day_price'] !== null ? (int) $body['day_price'] : null;
        $nightPrice = array_key_exists('night_price', $body) && $body['night_price'] !== '' && $body['night_price'] !== null ? (int) $body['night_price'] : null;

        $gameNameStmt = $this->pdo->prepare('SELECT `name` FROM `games` WHERE `id` = :id LIMIT 1');
        $gameNameStmt->execute([':id' => $gameId]);
        $foundName = $gameNameStmt->fetchColumn();
        if ($foundName === false) {
            return Response::fail('Không tìm thấy kịch bản yêu cầu', 'GAME_NOT_FOUND', 404);
        }
        $gameName = (string) $foundName;

        if ($status !== null && !in_array($status, ['ACTIVE', 'INACTIVE'], true)) {
            return Response::fail('Trạng thái kịch bản chỉ chấp nhận ACTIVE hoặc INACTIVE', 'VALIDATION_ERROR', 422);
        }
        if ($dayPrice !== null && $dayPrice < 50000) {
            return Response::fail('Giá vé khung giờ ban ngày phải từ 50.000 VNĐ trở lên', 'VALIDATION_ERROR', 422);
        }
        if ($nightPrice !== null && $nightPrice < 50000) {
            return Response::fail('Giá vé khung giờ buổi tối phải từ 50.000 VNĐ trở lên', 'VALIDATION_ERROR', 422);
        }

        if ($status !== null && in_array($status, ['ACTIVE', 'INACTIVE'], true)) {
            $st = $this->pdo->prepare('UPDATE `games` SET `status` = :st, `updated_at` = NOW() WHERE `id` = :id');
            $st->execute([':st' => $status, ':id' => $gameId]);

            // Nếu ẩn kịch bản, thông báo cho các người chơi đang có phòng FORMING của kịch bản này
            if ($status === 'INACTIVE') {
                try {
                    $uStmt = $this->pdo->prepare(
                        "SELECT DISTINCT rp.`user_id`
                         FROM `room_participants` rp
                         INNER JOIN `game_rooms` gr ON gr.`id` = rp.`game_room_id`
                         WHERE gr.`game_id` = :gid AND gr.`status` = 'FORMING'
                           AND rp.`status` IN ('PENDING_DEPOSIT', 'DEPOSITED')"
                    );
                    $uStmt->execute([':gid' => $gameId]);
                    $uIds = array_map('intval', $uStmt->fetchAll(PDO::FETCH_COLUMN));
                    if (!empty($uIds)) {
                        $notiRepo = new \App\Features\Notification\Repositories\NotificationRepository($this->pdo);
                        $notiRepo->createNotification(
                            'Kịch bản tạm ẩn khỏi danh mục',
                            "Kịch bản '{$gameName}' vừa được Quản trị viên tạm ẩn khỏi danh sách nhận đặt mới. Các phòng đang chờ sẽ được quán liên hệ hỗ trợ.",
                            'SYSTEM',
                            (int) $auth['id'],
                            $uIds
                        );
                    }
                } catch (\Throwable) {
                }
            }
        }

        if ($dayPrice !== null && $dayPrice >= 50000) {
            $upDay = $this->pdo->prepare("UPDATE `game_prices` SET `price_per_person` = :p, `updated_at` = NOW() WHERE `game_id` = :gid AND `start_time` < '20:00:00'");
            $upDay->execute([':p' => $dayPrice, ':gid' => $gameId]);
        }

        if ($nightPrice !== null && $nightPrice >= 50000) {
            $upNight = $this->pdo->prepare("UPDATE `game_prices` SET `price_per_person` = :p, `updated_at` = NOW() WHERE `game_id` = :gid AND `start_time` >= '20:00:00'");
            $upNight->execute([':p' => $nightPrice, ':gid' => $gameId]);
        }

        $this->auditService->log((int) $auth['id'], 'ADMIN_UPDATE_GAME', 'GAME', $gameId, $body);

        $msg = $status === 'INACTIVE'
            ? "Đã ẩn kịch bản '{$gameName}' khỏi Trang chủ, Kho kịch bản và màn hình Tạo phòng."
            : ($status === 'ACTIVE'
                ? "Đã hiển thị lại kịch bản '{$gameName}' trên toàn hệ thống."
                : "Cập nhật kịch bản '{$gameName}' và bảng giá thành công.");

        return Response::success(['game_id' => $gameId, 'game_name' => $gameName, 'status' => $status], $msg);
    }

    /**
     * GET /api/v1/admin/promotions
     */
    public function listPromotions(Request $request): Response
    {
        $auth = AuthMiddleware::authenticate($request, ['ADMIN']);
        if ($auth instanceof Response) {
            return $auth;
        }

        $promos = $this->pdo->query('SELECT * FROM `promotions` ORDER BY `id` DESC')->fetchAll(PDO::FETCH_ASSOC);
        return Response::success(['promotions' => $promos], 'Danh sách chương trình khuyến mãi');
    }

    /**
     * PATCH /api/v1/admin/promotions/{id}
     */
    public function togglePromotion(Request $request, string $id): Response
    {
        $auth = AuthMiddleware::authenticate($request, ['ADMIN']);
        if ($auth instanceof Response) {
            return $auth;
        }

        $promoId = (int) $id;
        $body = $request->getBodyParams();
        $isActive = !empty($body['is_active']) ? 1 : 0;

        $st = $this->pdo->prepare('UPDATE `promotions` SET `is_active` = :a, `updated_at` = NOW() WHERE `id` = :id');
        $st->execute([':a' => $isActive, ':id' => $promoId]);

        // Khi bật chương trình khuyến mãi, gửi thông báo quyền lợi chung cho tất cả người chơi
        if ($isActive === 1) {
            try {
                $pNameStmt = $this->pdo->prepare('SELECT `name`, `code` FROM `promotions` WHERE `id` = :id LIMIT 1');
                $pNameStmt->execute([':id' => $promoId]);
                $pInfo = $pNameStmt->fetch(PDO::FETCH_ASSOC);
                $pLabel = $pInfo ? ($pInfo['name'] ?: $pInfo['code'] ?: "Ưu đãi #{$promoId}") : "Ưu đãi #{$promoId}";
                $notiRepo = new \App\Features\Notification\Repositories\NotificationRepository($this->pdo);
                $notiRepo->broadcastToAudience(
                    'PLAYER',
                    'Chương trình ưu đãi đang mở áp dụng',
                    "Ưu đãi '{$pLabel}' hiện đã được kích hoạt cho toàn bộ người chơi. Nhấn để xem chi tiết quy định và bảng giá!",
                    (int) $auth['id'],
                    'PROMOTION',
                    $promoId
                );
            } catch (\Throwable) {
            }
        }

        $this->auditService->log((int) $auth['id'], 'ADMIN_TOGGLE_PROMOTION', 'PROMOTION', $promoId, ['is_active' => $isActive]);

        return Response::success(['promotion_id' => $promoId, 'is_active' => (bool) $isActive], 'Cập nhật trạng thái khuyến mãi thành công');
    }

    /**
     * POST /api/v1/admin/venues
     */
    public function createVenue(Request $request): Response
    {
        $auth = AuthMiddleware::authenticate($request, ['ADMIN']);
        if ($auth instanceof Response) {
            return $auth;
        }

        $body = $request->getBodyParams();
        $name = trim((string) ($body['name'] ?? ''));
        $address = trim((string) ($body['address'] ?? ''));
        $phone = trim((string) ($body['phone'] ?? '0901000000'));

        if ($name === '' || $address === '') {
            return Response::fail('Tên chi nhánh và địa chỉ là bắt buộc', 'VALIDATION_ERROR', 422);
        }

        $ins = $this->pdo->prepare(
            "INSERT INTO `venues` (`name`, `address`, `phone`, `status`, `created_at`, `updated_at`)
             VALUES (:name, :addr, :phone, 'ACTIVE', NOW(), NOW())"
        );
        $ins->execute([':name' => $name, ':addr' => $address, ':phone' => $phone]);
        $venueId = (int) $this->pdo->lastInsertId();

        $this->auditService->log((int) $auth['id'], 'ADMIN_CREATE_VENUE', 'VENUE', $venueId, ['name' => $name]);

        return Response::success(['venue_id' => $venueId, 'name' => $name], 'Thêm chi nhánh mới thành công', null, 201);
    }

    /**
     * POST /api/v1/admin/venues/{id}/rooms
     */
    public function createPhysicalRoom(Request $request, string $id): Response
    {
        $auth = AuthMiddleware::authenticate($request, ['ADMIN']);
        if ($auth instanceof Response) {
            return $auth;
        }

        $venueId = (int) $id;
        $body = $request->getBodyParams();
        $name = trim((string) ($body['name'] ?? ''));
        $capacity = max(4, (int) ($body['capacity'] ?? 8));

        if ($name === '') {
            return Response::fail('Tên phòng vật lý là bắt buộc', 'VALIDATION_ERROR', 422);
        }

        $ins = $this->pdo->prepare(
            "INSERT INTO `physical_rooms` (`venue_id`, `name`, `capacity`, `status`, `is_maintenance`, `created_at`, `updated_at`)
             VALUES (:vid, :name, :cap, 'ACTIVE', 0, NOW(), NOW())"
        );
        $ins->execute([':vid' => $venueId, ':name' => $name, ':cap' => $capacity]);
        $prId = (int) $this->pdo->lastInsertId();

        $this->auditService->log((int) $auth['id'], 'ADMIN_CREATE_PHYSICAL_ROOM', 'PHYSICAL_ROOM', $prId, ['venue_id' => $venueId, 'name' => $name]);

        return Response::success(['physical_room_id' => $prId, 'name' => $name], 'Thêm phòng vật lý mới thành công', null, 201);
    }
}
