<?php

namespace App\Enums;

enum PrescriptionStatus: string
{
    case CREATED = 'NOT-DISPENSE';
    case APPROVED = 'DISPENSED';
}
