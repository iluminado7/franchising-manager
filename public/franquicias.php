<?php
require_once __DIR__ . '/layout/config.php';
require_once __DIR__ . '/layout/auth.php';
verificarSesion();
$titulo        = 'Franquicias';
$pagina_actual = 'franquicias';
include 'layout/head.php';
?>

<div class="app-layout">
  <?php include 'layout/topbar.php'; ?>
  <div class="app-body">
    <?php include 'layout/sidebar.php'; ?>
    <main class="main-content">

      <!-- ── VISTA SUPER ADMIN ─────────────────────────────── -->
      <div id="vista-super-admin" style="display:none">

        <!-- Estado 1: Selector de empresa (sin empresa seleccionada) -->
        <div id="estado-selector">
          <div class="page-header" style="margin-bottom:24px">
            <div>
              <div class="page-title">Franquicias</div>
              <div class="page-sub">Seleccioná una empresa para ver sus franquicias</div>
            </div>
          </div>

          <div style="max-width:520px">
            <div style="font-size:11px;font-weight:500;letter-spacing:.06em;text-transform:uppercase;color:var(--gris5);margin-bottom:8px">Empresa</div>
            <select id="sel-empresa" class="form-select" onchange="onSelectEmpresa()">
              <option value="">— Todas las empresas —</option>
            </select>
          </div>

          <!-- Grid de empresas clickeables -->
          <div id="grid-empresas" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:14px;margin-top:28px"></div>
        </div>

        <!-- Estado 2: Lista de franquicias de la empresa seleccionada -->
        <div id="estado-franquicias" style="display:none">
          <div class="page-header" style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px">
            <div>
              <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px">
                <button onclick="volverSelector()" style="background:transparent;border:none;cursor:pointer;color:var(--gris4);display:flex;align-items:center;gap:4px;font-size:13px;font-family:'Archivo',sans-serif;transition:color .15s;padding:0" onmouseover="this.style.color='var(--blanco)'" onmouseout="this.style.color='var(--gris4)'">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                  Empresas
                </button>
                <span style="color:var(--gris3)">·</span>
                <span id="empresa-nombre-header" style="font-size:13px;color:var(--blanco);font-weight:500"></span>
              </div>
              <div class="page-title" style="font-size:22px">Franquicias</div>
              <div class="page-sub" id="page-sub-sa">Cargando...</div>
            </div>
            <button class="btn btn-primary" id="btn-nueva-sa" onclick="abrirModal()">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
              Nueva franquicia
            </button>
          </div>

          <div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap;align-items:center">
            <button class="filtro-btn active" onclick="filtrar('todas', this)">Todas</button>
            <button class="filtro-btn" onclick="filtrar('activas', this)">Activas</button>
            <button class="filtro-btn" onclick="filtrar('inactivas', this)">Inactivas</button>
            <div style="margin-left:auto;position:relative">
              <input type="text" id="inp-buscar-sa" placeholder="Buscar franquicia..." oninput="buscar(this.value)" class="buscar-input">
              <svg style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--gris4)" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            </div>
          </div>

          <div class="tabla-wrap">
            <div class="tabla-header"><h3 id="tabla-titulo-sa">Listado</h3></div>
            <table>
              <thead>
                <tr>
                  <th>Nombre</th>
                  <th>Razón social</th>
                  <th>CUIT</th>
                  <th>Contacto</th>
                  <th>Estado</th>
                  <th>Acciones</th>
                </tr>
              </thead>
              <tbody id="tabla-body-sa">
                <tr><td colspan="6"><div class="loading-msg"><div class="spinner" style="display:block"></div>Cargando...</div></td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- ── VISTA FRANQUICIANTE ────────────────────────────── -->
      <div id="vista-franquiciante" style="display:none">
        <div class="page-header" style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px">
          <div>
            <div class="page-title">Franquicias</div>
            <div class="page-sub" id="page-sub-fq">Cargando...</div>
          </div>
          <button class="btn btn-primary" onclick="abrirModal()">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Nueva franquicia
          </button>
        </div>

        <div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap;margin-top:20px">
          <button class="filtro-btn active" onclick="filtrar('todas', this)">Todas</button>
          <button class="filtro-btn" onclick="filtrar('activas', this)">Activas</button>
          <button class="filtro-btn" onclick="filtrar('inactivas', this)">Inactivas</button>
        </div>

        <div class="tabla-wrap">
          <div class="tabla-header">
            <h3 id="tabla-titulo-fq">Listado</h3>
            <div style="position:relative">
              <input type="text" id="inp-buscar-fq" placeholder="Buscar franquicia..." oninput="buscar(this.value)" class="buscar-input">
              <svg style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--gris4)" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            </div>
          </div>
          <table>
            <thead>
              <tr>
                <th>Nombre</th>
                <th>Razón social</th>
                <th>CUIT</th>
                <th>Contacto</th>
                <th>Estado</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody id="tabla-body-fq">
              <tr><td colspan="6"><div class="loading-msg"><div class="spinner" style="display:block"></div>Cargando...</div></td></tr>
            </tbody>
          </table>
        </div>
      </div>

    </main>
  </div>
