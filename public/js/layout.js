// ── LAYOUT GLOBAL ─────────────────────────────────────────────
// Se ejecuta en todas las páginas del panel

// ── ESCAPE HELPER (H-020 fix) ────────────────────────────────
// Cualquier string que venga del backend y se inyecte con innerHTML DEBE
// pasar por esta función para prevenir XSS almacenado. Firma idéntica a la
// usada en documentos.php/manuales.php/etc.
function esc(str) {
  if (!str) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

// ── TEMA ─────────────────────────────────────────────────────
function aplicarTema() {
  const esClaro = localStorage.getItem('tema') === 'claro';
  document.body.classList.toggle('tema-claro', esClaro);
}

function toggleTema() {
  const esClaro = document.body.classList.toggle('tema-claro');
  localStorage.setItem('tema', esClaro ? 'claro' : 'oscuro');
  const labelEl = document.getElementById('label-tema');
  const iconEl  = document.getElementById('icon-tema');
  if (labelEl) labelEl.textContent = esClaro ? 'Modo oscuro' : 'Modo claro';
  if (iconEl)  iconEl.innerHTML    = esClaro
    ? `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>`
    : `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>`;
}

aplicarTema();

// ── SIDEBAR ───────────────────────────────────────────────────
function renderSidebar(rol) {
  const el = document.getElementById('sidebar-nav');
  if (!el) return;

  const NAV = {
    super_admin: [
      { id: 'dashboard',   label: 'Inicio',       href: 'dashboard.php',   icon: 'home'        },
      { id: 'empresas',    label: 'Empresas',         href: 'empresas.php',    icon: 'building'    },
      { id: 'franquicias', label: 'Franquicias',      href: 'franquicias.php', icon: 'store'    },
      { id: 'usuarios',    label: 'Usuarios',         href: 'usuarios.php',    icon: 'users'       },
      { id: 'manuales',    label: 'Manuales',         href: 'manuales.php',    icon: 'file-text'   },
      { id: 'documentos',  label: 'Documentos',       href: 'documentos.php',  icon: 'folder'      },
      { id: 'aceptaciones', label: 'Aceptaciones',    href: 'aceptaciones.php', icon: 'check-circle' },
      { id: 'categorias',  label: 'Categorías',       href: 'categorias.php',  icon: 'tag'         },
      { id: 'planes',      label: 'Planes',           href: 'planes.php',      icon: 'credit-card' },
      { id: 'log',         label: 'Log de actividad', href: 'log.php',         icon: 'shield'      },
      { id: 'errores',     label: 'Errores',          href: 'logger.php',     icon: 'alert'       },
      { id: 'perfil',      label: 'Mi perfil',        href: 'perfil.php',      icon: 'user'        },
    ],
    franquiciante: [
      { id: 'dashboard',   label: 'Inicio',   href: 'dashboard.php',   icon: 'home'      },
      { id: 'manuales',    label: 'Manuales',    href: 'manuales-mi-empresa.php',    icon: 'file-text' },
      { id: 'franquicias', label: 'Franquicias', href: 'franquicias.php', icon: 'store'  },
      { id: 'usuarios',    label: 'Usuarios',    href: 'usuarios.php',    icon: 'users'     },
      { id: 'documentos',  label: 'Documentos',       href: 'documentos.php',  icon: 'folder'},
      { id: 'aceptaciones', label: 'Aceptaciones',    href: 'aceptaciones.php', icon: 'check-circle' },
      { id: 'categorias',  label: 'Categorías',  href: 'categorias.php',  icon: 'tag'       },
      { id: 'perfil',      label: 'Mi perfil',   href: 'perfil.php',      icon: 'user'      },
    ],
    franquiciado: [
      { id: 'manuales',   label: 'Mis manuales', href: 'mis-manuales.php',   icon: 'file-text' },
      { id: 'documentos',  label: 'Mis documentos',       href: 'documentos.php',  icon: 'folder'      },
      { id: 'perfil',     label: 'Mi perfil',    href: 'perfil.php',     icon: 'user'      },
    ],
    empleado: [
      { id: 'manuales', label: 'Mis manuales', href: 'mis-manuales.php', icon: 'file-text' },
      { id: 'perfil',   label: 'Mi perfil',    href: 'perfil.php',   icon: 'user'      },
    ],
  };

  const ICONOS = {
    'home':        `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>`,
    'file-text':   `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>`,
    'building':    `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>`,
    'store':       `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m2 7 4.41-4.41A2 2 0 0 1 7.83 2h8.34a2 2 0 0 1 1.42.59L22 7"/><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><path d="M15 22v-4a2 2 0 0 0-2-2h-2a2 2 0 0 0-2 2v4"/><path d="M2 7h20"/></svg>`,
    'users':       `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>`,
    'folder':      `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>`,
    'shield':      `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>`,
    'alert':       `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>`,
    'check-circle':`<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>`,
    'bell':        `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>`,
    'credit-card': `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>`,
    'user':        `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>`,
    'tag':         `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>`,
  };

  const items        = NAV[rol] || [];
  const paginaActual = document.body.dataset.pagina || '';

  el.innerHTML = items.map(item => `
    <div class="nav-section">
      <a href="${BASE_URL}/${item.href}"
         class="nav-item ${paginaActual === item.id ? 'active' : ''}"
         data-tooltip="${item.label}"
         onclick="cerrarSidebar()">
        ${ICONOS[item.icon] || ''}
        <span>${item.label}</span>
      </a>
    </div>
  `).join('');
}

// ── INICIAR LAYOUT ────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', async function iniciarLayout() {
  const token = getToken();

  if (!token) {
    window.location.href = BASE_URL + '/login.html';
    return;
  }

  actualizarHamburguesa();

  try {
    const me = await apiFetch('GET', '/me');

    const nombreEl = document.getElementById('topbar-nombre');
    const rolEl    = document.getElementById('topbar-rol');
    // v2.3: nombre/apellido viven en users — vienen al toplevel de /me
    const nombreCompleto = [me.nombre, me.apellido].filter(Boolean).join(' ').trim();
    if (nombreEl) nombreEl.textContent = nombreCompleto || me.email;

    // Avatar del topbar. Se pinta a mano y no con avatarUsuario() porque esa
    // funcion agrega u-avatar-click, y aca el clic tiene que ir al boton
    // (perfil.php), no al lightbox.
    //
    // La <img> se monta ENCIMA de las iniciales; si el endpoint devuelve 404
    // (sin foto, archivo faltante) el onerror la borra y quedan las iniciales.
    const avatarEl = document.getElementById('topbar-avatar');
    if (avatarEl) {
      avatarEl.textContent = inicialesDeUsuario(me);
      if (me.avatar_url) {
        const img = document.createElement('img');
        img.className = 'u-avatar-img';
        img.alt = '';
        img.onerror = () => img.remove();
        // Con API y no con me.avatar_url tal cual: ese campo es una ruta
        // absoluta y en XAMPP el proyecto vive en un subpath.
        img.src = `${API}/perfil/foto/${me.id}`;
        avatarEl.appendChild(img);
      }
    }
    if (rolEl) {
      rolEl.textContent = me.rol.replace('_', ' ');
      rolEl.className   = `rol-badge ${me.rol}`;
    }

    if (typeof MODO_EDITOR === 'undefined' || !MODO_EDITOR) {
      renderSidebar(me.rol);
    }

    actualizarBadgeNotificaciones(me.notificaciones_pendientes);

    if (me.notificaciones_pendientes > 0) {
      await mostrarPopupNotificaciones();
    }

    setInterval(async () => {
      try {
        const data = await apiFetch('GET', '/notificaciones?solo_no_leidas=1');
        actualizarBadgeNotificaciones(data.total_no_leidas);
      } catch (_) {}
    }, 60000);

  } catch (err) {
    console.error('Layout error:', err);
    window.location.href = BASE_URL + '/login.html';
  }
});

