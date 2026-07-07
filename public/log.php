<?php
include 'layout/config.php';
include 'layout/auth.php';
verificarSesion('super_admin');
$titulo        = 'Log de actividad';
$pagina_actual = 'log';
include 'layout/head.php';
?>

<div class="app-layout">
  <?php include 'layout/topbar.php'; ?>

  <div class="app-body">
    <?php include 'layout/sidebar.php'; ?>

    <main class="main-content">

      <div class="page-header" style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px">
        <div>
          <div class="page-title">Log de actividad</div>
          <div class="page-sub" id="page-sub">Registro inmutable de todas las acciones del sistema</div>
        </div>
        <button class="btn btn-ghost" onclick="exportarCSV()" id="btn-exportar">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
          Exportar CSV
        </button>
      </div>

      <!-- Pestañas -->
      <div class="log-tabs">
        <button class="log-tab active" id="tab-todos" onclick="cambiarVista('todos')">Todos</button>
        <button class="log-tab" id="tab-franq" onclick="cambiarVista('franquiciante')">Cambios de manuales (franquiciante)</button>
      </div>

      <!-- Filtros -->
      <div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap;align-items:center">

        <!-- Combobox empresa — solo super_admin -->
        <div id="grupo-filtro-empresa" style="display:none;align-items:center;gap:8px;position:relative">
          <div id="empresa-combo-log" style="position:relative;width:240px">
            <input type="text" id="inp-empresa-log" placeholder="Buscar empresa..." autocomplete="off" name="combo-empresa-log"
                   class="buscar-input" style="width:100%;box-sizing:border-box;padding-right:30px"
                   oninput="filtrarOpcionesEmpresaLog()" onfocus="filtrarOpcionesEmpresaLog()">
            <svg style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--gris4)" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <button type="button" id="empresa-clear-log" onclick="limpiarEmpresaLog()" title="Mostrar todas las empresas"
                    style="display:none;position:absolute;right:8px;top:50%;transform:translateY(-50%);background:transparent;border:none;color:var(--gris4);cursor:pointer;padding:2px;line-height:0">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
            <div id="empresa-opciones-log" class="combo-opciones"></div>
          </div>
        </div>

        <select id="filtro-accion" onchange="aplicarFiltros()" class="filtro-select">
          <option value="">Todas las acciones</option>
          <option value="login">Login</option>
          <option value="logout">Logout</option>
          <option value="manual_creado">Manual creado</option>
          <option value="manual_editado">Manual editado</option>
          <option value="manual_publicado">Manual publicado</option>
          <option value="version_publicada_franquiciante">Versión publicada (franquiciante)</option>
          <option value="manual_archivado">Manual archivado</option>
          <option value="manual_abierto">Manual abierto</option>
          <option value="manual_aceptado">Manual aceptado</option>
          <option value="manual_asignado">Manual asignado</option>
          <option value="manual_desasignado">Manual desasignado</option>
          <option value="usuario_creado">Usuario creado</option>
          <option value="usuario_desactivado">Usuario desactivado</option>
          <option value="documento_subido">Documento subido</option>
          <option value="firma_fisica_subida">Firma física subida</option>
          <option value="archivo_subido">Archivo subido</option>
          <option value="franquicia_creada">Franquicia creada</option>
        </select>

        <select id="filtro-usuario" onchange="aplicarFiltros()" class="filtro-select">
          <option value="">Todos los usuarios</option>
        </select>

        <input type="date" id="filtro-desde" onchange="aplicarFiltros()" class="filtro-date" title="Desde">
        <input type="date" id="filtro-hasta" onchange="aplicarFiltros()" class="filtro-date" title="Hasta">

        <button class="btn btn-ghost" onclick="limpiarFiltros()" style="padding:6px 12px;font-size:12px">
          Limpiar filtros
        </button>

        <div style="margin-left:auto;position:relative">
          <input type="text" id="inp-buscar" placeholder="Buscar IP, usuario, acción..." oninput="aplicarFiltros()" autocomplete="off" class="buscar-input">
          <svg style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--gris4)" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        </div>
      </div>

      <!-- Stats rápidas -->
      <div class="stats-grid" id="stats-grid" style="display:none">
        <div class="stat-card">
          <div class="stat-card-label">Total registros</div>
          <div class="stat-card-value" id="stat-total">—</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-label">Logins hoy</div>
          <div class="stat-card-value dorado" id="stat-logins">—</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-label">Manuales publicados</div>
          <div class="stat-card-value" id="stat-publicados">—</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-label">Aceptaciones</div>
          <div class="stat-card-value" id="stat-aceptaciones">—</div>
        </div>
      </div>

      <!-- Tabla -->
      <div class="tabla-wrap">
        <div class="tabla-header">
          <h3 id="tabla-titulo">Cargando...</h3>
        </div>
        <table>
          <thead id="tabla-thead">
            <tr>
              <th>Fecha y hora</th>
              <th>Usuario</th>
              <th>Acción</th>
              <th>Entidad</th>
              <th>Detalle</th>
              <th>IP</th>
            </tr>
          </thead>
          <tbody id="tabla-body">
            <tr><td colspan="6">
              <div class="loading-msg">
                <div class="spinner" style="display:block"></div>
                Cargando registros...
              </div>
            </td></tr>
          </tbody>
        </table>

        <!-- Paginación -->
        <div id="paginacion" style="display:none;padding:14px 20px;border-top:1px solid var(--gris2);display:flex;align-items:center;justify-content:space-between">
          <span id="pag-info" style="font-size:12px;color:var(--gris4);font-family:'Roboto',sans-serif"></span>
          <div style="display:flex;gap:6px">
            <button class="btn btn-ghost" id="btn-prev" onclick="cambiarPagina(-1)" style="padding:5px 12px;font-size:12px">← Anterior</button>
            <button class="btn btn-ghost" id="btn-next" onclick="cambiarPagina(1)"  style="padding:5px 12px;font-size:12px">Siguiente →</button>
          </div>
        </div>
      </div>

    </main>
  </div>
