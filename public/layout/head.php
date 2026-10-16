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
// Variables que cada página puede definir antes de incluir head.php
// $titulo    = título de la pestaña (opcional)
// $css_extra = archivo CSS adicional (opcional)
$titulo    = $titulo    ?? 'GoHarv.';
$css_extra = $css_extra ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($titulo) ?> — Sistema de Franquicias</title>
<?php
/* TEMA: se aplica ANTES de cargar el CSS, a proposito.
   Si se hiciera despues, el navegador alcanza a pintar un cuadro con el tema
   por defecto y el usuario ve un destello blanco al entrar a cada pagina.
   Por eso este script va inline y arriba de todo: es la unica forma de que
   no parpadee. */
?>
<script>
(function () {
  var guardado = null;
  try { guardado = localStorage.getItem('tema'); } catch (e) {}
  // Sin preferencia guardada se respeta la del sistema operativo (guia, §7).
  var oscuro = guardado
    ? guardado === 'oscuro'
    : window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
  if (oscuro) document.documentElement.setAttribute('data-theme', 'dark');
})();
</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
<?php /* Los tokens de marca van PRIMEROS: todo lo demas depende de ellos. */ ?>
<link rel="stylesheet" href="<?= BASE_URL_PHP ?>/styles/goharv-tokens.css">
<link rel="stylesheet" href="<?= BASE_URL_PHP ?>/styles/goharv-ui.css">
<link rel="stylesheet" href="<?= BASE_URL_PHP ?>/styles/style.css">
<link rel="stylesheet" href="<?= BASE_URL_PHP ?>/styles/panel.css">
<?php if ($css_extra): ?>
<link rel="stylesheet" href="<?= BASE_URL_PHP ?>/styles/<?= htmlspecialchars($css_extra) ?>">
<?php endif; ?>
<script src="<?= BASE_URL_PHP ?>/js/config.js"></script>
<script src="<?= BASE_URL_PHP ?>/js/api.js"></script>
</head>
<body data-pagina="<?= $pagina_actual ?? '' ?>">