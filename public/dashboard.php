<?php
include 'layout/config.php';
include 'layout/auth.php';
verificarSesion('franquiciante');
$titulo        = 'Dashboard';
$pagina_actual = 'dashboard';
include 'layout/head.php';
?>

<div class="app-layout">
  <?php include 'layout/topbar.php'; ?>

  <div class="app-body">
    <?php include 'layout/sidebar.php'; ?>

    <main class="main-content">
      <div class="page-header">
        <div class="page-title">Inicio</div>
        <div class="page-sub" id="page-sub">Cargando...</div>
      </div>

      <div class="stats-grid" id="stats-grid">
        <!-- Solo super_admin -->
        <div class="stat-card" id="card-empresas" style="display:none">
          <div class="stat-card-label">Empresas activas</div>
          <div class="stat-card-value dorado" id="stat-empresas">—</div>
        </div>
        <div class="stat-card" id="card-franquicias">
          <div class="stat-card-label">Franquicias activas</div>
          <div class="stat-card-value dorado" id="stat-franquicias">—</div>
        </div>
        <div class="stat-card" id="card-publicados">
          <div class="stat-card-label">Manuales publicados</div>
          <div class="stat-card-value" id="stat-publicados">—</div>
        </div>
        <div class="stat-card" id="card-borradores">
          <div class="stat-card-label">En borrador</div>
          <div class="stat-card-value" id="stat-borradores">—</div>
        </div>
        <div class="stat-card" id="card-usuarios">
          <div class="stat-card-label">Total usuarios</div>
          <div class="stat-card-value" id="stat-usuarios">—</div>
        </div>
      </div>

      <div class="tabla-wrap">
        <div class="tabla-header">
          <h3 id="tabla-titulo">Franquicias registradas</h3>
        </div>
        <table>
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
            <tr><td colspan="5" class="loading-msg">
              <div class="spinner" style="display:block;border-top-color:var(--dorado)"></div>
              Cargando...
            </td></tr>
          </tbody>
        </table>
      </div>
    </main>
  </div>
</div>

<style>
.stat-card-link { cursor:pointer; transition:border-color .15s, transform .15s; }
.stat-card-link:hover { border-color:var(--dorado); transform:translateY(-2px); }
.fila-clickeable { cursor:pointer; transition:background .15s; }
.fila-clickeable:hover { background:var(--gris2); }
</style>

<script>
const BASE_PHP = '<?= BASE_URL_PHP ?>';

async function cargarDashboard() {
  try {
    const me      = await apiFetch('GET', '/me');
    const rol     = me.rol;
    const esSuper = rol === 'super_admin';

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
      || (me.perfil ? `${me.perfil.nombre} ${me.perfil.apellido}` : '');
    document.getElementById('page-sub').textContent =
      esSuper ? 'Resumen general del sistema' : `Resumen de ${nombreEmpresa}`;

    if (esSuper) {
      configurarVistaSuperAdmin(empresas);
    } else {
      hacerClickeable('card-franquicias', `${BASE_PHP}/franquicias.php`);
      renderManualesPublicados(manuales);
    }

  } catch (e) {
    document.getElementById('page-sub').textContent = 'Error al cargar datos.';
  }
}

// ── SUPER ADMIN ───────────────────────────────────────────────
function configurarVistaSuperAdmin(empresas) {
  // Tarjeta de empresas activas
  document.getElementById('card-empresas').style.display = '';
  document.getElementById('stat-empresas').textContent = empresas.filter(e => e.activa).length;

  // Tarjetas clickeables → su sección con el filtro puesto
  hacerClickeable('card-empresas',    `${BASE_PHP}/empresas.php`);
  hacerClickeable('card-franquicias', `${BASE_PHP}/franquicias.php`);
  hacerClickeable('card-publicados',  `${BASE_PHP}/manuales.php?estado=publicado`);
  hacerClickeable('card-borradores',  `${BASE_PHP}/manuales.php?estado=borrador`);
  hacerClickeable('card-usuarios',    `${BASE_PHP}/usuarios.php`);

  // Lista: empresas registradas → franquicias.php de esa empresa
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
          <td style="color:var(--blanco);font-weight:500">${esc(e.nombre)}</td>
          <td>${esc(e.razon_social)}</td>
          <td>${esc(e.cuit)}</td>
          <td><span class="estado-pill ${e.activa ? 'estado-completo' : 'estado-pendiente'}">${e.activa ? 'Activa' : 'Suspendida'}</span></td>
          <td>${e.franquicias_count ?? 0} franquicia(s)</td>
          <td>
            <button class="btn btn-ghost" style="padding:4px 10px;font-size:12px"
              onclick="event.stopPropagation(); window.location.href='${BASE_PHP}/manuales.php?empresa_id=${e.id}'">
              Ver manuales
            </button>
          </td>
        </tr>`).join('')
    : `<tr><td colspan="6" class="empty-state" style="padding:24px">Sin empresas registradas.</td></tr>`;
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
          <td style="color:var(--blanco);font-weight:500">${esc(m.titulo)}</td>
          <td>${esc(m.categoria)}</td>
          <td>${v ? 'v' + v : '—'}</td>
          <td>
            <a href="${BASE_PHP}/lectura.php?id=${m.id}" class="btn btn-ghost" style="padding:4px 10px;font-size:12px">
              Vista previa
            </a>
          </td>
        </tr>`;
      }).join('')
    : `<tr><td colspan="4" class="empty-state" style="padding:24px">Sin manuales publicados.</td></tr>`;
}

// ── HELPERS ───────────────────────────────────────────────────
function hacerClickeable(cardId, url) {
  const card = document.getElementById(cardId);
  if (!card) return;
  card.classList.add('stat-card-link');
  card.addEventListener('click', () => { window.location.href = url; });
}

function esc(str) {
  if (!str) return '—';
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

cargarDashboard();
</script>

<?php include 'layout/footer.php'; ?>