// ── NAVEGACIÓN DE NOTIFICACIONES ──────────────────────────────
//
// El backend ya resolvió a dónde lleva cada notificación y si el recurso sigue
// disponible (NotificationController::resolverDestino). Acá NO se decide nada:
// solo se obedece. La lógica de "¿el manual sigue publicado? ¿el usuario todavía
// tiene acceso? ¿este rol navega libre o va por la cola?" vive en PHP, que es donde
// están los datos y las reglas.
//
// Cache id -> notificación, para que el onclick no tenga que serializar el objeto
// entero en un atributo HTML.
const NOTIFS_CACHE = new Map();

// Click en una notificación: marca leída y DESPUÉS navega.
//
// El await antes de navegar no es cosmético: si se dispara el POST y se navega en
// el mismo tick, el browser puede abortar el request en vuelo y la notificación
// queda sin marcar.
async function abrirNotif(id, el) {
  const n = NOTIFS_CACHE.get(id);
  await marcarNotifLeida(id, el);

  if (n && n.disponible && n.destino) {
    window.location.href = `${BASE_URL}/${n.destino}`;
  }
}

// ── BADGE ─────────────────────────────────────────────────────
function actualizarBadgeNotificaciones(total) {
  const badge = document.getElementById('notif-badge');
  if (!badge) return;
  if (total > 0) {
    badge.style.display = 'flex';
    badge.textContent   = total > 9 ? '9+' : total;
  } else {
    badge.style.display = 'none';
  }
}

