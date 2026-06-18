<?php
// Variables que cada página puede definir antes de incluir head.php
// $titulo    = título de la pestaña (opcional)
// $css_extra = archivo CSS adicional (opcional)
$titulo    = $titulo    ?? 'Cerrajería Leonardo';
$css_extra = $css_extra ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($titulo) ?> — Sistema de Franquicias</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@100;300;400;500;700;900&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;500;600;700&family=Archivo+Narrow:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/manuales-franquiciantes/public/styles/style.css">
<link rel="stylesheet" href="/manuales-franquiciantes/public/styles/panel.css">
<?php if ($css_extra): ?>
<link rel="stylesheet" href="/manuales-franquiciantes/public/styles/<?= htmlspecialchars($css_extra) ?>">
<?php endif; ?>
<script src="<?= BASE_URL_PHP ?>/js/config.js"></script>
<script src="<?= BASE_URL_PHP ?>/js/api.js"></script>
</head>
<body>
<body data-pagina="<?= $pagina_actual ?? '' ?>">