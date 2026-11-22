<?php

declare(strict_types=1);

namespace App\Features\VenueScheduling\Controllers;

use App\Core\Middleware\AuthMiddleware;
use App\Core\Request;
use App\Core\Response;
use App\Features\Pricing\Services\PricingService;
use App\Infrastructure\Database\DatabaseConnection;
use PDO;

class SchedulingController
{
    protected PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DatabaseConnection::getInstance();

        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }
    }

    /**
     * GET /api/v1/venues
     * Lấy danh sách chi nhánh và phòng chơi vật lý
     */
    public function venues(Request $request): Response
    {
        $stmt = $this->pdo->query('SELECT `id`, `name`, `address`, `phone`, `description`, `status` FROM `venues` WHERE `status` = \'ACTIVE\'');
        $venues = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($venues as &$v) {
            $rStmt = $this->pdo->prepare('SELECT `id`, `venue_id`, `name`, `capacity`, `description`, `status` FROM `physical_rooms` WHERE `venue_id` = :vid AND `status` = \'ACTIVE\'');
            $rStmt->bindValue(':vid', (int) $v['id'], PDO::PARAM_INT);
            $rStmt->execute();
            $v['rooms'] = $rStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        return Response::success([
            'venues' => $venues,
        ], 'Danh sách chi nhánh và phòng chơi vật lý');
    }

    /**
     * POST /api/v1/rooms/{id}/proposals
     * Tạo đề xuất mốc thời gian chơi
     */
    public function propose(Request $request, string $id): Response
    {
        $userId = $this->resolveCurrentUserId($request);
        if ($userId === null) {
            return Response::fail('Bạn cần đăng nhập để đề xuất lịch chơi', 'UNAUTHORIZED', 401);
        }

        $roomId = (int) $id;
        $body = $request->getBodyParams();

        $startAt = trim((string) ($body['proposed_start_at'] ?? ''));
        $endAt = trim((string) ($body['proposed_end_at'] ?? ''));

        if ($startAt === '' || $endAt === '') {
            return Response::fail('Vui lòng chọn thời gian bắt đầu và kết thúc', 'VALIDATION_ERROR', 422);
        }

        if (strtotime($startAt) >= strtotime($endAt)) {
            return Response::fail('Thời gian bắt đầu phải trước thời gian kết thúc', 'VALIDATION_ERROR', 422);
        }

        // Kiểm tra xem user có phải là thành viên active trong phòng không
        $partStmt = $this->pdo->prepare(
            'SELECT `id` FROM `room_participants`
             WHERE `game_room_id` = :room_id AND `user_id` = :user_id
               AND `status` IN (\'PENDING_DEPOSIT\', \'DEPOSITED\', \'LEAVE_REQUESTED\')
             LIMIT 1'
        );
        $partStmt->bindValue(':room_id', $roomId, PDO::PARAM_INT);
        $partStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $partStmt->execute();
        $participantId = $partStmt->fetchColumn();

        if ($participantId === false) {
            return Response::fail('Chỉ thành viên trong phòng mới có thể đề xuất lịch', 'FORBIDDEN', 403);
        }

        $this->pdo->beginTransaction();
        try {
            // Kiểm tra xem phòng có đề xuất nào đang mở không (PROPOSED)
            $chkProp = $this->pdo->prepare('SELECT `id` FROM `schedule_proposals` WHERE `game_room_id` = :room_id AND `status` = \'PROPOSED\' LIMIT 1 FOR UPDATE');
            $chkProp->bindValue(':room_id', $roomId, PDO::PARAM_INT);
            $chkProp->execute();
            $existingProp = $chkProp->fetchColumn();

            if ($existingProp !== false) {
                // Hủy đề xuất cũ nếu có
                $cancelStmt = $this->pdo->prepare('UPDATE `schedule_proposals` SET `status` = \'CANCELLED\' WHERE `id` = :id');
                $cancelStmt->bindValue(':id', (int) $existingProp, PDO::PARAM_INT);
                $cancelStmt->execute();
            }

            $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

            $insProp = $this->pdo->prepare(
                'INSERT INTO `schedule_proposals` (`game_room_id`, `proposed_by`, `proposed_start_at`, `proposed_end_at`, `status`, `expires_at`, `created_at`)
                 VALUES (:room_id, :user_id, :start_at, :end_at, \'PROPOSED\', :expires_at, NOW())'
            );
            $insProp->bindValue(':room_id', $roomId, PDO::PARAM_INT);
            $insProp->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $insProp->bindValue(':start_at', $startAt, PDO::PARAM_STR);
            $insProp->bindValue(':end_at', $endAt, PDO::PARAM_STR);
            $insProp->bindValue(':expires_at', $expiresAt, PDO::PARAM_STR);
            $insProp->execute();

            $proposalId = (int) $this->pdo->lastInsertId();

            // Tự động ghi nhận vote ACCEPTED của người đề xuất
            $insVote = $this->pdo->prepare(
                'INSERT INTO `schedule_responses` (`game_room_id`, `schedule_proposal_id`, `room_participant_id`, `response`, `responded_at`, `created_at`)
                 VALUES (:room_id, :proposal_id, :part_id, \'ACCEPTED\', NOW(), NOW())'
            );
            $insVote->bindValue(':room_id', $roomId, PDO::PARAM_INT);
            $insVote->bindValue(':proposal_id', $proposalId, PDO::PARAM_INT);
            $insVote->bindValue(':part_id', (int) $participantId, PDO::PARAM_INT);
            $insVote->execute();

            // DEC-01 (Option B): Tạo hóa đơn cọc (50.000 VNĐ) cho các thành viên active khi phòng bước vào giai đoạn biểu quyết lịch
            $depositDeadline = date('Y-m-d H:i:s', time() + (2 * 3600)); // 2 giờ theo V16 baseline

            $eligiblePartsStmt = $this->pdo->prepare(
                'SELECT `id`, `user_id`, `deposit_deadline_at`
                 FROM `room_participants`
                 WHERE `game_room_id` = :room_id
                   AND `status` = \'PENDING_DEPOSIT\'
                 FOR UPDATE'
            );
            $eligiblePartsStmt->bindValue(':room_id', $roomId, PDO::PARAM_INT);
            $eligiblePartsStmt->execute();
            $eligibleParticipants = $eligiblePartsStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($eligibleParticipants as $elPart) {
                $elPartId = (int) $elPart['id'];

                // Kiểm tra xem đã có hóa đơn DEPOSIT chưa để tránh tạo trùng lặp
                $chkPay = $this->pdo->prepare(
                    'SELECT `id` FROM `player_payments`
                     WHERE `room_participant_id` = :part_id AND `payment_type` = \'DEPOSIT\'
                     LIMIT 1 FOR UPDATE'
                );
                $chkPay->bindValue(':part_id', $elPartId, PDO::PARAM_INT);
                $chkPay->execute();
                $existingPayId = $chkPay->fetchColumn();

                if ($existingPayId === false) {
                    $insPay = $this->pdo->prepare(
                        'INSERT INTO `player_payments` (`room_participant_id`, `payment_type`, `amount`, `paid_amount`, `status`, `deadline_at`, `created_at`, `updated_at`)
                         VALUES (:part_id, \'DEPOSIT\', 50000, 0, \'PENDING\', :deadline_at, NOW(), NOW())'
                    );
                    $insPay->bindValue(':part_id', $elPartId, PDO::PARAM_INT);
                    $insPay->bindValue(':deadline_at', $depositDeadline, PDO::PARAM_STR);
                    $insPay->execute();
                }

                // Gán deposit_deadline_at cho participant nếu chưa có
                if (empty($elPart['deposit_deadline_at'])) {
                    $upPartDl = $this->pdo->prepare(
                        'UPDATE `room_participants`
                         SET `deposit_deadline_at` = :deadline_at, `updated_at` = NOW()
                         WHERE `id` = :part_id AND `deposit_deadline_at` IS NULL'
                    );
                    $upPartDl->bindValue(':deadline_at', $depositDeadline, PDO::PARAM_STR);
                    $upPartDl->bindValue(':part_id', $elPartId, PDO::PARAM_INT);
                    $upPartDl->execute();
                }
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return Response::success([
            'proposal_id' => $proposalId,
            'game_room_id' => $roomId,
            'proposed_start_at' => $startAt,
            'proposed_end_at' => $endAt,
            'status' => 'PROPOSED',
            'expires_at' => $expiresAt,
        ], 'Đã tạo đề xuất lịch chơi và mở biểu quyết cho thành viên phòng', null, 201);
    }

    /**
     * GET /api/v1/proposals/{id}
     * Xem chi tiết đề xuất lịch và các phiếu bầu
     */
    public function showProposal(Request $request, string $id): Response
    {
        $proposalId = (int) $id;
        $stmt = $this->pdo->prepare(
            'SELECT sp.`id`, sp.`game_room_id`, sp.`proposed_by`, sp.`proposed_start_at`, sp.`proposed_end_at`,
                    sp.`status`, sp.`expires_at`, sp.`confirmed_at`, sp.`rejected_at`, sp.`created_at`,
                    u.`full_name` AS `proposer_name`
             FROM `schedule_proposals` sp
             INNER JOIN `users` u ON u.`id` = sp.`proposed_by`
             WHERE sp.`id` = :id
             LIMIT 1'
        );
        $stmt->bindValue(':id', $proposalId, PDO::PARAM_INT);
        $stmt->execute();
        $proposal = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($proposal === false) {
            return Response::fail('Không tìm thấy đề xuất lịch chơi', 'PROPOSAL_NOT_FOUND', 404);
        }

        // Lấy danh sách phiếu bầu
        $vStmt = $this->pdo->prepare(
            'SELECT sr.`id`, sr.`room_participant_id`, sr.`response`, sr.`responded_at`,
                    u.`full_name` AS `participant_name`
             FROM `schedule_responses` sr
             INNER JOIN `room_participants` rp ON rp.`id` = sr.`room_participant_id`
             INNER JOIN `users` u ON u.`id` = rp.`user_id`
             WHERE sr.`schedule_proposal_id` = :pid'
        );
        $vStmt->bindValue(':pid', $proposalId, PDO::PARAM_INT);
        $vStmt->execute();
        $proposal['votes'] = $vStmt->fetchAll(PDO::FETCH_ASSOC);

        return Response::success([
            'proposal' => $proposal,
        ], 'Chi tiết đề xuất lịch chơi');
    }

    /**
     * POST /api/v1/proposals/{id}/vote
     * Thành viên biểu quyết đề xuất lịch
     */
    public function vote(Request $request, string $id): Response
    {
        $userId = $this->resolveCurrentUserId($request);
        if ($userId === null) {
            return Response::fail('Bạn cần đăng nhập để biểu quyết', 'UNAUTHORIZED', 401);
        }

        $proposalId = (int) $id;
        $body = $request->getBodyParams();
        $responseVote = strtoupper(trim((string) ($body['response'] ?? 'ACCEPTED')));

        if (!in_array($responseVote, ['ACCEPTED', 'REJECTED'], true)) {
            return Response::fail('Lựa chọn biểu quyết không hợp lệ (phải là ACCEPTED hoặc REJECTED)', 'VALIDATION_ERROR', 422);
        }

        // Lấy thông tin proposal
        $pStmt = $this->pdo->prepare('SELECT `id`, `game_room_id`, `status`, `proposed_start_at`, `proposed_end_at` FROM `schedule_proposals` WHERE `id` = :id LIMIT 1');
        $pStmt->bindValue(':id', $proposalId, PDO::PARAM_INT);
        $pStmt->execute();
        $proposal = $pStmt->fetch(PDO::FETCH_ASSOC);

        if ($proposal === false) {
            return Response::fail('Đề xuất lịch chơi không tồn tại', 'PROPOSAL_NOT_FOUND', 404);
        }

        if ($proposal['status'] !== 'PROPOSED') {
            return Response::fail('Đề xuất lịch này đã kết thúc hoặc bị hủy (Trạng thái: ' . $proposal['status'] . ')', 'PROPOSAL_CLOSED', 400);
        }

        $roomId = (int) $proposal['game_room_id'];

        // Lấy participant của user trong phòng
        $partStmt = $this->pdo->prepare(
            'SELECT `id` FROM `room_participants`
             WHERE `game_room_id` = :room_id AND `user_id` = :user_id
               AND `status` IN (\'PENDING_DEPOSIT\', \'DEPOSITED\', \'LEAVE_REQUESTED\')
             LIMIT 1'
        );
        $partStmt->bindValue(':room_id', $roomId, PDO::PARAM_INT);
        $partStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $partStmt->execute();
        $participantId = $partStmt->fetchColumn();

        if ($participantId === false) {
            return Response::fail('Bạn không phải là thành viên hợp lệ trong phòng để biểu quyết', 'FORBIDDEN', 403);
        }

        $participantId = (int) $participantId;

        // Ghi nhận hoặc cập nhật vote
        $voteStmt = $this->pdo->prepare(
            'INSERT INTO `schedule_responses` (`game_room_id`, `schedule_proposal_id`, `room_participant_id`, `response`, `responded_at`, `created_at`)
             VALUES (:room_id, :proposal_id, :part_id, :response, NOW(), NOW())
             ON DUPLICATE KEY UPDATE `response` = VALUES(`response`), `responded_at` = NOW()'
        );
        $voteStmt->bindValue(':room_id', $roomId, PDO::PARAM_INT);
        $voteStmt->bindValue(':proposal_id', $proposalId, PDO::PARAM_INT);
        $voteStmt->bindValue(':part_id', $participantId, PDO::PARAM_INT);
        $voteStmt->bindValue(':response', $responseVote, PDO::PARAM_STR);
        $voteStmt->execute();

        // Kiểm tra State Machine:
        if ($responseVote === 'REJECTED') {
            // Có người từ chối -> Đề xuất bị bác bỏ
            $rejStmt = $this->pdo->prepare('UPDATE `schedule_proposals` SET `status` = \'REJECTED\', `rejected_at` = NOW() WHERE `id` = :id');
            $rejStmt->bindValue(':id', $proposalId, PDO::PARAM_INT);
            $rejStmt->execute();

            return Response::success([
                'proposal_status' => 'REJECTED',
            ], 'Bạn đã từ chối mốc thời gian này. Đề xuất lịch đã được đánh dấu REJECTED để thành viên đề xuất mốc khác.');
        }

        // Kiểm tra xem tất cả thành viên trong phòng đã vote ACCEPTED chưa
        $allPartsStmt = $this->pdo->prepare(
            'SELECT `id` FROM `room_participants`
             WHERE `game_room_id` = :room_id
               AND `status` IN (\'PENDING_DEPOSIT\', \'DEPOSITED\', \'LEAVE_REQUESTED\')'
        );
        $allPartsStmt->bindValue(':room_id', $roomId, PDO::PARAM_INT);
        $allPartsStmt->execute();
        $activeParticipantIds = $allPartsStmt->fetchAll(PDO::FETCH_COLUMN);

        $acceptStmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM `schedule_responses`
             WHERE `schedule_proposal_id` = :pid AND `response` = \'ACCEPTED\''
        );
        $acceptStmt->bindValue(':pid', $proposalId, PDO::PARAM_INT);
        $acceptStmt->execute();
        $acceptedCount = (int) $acceptStmt->fetchColumn();

        $sessionCreated = false;
        $sessionId = null;

        // Nếu tất cả active participants đều ACCEPTED -> Chốt thành công
        if ($acceptedCount >= count($activeParticipantIds) && count($activeParticipantIds) > 0) {
            $this->pdo->beginTransaction();
            try {
                // DEF-22: Khóa phòng chơi và đề xuất lịch biểu để bảo đảm concurrency
                $rLock = $this->pdo->prepare('SELECT `id`, `game_id`, `status` FROM `game_rooms` WHERE `id` = :room_id FOR UPDATE');
                $rLock->bindValue(':room_id', $roomId, PDO::PARAM_INT);
                $rLock->execute();
                $lockedRoom = $rLock->fetch(PDO::FETCH_ASSOC);

                $pLock = $this->pdo->prepare('SELECT `id`, `status`, `proposed_start_at`, `proposed_end_at` FROM `schedule_proposals` WHERE `id` = :id FOR UPDATE');
                $pLock->bindValue(':id', $proposalId, PDO::PARAM_INT);
                $pLock->execute();
                $lockedProp = $pLock->fetch(PDO::FETCH_ASSOC);

                // DEF-22: Tính toán và lưu snapshot bảng giá phòng & từng thành viên TRƯỚC KHI chuyển sang SCHEDULED
                $pricingService = new PricingService($this->pdo);
                $pricingService->calculateAndSnapshot($roomId, (string) $proposal['proposed_start_at']);

                $confStmt = $this->pdo->prepare('UPDATE `schedule_proposals` SET `status` = \'CONFIRMED\', `confirmed_at` = NOW() WHERE `id` = :id');
                $confStmt->bindValue(':id', $proposalId, PDO::PARAM_INT);
                $confStmt->execute();

                // DEF-01: Đồng bộ trạng thái game_rooms sang SCHEDULED
                $upRoom = $this->pdo->prepare('UPDATE `game_rooms` SET `status` = \'SCHEDULED\', `updated_at` = NOW() WHERE `id` = :room_id');
                $upRoom->bindValue(':room_id', $roomId, PDO::PARAM_INT);
                $upRoom->execute();

                // DEF-25: Khởi tạo hoặc lấy DM assignment cho game room
                // Ưu tiên lấy assignment đã ACCEPTED gần nhất
                $dmStmt = $this->pdo->prepare('SELECT `id` FROM `dm_assignments` WHERE `game_room_id` = :room_id AND `status` = \'ACCEPTED\' ORDER BY `id` DESC LIMIT 1');
                $dmStmt->bindValue(':room_id', $roomId, PDO::PARAM_INT);
                $dmStmt->execute();
                $dmAssignId = $dmStmt->fetchColumn();

                if ($dmAssignId === false) {
                    // Kiểm tra xem phòng có assignment nào đang PENDING/REJECTED/REVOKED không
                    $anyDmStmt = $this->pdo->prepare('SELECT `id`, `status` FROM `dm_assignments` WHERE `game_room_id` = :room_id ORDER BY `id` DESC LIMIT 1');
                    $anyDmStmt->bindValue(':room_id', $roomId, PDO::PARAM_INT);
                    $anyDmStmt->execute();
                    $anyDm = $anyDmStmt->fetch(PDO::FETCH_ASSOC);

                    if ($anyDm === false) {
                        // Chưa có assignment nào -> Tạo DM assignment mặc định với DM user 2 (Host Hoàng Nam) và đặt ACCEPTED
                        $insDm = $this->pdo->prepare('INSERT INTO `dm_assignments` (`game_room_id`, `dm_user_id`, `assigned_by`, `status`, `assigned_at`, `created_at`, `updated_at`) VALUES (:room_id, 2, 1, \'ACCEPTED\', NOW(), NOW(), NOW())');
                        $insDm->bindValue(':room_id', $roomId, PDO::PARAM_INT);
                        $insDm->execute();
                        $dmAssignId = (int) $this->pdo->lastInsertId();
                    } elseif ($anyDm['status'] === 'PENDING') {
                        // Giữ nguyên trạng thái PENDING (tuyệt đối không auto-accept DM chưa xác nhận)
                        // nhưng vẫn liên kết ID assignment vào Session để phòng SCHEDULED không bị mồ côi
                        $dmAssignId = (int) $anyDm['id'];
                    } else {
                        // Assignment cũ đã bị REJECTED hoặc REVOKED -> Tạo assignment PENDING mới cho DM 2
                        $insDm = $this->pdo->prepare('INSERT INTO `dm_assignments` (`game_room_id`, `dm_user_id`, `assigned_by`, `status`, `reason`, `assigned_at`, `created_at`, `updated_at`) VALUES (:room_id, 2, 1, \'PENDING\', \'Phân công tự động khi chốt lịch (chờ quản trò xác nhận)\', NOW(), NOW(), NOW())');
                        $insDm->bindValue(':room_id', $roomId, PDO::PARAM_INT);
                        $insDm->execute();
                        $dmAssignId = (int) $this->pdo->lastInsertId();
                    }
                } else {
                    $dmAssignId = (int) $dmAssignId;
                }

                // DEF-25: Tạo buổi chơi chính thức (Session) bảo đảm phòng SCHEDULED luôn có Session liên kết
                $sessCheck = $this->pdo->prepare('SELECT `id` FROM `sessions` WHERE `game_room_id` = :room_id LIMIT 1');
                $sessCheck->bindValue(':room_id', $roomId, PDO::PARAM_INT);
                $sessCheck->execute();
                $existingSess = $sessCheck->fetchColumn();

                if ($existingSess === false) {
                    $insSess = $this->pdo->prepare(
                        'INSERT INTO `sessions` (`game_room_id`, `schedule_proposal_id`, `dm_assignment_id`, `venue_id`, `physical_room_id`, `start_at`, `end_at`, `status`, `created_at`, `updated_at`)
                         VALUES (:room_id, :proposal_id, :dm_id, 1, 1, :start_at, :end_at, \'SCHEDULED\', NOW(), NOW())'
                    );
                    $insSess->bindValue(':room_id', $roomId, PDO::PARAM_INT);
                    $insSess->bindValue(':proposal_id', $proposalId, PDO::PARAM_INT);
                    $insSess->bindValue(':dm_id', $dmAssignId, PDO::PARAM_INT);
                    $insSess->bindValue(':start_at', $proposal['proposed_start_at'], PDO::PARAM_STR);
                    $insSess->bindValue(':end_at', $proposal['proposed_end_at'], PDO::PARAM_STR);
                    $insSess->execute();
                    $sessionId = (int) $this->pdo->lastInsertId();
                    $sessionCreated = true;
                } else {
                    $sessionId = (int) $existingSess;
                    $sessionCreated = true;
                }

                $this->pdo->commit();
            } catch (\Throwable $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                throw $e;
            }
        }

        return Response::success([
            'proposal_id' => $proposalId,
            'accepted_votes' => $acceptedCount,
            'total_required' => count($activeParticipantIds),
            'proposal_confirmed' => $sessionCreated,
            'session_id' => $sessionId,
        ], 'Đã ghi nhận biểu quyết của bạn');
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
}
