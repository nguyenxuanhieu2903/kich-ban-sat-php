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
     * GET /api/v1/games
     * Query: ?limit=20&offset=0&players=6&search=an+mang
     */
    public function index(Request $request): Response
    {
        $limit = max(1, min(100, (int) $request->query('limit', 20)));
        $offset = max(0, (int) $request->query('offset', 0));
        $players = $request->query('players') !== null && $request->query('players') !== '' ? (int) $request->query('players') : null;
        $search = $request->query('search', $request->query('q'));
        $search = $search !== null ? trim((string) $search) : null;

        $games = $this->gameRepo->listActive($limit, $offset, $players, $search);

        return Response::success([
            'games' => $games,
            'count' => count($games),
            'limit' => $limit,
            'offset' => $offset,
        ], 'Danh sách kịch bản');
    }

    /**
     * GET /api/v1/games/{id}
     */
    public function show(Request $request, string $idOrSlug): Response
    {
        if (ctype_digit($idOrSlug)) {
            $game = $this->gameRepo->findById((int) $idOrSlug);
        } else {
            $game = $this->gameRepo->findBySlug($idOrSlug);
        }

        if ($game === null) {
            return Response::fail('Không tìm thấy kịch bản yêu cầu', 'GAME_NOT_FOUND', 404);
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

        return Response::success([
            'game' => $game,
        ], 'Chi tiết kịch bản');
    }

    /**
     * GET /api/v1/games/{id}/prices
     */
    public function prices(Request $request, string $id): Response
    {
        $gameId = (int) $id;
        $game = $this->gameRepo->findById($gameId);

        if ($game === null) {
            return Response::fail('Không tìm thấy kịch bản yêu cầu', 'GAME_NOT_FOUND', 404);
        }

        $prices = $this->gameRepo->getActivePrices($gameId);

        return Response::success([
            'game_id' => $gameId,
            'game_name' => $game['name'],
            'prices' => $prices,
        ], 'Bảng giá kịch bản');
    }
}
