<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $u = $request->user();
        if (! $u->active || (! $u->isSuper() && ! $u->company?->active)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/login')->withErrors(['email' => 'Tu acceso está desactivado. Contactá al administrador.']);
        }

        return $next($request);
    }
}
