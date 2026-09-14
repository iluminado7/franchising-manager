<?php

namespace App\Services;

use App\Models\Empresa;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Límite de mails por día hacia los usuarios de una empresa demo
 * (Empresa::DEMO_MAILS_POR_DIA).
 *
 * ── POR QUÉ EXISTE ────────────────────────────────────────────────────────
 *
 * Una demo se le da a alguien de afuera con permisos de franquiciante, y ese
 * rol puede disparar mails a direcciones que elige él: crea usuarios con
 * cualquier email, les cambia el email a sus socios, y cada alta, cada
 * asignación y cada publicación manda un mail. Con títulos de manuales que
 * también escribe él. Sin límite, se puede usar la plataforma para mandar
 * cientos de mails desde el dominio, y si Resend o los proveedores lo marcan
 * como spam, dejan de llegarles los mails también a los clientes reales.
 *
 * ── POR QUÉ ACÁ Y NO EN CADA DISPARADOR ───────────────────────────────────
 *
 * Los disparadores son muchos (alta, asignaciones iniciales, notificaciones
 * del observer, recuperar contraseña) y van a aparecer más. Cerrarlos de a uno
 * deja abiertos los que se agreguen después. MessageSending pasa por TODO mail
 * que sale, encolado o no: devolver false lo cancela (Mailer::shouldSendMessage).
 *
 * Se registra en AppServiceProvider y no como clase en app/Listeners: Laravel
 * descubre esa carpeta solo, y un registro manual adicional contaría cada mail
 * dos veces.
 *
 * ── CÓMO CUENTA ───────────────────────────────────────────────────────────
 *
 * Por empresa demo del DESTINATARIO, en ventanas de 24 h (RateLimiter, en el
 * cache: CACHE_STORE=database en producción, compartido entre el worker y
 * PHP-FPM). Solo cuentan los mails que efectivamente se permiten. Los que van a
 * super_admin o a empresas que no son demo no cuentan ni se frenan nunca.
 *
 * Un mail cancelado no lanza excepción: send() devuelve null. Quien necesite
 * saber si salió tiene que mirar ese retorno (UserController::store lo hace).
 */
class LimiteMailsDemo
{
    public static function permitir(MessageSending $evento): bool
    {
        $emails = array_map(
            fn ($a) => strtolower($a->getAddress()),
            array_merge(
                $evento->message->getTo(),
                $evento->message->getCc(),
                $evento->message->getBcc()
            )
        );
        if (!$emails) {
            return true;
        }

        $demos = DB::table('users')
            ->join('empresas', 'empresas.id', '=', 'users.empresa_id')
            // La collation de users.email (utf8mb4_unicode_ci) ya compara sin
            // distinguir mayúsculas: un LOWER() acá solo anularía el índice.
            ->whereIn('users.email', $emails)
            ->where('empresas.es_demo', 1)
            ->distinct()
            ->pluck('empresas.id')
            ->all();

        if (!$demos) {
            return true;
        }

        // Primero verificar todas, después contar: si el mail va a dos demos y
        // una está al tope, no se cuenta en la otra un mail que no sale.
        foreach ($demos as $empresaId) {
            if (RateLimiter::tooManyAttempts(self::clave($empresaId), Empresa::DEMO_MAILS_POR_DIA)) {
                self::registrarBloqueo($empresaId);
                return false;
            }
        }
        foreach ($demos as $empresaId) {
            RateLimiter::hit(self::clave($empresaId), 86400);
        }

        return true;
    }

    private static function clave(int $empresaId): string
    {
        return 'mails_demo:' . $empresaId;
    }

    // Un solo aviso en el log por ventana: si alguien está en un bucle, loguear
    // cada mail cancelado llenaría el log con miles de líneas iguales.
    private static function registrarBloqueo(int $empresaId): void
    {
        if (Cache::add('mails_demo_bloqueo_logueado:' . $empresaId, 1, 86400)) {
            Log::warning('Empresa demo al tope de mails diarios: se cancelan los envíos hasta que se renueve la ventana.', [
                'empresa_id' => $empresaId,
                'tope'       => Empresa::DEMO_MAILS_POR_DIA,
            ]);
        }
    }
}
