<?php
require_once __DIR__ . '/layout/config.php';
require_once __DIR__ . '/layout/auth.php';
verificarSesion(); // super_admin y franquiciante — restricciones por rol en JS
$titulo        = 'Usuarios';
$pagina_actual = 'usuarios';
include 'layout/head.php';
?>

<div class="app-layout">
  <?php include 'layout/topbar.php'; ?>
  <div class="app-body">
    <?php include 'layout/sidebar.php'; ?>
    <main class="main-content">

      <div class="page-header" style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px">
        <div>
          <div class="page-title">Usuarios</div>
          <div class="page-sub" id="page-sub">Cargando...</div>
        </div>
        <button class="btn btn-primary" onclick="abrirModalCrear()">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Nuevo usuario
        </button>
      </div>

      <!-- Filtros -->
      <div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap;align-items:center">
        <button class="filtro-btn active" onclick="filtrarRol('todos', this)">Todos</button>
        <button class="filtro-btn" onclick="filtrarRol('franquiciante', this)">Franquiciantes</button>
        <button class="filtro-btn" onclick="filtrarRol('franquiciado', this)">Franquiciados</button>
        <button class="filtro-btn" onclick="filtrarRol('empleado', this)">Empleados</button>
        <div class="filtro-sep"></div>
        <select id="sel-empresa" onchange="aplicarFiltros()" class="filtro-select" style="display:none">
          <option value="">Todas las empresas</option>
        </select>
        <select id="sel-franquicia" onchange="aplicarFiltros()" class="filtro-select">
          <option value="">Todas las franquicias</option>
        </select>
        <button id="btn-mostrar-eliminados" class="filtro-btn" style="display:none;margin-left:auto" onclick="toggleMostrarEliminados(this)">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:4px"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          Mostrar eliminados
        </button>
      </div>

      <!-- Tabla -->
      <div class="tabla-wrap">
        <div class="tabla-header">
          <h3 id="tabla-titulo">Listado</h3>
          
        </div>
        <table>
          <thead>
            <tr>
              <th>Nombre</th>
              <th>Email</th>
              <th>Rol</th>
              <th>Empresa / Franquicia</th>
              <th>DNI</th>
              <th>Estado</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody id="tabla-body">
            <tr><td colspan="7"><div class="loading-msg"><div class="spinner" style="display:block"></div>Cargando usuarios...</div></td></tr>
          </tbody>
        </table>
      </div>

    </main>
  </div>
</div>

<!-- ══════════════════════════════════════════════════
     MODAL CREAR / EDITAR USUARIO
══════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modal">
  <div class="modal-box">

    <div class="modal-header">
      <h3 id="modal-titulo">Nuevo usuario</h3>
      <button class="modal-close" onclick="cerrarModal()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>

    <div class="modal-body">
      <input type="hidden" id="form-id">
      <input type="hidden" id="form-rol-original"> <!-- rol guardado al abrir edición -->

      <div class="form-row">
        <div class="form-group">
          <label>Nombre *</label>
          <input type="text" id="form-nombre" placeholder="Juan" maxlength="100">
        </div>
        <div class="form-group">
          <label>Apellido *</label>
          <input type="text" id="form-apellido" placeholder="Pérez" maxlength="100">
        </div>
      </div>

      <div class="form-group">
        <label>Email *</label>
        <input type="email" id="form-email" placeholder="juan@empresa.com" maxlength="200">
      </div>

      <!-- Contraseña — oculta en edición de super_admin -->
      <div class="form-group" id="grupo-password">
        <label id="label-password">Contraseña *</label>
        <div style="position:relative">
          <input type="password" id="form-password" placeholder="Mínimo 8 caracteres" maxlength="100" style="padding-right:44px">
          <button type="button" onclick="togglePassModal()" style="position:absolute;right:0;top:0;height:100%;width:40px;background:transparent;border:none;cursor:pointer;color:var(--gris4);display:flex;align-items:center;justify-content:center;transition:color .2s" onmouseover="this.style.color='var(--blanco)'" onmouseout="this.style.color='var(--gris4)'">
            <svg id="modal-eye" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
        </div>
        <div id="hint-password" style="font-size:11px;color:var(--gris4);margin-top:4px;font-family:'Archivo Narrow',sans-serif"></div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Rol *</label>
          <select id="form-rol" onchange="onRolChange()" class="form-select">
            <option value="">Seleccioná un rol</option>
            <!-- opciones se inyectan en init() según el rol del usuario logueado -->
          </select>
        </div>
        <div class="form-group">
          <label>DNI</label>
          <input type="text" id="form-dni" placeholder="12345678" maxlength="15">
        </div>
      </div>

      <!-- Empresa — solo para franquiciante -->
      <div class="form-group" id="grupo-empresa" style="display:none">
        <label>Empresa *</label>
        <select id="form-empresa" class="form-select" onchange="onEmpresaChange()">
          <option value="">Seleccioná una empresa</option>
        </select>
        <div id="hint-empresa" style="font-size:11px;color:var(--gris4);margin-top:4px;font-family:'Archivo Narrow',sans-serif"></div>
      </div>

      <!-- Franquicia — solo para franquiciado / empleado -->
      <div class="form-group" id="grupo-franquicia" style="display:none">
        <label>Franquicia *</label>
        <select id="form-franquicia" class="form-select">
          <option value="">Seleccioná una franquicia</option>
        </select>
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

