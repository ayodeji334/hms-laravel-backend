<?php

namespace App\Enums;

enum UserTypes: string
{
    case STAFF_FAMILY = 'STAFF-FAMILY';
    case STAFF = 'STAFF';
    case STUDENT = 'STUDENT';
    case OTHERS = 'OTHERS';
}
