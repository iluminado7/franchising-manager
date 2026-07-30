<?php

namespace App\Http\Controllers;

use App\Http\Concerns\VerificaTurnstile;
use App\Models\User;
use App\Models\ActivityLog;
use App\Rules\Cuit;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // verificarTurnstile() vivia aca como metodo privado. Se movio al trait
    // cuando aparecio el segundo endpoint publico que lo necesita
    // (recuperacion de contrasena): dos copias de una politica de seguridad
    // con fail-open pueden divergir sin que nadie lo note.
    use VerificaTurnstile;

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        // Verificacion anti-bot ANTES de tocar la base: sin token valido no se
        // llega ni a averiguar si un email existe. Ver verificarTurnstile()
        // para la politica de fallo.
        $this->verificarTurnstile($request);

        // H-014 fix: buscar sin filtro de 'activo' para poder loguear el motivo
        // exacto del fallo (email inexistente vs cuenta suspendida vs password
        // incorrecta). Al usuario le devolvemos siempre "credenciales incorrectas"
        // para los primeros 3 casos, sin filtrar información a atacantes.
        $user = User::where('email', $request->email)
                    ->whereNull('deleted_at')
                    ->first();

        if (!$user) {
            $this->logLoginFallido($request, null, null, 'login_fallido_email_inexistente');
            throw ValidationException::withMessages([
                'email' => ['Las credenciales no son correctas.'],
            ]);
        }

        if (!Hash::check($request->password, $user->password_hash)) {
            $this->logLoginFallido($request, $user->id, $user->empresa_id, 'login_fallido_password_incorrecta');
            throw ValidationException::withMessages([
                'email' => ['Las credenciales no son correctas.'],
            ]);
        }

        if (!$user->activo) {
            $this->logLoginFallido($request, $user->id, $user->empresa_id, 'login_fallido_cuenta_suspendida');
            throw ValidationException::withMessages([
                'email' => ['Las credenciales no son correctas.'],
            ]);
        }

        // Bloqueo por suspensión de empresa o sucursal (super_admin nunca se bloquea).
        // Este mensaje SÍ es específico (histórico) porque el usuario legítimo
        // necesita saber que su cuenta está bien y el problema es de la empresa.
        if (!$user->esSuperAdmin()) {
            $empresaSuspendida    = $user->empresa && !$user->empresa->activa;
            $franquicia           = optional($user->franchiseStaff)->franquicia;
            $franquiciaSuspendida = $franquicia && !$franquicia->activa;

            if ($empresaSuspendida) {
                $this->logLoginFallido($request, $user->id, $user->empresa_id, 'login_fallido_empresa_suspendida');
                throw ValidationException::withMessages([
                    'email' => ['Tu empresa o sucursal fue suspendida. Contactá al administrador.'],
                ]);
            }
            if ($franquiciaSuspendida) {
                $this->logLoginFallido($request, $user->id, $user->empresa_id, 'login_fallido_franquicia_suspendida');
                throw ValidationException::withMessages([
                    'email' => ['Tu empresa o sucursal fue suspendida. Contactá al administrador.'],
                ]);
            }
        }

        // Cargar perfil según rol — ahora incluye super_admin
        $perfil = match($user->rol) {
            'super_admin'   => $user->superAdmin,
            'franquiciante' => $user->systemAdmin,
            'franquiciado'  => $user->franchiseStaff,
            'empleado'      => $user->franchiseStaff,
        };

        // V2-H-013: antes se generaba una lista de abilities por rol que NINGÚN
        // endpoint validaba (cero usos de tokenCan() y cero middleware 'ability:'
        // en todo el código). Era metadata muerta que sugería una protección
        // inexistente.
        //
        // No se aplicó el middleware 'ability:' que recomendaba la auditoría porque
        // la lista además estaba DESINCRONIZADA del modelo real de permisos: el rol
        // franquiciante no declaraba manual:crear / manual:editar / manual:publicar /
        // manual:archivar, pero sí ejerce esas rutas (api.php, grupo
        // role:super_admin,franquiciante). Aplicarla habría dejado a todos los
        // franquiciantes sin poder publicar.
        //
        // La autorización real vive en dos lugares, y en ningún otro:
        //   1. middleware 'role:' en routes/api.php  → qué roles llegan a la ruta
        //   2. Policies (ManualPolicy, DocumentPolicy) → qué recurso puede tocar
        //
        // Reconstruir las abilities como defensa en profundidad queda como mejora
        // futura; para no volver a desincronizarse tendrían que derivarse de la
        // misma fuente de verdad que las Policies.
        $token = $user->createToken(
            name:      'auth_token',
            abilities: ['*'],
            expiresAt: now()->addHours(8),
        )->plainTextToken;

        ActivityLog::registrar(
            userId:    $user->id,
            accion:    'login',
            ip:        $request->ip(),
            empresaId: $user->empresa_id,
            userAgent: $request->userAgent()
        );

        // H-010 fix: el flag Secure ahora se lee de la config de sesión (no
        // hardcodeado en false). Con fail-secure default true en config/session.php,
        // si SESSION_SECURE_COOKIE no está en el .env, la cookie sale con Secure=true.
        // En dev (XAMPP HTTP) hay que definir SESSION_SECURE_COOKIE=false en el .env
        // local, sino el navegador no envía la cookie por HTTP.
        //
        // Argumentos de cookie(): $name, $value, $minutes, $path, $domain,
        //                         $secure, $httpOnly, $raw, $sameSite
        return response()->json([
            'rol'    => $user->rol,
            'perfil' => $perfil,
        ])->cookie(
            'auth_token',
            $token,
            60 * 8,
            '/',
            null,
            (bool) config('session.secure'),  // Secure: leído de config
            true,                              // HttpOnly: bloquea acceso desde JS
            false,                             // raw: false → valor URL-encoded
            'Strict'                           // SameSite: mitigación CSRF
        );
    }

    public function logout(Request $request): JsonResponse
    {
        ActivityLog::registrar(
            userId:    $request->user()->id,
            accion:    'logout',
            ip:        $request->ip(),
            empresaId: $request->user()->empresa_id,
            userAgent: $request->userAgent()
        );

        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesión cerrada correctamente.'])
            ->withoutCookie('auth_token');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        $perfil = match($user->rol) {
            'super_admin'   => $user->superAdmin,
            'franquiciante' => $user->systemAdmin,
            'franquiciado'  => $user->franchiseStaff?->load('franquicia'),
            'empleado'      => $user->franchiseStaff?->load('franquicia'),
        };

        $notificacionesPendientes = $user->notifications()
                                         ->noLeidas()
                                         ->count();

        $empresa = null;
        if ($user->rol === 'franquiciante' && $user->empresa_id) {
            $empresa = \App\Models\Empresa::with('plan')->find($user->empresa_id);
        }

        // v2.3: nombre/apellido/dni/celular ahora viven en users — exponerlos al toplevel.
        // El campo `perfil` se mantiene por compat (incluye franquicia para franq/empleado).
        return response()->json([
            'id'                        => $user->id,
            'email'                     => $user->email,
            'rol'                       => $user->rol,
            'nombre'                    => $user->nombre,
            'apellido'                  => $user->apellido,
            'cuit'                       => $user->cuit,
            'celular'                   => $user->celular,
            'avatar_url'                => $user->avatar_url,
            'empresa_id'                => $user->empresa_id,
            'empresa'                   => $empresa,
            'perfil'                    => $perfil,
            'notificaciones_pendientes' => $notificacionesPendientes,
        ]);
    }

    public function updateEmail(Request $request)
    {
        // El email del socio comercial es su identificacion legal en la red: es
        // la direccion a la que llegan las notificaciones y la que queda
        // asociada a cada aceptacion. Si el propio usuario puede cambiarla, se
        // corta la cadena entre "esta persona acepto la version 2.1" y "esta
        // persona es quien decimos que es".
        //
        // Pasa a ser administrado: lo cambia un super_admin o el franquiciante
        // desde usuarios.php, y esa operacion queda registrada.
        //
        // El empleado entra en la misma regla: su cuenta la crea y administra
        // la franquicia, no el.
        //
        // Esto es la restriccion REAL. perfil.php ademas oculta la tarjeta,
        // pero eso es cosmetico: sin este guard, el endpoint sigue abierto
        // desde la consola. email_no_autoadministrable
        if (in_array($request->user()->rol, ['franquiciado', 'empleado'], true)) {
            return response()->json([
                'error' => 'Tu email no se puede modificar desde acá. Pedíselo al administrador de tu red.',
            ], 403);
        }

        $request->validate([
            'email'    => 'required|email|max:200|unique:users,email,' . $request->user()->id,
            'password' => 'required|string',
        ]);

        $user = $request->user();

        // H-024: registrar intento fallido de cambio de credenciales — señal de
        // brute-force o de sesión comprometida intentando tomar la cuenta.
        if (!Hash::check($request->password, $user->password_hash)) {
            try {
                ActivityLog::registrar(
                    userId:    $user->id,
                    accion:    'email_actualizado_fallo',
                    ip:        $request->ip(),
                    empresaId: $user->empresa_id,
                    userAgent: $request->userAgent()
                );
            } catch (\Throwable $e) { /* best-effort */ }

            return response()->json(['message' => 'La contraseña actual es incorrecta.'], 422);
        }

        $user->update(['email' => $request->email]);

        // H-024: log de éxito
        try {
            ActivityLog::registrar(
                userId:    $user->id,
                accion:    'email_actualizado',
                ip:        $request->ip(),
                empresaId: $user->empresa_id,
                userAgent: $request->userAgent()
            );
        } catch (\Throwable $e) { /* best-effort */ }

        return response()->json(['message' => 'Email actualizado correctamente.']);
    }

    // PUT /api/me/cuit
    //
    // Mismo criterio que updateEmail: el socio comercial y el empleado no
    // administran sus propios datos de identidad. Su cuenta la crea y la
    // gestiona la franquicia.
    //
    // Esto es la restriccion REAL. perfil.php ademas oculta la tarjeta, pero
    // eso es cosmetico: sin este guard el endpoint sigue abierto desde la
    // consola.
    //
    // A DIFERENCIA de updateEmail, NO pide la contrasena. El email es la
    // credencial de login —cambiarlo es tomar el control de la cuenta— y el
    // CUIT no abre ninguna puerta. Lo que si lleva es confirmacion explicita
    // en la pantalla: es un dato que va en la facturacion y un error de tipeo
    // no se nota hasta que alguien factura mal.
    public function updateCuit(Request $request): JsonResponse
    {
        $user = $request->user();

        if (in_array($user->rol, ['franquiciado', 'empleado'], true)) {
            return response()->json([
                'error' => 'Tu CUIT no se puede modificar desde acá. Pedíselo al administrador de tu red.',
            ], 403);
        }

        // La regla Cuit valida el digito verificador con modulo 11, igual que
        // en UserController. Eso descarta los errores de tipeo, que son la
        // mayoria; verificar que el CUIT EXISTA y a nombre de quien necesita
        // ARCA y sigue pendiente.
        $data = $request->validate([
            'cuit' => ['required', 'string', 'max:15', new Cuit],
        ]);

        $anterior = $user->cuit;

        // cuit SI esta en $fillable (no es un campo de privilegio), asi que
        // update() alcanza.
        $user->update(['cuit' => $data['cuit']]);

        // Solo claves permitidas por chk_detalle_schema y maximo 5.
        try {
            ActivityLog::registrar(
                userId:    $user->id,
                accion:    'cuit_actualizado',
                ip:        $request->ip(),
                empresaId: $user->empresa_id,
                detalle:   [
                    'campo'          => 'cuit',
                    'valor_anterior' => $anterior ?? '',
                    'valor_nuevo'    => $data['cuit'],
                ],
                userAgent: $request->userAgent()
            );
        } catch (\Throwable $e) { /* best-effort */ }

        return response()->json([
            'message' => 'CUIT actualizado correctamente.',
            'cuit'    => $user->cuit,
        ]);
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password'      => 'required|string',
            'password'              => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required|string',
        ]);

        $user = $request->user();

        // H-024: registrar intento fallido — señal de brute-force o de sesión
        // comprometida intentando cambiar la contraseña.
        if (!Hash::check($request->current_password, $user->password_hash)) {
            try {
                ActivityLog::registrar(
                    userId:    $user->id,
                    accion:    'password_actualizada_fallo',
                    ip:        $request->ip(),
                    empresaId: $user->empresa_id,
                    userAgent: $request->userAgent()
                );
            } catch (\Throwable $e) { /* best-effort */ }

            return response()->json(['message' => 'La contraseña actual es incorrecta.'], 422);
        }

        // H-015: password_hash está fuera del $fillable, se setea con setter directo.
        $user->password_hash = Hash::make($request->password);
        $user->save();

        // H-012: revocar TODAS las sesiones activas del usuario excepto la
        // actual. Si un atacante ya tenía una cookie/token robado y el usuario
        // cambia la contraseña como respuesta, la sesión del atacante debe
        // invalidarse — sino el cambio de contraseña no protege nada.
        //
        // Mantenemos la sesión actual (con la que se está haciendo este pedido)
        // porque si no, el usuario legítimo también se cerraría al terminar
        // este endpoint. currentAccessToken puede devolver null en algunos
        // flujos raros — el null-check evita error y en ese caso revocamos
        // todos los tokens (peor UX pero más seguro).
        try {
            $currentToken = $user->currentAccessToken();
            if ($currentToken) {
                $user->tokens()->where('id', '!=', $currentToken->id)->delete();
            } else {
                $user->tokens()->delete();
            }
        } catch (\Throwable $e) {
            // Si Sanctum no está configurado como se espera, no bloqueamos el
            // cambio de password — el log ya se hizo.
        }

        // H-024: log de éxito
        try {
            ActivityLog::registrar(
                userId:    $user->id,
                accion:    'password_actualizada',
                ip:        $request->ip(),
                empresaId: $user->empresa_id,
                userAgent: $request->userAgent()
            );
        } catch (\Throwable $e) { /* best-effort */ }

        return response()->json(['message' => 'Contraseña actualizada correctamente.']);
    }
    /**
     * H-014 fix: registra un intento fallido de login para trazabilidad y
     * detección de brute-force. Se llama antes de devolver 401/403.
     *
     * userId puede ser null cuando el email no existe. En ese caso solo queda
     * el registro con ip y user_agent — útil para detectar spray attacks.
     *
     * Envuelto en try/catch: si el log falla (ej: FK con user_id inválido),
     * no bloqueamos la respuesta al usuario.
     */
    private function logLoginFallido(Request $request, ?int $userId, ?int $empresaId, string $accion): void
    {
        try {
            ActivityLog::registrar(
                userId:    $userId,
                accion:    $accion,
                ip:        $request->ip(),
                empresaId: $empresaId,
                userAgent: $request->userAgent()
            );
        } catch (\Throwable $e) {
            // best-effort
        }
    }
}