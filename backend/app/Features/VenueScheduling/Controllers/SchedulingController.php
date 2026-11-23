<?php

declare(strict_types=1);

namespace App\Features\VenueScheduling\Controllers;

use App\Core\Middleware\AuthMiddleware;
use App\Core\Request;
use App\Core\Response;
use App\Features\PaymentRefund\Repositories\PaymentRepository;
use App\Features\Pricing\Services\PricingService;
use App\Features\VenueScheduling\Repositories\SchedulingRepository;
use App\Infrastructure\Database\DatabaseConnection;
use PDO;

class SchedulingController
{
    protected PDO $pdo;
    protected SchedulingRepository $schedRepo;
    protected PaymentRepository $paymentRepo;

    public function __construct(
        ?PDO $pdo = null,
        ?SchedulingRepository $schedRepo = null,
        ?PaymentRepository $paymentRepo = null
    ) {
        $this->pdo = $pdo ?? DatabaseConnection::getInstance();
        $this->schedRepo = $schedRepo ?? new SchedulingRepository($this->pdo);
        $this->paymentRepo = $paymentRepo ?? new PaymentRepository($this->pdo);

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

        if (strtotime($startAt) <= time()) {
            return Response::fail('Thời gian đề xuất chơi phải ở trong tương lai', 'VALIDATION_ERROR', 422);
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
            // Hủy đề xuất cũ đang mở (nếu có)
            $cancelStmt = $this->pdo->prepare('UPDATE `schedule_proposals` SET `status` = \'CANCELLED\' WHERE `game_room_id` = :room_id AND `status` = \'PROPOSED\'');
            $cancelStmt->bindValue(':room_id', $roomId, PDO::PARAM_INT);
            $cancelStmt->execute();

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

            $this->pdo->commit();

            return Response::success([
                'proposal_id' => $proposalId,
                'game_room_id' => $roomId,
                'proposed_start_at' => $startAt,
                'proposed_end_at' => $endAt,
                'status' => 'PROPOSED',
                'expires_at' => $expiresAt,
            ], 'Đã tạo đề xuất lịch chơi và mở biểu quyết cho thành viên phòng', null, 201);
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
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
     * Thành viên biểu quyết đề xuất lịch (bọc transaction, tìm phòng vật lý & DM động, sinh hóa đơn cọc 50k)
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

        $this->pdo->beginTransaction();
        try {
            // Khóa proposal FOR UPDATE
            $pStmt = $this->pdo->prepare(
                'SELECT `id`, `game_room_id`, `status`, `proposed_start_at`, `proposed_end_at`, `expires_at`
                 FROM `schedule_proposals`
                 WHERE `id` = :id
                 LIMIT 1 FOR UPDATE'
            );
            $pStmt->bindValue(':id', $proposalId, PDO::PARAM_INT);
            $pStmt->execute();
            $proposal = $pStmt->fetch(PDO::FETCH_ASSOC);

            if ($proposal === false) {
                $this->pdo->rollBack();
                return Response::fail('Đề xuất lịch chơi không tồn tại', 'PROPOSAL_NOT_FOUND', 404);
            }

            if ($proposal['status'] !== 'PROPOSED') {
                $this->pdo->rollBack();
                return Response::fail('Đề xuất lịch này đã kết thúc hoặc bị hủy (Trạng thái: ' . $proposal['status'] . ')', 'PROPOSAL_CLOSED', 400);
            }

            // Kiểm tra hạn biểu quyết
            if (!empty($proposal['expires_at']) && strtotime((string) $proposal['expires_at']) < time()) {
                $expStmt = $this->pdo->prepare('UPDATE `schedule_proposals` SET `status` = \'EXPIRED\' WHERE `id` = :id');
                $expStmt->bindValue(':id', $proposalId, PDO::PARAM_INT);
                $expStmt->execute();
                $this->pdo->commit();
                return Response::fail('Đề xuất lịch chơi này đã hết hạn biểu quyết', 'PROPOSAL_EXPIRED', 400);
            }

            $roomId = (int) $proposal['game_room_id'];

            // Khóa game_room FOR UPDATE
            $roomLock = $this->pdo->prepare('SELECT `id`, `status` FROM `game_rooms` WHERE `id` = :room_id FOR UPDATE');
            $roomLock->bindValue(':room_id', $roomId, PDO::PARAM_INT);
            $roomLock->execute();

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
                $this->pdo->rollBack();
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

            // Nếu REJECTED -> bác bỏ proposal ngay
            if ($responseVote === 'REJECTED') {
                $rejStmt = $this->pdo->prepare('UPDATE `schedule_proposals` SET `status` = \'REJECTED\', `rejected_at` = NOW() WHERE `id` = :id');
                $rejStmt->bindValue(':id', $proposalId, PDO::PARAM_INT);
                $rejStmt->execute();
                $this->pdo->commit();

                return Response::success([
                    'proposal_status' => 'REJECTED',
                ], 'Bạn đã từ chối mốc thời gian này. Đề xuất lịch đã được đánh dấu REJECTED để thành viên đề xuất mốc khác.');
            }

            // Đếm số active participants và số phiếu ACCEPTED
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
            $assignedPhysicalRoomId = null;
            $assignedVenueId = null;

            // Nếu 100% thành viên ACCEPTED -> Tìm phòng trống + DM rảnh + Chốt session + Sinh hóa đơn cọc
            if ($acceptedCount >= count($activeParticipantIds) && count($activeParticipantIds) > 0) {
                $startAt = (string) $proposal['proposed_start_at'];
                $endAt = (string) $proposal['proposed_end_at'];

                // Kiểm tra đã có session cho phòng này chưa
                $sessCheck = $this->pdo->prepare('SELECT `id`, `venue_id`, `physical_room_id` FROM `sessions` WHERE `game_room_id` = :room_id LIMIT 1');
                $sessCheck->bindValue(':room_id', $roomId, PDO::PARAM_INT);
                $sessCheck->execute();
                $existingSess = $sessCheck->fetch(PDO::FETCH_ASSOC);

                if ($existingSess === false) {
                    // Tìm phòng vật lý trống đủ sức chứa (có đệm 30 phút dọn phòng)
                    $availableRoom = $this->schedRepo->findAvailablePhysicalRoom(
                        count($activeParticipantIds),
                        $startAt,
                        $endAt,
                        30
                    );

                    if ($availableRoom === null) {
                        $this->pdo->rollBack();
                        return Response::fail(
                            'Không còn phòng vật lý trống đủ sức chứa trong khung giờ này. Vui lòng đề xuất khung giờ khác.',
                            'PHYSICAL_ROOM_CONFLICT',
                            409
                        );
                    }

                    $assignedVenueId = (int) $availableRoom['venue_id'];
                    $assignedPhysicalRoomId = (int) $availableRoom['id'];

                    // Lấy hoặc phân công DM rảnh động
                    $dmStmt = $this->pdo->prepare(
                        'SELECT `id` FROM `dm_assignments`
                         WHERE `game_room_id` = :room_id AND `status` IN (\'PENDING\', \'ACCEPTED\')
                         ORDER BY `id` DESC LIMIT 1'
                    );
                    $dmStmt->bindValue(':room_id', $roomId, PDO::PARAM_INT);
                    $dmStmt->execute();
                    $dmAssignId = $dmStmt->fetchColumn();

                    if ($dmAssignId === false) {
                        $dmUserId = $this->schedRepo->findAvailableDmUserId($startAt, $endAt, 30);
                        if ($dmUserId === null) {
                            $this->pdo->rollBack();
                            return Response::fail('Hiện chưa có Quản trò (DM) khả dụng trong hệ thống', 'DM_UNAVAILABLE', 409);
                        }

                        $insDm = $this->pdo->prepare(
                            'INSERT INTO `dm_assignments` (`game_room_id`, `dm_user_id`, `assigned_by`, `status`, `assigned_at`, `created_at`, `updated_at`)
                             VALUES (:room_id, :dm_user_id, :assigned_by, \'PENDING\', NOW(), NOW(), NOW())'
                        );
                        $insDm->bindValue(':room_id', $roomId, PDO::PARAM_INT);
                        $insDm->bindValue(':dm_user_id', $dmUserId, PDO::PARAM_INT);
                        $insDm->bindValue(':assigned_by', $userId, PDO::PARAM_INT);
                        $insDm->execute();
                        $dmAssignId = (int) $this->pdo->lastInsertId();
                    } else {
                        $dmAssignId = (int) $dmAssignId;
                    }

                    // Đánh dấu proposal CONFIRMED
                    $confStmt = $this->pdo->prepare('UPDATE `schedule_proposals` SET `status` = \'CONFIRMED\', `confirmed_at` = NOW() WHERE `id` = :id');
                    $confStmt->bindValue(':id', $proposalId, PDO::PARAM_INT);
                    $confStmt->execute();

                    // Tạo Session chính thức
                    $insSess = $this->pdo->prepare(
                        'INSERT INTO `sessions` (`game_room_id`, `schedule_proposal_id`, `dm_assignment_id`, `venue_id`, `physical_room_id`, `start_at`, `end_at`, `status`, `created_at`, `updated_at`)
                         VALUES (:room_id, :proposal_id, :dm_id, :venue_id, :physical_room_id, :start_at, :end_at, \'SCHEDULED\', NOW(), NOW())'
                    );
                    $insSess->bindValue(':room_id', $roomId, PDO::PARAM_INT);
                    $insSess->bindValue(':proposal_id', $proposalId, PDO::PARAM_INT);
                    $insSess->bindValue(':dm_id', $dmAssignId, PDO::PARAM_INT);
                    $insSess->bindValue(':venue_id', $assignedVenueId, PDO::PARAM_INT);
                    $insSess->bindValue(':physical_room_id', $assignedPhysicalRoomId, PDO::PARAM_INT);
                    $insSess->bindValue(':start_at', $startAt, PDO::PARAM_STR);
                    $insSess->bindValue(':end_at', $endAt, PDO::PARAM_STR);
                    $insSess->execute();
                    $sessionId = (int) $this->pdo->lastInsertId();
                    $sessionCreated = true;

                    // Cập nhật trạng thái phòng -> SCHEDULED
                    $upRoom = $this->pdo->prepare('UPDATE `game_rooms` SET `status` = \'SCHEDULED\', `updated_at` = NOW() WHERE `id` = :room_id AND `status` = \'FORMING\'');
                    $upRoom->bindValue(':room_id', $roomId, PDO::PARAM_INT);
                    $upRoom->execute();

                    // Tự động tạo hóa đơn cọc 50.000đ cho toàn bộ thành viên active
                    $this->paymentRepo->ensureDepositPaymentsForRoom($roomId, PricingService::DEPOSIT_PER_PERSON, 2);
                } else {
                    $confStmt = $this->pdo->prepare('UPDATE `schedule_proposals` SET `status` = \'CONFIRMED\', `confirmed_at` = NOW() WHERE `id` = :id');
                    $confStmt->bindValue(':id', $proposalId, PDO::PARAM_INT);
                    $confStmt->execute();
                    $sessionId = (int) $existingSess['id'];
                    $assignedVenueId = (int) $existingSess['venue_id'];
                    $assignedPhysicalRoomId = (int) $existingSess['physical_room_id'];
                }
            }

            $this->pdo->commit();

            return Response::success([
                'proposal_id' => $proposalId,
                'accepted_votes' => $acceptedCount,
                'total_required' => count($activeParticipantIds),
                'proposal_confirmed' => $sessionCreated,
                'session_id' => $sessionId,
                'venue_id' => $assignedVenueId,
                'physical_room_id' => $assignedPhysicalRoomId,
            ], 'Đã ghi nhận biểu quyết của bạn');
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
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