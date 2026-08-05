<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{ $titulo }}</title>
</head>
{{--
  Misma estructura que alta-usuario.blade.php y password-reset.blade.php:
  tabla de 520px, encabezado negro con el dorado, botón dorado.

  Sirve para manuales y para documentos. Lo que cambia lo decide el Mailable
  (título, intro, texto del botón); la vista no sabe de cuál de los dos se
  trata, así que un tipo nuevo no la toca.
--}}
<body style="margin:0; padding:0; background-color:#F2EFE9; font-family: Arial, Helvetica, sans-serif;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#F2EFE9; padding:32px 0;">
    <tr>
      <td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px; background-color:#FFFFFF; border-radius:12px; overflow:hidden; box-shadow:0 2px 12px rgba(0,0,0,0.06);">

          {{-- Encabezado --}}
          <tr>
            <td style="background-color:#1A1A1A; padding:22px 32px;">
              <span style="color:#C9A84C; font-size:18px; font-weight:bold; letter-spacing:0.02em;">Business Partner by GoHarv.</span>
            </td>
          </tr>

          {{-- Cuerpo --}}
          <tr>
            <td style="padding:32px;">
              <p style="margin:0 0 16px 0; font-size:14px; color:#555555;">Hola {{ $nombre }},</p>

              <h1 style="margin:0 0 12px 0; font-size:19px; color:#1A1A1A; font-weight:bold; line-height:1.35;">
                {{ $titulo }}
              </h1>

              <p style="margin:0 0 20px 0; font-size:14px; color:#444444; line-height:1.6;">
                {{ $intro }}
              </p>

              {{-- La lista.

                   Va en una tabla y no en un <ul>: Outlook de escritorio usa el
                   motor de Word e ignora el padding de las listas, dejando las
                   viñetas pegadas al borde. Una celda por fila se ve igual en
                   todos los clientes. --}}
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#FAF8F4; border:1px solid #EEEAE2; border-radius:8px; margin:0 0 24px 0;">
                <tr>
                  <td style="padding:16px 20px;">
                    @foreach($titulos as $t)
                      <p style="margin:0 0 10px 0; font-size:13.5px; color:#1A1A1A; line-height:1.5;">
                        <span style="color:#C9A84C; font-weight:bold;">&bull;</span>&nbsp;
                        {{ $t }}
                      </p>
                    @endforeach
                  </td>
                </tr>
              </table>

              {{-- Boton --}}
              <table role="presentation" cellpadding="0" cellspacing="0">
                <tr>
                  <td style="border-radius:8px; background-color:#C9A84C;">
                    <a href="{{ $url }}" target="_blank"
                       style="display:inline-block; padding:12px 28px; font-size:14px; font-weight:bold; color:#1A1A1A; text-decoration:none; border-radius:8px;">
                      {{ $boton }}
                    </a>
                  </td>
                </tr>
              </table>

              {{-- El enlace en texto plano, por si el cliente de correo no
                   renderiza el boton o el usuario lo quiere copiar. --}}
              <p style="margin:20px 0 0 0; font-size:11px; color:#999999; line-height:1.5; word-break:break-all;">
                Si el botón no funciona, copiá y pegá esta dirección en tu navegador:<br>
                <span style="color:#777777;">{{ $url }}</span>
              </p>

              <p style="margin:24px 0 0 0; font-size:12.5px; color:#777777; line-height:1.6;">
                Vas a necesitar tu usuario y contraseña, que te llegaron en un correo aparte.
              </p>
            </td>
          </tr>

          {{-- Pie --}}
          <tr>
            <td style="padding:20px 32px; border-top:1px solid #EEEAE2;">
              <p style="margin:0; font-size:11px; color:#999999; line-height:1.5;">
                Recibís este correo porque se te asignó material en Business Partner.
                Esta lista puede cambiar: al ingresar vas a ver siempre la versión actualizada.
              </p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>