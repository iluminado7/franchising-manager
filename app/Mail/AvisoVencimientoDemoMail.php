<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Aviso a un super_admin: la prueba de una empresa demo esta por vencer.
 *
 * Lo manda el comando demos:avisar-vencimiento, con send() y NO encolado:
 * el comando ya corre fuera de cualquier request (lo dispara el cron), y
 * necesita saber si el envio salio bien para marcar el aviso como enviado.
 * Encolado, el comando lo marcaria aunque el mail fallara despues en el worker,
 * y el aviso se perderia sin que nadie se entere.
 *
 * Por eso tampoco implementa ShouldQueue: con esa interfaz, send() lo
 * encolaria igual.
 *
 * Recibe datos planos y no modelos: el mail describe la empresa tal como
 * estaba al momento del aviso.
 */
class AvisoVencimientoDemoMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param array{nombre:string, razon_social:string, cuit:string} $empresa
     * @param array<int, array{nombre:string, email:string, celular:?string}> $franquiciantes
     * @param string[] $emailsContacto
     * @param array{socios:int, empleados:int, manuales:int, documentos:int} $uso
     */
    public function __construct(
        public string $nombreDestinatario,
        public string $asunto,
        public string $cuandoVence,
        public string $fechaVence,
        public array $empresa,
        public array $franquiciantes,
        public array $emailsContacto,
        public array $uso,
        public string $urlEmpresas,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->asunto);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.aviso-vencimiento-demo');
    }
}
