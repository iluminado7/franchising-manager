<?php
require_once __DIR__ . '/layout/config.php';
require_once __DIR__ . '/layout/auth.php';
verificarSesion();
$titulo        = 'Documentos';
$pagina_actual = 'documentos';
include 'layout/head.php';
?>

<div class="app-layout">
  <?php include 'layout/topbar.php'; ?>
  <div class="app-body">
    <?php include 'layout/sidebar.php'; ?>
    <main class="main-content">

      <div class="page-header">
        <div>
          <div class="page-title" id="page-title">Documentos</div>
          <div class="page-sub" id="page-sub">Repositorio de documentos operativos</div>
        </div>
        <button class="btn btn-primary" id="btn-subir" style="display:none" onclick="abrirModalSubir()">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Subir documento
        </button>
      </div>

      <!-- Filtros -->
      <div id="filtros-wrap" style="display:none;gap:8px;margin-bottom:20px;flex-wrap:wrap;align-items:center">

        <!-- Select empresa — solo super_admin -->
        <div id="grupo-sel-empresa" style="display:none;align-items:center;gap:8px">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--gris4)"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
          <select id="sel-empresa" class="filtro-select-lg" onchange="onEmpresaChange()">
            <option value="">— Todas las empresas —</option>
          </select>
        </div>

        <!-- Filtros de tipo y franquicia -->
        <select id="sel-tipo" class="filtro-select" onchange="aplicarFiltros()">
          <option value="">Todos los tipos</option>
          <option value="contrato">Contrato</option>
          <option value="anexo">Anexo</option>
          <option value="acta">Acta</option>
          <option value="otro">Otro</option>
        </select>

        <select id="sel-franquicia" class="filtro-select" onchange="aplicarFiltros()">
          <option value="">Todas las franquicias</option>
          <option value="global">Solo globales</option>
        </select>

        <div style="margin-left:auto;position:relative">
          <input type="text" id="inp-buscar" placeholder="Buscar documento..." oninput="aplicarFiltros()" class="buscar-input">
          <svg style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--gris4)" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        </div>
      </div>

      <!-- Tabla -->
      <div class="tabla-wrap">
        <div class="tabla-header">
          <span id="tabla-titulo">Cargando...</span>
        </div>
        <div style="overflow-x:auto">
          <table class="tabla" id="tabla-docs">
            <thead id="tabla-head"></thead>
            <tbody id="tabla-body">
              <tr><td colspan="6"><div class="empty-state">Cargando documentos...</div></td></tr>
            </tbody>
          </table>
        </div>
      </div>

    </main>
  </div>
</div>

<!-- ══════════════════════════════════════════════════
     MODAL SUBIR DOCUMENTO
