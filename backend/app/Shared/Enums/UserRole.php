<?php

declare(strict_types=1);

namespace App\Shared\Enums;

enum UserRole: string
{
    case ADMIN = 'ADMIN';
    case DM = 'DM';
    case PLAYER = 'PLAYER';
}
