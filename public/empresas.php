<?php
require_once __DIR__ . '/layout/config.php';
require_once __DIR__ . '/layout/auth.php';
verificarSesion('super_admin');
$titulo        = 'Empresas';
$pagina_actual = 'empresas';
include 'layout/head.php';
?>

<div class="app-layout">
  <?php include 'layout/topbar.php'; ?>
  <div class="app-body">
    <?php include 'layout/sidebar.php'; ?>
    <main class="main-content">

      <div class="page-header" style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px">
        <div>
          <div class="page-title">Empresas</div>
          <div class="page-sub" id="page-sub">Cargando...</div>
        </div>
        <button class="btn btn-primary" onclick="abrirModal()">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Nueva empresa
        </button>
      </div>

      <!-- Filtros -->
      <div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap;align-items:center">
        <button class="filtro-btn active" onclick="filtrar('todas', this)">Todas</button>
        <button class="filtro-btn" onclick="filtrar('activas', this)">Activas</button>
        <button class="filtro-btn" onclick="filtrar('inactivas', this)">Inactivas</button>
        <button class="filtro-btn" id="btn-mostrar-elim" onclick="toggleMostrarEliminadas(this)"
                style="margin-left:auto">Mostrar eliminadas</button>
        <div style="margin-left:auto;position:relative">
          <input type="text" id="inp-buscar" placeholder="Buscar empresa..." oninput="filtrarOpcionesEmpresa()" class="buscar-input">
          <svg style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--gris4)" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        </div>
      </div>

      <!-- Tabla -->
      <div class="tabla-wrap">
        <div class="tabla-header"><h3 id="tabla-titulo">Listado</h3></div>
        <table>
          <thead>
            <tr>
              <th>Empresa</th>
              <th>CUIT</th>
              <th>Plan / Precio</th>
              <th>Franquicias</th>
              <th>Emails</th>
              <th>Estado</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody id="tabla-body">
            <tr><td colspan="7"><div class="loading-msg"><div class="spinner" style="display:block"></div>Cargando...</div></td></tr>
          </tbody>
        </table>
      </div>

    </main>
  </div>
</div>

<!-- ══════════════════════════════════════════════════
     MODAL CREAR / EDITAR EMPRESA
