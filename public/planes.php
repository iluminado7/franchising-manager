<?php
require_once __DIR__ . '/layout/config.php';
require_once __DIR__ . '/layout/auth.php';
verificarSesion('super_admin');
$titulo        = 'Planes';
$pagina_actual = 'planes';
include 'layout/head.php';
?>

<div class="app-layout">
  <?php include 'layout/topbar.php'; ?>
  <div class="app-body">
    <?php include 'layout/sidebar.php'; ?>
    <main class="main-content">

      <div class="page-header">
        <div>
          <div class="page-title">Planes</div>
          <div class="page-sub">Gestión de planes de suscripción y facturación por empresa</div>
        </div>
      </div>

      <!-- ══════════════════════════════════════════════
           SECCIÓN 1 — PLANES
      ══════════════════════════════════════════════ -->
      <div class="seccion-titulo">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
        Planes disponibles
      </div>

      <div id="planes-grid" class="planes-grid">
        <div class="loading-msg"><div class="spinner" style="display:block"></div>Cargando planes...</div>
      </div>

      <!-- ══════════════════════════════════════════════
           SECCIÓN 2 — FACTURACIÓN POR EMPRESA
      ══════════════════════════════════════════════ -->
      <div class="seccion-titulo" style="margin-top:36px">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
        Facturación por empresa
      </div>
      
      <div class="facturacion-header">
        <div class="select-empresa-wrap">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--gris4)"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
          <select id="sel-empresa-fact" class="filtro-select-lg" onchange="cargarFacturas()">
            <option value="">— Seleccioná una empresa —</option>
          </select>
        </div>
        <div id="facturacion-acciones" style="display:none;gap:8px;align-items:center" class="flex-row">
          <select id="sel-estado-fact" class="filtro-select" onchange="cargarFacturas()">
            <option value="">Todos los estados</option>
            <option value="pendiente">Pendiente</option>
            <option value="pagada">Pagada</option>
            <option value="vencida">Vencida</option>
          </select>
          <button class="btn btn-primary btn-sm" onclick="abrirModalGenerar()">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Generar facturas del mes
          </button>
        </div>
      </div>

      <div id="facturas-wrap">
        <div class="empty-state-soft">Seleccioná una empresa para ver su historial de facturación.</div>
      </div>

    </main>
  </div>
</div>

<!-- ══════════════════════════════════════════════════
     MODAL EDITAR PLAN
══════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modal-plan">
  <div class="modal-box">
    <div class="modal-header">
      <h3 id="modal-plan-titulo">Editar plan</h3>
      <button class="modal-close" onclick="cerrarModalPlan()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="plan-id">
      <input type="hidden" id="plan-tipo">

      <div class="form-group">
        <label>Nombre del plan *</label>
        <input type="text" id="plan-nombre" placeholder="Ej: Starter" maxlength="100">
      </div>

      <div class="form-group">
        <label>Descripción</label>
        <textarea id="plan-descripcion" placeholder="Breve descripción de cómo funciona este plan y para qué tipo de empresa está pensado..." rows="3" maxlength="500"></textarea>
        <div style="font-size:11px;color:var(--gris4);margin-top:4px;font-family:'Roboto',sans-serif">Esta descripción es visible solo para el super admin.</div>
      </div>

      <div class="form-group" id="grupo-precio-franquicia" style="display:none">
        <label>Precio base por franquicia *</label>
        <div class="input-prefix-wrap">
          <span class="input-prefix">$</span>
          <input type="number" id="plan-precio-franquicia" placeholder="0.00" min="0" step="0.01">
        </div>
        <div style="font-size:11px;color:var(--gris4);margin-top:4px;font-family:'Roboto',sans-serif">Se multiplica por la cantidad de franquicias activas al generar la factura.</div>
      </div>

      <div class="form-group" id="grupo-precio-global" style="display:none">
        <label>Precio global mensual *</label>
        <div class="input-prefix-wrap">
          <span class="input-prefix">$</span>
          <input type="number" id="plan-precio-global" placeholder="0.00" min="0" step="0.01">
        </div>
        <div style="font-size:11px;color:var(--gris4);margin-top:4px;font-family:'Roboto',sans-serif">Precio fijo mensual sin importar la cantidad de franquicias.</div>
      </div>

      <div class="form-group">
        <label>Límite de franquicias</label>
        <input type="number" id="plan-limite" placeholder="Sin límite" min="1" step="1">
        <div style="font-size:11px;color:var(--gris4);margin-top:4px;font-family:'Roboto',sans-serif">Dejá vacío para franquicias ilimitadas.</div>
      </div>

      <div class="form-error" id="plan-error" style="display:none"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="cerrarModalPlan()">Cancelar</button>
      <button class="btn btn-primary" id="btn-guardar-plan" onclick="guardarPlan()">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
        Guardar cambios
      </button>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════
     MODAL EDITAR ESTADO FACTURA