// ── POPUP (al entrar con notificaciones pendientes) ───────────
async function mostrarPopupNotificaciones() {
  try {
    const data   = await apiFetch('GET', '/notificaciones?solo_no_leidas=1');
    const notifs = data.notificaciones || [];
    if (!notifs.length) return;

    if (!document.getElementById('popup-notif')) {
      const popup = document.createElement('div');
      popup.id = 'popup-notif';
      popup.innerHTML = `
        <div id="popup-notif-overlay" style="
          position:fixed;inset:0;background:rgba(0,0,0,.55);
          z-index:1000;display:flex;align-items:flex-start;
          justify-content:center;padding-top:64px">
          <div style="
            background:var(--gris1);border:1px solid var(--gris2);
            border-radius:14px;width:100%;max-width:420px;
            margin:0 16px;overflow:hidden;
            box-shadow:0 20px 60px rgba(0,0,0,.4)">
            <div style="padding:16px 20px;border-bottom:1px solid var(--gris2);display:flex;align-items:center;justify-content:space-between">
              <div style="display:flex;align-items:center;gap:10px">
                <div style="width:8px;height:8px;border-radius:50%;background:var(--dorado);flex-shrink:0"></div>
                <span style="font-size:14px;font-weight:600;color:var(--blanco)">
                  Tenés <span id="popup-notif-count" style="color:var(--dorado)"></span> novedad(es)
                </span>
              </div>
              <button onclick="cerrarPopupNotif()" style="background:transparent;border:none;cursor:pointer;color:var(--gris4);padding:4px;border-radius:5px;display:flex" onmouseover="this.style.color='var(--blanco)'" onmouseout="this.style.color='var(--gris4)'">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
              </button>
            </div>
            <div style="padding:8px 20px;max-height:280px;overflow-y:auto" id="popup-notif-lista"></div>
            <div style="padding:12px 20px;border-top:1px solid var(--gris2);display:flex;justify-content:flex-end;gap:8px">
              <button onclick="marcarTodasYCerrar()" style="padding:7px 16px;border-radius:7px;border:none;background:var(--dorado);color:var(--negro);font-size:13px;font-weight:600;font-family:'Archivo',sans-serif;cursor:pointer">Cerrar</button>
            </div>
          </div>
        </div>`;
      document.body.appendChild(popup);
    }

    notifs.forEach(n => NOTIFS_CACHE.set(n.id, n));

    document.getElementById('popup-notif-count').textContent = notifs.length;
    document.getElementById('popup-notif-lista').innerHTML   = notifs.slice(0, 6).map(n => {
      // Mismos campos que el panel: destino y disponible vienen resueltos del backend.
      const clickable = (n.disponible && n.destino)
        ? `onclick="abrirNotif(${n.id}, this)" style="cursor:pointer"`
        : '';
      const aviso = !n.disponible
        ? `<div style="font-size:11px;color:var(--gris4);font-style:italic;margin-top:2px;font-family:'Roboto',sans-serif">Ya no está disponible</div>`
        : '';
      return `
      <div ${clickable} data-notif-id="${n.id}" style="padding:12px 0;border-bottom:1px solid rgba(44,44,44,.5);display:flex;gap:12px;align-items:flex-start;opacity:${n.disponible ? '1' : '.55'}">
        <div style="width:6px;height:6px;border-radius:50%;background:var(--dorado);flex-shrink:0;margin-top:5px"></div>
        <div style="flex:1">
          <div style="font-size:13px;font-weight:500;color:var(--blanco);margin-bottom:3px;line-height:1.3">${esc(n.titulo)}</div>
          <div style="font-size:11px;color:var(--gris4);font-family:'Roboto',sans-serif">${formatFechaNotif(n.created_at)}</div>
          ${aviso}
        </div>
      </div>`;
    }).join('') + (notifs.length > 6
      ? `<div style="font-size:12px;color:var(--gris4);text-align:center;padding:12px 0;font-family:'Roboto',sans-serif">y ${notifs.length - 6} notificación(es) más...</div>`
      : '');

    document.getElementById('popup-notif-overlay').style.display = 'flex';
  } catch (_) {}
}

function cerrarPopupNotif() {
  const el = document.getElementById('popup-notif-overlay');
  if (el) el.style.display = 'none';
}

async function marcarTodasYCerrar() {
  try {
    await apiFetch('POST', '/notificaciones/leer-todas');
    actualizarBadgeNotificaciones(0);
  } catch (_) {}
  cerrarPopupNotif();
}

// ── PANEL LATERAL NOTIFICACIONES ──────────────────────────────
function toggleNotificaciones() {
  const drawer = document.getElementById('notif-drawer');
  if (drawer && drawer.style.transform === 'translateX(0px)') {
    cerrarPanelNotificaciones();
  } else {
    abrirPanelNotificaciones();
  }
}

async function abrirPanelNotificaciones() {
  if (!document.getElementById('notif-panel')) {
    const panel = document.createElement('div');
    panel.id = 'notif-panel';
    panel.innerHTML = `
      <div id="notif-panel-overlay" onclick="cerrarPanelNotificaciones()"
        style="position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:800;
        opacity:0;transition:opacity .25s;pointer-events:none"></div>
      <div id="notif-drawer" style="
        position:fixed;top:0;right:0;height:100vh;width:360px;
        background:var(--gris1);border-left:1px solid var(--gris2);
        z-index:801;display:flex;flex-direction:column;
        transform:translateX(100%);transition:transform .25s ease;
        box-shadow:-8px 0 32px rgba(0,0,0,.3)">
        <div style="padding:18px 20px;border-bottom:1px solid var(--gris2);display:flex;align-items:center;justify-content:space-between;flex-shrink:0">
          <div style="display:flex;align-items:center;gap:10px">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--dorado)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            <span style="font-size:15px;font-weight:600;color:var(--blanco)">Notificaciones</span>
            <span id="panel-count-badge" style="font-size:11px;font-weight:500;padding:2px 8px;border-radius:20px;background:rgba(201,168,76,.15);color:var(--dorado);display:none"></span>
          </div>
          <div style="display:flex;gap:8px;align-items:center">
            <button id="panel-marcar-btn" onclick="marcarTodasPanel()" style="font-size:11px;color:var(--gris4);background:transparent;border:none;cursor:pointer;font-family:'Archivo',sans-serif;display:none" onmouseover="this.style.color='var(--blanco)'" onmouseout="this.style.color='var(--gris4)'">Marcar todo leído</button>
            <button onclick="cerrarPanelNotificaciones()" style="background:transparent;border:none;cursor:pointer;color:var(--gris4);padding:4px;border-radius:5px;display:flex" onmouseover="this.style.color='var(--blanco)'" onmouseout="this.style.color='var(--gris4)'">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </div>
        </div>
        <div id="notif-panel-lista" style="flex:1;overflow-y:auto;padding:0"></div>
      </div>`;
    document.body.appendChild(panel);
  }

  const overlay = document.getElementById('notif-panel-overlay');
  const drawer  = document.getElementById('notif-drawer');
  overlay.style.pointerEvents = 'all';
  overlay.style.opacity       = '1';
  drawer.style.transform      = 'translateX(0)';

  await cargarNotificacionesPanel();
}

