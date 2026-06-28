<?php
require_once __DIR__ . '/layout/config.php';
require_once __DIR__ . '/layout/auth.php';
verificarSesion('franquiciante'); // super_admin pasa por bypass; franq/empleado quedan fuera
$titulo        = 'Categorías';
$pagina_actual = 'categorias';
include 'layout/head.php';
?>

<div class="app-layout">
  <?php include 'layout/topbar.php'; ?>
  <div class="app-body">
    <?php include 'layout/sidebar.php'; ?>
    <main class="main-content">

      <div class="page-header" style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px">
        <div>
          <button id="btn-volver" class="accion-btn" style="display:none;color:var(--gris5);margin-bottom:6px;padding:4px 8px;font-size:12px" onclick="volverAEmpresas()">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            Volver a empresas
          </button>
          <div class="page-title" id="page-title">Categorías</div>
          <div class="page-sub" id="page-sub">Cargando...</div>
        </div>
        <button id="btn-nueva" class="btn btn-primary" style="display:none" onclick="abrirModalCrear()">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Nueva categoría
        </button>
      </div>

      <!-- Filtros (solo en vista 1, solo super_admin) -->
      <div id="filtros-empresas" style="display:none;margin-bottom:20px">
        <button id="btn-toggle-suspendidas" class="filtro-btn" onclick="toggleSuspendidas(this)">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:4px"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          Mostrar suspendidas
        </button>
      </div>

      <!-- Contenedor principal (cambia según la vista) -->
      <div id="contenido">
        <div class="loading-msg"><div class="spinner" style="display:block"></div>Cargando...</div>
      </div>

    </main>
  </div>
</div>

<!-- ══════════════════════════════════════════════════
     MODAL CREAR / EDITAR CATEGORÍA
══════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modal-categoria">
  <div class="modal-box" style="max-width:480px">
    <div class="modal-header">
      <h3 id="modal-titulo">Nueva categoría</h3>
      <button class="modal-close" onclick="cerrarModal()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="form-id">

      <div class="form-group">
        <label>Nombre *</label>
        <input type="text" id="form-name" placeholder="Ej: Distribuidor" maxlength="100" autocomplete="off">
      </div>

      <div class="form-group">
        <label>Descripción</label>
        <textarea id="form-description" placeholder="Para qué sirve esta categoría, qué tipo de franquiciados la usan..." maxlength="1000" rows="3" style="resize:vertical"></textarea>
      </div>

      <div class="form-error" id="form-error"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="cerrarModal()">Cancelar</button>
      <button class="btn btn-primary" id="btn-guardar" onclick="guardar()">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
        Guardar
      </button>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════
     MODAL TOGGLE (activar/desactivar)
══════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modal-toggle" onclick="if(event.target===this)cerrarModalToggle()">
  <div class="modal-box" style="max-width:380px">
    <div class="modal-header">
      <h3 id="toggle-titulo">Confirmar acción</h3>
      <button class="modal-close" onclick="cerrarModalToggle()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-body">
      <p id="toggle-msg" style="font-size:14px;color:var(--gris5);line-height:1.6;font-family:'Archivo Narrow',sans-serif"></p>
      <div class="form-error" id="toggle-error"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="cerrarModalToggle()">Cancelar</button>
      <button class="btn" id="btn-toggle-confirmar" onclick="confirmarToggle()">Confirmar</button>
    </div>
  </div>
</div>

<!-- ── TOAST ─────────────────────────────────────────────────── -->
<div class="toast" id="toast"><span id="toast-icon"></span><span id="toast-msg"></span></div>

<style>
.filtro-btn {
  padding:6px 14px;border-radius:20px;border:1px solid var(--gris2);
  background:transparent;font-size:12px;font-family:'Archivo',sans-serif;
  color:var(--gris4);cursor:pointer;transition:all .15s;
}
.filtro-btn:hover  { border-color:var(--gris3);color:var(--blanco); }
.filtro-btn.active { background:rgba(201,168,76,.12);border-color:rgba(201,168,76,.3);color:var(--dorado); }