══════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modal">
  <div class="modal-box" style="max-width:620px;max-height:90vh;overflow-y:auto">

    <div class="modal-header" style="position:sticky;top:0;background:var(--gris1);z-index:1">
      <h3 id="modal-titulo">Nueva empresa</h3>
      <button class="modal-close" onclick="cerrarModal()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>

    <div class="modal-body">
      <input type="hidden" id="form-id">

      <!-- Sección: Datos básicos -->
      <div class="seccion-titulo">Datos de la empresa</div>

      <div class="form-row">
        <div class="form-group">
          <label>Nombre comercial *</label>
          <input type="text" id="form-nombre" placeholder="Ej: Cerrajería Leonardo" maxlength="200">
        </div>
        <div class="form-group">
          <label>Razón social *</label>
          <input type="text" id="form-razon" placeholder="Ej: Leonardo SA" maxlength="200">
        </div>
      </div>

      <div class="form-group" style="max-width:260px">
        <label>CUIT *</label>
        <input type="text" id="form-cuit" placeholder="30-12345678-9" maxlength="15">
      </div>
      <div id="wrap-ultimo-periodo" style="display:none;margin-bottom:14px">
      <div class="form-group" style="margin-bottom:0">
        <label>Último período facturado</label>
        <div style="background:var(--negro);border:1px solid var(--gris2);border-radius:7px;padding:10px 12px;font-size:13px;color:var(--gris4);font-family:'Roboto',sans-serif">
          <span id="ultimo-periodo">—</span>
        </div>
      </div>
    </div>
      <!-- Sección: Plan y precios -->
      <div class="seccion-titulo" style="margin-top:8px">Plan y facturación</div>

      <div class="form-group">
        <label>Plan *</label>
        <select id="form-plan" class="form-select" onchange="onChangePlan()">
          <option value="">Seleccioná un plan</option>
        </select>
      </div>

      <!-- Descripción del plan seleccionado -->
      <div id="plan-desc" style="display:none;margin-bottom:14px;background:rgba(201,168,76,.06);border:1px solid rgba(201,168,76,.2);border-radius:8px;padding:12px 14px;font-size:12px;font-family:'Roboto',sans-serif;line-height:1.6;color:var(--gris5)"></div>

      <!-- Precio custom según tipo de plan -->
      <div id="wrap-precio-franquicia" style="display:none">
        <div class="form-group">
          <label>Precio custom por franquicia <span style="color:var(--gris4);font-weight:400;text-transform:none;font-size:10px">(opcional — sobreescribe el precio del plan)</span></label>
          <div style="position:relative">
            <span style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--gris4);font-size:13px">$</span>
            <input type="number" id="form-precio-franquicia" placeholder="0.00" min="0" step="0.01" style="padding-left:24px">
          </div>
        </div>
      </div>

      <div id="wrap-precio-global" style="display:none">
        <div class="form-group">
          <label>Precio custom global <span style="color:var(--gris4);font-weight:400;text-transform:none;font-size:10px">(opcional — sobreescribe el precio del plan)</span></label>
          <div style="position:relative">
            <span style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--gris4);font-size:13px">$</span>
            <input type="number" id="form-precio-global" placeholder="0.00" min="0" step="0.01" style="padding-left:24px">
          </div>
        </div>
      </div>

      <!-- Simulador de precio -->
      <div id="simulador" style="display:none;margin-bottom:14px;background:var(--negro);border:1px solid var(--gris2);border-radius:8px;padding:12px 14px">
        <div style="font-size:11px;font-weight:500;letter-spacing:.06em;text-transform:uppercase;color:var(--gris4);margin-bottom:10px">Simulador de facturación</div>
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px">
          <label style="font-size:12px;color:var(--gris5);white-space:nowrap">Cantidad de franquicias:</label>
          <input type="number" id="sim-cantidad" value="1" min="0" step="1" oninput="simular()"
            style="width:80px;background:var(--gris2);border:1px solid var(--gris2);border-radius:6px;padding:6px 10px;font-size:13px;color:var(--blanco);outline:none;font-family:'Archivo',sans-serif">
        </div>
        <div style="font-size:14px;color:var(--blanco)">
          Total estimado: <strong id="sim-total" style="color:var(--dorado);font-size:16px">—</strong>
        </div>
        <div id="sim-detalle" style="font-size:11px;color:var(--gris4);margin-top:4px;font-family:'Roboto',sans-serif"></div>
      </div>

      <!-- Sección: Emails -->
      <div class="seccion-titulo" style="margin-top:8px">
        Emails de contacto
        <button type="button" onclick="agregarEmailRow()" style="margin-left:auto;background:transparent;border:1px solid var(--gris2);border-radius:6px;padding:3px 10px;font-size:11px;color:var(--gris4);cursor:pointer;font-family:'Archivo',sans-serif;transition:all .15s" onmouseover="this.style.borderColor='var(--dorado)';this.style.color='var(--dorado)'" onmouseout="this.style.borderColor='var(--gris2)';this.style.color='var(--gris4)'">
          + Agregar email
        </button>
      </div>

      <div id="emails-lista" style="display:flex;flex-direction:column;gap:8px;margin-bottom:14px">
        <!-- Rows de emails se generan dinámicamente -->
      </div>

      <div class="form-error" id="form-error"></div>
    </div>

    <div class="modal-footer" style="position:sticky;bottom:0;background:var(--gris1)">
      <button class="btn btn-ghost" onclick="cerrarModal()">Cancelar</button>
      <button class="btn btn-primary" id="btn-guardar" onclick="guardar()">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
        Guardar
      </button>
    </div>

  </div>
</div>

<!-- ── MODAL SUSPENDER / ACTIVAR ─────────────────────────────── -->
<div class="modal-overlay" id="modal-toggle" onclick="if(event.target===this)cerrarModalToggle()">
  <div class="modal-box" style="max-width:400px">
    <div class="modal-header">
      <h3 id="toggle-titulo">Confirmar acción</h3>
      <button class="modal-close" onclick="cerrarModalToggle()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-body">
      <p id="toggle-msg" style="font-size:14px;color:var(--gris5);line-height:1.6;font-family:'Roboto',sans-serif"></p>
      <div class="form-error" id="toggle-error"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="cerrarModalToggle()">Cancelar</button>
      <button class="btn" id="btn-toggle-confirmar" onclick="confirmarToggle()">Confirmar</button>
    </div>
  </div>
</div>
<!-- MODAL CONFIRMAR CAMBIOS   -->
<div class="modal-overlay" id="modal-confirmar-guardar" onclick="if(event.target===this)cerrarConfirmar()">
  <div class="modal-box" style="max-width:380px">
    <div class="modal-header">
      <h3>Confirmar cambios</h3>
      <button class="modal-close" onclick="cerrarConfirmar()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-body">
      <p style="font-size:14px;color:var(--gris5);line-height:1.6;font-family:'Roboto',sans-serif">
        ¿Estás seguro de guardar los cambios en esta empresa? Esta acción modificará los datos de facturación y contacto.
      </p>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="cerrarConfirmar()">Cancelar</button>
      <button class="btn btn-primary" onclick="ejecutarGuardar()">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        Sí, guardar
      </button>
    </div>
  </div>
</div>

<!-- ── TOAST ─────────────────────────────────────────────────── -->
<div class="toast" id="toast"><span id="toast-icon"></span><span id="toast-msg"></span></div>

<style>
.buscar-input {
  background: var(--gris2); border: 1px solid var(--gris2);
  border-radius: 7px; padding: 7px 12px 7px 32px;
  font-size: 13px; color: var(--blanco);
  font-family: 'Archivo', sans-serif; outline: none;
  width: 220px; transition: border-color .2s;
}
.buscar-input:focus { border-color: var(--dorado); }

