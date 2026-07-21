<?php
require_once __DIR__ . '/layout/config.php';
require_once __DIR__ . '/layout/auth.php';
verificarSesion('franquiciante');
$titulo        = 'Aceptaciones';
$pagina_actual = 'aceptaciones';
include 'layout/head.php';
?>

<div class="app-layout">
  <?php include 'layout/topbar.php'; ?>
  <div class="app-body">
    <?php include 'layout/sidebar.php'; ?>
    <main class="main-content">

      <div class="page-header">
        <div>
          <div class="page-title">Aceptaciones</div>
          <div class="page-sub">Compliance de manuales — aceptaciones digitales y firmas físicas</div>
        </div>
        <button class="btn btn-primary" id="btn-subir" style="display:none" onclick="abrirModalSubir()" disabled>
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Subir firma
        </button>
      </div>

      <!-- Filtros -->
      <div id="filtros-wrap" style="display:none;gap:8px;margin-bottom:20px;flex-wrap:wrap;align-items:center">

        <!-- Combobox empresa — solo super_admin (mismo patrón que documentos.php) -->
        <div id="grupo-sel-empresa" style="display:none;align-items:center;gap:8px;position:relative">
          <div id="empresa-combo" style="position:relative;width:240px">
            <input type="text" id="inp-empresa" placeholder="Buscar empresa..." autocomplete="off" name="combo-empresa-ace"
                   class="buscar-input" style="width:100%;box-sizing:border-box;padding-right:30px"
                   oninput="filtrarOpcionesEmpresa()" onfocus="filtrarOpcionesEmpresa()">
            <svg style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--gris4)" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <button type="button" id="empresa-clear" onclick="limpiarEmpresa()" title="Mostrar todas las empresas"
                    style="display:none;position:absolute;right:8px;top:50%;transform:translateY(-50%);background:transparent;border:none;color:var(--gris4);cursor:pointer;padding:2px;line-height:0">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
            <div id="empresa-opciones" class="combo-opciones"></div>
          </div>
        </div>

        <select id="sel-franquicia" class="filtro-select" onchange="onFiltroChange()" disabled>
          <option value="">Todas las sucursales</option>
        </select>

        <select id="sel-manual" class="filtro-select" onchange="onManualFiltroChange()" disabled>
          <option value="">Todos los manuales</option>
        </select>

        <select id="sel-version" class="filtro-select" onchange="onFiltroChange()" disabled>
          <option value="">Solo versiones activas</option>
        </select>

        <div style="margin-left:auto;position:relative">
          <input type="text" id="inp-buscar" placeholder="Buscar socio, sucursal, manual..." oninput="aplicarFiltroTexto()" class="buscar-input" style="width:260px">
          <svg style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--gris4)" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        </div>
      </div>

      <!-- Tabla -->
      <div class="tabla-wrap">
        <div class="tabla-header">
          <span id="tabla-titulo">Cargando...</span>
        </div>
        <div style="overflow-x:auto">
          <table class="tabla" id="tabla-ace">
            <thead>
              <tr>
                <th>Socio comercial</th>
                <th>Sucursal</th>
                <th>Manual</th>
                <th>Versión</th>
                <th>Estado</th>
                <th>Aceptación digital</th>
                <th>Firma física</th>
                <th style="text-align:right">Acciones</th>
              </tr>
            </thead>
            <tbody id="tabla-body">
              <tr><td colspan="8"><div class="empty-state">Cargando...</div></td></tr>
            </tbody>
          </table>
        </div>
      </div>

    </main>
  </div>
</div>

<!-- ══════════════════════════════════════════════════
     MODAL SUBIR FIRMA
══════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modal-subir">
  <div class="modal-box">
    <div class="modal-header">
      <h3>Subir firma física</h3>
      <button class="modal-close" onclick="cerrarModal()">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-body">

      <div class="form-group" id="grupo-empresa-modal" style="display:none">
        <label>Empresa *</label>
        <select id="modal-empresa" class="form-select" onchange="onEmpresaModalChange()">
          <option value="">Seleccioná una empresa</option>
        </select>
      </div>

      <div class="form-group">
        <label>Manual *</label>
        <select id="modal-manual" class="form-select" onchange="onManualModalChange()" disabled>
          <option value="">Elegí primero una empresa</option>
        </select>
      </div>

      <div class="form-group">
        <label>Versión *</label>
        <select id="modal-version" class="form-select" onchange="onVersionModalChange()" disabled>
          <option value="">Elegí primero un manual</option>
        </select>
      </div>

      <div class="form-group">
        <label>Socio comercial *</label>
        <select id="modal-socio" class="form-select" onchange="onSocioModalChange()" disabled>
          <option value="">Elegí primero una versión</option>
        </select>
        <div id="socio-info" class="info-box" style="display:none;margin-top:8px"></div>
        <div id="socio-warning" class="info-box warning" style="display:none;margin-top:8px">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
          Este socio ya tiene una firma cargada para esta versión. Al guardar, se reemplazará.
        </div>
      </div>

      <div class="form-group">
        <label>Archivo PDF *</label>
        <div id="drop-zone" class="drop-zone" onclick="document.getElementById('modal-archivo').click()">
          <input type="file" id="modal-archivo" accept="application/pdf" style="display:none" onchange="onArchivoSelected()">
          <div id="drop-zone-content">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color:var(--gris4);margin-bottom:6px"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            <div style="font-size:13px;color:var(--gris5);font-family:'Roboto',sans-serif">Hacé click o arrastrá un PDF acá</div>
            <div style="font-size:11px;color:var(--gris4);margin-top:4px;font-family:'Roboto',sans-serif">Máximo 10 MB</div>
          </div>
        </div>
      </div>

      <div class="form-group">
        <label>Notas (opcional)</label>
        <textarea id="modal-notas" maxlength="500" rows="3" class="form-input"
                  placeholder="Ej: Firma recibida el 02/07 en la reunión mensual."
                  style="resize:vertical;font-family:'Roboto',sans-serif"></textarea>
      </div>

      <div class="form-error" id="modal-error" style="display:none"></div>

    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="cerrarModal()">Cancelar</button>
      <button class="btn btn-primary" id="btn-guardar" onclick="guardar()">Subir firma</button>
    </div>
  </div>
