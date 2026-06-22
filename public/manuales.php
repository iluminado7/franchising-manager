<?php
require_once __DIR__ . '/layout/config.php';
require_once __DIR__ . '/layout/auth.php';
verificarSesion('super_admin');
$titulo        = 'Manuales';
$pagina_actual = 'manuales';
include 'layout/head.php';
?>

<div class="app-layout">
  <?php include 'layout/topbar.php'; ?>

  <div class="app-body">
    <?php include 'layout/sidebar.php'; ?>

    <main class="main-content">

      <div class="page-header" style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px">
        <div>
          <div class="page-title">Manuales operativos</div>
          <div class="page-sub">Gestión global de manuales operativos</div>
        </div>
        <button class="btn btn-primary" id="btn-nuevo" onclick="abrirModalNuevo()">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Nuevo manual
        </button>
      </div>

      <!-- Filtros -->
      <div id="filtros-wrap" style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap;align-items:center">
        <button class="filtro-btn active" onclick="filtrar('todos', this)">Todos</button>
        <button class="filtro-btn" onclick="filtrar('borrador', this)">Borrador</button>
        <button class="filtro-btn" onclick="filtrar('publicado', this)">Publicados</button>
        <button class="filtro-btn" onclick="filtrar('archivado', this)">Archivados</button>
        <!-- Autocompletado de empresa — solo super_admin -->
        <div id="empresa-combo" style="margin-left:auto;position:relative;width:240px">
          <input type="text" id="inp-empresa" placeholder="Buscar empresa..." autocomplete="off"
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

      <!-- Tabla -->
      <div class="tabla-wrap">
        <div class="tabla-header">
          <h3 id="tabla-titulo">Listado</h3>
        </div>
        <table>
          <thead>
            <tr>
              <th>Empresa / Manual</th>
              <th>Categoría</th>
              <th>Estado</th>
              <th>Última actualización</th>
              <th>Versión</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody id="tabla-body">
            <tr><td colspan="6">
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
     MODAL NUEVO MANUAL
══════════════════════════════════════════ -->
<div class="modal-overlay" id="modal-nuevo" onclick="if(event.target===this)cerrarModalNuevo()">
  <div class="modal-box" style="max-width:520px">

    <div class="modal-header">
      <h3>Nuevo manual</h3>
      <button class="modal-close" onclick="cerrarModalNuevo()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>

    <div class="modal-body">

      <div class="form-row">
        <div class="form-group">
          <label>Título del manual *</label>
          <input type="text" id="nuevo-titulo" placeholder="Ej: Manual de apertura" maxlength="200">
        </div>
        <div class="form-group">
          <label>Categoría</label>
          <input type="text" id="nuevo-categoria" placeholder="Ej: Operaciones" maxlength="100">
        </div>
      </div>

      <!-- Selector de empresa (super_admin siempre ve esto) -->
      <div class="form-group">
        <label>Empresa *</label>
        <select id="nuevo-empresa" class="form-select">
          <option value="">Seleccioná una empresa</option>
        </select>
        <div style="font-size:11px;color:var(--gris4);margin-top:4px;font-family:'Archivo Narrow',sans-serif">El manual quedará asociado a esta empresa.</div>
      </div>

      <!-- Selector de modo -->
      <div class="form-group">
        <label>¿Cómo querés comenzar?</label>
        <div class="modo-selector">
          <button class="modo-btn active" id="modo-btn-scratch" onclick="seleccionarModo('scratch')">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            <span>Escribir desde cero</span>
            <small>Abrís el editor en blanco</small>
          </button>
          <button class="modo-btn" id="modo-btn-import" onclick="seleccionarModo('import')">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            <span>Importar Word (.docx)</span>
            <small>Convertí tu documento a HTML editable</small>
          </button>
        </div>
      </div>

      <div id="zona-import" style="display:none">
        <div class="drop-zone" id="drop-zone"
          onclick="document.getElementById('file-docx').click()"
          ondragover="onDragOver(event)"
          ondragleave="onDragLeave(event)"
          ondrop="onDrop(event)">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color:var(--gris3);margin-bottom:8px"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
          <div style="font-size:13px;color:var(--gris4)">Arrastrá tu <strong style="color:var(--dorado)">.docx</strong> acá o hacé clic para seleccionarlo</div>
        </div>
        <input type="file" id="file-docx" accept=".docx" style="display:none" onchange="procesarDocx(this.files[0])">
        <div id="import-ok" style="display:none;margin-top:10px;background:rgba(92,184,122,.08);border:1px solid rgba(92,184,122,.2);border-radius:8px;padding:10px 12px;font-size:12px;color:var(--exito)"></div>
        <div id="import-warn" style="display:none;margin-top:6px;background:rgba(201,168,76,.08);border:1px solid rgba(201,168,76,.2);border-radius:8px;padding:10px 12px;font-size:11px;color:var(--dorado);line-height:1.5"></div>
      </div>

      <div class="form-error" id="nuevo-error"></div>
    </div>

    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="cerrarModalNuevo()">Cancelar</button>
      <button class="btn btn-primary" id="btn-crear" onclick="crearManual()">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        Crear y abrir editor
      </button>
    </div>

  </div>