function cerrarPanelNotificaciones() {
  const overlay = document.getElementById('notif-panel-overlay');
  const drawer  = document.getElementById('notif-drawer');
  if (!drawer) return;
  drawer.style.transform      = 'translateX(100%)';
  overlay.style.opacity       = '0';
  overlay.style.pointerEvents = 'none';
}

async function cargarNotificacionesPanel() {
  const lista = document.getElementById('notif-panel-lista');
  lista.innerHTML = `<div style="display:flex;align-items:center;justify-content:center;gap:10px;padding:48px;color:var(--gris4);font-size:13px;font-family:'Roboto',sans-serif"><div style="width:16px;height:16px;border:2px solid rgba(201,168,76,.2);border-top-color:var(--dorado);border-radius:50%;animation:spin .6s linear infinite"></div>Cargando...</div>`;

  try {
    const data     = await apiFetch('GET', '/notificaciones');
    const notifs   = data.notificaciones || [];
    const noLeidas = data.total_no_leidas || 0;

    const countEl  = document.getElementById('panel-count-badge');
    const marcarEl = document.getElementById('panel-marcar-btn');
    if (noLeidas > 0) {
      countEl.textContent    = `${noLeidas} nueva(s)`;
      countEl.style.display  = 'inline-block';
      marcarEl.style.display = 'inline-block';
    } else {
      countEl.style.display  = 'none';
      marcarEl.style.display = 'none';
    }

    if (!notifs.length) {
      lista.innerHTML = `<div style="text-align:center;padding:64px 24px;color:var(--gris4);font-size:14px;font-family:'Roboto',sans-serif">Sin notificaciones aún.</div>`;
      return;
    }

    NOTIFS_CACHE.clear();
    notifs.forEach(n => NOTIFS_CACHE.set(n.id, n));

    lista.innerHTML = notifs.map(n => {
      // Recurso borrado, archivado, despublicado, o al que el usuario ya no tiene
      // acceso. Se MUESTRA marcado, no se oculta: si el usuario recibió el mail y
      // después no encuentra nada, el silencio confunde más que el aviso.
      const aviso = !n.disponible
        ? `<div style="font-size:11px;color:var(--gris4);font-style:italic;margin-top:3px;font-family:'Roboto',sans-serif">Ya no está disponible</div>`
        : '';

      // Flechita solo si realmente lleva a algún lado. No prometemos lo que no hay.
      const flecha = (n.disponible && n.destino)
        ? `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;color:var(--gris3);margin-top:4px"><polyline points="9 18 15 12 9 6"/></svg>`
        : '';

      return `
      <div data-notif-id="${n.id}" onclick="abrirNotif(${n.id}, this)" style="
        padding:14px 20px;border-bottom:1px solid rgba(44,44,44,.5);
        cursor:pointer;transition:background .15s;
        opacity:${n.disponible ? '1' : '.55'};
        background:${!n.leida ? 'rgba(201,168,76,.04)' : 'transparent'}"
        onmouseover="this.style.background='rgba(255,255,255,.04)'"
        onmouseout="this.style.background='${!n.leida ? 'rgba(201,168,76,.04)' : 'transparent'}'">
        <div style="display:flex;gap:10px;align-items:flex-start">
          <div class="notif-dot" style="width:6px;height:6px;border-radius:50%;flex-shrink:0;margin-top:5px;background:${!n.leida ? 'var(--dorado)' : 'var(--gris2)'}"></div>
          <div style="flex:1">
            <div class="notif-titulo" style="font-size:13px;font-weight:${!n.leida ? '500' : '400'};color:${!n.leida ? 'var(--blanco)' : 'var(--gris5)'};margin-bottom:4px;line-height:1.3">${esc(n.titulo)}</div>
            <div style="font-size:11px;color:var(--gris4);font-family:'Roboto',sans-serif">${formatFechaNotif(n.created_at)}</div>
            ${aviso}
          </div>
          ${flecha}
        </div>
      </div>`;
    }).join('');

  } catch (_) {
    lista.innerHTML = `<div style="text-align:center;padding:48px 24px;color:var(--gris4);font-size:13px">Error al cargar.</div>`;
  }
}

async function marcarNotifLeida(id, el) {
  if (el.dataset.leida === '1') return;
  try {
    await apiFetch('POST', `/notificaciones/${id}/leer`);
    el.dataset.leida    = '1';
    el.style.background = 'transparent';
    el.onmouseout       = () => el.style.background = 'transparent';
    const dot    = el.querySelector('.notif-dot');
    const titulo = el.querySelector('.notif-titulo');
    if (dot)    dot.style.background    = 'var(--gris2)';
    if (titulo) { titulo.style.fontWeight = '400'; titulo.style.color = 'var(--gris5)'; }
    const badge = document.getElementById('notif-badge');
    if (badge && badge.style.display !== 'none') {
      const nuevo = (parseInt(badge.textContent) || 1) - 1;
      if (nuevo <= 0) badge.style.display = 'none';
      else badge.textContent = nuevo > 9 ? '9+' : nuevo;
    }
  } catch (_) {}
}

async function marcarTodasPanel() {
  try {
    await apiFetch('POST', '/notificaciones/leer-todas');
    actualizarBadgeNotificaciones(0);
    await cargarNotificacionesPanel();
  } catch (_) {}
}