<!-- ── MODAL MANUALES EMPLEADO ───────────────────────────────── -->
<div class="modal-overlay" id="modal-manuales">
  <div class="modal-box" style="max-width:580px">
    <div class="modal-header">
      <div>
        <h3 id="manuales-titulo">Manuales asignados</h3>
        <div id="manuales-subtitulo" style="font-size:12px;color:var(--gris4);margin-top:2px;font-family:'Archivo Narrow',sans-serif"></div>
      </div>
      <button class="modal-close" onclick="cerrarModalManuales()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-body">

      <!-- Asignar nuevo manual -->
      <div style="background:var(--negro);border:1px solid var(--gris2);border-radius:9px;padding:14px;margin-bottom:16px">
        <div style="font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:var(--gris4);margin-bottom:10px">Asignar manual</div>
        <div style="display:flex;gap:8px;align-items:flex-start">
          <select id="sel-manual-asignar" class="form-select" style="flex:1">
            <option value="">Seleccioná un manual publicado</option>
          </select>
          <button class="btn btn-primary btn-sm" onclick="asignarManual()" style="white-space:nowrap;flex-shrink:0">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Asignar
          </button>
        </div>
        <div class="form-error" id="asignar-error" style="display:none;margin-top:8px"></div>
      </div>

      <!-- Lista de manuales ya asignados -->
      <div style="font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:var(--gris4);margin-bottom:10px">Manuales asignados</div>
      <div id="lista-manuales-asignados">
        <div class="loading-msg"><div class="spinner" style="display:block"></div>Cargando...</div>
      </div>

    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="cerrarModalManuales()">Cerrar</button>
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
      <p id="toggle-msg" style="font-size:14px;color:var(--gris5);line-height:1.6;font-family:'Archivo Narrow',sans-serif"></p>
      <div class="form-error" id="toggle-error"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="cerrarModalToggle()">Cancelar</button>
      <button class="btn" id="btn-toggle-confirmar" onclick="confirmarToggle()">Confirmar</button>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════
     MODAL ELIMINAR USUARIO
     ══════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modal-eliminar" onclick="if(event.target===this)cerrarModalEliminar()">
  <div class="modal-box" style="max-width:380px">
    <div class="modal-header">
      <h3>Eliminar usuario</h3>
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
.filtro-sep { width:1px;height:20px;background:var(--gris2);margin:0 4px; }

.filtro-select, .form-select {
  background:var(--gris2);border:1px solid var(--gris2);
  border-radius:7px;color:var(--gris5);font-size:12px;
  font-family:'Archivo',sans-serif;padding:7px 10px;
  cursor:pointer;outline:none;transition:border-color .2s;
}
.filtro-select:focus, .form-select:focus { border-color:var(--dorado); }
.form-select { width:100%;font-size:13px;padding:10px 12px;background:var(--negro);color:var(--blanco); }
.form-select option { background:var(--gris1); }
.form-select:disabled { opacity:.5;cursor:not-allowed; }

.buscar-input {
  background:var(--gris2);border:1px solid var(--gris2);border-radius:7px;
  padding:7px 12px 7px 32px;font-size:13px;color:var(--blanco);
  font-family:'Archivo',sans-serif;outline:none;width:220px;transition:border-color .2s;
}
.buscar-input:focus { border-color:var(--dorado); }