.filtro-btn {
  padding: 6px 14px; border-radius: 20px;
  border: 1px solid var(--gris2); background: transparent;
  font-size: 12px; font-family: 'Archivo', sans-serif;
  color: var(--gris4); cursor: pointer; transition: all .15s;
}
.filtro-btn:hover  { border-color: var(--gris3); color: var(--blanco); }
.filtro-btn.active { background: rgba(201,168,76,.12); border-color: rgba(201,168,76,.3); color: var(--dorado); }

.modal-overlay { display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:500;align-items:center;justify-content:center;padding:16px; }
.modal-overlay.open { display:flex; }
.modal-box { background:var(--gris1);border:1px solid var(--gris2);border-radius:14px;width:100%; }
.modal-header { padding:18px 20px;border-bottom:1px solid var(--gris2);display:flex;align-items:center;justify-content:space-between; }
.modal-header h3 { font-size:15px;font-weight:600;color:var(--blanco); }
.modal-close { background:transparent;border:none;cursor:pointer;color:var(--gris4);padding:4px;border-radius:5px;transition:color .15s,background .15s;display:flex; }
.modal-close:hover { color:var(--blanco);background:var(--gris2); }
.modal-body  { padding:20px; }
.modal-footer { padding:14px 20px;border-top:1px solid var(--gris2);display:flex;justify-content:flex-end;gap:8px; }

.seccion-titulo {
  font-size: 11px; font-weight: 600; letter-spacing: .08em;
  text-transform: uppercase; color: var(--dorado);
  margin-bottom: 14px; padding-bottom: 6px;
  border-bottom: 1px solid rgba(201,168,76,.2);
  display: flex; align-items: center;
}

.form-row { display:grid;grid-template-columns:1fr 1fr;gap:14px; }
.form-group { margin-bottom:14px; }
.form-group label {
  display:block;font-size:11px;font-weight:500;
  letter-spacing:.06em;text-transform:uppercase;
  color:var(--gris5);margin-bottom:6px;
}
.form-group input[type=text],
.form-group input[type=email],
.form-group input[type=number],
.form-select {
  width:100%;background:var(--negro);border:1px solid var(--gris2);
  border-radius:7px;padding:10px 12px;font-size:13px;
  font-family:'Archivo',sans-serif;color:var(--blanco);
  outline:none;transition:border-color .2s;
}
.form-group input:focus, .form-select:focus { border-color:var(--dorado); }
.form-group input::placeholder { color:var(--gris3); }
.form-select option { background:var(--gris1); }

.form-error {
  background:rgba(226,92,92,.1);border:1px solid rgba(226,92,92,.3);
  border-radius:7px;padding:10px 12px;font-size:13px;
  color:var(--error);display:none;margin-top:8px;
}

/* Email row */
.email-row {
  display: grid;
  grid-template-columns: 1fr 130px 100px auto;
  gap: 8px;
  align-items: center;
}
.email-row input, .email-row select {
  background: var(--negro); border: 1px solid var(--gris2);
  border-radius: 7px; padding: 8px 10px; font-size: 13px;
  font-family: 'Archivo', sans-serif; color: var(--blanco);
  outline: none; width: 100%; transition: border-color .2s;
}
.email-row input:focus, .email-row select:focus { border-color: var(--dorado); }
.email-row select option { background: var(--gris1); }
.email-row .btn-remove {
  background: transparent; border: none; cursor: pointer;
  color: var(--gris4); padding: 6px; border-radius: 5px;
  display: flex; transition: color .15s;
}
.email-row .btn-remove:hover { color: var(--error); }

.accion-btn {
  background:transparent;border:none;cursor:pointer;
  padding:5px 8px;border-radius:5px;font-size:12px;
  font-family:'Archivo',sans-serif;
  transition:background .15s;display:inline-flex;align-items:center;gap:4px;
}
.accion-btn:hover { background:var(--gris2); }

.toast {
  position:fixed;bottom:24px;right:24px;
  background:var(--gris1);border:1px solid var(--gris2);
  border-radius:10px;padding:12px 16px;font-size:13px;
  color:var(--blanco);display:flex;align-items:center;gap:10px;
  transform:translateY(80px);opacity:0;
  transition:transform .3s,opacity .3s;
  z-index:600;font-family:'Roboto',sans-serif;max-width:320px;
}
.toast.show { transform:translateY(0);opacity:1; }
</style>

<script>
let todasLasEmpresas = [];
let todosLosPlanes   = [];
// Si venimos con ?estado=activas o ?estado=inactivas en la URL (ej: desde el
// dashboard), arrancamos con ese filtro aplicado. Valores no reconocidos caen
// al default 'todas'.
const _paramEstado = new URLSearchParams(window.location.search).get('estado');
let filtroActual   = ['todas', 'activas', 'inactivas'].includes(_paramEstado) ? _paramEstado : 'todas';
let mostrarEliminadas = false;   // toggle "Mostrar eliminadas"
let pendingToggle    = null;
let emailsOriginales = []; // emails guardados de la empresa en edición