// ── SIDEBAR MOBILE ────────────────────────────────────────────
// toggleSidebar y cerrarSidebar están definidos en topbar.php

function actualizarHamburguesa() {
  const btn = document.getElementById('btn-hamburger');
  if (!btn) return;
  btn.style.display = window.innerWidth <= 767 ? 'flex' : 'none';
}

window.addEventListener('resize', () => {
  actualizarHamburguesa();
  if (window.innerWidth > 767) {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    if (sidebar) sidebar.classList.remove('open');
    if (overlay) overlay.classList.remove('open');
  }
});

// ── HELPERS ───────────────────────────────────────────────────
function formatFechaNotif(str) {
  if (!str) return '';
  return new Date(str).toLocaleString('es-AR', {
    day: '2-digit', month: '2-digit', year: 'numeric',
    hour: '2-digit', minute: '2-digit'
  });
}

async function hacerLogout() {
  try { await apiFetch('POST', '/logout'); } catch (_) {}
  localStorage.removeItem('cl_token');
  localStorage.removeItem('cl_rol');
  window.location.href = BASE_URL + '/login.html';
}

// ── AVATAR Y LIGHTBOX DE FOTO DE PERFIL ───────────────────────────────
// Compartido por todas las pantallas. Antes estaba duplicado en log.php y
// usuarios.php; las dos copias habian divergido (ver §9 del README).
//
// Al unificar se tomo la version de log.php en los dos casos, que es superset:
// maneja `u` nulo y resuelve el nombre sin apellido. La de usuarios.php
// reventaba con null y exigia nombre Y apellido.
//
// El CSS vive en panel.css, que head.php carga en todas las paginas.

function inicialesDeUsuario(u) {
  if (!u) return '?';
  const n = (u.nombre || '').trim();
  const a = (u.apellido || '').trim();
  const ini = ((n[0] || '') + (a[0] || '')).toUpperCase();
  return ini || (u.email || '?').charAt(0).toUpperCase();
}

// Devuelve SIEMPRE el circulo con iniciales; si el usuario tiene foto, le monta
// la <img> encima. Si la imagen falla, el onerror la elimina y quedan las
// iniciales: por eso no hace falta chequear permisos en el frontend.
//
// La URL se arma con API (const global) y NO con u.avatar_url tal cual: ese
// campo es una ruta absoluta ("/api/perfil/foto/N") y en XAMPP el proyecto vive
// en un subpath, con lo cual resolveria contra la raiz del servidor y daria 404.
function avatarUsuario(u, extraClase) {
  const cls = 'u-avatar' + (extraClase ? ' ' + extraClase : '');
  if (!u) return `<span class="${cls}">?</span>`;

  const ini = esc(inicialesDeUsuario(u));
  if (!u.avatar_url) return `<span class="${cls}">${ini}</span>`;

  // El nombre viaja por data-nom y no interpolado dentro del onclick, porque un
  // apellido con apostrofe romperia el atributo.
  const nom = esc(`${u.nombre || ''} ${u.apellido || ''}`.trim() || u.email || '');
  return `<span class="${cls} u-avatar-click" data-uid="${u.id}" data-nom="${nom}"`
       + ` title="Ver foto de perfil">${ini}`
       + `<img class="u-avatar-img" src="${API}/perfil/foto/${u.id}" alt="" loading="lazy" onerror="this.remove()"></span>`;
}

// Cierra con clic afuera, Escape o la X. La politica de "los modales no cierran
// al hacer clic afuera" protege datos cargados; aca es solo lectura.
function abrirFotoPerfil(userId, nombre) {
  let lb = document.getElementById('avatar-lightbox');

  // Se construye una sola vez, la primera vez que hace falta.
  if (!lb) {
    lb = document.createElement('div');
    lb.id = 'avatar-lightbox';
    lb.className = 'avatar-lb';
    lb.innerHTML =
      '<button class="avatar-lb-x" type="button" title="Cerrar" aria-label="Cerrar">&times;</button>' +
      '<figure class="avatar-lb-fig">' +
        '<img id="avatar-lb-img" alt="">' +
        '<figcaption class="avatar-lb-cap" id="avatar-lb-cap"></figcaption>' +
      '</figure>';

    // Clic en el fondo cierra; clic en la figura no (si no, no se puede ni
    // seleccionar la imagen sin que se cierre).
    lb.addEventListener('click', cerrarFotoPerfil);
    lb.querySelector('.avatar-lb-fig')
      .addEventListener('click', (e) => e.stopPropagation());

    document.body.appendChild(lb);
  }

  const img = document.getElementById('avatar-lb-img');
  const cap = document.getElementById('avatar-lb-cap');
  if (img) img.src = `${API}/perfil/foto/${userId}`;
  if (cap) cap.textContent = nombre || '';
  lb.classList.add('visible');
  document.addEventListener('keydown', cerrarFotoPerfilConEscape);
}

function cerrarFotoPerfilConEscape(e) {
  if (e.key === 'Escape') cerrarFotoPerfil();
}

function cerrarFotoPerfil() {
  const lb = document.getElementById('avatar-lightbox');
  if (lb) lb.classList.remove('visible');
  document.removeEventListener('keydown', cerrarFotoPerfilConEscape);
  // Soltar la imagen: si no, queda en memoria y se ve la anterior al reabrir.
  const img = document.getElementById('avatar-lb-img');
  if (img) img.removeAttribute('src');
}

