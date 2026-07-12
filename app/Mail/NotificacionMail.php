<?php

namespace App\Mail;

use App\Models\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email de una notificacion personalizada al usuario.
 *
 * Implementa ShouldQueue: NO se envia dentro del request, se encola (cola
 * 'database') y lo procesa el worker (php artisan queue:work). Asi la accion
 * que dispara la notificacion (asignar manual, publicar version, etc.) responde
 * al instante y el mail se manda en segundo plano.
 *
 * Se dispara desde NotificationObserver::created, solo para los tipos elegidos.
 */
class NotificacionMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Notification $notificacion,
        public string $nombreDestinatario,
        public string $urlPlataforma,
    ) {}

    public function envelope(): Envelope
    {
        // El titulo de la notificacion ya es descriptivo ("Se te asignó el manual: X").
        return new Envelope(
            subject: $this->notificacion->titulo ?: 'Tenés una nueva notificación',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.notificacion',
            with: [
                'titulo'  => $this->notificacion->titulo ?: 'Nueva notificación',
                'mensaje' => $this->notificacion->mensaje,
                'nombre'  => $this->nombreDestinatario,
                'url'     => $this->urlPlataforma,
            ],
        );
    }
}