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
        // Nota de un socio comercial. Va por mail porque el franquiciante no
        // vive mirando el panel, y una sugerencia sin leer no sirve de nada.
        // El super_admin queda excluido mas abajo: la recibe solo in-app.
        'nota_manual',
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

        // El super_admin recibe las notas de los socios comerciales SOLO por
        // la campanita. Le llegan las de TODAS las empresas de la plataforma:
        // por mail seria un goteo permanente en la casilla. Y la asimetria es
        // deliberada — al franquiciante la nota le pide una accion sobre su
        // propia red, al super_admin lo mantiene informado.
        //
        // El corte va por tipo + rol, y NO sacando 'nota_manual' de la
        // whitelist: el franquiciante si tiene que recibir ese mail. Tampoco
        // se resuelve dejando de crear la notificacion, porque la campanita
        // es justamente lo que se quiere conservar.
        //
        // OJO: esto no aplica a 'acceso_anomalo_pdf'. Esa es una alerta de
        // seguridad y el mail al super_admin es parte del punto.
        if ($notificacion->tipo === 'nota_manual' && $user->esSuperAdmin()) {
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
        // Cada tipo al lugar donde se resuelve, no al panel generico:
        //   acceso_anomalo_pdf -> el registro dice QUIEN accedio y cuando
        //   nota_manual        -> el listado, donde se abre el hilo de notas
        $url = match ($notificacion->tipo) {
            'acceso_anomalo_pdf' => $base . '/log.php',
            // El super_admin no tiene una 'mi empresa': su listado es
            // manuales.php. Mismo criterio que la version in-app, que lo
            // resuelve en NotificationController::resolverDestino().
            //
            // Hoy esta rama no se alcanza — el guard de arriba corta el mail
            // de 'nota_manual' al super_admin antes de llegar aca. Se deja a
            // proposito: si algun dia se levanta esa exclusion, el link ya
            // apunta a donde tiene que apuntar y no a una pantalla ajena.
            'nota_manual'        => $base . ($user->esSuperAdmin()
                                        ? '/manuales.php'
                                        : '/manuales-mi-empresa.php'),
            default              => $base . '/dashboard.php',
        };

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