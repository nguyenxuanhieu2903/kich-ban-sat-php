<?php

declare(strict_types=1);

namespace App\Features\GameRoom;

use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;

class RoomWorkflowController
{
    private RoomWorkflowService $service;

    public function __construct(?RoomWorkflowService $service = null)
    {
        $this->service = $service ?? new RoomWorkflowService();
    }

    public function expire(Request $request, string $id): Response
    {
        $auth = $this->requireRole(['ADMIN']);
        $roomId = $this->parsePositiveInt($id, 'id');
        $body = $request->getBodyParams();
        $force = !empty($body['force']);

        $room = $this->service->expireRoom($roomId, $auth['user_id'], $auth['role'], $force);

        return Response::json([
            'success' => true,
            'message' => 'Đã đánh dấu phòng hết hạn',
            'data' => $room,
            'serverTime' => date('c'),
        ]);
    }

    public function reopen(Request $request, string $id): Response
    {
        $auth = $this->requireRole(['PLAYER', 'ADMIN']);
        $roomId = $this->parsePositiveInt($id, 'id');

        $room = $this->service->reopenRoom($roomId, $auth['user_id'], $auth['role']);

        return Response::json([
            'success' => true,
            'message' => 'Mở lại phòng tuyển người thành công',
            'data' => $room,
            'serverTime' => date('c'),
        ]);
    }

    public function proposeDm(Request $request, string $id): Response
    {
        $auth = $this->requireRole(['ADMIN']);
        $roomId = $this->parsePositiveInt($id, 'id');
        $body = $request->getBodyParams();
        $dmUserId = $this->parsePositiveInt($body['dm_user_id'] ?? null, 'dm_user_id');

        $assignment = $this->service->proposeDmAssignment($roomId, $dmUserId, $auth['user_id']);

        return Response::json([
            'success' => true,
            'message' => 'Gửi phân công DM thành công',
            'data' => $assignment,
            'serverTime' => date('c'),
        ], 201);
    }

    public function respondDm(Request $request, string $id): Response
    {
        $auth = $this->requireRole(['DM']);
        $assignmentId = $this->parsePositiveInt($id, 'id');
        $body = $request->getBodyParams();
        $decision = (string) ($body['decision'] ?? '');
        $physicalRoomId = isset($body['physical_room_id']) ? $this->parsePositiveInt($body['physical_room_id'], 'physical_room_id') : null;
        $reason = isset($body['reason']) && is_string($body['reason']) ? $body['reason'] : null;

        $result = $this->service->respondDmAssignment($assignmentId, $auth['user_id'], $decision, $physicalRoomId, $reason);

        return Response::json([
            'success' => true,
            'message' => 'Phản hồi phân công DM thành công',
            'data' => $result,
            'serverTime' => date('c'),
        ]);
    }

    public function proposeSchedule(Request $request, string $id): Response
    {
        $auth = $this->requireRole(['PLAYER', 'ADMIN']);
        $roomId = $this->parsePositiveInt($id, 'id');
        $body = $request->getBodyParams();
        $newStartAt = (string) ($body['new_start_at'] ?? '');

        $proposal = $this->service->proposeSchedule($roomId, $auth['user_id'], $auth['role'], $newStartAt);

        return Response::json([
            'success' => true,
            'message' => 'Tạo đề xuất đổi lịch thành công',
            'data' => $proposal,
            'serverTime' => date('c'),
        ], 201);
    }

    public function respondSchedule(Request $request, string $id): Response
    {
        $auth = $this->requireRole(['PLAYER', 'ADMIN']);
        $proposalId = $this->parsePositiveInt($id, 'id');
        $body = $request->getBodyParams();
        $decision = (string) ($body['decision'] ?? '');

        $result = $this->service->respondScheduleProposal($proposalId, $auth['user_id'], $auth['role'], $decision);

        return Response::json([
            'success' => true,
            'message' => 'Xử lý đề xuất đổi lịch thành công',
            'data' => $result,
            'serverTime' => date('c'),
        ]);
    }

    /** @return array{user_id: int, role: string} */
    private function requireAuth(): array
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $role = strtoupper((string) ($_SESSION['role'] ?? ''));

        if ($userId <= 0 || $role === '') {
            throw new HttpException('Bạn cần đăng nhập để thực hiện thao tác này', 401);
        }

        return ['user_id' => $userId, 'role' => $role];
    }

    /**
     * @param string[] $allowedRoles
     * @return array{user_id: int, role: string}
     */
    private function requireRole(array $allowedRoles): array
    {
        $auth = $this->requireAuth();
        if (!in_array($auth['role'], $allowedRoles, true)) {
            throw new HttpException('Bạn không có quyền thực hiện thao tác này', 403);
        }

        return $auth;
    }

    private function parsePositiveInt(mixed $value, string $field): int
    {
        $parsed = filter_var($value, FILTER_VALIDATE_INT);
        if ($parsed === false || $parsed <= 0) {
            throw new HttpException("Tham số {$field} phải là số nguyên dương", 400);
        }

        return (int) $parsed;
    }
}