══════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modal-factura">
  <div class="modal-box" style="max-width:420px">
    <div class="modal-header">
      <h3>Actualizar estado de factura</h3>
      <button class="modal-close" onclick="cerrarModalFactura()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="factura-id">
      <div id="factura-resumen" style="background:var(--negro);border:1px solid var(--gris2);border-radius:8px;padding:12px 14px;margin-bottom:16px;font-family:'Roboto',sans-serif;font-size:13px;color:var(--gris5);line-height:1.7"></div>
      <div class="form-group">
        <label>Estado *</label>
        <select id="factura-estado" class="form-select">
          <option value="pendiente">Pendiente</option>
          <option value="pagada">Pagada</option>
          <option value="vencida">Vencida</option>
        </select>
      </div>
      <div class="form-group">
        <label>Notas</label>
        <textarea id="factura-notas" rows="2" placeholder="Observaciones opcionales..." maxlength="1000"></textarea>
      </div>
      <div class="form-error" id="factura-error" style="display:none"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="cerrarModalFactura()">Cancelar</button>
      <button class="btn btn-primary btn-sm" id="btn-guardar-factura" onclick="guardarFactura()">Guardar</button>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════
     MODAL GENERAR FACTURAS
══════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modal-generar">
  <div class="modal-box" style="max-width:400px">
    <div class="modal-header">
      <h3>Generar facturas del mes</h3>
      <button class="modal-close" onclick="cerrarModalGenerar()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-body">
      <p style="font-size:13px;color:var(--gris5);line-height:1.7;font-family:'Roboto',sans-serif">
       Esto generará facturas del período <strong id="periodo-txt" style="color:var(--blanco)"></strong> para todas las empresas activas <strong style="color:var(--blanco)">y facturables</strong> que aún no tengan factura en ese período.<br><br>
        Las empresas que ya tienen factura para este mes <strong style="color:var(--blanco)">no serán afectadas</strong>. Las empresas exentas <strong style="color:var(--blanco)">nunca se facturan</strong>.
      </p>
      <div class="form-error" id="generar-error" style="display:none"></div>
      <div class="form-exito" id="generar-exito" style="display:none"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="cerrarModalGenerar()">Cancelar</button>
      <button class="btn btn-primary btn-sm" id="btn-confirmar-generar" onclick="confirmarGenerar()">Confirmar y generar</button>
    </div>
  </div>
</div>

<!-- ── TOAST ──────────────────────────────────────────────────── -->
<div class="toast" id="toast"><span id="toast-icon"></span><span id="toast-msg"></span></div>

<style>
/* ── Sección título ───────────────────────────────────────── */
.seccion-titulo {
  display: flex; align-items: center; gap: 8px;
  font-size: 11px; font-weight: 600;
  letter-spacing: .08em; text-transform: uppercase;
  color: var(--gris4);
  margin-bottom: 16px;
  padding-bottom: 10px;
  border-bottom: 1px solid var(--gris2);
}