</div>

<!-- ── TOAST ──────────────────────────────────────────────────── -->
<div class="toast" id="toast"><span id="toast-icon"></span><span id="toast-msg"></span></div>

<style>
/* ── Filtros (misma clase que documentos.php) ─────────────── */
.filtro-select {
  background: var(--gris2); border: 1px solid var(--gris2);
  border-radius: 7px; color: var(--gris5); font-size: 12px;
  font-family: 'Roboto', sans-serif; padding: 7px 10px;
  cursor: pointer; outline: none; transition: border-color .2s;
}
.filtro-select:focus { border-color: var(--dorado); }
.filtro-select:disabled { opacity: .5; cursor: not-allowed; }
.filtro-select option { background: var(--gris1); }

.buscar-input {
  background: var(--gris1); border: 1px solid var(--gris2);
  border-radius: 8px; padding: 8px 12px 8px 32px;
  font-size: 13px; font-family: 'Roboto', sans-serif;
  color: var(--blanco); outline: none; transition: border-color .2s;
}
.buscar-input:focus { border-color: var(--dorado); }
.buscar-input::placeholder { color: var(--gris3); }

/* ── Combobox de empresa ──────────────────────────────────── */
.combo-opciones { display:none;position:absolute;top:calc(100% + 4px);left:0;right:0;max-height:240px;overflow-y:auto;background:var(--gris1);border:1px solid var(--gris2);border-radius:8px;z-index:50;box-shadow:0 8px 24px rgba(0,0,0,.4); }
.combo-opcion { padding:9px 12px;font-size:13px;color:var(--gris5);cursor:pointer;font-family:'Roboto',sans-serif;transition:background .12s; }
.combo-opcion:hover { background:var(--gris2);color:var(--blanco); }
.combo-vacio { padding:10px 12px;font-size:12px;color:var(--gris4);font-family:'Roboto',sans-serif; }

/* ── Tabla ────────────────────────────────────────────────── */
.tabla { width: 100%; border-collapse: collapse; }
.tabla th { font-size: 11px; font-weight: 600; letter-spacing: .07em; text-transform: uppercase; color: var(--gris4); padding: 10px 16px; text-align: left; border-bottom: 1px solid var(--gris2); white-space: nowrap; }
.tabla td { padding: 13px 16px; font-size: 13px; color: var(--gris5); border-bottom: 1px solid var(--gris2); font-family: 'Roboto', sans-serif; vertical-align: middle; }
.tabla tr:last-child td { border-bottom: none; }
.tabla tr:hover td { background: rgba(255,255,255,.02); }

/* ── Celda del socio (nombre + email chico) ───────────────── */
.socio-cell { display: flex; flex-direction: column; gap: 2px; }
.socio-nombre { font-weight: 500; color: var(--blanco); }
.socio-email { font-size: 11px; color: var(--gris4); font-family: 'Roboto', sans-serif; }

/* ── Badge de versión ─────────────────────────────────────── */
.badge-version {
  display: inline-flex; align-items: center; gap: 4px;
  background: rgba(101,163,255,.12); color: #65a3ff;
  border: 1px solid rgba(101,163,255,.25);
  padding: 3px 9px; border-radius: 20px;
  font-size: 11px; font-weight: 600; letter-spacing: .06em;
  text-transform: uppercase; white-space: nowrap;
}
.badge-version.activa {
  background: rgba(201,168,76,.12); color: var(--dorado);
  border-color: rgba(201,168,76,.25);
}

/* ── Sin dato ─────────────────────────────────────────────── */
.sin-dato { color: var(--gris4); font-style: italic; }

/* ── Estado cell con icono ────────────────────────────────── */
.estado-cell { display: inline-flex; align-items: center; gap: 5px; font-size: 12px; }
.estado-cell.ok { color: var(--exito); }
.estado-cell.miss { color: var(--gris4); font-style: italic; }