// Delegado en document: cubre los avatares que se dibujan despues (paginacion,
// filtros, tablas que se re-renderizan). Estaba duplicado palabra por palabra
// en log.php y usuarios.php.
//
// El stopPropagation es necesario porque en log.php la fila entera es
// clickeable y abre el detalle del registro: sin esto, clickear el avatar
// abriria las dos cosas.
//
// El avatar del topbar NO entra aca: se pinta sin u-avatar-click a proposito.
document.addEventListener('click', (e) => {
  const av = e.target.closest ? e.target.closest('.u-avatar-click') : null;
  if (!av) return;
  e.stopPropagation();
  abrirFotoPerfil(av.dataset.uid, av.dataset.nom || '');
});


// ── PAGINACION CON NUMEROS ────────────────────────────────────────────
//
// Compartida por todas las pantallas con tabla. Antes cada una tenia su copia:
// seis copias de la misma logica es como divergio el lightbox de avatares.
//
// La pantalla solo necesita:
//   1. <div id="paginacion"></div> en el markup, debajo de la tabla
//   2. cortar su lista con slice(inicio, fin)
//   3. llamar a renderPaginacion({...}) al terminar de renderizar
//
// Ejemplo:
//   const total  = lista.length;
//   const inicio = (paginaActual - 1) * POR_PAGINA;
//   const fin    = Math.min(inicio + POR_PAGINA, total);
//   tbody.innerHTML = lista.slice(inicio, fin).map(...).join('');
//
//   renderPaginacion({
//     total, pagina: paginaActual, porPagina: POR_PAGINA,
//     onCambio: p => { paginaActual = p; renderTabla(); },
//   });
function renderPaginacion({
  total,
  pagina,
  porPagina,
  onCambio,
  contenedor = 'paginacion',
  ventana    = 4,
}) {
  const cont = document.getElementById(contenedor);
  if (!cont) return;

  const totalPaginas = Math.ceil(total / porPagina);

  // Con una sola pagina la barra entera desaparece: mostrarla con todo
  // deshabilitado seria ruido.
  if (totalPaginas <= 1) {
    cont.style.display = 'none';
    cont.innerHTML = '';
    return;
  }
  cont.style.display = 'flex';

  const inicio = (pagina - 1) * porPagina;
  const fin    = Math.min(inicio + porPagina, total);

  // Ventana de N numeros alrededor de la pagina actual. Listar TODAS seria
  // inmanejable: con miles de registros serian decenas de botones.
  let desde = Math.max(1, pagina - Math.floor((ventana - 1) / 2));
  let hasta = Math.min(totalPaginas, desde + ventana - 1);
  // Si la ventana toca el final, se corre hacia atras para no mostrar menos.
  desde = Math.max(1, hasta - ventana + 1);

  cont.innerHTML = '';

  const info = document.createElement('span');
  info.className   = 'pag-info';
  info.textContent = `Mostrando ${inicio + 1}–${fin} de ${total.toLocaleString('es-AR')}`;
  cont.appendChild(info);

  const caja = document.createElement('div');
  caja.className = 'pag-botones';

  // Sin onclick inline: el callback cambia por pantalla, y la CSP pendiente
  // (§9) no puede pasar a enforce con handlers inline.
  const boton = (label, destino, activa) => {
    const b = document.createElement('button');
    b.className   = 'pag-btn' + (activa ? ' pag-activa' : '');
    b.textContent = label;
    if (destino === null) {
      b.disabled = true;
    } else {
      b.addEventListener('click', () => onCambio(destino));
    }
    caja.appendChild(b);
  };

  const puntos = () => {
    const s = document.createElement('span');
    s.className   = 'pag-dots';
    s.textContent = '...';
    caja.appendChild(s);
  };

  boton('← Ant', pagina > 1 ? pagina - 1 : null);

  // Los "..." son SOLO un indicador de que hay mas paginas de ese lado. No son
  // clickeables a proposito: uno que salta a una pagina impredecible confunde
  // mas de lo que ayuda.
  if (desde > 1) puntos();

  for (let p = desde; p <= hasta; p++) {
    boton(String(p), p === pagina ? null : p, p === pagina);
  }

  if (hasta < totalPaginas) puntos();

  boton('Sig →', pagina < totalPaginas ? pagina + 1 : null);

  cont.appendChild(caja);
}


// ── VERSIONADO DE MANUALES PDF ────────────────────────────────
//
// Las dos preguntas que editor.php ya le hace a un manual editable, para los
// PDF, que no tienen editor.
//
// NOMBRES: llevan sufijo Pdf porque editor.php ya define elegirTipoCambio,
// abrirModalVersionInicial, previewVersionInicial y companhia — y editor.php
// carga este archivo por footer.php, DESPUES de su propio <script>. Un
// homonimo aca le pisaria la publicacion al editor sin ningun error que
// mencione la colision.
//
// API: las dos devuelven una promesa que resuelve con el valor elegido, o con
// null si el usuario cancela. Nada de estado global ni callbacks.

/**
 * Calcula las etiquetas de version a partir de la lista de versiones.
 * Devuelve { actual, menor, mayor } como strings tipo "v1.2", o null si no
 * hay ninguna version publicada todavia.
 *
 * OJO con el minor: se toma el MAXIMO entre las versiones que comparten el
 * version_number, no activa.version_minor + 1. Si un documento tiene v1.0,
 * v1.1 y v1.2 y la activa volvio a ser la v1.0, activa.minor + 1 propondria
 * v1.1 — que ya existe. Este es el motivo por el que el calculo vive en un
 * solo lugar y no copiado en cada pantalla.
 */