/* ── Grid de empresas (vista 1) ────────────────────────────── */
.empresas-grid {
  display:grid;
  grid-template-columns:repeat(auto-fill, minmax(280px, 1fr));
  gap:14px;
}
.empresa-card {
  background:var(--gris1);
  border:1px solid var(--gris2);
  border-radius:12px;
  padding:18px 18px 16px;
  cursor:pointer;
  transition:border-color .15s, transform .1s;
  display:flex;
  flex-direction:column;
  gap:12px;
}
.empresa-card:hover {
  border-color:var(--dorado);
  transform:translateY(-1px);
}
.empresa-card-header {
  display:flex;
  align-items:flex-start;
  justify-content:space-between;
  gap:8px;
}
.empresa-card h3 {
  font-size:14px;
  font-weight:600;
  color:var(--blanco);
  line-height:1.3;
  margin:0;
}
.badge-suspendida {
  font-size:10px;
  font-family:'Archivo',sans-serif;
  padding:2px 8px;
  border-radius:10px;
  background:rgba(226,92,92,.12);
  color:var(--error);
  border:1px solid rgba(226,92,92,.3);
  white-space:nowrap;
  flex-shrink:0;
}
.empresa-card-stat {
  font-size:12px;
  color:var(--gris4);
  font-family:'Archivo Narrow',sans-serif;
}
.empresa-card-stat strong {
  color:var(--dorado);
  font-family:'Archivo',sans-serif;
  font-weight:600;
  font-size:13px;
}
.empresa-card-stat.vacio {
  color:var(--gris3);
  font-style:italic;
}
.empresa-card-arrow {
  align-self:flex-end;
  color:var(--gris3);
  transition:color .15s, transform .15s;
}
.empresa-card:hover .empresa-card-arrow {
  color:var(--dorado);
  transform:translateX(2px);
}

/* ── Badges activa/inactiva (compartidos por la tabla) ───── */
.badge-inactiva {
  font-size:10px;
  font-family:'Archivo',sans-serif;
  padding:2px 8px;
  border-radius:10px;
  background:rgba(150,150,150,.12);
  color:var(--gris4);
  border:1px solid var(--gris3);
}
.badge-activa {
  font-size:10px;
  font-family:'Archivo',sans-serif;
  padding:2px 8px;
  border-radius:10px;
  background:rgba(95,179,128,.12);
  color:var(--exito);
  border:1px solid rgba(95,179,128,.3);
}

/* ── Tabla de categorías (vista 2) — consistente con usuarios.php ── */
.tr-inactiva { opacity:.65; }
.cat-descripcion {
  font-size:11px;
  color:var(--gris4);
  font-family:'Archivo Narrow',sans-serif;
  margin-top:3px;
  line-height:1.4;
  max-width:340px;
}
.cat-stat-num {
  font-family:'Archivo',sans-serif;
  font-weight:600;
  color:var(--blanco);
}

/* ── Modal ─────────────────────────────────────────────────── */
.modal-overlay { display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:500;align-items:center;justify-content:center;padding:16px; }
.modal-overlay.open { display:flex; }
.modal-box { background:var(--gris1);border:1px solid var(--gris2);border-radius:14px;width:100%;max-width:560px;max-height:90vh;overflow-y:auto; }
.modal-header { padding:18px 20px;border-bottom:1px solid var(--gris2);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:var(--gris1);z-index:1; }
.modal-header h3 { font-size:15px;font-weight:600;color:var(--blanco); }
.modal-close { background:transparent;border:none;cursor:pointer;color:var(--gris4);padding:4px;border-radius:5px;transition:color .15s,background .15s;display:flex; }
.modal-close:hover { color:var(--blanco);background:var(--gris2); }
.modal-body { padding:20px; }
.modal-footer { padding:14px 20px;border-top:1px solid var(--gris2);display:flex;justify-content:flex-end;gap:8px;position:sticky;bottom:0;background:var(--gris1); }
.form-group { margin-bottom:14px; }
.form-group label { display:block;font-size:11px;font-weight:500;letter-spacing:.06em;text-transform:uppercase;color:var(--gris5);margin-bottom:6px; }
.form-group input[type=text],
.form-group textarea {
  width:100%;background:var(--negro);border:1px solid var(--gris2);
  border-radius:7px;padding:10px 12px;font-size:13px;
  font-family:'Archivo',sans-serif;color:var(--blanco);
  outline:none;transition:border-color .2s;
  box-sizing:border-box;
}
.form-group input:focus, .form-group textarea:focus { border-color:var(--dorado); }
.form-group input::placeholder, .form-group textarea::placeholder { color:var(--gris3); }
.form-error { background:rgba(226,92,92,.1);border:1px solid rgba(226,92,92,.3);border-radius:7px;padding:10px 12px;font-size:13px;color:var(--error);display:none;margin-top:8px;line-height:1.5; }
.accion-btn { background:transparent;border:none;cursor:pointer;padding:5px 8px;border-radius:5px;font-size:12px;font-family:'Archivo',sans-serif;transition:background .15s;display:inline-flex;align-items:center;gap:4px; }
.accion-btn:hover { background:var(--gris2); }

