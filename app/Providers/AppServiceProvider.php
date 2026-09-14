<?php

namespace App\Providers;

use App\Services\LimiteMailsDemo;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
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
        // Recuperacion de contrasena. Mismo patron compuesto que 'login', pero
        // con el limite por email en HORAS.
        //
        // Este endpoint manda MAILS A TERCEROS, asi que el abuso no es solo
        // fuerza bruta: alguien puede usarlo para bombardear la casilla de un
        // franquiciado, o para barrer direcciones y ver cuales existen.
        //
        //   - Por IP: 5/minuto. Corta el barrido desde un origen.
        //   - Por email: 3/HORA. Nadie legitimo necesita tres enlaces en una
        //     hora, y es lo unico que frena a quien rota IPs para hostigar a
        //     una persona puntual.
        RateLimiter::for('password-reset', function (Request $request) {
            $email = strtolower((string) $request->input('email', ''));

            return [
                Limit::perMinute(5)->by('pwreset_ip:' . $request->ip()),
                Limit::perHour(3)->by('pwreset_email:' . $email),
            ];
        });

        RateLimiter::for('login', function (Request $request) {
            $email = strtolower((string) $request->input('email', ''));

            return [
                Limit::perMinute(10)->by('login_ip:' . $request->ip()),
                Limit::perMinute(5)->by('login_email:' . $email),
            ];
        });

        // Altas de usuarios (POST /usuarios). Cada alta manda un mail con
        // credenciales a la direccion que se cargue. Sin limite, un script
        // puede crear y eliminar usuarios en bucle y mandar cientos de mails
        // desde el dominio.
        //
        // Por USUARIO que da de alta, no por IP: quien lo hace ya esta
        // autenticado. Generoso a proposito: un cliente real cargando su red
        // de a una no llega; un script si.
        RateLimiter::for('altas-usuario', function (Request $request) {
            $quien = 'altas_usuario:' . ($request->user()?->id ?? $request->ip());
            $respuesta = fn () => response()->json([
                'message' => 'Demasiadas altas de usuarios seguidas. Esperá unos minutos y volvé a intentar.',
            ], 429);

            return [
                Limit::perMinute(10)->by($quien . ':min')->response($respuesta),
                Limit::perHour(100)->by($quien . ':hora')->response($respuesta),
            ];
        });

        // Tope diario de mails hacia usuarios de empresas demo. Engancha a TODO
        // mail que sale (encolado o no): devolver false cancela el envio. Ver
        // App\Services\LimiteMailsDemo, incluido por que va registrado aca y
        // no como clase en app/Listeners.
        Event::listen(MessageSending::class, function (MessageSending $evento) {
            return LimiteMailsDemo::permitir($evento) ? null : false;
        });
    }
}