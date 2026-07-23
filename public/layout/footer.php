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
?>
<script src="<?= BASE_URL_PHP ?>/js/layout.js"></script>
</body>
</html>