══════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modal-subir">
  <div class="modal-box">
    <div class="modal-header">
      <h3>Subir documento</h3>
      <button class="modal-close" onclick="cerrarModalSubir()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-body">

      <div class="form-group">
        <label>Título del documento *</label>
        <input type="text" id="doc-titulo" placeholder="Ej: Contrato de franquicia 2026" maxlength="200">
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Tipo *</label>
          <select id="doc-tipo" class="form-select">
            <option value="">Seleccioná un tipo</option>
            <option value="contrato">Contrato</option>
            <option value="anexo">Anexo</option>
            <option value="acta">Acta</option>
            <option value="otro">Otro</option>
          </select>
        </div>
        <div class="form-group" id="grupo-empresa-doc" style="display:none">
        <label>Empresa *</label>
        <select id="doc-empresa" class="form-select" onchange="onEmpresaDocChange()">
          <option value="">Seleccioná una empresa</option>
        </select>
        </div>
      </div>

      <div class="form-group">
        <label>
        <input type="checkbox" id="doc-visible" style="margin-right:6px;accent-color:var(--dorado)">
         Marque si quiere permitir que los franquiciados vean este documento
        </label>
        <div style="font-size:13px;color:var(--gris4);margin-top:4px;font-family:'Archivo Narrow',sans-serif">Si está activado, los franquiciados podrán ver y descargar este documento. Si no quiere que ocurra, dejar desactivado</div>
      </div>      

      <!-- Empresa — solo super_admin -->
      <div class="form-group">
          <label>Franquicia destino</label>
          <select id="doc-franquicia" class="form-select">
            <option value="">Toda la empresa (global)</option>
          </select>
          <div style="font-size:13px;color:var(--gris4);margin-top:4px;font-family:'Archivo Narrow',sans-serif">Dejá vacío para que aplique a todas las franquicias.</div>
      </div>

      <!-- Zona de archivo -->
      <div class="form-group">
        <label>Archivo *</label>
        <div class="drop-zone" id="drop-zone" onclick="document.getElementById('doc-archivo').click()">
          <input type="file" id="doc-archivo" accept=".pdf,.doc,.docx" style="display:none" onchange="onArchivoSeleccionado(this)">
          <div id="drop-zone-content">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color:var(--gris4);margin-bottom:8px"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            <div style="font-size:13px;color:var(--gris5)">Hacé clic o arrastrá un archivo</div>
            <div style="font-size:11px;color:var(--gris4);margin-top:4px">PDF, DOC o DOCX — máximo 20 MB</div>
          </div>
        </div>
      </div>

      <div class="form-error" id="doc-error" style="display:none"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="cerrarModalSubir()">Cancelar</button>
      <button class="btn btn-primary" id="btn-confirmar-subir" onclick="subirDocumento()">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
        Subir documento
      </button>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════
     MODAL ELIMINAR DOCUMENTO
     ══════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modal-eliminar">
  <div class="modal-box" style="max-width:380px">
    <div class="modal-header">
      <h3>Eliminar documento</h3>
      <button class="modal-close" onclick="cerrarModalEliminar()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-body">
      <p id="eliminar-msg" style="font-size:14px;color:var(--gris5);line-height:1.6;font-family:'Archivo Narrow',sans-serif"></p>
      <div class="form-error" id="eliminar-error"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="cerrarModalEliminar()">Cancelar</button>
      <button class="btn btn-danger" id="btn-eliminar-confirmar" onclick="ejecutarEliminar()">Eliminar</button>
    </div>
  </div>
</div>

<!-- ── TOAST ──────────────────────────────────────────────────── -->
<div class="toast" id="toast"><span id="toast-icon"></span><span id="toast-msg"></span></div>

<style>
/* ── Filtros ──────────────────────────────────────────────── */
.filtro-select {
  background: var(--gris2); border: 1px solid var(--gris2);
  border-radius: 7px; color: var(--gris5); font-size: 12px;
  font-family: 'Roboto', sans-serif; padding: 7px 10px;
  cursor: pointer; outline: none; transition: border-color .2s;
}
.filtro-select:focus { border-color: var(--dorado); }
.filtro-select option { background: var(--gris1); }
.filtro-select-lg {
  background: var(--gris1); border: 1px solid var(--gris2);
  border-radius: 8px; color: var(--blanco); font-size: 13px;
  font-family: 'Roboto', sans-serif; padding: 9px 14px;
  outline: none; cursor: pointer; transition: border-color .2s; min-width: 220px;
}
.filtro-select-lg:focus { border-color: var(--dorado); }
.filtro-select-lg option { background: var(--gris1); }
.buscar-input {
  background: var(--gris1); border: 1px solid var(--gris2);
  border-radius: 8px; padding: 8px 12px 8px 32px;
  font-size: 13px; font-family: 'Roboto', sans-serif;
  color: var(--blanco); outline: none; transition: border-color .2s; width: 220px;
}
.buscar-input:focus { border-color: var(--dorado); }
.buscar-input::placeholder { color: var(--gris3); }

/* ── Tabla ────────────────────────────────────────────────── */
.tabla-wrap { background: var(--gris1); border: 1px solid var(--gris2); border-radius: 12px; overflow: hidden; }
.tabla-header { padding: 14px 18px; border-bottom: 1px solid var(--gris2); font-size: 13px; font-weight: 500; color: var(--gris5); }
.tabla { width: 100%; border-collapse: collapse; }
.tabla th { font-size: 10px; font-weight: 600; letter-spacing: .07em; text-transform: uppercase; color: var(--gris4); padding: 10px 16px; text-align: left; border-bottom: 1px solid var(--gris2); white-space: nowrap; }
.tabla td { padding: 13px 16px; font-size: 13px; color: var(--gris5); border-bottom: 1px solid var(--gris2); font-family: 'Archivo Narrow', sans-serif; vertical-align: middle; }
.tabla tr:last-child td { border-bottom: none; }
.tabla tr:hover td { background: rgba(255,255,255,.02); }

