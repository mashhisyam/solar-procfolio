<?php

namespace App\Console\Commands;

use App\Mail\DailyDigest;
use App\Models\Procurement;
use App\Models\User;
use App\Services\ProcurementInsight;
use App\Support\WorkdayCalendar;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/** Kirim ringkasan harian pengadaan yang butuh perhatian ke pengguna aktif (admin & pengaju). */
class SendDailyDigest extends Command
{
    protected $signature = 'procfolio:digest {--dry : Tampilkan ringkasan tanpa mengirim email}';

    protected $description = 'Kirim email ringkasan harian pengadaan yang perlu ditindaklanjuti';

    public function handle(ProcurementInsight $insight, WorkdayCalendar $cal): int
    {
        if (! $cal->isWorkday(now()->toDateString())) {
            $this->info('Hari libur, ringkasan tidak dikirim.');

            return self::SUCCESS;
        }
        $rows = Procurement::query()->with(ProcurementInsight::WITH)->whereNull('cancelled_at')->where('is_draft', false)->get()
            ->filter(fn ($p) => $insight->isActive($p))->map(fn ($p) => $insight->row($p))
            ->filter(fn ($r) => $r['priority'] === 'Urgent' || count($r['alerts']) > 0)->sortByDesc('score')->values();
        if ($rows->isEmpty()) {
            $this->info('Tidak ada pengadaan yang perlu perhatian.');

            return self::SUCCESS;
        }
        if ($this->option('dry')) {
            $rows->each(fn ($r) => $this->line("{$r['spp_number']} · {$r['title']} · ".collect($r['alerts'])->pluck('label')->implode('; ')));

            return self::SUCCESS;
        }
        User::query()->where('active', true)->whereIn('role', ['admin', 'pengaju'])->get()
            ->each(fn (User $u) => Mail::to($u->email)->queue(new DailyDigest($rows->all())));
        $this->info("Ringkasan {$rows->count()} pengadaan dikirim.");

        return self::SUCCESS;
    }
}