</div>

<!-- ── MODAL DETALLE ──────────────────────────────────────────── -->
<div class="modal-overlay" id="modal-detalle" onclick="if(event.target===this)cerrarDetalle()">
  <div class="modal-box" style="max-width:500px">
    <div class="modal-header">
      <h3>Detalle del registro</h3>
      <button class="modal-close" onclick="cerrarDetalle()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-body" id="detalle-body"></div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="cerrarDetalle()">Cerrar</button>
    </div>
  </div>
</div>

<style>
.filtro-select, .filtro-date {
  background: var(--gris2); border: 1px solid var(--gris2);
  border-radius: 7px; color: var(--gris5);
  font-size: 12px; font-family: 'Archivo', sans-serif;
  padding: 7px 10px; cursor: pointer; outline: none;
  transition: border-color .2s;
}
.filtro-select:focus, .filtro-date:focus { border-color: var(--dorado); }
.filtro-select option { background: var(--gris1); }
.filtro-date { color-scheme: dark; }

.buscar-input {
  background: var(--gris2); border: 1px solid var(--gris2);
  border-radius: 7px; padding: 7px 12px 7px 32px;
  font-size: 13px; color: var(--blanco);
  font-family: 'Archivo', sans-serif; outline: none;
  width: 260px; transition: border-color .2s;
}
.buscar-input:focus { border-color: var(--dorado); }

/* ── Combobox de empresa (mismo patrón que documentos.php) ── */
.combo-opciones { display:none;position:absolute;top:calc(100% + 4px);left:0;right:0;max-height:240px;overflow-y:auto;background:var(--gris1);border:1px solid var(--gris2);border-radius:8px;z-index:50;box-shadow:0 8px 24px rgba(0,0,0,.4); }
.combo-opcion { padding:9px 12px;font-size:13px;color:var(--gris5);cursor:pointer;font-family:'Roboto',sans-serif;transition:background .12s; }
.combo-opcion:hover { background:var(--gris2);color:var(--blanco); }
.combo-vacio { padding:10px 12px;font-size:12px;color:var(--gris4);font-family:'Roboto',sans-serif; }

.accion-pill {
  display: inline-flex; align-items: center; gap: 5px;
  font-size: 11px; font-weight: 500;
  padding: 3px 9px; border-radius: 20px;
  white-space: nowrap;
}

