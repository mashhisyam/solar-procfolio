<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Access;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pemeriksaan hak akses menu: ->middleware('menu:pengadaan,edit'), atau 'menu:super' untuk aksi khusus Super Admin.
 */
class EnsureMenuAccess
{
    public function handle(Request $request, Closure $next, string $menu, string $level = Access::VIEW): Response
    {
        /** @var User|null $user */
        $user = $request->user();
        $ok = $user && ($menu === 'super' ? $user->isSuperAdmin() : $user->allows($menu, $level));
        if (! $ok) {
            // Dashboard tidak diizinkan: arahkan ke menu pertama yang boleh dibuka.
            if ($user && $menu === 'dashboard' && $request->isMethod('GET') && ($home = $user->homeUrl())) {
                return redirect($home);
            }
            abort(403, 'Anda tidak memiliki akses ke menu ini. Hubungi Super Admin.');
        }

        return $next($request);
    }
}
