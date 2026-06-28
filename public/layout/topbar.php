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

  <div class="topbar-brand">
    <div class="topbar-brand-dot"></div>
    GoHarv.
  </div>

  <div class="topbar-right">
    <button class="topbar-user-btn" id="topbar-nombre" onclick="window.location.href='perfil.php'" title="Mi perfil"></button>
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