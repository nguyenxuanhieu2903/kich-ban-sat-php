<?php

declare(strict_types=1);

namespace App\Features\VenueScheduling\Controllers;

use App\Core\Middleware\AuthMiddleware;
use App\Core\Request;
use App\Core\Response;
use App\Infrastructure\Database\DatabaseConnection;
use App\Shared\Services\AuditLogService;
use PDO;

class SessionLifecycleController
{
    protected PDO $pdo;
    protected AuditLogService $auditService;

    public function __construct(?PDO $pdo = null, ?AuditLogService $auditService = null)
    {
        $this->pdo = $pdo ?? DatabaseConnection::getInstance();
        $this->auditService = $auditService ?? new AuditLogService($this->pdo);

        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }
    }

    /**
     * Đảm bảo DM chỉ thao tác trên ca chơi được phân công cho chính mình (Admin luôn có quyền).
     *
     * @param array<string, mixed> $auth
     * @param array<string, mixed> $session
     */
    private function assertDmSessionAccess(array $auth, array $session): ?Response
    {
        $role = strtoupper((string) ($auth['role_code'] ?? $auth['role'] ?? ''));
        if ($role === 'ADMIN') {
            return null;
        }
        $dmAssignId = (int) ($session['dm_assignment_id'] ?? 0);
        if ($dmAssignId > 0) {
            $chk = $this->pdo->prepare('SELECT `dm_user_id` FROM `dm_assignments` WHERE `id` = :id LIMIT 1');
            $chk->bindValue(':id', $dmAssignId, PDO::PARAM_INT);
            $chk->execute();
            $assignedDmId = (int) $chk->fetchColumn();
            if ($assignedDmId === (int) $auth['id']) {
                return null;
            }
            return Response::fail(
                'Bạn không phải Quản trò được phân công phụ trách ca chơi này',
                'FORBIDDEN_DM_SESSION',
                403
            );
        }

        // Trường hợp session chưa gắn dm_assignment_id: kiểm tra phân công trực tiếp trên game_room_id
        $roomId = (int) ($session['game_room_id'] ?? 0);
        if ($roomId > 0) {
            $roomDmChk = $this->pdo->prepare(
                'SELECT `dm_user_id` FROM `dm_assignments`
                 WHERE `game_room_id` = :rid AND `status` IN (\'ACCEPTED\', \'PENDING\')
                 ORDER BY `id` DESC LIMIT 1'
            );
            $roomDmChk->execute([':rid' => $roomId]);
            $assignedDmId = (int) $roomDmChk->fetchColumn();
            if ($assignedDmId === (int) $auth['id']) {
                return null;
            }
        }

        return Response::fail(
            'Bạn không phải Quản trò được phân công phụ trách ca chơi này',
            'FORBIDDEN_DM_SESSION',
            403
        );
    }

    /**
     * GET /api/v1/sessions/{id} (UC-D02: Xem chi tiết buổi chơi — chống IDOR)
     */
    public function show(Request $request, string $id): Response
    {
        $auth = AuthMiddleware::authenticate($request);
        if ($auth instanceof Response) {
            return $auth;
        }

        $sessionId = (int) $id;
        $stmt = $this->pdo->prepare(
            'SELECT s.`id`, s.`game_room_id`, s.`schedule_proposal_id`, s.`dm_assignment_id`,
                    s.`venue_id`, s.`physical_room_id`, s.`start_at`, s.`end_at`, s.`status`,
                    s.`started_at`, s.`completed_at`, s.`cancelled_at`, s.`cancel_reason`,
                    s.`created_at`, s.`updated_at`,
                    a.`dm_user_id`, a.`status` AS `dm_assignment_status`,
                    dm.`full_name` AS `dm_name`, dm.`email` AS `dm_email`,
                    r.`title` AS `room_title`, r.`status` AS `room_status`, r.`creator_id`
             FROM `sessions` s
             LEFT JOIN `dm_assignments` a ON a.`id` = s.`dm_assignment_id`
             LEFT JOIN `users` dm ON dm.`id` = a.`dm_user_id`
             LEFT JOIN `game_rooms` r ON r.`id` = s.`game_room_id`
             WHERE s.`id` = :id
             LIMIT 1'
        );
        $stmt->bindValue(':id', $sessionId, PDO::PARAM_INT);
        $stmt->execute();
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($session === false) {
            return Response::fail('Không tìm thấy buổi chơi yêu cầu', 'SESSION_NOT_FOUND', 404);
        }

        // Chống IDOR: Chỉ Admin, DM phụ trách hoặc thành viên của phòng chơi mới được xem chi tiết ca chơi
        $role = strtoupper((string) ($auth['role_code'] ?? $auth['role'] ?? ''));
        $uid = (int) $auth['id'];
        if ($role !== 'ADMIN') {
            $isAssignedDm = (int) ($session['dm_user_id'] ?? 0) === $uid;
            $isCreator = (int) ($session['creator_id'] ?? 0) === $uid;
            $isMember = false;
            if (!$isAssignedDm && !$isCreator) {
                $mChk = $this->pdo->prepare(
                    'SELECT 1 FROM `room_participants` WHERE `game_room_id` = :rid AND `user_id` = :uid LIMIT 1'
                );
                $mChk->execute([':rid' => (int) $session['game_room_id'], ':uid' => $uid]);
                $isMember = $mChk->fetchColumn() !== false;
            }
            if (!$isAssignedDm && !$isCreator && !$isMember) {
                return Response::fail('Bạn không có quyền xem chi tiết buổi chơi này', 'FORBIDDEN', 403);
            }
        }

        return Response::success([
            'session' => $session,
        ], 'Thông tin chi tiết buổi chơi');
    }

    /**
     * POST /api/v1/sessions/{id}/start
     */
    public function start(Request $request, string $id): Response
    {
        $auth = AuthMiddleware::authenticate($request, ['ADMIN', 'DM']);
        if ($auth instanceof Response) {
            return $auth;
        }

        $sessionId = (int) $id;
        $body = $request->getBodyParams();
        $staffFillIn = !empty($body['staff_fill_in']) || !empty($body['force_start']);

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('SELECT `id`, `game_room_id`, `dm_assignment_id`, `status` FROM `sessions` WHERE `id` = :id OR `game_room_id` = :rid ORDER BY `id` DESC LIMIT 1 FOR UPDATE');
            $stmt->bindValue(':id', $sessionId, PDO::PARAM_INT);
            $stmt->bindValue(':rid', $sessionId, PDO::PARAM_INT);
            $stmt->execute();
            $session = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($session !== false) {
                $ownerErr = $this->assertDmSessionAccess($auth, $session);
                if ($ownerErr !== null) {
                    $this->pdo->rollBack();
                    return $ownerErr;
                }
            }

            $targetRoomId = $session !== false ? (int) $session['game_room_id'] : $sessionId;
            $rmMetaStmt = $this->pdo->prepare(
                'SELECT `id`, `status`, `min_players`, `max_players`, `total_players`, `creator_id`, `booking_type`, `guest_slots`, `price_details`
                 FROM `game_rooms` WHERE `id` = :rid LIMIT 1'
            );
            $rmMetaStmt->execute([':rid' => $targetRoomId]);
            $rmMeta = $rmMetaStmt->fetch(PDO::FETCH_ASSOC);

            if ($rmMeta !== false) {
                if (in_array(strtoupper((string) ($rmMeta['status'] ?? '')), ['CANCELLED', 'COMPLETED'], true)) {
                    $this->pdo->rollBack();
                    return Response::fail('Không thể bắt đầu buổi chơi cho phòng đã hủy hoặc đã hoàn tất', 'INVALID_ROOM_STATUS', 409);
                }

                // Kiểm tra DM đã được phân công và đã chấp nhận ca (ACCEPTED) chưa
                $daChkStmt = $this->pdo->prepare(
                    'SELECT `id`, `dm_user_id`, `status` FROM `dm_assignments`
                     WHERE `game_room_id` = :rid ORDER BY `id` DESC LIMIT 1'
                );
                $daChkStmt->execute([':rid' => $targetRoomId]);
                $latestDa = $daChkStmt->fetch(PDO::FETCH_ASSOC);
                $actorRole = strtoupper((string) ($auth['role_code'] ?? $auth['role'] ?? ''));
                if ($actorRole === 'DM') {
                    if ($latestDa === false || (int) ($latestDa['dm_user_id'] ?? 0) !== (int) $auth['id']) {
                        $this->pdo->rollBack();
                        return Response::fail('Bạn không phải Quản trò được phân công phụ trách phòng chơi này', 'FORBIDDEN_DM_SESSION', 403);
                    }
                    if (strtoupper((string) ($latestDa['status'] ?? '')) !== 'ACCEPTED') {
                        $this->pdo->rollBack();
                        return Response::fail(
                            'Bạn cần bấm Nhận ca trước khi bắt đầu buổi chơi.',
                            'DM_NOT_ACCEPTED',
                            409
                        );
                    }
                }

                // Kiểm tra đủ số lượng người chơi tối thiểu (min_players) qua RoomSeatHelper
                $pRowsStmt = $this->pdo->prepare(
                    'SELECT `user_id` FROM `room_participants`
                     WHERE `game_room_id` = :rid AND `status` IN (\'PENDING_DEPOSIT\', \'DEPOSITED\', \'LEAVE_REQUESTED\')'
                );
                $pRowsStmt->execute([':rid' => $targetRoomId]);
                $activeUids = array_map('intval', $pRowsStmt->fetchAll(PDO::FETCH_COLUMN));
                $seatCalc = \App\Shared\Services\RoomSeatHelper::computeSeats($rmMeta, $activeUids);
                $occupiedSeats = $seatCalc['occupied_seats'];
                $minRequired = max(1, (int) ($rmMeta['min_players'] ?? 1));

                if (!$staffFillIn && $occupiedSeats < $minRequired) {
                    $this->pdo->rollBack();
                    return Response::fail(
                        "Phòng chơi chưa đủ số lượng người chơi tối thiểu ({$occupiedSeats}/{$minRequired} người), không thể bắt đầu trò chơi.",
                        'INSUFFICIENT_PLAYERS',
                        422
                    );
                }
            }

            if ($session === false) {
                $rStmt = $this->pdo->prepare('SELECT `id`, `status` FROM `game_rooms` WHERE `id` = :id LIMIT 1 FOR UPDATE');
                $rStmt->bindValue(':id', $sessionId, PDO::PARAM_INT);
                $rStmt->execute();
                $room = $rStmt->fetch(PDO::FETCH_ASSOC);

                if ($room !== false) {
                    if (in_array(strtoupper((string) ($room['status'] ?? '')), ['CANCELLED', 'COMPLETED'], true)) {
                        $this->pdo->rollBack();
                        return Response::fail('Không thể bắt đầu buổi chơi cho phòng đã hủy hoặc đã hoàn tất', 'INVALID_ROOM_STATUS', 409);
                    }
                    $roomId = (int) $room['id'];
                    // 1. Get or create proposal
                    $pStmt = $this->pdo->prepare('SELECT `id` FROM `schedule_proposals` WHERE `game_room_id` = :rid ORDER BY `id` DESC LIMIT 1');
                    $pStmt->bindValue(':rid', $roomId, PDO::PARAM_INT);
                    $pStmt->execute();
                    $propId = $pStmt->fetchColumn();
                    if ($propId === false) {
                        $insP = $this->pdo->prepare('INSERT INTO `schedule_proposals` (`game_room_id`, `proposed_by`, `proposed_start_at`, `proposed_end_at`, `status`, `expires_at`, `created_at`) VALUES (:rid, :uid, NOW(), DATE_ADD(NOW(), INTERVAL 4 HOUR), \'CONFIRMED\', DATE_ADD(NOW(), INTERVAL 24 HOUR), NOW())');
                        $insP->bindValue(':rid', $roomId, PDO::PARAM_INT);
                        $insP->bindValue(':uid', (int) $auth['id'], PDO::PARAM_INT);
                        $insP->execute();
                        $propId = (int) $this->pdo->lastInsertId();
                    }

                    // 2. Get or create dm assignment
                    $dmStmt = $this->pdo->prepare('SELECT `id` FROM `dm_assignments` WHERE `game_room_id` = :rid ORDER BY `id` DESC LIMIT 1');
                    $dmStmt->bindValue(':rid', $roomId, PDO::PARAM_INT);
                    $dmStmt->execute();
                    $dmAssignId = $dmStmt->fetchColumn();
                    if ($dmAssignId === false) {
                        $insDm = $this->pdo->prepare('INSERT INTO `dm_assignments` (`game_room_id`, `dm_user_id`, `assigned_by`, `status`, `assigned_at`, `created_at`, `updated_at`) VALUES (:rid, :dm_id, :by_id, \'ACCEPTED\', NOW(), NOW(), NOW())');
                        $insDm->bindValue(':rid', $roomId, PDO::PARAM_INT);
                        $insDm->bindValue(':dm_id', (int) $auth['id'], PDO::PARAM_INT);
                        $insDm->bindValue(':by_id', (int) $auth['id'], PDO::PARAM_INT);
                        $insDm->execute();
                        $dmAssignId = (int) $this->pdo->lastInsertId();
                    }

                    // 3. Get physical room & venue
                    $prStmt = $this->pdo->query('SELECT `id`, `venue_id` FROM `physical_rooms` LIMIT 1');
                    $pr = $prStmt->fetch(PDO::FETCH_ASSOC) ?: ['id' => 1, 'venue_id' => 1];

                    // 4. Insert session
                    $insSess = $this->pdo->prepare(
                        'INSERT INTO `sessions` (`game_room_id`, `schedule_proposal_id`, `dm_assignment_id`, `venue_id`, `physical_room_id`, `start_at`, `end_at`, `status`, `started_at`, `created_at`, `updated_at`)
                         VALUES (:rid, :prop_id, :dm_id, :venue_id, :pr_id, NOW(), DATE_ADD(NOW(), INTERVAL 4 HOUR), \'IN_PROGRESS\', NOW(), NOW(), NOW())'
                    );
                    $insSess->bindValue(':rid', $roomId, PDO::PARAM_INT);
                    $insSess->bindValue(':prop_id', (int) $propId, PDO::PARAM_INT);
                    $insSess->bindValue(':dm_id', (int) $dmAssignId, PDO::PARAM_INT);
                    $insSess->bindValue(':venue_id', (int) $pr['venue_id'], PDO::PARAM_INT);
                    $insSess->bindValue(':pr_id', (int) $pr['id'], PDO::PARAM_INT);
                    $insSess->execute();
                    $createdSessionId = (int) $this->pdo->lastInsertId();

                    $updRoom = $this->pdo->prepare('UPDATE `game_rooms` SET `status` = \'IN_PROGRESS\', `updated_at` = NOW() WHERE `id` = :rid');
                    $updRoom->bindValue(':rid', $roomId, PDO::PARAM_INT);
                    $updRoom->execute();

                    $this->auditService->log((int) $auth['id'], 'START_SESSION', 'session', $createdSessionId, ['game_room_id' => $roomId]);
                    $this->pdo->commit();

                    return Response::success([
                        'session_id' => $createdSessionId,
                        'status' => 'IN_PROGRESS',
                    ], 'Đã bắt đầu phiên chơi');
                }

                $this->pdo->rollBack();
                return Response::fail('Không tìm thấy phiên chơi', 'SESSION_NOT_FOUND', 404);
            }

            if ($session['status'] === 'IN_PROGRESS') {
                $this->pdo->commit();
                return Response::success([
                    'session_id' => (int) $session['id'],
                    'status' => 'IN_PROGRESS',
                ], 'Phiên chơi hiện đang diễn ra');
            }

            if ($session['status'] !== 'SCHEDULED') {
                $this->pdo->rollBack();
                return Response::fail(
                    'Chỉ có thể bắt đầu phiên chơi đang ở trạng thái SCHEDULED (Hiện tại: ' . $session['status'] . ')',
                    'INVALID_SESSION_STATUS',
                    409
                );
            }

            $upd = $this->pdo->prepare(
                'UPDATE `sessions` SET `status` = \'IN_PROGRESS\', `started_at` = NOW(), `updated_at` = NOW() WHERE `id` = :id'
            );
            $upd->bindValue(':id', (int) $session['id'], PDO::PARAM_INT);
            $upd->execute();

            $updRoom = $this->pdo->prepare('UPDATE `game_rooms` SET `status` = \'IN_PROGRESS\', `updated_at` = NOW() WHERE `id` = :rid');
            $updRoom->bindValue(':rid', (int) $session['game_room_id'], PDO::PARAM_INT);
            $updRoom->execute();

            $this->auditService->log((int) $auth['id'], 'START_SESSION', 'session', (int) $session['id'], ['game_room_id' => (int) $session['game_room_id']]);

            $this->pdo->commit();

            return Response::success([
                'session_id' => (int) $session['id'],
                'status' => 'IN_PROGRESS',
            ], 'Đã bắt đầu phiên chơi');
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * POST /api/v1/sessions/{id}/complete
     */
    public function complete(Request $request, string $id): Response
    {
        $auth = AuthMiddleware::authenticate($request, ['ADMIN', 'DM']);
        if ($auth instanceof Response) {
            return $auth;
        }

        $sessionId = (int) $id;

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('SELECT `id`, `game_room_id`, `dm_assignment_id`, `status` FROM `sessions` WHERE `id` = :id OR `game_room_id` = :rid ORDER BY `id` DESC LIMIT 1 FOR UPDATE');
            $stmt->bindValue(':id', $sessionId, PDO::PARAM_INT);
            $stmt->bindValue(':rid', $sessionId, PDO::PARAM_INT);
            $stmt->execute();
            $session = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($session !== false) {
                $ownerErr = $this->assertDmSessionAccess($auth, $session);
                if ($ownerErr !== null) {
                    $this->pdo->rollBack();
                    return $ownerErr;
                }
            }

            if ($session === false) {
                $this->pdo->rollBack();
                return Response::fail('Không tìm thấy phiên chơi hợp lệ đang diễn ra', 'SESSION_NOT_FOUND', 404);
            }

            if ($session['status'] === 'COMPLETED') {
                $this->pdo->commit();
                return Response::success([
                    'session_id' => (int) $session['id'],
                    'status' => 'COMPLETED',
                ], 'Phiên chơi đã hoàn tất trước đó');
            }

            if ($session['status'] !== 'IN_PROGRESS') {
                $this->pdo->rollBack();
                return Response::fail(
                    'Chỉ có thể hoàn tất buổi chơi đang diễn ra. Không thể kết thúc khi buổi chơi chưa bắt đầu.',
                    'INVALID_SESSION_STATUS',
                    409
                );
            }

            // Bước 1: Đánh dấu đã kết thúc phần chơi (completed_at = NOW()), CHƯA chuyển COMPLETED.
            // Phải thực hiện bước Tất toán tại quầy (settle) tiếp theo thì ca mới chính thức chuyển sang COMPLETED.
            $upd = $this->pdo->prepare(
                'UPDATE `sessions` SET `completed_at` = COALESCE(`completed_at`, NOW()), `updated_at` = NOW() WHERE `id` = :id'
            );
            $upd->bindValue(':id', (int) $session['id'], PDO::PARAM_INT);
            $upd->execute();

            $this->auditService->log((int) $auth['id'], 'FINISH_GAMEPLAY', 'session', (int) $session['id'], [
                'game_room_id' => (int) $session['game_room_id'],
                'awaiting_settlement' => true,
            ]);

            $this->pdo->commit();

            return Response::success([
                'session_id' => (int) $session['id'],
                'game_room_id' => (int) $session['game_room_id'],
                'status' => 'IN_PROGRESS',
                'gameplay_finished' => true,
                'awaiting_settlement' => true,
            ], 'Đã hoàn tất buổi chơi. Vui lòng thực hiện Tất toán tại quầy để chuyển ca sang trạng thái Hoàn tất.');
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * POST /api/v1/sessions/{id}/notes
     * Ghi chú kết quả & sự cố hiện trường buổi chơi
     */
    public function saveNotes(Request $request, string $id): Response
    {
        $auth = AuthMiddleware::authenticate($request, ['ADMIN', 'DM']);
        if ($auth instanceof Response) {
            return $auth;
        }

        $sessionId = (int) $id;
        $body = $request->getBodyParams();
        $notes = trim((string) ($body['notes'] ?? $body['session_notes'] ?? ''));
        $incident = trim((string) ($body['incident_report'] ?? ''));

        $chkStmt = $this->pdo->prepare('SELECT `id`, `game_room_id`, `dm_assignment_id`, `status` FROM `sessions` WHERE `id` = :sid OR `game_room_id` = :rid ORDER BY `id` DESC LIMIT 1');
        $chkStmt->bindValue(':sid', $sessionId, PDO::PARAM_INT);
        $chkStmt->bindValue(':rid', $sessionId, PDO::PARAM_INT);
        $chkStmt->execute();
        $sessCheck = $chkStmt->fetch(PDO::FETCH_ASSOC);
        if ($sessCheck !== false) {
            $ownerErr = $this->assertDmSessionAccess($auth, $sessCheck);
            if ($ownerErr !== null) {
                return $ownerErr;
            }
        }

        $up = $this->pdo->prepare(
            'UPDATE `sessions`
             SET `session_notes` = :notes,
                 `incident_report` = COALESCE(NULLIF(:incident, \'\'), `incident_report`),
                 `updated_at` = NOW()
             WHERE `id` = :sid OR `game_room_id` = :rid'
        );
        $up->bindValue(':notes', $notes, PDO::PARAM_STR);
        $up->bindValue(':incident', $incident, PDO::PARAM_STR);
        $up->bindValue(':sid', $sessionId, PDO::PARAM_INT);
        $up->bindValue(':rid', $sessionId, PDO::PARAM_INT);
        $up->execute();

        $this->auditService->log(
            (int) $auth['id'],
            'SESSION_NOTES_UPDATED',
            'session',
            $sessionId,
            ['notes' => $notes, 'incident_report' => $incident]
        );

        return Response::success([
            'session_id' => $sessionId,
            'notes' => $notes,
        ], 'Đã lưu ghi chú hiện trường thành công');
    }

    /**
     * POST /api/v1/sessions/{id}/settle (hoặc /api/v1/dm/sessions/{id}/settle)
     * Xác nhận tất toán phần tiền còn lại tại quầy (Điểm 1 & Điểm 26)
     */
    public function settlePayment(Request $request, string $id): Response
    {
        $auth = AuthMiddleware::authenticate($request, ['ADMIN', 'DM']);
        if ($auth instanceof Response) {
            return $auth;
        }

        $sessionId = (int) $id;
        $body = $request->getBodyParams();
        $isDisputed = !empty($body['is_disputed']);
        $notes = trim((string) ($body['notes'] ?? ''));
        $extraFee = max(0, (int) round((float) ($body['extra_fee'] ?? 0)));
        $extraFeeReason = trim((string) ($body['extra_fee_reason'] ?? ''));

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('SELECT `id`, `game_room_id`, `dm_assignment_id`, `status`, `completed_at` FROM `sessions` WHERE `id` = :id OR `game_room_id` = :rid ORDER BY `id` DESC LIMIT 1 FOR UPDATE');
            $stmt->bindValue(':id', $sessionId, PDO::PARAM_INT);
            $stmt->bindValue(':rid', $sessionId, PDO::PARAM_INT);
            $stmt->execute();
            $session = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($session !== false) {
                $ownerErr = $this->assertDmSessionAccess($auth, $session);
                if ($ownerErr !== null) {
                    $this->pdo->rollBack();
                    return $ownerErr;
                }
                $stUpper = strtoupper((string) ($session['status'] ?? ''));
                if (in_array($stUpper, ['CANCELLED', 'COMPLETED'], true)) {
                    $this->pdo->rollBack();
                    return Response::fail('Không thể tất toán tại quầy cho ca chơi đã hủy hoặc đã hoàn tất', 'INVALID_SESSION_STATUS', 409);
                }
                if ($stUpper !== 'IN_PROGRESS') {
                    $this->pdo->rollBack();
                    return Response::fail('Phiên chơi chưa bắt đầu nên chưa thể tất toán tại quầy', 'SESSION_NOT_STARTED', 409);
                }
                if (empty($session['completed_at']) && !$isDisputed) {
                    $this->pdo->rollBack();
                    return Response::fail(
                        'Vui lòng bấm Hoàn tất buổi chơi trước, sau đó mới thực hiện Tất toán tại quầy để chốt hoàn tất ca.',
                        'GAMEPLAY_NOT_FINISHED',
                        409
                    );
                }
            }

            $roomId = $session ? (int) $session['game_room_id'] : $sessionId;
            $targetStatus = $isDisputed ? 'PAYMENT_DISPUTE' : 'SETTLED';

            if ($isDisputed) {
                $updPay = $this->pdo->prepare(
                    'UPDATE `player_payments` p
                     INNER JOIN `room_participants` rp ON rp.`id` = p.`room_participant_id`
                     SET p.`status` = \'CANCELLED\', p.`updated_at` = NOW()
                     WHERE rp.`game_room_id` = :rid
                       AND rp.`status` IN (\'PENDING_DEPOSIT\', \'DEPOSITED\', \'LEAVE_REQUESTED\')
                       AND p.`status` NOT IN (\'PAID\', \'CANCELLED\')'
                );
                $updPay->bindValue(':rid', $roomId, PDO::PARAM_INT);
                $updPay->execute();
            } else {
                // 1. Lấy và đánh dấu PAID cho các khoản PENDING/PARTIALLY_PAID (TUYỆT ĐỐI không hồi sinh khoản CANCELLED!)
                $unpaidStmt = $this->pdo->prepare(
                    'SELECT p.`id`, p.`amount`, p.`paid_amount`, p.`payment_type`
                     FROM `player_payments` p
                     INNER JOIN `room_participants` rp ON rp.`id` = p.`room_participant_id`
                     WHERE rp.`game_room_id` = :rid
                       AND rp.`status` IN (\'PENDING_DEPOSIT\', \'DEPOSITED\', \'LEAVE_REQUESTED\')
                       AND p.`status` NOT IN (\'PAID\', \'CANCELLED\')
                     FOR UPDATE'
                );
                $unpaidStmt->execute([':rid' => $roomId]);
                $unpaidRows = $unpaidStmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($unpaidRows as $upRow) {
                    $delta = max(0, (int) $upRow['amount'] - (int) $upRow['paid_amount']);
                    $this->pdo->prepare(
                        'UPDATE `player_payments`
                         SET `status` = \'PAID\', `paid_amount` = `amount`, `paid_at` = COALESCE(`paid_at`, NOW()), `updated_at` = NOW()
                         WHERE `id` = :id'
                    )->execute([':id' => (int) $upRow['id']]);
                    if ($delta > 0) {
                        $this->pdo->prepare(
                            'INSERT INTO `payment_transactions` (`player_payment_id`, `idempotency_key`, `transaction_reference`, `amount`, `status`, `provider_response`, `requested_at`, `processed_at`, `created_at`)
                             VALUES (:ppid, :ikey, :txref, :amt, \'SUCCESS\', :meta, NOW(), NOW(), NOW())'
                        )->execute([
                            ':ppid' => (int) $upRow['id'],
                            ':ikey' => 'CTR_EX_' . $roomId . '_' . (int) $upRow['id'] . '_' . bin2hex(random_bytes(5)),
                            ':txref' => 'TX_CTR_' . strtoupper(bin2hex(random_bytes(5))),
                            ':amt' => $delta,
                            ':meta' => json_encode(['method' => 'COUNTER_SETTLE', 'payment_type' => $upRow['payment_type'], 'dm_id' => (int) $auth['id']]),
                        ]);
                    }
                }

                // 2. Tự động ghi nhận khoản thu phần còn lại (REMAINING) + phụ phí extra_fee tại quầy để đồng bộ 100% Doanh thu Dashboard
                $rMetaStmt = $this->pdo->prepare('SELECT `game_id`, `creator_id`, `total_players`, `max_players`, `target_time_slot`, `price_details` FROM `game_rooms` WHERE `id` = :rid LIMIT 1');
                $rMetaStmt->execute([':rid' => $roomId]);
                $rMeta = $rMetaStmt->fetch(PDO::FETCH_ASSOC);
                $extraFeeApplied = false;

                if ($rMeta !== false) {
                    $pricingService = new \App\Features\Pricing\Services\PricingService($this->pdo);
                    $slotRaw = trim((string) ($rMeta['target_time_slot'] ?? ''));
                    $playDt = $slotRaw !== '' ? date('Y-m-d H:i:s', strtotime($slotRaw) ?: time()) : date('Y-m-d H:i:s');

                    $partsStmt = $this->pdo->prepare(
                        'SELECT rp.`id`, rp.`user_id`, COALESCE(u.`student_verified`, 0) AS `student_verified`
                         FROM `room_participants` rp
                         INNER JOIN `users` u ON u.`id` = rp.`user_id`
                         WHERE rp.`game_room_id` = :rid AND rp.`status` IN (\'PENDING_DEPOSIT\', \'DEPOSITED\', \'LEAVE_REQUESTED\')
                         ORDER BY rp.`id` ASC'
                    );
                    $partsStmt->execute([':rid' => $roomId]);
                    $activeParts = $partsStmt->fetchAll(PDO::FETCH_ASSOC);
                    $activeUids = array_map(static fn (array $ap): int => (int) $ap['user_id'], $activeParts);
                    $seatCalc = \App\Shared\Services\RoomSeatHelper::computeSeats($rMeta, $activeUids);

                    foreach ($activeParts as $ap) {
                        $pid = (int) $ap['id'];
                        $hasRemStmt = $this->pdo->prepare('SELECT `id`, `amount`, `paid_amount` FROM `player_payments` WHERE `room_participant_id` = :pid AND `payment_type` = \'REMAINING\' AND `status` != \'CANCELLED\' LIMIT 1');
                        $hasRemStmt->execute([':pid' => $pid]);
                        $existingRemRow = $hasRemStmt->fetch(PDO::FETCH_ASSOC);

                        if ($existingRemRow !== false) {
                            if ($extraFee > 0 && !$extraFeeApplied) {
                                $this->pdo->prepare(
                                    'UPDATE `player_payments` SET `amount` = `amount` + :ef, `paid_amount` = `paid_amount` + :ef2, `updated_at` = NOW() WHERE `id` = :ppid'
                                )->execute([':ef' => $extraFee, ':ef2' => $extraFee, ':ppid' => (int) $existingRemRow['id']]);
                                $this->pdo->prepare(
                                    'INSERT INTO `payment_transactions` (`player_payment_id`, `idempotency_key`, `transaction_reference`, `amount`, `status`, `provider_response`, `requested_at`, `processed_at`, `created_at`)
                                     VALUES (:ppid, :ikey, :txref, :amt, \'SUCCESS\', :meta, NOW(), NOW(), NOW())'
                                )->execute([
                                    ':ppid' => (int) $existingRemRow['id'],
                                    ':ikey' => 'CTR_FEE_' . $roomId . '_' . $pid . '_' . bin2hex(random_bytes(5)),
                                    ':txref' => 'TX_FEE_' . strtoupper(bin2hex(random_bytes(5))),
                                    ':amt' => $extraFee,
                                    ':meta' => json_encode(['method' => 'COUNTER_EXTRA_FEE', 'extra_fee_reason' => $extraFeeReason, 'dm_id' => (int) $auth['id']]),
                                ]);
                                $extraFeeApplied = true;
                            }
                            continue;
                        }

                        $paidSumStmt = $this->pdo->prepare('SELECT COALESCE(SUM(`paid_amount`), 0) FROM `player_payments` WHERE `room_participant_id` = :pid AND `status` != \'CANCELLED\'');
                        $paidSumStmt->execute([':pid' => $pid]);
                        $alreadyPaid = (int) $paidSumStmt->fetchColumn();

                        $seats = $seatCalc['seats_by_user'][(int) $ap['user_id']] ?? 1;
                        $quote = $pricingService->calculateQuote((int) $rMeta['game_id'], $seats, $playDt, !empty($ap['student_verified']));
                        $remAmt = max(0, (int) ($quote['final_total_vnd'] ?? 0) - $alreadyPaid);
                        $feeForThisPart = ($extraFee > 0 && !$extraFeeApplied) ? $extraFee : 0;
                        $totalToCollect = $remAmt + $feeForThisPart;

                        if ($totalToCollect > 0) {
                            $insRem = $this->pdo->prepare(
                                'INSERT INTO `player_payments` (`room_participant_id`, `payment_type`, `amount`, `paid_amount`, `status`, `paid_at`, `created_at`, `updated_at`)
                                 VALUES (:pid, \'REMAINING\', :amt, :paid, \'PAID\', NOW(), NOW(), NOW())'
                            );
                            $insRem->execute([':pid' => $pid, ':amt' => $totalToCollect, ':paid' => $totalToCollect]);
                            $newPayId = (int) $this->pdo->lastInsertId();

                            $txRef = 'TX_CTR_' . strtoupper(bin2hex(random_bytes(5)));
                            $this->pdo->prepare(
                                'INSERT INTO `payment_transactions` (`player_payment_id`, `idempotency_key`, `transaction_reference`, `amount`, `status`, `provider_response`, `requested_at`, `processed_at`, `created_at`)
                                 VALUES (:ppid, :ikey, :txref, :amt, \'SUCCESS\', :meta, NOW(), NOW(), NOW())'
                            )->execute([
                                ':ppid' => $newPayId,
                                ':ikey' => 'CTR_' . $roomId . '_' . $pid . '_' . bin2hex(random_bytes(5)),
                                ':txref' => $txRef,
                                ':amt' => $totalToCollect,
                                ':meta' => json_encode([
                                    'method' => 'COUNTER_SETTLE',
                                    'dm_id' => (int) $auth['id'],
                                    'base_remaining' => $remAmt,
                                    'extra_fee' => $feeForThisPart,
                                    'extra_fee_reason' => $feeForThisPart > 0 ? $extraFeeReason : '',
                                ]),
                            ]);
                            if ($feeForThisPart > 0) {
                                $extraFeeApplied = true;
                            }
                        }
                    }
                }

                // 3. Sau khi hoàn tất buổi chơi + tất toán tại quầy -> chính thức chuyển ca & phòng sang COMPLETED
                if ($session !== false) {
                    $this->pdo->prepare(
                        'UPDATE `sessions` SET `status` = \'COMPLETED\', `completed_at` = COALESCE(`completed_at`, NOW()), `updated_at` = NOW() WHERE `id` = :sid'
                    )->execute([':sid' => (int) $session['id']]);
                }
                $this->pdo->prepare('UPDATE `game_rooms` SET `status` = \'COMPLETED\', `updated_at` = NOW() WHERE `id` = :rid')
                    ->execute([':rid' => $roomId]);

                $this->auditService->log((int) $auth['id'], 'COMPLETE_SESSION', 'session', $session ? (int) $session['id'] : $sessionId, ['game_room_id' => $roomId]);

                // Gửi thông báo hoàn tất ca tới các thành viên đang hoạt động
                try {
                    $notiRepo = new \App\Features\Notification\Repositories\NotificationRepository($this->pdo);
                    $pStmt = $this->pdo->prepare('SELECT `user_id` FROM `room_participants` WHERE `game_room_id` = :rid AND `status` IN (\'PENDING_DEPOSIT\', \'DEPOSITED\', \'LEAVE_REQUESTED\')');
                    $pStmt->execute([':rid' => $roomId]);
                    $playerIds = array_map('intval', $pStmt->fetchAll(PDO::FETCH_COLUMN));
                    if (!empty($playerIds)) {
                        $notiRepo->createNotification(
                            'Buổi chơi đã hoàn tất',
                            "Buổi chơi tại phòng #{$roomId} đã hoàn tất và tất toán xong. Cảm ơn bạn đã tham gia — bạn có thể để lại đánh giá ngay trong trang chi tiết phòng!",
                            'SESSION',
                            (int) $auth['id'],
                            $playerIds,
                            'ROOM',
                            $roomId
                        );
                    }
                } catch (\Throwable) {
                }
            }

            $this->auditService->log((int) $auth['id'], 'SETTLE_PAYMENT_AT_COUNTER', 'session', $session ? (int) $session['id'] : $sessionId, [
                'status' => $targetStatus,
                'notes' => $notes,
                'room_id' => $roomId,
                'extra_fee' => $extraFee,
                'extra_fee_reason' => $extraFeeReason,
            ]);

            $this->pdo->commit();

            return Response::success([
                'session_id' => $session ? (int) $session['id'] : $sessionId,
                'room_id' => $roomId,
                'status' => $isDisputed ? 'IN_PROGRESS' : 'COMPLETED',
                'payment_status' => $targetStatus,
                'extra_fee' => $extraFee,
                'extra_fee_reason' => $extraFeeReason,
            ], $isDisputed ? 'Đã ghi nhận tranh chấp thanh toán tại quầy' : 'Đã tất toán tại quầy và chuyển ca sang trạng thái Hoàn tất thành công');
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * POST /api/v1/sessions/{id}/no-show (hoặc /api/v1/dm/sessions/{id}/no-show)
     * Xử lý trường hợp khách vắng mặt (No-show) phân cấp 3 mức độ (Điểm 9)
     */
    public function handleNoShow(Request $request, string $id): Response
    {
        $auth = AuthMiddleware::authenticate($request, ['ADMIN', 'DM']);
        if ($auth instanceof Response) {
            return $auth;
        }

        $sessionId = (int) $id;
        $body = $request->getBodyParams();
        $absentUserIds = $body['absent_user_ids'] ?? [];
        if (empty($absentUserIds) && !empty($body['absent_user_id'])) {
            $absentUserIds = [(int) $body['absent_user_id']];
        }

        if (!is_array($absentUserIds) || empty($absentUserIds)) {
            return Response::fail('Vui lòng nhập ID người chơi vắng mặt (absent_user_id hoặc absent_user_ids)', 'VALIDATION_ERROR', 422);
        }

        $absentUserIds = array_values(array_filter(array_map('intval', $absentUserIds), fn ($uid) => $uid > 0));
        if (empty($absentUserIds)) {
            return Response::fail('Danh sách ID người chơi vắng mặt không hợp lệ', 'VALIDATION_ERROR', 422);
        }
        $absentCount = count($absentUserIds);

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('SELECT `id`, `game_room_id`, `dm_assignment_id`, `status` FROM `sessions` WHERE `id` = :id OR `game_room_id` = :rid ORDER BY `id` DESC LIMIT 1 FOR UPDATE');
            $stmt->bindValue(':id', $sessionId, PDO::PARAM_INT);
            $stmt->bindValue(':rid', $sessionId, PDO::PARAM_INT);
            $stmt->execute();
            $session = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($session !== false) {
                $ownerErr = $this->assertDmSessionAccess($auth, $session);
                if ($ownerErr !== null) {
                    $this->pdo->rollBack();
                    return $ownerErr;
                }
                if (in_array(strtoupper((string) ($session['status'] ?? '')), ['CANCELLED', 'COMPLETED'], true)) {
                    $this->pdo->rollBack();
                    return Response::fail('Không thể ghi nhận vắng mặt cho ca chơi đã hủy hoặc đã hoàn tất', 'INVALID_SESSION_STATUS', 409);
                }
            }

            $roomId = $session ? (int) $session['game_room_id'] : $sessionId;
            $actualSessionId = $session ? (int) $session['id'] : $sessionId;

            $pStmt = $this->pdo->prepare('SELECT rp.`user_id`, rp.`id` AS `participant_id`, p.`id` AS `payment_id`, p.`paid_amount` FROM `room_participants` rp LEFT JOIN `player_payments` p ON p.`room_participant_id` = rp.`id` AND p.`status` != \'CANCELLED\' WHERE rp.`game_room_id` = :rid AND rp.`status` IN (\'PENDING_DEPOSIT\', \'DEPOSITED\', \'LEAVE_REQUESTED\')');
            $pStmt->bindValue(':rid', $roomId, PDO::PARAM_INT);
            $pStmt->execute();
            $allParticipants = $pStmt->fetchAll(PDO::FETCH_ASSOC);

            $notiRepo = new \App\Features\Notification\Repositories\NotificationRepository($this->pdo);

            if ($absentCount === 1) {
                $absentUid = $absentUserIds[0];
                foreach ($allParticipants as $p) {
                    if ((int) $p['user_id'] === $absentUid && !empty($p['participant_id'])) {
                        $this->pdo->prepare('UPDATE `room_participants` SET `status` = \'CANCELLED_BY_ADMIN\', `cancel_reason` = \'NO_SHOW_FORFEITED\', `cancelled_at` = NOW(), `updated_at` = NOW() WHERE `id` = :pid')
                            ->execute([':pid' => (int) $p['participant_id']]);
                    }
                }

                $this->auditService->log((int) $auth['id'], 'NO_SHOW_SINGLE_PLAYER', 'session', $actualSessionId, [
                    'absent_user_id' => $absentUid,
                    'action' => 'DM_ACTS_AS_NPC',
                ]);
                $this->pdo->commit();

                return Response::success([
                    'session_id' => $actualSessionId,
                    'absent_count' => 1,
                    'action' => 'NPC_SUBSTITUTE',
                    'session_status' => 'IN_PROGRESS',
                ], 'Đã ghi nhận 01 khách vắng mặt (Tịch thu cọc FORFEITED). Quản trò tiếp tục hỗ trợ đóng thế vai NPC.');
            }

            $this->pdo->prepare('UPDATE `sessions` SET `status` = \'CANCELLED\', `cancelled_at` = NOW(), `cancel_reason` = :r, `updated_at` = NOW() WHERE `id` = :id')
                ->execute([':r' => 'CANCELLED_ON_SITE: Hủy ca tại chỗ do thiếu từ 2 người chơi trở lên (No-show)', ':id' => $actualSessionId]);
            $this->pdo->prepare('UPDATE `game_rooms` SET `status` = \'CANCELLED\', `cancelled_at` = NOW(), `cancel_reason` = :r, `updated_at` = NOW() WHERE `id` = :rid')
                ->execute([':r' => 'CANCELLED_ON_SITE: Hủy ca tại chỗ do thiếu từ 2 người chơi trở lên (No-show)', ':rid' => $roomId]);

            $refundedUsers = [];
            $forfeitedUsers = [];

            foreach ($allParticipants as $p) {
                $uid = (int) $p['user_id'];
                if (in_array($uid, $absentUserIds, true)) {
                    if (!empty($p['participant_id'])) {
                        $this->pdo->prepare('UPDATE `room_participants` SET `status` = \'CANCELLED_BY_ADMIN\', `cancel_reason` = \'NO_SHOW_FORFEITED\', `cancelled_at` = NOW(), `updated_at` = NOW() WHERE `id` = :pid')
                            ->execute([':pid' => (int) $p['participant_id']]);
                    }
                    $forfeitedUsers[] = $uid;
                } else {
                    $paidAmt = (int) ($p['paid_amount'] ?? 0);
                    if ($paidAmt > 0 && !empty($p['payment_id'])) {
                        $chkRef = $this->pdo->prepare('SELECT `id` FROM `refunds` WHERE `player_payment_id` = :pid AND `status` IN (\'PENDING\', \'APPROVED\', \'COMPLETED\') LIMIT 1');
                        $chkRef->execute([':pid' => (int) $p['payment_id']]);
                        if ($chkRef->fetchColumn() === false) {
                            $insRef = $this->pdo->prepare('INSERT INTO `refunds` (`player_payment_id`, `amount`, `reason`, `status`, `requested_at`, `reviewed_by`, `reviewed_at`, `created_at`, `updated_at`) VALUES (:pid, :amt, :r, \'APPROVED\', NOW(), :rev, NOW(), NOW(), NOW())');
                            $insRef->bindValue(':pid', (int) $p['payment_id'], PDO::PARAM_INT);
                            $insRef->bindValue(':amt', $paidAmt, PDO::PARAM_INT);
                            $insRef->bindValue(':r', 'Hoàn cọc 100% do sự cố bàn chơi thiếu người (No-show >= 2)', PDO::PARAM_STR);
                            $insRef->bindValue(':rev', (int) $auth['id'], PDO::PARAM_INT);
                            $insRef->execute();
                        }
                    }

                    $notiRepo->createNotification(
                        'Thông báo hoàn cọc do sự cố bàn chơi',
                        'Buổi chơi bị hủy do có từ 2 thành viên khác vắng mặt. Bạn được duyệt hoàn cọc 100% trong vòng 02 giờ làm việc và tặng voucher giảm giá 20% cho lần chơi sau.',
                        'REFUND',
                        (int) $auth['id'],
                        [$uid]
                    );
                    $refundedUsers[] = $uid;
                }
            }

            $this->auditService->log((int) $auth['id'], 'CANCELLED_ON_SITE_NO_SHOW', 'session', $actualSessionId, [
                'absent_count' => $absentCount,
                'forfeited_users' => $forfeitedUsers,
                'refunded_users' => $refundedUsers,
            ]);
            $this->pdo->commit();

            return Response::success([
                'session_id' => $actualSessionId,
                'session_status' => 'CANCELLED',
                'action' => 'CANCELLED_ON_SITE',
                'absent_count' => $absentCount,
                'forfeited_users' => $forfeitedUsers,
                'refunded_users' => $refundedUsers,
            ], 'Đã hủy ca chơi tại chỗ do vắng từ 2 người trở lên. Tiền cọc người vắng mặt bị tịch thu, người đến đúng giờ được duyệt hoàn cọc 100%.');
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * POST /api/v1/sessions/{id}/overtime-alert (hoặc /api/v1/dm/sessions/{id}/overtime-alert)
     * Quản trò DM gửi cảnh báo ca chơi có nguy cơ kéo dài quá giờ để Admin điều phối phòng
     */
    public function overtimeAlert(Request $request, string $id): Response
    {
        $auth = AuthMiddleware::authenticate($request, ['ADMIN', 'DM']);
        if ($auth instanceof Response) {
            return $auth;
        }

        $sessionId = (int) $id;
        $body = $request->getBodyParams();
        $estimatedMinutes = max(10, min(180, (int) ($body['estimated_minutes'] ?? 30)));
        $reason = trim((string) ($body['reason'] ?? 'Người chơi tranh luận cao trào, dự kiến kéo dài thêm'));

        $stmt = $this->pdo->prepare('SELECT `id`, `game_room_id`, `dm_assignment_id`, `physical_room_id`, `venue_id`, `end_at` FROM `sessions` WHERE `id` = :id OR `game_room_id` = :rid ORDER BY `id` DESC LIMIT 1');
        $stmt->bindValue(':id', $sessionId, PDO::PARAM_INT);
        $stmt->bindValue(':rid', $sessionId, PDO::PARAM_INT);
        $stmt->execute();
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$session) {
            return Response::fail('Không tìm thấy ca chơi', 'SESSION_NOT_FOUND', 404);
        }

        $ownerErr = $this->assertDmSessionAccess($auth, $session);
        if ($ownerErr !== null) {
            return $ownerErr;
        }

        $actualSessionId = (int) $session['id'];

        $this->auditService->log((int) $auth['id'], 'SESSION_OVERTIME_ALERT', 'session', $actualSessionId, [
            'estimated_minutes' => $estimatedMinutes,
            'reason' => $reason,
            'physical_room_id' => $session['physical_room_id'],
            'venue_id' => $session['venue_id'],
            'original_end_at' => $session['end_at'],
        ]);

        // Gửi thông báo cảnh báo trễ ca tới toàn bộ Admin
        try {
            $adminStmt = $this->pdo->query('SELECT u.`id` FROM `users` u INNER JOIN `roles` r ON r.`id` = u.`role_id` WHERE r.`code` = \'ADMIN\' AND u.`status` = \'ACTIVE\'');
            $adminIds = $adminStmt ? array_map('intval', $adminStmt->fetchAll(PDO::FETCH_COLUMN)) : [];
            if (!empty($adminIds)) {
                $notiRepo = new \App\Features\Notification\Repositories\NotificationRepository($this->pdo);
                $notiRepo->createNotification(
                    'Cảnh báo ca chơi kéo dài quá giờ',
                    "Ca chơi #{$actualSessionId} (Phòng #{$session['game_room_id']}) dự kiến kéo dài thêm {$estimatedMinutes} phút. Lý do: {$reason}.",
                    'ADMIN',
                    (int) $auth['id'],
                    $adminIds
                );
            }
        } catch (\Throwable) {
            // Không chặn luồng chính
        }

        return Response::success([
            'session_id' => $actualSessionId,
            'estimated_minutes' => $estimatedMinutes,
            'reason' => $reason,
            'alert_sent' => true,
        ], "Đã gửi cảnh báo trễ ca thêm {$estimatedMinutes} phút tới Quản trị viên và Chi nhánh");
    }

    /**
     * POST /api/v1/sessions/{id}/reviews
     * Người chơi gửi đánh giá chất lượng DM và kịch bản (1-5 sao) sau khi ca chơi hoàn thành
     */
    public function submitReview(Request $request, string $id): Response
    {
        $auth = AuthMiddleware::authenticate($request);
        if ($auth instanceof Response) {
            return $auth;
        }

        $sessionId = (int) $id;
        $userId = (int) $auth['id'];
        $body = $request->getBodyParams();

        $fallbackRating = isset($body['rating']) ? (int) $body['rating'] : 5;
        $dmRating = max(1, min(5, (int) ($body['dm_rating'] ?? $fallbackRating)));
        $scenarioRating = max(1, min(5, (int) ($body['scenario_rating'] ?? $fallbackRating)));
        $comment = trim((string) ($body['comment'] ?? ''));

        $stmt = $this->pdo->prepare('SELECT `id`, `game_room_id`, `status`, `dm_assignment_id` FROM `sessions` WHERE `id` = :id LIMIT 1');
        $stmt->bindValue(':id', $sessionId, PDO::PARAM_INT);
        $stmt->execute();
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$session) {
            return Response::fail('Không tìm thấy ca chơi', 'SESSION_NOT_FOUND', 404);
        }

        if ($session['status'] !== 'COMPLETED') {
            return Response::fail('Chỉ có thể đánh giá sau khi buổi chơi đã hoàn thành (Trạng thái: ' . $session['status'] . ')', 'SESSION_NOT_COMPLETED', 400);
        }

        $partStmt = $this->pdo->prepare(
            'SELECT `id` FROM `room_participants`
             WHERE `game_room_id` = :rid AND `user_id` = :uid
             LIMIT 1'
        );
        $partStmt->bindValue(':rid', (int) $session['game_room_id'], PDO::PARAM_INT);
        $partStmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $partStmt->execute();
        $authRole = strtoupper((string) ($auth['role_code'] ?? $auth['role'] ?? ''));
        if ($partStmt->fetchColumn() === false && $authRole !== 'ADMIN') {
            return Response::fail('Bạn không phải là người tham gia ca chơi này', 'FORBIDDEN', 403);
        }

        $dupStmt = $this->pdo->prepare(
            'SELECT `id` FROM `audit_logs`
             WHERE `entity_type` = \'session\' AND `entity_id` = :sid AND `action` = \'POST_GAME_REVIEW\'
               AND (`actor_id` = :uid1 OR `user_id` = :uid2)
             LIMIT 1'
        );
        $dupStmt->execute([':sid' => $sessionId, ':uid1' => $userId, ':uid2' => $userId]);
        if ($dupStmt->fetchColumn() !== false) {
            return Response::fail('Bạn đã gửi đánh giá cho buổi chơi này rồi', 'ALREADY_REVIEWED', 409);
        }

        $this->auditService->log($userId, 'POST_GAME_REVIEW', 'session', $sessionId, [
            'rating' => $dmRating,
            'dm_rating' => $dmRating,
            'scenario_rating' => $scenarioRating,
            'comment' => $comment,
            'user_name' => $auth['full_name'] ?? $auth['username'] ?? 'Người chơi',
        ]);

        return Response::success([
            'session_id' => $sessionId,
            'rating' => $dmRating,
            'dm_rating' => $dmRating,
            'scenario_rating' => $scenarioRating,
            'comment' => $comment,
        ], 'Cảm ơn bạn đã gửi đánh giá buổi chơi! Đánh giá đã được ghi nhận.');
    }

    /**
     * GET /api/v1/sessions/{id}/reviews
     * Lấy danh sách đánh giá của ca chơi
     */
    public function getReviews(Request $request, string $id): Response
    {
        $sessionId = (int) $id;

        $stmt = $this->pdo->prepare(
            'SELECT `id`, COALESCE(`actor_id`, `user_id`) AS `actor_id`, `metadata`, `created_at`
             FROM `audit_logs`
             WHERE `entity_type` = \'session\' AND `entity_id` = :sid AND `action` = \'POST_GAME_REVIEW\'
             ORDER BY `id` DESC'
        );
        $stmt->bindValue(':sid', $sessionId, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $reviews = array_map(function ($r) {
            $meta = json_decode((string) ($r['metadata'] ?? '{}'), true) ?: [];
            return [
                'id' => (int) $r['id'],
                'user_id' => (int) $r['actor_id'],
                'user_name' => $meta['user_name'] ?? 'Ẩn danh',
                'dm_rating' => (int) ($meta['dm_rating'] ?? 5),
                'scenario_rating' => (int) ($meta['scenario_rating'] ?? 5),
                'comment' => $meta['comment'] ?? '',
                'created_at' => $r['created_at'],
            ];
        }, $rows);

        return Response::success([
            'session_id' => $sessionId,
            'reviews' => $reviews,
            'total_reviews' => count($reviews),
        ], 'Danh sách đánh giá ca chơi');
    }
}