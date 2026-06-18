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
        SELECT u.id, u.rol, u.activo
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