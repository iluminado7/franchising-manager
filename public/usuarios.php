<?php
require_once __DIR__ . '/layout/config.php';
require_once __DIR__ . '/layout/auth.php';
verificarSesion('franquiciante'); // super_admin y franquiciante — restricciones por rol en JS
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
        <button class="filtro-btn" onclick="filtrarRol('franquiciado', this)">Socios comerciales</button>
        <button class="filtro-btn" onclick="filtrarRol('empleado', this)">Empleados</button>
        <div class="filtro-sep"></div>
        <!-- Combobox empresa — solo super_admin -->
        <div id="grupo-empresa-filtro" style="display:none;position:relative">
          <div id="empresa-combo-usr" style="position:relative;width:220px">
            <input type="text" id="inp-empresa-usr" placeholder="Buscar empresa..." autocomplete="off" name="combo-empresa-usr"
                   class="filtro-select" style="width:100%;box-sizing:border-box;padding-left:30px;padding-right:30px"
                   oninput="filtrarOpcionesEmpresaUsr()" onfocus="filtrarOpcionesEmpresaUsr()">
            <svg style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--gris4)" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <button type="button" id="empresa-clear-usr" onclick="limpiarEmpresaUsr()" title="Quitar filtro de empresa"
                    style="display:none;position:absolute;right:8px;top:50%;transform:translateY(-50%);background:transparent;border:none;color:var(--gris4);cursor:pointer;padding:2px;line-height:0">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
            <div id="empresa-opciones-usr" class="combo-opciones"></div>
          </div>
        </div>

        <!-- Combobox franquicia — super_admin y franquiciante -->
        <div id="grupo-franquicia-filtro" style="position:relative">
          <div id="franquicia-combo-usr" style="position:relative;width:220px">
            <input readonly type="text" id="inp-franquicia-usr" placeholder="Buscar franquicia..." autocomplete="off" name="combo-franquicia-usr"
                   class="filtro-select" style="width:100%;box-sizing:border-box;padding-left:30px;padding-right:30px"
                   oninput="filtrarOpcionesFranquiciaUsr()" onfocus="filtrarOpcionesFranquiciaUsr()">
            <svg style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--gris4)" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <button type="button" id="franquicia-clear-usr" onclick="limpiarFranquiciaUsr()" title="Quitar filtro de franquicia"
                    style="display:none;position:absolute;right:8px;top:50%;transform:translateY(-50%);background:transparent;border:none;color:var(--gris4);cursor:pointer;padding:2px;line-height:0">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
            <div id="franquicia-opciones-usr" class="combo-opciones"></div>
          </div>
        </div>

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
              <th>Categorías</th>
              <th>DNI</th>
              <th>Estado</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody id="tabla-body">
            <tr><td colspan="8"><div class="loading-msg"><div class="spinner" style="display:block"></div>Cargando usuarios...</div></td></tr>
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
        <div id="hint-password" style="font-size:11px;color:var(--gris4);margin-top:4px;font-family:'Roboto',sans-serif"></div>
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
        <div id="hint-empresa" style="font-size:11px;color:var(--gris4);margin-top:4px;font-family:'Roboto',sans-serif"></div>
      </div>

      <!-- Franquicia / Sucursal — para empleado (obligatoria) y socio comercial (opcional) -->
      <div class="form-group" id="grupo-franquicia" style="display:none">
        <label id="label-franquicia">Franquicia</label>
        <select id="form-franquicia" class="form-select">
          <option value="">Sin sucursal asignada</option>
        </select>
        <div id="hint-franquicia" style="font-size:11px;color:var(--gris4);margin-top:4px;font-family:'Roboto',sans-serif"></div>
      </div>

      <!-- v2.3: Categorías para Socio comercial al crear -->
      <div class="form-group" id="grupo-categorias-socio" style="display:none">
        <label>Categorías del Socio comercial</label>
        <div id="categorias-socio-lista" style="background:var(--negro);border:1px solid var(--gris2);border-radius:8px;padding:10px;max-height:240px;overflow-y:auto">
          <!-- Se renderiza dinámicamente -->
        </div>
        <div id="hint-categorias-socio" style="font-size:11px;color:var(--gris4);margin-top:6px;font-family:'Roboto',sans-serif;line-height:1.5">
          Definí a qué categorías va a pertenecer este Socio comercial. Esto determina qué manuales y documentos podrá ver.
        </div>
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
        <div id="manuales-subtitulo" style="font-size:12px;color:var(--gris4);margin-top:2px;font-family:'Roboto',sans-serif"></div>
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