/* ── Grid de planes ───────────────────────────────────────── */
.planes-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 16px;
  margin-bottom: 8px;
}
.plan-card {
  background: var(--gris1);
  border: 1px solid var(--gris2);
  border-radius: 12px;
  padding: 20px;
  display: flex; flex-direction: column; gap: 12px;
  transition: border-color .2s;
}
.plan-card:hover { border-color: var(--gris3); }
.plan-card-top {
  display: flex; justify-content: space-between; align-items: flex-start;
}
.plan-card-nombre {
  font-size: 16px; font-weight: 700; color: var(--blanco);
}
.plan-tipo-badge {
  font-size: 11px; font-weight: 600; letter-spacing: .06em;
  text-transform: uppercase; padding: 3px 8px; border-radius: 20px;
  white-space: nowrap;
}
.plan-tipo-badge.por_franquicia {
  background: rgba(101,163,255,.12); color: #65a3ff;
  border: 1px solid rgba(101,163,255,.25);
}
.plan-tipo-badge.global {
  background: rgba(201,168,76,.12); color: var(--dorado);
  border: 1px solid rgba(201,168,76,.25);
}
.plan-precio {
  font-size: 26px; font-weight: 700; color: var(--blanco);
  font-family: 'Archivo', sans-serif;
}
.plan-precio span {
  font-size: 12px; font-weight: 400; color: var(--gris4); margin-left: 4px;
}
.plan-descripcion {
  font-size: 12px; color: var(--gris4);
  font-family: 'Roboto', sans-serif;
  line-height: 1.6; min-height: 36px;
}
.plan-meta {
  display: flex; gap: 12px; flex-wrap: wrap;
  padding-top: 12px; border-top: 1px solid var(--gris2);
}
.plan-meta-item {
  font-size: 11px; color: var(--gris4);
  font-family: 'Roboto', sans-serif;
  display: flex; align-items: center; gap: 5px;
}
.plan-meta-item strong { color: var(--gris5); }

/* ── Facturación header ───────────────────────────────────── */
.facturacion-header {
  display: flex; justify-content: space-between;
  align-items: center; gap: 12px;
  margin-bottom: 16px; flex-wrap: wrap;
}
.select-empresa-wrap {
  display: flex; align-items: center; gap: 8px;
}
.filtro-select-lg {
  background: var(--gris1); border: 1px solid var(--gris2);
  border-radius: 8px; color: var(--blanco);
  font-size: 13px; font-family: 'Archivo', sans-serif;
  padding: 9px 14px; outline: none; cursor: pointer;
  transition: border-color .2s; min-width: 240px;
}
.filtro-select-lg:focus { border-color: var(--dorado); }
.filtro-select-lg option { background: var(--gris1); }
.flex-row { display: flex; }

/* ── Tabla facturas ───────────────────────────────────────── */
.tabla-facturas-wrap {
  background: var(--gris1); border: 1px solid var(--gris2);
  border-radius: 12px; overflow: hidden;
}
.tabla-facturas-header {
  padding: 14px 18px; border-bottom: 1px solid var(--gris2);
  display: flex; justify-content: space-between; align-items: center;
}
.tabla-facturas-header h3 {
  font-size: 13px; font-weight: 600; color: var(--blanco);
}
.tabla-facturas { width: 100%; border-collapse: collapse; }
.tabla-facturas th {
  font-size: 11px; font-weight: 600; letter-spacing: .07em;
  text-transform: uppercase; color: var(--gris4);
  padding: 10px 16px; text-align: left;
  border-bottom: 1px solid var(--gris2);
}
.tabla-facturas td {
  padding: 12px 16px; font-size: 13px; color: var(--gris5);
  border-bottom: 1px solid var(--gris2);
  font-family: 'Roboto', sans-serif;
  vertical-align: middle;
}
.tabla-facturas tr:last-child td { border-bottom: none; }
.tabla-facturas tr:hover td { background: rgba(255,255,255,.02); }

