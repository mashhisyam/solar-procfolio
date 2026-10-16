<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pic;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Access;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Kelola Pengguna: hanya Super Admin. Membuat akun, mengatur peran, hak akses per menu, status, dan kata sandi. */
class UserController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(): Response
    {
        return Inertia::render('Users/Index', [
            'users' => User::query()->with('pic:id,name')->orderByRaw("case when role = 'super_admin' then 0 else 1 end")->orderBy('name')->get()
                ->map(fn (User $u) => [
                    'id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'role' => $u->role, 'active' => $u->active,
                    'pic_id' => $u->pic_id, 'pic' => $u->pic?->name, 'sso' => (bool) $u->microsoft_id, 'owner' => in_array($u->email, User::OWNER_EMAILS, true),
                    'permissions' => $u->role === User::SUPER_ADMIN ? Access::full() : Access::normalize($u->permissions ?? []),
                    'created_at' => $u->created_at?->toDateString(),
                ]),
            'menus' => collect(Access::MENUS)->map(fn ($m, $k) => ['key' => $k, 'label' => $m[0], 'levels' => $m[2]])->values(),
            'defaults' => ['admin' => Access::ADMIN_DEFAULT, 'view_only' => Access::VIEW_ONLY, 'full' => Access::full()],
            'roles' => collect(User::ROLE_LABELS)->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values(),
            'pics' => Pic::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'role']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $d = $this->validated($request, null);
        $u = User::query()->create([
            'name' => $d['name'], 'email' => strtolower($d['email']), 'password' => $d['password'], 'role' => $d['role'],
            'permissions' => Access::normalize($d['permissions'] ?? Access::ADMIN_DEFAULT), 'pic_id' => $d['pic_id'] ?? null, 'active' => $d['active'] ?? true,
        ]);
        $this->audit->log(null, 'Pengguna', $u->id, 'Buat akun', 'email', null, $u->email, User::ROLE_LABELS[$u->role]);

        return $this->done("Akun {$u->email} dibuat.");
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $d = $this->validated($request, $user);
        $self = $user->id === $request->user()->id;
        // Akun pemilik dan akun sendiri tidak boleh diturunkan atau dinonaktifkan agar sistem tidak terkunci.
        if (($self || in_array($user->email, User::OWNER_EMAILS, true)) && (! ($d['active'] ?? true) || ($user->role === User::SUPER_ADMIN && $d['role'] !== User::SUPER_ADMIN))) {
            throw ValidationException::withMessages(['role' => 'Akun pemilik dan akun Anda sendiri tidak dapat diturunkan atau dinonaktifkan.']);
        }
        $before = ['role' => $user->role, 'active' => $user->active, 'permissions' => Access::normalize($user->permissions ?? [])];
        $user->fill([
            'name' => $d['name'], 'email' => strtolower($d['email']), 'role' => $d['role'], 'pic_id' => $d['pic_id'] ?? null, 'active' => $d['active'] ?? true,
            'permissions' => Access::normalize($d['permissions'] ?? []),
        ]);
        if (filled($d['password'] ?? null)) {
            $user->password = $d['password'];
        }
        $user->save();

        if ($before['role'] !== $user->role) {
            $this->audit->log(null, 'Pengguna', $user->id, 'Ubah peran', 'role', User::ROLE_LABELS[$before['role']] ?? $before['role'], User::ROLE_LABELS[$user->role]);
        }
        if ($before['active'] !== $user->active) {
            $this->audit->log(null, 'Pengguna', $user->id, $user->active ? 'Aktifkan akun' : 'Nonaktifkan akun', 'active', $before['active'] ? 'Aktif' : 'Nonaktif', $user->active ? 'Aktif' : 'Nonaktif');
        }
        $changed = collect(Access::normalize($user->permissions ?? []))->filter(fn ($v, $k) => ($before['permissions'][$k] ?? null) !== $v)
            ->map(fn ($v, $k) => Access::MENUS[$k][0].': '.($before['permissions'][$k] ?? '-').' → '.$v)->implode('; ');
        if ($changed !== '') {
            $this->audit->log(null, 'Pengguna', $user->id, 'Ubah hak akses menu', 'permissions', null, $changed);
        }
        if (filled($d['password'] ?? null)) {
            $this->audit->log(null, 'Pengguna', $user->id, 'Atur ulang kata sandi');
        }

        return $this->done("Akun {$user->email} diperbarui.");
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?User $user): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user?->id)],
            'password' => [$user ? 'nullable' : 'required', 'string', Password::min(8)],
            'role' => ['required', Rule::in(User::ROLES)],
            'pic_id' => ['nullable', 'exists:pics,id'],
            'active' => ['boolean'],
            'permissions' => ['array'],
            'permissions.*' => [Rule::in([Access::NONE, Access::VIEW, Access::EDIT])],
        ], [], ['password' => 'kata sandi', 'role' => 'peran', 'pic_id' => 'PIC']);
    }
}