.toast { position:fixed;bottom:24px;right:24px;background:var(--gris1);border:1px solid var(--gris2);border-radius:10px;padding:12px 16px;font-size:13px;color:var(--blanco);display:flex;align-items:center;gap:10px;transform:translateY(80px);opacity:0;transition:transform .3s,opacity .3s;z-index:600;font-family:'Archivo Narrow',sans-serif;max-width:320px; }
.toast.show { transform:translateY(0);opacity:1; }

/* Estado vacío */
.empty-state {
  text-align:center;
  padding:60px 20px;
  color:var(--gris4);
  font-size:13px;
  font-family:'Archivo Narrow',sans-serif;
}
.empty-state svg {
  display:block;
  margin:0 auto 12px;
  color:var(--gris3);
}
.empty-state strong {
  display:block;
  color:var(--gris5);
  font-family:'Archivo',sans-serif;
  font-size:14px;
  font-weight:500;
  margin-bottom:4px;
}
</style>

<script>
// ── STATE ─────────────────────────────────────────────────────
let miRol               = '';
let miEmpresaId         = null;
let miEmpresaNombre     = '';
let empresas            = []; // solo super_admin
let categorias          = []; // todas las categorías (filtradas por empresa al renderizar)
let empresaSeleccionada = null; // { id, nombre } — cuando se está en vista 2
let mostrarSuspendidas  = false;
let modoEdicion         = false;
let pendingToggle       = null; // { id, is_active }

// ── INIT ──────────────────────────────────────────────────────
async function init() {
  try {
    const me = await apiFetch('GET', '/me');
    miRol           = me.rol;
    miEmpresaId     = me.empresa_id;
    miEmpresaNombre = me.empresa?.nombre || '';

    if (miRol === 'super_admin') {
      // Cargamos empresas + categorías (todas) en paralelo. Las categorías se
      // agrupan por empresa en JS para mostrar el conteo en cada card.
      const [emps, cats] = await Promise.all([
        apiFetch('GET', '/empresas'),
        apiFetch('GET', '/categorias'),
      ]);
      empresas   = emps || [];
      categorias = cats || [];

      // Si la URL trae ?empresa_id=X, entrar directo a vista 2
      const urlParams    = new URLSearchParams(location.search);
      const empresaIdUrl = parseInt(urlParams.get('empresa_id') || '0');
      if (empresaIdUrl) {
        const emp = empresas.find(e => e.id === empresaIdUrl);
        if (emp) {
          empresaSeleccionada = { id: emp.id, nombre: emp.nombre };
          renderVistaCategorias();
          return;
        }
      }

      renderVistaEmpresas();

    } else if (miRol === 'franquiciante') {
      // Va directo a la vista de SU empresa
      categorias = await apiFetch('GET', '/categorias') || [];
      empresaSeleccionada = { id: miEmpresaId, nombre: miEmpresaNombre || 'Mi empresa' };
      renderVistaCategorias();

    } else {
      // verificarSesion ya debería haber bloqueado, pero por las dudas
      document.getElementById('contenido').innerHTML =
        `<div class="empty-state"><strong>Sin acceso</strong>No tenés permisos para gestionar categorías.</div>`;
    }
  } catch (e) {
    document.getElementById('contenido').innerHTML =
      `<div class="empty-state"><strong>Error al cargar</strong>${e.data?.message || 'Intentá recargar la página.'}</div>`;
    document.getElementById('page-sub').textContent = '';
  }
}

