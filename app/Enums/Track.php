<?php

namespace App\Enums;

/** Dua progres per pengadaan. */
enum Track: string
{
    case Pengadaan = 'pengadaan'; // Progres 1: approval hingga SP2K/PO rilis (termasuk amandemen)
    case Pasca = 'pasca';         // Progres 2: satu alur per tahap pembayaran
}