<!-- ── MODAL CATEGORÍAS (solo franquiciado) ─────────────────── -->
<div class="modal-overlay" id="modal-categorias">
  <div class="modal-box" style="max-width:480px">
    <div class="modal-header">
      <div>
        <h3 id="categorias-titulo">Categorías del usuario</h3>
        <div id="categorias-subtitulo" style="font-size:12px;color:var(--gris4);margin-top:2px;font-family:'Roboto',sans-serif"></div>
      </div>
      <button class="modal-close" onclick="cerrarModalCategorias()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-body">
      <div style="font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:var(--gris4);margin-bottom:10px">Categorías disponibles</div>
      <div id="lista-categorias">
        <div class="loading-msg"><div class="spinner" style="display:block"></div>Cargando...</div>
      </div>
      <div class="form-error" id="categorias-error" style="display:none;margin-top:12px"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="cerrarModalCategorias()">Cancelar</button>
      <button class="btn btn-primary" id="btn-guardar-categorias" onclick="guardarCategorias()">
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
      <p id="eliminar-msg" style="font-size:14px;color:var(--gris5);line-height:1.6;font-family:'Roboto',sans-serif"></p>
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

/* ── Combobox de empresa/franquicia (mismo patrón que documentos/manuales/log) ── */
.combo-opciones { display:none;position:absolute;top:calc(100% + 4px);left:0;right:0;max-height:240px;overflow-y:auto;background:var(--gris1);border:1px solid var(--gris2);border-radius:8px;z-index:50;box-shadow:0 8px 24px rgba(0,0,0,.4); }
.combo-opcion { padding:9px 12px;font-size:13px;color:var(--gris5);cursor:pointer;font-family:'Roboto',sans-serif;transition:background .12s; }
.combo-opcion:hover { background:var(--gris2);color:var(--blanco); }
.combo-vacio { padding:10px 12px;font-size:12px;color:var(--gris4);font-family:'Roboto',sans-serif; }

/* Chips de categorías en la tabla */
/* ── Avatar en la celda Nombre ────────────────────────────── */
.u-nombre-cell { display: flex; align-items: center; gap: 10px; }

/* Base: circulo con las iniciales. Siempre se renderiza. */
.u-avatar {
  position: relative;
  flex-shrink: 0;
  width: 32px; height: 32px;
  border-radius: 50%;
  display: inline-flex; align-items: center; justify-content: center;
  background: var(--gris2);
  border: 1px solid var(--gris3);
  color: var(--gris5);
  font-size: 11px; font-weight: 700; letter-spacing: .02em;
  font-family: 'Archivo', sans-serif;
  overflow: hidden;
  user-select: none;
}

/* La foto se apoya ENCIMA de las iniciales. Si carga, las tapa; si el endpoint
   devuelve 404, el onerror la borra y las iniciales quedan a la vista. El
   fallback es el estado base, no una rama de codigo. */
.u-avatar-img {
  position: absolute; inset: 0;
  width: 100%; height: 100%;
  object-fit: cover;
  border-radius: 50%;
}

.cat-chips { display: inline-flex; flex-wrap: wrap; gap: 4px; align-items: center; max-width: 260px; }
.cat-chip {
  display: inline-flex; align-items: center;
  background: rgba(201,168,76,.10); color: var(--dorado);
  border: 1px solid rgba(201,168,76,.28);
  border-radius: 10px; padding: 2px 8px;
  font-size: 10.5px; font-family: 'Roboto', sans-serif;
  white-space: nowrap; max-width: 130px;
  overflow: hidden; text-overflow: ellipsis;
}
.cat-chip.more {
  cursor: pointer; background: transparent;
  color: var(--gris5); border-color: var(--gris2);
  transition: color .15s, border-color .15s;
}
.cat-chip.more:hover { color: var(--dorado); border-color: var(--dorado); }
.cat-empty { color: var(--gris4); font-style: italic; font-size: 12px; font-family: 'Roboto', sans-serif; }

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
.toast { position:fixed;bottom:24px;right:24px;background:var(--gris1);border:1px solid var(--gris2);border-radius:10px;padding:12px 16px;font-size:13px;color:var(--blanco);display:flex;align-items:center;gap:10px;transform:translateY(80px);opacity:0;transition:transform .3s,opacity .3s;z-index:600;font-family:'Roboto',sans-serif;max-width:320px; }
.toast.show { transform:translateY(0);opacity:1; }
</style>

<script>
let todosLosManuales    = [];
let empleadoSeleccionado = null; // { id, nombre }

// v2.3 — gestión de categorías del franquiciado
let categoriasUsuarioSel  = null; // { id, nombre, empresaId }

// v2.3: cats activas de la empresa al CREAR un Socio comercial (modal "Nuevo usuario")
let categoriasParaSocio = []; // [{ id, name, description, is_active, empresa_id }]
let categoriasDisponibles = []; // catálogo activo de la empresa

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

