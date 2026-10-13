<?php

namespace App\Enums;

enum SignStatus: string
{
    case Pending = 'Belum Diproses';
    case Signing = 'Proses TTD';
    case Signed = 'Selesai TTD';
    case Revision = 'Perlu Revisi';
}
