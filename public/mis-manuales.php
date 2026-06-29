<?php
require_once __DIR__ . '/layout/config.php';
require_once __DIR__ . '/layout/auth.php';
// Ambos roles llegan acá. verificarSesion() sin argumento acepta cualquier rol autenticado.
// Si querés restricción estricta usá: verificarSesion(['franquiciado', 'empleado']);
verificarSesion();
$titulo        = 'Mis manuales';
$pagina_actual = 'manuales';
include 'layout/head.php';
?>

<div class="app-layout">
  <?php include 'layout/topbar.php'; ?>

  <div class="app-body">
    <?php include 'layout/sidebar.php'; ?>

    <main class="main-content">

      <div class="page-header">
        <div>
          <div class="page-title">Mis manuales</div>
          <div class="page-sub" id="page-sub">Manuales publicados disponibles para vos</div>
        </div>
        <!-- Sin botón "Nuevo manual": franquiciado/empleado no crea manuales -->
      </div>

      <!-- Búsqueda rápida -->
      <div style="margin-bottom:20px;position:relative;display:inline-block">
        <input type="text" id="inp-buscar" placeholder="Buscar manual..." oninput="aplicarFiltros()" class="buscar-input">
        <svg style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--gris4)" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      </div>

      <!-- Tabla -->
      <div class="tabla-wrap">
        <div class="tabla-header">
          <h3 id="tabla-titulo">Listado</h3>
        </div>
        <table>
          <thead id="tabla-thead">
            <!-- Se renderiza en init() según si es franquiciado o empleado -->
          </thead>
          <tbody id="tabla-body">
            <tr><td colspan="5">
              <div class="loading-msg">
                <div class="spinner" style="display:block"></div>
                Cargando manuales...
              </div>
            </td></tr>
          </tbody>
        </table>
      </div>

    </main>
  </div>
</div>

<!-- ══════════════════════════════════════════
     MODAL CONFIRMAR ACEPTACIÓN (solo franquiciado)
══════════════════════════════════════════ -->
<div class="modal-overlay" id="modal-confirmar-acept" onclick="if(event.target===this)this.classList.remove('open')">
  <div class="modal-box" style="max-width:400px">
    <div class="modal-header">
      <h3>Confirmar aceptación</h3>
      <button class="modal-close" onclick="document.getElementById('modal-confirmar-acept').classList.remove('open')">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-body">
      <p style="font-size:13px;color:var(--gris5);line-height:1.7;font-family:'Roboto',sans-serif">
        Al confirmar, estás aceptando digitalmente el contenido de este manual. Esta acción queda registrada con la fecha y hora exacta.
      </p>
      <div class="form-error" id="acept-error"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="document.getElementById('modal-confirmar-acept').classList.remove('open')">Cancelar</button>
      <button class="btn btn-primary" id="btn-confirmar-acept" onclick="ejecutarAceptacion()">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        Confirmar aceptación
      </button>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════
     MODAL NOTAS (solo franquiciado)
══════════════════════════════════════════ -->
<div class="modal-overlay" id="modal-notas" onclick="if(event.target===this)cerrarModalNotas()">
  <div class="modal-box" style="max-width:600px">
    <div class="modal-header">
      <h3 id="notas-titulo">Notas</h3>
      <button class="modal-close" onclick="cerrarModalNotas()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-body">
      <p class="notas-intro">Dejá una sugerencia sobre este manual. La van a ver el franquiciante de tu empresa y el administrador. Queda asociada a la versión actual del manual.</p>

      <label class="notas-label">Nueva nota</label>
      <textarea id="nota-contenido" class="nota-textarea" placeholder="Ej: En la sección 3 sugiero aclarar..." maxlength="5000"></textarea>
      <div class="form-error" id="nota-error"></div>
      <div style="display:flex;justify-content:flex-end;margin:8px 0 18px">
        <button class="btn btn-primary" id="btn-enviar-nota" onclick="enviarNota()">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
          Enviar nota
        </button>
      </div>

      <div class="notas-hist-label">Tus notas enviadas</div>
      <div id="notas-body">
        <div class="loading-msg"><div class="spinner" style="display:block"></div>Cargando...</div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="cerrarModalNotas()">Cerrar</button>
    </div>
  </div>