/* ── Acciones cell (text-align en el td, sin display:flex para
       que la fila mantenga el border-bottom continuo) ────────── */
.acciones-cell { text-align: right; white-space: nowrap; }
.accion-btn {
  background: transparent; border: 1px solid var(--gris2); border-radius: 6px;
  padding: 5px 10px; font-size: 11px; color: var(--gris5);
  font-family: 'Roboto', sans-serif; cursor: pointer;
  display: inline-flex; align-items: center; gap: 4px; text-decoration: none;
  transition: all .15s;
}
.accion-btn:hover { border-color: var(--dorado); color: var(--dorado); }
.accion-btn.primary {
  background: var(--dorado); border-color: var(--dorado); color: var(--negro); font-weight: 500;
}
.accion-btn.primary:hover { opacity: .85; }

/* ── Modal (mismo patrón que documentos.php) ──────────────── */
.modal-overlay { display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:500;align-items:center;justify-content:center;padding:16px; }
.modal-overlay.open { display:flex; }
.modal-box { background:var(--gris1);border:1px solid var(--gris2);border-radius:14px;width:100%;max-width:540px;max-height:90vh;overflow-y:auto; }
.modal-header { padding:18px 20px;border-bottom:1px solid var(--gris2);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:var(--gris1);z-index:1; }
.modal-header h3 { font-size:15px;font-weight:600;color:var(--blanco); }
.modal-close { background:transparent;border:none;cursor:pointer;color:var(--gris4);padding:4px;border-radius:5px;transition:color .15s,background .15s;display:flex; }
.modal-close:hover { color:var(--blanco);background:var(--gris2); }
.modal-body { padding:20px; }
.modal-footer { padding:14px 20px;border-top:1px solid var(--gris2);display:flex;justify-content:flex-end;gap:8px;position:sticky;bottom:0;background:var(--gris1); }
.form-group { margin-bottom:14px; }
.form-group label { display:block;font-size:11px;font-weight:500;letter-spacing:.06em;text-transform:uppercase;color:var(--gris5);margin-bottom:6px; }
.form-group input[type=text], .form-group .form-select, .form-group textarea.form-input {
  width:100%;background:var(--negro);border:1px solid var(--gris2);border-radius:7px;
  padding:10px 12px;font-size:13px;font-family:'Archivo',sans-serif;color:var(--blanco);
  outline:none;transition:border-color .2s;box-sizing:border-box;
}
.form-group input[type=text]:focus, .form-group .form-select:focus, .form-group textarea.form-input:focus { border-color:var(--dorado); }
.form-group input::placeholder, .form-group textarea::placeholder { color:var(--gris3); }
.form-select { background:var(--negro)!important;color:var(--blanco)!important;cursor:pointer; }
.form-select:disabled { opacity:.5;cursor:not-allowed; }
.form-select option { background:var(--gris1); }
.form-error { background:rgba(226,92,92,.1);border:1px solid rgba(226,92,92,.3);border-radius:7px;padding:9px 12px;font-size:12px;color:var(--error);margin-top:8px;line-height:1.5; }

/* ── Info box ──────────────────────────────────────────────── */
.info-box {
  background: var(--negro); border: 1px solid var(--gris2); border-radius: 7px;
  padding: 9px 12px; font-size: 12px; color: var(--gris5);
  font-family: 'Roboto', sans-serif; line-height: 1.5;
}
.info-box.warning {
  background: rgba(201,168,76,.08); border-color: rgba(201,168,76,.3); color: var(--dorado);
  display: flex; align-items: flex-start; gap: 8px;
}

/* ── Drop zone (mismo patrón que documentos.php) ──────────── */
.drop-zone {
  border: 1.5px dashed var(--gris3); border-radius: 9px;
  padding: 24px 16px; text-align: center; cursor: pointer;
  transition: border-color .2s, background .2s;
}
.drop-zone:hover, .drop-zone.dragover {
  border-color: var(--dorado); background: rgba(201,168,76,.04);
}
.drop-zone.has-file {
  border-color: var(--exito); background: rgba(76,175,80,.04);
}

/* ── Toast ────────────────────────────────────────────────── */
.toast { position:fixed;bottom:24px;right:24px;background:var(--gris1);border:1px solid var(--gris2);border-radius:10px;padding:12px 16px;font-size:13px;color:var(--blanco);display:flex;align-items:center;gap:10px;transform:translateY(80px);opacity:0;transition:transform .3s,opacity .3s;z-index:600;font-family:'Roboto',sans-serif;max-width:320px; }
.toast.show { transform:translateY(0);opacity:1; }
</style>

<script>
// ═══════════════════════════════════════════════════════════
// State
// ═══════════════════════════════════════════════════════════
let miRol         = '';
let miEmpresaId   = null;
let todasLasEmpresas = [];
let empresaFiltroId  = null;
let todasLasFilas    = [];
let todasLasFranquicias = [];
let todosLosManuales = [];

