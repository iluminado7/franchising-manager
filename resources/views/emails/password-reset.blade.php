<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{ $titulo }}</title>
</head>
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

              {{-- Sin @else: PasswordResetMail siempre manda un mensaje. --}}
              <p style="margin:0 0 24px 0; font-size:14px; color:#444444; line-height:1.6;">
                {{ $mensaje }}
              </p>

              {{-- El boton SOLO cuando hay enlace.
                   Los correos de cuenta / empresa / sucursal suspendida llegan
                   con $url en NULL: no hay nada adonde llevar al usuario, y un
                   boton que no lleva a ningun lado seria peor que ninguno. --}}
              @if(!empty($url))
                <table role="presentation" cellpadding="0" cellspacing="0">
                  <tr>
                    <td style="border-radius:8px; background-color:#C9A84C;">
                      <a href="{{ $url }}" target="_blank"
                         style="display:inline-block; padding:12px 28px; font-size:14px; font-weight:bold; color:#1A1A1A; text-decoration:none; border-radius:8px;">
                        Restablecer contraseña
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
              @endif
            </td>
          </tr>

          {{-- Pie --}}
          <tr>
            <td style="padding:20px 32px; border-top:1px solid #EEEAE2;">
              <p style="margin:0; font-size:11px; color:#999999; line-height:1.5;">
                Recibís este correo porque alguien pidió restablecer la contraseña de
                una cuenta asociada a esta dirección. Si no fuiste vos, podés ignorar
                este mensaje: tu contraseña no cambió.
              </p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>