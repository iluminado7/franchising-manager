<?php

namespace App\Jobs;

use App\Models\ActivityLog;
use App\Models\User;
use App\Mail\PasswordResetMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Procesa una solicitud de recuperación de contraseña, FUERA del ciclo de
 * request.
 *
 * ── POR QUÉ UN JOB Y NO HACERLO EN EL CONTROLADOR ─────────────────────────
 *
 * El diseño se apoya en que la respuesta sea INDISTINGUIBLE exista o no la
 * cuenta. Con la lógica en el controlador eso se cumplía en el contenido pero
 * NO en el tiempo:
 *
 *   email inexistente -> una consulta y vuelve         (~pocos ms)
 *   email real        -> carga empresa y sucursal, invalida tokens viejos,
 *                        genera uno, inserta, encola mail   (~decenas de ms)
 *
 * Esa diferencia es medible, y un atacante que cronometre puede enumerar quién
 * está en el sistema aunque todas las respuestas digan lo mismo. Anula el
 * criterio que sostiene todo el flujo.
 *
 * Con el job, el controlador solo valida el formato y despacha: el tiempo pasa
 * a ser constante porque no depende de si el usuario existe.
 *
 * Efecto lateral bueno: si Resend está lento, no bloquea la petición.
 *
 * ── SIN REINTENTOS ────────────────────────────────────────────────────────
 *
 * $tries = 1 a propósito. Si el job falla a mitad de camino, reintentarlo podría
 * generar un SEGUNDO token y mandar un segundo mail, con el primero ya
 * invalidado. Es preferible que el usuario vuelva a pedirlo: es un clic, y no
 * deja tokens huérfanos ni correos duplicados.
 *
 * DEPENDE DEL WORKER: si no corre, no sale ningún mail y no hay error visible
 * (§10 del README). Lo supervisa supervisor, programa `businesspartner-worker`.
 */
class EnviarRecuperacionPassword implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Ver la nota de arriba: reintentar duplicaría tokens y correos. */
    public int $tries = 1;

    /** Minutos de vida del enlace. Mismo horizonte que el token de archivos. */
    private const VIGENCIA_MIN = 60;

    public function __construct(
        private string $email,
        private ?string $ip,
        private ?string $userAgent,
    ) {}

    public function handle(): void
    {
        $user = User::where('email', $this->email)
                    ->whereNull('deleted_at')
                    ->first();

        // Email inexistente: no se manda nada. Tampoco se registra en
        // activity_logs — user_id sería NULL y no aportaría más que el rastro
        // que ya deja el rate limiter.
        if (!$user) {
            return;
        }

        // Motivo de bloqueo, si lo hay. Mismo orden y criterio que
        // AuthController::login(). El super_admin nunca se bloquea.
        $bloqueo = null;
        if (!$user->activo) {
            $bloqueo = 'cuenta_suspendida';
        } elseif (!$user->esSuperAdmin()) {
            if ($user->empresa && !$user->empresa->activa) {
                $bloqueo = 'empresa_suspendida';
            } else {
                $franquicia = optional($user->franchiseStaff)->franquicia;
                if ($franquicia && !$franquicia->activa) {
                    $bloqueo = 'franquicia_suspendida';
                }
            }
        }

        if ($bloqueo) {
            // Se avisa POR MAIL, sin enlace: el usuario legítimo se entera de por
            // qué no puede entrar, y quien prueba emails ajenos no aprende nada
            // porque la pantalla nunca cambió.
            $this->enviar($user, $bloqueo, null);
            $this->registrar($user, 'password_reset_bloqueado', $bloqueo);
            return;
        }

        // Invalidar los enlaces anteriores. Si pidió tres veces, solo el último
        // sirve: reduce la ventana y evita que uno viejo reenviado por error
        // siga siendo válido.
        DB::table('password_resets')
            ->where('user_id', $user->id)
            ->whereNull('usado_at')
            ->update(['usado_at' => now()]);

        $token = Str::random(64);

        DB::table('password_resets')->insert([
            'user_id'      => $user->id,
            // Se guarda HASHEADO: el token vale tanto como la contraseña, y en
            // claro cualquier dump de la base daría acceso a todas las cuentas.
            'token_hash'   => hash('sha256', $token),
            'expira_at'    => now()->addMinutes(self::VIGENCIA_MIN),
            'usado_at'     => null,
            'creado_por'   => null,          // NULL = autoservicio
            'ip_solicitud' => $this->ip,
            'created_at'   => now(),
        ]);

        $this->enviar($user, 'enlace', $token);
        $this->registrar($user, 'password_reset_solicitado', null);
    }

    // ── PRIVADOS ─────────────────────────────────────────────────────

    /**
     * Envía el mail. Acá se usa send() y no queue(): esto YA está dentro de un
     * job encolado, así que encolar de nuevo agregaría un salto inútil.
     */
    private function enviar(User $user, string $motivo, ?string $token): void
    {
        $nombre = trim("{$user->nombre} {$user->apellido}") ?: 'usuario';

        // Mismo patrón que NotificationObserver: FRONTEND_URL cubre el caso de
        // que el frontend viva en un subpath.
        $base = rtrim((string) (config('app.frontend_url') ?: config('app.url')), '/');
        $url  = $token ? $base . '/restablecer.html?token=' . urlencode($token) : null;

        try {
            Mail::to($user->email)->send(new PasswordResetMail($nombre, $motivo, $url));
        } catch (\Throwable $e) {
            Log::warning('PasswordReset: no se pudo enviar el mail.', [
                'user_id' => $user->id,
                'motivo'  => $motivo,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    /**
     * chk_detalle_schema solo admite claves conocidas y máximo 5: se usa 'campo'
     * para el motivo del bloqueo, que es lo único que agrega información.
     */
    private function registrar(User $user, string $accion, ?string $motivo): void
    {
        try {
            ActivityLog::registrar(
                userId:      $user->id,
                accion:      $accion,
                ip:          $this->ip ?? '0.0.0.0',
                empresaId:   $user->empresa_id,
                entidadTipo: 'users',
                entidadId:   $user->id,
                detalle:     $motivo ? ['campo' => $motivo] : null,
                userAgent:   $this->userAgent
            );
        } catch (\Throwable $e) { /* best-effort */ }
    }
}