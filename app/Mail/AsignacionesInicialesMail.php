<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Aviso al socio comercial de lo que puede ver, al quedar asignado.
 *
 * UN SOLO Mailable para manuales y documentos. La decisión de QUÉ mandar ya la
 * tomó UserController::sincronizarCategorias(); acá solo se traduce el tipo a
 * asunto, título y destino. Dos clases para cambiar tres strings sería peor.
 *
 * Mismo criterio que PasswordResetMail, que cubre cuatro motivos con una clase.
 *
 * ── ESTE SÍ PUEDE ENCOLARSE ──────────────────────────────────────────────
 *
 * A diferencia de AltaUsuarioMail, acá no viaja ninguna credencial: solo una
 * lista de títulos. Aun así se manda sincrónicamente junto al de alta, para
 * que los tres correos lleguen en orden y el socio no reciba "estos son tus
 * manuales" antes que "se creó tu cuenta".
 *
 * ── NO SE MANDA SI LA LISTA ESTÁ VACÍA ───────────────────────────────────
 *
 * El guard vive en el controlador, no acá. "Estos son los manuales que vas a
 * ver" seguido de nada es peor que no escribir — y es la regla que se pidió:
 * si la categoría no tiene manuales, no hay correo.
 */
class AsignacionesInicialesMail extends Mailable
{
    use SerializesModels;

    /**
     * @param string   $tipo    'manuales' | 'documentos'
     * @param string[] $titulos Solo los nombres. No se mandan ids ni enlaces
     *                          por elemento: el correo invita a entrar al
     *                          sistema, no reemplaza la pantalla.
     */
    public function __construct(
        public string $nombre,
        public string $tipo,
        public array $titulos,
        public string $urlSeccion,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->asunto());
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.asignaciones-iniciales',
            with: [
                'nombre'  => $this->nombre,
                'titulo'  => $this->titulo(),
                'intro'   => $this->intro(),
                'titulos' => $this->titulos,
                'url'     => $this->urlSeccion,
                'boton'   => $this->tipo === 'manuales' ? 'Ver mis manuales' : 'Ver mis documentos',
            ],
        );
    }

    private function asunto(): string
    {
        return $this->tipo === 'manuales'
            ? 'Tus manuales en Business Partner'
            : 'Tus documentos en Business Partner';
    }

    private function titulo(): string
    {
        // Singular y plural: "1 manuales" en el primer correo que recibe
        // alguien del sistema se lee como un descuido.
        $n = count($this->titulos);

        if ($this->tipo === 'manuales') {
            return $n === 1 ? 'Tenés 1 manual asignado' : "Tenés {$n} manuales asignados";
        }

        return $n === 1 ? 'Tenés 1 documento asignado' : "Tenés {$n} documentos asignados";
    }

    private function intro(): string
    {
        return $this->tipo === 'manuales'
            ? 'Se creó tu cuenta en Business Partner. Estos son los manuales operativos que vas a ver al ingresar:'
            : 'Se creó tu cuenta en Business Partner. Estos son los documentos que vas a ver al ingresar:';
    }
}