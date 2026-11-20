<?php

declare(strict_types=1);

namespace App\Features\GameRoom;

use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;

class GameRoomController
{
    private GameRoomService $service;

    public function __construct(?GameRoomService $service = null)
    {
        $this->service = $service ?? new GameRoomService();
    }

    public function index(Request $request): Response
    {
        $status = $request->query('status');
        $rooms = $this->service->listRooms(is_string($status) ? $status : null);

        return Response::json([
            'success' => true,
            'data' => $rooms,
            'serverTime' => date('c'),
        ]);
    }

    public function show(Request $request, string $id): Response
    {
        $roomId = $this->parsePositiveInt($id, 'id');

        return Response::json([
            'success' => true,
            'data' => $this->service->getRoomDetail($roomId),
            'serverTime' => date('c'),
        ]);
    }

    public function store(Request $request): Response
    {
        $auth = $this->requireAuth();
        $room = $this->service->createRoom($auth['user_id'], $request->getBodyParams());

        return Response::json([
            'success' => true,
            'message' => 'Tạo phòng chơi thành công',
            'data' => $room,
            'serverTime' => date('c'),
        ], 201);
    }

    public function join(Request $request, string $id): Response
    {
        $auth = $this->requireAuth();
        $roomId = $this->parsePositiveInt($id, 'id');
        $room = $this->service->joinRoom($roomId, $auth['user_id']);

        return Response::json([
            'success' => true,
            'message' => 'Tham gia phòng thành công',
            'data' => $room,
            'serverTime' => date('c'),
        ]);
    }

    public function leave(Request $request, string $id): Response
    {
        $auth = $this->requireAuth();
        $roomId = $this->parsePositiveInt($id, 'id');
        $room = $this->service->leaveRoom($roomId, $auth['user_id']);

        return Response::json([
            'success' => true,
            'message' => 'Rời phòng thành công',
            'data' => $room,
            'serverTime' => date('c'),
        ]);
    }

    public function assignDm(Request $request, string $id): Response
    {
        $this->requireRole(['ADMIN']);

        $roomId = $this->parsePositiveInt($id, 'id');
        $body = $request->getBodyParams();
        $dmUserId = $this->parsePositiveInt($body['dm_user_id'] ?? null, 'dm_user_id');
        $physicalRoomId = $this->parsePositiveInt($body['physical_room_id'] ?? null, 'physical_room_id');

        $room = $this->service->assignDmAndPhysicalRoom($roomId, $dmUserId, $physicalRoomId);

        return Response::json([
            'success' => true,
            'message' => 'Phân công DM và chốt phòng vật lý thành công',
            'data' => $room,
            'serverTime' => date('c'),
        ]);
    }

    public function deposit(Request $request, string $id): Response
    {
        $auth = $this->requireAuth();
        $roomId = $this->parsePositiveInt($id, 'id');
        $body = $request->getBodyParams();
        $method = (string) ($body['provider'] ?? 'VNPAY');

        $room = $this->service->deposit($roomId, $auth['user_id'], $method);

        return Response::json([
            'success' => true,
            'message' => 'Đặt cọc thành công',
            'data' => $room,
            'serverTime' => date('c'),
        ]);
    }

    public function cancel(Request $request, string $id): Response
    {
        $auth = $this->requireRole(['ADMIN', 'PLAYER']);
        $roomId = $this->parsePositiveInt($id, 'id');
        $body = $request->getBodyParams();
        $reason = isset($body['reason']) && is_string($body['reason']) ? trim($body['reason']) : null;

        $room = $this->service->cancelRoom($roomId, $auth['user_id'], $auth['role'], $reason);

        return Response::json([
            'success' => true,
            'message' => 'Hủy phòng thành công',
            'data' => $room,
            'serverTime' => date('c'),
        ]);
    }

    public function complete(Request $request, string $id): Response
    {
        $auth = $this->requireRole(['ADMIN', 'DM']);
        $roomId = $this->parsePositiveInt($id, 'id');

        $room = $this->service->completeRoom($roomId, $auth['user_id'], $auth['role']);

        return Response::json([
            'success' => true,
            'message' => 'Hoàn thành trận chơi thành công',
            'data' => $room,
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

        return [
            'user_id' => $userId,
            'role' => $role,
        ];
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