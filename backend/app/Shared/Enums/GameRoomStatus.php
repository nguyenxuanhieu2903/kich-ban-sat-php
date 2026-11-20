<?php

declare(strict_types=1);

namespace App\Shared\Enums;

enum GameRoomStatus: string
{
    case FORMING = 'FORMING';
    case FORMED = 'FORMED';
    case SCHEDULE_NEGOTIATING = 'SCHEDULE_NEGOTIATING';
    case SCHEDULED = 'SCHEDULED';
    case IN_PROGRESS = 'IN_PROGRESS';
    case COMPLETED = 'COMPLETED';
    case CANCELLED = 'CANCELLED';
    case EXPIRED = 'EXPIRED';
}