/* ── Tipo badge ───────────────────────────────────────────── */
.tipo-badge {
  display: inline-flex; align-items: center;
  padding: 3px 9px; border-radius: 20px;
  font-size: 10px; font-weight: 600; letter-spacing: .06em;
  text-transform: uppercase; white-space: nowrap;
}
.tipo-contrato { background: rgba(101,163,255,.12); color: #65a3ff; border: 1px solid rgba(101,163,255,.25); }
.tipo-anexo    { background: rgba(201,168,76,.12);  color: var(--dorado); border: 1px solid rgba(201,168,76,.25); }
.tipo-acta     { background: rgba(76,175,80,.12);   color: var(--exito);  border: 1px solid rgba(76,175,80,.25); }
.tipo-otro     { background: rgba(255,255,255,.07); color: var(--gris5);  border: 1px solid var(--gris2); }
.tipo-politica     { background: rgba(255,255,255,.07); color: var(--gris5);  border: 1px solid var(--gris2); }
.tipo-circulo     { background: rgba(255,255,255,.07); color: var(--gris5);  border: 1px solid var(--gris2); }
.tipo-protocolo     { background: rgba(255,255,255,.07); color: var(--gris5);  border: 1px solid var(--gris2); }

/* ── Drop zone ────────────────────────────────────────────── */
.drop-zone {
  border: 1.5px dashed var(--gris3); border-radius: 9px;
  padding: 28px 16px; text-align: center; cursor: pointer;
  transition: border-color .2s, background .2s;
}
.drop-zone:hover, .drop-zone.dragover {
  border-color: var(--dorado); background: rgba(201,168,76,.04);
}
.drop-zone.has-file {
  border-color: var(--exito); background: rgba(76,175,80,.04);
}

/* ── Modal ────────────────────────────────────────────────── */
.modal-overlay { display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:500;align-items:center;justify-content:center;padding:16px; }
.modal-overlay.open { display:flex; }
.modal-box { background:var(--gris1);border:1px solid var(--gris2);border-radius:14px;width:100%;max-width:540px;max-height:90vh;overflow-y:auto; }
.modal-header { padding:18px 20px;border-bottom:1px solid var(--gris2);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:var(--gris1);z-index:1; }
.modal-header h3 { font-size:15px;font-weight:600;color:var(--blanco); }
.modal-close { background:transparent;border:none;cursor:pointer;color:var(--gris4);padding:4px;border-radius:5px;transition:color .15s,background .15s;display:flex; }
.modal-close:hover { color:var(--blanco);background:var(--gris2); }
.modal-body { padding:20px; }
.modal-footer { padding:14px 20px;border-top:1px solid var(--gris2);display:flex;justify-content:flex-end;gap:8px;position:sticky;bottom:0;background:var(--gris1); }
.form-row { display:grid;grid-template-columns:1fr 1fr;gap:12px; }
@media(max-width:520px) { .form-row { grid-template-columns:1fr; } }
.form-group { margin-bottom:14px; }
.form-group label { display:block;font-size:11px;font-weight:500;letter-spacing:.06em;text-transform:uppercase;color:var(--gris5);margin-bottom:6px; }
.form-group input[type=text], .form-group .form-select {
  width:100%;background:var(--negro);border:1px solid var(--gris2);border-radius:7px;
  padding:10px 12px;font-size:13px;font-family:'Archivo',sans-serif;color:var(--blanco);
  outline:none;transition:border-color .2s;box-sizing:border-box;
}
.form-group input[type=text]:focus, .form-group .form-select:focus { border-color:var(--dorado); }
.form-group input::placeholder { color:var(--gris3); }
.form-select { background:var(--negro)!important;color:var(--blanco)!important;cursor:pointer; }
.form-select option { background:var(--gris1); }
.form-error { background:rgba(226,92,92,.1);border:1px solid rgba(226,92,92,.3);border-radius:7px;padding:9px 12px;font-size:12px;color:var(--error);margin-top:8px;line-height:1.5; }
.accion-btn { background:transparent;border:none;cursor:pointer;padding:5px 8px;border-radius:5px;font-size:12px;font-family:'Archivo',sans-serif;transition:background .15s;display:inline-flex;align-items:center;gap:4px;color:var(--gris5); }
.accion-btn:hover { background:var(--gris2);color:var(--blanco); }

/* ── Toast ────────────────────────────────────────────────── */
.toast { position:fixed;bottom:24px;right:24px;background:var(--gris1);border:1px solid var(--gris2);border-radius:10px;padding:12px 16px;font-size:13px;color:var(--blanco);display:flex;align-items:center;gap:10px;transform:translateY(80px);opacity:0;transition:transform .3s,opacity .3s;z-index:600;font-family:'Archivo Narrow',sans-serif;max-width:320px; }
.toast.show { transform:translateY(0);opacity:1; }
</style>

<script>
let todosLosDocumentos = [];
let todasLasEmpresas  = [];
let todasLasFranquicias = [];
let rolUsuario        = '';
let miEmpresaId       = null;
let pendingEliminar   = null;

// ── INIT ──────────────────────────────────────────────────────
async function init() {
  try {
    const me = await apiFetch('GET', '/me');
    rolUsuario  = me.rol;
    miEmpresaId = me.empresa_id;

    // Solo super_admin y franquiciante ven filtros y pueden subir
    if (rolUsuario === 'super_admin' || rolUsuario === 'franquiciante') {
      document.getElementById('btn-subir').style.display      = 'flex';
      document.getElementById('filtros-wrap').style.display   = 'flex';
    }

    // Franquiciado y empleado redirigen si no tienen acceso
    if (rolUsuario === 'empleado') {
      window.location.href = 'manuales.php'; return;
    }

    renderThead();

    if (rolUsuario === 'super_admin') {
      // Cargar empresas para el selector
      const empresas = await apiFetch('GET', '/empresas');
      todasLasEmpresas = empresas;
      const selEmp = document.getElementById('sel-empresa');
      empresas.forEach(e => {
        const opt = document.createElement('option');
        opt.value = e.id;
        opt.textContent = e.nombre + (e.activa ? '' : ' (suspendida)');
        selEmp.appendChild(opt);
      });
      document.getElementById('grupo-sel-empresa').style.display = 'flex';

      // Select empresa en modal
      const selDocEmp = document.getElementById('doc-empresa');
      empresas.filter(e => e.activa).forEach(e => {
        const opt = document.createElement('option');
        opt.value = e.id; opt.textContent = e.nombre;
        selDocEmp.appendChild(opt);
      });
      document.getElementById('grupo-empresa-doc').style.display = 'block';
    } else {
      // Franquiciante: cargar sus franquicias
      await cargarFranquicias(miEmpresaId);
    }

    await cargarDocumentos();

  } catch (e) {
    document.getElementById('tabla-body').innerHTML =
      `<tr><td colspan="6"><div class="empty-state">Error al cargar.</div></td></tr>`;
  }
}

async function cargarFranquicias(empresaId) {
  if (!empresaId) return;
  try {
    const franquicias = await apiFetch('GET', '/franquicias');
    todasLasFranquicias = franquicias;

    // Filtro
    const selFiltro = document.getElementById('sel-franquicia');
    franquicias.forEach(f => {
      const opt = document.createElement('option');
      opt.value = f.id; opt.textContent = f.nombre;
      selFiltro.appendChild(opt);
    });

    // Modal
    const selDoc = document.getElementById('doc-franquicia');
    franquicias.forEach(f => {
      const opt = document.createElement('option');
      opt.value = f.id; opt.textContent = f.nombre;
      selDoc.appendChild(opt);
    });
  } catch {}
}

async function onEmpresaChange() {
  const empresaId = document.getElementById('sel-empresa').value;

  // Actualizar franquicias del filtro
  const selFiltro = document.getElementById('sel-franquicia');
  selFiltro.innerHTML = '<option value="">Todas las franquicias</option><option value="global">Solo globales</option>';

  if (empresaId) {
    try {
      const franquicias = await apiFetch('GET', `/franquicias?empresa_id=${empresaId}`);
      todasLasFranquicias = franquicias;
      franquicias.forEach(f => {
        const opt = document.createElement('option');
        opt.value = f.id; opt.textContent = f.nombre;
        selFiltro.appendChild(opt);
      });
    } catch {}
  }

  await cargarDocumentos();
}

async function onEmpresaDocChange() {
  const empresaId = document.getElementById('doc-empresa').value;
  const selDoc    = document.getElementById('doc-franquicia');
  selDoc.innerHTML = '<option value="">Toda la empresa (global)</option>';
  if (!empresaId) return;
  try {
    const franquicias = await apiFetch('GET', `/franquicias?empresa_id=${empresaId}`);
    franquicias.forEach(f => {
      const opt = document.createElement('option');
      opt.value = f.id; opt.textContent = f.nombre;
      selDoc.appendChild(opt);
    });
  } catch {}
}

async function cargarDocumentos() {
  document.getElementById('tabla-body').innerHTML =
    `<tr><td colspan="6"><div class="empty-state"><div class="spinner" style="display:block;margin:0 auto 8px"></div>Cargando...</div></td></tr>`;

  try {
    let url = '/documentos';
    if (rolUsuario === 'super_admin') {
      const empresaId = document.getElementById('sel-empresa').value;
      if (empresaId) url += `?empresa_id=${empresaId}`;
    }
    const docs = await apiFetch('GET', url);
    todosLosDocumentos = docs;
    aplicarFiltros();
  } catch (e) {
    document.getElementById('tabla-body').innerHTML =
      `<tr><td colspan="6"><div class="empty-state">Error al cargar los documentos.</div></td></tr>`;
  }
}

// ── FILTROS ───────────────────────────────────────────────────
function aplicarFiltros() {
  let lista = [...todosLosDocumentos];
  const tipo       = document.getElementById('sel-tipo').value;
  const franquicia = document.getElementById('sel-franquicia').value;
  const texto      = document.getElementById('inp-buscar').value.toLowerCase().trim();

  if (tipo)      lista = lista.filter(d => d.tipo === tipo);
  if (franquicia === 'global') lista = lista.filter(d => !d.franquicia_id);
  else if (franquicia) lista = lista.filter(d => String(d.franquicia_id) === franquicia);
  if (texto)     lista = lista.filter(d => d.titulo.toLowerCase().includes(texto));

  renderTabla(lista);
  document.getElementById('tabla-titulo').textContent = `${lista.length} documento(s)`;
}

// ── RENDER ────────────────────────────────────────────────────
function renderThead() {
  const cols = rolUsuario === 'super_admin'
    ? ['Empresa', 'Título', 'Tipo', 'Franquicia', 'Visibilidad', 'Fecha', 'Acciones']
    : rolUsuario === 'franquiciante'
    ? ['Título', 'Tipo', 'Franquicia', 'Visibilidad', 'Fecha', 'Acciones']
    : ['Título', 'Tipo', 'Fecha', 'Acciones'];  // franquiciado: vista simplificada
  document.getElementById('tabla-head').innerHTML =
    `<tr>${cols.map(c => `<th>${c}</th>`).join('')}</tr>`;
}

function renderTabla(lista) {
  const tbody = document.getElementById('tabla-body');
  const cols = rolUsuario === 'super_admin' ? 7 : rolUsuario === 'franquiciante' ? 6 : 4;

  if (!lista.length) {
    tbody.innerHTML = `<tr><td colspan="${cols}"><div class="empty-state">No hay documentos disponibles.</div></td></tr>`;
    return;
  }

  const tipoBadge = (tipo) => {
    const map = { contrato: 'tipo-contrato', politica: 'tipo-politica', protocolo: 'tipo-protocolo', circulo: 'tipo-circulo', anexo: 'tipo-anexo', acta: 'tipo-acta', otro: 'tipo-otro' };
    return `<span class="tipo-badge ${map[tipo] || 'tipo-otro'}">${tipo}</span>`;
  };

  const tamano = (bytes) => {
    if (!bytes) return '—';
    if (bytes < 1024)    return `${bytes} B`;
    if (bytes < 1048576) return `${(bytes/1024).toFixed(0)} KB`;
    return `${(bytes/1048576).toFixed(1)} MB`;
  };

  tbody.innerHTML = lista.map(d => {
    const empresaCol = rolUsuario === 'super_admin'
      ? `<td style="font-size:12px;color:var(--gris4)">${esc(d.empresa?.nombre || '—')}</td>` : '';

    const franquiciaCol = (rolUsuario === 'super_admin' || rolUsuario === 'franquiciante')
      ? `<td>${d.franquicia?.nombre
          ? `<span style="font-size:11px;color:var(--gris5)">${esc(d.franquicia.nombre)}</span>`
          : `<span style="font-size:11px;color:var(--gris3);font-style:italic">Global</span>`
        }</td>` : '';

    const visibilidadCol = (rolUsuario === 'super_admin' || rolUsuario === 'franquiciante')
      ? `<td>${d.visible_franquiciado
          ? `<span style="color:var(--exito);font-size:11px">● Visible</span>`
          : `<span style="color:var(--gris3);font-size:11px">● Oculto</span>`
        }</td>` : '';

    const esPdf = (d.mime_type === 'application/pdf') || /\.pdf$/i.test(d.archivo_url || '');

    // Estado de eliminación (solo relevante para super_admin: a los demás no les llega un eliminado)
    const eliminado          = !!d.deleted_at;
    const eliminadoPorFranq  = eliminado && d.deleted_by?.rol === 'franquiciante';
    const badgeEliminado     = eliminado
      ? `<span style="display:inline-block;margin-left:8px;padding:2px 8px;border-radius:10px;font-size:10px;font-family:'Archivo',sans-serif;background:rgba(226,92,92,.12);color:var(--error);border:1px solid rgba(226,92,92,.3);vertical-align:middle">Eliminado${eliminadoPorFranq ? ' por franquiciante' : ''}</span>`
      : '';

    return `<tr>
      ${empresaCol}
      <td>
        <div style="color:var(--blanco);font-weight:500">${esc(d.titulo)}${badgeEliminado}</div>
        <div style="font-size:11px;color:var(--gris4);margin-top:2px">${esc(d.mime_type || '')} · ${tamano(d.tamano_bytes)}</div>
      </td>
      <td>${tipoBadge(d.tipo)}</td>
      ${franquiciaCol}
      ${visibilidadCol}
      <td style="font-size:12px;color:var(--gris4);white-space:nowrap">${formatFecha(d.created_at)}</td>
      <td>
        <div style="display:flex;gap:14px;align-items:center;flex-wrap:wrap">
          ${esPdf ? `
          <a href="#" onclick="event.preventDefault(); previsualizarDocumento(${d.id})" class="accion-btn" style="color:var(--gris5)">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            Vista previa
          </a>` : ''}
          <a href="#" onclick="event.preventDefault(); descargarDocumento(${d.id})" class="accion-btn" style="color:var(--dorado)">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Descargar
          </a>
          ${eliminado ? `
          <a href="#" onclick="event.preventDefault(); restaurarDocumento(${d.id}, '${esc(d.titulo)}')" class="accion-btn" style="color:var(--dorado)">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
            Restaurar
          </a>` : `
          <a href="#" onclick="event.preventDefault(); abrirModalEliminar(${d.id}, '${esc(d.titulo)}')" class="accion-btn" style="color:var(--gris5)">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
            Eliminar
          </a>`}
        </div>
      </td>
    </tr>`;
  }).join('');
}

// ── MODAL SUBIR ───────────────────────────────────────────────
function abrirModalSubir() {
  document.getElementById('doc-titulo').value      = '';
  document.getElementById('doc-tipo').value        = '';
  document.getElementById('doc-franquicia').value  = '';
  document.getElementById('doc-visible').checked   = false;
  document.getElementById('doc-archivo').value     = '';
  document.getElementById('doc-error').style.display = 'none';
  resetDropZone();

  // Si hay empresa seleccionada en filtro y es super_admin, preseleccionar
  if (rolUsuario === 'super_admin') {
    const empId = document.getElementById('sel-empresa').value;
    document.getElementById('doc-empresa').value = empId || '';
    if (empId) onEmpresaDocChange();
  }

  document.getElementById('modal-subir').classList.add('open');
  setTimeout(() => document.getElementById('doc-titulo').focus(), 100);
}

function cerrarModalSubir() {
  document.getElementById('modal-subir').classList.remove('open');
}

function resetDropZone() {
  const dz = document.getElementById('drop-zone');
  dz.classList.remove('has-file');
  document.getElementById('drop-zone-content').innerHTML = `
    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color:var(--gris4);margin-bottom:8px"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
    <div style="font-size:13px;color:var(--gris5)">Hacé clic o arrastrá un archivo</div>
    <div style="font-size:11px;color:var(--gris4);margin-top:4px">PDF, DOC o DOCX — máximo 20 MB</div>`;
}

function onArchivoSeleccionado(input) {
  const file = input.files[0];
  if (!file) { resetDropZone(); return; }
  const dz = document.getElementById('drop-zone');
  dz.classList.add('has-file');
  document.getElementById('drop-zone-content').innerHTML = `
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--exito)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom:6px"><polyline points="20 6 9 17 4 12"/></svg>
    <div style="font-size:13px;color:var(--blanco);font-weight:500">${esc(file.name)}</div>
    <div style="font-size:11px;color:var(--gris4);margin-top:4px">${(file.size/1024).toFixed(0)} KB · ${file.type || 'archivo'}</div>`;
}

// Drag & drop
const dropZone = document.getElementById('drop-zone');
dropZone.addEventListener('dragover',  e => { e.preventDefault(); dropZone.classList.add('dragover'); });
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));
dropZone.addEventListener('drop', e => {
  e.preventDefault(); dropZone.classList.remove('dragover');
  const file = e.dataTransfer.files[0];
  if (file) {
    const input = document.getElementById('doc-archivo');
    const dt = new DataTransfer(); dt.items.add(file);
    input.files = dt.files;
    onArchivoSeleccionado(input);
  }
});

