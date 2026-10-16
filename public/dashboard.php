<?php
include 'layout/config.php';
include 'layout/auth.php';
verificarSesion('franquiciante');
$titulo        = 'Dashboard';
$pagina_actual = 'dashboard';
include 'layout/head.php';
?>
<!--
  PANTALLA DE INICIO — primera vista migrada a la Marca Madre.

  Usa los componentes gh- de goharv-ui.css y los roles de goharv-tokens.css.
  No hay ni un color, radio ni tamano escrito a mano en este archivo.

  Lo que todavia NO sigue la guia en esta pantalla es el marco compartido
  (topbar, sidebar y pie): se toca en el paso de layout, porque cambiarlo
  afecta a las 20 pantallas a la vez.
-->

<div class="app-layout">
  <?php include 'layout/topbar.php'; ?>

  <div class="app-body">
    <?php include 'layout/sidebar.php'; ?>

    <main class="main-content">

      <!-- §6 Banda de encabezado: eyebrow con linea + H1 + descripcion.
           El eyebrow dice el ROL de quien mira; lo completa el JS. -->
      <div class="gh-banda">
        <span class="gh-label gh-label--line" id="banda-rol">&nbsp;</span>
        <h1>Inicio</h1>
        <p class="gh-banda__desc" id="banda-desc">Cargando…</p>
      </div>

      <!-- §5.3 Metricas: numero arriba, etiqueta abajo, todas en --text -->
      <div class="gh-stats-grid" id="stats-grid">
        <div class="gh-stat" id="card-empresas" style="display:none">
          <div class="gh-stat__value" id="stat-empresas">—</div>
          <div class="gh-stat__label gh-label">Empresas activas</div>
        </div>
        <div class="gh-stat" id="card-franquicias">
          <div class="gh-stat__value" id="stat-franquicias">—</div>
          <div class="gh-stat__label gh-label">Franquicias activas</div>
        </div>
        <div class="gh-stat" id="card-publicados">
          <div class="gh-stat__value" id="stat-publicados">—</div>
          <div class="gh-stat__label gh-label">Manuales publicados</div>
        </div>
        <div class="gh-stat" id="card-borradores">
          <div class="gh-stat__value" id="stat-borradores">—</div>
          <div class="gh-stat__label gh-label">En borrador</div>
        </div>
        <div class="gh-stat" id="card-usuarios">
          <div class="gh-stat__value" id="stat-usuarios">—</div>
          <div class="gh-stat__label gh-label">Total usuarios</div>
        </div>
      </div>

      <!-- §5.4 Card con tabla -->
      <div class="gh-card" style="margin-top:var(--space-5)">
        <div class="gh-card__header">
          <h3 id="tabla-titulo">Franquicias registradas</h3>
        </div>
        <div class="gh-table-scroll">
          <table class="gh-table">
            <thead id="tabla-head">
              <tr>
                <th>Nombre</th>
                <th>Razón social</th>
                <th>CUIT</th>
                <th>Estado</th>
                <th>Dashboard</th>
              </tr>
            </thead>
            <tbody id="tabla-lista">
              <tr><td colspan="5" class="gh-td-muted" style="text-align:center;padding:var(--space-6)">
                Cargando…
              </td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </main>
  </div>
</div>

<style>
/* Lo unico propio de esta pantalla. Sin colores: solo el cursor de las filas
   que llevan a otro lado. El resaltado al pasar ya lo da .gh-table. */
.fila-clickeable { cursor: pointer; }
</style>

<script>
const BASE_PHP = '<?= BASE_URL_PHP ?>';

// El eyebrow de la banda muestra el rol, como en la guia ("SUPERADMIN").
const ROTULO_ROL = {
  super_admin:  'Superadmin',
  franquiciante:'Franquiciante',
  franquiciado: 'Socio comercial',
  empleado:     'Empleado',
};

async function cargarDashboard() {
  try {
    const me      = await apiFetch('GET', '/me');
    const rol     = me.rol;
    const esSuper = rol === 'super_admin';

    document.getElementById('banda-rol').textContent = ROTULO_ROL[rol] || rol;

    // Datos base; super_admin suma /empresas
    const reqs = [
      apiFetch('GET', '/manuales'),
      apiFetch('GET', '/franquicias'),
      apiFetch('GET', '/usuarios'),
    ];
    if (esSuper) reqs.push(apiFetch('GET', '/empresas'));

    const [manuales, franquicias, usuarios, empresas] = await Promise.all(reqs);

    const publicados = manuales.filter(m => m.estado === 'publicado').length;
    const borradores = manuales.filter(m => m.estado === 'borrador').length;
    const activas    = franquicias.filter(f => f.activa).length;

    document.getElementById('stat-franquicias').textContent = activas;
    document.getElementById('stat-publicados').textContent  = publicados;
    document.getElementById('stat-borradores').textContent  = borradores;
    document.getElementById('stat-usuarios').textContent    = usuarios.length;

    const nombreEmpresa = me.empresa?.nombre
      || [me.nombre, me.apellido].filter(Boolean).join(' ').trim()
      || me.email
      || '';
    document.getElementById('banda-desc').textContent =
      esSuper ? 'Resumen general del sistema.' : `Resumen de ${nombreEmpresa}.`;

    if (esSuper) {
      configurarVistaSuperAdmin(empresas);
    } else {
      hacerClickeable('card-franquicias', `${BASE_PHP}/franquicias.php`);
      renderManualesPublicados(manuales);
    }

  } catch (e) {
    document.getElementById('banda-desc').textContent = 'Error al cargar datos.';
  }
}