</div>

<!-- ── MODAL CREAR / EDITAR ───────────────────────────────────── -->
<div class="modal-overlay" id="modal">
  <div class="modal-box">
    <div class="modal-header">
      <h3 id="modal-titulo">Nueva franquicia</h3>
      <button class="modal-close" onclick="cerrarModal()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="form-id">
      <div class="form-row">
        <div class="form-group">
          <label>Nombre *</label>
          <input type="text" id="form-nombre" placeholder="Ej: Sucursal Palermo" maxlength="150">
        </div>
        <div class="form-group">
          <label>Razón social *</label>
          <input type="text" id="form-razon" placeholder="Ej: Cerrajería Norte SA" maxlength="200">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>CUIT *</label>
          <input type="text" id="form-cuit" placeholder="20-12345678-9" maxlength="15">
        </div>
        <div class="form-group">
          <label>Teléfono</label>
          <input type="text" id="form-telefono" placeholder="+54 11 1234-5678" maxlength="30">
        </div>
      </div>
      <div class="form-group">
        <label>Email de contacto</label>
        <input type="email" id="form-email" placeholder="contacto@franquicia.com" maxlength="200">
      </div>
      <div class="form-group">
        <label>Dirección</label>
        <input type="text" id="form-direccion" placeholder="Av. Corrientes 1234, CABA" maxlength="300">
      </div>
      <div class="form-group">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;text-transform:none;letter-spacing:0;font-size:13px;font-weight:400;color:var(--gris5)">
          <input type="checkbox" id="form-sede-central" style="width:auto;accent-color:var(--dorado);width:15px;height:15px">
          ¿Esta franquicia es la sede central?
        </label>
        <div style="font-size:11px;color:var(--gris4);margin-top:4px;font-family:'Roboto',sans-serif">Solo puede haber una sede central por empresa. Al marcarla se desmarca la anterior automáticamente.</div>
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

<!-- ── MODAL TOGGLE ───────────────────────────────────────────── -->
<div class="modal-overlay" id="modal-toggle" onclick="if(event.target===this)cerrarModalToggle()">
  <div class="modal-box" style="max-width:380px">
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

<!-- ── TOAST ─────────────────────────────────────────────────── -->
<div class="toast" id="toast"><span id="toast-icon"></span><span id="toast-msg"></span></div>

<style>
.buscar-input {
  background:var(--gris2);border:1px solid var(--gris2);border-radius:7px;
  padding:7px 12px 7px 32px;font-size:13px;color:var(--blanco);
  font-family:'Archivo',sans-serif;outline:none;width:220px;transition:border-color .2s;
}
.buscar-input:focus { border-color:var(--dorado); }

.form-select {
  width:100%;background:var(--negro);border:1px solid var(--gris2);
  border-radius:7px;padding:10px 12px;font-size:13px;
  font-family:'Archivo',sans-serif;color:var(--blanco);
  outline:none;transition:border-color .2s;
}
.form-select:focus { border-color:var(--dorado); }
.form-select option { background:var(--gris1); }

.filtro-btn {
  padding:6px 14px;border-radius:20px;border:1px solid var(--gris2);
  background:transparent;font-size:12px;font-family:'Archivo',sans-serif;
  color:var(--gris4);cursor:pointer;transition:all .15s;
}
.filtro-btn:hover  { border-color:var(--gris3);color:var(--blanco); }
.filtro-btn.active { background:rgba(201,168,76,.12);border-color:rgba(201,168,76,.3);color:var(--dorado); }

