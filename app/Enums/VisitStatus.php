<?php

namespace App\Enums;

enum VisitStatus: string
{
    case Running = 'Dalam Proses';
    case Done = 'Selesai';
    case Returned = 'Dikembalikan';
    case Skipped = 'Dilewati';
}