</div>

<!-- TOAST -->
<div class="toast" id="toast">
  <span id="toast-icon"></span>
  <span id="toast-msg"></span>
</div>

<style>
.buscar-input { background:var(--gris2);border:1px solid var(--gris2);border-radius:7px;padding:7px 12px 7px 32px;font-size:13px;color:var(--blanco);font-family:'Archivo',sans-serif;outline:none;width:260px;transition:border-color .2s; }
.buscar-input:focus { border-color:var(--dorado); }
.modal-overlay { display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:500;align-items:center;justify-content:center;padding:16px; }
.modal-overlay.open { display:flex; }
.modal-box { background:var(--gris1);border:1px solid var(--gris2);border-radius:14px;width:100%;max-height:90vh;overflow-y:auto; }
.modal-header { padding:18px 20px;border-bottom:1px solid var(--gris2);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:var(--gris1);z-index:1; }
.modal-header h3 { font-size:15px;font-weight:600;color:var(--blanco); }
.modal-close { background:transparent;border:none;cursor:pointer;color:var(--gris4);padding:4px;border-radius:5px;transition:color .15s,background .15s;display:flex; }
.modal-close:hover { color:var(--blanco);background:var(--gris2); }
.modal-body { padding:20px; }
.modal-footer { padding:14px 20px;border-top:1px solid var(--gris2);display:flex;justify-content:flex-end;gap:8px;position:sticky;bottom:0;background:var(--gris1); }
.form-error { background:rgba(226,92,92,.1);border:1px solid rgba(226,92,92,.3);border-radius:7px;padding:10px 12px;font-size:13px;color:var(--error);display:none;margin-top:8px;line-height:1.5; }
.badge-aceptado { display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:500;padding:3px 9px;border-radius:20px;background:rgba(92,184,122,.12);color:var(--exito); }
.toast { position:fixed;bottom:24px;right:24px;background:var(--gris1);border:1px solid var(--gris2);border-radius:10px;padding:12px 16px;font-size:13px;color:var(--blanco);display:flex;align-items:center;gap:10px;transform:translateY(80px);opacity:0;transition:transform .3s,opacity .3s;z-index:600;font-family:'Roboto',sans-serif;max-width:340px; }
.toast.show { transform:translateY(0);opacity:1; }
/* ── Notas / sugerencias ── */
.notas-intro { font-size:12px;color:var(--gris4);line-height:1.6;font-family:'Roboto',sans-serif;margin-bottom:14px; }
.notas-label { display:block;font-size:11px;font-weight:500;letter-spacing:.06em;text-transform:uppercase;color:var(--gris5);margin-bottom:6px; }
.nota-textarea { width:100%;min-height:90px;resize:vertical;background:var(--negro);border:1px solid var(--gris2);border-radius:7px;padding:10px 12px;font-size:13px;font-family:'Roboto',sans-serif;color:var(--blanco);outline:none;transition:border-color .2s;box-sizing:border-box;line-height:1.5; }
.nota-textarea:focus { border-color:var(--dorado); }
.nota-textarea::placeholder { color:var(--gris3); }
.notas-hist-label { font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:var(--gris5);margin-bottom:10px;padding-top:6px;border-top:1px solid var(--gris2); }
.nota-card { background:var(--negro);border:1px solid var(--gris2);border-radius:10px;padding:12px 14px;margin-bottom:10px; }
.nota-card:last-child { margin-bottom:0; }
.nota-card-top { display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:8px; }
.nota-meta { font-size:11px;color:var(--gris4);font-family:'Roboto',sans-serif; }

