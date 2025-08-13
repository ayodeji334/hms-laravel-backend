<?php

namespace App\Enums;

enum TitleTypes: string
{
    case PROF = 'Prof';
    case PROF_MRS = 'Prof. (Mrs)';
    case PROF_ENGR = 'Prof., Engr.';
    case PROF_ENGR_MRS = 'Prof., Engr., (Mrs)';
    case DR = 'Dr.';
    case MR = 'Mr.';
    case MISS = 'Miss.';
    case MASTER = 'Master.';
    case MRS = 'Mrs.';
    case DR_MRS = 'Dr. (Mrs)';
    case ENGR = 'Engr.,';
    case ALH = 'Alh.';
    case PASTOR = 'Pst.';
    case REVEREND = 'Rev.';
}
