<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Auth/Login', [
            'ssoEnabled' => (bool) config('services.azure.client_id'),
            'localLogin' => (bool) config('procfolio.local_login'),
        ]);
    }

    /** Login lokal: hanya untuk pengembangan/UAT bila SSO belum siap. */
    public function store(Request $request): RedirectResponse
    {
        abort_unless(config('procfolio.local_login'), 404);
        $cred = $request->validate(['email' => ['required', 'email'], 'password' => ['required']]);
        if (! Auth::attempt([...$cred, 'active' => true], $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => 'Email atau kata sandi tidak sesuai.']);
        }
        $request->session()->regenerate();

        return redirect()->intended('/');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