/* Release notes: anuncios del publicador, estilo destacado */
.nota-card.nota-release { background:rgba(196,162,107,.05);border-color:rgba(196,162,107,.3);border-left:3px solid var(--dorado); }
.nota-release-tag {
  display:inline-block;padding:2px 8px;border-radius:10px;
  font-size:9px;font-weight:600;letter-spacing:.04em;text-transform:uppercase;
  background:rgba(196,162,107,.18);color:var(--dorado);
  border:1px solid rgba(196,162,107,.4);
  font-family:'Archivo',sans-serif;
}
.nota-contenido { font-size:13px;color:var(--gris5);line-height:1.6;font-family:'Roboto',sans-serif;white-space:pre-wrap; }
.nota-estado-pill { flex-shrink:0;font-size:10px;font-weight:600;padding:3px 9px;border-radius:20px;text-transform:uppercase;letter-spacing:.04em; }
.nota-pendiente { background:rgba(201,168,76,.14);color:var(--dorado); }
.nota-leida { background:rgba(255,255,255,.07);color:var(--gris5); }
.nota-resuelta { background:rgba(92,184,122,.14);color:var(--exito); }
</style>

<script>
const BASE_PHP = '<?= BASE_URL_PHP ?>';

let todosLosManuales  = [];
let rolUsuario        = '';
// versionActivaId se usa para registrar la aceptación; se setea desde renderTabla al hacer clic en "Leer y aceptar"
let versionActivaId   = null;
let manualPendienteId = null; // id del manual cuya aceptación está pendiente de confirmar

// ── INIT ──────────────────────────────────────────────────────
async function init() {
  try {
    const me = await apiFetch('GET', '/me');
    rolUsuario = me.rol;

    // Encabezados de tabla según rol
    document.getElementById('tabla-thead').innerHTML = `
      <tr>
        <th>Manual</th>
        <th>Categoría</th>
        <th>Última actualización</th>
        <th>Versión</th>
        ${rolUsuario === 'franquiciado' ? '<th>Mi aceptación</th>' : ''}
        <th>Acción</th>
      </tr>`;

    await cargarManuales();
  } catch (e) {
    document.getElementById('tabla-body').innerHTML =
      `<tr><td colspan="6"><div class="empty-state">Error al cargar.</div></td></tr>`;
  }
}

async function cargarManuales() {
  const data = await apiFetch('GET', '/manuales');
  todosLosManuales = data;
  aplicarFiltros();
}

// ── FILTRO DE BÚSQUEDA ────────────────────────────────────────
function aplicarFiltros() {
  const texto = (document.getElementById('inp-buscar')?.value || '').toLowerCase().trim();
  let lista   = [...todosLosManuales];

  if (texto) lista = lista.filter(m =>
    m.titulo.toLowerCase().includes(texto) || (m.categoria || '').toLowerCase().includes(texto));

  renderTabla(lista);
  document.getElementById('tabla-titulo').textContent = `${lista.length} manual(es)`;
}

