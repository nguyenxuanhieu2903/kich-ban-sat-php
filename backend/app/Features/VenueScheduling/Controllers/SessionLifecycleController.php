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
}