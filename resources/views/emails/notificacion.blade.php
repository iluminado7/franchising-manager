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
              <span style="color:#C9A84C; font-size:18px; font-weight:bold; letter-spacing:0.02em;">GoHarv.</span>
            </td>
          </tr>

          {{-- Cuerpo --}}
          <tr>
            <td style="padding:32px;">
              <p style="margin:0 0 16px 0; font-size:14px; color:#555555;">Hola {{ $nombre }},</p>

              <h1 style="margin:0 0 12px 0; font-size:19px; color:#1A1A1A; font-weight:bold; line-height:1.35;">
                {{ $titulo }}
              </h1>

              @if(!empty($mensaje))
                <p style="margin:0 0 24px 0; font-size:14px; color:#444444; line-height:1.6;">
                  {{ $mensaje }}
                </p>
              @else
                <p style="margin:0 0 24px 0; font-size:14px; color:#444444; line-height:1.6;">
                  Ingresá a la plataforma para ver los detalles.
                </p>
              @endif

              {{-- Botón --}}
              <table role="presentation" cellpadding="0" cellspacing="0">
                <tr>
                  <td style="border-radius:8px; background-color:#C9A84C;">
                    <a href="{{ $url }}" target="_blank"
                       style="display:inline-block; padding:12px 28px; font-size:14px; font-weight:bold; color:#1A1A1A; text-decoration:none; border-radius:8px;">
                      Ver en la plataforma
                    </a>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          {{-- Pie --}}
          <tr>
            <td style="padding:20px 32px; border-top:1px solid #EEEAE2;">
              <p style="margin:0; font-size:11px; color:#999999; line-height:1.5;">
                Recibís este correo porque tenés una cuenta en la plataforma de gestión de manuales.
                Si no esperabas esta notificación, podés ignorar este mensaje.
              </p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>