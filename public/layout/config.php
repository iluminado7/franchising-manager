<?php
// layout/config.php
define('BASE_URL_PHP', '/manuales-franquiciantes/public');

// Leer el .env manualmente (parse_ini_file falla con caracteres especiales)
$envFile = dirname(__DIR__, 2) . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {
        $linea = trim($linea);
        // Ignorar comentarios
        if (str_starts_with($linea, '#') || !str_contains($linea, '=')) continue;
        [$clave, $valor] = explode('=', $linea, 2);
        $clave = trim($clave);
        $valor = trim($valor, " \t\"'"); // quitar comillas y espacios
        $_ENV[$clave] = $valor;
    }
}