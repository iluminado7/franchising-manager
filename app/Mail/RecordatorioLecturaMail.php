<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Recordatorio al socio comercial de los manuales que todavía no leyó.
 *
 * Lo manda el comando manuales:recordar-lectura con send() y NO encolado: el
 * comando necesita saber si el mail salió para registrar el recordatorio. Si
 * se encolara, lo registraría aunque el envío fallara después en el worker, y
 * ese manual no se recordaría nunca. Por eso tampoco implementa ShouldQueue.
 *
 * El texto NO menciona cuánto tiempo pasó sin leer: se pidió un recordatorio,
 * no un reproche. Solo "aún te falta leer" y la lista.
 */
class RecordatorioLecturaMail extends Mailable
{
    /**
     * @param string[] $titulos Títulos de los manuales pendientes, ya con la
     *                          versión si no es la 1.0.
     */
    public function __construct(
        public string $nombre,
        public array $titulos,
        public string $url,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: count($this->titulos) === 1
                ? 'Aún te falta leer un manual'
                : 'Aún te falta leer algunos manuales',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.recordatorio-lectura');
    }
}