.modal-overlay { display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:500;align-items:center;justify-content:center;padding:16px; }
.modal-overlay.open { display:flex; }
.modal-box { background:var(--gris1);border:1px solid var(--gris2);border-radius:14px;width:100%;max-width:560px;max-height:90vh;overflow-y:auto; }
.modal-header { padding:18px 20px;border-bottom:1px solid var(--gris2);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:var(--gris1);z-index:1; }
.modal-header h3 { font-size:15px;font-weight:600;color:var(--blanco); }
.modal-close { background:transparent;border:none;cursor:pointer;color:var(--gris4);padding:4px;border-radius:5px;transition:color .15s,background .15s;display:flex; }
.modal-close:hover { color:var(--blanco);background:var(--gris2); }
.modal-body { padding:20px; }
.modal-footer { padding:14px 20px;border-top:1px solid var(--gris2);display:flex;justify-content:flex-end;gap:8px;position:sticky;bottom:0;background:var(--gris1); }
.form-row { display:grid;grid-template-columns:1fr 1fr;gap:14px; }
.form-group { margin-bottom:14px; }
.form-group label { display:block;font-size:11px;font-weight:500;letter-spacing:.06em;text-transform:uppercase;color:var(--gris5);margin-bottom:6px; }
.form-group input[type=text],
.form-group input[type=email],
.form-group input[type=password] {
  width:100%;background:var(--negro);border:1px solid var(--gris2);
  border-radius:7px;padding:10px 12px;font-size:13px;
  font-family:'Archivo',sans-serif;color:var(--blanco);
  outline:none;transition:border-color .2s;
}
.form-group input:focus { border-color:var(--dorado); }
.form-group input::placeholder { color:var(--gris3); }
.form-group input:disabled { opacity:.5;cursor:not-allowed; }
.form-error { background:rgba(226,92,92,.1);border:1px solid rgba(226,92,92,.3);border-radius:7px;padding:10px 12px;font-size:13px;color:var(--error);display:none;margin-top:8px;line-height:1.5; }
.accion-btn { background:transparent;border:none;cursor:pointer;padding:5px 8px;border-radius:5px;font-size:12px;font-family:'Archivo',sans-serif;transition:background .15s;display:inline-flex;align-items:center;gap:4px; }
.accion-btn:hover { background:var(--gris2); }
.btn-sm { padding:8px 14px;font-size:12px; }
.toast { position:fixed;bottom:24px;right:24px;background:var(--gris1);border:1px solid var(--gris2);border-radius:10px;padding:12px 16px;font-size:13px;color:var(--blanco);display:flex;align-items:center;gap:10px;transform:translateY(80px);opacity:0;transition:transform .3s,opacity .3s;z-index:600;font-family:'Archivo Narrow',sans-serif;max-width:320px; }
.toast.show { transform:translateY(0);opacity:1; }
</style>

<script>
let todosLosManuales    = [];
let empleadoSeleccionado = null; // { id, nombre }

let todosLosUsuarios    = [];
let todasLasEmpresas    = [];
let todasLasFranquicias = [];
let filtroRolActual     = 'todos';
let pendingToggle       = null;
let modoEdicion         = false;
let rolEditando         = null; // rol del usuario que se está editando
let miRol               = localStorage.getItem('cl_rol') || '';
let miEmpresaId         = null; // se completa en init() para franquiciante
let pendingEliminar     = null;
let mostrarEliminados   = false;

// ── INIT ──────────────────────────────────────────────────────
async function init() {
  try {
    // Obtener datos del usuario logueado para saber empresa_id (franquiciante)
    const yo = await apiFetch('GET', '/me');
    miRol       = yo.rol;
    miEmpresaId = yo.empresa_id || null;

    // Opciones de rol disponibles según quien está logueado
    const selRolForm = document.getElementById('form-rol');
    if (miRol === 'super_admin') {
      selRolForm.innerHTML = `
        <option value="">Seleccioná un rol</option>
        <option value="super_admin">Super Admin</option>
        <option value="franquiciante">Franquiciante</option>
        <option value="franquiciado">Franquiciado</option>
        <option value="empleado">Empleado</option>`;
    } else if (miRol === 'franquiciante') {
      // franquiciante: solo puede crear franquiciado y empleado
      selRolForm.innerHTML = `
        <option value="">Seleccioná un rol</option>
        <option value="franquiciado">Franquiciado</option>
        <option value="empleado">Empleado</option>`;
    } else {
      // franquiciado: solo puede crear empleados
      selRolForm.innerHTML = `<option value="empleado">Empleado</option>`;
    }

    // Cargar datos según rol (el franquiciado no tiene acceso a /franquicias ni /empresas)
    let usuarios = [], empresas = [], franquicias = [], manuales = [];
    if (miRol === 'franquiciado') {
      const [u, m] = await Promise.all([apiFetch('GET', '/usuarios'), apiFetch('GET', '/manuales')]);
      usuarios = u; manuales = m;
    } else if (miRol === 'super_admin') {
      const [u, e, f, m] = await Promise.all([
        apiFetch('GET', '/usuarios'), apiFetch('GET', '/empresas'),
        apiFetch('GET', '/franquicias'), apiFetch('GET', '/manuales')
      ]);
      usuarios = u; empresas = e; franquicias = f; manuales = m;
    } else {
      const [u, f, m] = await Promise.all([
        apiFetch('GET', '/usuarios'), apiFetch('GET', '/franquicias'), apiFetch('GET', '/manuales')
      ]);
      usuarios = u; franquicias = f; manuales = m;
    }

    todosLosUsuarios    = usuarios;
    todasLasEmpresas    = empresas;
    todasLasFranquicias = franquicias;
    todosLosManuales    = (manuales || []).filter(m => m.estado === 'publicado');

    // Filtros: empresa solo visible para super_admin
    const selEmpFiltro = document.getElementById('sel-empresa');
    if (miRol === 'super_admin') {
      selEmpFiltro.style.display = '';
      empresas.forEach(e => {
        const opt = document.createElement('option');
        opt.value = e.id; opt.textContent = e.nombre;
        selEmpFiltro.appendChild(opt);
      });
      // Mostrar el toggle de "Mostrar eliminados" solo a super_admin
      document.getElementById('btn-mostrar-eliminados').style.display = '';
    }

    // Filtro franquicias
    const selFranqFiltro = document.getElementById('sel-franquicia');
    franquicias.forEach(f => {
      const opt = document.createElement('option');
      opt.value = f.id; opt.textContent = f.nombre;
      selFranqFiltro.appendChild(opt);
    });

    // Select empresa del formulario (solo super_admin, para rol franquiciante)
    if (miRol === 'super_admin') {
      const selEmpForm = document.getElementById('form-empresa');
      empresas.filter(e => e.activa).forEach(e => {
        const opt = document.createElement('option');
        opt.value = e.id; opt.textContent = e.nombre;
        selEmpForm.appendChild(opt);
      });
    }

    // Select franquicias del formulario
    const selFranqForm = document.getElementById('form-franquicia');
    franquicias.forEach(f => {
      const opt = document.createElement('option');
      opt.value = f.id; opt.textContent = f.nombre;
      selFranqForm.appendChild(opt);
    });

    // Filtros de rol: franquiciante no ve "Franquiciantes" ni "Super Admin"
    if (miRol === 'franquiciante') {
      document.querySelector('.filtro-btn[onclick*="franquiciante"]')?.remove();
    }

    // Franquiciado: solo gestiona empleados de su sucursal
    if (miRol === 'franquiciado') {
      document.querySelector(".filtro-btn[onclick*=\"filtrarRol('franquiciante'\"]")?.remove();
      document.querySelector(".filtro-btn[onclick*=\"filtrarRol('franquiciado'\"]")?.remove();
      document.getElementById('sel-franquicia').style.display = 'none';
    }

    renderTabla(usuarios);
    document.getElementById('page-sub').textContent =
      `${usuarios.length} usuario(s) registrado(s)`;

  } catch (e) {
    document.getElementById('tabla-body').innerHTML =
      `<tr><td colspan="7"><div class="empty-state">Error al cargar usuarios.</div></td></tr>`;
  }
}