/* ── Varios ───────────────────────────────────────────────── */
.empty-state-soft {
  padding: 32px 0; text-align: center;
  font-size: 13px; color: var(--gris4);
  font-family: 'Roboto', sans-serif;
}
.filtro-select {
  background: var(--gris2); border: 1px solid var(--gris2);
  border-radius: 7px; color: var(--gris5); font-size: 12px;
  font-family: 'Archivo', sans-serif; padding: 7px 10px;
  cursor: pointer; outline: none; transition: border-color .2s;
}
.filtro-select:focus { border-color: var(--dorado); }
.filtro-select option { background: var(--gris1); }
.input-prefix-wrap { position: relative; display: flex; align-items: center; }
.input-prefix {
  position: absolute; left: 12px;
  font-size: 13px; color: var(--gris4);
  pointer-events: none; font-family: 'Roboto', sans-serif;
}
.input-prefix-wrap input { padding-left: 26px; }

/* ── Modal reutilizado ────────────────────────────────────── */
.modal-overlay { display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:500;align-items:center;justify-content:center;padding:16px; }
.modal-overlay.open { display:flex; }
.modal-box { background:var(--gris1);border:1px solid var(--gris2);border-radius:14px;width:100%;max-width:520px;max-height:90vh;overflow-y:auto; }
.modal-header { padding:18px 20px;border-bottom:1px solid var(--gris2);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:var(--gris1);z-index:1; }
.modal-header h3 { font-size:15px;font-weight:600;color:var(--blanco); }
.modal-close { background:transparent;border:none;cursor:pointer;color:var(--gris4);padding:4px;border-radius:5px;transition:color .15s,background .15s;display:flex; }
.modal-close:hover { color:var(--blanco);background:var(--gris2); }
.modal-body { padding:20px; }
.modal-footer { padding:14px 20px;border-top:1px solid var(--gris2);display:flex;justify-content:flex-end;gap:8px;position:sticky;bottom:0;background:var(--gris1); }
.form-group { margin-bottom:14px; }
.form-group label { display:block;font-size:11px;font-weight:500;letter-spacing:.06em;text-transform:uppercase;color:var(--gris5);margin-bottom:6px; }
.form-group input[type=text],
.form-group input[type=number],
.form-group textarea,
.form-group .form-select {
  width:100%;background:var(--negro);border:1px solid var(--gris2);
  border-radius:7px;padding:10px 12px;font-size:13px;
  font-family:'Archivo',sans-serif;color:var(--blanco);
  outline:none;transition:border-color .2s;box-sizing:border-box;
}
.form-group textarea { resize:vertical; font-family:'Roboto',sans-serif; line-height:1.6; }
.form-group input:focus,
.form-group textarea:focus,
.form-group .form-select:focus { border-color:var(--dorado); }
.form-group input::placeholder,
.form-group textarea::placeholder { color:var(--gris3); }
.form-error { background:rgba(226,92,92,.1);border:1px solid rgba(226,92,92,.3);border-radius:7px;padding:9px 12px;font-size:12px;color:var(--error);margin-top:8px;line-height:1.5; }
.form-exito { background:rgba(76,175,80,.1);border:1px solid rgba(76,175,80,.3);border-radius:7px;padding:9px 12px;font-size:12px;color:var(--exito);margin-top:8px;line-height:1.5; }
.form-select { background:var(--negro)!important; color:var(--blanco)!important; }
.form-select option { background:var(--gris1); }
.btn-sm { padding:8px 14px;font-size:12px; }
.accion-btn { background:transparent;border:none;cursor:pointer;padding:5px 8px;border-radius:5px;font-size:12px;font-family:'Archivo',sans-serif;transition:background .15s;display:inline-flex;align-items:center;gap:4px;color:var(--gris5); }
.accion-btn:hover { background:var(--gris2);color:var(--blanco); }
.toast { position:fixed;bottom:24px;right:24px;background:var(--gris1);border:1px solid var(--gris2);border-radius:10px;padding:12px 16px;font-size:13px;color:var(--blanco);display:flex;align-items:center;gap:10px;transform:translateY(80px);opacity:0;transition:transform .3s,opacity .3s;z-index:600;font-family:'Roboto',sans-serif;max-width:320px; }
.toast.show { transform:translateY(0);opacity:1; }
</style>

