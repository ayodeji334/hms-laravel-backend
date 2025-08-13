<?php

namespace App;

enum MaritalStatus: string
{
    case MARRIED = 'MARRIED';
    case SINGLE = 'SINGLE';
    case DIVORCED = 'DIVORCED';
    case WIDOW = 'WIDOW';
    case WIDOWER = 'WIDOWER';
}
