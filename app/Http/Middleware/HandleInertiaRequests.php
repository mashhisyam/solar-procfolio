<?php

namespace App\Http\Middleware;

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
            'auth' => [
                'user' => $user ? ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $user->role] : null,
                'can' => ['edit' => (bool) $user?->canEdit(), 'admin' => (bool) $user?->isAdmin()],
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'next' => fn () => $request->session()->get('next'),
            ],
        ]);
    }
}
