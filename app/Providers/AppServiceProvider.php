<?php

namespace App\Providers;

use App\Models\Arbitro;
use App\Models\Entrenador;
use App\Models\Equipo;
use App\Models\Estadio;
use App\Models\Jugador;
use App\Models\Liga;
use App\Models\Trofeo;
use App\Observers\RegistroActividadObserver;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        ResetPassword::createUrlUsing(function ($user, string $token) {
            return config('app.frontend_url').'/reset-password?token='.$token.'&email='.urlencode($user->email);
        });

        Password::defaults(function () {
            return Password::min(8)->letters()->numbers();
        });

        Jugador::observe(RegistroActividadObserver::class);
        Equipo::observe(RegistroActividadObserver::class);
        Estadio::observe(RegistroActividadObserver::class);
        Arbitro::observe(RegistroActividadObserver::class);
        Trofeo::observe(RegistroActividadObserver::class);
        Entrenador::observe(RegistroActividadObserver::class);
        Liga::observe(RegistroActividadObserver::class);
    }
}