// Modal state
let modalManuales   = [];
let modalVersiones  = [];
let modalSocios     = [];
let modalEmpresaId  = null;
let modalManualId   = null;
let modalVersionId  = null;
let modalSocioId    = null;
let archivoSeleccionado = null;

// ═══════════════════════════════════════════════════════════
// Helpers
// ═══════════════════════════════════════════════════════════
function esc(str) {
  if (!str) return '';
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function formatFecha(iso) {
  if (!iso) return '—';
  const d = new Date(iso);
  return d.toLocaleDateString('es-AR', { day: '2-digit', month: '2-digit', year: '2-digit' });
}

let toastTimer;
function mostrarToast(msg, tipo = 'exito') {
  const el = document.getElementById('toast');
  const icon = tipo === 'exito'
    ? '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--exito)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>'
    : '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--error)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';
  document.getElementById('toast-icon').innerHTML = icon;
  document.getElementById('toast-msg').textContent = msg;
  el.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => el.classList.remove('show'), 3500);
}

// fetch multipart (no usa apiFetch porque necesita FormData sin Content-Type).
// Mismo patrón que documentos.php.
async function fetchMultipart(endpoint, formData) {
  const res = await fetch(API + endpoint, {
    method: 'POST',
    credentials: 'include',
    body: formData,
  });
  const data = await res.json();
  if (!res.ok) { const err = new Error(); err.data = data; throw err; }
  return data;
}

// ═══════════════════════════════════════════════════════════
// Init
// ═══════════════════════════════════════════════════════════
async function init() {
  try {
    const me = await apiFetch('GET', '/me');
    miRol       = me.rol || '';
    miEmpresaId = me.empresa_id;

    // Gate de rol: solo super_admin y franquiciante entran a esta pantalla.
    // El menú del layout tampoco muestra la entrada para otros roles, pero
    // alguien puede intentar acceder por URL directa.
    if (miRol !== 'super_admin' && miRol !== 'franquiciante') {
      window.location.href = 'dashboard.php';
      return;
    }

    document.getElementById('filtros-wrap').style.display = 'flex';
    document.getElementById('btn-subir').style.display = 'inline-flex';

    if (miRol === 'super_admin') {
      document.getElementById('grupo-sel-empresa').style.display = 'flex';
      const empresas = await apiFetch('GET', '/empresas');
      todasLasEmpresas = (empresas || []).filter(e => e.activa);
      document.getElementById('tabla-titulo').textContent = 'Seleccioná una empresa para ver aceptaciones.';
      document.getElementById('tabla-body').innerHTML =
        '<tr><td colspan="8"><div class="empty-state">Elegí una empresa para empezar.</div></td></tr>';
    } else {
      // franquiciante
      empresaFiltroId = miEmpresaId;
      document.getElementById('btn-subir').disabled = false;
      await cargarContextoEmpresa();
      await cargarFilas();
    }
  } catch (e) {
    console.error('init', e);
    document.getElementById('tabla-body').innerHTML =
      '<tr><td colspan="8"><div class="empty-state" style="color:var(--error)">Error al cargar la página.</div></td></tr>';
  }
}

// ═══════════════════════════════════════════════════════════
// Autocomplete empresa (mismo patrón que documentos.php)
// ═══════════════════════════════════════════════════════════
function filtrarOpcionesEmpresa() {
  const input = document.getElementById('inp-empresa');
  const opts  = document.getElementById('empresa-opciones');
  const f = input.value.toLowerCase().trim();
  const lista = f
    ? todasLasEmpresas.filter(e => e.nombre.toLowerCase().includes(f))
    : todasLasEmpresas;

  if (!lista.length) {
    opts.innerHTML = '<div class="combo-vacio">Sin resultados.</div>';
  } else {
    opts.innerHTML = lista.map(e =>
      `<div class="combo-opcion" onmousedown="seleccionarEmpresa(${e.id}, '${esc(e.nombre).replace(/'/g,"\\'")}')">${esc(e.nombre)}</div>`
    ).join('');
  }
  opts.style.display = 'block';
}

document.addEventListener('click', (ev) => {
  const combo = document.getElementById('empresa-combo');
  if (combo && !combo.contains(ev.target)) {
    document.getElementById('empresa-opciones').style.display = 'none';
  }
});

async function seleccionarEmpresa(id, nombre) {
  empresaFiltroId = id;
  document.getElementById('inp-empresa').value = nombre;
  document.getElementById('empresa-opciones').style.display = 'none';
  document.getElementById('empresa-clear').style.display = 'block';
  document.getElementById('btn-subir').disabled = false;

  // Reset filtros dependientes
  document.getElementById('sel-franquicia').value = '';
  document.getElementById('sel-manual').value = '';
  document.getElementById('sel-version').innerHTML = '<option value="">Solo versiones activas</option>';
  document.getElementById('sel-version').disabled = true;

  await cargarContextoEmpresa();
  await cargarFilas();
}

