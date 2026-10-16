<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Period;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        $user = $request->user();

        return array_merge(parent::share($request), [
            'app' => ['name' => config('app.name'), 'today' => now()->toDateString()],
            // Periode pengadaan aktif (2 bulan bawaan); dipakai filter di Dashboard dan daftar.
            'period' => fn () => Period::share($request),
            'auth' => [
                'user' => $user ? ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $user->role, 'role_label' => User::ROLE_LABELS[$user->role] ?? $user->role] : null,
                // Hak akses per menu (none | view | edit). 'edit' lama = boleh mengubah pengadaan.
                'access' => $user?->accessMap() ?? [],
                'can' => ['edit' => (bool) $user?->canEdit(), 'admin' => (bool) $user?->isAdmin(), 'super' => (bool) $user?->isSuperAdmin()],
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'next' => fn () => $request->session()->get('next'),
            ],
        ]);
    }
}
