<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tu acceso a Business Partner</title>
</head>
{{--
  Plantilla propia y NO una variante de password-reset.blade.php.

  Se evaluo reusar aquella, pero habria requerido tres condicionales (bloque de
  credenciales, advertencia, y un pie distinto — el actual dice "alguien pidio
  restablecer la contrasena", que en un alta confunde). Esa plantilla es parte
  del flujo de recuperacion, que funciona; se prefirio duplicar 90 lineas de
  tabla HTML antes que tocarla.

  El layout es el mismo a proposito: mismos colores, mismo encabezado.
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
                Ya tenés acceso al sistema
              </h1>

              <p style="margin:0 0 24px 0; font-size:14px; color:#444444; line-height:1.6;">
                Se creó tu cuenta en <strong>Business Partner</strong>
              </p>

              {{-- Credenciales --}}
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#FAF8F4; border:1px solid #EEEAE2; border-radius:8px; margin:0 0 24px 0;">
                <tr>
                  <td style="padding:18px 20px;">
                    <p style="margin:0 0 12px 0; font-size:11px; color:#999999; letter-spacing:0.08em; text-transform:uppercase; font-weight:bold;">
                      Tus datos de acceso son:
                    </p>
                    <p style="margin:0 0 8px 0; font-size:13px; color:#555555;">
                      Usuario:<br>
                      <span style="font-family:'Courier New', Courier, monospace; font-size:15px; color:#1A1A1A;">{{ $email }}</span>
                    </p>
                    <p style="margin:0; font-size:13px; color:#555555;">
                      Contraseña provisoria:<br>
                      <span style="font-family:'Courier New', Courier, monospace; font-size:15px; color:#1A1A1A; letter-spacing:0.04em;">{{ $password }}</span>
                    </p>
                  </td>
                </tr>
              </table>

              {{-- Advertencia. Es el punto central del correo: el sistema NO
                   obliga a cambiarla, asi que el pedido tiene que ser
                   inequivoco y estar antes del boton, no en la letra chica. --}}
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#FDF3F3; border:1px solid #F0D5D5; border-radius:8px; margin:0 0 24px 0;">
                <tr>
                  <td style="padding:16px 20px;">
                    <p style="margin:0 0 6px 0; font-size:13px; color:#A93838; font-weight:bold;">
                      Cambiá esta contraseña la primera vez que entres.
                    </p>
                    <p style="margin:0 0 8px 0; font-size:13px; color:#8A4040; line-height:1.6;">
                      Para modificarla, una vez que hayas ingresado al sistema:
                    </p>
                    <ol style="margin:0; padding-left:18px; font-size:13px; color:#8A4040; line-height:1.7;">
                      <li>Andá al menú lateral izquierdo.</li>
                      <li>Entrá en <strong>Mi perfil</strong>.</li>
                      <li>Bajá hasta <strong>Cambiar contraseña</strong>.</li>
                      <li>Ingresá la contraseña provisoria de este correo.</li>
                      <li>Escribí dos veces tu contraseña nueva (mínimo 8 caracteres).</li>
                    </ol>
                  </td>
                </tr>
              </table>

              {{-- Boton --}}
              <table role="presentation" cellpadding="0" cellspacing="0">
                <tr>
                  <td style="border-radius:8px; background-color:#C9A84C;">
                    <a href="{{ $url }}" target="_blank"
                       style="display:inline-block; padding:12px 28px; font-size:14px; font-weight:bold; color:#1A1A1A; text-decoration:none; border-radius:8px;">
                      Ingresar al sistema
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

            </td>
          </tr>

          {{-- Pie --}}
          <tr>
            <td style="padding:20px 32px; border-top:1px solid #EEEAE2;">
              <p style="margin:0; font-size:11px; color:#999999; line-height:1.5;">
                Recibís este correo porque se creó una cuenta a tu nombre en Business Partner.
                Si creés que fue un error, avisale al administrador de tu red.
              </p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>