// ── SUPER ADMIN ───────────────────────────────────────────────
function configurarVistaSuperAdmin(empresas) {
  document.getElementById('card-empresas').style.display = '';
  document.getElementById('stat-empresas').textContent = empresas.filter(e => e.activa).length;

  // Tarjetas clickeables → su sección con el filtro puesto
  hacerClickeable('card-empresas',    `${BASE_PHP}/empresas.php?estado=activas`);
  hacerClickeable('card-franquicias', `${BASE_PHP}/franquicias.php`);
  hacerClickeable('card-publicados',  `${BASE_PHP}/manuales.php?estado=publicado`);
  hacerClickeable('card-borradores',  `${BASE_PHP}/manuales.php?estado=borrador`);
  hacerClickeable('card-usuarios',    `${BASE_PHP}/usuarios.php`);

  document.getElementById('tabla-titulo').textContent = 'Empresas registradas';
  document.getElementById('tabla-head').innerHTML = `
    <tr>
      <th>Empresa</th>
      <th>Razón social</th>
      <th>CUIT</th>
      <th>Estado</th>
      <th>Franquicias</th>
      <th>Acciones</th>
    </tr>`;

  const tbody = document.getElementById('tabla-lista');
  tbody.innerHTML = empresas.length
    ? empresas.map(e => `
        <tr class="fila-clickeable" onclick="window.location.href='${BASE_PHP}/franquicias.php?empresa_id=${e.id}'">
          <td style="font-weight:500">${esc(e.nombre)}</td>
          <td class="gh-td-muted">${esc(e.razon_social)}</td>
          <td class="gh-td-muted">${esc(e.cuit)}</td>
          <td><span class="gh-status ${e.activa ? 'gh-status--ok' : 'gh-status--error'}">${e.activa ? 'Activa' : 'Suspendida'}</span></td>
          <td class="gh-td-muted">${e.franquicias_count ?? 0} franquicia(s)</td>
          <td>
            <button class="gh-btn gh-btn--ghost"
              onclick="event.stopPropagation(); window.location.href='${BASE_PHP}/manuales.php?empresa_id=${e.id}'">
              Ver manuales
            </button>
          </td>
        </tr>`).join('')
    : filaVacia(6, 'Sin empresas registradas.');
}

// ── FRANQUICIANTE: manuales publicados (con vista previa) ────
function renderManualesPublicados(manuales) {
  const publicados = manuales.filter(m => m.estado === 'publicado');

  document.getElementById('tabla-titulo').textContent = 'Manuales publicados';
  document.getElementById('tabla-head').innerHTML = `
    <tr>
      <th>Manual</th>
      <th>Categoría</th>
      <th>Versión</th>
      <th>Vista previa</th>
    </tr>`;

  document.getElementById('tabla-lista').innerHTML = publicados.length
    ? publicados.map(m => {
        const v = m.version_activa?.[0]?.version_number ?? m.version_activa?.version_number;
        return `
        <tr>
          <td style="font-weight:500">${esc(m.titulo)}</td>
          <td class="gh-td-muted">${esc(m.categoria)}</td>
          <td class="gh-td-muted">${v ? 'v' + v : '—'}</td>
          <td>
            <a href="${BASE_PHP}/lectura.php?id=${m.id}" class="gh-btn gh-btn--ghost">
              Vista previa
            </a>
          </td>
        </tr>`;
      }).join('')
    : filaVacia(4, 'Sin manuales publicados.');
}

// ── HELPERS ───────────────────────────────────────────────────
function filaVacia(columnas, texto) {
  return `<tr><td colspan="${columnas}" class="gh-td-muted"
    style="text-align:center;padding:var(--space-6)">${texto}</td></tr>`;
}

function hacerClickeable(cardId, url) {
  const card = document.getElementById(cardId);
  if (!card) return;
  card.classList.add('gh-stat--link');
  card.addEventListener('click', () => { window.location.href = url; });
}

function esc(str) {
  if (!str) return '—';
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

cargarDashboard();
</script>

<?php include 'layout/footer.php'; ?>