</div>

<!-- ══════════════════════════════════════════
     MODAL ACEPTACIONES
══════════════════════════════════════════ -->
<div class="modal-overlay" id="modal-aceptaciones" onclick="if(event.target===this)cerrarModalAceptaciones()">
  <div class="modal-box" style="max-width:620px">
    <div class="modal-header">
      <h3 id="acept-titulo">Aceptaciones</h3>
      <button class="modal-close" onclick="cerrarModalAceptaciones()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-body" id="acept-body">
      <div class="loading-msg"><div class="spinner" style="display:block"></div>Cargando...</div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="cerrarModalAceptaciones()">Cerrar</button>
    </div>
  </div>
</div>

<!-- MODAL ARCHIVAR -->
<div class="modal-overlay" id="modal-archivar" onclick="if(event.target===this)cerrarModalArchivar()">
  <div class="modal-box" style="max-width:380px">
    <div class="modal-header">
      <h3>Archivar manual</h3>
      <button class="modal-close" onclick="cerrarModalArchivar()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-body">
      <p id="archivar-msg" style="font-size:14px;color:var(--gris5);line-height:1.6;font-family:'Archivo Narrow',sans-serif"></p>
      <div class="form-error" id="archivar-error"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="cerrarModalArchivar()">Cancelar</button>
      <button class="btn btn-danger" id="btn-archivar-confirmar" onclick="ejecutarArchivar()">Archivar</button>
    </div>
  </div>
</div>

<!-- MODAL ELIMINAR MANUAL -->
<div class="modal-overlay" id="modal-eliminar" onclick="if(event.target===this)cerrarModalEliminar()">
  <div class="modal-box" style="max-width:380px">
    <div class="modal-header">
      <button class="modal-close" onclick="cerrarModalEliminar()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
      <h3>Eliminar manual</h3>
    </div>
    <div class="modal-body">
      <p style="color:var(--gris4);margin:12px 0 24px">¿Descartar el manual "<span id="modal-eliminar-titulo" style="color:var(--blanco);font-weight:500"></span>"? Esta acción es reversible.</p>
    </div> 
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="cerrarModalEliminar()">Cancelar</button>
      <button class="btn btn-danger" id="btn-eliminar-confirmar" onclick="ejecutarEliminar()">Eliminar</button>
    </div>
  </div>
</div>

