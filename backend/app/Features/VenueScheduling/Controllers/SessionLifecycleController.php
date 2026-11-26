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
        if ($dmAssignId <= 0) {
            return null;
        }
        $chk = $this->pdo->prepare('SELECT `dm_user_id` FROM `dm_assignments` WHERE `id` = :id LIMIT 1');
        $chk->bindValue(':id', $dmAssignId, PDO::PARAM_INT);
        $chk->execute();
        $assignedDmId = (int) $chk->fetchColumn();
        if ($assignedDmId > 0 && $assignedDmId !== (int) $auth['id']) {
            return Response::fail(
                'Bạn không phải Quản trò được phân công phụ trách ca chơi này',
                'FORBIDDEN_DM_SESSION',
                403
            );
        }
        return null;
    }

    /**
     * GET /api/v1/sessions/{id} (UC-D02: Xem chi tiết buổi chơi)
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
                    r.`title` AS `room_title`, r.`status` AS `room_status`
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
                $rStmt = $this->pdo->prepare('SELECT `id`, `status` FROM `game_rooms` WHERE `id` = :id LIMIT 1 FOR UPDATE');
                $rStmt->bindValue(':id', $sessionId, PDO::PARAM_INT);
                $rStmt->execute();
                $room = $rStmt->fetch(PDO::FETCH_ASSOC);
                if ($room !== false) {
                    if (in_array(strtoupper((string) ($room['status'] ?? '')), ['CANCELLED', 'COMPLETED'], true)) {
                        $this->pdo->rollBack();
                        return Response::fail('Không thể hoàn tất buổi chơi cho phòng đã hủy hoặc đã kết thúc', 'INVALID_ROOM_STATUS', 409);
                    }
                    $updRoom = $this->pdo->prepare('UPDATE `game_rooms` SET `status` = \'COMPLETED\', `updated_at` = NOW() WHERE `id` = :rid');
                    $updRoom->bindValue(':rid', (int) $room['id'], PDO::PARAM_INT);
                    $updRoom->execute();
                    $this->pdo->commit();
                    return Response::success([
                        'room_id' => (int) $room['id'],
                        'status' => 'COMPLETED',
                    ], 'Đã hoàn tất phiên chơi');
                }

                $this->pdo->rollBack();
                return Response::fail('Không tìm thấy phiên chơi', 'SESSION_NOT_FOUND', 404);
            }

            if ($session['status'] === 'COMPLETED') {
                $this->pdo->commit();
                return Response::success([
                    'session_id' => (int) $session['id'],
                    'status' => 'COMPLETED',
                ], 'Phiên chơi đã hoàn tất trước đó');
            }

            if ($session['status'] !== 'IN_PROGRESS' && $session['status'] !== 'SCHEDULED') {
                $this->pdo->rollBack();
                return Response::fail(
                    'Chỉ có thể hoàn tất phiên chơi đang diễn ra IN_PROGRESS (Hiện tại: ' . $session['status'] . ')',
                    'INVALID_SESSION_STATUS',
                    409
                );
            }

            $upd = $this->pdo->prepare(
                'UPDATE `sessions` SET `status` = \'COMPLETED\', `completed_at` = NOW(), `updated_at` = NOW() WHERE `id` = :id'
            );
            $upd->bindValue(':id', (int) $session['id'], PDO::PARAM_INT);
            $upd->execute();

            $updRoom = $this->pdo->prepare('UPDATE `game_rooms` SET `status` = \'COMPLETED\', `updated_at` = NOW() WHERE `id` = :rid');
            $updRoom->bindValue(':rid', (int) $session['game_room_id'], PDO::PARAM_INT);
            $updRoom->execute();

            $this->auditService->log((int) $auth['id'], 'COMPLETE_SESSION', 'session', (int) $session['id'], ['game_room_id' => (int) $session['game_room_id']]);

            // Gửi thông báo hoàn tất phiên chơi tới người chơi đang hoạt động (không gửi cho người đã LEFT)
            $notiRepo = new \App\Features\Notification\Repositories\NotificationRepository($this->pdo);
            $pStmt = $this->pdo->prepare('SELECT `user_id` FROM `room_participants` WHERE `game_room_id` = :rid AND `status` IN (\'PENDING_DEPOSIT\', \'DEPOSITED\', \'LEAVE_REQUESTED\')');
            $pStmt->bindValue(':rid', (int) $session['game_room_id'], PDO::PARAM_INT);
            $pStmt->execute();
            $playerIds = array_map('intval', $pStmt->fetchAll(PDO::FETCH_COLUMN));

            if (!empty($playerIds)) {
                $notiRepo->createNotification(
                    'Phiên chơi đã kết thúc',
                    'Phiên chơi kịch bản sát của bạn đã hoàn tất. Cảm ơn bạn đã tham gia và chúc bạn có những trải nghiệm tuyệt vời!',
                    'SESSION',
                    (int) $auth['id'],
                    $playerIds
                );
            }

            $this->pdo->commit();

            return Response::success([
                'session_id' => $sessionId,
                'status' => 'COMPLETED',
            ], 'Đã hoàn tất phiên chơi');
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
        $extraFee = max(0, (float) ($body['extra_fee'] ?? 0));
        $extraFeeReason = trim((string) ($body['extra_fee_reason'] ?? ''));

        $stmt = $this->pdo->prepare('SELECT `id`, `game_room_id`, `dm_assignment_id`, `status` FROM `sessions` WHERE `id` = :id OR `game_room_id` = :rid ORDER BY `id` DESC LIMIT 1');
        $stmt->bindValue(':id', $sessionId, PDO::PARAM_INT);
        $stmt->bindValue(':rid', $sessionId, PDO::PARAM_INT);
        $stmt->execute();
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($session !== false) {
            $ownerErr = $this->assertDmSessionAccess($auth, $session);
            if ($ownerErr !== null) {
                return $ownerErr;
            }
            if (in_array(strtoupper((string) ($session['status'] ?? '')), ['CANCELLED', 'COMPLETED'], true)) {
                return Response::fail('Không thể tất toán tại quầy cho ca chơi đã hủy hoặc đã hoàn tất', 'INVALID_SESSION_STATUS', 409);
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
                   AND rp.`status` IN (\'PENDING_DEPOSIT\', \'DEPOSITED\', \'LEAVE_REQUESTED\')'
            );
            $updPay->bindValue(':rid', $roomId, PDO::PARAM_INT);
            $updPay->execute();
        } else {
            // 1. Đánh dấu PAID cho tất cả bản ghi player_payments hiện có
            $updPay = $this->pdo->prepare(
                'UPDATE `player_payments` p
                 INNER JOIN `room_participants` rp ON rp.`id` = p.`room_participant_id`
                 SET p.`status` = \'PAID\', p.`paid_amount` = p.`amount`, p.`paid_at` = COALESCE(p.`paid_at`, NOW()), p.`updated_at` = NOW()
                 WHERE rp.`game_room_id` = :rid
                   AND rp.`status` IN (\'PENDING_DEPOSIT\', \'DEPOSITED\', \'LEAVE_REQUESTED\')'
            );
            $updPay->bindValue(':rid', $roomId, PDO::PARAM_INT);
            $updPay->execute();

            // 2. Tự động ghi nhận khoản thu phần còn lại (REMAINING) tại quầy vào player_payments để đồng bộ Doanh thu thuần cho Admin
            $rMetaStmt = $this->pdo->prepare('SELECT `game_id`, `creator_id`, `total_players`, `target_time_slot` FROM `game_rooms` WHERE `id` = :rid LIMIT 1');
            $rMetaStmt->execute([':rid' => $roomId]);
            $rMeta = $rMetaStmt->fetch(PDO::FETCH_ASSOC);

            if ($rMeta !== false) {
                $pricingService = new \App\Features\Pricing\Services\PricingService($this->pdo);
                $slotRaw = trim((string) ($rMeta['target_time_slot'] ?? ''));
                $playDt = $slotRaw !== '' ? date('Y-m-d H:i:s', strtotime($slotRaw) ?: time()) : date('Y-m-d H:i:s');

                $partsStmt = $this->pdo->prepare(
                    'SELECT rp.`id`, rp.`user_id`, COALESCE(u.`student_verified`, 0) AS `student_verified`
                     FROM `room_participants` rp
                     INNER JOIN `users` u ON u.`id` = rp.`user_id`
                     WHERE rp.`game_room_id` = :rid AND rp.`status` IN (\'PENDING_DEPOSIT\', \'DEPOSITED\', \'LEAVE_REQUESTED\')'
                );
                $partsStmt->execute([':rid' => $roomId]);
                $activeParts = $partsStmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($activeParts as $ap) {
                    $pid = (int) $ap['id'];
                    $hasRem = $this->pdo->prepare('SELECT `id` FROM `player_payments` WHERE `room_participant_id` = :pid AND `payment_type` = \'REMAINING\' LIMIT 1');
                    $hasRem->execute([':pid' => $pid]);
                    if ($hasRem->fetchColumn() !== false) {
                        continue;
                    }

                    $paidSumStmt = $this->pdo->prepare('SELECT COALESCE(SUM(`paid_amount`), 0) FROM `player_payments` WHERE `room_participant_id` = :pid');
                    $paidSumStmt->execute([':pid' => $pid]);
                    $alreadyPaid = (int) $paidSumStmt->fetchColumn();

                    $seats = ((int) $ap['user_id'] === (int) $rMeta['creator_id']) ? max(1, (int) ($rMeta['total_players'] ?: 1)) : 1;
                    $quote = $pricingService->calculateQuote((int) $rMeta['game_id'], $seats, $playDt, !empty($ap['student_verified']));
                    $remAmt = max(0, (int) ($quote['final_total_vnd'] ?? 0) - $alreadyPaid);
                    if ($remAmt > 0) {
                        $insRem = $this->pdo->prepare(
                            'INSERT INTO `player_payments` (`room_participant_id`, `payment_type`, `amount`, `paid_amount`, `status`, `paid_at`, `created_at`, `updated_at`)
                             VALUES (:pid, \'REMAINING\', :amt, :paid, \'PAID\', NOW(), NOW(), NOW())'
                        );
                        $insRem->execute([':pid' => $pid, ':amt' => $remAmt, ':paid' => $remAmt]);
                        $newPayId = (int) $this->pdo->lastInsertId();

                        $txRef = 'TX_CTR_' . strtoupper(bin2hex(random_bytes(5)));
                        $this->pdo->prepare(
                            'INSERT INTO `payment_transactions` (`player_payment_id`, `idempotency_key`, `transaction_reference`, `amount`, `status`, `provider_response`, `requested_at`, `processed_at`, `created_at`)
                             VALUES (:ppid, :ikey, :txref, :amt, \'SUCCESS\', :meta, NOW(), NOW(), NOW())'
                        )->execute([
                            ':ppid' => $newPayId,
                            ':ikey' => 'CTR_' . $roomId . '_' . $pid . '_' . time(),
                            ':txref' => $txRef,
                            ':amt' => $remAmt,
                            ':meta' => json_encode(['method' => 'COUNTER_SETTLE', 'dm_id' => (int) $auth['id']]),
                        ]);
                    }
                }
            }
        }

        $this->auditService->log((int) $auth['id'], 'SETTLE_PAYMENT_AT_COUNTER', 'session', $sessionId, [
            'status' => $targetStatus,
            'notes' => $notes,
            'room_id' => $roomId,
            'extra_fee' => $extraFee,
            'extra_fee_reason' => $extraFeeReason,
        ]);

        return Response::success([
            'session_id' => $sessionId,
            'room_id' => $roomId,
            'payment_status' => $targetStatus,
            'extra_fee' => $extraFee,
            'extra_fee_reason' => $extraFeeReason,
        ], $isDisputed ? 'Đã ghi nhận cờ tranh chấp tài chính PAYMENT_DISPUTE' : 'Đã xác nhận tất toán đủ tiền tại quầy thành công');
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

        $stmt = $this->pdo->prepare('SELECT `id`, `game_room_id`, `dm_assignment_id`, `status` FROM `sessions` WHERE `id` = :id OR `game_room_id` = :rid ORDER BY `id` DESC LIMIT 1');
        $stmt->bindValue(':id', $sessionId, PDO::PARAM_INT);
        $stmt->bindValue(':rid', $sessionId, PDO::PARAM_INT);
        $stmt->execute();
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($session !== false) {
            $ownerErr = $this->assertDmSessionAccess($auth, $session);
            if ($ownerErr !== null) {
                return $ownerErr;
            }
            if (in_array(strtoupper((string) ($session['status'] ?? '')), ['CANCELLED', 'COMPLETED'], true)) {
                return Response::fail('Không thể tất toán tại quầy cho ca chơi đã hủy hoặc đã hoàn tất', 'INVALID_SESSION_STATUS', 409);
            }
        }

        $roomId = $session ? (int) $session['game_room_id'] : $sessionId;
        $actualSessionId = $session ? (int) $session['id'] : $sessionId;

        // Lấy danh sách thành viên ĐANG HOẠT ĐỘNG trong phòng (không bao gồm người đã LEFT/CANCELLED)
        $pStmt = $this->pdo->prepare('SELECT rp.`user_id`, rp.`id` AS `participant_id`, p.`id` AS `payment_id`, p.`paid_amount` FROM `room_participants` rp LEFT JOIN `player_payments` p ON p.`room_participant_id` = rp.`id` WHERE rp.`game_room_id` = :rid AND rp.`status` IN (\'PENDING_DEPOSIT\', \'DEPOSITED\', \'LEAVE_REQUESTED\')');
        $pStmt->bindValue(':rid', $roomId, PDO::PARAM_INT);
        $pStmt->execute();
        $allParticipants = $pStmt->fetchAll(PDO::FETCH_ASSOC);

        $notiRepo = new \App\Features\Notification\Repositories\NotificationRepository($this->pdo);

        if ($absentCount === 1) {
            // Cấp độ 1: Thiếu 1 người -> Tịch thu cọc người vắng mặt (FORFEITED), DM đóng thế vai NPC
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

            return Response::success([
                'session_id' => $actualSessionId,
                'absent_count' => 1,
                'action' => 'NPC_SUBSTITUTE',
                'session_status' => 'IN_PROGRESS',
            ], 'Đã ghi nhận 01 khách vắng mặt (Tịch thu cọc FORFEITED). Quản trò tiếp tục hỗ trợ đóng thế vai NPC.');
        }

        // Cấp độ 2 & 3: Thiếu từ 2 người trở lên hoặc cả nhóm -> Buổi chơi Game Unplayable, hủy ca tại chỗ
        $this->pdo->prepare('UPDATE `sessions` SET `status` = \'CANCELLED\', `cancelled_at` = NOW(), `cancel_reason` = :r, `updated_at` = NOW() WHERE `id` = :id')
            ->execute([':r' => 'CANCELLED_ON_SITE: Hủy ca tại chỗ do thiếu từ 2 người chơi trở lên (No-show)', ':id' => $actualSessionId]);
        $this->pdo->prepare('UPDATE `game_rooms` SET `status` = \'CANCELLED\', `cancelled_at` = NOW(), `cancel_reason` = :r, `updated_at` = NOW() WHERE `id` = :rid')
            ->execute([':r' => 'CANCELLED_ON_SITE: Hủy ca tại chỗ do thiếu từ 2 người chơi trở lên (No-show)', ':rid' => $roomId]);

        $refundedUsers = [];
        $forfeitedUsers = [];

        foreach ($allParticipants as $p) {
            $uid = (int) $p['user_id'];
            if (in_array($uid, $absentUserIds, true)) {
                // Người vắng mặt: Tịch thu cọc
                if (!empty($p['participant_id'])) {
                    $this->pdo->prepare('UPDATE `room_participants` SET `status` = \'CANCELLED_BY_ADMIN\', `cancel_reason` = \'NO_SHOW_FORFEITED\', `cancelled_at` = NOW(), `updated_at` = NOW() WHERE `id` = :pid')
                        ->execute([':pid' => (int) $p['participant_id']]);
                }
                $forfeitedUsers[] = $uid;
            } else {
                // Người có mặt đúng giờ: Hoàn cọc 100% trong 2h làm việc + voucher (không tạo trùng lặp)
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

        $this->auditService->log((int) $auth['id'], 'CANCELLED_ON_SITE_NO_SHOW', 'session', $sessionId, [
            'absent_count' => $absentCount,
            'forfeited_users' => $forfeitedUsers,
            'refunded_users' => $refundedUsers,
        ]);

        return Response::success([
            'session_id' => $sessionId,
            'session_status' => 'CANCELLED',
            'action' => 'CANCELLED_ON_SITE',
            'absent_count' => $absentCount,
            'forfeited_users' => $forfeitedUsers,
            'refunded_users' => $refundedUsers,
        ], 'Đã hủy ca chơi tại chỗ do vắng từ 2 người trở lên. Tiền cọc người vắng mặt bị tịch thu, người đến đúng giờ được duyệt hoàn cọc 100%.');
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