function calcularEtiquetasVersion(versiones) {
  // version_number 0 esta reservado para el borrador: nunca es una publicada.
  const publicadas = (versiones || []).filter(v => (v.version_number ?? 0) > 0);
  if (!publicadas.length) return null;

  const activa = publicadas.find(v => v.es_activa) || publicadas[0];
  const baseNumber = activa.version_number;

  const maxMinorBase = publicadas
    .filter(v => v.version_number === baseNumber)
    .reduce((max, v) => Math.max(max, v.version_minor ?? 0), 0);
  const maxNumber = publicadas
    .reduce((max, v) => Math.max(max, v.version_number), 0);

  return {
    actual: `v${baseNumber}.${activa.version_minor ?? 0}`,
    menor:  `v${baseNumber}.${maxMinorBase + 1}`,
    mayor:  `v${maxNumber + 1}.0`,
  };
}

// Inyecta el markup una sola vez por pagina y lo devuelve.
function _vpdfModal(id, armar) {
  let el = document.getElementById(id);
  if (el) return el;
  el = document.createElement('div');
  el.className = 'modal-overlay';
  el.id = id;
  el.innerHTML = armar();
  document.body.appendChild(el);
  return el;
}

/**
 * Pregunta si la version nueva es un cambio mayor o menor.
 * Devuelve 'mayor' | 'menor' | null (cancelado).
 */
async function pedirTipoCambioPdf(manualId) {
  // Las etiquetas se calculan con TODAS las versiones, no solo con la activa.
  //
  // activa.version_minor + 1 esta mal: si un manual tiene v1.0, v1.1 y v1.2 y
  // la activa volvio a ser la v1.0, propondria v1.1 — que ya existe. Hay que
  // tomar el maximo minor entre las versiones que comparten el number.
  let versiones = [];
  try {
    versiones = await apiFetch('GET', `/manuales/${manualId}/versiones`);
  } catch (e) {
    // Sin la lista no se pueden mostrar numeros reales. Se avisa y se corta:
    // mostrar "v?.?" invita a elegir a ciegas en algo que no se puede deshacer.
    alert('No se pudieron leer las versiones del manual. Probá de nuevo.');
    return null;
  }

  // El calculo vive en calcularEtiquetasVersion(): lo comparte con el selector
  // de documentos.php y es lo bastante sutil como para no tenerlo por
  // duplicado. null = todavia no hay ninguna version publicada.
  const et = calcularEtiquetasVersion(versiones) || { actual: '—', menor: 'v1.0', mayor: 'v1.0' };

  const el = _vpdfModal('modal-vpdf-tipo', () => `
    <div class="modal-box" style="max-width:480px">
      <div class="modal-header">
        <h3>¿Qué tipo de cambio es?</h3>
        <button class="modal-close" data-vpdf-cancel>
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>
      <div class="modal-body">
        <p style="font-size:13.5px;color:var(--gris5);line-height:1.6;font-family:'Roboto',sans-serif;margin-bottom:16px">
          Elegí cómo numerar esta publicación según la magnitud de los cambios respecto de la versión actual (<span data-vpdf-actual style="color:var(--blanco);font-weight:600">—</span>).
        </p>
        <div class="vpdf-opciones">
          <button class="vpdf-opcion" data-vpdf-tipo="menor">
            <div class="vpdf-opcion-num" data-vpdf-menor>v0.0</div>
            <div class="vpdf-opcion-titulo">Cambio menor</div>
            <div class="vpdf-opcion-desc">Correcciones, ajustes de redacción o cambios que no alteran el fondo del manual.</div>
          </button>
          <button class="vpdf-opcion" data-vpdf-tipo="mayor">
            <div class="vpdf-opcion-num" data-vpdf-mayor>v0.0</div>
            <div class="vpdf-opcion-titulo">Cambio mayor</div>
            <div class="vpdf-opcion-desc">Modificaciones sustanciales de contenido, cláusulas o estructura del manual.</div>
          </button>
        </div>
      </div>
    </div>`);

  el.querySelector('[data-vpdf-actual]').textContent = et.actual;
  el.querySelector('[data-vpdf-menor]').textContent = et.menor;
  el.querySelector('[data-vpdf-mayor]').textContent = et.mayor;

  return new Promise(resolve => {
    const cerrar = valor => {
      el.classList.remove('open');
      el.onclick = null;
      resolve(valor);
    };
    el.onclick = ev => {
      const op = ev.target.closest('[data-vpdf-tipo]');
      if (op) return cerrar(op.getAttribute('data-vpdf-tipo'));
      if (ev.target.closest('[data-vpdf-cancel]')) return cerrar(null);
      // No cierra al clic afuera: politica de modales del proyecto.
    };
    el.classList.add('open');
  });
}

/**
 * Pregunta con que version arranca un manual nuevo.
 * Devuelve {number, minor} | null (cancelado).
 */