// Mapa de roles visibles en la UI. El valor interno (DB / API) sigue siendo
// 'franquiciado' — sólo cambia la etiqueta para diferenciarlo de la categoría.
const LABEL_ROL = {
  super_admin:   'Super Admin',
  franquiciante: 'Franquiciante',
  franquiciado:  'Socio comercial',
  empleado:      'Empleado',
};
let empresaFiltroId     = ''; // ID activo del combobox de empresa (super_admin)
let franquiciaFiltroId  = ''; // ID activo del combobox de franquicia (super_admin y franquiciante)

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
        <option value="franquiciado">Socio comercial</option>
        <option value="empleado">Empleado</option>`;
    } else if (miRol === 'franquiciante') {
      // franquiciante: solo puede crear socios comerciales (rol franquiciado) y empleados
      selRolForm.innerHTML = `
        <option value="">Seleccioná un rol</option>
        <option value="franquiciado">Socio comercial</option>
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

    // Filtros: combobox de empresa solo visible para super_admin
    if (miRol === 'super_admin') {
      document.getElementById('grupo-empresa-filtro').style.display = '';
      // Mostrar el toggle de "Mostrar eliminados" solo a super_admin
      document.getElementById('btn-mostrar-eliminados').style.display = '';
    }

    // Combobox de franquicia: oculto para franquiciado (que no filtra por sucursal porque
    // ya ve solo los empleados de la suya) y oculto para empleado.
    if (miRol === 'franquiciado' || miRol === 'empleado') {
      document.getElementById('grupo-franquicia-filtro').style.display = 'none';
    }

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
    // Para super_admin queda vacío hasta que elija una empresa; para franquiciante
    // se pre-popula con las franquicias de su propia empresa (las únicas que ve).
    const selFranqForm = document.getElementById('form-franquicia');
    if (miRol === 'super_admin') {
      selFranqForm.innerHTML = '<option value="">Elegí primero una empresa</option>';
      selFranqForm.disabled  = true;
    } else {
      franquicias.forEach(f => {
        const opt = document.createElement('option');
        opt.value = f.id; opt.textContent = f.nombre;
        selFranqForm.appendChild(opt);
      });
    }

    // Filtros de rol: franquiciante no ve "Franquiciantes" ni "Super Admin"
    if (miRol === 'franquiciante') {
      document.querySelector('.filtro-btn[onclick*="franquiciante"]')?.remove();
    }

    // Franquiciado: solo gestiona empleados de su sucursal
    if (miRol === 'franquiciado') {
      document.querySelector(".filtro-btn[onclick*=\"filtrarRol('franquiciante'\"]")?.remove();
      document.querySelector(".filtro-btn[onclick*=\"filtrarRol('franquiciado'\"]")?.remove();
      // grupo-franquicia-filtro ya quedó oculto antes en el init
    }

    renderTabla(usuarios);
    document.getElementById('page-sub').textContent =
      `${usuarios.length} usuario(s) registrado(s)`;

  } catch (e) {
    document.getElementById('tabla-body').innerHTML =
      `<tr><td colspan="8"><div class="empty-state">Error al cargar usuarios.</div></td></tr>`;
  }
}

// ── RENDER TABLA ──────────────────────────────────────────────
// Iniciales del usuario: NA (nombre+apellido) o la primera letra del email.
// Mismo criterio que perfil.php, para que un usuario se vea igual en las dos pantallas.
function inicialesDe(u) {
  return (u.nombre && u.apellido)
    ? `${u.nombre[0]}${u.apellido[0]}`.toUpperCase()
    : (u.email ? u.email[0].toUpperCase() : '?');
}

// Avatar de 32px. Devuelve SIEMPRE el circulo con iniciales; si el usuario tiene
// foto, le monta la <img> encima. Si la imagen falla (404 del endpoint, sin
// permiso, archivo faltante) el onerror la elimina y quedan las iniciales.
//
// La URL se arma con API (const global) y NO con u.avatar_url tal cual: ese campo
// es una ruta absoluta ("/api/perfil/foto/N") y en XAMPP el proyecto vive en un
// subpath, con lo cual resolveria contra la raiz del servidor y daria 404.
function renderAvatar(u) {
  const ini = esc(inicialesDe(u));
  if (!u.avatar_url) {
    return `<span class="u-avatar">${ini}</span>`;
  }
  return `<span class="u-avatar">${ini}<img class="u-avatar-img" src="${API}/perfil/foto/${u.id}" alt="" loading="lazy" onerror="this.remove()"></span>`;
}

