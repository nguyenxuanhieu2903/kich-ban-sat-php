<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Features\GameroomManagement\Services\RoomSchedulingService;
use App\Infrastructure\Config\Config;
use App\Infrastructure\Database\DatabaseConnection;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class RoomSchedulingServiceTest extends TestCase
{
    private RoomSchedulingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Config::load(dirname(__DIR__, 2));
        $pdo = DatabaseConnection::getInstance();
        $this->service = new RoomSchedulingService($pdo);
    }

    public function testThrowsValidationErrorForInvalidDateFormat(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('VALIDATION_ERROR:Thời gian bắt đầu không hợp lệ');

        $this->service->scheduleRoom(
            roomId: 999999,
            actorId: 1,
            actorRole: 'ADMIN',
            venueId: 1,
            physicalRoomId: 1,
            dmUserId: 2,
            startAtRaw: 'not-a-valid-date'
        );
    }

    public function testThrowsNotFoundForNonExistentRoom(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ROOM_NOT_FOUND:Phòng chơi không tồn tại');

        $this->service->scheduleRoom(
            roomId: 9999999,
            actorId: 1,
            actorRole: 'ADMIN',
            venueId: 1,
            physicalRoomId: 1,
            dmUserId: 2,
            startAtRaw: '2027-01-01 10:00:00'
        );
    }
}