async function limpiarEmpresa() {
  empresaFiltroId = null;
  document.getElementById('inp-empresa').value = '';
  document.getElementById('empresa-clear').style.display = 'none';
  document.getElementById('btn-subir').disabled = true;

  document.getElementById('sel-franquicia').innerHTML = '<option value="">Todas las sucursales</option>';
  document.getElementById('sel-manual').innerHTML = '<option value="">Todos los manuales</option>';
  document.getElementById('sel-version').innerHTML = '<option value="">Solo versiones activas</option>';
  document.getElementById('sel-franquicia').disabled = true;
  document.getElementById('sel-manual').disabled = true;
  document.getElementById('sel-version').disabled = true;

  todasLasFilas = [];
  document.getElementById('tabla-titulo').textContent = 'Seleccioná una empresa para ver aceptaciones.';
  document.getElementById('tabla-body').innerHTML =
    '<tr><td colspan="8"><div class="empty-state">Elegí una empresa para empezar.</div></td></tr>';
}

// ═══════════════════════════════════════════════════════════
// Cargar sucursales + manuales de la empresa
// ═══════════════════════════════════════════════════════════
async function cargarContextoEmpresa() {
  if (!empresaFiltroId) return;

  try {
    const franqs = await apiFetch('GET', `/franquicias?empresa_id=${empresaFiltroId}`);
    todasLasFranquicias = franqs || [];
    const selFr = document.getElementById('sel-franquicia');
    selFr.innerHTML = '<option value="">Todas las sucursales</option>' +
      todasLasFranquicias.map(f => `<option value="${f.id}">${esc(f.nombre)}</option>`).join('');
    selFr.disabled = false;
  } catch (e) { console.error('sucursales', e); }

  try {
    const manuales = await apiFetch('GET', `/manuales?empresa_id=${empresaFiltroId}`);
    todosLosManuales = (manuales || []).filter(m => m.estado === 'publicado');
    const selMa = document.getElementById('sel-manual');
    selMa.innerHTML = '<option value="">Todos los manuales</option>' +
      todosLosManuales.map(m => `<option value="${m.id}">${esc(m.titulo)}</option>`).join('');
    selMa.disabled = false;
  } catch (e) { console.error('manuales', e); }
}

async function onManualFiltroChange() {
  const manualId = document.getElementById('sel-manual').value;
  const selVe = document.getElementById('sel-version');
  selVe.innerHTML = '<option value="">Solo versiones activas</option>';

  if (!manualId) {
    selVe.disabled = true;
    await cargarFilas();
    return;
  }

  try {
    const versiones = await apiFetch('GET', `/manuales/${manualId}/versiones`);
    const publicadas = (versiones || []).filter(v => v.publicado_at);
    selVe.innerHTML = '<option value="">Todas las versiones publicadas</option>' +
      publicadas.map(v =>
        `<option value="${v.id}">v${v.version_number}${v.es_activa ? ' · activa' : ''}</option>`
      ).join('');
    selVe.disabled = false;
  } catch (e) {
    console.error('versiones', e);
    selVe.disabled = true;
  }

  await cargarFilas();
}

function onFiltroChange() {
  cargarFilas();
}

// ═══════════════════════════════════════════════════════════
// Cargar filas
// ═══════════════════════════════════════════════════════════
async function cargarFilas() {
  if (!empresaFiltroId) return;

  const franquiciaId = document.getElementById('sel-franquicia').value;
  const manualId     = document.getElementById('sel-manual').value;
  const versionId    = document.getElementById('sel-version').value;

  const params = new URLSearchParams();
  if (miRol === 'super_admin') params.append('empresa_id', empresaFiltroId);
  if (franquiciaId) params.append('franquicia_id', franquiciaId);
  if (manualId)     params.append('manual_id', manualId);
  if (versionId)    params.append('version_id', versionId);

  document.getElementById('tabla-titulo').textContent = 'Cargando...';
  document.getElementById('tabla-body').innerHTML =
    '<tr><td colspan="8"><div class="loading-msg"><div class="spinner" style="display:block"></div>Cargando aceptaciones...</div></td></tr>';

  try {
    const res = await apiFetch('GET', `/firmas-fisicas?${params.toString()}`);
    todasLasFilas = res.filas || [];
    aplicarFiltroTexto();
  } catch (e) {
    console.error('cargar filas', e);
    document.getElementById('tabla-titulo').textContent = 'Error';
    document.getElementById('tabla-body').innerHTML =
      `<tr><td colspan="8"><div class="empty-state" style="color:var(--error)">Error al cargar: ${esc(e.data?.error || 'desconocido')}</div></td></tr>`;
  }
}

function aplicarFiltroTexto() {
  const texto = document.getElementById('inp-buscar').value.toLowerCase().trim();

  let lista = todasLasFilas;
  if (texto) {
    lista = lista.filter(f => {
      const socio = `${f.socio.nombre} ${f.socio.apellido} ${f.socio.email}`.toLowerCase();
      const sucursal = (f.franquicia?.nombre || '').toLowerCase();
      const manual = (f.manual.titulo || '').toLowerCase();
      return socio.includes(texto) || sucursal.includes(texto) || manual.includes(texto);
    });
  }

  renderTabla(lista);
}