<script>
let todosLosPlanes  = [];
let todasLasEmpresas = [];
let todasLasFacturas = [];
let planEditando    = null;
let facturaEditando = null;

// ── INIT ──────────────────────────────────────────────────────
async function init() {
  try {
    const [planes, empresas] = await Promise.all([
      apiFetch('GET', '/planes'),
      apiFetch('GET', '/empresas'),
    ]);
    todosLosPlanes   = planes;
    todasLasEmpresas = empresas;

    renderPlanes(planes);
    poblarSelectEmpresas(empresas);

    // Período del mes actual para el modal generar
    const ahora = new Date();
    const mes   = ahora.toLocaleString('es-AR', { month: 'long', year: 'numeric' });
    document.getElementById('periodo-txt').textContent = mes;

  } catch (e) {
    document.getElementById('planes-grid').innerHTML =
      '<div class="empty-state">Error al cargar los planes.</div>';
  }
}

// ── PLANES ────────────────────────────────────────────────────
function renderPlanes(planes) {
  const grid = document.getElementById('planes-grid');
  if (!planes.length) {
    grid.innerHTML = '<div class="empty-state">No hay planes configurados.</div>';
    return;
  }

  grid.innerHTML = planes.map(p => {
    const esPorFranquicia = p.tipo_plan === 'por_franquicia';
    const precio = esPorFranquicia ? p.precio_base_por_franquicia : p.precio_global;
    const precioFmt = precio ? `$${Number(precio).toLocaleString('es-AR')}` : '—';
    const sufijo  = esPorFranquicia ? '/ franquicia' : '/ mes';
    const desc    = p.descripcion || textoDefaultPlan(p);

    return `<div class="plan-card">
      <div class="plan-card-top">
        <div class="plan-card-nombre">${esc(p.nombre)}</div>
        <span class="plan-tipo-badge ${p.tipo_plan}">${esPorFranquicia ? 'Por franquicia' : 'Global'}</span>
      </div>
      <div class="plan-precio">${precioFmt}<span>${sufijo}</span></div>
      <div class="plan-descripcion">${esc(desc)}</div>
      <div class="plan-meta">
        <div class="plan-meta-item">
          <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
          <strong>${p.empresas_count ?? 0}</strong> empresa(s) usando este plan
        </div>
        ${p.limite_franquicias
          ? `<div class="plan-meta-item">
               <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
               Límite: <strong>${p.limite_franquicias} franquicias</strong>
             </div>`
          : `<div class="plan-meta-item">Sin límite de franquicias</div>`
        }
      </div>
      <button class="btn btn-ghost btn-sm" style="width:100%;justify-content:center;margin-top:4px" onclick="abrirModalPlan(${p.id})">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        Editar plan
      </button>
    </div>`;
  }).join('');
}

function textoDefaultPlan(p) {
  if (p.tipo_plan === 'por_franquicia') {
    return `Se cobra $${Number(p.precio_base_por_franquicia || 0).toLocaleString('es-AR')} por cada franquicia activa al momento de facturar. El total varía según la cantidad de sucursales habilitadas.`;
  }
  return `Se cobra un precio fijo de $${Number(p.precio_global || 0).toLocaleString('es-AR')} sin importar la cantidad de franquicias activas.`;
}

