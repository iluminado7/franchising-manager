<?php
require_once __DIR__ . '/layout/config.php';
require_once __DIR__ . '/layout/auth.php';
verificarSesion();
$titulo        = 'Mi perfil';
$pagina_actual = 'perfil';
include 'layout/head.php';
?>

<div class="app-layout">
  <?php include 'layout/topbar.php'; ?>
  <div class="app-body">
    <?php include 'layout/sidebar.php'; ?>
    <main class="main-content">

      <div class="page-header">
        <div>
          <div class="page-title">Mi perfil</div>
          <div class="page-sub" id="page-sub">Cargando...</div>
        </div>
      </div>

      <div class="perfil-grid">

        <!-- ── COLUMNA IZQUIERDA ─────────────────────────────── -->
        <div class="perfil-col">

          <!-- Tarjeta datos personales -->
          <div class="card-perfil">
            <div class="card-perfil-header">
              <div class="avatar" id="avatar-wrap">
                <img id="avatar-foto" alt="" style="display:none">
                <span id="avatar-iniciales"></span>
              </div>
              <div>
                <div class="perfil-nombre" id="perfil-nombre">—</div>
                <div id="perfil-rol-badge" style="margin:4px 0"></div>
                <div class="foto-acciones">
                  <button type="button" class="foto-btn" onclick="document.getElementById('input-foto').click()">Cambiar foto</button>
                  <button type="button" class="foto-btn foto-btn-quitar" id="btn-quitar-foto" onclick="quitarFoto()" style="display:none">Quitar</button>
                </div>
                <input type="file" id="input-foto" accept="image/jpeg,image/png,image/webp" style="display:none" onchange="onSeleccionarFoto(this)">
              </div>
            </div>

            <div class="datos-grid">
              <div class="dato-item">
                <span class="dato-label">Email</span>
                <span class="dato-valor" id="dato-email">—</span>
              </div>
              <div class="dato-item">
                <span class="dato-label">DNI</span>
                <span class="dato-valor" id="dato-dni">—</span>
              </div>
              <div class="dato-item" id="dato-empresa-wrap" style="display:none">
                <span class="dato-label">Empresa</span>
                <span class="dato-valor" id="dato-empresa">—</span>
              </div>
              <div class="dato-item" id="dato-franquicia-wrap" style="display:none">
                <span class="dato-label">Franquicia</span>
                <span class="dato-valor" id="dato-franquicia">—</span>
              </div>
            </div>
          </div>

          <!-- Tarjeta cambiar email -->
          <div class="card-perfil" id="card-email" autocomplete="off">
            <div class="card-perfil-titulo">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,12 2,6"/></svg>
              Cambiar email
            </div>
            <div class="form-group">
              <label>Nuevo email</label>
              <input type="email" id="nuevo-email" placeholder="nuevo@email.com" maxlength="200"  autocomplete="new-email" autocorrect="off" autocapitalize="off" spellcheck="false">
            </div>
            <div class="form-group">
              <label>Contraseña actual (para confirmar)</label>
              <div class="pass-wrap">
                <input type="password" id="confirm-pass-email" placeholder="Tu contraseña actual" maxlength="100" readonly
  onfocus="this.removeAttribute('readonly')">
                <button type="button" class="eye-btn" onclick="togglePass('confirm-pass-email', 'eye-email')">
                  <svg id="eye-email" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
              </div>
            </div>
            <div class="form-error" id="error-email" style="display:none"></div>
            <div class="form-exito" id="exito-email" style="display:none"></div>
            <button class="btn btn-primary btn-sm" onclick="cambiarEmail()">Actualizar email</button>
          </div>

          <!-- Tarjeta cambiar contraseña -->
          <div class="card-perfil" id="card-pass">
            <div class="card-perfil-titulo">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
              Cambiar contraseña
            </div>
            <div class="form-group">
              <label>Contraseña actual</label>
              <div class="pass-wrap">
                <input type="password" id="pass-actual" placeholder="Tu contraseña actual" maxlength="100"  readonly
  onfocus="this.removeAttribute('readonly')">
                <button type="button" class="eye-btn" onclick="togglePass('pass-actual', 'eye-actual')">
                  <svg id="eye-actual" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
              </div>
            </div>
            <div class="form-group">
              <label>Nueva contraseña</label>
              <div class="pass-wrap">
                <input type="password" id="pass-nueva" placeholder="Mínimo 8 caracteres" maxlength="100"  readonly
  onfocus="this.removeAttribute('readonly')">
                <button type="button" class="eye-btn" onclick="togglePass('pass-nueva', 'eye-nueva')">
                  <svg id="eye-nueva" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
              </div>
            </div>
            <div class="form-group">
              <label>Repetir nueva contraseña</label>
              <div class="pass-wrap">
                <input type="password" id="pass-repetir" placeholder="Repetí la nueva contraseña" maxlength="100"  readonly
  onfocus="this.removeAttribute('readonly')">
                <button type="button" class="eye-btn" onclick="togglePass('pass-repetir', 'eye-repetir')">
                  <svg id="eye-repetir" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
              </div>
            </div>
            <div class="form-error" id="error-pass" style="display:none"></div>
            <div class="form-exito" id="exito-pass" style="display:none"></div>
            <button class="btn btn-primary btn-sm" onclick="cambiarPassword()">Actualizar contraseña</button>
          </div>

        </div>

        <!-- ── COLUMNA DERECHA: solo franquiciante ────────────── -->
        <div class="perfil-col" id="col-facturacion" style="display:none">

          <!-- Plan activo -->
          <div class="card-perfil" id="card-plan">
            <div class="card-perfil-titulo">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
              Plan activo
            </div>
            <div id="plan-contenido">
              <div class="loading-msg"><div class="spinner" style="display:block"></div>Cargando...</div>
            </div>
          </div>

          <!-- Historial de facturas -->
          <div class="card-perfil">
            <div class="card-perfil-titulo">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
              Historial de facturación
            </div>
            <div id="facturas-contenido">
              <div class="loading-msg"><div class="spinner" style="display:block"></div>Cargando...</div>
            </div>
          </div>

        </div>

      </div><!-- /perfil-grid -->

    <!-- Cropper.js (CDN) para encuadrar la foto antes de subir -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js"></script>

    <!-- Modal de encuadre -->
    <div class="cropper-overlay" id="cropper-modal" style="display:none">
      <div class="cropper-box">
        <div class="cropper-header">Encuadrá tu foto</div>
        <div class="cropper-area">
          <img id="cropper-img" alt="">
        </div>
        <div class="cropper-controles">
          <button type="button" class="foto-btn" onclick="cropperInstance && cropperInstance.zoom(0.1)">Acercar +</button>
          <button type="button" class="foto-btn" onclick="cropperInstance && cropperInstance.zoom(-0.1)">Alejar −</button>
        </div>
        <div class="cropper-acciones">
          <button type="button" class="foto-btn" onclick="cerrarCropper()">Cancelar</button>
          <button type="button" class="foto-btn foto-btn-aplicar" id="btn-aplicar-crop" onclick="aplicarCrop()">Aplicar y subir</button>
        </div>
      </div>
    </div>

    </main>
  </div>
