<?php
// BASE_URL_PHP se adapta solo: en local (XAMPP) usa la subcarpeta; en Cloud, la raíz del dominio.
$host = $_SERVER['HTTP_HOST'] ?? '';
$esLocal = str_starts_with($host, 'localhost') || str_starts_with($host, '127.0.0.1');
define('BASE_URL_PHP', $esLocal ? '/manuales-franquiciantes/public' : '');

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