function renderTabla(lista) {
  const tbody = document.getElementById('tabla-body');
  document.getElementById('tabla-titulo').textContent = `${lista.length} resultado(s)`;

  if (!lista.length) {
    tbody.innerHTML = `<tr><td colspan="8"><div class="empty-state">Sin usuarios que mostrar.</div></td></tr>`;
    return;
  }

  tbody.innerHTML = lista.map(u => {
    // v2.3: nombre/apellido/dni viven en users (toplevel del JSON).
    const nombreFull = [u.nombre, u.apellido].filter(Boolean).join(' ').trim();
    const nombre     = nombreFull || '—';
    const dni        = u.dni || '—';

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
    } else if (u.rol === 'franquiciado') {
      // Socio comercial sin sucursal (ej. distribuidor, dropshipper)
      contexto = `<div style="font-size:12px;color:var(--gris3);font-style:italic">Sin sucursal</div>`;
    }

    // Si está eliminado: solo super_admin lo ve, y solo puede restaurarlo (sin otras acciones)
    if (eliminado) {
      return `<tr style="opacity:.65">
        <td style="color:var(--blanco);font-weight:500">
          <div class="u-nombre-cell">${renderAvatar(u)}<span>${esc(nombre)}${badgeElim}</span></div>
        </td>
        <td style="font-size:12px;font-family:'Roboto',sans-serif">${esc(u.email)}</td>
        <td><span class="rol-badge ${u.rol}">${LABEL_ROL[u.rol] || u.rol}</span></td>
        <td>${contexto}</td>
        <td>${renderCategoriasChips(u, nombre)}</td>
        <td style="font-family:'Roboto',sans-serif">${esc(dni)}</td>
        <td><span class="estado-pill estado-pendiente">Eliminado</span></td>
        <td>
          <button class="accion-btn" style="color:var(--dorado)" onclick="restaurarUsuario(${u.id}, '${escAttr(nombre)}')">
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
      <button class="accion-btn" style="color:var(--gris5)" onclick="abrirModalEliminar(${u.id}, '${escAttr(nombre)}')">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
        Eliminar
      </button>` : '';

    return `<tr>
      <td style="color:var(--blanco);font-weight:500">
        <div class="u-nombre-cell">${renderAvatar(u)}<span>${esc(nombre)}</span></div>
      </td>
      <td style="font-size:12px;font-family:'Roboto',sans-serif">${esc(u.email)}</td>
      <td><span class="rol-badge ${u.rol}">${LABEL_ROL[u.rol] || u.rol}</span></td>
      <td>${contexto}</td>
      <td>${renderCategoriasChips(u, nombre)}</td>
      <td style="font-family:'Roboto',sans-serif">${esc(dni)}</td>
      <td><span class="estado-pill ${u.activo ? 'estado-completo' : 'estado-pendiente'}">${u.activo ? 'Activo' : 'Inactivo'}</span></td>
      <td>
        <div style="display:flex;gap:4px;align-items:center;flex-wrap:wrap">
          <button class="accion-btn" style="color:var(--gris5)" onclick="abrirModalEditar(${u.id})">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            Editar
          </button>
          ${u.rol === 'empleado' ? `
            <button class="accion-btn" style="color:var(--dorado)" onclick="abrirModalManuales(${u.id}, '${escAttr(nombre)}')">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            Manuales
          </button>` : ''}
          ${u.rol === 'franquiciado' ? `
          <button class="accion-btn" style="color:var(--dorado)" onclick="abrirModalCategorias(${u.id}, '${escAttr(nombre)}', ${u.empresa_id || 'null'})">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
            Categorías
          </button>` : ''}
          ${btnToggle}
          ${btnEliminar}
        </div>
      </td>
    </tr>`;
  }).join('');
}

// ── CHIPS DE CATEGORÍAS ───────────────────────────────────────
// Solo el rol 'franquiciado' (UI: Socio comercial) tiene categorías.
// 0 → "Sin categoría"; 1-2 → todos los chips; 3+ → los primeros 2 + "+N más"
// clickeable que abre el modal de gestión existente.
function renderCategoriasChips(u, nombre) {
  if (u.rol !== 'franquiciado') return '<span class="cat-empty">—</span>';

  const cats = Array.isArray(u.categorias) ? u.categorias : [];

  if (!cats.length) return '<span class="cat-empty">Sin categoría</span>';

  const MAX_VISIBLE = 2;
  const visibles = cats.slice(0, MAX_VISIBLE);
  const resto    = cats.length - MAX_VISIBLE;

  const chips = visibles.map(c =>
    `<span class="cat-chip" title="${esc(c.name)}">${esc(c.name)}</span>`
  ).join('');

  const more = resto > 0
    ? `<span class="cat-chip more"
             title="Ver todas las categorías de ${esc(nombre)}"
             onclick="abrirModalCategorias(${u.id}, '${escAttr(nombre)}', ${u.empresa_id || 'null'})">
         +${resto} más
       </span>`
    : '';

  return `<div class="cat-chips">${chips}${more}</div>`;
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
  const empId    = empresaFiltroId;
  const franqId  = franquiciaFiltroId;

  if (filtroRolActual !== 'todos')
    lista = lista.filter(u => u.rol === filtroRolActual);

  if (empId)
    lista = lista.filter(u => String(u.empresa_id) === empId);

  if (franqId)
    lista = lista.filter(u => String(u.franchise_staff?.franquicia_id) === franqId);

  if (texto) {
    lista = lista.filter(u => {
      // v2.3: nombre/apellido viven en users
      const nombre = [u.nombre, u.apellido].filter(Boolean).join(' ').toLowerCase();
      return nombre.includes(texto);
    });
  }

  renderTabla(lista);
}