<!-- MODAL NOTAS -->
<div class="modal-overlay" id="modal-notas" onclick="if(event.target===this)cerrarModalNotas()">
  <div class="modal-box" style="max-width:620px">
    <div class="modal-header">
      <h3 id="notas-titulo">Notas</h3>
      <button class="modal-close" onclick="cerrarModalNotas()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-body" id="notas-body">
      <div class="loading-msg"><div class="spinner" style="display:block"></div>Cargando...</div>
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
/* ── Estilos compartidos (copiar desde manuales_shared.css si lo centralizás) ── */
.filtro-btn { padding:6px 14px;border-radius:20px;border:1px solid var(--gris2);background:transparent;font-size:12px;font-family:'Archivo',sans-serif;color:var(--gris4);cursor:pointer;transition:all .15s; }
.filtro-btn:hover { border-color:var(--gris3);color:var(--blanco); }
.filtro-btn.active { background:rgba(201,168,76,.12);border-color:rgba(201,168,76,.3);color:var(--dorado); }
.filtro-select { background:var(--gris2);border:1px solid var(--gris2);border-radius:7px;color:var(--gris5);font-size:12px;font-family:'Archivo',sans-serif;padding:7px 10px;cursor:pointer;outline:none;transition:border-color .2s; }
.filtro-select:focus { border-color:var(--dorado); }
.filtro-select option { background:var(--gris1); }
.buscar-input { background:var(--gris2);border:1px solid var(--gris2);border-radius:7px;padding:7px 12px 7px 32px;font-size:13px;color:var(--blanco);font-family:'Archivo',sans-serif;outline:none;width:220px;transition:border-color .2s; }
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
.form-row { display:grid;grid-template-columns:1fr 1fr;gap:14px; }
.form-group { margin-bottom:14px; }
.form-group label { display:block;font-size:11px;font-weight:500;letter-spacing:.06em;text-transform:uppercase;color:var(--gris5);margin-bottom:6px; }
.form-group input[type=text] { width:100%;background:var(--negro);border:1px solid var(--gris2);border-radius:7px;padding:10px 12px;font-size:13px;font-family:'Archivo',sans-serif;color:var(--blanco);outline:none;transition:border-color .2s; }
.form-group input:focus { border-color:var(--dorado); }
.form-group input::placeholder { color:var(--gris3); }
.form-select { width:100%;background:var(--negro);border:1px solid var(--gris2);border-radius:7px;padding:10px 12px;font-size:13px;font-family:'Archivo',sans-serif;color:var(--blanco);outline:none;transition:border-color .2s;box-sizing:border-box;cursor:pointer; }
.form-select:focus { border-color:var(--dorado); }
.form-select option { background:var(--gris1); }
.form-error { background:rgba(226,92,92,.1);border:1px solid rgba(226,92,92,.3);border-radius:7px;padding:10px 12px;font-size:13px;color:var(--error);display:none;margin-top:8px;line-height:1.5; }
.modo-selector { display:grid;grid-template-columns:1fr 1fr;gap:10px; }
.modo-btn { display:flex;flex-direction:column;align-items:center;gap:6px;padding:16px 12px;border-radius:10px;border:1px solid var(--gris2);background:var(--negro);cursor:pointer;transition:border-color .2s,background .2s;color:var(--gris4);text-align:center; }
.modo-btn:hover { border-color:var(--gris3);color:var(--blanco); }
.modo-btn.active { border-color:var(--dorado);background:rgba(201,168,76,.06);color:var(--blanco); }
.modo-btn span { font-size:13px;font-weight:500;color:inherit;font-family:'Archivo',sans-serif; }
.modo-btn small { font-size:11px;color:var(--gris4);font-family:'Archivo Narrow',sans-serif; }
.drop-zone { border:1.5px dashed var(--gris2);border-radius:8px;padding:24px 16px;text-align:center;cursor:pointer;transition:border-color .2s,background .2s;display:flex;flex-direction:column;align-items:center; }
.drop-zone:hover { border-color:var(--dorado);background:rgba(201,168,76,.04); }
.drop-zone.drag { border-color:var(--dorado);background:rgba(201,168,76,.08); }
.accion-btn { background:transparent;border:none;cursor:pointer;padding:5px 8px;border-radius:5px;font-size:12px;font-family:'Archivo',sans-serif;transition:background .15s;display:inline-flex;align-items:center;gap:4px; }
.accion-btn:hover { background:var(--gris2); }
.empresa-separador td { background:rgba(201,168,76,.06);border-top:1px solid rgba(201,168,76,.15);border-bottom:1px solid rgba(201,168,76,.15);padding:8px 16px !important; }
.empresa-sep-inner { display:flex;align-items:center;gap:8px;font-size:11px;font-weight:600;letter-spacing:.07em;text-transform:uppercase;color:var(--dorado); }
.empresa-sep-count { font-size:10px;font-weight:400;color:var(--gris4);text-transform:none;letter-spacing:0;margin-left:4px; }
.toast { position:fixed;bottom:24px;right:24px;background:var(--gris1);border:1px solid var(--gris2);border-radius:10px;padding:12px 16px;font-size:13px;color:var(--blanco);display:flex;align-items:center;gap:10px;transform:translateY(80px);opacity:0;transition:transform .3s,opacity .3s;z-index:600;font-family:'Archivo Narrow',sans-serif;max-width:340px; }
.toast.show { transform:translateY(0);opacity:1; }
/* ── Notas / sugerencias ── */
.nota-card { background:var(--negro);border:1px solid var(--gris2);border-radius:10px;padding:14px 16px;margin-bottom:12px; }
.nota-card:last-child { margin-bottom:0; }
.nota-card-top { display:flex;align-items:flex-start;justify-content:space-between;gap:10px;margin-bottom:8px; }
.nota-empresa { display:block;font-size:13px;font-weight:600;color:var(--blanco); }
.nota-meta { font-size:11px;color:var(--gris4);font-family:'Archivo Narrow',sans-serif; }
.nota-contenido { font-size:13px;color:var(--gris5);line-height:1.6;font-family:'Archivo Narrow',sans-serif;white-space:pre-wrap;margin-bottom:12px; }
.nota-estado-pill { flex-shrink:0;font-size:10px;font-weight:600;padding:3px 9px;border-radius:20px;text-transform:uppercase;letter-spacing:.04em; }
.nota-pendiente { background:rgba(201,168,76,.14);color:var(--dorado); }
.nota-leida { background:rgba(255,255,255,.07);color:var(--gris5); }
.nota-resuelta { background:rgba(92,184,122,.14);color:var(--exito); }
.nota-acciones { display:flex;align-items:center;gap:6px;flex-wrap:wrap;border-top:1px solid var(--gris2);padding-top:10px; }
.nota-acciones-label { font-size:11px;color:var(--gris4);font-family:'Archivo Narrow',sans-serif;margin-right:2px; }
.nota-estado-btn { background:transparent;border:1px solid var(--gris2);border-radius:20px;padding:4px 12px;font-size:11px;font-family:'Archivo',sans-serif;color:var(--gris4);cursor:pointer;transition:all .15s; }
.nota-estado-btn:hover { border-color:var(--gris3);color:var(--blanco); }
.nota-estado-btn.activo { background:rgba(201,168,76,.12);border-color:rgba(201,168,76,.3);color:var(--dorado); }
/* ── Autocompletado de empresa ── */
.combo-opciones { display:none;position:absolute;top:calc(100% + 4px);left:0;right:0;max-height:240px;overflow-y:auto;background:var(--gris1);border:1px solid var(--gris2);border-radius:8px;z-index:50;box-shadow:0 8px 24px rgba(0,0,0,.4); }
.combo-opcion { padding:9px 12px;font-size:13px;color:var(--gris5);cursor:pointer;font-family:'Archivo Narrow',sans-serif;transition:background .12s; }
.combo-opcion:hover { background:var(--gris2);color:var(--blanco); }
.combo-vacio { padding:10px 12px;font-size:12px;color:var(--gris4);font-family:'Archivo Narrow',sans-serif; }
</style>

