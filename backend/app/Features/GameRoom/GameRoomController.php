<?php

declare(strict_types=1);

namespace App\Features\GameRoom;

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
        return Response::json([
            'success' => true,
            'data' => $this->service->getRoomDetail((int) $id),
            'serverTime' => date('c'),
        ]);
    }

    public function store(Request $request): Response
    {
        $hostUserId = (int) ($_SESSION['user_id'] ?? $request->input('host_user_id', 1));
        $room = $this->service->createRoom($hostUserId, $request->getBodyParams());

        return Response::json([
            'success' => true,
            'message' => 'Tạo phòng chơi thành công',
            'data' => $room,
            'serverTime' => date('c'),
        ], 201);
    }

    public function join(Request $request, string $id): Response
    {
        $userId = (int) ($_SESSION['user_id'] ?? $request->input('user_id', 1));
        $room = $this->service->joinRoom((int) $id, $userId);

        return Response::json([
            'success' => true,
            'message' => 'Tham gia phòng thành công',
            'data' => $room,
            'serverTime' => date('c'),
        ]);
    }
    public function leave(Request $request, string $id): Response
    {
        $userId = (int) ($_SESSION['user_id'] ?? $request->input('user_id', 1));
        $room = $this->service->leaveRoom((int) $id, $userId);

        return Response::json([
            'success' => true,
            'message' => 'Rời phòng thành công',
            'data' => $room,
            'serverTime' => date('c'),
        ]);
    }

    public function assignDm(Request $request, string $id): Response
    {
        $body = $request->getBodyParams();
        $dmUserId = (int) ($body['dm_user_id'] ?? 1);
        $physicalRoomId = (int) ($body['physical_room_id'] ?? 1);

        $room = $this->service->assignDmAndPhysicalRoom((int) $id, $dmUserId, $physicalRoomId);

        return Response::json([
            'success' => true,
            'message' => 'Phân công DM và chốt phòng vật lý thành công',
            'data' => $room,
            'serverTime' => date('c'),
        ]);
    }

    public function deposit(Request $request, string $id): Response
    {
        $body = $request->getBodyParams();
        $userId = (int) ($_SESSION['user_id'] ?? $body['user_id'] ?? 1);
        $method = (string) ($body['provider'] ?? 'VNPAY');

        $room = $this->service->deposit((int) $id, $userId, $method);

        return Response::json([
            'success' => true,
            'message' => 'Đặt cọc thành công',
            'data' => $room,
            'serverTime' => date('c'),
        ]);
    }
}