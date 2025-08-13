<?php

namespace App\Enums;

enum TreatmentStatus: string
{
    case CANCELED = 'CANCELED';
    case COMPLETED = 'COMPLETED';
    case IN_PROGRESS = 'IN_PROGRESS';
}