function renderTabla(filas) {
  const body = document.getElementById('tabla-body');
  const titulo = document.getElementById('tabla-titulo');

  if (!filas.length) {
    body.innerHTML = '<tr><td colspan="8"><div class="empty-state">No hay aceptaciones para mostrar con estos filtros.</div></td></tr>';
    titulo.textContent = '0 registros';
    return;
  }

  titulo.textContent = `${filas.length} registro(s)`;

  body.innerHTML = filas.map(f => {
    const nombreCompleto = `${f.socio.nombre || ''} ${f.socio.apellido || ''}`.trim() || '—';
    const sucursal = f.franquicia
      ? esc(f.franquicia.nombre)
      : '<span class="sin-dato">Sin sucursal</span>';

    // Estado combinado
    const hasDigital = !!f.aceptacion_digital;
    const hasFisica  = !!f.firma_fisica;
    let estadoPill;
    if (hasDigital && hasFisica) {
      estadoPill = '<span class="estado-pill estado-completo">Completo</span>';
    } else if (hasDigital) {
      estadoPill = '<span class="estado-pill estado-solo-digital">Solo digital</span>';
    } else if (hasFisica) {
      estadoPill = '<span class="estado-pill estado-solo-fisico">Solo físico</span>';
    } else {
      estadoPill = '<span class="estado-pill estado-pendiente">Pendiente</span>';
    }

    const aceptado = hasDigital
      ? `<span class="estado-cell ok"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>${formatFecha(f.aceptacion_digital.aceptado_at)}</span>`
      : '<span class="estado-cell miss">—</span>';

    let firmaCell;
    if (hasFisica) {
      const url = `${API}/firmas-fisicas/${f.firma_fisica.id}/descargar`;
      firmaCell = `<a class="accion-btn" href="${url}" target="_blank" rel="noopener" title="Ver PDF firmado">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Descargar
      </a>`;
    } else {
      firmaCell = '<span class="estado-cell miss">—</span>';
    }

    const btnAccion = `<button class="accion-btn primary" onclick="abrirModalConContexto(${f.manual.id}, ${f.version.id}, ${f.socio.id})">
      ${hasFisica ? 'Reemplazar' : 'Subir'}
    </button>`;

    return `<tr>
      <td>
        <div class="socio-cell">
          <span class="socio-nombre">${esc(nombreCompleto)}</span>
          <span class="socio-email">${esc(f.socio.email)}</span>
        </div>
      </td>
      <td>${sucursal}</td>
      <td>${esc(f.manual.titulo)}${f.manual.tipo === 'pdf' ? `<span style="margin-left:8px;font-size:9px;font-weight:700;letter-spacing:.06em;padding:2px 6px;border-radius:4px;background:rgba(201,168,76,.14);color:var(--dorado);vertical-align:middle;font-family:'Roboto',sans-serif">PDF</span>` : ''}</td>
      <td>
        <span class="badge-version ${f.version.es_activa ? 'activa' : ''}">v${f.version.version_number}${f.version.es_activa ? ' · activa' : ''}</span>
      </td>
      <td>${estadoPill}</td>
      <td>${aceptado}</td>
      <td>${firmaCell}</td>
      <td class="acciones-cell">${btnAccion}</td>
    </tr>`;
  }).join('');
}

// ═══════════════════════════════════════════════════════════
// Modal
// ═══════════════════════════════════════════════════════════
async function abrirModalSubir() {
  resetearModal();

  if (miRol === 'super_admin') {
    document.getElementById('grupo-empresa-modal').style.display = 'block';
    const sel = document.getElementById('modal-empresa');
    sel.innerHTML = '<option value="">Seleccioná una empresa</option>' +
      todasLasEmpresas.map(e => `<option value="${e.id}">${esc(e.nombre)}</option>`).join('');
    if (empresaFiltroId) {
      sel.value = empresaFiltroId;
      modalEmpresaId = empresaFiltroId;
      await cargarManualesModal();
    }
  } else {
    modalEmpresaId = miEmpresaId;
    await cargarManualesModal();
  }

  document.getElementById('modal-subir').classList.add('open');
}

async function abrirModalConContexto(manualId, versionId, socioId) {
  await abrirModalSubir();
  document.getElementById('modal-manual').value = manualId;
  modalManualId = manualId;
  await cargarVersionesModal();

  document.getElementById('modal-version').value = versionId;
  modalVersionId = versionId;
  await cargarSociosModal();

  document.getElementById('modal-socio').value = socioId;
  modalSocioId = socioId;
  onSocioModalChange();
}

function cerrarModal() {
  document.getElementById('modal-subir').classList.remove('open');
  resetearModal();
}