// ── MODAL EDITAR PLAN ─────────────────────────────────────────
function abrirModalPlan(id) {
  const p = todosLosPlanes.find(x => x.id === id);
  if (!p) return;
  planEditando = p;

  document.getElementById('plan-id').value          = p.id;
  document.getElementById('plan-tipo').value         = p.tipo_plan;
  document.getElementById('plan-nombre').value       = p.nombre;
  document.getElementById('plan-descripcion').value  = p.descripcion || '';
  document.getElementById('plan-limite').value       = p.limite_franquicias || '';
  document.getElementById('plan-error').style.display = 'none';

  const esPorFranquicia = p.tipo_plan === 'por_franquicia';
  document.getElementById('grupo-precio-franquicia').style.display = esPorFranquicia ? 'block' : 'none';
  document.getElementById('grupo-precio-global').style.display     = esPorFranquicia ? 'none' : 'block';

  if (esPorFranquicia) {
    document.getElementById('plan-precio-franquicia').value = p.precio_base_por_franquicia || '';
  } else {
    document.getElementById('plan-precio-global').value = p.precio_global || '';
  }

  document.getElementById('modal-plan-titulo').textContent = `Editar plan — ${p.nombre}`;
  document.getElementById('modal-plan').classList.add('open');
  setTimeout(() => document.getElementById('plan-nombre').focus(), 100);
}

function cerrarModalPlan() {
  document.getElementById('modal-plan').classList.remove('open');
  planEditando = null;
}

