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
        $sql = "SELECT r.*, g.title AS game_title, g.min_players, g.max_players,
                       (SELECT COUNT(*) FROM room_participants p
                        WHERE p.room_id = r.id AND p.status IN ('PENDING_DEPOSIT', 'DEPOSITED', 'LEAVE_REQUESTED')) AS active_players
                FROM game_rooms r
                JOIN games g ON g.id = r.game_id";
        $params = [];

        if ($status !== null && $status !== '') {
            $sql .= ' WHERE r.status = :status';
            $params[':status'] = $status;
        }

        $sql .= ' ORDER BY r.created_at DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $row['snapshot_game_rule'] = json_decode((string) $row['snapshot_game_rule'], true);
        }

        return $rows;
    }

    /** @return array<string, mixed> */
    public function getRoomDetail(int $roomId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT r.*, g.title AS game_title, g.category, g.is_18_plus
             FROM game_rooms r
             JOIN games g ON g.id = r.game_id
             WHERE r.id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $roomId]);
        $room = $stmt->fetch();

        if ($room === false) {
            throw new NotFoundException('Không tìm thấy phòng chơi #' . $roomId);
        }

        $partStmt = $this->pdo->prepare(
            'SELECT p.id, p.user_id, u.full_name, p.status, p.deposit_deadline_at, p.joined_at
             FROM room_participants p
             JOIN users u ON u.id = p.user_id
             WHERE p.room_id = :room_id
             ORDER BY p.id ASC'
        );
        $partStmt->execute([':room_id' => $roomId]);

        $room['snapshot_game_rule'] = json_decode((string) $room['snapshot_game_rule'], true);
        $room['participants'] = $partStmt->fetchAll();

        return $room;
    }

    /** @param array<string, mixed> $payload */
    public function createRoom(int $hostUserId, array $payload): array
    {
        $gameId = (int) ($payload['game_id'] ?? 0);
        $scheduledStartRaw = trim((string) ($payload['scheduled_start_at'] ?? ''));

        $game = $this->gameRepo->findById($gameId);
        if ($game === null || (int) $game['is_active'] !== 1) {
            throw new BadRequestException('Kịch bản không tồn tại hoặc đang ngừng kinh doanh', ['game_id' => ['Invalid game']]);
        }

        $startAt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $scheduledStartRaw)
            ?: DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $scheduledStartRaw);

        if (!$startAt) {
            throw new BadRequestException('Thời gian bắt đầu không đúng định dạng (YYYY-MM-DD HH:MM:SS)', ['scheduled_start_at' => ['Invalid format']]);
        }

        $minute = (int) $startAt->format('i');
        if (!in_array($minute, [0, 30], true)) {
            throw new BadRequestException('Giờ bắt đầu chỉ được chọn mốc chẵn :00 hoặc :30', ['scheduled_start_at' => ['Minute must be 00 or 30']]);
        }

        $now = new DateTimeImmutable();
        if ($startAt <= $now) {
            throw new BadRequestException('Giờ bắt đầu phải nằm ở tương lai', ['scheduled_start_at' => ['Must be in future']]);
        }

        $roomId = DatabaseConnection::transaction(function (PDO $pdo) use ($hostUserId, $game, $startAt, $now): int {
            $countStmt = $pdo->prepare(
                "SELECT COUNT(*) FROM game_rooms
                 WHERE host_user_id = :uid AND status NOT IN ('COMPLETED', 'EXPIRED', 'CANCELLED')"
            );
            $countStmt->execute([':uid' => $hostUserId]);
            if ((int) $countStmt->fetchColumn() >= self::MAX_ACTIVE_ROOMS) {
                throw new HttpException('Mỗi tài khoản chỉ được tạo tối đa 3 phòng đang hoạt động', 409);
            }

            $formationDeadline = $now->add(new DateInterval('PT' . self::FORMATION_HOURS . 'H'));
            $roomCode = 'KBS-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

            $snapshot = [
                'min_players' => (int) $game['min_players'],
                'max_players' => (int) $game['max_players'],
                'duration_minutes' => (int) $game['duration_minutes'],
                'price_day_vnd' => (int) $game['price_day_vnd'],
                'price_evening_vnd' => (int) $game['price_evening_vnd'],
                'deposit_vnd' => (int) $game['deposit_vnd'],
            ];

            $insertRoom = $pdo->prepare(
                "INSERT INTO game_rooms (room_code, game_id, host_user_id, scheduled_start_at, status, formation_deadline_at, snapshot_game_rule)
                 VALUES (:code, :game_id, :host_id, :start_at, 'FORMING', :deadline, :snapshot)"
            );
            $insertRoom->execute([
                ':code' => $roomCode,
                ':game_id' => $game['id'],
                ':host_id' => $hostUserId,
                ':start_at' => $startAt->format('Y-m-d H:i:s'),
                ':deadline' => $formationDeadline->format('Y-m-d H:i:s'),
                ':snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
            ]);

            $newRoomId = (int) $pdo->lastInsertId();

            $insertPart = $pdo->prepare(
                "INSERT INTO room_participants (room_id, user_id, status) VALUES (:room_id, :user_id, 'PENDING_DEPOSIT')"
            );
            $insertPart->execute([':room_id' => $newRoomId, ':user_id' => $hostUserId]);

            return $newRoomId;
        }, $this->pdo);

        return $this->getRoomDetail($roomId);
    }

    public function joinRoom(int $roomId, int $userId): array
    {
        DatabaseConnection::transaction(function (PDO $pdo) use ($roomId, $userId): void {
            $lockStmt = $pdo->prepare('SELECT * FROM game_rooms WHERE id = :id FOR UPDATE');
            $lockStmt->execute([':id' => $roomId]);
            $room = $lockStmt->fetch();

            if ($room === false) {
                throw new NotFoundException('Không tìm thấy phòng chơi #' . $roomId);
            }

            if (!in_array($room['status'], ['FORMING', 'REPLENISHING'], true)) {
                throw new HttpException('Phòng hiện không mở nhận thêm người chơi (trạng thái: ' . $room['status'] . ')', 409);
            }

            $dupStmt = $pdo->prepare(
                "SELECT COUNT(*) FROM room_participants
                 WHERE room_id = :room_id AND user_id = :user_id
                   AND status IN ('PENDING_DEPOSIT', 'DEPOSITED', 'LEAVE_REQUESTED')"
            );
            $dupStmt->execute([':room_id' => $roomId, ':user_id' => $userId]);
            if ((int) $dupStmt->fetchColumn() > 0) {
                throw new HttpException('Bạn đã ở trong phòng này rồi', 409);
            }

            $rule = json_decode((string) $room['snapshot_game_rule'], true);
            $cntStmt = $pdo->prepare(
                "SELECT COUNT(*) FROM room_participants
                 WHERE room_id = :room_id AND status IN ('PENDING_DEPOSIT', 'DEPOSITED', 'LEAVE_REQUESTED')"
            );
            $cntStmt->execute([':room_id' => $roomId]);
            $activeCount = (int) $cntStmt->fetchColumn();

            if ($activeCount >= (int) $rule['max_players']) {
                throw new HttpException('Phòng đã đủ số lượng người chơi tối đa', 409);
            }

            $insertPart = $pdo->prepare(
                "INSERT INTO room_participants (room_id, user_id, status) VALUES (:room_id, :user_id, 'PENDING_DEPOSIT')"
            );
            $insertPart->execute([':room_id' => $roomId, ':user_id' => $userId]);

            if ($room['status'] === 'FORMING' && ($activeCount + 1) >= (int) $rule['min_players']) {
                $upd = $pdo->prepare("UPDATE game_rooms SET status = 'WAITING_FOR_DM' WHERE id = :id");
                $upd->execute([':id' => $roomId]);
            }
        }, $this->pdo);

        return $this->getRoomDetail($roomId);
    }

    public function leaveRoom(int $roomId, int $userId): array
    {
        DatabaseConnection::transaction(function (PDO $pdo) use ($roomId, $userId): void {
            $lockStmt = $pdo->prepare('SELECT * FROM game_rooms WHERE id = :id FOR UPDATE');
            $lockStmt->execute([':id' => $roomId]);
            $room = $lockStmt->fetch();

            if ($room === false) {
                throw new NotFoundException('Không tìm thấy phòng chơi #' . $roomId);
            }

            if (in_array($room['status'], ['COMPLETED', 'EXPIRED', 'CANCELLED'], true)) {
                throw new HttpException('Không thể rời phòng ở trạng thái ' . $room['status'], 409);
            }

            $updPart = $pdo->prepare(
                "UPDATE room_participants
                 SET status = 'LEFT'
                 WHERE room_id = :room_id AND user_id = :user_id
                   AND status IN ('PENDING_DEPOSIT', 'DEPOSITED', 'LEAVE_REQUESTED')"
            );
            $updPart->execute([':room_id' => $roomId, ':user_id' => $userId]);

            if ($updPart->rowCount() === 0) {
                throw new NotFoundException('Bạn không có trong danh sách người chơi đang hoạt động của phòng này');
            }

            $rule = json_decode((string) $room['snapshot_game_rule'], true);
            $cntStmt = $pdo->prepare(
                "SELECT COUNT(*) FROM room_participants
                 WHERE room_id = :room_id AND status IN ('PENDING_DEPOSIT', 'DEPOSITED', 'LEAVE_REQUESTED')"
            );
            $cntStmt->execute([':room_id' => $roomId]);
            $activeCount = (int) $cntStmt->fetchColumn();

            if ($room['status'] === 'WAITING_FOR_DM' && $activeCount < (int) $rule['min_players']) {
                $pdo->prepare("UPDATE game_rooms SET status = 'FORMING' WHERE id = :id")
                    ->execute([':id' => $roomId]);
            }
        }, $this->pdo);

        return $this->getRoomDetail($roomId);
    }

    public function assignDmAndPhysicalRoom(int $roomId, int $dmUserId, int $physicalRoomId, int $assignedBy = 1): array
    {
        DatabaseConnection::transaction(function (PDO $pdo) use ($roomId, $dmUserId, $physicalRoomId, $assignedBy): void {
            $lockStmt = $pdo->prepare('SELECT * FROM game_rooms WHERE id = :id FOR UPDATE');
            $lockStmt->execute([':id' => $roomId]);
            $room = $lockStmt->fetch();

            if ($room === false) {
                throw new NotFoundException('Không tìm thấy phòng chơi #' . $roomId);
            }

            $physStmt = $pdo->prepare("SELECT * FROM physical_rooms WHERE id = :id AND status = 'AVAILABLE' LIMIT 1");
            $physStmt->execute([':id' => $physicalRoomId]);
            if ($physStmt->fetch() === false) {
                throw new BadRequestException('Phòng vật lý không tồn tại hoặc không khả dụng');
            }

            $insDm = $pdo->prepare(
                "INSERT INTO dm_assignments (room_id, dm_user_id, assigned_by, status)
                 VALUES (:room_id, :dm_id, :assigned_by, 'ASSIGNED')"
            );
            $insDm->execute([
                ':room_id' => $roomId,
                ':dm_id' => $dmUserId,
                ':assigned_by' => $assignedBy,
            ]);

            $rule = json_decode((string) $room['snapshot_game_rule'], true);
            $duration = (int) ($rule['duration_minutes'] ?? 210);
            $startAt = new DateTimeImmutable((string) $room['scheduled_start_at']);
            $endAt = $startAt->add(new DateInterval('PT' . $duration . 'M'));

            $insSession = $pdo->prepare(
                "INSERT INTO game_sessions (room_id, dm_user_id, physical_room_id, start_at, end_at, status)
                 VALUES (:room_id, :dm_id, :phys_id, :start_at, :end_at, 'SCHEDULED')
                 ON DUPLICATE KEY UPDATE
                     dm_user_id = VALUES(dm_user_id),
                     physical_room_id = VALUES(physical_room_id),
                     start_at = VALUES(start_at),
                     end_at = VALUES(end_at)"
            );
            $insSession->execute([
                ':room_id' => $roomId,
                ':dm_id' => $dmUserId,
                ':phys_id' => $physicalRoomId,
                ':start_at' => $startAt->format('Y-m-d H:i:s'),
                ':end_at' => $endAt->format('Y-m-d H:i:s'),
            ]);

            $deadline = (new DateTimeImmutable())->add(new DateInterval('PT2H'))->format('Y-m-d H:i:s');

            $pdo->prepare("UPDATE game_rooms SET status = 'CONFIRMED' WHERE id = :room_id")
                ->execute([':room_id' => $roomId]);

            $pdo->prepare(
                "UPDATE room_participants
                 SET deposit_deadline_at = :deadline
                 WHERE room_id = :room_id AND status = 'PENDING_DEPOSIT'"
            )->execute([':deadline' => $deadline, ':room_id' => $roomId]);
        }, $this->pdo);

        return $this->getRoomDetail($roomId);
    }

    public function deposit(int $roomId, int $userId, string $method = 'VNPAY'): array
    {
        DatabaseConnection::transaction(function (PDO $pdo) use ($roomId, $userId, $method): void {
            $roomStmt = $pdo->prepare('SELECT * FROM game_rooms WHERE id = :id LIMIT 1');
            $roomStmt->execute([':id' => $roomId]);
            $room = $roomStmt->fetch();

            if ($room === false) {
                throw new NotFoundException('Không tìm thấy phòng chơi #' . $roomId);
            }

            $rule = json_decode((string) $room['snapshot_game_rule'], true);
            $amount = (int) ($rule['deposit_vnd'] ?? 50000);

            $partStmt = $pdo->prepare(
                "SELECT * FROM room_participants
                 WHERE room_id = :room_id AND user_id = :user_id
                   AND status IN ('PENDING_DEPOSIT', 'DEPOSITED') LIMIT 1"
            );
            $partStmt->execute([':room_id' => $roomId, ':user_id' => $userId]);
            $participant = $partStmt->fetch();

            if ($participant === false) {
                throw new NotFoundException('Người chơi không thuộc phòng này');
            }

            if ($participant['status'] === 'DEPOSITED') {
                throw new HttpException('Người chơi đã đặt cọc cho phòng này rồi', 409);
            }

            $txKey = 'DEP-' . $roomId . '-' . $userId . '-' . bin2hex(random_bytes(3));
            $insTx = $pdo->prepare(
                "INSERT INTO payment_transactions (room_id, participant_id, user_id, idempotency_key, payment_type, amount_vnd, status, note, paid_at)
                 VALUES (:room_id, :part_id, :user_id, :idem_key, 'DEPOSIT', :amount, 'SUCCESS', :note, NOW())"
            );
            $insTx->execute([
                ':room_id' => $roomId,
                ':part_id' => $participant['id'],
                ':user_id' => $userId,
                ':idem_key' => $txKey,
                ':amount' => $amount,
                ':note' => 'Method: ' . strtoupper($method),
            ]);

            $updPart = $pdo->prepare("UPDATE room_participants SET status = 'DEPOSITED' WHERE id = :id");
            $updPart->execute([':id' => $participant['id']]);
        }, $this->pdo);

        return $this->getRoomDetail($roomId);
    }
}