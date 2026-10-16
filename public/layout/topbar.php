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
<header class="topbar">
  <!-- Botón hamburguesa (solo mobile, se muestra via JS) -->
  <button class="btn-hamburger" id="btn-hamburger" aria-label="Menú" style="display:none"
    onclick="toggleSidebar()">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <line x1="3" y1="6"  x2="21" y2="6"/>
      <line x1="3" y1="12" x2="21" y2="12"/>
      <line x1="3" y1="18" x2="21" y2="18"/>
    </svg>
  </button>

  <!-- El logo es una MASCARA, no un <img>: el PNG es blanco sobre
       transparente y asi toma el color del tema (ver .topbar-logo en
       panel.css). Por eso lleva role e aria-label: un div no se anuncia solo.

       Se fue el punto dorado que acompanaba al texto: el logo ya trae su ® y
       se sostiene solo. -->
  <!-- Debajo del logotipo va el nombre del producto (guia §6): el mismo
       logo de GoHarv se usa en Academy, Canal Etico y lo que venga, asi que
       es lo unico que dice en que software estas parado. -->
  <div class="topbar-brand">
    <div class="topbar-logo" role="img" aria-label="GoHarv"></div>
  </div>

  <!-- Orden fijado por la guia (§6): tema, notificaciones, avatar. -->
  <div class="topbar-right">
    <!-- El tema se cambia desde aca y ya no desde un item de texto al pie de
         la sidebar. El icono lo pone el script de abajo; empieza vacio para
         que no se vea el sol cuando corresponde la luna. -->
    <button class="topbar-icon-btn" id="btn-tema" onclick="toggleTema()"
            title="Cambiar tema" aria-label="Cambiar tema">
      <span id="icon-tema" aria-hidden="true"></span>
    </button>

    <button class="notif-btn" onclick="toggleNotificaciones()" title="Notificaciones" aria-label="Notificaciones">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
        <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
      </svg>
      <span class="notif-badge" id="notif-badge"></span>
    </button>

    <!-- Avatar + nombre + rol, todo dentro del mismo boton, que lleva a
         perfil.php.

         La guia (§6) los pone al pie de la sidebar, pero ahi se comian una
         franja de alto en una columna donde el espacio vertical es el
         recurso escaso: con doce items de menu, el bloque de usuario
         empujaba "Salir" fuera de la vista. Aca aprovechan el ancho sobrante
         del topbar, que estaba vacio.

         El avatar NO abre el lightbox: si tuviera u-avatar-click, un clic
         navegaria Y abriria el visor, porque el listener delegado escucha en
         document y el evento burbujea por aca. Ademas es tu propia foto, y
         este boton ya lleva a donde se cambia.
         Lo pinta layout.js en iniciarLayout(). -->
    <button class="topbar-user-btn" onclick="window.location.href='perfil.php'"
            title="Mi perfil" aria-label="Mi perfil">
      <span class="u-avatar u-avatar-sm" id="topbar-avatar" aria-hidden="true"></span>
      <span class="topbar-user-btn__datos">
        <span class="topbar-user-btn__nombre" id="topbar-nombre"></span>
        <span class="topbar-user-btn__rol gh-label" id="topbar-rol"></span>
      </span>
    </button>
  </div>
</header>

<script>
/* El icono del tema se pinta ACA y no en layout.js porque layout.js se carga
   al final de la pagina: el boton quedaria vacio hasta entonces.
   Muestra el tema que se va a APLICAR al tocarlo, no el actual. */
(function () {
  var oscuro = document.documentElement.getAttribute('data-theme') === 'dark';
  var el = document.getElementById('icon-tema');
  if (!el) return;
  el.innerHTML = oscuro
    ? '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>'
    : '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>';
})();
</script>

<!-- Overlay para cerrar sidebar en mobile -->
<div class="sidebar-overlay" id="sidebar-overlay" onclick="cerrarSidebar()"></div>

<script>
// Funciones inline para que estén disponibles ANTES de que cargue layout.js
function toggleSidebar() {
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('sidebar-overlay');
  if (!sidebar || !overlay) return;
  sidebar.classList.toggle('open');
  overlay.classList.toggle('open');
}

function cerrarSidebar() {
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('sidebar-overlay');
  if (!sidebar || !overlay) return;
  sidebar.classList.remove('open');
  overlay.classList.remove('open');
}
</script>