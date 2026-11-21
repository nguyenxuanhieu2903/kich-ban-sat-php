<?php

declare(strict_types=1);

namespace App\Features\GameRoom;

use App\Core\Exceptions\BadRequestException;
use App\Core\Exceptions\HttpException;
use App\Core\Exceptions\NotFoundException;
use App\Features\Game\GameRepository;
use App\Infrastructure\Database\DatabaseConnection;
use DateInterval;
use DateTimeImmutable;
use PDO;

class GameRoomService
{
    private const MAX_ACTIVE_ROOMS = 3;
    private const FORMATION_HOURS = 4;

    private PDO $pdo;
    private GameRepository $gameRepo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DatabaseConnection::getInstance();
        $this->gameRepo = new GameRepository($this->pdo);
    }

    /** @return array<int, array<string, mixed>> */
    public function listRooms(?string $status = null): array
    {
        $sql = 'SELECT * FROM game_rooms';
        $params = [];

        if ($status !== null && trim($status) !== '') {
            $sql .= ' WHERE status = :status';
            $params[':status'] = strtoupper(trim($status));
        }

        $sql .= ' ORDER BY scheduled_start_at ASC, id DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rooms = $stmt->fetchAll();

        $result = [];
        foreach ($rooms as $room) {
            $roomId = (int) $room['id'];
            $result[] = $this->formatRoomSummary($room, $this->countActivePlayers($roomId));
        }

        return $result;
    }

    /** @return array<string, mixed> */
    public function getRoomDetail(int $roomId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM game_rooms WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $roomId]);
        $room = $stmt->fetch();

        if ($room === false) {
            throw new NotFoundException('Không tìm thấy phòng chơi');
        }

        $partStmt = $this->pdo->prepare(
            "SELECT rp.id, rp.user_id, rp.status, rp.deposit_deadline_at, rp.joined_at,
                    u.full_name, u.email
             FROM room_participants rp
             INNER JOIN users u ON u.id = rp.user_id
             WHERE rp.room_id = :room_id
             ORDER BY rp.id ASC"
        );
        $partStmt->execute([':room_id' => $roomId]);
        $participants = $partStmt->fetchAll();

        $activeCount = 0;
        foreach ($participants as $p) {
            if (in_array((string) $p['status'], ['JOINED', 'DEPOSITED', 'PAID_FULL'], true)) {
                $activeCount++;
            }
        }

        $detail = $this->formatRoomSummary($room, $activeCount);
        $detail['participants'] = array_map(static function (array $p): array {
            return [
                'id' => (int) $p['id'],
                'user_id' => (int) $p['user_id'],
                'full_name' => (string) $p['full_name'],
                'email' => (string) $p['email'],
                'status' => (string) $p['status'],
                'deposit_deadline_at' => $p['deposit_deadline_at'],
                'joined_at' => (string) $p['joined_at'],
            ];
        }, $participants);

        return $detail;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function createRoom(int $hostUserId, array $input): array
    {
        $this->requireActiveUser($hostUserId, ['PLAYER', 'ADMIN']);

        $gameId = (int) ($input['game_id'] ?? 0);
        $scheduledRaw = trim((string) ($input['scheduled_start_at'] ?? ''));

        if ($gameId <= 0 || $scheduledRaw === '') {
            throw new BadRequestException('Thiếu game_id hoặc scheduled_start_at');
        }

        $scheduledAt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $scheduledRaw);
        if ($scheduledAt === false) {
            $scheduledAt = DateTimeImmutable::createFromFormat(DateTimeImmutable::ATOM, $scheduledRaw);
        }
        if ($scheduledAt === false) {
            throw new BadRequestException('Định dạng scheduled_start_at không hợp lệ (dùng YYYY-MM-DD HH:MM:SS)');
        }

        $minute = (int) $scheduledAt->format('i');
        $second = (int) $scheduledAt->format('s');
        if (!in_array($minute, [0, 30], true) || $second !== 0) {
            throw new BadRequestException('Giờ bắt đầu phải là mốc chẵn :00 hoặc :30');
        }

        $now = new DateTimeImmutable();
        if ($scheduledAt <= $now) {
            throw new BadRequestException('Giờ bắt đầu phải ở thời điểm tương lai');
        }

        $game = $this->gameRepo->findById($gameId);
        if ($game !== null && isset($game['is_active']) && (int) $game['is_active'] !== 1) {
            $game = null;
        }
        if ($game === null) {
            throw new NotFoundException('Kịch bản không tồn tại hoặc đã ngừng kinh doanh');
        }

        $activeRoomCount = $this->countHostActiveRooms($hostUserId);
        if ($activeRoomCount >= self::MAX_ACTIVE_ROOMS) {
            throw new HttpException('Bạn đã đạt giới hạn tối đa ' . self::MAX_ACTIVE_ROOMS . ' phòng đang hoạt động', 409);
        }

        $isEvening = (int) $scheduledAt->format('H') >= 18;
        $ticketPrice = $isEvening ? (int) $game['price_evening_vnd'] : (int) $game['price_day_vnd'];
        $depositVnd = max(50000, (int) round($ticketPrice * 0.3));

        $snapshot = [
            'game_id' => (int) $game['id'],
            'title' => (string) $game['title'],
            'min_players' => (int) $game['min_players'],
            'max_players' => (int) $game['max_players'],
            'duration_minutes' => (int) $game['duration_minutes'],
            'is_18_plus' => (bool) $game['is_18_plus'],
            'slot_type' => $isEvening ? 'EVENING' : 'DAY',
            'ticket_price_vnd' => $ticketPrice,
            'deposit_vnd' => $depositVnd,
        ];

        $formationDeadline = $now->add(new DateInterval('PT' . self::FORMATION_HOURS . 'H'));
        if ($formationDeadline > $scheduledAt) {
            $formationDeadline = $scheduledAt;
        }

        $roomCode = 'KBS-' . strtoupper(bin2hex(random_bytes(3)));

        $this->pdo->beginTransaction();
        try {
            $insRoom = $this->pdo->prepare(
                "INSERT INTO game_rooms
                    (room_code, game_id, host_user_id, scheduled_start_at, status, formation_deadline_at, reopen_count, snapshot_game_rule)
                 VALUES
                    (:room_code, :game_id, :host_user_id, :scheduled_start_at, 'FORMING', :formation_deadline_at, 0, :snapshot)"
            );
            $insRoom->execute([
                ':room_code' => $roomCode,
                ':game_id' => $gameId,
                ':host_user_id' => $hostUserId,
                ':scheduled_start_at' => $scheduledAt->format('Y-m-d H:i:s'),
                ':formation_deadline_at' => $formationDeadline->format('Y-m-d H:i:s'),
                ':snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
            ]);

            $roomId = (int) $this->pdo->lastInsertId();

            $insPart = $this->pdo->prepare(
                "INSERT INTO room_participants (room_id, user_id, status, joined_at)
                 VALUES (:room_id, :user_id, 'JOINED', :joined_at)"
            );
            $insPart->execute([
                ':room_id' => $roomId,
                ':user_id' => $hostUserId,
                ':joined_at' => $now->format('Y-m-d H:i:s'),
            ]);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return $this->getRoomDetail($roomId);
    }

    /** @return array<string, mixed> */
    public function joinRoom(int $roomId, int $userId): array
    {
        $this->requireActiveUser($userId, ['PLAYER', 'ADMIN']);

        $lockSql = $this->getRowLockSql();
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM game_rooms WHERE id = :id{$lockSql}");
            $stmt->execute([':id' => $roomId]);
            $room = $stmt->fetch();

            if ($room === false) {
                throw new NotFoundException('Không tìm thấy phòng chơi');
            }

            $status = (string) $room['status'];
            if (!in_array($status, ['FORMING', 'REPLENISHING'], true)) {
                throw new HttpException("Phòng đang ở trạng thái {$status}, không thể tham gia", 409);
            }

            $checkStmt = $this->pdo->prepare(
                "SELECT id, status FROM room_participants
                 WHERE room_id = :room_id AND user_id = :user_id
                 LIMIT 1{$lockSql}"
            );
            $checkStmt->execute([':room_id' => $roomId, ':user_id' => $userId]);
            $existing = $checkStmt->fetch();

            if (is_array($existing) && in_array((string) $existing['status'], ['JOINED', 'DEPOSITED', 'PAID_FULL'], true)) {
                throw new HttpException('Bạn đã ở trong phòng này rồi', 409);
            }

            $rule = json_decode((string) $room['snapshot_game_rule'], true);
            $minPlayers = is_array($rule) ? (int) ($rule['min_players'] ?? 1) : 1;
            $maxPlayers = is_array($rule) ? (int) ($rule['max_players'] ?? 8) : 8;

            $activeCount = $this->countActivePlayers($roomId);
            if ($activeCount >= $maxPlayers) {
                throw new HttpException('Phòng đã đủ số người chơi tối đa', 409);
            }

            $nowStr = (new DateTimeImmutable())->format('Y-m-d H:i:s');
            if (is_array($existing)) {
                $updPart = $this->pdo->prepare(
                    "UPDATE room_participants
                     SET status = 'JOINED', joined_at = :joined_at, left_at = NULL
                     WHERE id = :id"
                );
                $updPart->execute([
                    ':id' => $existing['id'],
                    ':joined_at' => $nowStr,
                ]);
            } else {
                $insPart = $this->pdo->prepare(
                    "INSERT INTO room_participants (room_id, user_id, status, joined_at)
                     VALUES (:room_id, :user_id, 'JOINED', :joined_at)"
                );
                $insPart->execute([
                    ':room_id' => $roomId,
                    ':user_id' => $userId,
                    ':joined_at' => $nowStr,
                ]);
            }

            $newCount = $this->countActivePlayers($roomId);
            if (in_array($status, ['FORMING', 'REPLENISHING'], true) && $newCount >= $minPlayers) {
                $updRoom = $this->pdo->prepare("UPDATE game_rooms SET status = 'WAITING_FOR_DM' WHERE id = :id");
                $updRoom->execute([':id' => $roomId]);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return $this->getRoomDetail($roomId);
    }

    /** @return array<string, mixed> */
    public function leaveRoom(int $roomId, int $userId): array
    {
        $this->requireActiveUser($userId, ['PLAYER', 'ADMIN']);

        $lockSql = $this->getRowLockSql();
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM game_rooms WHERE id = :id{$lockSql}");
            $stmt->execute([':id' => $roomId]);
            $room = $stmt->fetch();

            if ($room === false) {
                throw new NotFoundException('Không tìm thấy phòng chơi');
            }

            $status = (string) $room['status'];
            if ($status === 'CONFIRMED') {
                throw new HttpException('Phòng đã chốt lịch (CONFIRMED), không thể tự rời phòng', 409);
            }
            if (in_array($status, ['CANCELLED', 'COMPLETED', 'EXPIRED'], true)) {
                throw new HttpException("Phòng đã đóng ({$status}), không thể rời phòng", 409);
            }

            $partStmt = $this->pdo->prepare(
                "SELECT id, status FROM room_participants
                 WHERE room_id = :room_id AND user_id = :user_id
                 LIMIT 1{$lockSql}"
            );
            $partStmt->execute([':room_id' => $roomId, ':user_id' => $userId]);
            $participant = $partStmt->fetch();

            if ($participant === false || !in_array((string) $participant['status'], ['JOINED', 'DEPOSITED', 'PAID_FULL'], true)) {
                throw new NotFoundException('Bạn hiện không có mặt trong phòng này');
            }

            $nowStr = (new DateTimeImmutable())->format('Y-m-d H:i:s');
            $updPart = $this->pdo->prepare(
                "UPDATE room_participants
                 SET status = 'LEFT', left_at = :left_at
                 WHERE id = :id"
            );
            $updPart->execute([
                ':id' => $participant['id'],
                ':left_at' => $nowStr,
            ]);

            $rule = json_decode((string) $room['snapshot_game_rule'], true);
            $minPlayers = is_array($rule) ? (int) ($rule['min_players'] ?? 1) : 1;
            $newCount = $this->countActivePlayers($roomId);

            if ($status === 'WAITING_FOR_DM' && $newCount < $minPlayers) {
                $updRoom = $this->pdo->prepare("UPDATE game_rooms SET status = 'FORMING' WHERE id = :id");
                $updRoom->execute([':id' => $roomId]);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return $this->getRoomDetail($roomId);
    }

    /** @return array<string, mixed> */
    public function assignDmAndPhysicalRoom(int $roomId, int $dmUserId, int $physicalRoomId, int $assignedBy): array
    {
        $this->requireActiveUser($assignedBy, ['ADMIN']);

        $lockSql = $this->getRowLockSql();
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM game_rooms WHERE id = :id{$lockSql}");
            $stmt->execute([':id' => $roomId]);
            $room = $stmt->fetch();

            if ($room === false) {
                throw new NotFoundException('Không tìm thấy phòng chơi');
            }

            $status = (string) $room['status'];
            if (!in_array($status, ['FORMING', 'WAITING_FOR_DM'], true)) {
                throw new HttpException("Không thể phân công DM khi phòng đang ở trạng thái {$status}", 409);
            }

            $rule = json_decode((string) $room['snapshot_game_rule'], true);
            $minPlayers = is_array($rule) ? (int) ($rule['min_players'] ?? 1) : 1;
            $durationMinutes = is_array($rule) ? (int) ($rule['duration_minutes'] ?? 180) : 180;

            $activePlayers = $this->countActivePlayers($roomId);
            if ($activePlayers < $minPlayers) {
                throw new HttpException(
                    "Phòng chưa đủ số người chơi tối thiểu ({$activePlayers}/{$minPlayers}) để phân công DM",
                    409
                );
            }

            $dmStmt = $this->pdo->prepare('SELECT id, role, is_active FROM users WHERE id = :id LIMIT 1');
            $dmStmt->execute([':id' => $dmUserId]);
            $dm = $dmStmt->fetch();
            if ($dm === false || strtoupper((string) $dm['role']) !== 'DM' || (int) $dm['is_active'] !== 1) {
                throw new BadRequestException('Tài khoản DM không hợp lệ hoặc chưa kích hoạt');
            }

            $prStmt = $this->pdo->prepare('SELECT id, status, capacity FROM physical_rooms WHERE id = :id LIMIT 1');
            $prStmt->execute([':id' => $physicalRoomId]);
            $pr = $prStmt->fetch();
            if ($pr === false || (string) $pr['status'] !== 'AVAILABLE') {
                throw new BadRequestException('Phòng vật lý không tồn tại hoặc không khả dụng');
            }

            $requiredCapacity = $activePlayers + 1;
            $roomCapacity = (int) $pr['capacity'];
            if ($roomCapacity < $requiredCapacity) {
                throw new HttpException(
                    "Phòng vật lý không đủ sức chứa (cần {$requiredCapacity} chỗ tính cả DM, phòng chỉ có {$roomCapacity} chỗ)",
                    409
                );
            }

            $startAt = new DateTimeImmutable((string) $room['scheduled_start_at']);
            $endAt = $startAt->add(new DateInterval('PT' . $durationMinutes . 'M'));
            $startStr = $startAt->format('Y-m-d H:i:s');
            $endStr = $endAt->format('Y-m-d H:i:s');

            $conflictStmt = $this->pdo->prepare(
                "SELECT id FROM game_sessions
                 WHERE room_id != :room_id
                   AND status = 'SCHEDULED'
                   AND (physical_room_id = :pr_id OR dm_user_id = :dm_id)
                   AND start_at < :end_at
                   AND end_at > :start_at
                 LIMIT 1{$lockSql}"
            );
            $conflictStmt->execute([
                ':room_id' => $roomId,
                ':pr_id' => $physicalRoomId,
                ':dm_id' => $dmUserId,
                ':start_at' => $startStr,
                ':end_at' => $endStr,
            ]);
            if ($conflictStmt->fetch() !== false) {
                throw new HttpException('Phòng vật lý hoặc DM đã bị trùng khung giờ', 409);
            }

            $insAssign = $this->pdo->prepare(
                "INSERT INTO dm_assignments (room_id, dm_user_id, assigned_by, status)
                 VALUES (:room_id, :dm_user_id, :assigned_by, 'ACCEPTED')"
            );
            $insAssign->execute([
                ':room_id' => $roomId,
                ':dm_user_id' => $dmUserId,
                ':assigned_by' => $assignedBy,
            ]);

            $insSession = $this->pdo->prepare(
                "INSERT INTO game_sessions (room_id, dm_user_id, physical_room_id, start_at, end_at, status)
                 VALUES (:room_id, :dm_user_id, :pr_id, :start_at, :end_at, 'SCHEDULED')"
            );
            $insSession->execute([
                ':room_id' => $roomId,
                ':dm_user_id' => $dmUserId,
                ':pr_id' => $physicalRoomId,
                ':start_at' => $startStr,
                ':end_at' => $endStr,
            ]);

            $depositDeadline = (new DateTimeImmutable())->add(new DateInterval('PT2H'))->format('Y-m-d H:i:s');
            $updPart = $this->pdo->prepare(
                "UPDATE room_participants
                 SET deposit_deadline_at = :deadline
                 WHERE room_id = :room_id AND status IN ('JOINED', 'DEPOSITED', 'PAID_FULL')"
            );
            $updPart->execute([
                ':deadline' => $depositDeadline,
                ':room_id' => $roomId,
            ]);

            $updRoom = $this->pdo->prepare("UPDATE game_rooms SET status = 'CONFIRMED' WHERE id = :id");
            $updRoom->execute([':id' => $roomId]);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return $this->getRoomDetail($roomId);
    }

    /** @return array<string, mixed> */
    public function deposit(int $roomId, int $userId, string $method = 'VNPAY'): array
    {
        $this->requireActiveUser($userId, ['PLAYER', 'ADMIN']);

        $now = new DateTimeImmutable();
        $nowStr = $now->format('Y-m-d H:i:s');
        $lockSql = $this->getRowLockSql();

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM game_rooms WHERE id = :id{$lockSql}");
            $stmt->execute([':id' => $roomId]);
            $room = $stmt->fetch();

            if ($room === false) {
                throw new NotFoundException('Không tìm thấy phòng chơi');
            }

            $status = (string) $room['status'];
            if (in_array($status, ['CANCELLED', 'COMPLETED', 'EXPIRED'], true)) {
                throw new HttpException("Phòng đang ở trạng thái {$status}, không thể đặt cọc", 409);
            }

            $partStmt = $this->pdo->prepare(
                "SELECT id, status, deposit_deadline_at FROM room_participants
                 WHERE room_id = :room_id AND user_id = :user_id
                 LIMIT 1{$lockSql}"
            );
            $partStmt->execute([':room_id' => $roomId, ':user_id' => $userId]);
            $participant = $partStmt->fetch();

            if ($participant === false) {
                throw new NotFoundException('Người chơi không thuộc phòng này');
            }

            if ((string) $participant['status'] === 'DEPOSITED') {
                throw new HttpException('Người chơi đã đặt cọc cho phòng này rồi', 409);
            }

            if (!empty($participant['deposit_deadline_at'])) {
                $deadline = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string) $participant['deposit_deadline_at']);
                if ($deadline !== false && $now > $deadline) {
                    throw new HttpException('Đã quá hạn đặt cọc cho phòng này', 409);
                }
            }

            $rule = json_decode((string) $room['snapshot_game_rule'], true);
            $amount = is_array($rule) && isset($rule['deposit_vnd'])
                ? (int) $rule['deposit_vnd']
                : 50000;

            if ($amount <= 0) {
                throw new BadRequestException('Số tiền đặt cọc không hợp lệ');
            }

            $txKey = 'DEP-' . $roomId . '-' . $userId . '-' . bin2hex(random_bytes(4));
            $insTx = $this->pdo->prepare(
                "INSERT INTO payment_transactions (room_id, participant_id, user_id, idempotency_key, payment_type, amount_vnd, status, note, paid_at)
                 VALUES (:room_id, :part_id, :user_id, :idem_key, 'DEPOSIT', :amount, 'SUCCESS', :note, :paid_at)"
            );
            $insTx->execute([
                ':room_id' => $roomId,
                ':part_id' => $participant['id'],
                ':user_id' => $userId,
                ':idem_key' => $txKey,
                ':amount' => $amount,
                ':note' => 'Method: ' . strtoupper(trim($method)),
                ':paid_at' => $nowStr,
            ]);

            $updPart = $this->pdo->prepare("UPDATE room_participants SET status = 'DEPOSITED' WHERE id = :id");
            $updPart->execute([':id' => $participant['id']]);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return $this->getRoomDetail($roomId);
    }

    /** @return array<string, mixed> */
    public function cancelRoom(int $roomId, int $actorUserId, string $actorRole, ?string $reason = null): array
    {
        $cleanReason = ($reason !== null && $reason !== '')
            ? mb_substr($reason, 0, 500)
            : ((strtoupper($actorRole) === 'ADMIN') ? 'Hủy bởi quản trị viên' : 'Chủ phòng hủy phòng');

        $lockSql = $this->getRowLockSql();
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM game_rooms WHERE id = :id{$lockSql}");
            $stmt->execute([':id' => $roomId]);
            $room = $stmt->fetch();

            if ($room === false) {
                throw new NotFoundException('Không tìm thấy phòng chơi');
            }

            $status = (string) $room['status'];
            if (in_array($status, ['CANCELLED', 'COMPLETED', 'EXPIRED'], true)) {
                throw new HttpException("Không thể hủy phòng đang ở trạng thái {$status}", 409);
            }

            $isAdmin = strtoupper($actorRole) === 'ADMIN';
            $isHost = (int) $room['host_user_id'] === $actorUserId;

            if (!$isAdmin) {
                if (!$isHost) {
                    throw new HttpException('Chỉ chủ phòng hoặc quản trị viên mới có quyền hủy phòng', 403);
                }
                if (!in_array($status, ['FORMING', 'WAITING_FOR_DM'], true)) {
                    throw new HttpException('Phòng đã chốt lịch, vui lòng liên hệ quản trị viên để hủy', 409);
                }
            }

            $updRoom = $this->pdo->prepare(
                "UPDATE game_rooms
                 SET status = 'CANCELLED', cancel_reason = :reason
                 WHERE id = :id"
            );
            $updRoom->execute([
                ':id' => $roomId,
                ':reason' => $cleanReason,
            ]);

            $updSession = $this->pdo->prepare(
                "UPDATE game_sessions
                 SET status = 'CANCELLED', cancel_reason = :reason
                 WHERE room_id = :room_id AND status = 'SCHEDULED'"
            );
            $updSession->execute([
                ':room_id' => $roomId,
                ':reason' => $cleanReason,
            ]);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return $this->getRoomDetail($roomId);
    }

    /** @return array<string, mixed> */
    public function completeRoom(int $roomId, int $actorUserId, string $actorRole): array
    {
        $lockSql = $this->getRowLockSql();
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM game_rooms WHERE id = :id{$lockSql}");
            $stmt->execute([':id' => $roomId]);
            $room = $stmt->fetch();

            if ($room === false) {
                throw new NotFoundException('Không tìm thấy phòng chơi');
            }

            if ((string) $room['status'] !== 'CONFIRMED') {
                throw new HttpException('Chỉ có thể hoàn thành phòng đang ở trạng thái CONFIRMED', 409);
            }

            $sessionStmt = $this->pdo->prepare("SELECT id, dm_user_id, status FROM game_sessions WHERE room_id = :room_id LIMIT 1{$lockSql}");
            $sessionStmt->execute([':room_id' => $roomId]);
            $session = $sessionStmt->fetch();

            $isAdmin = strtoupper($actorRole) === 'ADMIN';
            $isAssignedDm = is_array($session) && (int) ($session['dm_user_id'] ?? 0) === $actorUserId;

            if (!$isAdmin && !$isAssignedDm) {
                throw new HttpException('Chỉ quản trị viên hoặc DM được phân công mới có quyền hoàn thành trận', 403);
            }

            $updRoom = $this->pdo->prepare("UPDATE game_rooms SET status = 'COMPLETED' WHERE id = :id");
            $updRoom->execute([':id' => $roomId]);

            if (is_array($session)) {
                $updSession = $this->pdo->prepare("UPDATE game_sessions SET status = 'COMPLETED' WHERE id = :id");
                $updSession->execute([':id' => $session['id']]);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return $this->getRoomDetail($roomId);
    }

    /**
     * @param string[]|null $allowedRoles
     * @return array<string, mixed>
     */
    private function requireActiveUser(int $userId, ?array $allowedRoles = null): array
    {
        $stmt = $this->pdo->prepare('SELECT id, role, is_active FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch();

        if ($user === false || (int) $user['is_active'] !== 1) {
            throw new HttpException('Tài khoản không tồn tại hoặc đã bị khóa', 403);
        }

        if ($allowedRoles !== null) {
            $role = strtoupper((string) $user['role']);
            if (!in_array($role, $allowedRoles, true)) {
                throw new HttpException('Tài khoản không có quyền thực hiện thao tác này', 403);
            }
        }

        return $user;
    }

    private function getRowLockSql(): string
    {
        return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
    }

    private function countActivePlayers(int $roomId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM room_participants
             WHERE room_id = :room_id AND status IN ('JOINED', 'DEPOSITED', 'PAID_FULL')"
        );
        $stmt->execute([':room_id' => $roomId]);

        return (int) $stmt->fetchColumn();
    }

    private function countHostActiveRooms(int $hostUserId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM game_rooms
             WHERE host_user_id = :host_id
               AND status NOT IN ('CANCELLED', 'COMPLETED', 'EXPIRED')"
        );
        $stmt->execute([':host_id' => $hostUserId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @param array<string, mixed> $room
     * @return array<string, mixed>
     */
    private function formatRoomSummary(array $room, int $activePlayers): array
    {
        $decodedRule = json_decode((string) $room['snapshot_game_rule'], true);

        return [
            'id' => (int) $room['id'],
            'room_code' => (string) $room['room_code'],
            'game_id' => (int) $room['game_id'],
            'host_user_id' => (int) $room['host_user_id'],
            'scheduled_start_at' => (string) $room['scheduled_start_at'],
            'status' => (string) $room['status'],
            'formation_deadline_at' => (string) $room['formation_deadline_at'],
            'reopen_count' => (int) $room['reopen_count'],
            'cancel_reason' => $room['cancel_reason'] !== null ? (string) $room['cancel_reason'] : null,
            'active_players' => $activePlayers,
            'snapshot_game_rule' => is_array($decodedRule) ? $decodedRule : [],
            'created_at' => (string) $room['created_at'],
        ];
    }
}