// ══════════════════════════════════════════════════════════════
// VISTA 1 — Lista de empresas (solo super_admin)
// ══════════════════════════════════════════════════════════════
function renderVistaEmpresas() {
  document.getElementById('btn-volver').style.display       = 'none';
  document.getElementById('btn-nueva').style.display        = 'none';
  document.getElementById('filtros-empresas').style.display = 'block';
  document.getElementById('page-title').textContent         = 'Categorías';

  // Filtrar empresas según el toggle de suspendidas
  const visibles = mostrarSuspendidas
    ? empresas
    : empresas.filter(e => e.activa);

  document.getElementById('page-sub').textContent =
    `Elegí una empresa para gestionar sus categorías — ${visibles.length} empresa(s)`;

  if (!visibles.length) {
    document.getElementById('contenido').innerHTML = `
      <div class="empty-state">
        <strong>Sin empresas para mostrar</strong>
        ${mostrarSuspendidas ? 'No hay empresas registradas.' : 'No hay empresas activas. Probá mostrar las suspendidas.'}
      </div>`;
    return;
  }

  // Conteos por empresa (de las que ya están en memoria)
  const conteoPorEmpresa = {};
  for (const c of categorias) {
    if (!conteoPorEmpresa[c.empresa_id]) conteoPorEmpresa[c.empresa_id] = { total: 0, activas: 0 };
    conteoPorEmpresa[c.empresa_id].total++;
    if (c.is_active) conteoPorEmpresa[c.empresa_id].activas++;
  }

  document.getElementById('contenido').innerHTML = `
    <div class="empresas-grid">
      ${visibles.map(e => {
        const cnt = conteoPorEmpresa[e.id] || { total: 0, activas: 0 };
        const statHtml = cnt.total === 0
          ? `<div class="empresa-card-stat vacio">Sin categorías configuradas</div>`
          : `<div class="empresa-card-stat"><strong>${cnt.activas}</strong> categoría(s) activa(s)${cnt.total > cnt.activas ? ` · ${cnt.total - cnt.activas} inactiva(s)` : ''}</div>`;

        return `
          <div class="empresa-card" onclick="seleccionarEmpresa(${e.id})">
            <div class="empresa-card-header">
              <h3>${esc(e.nombre)}</h3>
              ${!e.activa ? `<span class="badge-suspendida">Suspendida</span>` : ''}
            </div>
            ${statHtml}
            <div class="empresa-card-arrow">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
            </div>
          </div>`;
      }).join('')}
    </div>`;
}

function seleccionarEmpresa(empresaId) {
  const emp = empresas.find(e => e.id === empresaId);
  if (!emp) return;
  empresaSeleccionada = { id: emp.id, nombre: emp.nombre };
  // Cambiar URL sin recargar (para que el back del navegador funcione)
  history.pushState({ vista: 'categorias', empresaId: emp.id }, '', `${BASE_URL}/categorias.php?empresa_id=${emp.id}`);
  renderVistaCategorias();
}

function volverAEmpresas() {
  empresaSeleccionada = null;
  history.pushState({ vista: 'empresas' }, '', `${BASE_URL}/categorias.php`);
  renderVistaEmpresas();
}

// El botón atrás del navegador re-renderiza la vista correcta
window.addEventListener('popstate', () => {
  if (miRol !== 'super_admin') return; // franquiciante no navega entre vistas
  const urlParams    = new URLSearchParams(location.search);
  const empresaIdUrl = parseInt(urlParams.get('empresa_id') || '0');
  if (empresaIdUrl) {
    const emp = empresas.find(e => e.id === empresaIdUrl);
    if (emp) {
      empresaSeleccionada = { id: emp.id, nombre: emp.nombre };
      renderVistaCategorias();
      return;
    }
  }
  empresaSeleccionada = null;
  renderVistaEmpresas();
});

function toggleSuspendidas(btn) {
  mostrarSuspendidas = !mostrarSuspendidas;
  btn.classList.toggle('active', mostrarSuspendidas);
  renderVistaEmpresas();
}

