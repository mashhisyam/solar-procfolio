<?php

use App\Console\Commands\SendDailyDigest;
use Illuminate\Support\Facades\Schedule;

// Ringkasan harian pengadaan yang perlu perhatian (hari kerja, 07:30 WIB).
Schedule::command(SendDailyDigest::class)->weekdays()->at('07:30');
