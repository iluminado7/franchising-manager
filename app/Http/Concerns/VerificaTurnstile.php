<?php

namespace App\Http\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Verificacion de Cloudflare Turnstile, compartida.
 *
 * Estaba como metodo privado de AuthController. Se movio aca cuando aparecio el
 * segundo endpoint publico que lo necesita (recuperacion de contrasena), para
 * no tener dos copias que puedan divergir — el mismo problema que ya paso con
 * el lightbox de avatares (§9 del README).
 *
 * QUE PROTEGE
 *   Los endpoints publicos, sin sesion, que un bot puede castigar directo:
 *     - POST /api/login              fuerza bruta / credential stuffing
 *     - POST /api/password/solicitar dispara MAILS A TERCEROS
 *
 *   El widget del lado del cliente NO protege nada por si solo: cualquiera
 *   puede postear un string arbitrario al endpoint. La unica verificacion real
 *   es esta.
 */
trait VerificaTurnstile
{
    /**
     * Verifica el token de Cloudflare Turnstile contra la API Siteverify.
     *
     * El widget del lado del cliente NO protege nada por si solo: cualquiera
     * puede postear un string arbitrario a /api/login. La unica verificacion
     * real es esta.
     *
     * POLITICA DE FALLO — leer antes de cambiar algo aca:
     *
     *   RECHAZA solo cuando la culpa es del cliente: token ausente, forjado,
     *   vencido o ya usado. El usuario ve un 422 y reintenta.
     *
     *   DEJA PASAR (fail-open) cuando el problema es nuestro o de Cloudflare:
     *   secret vacio o mal pegado, timeout de red, 5xx de siteverify. La
     *   alternativa seria dejar a toda la empresa sin poder entrar por una
     *   variable de entorno mal copiada o por una caida de un tercero.
     *   El rate limiter compuesto ('throttle:login' en api.php) corre como
     *   middleware de ruta, o sea ANTES de este metodo, y sigue activo en
     *   todos esos casos: la fuerza bruta continua cubierta.
     *
     *   Todo fail-open queda en el log. Si aparece seguido en produccion es
     *   un problema de configuracion, no ruido.
     *
     * El token dura 300s y es de un solo uso; uno repetido vuelve con
     * 'timeout-or-duplicate'. Por eso login.html resetea el widget en cada
     * error — sin ese reset, el segundo intento fallaria siempre aunque la
     * contrasena fuera correcta.
     *
     * No se registra en activity_logs porque user_id es NOT NULL y en este
     * punto todavia no hay usuario resuelto. Misma limitacion que los
     * intentos contra emails inexistentes.
     */
    protected function verificarTurnstile(Request $request, string $campoError = 'email'): void
    {
        if (!config('services.turnstile.enabled')) {
            return;
        }

        $secret = trim((string) config('services.turnstile.secret', ''));

        if ($secret === '') {
            Log::error('Turnstile habilitado pero sin TURNSTILE_SECRET_KEY. Login sin verificacion anti-bot.');
            return;
        }

        $token = trim((string) $request->input('turnstile_token', ''));

        if ($token === '') {
            throw ValidationException::withMessages([
                $campoError => ['Completá la verificación de seguridad.'],
            ]);
        }

        try {
            $respuesta = Http::timeout((int) config('services.turnstile.timeout', 4))
                ->asForm()
                ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                    'secret'   => $secret,
                    'response' => $token,
                ]);
        } catch (\Throwable $e) {
            Log::warning('Turnstile: siteverify inalcanzable, se deja pasar el login.', [
                'error' => $e->getMessage(),
                'ip'    => $request->ip(),
            ]);
            return;
        }

        if (!$respuesta->successful()) {
            Log::warning('Turnstile: siteverify respondio HTTP no-2xx, se deja pasar el login.', [
                'status' => $respuesta->status(),
                'ip'     => $request->ip(),
            ]);
            return;
        }

        $datos = (array) $respuesta->json();

        if (($datos['success'] ?? false) === true) {
            return;
        }

        $codigos = (array) ($datos['error-codes'] ?? []);

        // Configuracion nuestra rota o caida de Cloudflare: no se le puede
        // cobrar al visitante. Fail-open ruidoso.
        $codigosInfra = [
            'missing-input-secret',
            'invalid-input-secret',
            'internal-error',
            'bad-request',
        ];

        if (array_intersect($codigos, $codigosInfra)) {
            Log::error('Turnstile: error de configuracion, se deja pasar el login.', [
                'error-codes' => $codigos,
            ]);
            return;
        }

        Log::warning('Turnstile: token rechazado.', [
            'error-codes' => $codigos,
            'ip'          => $request->ip(),
        ]);

        $mensaje = in_array('timeout-or-duplicate', $codigos, true)
            ? 'La verificación de seguridad expiró. Volvé a marcar la casilla e intentá de nuevo.'
            : 'La verificación de seguridad falló. Volvé a intentar.';

        throw ValidationException::withMessages([
            $campoError => [$mensaje],
        ]);
    }
}