function resetearModal() {
  document.getElementById('modal-empresa').value = '';
  document.getElementById('modal-manual').innerHTML = '<option value="">Elegí primero una empresa</option>';
  document.getElementById('modal-manual').disabled = true;
  document.getElementById('modal-version').innerHTML = '<option value="">Elegí primero un manual</option>';
  document.getElementById('modal-version').disabled = true;
  document.getElementById('modal-socio').innerHTML = '<option value="">Elegí primero una versión</option>';
  document.getElementById('modal-socio').disabled = true;
  document.getElementById('modal-notas').value = '';
  document.getElementById('modal-error').style.display = 'none';
  document.getElementById('modal-error').textContent = '';
  document.getElementById('socio-info').style.display = 'none';
  document.getElementById('socio-warning').style.display = 'none';
  document.getElementById('btn-guardar').disabled = false;
  document.getElementById('btn-guardar').textContent = 'Subir firma';
  document.getElementById('modal-archivo').value = '';
  document.getElementById('drop-zone').classList.remove('has-file');
  document.getElementById('drop-zone-content').innerHTML = `
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color:var(--gris4);margin-bottom:6px"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
    <div style="font-size:13px;color:var(--gris5);font-family:'Roboto',sans-serif">Hacé click o arrastrá un PDF acá</div>
    <div style="font-size:11px;color:var(--gris4);margin-top:4px;font-family:'Roboto',sans-serif">Máximo 10 MB</div>`;

  archivoSeleccionado = null;
  modalEmpresaId = modalManualId = modalVersionId = modalSocioId = null;
  modalManuales = modalVersiones = modalSocios = [];
}

async function onEmpresaModalChange() {
  modalEmpresaId = parseInt(document.getElementById('modal-empresa').value, 10) || null;
  await cargarManualesModal();
}

async function cargarManualesModal() {
  const sel = document.getElementById('modal-manual');
  sel.innerHTML = '<option value="">Cargando...</option>';
  sel.disabled = true;

  if (!modalEmpresaId) {
    sel.innerHTML = '<option value="">Elegí primero una empresa</option>';
    return;
  }

  try {
    const manuales = await apiFetch('GET', `/manuales?empresa_id=${modalEmpresaId}`);
    modalManuales = (manuales || []).filter(m => m.estado === 'publicado');

    if (!modalManuales.length) {
      sel.innerHTML = '<option value="">Esta empresa no tiene manuales publicados</option>';
      return;
    }

    sel.innerHTML = '<option value="">Seleccioná un manual</option>' +
      modalManuales.map(m => `<option value="${m.id}">${esc(m.titulo)}</option>`).join('');
    sel.disabled = false;

    // Reset dependientes
    document.getElementById('modal-version').innerHTML = '<option value="">Elegí primero un manual</option>';
    document.getElementById('modal-version').disabled = true;
    document.getElementById('modal-socio').innerHTML = '<option value="">Elegí primero una versión</option>';
    document.getElementById('modal-socio').disabled = true;
    modalManualId = modalVersionId = modalSocioId = null;
  } catch (e) {
    sel.innerHTML = '<option value="">Error al cargar</option>';
  }
}

async function onManualModalChange() {
  modalManualId = parseInt(document.getElementById('modal-manual').value, 10) || null;
  await cargarVersionesModal();
}

async function cargarVersionesModal() {
  const sel = document.getElementById('modal-version');
  sel.innerHTML = '<option value="">Cargando...</option>';
  sel.disabled = true;

  if (!modalManualId) {
    sel.innerHTML = '<option value="">Elegí primero un manual</option>';
    return;
  }

  try {
    const versiones = await apiFetch('GET', `/manuales/${modalManualId}/versiones`);
    modalVersiones = (versiones || []).filter(v => v.publicado_at);

    if (!modalVersiones.length) {
      sel.innerHTML = '<option value="">Este manual no tiene versiones publicadas</option>';
      return;
    }

    modalVersiones.sort((a, b) => {
      if (a.es_activa && !b.es_activa) return -1;
      if (!a.es_activa && b.es_activa) return 1;
      return b.version_number - a.version_number;
    });

    sel.innerHTML = modalVersiones.map(v =>
      `<option value="${v.id}">v${v.version_number}${v.es_activa ? ' · activa' : ''}</option>`
    ).join('');
    sel.disabled = false;

    const activa = modalVersiones.find(v => v.es_activa);
    if (activa) {
      sel.value = activa.id;
      modalVersionId = activa.id;
      await cargarSociosModal();
    }
  } catch (e) {
    sel.innerHTML = '<option value="">Error al cargar</option>';
  }
}

async function onVersionModalChange() {
  modalVersionId = parseInt(document.getElementById('modal-version').value, 10) || null;
  await cargarSociosModal();
}

async function cargarSociosModal() {
  const sel = document.getElementById('modal-socio');
  sel.innerHTML = '<option value="">Cargando...</option>';
  sel.disabled = true;

  if (!modalManualId || !modalEmpresaId) {
    sel.innerHTML = '<option value="">Elegí primero un manual</option>';
    return;
  }

  try {
    const params = new URLSearchParams();
    params.append('manual_id', modalManualId);
    if (miRol === 'super_admin') params.append('empresa_id', modalEmpresaId);

    const res = await apiFetch('GET', `/firmas-fisicas/socios-para-manual?${params.toString()}`);
    modalSocios = res.socios || [];

    if (!modalSocios.length) {
      sel.innerHTML = '<option value="">Ningún socio tiene acceso a este manual</option>';
      return;
    }

    sel.innerHTML = '<option value="">Seleccioná un socio</option>' +
      modalSocios.map(s => {
        const nombre = `${s.nombre} ${s.apellido}`.trim();
        const sucursal = s.franquicia?.nombre ? ` — ${s.franquicia.nombre}` : ' — Sin sucursal';
        return `<option value="${s.id}">${esc(nombre)}${esc(sucursal)}</option>`;
      }).join('');
    sel.disabled = false;
  } catch (e) {
    sel.innerHTML = '<option value="">Error al cargar</option>';
  }
}

