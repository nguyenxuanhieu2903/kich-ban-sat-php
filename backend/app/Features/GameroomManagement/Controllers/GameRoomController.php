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
        $gameId = $request->query('game_id') !== null ? (int) $request->query('game_id') : null;
        $status = (string) $request->query('status', 'FORMING');
        $limit = max(1, min(100, (int) $request->query('limit', 20)));
        $offset = max(0, (int) $request->query('offset', 0));

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
                LEFT JOIN `venues` v ON v.`id` = gr.`target_venue_id`
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

        $bookingType = $request->query('booking_type') !== null ? strtoupper(trim((string) $request->query('booking_type'))) : null;
        if ($bookingType !== null && in_array($bookingType, ['PUBLIC', 'PRIVATE'], true)) {
            $sql .= ' AND gr.`booking_type` = :booking_type';
            $bindings[':booking_type'] = [$bookingType, PDO::PARAM_STR];
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

        return Response::success([
            'rooms' => $rooms,
            'count' => count($rooms),
        ], 'Danh sách phòng chơi');
    }

    /**
     * GET /api/v1/rooms/{id}
     * Chi tiết phòng chơi và danh sách người tham gia
     */
    public function show(Request $request, string $id): Response
    {
        $roomId = (int) $id;
        $this->performLazyExpirationCheck($roomId);
        $room = $this->roomRepo->findById($roomId);

        if ($room === null) {
            return Response::fail('Không tìm thấy phòng chơi yêu cầu', 'ROOM_NOT_FOUND', 404);
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

        return Response::success([
            'room' => $room,
        ], 'Chi tiết phòng chơi');
    }

    /**
     * POST /api/v1/rooms
     * Tạo phòng ghép người chơi mới
     */
    public function create(Request $request): Response
    {
        $userId = $this->resolveCurrentUserId($request);
        if ($userId === null) {
            return Response::fail('Bạn cần đăng nhập để tạo phòng chơi', 'UNAUTHORIZED', 401);
        }

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
        $totalPlayers = isset($body['total_players']) && (int) $body['total_players'] > 0 ? (int) $body['total_players'] : null;
        $guestSlots = isset($body['guest_slots']) ? max(0, (int) $body['guest_slots']) : 0;
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
        $userId = $this->resolveCurrentUserId($request);
        if ($userId === null) {
            return Response::fail('Bạn cần đăng nhập để tham gia phòng', 'UNAUTHORIZED', 401);
        }

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

        // TRƯỜNG HỢP 1: Vào thay thế (Replacement Slot Takeover)
        if ($isReplacementOpen) {
            $leavePartStmt = $this->pdo->prepare(
                'SELECT `id`, `user_id` FROM `room_participants`
                 WHERE `game_room_id` = :room_id AND `status` = \'LEAVE_REQUESTED\'
                 ORDER BY `id` ASC LIMIT 1'
            );
            $leavePartStmt->bindValue(':room_id', $roomId, PDO::PARAM_INT);
            $leavePartStmt->execute();
            $leavingPart = $leavePartStmt->fetch(PDO::FETCH_ASSOC);

            if ($leavingPart === false) {
                return Response::fail('Hiện không còn slot tìm người thay thế trong phòng này', 'NO_REPLACEMENT_SLOT', 400);
            }

            $leavingPartId = (int) $leavingPart['id'];
            $leavingUserId = (int) $leavingPart['user_id'];

            $this->pdo->beginTransaction();
            try {
                // 1. Cho người cũ rời phòng: cập nhật status thành 'LEFT'
                $upOld = $this->pdo->prepare(
                    'UPDATE `room_participants`
                     SET `status` = \'LEFT\', `left_at` = NOW(), `cancel_reason` = \'Đã có người chơi khác nhận thay thế slot\'
                     WHERE `id` = :id'
                );
                $upOld->bindValue(':id', $leavingPartId, PDO::PARAM_INT);
                $upOld->execute();

                // 2. Thêm người mới vào slot thay thế
                $newPartId = $this->roomRepo->addParticipant($roomId, $userId);

                // 3. Kiểm tra xem còn ai LEAVE_REQUESTED không
                $checkLeftStmt = $this->pdo->prepare(
                    'SELECT COUNT(*) FROM `room_participants`
                     WHERE `game_room_id` = :room_id AND `status` = \'LEAVE_REQUESTED\''
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

        // TRƯỜNG HỢP 2: Vào phòng FORMING bình thường
        if (count($activeParticipants) >= (int) $room['max_players']) {
            return Response::fail('Phòng chơi đã đủ số lượng thành viên tối đa (' . $room['max_players'] . ' người)', 'ROOM_FULL', 400);
        }

        $this->pdo->beginTransaction();
        try {
            $lockStmt = $this->pdo->prepare('SELECT `id`, `max_players` FROM `game_rooms` WHERE `id` = :id FOR UPDATE');
            $lockStmt->bindValue(':id', $roomId, PDO::PARAM_INT);
            $lockStmt->execute();
            $maxPlayers = (int) ($lockStmt->fetchColumn(1) ?: $room['max_players']);

            $cntStmt = $this->pdo->prepare(
                'SELECT COUNT(*) FROM `room_participants`
                 WHERE `game_room_id` = :id AND `status` IN (\'PENDING_DEPOSIT\', \'DEPOSITED\', \'LEAVE_REQUESTED\')'
            );
            $cntStmt->bindValue(':id', $roomId, PDO::PARAM_INT);
            $cntStmt->execute();
            $currCount = (int) $cntStmt->fetchColumn();

            if ($currCount >= $maxPlayers) {
                $this->pdo->rollBack();
                return Response::fail('Phòng chơi đã đủ số lượng thành viên tối đa (' . $maxPlayers . ' người)', 'ROOM_FULL', 400);
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
            'current_players' => count($activeParticipants) + 1,
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
                    $upHost = $this->pdo->prepare('UPDATE `game_rooms` SET `creator_id` = :next_uid WHERE `id` = :room_id');
                    $upHost->bindValue(':next_uid', (int) $nextHostId, PDO::PARAM_INT);
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
                 SET `status` = \'EXPIRED\', `updated_at` = NOW()
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

            // 3. Kiểm tra quá hạn gom đội (48h) nếu phòng FORMING
            $roomStmt = $this->pdo->prepare('SELECT `status`, `min_players`, `formation_deadline_at` FROM `game_rooms` WHERE `id` = :id');
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

                    if ($activeCount < (int) $rData['min_players']) {
                        $upExp = $this->pdo->prepare(
                            'UPDATE `game_rooms`
                             SET `status` = \'EXPIRED\', `updated_at` = NOW()
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
