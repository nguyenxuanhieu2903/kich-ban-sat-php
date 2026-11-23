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
     * GET /api/v1/sessions/{id} (UC-D02: Xem chi tiết buổi chơi)
     */
    public function show(Request $request, string $id): Response
    {
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
            $stmt = $this->pdo->prepare('SELECT `id`, `game_room_id`, `status` FROM `sessions` WHERE `id` = :id OR `game_room_id` = :rid ORDER BY `id` DESC LIMIT 1 FOR UPDATE');
            $stmt->bindValue(':id', $sessionId, PDO::PARAM_INT);
            $stmt->bindValue(':rid', $sessionId, PDO::PARAM_INT);
            $stmt->execute();
            $session = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($session === false) {
                $rStmt = $this->pdo->prepare('SELECT `id`, `status` FROM `game_rooms` WHERE `id` = :id LIMIT 1 FOR UPDATE');
                $rStmt->bindValue(':id', $sessionId, PDO::PARAM_INT);
                $rStmt->execute();
                $room = $rStmt->fetch(PDO::FETCH_ASSOC);

                if ($room !== false) {
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
            $stmt = $this->pdo->prepare('SELECT `id`, `game_room_id`, `status` FROM `sessions` WHERE `id` = :id OR `game_room_id` = :rid ORDER BY `id` DESC LIMIT 1 FOR UPDATE');
            $stmt->bindValue(':id', $sessionId, PDO::PARAM_INT);
            $stmt->bindValue(':rid', $sessionId, PDO::PARAM_INT);
            $stmt->execute();
            $session = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($session === false) {
                $rStmt = $this->pdo->prepare('SELECT `id`, `status` FROM `game_rooms` WHERE `id` = :id LIMIT 1 FOR UPDATE');
                $rStmt->bindValue(':id', $sessionId, PDO::PARAM_INT);
                $rStmt->execute();
                $room = $rStmt->fetch(PDO::FETCH_ASSOC);
                if ($room !== false) {
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

            // Gửi thông báo hoàn tất phiên chơi tới người chơi
            $notiRepo = new \App\Features\Notification\Repositories\NotificationRepository($this->pdo);
            $pStmt = $this->pdo->prepare('SELECT `user_id` FROM `room_participants` WHERE `game_room_id` = :rid');
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
        $notes = trim((string) ($body['notes'] ?? ''));

        $this->auditService->log(
            (int) $auth['id'],
            'SESSION_NOTES_UPDATED',
            'session',
            $sessionId,
            ['notes' => $notes]
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

        $stmt = $this->pdo->prepare('SELECT `id`, `game_room_id`, `status` FROM `sessions` WHERE `id` = :id OR `game_room_id` = :rid ORDER BY `id` DESC LIMIT 1');
        $stmt->bindValue(':id', $sessionId, PDO::PARAM_INT);
        $stmt->bindValue(':rid', $sessionId, PDO::PARAM_INT);
        $stmt->execute();
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        $roomId = $session ? (int) $session['game_room_id'] : $sessionId;
        $targetDbStatus = $isDisputed ? 'CANCELLED' : 'PAID';
        $targetStatus = $isDisputed ? 'PAYMENT_DISPUTE' : 'SETTLED';

        $updPay = $this->pdo->prepare(
            'UPDATE `player_payments` p
             INNER JOIN `room_participants` rp ON rp.`id` = p.`room_participant_id`
             SET p.`status` = :st, p.`paid_amount` = p.`amount`, p.`paid_at` = NOW(), p.`updated_at` = NOW()
             WHERE rp.`game_room_id` = :rid'
        );
        $updPay->bindValue(':st', $targetDbStatus, PDO::PARAM_STR);
        $updPay->bindValue(':rid', $roomId, PDO::PARAM_INT);
        $updPay->execute();

        $this->auditService->log((int) $auth['id'], 'SETTLE_PAYMENT_AT_COUNTER', 'session', $sessionId, [
            'status' => $targetStatus,
            'notes' => $notes,
            'room_id' => $roomId,
        ]);

        return Response::success([
            'session_id' => $sessionId,
            'room_id' => $roomId,
            'payment_status' => $targetStatus,
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

        if (!is_array($absentUserIds) || empty($absentUserIds)) {
            return Response::fail('Vui lòng cung cấp danh sách ID người chơi vắng mặt (absent_user_ids)', 'VALIDATION_ERROR', 422);
        }

        $absentUserIds = array_map('intval', $absentUserIds);
        $absentCount = count($absentUserIds);

        $stmt = $this->pdo->prepare('SELECT `id`, `game_room_id`, `status` FROM `sessions` WHERE `id` = :id OR `game_room_id` = :rid ORDER BY `id` DESC LIMIT 1');
        $stmt->bindValue(':id', $sessionId, PDO::PARAM_INT);
        $stmt->bindValue(':rid', $sessionId, PDO::PARAM_INT);
        $stmt->execute();
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        $roomId = $session ? (int) $session['game_room_id'] : $sessionId;

        // Lấy danh sách toàn bộ thành viên trong phòng
        $pStmt = $this->pdo->prepare('SELECT rp.`user_id`, rp.`id` AS `participant_id`, p.`id` AS `payment_id`, p.`paid_amount` FROM `room_participants` rp LEFT JOIN `player_payments` p ON p.`room_participant_id` = rp.`id` WHERE rp.`game_room_id` = :rid');
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

            $this->auditService->log((int) $auth['id'], 'NO_SHOW_SINGLE_PLAYER', 'session', $sessionId, [
                'absent_user_id' => $absentUid,
                'action' => 'DM_ACTS_AS_NPC',
            ]);

            return Response::success([
                'session_id' => $sessionId,
                'absent_count' => 1,
                'action' => 'NPC_SUBSTITUTE',
                'session_status' => 'IN_PROGRESS',
            ], 'Đã ghi nhận 01 khách vắng mặt (Tịch thu cọc FORFEITED). Quản trò tiếp tục hỗ trợ đóng thế vai NPC.');
        }

        // Cấp độ 2 & 3: Thiếu từ 2 người trở lên hoặc cả nhóm -> Buổi chơi Game Unplayable, hủy ca tại chỗ
        $this->pdo->prepare('UPDATE `sessions` SET `status` = \'CANCELLED\', `cancelled_at` = NOW(), `cancel_reason` = :r, `updated_at` = NOW() WHERE `id` = :id')
            ->execute([':r' => 'CANCELLED_ON_SITE: Hủy ca tại chỗ do thiếu từ 2 người chơi trở lên (No-show)', ':id' => $sessionId]);
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
                // Người có mặt đúng giờ: Hoàn cọc 100% trong 2h làm việc + voucher
                $paidAmt = (int) ($p['paid_amount'] ?? 50000);
                if ($paidAmt > 0 && !empty($p['payment_id'])) {
                    $insRef = $this->pdo->prepare('INSERT INTO `refunds` (`player_payment_id`, `amount`, `reason`, `status`, `requested_at`, `reviewed_by`, `reviewed_at`, `created_at`, `updated_at`) VALUES (:pid, :amt, :r, \'APPROVED\', NOW(), :rev, NOW(), NOW(), NOW())');
                    $insRef->bindValue(':pid', (int) $p['payment_id'], PDO::PARAM_INT);
                    $insRef->bindValue(':amt', $paidAmt, PDO::PARAM_INT);
                    $insRef->bindValue(':r', 'Hoàn cọc 100% do sự cố bàn chơi thiếu người (No-show >= 2)', PDO::PARAM_STR);
                    $insRef->bindValue(':rev', (int) $auth['id'], PDO::PARAM_INT);
                    $insRef->execute();
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
}