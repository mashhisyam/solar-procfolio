<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

abstract class Controller
{
    protected function done(string $message, ?string $next = null): RedirectResponse
    {
        return back()->with('success', $message)->with('next', $next);
    }
}
