<?php
include 'layout/config.php';
include 'layout/auth.php';
verificarSesion('super_admin');
$titulo        = 'Errores del servidor';
$pagina_actual = 'errores';
include 'layout/head.php';
?>

<div class="app-layout">
  <?php include 'layout/topbar.php'; ?>

  <div class="app-body">
    <?php include 'layout/sidebar.php'; ?>

    <main class="main-content">

      <div class="page-header">
        <div>
          <h1 class="page-title">Errores del servidor</h1>
          <p class="page-sub">Errores 5xx agrupados por tipo. No reemplaza a storage/logs.</p>
        </div>
      </div>

      <div class="log-tabs">
        <button class="log-tab active" id="tab-todos"      onclick="cambiarVista('todos')">Todos</button>
        <button class="log-tab"        id="tab-pendientes" onclick="cambiarVista('pendientes')">Sin resolver</button>
        <button class="log-tab"        id="tab-resueltos"  onclick="cambiarVista('resueltos')">Resueltos</button>
      </div>

      <div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap;align-items:center">
        <div style="position:relative">
          <svg class="buscar-icon" width="14" height="14" viewBox="0 0 24 24" fill="none"
               stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
          </svg>
          <input type="search" id="buscador" class="buscar-input"
                 placeholder="Buscar excepción, mensaje, ruta..." oninput="aplicarFiltros()">
        </div>
      </div>

      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-card-label">ERRORES DISTINTOS</div>
          <div class="stat-card-value" id="stat-distintos">—</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-label">SIN RESOLVER</div>
          <div class="stat-card-value dorado" id="stat-pendientes">—</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-label">OCURRENCIAS TOTALES</div>
          <div class="stat-card-value" id="stat-ocurrencias">—</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-label">ÚLTIMO</div>
          <div class="stat-card-value" id="stat-ultimo" style="font-size:15px">—</div>
        </div>
      </div>

      <div class="tabla-wrap">
        <div class="tabla-header"><span id="contador">Cargando...</span></div>
        <table>
          <thead>
            <tr>
              <th>ÚLTIMA VEZ</th>
              <th>EXCEPCIÓN</th>
              <th>MENSAJE</th>
              <th>RUTA</th>
              <th style="text-align:center">VECES</th>
              <th>ESTADO</th>
              <th>ACCIONES</th>
            </tr>
          </thead>
          <tbody id="tbody">
            <tr><td colspan="7"><div class="loading-msg">Cargando...</div></td></tr>
          </tbody>
        </table>
      </div>

    </main>
  </div>
</div>

<!-- Detalle -->
<div class="modal-overlay" id="modal-detalle">
  <div class="modal-box" style="max-width:780px">
    <div class="modal-header">
      <h3 id="det-titulo">Detalle del error</h3>
      <button class="modal-close" onclick="cerrarDetalle()" aria-label="Cerrar">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-body" id="det-body"></div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="cerrarDetalle()">Cerrar</button>
    </div>
  </div>
</div>

<?php include 'layout/footer.php'; ?>

<style>
/* Los estilos de modal, tabs, buscador y filas de detalle NO estan en
   panel.css: cada pagina define los suyos. Estos son los mismos de log.php,
   para que las dos pantallas se vean iguales. */

.buscar-icon {
  position: absolute; left: 11px; top: 50%; transform: translateY(-50%);
  color: var(--gris4); pointer-events: none;
}
.buscar-input {
  background: var(--gris2); border: 1px solid var(--gris2);
  border-radius: 7px; padding: 7px 12px 7px 32px;
  font-size: 13px; color: var(--blanco);
  font-family: 'Archivo', sans-serif; outline: none;
  width: 300px; transition: border-color .2s;
}
.buscar-input:focus { border-color: var(--dorado); }

.log-tabs { display:flex; gap:6px; margin-bottom:16px; flex-wrap:wrap; }
.log-tab {
  background:transparent; border:1px solid var(--gris2); border-radius:20px;
  padding:7px 16px; font-size:12px; font-family:'Archivo',sans-serif;
  color:var(--gris4); cursor:pointer; transition:all .15s;
}
.log-tab:hover  { color:var(--blanco); border-color:var(--gris3); }
.log-tab.active { background:rgba(201,168,76,.12); border-color:rgba(201,168,76,.3); color:var(--dorado); }

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
.modal-header h3 { font-size: 14px; font-weight: 600; color: var(--blanco); }
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
  line-height: 1.6; margin-top: 4px; max-height: 320px; overflow-y: auto;
}