function onSocioModalChange() {
  modalSocioId = parseInt(document.getElementById('modal-socio').value, 10) || null;
  const socio = modalSocios.find(s => s.id === modalSocioId);
  const infoBox = document.getElementById('socio-info');
  const warningBox = document.getElementById('socio-warning');

  if (!socio) {
    infoBox.style.display = 'none';
    warningBox.style.display = 'none';
    return;
  }

  const sucursal = socio.franquicia?.nombre || 'Sin sucursal';
  infoBox.innerHTML = `<strong>${esc(socio.nombre)} ${esc(socio.apellido)}</strong><br>${esc(socio.email)} · ${esc(sucursal)}`;
  infoBox.style.display = 'block';

  const yaExiste = todasLasFilas.some(f =>
    f.socio.id === socio.id && f.version.id === modalVersionId && f.firma_fisica
  );
  warningBox.style.display = yaExiste ? 'flex' : 'none';
}

// ═══════════════════════════════════════════════════════════
// Drop zone
// ═══════════════════════════════════════════════════════════
function onArchivoSelected() {
  const file = document.getElementById('modal-archivo').files[0];
  if (!file) return;

  if (file.type !== 'application/pdf') {
    mostrarError('El archivo debe ser un PDF.');
    document.getElementById('modal-archivo').value = '';
    return;
  }
  if (file.size > 10 * 1024 * 1024) {
    mostrarError('El archivo excede el máximo de 10 MB.');
    document.getElementById('modal-archivo').value = '';
    return;
  }

  archivoSeleccionado = file;
  const kb = Math.round(file.size / 1024);
  const size = kb > 1024 ? `${(kb/1024).toFixed(1)} MB` : `${kb} KB`;

  document.getElementById('drop-zone').classList.add('has-file');
  document.getElementById('drop-zone-content').innerHTML = `
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--exito)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom:6px"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
    <div style="font-size:13px;color:var(--blanco);font-family:'Roboto',sans-serif">${esc(file.name)}</div>
    <div style="font-size:11px;color:var(--gris4);margin-top:4px;font-family:'Roboto',sans-serif">${size} · click para cambiar</div>`;
}

// Drag & drop
document.addEventListener('DOMContentLoaded', () => {
  const dz = document.getElementById('drop-zone');
  if (!dz) return;
  ['dragenter','dragover'].forEach(ev => dz.addEventListener(ev, e => {
    e.preventDefault(); e.stopPropagation(); dz.classList.add('dragover');
  }));
  ['dragleave','drop'].forEach(ev => dz.addEventListener(ev, e => {
    e.preventDefault(); e.stopPropagation(); dz.classList.remove('dragover');
  }));
  dz.addEventListener('drop', e => {
    const files = e.dataTransfer.files;
    if (files && files[0]) {
      document.getElementById('modal-archivo').files = files;
      onArchivoSelected();
    }
  });
});

function mostrarError(msg) {
  const el = document.getElementById('modal-error');
  el.textContent = msg;
  el.style.display = 'block';
}

// ═══════════════════════════════════════════════════════════
// Guardar
// ═══════════════════════════════════════════════════════════
async function guardar() {
  document.getElementById('modal-error').style.display = 'none';

  if (!modalVersionId || !modalSocioId) {
    return mostrarError('Completá todos los campos obligatorios.');
  }
  if (!archivoSeleccionado) {
    return mostrarError('Seleccioná un archivo PDF.');
  }

  const btn = document.getElementById('btn-guardar');
  btn.disabled = true;
  btn.textContent = 'Subiendo...';

  try {
    const fd = new FormData();
    fd.append('archivo', archivoSeleccionado);
    fd.append('user_id', modalSocioId);
    const notas = document.getElementById('modal-notas').value.trim();
    if (notas) fd.append('notas', notas);

    await fetchMultipart(`/versiones/${modalVersionId}/firma-fisica`, fd);

    mostrarToast('Firma cargada correctamente.', 'exito');
    cerrarModal();
    await cargarFilas();
  } catch (e) {
    const msg = e.data?.error
      || (e.data?.errors ? Object.values(e.data.errors).flat().join(' ') : null)
      || e.data?.message
      || 'Error al subir la firma.';
    mostrarError(msg);
    btn.disabled = false;
    btn.textContent = 'Subir firma';
  }
}

// ═══════════════════════════════════════════════════════════
// Boot
// ═══════════════════════════════════════════════════════════
document.addEventListener('DOMContentLoaded', () => init());
</script>

<?php include 'layout/footer.php'; ?>