.modal-overlay { display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:500;align-items:center;justify-content:center;padding:16px; }
.modal-overlay.open { display:flex; }
.modal-box { background:var(--gris1);border:1px solid var(--gris2);border-radius:14px;width:100%;max-width:560px;overflow:hidden; }
.modal-header { padding:18px 20px;border-bottom:1px solid var(--gris2);display:flex;align-items:center;justify-content:space-between; }
.modal-header h3 { font-size:15px;font-weight:600;color:var(--blanco); }
.modal-close { background:transparent;border:none;cursor:pointer;color:var(--gris4);padding:4px;border-radius:5px;transition:color .15s,background .15s;display:flex; }
.modal-close:hover { color:var(--blanco);background:var(--gris2); }
.modal-body  { padding:20px; }
.modal-footer { padding:14px 20px;border-top:1px solid var(--gris2);display:flex;justify-content:flex-end;gap:8px; }
.form-row { display:grid;grid-template-columns:1fr 1fr;gap:14px; }
.form-group { margin-bottom:14px; }
.form-group label { display:block;font-size:11px;font-weight:500;letter-spacing:.06em;text-transform:uppercase;color:var(--gris5);margin-bottom:6px; }
.form-group input { width:100%;background:var(--negro);border:1px solid var(--gris2);border-radius:7px;padding:10px 12px;font-size:13px;font-family:'Archivo',sans-serif;color:var(--blanco);outline:none;transition:border-color .2s; }
.form-group input:focus { border-color:var(--dorado); }
.form-group input::placeholder { color:var(--gris3); }
.form-error { background:rgba(226,92,92,.1);border:1px solid rgba(226,92,92,.3);border-radius:7px;padding:10px 12px;font-size:13px;color:var(--error);display:none;margin-top:8px; }
.accion-btn { background:transparent;border:none;cursor:pointer;padding:5px 8px;border-radius:5px;font-size:12px;font-family:'Archivo',sans-serif;transition:background .15s;display:inline-flex;align-items:center;gap:4px; }
.accion-btn:hover { background:var(--gris2); }
.toast { position:fixed;bottom:24px;right:24px;background:var(--gris1);border:1px solid var(--gris2);border-radius:10px;padding:12px 16px;font-size:13px;color:var(--blanco);display:flex;align-items:center;gap:10px;transform:translateY(80px);opacity:0;transition:transform .3s,opacity .3s;z-index:600;font-family:'Roboto',sans-serif;max-width:320px; }
.toast.show { transform:translateY(0);opacity:1; }

/* Tarjetas de empresa */
.empresa-card {
  background: var(--gris1); border: 1px solid var(--gris2);
  border-radius: 10px; padding: 18px 20px; cursor: pointer;
  transition: border-color .2s, background .2s;
}
.empresa-card:hover { border-color: var(--dorado); background: rgba(201,168,76,.04); }
.empresa-card-nombre { font-size:14px;font-weight:600;color:var(--blanco);margin-bottom:4px; }
.empresa-card-sub    { font-size:12px;color:var(--gris4);font-family:'Roboto',sans-serif; }
.empresa-card-badge  { display:inline-block;font-size:10px;padding:2px 8px;border-radius:20px;margin-top:8px; }
</style>

<script>
let todasLasFranquicias = [];
let todasLasEmpresas    = [];
let filtroActual        = 'todas';
let pendingToggle       = null;
let empresaSeleccionada = null; // solo super_admin
let rolActual           = '';

// ── INIT ──────────────────────────────────────────────────────
async function init() {
  try {
    const me = await apiFetch('GET', '/me');
    rolActual = me.rol;

    if (rolActual === 'super_admin') {
      document.getElementById('vista-super-admin').style.display = 'block';
      await cargarVistaAdmin();
    } else {
      document.getElementById('vista-franquiciante').style.display = 'block';
      await cargarFranquicias();
    }

  } catch (e) {
    console.error(e);
  }
}

// ── SUPER ADMIN: cargar empresas + selector ───────────────────
async function cargarVistaAdmin() {
  try {
    const empresas = await apiFetch('GET', '/empresas');
    todasLasEmpresas = empresas;

    // Poblar select
    const sel = document.getElementById('sel-empresa');
    sel.innerHTML = '<option value="">— Todas las empresas —</option>';
    empresas.forEach(e => {
      const opt = document.createElement('option');
      opt.value       = e.id;
      opt.textContent = e.nombre;
      sel.appendChild(opt);
    });

    // Renderizar grid de tarjetas
    renderGridEmpresas(empresas);

    // Si viene con ?empresa_id en la URL, seleccionar automáticamente
    const params    = new URLSearchParams(location.search);
    const empresaId = params.get('empresa_id');
    if (empresaId) {
      sel.value = empresaId;
      await seleccionarEmpresa(parseInt(empresaId));
    }

  } catch (e) {
    document.getElementById('grid-empresas').innerHTML =
      `<div class="empty-state">Error al cargar empresas.</div>`;
  }
}

