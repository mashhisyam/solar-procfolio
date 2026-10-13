<?php

namespace App\Enums;

enum GrStatus: string
{
    case NotSubmitted = 'Belum Diajukan';
    case Submitted = 'Diajukan';
    case Revision = 'Perlu Revisi';
    case Issued = 'GR Terbit';
}