// ── RENDER TABLA ──────────────────────────────────────────────
function renderTabla(lista) {
  const tbody = document.getElementById('tabla-body');
  const cols  = rolUsuario === 'franquiciado' ? 6 : 5;

  if (!lista.length) {
    tbody.innerHTML = `<tr><td colspan="${cols}"><div class="empty-state">Sin manuales disponibles.</div></td></tr>`;
    return;
  }

  tbody.innerHTML = lista.map(m => {
    const version  = m.version_activa?.[0] || null;
    const verNum   = version ? `v${version.version_number}` : '—';
    const fecha    = version ? formatFecha(version.publicado_at) : formatFecha(m.created_at);
    const aceptado = m.mi_aceptacion || false;

    // Columna aceptación: solo franquiciado
    const colAceptacion = rolUsuario === 'franquiciado' ? `
      <td>
        ${aceptado
          ? `<span class="badge-aceptado"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>Aceptado</span>`
          : `<span class="estado-pill estado-pendiente">Pendiente</span>`
        }
      </td>` : '';

    // Botón de acción: empleado siempre "Ver manual"; franquiciado "Leer y aceptar" o "Ver manual"
    const btnLabel   = rolUsuario === 'empleado' || aceptado ? 'Ver manual' : 'Leer y aceptar';
    const btnStyle   = (!aceptado && rolUsuario === 'franquiciado')
      ? 'class="btn btn-primary" style="padding:5px 12px;font-size:12px"'
      : 'class="btn btn-ghost" style="padding:5px 12px;font-size:12px"';
    const btnOnclick = (!aceptado && rolUsuario === 'franquiciado' && version)
      ? `onclick="abrirParaAceptar(${m.id}, ${version.id})"`
      : `onclick="abrirManual(${m.id})"`;

    return `<tr>
      <td>
        <div style="color:var(--blanco);font-weight:500;margin-top:2px">${esc(m.titulo)}</div>
      </td>
      <td>${esc(m.categoria) || '—'}</td>
      <td style="font-size:12px;font-family:'Roboto',sans-serif;color:var(--gris4)">${fecha}</td>
      <td style="font-family:'Roboto',sans-serif">${verNum}</td>
      ${colAceptacion}
      <td>
        <div style="display:flex;gap:4px;flex-wrap:wrap;align-items:center">
          <button ${btnStyle} ${btnOnclick}>${btnLabel}</button>
          ${rolUsuario === 'franquiciado' ? `
          <button class="btn btn-ghost" style="padding:5px 12px;font-size:12px" onclick="verNotas(${m.id}, '${esc(m.titulo).replace(/'/g, "\\'")}')">Escribir notas</button>` : ''}
        </div>
      </td>
    </tr>`;
  }).join('');
}

// ── NAVEGACIÓN ────────────────────────────────────────────────
// Abre lectura.php en modo solo lectura
function abrirManual(manualId) {
  window.location.href = `${BASE_PHP}/lectura.php?id=${manualId}`;
}

// Abre lectura.php en modo aceptación (franquiciado con manual pendiente)
function abrirParaAceptar(manualId, versiónId) {
  versionActivaId   = versiónId;
  manualPendienteId = manualId;
  // Pasamos ?aceptar=1 para que lectura.php muestre el botón de aceptación
  window.location.href = `${BASE_PHP}/lectura.php?id=${manualId}&aceptar=1`;
}

// ── ACEPTACIÓN DIGITAL ────────────────────────────────────────
// Esta función se llama si la aceptación se maneja inline (sin redirigir a lectura.php).
// Mantenerla por si en el futuro el modal vuelve a estar en esta página.
function confirmarAceptacion(versiónId) {
  versionActivaId = versiónId;
  document.getElementById('acept-error').style.display = 'none';
  document.getElementById('modal-confirmar-acept').classList.add('open');
}

async function ejecutarAceptacion() {
  if (!versionActivaId) return;
  const btn = document.getElementById('btn-confirmar-acept');
  btn.disabled = true;
  btn.textContent = 'Registrando...';

  try {
    await apiFetch('POST', `/versiones/${versionActivaId}/aceptar`);
    mostrarToast('¡Manual aceptado correctamente! El registro quedó guardado.', 'exito');
    document.getElementById('modal-confirmar-acept').classList.remove('open');
    versionActivaId   = null;
    manualPendienteId = null;
    await cargarManuales();
  } catch (e) {
    const msg = e.data?.message || 'Error al registrar la aceptación.';
    document.getElementById('acept-error').textContent   = msg;
    document.getElementById('acept-error').style.display = 'block';
    btn.disabled = false;
    btn.innerHTML = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> Confirmar aceptación`;
  }
}

// ── NOTAS / SUGERENCIAS (solo franquiciado) ───────────────────
let notaManualActual = null;

async function verNotas(manualId, titulo) {
  notaManualActual = manualId;
  document.getElementById('notas-titulo').textContent = `Notas — ${titulo}`;
  document.getElementById('nota-contenido').value = '';
  document.getElementById('nota-error').style.display = 'none';
  document.getElementById('notas-body').innerHTML =
    `<div class="loading-msg"><div class="spinner" style="display:block"></div>Cargando...</div>`;
  document.getElementById('modal-notas').classList.add('open');
  await cargarNotas(manualId);
}

async function cargarNotas(manualId) {
  try {
    const notas = await apiFetch('GET', `/manuales/${manualId}/notas`);
    renderNotas(notas);
  } catch (e) {
    document.getElementById('notas-body').innerHTML =
      `<div class="empty-state">Error al cargar tus notas.</div>`;
  }
}

