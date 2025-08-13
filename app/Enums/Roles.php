<?php

namespace App\Enums;

enum Roles: string
{
    //
    case ADMIN = 'ADMIN';
    case NURSE = 'NURSE';
    case PHARMACIST = 'PHARMACIST';
    case DOCTOR = 'DOCTOR';
    case SUPER_ADMIN = 'SUPER-ADMIN';
    case LAB_TECHNOLOGIST = 'LAB-TECHNOLOGIST';
    case RECORD_KEEPER = 'RECORD-KEEPER';
    case CASHIER = 'CASHIER';
}