// ── CARGAR ────────────────────────────────────────────────────
async function cargarDatos() {
  try {
    const [empresas, planes] = await Promise.all([
      apiFetch('GET', '/empresas' + (mostrarEliminadas ? '?include_deleted=1' : '')),
      apiFetch('GET', '/planes'),
    ]);

    todasLasEmpresas = empresas;
    todosLosPlanes   = planes;

    poblarSelectPlanes();

    // Si arrancamos con un filtro no-default (por query param), sincronizamos el
    // botón activo del tab y aplicamos el filtro sobre la lista cargada.
    if (filtroActual !== 'todas') {
      document.querySelectorAll('.filtro-btn').forEach(b => {
        b.classList.remove('active');
        // El onclick tiene la forma: filtrar('activas', this) — matcheamos por
        // el nombre del filtro que va como primer argumento.
        if (b.getAttribute('onclick')?.includes(`'${filtroActual}'`)) {
          b.classList.add('active');
        }
      });
      aplicarFiltros();
    } else {
      renderTabla(empresas);
    }

    document.getElementById('page-sub').textContent =
      `${empresas.length} empresa(s) registrada(s)`;

  } catch (e) {
    document.getElementById('tabla-body').innerHTML =
      `<tr><td colspan="7"><div class="empty-state">Error al cargar empresas.</div></td></tr>`;
  }
}

function poblarSelectPlanes() {
  const sel = document.getElementById('form-plan');
  sel.innerHTML = '<option value="">Seleccioná un plan</option>';
  todosLosPlanes.filter(p => p.activo).forEach(p => {
    const opt = document.createElement('option');
    opt.value            = p.id;
    opt.dataset.tipo     = p.tipo_plan;
    opt.dataset.precioF  = p.precio_base_por_franquicia || '';
    opt.dataset.precioG  = p.precio_global || '';
    opt.dataset.limite   = p.limite_franquicias || '';
    opt.textContent      = p.nombre;
    sel.appendChild(opt);
  });
}

// ── RENDER TABLA ──────────────────────────────────────────────
function renderTabla(lista) {
  const tbody = document.getElementById('tabla-body');
  document.getElementById('tabla-titulo').textContent = `${lista.length} resultado(s)`;

  if (!lista.length) {
    tbody.innerHTML = `<tr><td colspan="7"><div class="empty-state">Sin empresas que mostrar.</div></td></tr>`;
    return;
  }

  tbody.innerHTML = lista.map(e => {
    const planNombre = e.plan?.nombre || '—';
    const tipo       = e.plan?.tipo_plan;
    let precioHtml   = '—';

    if (tipo === 'por_franquicia') {
      const base   = parseFloat(e.plan?.precio_base_por_franquicia || 0);
      const custom = e.precio_custom_por_franquicia ? parseFloat(e.precio_custom_por_franquicia) : null;
      const p      = custom ?? base;
      precioHtml   = `$${p.toLocaleString('es-AR')} / franquicia`;
      if (custom) precioHtml += ` <span style="font-size:10px;color:var(--dorado)">(custom)</span>`;
    } else if (tipo === 'global') {
      const base   = parseFloat(e.plan?.precio_global || 0);
      const custom = e.precio_custom_global ? parseFloat(e.precio_custom_global) : null;
      const p      = custom ?? base;
      precioHtml   = `$${p.toLocaleString('es-AR')} fijo`;
      if (custom) precioHtml += ` <span style="font-size:10px;color:var(--dorado)">(custom)</span>`;
    }

    const activas = e.franquicias_activas_count ?? '—';
    const total   = e.franquicias_count ?? '—';

    const emailsContacto    = (e.emails || []).filter(m => m.tipo === 'contacto');
    const emailsFacturacion = (e.emails || []).filter(m => m.tipo === 'facturacion');
    let emailsHtml = '—';
    if (e.emails?.length) {
      emailsHtml = '';
      if (emailsContacto.length) {
        emailsHtml += emailsContacto.slice(0,2).map(m =>
          `<div style="font-size:12px">${esc(m.email)}</div>`
        ).join('');
      }
      if (emailsFacturacion.length) {
        emailsHtml += emailsFacturacion.slice(0,1).map(m =>
          `<div style="font-size:11px;color:var(--gris4)">💰 ${esc(m.email)}</div>`
        ).join('');
      }
      const resto = e.emails.length - 3;
      if (resto > 0) emailsHtml += `<div style="font-size:10px;color:var(--gris4)">+${resto} más</div>`;
    }

    const eliminada = !!e.deleted_at;

    return `<tr style="${eliminada ? 'opacity:.55' : ''}">
      <td>
        <div style="font-weight:500;color:var(--blanco)">${esc(e.nombre)}${eliminada ? ' <span class="estado-pill estado-pendiente" style="margin-left:6px">Dada de baja</span>' : ''}</div>
        <div style="font-size:11px;color:var(--gris4);font-family:'Roboto',sans-serif">${esc(e.razon_social)}</div>
      </td>
      <td style="font-family:'Roboto',sans-serif;font-size:12px">${esc(e.cuit)}</td>
      <td>
        <div style="font-size:13px;font-weight:500">${esc(planNombre)}</div>
        <div style="font-size:12px;color:var(--gris4);font-family:'Roboto',sans-serif">${precioHtml}</div>
      </td>
      <td style="font-family:'Roboto',sans-serif;font-size:13px;text-align:center">
        <span style="color:var(--blanco);font-weight:500">${activas}</span>
        <span style="color:var(--gris4)"> / ${total}</span>
      </td>
      <td style="font-size:12px">${emailsHtml}</td>
      <td>
        <span class="estado-pill ${e.activa ? 'estado-completo' : 'estado-pendiente'}">
          ${e.activa ? 'Activa' : 'Suspendida'}
        </span>
      </td>
      <td>
        <div style="display:flex;gap:4px;flex-wrap:wrap">
          ${eliminada ? `
          <button class="accion-btn" style="color:var(--exito)" onclick="restaurarEmpresa(${e.id})">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
            Restaurar
          </button>` : `
          <button class="accion-btn" style="color:var(--dorado)"
            onclick="window.location.href='franquicias.php?empresa_id=${e.id}'">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
            Ver franquicias
          </button>
          <button class="accion-btn" style="color:var(--gris5)" onclick="abrirModalEditar(${e.id})">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            Editar
          </button>
          <button class="accion-btn" style="color:${e.activa ? 'var(--error)' : 'var(--exito)'}"
            onclick="abrirModalToggle(${e.id}, ${e.activa ? 'true' : 'false'})">
            ${e.activa
              ? `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg> Suspender`
              : `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> Activar`
            }
          </button>
          <button class="accion-btn" style="color:var(--error)" onclick="abrirBajaEmpresa(${e.id}, '${esc(e.nombre).replace(/'/g, "\\'")}')">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
            Dar de baja
          </button>`}
        </div>
      </td>
    </tr>`;
  }).join('');
}