/* Colores por categoría de acción */
.accion-auth     { background: rgba(55,138,221,.12);  color: #378ADD; }
.accion-manual   { background: rgba(201,168,76,.12);  color: var(--dorado); }
.accion-usuario  { background: rgba(92,184,122,.12);  color: var(--exito); }
.accion-archivo  { background: rgba(136,136,136,.12); color: var(--gris5); }
.accion-sistema  { background: rgba(226,92,92,.12);   color: var(--error); }

.modal-overlay {
  display: none; position: fixed; inset: 0;
  background: rgba(0,0,0,.6); z-index: 500;
  align-items: center; justify-content: center; padding: 16px;
}
.modal-overlay.open { display: flex; }

.modal-box {
  background: var(--gris1); border: 1px solid var(--gris2);
  border-radius: 14px; width: 100%; max-height: 90vh; overflow-y: auto;
}

.modal-header {
  padding: 18px 20px; border-bottom: 1px solid var(--gris2);
  display: flex; align-items: center; justify-content: space-between;
  position: sticky; top: 0; background: var(--gris1); z-index: 1;
}
.modal-header h3 { font-size: 15px; font-weight: 600; color: var(--blanco); }

.modal-close {
  background: transparent; border: none; cursor: pointer;
  color: var(--gris4); padding: 4px; border-radius: 5px;
  transition: color .15s, background .15s; display: flex;
}
.modal-close:hover { color: var(--blanco); background: var(--gris2); }

.modal-body   { padding: 20px; }
.modal-footer {
  padding: 14px 20px; border-top: 1px solid var(--gris2);
  display: flex; justify-content: flex-end; gap: 8px;
  position: sticky; bottom: 0; background: var(--gris1);
}

.detalle-row {
  display: flex; gap: 12px; margin-bottom: 12px;
  font-size: 13px; font-family: 'Roboto', sans-serif;
}
.detalle-label { color: var(--gris4); min-width: 110px; flex-shrink: 0; }
.detalle-valor { color: var(--blanco); word-break: break-all; }

.json-block {
  background: var(--negro); border: 1px solid var(--gris2);
  border-radius: 7px; padding: 12px; font-size: 12px;
  font-family: monospace; color: var(--gris5);
  white-space: pre-wrap; word-break: break-all;
  line-height: 1.6; margin-top: 4px;
}
.log-tabs { display:flex; gap:6px; margin-bottom:16px; flex-wrap:wrap; }
.log-tab { background:transparent; border:1px solid var(--gris2); border-radius:20px; padding:7px 16px; font-size:12px; font-family:'Archivo',sans-serif; color:var(--gris4); cursor:pointer; transition:all .15s; }
.log-tab:hover { color:var(--blanco); border-color:var(--gris3); }
.log-tab.active { background:rgba(201,168,76,.12); border-color:rgba(201,168,76,.3); color:var(--dorado); }
</style>

<script>
const POR_PAGINA   = 50;
let todosLosLogs   = [];
let logsFiltrados  = [];
let paginaActual   = 1;
let todosUsuarios  = [];
let vistaActual    = 'todos';
let todasLasEmpresas = [];
let empresaFiltroId  = ''; // filtro de empresa (solo super_admin)
let miRol            = '';

// ── INICIALIZAR ───────────────────────────────────────────────
async function init() {
  try {
    // Primero traemos el usuario actual para saber qué rol es y si mostrar el filtro de empresa
    const me = await apiFetch('GET', '/me');
    miRol = me.rol || '';

    // Cargas en paralelo: logs, usuarios y (solo super_admin) empresas
    const reqs = [
      apiFetch('GET', '/activity-logs'),
      apiFetch('GET', '/usuarios'),
    ];
    if (miRol === 'super_admin') reqs.push(apiFetch('GET', '/empresas'));

    const [logs, usuarios, empresas] = await Promise.all(reqs);

    todosLosLogs  = logs;
    todosUsuarios = usuarios;

    // Combobox de empresa: solo super_admin
    if (miRol === 'super_admin') {
      todasLasEmpresas = empresas;
      document.getElementById('grupo-filtro-empresa').style.display = '';
    }

    // Poblar filtro de usuarios
    const sel = document.getElementById('filtro-usuario');
    usuarios.forEach(u => {
      const nombre = nombreUsuario(u);
      const opt    = document.createElement('option');
      opt.value       = u.id;
      opt.textContent = nombre;
      sel.appendChild(opt);
    });

    // Stats
    const hoy = new Date().toDateString();
    document.getElementById('stat-total').textContent =
      logs.length.toLocaleString('es-AR');
    document.getElementById('stat-logins').textContent =
      logs.filter(l => l.accion === 'login' && new Date(l.created_at).toDateString() === hoy).length;
    document.getElementById('stat-publicados').textContent =
      logs.filter(l => l.accion === 'manual_publicado').length;
    document.getElementById('stat-aceptaciones').textContent =
      logs.filter(l => l.accion === 'manual_aceptado').length;
    document.getElementById('stats-grid').style.display = 'grid';

    aplicarFiltros();

  } catch (e) {
    document.getElementById('tabla-body').innerHTML =
      `<tr><td colspan="6"><div class="empty-state">Error al cargar el log.</div></td></tr>`;
  }
}

// Recarga los logs desde el servidor respetando el filtro de empresa activo.
async function recargarLogs() {
  const url = empresaFiltroId ? `/activity-logs?empresa_id=${empresaFiltroId}` : '/activity-logs';
  try {
    todosLosLogs = await apiFetch('GET', url);
    paginaActual = 1;

    // Re-calcular stats con la nueva lista
    const hoy = new Date().toDateString();
    document.getElementById('stat-total').textContent =
      todosLosLogs.length.toLocaleString('es-AR');
    document.getElementById('stat-logins').textContent =
      todosLosLogs.filter(l => l.accion === 'login' && new Date(l.created_at).toDateString() === hoy).length;
    document.getElementById('stat-publicados').textContent =
      todosLosLogs.filter(l => l.accion === 'manual_publicado').length;
    document.getElementById('stat-aceptaciones').textContent =
      todosLosLogs.filter(l => l.accion === 'manual_aceptado').length;

    aplicarFiltros();
  } catch {
    document.getElementById('tabla-body').innerHTML =
      `<tr><td colspan="6"><div class="empty-state">Error al cargar el log.</div></td></tr>`;
  }
}

// ── COMBOBOX DE EMPRESA (mismo patrón que documentos.php) ─────
function filtrarOpcionesEmpresaLog() {
  const input = document.getElementById('inp-empresa-log');
  const cont  = document.getElementById('empresa-opciones-log');
  const texto = input.value.toLowerCase().trim();

  if (texto === '' && empresaFiltroId !== '') {
    empresaFiltroId = '';
    document.getElementById('empresa-clear-log').style.display = 'none';
    recargarLogs();
  }

  const coincidencias = todasLasEmpresas.filter(e => e.nombre.toLowerCase().includes(texto));

  if (!coincidencias.length) {
    cont.innerHTML = `<div class="combo-vacio">Sin coincidencias</div>`;
    cont.style.display = 'block';
    return;
  }

  cont.innerHTML = coincidencias.map(e => `
    <div class="combo-opcion" onmousedown="seleccionarEmpresaLog(${e.id}, '${esc(e.nombre).replace(/'/g, "\\'")}')">
      ${esc(e.nombre)}${e.activa ? '' : ' <span style="color:var(--gris4)">(suspendida)</span>'}
    </div>`).join('');
  cont.style.display = 'block';
}

function seleccionarEmpresaLog(id, nombre) {
  empresaFiltroId = String(id);
  document.getElementById('inp-empresa-log').value = nombre;
  document.getElementById('empresa-clear-log').style.display = 'block';
  document.getElementById('empresa-opciones-log').style.display = 'none';
  recargarLogs();
}

function limpiarEmpresaLog() {
  empresaFiltroId = '';
  document.getElementById('inp-empresa-log').value = '';
  document.getElementById('empresa-clear-log').style.display = 'none';
  document.getElementById('empresa-opciones-log').style.display = 'none';
  recargarLogs();
}

// ── FILTROS ───────────────────────────────────────────────────
function aplicarFiltros() {
  const accion  = vistaActual === 'franquiciante'
    ? 'version_publicada_franquiciante'
    : document.getElementById('filtro-accion').value;
  const userId  = document.getElementById('filtro-usuario').value;
  const desde   = document.getElementById('filtro-desde').value;
  const hasta   = document.getElementById('filtro-hasta').value;
  const texto   = document.getElementById('inp-buscar').value.toLowerCase().trim();

  logsFiltrados = todosLosLogs.filter(l => {
    if (accion  && l.accion   !== accion)             return false;
    if (userId  && String(l.user_id) !== userId)       return false;
    if (desde   && l.created_at < desde)               return false;
    if (hasta   && l.created_at > hasta + 'T23:59:59') return false;
    if (texto) {
      const haystack = [
        l.accion, l.ip_address,
        l.user?.nombre || '',
        l.user?.apellido || '',
        l.user?.email || '',
        JSON.stringify(l.detalle || ''),
      ].join(' ').toLowerCase();
      if (!haystack.includes(texto)) return false;
    }
    return true;
  });

  paginaActual = 1;
  renderTabla();
}

function limpiarFiltros() {
  document.getElementById('filtro-accion').value  = '';
  document.getElementById('filtro-usuario').value = '';
  document.getElementById('filtro-desde').value   = '';
  document.getElementById('filtro-hasta').value   = '';
  document.getElementById('inp-buscar').value     = '';
  // Si el filtro de empresa estaba puesto, recargamos los logs sin él
  if (empresaFiltroId) {
    limpiarEmpresaLog();
  } else {
    aplicarFiltros();
  }
}

function cambiarVista(v) {
  vistaActual = v;
  document.getElementById('tab-todos').classList.toggle('active', v === 'todos');
  document.getElementById('tab-franq').classList.toggle('active', v === 'franquiciante');
  // En la vista de franquiciante la acción está fija, deshabilitamos ese filtro
  document.getElementById('filtro-accion').disabled = (v === 'franquiciante');
  aplicarFiltros();
}

// ── RENDER TABLA ──────────────────────────────────────────────
function renderTabla() {
  const tbody  = document.getElementById('tabla-body');
  const total  = logsFiltrados.length;
  const inicio = (paginaActual - 1) * POR_PAGINA;
  const fin    = Math.min(inicio + POR_PAGINA, total);
  const pagina = logsFiltrados.slice(inicio, fin);

  document.getElementById('tabla-titulo').textContent =
    `${total.toLocaleString('es-AR')} registro(s)`;

  const thead = document.getElementById('tabla-thead');

  // ── Vista "Cambios de manuales por franquiciante" ──
  if (vistaActual === 'franquiciante') {
    thead.innerHTML = `<tr><th>Fecha y hora</th><th>Franquiciante</th><th>Empresa</th><th>Email</th><th>IP</th></tr>`;
    if (!pagina.length) {
      tbody.innerHTML = `<tr><td colspan="5"><div class="empty-state">Sin cambios de manuales por franquiciantes.</div></td></tr>`;
      document.getElementById('paginacion').style.display = 'none';
      return;
    }
    tbody.innerHTML = pagina.map((l, i) => {
      const d = l.detalle ? (typeof l.detalle === 'string' ? JSON.parse(l.detalle) : l.detalle) : {};
      const nombre = nombreUsuario(l.user, l.user_id);
      const empresa = l.empresa?.nombre || l.user?.empresa?.nombre || '—';
      return `<tr style="cursor:pointer" onclick="verDetalle(${inicio + i})" title="Ver detalle">
        <td style="font-family:'Roboto',sans-serif;font-size:12px;white-space:nowrap;color:var(--gris4)">${formatFechaHora(l.created_at)}</td>
        <td>
          <div style="font-size:13px;font-weight:500;color:var(--blanco)">${esc(nombre)}</div>
          <div style="font-size:11px;color:var(--gris4)">${esc(d.manual_titulo || '')}${d.version ? ' · v' + d.version : ''}</div>
        </td>
        <td style="font-size:12px;color:var(--gris5)">${esc(empresa)}</td>
        <td style="font-size:12px;font-family:'Roboto',sans-serif;color:var(--gris4)">${esc(l.user?.email || '—')}</td>
        <td style="font-family:'Roboto',sans-serif;font-size:12px;color:var(--gris4)">${esc(l.ip_address)}</td>
      </tr>`;
    }).join('');
    const pagF = document.getElementById('paginacion');
    pagF.style.display = total > POR_PAGINA ? 'flex' : 'none';
    document.getElementById('pag-info').textContent =
      `Mostrando ${inicio + 1}–${fin} de ${total.toLocaleString('es-AR')}`;
    document.getElementById('btn-prev').disabled = paginaActual === 1;
    document.getElementById('btn-next').disabled = fin >= total;
    return;
  }

  // ── Vista "Todos" ──
  thead.innerHTML = `<tr><th>Fecha y hora</th><th>Usuario</th><th>Acción</th><th>Entidad</th><th>Detalle</th><th>IP</th></tr>`;

  if (!pagina.length) {
    tbody.innerHTML = `<tr><td colspan="6"><div class="empty-state">Sin registros que mostrar.</div></td></tr>`;
    document.getElementById('paginacion').style.display = 'none';
    return;
  }

  tbody.innerHTML = pagina.map((l, i) => {
    const nombre = nombreUsuario(l.user, l.user_id);
    const rol    = l.user?.rol || '';

    return `<tr style="cursor:pointer" onclick="verDetalle(${inicio + i})" title="Ver detalle">
      <td style="font-family:'Roboto',sans-serif;font-size:12px;white-space:nowrap;color:var(--gris4)">
        ${formatFechaHora(l.created_at)}
      </td>
      <td>
        <div style="font-size:13px;font-weight:500;color:var(--blanco)">${esc(nombre)}</div>
        <div style="font-size:11px;color:var(--gris4)">${rol}</div>
      </td>
      <td>${accionPill(l.accion)}</td>
      <td style="font-size:12px;font-family:'Roboto',sans-serif;color:var(--gris4)">
        ${l.entidad_tipo ? `<span style="color:var(--gris5)">${esc(l.entidad_tipo)}</span> #${l.entidad_id}` : '—'}
      </td>
      <td style="font-size:12px;font-family:'Roboto',sans-serif;color:var(--gris4);max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
        ${resumenDetalle(l.detalle)}
      </td>
      <td style="font-family:'Roboto',sans-serif;font-size:12px;color:var(--gris4)">
        ${esc(l.ip_address)}
      </td>
    </tr>`;
  }).join('');

  // Paginación
  const pag = document.getElementById('paginacion');
  pag.style.display = total > POR_PAGINA ? 'flex' : 'none';
  document.getElementById('pag-info').textContent =
    `Mostrando ${inicio + 1}–${fin} de ${total.toLocaleString('es-AR')}`;
  document.getElementById('btn-prev').disabled = paginaActual === 1;
  document.getElementById('btn-next').disabled = fin >= total;
}

function cambiarPagina(dir) {
  paginaActual += dir;
  renderTabla();
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

// ── DETALLE ───────────────────────────────────────────────────
function verDetalle(idx) {
  const l      = logsFiltrados[idx];
  const nombre = nombreUsuario(l.user, l.user_id);

  document.getElementById('detalle-body').innerHTML = `
    <div class="detalle-row">
      <span class="detalle-label">Fecha y hora</span>
      <span class="detalle-valor">${formatFechaHora(l.created_at)}</span>
    </div>
    <div class="detalle-row">
      <span class="detalle-label">Usuario</span>
      <span class="detalle-valor">${esc(nombre)} <span style="color:var(--gris4)">(${l.user?.rol || ''})</span></span>
    </div>
    <div class="detalle-row">
      <span class="detalle-label">Email</span>
      <span class="detalle-valor">${esc(l.user?.email || '—')}</span>
    </div>
    <div class="detalle-row">
      <span class="detalle-label">Acción</span>
      <span class="detalle-valor">${accionPill(l.accion)}</span>
    </div>
    <div class="detalle-row">
      <span class="detalle-label">Entidad</span>
      <span class="detalle-valor">${l.entidad_tipo ? `${esc(l.entidad_tipo)} #${l.entidad_id}` : '—'}</span>
    </div>
    <div class="detalle-row">
      <span class="detalle-label">IP</span>
      <span class="detalle-valor">${esc(l.ip_address)}</span>
    </div>
    <div class="detalle-row">
      <span class="detalle-label">User agent</span>
      <span class="detalle-valor" style="font-size:11px;color:var(--gris4)">${esc(l.user_agent || '—')}</span>
    </div>
    ${l.detalle ? `
    <div class="detalle-row" style="flex-direction:column;gap:6px">
      <span class="detalle-label">Detalle JSON</span>
      <div class="json-block">${JSON.stringify(l.detalle, null, 2)}</div>
    </div>` : ''}
  `;

  document.getElementById('modal-detalle').classList.add('open');
}

function cerrarDetalle() {
  document.getElementById('modal-detalle').classList.remove('open');
}

// ── EXPORTAR CSV ──────────────────────────────────────────────
function exportarCSV() {
  const cabecera = ['Fecha', 'Usuario', 'Email', 'Rol', 'Accion', 'Entidad', 'IP'];
  const filas    = logsFiltrados.map(l => {
    const nombre = nombreUsuario(l.user, l.user_id);
    return [
      formatFechaHora(l.created_at),
      nombre,
      l.user?.email || '',
      l.user?.rol   || '',
      l.accion,
      l.entidad_tipo ? `${l.entidad_tipo}#${l.entidad_id}` : '',
      l.ip_address,
    ].map(v => `"${String(v).replace(/"/g, '""')}"`).join(',');
  });

  const csv  = [cabecera.join(','), ...filas].join('\n');
  const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
  const url  = URL.createObjectURL(blob);
  const a    = document.createElement('a');
  a.href     = url;
  a.download = `log_actividad_${new Date().toISOString().slice(0,10)}.csv`;
  a.click();
  URL.revokeObjectURL(url);
}