// ══════════════════════════════════════════════════════════════
// VISTA 2 — Categorías de una empresa
// ══════════════════════════════════════════════════════════════
function renderVistaCategorias() {
  if (!empresaSeleccionada) return;

  // super_admin tiene botón volver; franquiciante no
  document.getElementById('btn-volver').style.display       = (miRol === 'super_admin') ? 'inline-flex' : 'none';
  document.getElementById('filtros-empresas').style.display = 'none';
  document.getElementById('btn-nueva').style.display        = 'inline-flex';

  document.getElementById('page-title').textContent = `Categorías · ${empresaSeleccionada.nombre}`;

  const catsEmpresa = categorias.filter(c => c.empresa_id === empresaSeleccionada.id);

  document.getElementById('page-sub').textContent =
    catsEmpresa.length === 0
      ? 'Aún no hay categorías. Creá la primera con el botón de arriba.'
      : `${catsEmpresa.length} categoría(s) en esta empresa`;

  if (!catsEmpresa.length) {
    document.getElementById('contenido').innerHTML = `
      <div class="empty-state">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
        <strong>Sin categorías todavía</strong>
        Las categorías te permiten agrupar franquiciados y asignarles manuales y documentos de forma masiva.
      </div>`;
    return;
  }

  document.getElementById('contenido').innerHTML = `
    <div class="tabla-wrap">
      <div class="tabla-header">
        <h3>${catsEmpresa.length} resultado(s)</h3>
      </div>
      <table>
        <thead>
          <tr>
            <th>Categoría</th>
            <th style="text-align:center">Usuarios</th>
            <th style="text-align:center">Manuales</th>
            <th style="text-align:center">Documentos</th>
            <th>Estado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          ${catsEmpresa.map(c => `
            <tr class="${!c.is_active ? 'tr-inactiva' : ''}">
              <td>
                <div style="color:var(--blanco);font-weight:500">${esc(c.name)}</div>
                ${c.description ? `<div class="cat-descripcion">${esc(c.description)}</div>` : ''}
              </td>
              <td style="text-align:center" class="cat-stat-num">${c.usuarios_count ?? 0}</td>
              <td style="text-align:center" class="cat-stat-num">${c.manuales_asignados_count ?? 0}</td>
              <td style="text-align:center" class="cat-stat-num">${c.documentos_asignados_count ?? 0}</td>
              <td>${c.is_active
                ? `<span class="badge-activa">Activa</span>`
                : `<span class="badge-inactiva">Inactiva</span>`}
              </td>
              <td>
                <div style="display:flex;gap:4px;align-items:center;flex-wrap:wrap">
                  <button class="accion-btn" style="color:var(--gris5)" onclick="abrirModalEditar(${c.id})">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    Editar
                  </button>
                  <button class="accion-btn" style="color:${c.is_active ? 'var(--error)' : 'var(--exito)'}" onclick="abrirModalToggle(${c.id}, ${c.is_active ? 'true' : 'false'})">
                    ${c.is_active
                      ? `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg> Desactivar`
                      : `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> Activar`}
                  </button>
                </div>
              </td>
            </tr>
          `).join('')}
        </tbody>
      </table>
    </div>`;
}

// ══════════════════════════════════════════════════════════════
// MODAL CREAR / EDITAR
// ══════════════════════════════════════════════════════════════
function abrirModalCrear() {
  if (!empresaSeleccionada) return;
  modoEdicion = false;
  limpiarForm();
  document.getElementById('modal-titulo').textContent = `Nueva categoría · ${empresaSeleccionada.nombre}`;
  document.getElementById('modal-categoria').classList.add('open');
  setTimeout(() => document.getElementById('form-name').focus(), 100);
}

function abrirModalEditar(id) {
  const c = categorias.find(x => x.id === id);
  if (!c) return;
  modoEdicion = true;
  limpiarForm();

  document.getElementById('modal-titulo').textContent = 'Editar categoría';
  document.getElementById('form-id').value            = c.id;
  document.getElementById('form-name').value          = c.name || '';
  document.getElementById('form-description').value   = c.description || '';

  document.getElementById('modal-categoria').classList.add('open');
  setTimeout(() => document.getElementById('form-name').focus(), 100);
}

function cerrarModal() {
  document.getElementById('modal-categoria').classList.remove('open');
}

function limpiarForm() {
  document.getElementById('form-id').value          = '';
  document.getElementById('form-name').value        = '';
  document.getElementById('form-description').value = '';
  const err = document.getElementById('form-error');
  err.style.display = 'none'; err.textContent = '';
}

