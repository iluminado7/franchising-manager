<?php
// src/auth.php
// Verificación server-side de sesión y rol.
// super_admin tiene acceso a TODAS las páginas sin importar el rol requerido.

function verificarSesion(?string $rol_requerido = null): void
{
    // No cachear: estas páginas son dinámicas y cambian según el estado del usuario.
    // Esto es crítico en Laravel Cloud donde un proxy/CDN cachea GET responses.
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: ' . gmdate('D, d M Y H:i:s', time() - 3600) . ' GMT');

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
                $_ENV['DB_HOST']  ,
                $_ENV['DB_PORT']  ,
                $_ENV['DB_DATABASE'] ?? ''
            ),
            $_ENV['DB_USERNAME'],
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
          AND u.deleted_at IS NULL
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

    // Gate de aceptaciones: si el usuario es franquiciado y tiene manuales pendientes
    // de aceptar, se lo redirige a lectura.php del primer pendiente. La función
    // solo redirige cuando NO está ya en la página de lectura del manual pendiente.
    verificarAceptacionesPendientes($pdo, $usuario);

    $GLOBALS['usuario_actual'] = $usuario;
}

/**
 * Si el usuario es franquiciado con manuales asignados sin aceptar, lo manda
 * a lectura.php del primer pendiente. Si ya está viendo uno de esos manuales,
 * lo deja pasar (para que pueda aceptarlo).
 */
function verificarAceptacionesPendientes(PDO $pdo, array $usuario): void
{
    if (($usuario['rol'] ?? '') !== 'franquiciado') return;
    if (empty($usuario['empresa_id'])) return;

    $stmt = $pdo->prepare("
        SELECT m.id AS manual_id
        FROM manual_empresa_assignments mea
        JOIN manuals m
          ON m.id = mea.manual_id
        JOIN manual_versions mv
          ON mv.manual_id = m.id
         AND mv.es_activa = 1
        LEFT JOIN acceptances a
          ON a.manual_version_id = mv.id
         AND a.user_id = :uid
        WHERE mea.empresa_id = :emp
          AND m.estado = 'publicado'
          AND a.id IS NULL
        ORDER BY m.created_at ASC
    ");
    $stmt->execute([
        ':uid' => $usuario['id'],
        ':emp' => $usuario['empresa_id'],
    ]);
    $pendientes = $stmt->fetchAll();

    if (!$pendientes) return;

    // Si ya está en lectura.php viendo uno de los manuales pendientes, lo dejamos pasar
    // (esa es justamente la página donde tiene que aceptarlo).
    $scriptName    = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $manualIdEnUrl = (int) ($_GET['id'] ?? 0);

    if ($scriptName === 'lectura.php' && $manualIdEnUrl > 0) {
        foreach ($pendientes as $p) {
            if ((int) $p['manual_id'] === $manualIdEnUrl) {
                return; // está en el lugar correcto, dejarlo
            }
        }
    }

    // Redirigir al primer manual pendiente
    $primero = (int) $pendientes[0]['manual_id'];
    header('Location: ' . BASE_URL_PHP . '/lectura.php?id=' . $primero);
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