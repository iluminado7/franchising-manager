<?php

namespace App\Http\Controllers;

use App\Http\Concerns\VerificaTurnstile;
use App\Jobs\EnviarRecuperacionPassword;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Recuperación de contraseña por email.
 *
 * ── LA REGLA QUE GOBIERNA TODO ESTE CONTROLADOR ───────────────────────────
 *
 * La respuesta HTTP de solicitar() es SIEMPRE IDÉNTICA: exista el email o no,
 * esté la cuenta activa o suspendida. El motivo específico viaja POR MAIL, que
 * solo le llega a quien controla esa casilla.
 *
 * Si la pantalla dijera "usuario suspendido", cualquiera que escriba un email
 * averiguaría si esa persona está en el sistema; y con "sucursal suspendida",
 * además a qué empresa pertenece. Eso es enumeración — justo lo que se
 * instrumentó detectar al hacer nullable activity_logs.user_id.
 *
 * Es el mismo criterio que ya aplica AuthController::login(): ahí el mensaje
 * específico de empresa suspendida aparece RECIÉN DESPUÉS de validar la
 * contraseña, o sea solo a quien demostró ser dueño de la cuenta. Acá no hay
 * contraseña que validar, así que la única forma equivalente de demostrarlo es
 * recibir el mail.
 *
 * ── OTRAS DECISIONES ──────────────────────────────────────────────────────
 *
 * El token viaja en la URL pero se guarda HASHEADO: vale tanto como la
 * contraseña, y en claro cualquier dump de la base daría acceso a todas las
 * cuentas.
 *
 * Al restablecer se revocan TODAS las sesiones del usuario. Si alguien recupera
 * la contraseña puede ser porque perdió el control de la cuenta; dejarle las
 * sesiones vivas al atacante vaciaría de sentido el cambio. Efecto conocido:
 * quien lo haga desde el celular se cierra la sesión de la computadora.
 */
class PasswordResetController extends Controller
{
    use VerificaTurnstile;

    // POST /api/password/solicitar
    //
    // Valida el formato, despacha el job y responde. NADA MAS.
    //
    // Toda la logica vive en EnviarRecuperacionPassword a proposito: si el
    // controlador buscara el usuario y generara el token, el email inexistente
    // volveria enseguida y el real tardaria decenas de milisegundos mas. Esa
    // diferencia es MEDIBLE, y permite enumerar quien esta en el sistema aunque
    // todas las respuestas digan lo mismo.
    //
    // Despachando siempre, el tiempo es constante: no depende de si el usuario
    // existe.
    public function solicitar(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email|max:200']);

        // Anti-bot. Va ANTES del dispatch: despues, el job ya estaria encolado
        // y el mail saldria igual.
        //
        // Y DESPUES del validate() del email, para que una direccion mal escrita
        // no queme una verificacion: el token de Turnstile es de un solo uso.
        //
        // Este endpoint manda MAILS A TERCEROS. Sin esto, el widget de
        // recuperar.html seria decorativo: un bot que postea directo se lo
        // saltea entero y el unico freno seria el rate limiter.
        $this->verificarTurnstile($request);

        EnviarRecuperacionPassword::dispatch(
            $request->email,
            $request->ip(),
            $request->userAgent()
        );

        return response()->json([
            'message' => 'Si el email está registrado, vas a recibir las instrucciones en unos minutos.',
        ]);
    }

    // GET /api/password/validar/{token}
    //
    // La pantalla lo llama al abrir, para no mostrar el formulario si el enlace
    // ya venció o se usó. NO devuelve datos del usuario: solo si sirve o no.
    public function validar(string $token): JsonResponse
    {
        $fila = $this->buscarTokenVigente($token);

        if (!$fila) {
            return response()->json([
                'valido' => false,
                'error'  => 'El enlace no es válido, ya se usó o venció. Pedí uno nuevo.',
            ], 404);
        }

        return response()->json(['valido' => true]);
    }

    // POST /api/password/restablecer
    public function restablecer(Request $request): JsonResponse
    {
        $request->validate([
            'token'                 => 'required|string',
            'password'              => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required|string',
        ]);

        $fila = $this->buscarTokenVigente($request->token);

        if (!$fila) {
            return response()->json([
                'error' => 'El enlace no es válido, ya se usó o venció. Pedí uno nuevo.',
            ], 422);
        }

        $user = User::find($fila->user_id);
        if (!$user || $user->deleted_at !== null) {
            return response()->json(['error' => 'El enlace no es válido.'], 422);
        }

        // Se marca usado ANTES de tocar la contraseña: si algo fallara después,
        // el token queda quemado igual. Un token que sobrevive a un error es
        // peor que un reseteo a medias.
        DB::table('password_resets')
          ->where('id', $fila->id)
          ->update(['usado_at' => now()]);

        // password_hash está fuera del $fillable (H-015): setter directo.
        $user->password_hash = Hash::make($request->password);
        $user->save();

        // TODAS las sesiones, sin excepción: acá no hay una sesión "actual" del
        // usuario que preservar, y si recuperó la contraseña puede ser porque
        // alguien más tenía acceso.
        try {
            $user->tokens()->delete();
        } catch (\Throwable $e) { /* best-effort */ }

        $this->registrar($user, 'password_reset_completado', $request);

        return response()->json([
            'message' => 'Contraseña actualizada. Ya podés iniciar sesión.',
        ]);
    }

    // ── PRIVADOS ─────────────────────────────────────────────────────

    /**
     * Busca el token por su HASH. Devuelve la fila solo si está sin usar y no
     * venció; null en cualquier otro caso.
     *
     * Se compara el hash y no el token en claro porque en la base solo está el
     * hash — es lo que impide que un dump dé acceso a las cuentas.
     */
    private function buscarTokenVigente(string $token): ?object
    {
        return DB::table('password_resets')
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('usado_at')
            ->where('expira_at', '>', now())
            ->first();
    }

    /**
     * Registra en activity_logs. Best-effort: el reseteo no puede fallar porque
     * falle el log.
     *
     * Solo lo usa restablecer(): el resto de los registros los hace el job.
     */
    private function registrar(User $user, string $accion, Request $request): void
    {
        try {
            ActivityLog::registrar(
                userId:      $user->id,
                accion:      $accion,
                ip:          $request->ip(),
                empresaId:   $user->empresa_id,
                entidadTipo: 'users',
                entidadId:   $user->id,
                detalle:     null,
                userAgent:   $request->userAgent()
            );
        } catch (\Throwable $e) { /* best-effort */ }
    }
}