async function pedirVersionInicialPdf() {
  const el = _vpdfModal('modal-vpdf-inicial', () => `
    <div class="modal-box" style="max-width:480px">
      <div class="modal-header">
        <h3>¿Con qué versión arranca este manual?</h3>
        <button class="modal-close" data-vpdf-cancel>
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>
      <div class="modal-body">
        <p style="font-size:13.5px;color:var(--gris5);line-height:1.6;font-family:'Roboto',sans-serif;margin-bottom:12px">
          Si este manual ya venía usándose fuera del sistema con su propia numeración
          (por ejemplo <strong style="color:var(--blanco)">v6.2</strong>), declarala acá.
          Así el número que ve el socio comercial coincide con el que dice el documento por dentro.
        </p>
        <p style="font-size:13px;color:var(--gris4);line-height:1.6;font-family:'Roboto',sans-serif;margin-bottom:18px">
          <strong style="color:var(--dorado)">Se elige una sola vez.</strong>
          A partir de la próxima publicación el sistema numera solo.
        </p>
        <div style="display:flex;align-items:flex-end;gap:10px">
          <div class="form-group" style="flex:1;margin-bottom:0">
            <label>Versión</label>
            <input data-vpdf-number type="number" class="vpdf-num" min="1" max="999" step="1" value="1">
          </div>
          <div style="font-size:22px;font-weight:700;color:var(--gris4);padding-bottom:10px">.</div>
          <div class="form-group" style="flex:1;margin-bottom:0">
            <label>Revisión</label>
            <input data-vpdf-minor type="number" class="vpdf-num" min="0" max="999" step="1" value="0">
          </div>
        </div>
        <div class="vpdf-preview-caja">
          <div class="vpdf-preview-lbl">Se publicará como</div>
          <div class="vpdf-preview-val" data-vpdf-preview>v1.0</div>
        </div>
        <div class="form-error" data-vpdf-error style="display:none;margin-top:12px"></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-ghost" data-vpdf-cancel>Cancelar</button>
        <button class="btn btn-primary" data-vpdf-ok>Continuar</button>
      </div>
    </div>`);

  const inpN = el.querySelector('[data-vpdf-number]');
  const inpM = el.querySelector('[data-vpdf-minor]');
  const prev = el.querySelector('[data-vpdf-preview]');
  const err = el.querySelector('[data-vpdf-error]');

  inpN.value = 1;
  inpM.value = 0;
  err.style.display = 'none';

  const leer = () => [parseInt(inpN.value, 10), parseInt(inpM.value, 10)];
  const valido = (n, m) =>
    Number.isInteger(n) && n >= 1 && n <= 999 &&
    Number.isInteger(m) && m >= 0 && m <= 999;

  const repintar = () => {
    const [n, m] = leer();
    prev.textContent = valido(n, m) ? `v${n}.${m}` : '—';
  };
  inpN.oninput = repintar;
  inpM.oninput = repintar;
  repintar();

  return new Promise(resolve => {
    const cerrar = valor => {
      el.classList.remove('open');
      el.onclick = null;
      resolve(valor);
    };
    el.onclick = ev => {
      if (ev.target.closest('[data-vpdf-cancel]')) return cerrar(null);
      if (!ev.target.closest('[data-vpdf-ok]')) return;
      const [n, m] = leer();
      // version_number 0 esta reservado para el borrador -> minimo 1.
      if (!valido(n, m)) {
        err.textContent = 'La versión debe ser entre 1 y 999, y la revisión entre 0 y 999.';
        err.style.display = 'block';
        return;
      }
      cerrar({ number: n, minor: m });
    };
    el.classList.add('open');
  });
}


// ── ORDEN POR DEFECTO DE LOS MANUALES ─────────────────────────
//
// Del mas reciente al mas viejo. La clave es la misma que muestra la columna
// "Ultima actualizacion" en las tres pantallas: la fecha de publicacion de la
// version activa, y la de creacion del manual si todavia no publico ninguna.
// Ordenar por otro campo dejaria esa columna viendose desordenada.

function _fechaManual(m) {
  const v = m.version_activa?.[0];
  const raw = (v && v.publicado_at) || m.created_at;
  if (!raw) return 0;

  // MySQL devuelve "2026-07-29 14:12:00" (con espacio). Date.parse de ese
  // formato es dependiente del motor; con la 'T' es ISO y se parsea igual en
  // todos lados. Para ORDENAR no importa que huso asuma —lo aplica a todos
  // los valores por igual— pero si importa que no devuelva NaN en algunos.
  const t = Date.parse(String(raw).replace(' ', 'T'));
  return Number.isNaN(t) ? 0 : t;
}

// Devuelve una copia ordenada. No muta la lista que recibe: las pantallas
// guardan todosLosManuales como fuente de verdad y reordenarla in situ
// esconderia efectos raros en los filtros.
function ordenarManualesRecientes(lista) {
  return [...(lista || [])].sort((a, b) => {
    const fa = _fechaManual(a);
    const fb = _fechaManual(b);
    if (fb !== fa) return fb - fa;
    // Empate de fecha: el id mas alto es el mas nuevo. Sin este desempate el
    // orden entre manuales publicados el mismo dia queda a merced del sort,
    // y la tabla se reacomoda sola entre recargas.
    return (b.id || 0) - (a.id || 0);
  });
}


// ── NUMERO DE VERSION PARA MOSTRAR ────────────────────────────
//
// Devuelve "1.1" — SIN la 'v' adelante, porque los call sites ya la escriben
// como `v${numeroVersion(x)}`.
//
// Prefiere version_label (lo calcula el modelo) y compone number.minor solo
// como respaldo, para versiones anteriores a que ese accessor existiera.
//
// NO sirve como condicion: para un documento sin version publicada devuelve
// '' pero para el borrador devuelve '0.0', que es truthy. Donde haga falta
// decidir si mostrar algo, chequear version_number aparte.
function numeroVersion(v) {
  if (!v) return '';
  if (v.version_label) return String(v.version_label);
  const n = v.version_number ?? 0;
  const m = v.version_minor ?? 0;
  return `${n}.${m}`;
}
