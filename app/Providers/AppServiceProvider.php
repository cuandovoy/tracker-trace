<?php

namespace App\Providers;

use App\Models\Driver;
use App\Models\Trip;
use App\Models\Truck;
use App\Policies\FleetPolicy;
use App\Support\Tenant;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(Tenant::class, fn () => new Tenant);
    }

    public function boot(): void
    {
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(30)->by($request->ip()),
        ]);
        foreach ([Truck::class, Driver::class, Trip::class] as $model) {
            Gate::policy($model, FleetPolicy::class);
        }
        Gate::define('manage-company', fn ($u) => $u->active && $u->isAdmin() && $u->company?->active);
        Gate::define('manage-platform', fn ($u) => $u->active && $u->isSuper());
        ResetPassword::toMailUsing(fn ($user, $token) => (new MailMessage)
            ->subject('Establecé tu contraseña — Consola de flota')->greeting('Hola, '.$user->name)
            ->line('Usá este enlace para establecer o recuperar tu contraseña.')->action('Establecer contraseña', url('/reset-password/'.$token.'?email='.urlencode($user->email)))
            ->line('El enlace vence en 60 minutos. Si no lo solicitaste, podés ignorar este mensaje.'));
    }
}