// ── RENDER TABLA ──────────────────────────────────────────────
function renderTabla(lista) {
  const tbody = document.getElementById('tabla-body');
  document.getElementById('tabla-titulo').textContent = `${lista.length} resultado(s)`;

  if (!lista.length) {
    tbody.innerHTML = `<tr><td colspan="7"><div class="empty-state">Sin usuarios que mostrar.</div></td></tr>`;
    return;
  }

  tbody.innerHTML = lista.map(u => {
    const perfil    = u.super_admin || u.system_admin || u.franchise_staff;
    const nombre    = perfil ? `${perfil.nombre} ${perfil.apellido}` : '—';
    const dni       = perfil?.dni || '—';

    // Estado de eliminación (solo aparece para super_admin con flag include_deleted=1)
    const eliminado     = !!u.deleted_at;
    const badgeElim     = eliminado
      ? `<span style="display:inline-block;margin-left:8px;padding:2px 8px;border-radius:10px;font-size:10px;font-family:'Archivo',sans-serif;background:rgba(226,92,92,.12);color:var(--error);border:1px solid rgba(226,92,92,.3);vertical-align:middle">Eliminado</span>`
      : '';

    // Empresa / franquicia según rol
    let contexto = '—';
    if (u.rol === 'franquiciante') {
      const emp = todasLasEmpresas.find(e => e.id === u.empresa_id);
      contexto  = emp ? `<div style="font-size:12px;color:var(--blanco)">${esc(emp.nombre)}</div><div style="font-size:11px;color:var(--gris4)">Franquiciante</div>` : '—';
    } else if (u.franchise_staff?.franquicia) {
      contexto = `<div style="font-size:12px">${esc(u.franchise_staff.franquicia.nombre)}</div>`;
    }

    // Si está eliminado: solo super_admin lo ve, y solo puede restaurarlo (sin otras acciones)
    if (eliminado) {
      return `<tr style="opacity:.65">
        <td style="color:var(--blanco);font-weight:500">${esc(nombre)}${badgeElim}</td>
        <td style="font-size:12px;font-family:'Archivo Narrow',sans-serif">${esc(u.email)}</td>
        <td><span class="rol-badge ${u.rol}">${u.rol.replace('_',' ')}</span></td>
        <td>${contexto}</td>
        <td style="font-family:'Archivo Narrow',sans-serif">${esc(dni)}</td>
        <td><span class="estado-pill estado-pendiente">Eliminado</span></td>
        <td>
          <button class="accion-btn" style="color:var(--dorado)" onclick="restaurarUsuario(${u.id}, '${esc(nombre)}')">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
            Restaurar
          </button>
        </td>
      </tr>`;
    }

    // Botón toggle — no aparece para super_admin; franquiciante no puede tocar a franquiciantes
    const puedeToggle = u.rol !== 'super_admin' &&
      !(miRol === 'franquiciante' && u.rol === 'franquiciante');
    const btnToggle = puedeToggle ? `
      <button class="accion-btn" style="color:${u.activo ? 'var(--error)' : 'var(--exito)'}"
        onclick="abrirModalToggle(${u.id}, ${u.activo ? 'true' : 'false'})">
        ${u.activo
          ? `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg> Desactivar`
          : `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> Activar`
        }
      </button>` : '';

    // Botón eliminar:
    // - super_admin puede eliminar a cualquiera (excepto a sí mismo, que ya está filtrado en backend)
    // - franquiciante solo puede eliminar franquiciados/empleados de su empresa
    const puedeEliminar = u.rol !== 'super_admin' &&
      !(miRol === 'franquiciante' && u.rol === 'franquiciante');
    const btnEliminar = puedeEliminar ? `
      <button class="accion-btn" style="color:var(--gris5)" onclick="abrirModalEliminar(${u.id}, '${esc(nombre)}')">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
        Eliminar
      </button>` : '';

    return `<tr>
      <td style="color:var(--blanco);font-weight:500">${esc(nombre)}</td>
      <td style="font-size:12px;font-family:'Archivo Narrow',sans-serif">${esc(u.email)}</td>
      <td><span class="rol-badge ${u.rol}">${u.rol.replace('_',' ')}</span></td>
      <td>${contexto}</td>
      <td style="font-family:'Archivo Narrow',sans-serif">${esc(dni)}</td>
      <td><span class="estado-pill ${u.activo ? 'estado-completo' : 'estado-pendiente'}">${u.activo ? 'Activo' : 'Inactivo'}</span></td>
      <td>
        <div style="display:flex;gap:4px;align-items:center;flex-wrap:wrap">
          <button class="accion-btn" style="color:var(--gris5)" onclick="abrirModalEditar(${u.id})">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            Editar
          </button>
          ${u.rol === 'empleado' ? `
          <button class="accion-btn" style="color:var(--dorado)" onclick="abrirModalManuales(${u.id}, '${esc(nombre)}')">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            Manuales
          </button>` : ''}
          ${btnToggle}
          ${btnEliminar}
        </div>
      </td>
    </tr>`;
  }).join('');
}

