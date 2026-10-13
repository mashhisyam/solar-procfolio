<?php

namespace App\Enums;

enum AmendmentStatus: string
{
    case Running = 'Dalam Proses';
    case Released = 'Rilis';
    case Cancelled = 'Dibatalkan';
}
