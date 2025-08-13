<?php

namespace App\Enums;

enum ServiceTypes: string
{
    //
    case PHARMACY = 'PHARMACY';
    case TREATMENT = 'TREATMENT';
    case CONSULTATION = 'CONSULTATION';
    case ADMISSION = 'ADMISSION';
    case APPOINTMENT = 'APPOINTMENT';
    case LAB_TEST = 'LAB-TEST';
    case EXPENSES = 'EXPENSES';
    case XRAY = 'X-RAY';
    case ANTE_NATAL = 'ANTE-NATAL';
    case ACCOUNT = 'ACCOUNT';
    case DEPOSIT = 'DEPOSIT';
    case RADIOLOGY_TEST = 'RADIOLOGY-TEST';
}