</div>

<!-- ── TOAST ──────────────────────────────────────────────────── -->
<div class="toast" id="toast"><span id="toast-icon"></span><span id="toast-msg"></span></div>

<style>
/* ── Layout ──────────────────────────────────────────────── */
.perfil-grid {
  display: grid;
  grid-template-columns: 380px 1fr;
  gap: 20px;
  align-items: start;
}
@media (max-width: 860px) {
  .perfil-grid { grid-template-columns: 1fr; }
}
.perfil-col { display: flex; flex-direction: column; gap: 16px; }

/* ── Card ─────────────────────────────────────────────────── */
.card-perfil {
  background: var(--gris1);
  border: 1px solid var(--gris2);
  border-radius: 12px;
  padding: 20px;
}
.card-perfil-header {
  display: flex;
  align-items: center;
  gap: 16px;
  margin-bottom: 20px;
  padding-bottom: 20px;
  border-bottom: 1px solid var(--gris2);
}
.card-perfil-titulo {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: .07em;
  text-transform: uppercase;
  color: var(--gris4);
  margin-bottom: 16px;
}

/* ── Avatar ───────────────────────────────────────────────── */
.avatar {
  width: 52px; height: 52px;
  border-radius: 50%;
  background: rgba(201,168,76,.15);
  border: 1px solid rgba(201,168,76,.25);
  display: flex; align-items: center; justify-content: center;
  font-size: 18px; font-weight: 700;
  color: var(--dorado);
  flex-shrink: 0;
  font-family: 'Archivo', sans-serif;
  letter-spacing: .02em;
}
#avatar-wrap { position: relative; overflow: hidden; }
#avatar-foto { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
.foto-acciones { display: flex; gap: 8px; margin-top: 6px; }
.foto-btn {
  background: transparent; border: 1px solid var(--gris2); border-radius: 6px;
  color: var(--gris5); cursor: pointer; font-size: 11px; padding: 3px 10px;
  font-family: 'Roboto', sans-serif; transition: border-color .12s, color .12s;
}
.foto-btn:hover { border-color: var(--dorado); color: var(--blanco); }
.foto-btn-quitar:hover { border-color: #c0392b; color: #e57373; }
.cropper-overlay {
  position: fixed; inset: 0; z-index: 1000;
  display: none; align-items: center; justify-content: center;
  background: rgba(0,0,0,.7);
}
.cropper-box {
  background: var(--gris1); border: 1px solid var(--gris2); border-radius: 12px;
  padding: 20px; width: 92%; max-width: 420px;
}
.cropper-header { font-size: 15px; font-weight: 600; color: var(--blanco); margin-bottom: 14px; font-family: 'Archivo', sans-serif; }
.cropper-area { max-height: 360px; }
.cropper-area img { max-width: 100%; display: block; }
.cropper-controles { display: flex; gap: 8px; justify-content: center; margin: 12px 0; }
.cropper-acciones { display: flex; gap: 10px; justify-content: flex-end; margin-top: 8px; }
.foto-btn-aplicar { border-color: var(--dorado); color: var(--dorado); }
.foto-btn-aplicar:hover { background: var(--dorado); color: #1a1a1a; }
/* Mascara circular del recorte */
.cropper-view-box, .cropper-face { border-radius: 50%; }
.perfil-nombre {
  font-size: 16px;
  font-weight: 600;
  color: var(--blanco);
  margin-bottom: 5px;
}
.perfil-rol {
  display: inline-flex;
}

/* ── Datos ────────────────────────────────────────────────── */
.datos-grid { display: flex; flex-direction: column; gap: 12px; }
.dato-item {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  gap: 12px;
  padding: 10px 12px;
  background: var(--negro);
  border-radius: 7px;
  border: 1px solid var(--gris2);
}
.dato-label {
  font-size: 11px;
  font-weight: 500;
  letter-spacing: .06em;
  text-transform: uppercase;
  color: var(--gris4);
  white-space: nowrap;
}
.dato-valor {
  font-size: 13px;
  color: var(--blanco);
  font-family: 'Roboto', sans-serif;
  text-align: right;
  word-break: break-all;
}

/* ── Formulario ───────────────────────────────────────────── */
.form-group { margin-bottom: 14px; }
.form-group:last-of-type { margin-bottom: 16px; }
.form-group label {
  display: block;
  font-size: 11px; font-weight: 500;
  letter-spacing: .06em; text-transform: uppercase;
  color: var(--gris5); margin-bottom: 6px;
}
.form-group input[type=email],
.form-group input[type=password],
.form-group input[type=text] {
  width: 100%;
  background: var(--negro); border: 1px solid var(--gris2);
  border-radius: 7px; padding: 10px 12px;
  font-size: 13px; font-family: 'Archivo', sans-serif;
  color: var(--blanco); outline: none; transition: border-color .2s;
  box-sizing: border-box;
}
.form-group input:focus { border-color: var(--dorado); }
.form-group input::placeholder { color: var(--gris3); }
.pass-wrap { position: relative; }
.pass-wrap input { padding-right: 40px; }
.eye-btn {
  position: absolute; right: 0; top: 0;
  height: 100%; width: 38px;
  background: transparent; border: none;
  cursor: pointer; color: var(--gris4);
  display: flex; align-items: center; justify-content: center;
  transition: color .2s;
}
.eye-btn:hover { color: var(--blanco); }
.form-error {
  background: rgba(226,92,92,.1); border: 1px solid rgba(226,92,92,.3);
  border-radius: 7px; padding: 9px 12px;
  font-size: 12px; color: var(--error);
  margin-bottom: 12px; line-height: 1.5;
}
.form-exito {
  background: rgba(76,175,80,.1); border: 1px solid rgba(76,175,80,.3);
  border-radius: 7px; padding: 9px 12px;
  font-size: 12px; color: var(--exito);
  margin-bottom: 12px; line-height: 1.5;
}
.btn-sm { padding: 8px 16px; font-size: 12px; }

/* ── Plan ─────────────────────────────────────────────────── */
.plan-card {
  background: var(--negro);
  border: 1px solid rgba(201,168,76,.2);
  border-radius: 9px;
  padding: 16px;
}
.plan-nombre {
  font-size: 15px; font-weight: 700;
  color: var(--dorado); margin-bottom: 10px;
}
.plan-detalle {
  font-size: 12px; color: var(--gris4);
  font-family: 'Roboto', sans-serif;
  line-height: 1.7;
}
.plan-precio {
  font-size: 22px; font-weight: 700;
  color: var(--blanco); margin-top: 12px;
}
.plan-precio span {
  font-size: 12px; font-weight: 400;
  color: var(--gris4); margin-left: 4px;
}

/* ── Facturas ─────────────────────────────────────────────── */
.facturas-tabla { width: 100%; border-collapse: collapse; }
.facturas-tabla th {
  font-size: 10px; font-weight: 600;
  letter-spacing: .07em; text-transform: uppercase;
  color: var(--gris4); padding: 6px 10px;
  text-align: left; border-bottom: 1px solid var(--gris2);
}
.facturas-tabla td {
  font-size: 12px; color: var(--gris5);
  padding: 10px 10px; border-bottom: 1px solid var(--gris2);
  font-family: 'Roboto', sans-serif;
  vertical-align: middle;
}
.facturas-tabla tr:last-child td { border-bottom: none; }
.facturas-tabla tr:hover td { background: rgba(255,255,255,.02); }
.factura-total {
  font-size: 13px; font-weight: 600;
  color: var(--blanco);
}

/* ── Toast ────────────────────────────────────────────────── */
.toast {
  position: fixed; bottom: 24px; right: 24px;
  background: var(--gris1); border: 1px solid var(--gris2);
  border-radius: 10px; padding: 12px 16px;
  font-size: 13px; color: var(--blanco);
  display: flex; align-items: center; gap: 10px;
  transform: translateY(80px); opacity: 0;
  transition: transform .3s, opacity .3s;
  z-index: 600; font-family: 'Roboto', sans-serif; max-width: 320px;
}
.toast.show { transform: translateY(0); opacity: 1; }
</style>

<script>
let miPerfil = null;

// ── INIT ──────────────────────────────────────────────────────
async function init() {
  try {
    miPerfil = await apiFetch('GET', '/me');
    renderPerfil(miPerfil);

    const empresaFacturable = miPerfil.empresa?.facturable !== false;

    if (miPerfil.rol === 'franquiciante' && empresaFacturable) {
      document.getElementById('col-facturacion').style.display = 'flex';
      cargarFacturacion(miPerfil);  // pasamos el perfil completo, empresa ya viene adentro
    }
  } catch (e) {
    document.getElementById('page-sub').textContent = 'Error al cargar el perfil.';
  }
}

// ── RENDER PERFIL ─────────────────────────────────────────────
function renderPerfil(u) {
  // v2.3: nombre/apellido/dni viven en users (toplevel de /me). El campo `perfil`
  // sigue trayendo la franquicia para franquiciado/empleado.
  const perfil    = u.perfil;
  const nombreFull= [u.nombre, u.apellido].filter(Boolean).join(' ').trim();
  const nombre    = nombreFull || u.email;
  const iniciales = (u.nombre && u.apellido)
    ? `${u.nombre[0]}${u.apellido[0]}`.toUpperCase()
    : (u.email ? u.email[0].toUpperCase() : '?');

  document.getElementById('avatar-iniciales').textContent = iniciales;
  document.getElementById('perfil-nombre').textContent    = nombre;
  aplicarAvatar(u, !!u.avatar_url);
  document.getElementById('dato-email').textContent       = u.email;
  document.getElementById('dato-dni').textContent         = u.dni || '—';

  // Badge de rol
  const labels = { super_admin: 'Super Admin', franquiciante: 'Franquiciante', franquiciado: 'Franquiciado', empleado: 'Empleado' };
  document.getElementById('perfil-rol-badge').innerHTML =
    `<span class="rol-badge ${u.rol}">${labels[u.rol] || u.rol}</span>`;

  document.getElementById('page-sub').textContent = `Perfil de ${nombre}`;

  // Empresa (franquiciante) — viene en u.empresa desde /me
  if (u.rol === 'franquiciante' && u.empresa) {
    document.getElementById('dato-empresa-wrap').style.display = 'flex';
    cargarNombreEmpresa(u.empresa);
  }

  // Franquicia (franquiciado / empleado) — viene en perfil.franquicia (ya cargada por me())
  if ((u.rol === 'franquiciado' || u.rol === 'empleado') && perfil?.franquicia) {
    document.getElementById('dato-franquicia-wrap').style.display = 'flex';
    document.getElementById('dato-franquicia').textContent = perfil.franquicia.nombre || '—';
  }
}

async function cargarNombreEmpresa(empresa) {
  // empresa ya viene en /me para franquiciante
  if (empresa?.nombre) {
    document.getElementById('dato-empresa').textContent = empresa.nombre;
  }
}

// ── FACTURACIÓN (solo franquiciante) ──────────────────────────
async function cargarFacturacion(u) {
  const empresa = u.empresa;  // viene en /me
 // Empresa exenta: no hay plan ni facturas que mostrar. Salimos sin pegarle a /invoices.
  if (empresa && empresa.facturable === false) {
    document.getElementById('col-facturacion').style.display = 'none';
    return;
  }
  
  if (empresa) {
    renderPlan(empresa);
    document.getElementById('dato-empresa').textContent = empresa.nombre || '—';
  } else {
    document.getElementById('plan-contenido').innerHTML =
      '<p style="font-size:13px;color:var(--gris4)">Sin empresa asignada.</p>';
  }

  try {
    const invoices = await apiFetch('GET', '/invoices');
    renderFacturas(invoices);
  } catch (e) {
    document.getElementById('facturas-contenido').innerHTML =
      '<p style="font-size:13px;color:var(--gris4)">Error al cargar las facturas.</p>';
  }
}

function renderPlan(empresa) {
  const plan = empresa.plan;
  if (!plan) {
    document.getElementById('plan-contenido').innerHTML =
      '<p style="font-size:13px;color:var(--gris4)">Sin plan asignado.</p>';
    return;
  }

  const esPorFranquicia = plan.tipo_plan === 'por_franquicia';
  const precio = empresa.precio_custom_por_franquicia || empresa.precio_custom_global || plan.precio_base_por_franquicia || plan.precio_global;
  const precioFmt = precio ? `$${Number(precio).toLocaleString('es-AR')}` : '—';
  const detalle   = esPorFranquicia
    ? `${empresa.franquicias_activas_count ?? '—'} franquicias activas × ${precioFmt}`
    : 'Precio fijo mensual';

  document.getElementById('plan-contenido').innerHTML = `
    <div class="plan-card">
      <div class="plan-nombre">${esc(plan.nombre || plan.tipo_plan)}</div>
      <div class="plan-detalle">
        Tipo: ${esPorFranquicia ? 'Por franquicia' : 'Global'}<br>
        ${detalle}
      </div>
      <div class="plan-precio">${precioFmt}<span>${esPorFranquicia ? '/ franquicia' : '/ mes'}</span></div>
    </div>`;
}

function renderFacturas(invoices) {
  const cont = document.getElementById('facturas-contenido');
  if (!invoices || !invoices.length) {
    cont.innerHTML = '<p style="font-size:13px;color:var(--gris4);padding:4px 0">Aún no hay facturas registradas.</p>';
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

  cont.innerHTML = `
    <div style="overflow-x:auto">
      <table class="facturas-tabla">
        <thead>
          <tr>
            <th>Nº Factura</th>
            <th>Período</th>
            <th>Franq.</th>
            <th>Total</th>
            <th>Estado</th>
          </tr>
        </thead>
        <tbody>
          ${invoices.map(inv => `
            <tr>
              <td style="font-family:'Archivo',sans-serif;font-size:12px;color:var(--blanco)">${esc(inv.numero_factura || '—')}</td>
              <td>${esc(inv.periodo || '—')}</td>
              <td style="text-align:center">${inv.franquicias_activas ?? '—'}</td>
              <td class="factura-total">$${Number(inv.total || 0).toLocaleString('es-AR')}</td>
              <td>${estadoBadge(inv.estado)}</td>
            </tr>`).join('')}
        </tbody>
      </table>
    </div>`;
}

// ── CAMBIAR EMAIL ─────────────────────────────────────────────
async function cambiarEmail() {
  const nuevoEmail    = document.getElementById('nuevo-email').value.trim();
  const passConfirm   = document.getElementById('confirm-pass-email').value;
  const errEl         = document.getElementById('error-email');
  const exitoEl       = document.getElementById('exito-email');

  errEl.style.display = 'none';
  exitoEl.style.display = 'none';

  if (!nuevoEmail)  { mostrarErr(errEl, 'Ingresá el nuevo email.'); return; }
  if (!passConfirm) { mostrarErr(errEl, 'Ingresá tu contraseña actual para confirmar.'); return; }
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(nuevoEmail)) {
    mostrarErr(errEl, 'El email no tiene un formato válido.'); return;
  }

  try {
    await apiFetch('PUT', '/me/email', { email: nuevoEmail, password: passConfirm });
    document.getElementById('dato-email').textContent = nuevoEmail;
    document.getElementById('nuevo-email').value      = '';
    document.getElementById('confirm-pass-email').value = '';
    exitoEl.textContent = 'Email actualizado correctamente.';
    exitoEl.style.display = 'block';
    mostrarToast('Email actualizado.', 'exito');
  } catch (e) {
    const msg = e.data?.errors
      ? Object.values(e.data.errors).flat().join(' ')
      : e.data?.message || 'Error al actualizar el email.';
    mostrarErr(errEl, msg);
  }
}

// ── CAMBIAR CONTRASEÑA ────────────────────────────────────────
async function cambiarPassword() {
  const actual  = document.getElementById('pass-actual').value;
  const nueva   = document.getElementById('pass-nueva').value;
  const repetir = document.getElementById('pass-repetir').value;
  const errEl   = document.getElementById('error-pass');
  const exitoEl = document.getElementById('exito-pass');

  errEl.style.display   = 'none';
  exitoEl.style.display = 'none';

  if (!actual)           { mostrarErr(errEl, 'Ingresá tu contraseña actual.'); return; }
  if (!nueva)            { mostrarErr(errEl, 'Ingresá la nueva contraseña.'); return; }
  if (nueva.length < 8)  { mostrarErr(errEl, 'La nueva contraseña debe tener al menos 8 caracteres.'); return; }
  if (nueva !== repetir) { mostrarErr(errEl, 'Las contraseñas no coinciden.'); return; }
  if (nueva === actual)  { mostrarErr(errEl, 'La nueva contraseña debe ser diferente a la actual.'); return; }

  try {
    await apiFetch('PUT', '/me/password', {
      current_password: actual,
      password: nueva,
      password_confirmation: repetir,
    });
    ['pass-actual','pass-nueva','pass-repetir'].forEach(id =>
      document.getElementById(id).value = '');
    exitoEl.textContent = 'Contraseña actualizada correctamente.';
    exitoEl.style.display = 'block';
    mostrarToast('Contraseña actualizada.', 'exito');
  } catch (e) {
    const msg = e.data?.errors
      ? Object.values(e.data.errors).flat().join(' ')
      : e.data?.message || 'Error al actualizar la contraseña.';
    mostrarErr(errEl, msg);
  }
}

// ── HELPERS ───────────────────────────────────────────────────
function mostrarErr(el, msg) {
  el.textContent    = msg;
  el.style.display  = 'block';
}

function togglePass(inputId, eyeId) {
  const inp = document.getElementById(inputId);
  const eye = document.getElementById(eyeId);
  const visible = inp.type === 'text';
  inp.type = visible ? 'password' : 'text';
  eye.innerHTML = visible
    ? `<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>`
    : `<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>`;
}

let toastTimer;
// ── FOTO DE PERFIL ────────────────────────────────────────────
// Multipart directo: apiFetch no sirve para FormData (setea Content-Type JSON).
async function fetchMultipart(endpoint, formData) {
  const res = await fetch(API + endpoint, { method: 'POST', credentials: 'include', body: formData });
  const data = await res.json().catch(() => ({}));
  if (!res.ok) { const err = new Error(); err.data = data; throw err; }
  return data;
}

// Muestra la foto (via el endpoint autenticado) o cae a las iniciales.
function aplicarAvatar(u, tieneFoto) {
  const img = document.getElementById('avatar-foto');
  const ini = document.getElementById('avatar-iniciales');
  const btnQuitar = document.getElementById('btn-quitar-foto');
  if (tieneFoto) {
    // ?t= fuerza recarga tras subir/quitar (el endpoint tiene Cache-Control).
    img.src = API + '/perfil/foto/' + u.id + '?t=' + Date.now();
    img.style.display = 'block';
    ini.style.display = 'none';
    if (btnQuitar) btnQuitar.style.display = '';
  } else {
    const iniciales = (u.nombre && u.apellido)
      ? `${u.nombre[0]}${u.apellido[0]}`.toUpperCase()
      : (u.email ? u.email[0].toUpperCase() : '?');
    ini.textContent = iniciales;
    img.style.display = 'none';
    img.removeAttribute('src');
    ini.style.display = '';
    if (btnQuitar) btnQuitar.style.display = 'none';
  }
}

let cropperInstance = null;

// Al elegir archivo: validar y abrir el modal de encuadre (no sube todavia).
function onSeleccionarFoto(input) {
  const file = input.files && input.files[0];
  if (!file) return;
  const tiposOk = ['image/jpeg', 'image/png', 'image/webp'];
  if (!tiposOk.includes(file.type)) {
    mostrarToast('Formato no válido. Usá JPG, PNG o WebP.', 'error');
    input.value = ''; return;
  }
  if (file.size > 5 * 1024 * 1024) {
    mostrarToast('La imagen supera los 5 MB.', 'error');
    input.value = ''; return;
  }
  if (typeof Cropper === 'undefined') {
    mostrarToast('No se pudo cargar el editor de imagen.', 'error');
    input.value = ''; return;
  }

  const img = document.getElementById('cropper-img');
  img.src = URL.createObjectURL(file);
  document.getElementById('cropper-modal').style.display = 'flex';

  if (cropperInstance) cropperInstance.destroy();
  cropperInstance = new Cropper(img, {
    aspectRatio: 1,
    viewMode: 1,
    dragMode: 'move',
    autoCropArea: 1,
    background: false,
    cropBoxResizable: false,
    cropBoxMovable: false,
    minContainerHeight: 300,
  });
  input.value = '';
}

function cerrarCropper() {
  if (cropperInstance) { cropperInstance.destroy(); cropperInstance = null; }
  const img = document.getElementById('cropper-img');
  if (img.src) { URL.revokeObjectURL(img.src); img.removeAttribute('src'); }
  document.getElementById('cropper-modal').style.display = 'none';
}

// Recorta a 512x512 en el cliente y sube el resultado.
async function aplicarCrop() {
  if (!cropperInstance) return;
  const btn = document.getElementById('btn-aplicar-crop');
  btn.disabled = true; btn.textContent = 'Subiendo...';

  const canvas = cropperInstance.getCroppedCanvas({
    width: 512, height: 512, imageSmoothingQuality: 'high',
  });
  if (!canvas) {
    mostrarToast('No se pudo procesar la imagen.', 'error');
    btn.disabled = false; btn.textContent = 'Aplicar y subir'; return;
  }

  canvas.toBlob(async (blob) => {
    try {
      const fd = new FormData();
      fd.append('foto', blob, 'avatar.jpg');
      await fetchMultipart('/perfil/foto', fd);
      if (miPerfil) miPerfil.avatar_url = '/api/perfil/foto/' + miPerfil.id;
      aplicarAvatar(miPerfil, true);
      mostrarToast('Foto de perfil actualizada.', 'exito');
      cerrarCropper();
    } catch (e) {
      mostrarToast('No se pudo subir la foto.', 'error');
      btn.disabled = false; btn.textContent = 'Aplicar y subir';
    }
  }, 'image/jpeg', 0.9);
}

async function quitarFoto() {
  if (!confirm('¿Quitar tu foto de perfil?')) return;
  try {
    await apiFetch('DELETE', '/perfil/foto');
    if (miPerfil) miPerfil.avatar_url = null;
    aplicarAvatar(miPerfil, false);
    mostrarToast('Foto de perfil eliminada.', 'exito');
  } catch (e) {
    mostrarToast('No se pudo quitar la foto.', 'error');
  }
}

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

init();
</script>

<?php include 'layout/footer.php'; ?>