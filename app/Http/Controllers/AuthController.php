<?php

namespace App\Http\Controllers;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    public function login(): Response
    {
        return Inertia::render('Auth', ['mode' => 'login']);
    }

    public function authenticate(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        if (! Auth::attempt([...$data, 'active' => true], $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => 'El correo o la contraseña no son correctos.']);
        }
        if (! Auth::user()->isSuper() && ! Auth::user()->company?->active) {
            Auth::logout();
            throw ValidationException::withMessages(['email' => 'El acceso de la empresa está desactivado.']);
        }
        $request->session()->regenerate();

        return redirect()->intended(Auth::user()->isSuper() ? '/admin/empresas' : '/');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    public function forgot(): Response
    {
        return Inertia::render('Auth', ['mode' => 'forgot']);
    }

    public function sendReset(Request $request): RedirectResponse
    {
        $request->validate(['email' => 'required|email']);
        try {
            Password::sendResetLink($request->only('email'));
        } catch (\Throwable $e) {
            report($e);
        }

        return back()->with('success', 'Si el correo está registrado, recibirás un enlace para establecer tu contraseña.');
    }

    public function reset(Request $request, string $token): Response
    {
        return Inertia::render('Auth', ['mode' => 'reset', 'token' => $token, 'email' => (string) $request->query('email', '')]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $request->validate(['token' => 'required', 'email' => 'required|email', 'password' => ['required', 'confirmed', PasswordRule::min(12)->letters()->numbers()]]);
        $status = Password::reset($request->only('email', 'password', 'password_confirmation', 'token'), function ($user, $password) {
            $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            event(new PasswordReset($user));
        });
        if ($status !== Password::PasswordReset) {
            throw ValidationException::withMessages(['email' => 'El enlace venció o no es válido. Solicitá uno nuevo.']);
        }

        return redirect('/login')->with('success', 'Contraseña guardada. Ya podés ingresar.');
    }
}
