<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // H-014 fix: rate limiter compuesto para login.
        //
        // El throttle:5,1 anterior era solo por IP, lo que permitía credential
        // stuffing rotando IPs (botnets/proxies) para atacar un email específico.
        // Ahora tenemos DOS límites que se aplican simultáneamente:
        //
        //   - Por IP: 10 intentos/minuto. Permisivo porque una IP puede ser una
        //     empresa entera detrás de un NAT (varios usuarios legítimos).
        //   - Por email: 5 intentos/minuto. Estricto porque cada email es una
        //     cuenta única. Rotar IPs no ayuda si el email queda bloqueado.
        //
        // Si CUALQUIERA de los dos se supera, el login se bloquea.
        RateLimiter::for('login', function (Request $request) {
            $email = strtolower((string) $request->input('email', ''));

            return [
                Limit::perMinute(10)->by('login_ip:' . $request->ip()),
                Limit::perMinute(5)->by('login_email:' . $email),
            ];
        });
    }
}