// ── FILTROS ───────────────────────────────────────────────────
function filtrar(tipo, btn) {
  filtroActual = tipo;
  document.querySelectorAll('.filtro-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  aplicarFiltros();
}

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

  // Solo mostramos empresas activas en el autocomplete. todasLasEmpresas sigue
  // conteniendo también las suspendidas para lookups por ID.
  const coincidencias = todasLasEmpresas.filter(e => e.activa && e.nombre.toLowerCase().includes(texto));

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


function aplicarFiltros(texto = document.getElementById('inp-buscar').value) {
  let lista = [...todasLasEmpresas];
  // Los filtros activas/inactivas aplican SOLO a empresas vivas. Una empresa dada
  // de baja no es "activa" ni "inactiva": es otra categoria. Si el usuario filtra
  // por activas, no queremos que aparezcan las eliminadas.
  if (filtroActual === 'activas')   lista = lista.filter(e => e.activa && !e.deleted_at);
  if (filtroActual === 'inactivas') lista = lista.filter(e => !e.activa && !e.deleted_at);
  if (texto.trim()) {
    const q = texto.toLowerCase();
    lista = lista.filter(e =>
      e.nombre.toLowerCase().includes(q) ||
      e.razon_social.toLowerCase().includes(q) ||
      e.cuit.includes(q)
    );
  }
  renderTabla(lista);
}

// ── MODAL ─────────────────────────────────────────────────────
function abrirModal() {
  limpiarForm();
  emailsOriginales = [];
  document.getElementById('modal-titulo').textContent = 'Nueva empresa';
  document.getElementById('form-id').value = '';
  agregarEmailRow(); // una fila vacía por defecto
  document.getElementById('modal').classList.add('open');
  setTimeout(() => document.getElementById('form-nombre').focus(), 100);
  document.getElementById('form-nombre').disabled = false;
    document.getElementById('form-nombre').style.opacity = '';
    document.getElementById('form-nombre').style.cursor  = '';
  document.getElementById('wrap-ultimo-periodo').style.display = 'none';
}

async function abrirModalEditar(id) {
  const e = todasLasEmpresas.find(x => x.id === id);
  if (!e) return;

  limpiarForm();
  document.getElementById('modal-titulo').textContent   = 'Editar empresa';
  document.getElementById('form-id').value              = e.id;
  document.getElementById('form-nombre').value          = e.nombre;
  document.getElementById('form-nombre').disabled = true;
    document.getElementById('form-nombre').style.opacity = '0.5';
    document.getElementById('form-nombre').style.cursor  = 'not-allowed';
  document.getElementById('form-razon').value           = e.razon_social;
  document.getElementById('form-cuit').value            = e.cuit;
  document.getElementById('form-plan').value            = e.plan_id || '';
  document.getElementById('form-precio-franquicia').value = e.precio_custom_por_franquicia || '';
  document.getElementById('form-precio-global').value   = e.precio_custom_global || '';
  document.getElementById('wrap-ultimo-periodo').style.display = 'block';
  document.getElementById('ultimo-periodo').textContent = 'Cargando...';
  onChangePlan();

  // Cargar emails existentes
  try {
    const emails = await apiFetch('GET', `/empresas/${id}/emails`);
    emailsOriginales = emails;
    emails.forEach(m => agregarEmailRow(m.email, m.tipo, m.principal, m.id));
    if (!emails.length) agregarEmailRow();
  } catch (_) {
    agregarEmailRow();
  }

  // Cargar última factura
  try {
    const invoices = await apiFetch('GET', '/invoices?empresa_id=' + id);
    const ultima   = invoices.sort((a, b) => new Date(b.periodo) - new Date(a.periodo))[0];
    const el       = document.getElementById('ultimo-periodo');
    if (ultima) {
      const fecha = new Date(ultima.periodo);
      el.textContent = fecha.toLocaleDateString('es-AR', { month: 'long', year: 'numeric' });
    } else {
      el.textContent = 'Sin facturas aún';
    }
  } catch (_) {
    document.getElementById('ultimo-periodo').textContent = '—';
  }
  document.getElementById('modal').classList.add('open');
}

function cerrarModal() {
  document.getElementById('modal').classList.remove('open');
}

function limpiarForm() {
  document.getElementById('form-nombre').value            = '';
  document.getElementById('form-razon').value             = '';
  document.getElementById('form-cuit').value              = '';
  document.getElementById('form-plan').value              = '';
  document.getElementById('form-precio-franquicia').value = '';
  document.getElementById('form-precio-global').value     = '';
  document.getElementById('form-error').style.display    = 'none';
  document.getElementById('emails-lista').innerHTML       = '';
  document.getElementById('plan-desc').style.display     = 'none';
  document.getElementById('wrap-precio-franquicia').style.display = 'none';
  document.getElementById('wrap-precio-global').style.display     = 'none';
  document.getElementById('simulador').style.display     = 'none';
}

// ── PLAN: mostrar campos y simulador ──────────────────────────
function onChangePlan() {
  const sel  = document.getElementById('form-plan');
  const opt  = sel.options[sel.selectedIndex];
  const tipo = opt?.dataset.tipo || '';
  const pF   = parseFloat(opt?.dataset.precioF || 0);
  const pG   = parseFloat(opt?.dataset.precioG || 0);
  const lim  = opt?.dataset.limite;

  const descEl = document.getElementById('plan-desc');
  const wrapF  = document.getElementById('wrap-precio-franquicia');
  const wrapG  = document.getElementById('wrap-precio-global');
  const simEl  = document.getElementById('simulador');

  if (!tipo) {
    descEl.style.display = wrapF.style.display = wrapG.style.display = simEl.style.display = 'none';
    return;
  }

  // Descripción del plan
  let desc = '';
  if (tipo === 'por_franquicia') {
    desc = `<strong>Por franquicia</strong> — Se cobra $${pF.toLocaleString('es-AR')} por cada franquicia activa al momento de facturación.`;
    if (lim) desc += ` Límite: ${lim} franquicias.`;
    wrapF.style.display = 'block';
    wrapG.style.display = 'none';
    simEl.style.display = 'block';
    simular();
  } else {
    desc = `<strong>Global</strong> — Se cobra un precio fijo de $${pG.toLocaleString('es-AR')} sin importar la cantidad de franquicias activas.`;
    wrapG.style.display = 'block';
    wrapF.style.display = 'none';
    simEl.style.display = 'none';
  }
  descEl.innerHTML     = desc;
  descEl.style.display = 'block';
}

function simular() {
  const sel      = document.getElementById('form-plan');
  const opt      = sel.options[sel.selectedIndex];
  const tipo     = opt?.dataset.tipo || '';
  if (tipo !== 'por_franquicia') return;

  const pBase    = parseFloat(opt?.dataset.precioF || 0);
  const pCustom  = parseFloat(document.getElementById('form-precio-franquicia').value || 0);
  const precio   = pCustom > 0 ? pCustom : pBase;
  const cantidad = parseInt(document.getElementById('sim-cantidad').value || 0);
  const total    = precio * cantidad;

  document.getElementById('sim-total').textContent   = `$${total.toLocaleString('es-AR', {minimumFractionDigits:2})}`;
  document.getElementById('sim-detalle').textContent =
    `${cantidad} franquicia(s) × $${precio.toLocaleString('es-AR')} c/u${pCustom > 0 ? ' (precio custom)' : ' (precio base del plan)'}`;
}

// ── EMAILS ────────────────────────────────────────────────────
function agregarEmailRow(email = '', tipo = 'contacto', principal = false, emailId = null) {
  const lista = document.getElementById('emails-lista');
  const div   = document.createElement('div');
  div.className = 'email-row';
  div.dataset.emailId = emailId || '';

  div.innerHTML = `
    <input type="email" placeholder="correo@empresa.com" value="${esc(email)}" class="email-input">
    <select class="email-tipo">
      <option value="contacto"    ${tipo === 'contacto'    ? 'selected' : ''}>Contacto</option>
      <option value="facturacion" ${tipo === 'facturacion' ? 'selected' : ''}>Facturación</option>
    </select>
    <label style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--gris4);cursor:pointer;white-space:nowrap">
      <input type="checkbox" class="email-principal" ${principal ? 'checked' : ''}
        style="accent-color:var(--dorado);width:14px;height:14px">
      Legal
    </label>
    <button type="button" class="btn-remove" onclick="this.closest('.email-row').remove()" title="Eliminar">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>`;

  lista.appendChild(div);
}

function obtenerEmailsForm() {
  return [...document.querySelectorAll('.email-row')].map(row => ({
    id:        row.dataset.emailId || null,
    email:     row.querySelector('.email-input').value.trim(),
    tipo:      row.querySelector('.email-tipo').value,
    principal: row.querySelector('.email-principal').checked,
  })).filter(m => m.email);
}

// ── GUARDAR ───────────────────────────────────────────────────
// guardar() ahora solo valida y abre confirmación si es edición
async function guardar() {
  const id     = document.getElementById('form-id').value;
  const nombre = document.getElementById('form-nombre').value.trim();
  const razon  = document.getElementById('form-razon').value.trim();
  const cuit   = document.getElementById('form-cuit').value.trim();
  const planId = document.getElementById('form-plan').value;

  document.getElementById('form-error').style.display = 'none';

  if (!nombre) { mostrarFormError('El nombre es obligatorio.'); return; }
  if (!razon)  { mostrarFormError('La razón social es obligatoria.'); return; }
  if (!cuit)   { mostrarFormError('El CUIT es obligatorio.'); return; }
  if (!planId) { mostrarFormError('El plan es obligatorio.'); return; }

  // Si es edición → confirmar, si es nuevo → guardar directo
  if (id) {
    document.getElementById('modal-confirmar-guardar').classList.add('open');
  } else {
    await ejecutarGuardar();
  }
}

function cerrarConfirmar() {
  document.getElementById('modal-confirmar-guardar').classList.remove('open');
}

// ejecutarGuardar() hace el trabajo real
async function ejecutarGuardar() {
  cerrarConfirmar();

  const id      = document.getElementById('form-id').value;
  const nombre  = document.getElementById('form-nombre').value.trim();
  const razon   = document.getElementById('form-razon').value.trim();
  const cuit    = document.getElementById('form-cuit').value.trim();
  const planId  = document.getElementById('form-plan').value;
  const precioF = document.getElementById('form-precio-franquicia').value;
  const precioG = document.getElementById('form-precio-global').value;
  const emails  = obtenerEmailsForm();
  const btn     = document.getElementById('btn-guardar');

  btn.disabled    = true;
  btn.textContent = 'Guardando...';

  try {
    const body = {
      nombre,
      razon_social:                 razon,
      cuit,
      plan_id:                      parseInt(planId),
      precio_custom_por_franquicia: precioF ? parseFloat(precioF) : null,
      precio_custom_global:         precioG ? parseFloat(precioG) : null,
    };

    let empresaId = id;

    if (id) {
      await apiFetch('PUT', `/empresas/${id}`, body);
    } else {
      const nueva = await apiFetch('POST', '/empresas', body);
      empresaId = nueva.id;
    }

    await sincronizarEmails(empresaId, emails);

    mostrarToast(id ? 'Empresa actualizada.' : 'Empresa creada.', 'exito');
    cerrarModal();
    await cargarDatos();

  } catch (e) {
    const msg = e.data?.errors
      ? Object.values(e.data.errors).flat().join(' ')
      : e.data?.message || 'Error al guardar.';
    mostrarFormError(msg);
  } finally {
    btn.disabled  = false;
    btn.innerHTML = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg> Guardar`;
  }
}

async function sincronizarEmails(empresaId, emailsForm) {
  const idsOriginales = emailsOriginales.map(m => m.id);
  const idsForm       = emailsForm.filter(m => m.id).map(m => parseInt(m.id));

  // Eliminar los que ya no están
  for (const id of idsOriginales) {
    if (!idsForm.includes(id)) {
      await apiFetch('DELETE', `/empresas/${empresaId}/emails/${id}`).catch(() => {});
    }
  }

  // Crear o actualizar
  for (const m of emailsForm) {
    if (!m.email) continue;
    if (m.id) {
      await apiFetch('PUT', `/empresas/${empresaId}/emails/${m.id}`, {
        email: m.email, tipo: m.tipo, principal: m.principal,
      }).catch(() => {});
    } else {
      await apiFetch('POST', `/empresas/${empresaId}/emails`, {
        email: m.email, tipo: m.tipo, principal: m.principal,
      }).catch(() => {});
    }
  }
}

// ── TOGGLE ────────────────────────────────────────────────────
function abrirModalToggle(id, activa) {
  pendingToggle = { id, activa };
  const e = todasLasEmpresas.find(x => x.id === id);
  document.getElementById('toggle-titulo').textContent = activa ? 'Suspender empresa' : 'Activar empresa';
  document.getElementById('toggle-msg').innerHTML      = activa
    ? `¿Suspender <strong>${esc(e?.nombre)}</strong>? Se revocarán todos los tokens de sus usuarios. Los datos se conservan.`
    : `¿Activar <strong>${esc(e?.nombre)}</strong>? Sus usuarios podrán volver a ingresar.`;
  const btn = document.getElementById('btn-toggle-confirmar');
  btn.className   = `btn ${activa ? 'btn-danger' : 'btn-success'}`;
  btn.textContent = activa ? 'Suspender' : 'Activar';
  document.getElementById('toggle-error').style.display = 'none';
  document.getElementById('modal-toggle').classList.add('open');
}

function cerrarModalToggle() {
  document.getElementById('modal-toggle').classList.remove('open');
  pendingToggle = null;
}

async function confirmarToggle() {
  if (!pendingToggle) return;
  const { id, activa } = pendingToggle;
  const btn = document.getElementById('btn-toggle-confirmar');
  btn.disabled = true; btn.textContent = 'Procesando...';
  try {
    await apiFetch('PUT', `/empresas/${id}`, { activa: !activa });
    mostrarToast(activa ? 'Empresa suspendida.' : 'Empresa activada.', activa ? 'error' : 'exito');
    cerrarModalToggle();
    await cargarDatos();
  } catch (e) {
    document.getElementById('toggle-error').textContent  = e.data?.message || 'Error.';
    document.getElementById('toggle-error').style.display = 'block';
    btn.disabled = false;
    btn.textContent = activa ? 'Suspender' : 'Activar';
  }
}

// ── SOFT-DELETE ───────────────────────────────────────────────

async function toggleMostrarEliminadas(btn) {
  mostrarEliminadas = !mostrarEliminadas;
  btn.classList.toggle('active', mostrarEliminadas);
  btn.textContent = mostrarEliminadas ? 'Ocultar eliminadas' : 'Mostrar eliminadas';
  await cargarDatos();
}

// Dar de baja: reusa el modal-toggle genérico, configurándolo como acción de baja.
let pendingBaja = null;
function abrirBajaEmpresa(id, nombre) {
  pendingBaja = id;
  document.getElementById('toggle-titulo').textContent = 'Dar de baja la empresa';
  document.getElementById('toggle-msg').innerHTML =
    `Vas a dar de baja <strong>${nombre}</strong>. Esto también da de baja sus franquicias y ` +
    `cierra la sesión de todos sus usuarios. No se borra nada: podés restaurarla después ` +
    `desde "Mostrar eliminadas".`;
  const b = document.getElementById('btn-toggle-confirmar');
  b.textContent = 'Dar de baja';
  b.className = 'btn';
  b.style.background = 'var(--error)';
  b.style.color = '#fff';
  b.onclick = confirmarBaja;   // se reasigna; confirmarToggle usa su propio onclick del HTML
  document.getElementById('toggle-error').style.display = 'none';
  document.getElementById('modal-toggle').classList.add('open');
}

async function confirmarBaja() {
  if (!pendingBaja) return;
  const b = document.getElementById('btn-toggle-confirmar');
  b.disabled = true; b.textContent = 'Procesando...';
  try {
    await apiFetch('DELETE', `/empresas/${pendingBaja}`);
    mostrarToast('Empresa dada de baja.', 'error');
    cerrarModalToggle();
    b.onclick = confirmarToggle;   // restaurar el handler original del modal
    b.style.background = ''; b.style.color = '';
    pendingBaja = null;
    await cargarDatos();
  } catch (e) {
    document.getElementById('toggle-error').textContent  = e.data?.message || 'Error.';
    document.getElementById('toggle-error').style.display = 'block';
    b.disabled = false; b.textContent = 'Dar de baja';
  }
}

async function restaurarEmpresa(id) {
  try {
    const r = await apiFetch('POST', `/empresas/${id}/restore`);
    mostrarToast(r.message || 'Empresa restaurada.', 'exito');
    await cargarDatos();
  } catch (e) {
    mostrarToast(e.data?.message || 'No se pudo restaurar.', 'error');
  }
}

// ── HELPERS ───────────────────────────────────────────────────
function mostrarFormError(msg) {
  const el = document.getElementById('form-error');
  el.textContent   = msg;
  el.style.display = 'block';
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
  if (!str) return '';
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

document.addEventListener('keydown', e => {
  if (e.key === 'Escape') { cerrarModal(); cerrarModalToggle(); }
});

// Simulador se actualiza en tiempo real al cambiar precio custom
document.addEventListener('input', e => {
  if (e.target.id === 'form-precio-franquicia' || e.target.id === 'sim-cantidad') simular();
});

document.addEventListener('DOMContentLoaded', () => cargarDatos());
</script>

<?php include 'layout/footer.php'; ?>