<?php

declare(strict_types=1);

namespace App\Features\GameroomManagement\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Features\GameCatalog\Repositories\GameRepository;
use App\Features\GameroomManagement\Repositories\GameRoomRepository;
use App\Infrastructure\Database\DatabaseConnection;
use PDO;

class GameRoomController
{
    protected GameRoomRepository $roomRepo;
    protected GameRepository $gameRepo;
    protected PDO $pdo;

    public function __construct(
        ?GameRoomRepository $roomRepo = null,
        ?GameRepository $gameRepo = null,
        ?PDO $pdo = null
    ) {
        $this->pdo = $pdo ?? DatabaseConnection::getInstance();
        $this->roomRepo = $roomRepo ?? new GameRoomRepository($this->pdo);
        $this->gameRepo = $gameRepo ?? new GameRepository($this->pdo);

        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }
    }

    /**
     * GET /api/rooms
     * Lấy danh sách các phòng đang tuyển người (FORMING)
     */
    public function index(Request $request): Response
    {
        $gameId = $request->query('game_id') !== null ? (int) $request->query('game_id') : null;
        $status = (string) $request->query('status', 'FORMING');
        $limit = max(1, min(100, (int) $request->query('limit', 20)));
        $offset = max(0, (int) $request->query('offset', 0));

        $sql = 'SELECT gr.`id`, gr.`game_id`, gr.`creator_id`, gr.`title`, gr.`description`,
                       gr.`min_players`, gr.`max_players`, gr.`status`, gr.`formation_deadline_at`,
                       gr.`replacement_status`, gr.`replacement_deadline_at`,
                       g.`name` AS `game_name`, g.`image_url` AS `game_image_url`, g.`duration_minutes`,
                       u.`full_name` AS `creator_name`,
                       (SELECT COUNT(*) FROM `room_participants` rp
                        WHERE rp.`game_room_id` = gr.`id`
                          AND rp.`status` IN (\'PENDING_DEPOSIT\', \'DEPOSITED\', \'LEAVE_REQUESTED\')
                       ) AS `current_players`
                FROM `game_rooms` gr
                INNER JOIN `games` g ON g.`id` = gr.`game_id`
                INNER JOIN `users` u ON u.`id` = gr.`creator_id`
                WHERE 1 = 1';

        $bindings = [];

        if ($status !== 'ALL') {
            $sql .= ' AND gr.`status` = :status';
            $bindings[':status'] = [$status, PDO::PARAM_STR];
        }

        if ($gameId !== null && $gameId > 0) {
            $sql .= ' AND gr.`game_id` = :game_id';
            $bindings[':game_id'] = [$gameId, PDO::PARAM_INT];
        }

        $sql .= ' ORDER BY gr.`id` DESC LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($bindings as $key => [$val, $type]) {
            $stmt->bindValue($key, $val, $type);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return Response::json([
            'success' => true,
            'data' => [
                'rooms' => $rooms,
                'count' => count($rooms),
            ],
        ]);
    }

    /**
     * GET /api/rooms/{id}
     * Chi tiết phòng chơi và danh sách người tham gia
     */
    public function show(Request $request, string $id): Response
    {
        $roomId = (int) $id;
        $room = $this->roomRepo->findById($roomId);

        if ($room === null) {
            return Response::json([
                'success' => false,
                'message' => 'Không tìm thấy phòng chơi yêu cầu',
            ], 404);
        }

        // Lấy kịch bản game
        $game = $this->gameRepo->findById((int) $room['game_id']);
        $room['game'] = $game;

        // Lấy danh sách thành viên tham gia
        $participants = $this->roomRepo->getParticipants($roomId);
        $room['participants'] = $participants;
        $room['current_players'] = count(array_filter($participants, function ($p) {
            return in_array($p['status'], ['PENDING_DEPOSIT', 'DEPOSITED', 'LEAVE_REQUESTED'], true);
        }));

        // Lấy các đề xuất lịch của phòng
        $propStmt = $this->pdo->prepare(
            'SELECT sp.`id`, sp.`game_room_id`, sp.`proposed_by`, sp.`proposed_start_at`, sp.`proposed_end_at`,
                    sp.`status`, sp.`expires_at`, sp.`confirmed_at`, sp.`rejected_at`, sp.`created_at`,
                    u.`full_name` AS `proposer_name`
             FROM `schedule_proposals` sp
             INNER JOIN `users` u ON u.`id` = sp.`proposed_by`
             WHERE sp.`game_room_id` = :room_id
             ORDER BY sp.`id` DESC'
        );
        $propStmt->bindValue(':room_id', $roomId, PDO::PARAM_INT);
        $propStmt->execute();
        $room['proposals'] = $propStmt->fetchAll(PDO::FETCH_ASSOC);

        return Response::json([
            'success' => true,
            'data' => [
                'room' => $room,
            ],
        ]);
    }

    /**
     * POST /api/rooms
     * Tạo phòng ghép người chơi mới
     */
    public function create(Request $request): Response
    {
        $userId = $this->resolveCurrentUserId($request);
        if ($userId === null) {
            return Response::json([
                'success' => false,
                'message' => 'Bạn cần đăng nhập để tạo phòng chơi',
            ], 401);
        }

        $body = $request->getBodyParams();
        $gameId = (int) ($body['game_id'] ?? 0);
        $title = trim((string) ($body['title'] ?? ''));
        $description = isset($body['description']) ? trim((string) $body['description']) : null;
        $formationDeadline = $body['formation_deadline_at'] ?? $body['formation_deadline'] ?? null;

        if ($gameId <= 0) {
            return Response::json([
                'success' => false,
                'message' => 'Vui lòng chọn kịch bản game hợp lệ',
            ], 422);
        }

        $game = $this->gameRepo->findById($gameId);
        if ($game === null) {
            return Response::json([
                'success' => false,
                'message' => 'Kịch bản game không tồn tại trên hệ thống',
            ], 404);
        }

        if ($title === '') {
            $title = 'Phòng chơi ' . $game['name'] . ' #' . strtoupper(substr(uniqid(), -4));
        }

        $minPlayers = isset($body['min_players']) ? (int) $body['min_players'] : (int) $game['min_players'];
        $maxPlayers = isset($body['max_players']) ? (int) $body['max_players'] : (int) $game['max_players'];

        if ($deadlineStr = $formationDeadline) {
            $deadline = date('Y-m-d H:i:s', strtotime((string) $deadlineStr));
        } else {
            $deadline = date('Y-m-d H:i:s', strtotime('+3 days'));
        }

        // Lấy configuration id v1
        $cfgStmt = $this->pdo->prepare('SELECT `id` FROM `game_configurations` WHERE `game_id` = :game_id ORDER BY `version` DESC LIMIT 1');
        $cfgStmt->bindValue(':game_id', $gameId, PDO::PARAM_INT);
        $cfgStmt->execute();
        $cfgId = (int) ($cfgStmt->fetchColumn() ?: 1);

        $roomId = $this->roomRepo->create([
            'game_id' => $gameId,
            'game_configuration_id' => $cfgId,
            'creator_id' => $userId,
            'title' => $title,
            'description' => $description,
            'min_players' => $minPlayers,
            'max_players' => $maxPlayers,
            'status' => 'FORMING',
            'formation_deadline_at' => $deadline,
        ]);

        // Thêm creator vào làm thành viên đầu tiên
        $this->roomRepo->addParticipant($roomId, $userId);

        $createdRoom = $this->roomRepo->findById($roomId);

        return Response::json([
            'success' => true,
            'message' => 'Tạo phòng chơi thành công',
            'data' => [
                'room' => $createdRoom,
            ],
        ], 201);
    }

    /**
     * POST /api/rooms/{id}/join
     * Tham gia vào phòng chơi
     */
    public function join(Request $request, string $id): Response
    {
        $userId = $this->resolveCurrentUserId($request);
        if ($userId === null) {
            return Response::json([
                'success' => false,
                'message' => 'Bạn cần đăng nhập để tham gia phòng',
            ], 401);
        }

        $roomId = (int) $id;
        $room = $this->roomRepo->findById($roomId);
        if ($room === null) {
            return Response::json([
                'success' => false,
                'message' => 'Phòng chơi không tồn tại',
            ], 404);
        }

        if ($room['status'] !== 'FORMING') {
            return Response::json([
                'success' => false,
                'message' => 'Phòng chơi hiện không trong giai đoạn nhận thành viên (Trạng thái: ' . $room['status'] . ')',
            ], 400);
        }

        // Kiểm tra số lượng người hiện tại
        $participants = $this->roomRepo->getParticipants($roomId);
        $activeParticipants = array_filter($participants, function ($p) {
            return in_array($p['status'], ['PENDING_DEPOSIT', 'DEPOSITED', 'LEAVE_REQUESTED'], true);
        });

        // Kiểm tra xem user này đã ở trong phòng chưa
        foreach ($activeParticipants as $part) {
            if ((int) $part['user_id'] === $userId) {
                return Response::json([
                    'success' => false,
                    'message' => 'Bạn đã có tên trong phòng chơi này rồi',
                ], 409);
            }
        }

        if (count($activeParticipants) >= (int) $room['max_players']) {
            return Response::json([
                'success' => false,
                'message' => 'Phòng chơi đã đủ số lượng thành viên tối đa (' . $room['max_players'] . ' người)',
            ], 400);
        }

        $participantId = $this->roomRepo->addParticipant($roomId, $userId);

        return Response::json([
            'success' => true,
            'message' => 'Tham gia phòng chơi thành công',
            'data' => [
                'room_id' => $roomId,
                'participant_id' => $participantId,
                'current_players' => count($activeParticipants) + 1,
            ],
        ]);
    }

    /**
     * POST /api/rooms/{id}/leave
     * Rời phòng chơi hoặc mở cửa sổ tìm người thay thế
     */
    public function leave(Request $request, string $id): Response
    {
        $userId = $this->resolveCurrentUserId($request);
        if ($userId === null) {
            return Response::json([
                'success' => false,
                'message' => 'Bạn cần đăng nhập để thực hiện thao tác này',
            ], 401);
        }

        $roomId = (int) $id;
        $room = $this->roomRepo->findById($roomId);
        if ($room === null) {
            return Response::json([
                'success' => false,
                'message' => 'Phòng chơi không tồn tại',
            ], 404);
        }

        // Tìm participant active của user trong room
        $stmt = $this->pdo->prepare(
            'SELECT `id`, `status` FROM `room_participants`
             WHERE `game_room_id` = :room_id AND `user_id` = :user_id
               AND `status` IN (\'PENDING_DEPOSIT\', \'DEPOSITED\', \'LEAVE_REQUESTED\')
             LIMIT 1'
        );
        $stmt->bindValue(':room_id', $roomId, PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $part = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($part === false) {
            return Response::json([
                'success' => false,
                'message' => 'Bạn không phải là thành viên đang hoạt động trong phòng này',
            ], 404);
        }

        $partId = (int) $part['id'];

        if ($room['status'] === 'FORMING') {
            // Rời phòng trực tiếp khi phòng chưa chốt lịch
            $upStmt = $this->pdo->prepare('UPDATE `room_participants` SET `status` = \'LEFT\', `left_at` = NOW() WHERE `id` = :id');
            $upStmt->bindValue(':id', $partId, PDO::PARAM_INT);
            $upStmt->execute();

            return Response::json([
                'success' => true,
                'message' => 'Đã rời phòng chơi thành công',
            ]);
        }

        // Nếu phòng đã chốt lịch/cọc: Mở cửa sổ tìm người thay thế (Replacement Window)
        $upStmt = $this->pdo->prepare('UPDATE `room_participants` SET `status` = \'LEAVE_REQUESTED\' WHERE `id` = :id');
        $upStmt->bindValue(':id', $partId, PDO::PARAM_INT);
        $upStmt->execute();

        $openRw = $this->pdo->prepare(
            'UPDATE `game_rooms`
             SET `replacement_opened_by` = :uid,
                 `replacement_reason` = \'Thành viên bận xin rút và tìm người thay thế\',
                 `replacement_status` = \'OPEN\',
                 `replacement_started_at` = NOW(),
                 `replacement_deadline_at` = DATE_ADD(NOW(), INTERVAL 24 HOUR)
             WHERE `id` = :room_id'
        );
        $openRw->bindValue(':uid', $userId, PDO::PARAM_INT);
        $openRw->bindValue(':room_id', $roomId, PDO::PARAM_INT);
        $openRw->execute();

        return Response::json([
            'success' => true,
            'message' => 'Yêu cầu rút phòng đã được ghi nhận. Đã mở cửa sổ tuyển người thay thế trong 24 giờ.',
            'data' => [
                'replacement_status' => 'OPEN',
            ],
        ]);
    }

    /**
     * Resolve current user ID from session or header.
     */
    protected function resolveCurrentUserId(Request $request): ?int
    {
        if (!empty($_SESSION['user_id'])) {
            return (int) $_SESSION['user_id'];
        }

        $authHeader = $request->getHeader('authorization');
        if ($authHeader !== null && preg_match('/Bearer\s+(\d+)/i', $authHeader, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }
}
