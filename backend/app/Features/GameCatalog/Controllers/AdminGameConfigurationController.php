<?php

declare(strict_types=1);

namespace App\Features\GameCatalog\Controllers;

use App\Core\Middleware\AuthMiddleware;
use App\Core\Request;
use App\Core\Response;
use App\Infrastructure\Database\DatabaseConnection;
use App\Infrastructure\Logging\AuditLogger;
use PDO;

class AdminGameConfigurationController
{
    protected PDO $pdo;
    protected AuditLogger $logger;

    public function __construct(?PDO $pdo = null, ?AuditLogger $logger = null)
    {
        $this->pdo = $pdo ?? DatabaseConnection::getInstance();
        $this->logger = $logger ?? new AuditLogger($this->pdo);

        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }
    }

    protected function requireAdmin(Request $request): ?Response
    {
        $auth = AuthMiddleware::authenticate($request, ['ADMIN']);
        if ($auth instanceof Response) {
            return $auth;
        }

        return null;
    }

    /**
     * GET /api/v1/admin/games/{id}/configurations
     */
    public function index(Request $request, string $gameId): Response
    {
        $check = $this->requireAdmin($request);
        if ($check !== null) {
            return $check;
        }

        $gid = (int) $gameId;

        // Kiểm tra kịch bản tồn tại
        $gStmt = $this->pdo->prepare('SELECT `id`, `name`, `slug` FROM `games` WHERE `id` = :gid LIMIT 1');
        $gStmt->execute([':gid' => $gid]);
        $game = $gStmt->fetch(PDO::FETCH_ASSOC);

        if (!$game) {
            return Response::fail('Không tìm thấy kịch bản', 'GAME_NOT_FOUND', 404);
        }

        $stmt = $this->pdo->prepare(
            'SELECT gc.`id`, gc.`game_id`, gc.`version`, gc.`configuration`, gc.`description`,
                    gc.`is_active`, gc.`created_by`, gc.`created_at`,
                    u.`full_name` AS `creator_name`
             FROM `game_configurations` gc
             LEFT JOIN `users` u ON u.`id` = gc.`created_by`
             WHERE gc.`game_id` = :gid
             ORDER BY gc.`version` DESC, gc.`id` DESC'
        );
        $stmt->execute([':gid' => $gid]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $configurations = array_map(function ($row) {
            if (is_string($row['configuration'])) {
                $decoded = json_decode($row['configuration'], true);
                $row['configuration'] = $decoded !== null ? $decoded : $row['configuration'];
            }
            $row['is_active'] = (int) $row['is_active'];
            $row['version'] = (int) $row['version'];
            return $row;
        }, $rows);

        return Response::success([
            'game' => $game,
            'configurations' => $configurations,
            'count' => count($configurations),
        ], 'Danh sách cấu hình nhân vật kịch bản');
    }

    /**
     * POST /api/v1/admin/games/{id}/configurations
     */
    public function create(Request $request, string $gameId): Response
    {
        $check = $this->requireAdmin($request);
        if ($check !== null) {
            return $check;
        }

        $gid = (int) $gameId;

        $gStmt = $this->pdo->prepare('SELECT `id`, `name` FROM `games` WHERE `id` = :gid LIMIT 1');
        $gStmt->execute([':gid' => $gid]);
        $game = $gStmt->fetch(PDO::FETCH_ASSOC);

        if (!$game) {
            return Response::fail('Không tìm thấy kịch bản', 'GAME_NOT_FOUND', 404);
        }

        $body = $request->getBodyParams();
        $rawConfig = $body['configuration'] ?? null;
        $description = isset($body['description']) ? trim((string) $body['description']) : 'Cấu hình nhân vật kịch bản';
        $isActive = isset($body['is_active']) ? ((int) $body['is_active'] ? 1 : 0) : 1;

        if ($rawConfig === null || (is_array($rawConfig) && empty($rawConfig))) {
            return Response::fail('Dữ liệu cấu hình nhân vật không được để trống', 'VALIDATION_ERROR', 422);
        }

        $configJson = is_string($rawConfig) ? $rawConfig : json_encode($rawConfig, JSON_UNESCAPED_UNICODE);

        // Lấy version tiếp theo
        $vStmt = $this->pdo->prepare('SELECT COALESCE(MAX(`version`), 0) FROM `game_configurations` WHERE `game_id` = :gid');
        $vStmt->execute([':gid' => $gid]);
        $maxVersion = (int) $vStmt->fetchColumn();
        $version = isset($body['version']) && (int) $body['version'] > 0 ? (int) $body['version'] : ($maxVersion + 1);

        $creatorId = (int) ($_SESSION['user_id'] ?? 1);

        // Nếu version đã tồn tại, báo lỗi hoặc tự động tăng
        $checkVer = $this->pdo->prepare('SELECT `id` FROM `game_configurations` WHERE `game_id` = :gid AND `version` = :ver LIMIT 1');
        $checkVer->execute([':gid' => $gid, ':ver' => $version]);
        if ($checkVer->fetchColumn()) {
            $version = $maxVersion + 1;
        }

        // Nếu cấu hình mới là active, có thể tắt active cấu hình cũ
        if ($isActive === 1) {
            $this->pdo->prepare('UPDATE `game_configurations` SET `is_active` = 0 WHERE `game_id` = :gid')->execute([':gid' => $gid]);
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO `game_configurations` (`game_id`, `version`, `configuration`, `description`, `is_active`, `created_by`, `created_at`)
             VALUES (:gid, :ver, :cfg, :desc, :active, :creator, NOW())'
        );
        $stmt->execute([
            ':gid' => $gid,
            ':ver' => $version,
            ':cfg' => $configJson,
            ':desc' => $description,
            ':active' => $isActive,
            ':creator' => $creatorId,
        ]);
        $newId = (int) $this->pdo->lastInsertId();

        $this->logger->log(
            'GAME_CONFIGURATION_CREATED',
            'GAME_CONFIGURATION',
            $newId,
            null,
            ['game_id' => $gid, 'version' => $version, 'is_active' => $isActive],
            $creatorId,
            $request
        );

        return Response::success([
            'id' => $newId,
            'game_id' => $gid,
            'version' => $version,
            'configuration' => is_array($rawConfig) ? $rawConfig : json_decode($configJson, true),
            'description' => $description,
            'is_active' => $isActive,
            'created_at' => date('Y-m-d H:i:s'),
        ], 'Thêm cấu hình nhân vật kịch bản thành công', null, 201);
    }

    /**
     * PATCH /api/v1/admin/game-configurations/{id}
     */
    public function update(Request $request, string $id): Response
    {
        $check = $this->requireAdmin($request);
        if ($check !== null) {
            return $check;
        }

        $cid = (int) $id;

        $stmt = $this->pdo->prepare('SELECT * FROM `game_configurations` WHERE `id` = :id LIMIT 1');
        $stmt->execute([':id' => $cid]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$current) {
            return Response::fail('Không tìm thấy cấu hình nhân vật', 'CONFIGURATION_NOT_FOUND', 404);
        }

        $body = $request->getBodyParams();
        $fields = [];
        $params = [':id' => $cid];

        if (isset($body['description'])) {
            $fields[] = '`description` = :description';
            $params[':description'] = trim((string) $body['description']);
        }

        if (isset($body['is_active'])) {
            $newActive = (int) $body['is_active'] ? 1 : 0;
            $fields[] = '`is_active` = :is_active';
            $params[':is_active'] = $newActive;

            // Nếu kích hoạt cấu hình này, tắt các cấu hình khác của kịch bản
            if ($newActive === 1) {
                $this->pdo->prepare('UPDATE `game_configurations` SET `is_active` = 0 WHERE `game_id` = :gid AND `id` != :id')
                    ->execute([':gid' => $current['game_id'], ':id' => $cid]);
            }
        }

        if (isset($body['configuration'])) {
            $rawConfig = $body['configuration'];
            $configJson = is_string($rawConfig) ? $rawConfig : json_encode($rawConfig, JSON_UNESCAPED_UNICODE);
            $fields[] = '`configuration` = :configuration';
            $params[':configuration'] = $configJson;
        }

        if (empty($fields)) {
            return Response::fail('Không có thông tin cần cập nhật', 'VALIDATION_ERROR', 422);
        }

        $sql = 'UPDATE `game_configurations` SET ' . implode(', ', $fields) . ' WHERE `id` = :id';
        $upStmt = $this->pdo->prepare($sql);
        $upStmt->execute($params);

        $creatorId = (int) ($_SESSION['user_id'] ?? 1);
        $this->logger->log(
            'GAME_CONFIGURATION_UPDATED',
            'GAME_CONFIGURATION',
            $cid,
            ['is_active' => $current['is_active']],
            $body,
            $creatorId,
            $request
        );

        return Response::success([
            'id' => $cid,
            'updated' => true,
        ], 'Cập nhật cấu hình kịch bản thành công');
    }
}