.accion-pill {
  display: inline-flex; align-items: center; gap: 5px;
  font-size: 11px; font-weight: 500;
  padding: 3px 9px; border-radius: 20px; white-space: nowrap;
}
.pill-pendiente { background: rgba(201,76,76,.12); color: #D46A6A; }
.pill-resuelto  { background: rgba(92,184,122,.12); color: var(--exito); }

/* Botones de accion por fila. Mismo aspecto que en manuales.php, que tampoco
   los tiene en panel.css. */
.err-btn {
  background: transparent; border: none; cursor: pointer;
  font-family: 'Archivo', sans-serif; font-size: 12px;
  padding: 4px 7px; border-radius: 5px; transition: background .15s;
}
.err-btn:hover { background: var(--gris2); }

/* Propias de esta pantalla */
.err-clase { color: var(--blanco); font-weight: 600; font-size: 12.5px; }
.err-msg   { max-width: 300px; overflow: hidden; text-overflow: ellipsis;
             white-space: nowrap; color: var(--gris5); }
.err-ruta  { font-family: monospace; font-size: 11.5px; color: var(--gris4);
             max-width: 220px; overflow: hidden; text-overflow: ellipsis;
             white-space: nowrap; }
.err-veces { text-align: center; font-weight: 700; color: var(--blanco); }
</style>

<script>
let todosLosErrores = [];
let vistaActual     = 'todos';

async function cargar() {
  try {
    const res = await apiFetch('GET', '/errores');
    todosLosErrores = res?.errores || [];

    const r = res?.resumen || {};
    document.getElementById('stat-distintos').textContent   = (r.distintos ?? 0).toLocaleString('es-AR');
    document.getElementById('stat-pendientes').textContent  = (r.sin_resolver ?? 0).toLocaleString('es-AR');
    document.getElementById('stat-ocurrencias').textContent = (r.ocurrencias ?? 0).toLocaleString('es-AR');
    document.getElementById('stat-ultimo').textContent      = r.ultimo ? formatFechaErr(r.ultimo) : '—';

    aplicarFiltros();
  } catch (e) {
    document.getElementById('tbody').innerHTML =
      `<tr><td colspan="7"><div class="empty-state">No se pudo cargar el listado de errores.</div></td></tr>`;
    document.getElementById('contador').textContent = 'Error al cargar';
  }
}

function cambiarVista(v) {
  vistaActual = v;
  ['todos', 'pendientes', 'resueltos'].forEach(x =>
    document.getElementById('tab-' + x).classList.toggle('active', x === v));
  aplicarFiltros();
}

function aplicarFiltros() {
  let lista = todosLosErrores;

  if (vistaActual === 'pendientes') lista = lista.filter(e => !Number(e.resuelto));
  if (vistaActual === 'resueltos')  lista = lista.filter(e =>  Number(e.resuelto));

  const q = document.getElementById('buscador').value.trim().toLowerCase();
  if (q) {
    lista = lista.filter(e =>
      (e.excepcion || '').toLowerCase().includes(q) ||
      (e.mensaje   || '').toLowerCase().includes(q) ||
      (e.ruta      || '').toLowerCase().includes(q));
  }

  renderTabla(lista);
}

function renderTabla(lista) {
  const tbody = document.getElementById('tbody');
  document.getElementById('contador').textContent = `${lista.length} error(es)`;

  if (!lista.length) {
    tbody.innerHTML = `<tr><td colspan="7"><div class="empty-state">Sin errores registrados.</div></td></tr>`;
    return;
  }

  tbody.innerHTML = lista.map(e => {
    // Solo el nombre corto de la clase: el namespace completo no entra en la
    // columna y no aporta. Va entero en el detalle.
    const clase    = (e.excepcion || '').split('\\').pop();
    const resuelto = Number(e.resuelto);

    return `
      <tr>
        <td style="white-space:nowrap;font-size:12px;color:var(--gris4)">${formatFechaErr(e.ultima_vez)}</td>
        <td><span class="err-clase">${esc(clase)}</span></td>
        <td><div class="err-msg" title="${esc(e.mensaje || '')}">${esc(e.mensaje || '')}</div></td>
        <td><div class="err-ruta" title="${esc(e.ruta || '')}">${esc(e.ruta || '—')}</div></td>
        <td class="err-veces">${e.ocurrencias}</td>
        <td>
          <span class="accion-pill ${resuelto ? 'pill-resuelto' : 'pill-pendiente'}">
            ${resuelto ? 'Resuelto' : 'Pendiente'}
          </span>
        </td>
        <td style="white-space:nowrap">
          <button class="err-btn" style="color:var(--gris5)" onclick="verDetalle(${e.id})">Detalle</button>
          <button class="err-btn" style="color:var(--dorado)" onclick="alternarResuelto(${e.id})">${resuelto ? 'Reabrir' : 'Resolver'}</button>
          <button class="err-btn" style="color:#D46A6A" onclick="eliminar(${e.id})">Eliminar</button>
        </td>
      </tr>`;
  }).join('');
}

function verDetalle(id) {
  const e = todosLosErrores.find(x => x.id === id);
  if (!e) return;

  const fila = (k, v) =>
    `<div class="detalle-row"><div class="detalle-label">${k}</div>
     <div class="detalle-valor">${v}</div></div>`;

  document.getElementById('det-titulo').textContent = (e.excepcion || '').split('\\').pop();
  document.getElementById('det-body').innerHTML =
      fila('Excepción',   esc(e.excepcion || ''))
    + fila('Mensaje',     esc(e.mensaje || ''))
    + fila('Archivo',     esc(e.archivo || '') + (e.linea ? `:${e.linea}` : ''))
    + fila('Petición',    `${esc(e.metodo || '—')} ${esc(e.ruta || '')}`)
    + fila('Usuario',     e.user_id ? `#${e.user_id}` : 'Sin sesión')
    + fila('IP',          esc(e.ip || '—'))
    + fila('Navegador',   esc(e.user_agent || '—'))
    + fila('Ocurrencias', `${e.ocurrencias} · primera: ${formatFechaErr(e.primera_vez)} · última: ${formatFechaErr(e.ultima_vez)}`)
    + (e.trace
        ? `<div class="detalle-label" style="margin:16px 0 4px">Traza (primeros frames)</div>
           <div class="json-block">${esc(e.trace)}</div>`
        : '');

  document.getElementById('modal-detalle').classList.add('open');
}

function cerrarDetalle() {
  document.getElementById('modal-detalle').classList.remove('open');
}

async function alternarResuelto(id) {
  try {
    const res = await apiFetch('POST', `/errores/${id}/resolver`);
    mostrarToast(res?.message || 'Actualizado.', 'exito');
    await cargar();
  } catch (e) {
    mostrarToast('No se pudo actualizar.', 'error');
  }
}

async function eliminar(id) {
  if (!confirm('¿Eliminar este registro?\n\nEs diagnóstico, no un registro de cumplimiento: se puede borrar sin problema.')) return;
  try {
    await apiFetch('DELETE', `/errores/${id}`);
    mostrarToast('Registro eliminado.', 'exito');
    await cargar();
  } catch (e) {
    mostrarToast('No se pudo eliminar.', 'error');
  }
}

function mostrarToast(msg, tipo = 'exito') {
  let t = document.getElementById('toast-errores');
  if (!t) {
    t = document.createElement('div');
    t.id = 'toast-errores';
    t.style.cssText =
      'position:fixed;bottom:24px;left:50%;transform:translateX(-50%);' +
      'padding:11px 20px;border-radius:8px;font-family:Archivo,sans-serif;' +
      'font-size:13px;z-index:900;opacity:0;transition:opacity .2s;' +
      'pointer-events:none;box-shadow:0 8px 24px rgba(0,0,0,.35)';
    document.body.appendChild(t);
  }
  t.textContent = msg;
  t.style.background = tipo === 'error' ? '#8C2F2F' : '#2F6B45';
  t.style.color      = '#F5F3EE';
  t.style.opacity    = '1';
  clearTimeout(t._to);
  t._to = setTimeout(() => { t.style.opacity = '0'; }, 2600);
}


// Nombre propio para no chocar con el formatFecha de otras pantallas.
function formatFechaErr(str) {
  if (!str) return '—';
  return new Date(str).toLocaleString('es-AR', {
    day: '2-digit', month: '2-digit', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  });
}

// footer.php carga layout.js DESPUES de este bloque: llamar a cargar() directo
// fallaria porque esc(), apiFetch() y mostrarToast() todavia no existen.
// Ver §11 del README.
document.addEventListener('DOMContentLoaded', () => cargar());
</script>