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
        $exceptions->render(function (\Symfony\Component\Routing\Exception\RouteNotFoundException $e, Request $request) {
            if (str_contains($e->getMessage(), 'Route [login] not defined')) {
                return response()->json(['message' => 'No autenticado.'], 401);
            }
            // Cualquier otra ruta faltante sigue su curso: eso SI es un bug
            // nuestro y tiene que seguir apareciendo como error.
            return null;
        });
        // ── REGISTRO DE ERRORES EN BASE (tabla error_logs) ───────────────
        //
        // Para que el super_admin pueda ver los errores desde el sistema sin
        // entrar por SSH. NO reemplaza a storage/logs: eso sigue igual, con el
        // trace completo. Esta tabla es un resumen consultable.
        //
        // Se agrupa por huella = sha256(clase|archivo|linea). Una fila por error
        // unico, con contador. Un error en bucle no llena la tabla.
        $exceptions->report(function (\Throwable $e) {

            // 1. SOLO 5xx. Los 404, 422, 401, 403 y 419 son comportamiento
            //    normal de la aplicacion: incluirlos ahoga la senal.
            if ($e instanceof \Illuminate\Validation\ValidationException
                || $e instanceof \Illuminate\Auth\AuthenticationException
                || $e instanceof \Illuminate\Auth\Access\AuthorizationException
                || $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException
                || $e instanceof \Illuminate\Session\TokenMismatchException) {
                return;
            }
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
                && $e->getStatusCode() < 500) {
                return;
            }

            // 2. TODO envuelto: si falla registrar el error, NO puede lanzar
            //    otra excepcion. Eso seria un bucle infinito.
            try {
                $request = request();
                $esWeb   = $request instanceof \Illuminate\Http\Request
                           && !app()->runningInConsole();

                // 3. La ruta va REDACTADA antes de guardarse.
                //
                //    /manuales/archivo/{token} usa un token opaco, atado al
                //    usuario y valido 60 minutos: es una CREDENCIAL FUNCIONAL.
                //    Guardarla entera seria escribirla en una tabla que despues
                //    se muestra en pantalla.
                $ruta = null;
                if ($esWeb) {
                    $ruta = '/' . ltrim($request->path(), '/');
                    $ruta = preg_replace(
                        '#(/manuales/archivo/)[^/?]+#',
                        '$1{token}',
                        $ruta
                    );

                    // Del query string se conservan las claves pero se enmascaran
                    // los valores sensibles.
                    $query = $request->query();
                    foreach (['password', 'turnstile_token', 'token', 'api_key'] as $k) {
                        if (array_key_exists($k, $query)) {
                            $query[$k] = '{redactado}';
                        }
                    }
                    if ($query) {
                        $ruta .= '?' . http_build_query($query);
                    }
                    $ruta = mb_substr($ruta, 0, 500);
                }

                $user = $esWeb ? $request->user() : null;

                // 4. El trace, recortado. Los primeros frames dicen que paso;
                //    el resto es Laravel interno y solo agrega superficie.
                $trace = implode("\n", array_slice(
                    explode("\n", $e->getTraceAsString()), 0, 8
                ));

                $archivo = (string) $e->getFile();
                $linea   = (int) $e->getLine();
                $clase   = get_class($e);
                $huella  = hash('sha256', $clase . '|' . $archivo . '|' . $linea);

                // 5. Upsert con incremento. La sintaxis con alias (AS nuevo)
                //    reemplaza a VALUES(), deprecada desde MySQL 8.0.20.
                //    Requiere MySQL >= 8.0.19; el proyecto usa 8.0.45.
                \Illuminate\Support\Facades\DB::insert(
                    'INSERT INTO error_logs
                        (huella, excepcion, mensaje, archivo, linea, metodo, ruta,
                         user_id, empresa_id, ip, user_agent, trace,
                         ocurrencias, primera_vez, ultima_vez, resuelto)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,1,NOW(),NOW(),0) AS nuevo
                     ON DUPLICATE KEY UPDATE
                        ocurrencias = error_logs.ocurrencias + 1,
                        ultima_vez  = NOW(),
                        mensaje     = nuevo.mensaje,
                        metodo      = nuevo.metodo,
                        ruta        = nuevo.ruta,
                        user_id     = nuevo.user_id,
                        empresa_id  = nuevo.empresa_id,
                        ip          = nuevo.ip,
                        user_agent  = nuevo.user_agent,
                        trace       = nuevo.trace,
                        resuelto    = 0',
                    [
                        $huella,
                        mb_substr($clase, 0, 255),
                        mb_substr((string) $e->getMessage(), 0, 5000),
                        mb_substr($archivo, 0, 500),
                        $linea,
                        $esWeb ? $request->method() : 'CLI',
                        $ruta,
                        $user?->id,
                        $user?->empresa_id,
                        $esWeb ? $request->ip() : null,
                        $esWeb ? mb_substr((string) $request->userAgent(), 0, 500) : null,
                        mb_substr($trace, 0, 5000),
                    ]
                );
            } catch (\Throwable $ignorar) {
                // Deliberadamente vacio. El error real ya quedo en
                // storage/logs; esta tabla es un extra y no puede romper nada.
            }
        });
    })->create();