function renderNotas(notas) {
  const body = document.getElementById('notas-body');
  if (!notas.length) {
    body.innerHTML = `<div class="empty-state">Todavía no hay notas ni mensajes para este manual.</div>`;
    return;
  }
  const estadoLabel = { pendiente: 'Pendiente', leida: 'Leída', resuelta: 'Resuelta' };
  body.innerHTML = notas.map(n => {
    const version = n.version ? `v${n.version.version_number}` : 'Sin versión publicada';

    // Release note: anuncio del publicador (super_admin/franquiciante) al subir una versión.
    // Estilo destacado, sin badge de estado (no se gestiona como feedback).
    if (n.tipo === 'release') {
      const autor = autorReleaseLabel(n);
      return `
        <div class="nota-card nota-release">
          <div class="nota-card-top">
            <div>
              <span class="nota-release-tag">Mensaje del publicador · ${version}</span>
              <span class="nota-meta" style="display:block;margin-top:4px">${esc(autor)} · ${formatFechaHora(n.created_at)}</span>
            </div>
          </div>
          <div class="nota-contenido">${esc(n.contenido)}</div>
        </div>`;
    }

    // Feedback (nota propia del franquiciado)
    return `
      <div class="nota-card">
        <div class="nota-card-top">
          <span class="nota-meta">${version} · ${formatFechaHora(n.created_at)}</span>
          <span class="nota-estado-pill nota-${n.estado}">${estadoLabel[n.estado] || n.estado}</span>
        </div>
        <div class="nota-contenido">${esc(n.contenido)}</div>
      </div>`;
  }).join('');
}

// Nombre legible del autor de una release note (el que publicó la versión)
function autorReleaseLabel(n) {
  const u = n.autor;
  if (!u) return 'Publicador';
  const p = u.system_admin || u.super_admin || u.franchise_staff;
  if (p?.nombre) return `${p.nombre} ${p.apellido}`;
  return u.email || 'Publicador';
}

async function enviarNota() {
  const contenido = document.getElementById('nota-contenido').value.trim();
  const errEl = document.getElementById('nota-error');
  errEl.style.display = 'none';
  if (!contenido) {
    errEl.textContent = 'Escribí una nota antes de enviar.';
    errEl.style.display = 'block';
    return;
  }
  const btn = document.getElementById('btn-enviar-nota');
  btn.disabled = true;
  try {
    await apiFetch('POST', `/manuales/${notaManualActual}/notas`, { contenido });
    document.getElementById('nota-contenido').value = '';
    mostrarToast('Nota enviada.', 'exito');
    await cargarNotas(notaManualActual);
  } catch (e) {
    errEl.textContent = e.data?.error || e.data?.message || 'No se pudo enviar la nota.';
    errEl.style.display = 'block';
  } finally {
    btn.disabled = false;
  }
}

function cerrarModalNotas() {
  document.getElementById('modal-notas').classList.remove('open');
  notaManualActual = null;
}

// ── HELPERS ───────────────────────────────────────────────────
function formatFecha(str) { if (!str) return '—'; return new Date(str).toLocaleDateString('es-AR', {day:'2-digit',month:'2-digit',year:'numeric'}); }
function formatFechaHora(str) { if (!str) return '—'; return new Date(str).toLocaleString('es-AR', {day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'}); }
function esc(str) { if (!str) return ''; return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

let toastTimer;
function mostrarToast(msg, tipo = 'exito') {
  const el = document.getElementById('toast');
  const icon = tipo === 'exito'
    ? `<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--exito)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>`
    : `<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--error)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>`;
  document.getElementById('toast-icon').innerHTML  = icon;
  document.getElementById('toast-msg').textContent = msg;
  el.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => el.classList.remove('show'), 4000);
}

document.addEventListener('keydown', e => {
  if (e.key === 'Escape') { document.getElementById('modal-confirmar-acept').classList.remove('open'); cerrarModalNotas(); }
});

document.addEventListener('DOMContentLoaded', () => init());
</script>

<?php include 'layout/footer.php'; ?>