// ── COMBOBOX EMPRESA (super_admin) ────────────────────────────
function filtrarOpcionesEmpresaUsr() {
  const input = document.getElementById('inp-empresa-usr');
  const cont  = document.getElementById('empresa-opciones-usr');
  const texto = input.value.toLowerCase().trim();

  // Si vació el input y había una empresa elegida, limpia el filtro
  if (texto === '' && empresaFiltroId !== '') {
    empresaFiltroId = '';
    document.getElementById('empresa-clear-usr').style.display = 'none';
    aplicarFiltros();
  }

  // Solo mostramos empresas activas en el autocomplete. todasLasEmpresas sigue
  // conteniendo también las suspendidas para lookups por ID en la tabla.
  const coincidencias = todasLasEmpresas.filter(e => e.activa && e.nombre.toLowerCase().includes(texto));

  if (!coincidencias.length) {
    cont.innerHTML = `<div class="combo-vacio">Sin coincidencias</div>`;
    cont.style.display = 'block';
    return;
  }

  cont.innerHTML = coincidencias.map(e => `
    <div class="combo-opcion" onmousedown="seleccionarEmpresaUsr(${e.id}, '${escAttr(e.nombre)}')">
      ${esc(e.nombre)}${e.activa ? '' : ' <span style="color:var(--gris4)">(suspendida)</span>'}
    </div>`).join('');
  cont.style.display = 'block';
}

function seleccionarEmpresaUsr(id, nombre) {
  empresaFiltroId = String(id);
  document.getElementById('inp-empresa-usr').value = nombre;
  document.getElementById('empresa-clear-usr').style.display = 'block';
  document.getElementById('empresa-opciones-usr').style.display = 'none';
  aplicarFiltros();
}

function limpiarEmpresaUsr() {
  empresaFiltroId = '';
  document.getElementById('inp-empresa-usr').value = '';
  document.getElementById('empresa-clear-usr').style.display = 'none';
  document.getElementById('empresa-opciones-usr').style.display = 'none';
  aplicarFiltros();
}

// ── COMBOBOX FRANQUICIA (super_admin y franquiciante) ─────────
function filtrarOpcionesFranquiciaUsr() {
  const input = document.getElementById('inp-franquicia-usr');
  const cont  = document.getElementById('franquicia-opciones-usr');
  const texto = input.value.toLowerCase().trim();

  if (texto === '' && franquiciaFiltroId !== '') {
    franquiciaFiltroId = '';
    document.getElementById('franquicia-clear-usr').style.display = 'none';
    aplicarFiltros();
  }

  // Si el super_admin tiene una empresa filtrada, mostrar solo las franquicias de esa empresa.
  // Para franquiciante, todasLasFranquicias ya viene filtrada a su empresa por el backend.
  let pool = todasLasFranquicias;
  if (empresaFiltroId) {
    pool = pool.filter(f => String(f.empresa_id) === empresaFiltroId);
  }

  const coincidencias = pool.filter(f => f.nombre.toLowerCase().includes(texto));

  if (!coincidencias.length) {
    cont.innerHTML = `<div class="combo-vacio">Sin coincidencias</div>`;
    cont.style.display = 'block';
    return;
  }

  cont.innerHTML = coincidencias.map(f => `
    <div class="combo-opcion" onmousedown="seleccionarFranquiciaUsr(${f.id}, '${escAttr(f.nombre)}')">
      ${esc(f.nombre)}
    </div>`).join('');
  cont.style.display = 'block';
}

function seleccionarFranquiciaUsr(id, nombre) {
  franquiciaFiltroId = String(id);
  document.getElementById('inp-franquicia-usr').value = nombre;
  document.getElementById('franquicia-clear-usr').style.display = 'block';
  document.getElementById('franquicia-opciones-usr').style.display = 'none';
  aplicarFiltros();
}

function limpiarFranquiciaUsr() {
  franquiciaFiltroId = '';
  document.getElementById('inp-franquicia-usr').value = '';
  document.getElementById('franquicia-clear-usr').style.display = 'none';
  document.getElementById('franquicia-opciones-usr').style.display = 'none';
  aplicarFiltros();
}

