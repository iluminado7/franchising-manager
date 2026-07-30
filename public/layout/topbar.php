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
  <div class="topbar-brand">
    <div class="topbar-logo" role="img" aria-label="GoHarv"></div>
  </div>

  <div class="topbar-right">
    <!-- El boton entero lleva a perfil.php. El avatar NO abre el lightbox: si
         tuviera u-avatar-click, un clic navegaria Y abriria el visor, porque el
         listener delegado escucha en document y el evento burbujea por aca.
         Ademas es tu propia foto, y este boton ya lleva a donde se cambia.
         Lo pinta layout.js en iniciarLayout(). -->
    <button class="topbar-user-btn" onclick="window.location.href='perfil.php'" title="Mi perfil">
      <span class="u-avatar u-avatar-sm" id="topbar-avatar" aria-hidden="true"></span>
      <span id="topbar-nombre"></span>
    </button>
    <button class="notif-btn" onclick="toggleNotificaciones()" title="Notificaciones" aria-label="Notificaciones">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
        <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
      </svg>
      <span class="notif-badge" id="notif-badge"></span>
    </button>
    <button class="btn-logout" onclick="hacerLogout()">Cerrar sesión</button>
  </div>
</header>

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