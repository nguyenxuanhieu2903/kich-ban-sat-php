<?php

declare(strict_types=1);

namespace App\Shared\Enums;

enum PaymentStatus: string
{
    case PENDING = 'PENDING';
    case PROCESSING = 'PROCESSING';
    case PAID = 'PAID';
    case PARTIALLY_PAID = 'PARTIALLY_PAID';
    case FAILED = 'FAILED';
    case EXPIRED = 'EXPIRED';
    case CANCELLED = 'CANCELLED';
}