<script src="<?= BASE_URL_PHP ?>/js/mammoth.browser.min.js"></script>
<script>
const BASE_PHP = '<?= BASE_URL_PHP ?>';

let todosLosManuales = [];
let todasLasEmpresas = [];
let filtroActual     = 'todos';
let empresaFiltroId  = '';
let modoImport       = 'scratch';
let htmlImportado    = '';
let pendingArchivar  = null;
let pendingEliminar = null;

// ── INIT ──────────────────────────────────────────────────────
async function init() {
  try {
    // Cargar empresas para filtro y modal
    const empresas = await apiFetch('GET', '/empresas');
    todasLasEmpresas = empresas;

    // Select modal (solo activas)
    const selModal = document.getElementById('nuevo-empresa');
    empresas.filter(e => e.activa).forEach(e => {
      const opt = document.createElement('option');
      opt.value = e.id; opt.textContent = e.nombre;
      selModal.appendChild(opt);
    });

    // El filtro de empresa ahora es un autocompletado que usa todasLasEmpresas directamente.

    // Filtro inicial desde la URL (?estado=... y ?empresa_id=...)
    aplicarEstadoDesdeUrl();
    aplicarEmpresaDesdeUrl();

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

// ── FILTROS ───────────────────────────────────────────────────
function filtrar(tipo, btn) {
  filtroActual = tipo;
  document.querySelectorAll('.filtro-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  aplicarFiltros();
}

// Aplica el filtro de estado que venga en la URL (?estado=borrador|publicado|archivado).
// Se usa cuando se llega desde el dashboard con un filtro ya elegido.
function aplicarEstadoDesdeUrl() {
  const estado  = new URLSearchParams(location.search).get('estado');
  const validos = ['borrador', 'publicado', 'archivado'];
  if (!validos.includes(estado)) return;

  filtroActual = estado;
  document.querySelectorAll('.filtro-btn').forEach(b => {
    b.classList.toggle('active', (b.getAttribute('onclick') || '').includes(`'${estado}'`));
  });
}

// Aplica el filtro de empresa que venga en la URL (?empresa_id=...).
// Se usa al llegar desde el dashboard con el botón "Ver manuales" de una empresa.
function aplicarEmpresaDesdeUrl() {
  const empresaId = new URLSearchParams(location.search).get('empresa_id');
  if (!empresaId) return;
  const emp = todasLasEmpresas.find(e => String(e.id) === String(empresaId));
  if (!emp) return;
  empresaFiltroId = String(emp.id);
  document.getElementById('inp-empresa').value = emp.nombre;
  document.getElementById('empresa-clear').style.display = 'block';
  // El filtrado real lo aplica cargarManuales() a continuación.
}

// ── AUTOCOMPLETADO DE EMPRESA ─────────────────────────────────
function filtrarOpcionesEmpresa() {
  const input = document.getElementById('inp-empresa');
  const cont  = document.getElementById('empresa-opciones');
  const texto = input.value.toLowerCase().trim();

  // Si vació el campo y había una empresa elegida, vuelve a "todas".
  if (texto === '' && empresaFiltroId !== '') {
    empresaFiltroId = '';
    document.getElementById('empresa-clear').style.display = 'none';
    aplicarFiltros();
  }

  const coincidencias = todasLasEmpresas.filter(e => e.nombre.toLowerCase().includes(texto));

  if (!coincidencias.length) {
    cont.innerHTML = `<div class="combo-vacio">Sin coincidencias</div>`;
    cont.style.display = 'block';
    return;
  }

  cont.innerHTML = coincidencias.map(e => `
    <div class="combo-opcion" onmousedown="seleccionarEmpresa(${e.id}, '${esc(e.nombre).replace(/'/g, "\\'")}')">
      ${esc(e.nombre)}${e.activa ? '' : ' <span style="color:var(--gris4)">(suspendida)</span>'}
    </div>`).join('');
  cont.style.display = 'block';
}

function seleccionarEmpresa(id, nombre) {
  empresaFiltroId = String(id);
  document.getElementById('inp-empresa').value = nombre;
  document.getElementById('empresa-clear').style.display = 'block';
  document.getElementById('empresa-opciones').style.display = 'none';
  aplicarFiltros();
}

function limpiarEmpresa() {
  empresaFiltroId = '';
  document.getElementById('inp-empresa').value = '';
  document.getElementById('empresa-clear').style.display = 'none';
  document.getElementById('empresa-opciones').style.display = 'none';
  aplicarFiltros();
}

function aplicarFiltros() {
  let lista = [...todosLosManuales];

  if (filtroActual !== 'todos') lista = lista.filter(m => m.estado === filtroActual);
  if (empresaFiltroId) lista = lista.filter(m => String(m.empresa_id) === String(empresaFiltroId));

  renderTabla(lista);
  document.getElementById('tabla-titulo').textContent = `${lista.length} manual(es)`;
}

// ── RENDER TABLA (agrupado por empresa) ───────────────────────
function renderTabla(lista) {
  const tbody = document.getElementById('tabla-body');

  if (!lista.length) {
    tbody.innerHTML = `<tr><td colspan="6"><div class="empty-state">Sin manuales que mostrar.</div></td></tr>`;
    return;
  }

  const grupos = {};
  lista.forEach(m => {
    const empId     = m.empresa_id || 0;
    const empNombre = m.empresa?.nombre
      || todasLasEmpresas.find(e => e.id === empId)?.nombre
      || 'Sin empresa asignada';
    if (!grupos[empId]) grupos[empId] = { nombre: empNombre, manuales: [] };
    grupos[empId].manuales.push(m);
  });

  const filas = [];
  Object.values(grupos).forEach(grupo => {
    filas.push(`
      <tr class="empresa-separador">
        <td colspan="6">
          <div class="empresa-sep-inner">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            ${esc(grupo.nombre)}
            <span class="empresa-sep-count">${grupo.manuales.length} manual(es)</span>
          </div>
        </td>
      </tr>`);

    grupo.manuales.forEach(m => {
      const version    = m.version_activa?.[0] || null;
      const verNum     = version ? `v${version.version_number}` : '—';
      const fecha      = version ? formatFecha(version.publicado_at) : formatFecha(m.created_at);
      const fechaLabel = version ? 'Publicado' : 'Creado';

      filas.push(`<tr>
        <td style="padding-left:28px">
          <div style="color:var(--blanco);font-weight:500">${esc(m.titulo)}</div>
        </td>
        <td>${esc(m.categoria) || '—'}</td>
        <td>${estadoPill(m.estado)}</td>
        <td style="font-size:12px;font-family:'Archivo Narrow',sans-serif;color:var(--gris4)">
          <span style="color:var(--gris3);font-size:10px">${fechaLabel}</span><br>${fecha}
        </td>
        <td style="font-family:'Archivo Narrow',sans-serif">${verNum}</td>
        <td>
          <div style="display:flex;gap:4px;flex-wrap:wrap">
            <button class="accion-btn" style="color:var(--dorado)" onclick="irEditor(${m.id})">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
              Editar
            </button>
            ${version ? `
            <button class="accion-btn" style="color:var(--gris5)" onclick="verAceptaciones(${m.id}, '${esc(m.titulo)}', ${version.id})">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
              Aceptaciones
            </button>` : ''}
            <button class="accion-btn" style="color:var(--gris5)" onclick="verNotas(${m.id}, '${esc(m.titulo)}')">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
              Ver notas
            </button>
          ${m.estado !== 'archivado' ? `
          <button class="accion-btn" style="color:var(--error)" onclick="abrirModalArchivar(${m.id}, '${esc(m.titulo)}')">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/></svg>
            Archivar
          </button>` : `
          <button class="accion-btn" style="color:var(--dorado)" onclick="desarchivarManual(${m.id}, '${esc(m.titulo)}')">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><polyline points="9 13 12 10 15 13"/><line x1="12" y1="10" x2="12" y2="17"/></svg>
            Restaurar
          </button>`}
          ${m.estado !== 'eliminado' ? `
          <button class="accion-btn" style="color:var(--gris5)" onclick="abrirModalEliminar(${m.id}, '${esc(m.titulo)}')">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
            Eliminar
          </button>` : ''}
          </div>
        </td>
      </tr>`);
    });
  });

  tbody.innerHTML = filas.join('');
}

// ── MODAL NUEVO MANUAL ────────────────────────────────────────
function abrirModalNuevo() {
  document.getElementById('nuevo-titulo').value    = '';
  document.getElementById('nuevo-categoria').value = '';
  document.getElementById('nuevo-empresa').value   = '';
  document.getElementById('nuevo-error').style.display  = 'none';
  document.getElementById('import-ok').style.display    = 'none';
  document.getElementById('import-warn').style.display  = 'none';
  htmlImportado = '';
  seleccionarModo('scratch');
  document.getElementById('modal-nuevo').classList.add('open');
  setTimeout(() => document.getElementById('nuevo-titulo').focus(), 100);
}
function cerrarModalNuevo() { document.getElementById('modal-nuevo').classList.remove('open'); }

function seleccionarModo(modo) {
  modoImport = modo;
  document.getElementById('modo-btn-scratch').classList.toggle('active', modo === 'scratch');
  document.getElementById('modo-btn-import').classList.toggle('active', modo === 'import');
  document.getElementById('zona-import').style.display = modo === 'import' ? 'block' : 'none';
}

// ── DOCX ──────────────────────────────────────────────────────
function onDragOver(e) { e.preventDefault(); document.getElementById('drop-zone').classList.add('drag'); }
function onDragLeave() { document.getElementById('drop-zone').classList.remove('drag'); }
function onDrop(e) {
  e.preventDefault();
  document.getElementById('drop-zone').classList.remove('drag');
  const f = e.dataTransfer.files[0];
  if (f) procesarDocx(f);
}

async function procesarDocx(file) {
  if (!file?.name.endsWith('.docx')) { mostrarNuevoError('Solo se aceptan archivos .docx'); return; }
  const okEl = document.getElementById('import-ok');
  const warnEl = document.getElementById('import-warn');
  okEl.style.display = warnEl.style.display = 'none';
  try {
    const ab = await file.arrayBuffer();
    const result = await mammoth.convertToHtml({ arrayBuffer: ab }, {
      styleMap: [
        "p[style-name='Heading 1'] => h1:fresh","p[style-name='Heading 2'] => h2:fresh",
        "p[style-name='Heading 3'] => h3:fresh","p[style-name='Título 1'] => h1:fresh",
        "p[style-name='Título 2'] => h2:fresh","p[style-name='Título 3'] => h3:fresh",
        "p[style-name='Title'] => h1:fresh",
      ]
    });
    htmlImportado = result.value;
    const tmp = document.createElement('div');
    tmp.innerHTML = result.value;
    const words = (tmp.innerText || '').trim().split(/\s+/).filter(Boolean).length;
    okEl.textContent = `✓ "${file.name}" importado — ${words.toLocaleString('es-AR')} palabras`;
    okEl.style.display = 'block';
    if (result.messages.length) {
      warnEl.innerHTML = `⚠ ${result.messages.length} advertencia(s): ` + result.messages.slice(0,3).map(m => m.message).join(' · ');
      warnEl.style.display = 'block';
    }
    const tituloInp = document.getElementById('nuevo-titulo');
    if (!tituloInp.value.trim()) tituloInp.value = file.name.replace('.docx','').replace(/_/g,' ');
  } catch (err) { mostrarNuevoError('Error al convertir el archivo.'); }
}

// ── CREAR MANUAL ──────────────────────────────────────────────
async function crearManual() {
  const titulo    = document.getElementById('nuevo-titulo').value.trim();
  const categoria = document.getElementById('nuevo-categoria').value.trim();
  const empresaId = document.getElementById('nuevo-empresa').value;
  const btn       = document.getElementById('btn-crear');

  if (!titulo)    { mostrarNuevoError('El título es obligatorio.'); return; }
  if (!empresaId) { mostrarNuevoError('Seleccioná una empresa para asociar el manual.'); return; }
  if (modoImport === 'import' && !htmlImportado) { mostrarNuevoError('Importá un archivo .docx antes de continuar.'); return; }

  btn.disabled = true; btn.textContent = 'Creando...';
  try {
    const payload = { titulo, categoria: categoria || null, orden: 0, empresa_id: empresaId };
    const manual  = await apiFetch('POST', '/manuales', payload);
    cerrarModalNuevo();
    const url = `${BASE_PHP}/editor.php?id=${manual.id}` + (modoImport === 'import' && htmlImportado ? `&import=1` : '');
    if (modoImport === 'import' && htmlImportado) sessionStorage.setItem(`import_html_${manual.id}`, htmlImportado);
    window.location.href = url;
  } catch (e) {
    const msg = e.data?.errors ? Object.values(e.data.errors).flat().join(' ') : e.data?.message || 'Error al crear el manual.';
    mostrarNuevoError(msg);
    btn.disabled = false; btn.textContent = 'Crear y abrir editor';
  }
}

function mostrarNuevoError(msg) {
  const el = document.getElementById('nuevo-error');
  el.textContent = msg; el.style.display = 'block';
}

// ── EDITOR ────────────────────────────────────────────────────
function irEditor(id) { window.location.href = `${BASE_PHP}/editor.php?id=${id}`; }

// ── ARCHIVAR ──────────────────────────────────────────────────
function abrirModalArchivar(id, titulo) {
  pendingArchivar = id;
  document.getElementById('archivar-msg').textContent = `¿Archivar "${titulo}"? Dejará de ser visible para los franquiciados. El historial se conserva.`;
  document.getElementById('archivar-error').style.display = 'none';
  document.getElementById('modal-archivar').classList.add('open');
}
function cerrarModalArchivar() { document.getElementById('modal-archivar').classList.remove('open'); pendingArchivar = null; }

async function ejecutarArchivar() {
  if (!pendingArchivar) return;
  const btn = document.getElementById('btn-archivar-confirmar');
  btn.disabled = true; btn.textContent = 'Archivando...';
  try {
    await apiFetch('POST', `/manuales/${pendingArchivar}/archivar`);
    mostrarToast('Manual archivado correctamente.', 'exito');
    cerrarModalArchivar();
    await cargarManuales();
  } catch (e) {
    document.getElementById('archivar-error').textContent = e.data?.message || 'Error al archivar.';
    document.getElementById('archivar-error').style.display = 'block';
    btn.disabled = false; btn.textContent = 'Archivar';
  }
}

// ── MODAL ELIMINAR MANUAL ────────────────────────────────────────
function abrirModalEliminar(id, titulo) {
  pendingEliminar = id;
  document.getElementById('modal-eliminar-titulo').textContent = titulo;
  document.getElementById('modal-eliminar').style.display = 'flex';
}

function cerrarModalEliminar() {
  document.getElementById('modal-eliminar').style.display = 'none';
  pendingEliminar = null;
}

async function ejecutarEliminar() {
  if (!pendingEliminar) return;
  const id = pendingEliminar;
  try {
    const res = await apiFetch('DELETE', `/manuales/${id}`);
    mostrarToast('Manual eliminado correctamente.', 'success');
    cerrarModalEliminar();
    cargarManuales();
  } catch (e) {
    mostrarToast('Error al eliminar el manual.', 'error');
  }
}

// ── DESARCHIVACIÓN ───────────────────────────────────────────────
async function desarchivarManual(id, titulo) {
  try {
    await apiFetch('POST', `/manuales/${id}/desarchivar`);
    mostrarToast(`"${titulo}" restaurado a borrador.`, 'exito');
    todosLosManuales = await apiFetch('GET', '/manuales');
    aplicarFiltros();
  } catch (e) {
    mostrarToast(e.data?.message || 'Error al restaurar.', 'error');
  }
}
// ── ACEPTACIONES ──────────────────────────────────────────────
async function verAceptaciones(manualId, titulo, versionId) {
  document.getElementById('acept-titulo').textContent = `Aceptaciones — ${titulo}`;
  document.getElementById('acept-body').innerHTML = `<div class="loading-msg"><div class="spinner" style="display:block"></div>Cargando...</div>`;
  document.getElementById('modal-aceptaciones').classList.add('open');
  try {
    const aceptaciones = await apiFetch('GET', `/versiones/${versionId}/aceptaciones`);
    if (!aceptaciones.length) {
      document.getElementById('acept-body').innerHTML = `<div class="empty-state">Ningún franquiciado ha aceptado esta versión aún.</div>`;
      return;
    }
    document.getElementById('acept-body').innerHTML = `
      <table>
        <thead><tr><th>Franquiciado</th><th>Franquicia</th><th>Fecha</th><th>IP</th></tr></thead>
        <tbody>
          ${aceptaciones.map(a => `<tr>
            <td style="color:var(--blanco);font-weight:500">${esc(a.user?.franchise_staff?.nombre || '')} ${esc(a.user?.franchise_staff?.apellido || '')}</td>
            <td>${esc(a.user?.franchise_staff?.franquicia?.nombre || '—')}</td>
            <td style="font-family:'Archivo Narrow',sans-serif;font-size:12px">${formatFechaHora(a.aceptado_at)}</td>
            <td style="font-family:'Archivo Narrow',sans-serif;font-size:12px;color:var(--gris4)">${esc(a.ip_address)}</td>
          </tr>`).join('')}
        </tbody>
      </table>
      <div style="margin-top:12px;font-size:12px;color:var(--gris4);font-family:'Archivo Narrow',sans-serif">
        Total: ${aceptaciones.length} aceptación(es)
      </div>`;
  } catch (e) {
    document.getElementById('acept-body').innerHTML = `<div class="empty-state">Error al cargar las aceptaciones.</div>`;
  }
}
function cerrarModalAceptaciones() { document.getElementById('modal-aceptaciones').classList.remove('open'); }

// ── NOTAS / SUGERENCIAS ───────────────────────────────────────
let notaManualActual = null;

async function verNotas(manualId, titulo) {
  notaManualActual = manualId;
  document.getElementById('notas-titulo').textContent = `Notas — ${titulo}`;
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
      `<div class="empty-state">Error al cargar las notas.</div>`;
  }
}

function autorLabel(n) {
  const rol = n.autor?.rol;
  if (rol === 'franquiciado') {
    const fr = n.autor?.franchise_staff?.franquicia?.nombre;
    return fr ? `Franquiciado · ${esc(fr)}` : 'Franquiciado';
  }
  if (rol === 'franquiciante') return 'Franquiciante';
  return n.autor?.email ? esc(n.autor.email) : 'Autor';
}

function renderNotas(notas) {
  const body = document.getElementById('notas-body');

  if (!notas.length) {
    body.innerHTML = `<div class="empty-state">Este manual no tiene notas.</div>`;
    return;
  }

  const estadoLabel = { pendiente: 'Pendiente', leida: 'Leída', resuelta: 'Resuelta' };

  body.innerHTML = notas.map(n => {
    const version = n.version ? `v${n.version.version_number}` : 'Sin versión publicada';
    return `
      <div class="nota-card">
        <div class="nota-card-top">
          <div>
            <span class="nota-empresa">${esc(n.empresa?.nombre || 'Empresa')}</span>
            <span class="nota-meta">${autorLabel(n)} · ${version} · ${formatFechaHora(n.created_at)}</span>
          </div>
          <span class="nota-estado-pill nota-${n.estado}">${estadoLabel[n.estado] || n.estado}</span>
        </div>
        <div class="nota-contenido">${esc(n.contenido)}</div>
        <div class="nota-acciones">
          <span class="nota-acciones-label">Marcar como:</span>
          <button class="nota-estado-btn ${n.estado === 'pendiente' ? 'activo' : ''}" onclick="marcarEstadoNota(${n.id}, 'pendiente')">Pendiente</button>
          <button class="nota-estado-btn ${n.estado === 'leida' ? 'activo' : ''}" onclick="marcarEstadoNota(${n.id}, 'leida')">Leída</button>
          <button class="nota-estado-btn ${n.estado === 'resuelta' ? 'activo' : ''}" onclick="marcarEstadoNota(${n.id}, 'resuelta')">Resuelta</button>
        </div>
      </div>`;
  }).join('');
}

async function marcarEstadoNota(notaId, estado) {
  try {
    await apiFetch('PUT', `/notas/${notaId}/estado`, { estado });
    mostrarToast('Estado actualizado.', 'exito');
    if (notaManualActual) await cargarNotas(notaManualActual); // refrescar el hilo
  } catch (e) {
    mostrarToast(e.data?.message || 'Error al actualizar el estado.', 'error');
  }
}

function cerrarModalNotas() {
  document.getElementById('modal-notas').classList.remove('open');
  notaManualActual = null;
}

// ── HELPERS ───────────────────────────────────────────────────
function estadoPill(estado) {
  const map = { borrador:['estado-solo-digital','Borrador'], publicado:['estado-completo','Publicado'], archivado:['estado-solo-fisico','Archivado'] };
  const [cls, label] = map[estado] || ['estado-pendiente', estado];
  return `<span class="estado-pill ${cls}">${label}</span>`;
}
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
  if (e.key === 'Escape') { cerrarModalNuevo(); cerrarModalAceptaciones(); cerrarModalArchivar(); cerrarModalNotas(); }
});

// Cerrar el desplegable del autocompletado al hacer clic afuera
document.addEventListener('click', e => {
  const combo = document.getElementById('empresa-combo');
  if (combo && !combo.contains(e.target)) {
    document.getElementById('empresa-opciones').style.display = 'none';
  }
});

document.addEventListener('DOMContentLoaded', () => init());
</script>

<?php include 'layout/footer.php'; ?>