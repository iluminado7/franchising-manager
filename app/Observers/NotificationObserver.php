<?php

namespace App\Observers;

use App\Models\Notification;
use App\Models\User;
use App\Mail\NotificacionMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

/**
 * Observer de Notification: envia por email (encolado) las notificaciones
 * relevantes, de forma CENTRALIZADA. Cualquier Notification::create(...) de
 * cualquier controller dispara el mail automaticamente — no hay que tocar los
 * controllers uno por uno.
 *
 * Solo se envian los tipos de la whitelist (asignado / actualizado / nuevo, de
 * manuales y documentos). El resto (recordatorio_pendiente, etc.) NO manda mail.
 */
class NotificationObserver
{
    // Tipos que disparan email. El resto solo queda como notificacion in-app.
    private const TIPOS_CON_EMAIL = [
        // Nuevos
        'nuevo_manual',
        'nuevo_documento',
        // Asignados (individual + por categoria)
        'manual_asignado',
        'manual_asignado_categoria',
        'documento_asignado',
        'documento_asignado_categoria',
        // Actualizados
        'modificacion_manual',
        'nueva_version_documento',
        // Alerta de seguridad: un socio pidio el archivo de un manual PDF por
        // fuera del visor. Va por mail porque el destinatario (franquiciante /
        // super_admin) no vive mirando el panel.
        'acceso_anomalo_pdf',
    ];

    public function created(Notification $notificacion): void
    {
        if (!in_array($notificacion->tipo, self::TIPOS_CON_EMAIL, true)) {
            return;
        }

        $user = User::find($notificacion->user_id);

        // No mandar si no hay email o la cuenta esta inactiva/eliminada.
        if (!$user || empty($user->email) || !$user->activo || $user->deleted_at !== null) {
            return;
        }

        // Tampoco mandar si la empresa o la sucursal estan suspendidas: esos
        // usuarios no pueden ni loguearse (ver AuthController::login), asi que
        // recibir mails de manuales que no pueden abrir seria incoherente.
        // El super_admin nunca se bloquea (no tiene empresa).
        if (!$user->esSuperAdmin()) {
            $empresaSuspendida = $user->empresa && !$user->empresa->activa;
            if ($empresaSuspendida) {
                return;
            }

            $franquicia = optional($user->franchiseStaff)->franquicia;
            if ($franquicia && !$franquicia->activa) {
                return;
            }
        }

        $nombre = trim("{$user->nombre} {$user->apellido}") ?: 'usuario';

        // URL del frontend. Definir FRONTEND_URL en el .env si el frontend vive en
        // un subpath (ej. http://localhost/manuales-franquiciantes/public).
        $base = rtrim((string) (config('app.frontend_url') ?: config('app.url')), '/');
        // Las alertas de acceso llevan al registro de actividad, no al panel:
        // lo que el destinatario quiere ver es QUIEN accedio y cuando.
        $url  = $notificacion->tipo === 'acceso_anomalo_pdf'
            ? $base . '/log.php'
            : $base . '/dashboard.php';

        try {
            Mail::to($user->email)->queue(
                new NotificacionMail($notificacion, $nombre, $url)
            );
        } catch (\Throwable $e) {
            // Best-effort: si falla el encolado, la notificacion in-app ya quedo
            // guardada. No rompemos la operacion que la origino.
            Log::warning('NotificationObserver: no se pudo encolar el mail de notificacion', [
                'notification_id' => $notificacion->id ?? null,
                'user_id'         => $notificacion->user_id ?? null,
                'error'           => $e->getMessage(),
            ]);
        }
    }
}