// Escape para usar dentro de onmousedown="...('${valor}')"
function escAttr(s) {
   return String(s ?? '')
    .replace(/\\/g, '\\\\')   // JS: barra invertida
    .replace(/'/g, "\\'")     // JS: cierre del string
    .replace(/&/g, '&amp;')   // HTML: primero, para no romper las entidades de abajo
    .replace(/"/g, '&quot;')  // HTML: cierre del atributo
    .replace(/</g, '&lt;')    // HTML: defensivo
    .replace(/>/g, '&gt;');
}

// Cerrar los comboboxes al hacer click afuera
document.addEventListener('click', e => {
  const combo1 = document.getElementById('empresa-combo-usr');
  const opc1   = document.getElementById('empresa-opciones-usr');
  if (combo1 && opc1 && !combo1.contains(e.target)) opc1.style.display = 'none';

  const combo2 = document.getElementById('franquicia-combo-usr');
  const opc2   = document.getElementById('franquicia-opciones-usr');
  if (combo2 && opc2 && !combo2.contains(e.target)) opc2.style.display = 'none';
});

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

  // v2.3: nombre/apellido/dni viven en users
  document.getElementById('modal-titulo').textContent   = 'Editar usuario';
  document.getElementById('form-id').value              = u.id;
  document.getElementById('form-rol-original').value    = u.rol;
  document.getElementById('form-nombre').value          = u.nombre   || '';
  document.getElementById('form-apellido').value        = u.apellido || '';
  document.getElementById('form-email').value           = u.email;
  document.getElementById('form-dni').value             = u.dni      || '';
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

  // Empresa: el super_admin elige empresa para CUALQUIER rol que tenga empresa asignada
  // (franquiciante, franquiciado, empleado). El franquiciante no la elige: usa la suya.
  const necesitaEmpresa = miRol === 'super_admin'
    && ['franquiciante','franquiciado','empleado'].includes(rol);
  grupoEmp.style.display = necesitaEmpresa ? 'block' : 'none';

  // Franquicia: solo para franquiciado/empleado.
  // El franquiciado crea empleados en su propia sucursal (forzada en el server),
  // por eso no mostramos el selector de franquicia en ese caso.
  const mostrarFranq = (rol === 'franquiciado' || rol === 'empleado') && miRol !== 'franquiciado';
  grupoFranq.style.display = mostrarFranq ? 'block' : 'none';

  // Label + hint dinámicos: socio comercial = opcional, empleado = obligatorio
  if (mostrarFranq) {
    const labelEl = document.getElementById('label-franquicia');
    const hintEl  = document.getElementById('hint-franquicia');
    if (rol === 'empleado') {
      labelEl.textContent = 'Sucursal *';
      hintEl.textContent  = 'Los empleados pertenecen a una sucursal específica.';
    } else {
      labelEl.textContent = 'Sucursal';
      hintEl.textContent  = 'Opcional para Socios comerciales. Dejá vacío para distribuidores, dropshippers o similares sin sucursal asignada.';
    }
  }

  // Si el super_admin va a elegir franquicia, el select arranca dependiente
  // de la empresa actual: vacío si no eligió, filtrado si ya eligió.
  if (mostrarFranq && miRol === 'super_admin') {
    onEmpresaChange();
  }

  // Al cambiar a franquiciante (super_admin), refrescar también por si había
  // franquicias en pantalla de un cambio anterior.
  if (rol === 'franquiciante' && miRol === 'super_admin') {
    onEmpresaChange();
  }

  // v2.3: bloque de categorías del Socio comercial. Solo visible al CREAR un
  // franquiciado. En edición se gestiona desde el botón "Categorías" en la fila.
  const grupoCatsSocio = document.getElementById('grupo-categorias-socio');
  const mostrarCatsSocio = !modoEdicion && rol === 'franquiciado';
  grupoCatsSocio.style.display = mostrarCatsSocio ? 'block' : 'none';

  if (mostrarCatsSocio) {
    if (miRol === 'franquiciante') {
      // Empresa fija: la del actor. Cargamos las cats al instante.
      cargarCategoriasSocio(miEmpresaId);
    } else {
      // super_admin: arranca dependiente de la empresa actual del select.
      const empresaIdActual = document.getElementById('form-empresa').value;
      if (empresaIdActual) {
        cargarCategoriasSocio(parseInt(empresaIdActual, 10));
      } else {
        renderCategoriasSocio('empresa');
      }
    }
  }
}

function onEmpresaChange() {
  const empresaId = document.getElementById('form-empresa').value;
  const sel       = document.getElementById('form-franquicia');

  // Sin empresa: el select queda deshabilitado con un placeholder.
  if (!empresaId) {
    sel.innerHTML = '<option value="">Elegí primero una empresa</option>';
    sel.disabled  = true;
    return;
  }

  // Con empresa: filtrar franquicias y habilitar
  sel.disabled = false;
  const franqsFiltradas = todasLasFranquicias.filter(f => String(f.empresa_id) === empresaId);

  sel.innerHTML = '<option value="">Sin sucursal asignada</option>';
  franqsFiltradas.forEach(f => {
    const opt = document.createElement('option');
    opt.value = f.id; opt.textContent = f.nombre;
    sel.appendChild(opt);
  });

  // Hint útil cuando la empresa elegida no tiene sucursales cargadas todavía
  if (franqsFiltradas.length === 0) {
    sel.innerHTML += '<option value="" disabled>(esta empresa no tiene sucursales registradas)</option>';
  }

  // v2.3: si el rol elegido es franquiciado, recargamos las cats para la
  // nueva empresa. Esto vale solo en CREATE (super_admin).
  const rolActual = document.getElementById('form-rol').value;
  if (!modoEdicion && rolActual === 'franquiciado') {
    if (empresaId) cargarCategoriasSocio(parseInt(empresaId, 10));
    else           renderCategoriasSocio('empresa');
  }
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

// ── v2.3: CATEGORÍAS AL CREAR SOCIO COMERCIAL ─────────────────

async function cargarCategoriasSocio(empresaId) {
  if (!empresaId) {
    categoriasParaSocio = [];
    renderCategoriasSocio('empresa');
    return;
  }

  // Mostramos un loading mientras llega la respuesta
  document.getElementById('categorias-socio-lista').innerHTML =
    `<div style="font-size:12px;color:var(--gris4);font-family:'Roboto',sans-serif;padding:8px">Cargando categorías...</div>`;

  try {
    // super_admin pasa empresa_id explícito; franquiciante lo infiere el server.
    const url = (miRol === 'super_admin')
      ? `/categorias?empresa_id=${empresaId}&activa=1`
      : `/categorias?activa=1`;
    const cats = await apiFetch('GET', url);
    categoriasParaSocio = (cats || []).filter(c => c.is_active);
    renderCategoriasSocio(categoriasParaSocio.length ? 'lista' : 'vacio');
  } catch (e) {
    console.error('Error al cargar categorías para el Socio comercial:', e);
    categoriasParaSocio = [];
    renderCategoriasSocio('error');
  }
}

function renderCategoriasSocio(estado) {
  const cont = document.getElementById('categorias-socio-lista');

  if (estado === 'empresa') {
    cont.innerHTML = `<div style="font-size:12px;color:var(--gris4);font-family:'Roboto',sans-serif;padding:8px">Seleccioná primero una empresa.</div>`;
    return;
  }

  if (estado === 'vacio') {
    cont.innerHTML = `
      <div style="font-size:13px;color:var(--gris5);font-family:'Roboto',sans-serif;padding:8px;line-height:1.5">
        Esta empresa no tiene categorías. Creálas primero en
        <a href="categorias.php" style="color:var(--dorado);text-decoration:underline">categorías</a>.
      </div>`;
    return;
  }

  if (estado === 'error') {
    cont.innerHTML = `<div style="font-size:12px;color:#E25C5C;font-family:'Roboto',sans-serif;padding:8px">Error al cargar las categorías. Probá de nuevo.</div>`;
    return;
  }

  // estado === 'lista'
  cont.innerHTML = categoriasParaSocio.map(c => `
    <label class="cat-socio-item" style="display:flex;align-items:flex-start;gap:8px;padding:8px 10px;cursor:pointer;border-radius:5px;transition:background .12s" onmouseover="this.style.background='var(--gris2)'" onmouseout="this.style.background='transparent'">
      <input type="checkbox" data-cat-id="${c.id}" class="cat-socio-check" style="margin:0;margin-top:2px;cursor:pointer;accent-color:var(--dorado);flex-shrink:0">
      <div style="flex:1;min-width:0">
        <div style="font-size:13px;color:var(--blanco);font-weight:500">${esc(c.name)}</div>
        ${c.description ? `<div style="font-size:11px;color:var(--gris4);margin-top:2px;font-family:'Roboto',sans-serif;line-height:1.4">${esc(c.description)}</div>` : ''}
      </div>
    </label>
  `).join('');
}

function leerCategoriasSocioSeleccionadas() {
  const checks = document.querySelectorAll('.cat-socio-check:checked');
  return Array.from(checks).map(cb => parseInt(cb.dataset.catId, 10));
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
  if ((rol === 'franquiciado' || rol === 'empleado') && !modoEdicion && miRol === 'super_admin' && !empresaId) {
    mostrarFormError('Seleccioná una empresa.'); return;
  }
  // Empleado: la sucursal sigue siendo obligatoria.
  // Socio comercial (rol franquiciado): la sucursal es opcional — puede ser
  // distribuidor, dropshipper, etc. sin sucursal asignada.
  if (rol === 'empleado' && !franqId && miRol !== 'franquiciado') {
    mostrarFormError('Seleccioná una sucursal para el empleado.'); return;
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
    // empresa_id la manda super_admin al crear cualquier rol con empresa
    // (franquiciante, franquiciado o empleado). El franquiciante no la manda:
    // el backend toma la empresa del actor.
    if (!modoEdicion && miRol === 'super_admin' && empresaId) body.empresa_id = empresaId;

    if (modoEdicion) {
      await apiFetch('PUT', `/usuarios/${id}`, body);
      mostrarToast('Usuario actualizado correctamente.', 'exito');
    } else {
      const nuevoUsuario = await apiFetch('POST', '/usuarios', body);

      // v2.3: si el rol es franquiciado, sincronizamos las cats seleccionadas.
      // El POST anterior ya creó el usuario; si el PUT falla, el usuario igual
      // queda creado y el admin lo puede editar después desde la fila.
      if (rol === 'franquiciado' && nuevoUsuario?.id) {
        const catsSeleccionadas = leerCategoriasSocioSeleccionadas();
        if (catsSeleccionadas.length > 0) {
          try {
            await apiFetch('PUT', `/usuarios/${nuevoUsuario.id}/categorias`, {
              category_ids: catsSeleccionadas,
            });
          } catch (errCat) {
            console.error('Falló sync de categorías del Socio comercial:', errCat);
            const msg = errCat?.data?.error || errCat?.data?.message || 'Error desconocido';
            alert(
              `El usuario fue creado, pero falló la asignación de categorías:\n\n${msg}\n\n` +
              `Podés editar las categorías desde el botón "Categorías" en la fila del usuario.`
            );
          }
        }
      }

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
  // v2.3: nombre/apellido viven en users
  const nombreFull = u ? [u.nombre, u.apellido].filter(Boolean).join(' ').trim() : '';
  const nombre     = nombreFull || u?.email;
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
      lista.innerHTML = `<div style="text-align:center;padding:24px;font-size:13px;color:var(--gris4);font-family:'Roboto',sans-serif">No tiene manuales asignados todavía.</div>`;
      return;
    }

    lista.innerHTML = asignaciones.map(a => {
      const manual = a.manual;
      const version = manual?.version_activa?.[0];
      return `<div style="display:flex;align-items:center;justify-content:space-between;padding:10px 12px;background:var(--negro);border:1px solid var(--gris2);border-radius:8px;margin-bottom:8px">
        <div>
          <div style="font-size:13px;color:var(--blanco);font-weight:500">${esc(manual?.titulo || '—')}</div>
          <div style="font-size:11px;color:var(--gris4);margin-top:2px;font-family:'Roboto',sans-serif">
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

// ── MODAL CATEGORÍAS (solo franquiciado) ───────────────────────
async function abrirModalCategorias(userId, userNombre, empresaId) {
  categoriasUsuarioSel = { id: userId, nombre: userNombre, empresaId: empresaId };

  document.getElementById('categorias-titulo').textContent    = `Categorías de ${userNombre}`;
  document.getElementById('categorias-subtitulo').textContent = 'Asigná las categorías que aplican a este franquiciado';
  document.getElementById('categorias-error').style.display   = 'none';
  document.getElementById('modal-categorias').classList.add('open');

  await cargarCategoriasUsuario();
}

function cerrarModalCategorias() {
  const modal = document.getElementById('modal-categorias');
  if (modal) modal.classList.remove('open');
  categoriasUsuarioSel = null;
}

async function cargarCategoriasUsuario() {
  const lista = document.getElementById('lista-categorias');
  lista.innerHTML = `<div class="loading-msg"><div class="spinner" style="display:block"></div>Cargando...</div>`;

  try {
    // 1. Catálogo de la empresa. super_admin manda ?empresa_id; franquiciante ya viene filtrado por su empresa.
    const empresaId = categoriasUsuarioSel.empresaId;
    const urlCats = (miRol === 'super_admin' && empresaId)
      ? `/categorias?empresa_id=${empresaId}&activa=1`
      : `/categorias?activa=1`;

    const todas = await apiFetch('GET', urlCats);
    categoriasDisponibles = (todas || []).filter(c => c.is_active);

    // 2. Categorías que el usuario ya tiene asignadas
    const actuales = await apiFetch('GET', `/usuarios/${categoriasUsuarioSel.id}/categorias`);
    const idsActuales = new Set((actuales || []).map(c => c.id));

    if (!categoriasDisponibles.length) {
      lista.innerHTML = `<div style="text-align:center;padding:24px;font-size:13px;color:var(--gris4);font-family:'Roboto',sans-serif">
        No hay categorías activas en esta empresa.<br>
        <a href="${BASE_URL}/categorias.php" style="color:var(--dorado);text-decoration:underline">Creá una categoría primero.</a>
      </div>`;
      return;
    }

    lista.innerHTML = categoriasDisponibles.map(c => `
      <label style="display:flex;align-items:center;gap:10px;padding:10px 12px;background:var(--negro);border:1px solid var(--gris2);border-radius:8px;margin-bottom:8px;cursor:pointer;transition:border-color .15s"
             onmouseover="this.style.borderColor='var(--gris3)'"
             onmouseout="this.style.borderColor='var(--gris2)'">
        <input type="checkbox" data-cat-id="${c.id}" ${idsActuales.has(c.id) ? 'checked' : ''} style="margin:0;cursor:pointer;accent-color:var(--dorado)">
        <div style="flex:1">
          <div style="font-size:13px;color:var(--blanco);font-weight:500">${esc(c.name)}</div>
          ${c.description ? `<div style="font-size:13px;color:var(--gris4);margin-top:2px;font-family:'Roboto',sans-serif">${esc(c.description)}</div>` : ''}
        </div>
      </label>
    `).join('');

  } catch (e) {
    lista.innerHTML = `<div style="font-size:13px;color:var(--error);padding:12px">Error al cargar las categorías.</div>`;
  }
}

async function guardarCategorias() {
  if (!categoriasUsuarioSel) return;
  const btn   = document.getElementById('btn-guardar-categorias');
  const errEl = document.getElementById('categorias-error');
  errEl.style.display = 'none';

  const checkboxes = document.querySelectorAll('#lista-categorias input[type=checkbox]');
  const seleccion  = Array.from(checkboxes).filter(cb => cb.checked).map(cb => parseInt(cb.dataset.catId));

  const labelOriginal = btn.innerHTML;
  btn.disabled = true;
  btn.textContent = 'Guardando...';

  try {
    await apiFetch('PUT', `/usuarios/${categoriasUsuarioSel.id}/categorias`, { category_ids: seleccion });
    mostrarToast('Categorías actualizadas.', 'exito');
    cerrarModalCategorias();
  } catch (e) {
    errEl.textContent  = e.data?.error || e.data?.message || 'Error al guardar las categorías.';
    errEl.style.display = 'block';
  } finally {
    btn.disabled  = false;
    btn.innerHTML = labelOriginal;
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
  if (e.key === 'Escape') { cerrarModal(); cerrarModalToggle(); cerrarModalManuales(); cerrarModalCategorias(); }
});

init();
</script>

<?php include 'layout/footer.php'; ?>