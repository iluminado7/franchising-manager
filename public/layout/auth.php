<?php
// src/auth.php
// Verificación server-side de sesión y rol.
// super_admin tiene acceso a TODAS las páginas sin importar el rol requerido.

function verificarSesion(?string $rol_requerido = null): void
{
    $tokenRaw = $_COOKIE['auth_token'] ?? null;

    if (!$tokenRaw) {
        header('Location: ' . BASE_URL_PHP . '/login.html');
        exit;
    }

    $partes = explode('|', $tokenRaw, 2);
    if (count($partes) !== 2) {
        _redirigirLogin();
    }

    $tokenId   = (int) $partes[0];
    $tokenHash = hash('sha256', $partes[1]);

    try {
        $pdo = new PDO(
            sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $_ENV['DB_HOST']     ?? '127.0.0.1',
                $_ENV['DB_PORT']     ?? '3306',
                $_ENV['DB_DATABASE'] ?? ''
            ),
            $_ENV['DB_USERNAME'] ?? 'root',
            $_ENV['DB_PASSWORD'] ?? '',
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    } catch (PDOException $e) {
        http_response_code(500);
        echo '<!DOCTYPE html><html lang="es"><body style="font-family:sans-serif;padding:40px;background:#0A0A0A;color:#F5F3EE">
            <h2>Error del servidor</h2>
            <p style="color:#888">No se pudo verificar la sesión. Intentá de nuevo.</p>
        </body></html>';
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT u.id, u.rol, u.activo, u.empresa_id
        FROM personal_access_tokens pat
        JOIN users u ON u.id = pat.tokenable_id
        WHERE pat.id = ?
          AND pat.token = ?
          AND (pat.expires_at IS NULL OR pat.expires_at > NOW())
          AND u.activo = 1
        LIMIT 1
    ");
    $stmt->execute([$tokenId, $tokenHash]);
    $usuario = $stmt->fetch();

    if (!$usuario) {
        _redirigirLogin();
    }

    // super_admin tiene acceso a todo — nunca se bloquea por rol
    if ($rol_requerido && $usuario['rol'] !== $rol_requerido && $usuario['rol'] !== 'super_admin') {
        http_response_code(403);
        $volver = BASE_URL_PHP . '/manuales.php';
        echo '<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Acceso denegado</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: Arial, sans-serif; background: #0A0A0A; color: #F5F3EE;
           display: flex; align-items: center; justify-content: center; min-height: 100vh; }
    .card { background: #1A1A1A; border: 1px solid #2C2C2C; border-radius: 12px;
            padding: 40px 32px; text-align: center; max-width: 380px; width: 90%; }
    .icon { font-size: 40px; margin-bottom: 16px; }
    h2 { color: #E25C5C; font-size: 20px; margin-bottom: 10px; }
    p  { color: #888; font-size: 14px; line-height: 1.6; margin-bottom: 24px; }
    a  { display: inline-block; background: #C9A84C; color: #0A0A0A;
         padding: 10px 24px; border-radius: 7px; text-decoration: none;
         font-weight: 600; font-size: 13px; }
    a:hover { opacity: .85; }
  </style>
</head>
<body>
  <div class="card">
    <div class="icon">🔒</div>
    <h2>Acceso denegado</h2>
    <p>No tenés permisos para acceder a esta sección.</p>
    <a href="' . $volver . '">Volver al inicio</a>
  </div>
</body>
</html>';
        exit;
    }

    $GLOBALS['usuario_actual'] = $usuario;

    // Bloqueo por suspensión de empresa o sucursal (prioridad sobre todo lo demás).
    verificarSuspension($pdo, $usuario);

    // Bloqueo de navegación: el franquiciado no puede ir a otras secciones
    // mientras tenga manuales con versión activa sin aceptar.
    verificarAceptacionesPendientes($pdo, $usuario);
}

/**
 * Gate de cumplimiento para franquiciados.
 * Si el franquiciado tiene manuales asignados a su empresa con una versión
 * activa que todavía no aceptó, lo redirige a leer/aceptar ese manual y le
 * impide navegar a cualquier otra sección hasta que no quede ninguno pendiente.
 * Se resuelven de a uno. El logout (acción JS hacia login.html) no pasa por acá.
 */
function verificarAceptacionesPendientes(PDO $pdo, array $usuario): void
{
    // Solo aplica a franquiciado. Empleado es solo lectura; los demás no aceptan.
    if (($usuario['rol'] ?? '') !== 'franquiciado') {
        return;
    }

    $empresaId = $usuario['empresa_id'] ?? null;
    if (!$empresaId) {
        return; // sin empresa asignada no hay nada que bloquear
    }

    // Manuales asignados a su empresa, con versión activa, sin aceptación del usuario.
    $stmt = $pdo->prepare("
        SELECT mea.manual_id
        FROM manual_empresa_assignments mea
        JOIN manual_versions mv
          ON mv.manual_id = mea.manual_id
         AND mv.es_activa = 1
        LEFT JOIN acceptances a
          ON a.manual_version_id = mv.id
         AND a.user_id = ?
        WHERE mea.empresa_id = ?
          AND a.id IS NULL
        ORDER BY mea.manual_id
    ");
    $stmt->execute([$usuario['id'], $empresaId]);
    $pendientes = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    if (!$pendientes) {
        return; // al día: navega libre
    }

    // Si ya está leyendo/aceptando uno de los manuales pendientes, dejarlo seguir.
    $paginaActual = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $idActual     = isset($_GET['id']) ? (int) $_GET['id'] : 0;

    if ($paginaActual === 'lectura.php' && in_array($idActual, $pendientes, true)) {
        return;
    }

    // Si no, lo mandamos al primer manual pendiente.
    header('Location: ' . BASE_URL_PHP . '/lectura.php?id=' . $pendientes[0]);
    exit;
}

/**
 * Bloqueo por suspensión de empresa o sucursal.
 * super_admin nunca se bloquea. Cualquier otro usuario queda bloqueado si su empresa
 * (users.empresa_id) o su franquicia (vía franchise_staff) tiene activa = 0.
 */
function verificarSuspension(PDO $pdo, array $usuario): void
{
    if (($usuario['rol'] ?? '') === 'super_admin') {
        return;
    }

    $stmt = $pdo->prepare("
        SELECT
            e.activa  AS empresa_activa,
            fr.activa AS franquicia_activa
        FROM users u
        LEFT JOIN empresas e         ON e.id  = u.empresa_id
        LEFT JOIN franchise_staff fs ON fs.user_id = u.id
        LEFT JOIN franquicias fr     ON fr.id = fs.franquicia_id
        WHERE u.id = ?
        LIMIT 1
    ");
    $stmt->execute([$usuario['id']]);
    $estado = $stmt->fetch();

    $empresaSuspendida    = $estado && $estado['empresa_activa']    !== null && (int) $estado['empresa_activa']    === 0;
    $franquiciaSuspendida = $estado && $estado['franquicia_activa'] !== null && (int) $estado['franquicia_activa'] === 0;

    if (!$empresaSuspendida && !$franquiciaSuspendida) {
        return; // todo activo
    }

    $motivo = $empresaSuspendida
        ? 'La empresa a la que pertenecés fue suspendida.'
        : 'La sucursal a la que pertenecés fue suspendida.';

    _mostrarSuspendido($motivo);
}

function _mostrarSuspendido(string $motivo): never
{
    // Matamos la sesión: un usuario suspendido no debe seguir logueado.
    setcookie('auth_token', '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);

    http_response_code(403);
    echo '<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Acceso suspendido</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: Arial, sans-serif; background: #0A0A0A; color: #F5F3EE;
           display: flex; align-items: center; justify-content: center; min-height: 100vh; }
    .card { background: #1A1A1A; border: 1px solid #2C2C2C; border-radius: 12px;
            padding: 40px 32px; text-align: center; max-width: 400px; width: 90%; }
    .icon { font-size: 40px; margin-bottom: 16px; }
    h2 { color: #E2B65C; font-size: 20px; margin-bottom: 10px; }
    p  { color: #888; font-size: 14px; line-height: 1.6; margin-bottom: 24px; }
    a  { display: inline-block; background: #C9A84C; color: #0A0A0A;
         padding: 10px 24px; border-radius: 7px; text-decoration: none;
         font-weight: 600; font-size: 13px; }
    a:hover { opacity: .85; }
  </style>
</head>
<body>
  <div class="card">
    <div class="icon">⛔</div>
    <h2>Acceso suspendido</h2>
    <p>' . htmlspecialchars($motivo) . ' Si creés que es un error, contactá al administrador.</p>
    <a href="' . BASE_URL_PHP . '/login.html">Volver al inicio</a>
  </div>
</body>
</html>';
    exit;
}

function _redirigirLogin(): never
{
    setcookie('auth_token', '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    header('Location: ' . BASE_URL_PHP . '/login.html');
    exit;
}