async function subirDocumento() {
  const titulo     = document.getElementById('doc-titulo').value.trim();
  const tipo       = document.getElementById('doc-tipo').value;
  const franqId    = document.getElementById('doc-franquicia').value;
  const visible    = document.getElementById('doc-visible').checked;
  const empresaId  = document.getElementById('doc-empresa')?.value || '';
  const archivo    = document.getElementById('doc-archivo').files[0];
  const errEl      = document.getElementById('doc-error');
  const btn        = document.getElementById('btn-confirmar-subir');

  errEl.style.display = 'none';

  if (!titulo)  { errEl.textContent = 'El título es obligatorio.'; errEl.style.display = 'block'; return; }
  if (!tipo)    { errEl.textContent = 'Seleccioná un tipo.';       errEl.style.display = 'block'; return; }
  if (!archivo) { errEl.textContent = 'Seleccioná un archivo.';    errEl.style.display = 'block'; return; }
  if (rolUsuario === 'super_admin' && !empresaId) {
    errEl.textContent = 'Seleccioná una empresa.'; errEl.style.display = 'block'; return;
  }
  if (archivo.size > 20 * 1024 * 1024) {
    errEl.textContent = 'El archivo supera los 20 MB.'; errEl.style.display = 'block'; return;
  }

  btn.disabled = true; btn.textContent = 'Subiendo...';

  try {
    const form = new FormData();
    form.append('titulo',               titulo);
    form.append('tipo',                 tipo);
    form.append('visible_franquiciado', visible ? '1' : '0');
    form.append('archivo',             archivo);
    if (franqId)   form.append('franquicia_id', franqId);
    if (rolUsuario === 'super_admin' && empresaId) form.append('empresa_id', empresaId);

    // apiFetch no sirve para multipart, usamos fetch directo
    const res = await fetchMultipart('/documentos', form);
    todosLosDocumentos.unshift(res);
    aplicarFiltros();
    cerrarModalSubir();
    mostrarToast('Documento subido correctamente.', 'exito');
  } catch (e) {
    const msg = e.data?.errors
      ? Object.values(e.data.errors).flat().join(' ')
      : e.data?.message || 'Error al subir el documento.';
    errEl.textContent = msg; errEl.style.display = 'block';
  } finally {
    btn.disabled = false;
    btn.innerHTML = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg> Subir documento`;
  }
}

// fetch multipart (no usa apiFetch porque necesita FormData sin Content-Type)
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

// ── DESCARGA AUTENTICADA ──────────────────────────────────────
// Pasa por el endpoint protegido (valida rol + empresa) y recibe un stream.
// Se maneja como blob, asi funciona igual en disco local y en S3.
async function descargarDocumento(id) {
  const doc = todosLosDocumentos.find(d => d.id === id);
  try {
    const res = await fetch(API + '/documentos/' + id + '/descargar', {
      credentials: 'include',
    });

    if (!res.ok) {
      mostrarToast('No se pudo descargar el documento.', 'error');
      return;
    }

    const blob = await res.blob();

    // Nombre del archivo: lo toma del header del backend, o lo arma desde el titulo
    let filename = 'documento';
    const cd = res.headers.get('Content-Disposition');
    if (cd && cd.includes('filename=')) {
      filename = decodeURIComponent(cd.split('filename=')[1].replace(/["';]/g, '').trim());
    } else if (doc) {
      const ext = (doc.archivo_url || '').split('.').pop();
      filename = doc.titulo + (ext ? '.' + ext : '');
    }

    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    a.remove();
    window.URL.revokeObjectURL(url);
  } catch (e) {
    mostrarToast('Error al descargar el documento.', 'error');
  }
}

async function previsualizarDocumento(id) {
  // Abrimos la pestaña en el mismo gesto del click para que no la bloquee el navegador.
  const win = window.open('', '_blank');
  if (win) win.document.write('<p style="font-family:sans-serif;color:#555;padding:24px">Cargando vista previa...</p>');
  try {
    const res = await fetch(API + '/documentos/' + id + '/preview', { credentials: 'include' });
    if (!res.ok) {
      if (win) win.close();
      mostrarToast('No se pudo abrir la vista previa.', 'error');
      return;
    }
    const blob = await res.blob();
    const url  = window.URL.createObjectURL(blob);
    if (win) { win.location.href = url; } else { window.open(url, '_blank'); }
    // Liberamos el blob después de un rato (ya quedó cargado en el visor).
    setTimeout(() => window.URL.revokeObjectURL(url), 60000);
  } catch (e) {
    if (win) win.close();
    mostrarToast('Error al abrir la vista previa.', 'error');
  }
}

// ── MODAL ELIMINAR DOCUMENTO ──────────────────────────────────
function abrirModalEliminar(id, titulo) {
  pendingEliminar = id;
  document.getElementById('eliminar-msg').textContent = `¿Eliminar "${titulo}"? Dejará de ser visible en caso de haber designado este documento a algun franquiciado.`;
  document.getElementById('eliminar-error').textContent = '';
  document.getElementById('eliminar-error').style.display = 'none';
  document.getElementById('modal-eliminar').classList.add('open');
}
function cerrarModalEliminar() { document.getElementById('modal-eliminar').classList.remove('open'); pendingEliminar = null; }

async function ejecutarEliminar() {
  if (!pendingEliminar) return;
  const btn = document.getElementById('btn-eliminar-confirmar');
  btn.disabled = true; btn.textContent = 'Eliminando...';
  try {
    await apiFetch('DELETE', `/documentos/${pendingEliminar}`);
    mostrarToast('Documento eliminado correctamente.', 'exito');
    cerrarModalEliminar();
    await cargarDocumentos();
  } catch (e) {
    document.getElementById('eliminar-error').textContent = e.data?.message || 'Error al eliminar.';
    document.getElementById('eliminar-error').style.display = 'block';
    btn.disabled = false; btn.textContent = 'Eliminar';
  }
}

// ── RESTAURAR DOCUMENTO (solo super_admin) ────────────────────
async function restaurarDocumento(id, titulo) {
  try {
    await apiFetch('POST', `/documentos/${id}/restore`);
    mostrarToast(`"${titulo}" restaurado correctamente.`, 'exito');
    await cargarDocumentos();
  } catch (e) {
    mostrarToast(e.data?.message || 'Error al restaurar.', 'error');
  }
}

// ── HELPERS ───────────────────────────────────────────────────
function formatFecha(str) {
  if (!str) return '—';
  const d = new Date(str);
  return d.toLocaleDateString('es-AR', { day:'2-digit', month:'2-digit', year:'numeric' });
}

function esc(str) {
  if (!str) return '—';
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
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

document.addEventListener('keydown', e => {
  if (e.key === 'Escape') cerrarModalSubir();
});

init();
</script>

<?php include 'layout/footer.php'; ?>