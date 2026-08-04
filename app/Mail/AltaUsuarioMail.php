<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Correo de alta: le avisa al usuario que tiene cuenta y le entrega sus
 * credenciales, con el manual de usuario adjunto.
 *
 * ⚠️ NO USA Queueable NI SerializesModels, Y ES A PROPOSITO.
 *
 * Este mail lleva la contrasena EN TEXTO PLANO. Si se encolara, Laravel
 * serializa el Mailable entero en la tabla `jobs` — o sea que la contrasena
 * quedaria guardada en la base hasta que el worker la procese. Y si el job
 * fallara tres veces, va a `failed_jobs`, que NO SE LIMPIA NUNCA y nadie mira.
 *
 * Sin el trait Queueable, Mail::queue() sobre esta clase no funciona como se
 * esperaria: hay que mandarlo con Mail::send(), que es sincrono. Es una
 * barrera contra el descuido, no solo una convencion.
 *
 * El costo es ~1 segundo agregado al alta de un usuario. Es un mail por alta,
 * no un envio masivo.
 *
 * ── SOBRE MANDAR LA CONTRASENA POR MAIL ──────────────────────────────────
 *
 * Es una decision tomada a sabiendas. El mail queda en la casilla del usuario
 * para siempre, pasa por servidores intermedios y sobrevive en sus backups. Si
 * dentro de dos anos le comprometen el correo, la contrasena esta ahi.
 *
 * El control compensatorio es operativo: se verifica en activity_logs quien
 * ejecuto 'password_actualizada' y se le reclama por fuera del sistema a quien
 * no lo hizo. Por eso el cuerpo del mail insiste con cambiarla.
 *
 * La alternativa mas segura seria un enlace de un solo uso —el mecanismo de
 * password_resets ya existe— pero se opto por esto.
 */
class AltaUsuarioMail extends Mailable
{
    public function __construct(
        public string $nombre,
        public string $email,
        public string $password,
        public string $urlLogin,
        public string $rolEtiqueta,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Tu acceso a Business Partner by GoHarv');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.alta-usuario',
            with: [
                'nombre'   => $this->nombre,
                'email'    => $this->email,
                'password' => $this->password,
                'url'      => $this->urlLogin,
                'rol'      => $this->rolEtiqueta,
            ],
        );
    }

    /**
     * Manual de usuario adjunto.
     *
     * Vive en resources/, NO en public/: desde public/ cualquiera podria
     * bajarlo sin autenticarse. Y en resources/ viaja con el git pull, sin
     * paso de subida ni dependencia de S3.
     *
     * SI EL ARCHIVO NO ESTA, EL MAIL SALE IGUAL, SIN ADJUNTO.
     *
     * Es deliberado: que alguien mueva o renombre el PDF no puede impedir que
     * el usuario reciba sus credenciales. Perder el manual adjunto es molesto;
     * perder el mail de alta deja a una persona sin saber que tiene cuenta.
     */
    public function attachments(): array
    {
        $ruta = resource_path('manuales/manual-usuario.pdf');

        if (!is_file($ruta) || !is_readable($ruta)) {
            return [];
        }

        return [
            Attachment::fromPath($ruta)
                      ->as('Manual de usuario - Business Partner.pdf')
                      ->withMime('application/pdf'),
        ];
    }
}