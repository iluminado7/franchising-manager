<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{ $asunto }}</title>
</head>
<body style="margin:0; padding:0; background-color:#F2EFE9; font-family: Arial, Helvetica, sans-serif;">
  {{-- Misma estructura y paleta que emails/notificacion.blade.php --}}
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
              <p style="margin:0 0 16px 0; font-size:14px; color:#555555;">Hola {{ $nombreDestinatario }},</p>

              <h1 style="margin:0 0 12px 0; font-size:19px; color:#1A1A1A; font-weight:bold; line-height:1.35;">
                La prueba de {{ $empresa['nombre'] }} {{ $cuandoVence }}
              </h1>

              <p style="margin:0 0 24px 0; font-size:14px; color:#444444; line-height:1.6;">
                El acceso se corta el <strong>{{ $fechaVence }}</strong> (hora de Argentina).
                La prueba no se puede extender: si la empresa quiere seguir, hay que
                convertirla en cliente antes de esa fecha. Los datos que cargó se conservan.
              </p>

              {{-- Empresa --}}
              <p style="margin:0 0 8px 0; font-size:11px; font-weight:bold; letter-spacing:0.06em; text-transform:uppercase; color:#999999;">Empresa</p>
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 22px 0; font-size:14px; color:#444444;">
                <tr><td style="padding:3px 0; color:#888888; width:120px;">Nombre</td><td style="padding:3px 0;">{{ $empresa['nombre'] }}</td></tr>
                <tr><td style="padding:3px 0; color:#888888;">Razón social</td><td style="padding:3px 0;">{{ $empresa['razon_social'] }}</td></tr>
                <tr><td style="padding:3px 0; color:#888888;">CUIT</td><td style="padding:3px 0;">{{ $empresa['cuit'] }}</td></tr>
              </table>

              {{-- Contacto: lo primero que necesita quien va a llamar --}}
              <p style="margin:0 0 8px 0; font-size:11px; font-weight:bold; letter-spacing:0.06em; text-transform:uppercase; color:#999999;">Contacto</p>
              <div style="margin:0 0 22px 0; font-size:14px; color:#444444; line-height:1.6;">
                @forelse($franquiciantes as $f)
                  <div>
                    {{ $f['nombre'] }} (franquiciante) —
                    <a href="mailto:{{ $f['email'] }}" style="color:#8A6D1F;">{{ $f['email'] }}</a>@if(!empty($f['celular'])) · {{ $f['celular'] }}@endif
                  </div>
                @empty
                  <div style="color:#888888;">La empresa no tiene franquiciante cargado.</div>
                @endforelse
                @foreach($emailsContacto as $email)
                  <div>
                    <a href="mailto:{{ $email }}" style="color:#8A6D1F;">{{ $email }}</a>
                    <span style="color:#888888;">(email de la empresa)</span>
                  </div>
                @endforeach
              </div>

              {{-- Uso: da una idea de cuánto la aprovecharon --}}
              <p style="margin:0 0 8px 0; font-size:11px; font-weight:bold; letter-spacing:0.06em; text-transform:uppercase; color:#999999;">Uso durante la prueba</p>
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 26px 0; font-size:14px; color:#444444;">
                <tr><td style="padding:3px 0; color:#888888; width:170px;">Socios comerciales</td><td style="padding:3px 0;">{{ $uso['socios'] }} de {{ \App\Models\Empresa::DEMO_TOPES['franquiciado'] }}</td></tr>
                <tr><td style="padding:3px 0; color:#888888;">Empleados</td><td style="padding:3px 0;">{{ $uso['empleados'] }} de {{ \App\Models\Empresa::DEMO_TOPES['empleado'] }}</td></tr>
                <tr><td style="padding:3px 0; color:#888888;">Manuales asignados</td><td style="padding:3px 0;">{{ $uso['manuales'] }}</td></tr>
                <tr><td style="padding:3px 0; color:#888888;">Documentos</td><td style="padding:3px 0;">{{ $uso['documentos'] }}</td></tr>
              </table>

              {{-- Botón --}}
              <table role="presentation" cellpadding="0" cellspacing="0">
                <tr>
                  <td style="border-radius:8px; background-color:#C9A84C;">
                    <a href="{{ $urlEmpresas }}" target="_blank"
                       style="display:inline-block; padding:12px 28px; font-size:14px; font-weight:bold; color:#1A1A1A; text-decoration:none; border-radius:8px;">
                      Ver empresas
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
                Recibís este aviso porque sos administrador de la plataforma.
                Se manda 7 días antes y el día anterior al vencimiento de cada empresa demo.
              </p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