async function guardarPlan() {
  const id          = document.getElementById('plan-id').value;
  const tipo        = document.getElementById('plan-tipo').value;
  const nombre      = document.getElementById('plan-nombre').value.trim();
  const descripcion = document.getElementById('plan-descripcion').value.trim();
  const limite      = document.getElementById('plan-limite').value;
  const errEl       = document.getElementById('plan-error');
  const btn         = document.getElementById('btn-guardar-plan');

  errEl.style.display = 'none';

  if (!nombre) { errEl.textContent = 'El nombre es obligatorio.'; errEl.style.display = 'block'; return; }

  const body = { nombre, descripcion: descripcion || null, limite_franquicias: limite ? parseInt(limite) : null };

  if (tipo === 'por_franquicia') {
    const precio = document.getElementById('plan-precio-franquicia').value;
    if (!precio) { errEl.textContent = 'El precio por franquicia es obligatorio.'; errEl.style.display = 'block'; return; }
    body.precio_base_por_franquicia = parseFloat(precio);
  } else {
    const precio = document.getElementById('plan-precio-global').value;
    if (!precio) { errEl.textContent = 'El precio global es obligatorio.'; errEl.style.display = 'block'; return; }
    body.precio_global = parseFloat(precio);
  }

  btn.disabled = true; btn.textContent = 'Guardando...';

  try {
    const planActualizado = await apiFetch('PUT', `/planes/${id}`, body);
    // Actualizar en memoria
    const idx = todosLosPlanes.findIndex(x => x.id === parseInt(id));
    if (idx !== -1) todosLosPlanes[idx] = { ...todosLosPlanes[idx], ...planActualizado };
    renderPlanes(todosLosPlanes);
    cerrarModalPlan();
    mostrarToast('Plan actualizado correctamente.', 'exito');
  } catch (e) {
    const msg = e.data?.errors
      ? Object.values(e.data.errors).flat().join(' ')
      : e.data?.message || 'Error al guardar.';
    errEl.textContent = msg; errEl.style.display = 'block';
  } finally {
    btn.disabled = false;
    btn.innerHTML = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg> Guardar cambios`;
  }
}

// ── FACTURACIÓN ───────────────────────────────────────────────
function poblarSelectEmpresas(empresas) {
  const sel = document.getElementById('sel-empresa-fact');
  empresas.forEach(e => {
    const opt = document.createElement('option');
    opt.value = e.id;

    // Empresa exenta (facturable = false): nunca tiene facturas. La mostramos
    // deshabilitada en vez de esconderla, para que el super_admin vea que existe
    // y entienda por qué no aparece en el historial.
    if (e.facturable === false) {
      opt.textContent = `${e.nombre} (exenta — no se factura)`;
      opt.disabled    = true;
      opt.style.color = '#888';
    } else {
      opt.textContent = `${e.nombre}${e.activa ? '' : ' (suspendida)'}`;
    }

    sel.appendChild(opt);
  });
}

async function cargarFacturas() {
  const empresaId = document.getElementById('sel-empresa-fact').value;
  const estado    = document.getElementById('sel-estado-fact').value;
  const wrap      = document.getElementById('facturas-wrap');
  const acciones  = document.getElementById('facturacion-acciones');

  if (!empresaId) {
    acciones.style.display = 'none';
    wrap.innerHTML = '<div class="empty-state-soft">Seleccioná una empresa para ver su historial de facturación.</div>';
    return;
  }

  acciones.style.display = 'flex';
  wrap.innerHTML = '<div class="loading-msg"><div class="spinner" style="display:block"></div>Cargando facturas...</div>';

  try {
    let url = `/invoices?empresa_id=${empresaId}`;
    if (estado) url += `&estado=${estado}`;
    const facturas = await apiFetch('GET', url);
    todasLasFacturas = facturas;
    renderTablaFacturas(facturas, empresaId);
  } catch (e) {
    wrap.innerHTML = '<div class="empty-state-soft">Error al cargar las facturas.</div>';
  }
}

function renderTablaFacturas(facturas, empresaId) {
  const empresa = todasLasEmpresas.find(e => String(e.id) === String(empresaId));
  const wrap    = document.getElementById('facturas-wrap');

  if (!facturas.length) {
    wrap.innerHTML = `<div class="empty-state-soft">No hay facturas registradas para <strong>${esc(empresa?.nombre || '')}</strong> con los filtros seleccionados.</div>`;
    return;
  }

  const estadoBadge = (estado) => {
    const map = {
      pagada:   ['estado-completo',  'Pagada'],
      pendiente:['estado-pendiente', 'Pendiente'],
      vencida:  ['estado-vencido',   'Vencida'],
      anulada:  ['estado-archivado', 'Anulada'],
    };
    const [cls, txt] = map[estado] || ['estado-pendiente', estado];
    return `<span class="estado-pill ${cls}">${txt}</span>`;
  };

  // Totales
  const totalPagado   = facturas.filter(f => f.estado === 'pagada').reduce((s, f) => s + Number(f.total), 0);
  const totalPendiente = facturas.filter(f => f.estado === 'pendiente').reduce((s, f) => s + Number(f.total), 0);

  wrap.innerHTML = `
    <div class="tabla-facturas-wrap">
      <div class="tabla-facturas-header">
        <h3>${esc(empresa?.nombre || '')} — ${facturas.length} factura(s)</h3>
        <div style="display:flex;gap:16px;font-size:12px;font-family:'Roboto',sans-serif">
          <span style="color:var(--exito)">Pagado: $${totalPagado.toLocaleString('es-AR')}</span>
          <span style="color:var(--amarillo,#f0c040)">Pendiente: $${totalPendiente.toLocaleString('es-AR')}</span>
        </div>
      </div>
      <div style="overflow-x:auto">
        <table class="tabla-facturas">
          <thead>
            <tr>
              <th>Nº Factura</th>
              <th>Período</th>
              <th>Plan</th>
              <th>Franq. activas</th>
              <th>Total</th>
              <th>Estado</th>
              <th>Acción</th>
            </tr>
          </thead>
          <tbody>
            ${facturas.map(f => `<tr>
              <td style="color:var(--blanco);font-family:'Archivo',sans-serif;font-size:12px">${esc(f.numero_factura || '—')}</td>
              <td>${esc(f.periodo || '—')}</td>
              <td>${esc(f.plan?.nombre || '—')}</td>
              <td style="text-align:center">${f.franquicias_activas ?? '—'}</td>
              <td style="font-weight:600;color:var(--blanco)">$${Number(f.total || 0).toLocaleString('es-AR')}</td>
              <td>${estadoBadge(f.estado)}</td>
              <td>
                <button class="accion-btn" onclick="abrirModalFactura(${f.id})">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                  Estado
                </button>
              </td>
            </tr>`).join('')}
          </tbody>
        </table>
      </div>
    </div>`;
}

// ── MODAL FACTURA ─────────────────────────────────────────────
function abrirModalFactura(id) {
  const f = todasLasFacturas.find(x => x.id === id);
  if (!f) return;
  facturaEditando = f;

  document.getElementById('factura-id').value     = f.id;
  document.getElementById('factura-estado').value = f.estado;
  document.getElementById('factura-notas').value  = f.notas || '';
  document.getElementById('factura-error').style.display = 'none';

  document.getElementById('factura-resumen').innerHTML = `
    <strong style="color:var(--blanco)">${esc(f.numero_factura)}</strong><br>
    Período: ${esc(f.periodo)} &nbsp;·&nbsp; Total: <strong style="color:var(--blanco)">$${Number(f.total).toLocaleString('es-AR')}</strong>
  `;

  document.getElementById('modal-factura').classList.add('open');
}

function cerrarModalFactura() {
  document.getElementById('modal-factura').classList.remove('open');
  facturaEditando = null;
}

async function guardarFactura() {
  const id     = document.getElementById('factura-id').value;
  const estado = document.getElementById('factura-estado').value;
  const notas  = document.getElementById('factura-notas').value.trim();
  const errEl  = document.getElementById('factura-error');
  const btn    = document.getElementById('btn-guardar-factura');

  errEl.style.display = 'none';
  btn.disabled = true; btn.textContent = 'Guardando...';

  try {
    await apiFetch('PUT', `/invoices/${id}`, { estado, notas: notas || null });
    mostrarToast('Estado de factura actualizado.', 'exito');
    cerrarModalFactura();
    cargarFacturas(); // recargar tabla
  } catch (e) {
    errEl.textContent = e.data?.message || 'Error al actualizar.';
    errEl.style.display = 'block';
  } finally {
    btn.disabled = false; btn.textContent = 'Guardar';
  }
}

// ── MODAL GENERAR ─────────────────────────────────────────────
function abrirModalGenerar() {
  document.getElementById('generar-error').style.display = 'none';
  document.getElementById('generar-exito').style.display = 'none';
  document.getElementById('btn-confirmar-generar').disabled = false;
  document.getElementById('btn-confirmar-generar').textContent = 'Confirmar y generar';
  document.getElementById('modal-generar').classList.add('open');
}

function cerrarModalGenerar() {
  document.getElementById('modal-generar').classList.remove('open');
}

async function confirmarGenerar() {
  const errEl   = document.getElementById('generar-error');
  const exitoEl = document.getElementById('generar-exito');
  const btn     = document.getElementById('btn-confirmar-generar');

  errEl.style.display = 'none';
  exitoEl.style.display = 'none';
  btn.disabled = true; btn.textContent = 'Generando...';

  try {
    const res = await apiFetch('POST', '/invoices/generar');
    exitoEl.textContent = res.message;
    exitoEl.style.display = 'block';
    btn.textContent = 'Listo';

    if (res.errores?.length) {
      errEl.textContent = 'Errores: ' + res.errores.join('; ');
      errEl.style.display = 'block';
    }

    // Recargar facturas si hay empresa seleccionada
    if (document.getElementById('sel-empresa-fact').value) {
      setTimeout(cargarFacturas, 800);
    }
  } catch (e) {
    errEl.textContent = e.data?.message || 'Error al generar las facturas.';
    errEl.style.display = 'block';
    btn.disabled = false; btn.textContent = 'Confirmar y generar';
  }
}

// ── HELPERS ───────────────────────────────────────────────────
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
  if (e.key === 'Escape') {
    cerrarModalPlan();
    cerrarModalFactura();
    cerrarModalGenerar();
  }
});

init();
</script>

<?php include 'layout/footer.php'; ?>