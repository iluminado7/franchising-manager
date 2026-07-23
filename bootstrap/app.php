<?php
use Illuminate\Http\Request;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web:      __DIR__.'/../routes/web.php',
        api:      __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health:   '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Detras de nginx, sin esto Laravel ignora las cabeceras X-Forwarded-*:
        //   - $request->ip() devuelve la IP del proxy para TODOS los usuarios,
        //     lo que vacia de sentido activity_logs (compliance) y hace que el
        //     rate limiter por IP del login cuente a todo el mundo junto;
        //   - $request->isSecure() puede dar false aunque el usuario este en
        //     HTTPS, porque nginx termina el TLS y reenvia por HTTP.
        //
        // Se confia SOLO en el nginx local, no en '*'. Con '*' Laravel confia en
        // toda la cadena de X-Forwarded-For y toma el primer valor, que es el que
        // el cliente puede escribir: un atacante mandaria una IP falsa y quedaria
        // registrada como suya. Confiando solo en el proxy conocido, Laravel
        // descarta lo falsificado y toma la IP real que nginx agrego.
        //
        // SI CAMBIA LA INFRA (ALB, CloudFront, Laravel Cloud): agregar aca el
        // rango del proxy, por ejemplo el CIDR de la VPC.
        $middleware->trustProxies(at: [
            '127.0.0.1',
            '::1',
        ]);

        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
        ]);
        $middleware->prepend(\App\Http\Middleware\CookieToBearer::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, Request $request) {
            return response()->json(['message' => 'No autenticado.'], 401);
        });

        // V2-H-014: sin esto, $this->authorize() devolveria HTML y romperia apiFetch.
        // Se conserva la forma {'error': '...'} que ya esperaba el frontend en los
        // 403 emitidos a mano por los controllers.
        $exceptions->render(function (\Illuminate\Auth\Access\AuthorizationException $e, Request $request) {
            $msg = $e->getMessage();
            if ($msg === '' || $msg === 'This action is unauthorized.') {
                $msg = 'Sin permisos.';
            }
            return response()->json(['error' => $msg], 403);
        });
    })->create();