// ── FILTROS ───────────────────────────────────────────────────
function filtrarRol(rol, btn) {
  filtroRolActual = rol;
  document.querySelectorAll('.filtro-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  aplicarFiltros();
}

function aplicarFiltros() {
  let lista      = [...todosLosUsuarios];
  const texto    = document.getElementById('inp-buscar')?.value.toLowerCase().trim() || '';
  const empId    = document.getElementById('sel-empresa')?.value || '';
  const franqId  = document.getElementById('sel-franquicia')?.value || '';

  if (filtroRolActual !== 'todos')
    lista = lista.filter(u => u.rol === filtroRolActual);

  if (empId)
    lista = lista.filter(u => String(u.empresa_id) === empId);

  if (franqId)
    lista = lista.filter(u => String(u.franchise_staff?.franquicia_id) === franqId);

  if (texto) {
    lista = lista.filter(u => {
      const perfil = u.super_admin || u.system_admin || u.franchise_staff;
      const nombre = perfil ? `${perfil.nombre} ${perfil.apellido}`.toLowerCase() : '';
      return nombre.includes(texto);
    });
  }

  renderTabla(lista);
}

// ── MODAL CREAR ───────────────────────────────────────────────
function abrirModalCrear() {
  modoEdicion  = false;
  rolEditando  = null;
  limpiarForm();
  document.getElementById('modal-titulo').textContent         = 'Nuevo usuario';
  document.getElementById('form-id').value                   = '';
  document.getElementById('grupo-password').style.display    = 'block';
  document.getElementById('label-password').textContent      = 'Contraseña *';
  document.getElementById('hint-password').textContent       = '';
  document.getElementById('form-rol').disabled               = false;

  // Franquiciado: solo crea empleados de su propia sucursal
  if (miRol === 'franquiciado') {
    document.getElementById('modal-titulo').textContent = 'Nuevo empleado';
    document.getElementById('form-rol').value    = 'empleado';
    document.getElementById('form-rol').disabled = true;
    onRolChange();
  }

  document.getElementById('modal').classList.add('open');
  setTimeout(() => document.getElementById('form-nombre').focus(), 100);
}

// ── MODAL EDITAR ──────────────────────────────────────────────
function abrirModalEditar(id) {
  const u = todosLosUsuarios.find(x => x.id === id);
  if (!u) return;

  modoEdicion = true;
  rolEditando = u.rol;
  limpiarForm();

  const perfil = u.super_admin || u.system_admin || u.franchise_staff;

  document.getElementById('modal-titulo').textContent   = 'Editar usuario';
  document.getElementById('form-id').value              = u.id;
  document.getElementById('form-rol-original').value    = u.rol;
  document.getElementById('form-nombre').value          = perfil?.nombre   || '';
  document.getElementById('form-apellido').value        = perfil?.apellido || '';
  document.getElementById('form-email').value           = u.email;
  document.getElementById('form-dni').value             = perfil?.dni      || '';
  document.getElementById('form-rol').value             = u.rol;
  document.getElementById('form-rol').disabled          = true; // rol no editable

  // Empresa del franquiciante — no editable en edición
  if (u.rol === 'franquiciante') {
    document.getElementById('form-empresa').value    = u.empresa_id || '';
    document.getElementById('form-empresa').disabled = true;
    document.getElementById('hint-empresa').textContent =
      'La empresa del franquiciante no se puede modificar una vez asignada.';
  }

  // Franquicia del franquiciado/empleado
  if (u.franchise_staff) {
    document.getElementById('form-franquicia').value = u.franchise_staff.franquicia_id || '';
  }

  // Contraseña: oculta para super_admin editado, oculta siempre para franquiciante logueado
  if (u.rol === 'super_admin' || miRol === 'franquiciante') {
    document.getElementById('grupo-password').style.display = 'none';
  } else {
    document.getElementById('grupo-password').style.display = 'block';
    document.getElementById('label-password').textContent   = 'Nueva contraseña';
    document.getElementById('hint-password').textContent    =
      'Dejá en blanco para no cambiar la contraseña.';
  }

  onRolChange();
  document.getElementById('modal').classList.add('open');
  setTimeout(() => document.getElementById('form-nombre').focus(), 100);
}

function cerrarModal() {
  document.getElementById('modal').classList.remove('open');
  document.getElementById('form-rol').disabled     = false;
  document.getElementById('form-empresa').disabled = false;
}

function limpiarForm() {
  ['form-nombre','form-apellido','form-email','form-password','form-dni']
    .forEach(id => document.getElementById(id).value = '');
  document.getElementById('form-rol').value          = '';
  document.getElementById('form-empresa').value      = '';
  document.getElementById('form-franquicia').value   = '';
  document.getElementById('form-rol').disabled       = false;
  document.getElementById('form-empresa').disabled   = false;
  document.getElementById('hint-empresa').textContent = '';
  const err = document.getElementById('form-error');
  err.style.display = 'none'; err.textContent = '';
  onRolChange();
}

// ── LÓGICA DE CAMPOS SEGÚN ROL ────────────────────────────────
function onRolChange() {
  const rol         = document.getElementById('form-rol').value;
  const grupoEmp    = document.getElementById('grupo-empresa');
  const grupoFranq  = document.getElementById('grupo-franquicia');

  grupoEmp.style.display = rol === 'franquiciante' ? 'block' : 'none';
  // El franquiciado crea empleados en su propia sucursal (forzada en el server),
  // por eso no mostramos el selector de franquicia.
  const mostrarFranq = (rol === 'franquiciado' || rol === 'empleado') && miRol !== 'franquiciado';
  grupoFranq.style.display = mostrarFranq ? 'block' : 'none';

  // Al cambiar a franquiciante, filtrar franquicias por empresa seleccionada
  if (rol === 'franquiciante') onEmpresaChange();
}

function onEmpresaChange() {
  const empresaId = document.getElementById('form-empresa').value;
  const sel       = document.getElementById('form-franquicia');

  // Filtrar franquicias del select por empresa
  const franqsFiltradas = empresaId
    ? todasLasFranquicias.filter(f => String(f.empresa_id) === empresaId)
    : todasLasFranquicias;

  sel.innerHTML = '<option value="">Seleccioná una franquicia</option>';
  franqsFiltradas.forEach(f => {
    const opt = document.createElement('option');
    opt.value = f.id; opt.textContent = f.nombre;
    sel.appendChild(opt);
  });
}

function togglePassModal() {
  const inp = document.getElementById('form-password');
  const eye = document.getElementById('modal-eye');
  const visible = inp.type === 'text';
  inp.type = visible ? 'password' : 'text';
  eye.innerHTML = visible
    ? `<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>`
    : `<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>`;
}

// ── GUARDAR ───────────────────────────────────────────────────
async function guardar() {
  const id        = document.getElementById('form-id').value;
  const nombre    = document.getElementById('form-nombre').value.trim();
  const apellido  = document.getElementById('form-apellido').value.trim();
  const email     = document.getElementById('form-email').value.trim();
  const password  = document.getElementById('form-password').value;
  const rol       = document.getElementById('form-rol').value || document.getElementById('form-rol-original').value;
  const dni       = document.getElementById('form-dni').value.trim();
  const empresaId = document.getElementById('form-empresa').value;
  const franqId   = document.getElementById('form-franquicia').value;
  const btn       = document.getElementById('btn-guardar');

  document.getElementById('form-error').style.display = 'none';

  if (!nombre)   { mostrarFormError('El nombre es obligatorio.');   return; }
  if (!apellido) { mostrarFormError('El apellido es obligatorio.'); return; }
  if (!email)    { mostrarFormError('El email es obligatorio.');    return; }
  if (!rol)      { mostrarFormError('Seleccioná un rol.');          return; }

  if (rol === 'franquiciante' && !modoEdicion && miRol === 'super_admin' && !empresaId) {
    mostrarFormError('Seleccioná una empresa para el franquiciante.'); return;
  }
  if ((rol === 'franquiciado' || rol === 'empleado') && !franqId && miRol !== 'franquiciado') {
    mostrarFormError('Seleccioná una franquicia.'); return;
  }
  if (!modoEdicion && !password) {
    mostrarFormError('La contraseña es obligatoria.'); return;
  }
  if (!modoEdicion && password.length < 8) {
    mostrarFormError('La contraseña debe tener al menos 8 caracteres.'); return;
  }
  // Solo validar contraseña en edición si el campo está visible (super_admin logueado)
  if (modoEdicion && miRol === 'super_admin' && password && password.length < 8) {
    mostrarFormError('La contraseña debe tener al menos 8 caracteres.'); return;
  }

  btn.disabled = true; btn.textContent = 'Guardando...';

  try {
    const body = { nombre, apellido, email, rol, dni: dni || null };

    if (password)  body.password      = password;
    if (franqId)   body.franquicia_id = franqId;
    // empresa_id solo la manda super_admin al crear un franquiciante
    if (!modoEdicion && miRol === 'super_admin' && empresaId) body.empresa_id = empresaId;

    if (modoEdicion) {
      await apiFetch('PUT', `/usuarios/${id}`, body);
      mostrarToast('Usuario actualizado correctamente.', 'exito');
    } else {
      await apiFetch('POST', '/usuarios', body);
      mostrarToast('Usuario creado correctamente.', 'exito');
    }

    cerrarModal();
    const usuarios = await apiFetch('GET', '/usuarios');
    todosLosUsuarios = usuarios;
    aplicarFiltros();
    document.getElementById('page-sub').textContent =
      `${usuarios.length} usuario(s) registrado(s)`;

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
function abrirModalToggle(id, activo) {
  const u      = todosLosUsuarios.find(x => x.id === id);
  const perfil = u?.super_admin || u?.system_admin || u?.franchise_staff;
  const nombre = perfil ? `${perfil.nombre} ${perfil.apellido}` : u?.email;
  pendingToggle = { id, activo };

  document.getElementById('toggle-titulo').textContent = activo ? 'Desactivar usuario' : 'Activar usuario';
  document.getElementById('toggle-msg').textContent    = activo
    ? `¿Desactivar a "${nombre}"? No podrá ingresar al sistema hasta que sea reactivado.`
    : `¿Activar a "${nombre}"? Podrá volver a ingresar al sistema.`;

  const btn = document.getElementById('btn-toggle-confirmar');
  btn.className   = `btn ${activo ? 'btn-danger' : 'btn-success'}`;
  btn.textContent = activo ? 'Desactivar' : 'Activar';

  document.getElementById('toggle-error').style.display = 'none';
  document.getElementById('modal-toggle').classList.add('open');
}

function cerrarModalToggle() {
  document.getElementById('modal-toggle').classList.remove('open');
  pendingToggle = null;
}

async function confirmarToggle() {
  if (!pendingToggle) return;
  const { id, activo } = pendingToggle;
  const btn = document.getElementById('btn-toggle-confirmar');
  btn.disabled = true; btn.textContent = 'Procesando...';
  try {
    await apiFetch('POST', `/usuarios/${id}/toggle-activo`);
    mostrarToast(activo ? 'Usuario desactivado.' : 'Usuario activado.', activo ? 'error' : 'exito');
    cerrarModalToggle();
    await recargarUsuarios();
  } catch (e) {
    document.getElementById('toggle-error').textContent  = e.data?.message || 'Error al procesar.';
    document.getElementById('toggle-error').style.display = 'block';
    btn.disabled = false;
    btn.textContent = activo ? 'Desactivar' : 'Activar';
  }
}

// ── HELPER: recargar lista de usuarios (respeta el flag de eliminados) ─
async function recargarUsuarios() {
  const url = mostrarEliminados ? '/usuarios?include_deleted=1' : '/usuarios';
  todosLosUsuarios = await apiFetch('GET', url);
  aplicarFiltros();
}

// ── MOSTRAR ELIMINADOS (solo super_admin) ─────────────────────
async function toggleMostrarEliminados(btn) {
  mostrarEliminados = !mostrarEliminados;
  if (mostrarEliminados) {
    btn.classList.add('active');
  } else {
    btn.classList.remove('active');
  }
  await recargarUsuarios();
}

// ── MODAL ELIMINAR USUARIO ────────────────────────────────────
function abrirModalEliminar(id, nombre) {
  pendingEliminar = id;
  document.getElementById('eliminar-msg').textContent =
    `¿Eliminar a "${nombre}"? El usuario perderá acceso al sistema inmediatamente y dejará de ser visible.`;
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
    await apiFetch('DELETE', `/usuarios/${pendingEliminar}`);
    mostrarToast('Usuario eliminado correctamente.', 'exito');
    cerrarModalEliminar();
    await recargarUsuarios();
  } catch (e) {
    document.getElementById('eliminar-error').textContent = e.data?.message || 'Error al eliminar.';
    document.getElementById('eliminar-error').style.display = 'block';
    btn.disabled = false; btn.textContent = 'Eliminar';
  }
}

// ── RESTAURAR USUARIO (solo super_admin) ──────────────────────
async function restaurarUsuario(id, nombre) {
  try {
    await apiFetch('POST', `/usuarios/${id}/restore`);
    mostrarToast(`"${nombre}" restaurado correctamente.`, 'exito');
    await recargarUsuarios();
  } catch (e) {
    mostrarToast(e.data?.message || 'Error al restaurar.', 'error');
  }
}

// ── MODAL MANUALES EMPLEADO ───────────────────────────────────
async function abrirModalManuales(empleadoId, empleadoNombre) {
  empleadoSeleccionado = { id: empleadoId, nombre: empleadoNombre };

  document.getElementById('manuales-titulo').textContent    = `Manuales de ${empleadoNombre}`;
  document.getElementById('manuales-subtitulo').textContent = 'Gestioná los manuales que puede leer este empleado';
  document.getElementById('asignar-error').style.display   = 'none';
  document.getElementById('modal-manuales').classList.add('open');

  await cargarManualAsignados();
}

function cerrarModalManuales() {
  document.getElementById('modal-manuales').classList.remove('open');
  empleadoSeleccionado = null;
}

async function cargarManualAsignados() {
  const lista = document.getElementById('lista-manuales-asignados');
  lista.innerHTML = `<div class="loading-msg"><div class="spinner" style="display:block"></div>Cargando...</div>`;

  try {
    const asignaciones = await apiFetch('GET', `/empleados/${empleadoSeleccionado.id}/asignaciones`);
    const idsAsignados = asignaciones.map(a => a.manual_id);

    // Poblar select con manuales NO asignados todavía
    const sel = document.getElementById('sel-manual-asignar');
    sel.innerHTML = '<option value="">Seleccioná un manual publicado</option>';
    todosLosManuales
      .filter(m => !idsAsignados.includes(m.id))
      .forEach(m => {
        const opt = document.createElement('option');
        opt.value = m.id; opt.textContent = m.titulo;
        sel.appendChild(opt);
      });

    if (!asignaciones.length) {
      lista.innerHTML = `<div style="text-align:center;padding:24px;font-size:13px;color:var(--gris4);font-family:'Archivo Narrow',sans-serif">No tiene manuales asignados todavía.</div>`;
      return;
    }

    lista.innerHTML = asignaciones.map(a => {
      const manual = a.manual;
      const version = manual?.version_activa?.[0];
      return `<div style="display:flex;align-items:center;justify-content:space-between;padding:10px 12px;background:var(--negro);border:1px solid var(--gris2);border-radius:8px;margin-bottom:8px">
        <div>
          <div style="font-size:13px;color:var(--blanco);font-weight:500">${esc(manual?.titulo || '—')}</div>
          <div style="font-size:11px;color:var(--gris4);margin-top:2px;font-family:'Archivo Narrow',sans-serif">
            ${manual?.categoria ? esc(manual.categoria) + ' · ' : ''}
            ${version ? `v${version.version_number}` : 'Sin versión publicada'}
          </div>
        </div>
        <button class="accion-btn" style="color:var(--error);flex-shrink:0" onclick="desasignarManual(${a.manual_id})">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          Quitar
        </button>
      </div>`;
    }).join('');

  } catch (e) {
    lista.innerHTML = `<div style="font-size:13px;color:var(--error);padding:12px">Error al cargar las asignaciones.</div>`;
  }
}

async function asignarManual() {
  const manualId = document.getElementById('sel-manual-asignar').value;
  const errEl    = document.getElementById('asignar-error');
  errEl.style.display = 'none';

  if (!manualId) {
    errEl.textContent = 'Seleccioná un manual.'; errEl.style.display = 'block'; return;
  }

  try {
    await apiFetch('POST', `/empleados/${empleadoSeleccionado.id}/asignaciones`, { manual_id: parseInt(manualId) });
    mostrarToast('Manual asignado correctamente.', 'exito');
    await cargarManualAsignados();
  } catch (e) {
    errEl.textContent = e.data?.message || 'Error al asignar el manual.';
    errEl.style.display = 'block';
  }
}

async function desasignarManual(manualId) {
  try {
    await apiFetch('DELETE', `/empleados/${empleadoSeleccionado.id}/asignaciones/${manualId}`);
    mostrarToast('Manual quitado.', 'exito');
    await cargarManualAsignados();
  } catch (e) {
    mostrarToast(e.data?.message || 'Error al quitar el manual.', 'error');
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
  if (!str || str === '—') return str || '—';
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

document.addEventListener('keydown', e => {
  if (e.key === 'Escape') { cerrarModal(); cerrarModalToggle(); cerrarModalManuales(); }
});

init();
</script>

<?php include 'layout/footer.php'; ?>