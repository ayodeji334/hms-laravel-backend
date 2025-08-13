<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case COMPLETED = 'COMPLETED';
    case PENDING = 'PENDING';
    case FAILED = 'FAILED';
    case REFUNDED = 'REFUNDED';
    case CANCELLED = 'CANCELLED';
}
