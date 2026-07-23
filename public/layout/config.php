<?php
// Acceso directo por HTTP: este archivo es un include, no una pagina. Si se
// pide como /layout/<archivo>.php se responde 404 y se corta.
//
// El guard va en PHP y no en un .htaccess porque nginx (Laravel Cloud) ignora
// los .htaccess: la proteccion tiene que ser portable entre servidores.
if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    http_response_code(404);
    exit;
}
// BASE_URL_PHP se adapta solo: en local (XAMPP) usa la subcarpeta; en Cloud, la raíz del dominio.
$host = $_SERVER['HTTP_HOST'] ?? '';
$esLocal = str_starts_with($host, 'localhost') || str_starts_with($host, '127.0.0.1');
define('BASE_URL_PHP', $esLocal ? '/manuales-franquiciantes/public' : '');

// Credenciales de base de datos para las paginas PHP del frontend.
//
// SOLO estas cinco claves. Antes se cargaba el .env ENTERO en $_ENV, lo que
// dejaba APP_KEY, AWS_SECRET_ACCESS_KEY y RESEND_API_KEY en memoria de cada
// pagina sin ninguna necesidad.
$CLAVES_DB = ['DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'];

// 1. Entorno real del proceso. Es el unico camino que funciona en produccion:
//    en Laravel Cloud, y en cualquier deploy con variables inyectadas, NO
//    existe un archivo .env en disco.
//
//    Se usa getenv y no $_ENV a secas porque $_ENV depende de
//    variables_order del php.ini, que por defecto es "GPCS" — sin la E de
//    environment — y puede estar vacio aunque las variables existan.
foreach ($CLAVES_DB as $clave) {
    $valor = getenv($clave);
    if ($valor !== false && $valor !== '') {
        $_ENV[$clave] = $valor;
    }
}

// 2. Fallback para DESARROLLO: leer el .env del proyecto. Solo completa lo
//    que no vino del entorno. parse_ini_file falla con algunos caracteres,
//    por eso el parseo a mano.
$envFile = dirname(__DIR__, 2) . '/.env';
if (file_exists($envFile) && is_readable($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {
        $linea = trim($linea);
        if ($linea === '' || str_starts_with($linea, '#') || !str_contains($linea, '=')) continue;

        [$clave, $valor] = explode('=', $linea, 2);
        $clave = trim($clave);

        // Whitelist: cualquier otra clave del .env se ignora.
        if (!in_array($clave, $CLAVES_DB, true)) continue;
        if (isset($_ENV[$clave]) && $_ENV[$clave] !== '') continue;   // ya vino del entorno

        $_ENV[$clave] = trim($valor, " \t\"'");
    }
}

// Defaults minimos: sin esto, un $_ENV['DB_HOST'] inexistente tira warning, y
// con display_errors activo el warning revela la ruta del archivo.
$_ENV['DB_HOST'] = $_ENV['DB_HOST'] ?? '127.0.0.1';
$_ENV['DB_PORT'] = $_ENV['DB_PORT'] ?? '3306';