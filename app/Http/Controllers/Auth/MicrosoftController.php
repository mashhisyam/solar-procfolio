<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Access;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

/** SSO Microsoft Entra ID (akun Microsoft 365 kantor). */
class MicrosoftController extends Controller
{
    public function redirect(): RedirectResponse
    {
        abort_unless(config('services.azure.client_id'), 404);

        return Socialite::driver('azure')->scopes(['openid', 'profile', 'email', 'User.Read'])->redirect();
    }

    public function callback(): RedirectResponse
    {
        $ms = Socialite::driver('azure')->user();
        $email = strtolower((string) ($ms->getEmail() ?: ($ms->user['userPrincipalName'] ?? '')));
        if ($email === '') {
            return redirect('/login')->with('error', 'Akun Microsoft tidak memiliki email.');
        }
        $user = User::query()->where('microsoft_id', $ms->getId())->orWhere('email', $email)->first();
        $isAdmin = in_array($email, array_map('strtolower', config('procfolio.admin_emails', [])), true);
        if (! $user) {
            $user = User::query()->create([
                'name' => $ms->getName() ?: $email, 'email' => $email, 'microsoft_id' => $ms->getId(),
                // Akun SSO baru: Admin hanya lihat sampai Super Admin mengatur hak aksesnya.
                'role' => $isAdmin ? User::SUPER_ADMIN : User::ADMIN, 'permissions' => $isAdmin ? Access::full() : Access::VIEW_ONLY, 'active' => true,
            ]);
        } else {
            $user->fill(['microsoft_id' => $ms->getId(), 'name' => $ms->getName() ?: $user->name]);
            if ($isAdmin) {
                $user->role = User::SUPER_ADMIN;
            }
            $user->save();
        }
        if (! $user->active) {
            return redirect('/login')->with('error', 'Akun Anda dinonaktifkan. Hubungi admin Procfolio.');
        }
        Auth::login($user, true);
        request()->session()->regenerate();

        return redirect()->intended('/');
    }
}
