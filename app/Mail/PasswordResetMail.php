<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Correos de recuperación de contraseña.
 *
 * UN SOLO Mailable para los cuatro casos, porque la decisión de QUÉ mandar ya
 * la tomó PasswordResetController: acá solo se traduce el motivo a asunto y
 * texto. Tener cuatro clases para cambiar dos strings sería peor.
 *
 *   'enlace'                -> con botón al formulario de nueva contraseña
 *   'cuenta_suspendida'     -> sin botón, explica el motivo
 *   'empresa_suspendida'    -> idem
 *   'franquicia_suspendida' -> idem
 *
 * Los tres últimos existen porque la PANTALLA no puede decir el motivo (sería
 * enumeración: cualquiera averiguaría quién está en el sistema escribiendo
 * emails). El mail sí, porque solo le llega a quien controla esa casilla.
 *
 * Va encolado (implements ShouldQueue vía el trait Queueable + Mail::queue en
 * el controlador), igual que NotificacionMail.
 */
class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $nombre,
        public string $motivo,
        public ?string $url = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->asunto());
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.password-reset',
            with: [
                'titulo'  => $this->titulo(),
                'nombre'  => $this->nombre,
                'mensaje' => $this->mensaje(),
                'url'     => $this->url,
            ],
        );
    }

    private function asunto(): string
    {
        return match ($this->motivo) {
            'enlace' => 'Recuperá tu contraseña — Business Partner',
            default  => 'No se puede recuperar la contraseña — Business Partner',
        };
    }

    private function titulo(): string
    {
        return match ($this->motivo) {
            'enlace'                => 'Restablecé tu contraseña',
            'cuenta_suspendida'     => 'Tu cuenta está suspendida',
            'empresa_suspendida'    => 'Tu empresa está suspendida',
            'franquicia_suspendida' => 'Tu sucursal está suspendida',
            default                 => 'Recuperación de contraseña',
        };
    }

    private function mensaje(): string
    {
        return match ($this->motivo) {
            'enlace' =>
                'Recibimos un pedido para restablecer la contraseña de tu cuenta. '
                . 'El enlace vence en 60 minutos y sirve una sola vez. '
                . 'Al cambiarla se van a cerrar todas tus sesiones abiertas. '
                . 'Si no fuiste vos, ignorá este correo: tu contraseña no cambió.',

            'cuenta_suspendida' =>
                'Pediste restablecer tu contraseña, pero tu cuenta está suspendida, '
                . 'así que no podemos generar el enlace. Contactá al administrador '
                . 'de tu red para reactivarla.',

            'empresa_suspendida' =>
                'Pediste restablecer tu contraseña, pero la empresa a la que pertenece '
                . 'tu cuenta está suspendida. El acceso depende '
                . 'de que se reactive la empresa. Contactá al administrador.',

            'franquicia_suspendida' =>
                'Pediste restablecer tu contraseña, pero tu sucursal está suspendida. '
                . 'El acceso depende de que se reactive la '
                . 'sucursal. Contactá al administrador.',

            default => 'Recibimos un pedido relacionado con tu contraseña.',
        };
    }
}