// ── HELPERS ───────────────────────────────────────────────────
const ACCION_MAP = {
  login:               ['accion-auth',    'Login'],
  logout:              ['accion-auth',    'Logout'],
  manual_creado:       ['accion-manual',  'Manual creado'],
  manual_editado:      ['accion-manual',  'Manual editado'],
  manual_publicado:    ['accion-manual',  'Manual publicado'],
  version_publicada_franquiciante: ['accion-manual', 'Versión publicada (franquiciante)'],
  manual_archivado:    ['accion-manual',  'Manual archivado'],
  manual_abierto:      ['accion-manual',  'Manual abierto'],
  manual_aceptado:     ['accion-manual',  'Manual aceptado'],
  manual_asignado:     ['accion-manual',  'Manual asignado'],
  manual_desasignado:  ['accion-manual',  'Manual desasignado'],
  usuario_creado:      ['accion-usuario', 'Usuario creado'],
  usuario_desactivado: ['accion-sistema', 'Usuario desactivado'],
  documento_subido:    ['accion-archivo', 'Documento subido'],
  firma_fisica_subida: ['accion-archivo', 'Firma física subida'],
  archivo_subido:      ['accion-archivo', 'Archivo subido'],
  franquicia_creada:   ['accion-usuario', 'Franquicia creada'],
  config_modificada:   ['accion-sistema', 'Config modificada'],
};

