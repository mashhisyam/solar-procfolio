<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Company;
use App\Models\Holiday;
use App\Models\MasterItem;
use App\Models\Pic;
use App\Models\User;
use App\Models\WorkflowTemplate;
use App\Services\AuditLogger;
use App\Support\WorkdayCalendar as Cal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Master & Konfigurasi (khusus admin). Data yang sudah dipakai dinonaktifkan, tidak dihapus. */
class MasterController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Master/Index', [
            'tab' => $request->query('tab', 'organisasi'),
            'companies' => Company::query()->orderBy('type')->orderBy('name')->get(),
            'pics' => Pic::query()->with('company:id,name')->orderBy('name')->get(),
            'items' => MasterItem::query()->orderBy('group')->orderBy('sort')->orderBy('label')->get()->groupBy('group'),
            'groups' => MasterItem::GROUPS,
            'templates' => collect(WorkflowTemplate::SCHEMES)->mapWithKeys(fn ($s) => [$s => WorkflowTemplate::query()->where('scheme', $s)->orderBy('sort')->get()]),
            'settings' => AppSetting::allWithDefaults(),
            'holidays' => Holiday::query()->orderBy('date')->get()->map(fn ($h) => ['id' => $h->id, 'date' => Cal::iso($h->date), 'name' => $h->name, 'type' => $h->type]),
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'email', 'role', 'active']),
        ]);
    }

    public function saveCompany(Request $request, ?Company $company = null): RedirectResponse
    {
        $d = $request->validate(['name' => ['required', 'string', 'max:255'], 'type' => ['required', Rule::in(Company::TYPES)], 'active' => ['boolean']]);
        $company ? $company->update($d) : Company::query()->create($d);

        return $this->done('Perusahaan disimpan.');
    }

    public function savePic(Request $request, ?Pic $pic = null): RedirectResponse
    {
        $d = $request->validate([
            'name' => ['required', 'string', 'max:150'], 'title' => ['nullable', 'string'], 'company_id' => ['nullable', 'exists:companies,id'],
            'role' => ['nullable', 'string', 'max:40'], 'sub_bidang' => ['nullable', 'string', 'max:20'], 'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:40'], 'active' => ['boolean'],
        ]);
        $pic ? $pic->update($d) : Pic::query()->create($d);

        return $this->done('PIC disimpan.');
    }

    public function saveItem(Request $request, ?MasterItem $item = null): RedirectResponse
    {
        $d = $request->validate(['group' => ['required', Rule::in(array_keys(MasterItem::GROUPS))], 'label' => ['required', 'string', 'max:120'], 'active' => ['boolean']]);
        abort_if($item?->locked && $item->label !== $d['label'], 422, 'Label inti tidak dapat diubah.');
        $item ? $item->update($d) : MasterItem::query()->create([...$d, 'sort' => (int) MasterItem::query()->where('group', $d['group'])->max('sort') + 1]);

        return $this->done('Master disimpan.');
    }

    /** Simpan template tahap untuk satu skema (SPP, Kemitraan, Pasca). Pengadaan berjalan memakai snapshot-nya sendiri. */
    public function saveWorkflow(Request $request, string $scheme): RedirectResponse
    {
        abort_unless(in_array($scheme, WorkflowTemplate::SCHEMES, true), 404);
        $d = $request->validate([
            'stages' => ['required', 'array', 'min:1'], 'stages.*.key' => ['required', 'string', 'max:40', 'distinct'],
            'stages.*.name' => ['required', 'string'], 'stages.*.enabled' => ['boolean'], 'stages.*.optional' => ['boolean'],
            'stages.*.pic_id' => ['nullable', 'exists:pics,id'], 'stages.*.default_activities' => ['array'],
        ]);
        DB::transaction(function () use ($scheme, $d) {
            WorkflowTemplate::query()->where('scheme', $scheme)->delete();
            foreach ($d['stages'] as $i => $s) {
                WorkflowTemplate::query()->create([...$s, 'scheme' => $scheme, 'sort' => $i]);
            }
        });
        $this->audit->log(null, 'Konfigurasi', $scheme, 'Ubah template workflow', null, null, implode(' → ', array_column($d['stages'], 'name')));

        return $this->done("Template {$scheme} disimpan. Berlaku untuk pengadaan baru.");
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $d = $request->validate(collect(AppSetting::DEFAULTS)->map(fn () => ['required', 'integer', 'min:1', 'max:365'])->all());
        abort_if($d['dur_bad'] <= $d['dur_warn'], 422, 'Ambang merah harus lebih besar dari kuning.');
        foreach ($d as $k => $v) {
            if ((int) AppSetting::get($k) !== (int) $v) {
                $this->audit->log(null, 'Konfigurasi', $k, 'Ubah parameter', $k, AppSetting::get($k), $v);
            }
            AppSetting::put($k, (int) $v);
        }

        return $this->done('Parameter disimpan.');
    }

    public function saveHoliday(Request $request): RedirectResponse
    {
        $d = $request->validate(['date' => ['required', 'date', 'unique:holidays,date'], 'name' => ['required', 'string'], 'type' => ['required', Rule::in(Holiday::TYPES)]]);
        Holiday::query()->create($d);

        return $this->done('Tanggal kalender disimpan.');
    }

    public function deleteHoliday(Holiday $holiday): RedirectResponse
    {
        $holiday->delete();

        return $this->done('Tanggal kalender dihapus.');
    }

    public function saveUser(Request $request, User $user): RedirectResponse
    {
        $d = $request->validate(['role' => ['required', Rule::in(User::ROLES)], 'active' => ['boolean']]);
        abort_if($user->id === $request->user()->id && ($d['role'] !== 'admin' || ! ($d['active'] ?? true)), 422, 'Tidak dapat menurunkan peran akun sendiri.');
        $this->audit->log(null, 'Pengguna', $user->id, 'Ubah peran pengguna', 'role', $user->role, $d['role']);
        $user->update($d);

        return $this->done('Pengguna diperbarui.');
    }
}
