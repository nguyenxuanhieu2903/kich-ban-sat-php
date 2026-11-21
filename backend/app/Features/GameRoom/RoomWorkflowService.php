<?php

declare(strict_types=1);

namespace App\Features\GameRoom;

use App\Core\Exceptions\BadRequestException;
use App\Core\Exceptions\HttpException;
use App\Core\Exceptions\NotFoundException;
use App\Infrastructure\Database\DatabaseConnection;
use DateInterval;
use DateTimeImmutable;
use PDO;

class RoomWorkflowService
{
    private const MAX_REOPEN_COUNT = 2;
    private const FORMATION_HOURS = 4;
    private const PROPOSAL_EXPIRE_HOURS = 2;

    private PDO $pdo;
    private GameRoomService $roomService;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DatabaseConnection::getInstance();
        $this->roomService = new GameRoomService($this->pdo);
    }

    /** @return array<string, mixed> */
    public function expireRoom(int $roomId, int $actorUserId, string $actorRole, bool $force = false): array
    {
        if (strtoupper($actorRole) !== 'ADMIN') {
            throw new HttpException('Chỉ quản trị viên mới có quyền chốt hết hạn phòng', 403);
        }
        $this->requireActiveUser($actorUserId, ['ADMIN']);

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
                throw new HttpException("Không thể đánh dấu hết hạn phòng đang ở trạng thái {$status}", 409);
            }

            $now = new DateTimeImmutable();
            $deadline = new DateTimeImmutable((string) $room['formation_deadline_at']);
            if (!$force && $now < $deadline) {
                throw new HttpException('Phòng chưa đến hạn hết thời gian gom người', 409);
            }

            $rule = json_decode((string) $room['snapshot_game_rule'], true);
            $minPlayers = is_array($rule) ? (int) ($rule['min_players'] ?? 1) : 1;
            if ($this->countActivePlayers($roomId) >= $minPlayers) {
                throw new HttpException('Phòng đã đủ số người chơi tối thiểu, không thể đánh dấu hết hạn', 409);
            }

            $upd = $this->pdo->prepare("UPDATE game_rooms SET status = 'EXPIRED' WHERE id = :id");
            $upd->execute([':id' => $roomId]);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return $this->roomService->getRoomDetail($roomId);
    }

    /** @return array<string, mixed> */
    public function reopenRoom(int $roomId, int $actorUserId, string $actorRole): array
    {
        $this->requireActiveUser($actorUserId, ['PLAYER', 'ADMIN']);

        $lockSql = $this->getRowLockSql();
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM game_rooms WHERE id = :id{$lockSql}");
            $stmt->execute([':id' => $roomId]);
            $room = $stmt->fetch();

            if ($room === false) {
                throw new NotFoundException('Không tìm thấy phòng chơi');
            }

            $isAdmin = strtoupper($actorRole) === 'ADMIN';
            $isHost = (int) $room['host_user_id'] === $actorUserId;
            if (!$isAdmin && !$isHost) {
                throw new HttpException('Chỉ chủ phòng hoặc quản trị viên mới có quyền mở lại phòng', 403);
            }

            if ((string) $room['status'] !== 'EXPIRED') {
                throw new HttpException('Chỉ có thể mở lại phòng đang ở trạng thái EXPIRED', 409);
            }

            $reopenCount = (int) $room['reopen_count'];
            if ($reopenCount >= self::MAX_REOPEN_COUNT) {
                throw new HttpException('Phòng đã vượt quá giới hạn số lần mở lại (' . self::MAX_REOPEN_COUNT . ' lần)', 409);
            }

            $now = new DateTimeImmutable();
            $scheduledAt = new DateTimeImmutable((string) $room['scheduled_start_at']);
            if ($scheduledAt <= $now) {
                throw new HttpException('Giờ bắt đầu của phòng đã qua, không thể mở lại', 409);
            }

            $newDeadline = $now->add(new DateInterval('PT' . self::FORMATION_HOURS . 'H'));
            if ($newDeadline > $scheduledAt) {
                $newDeadline = $scheduledAt;
            }

            $upd = $this->pdo->prepare(
                "UPDATE game_rooms
                 SET status = 'REPLENISHING',
                     reopen_count = reopen_count + 1,
                     formation_deadline_at = :deadline
                 WHERE id = :id"
            );
            $upd->execute([
                ':deadline' => $newDeadline->format('Y-m-d H:i:s'),
                ':id' => $roomId,
            ]);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return $this->roomService->getRoomDetail($roomId);
    }

    /** @return array<string, mixed> */
    public function proposeDmAssignment(int $roomId, int $dmUserId, int $assignedBy): array
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

            if ((string) $room['status'] !== 'WAITING_FOR_DM') {
                throw new HttpException('Chỉ có thể gửi phân công DM khi phòng đang ở trạng thái WAITING_FOR_DM', 409);
            }

            $rule = json_decode((string) $room['snapshot_game_rule'], true);
            $minPlayers = is_array($rule) ? (int) ($rule['min_players'] ?? 1) : 1;
            $activePlayers = $this->countActivePlayers($roomId);
            if ($activePlayers < $minPlayers) {
                throw new HttpException("Phòng chưa đủ số người chơi tối thiểu ({$activePlayers}/{$minPlayers})", 409);
            }

            $dmStmt = $this->pdo->prepare('SELECT id, role, is_active FROM users WHERE id = :id LIMIT 1');
            $dmStmt->execute([':id' => $dmUserId]);
            $dm = $dmStmt->fetch();
            if ($dm === false || strtoupper((string) $dm['role']) !== 'DM' || (int) $dm['is_active'] !== 1) {
                throw new BadRequestException('Tài khoản DM không hợp lệ hoặc chưa kích hoạt');
            }

            $pendingStmt = $this->pdo->prepare(
                "SELECT id FROM dm_assignments
                 WHERE room_id = :room_id AND dm_user_id = :dm_id AND status = 'PENDING'
                 LIMIT 1"
            );
            $pendingStmt->execute([':room_id' => $roomId, ':dm_id' => $dmUserId]);
            if ($pendingStmt->fetch() !== false) {
                throw new HttpException('DM này đã có lời mời phân công đang chờ phản hồi cho phòng', 409);
            }

            $ins = $this->pdo->prepare(
                "INSERT INTO dm_assignments (room_id, dm_user_id, assigned_by, status)
                 VALUES (:room_id, :dm_user_id, :assigned_by, 'PENDING')"
            );
            $ins->execute([
                ':room_id' => $roomId,
                ':dm_user_id' => $dmUserId,
                ':assigned_by' => $assignedBy,
            ]);
            $assignmentId = (int) $this->pdo->lastInsertId();

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return [
            'id' => $assignmentId,
            'room_id' => $roomId,
            'dm_user_id' => $dmUserId,
            'assigned_by' => $assignedBy,
            'status' => 'PENDING',
        ];
    }

    /** @return array<string, mixed> */
    public function respondDmAssignment(
        int $assignmentId,
        int $dmUserId,
        string $decision,
        ?int $physicalRoomId = null,
        ?string $reason = null
    ): array {
        $this->requireActiveUser($dmUserId, ['DM']);
        $normalized = strtoupper(trim($decision));
        if (!in_array($normalized, ['ACCEPTED', 'REJECTED'], true)) {
            throw new BadRequestException('Quyết định chỉ chấp nhận ACCEPTED hoặc REJECTED');
        }

        $lockSql = $this->getRowLockSql();
        $nowStr = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        $this->pdo->beginTransaction();
        try {
            $aStmt = $this->pdo->prepare("SELECT * FROM dm_assignments WHERE id = :id{$lockSql}");
            $aStmt->execute([':id' => $assignmentId]);
            $assignment = $aStmt->fetch();

            if ($assignment === false) {
                throw new NotFoundException('Không tìm thấy phân công DM');
            }
            if ((int) $assignment['dm_user_id'] !== $dmUserId) {
                throw new HttpException('Bạn không phải DM được phân công trong yêu cầu này', 403);
            }
            if ((string) $assignment['status'] !== 'PENDING') {
                throw new HttpException('Phân công DM này đã được xử lý trước đó', 409);
            }

            $roomId = (int) $assignment['room_id'];

            if ($normalized === 'REJECTED') {
                $upd = $this->pdo->prepare(
                    "UPDATE dm_assignments
                     SET status = 'REJECTED', reason = :reason, responded_at = :responded_at
                     WHERE id = :id"
                );
                $upd->execute([
                    ':reason' => ($reason !== null && trim($reason) !== '') ? mb_substr(trim($reason), 0, 500) : 'DM từ chối lịch',
                    ':responded_at' => $nowStr,
                    ':id' => $assignmentId,
                ]);
                $this->pdo->commit();

                return [
                    'assignment_id' => $assignmentId,
                    'room_id' => $roomId,
                    'status' => 'REJECTED',
                ];
            }

            if ($physicalRoomId === null || $physicalRoomId <= 0) {
                throw new BadRequestException('Cần cung cấp physical_room_id khi DM xác nhận nhận phòng');
            }

            $rStmt = $this->pdo->prepare("SELECT * FROM game_rooms WHERE id = :id{$lockSql}");
            $rStmt->execute([':id' => $roomId]);
            $room = $rStmt->fetch();
            if ($room === false || (string) $room['status'] !== 'WAITING_FOR_DM') {
                throw new HttpException('Phòng không còn ở trạng thái WAITING_FOR_DM', 409);
            }

            $rule = json_decode((string) $room['snapshot_game_rule'], true);
            $minPlayers = is_array($rule) ? (int) ($rule['min_players'] ?? 1) : 1;
            $durationMinutes = is_array($rule) ? (int) ($rule['duration_minutes'] ?? 180) : 180;
            $activePlayers = $this->countActivePlayers($roomId);
            if ($activePlayers < $minPlayers) {
                throw new HttpException("Phòng chưa đủ số người chơi tối thiểu ({$activePlayers}/{$minPlayers})", 409);
            }

            $prStmt = $this->pdo->prepare('SELECT id, status, capacity FROM physical_rooms WHERE id = :id LIMIT 1');
            $prStmt->execute([':id' => $physicalRoomId]);
            $pr = $prStmt->fetch();
            if ($pr === false || (string) $pr['status'] !== 'AVAILABLE') {
                throw new BadRequestException('Phòng vật lý không tồn tại hoặc không khả dụng');
            }
            if ((int) $pr['capacity'] < $activePlayers + 1) {
                throw new HttpException('Phòng vật lý không đủ sức chứa (tính cả DM)', 409);
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

            $updAssign = $this->pdo->prepare(
                "UPDATE dm_assignments SET status = 'ACCEPTED', responded_at = :responded_at WHERE id = :id"
            );
            $updAssign->execute([':responded_at' => $nowStr, ':id' => $assignmentId]);

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
            $updPart->execute([':deadline' => $depositDeadline, ':room_id' => $roomId]);

            $updRoom = $this->pdo->prepare("UPDATE game_rooms SET status = 'CONFIRMED' WHERE id = :id");
            $updRoom->execute([':id' => $roomId]);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return [
            'assignment_id' => $assignmentId,
            'status' => 'ACCEPTED',
            'room' => $this->roomService->getRoomDetail($roomId),
        ];
    }

    /** @return array<string, mixed> */
    public function proposeSchedule(int $roomId, int $actorUserId, string $actorRole, string $newStartRaw): array
    {
        $this->requireActiveUser($actorUserId, ['PLAYER', 'ADMIN']);

        $newStartAt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', trim($newStartRaw));
        if ($newStartAt === false) {
            $newStartAt = DateTimeImmutable::createFromFormat(DateTimeImmutable::ATOM, trim($newStartRaw));
        }
        if ($newStartAt === false) {
            throw new BadRequestException('Định dạng new_start_at không hợp lệ (dùng YYYY-MM-DD HH:MM:SS)');
        }
        if (!in_array((int) $newStartAt->format('i'), [0, 30], true) || (int) $newStartAt->format('s') !== 0) {
            throw new BadRequestException('Giờ đề xuất mới phải là mốc chẵn :00 hoặc :30');
        }

        $now = new DateTimeImmutable();
        if ($newStartAt <= $now) {
            throw new BadRequestException('Giờ đề xuất mới phải ở thời điểm tương lai');
        }

        $lockSql = $this->getRowLockSql();
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM game_rooms WHERE id = :id{$lockSql}");
            $stmt->execute([':id' => $roomId]);
            $room = $stmt->fetch();

            if ($room === false) {
                throw new NotFoundException('Không tìm thấy phòng chơi');
            }

            $isAdmin = strtoupper($actorRole) === 'ADMIN';
            $isHost = (int) $room['host_user_id'] === $actorUserId;
            if (!$isAdmin && !$isHost) {
                throw new HttpException('Chỉ chủ phòng hoặc quản trị viên mới có quyền đề xuất đổi lịch', 403);
            }

            if (in_array((string) $room['status'], ['CANCELLED', 'COMPLETED', 'EXPIRED'], true)) {
                throw new HttpException('Phòng đã đóng, không thể đề xuất đổi lịch', 409);
            }

            $expiresAt = $now->add(new DateInterval('PT' . self::PROPOSAL_EXPIRE_HOURS . 'H'))->format('Y-m-d H:i:s');
            $ins = $this->pdo->prepare(
                "INSERT INTO schedule_proposals (room_id, proposed_by, old_start_at, new_start_at, expires_at, status)
                 VALUES (:room_id, :proposed_by, :old_start_at, :new_start_at, :expires_at, 'PROPOSED')"
            );
            $ins->execute([
                ':room_id' => $roomId,
                ':proposed_by' => $actorUserId,
                ':old_start_at' => (string) $room['scheduled_start_at'],
                ':new_start_at' => $newStartAt->format('Y-m-d H:i:s'),
                ':expires_at' => $expiresAt,
            ]);
            $proposalId = (int) $this->pdo->lastInsertId();

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return [
            'id' => $proposalId,
            'room_id' => $roomId,
            'proposed_by' => $actorUserId,
            'old_start_at' => (string) $room['scheduled_start_at'],
            'new_start_at' => $newStartAt->format('Y-m-d H:i:s'),
            'expires_at' => $expiresAt,
            'status' => 'PROPOSED',
        ];
    }

    /** @return array<string, mixed> */
    public function respondScheduleProposal(int $proposalId, int $actorUserId, string $actorRole, string $decision): array
    {
        $this->requireActiveUser($actorUserId, ['PLAYER', 'ADMIN']);
        $normalized = strtoupper(trim($decision));
        if (!in_array($normalized, ['APPROVED', 'REJECTED'], true)) {
            throw new BadRequestException('Quyết định chỉ chấp nhận APPROVED hoặc REJECTED');
        }

        $lockSql = $this->getRowLockSql();
        $now = new DateTimeImmutable();

        $this->pdo->beginTransaction();
        try {
            $pStmt = $this->pdo->prepare("SELECT * FROM schedule_proposals WHERE id = :id{$lockSql}");
            $pStmt->execute([':id' => $proposalId]);
            $proposal = $pStmt->fetch();

            if ($proposal === false) {
                throw new NotFoundException('Không tìm thấy đề xuất đổi lịch');
            }
            if ((string) $proposal['status'] !== 'PROPOSED') {
                throw new HttpException('Đề xuất đổi lịch này đã được xử lý trước đó', 409);
            }

            $expiresAt = new DateTimeImmutable((string) $proposal['expires_at']);
            if ($now > $expiresAt) {
                $exp = $this->pdo->prepare("UPDATE schedule_proposals SET status = 'EXPIRED' WHERE id = :id");
                $exp->execute([':id' => $proposalId]);
                $this->pdo->commit();
                throw new HttpException('Đề xuất đổi lịch đã quá hạn biểu quyết', 409);
            }

            $roomId = (int) $proposal['room_id'];
            $rStmt = $this->pdo->prepare("SELECT * FROM game_rooms WHERE id = :id{$lockSql}");
            $rStmt->execute([':id' => $roomId]);
            $room = $rStmt->fetch();
            if ($room === false) {
                throw new NotFoundException('Không tìm thấy phòng chơi');
            }

            $isAdmin = strtoupper($actorRole) === 'ADMIN';
            $isHost = (int) $room['host_user_id'] === $actorUserId;
            if (!$isAdmin && !$isHost) {
                throw new HttpException('Chỉ chủ phòng hoặc quản trị viên mới có quyền chốt đề xuất đổi lịch', 403);
            }

            if ($normalized === 'REJECTED') {
                $upd = $this->pdo->prepare("UPDATE schedule_proposals SET status = 'REJECTED' WHERE id = :id");
                $upd->execute([':id' => $proposalId]);
                $this->pdo->commit();

                return [
                    'proposal_id' => $proposalId,
                    'room_id' => $roomId,
                    'status' => 'REJECTED',
                ];
            }

            $newStartStr = (string) $proposal['new_start_at'];
            $rule = json_decode((string) $room['snapshot_game_rule'], true);
            $durationMinutes = is_array($rule) ? (int) ($rule['duration_minutes'] ?? 180) : 180;
            $newEndStr = (new DateTimeImmutable($newStartStr))
                ->add(new DateInterval('PT' . $durationMinutes . 'M'))
                ->format('Y-m-d H:i:s');

            $sessStmt = $this->pdo->prepare(
                "SELECT id, dm_user_id, physical_room_id FROM game_sessions
                 WHERE room_id = :room_id AND status = 'SCHEDULED'
                 LIMIT 1{$lockSql}"
            );
            $sessStmt->execute([':room_id' => $roomId]);
            $session = $sessStmt->fetch();

            if (is_array($session)) {
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
                    ':pr_id' => (int) $session['physical_room_id'],
                    ':dm_id' => (int) $session['dm_user_id'],
                    ':start_at' => $newStartStr,
                    ':end_at' => $newEndStr,
                ]);
                if ($conflictStmt->fetch() !== false) {
                    throw new HttpException('Khung giờ mới bị trùng lịch DM hoặc phòng vật lý', 409);
                }

                $updSess = $this->pdo->prepare(
                    'UPDATE game_sessions SET start_at = :start_at, end_at = :end_at WHERE id = :id'
                );
                $updSess->execute([
                    ':start_at' => $newStartStr,
                    ':end_at' => $newEndStr,
                    ':id' => (int) $session['id'],
                ]);
            }

            $updRoom = $this->pdo->prepare('UPDATE game_rooms SET scheduled_start_at = :start_at WHERE id = :id');
            $updRoom->execute([':start_at' => $newStartStr, ':id' => $roomId]);

            $updProp = $this->pdo->prepare("UPDATE schedule_proposals SET status = 'APPROVED' WHERE id = :id");
            $updProp->execute([':id' => $proposalId]);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return [
            'proposal_id' => $proposalId,
            'status' => 'APPROVED',
            'room' => $this->roomService->getRoomDetail($roomId),
        ];
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
}