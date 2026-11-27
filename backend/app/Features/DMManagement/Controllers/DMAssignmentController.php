<?php

declare(strict_types=1);

namespace App\Features\DMManagement\Controllers;

use App\Core\Middleware\AuthMiddleware;
use App\Core\Request;
use App\Core\Response;
use App\Features\DMManagement\Repositories\DMRepository;
use App\Features\Notification\Repositories\NotificationRepository;
use App\Infrastructure\Database\DatabaseConnection;
use App\Shared\Services\AuditLogService;
use PDO;

class DMAssignmentController
{
    protected DMRepository $dmRepo;
    protected NotificationRepository $notiRepo;
    protected AuditLogService $auditService;
    protected PDO $pdo;

    public function __construct(
        ?DMRepository $dmRepo = null,
        ?NotificationRepository $notiRepo = null,
        ?AuditLogService $auditService = null,
        ?PDO $pdo = null
    ) {
        $this->pdo = $pdo ?? DatabaseConnection::getInstance();
        $this->dmRepo = $dmRepo ?? new DMRepository($this->pdo);
        $this->notiRepo = $notiRepo ?? new NotificationRepository($this->pdo);
        $this->auditService = $auditService ?? new AuditLogService($this->pdo);

        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }
    }

    /**
     * GET /api/v1/dm/assignments
     * Query: status, dm_id (admin only)
     */
    public function index(Request $request): Response
    {
        $auth = AuthMiddleware::authenticate($request, ['DM', 'ADMIN']);
        if ($auth instanceof Response) {
            return $auth;
        }

        $userRole = strtoupper((string) ($auth['role_code'] ?? ''));
        $targetDmId = (int) $auth['id'];

        if ($userRole === 'ADMIN') {
            $queryDmId = $request->query('dm_id');
            $targetDmId = $queryDmId !== null && (int) $queryDmId > 0 ? (int) $queryDmId : null;
        }

        $status = $request->query('status') !== null ? (string) $request->query('status') : null;

        $assignments = $this->dmRepo->listAssignments($targetDmId, $status);

        return Response::success([
            'assignments' => $assignments,
            'count' => count($assignments),
        ], 'Danh sách phân công quản trò');
    }

    /**
     * POST /api/v1/dm/assignments/{id}/accept
     */
    public function accept(Request $request, string $id): Response
    {
        $auth = AuthMiddleware::authenticate($request, ['DM']);
        if ($auth instanceof Response) {
            return $auth;
        }

        $assignmentId = (int) $id;
        $assignment = $this->dmRepo->findAssignmentById($assignmentId);

        if ($assignment === null) {
            return Response::fail('Không tìm thấy ca làm việc yêu cầu', 'ASSIGNMENT_NOT_FOUND', 404);
        }

        // Chống IDOR: Chỉ đúng Quản trò (DM) được phân công mới có quyền nhận ca
        if ((int) $assignment['dm_user_id'] !== (int) $auth['id']) {
            return Response::fail('Bạn không có quyền phản hồi ca làm việc của người khác', 'FORBIDDEN', 403);
        }

        if ($assignment['status'] !== 'PENDING') {
            return Response::fail('Ca làm việc này đã được xử lý trước đó (Trạng thái: ' . $assignment['status'] . ')', 'INVALID_STATUS', 400);
        }

        $roomStatus = strtoupper((string) ($assignment['room_status'] ?? ''));
        if (in_array($roomStatus, ['CANCELLED', 'COMPLETED'], true)) {
            return Response::fail('Phòng chơi đã bị hủy hoặc đã hoàn tất, không thể nhận ca', 'INVALID_ROOM_STATUS', 409);
        }

        $this->dmRepo->respondAssignment($assignmentId, 'ACCEPTED');

        // Khi DM chấp nhận ca, chuyển phòng sang CONFIRMED và đồng bộ dm_assignment_id vào session SCHEDULED nếu có
        $roomId = (int) ($assignment['game_room_id'] ?? 0);
        if ($roomId > 0) {
            $this->pdo->prepare(
                "UPDATE `game_rooms`
                 SET `status` = 'CONFIRMED', `updated_at` = NOW()
                 WHERE `id` = :rid AND `status` IN ('FORMING', 'SCHEDULED')"
            )->execute([':rid' => $roomId]);

            $this->pdo->prepare(
                "UPDATE `sessions`
                 SET `dm_assignment_id` = :daid, `updated_at` = NOW()
                 WHERE `game_room_id` = :rid AND `status` = 'SCHEDULED'"
            )->execute([':daid' => $assignmentId, ':rid' => $roomId]);
        }

        // Audit log & thông báo hai chiều (người chơi + Quản trị viên)
        $this->auditService->log((int) $auth['id'], 'ACCEPT_DM_ASSIGNMENT', 'DM_ASSIGNMENT', $assignmentId, [
            'game_room_id' => $assignment['game_room_id'] ?? null,
        ]);

        $dmName = $auth['full_name'] ?? $auth['name'] ?? 'Quản trò';

        $partsStmt = $this->pdo->prepare(
            'SELECT `user_id` FROM `room_participants` WHERE `game_room_id` = :room_id AND `status` IN (\'PENDING_DEPOSIT\', \'DEPOSITED\', \'LEAVE_REQUESTED\')'
        );
        $partsStmt->bindValue(':room_id', $roomId, PDO::PARAM_INT);
        $partsStmt->execute();
        $targetUserIds = array_map('intval', $partsStmt->fetchAll(PDO::FETCH_COLUMN));

        if (!empty($targetUserIds)) {
            $this->notiRepo->createNotification(
                'Quản trò đã tiếp nhận phòng',
                "Quản trò {$dmName} đã chính thức tiếp nhận điều phối phiên chơi cho phòng #{$roomId} ('{$assignment['room_title']}').",
                'ROOM',
                (int) $auth['id'],
                $targetUserIds,
                'ROOM',
                $roomId
            );
        }

        // Thông báo ngược lại cho Admin biết DM đã xác nhận nhận ca
        $adminStmt = $this->pdo->query('SELECT u.`id` FROM `users` u JOIN `roles` r ON r.`id` = u.`role_id` WHERE r.`code` = \'ADMIN\' AND u.`status` = \'ACTIVE\'');
        $adminIds = array_map('intval', $adminStmt->fetchAll(PDO::FETCH_COLUMN));
        if (!empty($adminIds)) {
            $this->notiRepo->createNotification(
                'Quản trò đã chấp nhận ca trực',
                "Quản trò {$dmName} đã chấp nhận ca trực tại phòng #{$roomId} ('{$assignment['room_title']}'). Phòng đã chuyển sang trạng thái Đã chốt ca.",
                'ADMIN',
                (int) $auth['id'],
                $adminIds,
                'ROOM',
                $roomId
            );
        }

        $updated = $this->dmRepo->findAssignmentById($assignmentId);

        return Response::success([
            'assignment' => $updated,
        ], 'Đã chấp nhận ca làm việc thành công');
    }

    /**
     * POST /api/v1/dm/assignments/{id}/reject
     * Body: reason (string, required)
     */
    public function reject(Request $request, string $id): Response
    {
        $auth = AuthMiddleware::authenticate($request, ['DM']);
        if ($auth instanceof Response) {
            return $auth;
        }

        $assignmentId = (int) $id;
        $assignment = $this->dmRepo->findAssignmentById($assignmentId);

        if ($assignment === null) {
            return Response::fail('Không tìm thấy ca làm việc yêu cầu', 'ASSIGNMENT_NOT_FOUND', 404);
        }

        // Chống IDOR: Chỉ đúng Quản trò (DM) được phân công mới có quyền từ chối ca
        if ((int) $assignment['dm_user_id'] !== (int) $auth['id']) {
            return Response::fail('Bạn không có quyền từ chối ca làm việc của người khác', 'FORBIDDEN', 403);
        }

        if (!in_array($assignment['status'], ['PENDING', 'ACCEPTED'], true)) {
            return Response::fail('Ca làm việc này đã được xử lý trước đó (Trạng thái: ' . $assignment['status'] . ')', 'INVALID_STATUS', 400);
        }

        // Nếu ca đã ACCEPTED, kiểm tra xem phiên chơi đã diễn ra hoặc kết thúc chưa
        if ($assignment['status'] === 'ACCEPTED') {
            $sessCheck = $this->pdo->prepare('SELECT `status` FROM `sessions` WHERE `game_room_id` = :rid ORDER BY `id` DESC LIMIT 1');
            $sessCheck->bindValue(':rid', (int) $assignment['game_room_id'], PDO::PARAM_INT);
            $sessCheck->execute();
            $sessStatus = $sessCheck->fetchColumn();
            if (in_array($sessStatus, ['IN_PROGRESS', 'COMPLETED'], true)) {
                return Response::fail('Không thể hủy nhận ca khi buổi chơi đang diễn ra hoặc đã hoàn tất', 'SESSION_ALREADY_STARTED', 409);
            }
        }

        $body = $request->getBodyParams();
        $reason = trim((string) ($body['reason'] ?? ''));

        if ($reason === '') {
            return Response::fail('Vui lòng nêu rõ lý do từ chối ca làm việc', 'VALIDATION_ERROR', 422);
        }

        $this->dmRepo->respondAssignment($assignmentId, 'REJECTED', $reason);

        // Tự động xoay tua sang Quản trò (DM) rảnh tiếp theo chưa từ chối phòng này và không trùng lịch ca khác (cả sessions lẫn target_time_slot)
        $rotatedAssignmentId = null;
        $rotatedDmId = null;
        $roomId = (int) ($assignment['game_room_id'] ?? 0);
        if ($roomId > 0) {
            $sessTimeStmt = $this->pdo->prepare(
                "SELECT s.`start_at`, s.`end_at`, gr.`target_time_slot`, COALESCE(g.`duration_minutes`, 210) AS `duration_minutes`
                 FROM `game_rooms` gr
                 INNER JOIN `games` g ON g.`id` = gr.`game_id`
                 LEFT JOIN `sessions` s ON s.`game_room_id` = gr.`id` AND s.`status` = 'SCHEDULED'
                 WHERE gr.`id` = :rid
                 ORDER BY s.`id` DESC LIMIT 1"
            );
            $sessTimeStmt->execute([':rid' => $roomId]);
            $sessTime = $sessTimeStmt->fetch(PDO::FETCH_ASSOC);

            $curStart = null;
            $curEnd = null;
            if ($sessTime !== false) {
                $durMins = max(60, (int) ($sessTime['duration_minutes'] ?? 210));
                if (!empty($sessTime['start_at']) && !empty($sessTime['end_at'])) {
                    $curStart = (string) $sessTime['start_at'];
                    $curEnd = (string) $sessTime['end_at'];
                } elseif (!empty($sessTime['target_time_slot']) && strtotime((string) $sessTime['target_time_slot']) !== false) {
                    $ts = strtotime((string) $sessTime['target_time_slot']);
                    $curStart = date('Y-m-d H:i:s', $ts);
                    $curEnd = date('Y-m-d H:i:s', $ts + $durMins * 60);
                }
            }

            $candStmt = $this->pdo->prepare(
                "SELECT u.`id`
                 FROM `users` u
                 INNER JOIN `roles` r ON r.`id` = u.`role_id`
                 WHERE r.`code` = 'DM'
                   AND u.`status` = 'ACTIVE'
                   AND u.`id` NOT IN (
                       SELECT da.`dm_user_id` FROM `dm_assignments` da
                       WHERE da.`game_room_id` = :rid AND da.`status` IN ('REJECTED', 'PENDING', 'ACCEPTED')
                   )
                 ORDER BY u.`id` ASC"
            );
            $candStmt->execute([':rid' => $roomId]);
            $candidates = array_map('intval', $candStmt->fetchAll(PDO::FETCH_COLUMN));

            $candidateId = 0;
            $myS = $curStart !== null ? strtotime($curStart) : false;
            $myE = $curEnd !== null ? strtotime($curEnd) : false;

            $nowTs = time();
            foreach ($candidates as $candUid) {
                if ($myS !== false && $myE !== false) {
                    // 1. Kiểm tra trùng trong sessions theo thời lượng thực tế từng kịch bản (IN_PROGRESS tính từ started_at) + 30 phút đệm
                    $cSess = $this->pdo->prepare(
                        "SELECT s2.`status`, s2.`start_at`, s2.`started_at`, COALESCE(g2.`duration_minutes`, 210) AS `duration_minutes`
                         FROM `sessions` s2
                         INNER JOIN `dm_assignments` da2 ON da2.`id` = s2.`dm_assignment_id`
                         INNER JOIN `game_rooms` gr2 ON gr2.`id` = s2.`game_room_id`
                         INNER JOIN `games` g2 ON g2.`id` = gr2.`game_id`
                         WHERE da2.`dm_user_id` = :uid
                           AND da2.`status` IN ('PENDING', 'ACCEPTED')
                           AND s2.`game_room_id` != :rid
                           AND s2.`status` IN ('SCHEDULED', 'IN_PROGRESS')"
                    );
                    $cSess->execute([':uid' => $candUid, ':rid' => $roomId]);
                    $hasSessConflict = false;
                    foreach ($cSess->fetchAll(PDO::FETCH_ASSOC) as $sr) {
                        $isRun = strtoupper((string) $sr['status']) === 'IN_PROGRESS';
                        $oDur = max(60, (int) ($sr['duration_minutes'] ?? 210));
                        $os = $isRun && !empty($sr['started_at'])
                            ? strtotime((string) $sr['started_at'])
                            : strtotime((string) ($sr['start_at'] ?? ''));
                        if ($os === false) {
                            $os = $isRun ? $nowTs : false;
                        }
                        if ($os === false) {
                            continue;
                        }
                        $oe = $os + $oDur * 60;
                        if ($isRun) {
                            $oe = max($oe, $nowTs + 60);
                        }
                        if ($myS < ($oe + 1800) && $myE > ($os - 1800)) {
                            $hasSessConflict = true;
                            break;
                        }
                    }
                    if ($hasSessConflict) {
                        continue;
                    }

                    // 2. Kiểm tra trùng với các phòng khác đã gán DM này (PENDING/ACCEPTED) theo target_time_slot
                    $cDa = $this->pdo->prepare(
                        "SELECT gr2.`target_time_slot`, COALESCE(g2.`duration_minutes`, 210) AS `duration_minutes`
                         FROM `dm_assignments` da2
                         INNER JOIN `game_rooms` gr2 ON gr2.`id` = da2.`game_room_id`
                         INNER JOIN `games` g2 ON g2.`id` = gr2.`game_id`
                         WHERE da2.`dm_user_id` = :uid
                           AND da2.`status` IN ('PENDING', 'ACCEPTED')
                           AND gr2.`id` != :rid
                           AND gr2.`status` NOT IN ('CANCELLED', 'COMPLETED')
                           AND gr2.`target_time_slot` IS NOT NULL"
                    );
                    $cDa->execute([':uid' => $candUid, ':rid' => $roomId]);
                    $hasConflict = false;
                    foreach ($cDa->fetchAll(PDO::FETCH_ASSOC) as $otherRow) {
                        $os = strtotime((string) $otherRow['target_time_slot']);
                        if ($os === false) {
                            continue;
                        }
                        $oe = $os + max(60, (int) $otherRow['duration_minutes']) * 60;
                        if ($myS < ($oe + 1800) && $myE > ($os - 1800)) {
                            $hasConflict = true;
                            break;
                        }
                    }
                    if ($hasConflict) {
                        continue;
                    }
                }
                $candidateId = $candUid;
                break;
            }
            if ($candidateId > 0) {
                $rotatedDmId = $candidateId;
                $rotatedAssignmentId = $this->dmRepo->assignToRoom(
                    $roomId,
                    $candidateId,
                    'Tự động xoay tua do Quản trò trước từ chối ca',
                    (int) $auth['id']
                );
                $updSess = $this->pdo->prepare(
                    "UPDATE `sessions` SET `dm_assignment_id` = :daid, `updated_at` = NOW()
                     WHERE `game_room_id` = :rid AND `status` = 'SCHEDULED'"
                );
                $updSess->bindValue(':daid', $rotatedAssignmentId, PDO::PARAM_INT);
                $updSess->bindValue(':rid', $roomId, PDO::PARAM_INT);
                $updSess->execute();

                $this->notiRepo->createNotification(
                    'Phân công ca trực mới (Xoay tua tự động)',
                    "Bạn được hệ thống tự động xoay tua phân công phụ trách phòng #{$roomId} ('{$assignment['room_title']}'). Vui lòng xác nhận trong vòng 2 giờ.",
                    'DM_ASSIGNMENT',
                    (int) $auth['id'],
                    [$candidateId],
                    'ROOM',
                    $roomId
                );
            }

            // Đưa phòng về trạng thái SCHEDULED (chờ DM mới xác nhận hoặc chờ Admin điều phối lại; tuyệt đối không giữ CONFIRMED)
            $this->pdo->prepare(
                "UPDATE `game_rooms`
                 SET `status` = 'SCHEDULED', `updated_at` = NOW()
                 WHERE `id` = :rid AND `status` IN ('CONFIRMED', 'FORMING')"
            )->execute([':rid' => $roomId]);
        }

        // Audit log & thông báo Admin
        $this->auditService->log((int) $auth['id'], 'REJECT_DM_ASSIGNMENT', 'DM_ASSIGNMENT', $assignmentId, [
            'game_room_id' => $assignment['game_room_id'] ?? null,
            'reason' => $reason,
        ]);

        $adminStmt = $this->pdo->query('SELECT u.`id` FROM `users` u JOIN `roles` r ON r.`id` = u.`role_id` WHERE r.`code` = \'ADMIN\' AND u.`status` = \'ACTIVE\'');
        $adminIds = array_map('intval', $adminStmt->fetchAll(PDO::FETCH_COLUMN));

        $rotatedDmName = null;
        if ($rotatedDmId !== null) {
            $rdStmt = $this->pdo->prepare('SELECT `full_name` FROM `users` WHERE `id` = :id LIMIT 1');
            $rdStmt->execute([':id' => $rotatedDmId]);
            $rotatedDmName = (string) ($rdStmt->fetchColumn() ?: ('DM #' . $rotatedDmId));
        }

        if (!empty($adminIds)) {
            $dmName = $auth['full_name'] ?? $auth['name'] ?? 'Quản trò';
            $followUp = $rotatedDmId !== null
                ? "Hệ thống đã tự động xoay tua sang Quản trò {$rotatedDmName} (đang chờ xác nhận)."
                : 'CẦN ADMIN ĐIỀU PHỐI THỦ CÔNG: Hiện không còn Quản trò rảnh để xoay tua tự động.';
            $this->notiRepo->createNotification(
                $rotatedDmId !== null ? 'Quản trò từ chối ca (Đã xoay tua)' : '[Khẩn] Quản trò từ chối ca — Cần gán lại',
                "Quản trò {$dmName} đã TỪ CHỐI ca phòng #{$roomId} ('{$assignment['room_title']}'). Lý do: {$reason}. {$followUp}",
                'ADMIN',
                (int) $auth['id'],
                $adminIds,
                'ROOM',
                $roomId
            );
        }

        $updated = $this->dmRepo->findAssignmentById($assignmentId);

        return Response::success([
            'assignment' => $updated,
            'rotated_assignment_id' => $rotatedAssignmentId,
            'rotated_dm_id' => $rotatedDmId,
            'rotated_dm_name' => $rotatedDmName,
            'needs_admin_action' => $rotatedDmId === null,
        ], $rotatedDmId !== null
            ? "Đã từ chối ca trực và tự động xoay tua sang Quản trò {$rotatedDmName}"
            : 'Đã từ chối ca trực và gửi cảnh báo cho Quản trị viên điều phối lại');
    }
}