function renderGridEmpresas(lista) {
  const grid = document.getElementById('grid-empresas');

  if (!lista.length) {
    grid.innerHTML = `<div class="empty-state" style="grid-column:1/-1">Sin empresas registradas.</div>`;
    return;
  }

  grid.innerHTML = lista.map(e => `
    <div class="empresa-card" onclick="seleccionarEmpresa(${e.id})">
      <div class="empresa-card-nombre">${esc(e.nombre)}</div>
      <div class="empresa-card-sub">${esc(e.razon_social)} · ${esc(e.cuit)}</div>
      <div style="display:flex;align-items:center;justify-content:space-between;margin-top:8px">
        <span class="empresa-card-badge ${e.activa ? 'estado-completo' : 'estado-pendiente'}">
          ${e.activa ? 'Activa' : 'Suspendida'}
        </span>
        <span style="font-size:11px;color:var(--gris4);font-family:'Roboto',sans-serif">
          ${e.franquicias_count ?? 0} franquicia(s)
        </span>
      </div>
    </div>
  `).join('');
}

// Cuando cambia el select
async function onSelectEmpresa() {
  const id = parseInt(document.getElementById('sel-empresa').value);
  if (id) {
    await seleccionarEmpresa(id);
  } else {
    volverSelector();
  }
}

async function seleccionarEmpresa(id) {
  const empresa = todasLasEmpresas.find(e => e.id === id);
  if (!empresa) return;

  empresaSeleccionada = empresa;
  document.getElementById('sel-empresa').value = id;

  // Mostrar vista de franquicias
  document.getElementById('estado-selector').style.display    = 'none';
  document.getElementById('estado-franquicias').style.display = 'block';
  document.getElementById('empresa-nombre-header').textContent = empresa.nombre;

  // Actualizar URL sin recargar
  history.pushState({}, '', `franquicias.php?empresa_id=${id}`);

  await cargarFranquiciasDeEmpresa(id);
}

function volverSelector() {
  empresaSeleccionada = null;
  document.getElementById('estado-selector').style.display    = 'block';
  document.getElementById('estado-franquicias').style.display = 'none';
  document.getElementById('sel-empresa').value = '';
  history.pushState({}, '', 'franquicias.php');
}

async function cargarFranquiciasDeEmpresa(empresaId) {
  try {
    const data = await apiFetch('GET', `/franquicias?empresa_id=${empresaId}`);
    todasLasFranquicias = data;
    aplicarFiltrosSA();
    document.getElementById('page-sub-sa').textContent =
      `${data.length} franquicia(s) en ${empresaSeleccionada?.nombre}`;
  } catch (e) {
    document.getElementById('tabla-body-sa').innerHTML =
      `<tr><td colspan="6"><div class="empty-state">Error al cargar franquicias.</div></td></tr>`;
  }
}

function aplicarFiltrosSA(texto = '') {
  let lista = [...todasLasFranquicias];
  if (filtroActual === 'activas')   lista = lista.filter(f => f.activa);
  if (filtroActual === 'inactivas') lista = lista.filter(f => !f.activa);
  if (texto.trim()) {
    const q = texto.toLowerCase();
    lista = lista.filter(f =>
      f.nombre.toLowerCase().includes(q) ||
      f.razon_social.toLowerCase().includes(q) ||
      f.cuit.includes(q)
    );
  }
  renderTabla(lista, 'tabla-body-sa', 'tabla-titulo-sa');
}

// ── FRANQUICIANTE: cargar sus franquicias ─────────────────────
async function cargarFranquicias() {
  try {
    const data = await apiFetch('GET', '/franquicias');
    todasLasFranquicias = data;
    aplicarFiltrosFQ();
    document.getElementById('page-sub-fq').textContent =
      `${data.length} franquicia(s) registrada(s)`;
  } catch (e) {
    document.getElementById('tabla-body-fq').innerHTML =
      `<tr><td colspan="6"><div class="empty-state">Error al cargar franquicias.</div></td></tr>`;
  }
}

