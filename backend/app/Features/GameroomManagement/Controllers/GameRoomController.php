<?php

declare(strict_types=1);

namespace App\Features\GameroomManagement\Controllers;

use App\Core\Middleware\AuthMiddleware;
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
     * GET /api/v1/rooms
     * Lấy danh sách các phòng đang tuyển người (FORMING)
     */
    public function index(Request $request): Response
    {
        $auth = AuthMiddleware::authenticate($request);
        if ($auth instanceof Response) {
            return Response::fail('Vui lòng đăng nhập để xem danh sách phòng đang tuyển', 'UNAUTHORIZED', 401);
        }

        $gameId = $request->query('game_id') !== null ? (int) $request->query('game_id') : null;
        $statusRaw = $request->query('status');
        $status = ($statusRaw !== null && trim((string) $statusRaw) !== '') ? strtoupper(trim((string) $statusRaw)) : 'FORMING';
        $searchQ = trim((string) ($request->query('q') ?? $request->query('search') ?? ''));
        $genre = trim((string) ($request->query('genre') ?? ''));

        $perPageParam = $request->query('per_page') ?? $request->query('limit');
        $limit = max(1, min(100, (int) ($perPageParam ?? 20)));
        $page = max(1, (int) ($request->query('page') ?? 1));
        $offset = $request->query('offset') !== null
            ? max(0, (int) $request->query('offset'))
            : ($page - 1) * $limit;

        $whereSql = ' WHERE 1 = 1';
        $bindings = [];

        if ($status !== 'ALL') {
            $whereSql .= ' AND gr.`status` = :status';
            $bindings[':status'] = [$status, PDO::PARAM_STR];
        }

        if ($gameId !== null && $gameId > 0) {
            $whereSql .= ' AND gr.`game_id` = :game_id';
            $bindings[':game_id'] = [$gameId, PDO::PARAM_INT];
        }

        if ($searchQ !== '') {
            $whereSql .= ' AND (gr.`title` LIKE :q_title OR g.`name` LIKE :q_game)';
            $like = '%' . $searchQ . '%';
            $bindings[':q_title'] = [$like, PDO::PARAM_STR];
            $bindings[':q_game'] = [$like, PDO::PARAM_STR];
        }

        if ($genre !== '' && strtolower($genre) !== 'all') {
            $whereSql .= ' AND (g.`name` LIKE :genre_name OR g.`description` LIKE :genre_desc)';
            $genreLike = '%' . $genre . '%';
            $bindings[':genre_name'] = [$genreLike, PDO::PARAM_STR];
            $bindings[':genre_desc'] = [$genreLike, PDO::PARAM_STR];
        }

        $bookingType = $request->query('booking_type') !== null ? strtoupper(trim((string) $request->query('booking_type'))) : null;
        if ($bookingType !== null && in_array($bookingType, ['PUBLIC', 'PRIVATE'], true)) {
            $whereSql .= ' AND gr.`booking_type` = :booking_type';
            $bindings[':booking_type'] = [$bookingType, PDO::PARAM_STR];
        }

        $countSql = 'SELECT COUNT(*) FROM `game_rooms` gr INNER JOIN `games` g ON g.`id` = gr.`game_id`' . $whereSql;
        $countStmt = $this->pdo->prepare($countSql);
        foreach ($bindings as $key => [$val, $type]) {
            $countStmt->bindValue($key, $val, $type);
        }
        $countStmt->execute();
        $total = (int) $countStmt->fetchColumn();

        $sql = 'SELECT gr.`id`, gr.`game_id`, gr.`creator_id`, gr.`title`, gr.`description`,
                       gr.`min_players`, gr.`max_players`, gr.`status`, gr.`formation_deadline_at`,
                       gr.`booking_type`, gr.`target_venue_id`, gr.`target_time_slot`,
                       gr.`total_players`, gr.`confirmed_players`, gr.`required_players`, gr.`guest_slots`, gr.`is_18_plus_acknowledged`,
                       gr.`replacement_status`, gr.`replacement_deadline_at`,
                       g.`name` AS `game_name`, g.`image_url` AS `game_image_url`, g.`duration_minutes`,
                       u.`full_name` AS `creator_name`,
                       v.`name` AS `target_venue_name`,
                       (SELECT COUNT(*) FROM `room_participants` rp
                        WHERE rp.`game_room_id` = gr.`id`
                          AND rp.`status` IN (\'PENDING_DEPOSIT\', \'DEPOSITED\', \'LEAVE_REQUESTED\')
                       ) AS `current_players`
                FROM `game_rooms` gr
                INNER JOIN `games` g ON g.`id` = gr.`game_id`
                INNER JOIN `users` u ON u.`id` = gr.`creator_id`
                LEFT JOIN `venues` v ON v.`id` = gr.`target_venue_id`'
            . $whereSql
            . ' ORDER BY gr.`id` DESC LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($bindings as $key => [$val, $type]) {
            $stmt->bindValue($key, $val, $type);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rooms as &$room) {
            $rawCount = (int) ($room['current_players'] ?? 0);
            $creatorSeats = max(1, (int) ($room['total_players'] ?? 1));
            $maxP = (int) ($room['max_players'] ?? 8);
            $occupied = $rawCount > 0 ? min($maxP, $creatorSeats + max(0, $rawCount - 1)) : 0;
            $room['current_players'] = $occupied;
            $room['member_count'] = $occupied;
        }
        unset($room);

        return Response::success([
            'rooms' => $rooms,
            'count' => count($rooms),
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $limit,
                'total_pages' => max(1, (int) ceil($total / $limit)),
            ],
        ], 'Danh sách phòng chơi');
    }

    /**
     * GET /api/v1/rooms/{id}
     * Chi tiết phòng chơi và danh sách người tham gia
     */
    public function show(Request $request, string $id): Response
    {
        $auth = AuthMiddleware::authenticate($request);
        if ($auth instanceof Response) {
            return Response::fail('Vui lòng đăng nhập để xem chi tiết phòng chơi', 'UNAUTHORIZED', 401);
        }

        $roomId = (int) $id;
        $this->performLazyExpirationCheck($roomId);
        $room = $this->roomRepo->findById($roomId);

        if ($room === null) {
            return Response::fail('Không tìm thấy phòng chơi yêu cầu', 'ROOM_NOT_FOUND', 404);
        }

        // Lấy kịch bản game
        $game = $this->gameRepo->findById((int) $room['game_id']);
        $room['game'] = $game;

        // Lấy tên chi nhánh dự kiến (nếu có)
        if (!empty($room['target_venue_id'])) {
            $vStmt = $this->pdo->prepare('SELECT `name`, `address` FROM `venues` WHERE `id` = :vid LIMIT 1');
            $vStmt->bindValue(':vid', (int) $room['target_venue_id'], PDO::PARAM_INT);
            $vStmt->execute();
            $vInfo = $vStmt->fetch(PDO::FETCH_ASSOC);
            if ($vInfo !== false) {
                $room['target_venue_name'] = $vInfo['name'];
                $room['target_venue_address'] = $vInfo['address'];
            }
        }

        // Lấy danh sách thành viên tham gia (chỉ thành viên đang hoạt động)
        $participants = $this->roomRepo->getParticipants($roomId);
        $room['participants'] = $participants;
        $activeParts = array_filter($participants, function ($p) {
            return in_array($p['status'], ['PENDING_DEPOSIT', 'DEPOSITED', 'LEAVE_REQUESTED'], true);
        });
        $creatorInActive = false;
        foreach ($activeParts as $ap) {
            if ((int) $ap['user_id'] === (int) ($room['creator_id'] ?? 0)) {
                $creatorInActive = true;
                break;
            }
        }
        $creatorSeats = $creatorInActive ? max(1, (int) ($room['total_players'] ?? 1)) : 0;
        $otherSeats = max(0, count($activeParts) - ($creatorInActive ? 1 : 0));
        $room['current_players'] = min((int) ($room['max_players'] ?? 8), $creatorSeats + $otherSeats);

        // Xác định user hiện tại (nếu có) để trả về phiếu biểu quyết và kiểm tra quyền xem invite_token
        $currentUserId = $this->resolveCurrentUserId($request);
        $myParticipantId = null;
        if ($currentUserId !== null) {
            foreach ($participants as $part) {
                if ((int) $part['user_id'] === $currentUserId) {
                    $myParticipantId = (int) $part['id'];
                    break;
                }
            }
        }

        // Chỉ trả về invite_token cho thành viên trong phòng, Chủ phòng, DM hoặc Admin (chống lộ mã mời phòng PRIVATE)
        $currentUserRole = strtoupper((string) ($_SESSION['role_code'] ?? $_SESSION['role'] ?? ''));
        $canSeeInviteToken = $myParticipantId !== null
            || ($currentUserId !== null && (int) ($room['creator_id'] ?? 0) === $currentUserId)
            || in_array($currentUserRole, ['ADMIN', 'DM'], true);
        if ($canSeeInviteToken) {
            $room['invite_token'] = $this->buildInviteToken($room);
        }

        // Lấy các đề xuất lịch của phòng kèm thống kê phiếu biểu quyết
        $propStmt = $this->pdo->prepare(
            'SELECT sp.`id`, sp.`game_room_id`, sp.`proposed_by`, sp.`proposed_start_at`, sp.`proposed_end_at`,
                    sp.`status`, sp.`expires_at`, sp.`confirmed_at`, sp.`rejected_at`, sp.`created_at`,
                    u.`full_name` AS `proposer_name`,
                    (SELECT COUNT(*) FROM `schedule_responses` sr WHERE sr.`schedule_proposal_id` = sp.`id` AND sr.`response` = \'ACCEPTED\') AS `accepted_votes`,
                    (SELECT COUNT(*) FROM `schedule_responses` sr WHERE sr.`schedule_proposal_id` = sp.`id` AND sr.`response` = \'REJECTED\') AS `rejected_votes`
             FROM `schedule_proposals` sp
             INNER JOIN `users` u ON u.`id` = sp.`proposed_by`
             WHERE sp.`game_room_id` = :room_id
             ORDER BY sp.`id` DESC'
        );
        $propStmt->bindValue(':room_id', $roomId, PDO::PARAM_INT);
        $propStmt->execute();
        $proposals = $propStmt->fetchAll(PDO::FETCH_ASSOC);

        if ($myParticipantId !== null && !empty($proposals)) {
            $myVoteStmt = $this->pdo->prepare(
                'SELECT `schedule_proposal_id`, `response` FROM `schedule_responses`
                 WHERE `game_room_id` = :room_id AND `room_participant_id` = :part_id'
            );
            $myVoteStmt->bindValue(':room_id', $roomId, PDO::PARAM_INT);
            $myVoteStmt->bindValue(':part_id', $myParticipantId, PDO::PARAM_INT);
            $myVoteStmt->execute();
            $myVotesMap = [];
            foreach ($myVoteStmt->fetchAll(PDO::FETCH_ASSOC) as $vr) {
                $myVotesMap[(int) $vr['schedule_proposal_id']] = $vr['response'];
            }
            foreach ($proposals as &$prop) {
                $prop['accepted_votes'] = (int) $prop['accepted_votes'];
                $prop['rejected_votes'] = (int) $prop['rejected_votes'];
                $prop['total_active'] = $room['current_players'];
                $prop['my_vote'] = $myVotesMap[(int) $prop['id']] ?? null;
            }
            unset($prop);
        } else {
            foreach ($proposals as &$prop) {
                $prop['accepted_votes'] = (int) $prop['accepted_votes'];
                $prop['rejected_votes'] = (int) $prop['rejected_votes'];
                $prop['total_active'] = $room['current_players'];
                $prop['my_vote'] = null;
            }
            unset($prop);
        }
        $room['proposals'] = $proposals;

        // Lấy thông tin Session chính thức (nếu đã chốt lịch)
        $sessStmt = $this->pdo->prepare(
            'SELECT s.`id`, s.`venue_id`, s.`physical_room_id`, s.`start_at`, s.`end_at`, s.`status`,
                    v.`name` AS `venue_name`, v.`address` AS `venue_address`,
                    pr.`name` AS `physical_room_name`,
                    dm.`full_name` AS `dm_name`, dma.`status` AS `dm_assignment_status`
             FROM `sessions` s
             LEFT JOIN `venues` v ON v.`id` = s.`venue_id`
             LEFT JOIN `physical_rooms` pr ON pr.`id` = s.`physical_room_id`
             LEFT JOIN `dm_assignments` dma ON dma.`id` = s.`dm_assignment_id`
             LEFT JOIN `users` dm ON dm.`id` = dma.`dm_user_id`
             WHERE s.`game_room_id` = :room_id
             ORDER BY s.`id` DESC
             LIMIT 1'
        );
        $sessStmt->bindValue(':room_id', $roomId, PDO::PARAM_INT);
        $sessStmt->execute();
        $sessionRow = $sessStmt->fetch(PDO::FETCH_ASSOC);
        $room['session'] = $sessionRow !== false ? $sessionRow : null;

        return Response::success([
            'room' => $room,
        ], 'Chi tiết phòng chơi');
    }

    /**
     * Tạo mã mời ổn định cho phòng chơi (không cần đổi schema CSDL).
     *
     * @param array<string, mixed> $room
     */
    protected function buildInviteToken(array $room): string
    {
        $seed = 'KBS_V16_INVITE_' . ($room['id'] ?? 0) . '_' . ($room['creator_id'] ?? 0) . '_' . ($room['created_at'] ?? '');
        return strtoupper(substr(hash('sha256', $seed), 0, 10));
    }

    /**
     * POST /api/v1/rooms
     * Tạo phòng ghép người chơi mới
     */
    public function create(Request $request): Response
    {
        $auth = AuthMiddleware::authenticate($request);
        if ($auth instanceof Response) {
            return Response::fail('Bạn cần đăng nhập để tạo phòng chơi', 'UNAUTHORIZED', 401);
        }
        $userRole = strtoupper((string) ($auth['role_code'] ?? $auth['role'] ?? 'PLAYER'));
        if (in_array($userRole, ['DM', 'ADMIN'], true)) {
            return Response::fail('Tài khoản Quản trò (DM) và Quản trị viên (Admin) không tạo phòng ghép người chơi trực tiếp', 'FORBIDDEN_ROLE', 403);
        }
        $userId = (int) $auth['id'];

        $body = $request->getBodyParams();
        $gameId = (int) ($body['game_id'] ?? 0);
        $title = trim((string) ($body['title'] ?? ''));
        $description = isset($body['description']) ? trim((string) $body['description']) : null;
        $formationDeadline = $body['formation_deadline_at'] ?? $body['formation_deadline'] ?? null;

        if ($gameId <= 0) {
            return Response::fail('Vui lòng chọn kịch bản game hợp lệ', 'VALIDATION_ERROR', 422);
        }

        $game = $this->gameRepo->findById($gameId);
        if ($game === null) {
            return Response::fail('Kịch bản game không tồn tại trên hệ thống', 'NOT_FOUND', 404);
        }
        if (strtoupper((string) ($game['status'] ?? 'ACTIVE')) !== 'ACTIVE') {
            return Response::fail('Kịch bản này hiện đang tạm ẩn và không nhận đặt phòng mới', 'GAME_INACTIVE', 422);
        }

        if ($title === '') {
            $title = 'Phòng chơi ' . $game['name'] . ' #' . strtoupper(substr(uniqid(), -4));
        }

        $minPlayers = isset($body['min_players']) ? (int) $body['min_players'] : (int) $game['min_players'];
        $maxPlayers = isset($body['max_players']) ? (int) $body['max_players'] : (int) $game['max_players'];

        if ($minPlayers < 2) {
            $minPlayers = (int) $game['min_players'];
        }
        if ($maxPlayers < $minPlayers) {
            return Response::fail('Số lượng người chơi tối đa không được nhỏ hơn số lượng tối thiểu', 'VALIDATION_ERROR', 422);
        }

        if ($deadlineStr = $formationDeadline) {
            $deadline = date('Y-m-d H:i:s', strtotime((string) $deadlineStr));
        } else {
            $deadlineDays = isset($body['deadline_days']) ? max(1, min(14, (int) $body['deadline_days'])) : 3;
            $deadline = date('Y-m-d H:i:s', strtotime("+{$deadlineDays} days"));
        }

        // Chống spam: Giới hạn số phòng FORMING một user được mở cùng lúc (tối đa 5 phòng trong môi trường production)
        if (\App\Infrastructure\Config\Config::get('APP_ENV') === 'production') {
            $formingCheck = $this->pdo->prepare('SELECT COUNT(*) FROM `game_rooms` WHERE `creator_id` = :uid AND `status` = \'FORMING\'');
            $formingCheck->bindValue(':uid', $userId, PDO::PARAM_INT);
            $formingCheck->execute();
            if ((int) $formingCheck->fetchColumn() >= 5) {
                return Response::fail('Bạn đang có nhiều phòng chờ ghép đội chưa hoàn tất (tối đa 5 phòng), vui lòng quản lý phòng hiện tại trước khi tạo mới', 'LIMIT_FORMING_ROOMS_EXCEEDED', 422);
            }
        }

        // Đọc thông số Public / Private và target venue/slot
        $bookingType = strtoupper(trim((string) ($body['booking_type'] ?? 'PUBLIC')));
        if (!in_array($bookingType, ['PUBLIC', 'PRIVATE'], true)) {
            $bookingType = 'PUBLIC';
        }

        $targetVenueId = isset($body['target_venue_id']) && (int) $body['target_venue_id'] > 0 ? (int) $body['target_venue_id'] : null;
        $targetTimeSlot = isset($body['target_time_slot']) ? trim((string) $body['target_time_slot']) : null;
        $totalPlayers = isset($body['total_players']) && (int) $body['total_players'] > 0
            ? min($maxPlayers, (int) $body['total_players'])
            : 1;
        $guestSlots = isset($body['guest_slots'])
            ? max(0, min($maxPlayers - 1, (int) $body['guest_slots']))
            : max(0, $maxPlayers - $totalPlayers);
        $is18Plus = !empty($body['is_18_plus_acknowledged']) ? 1 : 0;

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
            'booking_type' => $bookingType,
            'target_venue_id' => $targetVenueId,
            'target_time_slot' => $targetTimeSlot,
            'total_players' => $totalPlayers,
            'confirmed_players' => 1,
            'required_players' => $minPlayers,
            'guest_slots' => $guestSlots,
            'is_18_plus_acknowledged' => $is18Plus,
            'formation_deadline_at' => $deadline,
        ]);

        // Thêm creator vào làm thành viên đầu tiên
        $this->roomRepo->addParticipant($roomId, $userId);

        $createdRoom = $this->roomRepo->findById($roomId);

        return Response::success([
            'room' => $createdRoom,
            'game_room' => $createdRoom,
        ], 'Tạo phòng chơi thành công', null, 201);
    }

    /**
     * POST /api/v1/rooms/{id}/join
     * Tham gia vào phòng chơi
     */
    public function join(Request $request, string $id): Response
    {
        $auth = AuthMiddleware::authenticate($request);
        if ($auth instanceof Response) {
            return Response::fail('Bạn cần đăng nhập để tham gia phòng', 'UNAUTHORIZED', 401);
        }
        $userRole = strtoupper((string) ($auth['role_code'] ?? $auth['role'] ?? 'PLAYER'));
        if (in_array($userRole, ['DM', 'ADMIN'], true)) {
            return Response::fail('Tài khoản Quản trò (DM) và Quản trị viên (Admin) không tham gia làm người chơi trong phòng ghép khách', 'FORBIDDEN_ROLE', 403);
        }
        $userId = (int) $auth['id'];

        $roomId = (int) $id;
        $room = $this->roomRepo->findById($roomId);
        if ($room === null) {
            return Response::fail('Phòng chơi không tồn tại', 'ROOM_NOT_FOUND', 404);
        }

        $isReplacementOpen = ($room['replacement_status'] ?? '') === 'OPEN';

        if ($room['status'] !== 'FORMING' && !$isReplacementOpen) {
            return Response::fail('Phòng chơi hiện không trong giai đoạn nhận thành viên (Trạng thái: ' . $room['status'] . ')', 'ROOM_NOT_FORMING', 400);
        }

        // Kiểm tra số lượng người hiện tại
        $participants = $this->roomRepo->getParticipants($roomId);
        $activeParticipants = array_filter($participants, function ($p) {
            return in_array($p['status'], ['PENDING_DEPOSIT', 'DEPOSITED', 'LEAVE_REQUESTED'], true);
        });

        // Kiểm tra xem user này đã ở trong phòng chưa
        foreach ($activeParticipants as $part) {
            if ((int) $part['user_id'] === $userId) {
                return Response::fail('Bạn đã có tên trong phòng chơi này rồi', 'ALREADY_JOINED', 409);
            }
        }

        $body = $request->getBodyParams();
        $allowReplay = !empty($body['allow_replay']) || !empty($body['acknowledge_spoiler']);
        $checkAntiReplay = !empty($body['strict_anti_spoiler']) || !empty($body['check_replay']);

        // Kiểm soát phòng Nhóm Riêng (PRIVATE): nếu guest_slots = 0 thì chỉ cho vào khi có đúng invite_token từ Host (trừ lúc mở Replacement Window)
        if (($room['booking_type'] ?? 'PUBLIC') === 'PRIVATE' && !$isReplacementOpen) {
            $guestSlots = (int) ($room['guest_slots'] ?? 0);
            $suppliedToken = strtoupper(trim((string) ($body['invite_token'] ?? $request->query('invite') ?? '')));
            $expectedToken = $this->buildInviteToken($room);
            $hasValidInvite = $suppliedToken !== '' && hash_equals($expectedToken, $suppliedToken);

            if ($guestSlots <= 0 && !$hasValidInvite) {
                return Response::fail(
                    'Đây là phòng Nhóm Bạn Riêng Tư (không mở slot ghép khách ngoài). Vui lòng xin link mời có mã bảo mật từ Chủ phòng để tham gia.',
                    'PRIVATE_ROOM_CLOSED',
                    403
                );
            }
        }

        // 1. Chống chơi lại kịch bản đã hoàn thành (Bảo vệ bí mật cốt truyện cho cả bàn chơi khi kích hoạt)
        if ($checkAntiReplay && !$allowReplay) {
            $playedCheck = $this->pdo->prepare(
                'SELECT COUNT(*) FROM `room_participants` rp
                 INNER JOIN `game_rooms` gr ON gr.`id` = rp.`game_room_id`
                 INNER JOIN `sessions` s ON s.`game_room_id` = gr.`id`
                 WHERE rp.`user_id` = :uid AND gr.`game_id` = :gid AND s.`status` = \'COMPLETED\''
            );
            $playedCheck->bindValue(':uid', $userId, PDO::PARAM_INT);
            $playedCheck->bindValue(':gid', (int) $room['game_id'], PDO::PARAM_INT);
            $playedCheck->execute();
            if ((int) $playedCheck->fetchColumn() > 0) {
                return Response::fail(
                    'Bạn đã từng tham gia và hoàn tất kịch bản này trước đây. Để đảm bảo trải nghiệm suy luận và giữ kín bí mật vụ án cho các thành viên khác, vui lòng chọn kịch bản khác (hoặc gửi cờ xác nhận allow_replay nếu đi cùng bạn bè).',
                    'ALREADY_COMPLETED_SCENARIO',
                    400
                );
            }
        }

        // 2. Chống trùng lịch ca chơi: Kiểm tra xem người chơi đã có ca chơi khác trùng khung giờ không
        $roomSessStmt = $this->pdo->prepare(
            'SELECT `start_at`, `end_at` FROM `sessions`
             WHERE `game_room_id` = :rid AND `status` IN (\'SCHEDULED\', \'IN_PROGRESS\')
             LIMIT 1'
        );
        $roomSessStmt->bindValue(':rid', $roomId, PDO::PARAM_INT);
        $roomSessStmt->execute();
        $roomSess = $roomSessStmt->fetch(PDO::FETCH_ASSOC);

        if ($roomSess) {
            $conflictStmt = $this->pdo->prepare(
                'SELECT s.`id` FROM `sessions` s
                 INNER JOIN `game_rooms` gr ON gr.`id` = s.`game_room_id`
                 INNER JOIN `room_participants` rp ON rp.`game_room_id` = gr.`id`
                 WHERE rp.`user_id` = :uid
                   AND rp.`status` IN (\'PENDING_DEPOSIT\', \'DEPOSITED\')
                   AND s.`status` IN (\'SCHEDULED\', \'IN_PROGRESS\')
                   AND s.`start_at` < :end_at AND s.`end_at` > :start_at
                 LIMIT 1'
            );
            $conflictStmt->bindValue(':uid', $userId, PDO::PARAM_INT);
            $conflictStmt->bindValue(':start_at', $roomSess['start_at'], PDO::PARAM_STR);
            $conflictStmt->bindValue(':end_at', $roomSess['end_at'], PDO::PARAM_STR);
            $conflictStmt->execute();
            if ($conflictStmt->fetchColumn() !== false) {
                return Response::fail('Bạn đã có một ca chơi khác đã xác nhận trong cùng khung giờ này', 'PLAYER_SCHEDULE_CONFLICT', 409);
            }
        }

        // TRƯỜNG HỢP 1: Vào thay thế (Replacement Slot Takeover) — có khóa FOR UPDATE chống double-booking
        if ($isReplacementOpen) {
            $this->pdo->beginTransaction();
            try {
                $leavePartStmt = $this->pdo->prepare(
                    'SELECT `id`, `user_id`, `status` FROM `room_participants`
                     WHERE `game_room_id` = :room_id AND `status` IN (\'LEAVE_REQUESTED\', \'CANCELLED_UNPAID\')
                     ORDER BY FIELD(`status`, \'LEAVE_REQUESTED\', \'CANCELLED_UNPAID\'), `id` ASC
                     LIMIT 1 FOR UPDATE'
                );
                $leavePartStmt->bindValue(':room_id', $roomId, PDO::PARAM_INT);
                $leavePartStmt->execute();
                $leavingPart = $leavePartStmt->fetch(PDO::FETCH_ASSOC);

                if ($leavingPart === false) {
                    $this->pdo->rollBack();
                    return Response::fail('Hiện không còn slot tìm người thay thế trong phòng này', 'NO_REPLACEMENT_SLOT', 400);
                }

                $leavingPartId = (int) $leavingPart['id'];
                $leavingUserId = (int) $leavingPart['user_id'];
                $leavingStatus = (string) $leavingPart['status'];

                // 1. Cho người cũ rời phòng (atomic check status)
                $upOld = $this->pdo->prepare(
                    'UPDATE `room_participants`
                     SET `status` = \'LEFT\', `left_at` = NOW(), `cancel_reason` = \'Đã có người chơi khác nhận thay thế slot\'
                     WHERE `id` = :id AND `status` = :expected_status'
                );
                $upOld->bindValue(':id', $leavingPartId, PDO::PARAM_INT);
                $upOld->bindValue(':expected_status', $leavingStatus, PDO::PARAM_STR);
                $upOld->execute();

                if ($upOld->rowCount() === 0) {
                    $this->pdo->rollBack();
                    return Response::fail('Slot thay thế này vừa được người khác nhận', 'NO_REPLACEMENT_SLOT', 409);
                }

                // 2. Thêm người mới vào slot thay thế
                $newPartId = $this->roomRepo->addParticipant($roomId, $userId);

                // 3. Kiểm tra xem còn ai LEAVE_REQUESTED hoặc CANCELLED_UNPAID không
                $checkLeftStmt = $this->pdo->prepare(
                    'SELECT COUNT(*) FROM `room_participants`
                     WHERE `game_room_id` = :room_id AND `status` IN (\'LEAVE_REQUESTED\', \'CANCELLED_UNPAID\')'
                );
                $checkLeftStmt->bindValue(':room_id', $roomId, PDO::PARAM_INT);
                $checkLeftStmt->execute();
                $remainingReplacements = (int) $checkLeftStmt->fetchColumn();

                if ($remainingReplacements === 0) {
                    $closeRw = $this->pdo->prepare(
                        'UPDATE `game_rooms`
                         SET `replacement_status` = \'CLOSED\', `replacement_closed_at` = NOW()
                         WHERE `id` = :room_id'
                    );
                    $closeRw->bindValue(':room_id', $roomId, PDO::PARAM_INT);
                    $closeRw->execute();
                }

                $this->pdo->commit();

                // Gửi thông báo cho người cũ
                $notiRepo = new \App\Features\Notification\Repositories\NotificationRepository($this->pdo);
                $notiRepo->createNotification(
                    'Đã có người thay thế slot',
                    "Yêu cầu rút phòng của bạn tại phòng '{$room['title']}' đã hoàn tất nhờ có người chơi khác thế chỗ.",
                    'ROOM',
                    null,
                    [$leavingUserId]
                );

                return Response::success([
                    'room_id' => $roomId,
                    'participant_id' => $newPartId,
                    'is_replacement' => true,
                    'replaced_participant_id' => $leavingPartId,
                    'replacement_window_closed' => $remainingReplacements === 0,
                ], 'Bạn đã tham gia thế chỗ thành công vào phòng chơi');
            } catch (\Throwable $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                throw $e;
            }
        }

        // TRƯỜNG HỢP 2: Vào phòng FORMING bình thường — tính đúng tổng số ghế đã giữ (nhóm Chủ phòng + khách ghép)
        $effectiveMax = (int) ($room['max_players'] ?? 8);
        $creatorSeats = max(1, (int) ($room['total_players'] ?? 1));
        $creatorInActive = false;
        foreach ($activeParticipants as $ap) {
            if ((int) $ap['user_id'] === (int) ($room['creator_id'] ?? 0)) {
                $creatorInActive = true;
                break;
            }
        }
        $occupiedSeats = ($creatorInActive ? $creatorSeats : 0) + max(0, count($activeParticipants) - ($creatorInActive ? 1 : 0));

        if ($occupiedSeats >= $effectiveMax) {
            return Response::fail('Phòng chơi đã đủ số lượng thành viên tối đa (' . $effectiveMax . ' người)', 'ROOM_FULL', 400);
        }

        $this->pdo->beginTransaction();
        try {
            $lockStmt = $this->pdo->prepare('SELECT `id`, `status`, `creator_id`, `max_players`, `total_players` FROM `game_rooms` WHERE `id` = :id FOR UPDATE');
            $lockStmt->bindValue(':id', $roomId, PDO::PARAM_INT);
            $lockStmt->execute();
            $roomRow = $lockStmt->fetch(PDO::FETCH_ASSOC);
            if ($roomRow === false || strtoupper((string) ($roomRow['status'] ?? '')) !== 'FORMING') {
                $this->pdo->rollBack();
                return Response::fail('Phòng chơi không còn mở nhận thành viên mới', 'ROOM_NOT_FORMING', 400);
            }
            $effectiveMax = (int) ($roomRow['max_players'] ?? $room['max_players']);

            $cntStmt = $this->pdo->prepare(
                'SELECT `user_id` FROM `room_participants`
                 WHERE `game_room_id` = :id AND `status` IN (\'PENDING_DEPOSIT\', \'DEPOSITED\', \'LEAVE_REQUESTED\')'
            );
            $cntStmt->bindValue(':id', $roomId, PDO::PARAM_INT);
            $cntStmt->execute();
            $activeUids = array_map('intval', $cntStmt->fetchAll(PDO::FETCH_COLUMN));
            $hasCreator = in_array((int) ($roomRow['creator_id'] ?? 0), $activeUids, true);
            $currOccupied = ($hasCreator ? max(1, (int) ($roomRow['total_players'] ?? 1)) : 0) + max(0, count($activeUids) - ($hasCreator ? 1 : 0));

            if ($currOccupied >= $effectiveMax) {
                $this->pdo->rollBack();
                return Response::fail('Phòng chơi đã đủ số lượng thành viên tối đa (' . $effectiveMax . ' người)', 'ROOM_FULL', 400);
            }

            $participantId = $this->roomRepo->addParticipant($roomId, $userId);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return Response::success([
            'room_id' => $roomId,
            'participant_id' => $participantId,
            'current_players' => $currOccupied + 1,
        ], 'Tham gia phòng chơi thành công');
    }

    /**
     * POST /api/v1/rooms/{id}/leave
     * Rời phòng chơi hoặc mở cửa sổ tìm người thay thế
     */
    public function leave(Request $request, string $id): Response
    {
        $userId = $this->resolveCurrentUserId($request);
        if ($userId === null) {
            return Response::fail('Bạn cần đăng nhập để thực hiện thao tác này', 'UNAUTHORIZED', 401);
        }

        $roomId = (int) $id;
        $room = $this->roomRepo->findById($roomId);
        if ($room === null) {
            return Response::fail('Phòng chơi không tồn tại', 'ROOM_NOT_FOUND', 404);
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
            return Response::fail('Bạn không phải là thành viên đang hoạt động trong phòng này', 'PARTICIPANT_NOT_FOUND', 404);
        }

        $partId = (int) $part['id'];

        if (in_array($room['status'], ['CANCELLED', 'COMPLETED', 'EXPIRED'], true)) {
            return Response::fail('Phòng chơi đã kết thúc hoặc bị hủy, không thể rời phòng', 'INVALID_ROOM_STATUS', 400);
        }

        if ($room['status'] === 'FORMING') {
            // Rời phòng trực tiếp khi phòng chưa chốt lịch
            $upStmt = $this->pdo->prepare('UPDATE `room_participants` SET `status` = \'LEFT\', `left_at` = NOW() WHERE `id` = :id');
            $upStmt->bindValue(':id', $partId, PDO::PARAM_INT);
            $upStmt->execute();

            // Nếu người rời phòng là Chủ phòng (Host), chuyển quyền Host cho thành viên tiếp theo
            if ((int) $room['creator_id'] === $userId) {
                $nextHostStmt = $this->pdo->prepare(
                    'SELECT `user_id` FROM `room_participants`
                     WHERE `game_room_id` = :room_id AND `user_id` != :uid
                       AND `status` IN (\'PENDING_DEPOSIT\', \'DEPOSITED\')
                     ORDER BY `joined_at` ASC, `id` ASC LIMIT 1'
                );
                $nextHostStmt->bindValue(':room_id', $roomId, PDO::PARAM_INT);
                $nextHostStmt->bindValue(':uid', $userId, PDO::PARAM_INT);
                $nextHostStmt->execute();
                $nextHostId = $nextHostStmt->fetchColumn();

                if ($nextHostId !== false) {
                    $newGuestSlots = max(0, (int) ($room['max_players'] ?? 6) - 1);
                    $upHost = $this->pdo->prepare(
                        'UPDATE `game_rooms`
                         SET `creator_id` = :next_uid,
                             `total_players` = 1,
                             `guest_slots` = :gslots,
                             `updated_at` = NOW()
                         WHERE `id` = :room_id'
                    );
                    $upHost->bindValue(':next_uid', (int) $nextHostId, PDO::PARAM_INT);
                    $upHost->bindValue(':gslots', $newGuestSlots, PDO::PARAM_INT);
                    $upHost->bindValue(':room_id', $roomId, PDO::PARAM_INT);
                    $upHost->execute();
                } else {
                    // Không còn thành viên nào trong phòng -> Hủy giải tán phòng
                    $disbandStmt = $this->pdo->prepare('UPDATE `game_rooms` SET `status` = \'CANCELLED\', `cancel_reason` = \'Chủ phòng rời và không còn thành viên nào trong phòng\', `cancelled_at` = NOW() WHERE `id` = :room_id');
                    $disbandStmt->bindValue(':room_id', $roomId, PDO::PARAM_INT);
                    $disbandStmt->execute();
                }
            }

            return Response::success(null, 'Đã rời phòng chơi thành công');
        }

        // Nếu phòng đã chốt lịch/cọc: Mở cửa sổ tìm người thay thế (Replacement Window)
        $upStmt = $this->pdo->prepare('UPDATE `room_participants` SET `status` = \'LEAVE_REQUESTED\' WHERE `id` = :id');
        $upStmt->bindValue(':id', $partId, PDO::PARAM_INT);
        $upStmt->execute();

        // Tính replacement_deadline_at không vượt quá giờ bắt đầu Session (Quy tắc V16)
        $sessStmt = $this->pdo->prepare('SELECT `start_at` FROM `sessions` WHERE `game_room_id` = :room_id AND `status` = \'SCHEDULED\' LIMIT 1');
        $sessStmt->bindValue(':room_id', $roomId, PDO::PARAM_INT);
        $sessStmt->execute();
        $sessStart = $sessStmt->fetchColumn();

        $deadlineExpr = 'DATE_ADD(NOW(), INTERVAL 24 HOUR)';
        $sessParam = null;
        if ($sessStart !== false && $sessStart !== null) {
            $sessTs = strtotime((string) $sessStart);
            if ($sessTs < (time() + 24 * 3600)) {
                $deadlineExpr = ':sess_start';
                $sessParam = (string) $sessStart;
            }
        }

        $openRw = $this->pdo->prepare(
            "UPDATE `game_rooms`
             SET `replacement_opened_by` = :uid,
                 `replacement_reason` = 'Thành viên bận xin rút và tìm người thay thế',
                 `replacement_status` = 'OPEN',
                 `replacement_started_at` = NOW(),
                 `replacement_deadline_at` = {$deadlineExpr}
             WHERE `id` = :room_id"
        );
        $openRw->bindValue(':uid', $userId, PDO::PARAM_INT);
        $openRw->bindValue(':room_id', $roomId, PDO::PARAM_INT);
        if ($sessParam !== null) {
            $openRw->bindValue(':sess_start', $sessParam, PDO::PARAM_STR);
        }
        $openRw->execute();

        return Response::success([
            'replacement_status' => 'OPEN',
        ], 'Yêu cầu rút phòng đã được ghi nhận. Đã mở cửa sổ tuyển người thay thế trong 24 giờ.');
    }

    /**
     * POST /api/v1/rooms/{id}/kick
     * Chủ phòng (hoặc Admin) mời một thành viên rời phòng khi phòng đang ở trạng thái FORMING
     */
    public function kick(Request $request, string $id): Response
    {
        $auth = AuthMiddleware::authenticate($request);
        if ($auth instanceof Response) {
            return Response::fail('Bạn cần đăng nhập để thực hiện thao tác này', 'UNAUTHORIZED', 401);
        }

        $actorId = (int) $auth['id'];
        $actorRole = strtoupper((string) ($auth['role_code'] ?? $auth['role'] ?? 'PLAYER'));

        $roomId = (int) $id;
        $room = $this->roomRepo->findById($roomId);
        if ($room === null) {
            return Response::fail('Phòng chơi không tồn tại', 'ROOM_NOT_FOUND', 404);
        }

        if ($actorRole !== 'ADMIN' && (int) ($room['creator_id'] ?? 0) !== $actorId) {
            return Response::fail('Chỉ Chủ phòng hoặc Quản trị viên mới có quyền mời thành viên rời phòng', 'FORBIDDEN', 403);
        }

        if ((string) ($room['status'] ?? '') !== 'FORMING') {
            return Response::fail('Chỉ có thể mời thành viên rời phòng khi phòng đang trong giai đoạn ghép người (FORMING)', 'INVALID_ROOM_STATUS', 400);
        }

        $body = $request->getBodyParams();
        $targetUserId = (int) ($body['user_id'] ?? $body['target_user_id'] ?? 0);
        $reason = trim((string) ($body['reason'] ?? 'Chủ phòng điều chỉnh danh sách thành viên'));

        if ($targetUserId <= 0) {
            return Response::fail('Vui lòng chỉ định thành viên cần mời rời phòng', 'VALIDATION_ERROR', 422);
        }

        if ($targetUserId === (int) ($room['creator_id'] ?? 0)) {
            return Response::fail('Chủ phòng không thể tự mời chính mình rời phòng', 'VALIDATION_ERROR', 422);
        }

        $partStmt = $this->pdo->prepare(
            'SELECT `id`, `status` FROM `room_participants`
             WHERE `game_room_id` = :rid AND `user_id` = :uid
               AND `status` IN (\'PENDING_DEPOSIT\', \'DEPOSITED\', \'LEAVE_REQUESTED\')
             LIMIT 1'
        );
        $partStmt->execute([':rid' => $roomId, ':uid' => $targetUserId]);
        $part = $partStmt->fetch(PDO::FETCH_ASSOC);

        if ($part === false) {
            return Response::fail('Thành viên này không còn hoạt động trong phòng chơi', 'PARTICIPANT_NOT_FOUND', 404);
        }

        $partId = (int) $part['id'];

        $this->pdo->beginTransaction();
        try {
            $upd = $this->pdo->prepare(
                'UPDATE `room_participants`
                 SET `status` = \'LEFT\', `left_at` = NOW(), `cancel_reason` = :reason, `updated_at` = NOW()
                 WHERE `id` = :id'
            );
            $upd->execute([
                ':reason' => mb_substr('Chủ phòng mời rời phòng: ' . $reason, 0, 250),
                ':id' => $partId,
            ]);

            // Nếu thành viên đã cọc sớm trong lúc FORMING, tự động tạo yêu cầu hoàn cọc 100%
            $payStmt = $this->pdo->prepare(
                'SELECT `id`, `paid_amount` FROM `player_payments`
                 WHERE `room_participant_id` = :pid AND `paid_amount` > 0'
            );
            $payStmt->execute([':pid' => $partId]);
            foreach ($payStmt->fetchAll(PDO::FETCH_ASSOC) as $pay) {
                $chkRef = $this->pdo->prepare(
                    'SELECT `id` FROM `refunds`
                     WHERE `player_payment_id` = :ppid AND `status` IN (\'PENDING\', \'APPROVED\', \'COMPLETED\')
                     LIMIT 1'
                );
                $chkRef->execute([':ppid' => (int) $pay['id']]);
                if ($chkRef->fetchColumn() === false) {
                    $this->pdo->prepare(
                        'INSERT INTO `refunds` (`player_payment_id`, `amount`, `reason`, `status`, `requested_at`, `created_at`, `updated_at`)
                         VALUES (:ppid, :amt, :r, \'APPROVED\', NOW(), NOW(), NOW())'
                    )->execute([
                        ':ppid' => (int) $pay['id'],
                        ':amt' => (int) $pay['paid_amount'],
                        ':r' => 'Hoàn cọc 100% do Chủ phòng mời rời phòng ở giai đoạn FORMING',
                    ]);
                }
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        try {
            $notiRepo = new \App\Features\Notification\Repositories\NotificationRepository($this->pdo);
            $notiRepo->createNotification(
                'Thông báo từ phòng chơi',
                "Bạn đã được Chủ phòng mời rời khỏi phòng '{$room['title']}'. Lý do: {$reason}.",
                'ROOM',
                $actorId,
                [$targetUserId]
            );
        } catch (\Throwable) {
        }

        return Response::success([
            'room_id' => $roomId,
            'kicked_user_id' => $targetUserId,
            'participant_id' => $partId,
        ], 'Đã mời thành viên rời phòng thành công');
    }

    /**
     * Resolve current user ID from session or header.
     */
    protected function resolveCurrentUserId(Request $request): ?int
    {
        $auth = AuthMiddleware::authenticate($request);
        if ($auth instanceof Response) {
            return null;
        }

        return (int) $auth['id'];
    }

    /**
     * Tự động kiểm tra và chuyển trạng thái quá hạn ngay khi có người xem phòng (Lazy Expiration Check)
     * Đảm bảo hệ thống vận hành chuẩn xác ngay cả khi chưa có Cron job ngoài chạy.
     */
    protected function performLazyExpirationCheck(int $roomId): void
    {
        try {
            // 1. Kiểm tra quá hạn biểu quyết đề xuất lịch (12h)
            $expProp = $this->pdo->prepare(
                'UPDATE `schedule_proposals`
                 SET `status` = \'EXPIRED\'
                 WHERE `game_room_id` = :room_id
                   AND `status` = \'PROPOSED\'
                   AND `expires_at` IS NOT NULL
                   AND `expires_at` <= NOW()'
            );
            $expProp->bindValue(':room_id', $roomId, PDO::PARAM_INT);
            $expProp->execute();

            // 2. Kiểm tra quá hạn nộp cọc (2h) của từng người chơi
            $expParts = $this->pdo->prepare(
                'SELECT `id`, `user_id` FROM `room_participants`
                 WHERE `game_room_id` = :room_id
                   AND `status` = \'PENDING_DEPOSIT\'
                   AND `deposit_deadline_at` IS NOT NULL
                   AND `deposit_deadline_at` <= NOW()'
            );
            $expParts->bindValue(':room_id', $roomId, PDO::PARAM_INT);
            $expParts->execute();
            $expiredList = $expParts->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($expiredList)) {
                foreach ($expiredList as $ep) {
                    $upEp = $this->pdo->prepare(
                        'UPDATE `room_participants`
                         SET `status` = \'CANCELLED_UNPAID\',
                             `cancelled_at` = NOW(),
                             `cancel_reason` = \'Quá hạn nộp tiền cọc (Lazy Expiration)\',
                             `updated_at` = NOW()
                         WHERE `id` = :id AND `status` = \'PENDING_DEPOSIT\''
                    );
                    $upEp->bindValue(':id', (int) $ep['id'], PDO::PARAM_INT);
                    $upEp->execute();
                }

                // Mở cửa sổ tìm người thay thế
                $upRoom = $this->pdo->prepare(
                    'UPDATE `game_rooms`
                     SET `replacement_status` = \'OPEN\',
                         `replacement_started_at` = NOW(),
                         `replacement_deadline_at` = DATE_ADD(NOW(), INTERVAL 24 HOUR),
                         `replacement_reason` = \'Có thành viên quá hạn đóng tiền cọc cần tìm người thay thế\',
                         `updated_at` = NOW()
                     WHERE `id` = :room_id AND `replacement_status` != \'OPEN\''
                );
                $upRoom->bindValue(':room_id', $roomId, PDO::PARAM_INT);
                $upRoom->execute();
            }

            // 3. Kiểm tra quá hạn gom đội nếu phòng FORMING
            $roomStmt = $this->pdo->prepare('SELECT `status`, `min_players`, `total_players`, `formation_deadline_at` FROM `game_rooms` WHERE `id` = :id');
            $roomStmt->bindValue(':id', $roomId, PDO::PARAM_INT);
            $roomStmt->execute();
            $rData = $roomStmt->fetch(PDO::FETCH_ASSOC);

            if ($rData && $rData['status'] === 'FORMING' && !empty($rData['formation_deadline_at'])) {
                if (strtotime((string) $rData['formation_deadline_at']) <= time()) {
                    $cntStmt = $this->pdo->prepare(
                        'SELECT COUNT(*) FROM `room_participants`
                         WHERE `game_room_id` = :id AND `status` IN (\'PENDING_DEPOSIT\', \'DEPOSITED\', \'LEAVE_REQUESTED\')'
                    );
                    $cntStmt->bindValue(':id', $roomId, PDO::PARAM_INT);
                    $cntStmt->execute();
                    $activeCount = (int) $cntStmt->fetchColumn();
                    $occupiedSeats = $activeCount > 0 ? (max(1, (int) ($rData['total_players'] ?? 1)) + max(0, $activeCount - 1)) : 0;

                    if ($occupiedSeats < (int) $rData['min_players']) {
                        $upExp = $this->pdo->prepare(
                            'UPDATE `game_rooms`
                             SET `status` = \'CANCELLED\', `cancelled_at` = NOW(), `cancel_reason` = \'Quá hạn gom đủ người chơi\', `updated_at` = NOW()
                             WHERE `id` = :id AND `status` = \'FORMING\''
                        );
                        $upExp->bindValue(':id', $roomId, PDO::PARAM_INT);
                        $upExp->execute();
                    }
                }
            }
        } catch (\Throwable $e) {
            // Lazy check never breaks primary read flow
        }
    }
}
