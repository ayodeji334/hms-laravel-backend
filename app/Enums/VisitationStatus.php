<?php

namespace App\Enums;

enum VisitationStatus: string
{
    case PENDING = 'WAITING';
    case ACCEPTED = 'ACCEPTED';
    case CANCELLED = 'UNREFERRED';
    case CONSULTED = 'CONSULTED';
    case RESCHEDULE = 'RESCHEDULE';
}