function aplicarFiltrosFQ(texto = '') {
  let lista = [...todasLasFranquicias];
  if (filtroActual === 'activas')   lista = lista.filter(f => f.activa);
  if (filtroActual === 'inactivas') lista = lista.filter(f => !f.activa);
  if (texto.trim()) {
    const q = texto.toLowerCase();
    lista = lista.filter(f =>
      f.nombre.toLowerCase().includes(q) ||
      f.razon_social.toLowerCase().includes(q) ||
      f.cuit.includes(q)
    );
  }
  renderTabla(lista, 'tabla-body-fq', 'tabla-titulo-fq');
}

// ── RENDER TABLA ──────────────────────────────────────────────
function renderTabla(lista, tbodyId, tituloId) {
  const tbody = document.getElementById(tbodyId);
  document.getElementById(tituloId).textContent = `${lista.length} resultado(s)`;

  if (!lista.length) {
    tbody.innerHTML = `<tr><td colspan="6"><div class="empty-state">Sin franquicias que mostrar.</div></td></tr>`;
    return;
  }

  tbody.innerHTML = lista.map(f => `
    <tr>
      <td>
        <div style="display:flex;align-items:center;gap:7px">
          <span style="color:var(--blanco);font-weight:500">${esc(f.nombre)}</span>
          ${f.es_sede_central ? `<span style="font-size:10px;font-weight:600;letter-spacing:.05em;text-transform:uppercase;padding:2px 7px;border-radius:20px;background:rgba(201,168,76,.15);color:var(--dorado);border:1px solid rgba(201,168,76,.3);white-space:nowrap">Sede central</span>` : ''}
        </div>
      </td>
      <td>${esc(f.razon_social)}</td>
      <td style="font-family:'Roboto',sans-serif">${esc(f.cuit)}</td>
      <td style="font-size:12px">
        ${f.email_contacto ? `<div>${esc(f.email_contacto)}</div>` : ''}
        ${f.telefono       ? `<div style="color:var(--gris4)">${esc(f.telefono)}</div>` : ''}
        ${!f.email_contacto && !f.telefono ? '—' : ''}
      </td>
      <td>
        <span class="estado-pill ${f.activa ? 'estado-completo' : 'estado-pendiente'}">
          ${f.activa ? 'Activa' : 'Inactiva'}
        </span>
      </td>
      <td>
        <div style="display:flex;gap:4px">
          <button class="accion-btn" style="color:var(--gris5)" onclick="abrirModalEditar(${f.id})">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            Editar
          </button>
          <button class="accion-btn" style="color:${f.activa ? 'var(--error)' : 'var(--exito)'}"
            onclick="abrirModalToggle(${f.id}, ${f.activa ? 'true' : 'false'})">
            ${f.activa
              ? `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg> Desactivar`
              : `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> Activar`
            }
          </button>
        </div>
      </td>
    </tr>
  `).join('');
}

