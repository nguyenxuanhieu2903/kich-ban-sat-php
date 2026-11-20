<?php

declare(strict_types=1);

namespace App\Features\GameCatalog\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Features\GameCatalog\Repositories\GameRepository;
use App\Infrastructure\Database\DatabaseConnection;
use PDO;

class GameController
{
    protected GameRepository $gameRepo;
    protected PDO $pdo;

    public function __construct(?GameRepository $gameRepo = null, ?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DatabaseConnection::getInstance();
        $this->gameRepo = $gameRepo ?? new GameRepository($this->pdo);
    }

    /**
     * GET /api/games
     * Query: ?limit=20&offset=0&players=6
     */
    public function index(Request $request): Response
    {
        $limit = max(1, min(100, (int) $request->query('limit', 20)));
        $offset = max(0, (int) $request->query('offset', 0));
        $players = $request->query('players') !== null ? (int) $request->query('players') : null;

        if ($players !== null && $players > 0) {
            $stmt = $this->pdo->prepare(
                'SELECT `id`, `name`, `name` AS `title`, `slug`, `description`, `image_url`,
                        `min_players`, `max_players`, `duration_minutes`, `status`,
                        1 AS `is_active`, `display_order`, `created_at`, `updated_at`
                 FROM `games`
                 WHERE `status` = \'ACTIVE\' AND `min_players` <= :p1 AND `max_players` >= :p2
                 ORDER BY `display_order` ASC, `name` ASC
                 LIMIT :limit OFFSET :offset'
            );
            $stmt->bindValue(':p1', $players, PDO::PARAM_INT);
            $stmt->bindValue(':p2', $players, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $games = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $games = $this->gameRepo->listActive($limit, $offset);
        }

        return Response::json([
            'success' => true,
            'data' => [
                'games' => $games,
                'count' => count($games),
                'limit' => $limit,
                'offset' => $offset,
            ],
        ]);
    }

    /**
     * GET /api/games/{id}
     */
    public function show(Request $request, string $idOrSlug): Response
    {
        if (ctype_digit($idOrSlug)) {
            $game = $this->gameRepo->findById((int) $idOrSlug);
        } else {
            $game = $this->gameRepo->findBySlug($idOrSlug);
        }

        if ($game === null) {
            return Response::json([
                'success' => false,
                'message' => 'Không tìm thấy kịch bản yêu cầu',
            ], 404);
        }

        // Lấy cấu hình phiên bản mới nhất
        $cfgStmt = $this->pdo->prepare(
            'SELECT `id`, `version`, `configuration`, `description`
             FROM `game_configurations`
             WHERE `game_id` = :game_id AND `is_active` = 1
             ORDER BY `version` DESC
             LIMIT 1'
        );
        $cfgStmt->bindValue(':game_id', (int) $game['id'], PDO::PARAM_INT);
        $cfgStmt->execute();
        $config = $cfgStmt->fetch(PDO::FETCH_ASSOC);

        if ($config !== false && !empty($config['configuration'])) {
            $game['configuration'] = json_decode((string) $config['configuration'], true);
            $game['game_configuration_id'] = (int) $config['id'];
        }

        // Kèm bảng giá
        $game['prices'] = $this->gameRepo->getActivePrices((int) $game['id']);

        return Response::json([
            'success' => true,
            'data' => [
                'game' => $game,
            ],
        ]);
    }

    /**
     * GET /api/games/{id}/prices
     */
    public function prices(Request $request, string $id): Response
    {
        $gameId = (int) $id;
        $game = $this->gameRepo->findById($gameId);

        if ($game === null) {
            return Response::json([
                'success' => false,
                'message' => 'Không tìm thấy kịch bản yêu cầu',
            ], 404);
        }

        $prices = $this->gameRepo->getActivePrices($gameId);

        return Response::json([
            'success' => true,
            'data' => [
                'game_id' => $gameId,
                'game_name' => $game['name'],
                'prices' => $prices,
            ],
        ]);
    }
}