async function guardar() {
  const id          = document.getElementById('form-id').value;
  const name        = document.getElementById('form-name').value.trim();
  const description = document.getElementById('form-description').value.trim();
  const btn         = document.getElementById('btn-guardar');
  const errEl       = document.getElementById('form-error');
  errEl.style.display = 'none';

  if (!name) {
    errEl.textContent = 'El nombre es obligatorio.';
    errEl.style.display = 'block'; return;
  }

  const labelOriginal = btn.innerHTML;
  btn.disabled = true; btn.textContent = 'Guardando...';

  try {
    const body = { name, description: description || null };

    if (modoEdicion) {
      await apiFetch('PUT', `/categorias/${id}`, body);
      mostrarToast('Categoría actualizada.', 'exito');
    } else {
      // En la creación, super_admin tiene que mandar empresa_id; franquiciante no.
      if (miRol === 'super_admin') body.empresa_id = empresaSeleccionada.id;
      await apiFetch('POST', '/categorias', body);
      mostrarToast('Categoría creada.', 'exito');
    }

    cerrarModal();
    await recargarCategorias();

  } catch (e) {
    const msg = e.data?.errors
      ? Object.values(e.data.errors).flat().join(' ')
      : (e.data?.error || e.data?.message || 'Error al guardar.');
    errEl.textContent = msg;
    errEl.style.display = 'block';
  } finally {
    btn.disabled = false;
    btn.innerHTML = labelOriginal;
  }
}

// ══════════════════════════════════════════════════════════════
// TOGGLE ACTIVAR / DESACTIVAR
// ══════════════════════════════════════════════════════════════
function abrirModalToggle(id, isActive) {
  const c = categorias.find(x => x.id === id);
  if (!c) return;
  pendingToggle = { id, isActive };

  document.getElementById('toggle-titulo').textContent = isActive ? 'Desactivar categoría' : 'Activar categoría';
  document.getElementById('toggle-msg').textContent    = isActive
    ? `¿Desactivar "${c.name}"? Las asignaciones existentes se conservan pero los usuarios dejan de ver los manuales y documentos vinculados a esta categoría.`
    : `¿Activar "${c.name}"? Los usuarios que la tienen asignada volverán a ver el contenido vinculado.`;

  const btn = document.getElementById('btn-toggle-confirmar');
  btn.className   = `btn ${isActive ? 'btn-danger' : 'btn-success'}`;
  btn.textContent = isActive ? 'Desactivar' : 'Activar';

  document.getElementById('toggle-error').style.display = 'none';
  document.getElementById('modal-toggle').classList.add('open');
}

function cerrarModalToggle() {
  document.getElementById('modal-toggle').classList.remove('open');
  pendingToggle = null;
}

async function confirmarToggle() {
  if (!pendingToggle) return;
  const { id, isActive } = pendingToggle;
  const btn = document.getElementById('btn-toggle-confirmar');
  const labelOriginal = btn.textContent;
  btn.disabled = true; btn.textContent = 'Procesando...';

  try {
    await apiFetch('POST', `/categorias/${id}/toggle-activa`);
    mostrarToast(isActive ? 'Categoría desactivada.' : 'Categoría activada.', isActive ? 'error' : 'exito');
    cerrarModalToggle();
    await recargarCategorias();
  } catch (e) {
    document.getElementById('toggle-error').textContent  = e.data?.error || e.data?.message || 'Error al procesar.';
    document.getElementById('toggle-error').style.display = 'block';
    btn.disabled = false;
    btn.textContent = labelOriginal;
  }
}

// ══════════════════════════════════════════════════════════════
// HELPERS
// ══════════════════════════════════════════════════════════════
async function recargarCategorias() {
  // Recargamos el listado completo y re-renderizamos la vista actual
  categorias = await apiFetch('GET', '/categorias') || [];
  if (empresaSeleccionada) renderVistaCategorias();
  else if (miRol === 'super_admin') renderVistaEmpresas();
}

let toastTimer;
function mostrarToast(msg, tipo = 'exito') {
  const el   = document.getElementById('toast');
  const icon = tipo === 'exito'
    ? `<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--exito)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>`
    : `<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--error)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>`;
  document.getElementById('toast-icon').innerHTML  = icon;
  document.getElementById('toast-msg').textContent = msg;
  el.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => el.classList.remove('show'), 3500);
}

function esc(str) {
  if (str === null || str === undefined || str === '') return '';
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

document.addEventListener('keydown', e => {
  if (e.key === 'Escape') { cerrarModal(); cerrarModalToggle(); }
});

init();
</script>

<?php include 'layout/footer.php'; ?>