// ── FILTROS ───────────────────────────────────────────────────
function filtrar(tipo, btn) {
  filtroActual = tipo;
  document.querySelectorAll('.filtro-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  buscar('');
}

function buscar(texto) {
  if (rolActual === 'super_admin') {
    aplicarFiltrosSA(texto);
  } else {
    aplicarFiltrosFQ(texto);
  }
}

// ── MODAL CREAR ───────────────────────────────────────────────
function abrirModal() {
  limpiarForm();
  document.getElementById('modal-titulo').textContent = empresaSeleccionada
    ? `Nueva franquicia — ${empresaSeleccionada.nombre}`
    : 'Nueva franquicia';
  document.getElementById('form-id').value = '';
  document.getElementById('form-sede-central').checked = false;
  document.getElementById('modal').classList.add('open');
  setTimeout(() => document.getElementById('form-nombre').focus(), 100);
}

function abrirModalEditar(id) {
  const f = todasLasFranquicias.find(x => x.id === id);
  if (!f) return;
  limpiarForm();
  document.getElementById('modal-titulo').textContent    = 'Editar franquicia';
  document.getElementById('form-id').value               = f.id;
  document.getElementById('form-nombre').value           = f.nombre;
  document.getElementById('form-razon').value            = f.razon_social;
  document.getElementById('form-cuit').value             = f.cuit;
  document.getElementById('form-telefono').value         = f.telefono       || '';
  document.getElementById('form-email').value            = f.email_contacto || '';
  document.getElementById('form-direccion').value        = f.direccion      || '';
  document.getElementById('form-sede-central').checked  = !!f.es_sede_central;
  document.getElementById('modal').classList.add('open');
}

function cerrarModal() { document.getElementById('modal').classList.remove('open'); }

function limpiarForm() {
  ['form-nombre','form-razon','form-cuit','form-telefono','form-email','form-direccion']
    .forEach(id => document.getElementById(id).value = '');
  const err = document.getElementById('form-error');
  err.style.display = 'none'; err.textContent = '';
}

// ── GUARDAR ───────────────────────────────────────────────────
async function guardar() {
  const id          = document.getElementById('form-id').value;
  const nombre      = document.getElementById('form-nombre').value.trim();
  const razon       = document.getElementById('form-razon').value.trim();
  const cuit        = document.getElementById('form-cuit').value.trim();
  const telefono    = document.getElementById('form-telefono').value.trim();
  const email       = document.getElementById('form-email').value.trim();
  const dir         = document.getElementById('form-direccion').value.trim();
  const sedeCentral = document.getElementById('form-sede-central').checked;
  const btn         = document.getElementById('btn-guardar');

  document.getElementById('form-error').style.display = 'none';
  if (!nombre) { mostrarFormError('El nombre es obligatorio.'); return; }
  if (!razon)  { mostrarFormError('La razón social es obligatoria.'); return; }
  if (!cuit)   { mostrarFormError('El CUIT es obligatorio.'); return; }

  btn.disabled = true; btn.textContent = 'Guardando...';

  try {
    const body = {
      nombre, razon_social: razon, cuit,
      telefono:        telefono    || null,
      email_contacto:  email       || null,
      direccion:       dir         || null,
      es_sede_central: sedeCentral,
    };

    // Super admin agrega empresa_id al crear
    if (!id && empresaSeleccionada) {
      body.empresa_id = empresaSeleccionada.id;
    }

    if (id) {
      await apiFetch('PUT', `/franquicias/${id}`, body);
      mostrarToast('Franquicia actualizada.', 'exito');
    } else {
      await apiFetch('POST', '/franquicias', body);
      mostrarToast('Franquicia creada.', 'exito');
    }

    cerrarModal();
    if (rolActual === 'super_admin' && empresaSeleccionada) {
      await cargarFranquiciasDeEmpresa(empresaSeleccionada.id);
    } else {
      await cargarFranquicias();
    }

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

// ── TOGGLE ────────────────────────────────────────────────────
function abrirModalToggle(id, activa) {
  pendingToggle = { id, activa };
  const f = todasLasFranquicias.find(x => x.id === id);
  document.getElementById('toggle-titulo').textContent =
    activa ? 'Desactivar franquicia' : 'Activar franquicia';
  document.getElementById('toggle-msg').textContent = activa
    ? `¿Desactivar "${f?.nombre}"? Los usuarios de esta franquicia perderán el acceso.`
    : `¿Activar "${f?.nombre}"? Los usuarios podrán volver a ingresar.`;
  const btn = document.getElementById('btn-toggle-confirmar');
  btn.className   = `btn ${activa ? 'btn-danger' : 'btn-success'}`;
  btn.textContent = activa ? 'Desactivar' : 'Activar';
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
    await apiFetch('PUT', `/franquicias/${id}`, { activa: !activa });
    mostrarToast(activa ? 'Franquicia desactivada.' : 'Franquicia activada.', activa ? 'error' : 'exito');
    cerrarModalToggle();
    if (rolActual === 'super_admin' && empresaSeleccionada) {
      await cargarFranquiciasDeEmpresa(empresaSeleccionada.id);
    } else {
      await cargarFranquicias();
    }
  } catch (e) {
    document.getElementById('toggle-error').textContent  = e.data?.message || 'Error.';
    document.getElementById('toggle-error').style.display = 'block';
    btn.disabled = false;
    btn.textContent = activa ? 'Desactivar' : 'Activar';
  }
}

// ── HELPERS ───────────────────────────────────────────────────
function mostrarFormError(msg) {
  const el = document.getElementById('form-error');
  el.textContent = msg; el.style.display = 'block';
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
  if (!str) return '—';
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

document.addEventListener('keydown', e => {
  if (e.key === 'Escape') { cerrarModal(); cerrarModalToggle(); }
  if (e.key === 'Enter' && document.getElementById('modal').classList.contains('open')) guardar();
});

document.addEventListener('DOMContentLoaded', () => init());
</script>

<?php include 'layout/footer.php'; ?>