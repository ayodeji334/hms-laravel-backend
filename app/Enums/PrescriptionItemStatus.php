<?php

namespace App\Enums;

enum PrescriptionItemStatus: string
{
    case DISPENSE = 'DISPENSED';
    case NOT_AVAILABLE = 'NOT-AVAILABLE';
    case CREATED = 'CREATED';
}