function accionPill(accion) {
  const [cls, label] = ACCION_MAP[accion] || ['accion-archivo', accion];
  return `<span class="accion-pill ${cls}">${label}</span>`;
}

function resumenDetalle(detalle) {
  if (!detalle) return '—';
  const d = typeof detalle === 'string' ? JSON.parse(detalle) : detalle;
  if (d.manual_titulo)   return `Manual: ${esc(d.manual_titulo)}`;
  if (d.campo)           return `${esc(d.campo)}: ${esc(d.valor_nuevo || '')}`;
  if (d.empleado_nombre) return `Empleado: ${esc(d.empleado_nombre)}`;
  return JSON.stringify(d).slice(0, 60) + '...';
}

function formatFechaHora(str) {
  if (!str) return '—';
  return new Date(str).toLocaleString('es-AR', {
    day: '2-digit', month: '2-digit', year: 'numeric',
    hour: '2-digit', minute: '2-digit', second: '2-digit'
  });
}

// v2.3: el nombre del usuario vive en la tabla users (nombre/apellido), no en
// system_admin/franchise_staff. Fallback a email y luego a #id.
function nombreUsuario(u, fallbackId) {
  if (!u) return fallbackId ? `Usuario #${fallbackId}` : '—';
  const nom = `${u.nombre || ''} ${u.apellido || ''}`.trim();
  return nom || u.email || (fallbackId ? `Usuario #${fallbackId}` : '—');
}

function esc(str) {
  if (!str) return '—';
  return String(str)
    .replace(/&/g,'&amp;').replace(/</g,'&lt;')
    .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

document.addEventListener('keydown', e => {
  if (e.key === 'Escape') cerrarDetalle();
});

// Cerrar el desplegable del combobox de empresa al hacer clic afuera
document.addEventListener('click', e => {
  const combo = document.getElementById('empresa-combo-log');
  const opc   = document.getElementById('empresa-opciones-log');
  if (combo && opc && !combo.contains(e.target)) {
    opc.style.display = 'none';
  }
});

document.addEventListener('DOMContentLoaded', () => init());
</script>

<?php include 'layout/footer.php'; ?>