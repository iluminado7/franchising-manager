<?php
require_once __DIR__ . '/layout/config.php';
require_once __DIR__ . '/layout/auth.php';
// Ambos roles llegan acá. verificarSesion() sin argumento acepta cualquier rol autenticado.
// Si querés restricción estricta usá: verificarSesion(['franquiciado', 'empleado']);
verificarSesion();
$titulo        = 'Mis manuales';
$pagina_actual = 'manuales';
include 'layout/head.php';
?>

<div class="app-layout">
  <?php include 'layout/topbar.php'; ?>

  <div class="app-body">
    <?php include 'layout/sidebar.php'; ?>

    <main class="main-content">

      <div class="page-header">
        <div>
          <div class="page-title">Mis manuales</div>
          <div class="page-sub" id="page-sub">Manuales publicados disponibles para vos</div>
        </div>
        <!-- Sin botón "Nuevo manual": franquiciado/empleado no crea manuales -->
      </div>

      <!-- Búsqueda rápida -->
      <div style="margin-bottom:20px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
        <!-- Cuantos manuales por pagina. Solo afecta la vista de este
             usuario y no se recuerda al recargar. -->
        <select id="sel-por-pagina" class="form-select" onchange="cambiarPorPagina(this.value)"
                title="Manuales por página"
                style="width:auto;min-width:96px;padding:8px 10px;font-size:13px">
          <option value="10">10 por página</option>
          <option value="20">20 por página</option>
          <option value="50">50 por página</option>
        </select>
        <!-- Filtro por categoria de manual. Las opciones se derivan de los
             manuales cargados: manuals.categoria es texto libre, no hay tabla. -->
        <div id="cat-combo" style="position:relative;width:200px">
          <input type="text" id="inp-categoria" placeholder="Categoría..." autocomplete="off"
                 class="buscar-input" style="width:100%;box-sizing:border-box;padding-right:30px"
                 oninput="filtrarOpcionesCategoria()" onfocus="filtrarOpcionesCategoria()">
          <svg style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--gris4)" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
          <button type="button" id="cat-clear" onclick="limpiarCategoria()" title="Todas las categorías"
                  style="display:none;position:absolute;right:8px;top:50%;transform:translateY(-50%);background:transparent;border:none;color:var(--gris4);cursor:pointer;padding:2px;line-height:0">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          </button>
          <div id="categoria-opciones" class="combo-opciones"></div>
        </div>
      <div style="position:relative;display:inline-block">
        <input type="text" id="inp-buscar" placeholder="Buscar manual..." oninput="aplicarFiltros()" class="buscar-input">
        <svg style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--gris4)" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      </div>
      </div>

      <!-- Tabla -->
      <div class="tabla-wrap">
        <div class="tabla-header">
          <h3 id="tabla-titulo">Listado</h3>
        </div>
        <table>
          <thead id="tabla-thead">
            <!-- Se renderiza en init() según si es franquiciado o empleado -->
          </thead>
          <tbody id="tabla-body">
            <tr><td colspan="5">
              <div class="loading-msg">
                <div class="spinner" style="display:block"></div>
                Cargando manuales...
              </div>
            </td></tr>
          </tbody>
        </table>

        <!-- Lo llena renderPaginacion() de layout.js, la misma que usan las
             pantallas de gestion. Si sobra una sola pagina no dibuja nada. -->
        <div id="paginacion"></div>
      </div>

    </main>
  </div>
</div>

<!-- ══════════════════════════════════════════
     MODAL NOTAS (solo franquiciado)
══════════════════════════════════════════ -->
<div class="modal-overlay" id="modal-notas" onclick="if(event.target===this)cerrarModalNotas()">
  <div class="modal-box" style="max-width:600px">
    <div class="modal-header">
      <h3 id="notas-titulo">Notas</h3>
      <button class="modal-close" onclick="cerrarModalNotas()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-body">
      <p class="notas-intro">Dejá una sugerencia sobre este manual. La van a ver el franquiciante de tu empresa y el administrador. Queda asociada a la versión actual del manual.</p>

      <label class="notas-label">Nueva nota</label>
      <textarea id="nota-contenido" class="nota-textarea" placeholder="Ej: En la sección 3 sugiero aclarar..." maxlength="5000"></textarea>
      <div class="form-error" id="nota-error"></div>
      <div style="display:flex;justify-content:flex-end;margin:8px 0 18px">
        <button class="btn btn-primary" id="btn-enviar-nota" onclick="enviarNota()">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
          Enviar nota
        </button>
      </div>

      <div class="notas-hist-label">Tus notas enviadas</div>
      <div id="notas-body">
        <div class="loading-msg"><div class="spinner" style="display:block"></div>Cargando...</div>
      </div>
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
.buscar-input { background:var(--gris2);border:1px solid var(--gris2);border-radius:7px;padding:7px 12px 7px 32px;font-size:13px;color:var(--blanco);font-family:'Archivo',sans-serif;outline:none;width:260px;transition:border-color .2s; }
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
.form-error { background:rgba(226,92,92,.1);border:1px solid rgba(226,92,92,.3);border-radius:7px;padding:10px 12px;font-size:13px;color:var(--error);display:none;margin-top:8px;line-height:1.5; }
.badge-aceptado { display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:500;padding:3px 9px;border-radius:20px;background:rgba(92,184,122,.12);color:var(--exito); }
.toast { position:fixed;bottom:24px;right:24px;background:var(--gris1);border:1px solid var(--gris2);border-radius:10px;padding:12px 16px;font-size:13px;color:var(--blanco);display:flex;align-items:center;gap:10px;transform:translateY(80px);opacity:0;transition:transform .3s,opacity .3s;z-index:600;font-family:'Roboto',sans-serif;max-width:340px; }
.toast.show { transform:translateY(0);opacity:1; }
/* ── Notas / sugerencias ── */
.notas-intro { font-size:12px;color:var(--gris4);line-height:1.6;font-family:'Roboto',sans-serif;margin-bottom:14px; }
.notas-label { display:block;font-size:11px;font-weight:500;letter-spacing:.06em;text-transform:uppercase;color:var(--gris5);margin-bottom:6px; }
.nota-textarea { width:100%;min-height:90px;resize:vertical;background:var(--negro);border:1px solid var(--gris2);border-radius:7px;padding:10px 12px;font-size:13px;font-family:'Roboto',sans-serif;color:var(--blanco);outline:none;transition:border-color .2s;box-sizing:border-box;line-height:1.5; }
.nota-textarea:focus { border-color:var(--dorado); }
.nota-textarea::placeholder { color:var(--gris3); }
.notas-hist-label { font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:var(--gris5);margin-bottom:10px;padding-top:6px;border-top:1px solid var(--gris2); }
.nota-card { background:var(--negro);border:1px solid var(--gris2);border-radius:10px;padding:12px 14px;margin-bottom:10px; }
.nota-card:last-child { margin-bottom:0; }
.nota-card-top { display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:8px; }
.nota-meta { font-size:11px;color:var(--gris4);font-family:'Roboto',sans-serif; }
.form-select { width:100%;background:var(--negro);border:1px solid var(--gris2);border-radius:7px;padding:10px 12px;font-size:13px;font-family:'Archivo',sans-serif;color:var(--blanco);outline:none;transition:border-color .2s;box-sizing:border-box;cursor:pointer; }
.form-select:focus { border-color:var(--dorado); }
.form-select option { background:var(--gris1); }
/* Release notes: anuncios del publicador, estilo destacado */
.nota-card.nota-release { background:rgba(196,162,107,.05);border-color:rgba(196,162,107,.3);border-left:3px solid var(--dorado); }
.nota-release-tag {
  display:inline-block;padding:2px 8px;border-radius:10px;
  font-size:9px;font-weight:600;letter-spacing:.04em;text-transform:uppercase;
  background:rgba(196,162,107,.18);color:var(--dorado);
  border:1px solid rgba(196,162,107,.4);
  font-family:'Archivo',sans-serif;
}
.nota-contenido { font-size:13px;color:var(--gris5);line-height:1.6;font-family:'Roboto',sans-serif;white-space:pre-wrap; }
.nota-estado-pill { flex-shrink:0;font-size:10px;font-weight:600;padding:3px 9px;border-radius:20px;text-transform:uppercase;letter-spacing:.04em; }
.nota-pendiente { background:rgba(201,168,76,.14);color:var(--dorado); }
.nota-leida { background:rgba(255,255,255,.07);color:var(--gris5); }
.nota-resuelta { background:rgba(92,184,122,.14);color:var(--exito); }
/* Combobox de categoría. Estas clases YA existen en manuales.php; acá van
   porque en este proyecto casi nada es global (README §13). */
.combo-opciones { display:none;position:absolute;top:calc(100% + 4px);left:0;right:0;max-height:240px;overflow-y:auto;background:var(--gris1);border:1px solid var(--gris2);border-radius:8px;z-index:50;box-shadow:0 8px 24px rgba(0,0,0,.4); }
.combo-opcion { padding:9px 12px;font-size:13px;color:var(--gris5);cursor:pointer;font-family:'Roboto',sans-serif;transition:background .12s; }
.combo-opcion:hover { background:var(--gris2);color:var(--blanco); }
.combo-vacio { padding:10px 12px;font-size:12px;color:var(--gris4);font-family:'Roboto',sans-serif; }
</style>

<script>
const BASE_PHP = '<?= BASE_URL_PHP ?>';

let todosLosManuales  = [];
let rolUsuario        = '';
// versionActivaId y manualPendienteId se fueron con el flujo de aceptacion
// inline: el registro ahora lo hace lectura.php al abrir el manual.

// ── INIT ──────────────────────────────────────────────────────
async function init() {
  try {
    const me = await apiFetch('GET', '/me');
    rolUsuario = me.rol;

    // Encabezados de tabla según rol
    document.getElementById('tabla-thead').innerHTML = `
      <tr>
        <th>Manual</th>
        <th>Categoría</th>
        <th>Última actualización</th>
        <th>Versión</th>
        ${rolUsuario === 'franquiciado' ? '<th>Leído</th>' : ''}
        <th>Acción</th>
      </tr>`;

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

// ── FILTRO DE BÚSQUEDA ────────────────────────────────────────
// ── FILTRO POR CATEGORÍA DE MANUAL ────────────────────────────
//
// manuals.categoria es un varchar de TEXTO LIBRE, no una FK: no hay tabla de
// categorías de manual ni listado canónico por empresa. Las opciones se
// derivan de los manuales ya cargados, así que esto no necesita backend.
//
// Es un filtro de VISTA: no cambia nada para los demás usuarios.

let categoriaFiltro = '';   // clave normalizada; '' = todas

// Paginacion. Antes esta pantalla mostraba TODO; se agrego junto con el
// selector de cantidad, que sin paginacion no tendria efecto.
let paginaActual = 1;
let POR_PAGINA   = 10;

function cambiarPorPagina(valor) {
  const n = parseInt(valor, 10);
  // Se ignora cualquier valor fuera de la lista: POR_PAGINA alimenta un slice
  // y un NaN ahi deja la tabla vacia sin decir por que.
  if (![10, 20, 50].includes(n)) return;

  POR_PAGINA = n;

  // Volver a la 1 NO es opcional: si estaba en la pagina 3 con 10 por pagina
  // y pasa a 50, la 3 puede no existir. El slice apuntaria a un rango vacio y
  // la tabla saldria VACIA sin ninguna explicacion.
  paginaActual = 1;

  aplicarFiltros();
}

// Quita acentos y pasa a minúsculas.
//
// Al ser texto libre conviven "Pruebas" y "pruebas": son la misma categoría
// escrita distinto y sin esto aparecerían como dos opciones que filtran cosas
// distintas. NFD separa la letra de su tilde y el rango \u0300-\u036f son las
// marcas diacríticas.
function claveCategoria(s) {
  return String(s || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .trim();
}

// Categorías presentes en los manuales visibles, agrupadas por clave.
//
// Se muestra la variante MÁS FRECUENTE de cada grupo: si el equipo escribió
// "Pruebas" seis veces y "pruebas" una, la que se usa de verdad es la primera.
function categoriasDisponibles() {
  const grupos = new Map();

  manualesParaCategorias().forEach(m => {
    const orig = (m.categoria || '').trim();
    if (!orig) return;
    const k = claveCategoria(orig);
    if (!k) return;

    if (!grupos.has(k)) grupos.set(k, new Map());
    const variantes = grupos.get(k);
    variantes.set(orig, (variantes.get(orig) || 0) + 1);
  });

  return [...grupos.entries()]
    .map(([clave, variantes]) => {
      const orden = [...variantes.entries()].sort((a, b) => b[1] - a[1]);
      const total = orden.reduce((s, [, n]) => s + n, 0);
      return { clave, etiqueta: orden[0][0], total };
    })
    .sort((a, b) => a.etiqueta.localeCompare(b.etiqueta, 'es'));
}

function filtrarOpcionesCategoria() {
  const inp  = document.getElementById('inp-categoria');
  const cont = document.getElementById('categoria-opciones');
  if (!inp || !cont) return;

  const q = claveCategoria(inp.value);
  const opciones = categoriasDisponibles().filter(c => !q || c.clave.includes(q));

  cont.style.display = 'block';

  if (!opciones.length) {
    cont.innerHTML = `<div class="combo-vacio">Sin coincidencias</div>`;
    return;
  }

  // onmousedown y no onclick: el blur del input dispara antes que el click y
  // cerraría la lista antes de que el click llegue. Mismo criterio que el
  // combo de empresa.
  cont.innerHTML = opciones.map(c => `
    <div class="combo-opcion" onmousedown="seleccionarCategoria('${esc(c.clave).replace(/'/g, "\\'")}', '${esc(c.etiqueta).replace(/'/g, "\\'")}')">
      ${esc(c.etiqueta)} <span style="color:var(--gris4);font-size:11px">(${c.total})</span>
    </div>`).join('');
}

function seleccionarCategoria(clave, etiqueta) {
  categoriaFiltro = clave;
  document.getElementById('inp-categoria').value = etiqueta;
  document.getElementById('categoria-opciones').style.display = 'none';
  document.getElementById('cat-clear').style.display = 'block';
  aplicarFiltros();
}

function limpiarCategoria() {
  categoriaFiltro = '';
  document.getElementById('inp-categoria').value = '';
  document.getElementById('categoria-opciones').style.display = 'none';
  document.getElementById('cat-clear').style.display = 'none';
  aplicarFiltros();
}

// Cierra la lista al hacer clic afuera.
document.addEventListener('click', (e) => {
  const combo = document.getElementById('cat-combo');
  if (combo && !combo.contains(e.target)) {
    const cont = document.getElementById('categoria-opciones');
    if (cont) cont.style.display = 'none';
  }
});

// Esta pantalla ya muestra una sola empresa: no hay nada que acotar.
function manualesParaCategorias() {
  return todosLosManuales;
}

function aplicarFiltros() {
  const texto = (document.getElementById('inp-buscar')?.value || '').toLowerCase().trim();
  let lista   = [...todosLosManuales];

  if (texto) lista = lista.filter(m =>
    m.titulo.toLowerCase().includes(texto) || (m.categoria || '').toLowerCase().includes(texto));

  // Orden por defecto: del mas reciente al mas viejo, igual que en las
  // pantallas de gestion. Esta no pagina, asi que alcanza con ordenar acá.
  // Categoría: se compara por la clave normalizada, así "Pruebas" y "pruebas"
  // caen en el mismo filtro.
  if (categoriaFiltro) {
    lista = lista.filter(m => claveCategoria(m.categoria) === categoriaFiltro);
  }

  // El orden lo decide el BACKEND (orderBy('orden') + created_at como
  // desempate). Reordenar acá por fecha pisaría el orden manual que los
  // administradores configuran desde "Ordenar", y el bug sería difícil de
  // entender: el guardado funciona, la consulta devuelve bien, y la pantalla
  // muestra otra cosa.
  //
  // ordenarManualesRecientes() sigue existiendo en layout.js; solo se dejó de
  // invocar acá.

  // Al filtrar hay que volver a la 1: si estaba en la pagina 3 y el resultado
  // tiene 2 manuales, el slice apuntaria a un rango inexistente y la tabla
  // saldria vacia sin ninguna explicacion.
  paginaActual = 1;

  renderTabla(lista);
  // El total es el FILTRADO, no el de la pagina: si dijera los de la pagina,
  // con 25 manuales el titulo diria "10 manual(es)" y el socio pensaria que
  // perdio quince.
  document.getElementById('tabla-titulo').textContent = `${lista.length} manual(es)`;
}

// ── RENDER TABLA ──────────────────────────────────────────────
function renderTabla(lista) {
  const tbody = document.getElementById('tabla-body');
  const cols  = rolUsuario === 'franquiciado' ? 6 : 5;

  if (!lista.length) {
    tbody.innerHTML = `<tr><td colspan="${cols}"><div class="empty-state">Sin manuales disponibles.</div></td></tr>`;
    // Sin resultados tampoco tiene que quedar la paginacion de la busqueda
    // anterior: seria un control para navegar una lista vacia.
    renderPaginacion({ total: 0, pagina: 1, porPagina: POR_PAGINA, onCambio: () => {} });
    return;
  }

  const total  = lista.length;
  const inicio = (paginaActual - 1) * POR_PAGINA;
  const pagina = lista.slice(inicio, inicio + POR_PAGINA);

  // renderPaginacion vive en layout.js, que carga DESPUES del <script> de esta
  // pagina (footer.php). Por eso solo se la llama desde aca, que corre por
  // eventos, y nunca en el nivel superior del script.
  renderPaginacion({
    total,
    pagina: paginaActual,
    porPagina: POR_PAGINA,
    onCambio: (p) => { paginaActual = p; renderTabla(lista); },
  });

  tbody.innerHTML = pagina.map(m => {
    const version  = m.version_activa?.[0] || null;
    const verNum   = version ? `v${version.version_label || (version.version_number + '.' + (version.version_minor ?? 0))}` : '—';
    const fecha    = version ? formatFecha(version.publicado_at) : formatFecha(m.created_at);
    const aceptado = m.mi_aceptacion || false;

    // Columna "Leído": solo socio comercial. El nombre de la clase
    // (badge-aceptado) queda: es interno, solo cambia lo que se ve.
    const colAceptacion = rolUsuario === 'franquiciado' ? `
      <td>
        ${aceptado
          ? `<span class="badge-aceptado"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>Leído</span>`
          : `<span class="estado-pill estado-pendiente">Pendiente</span>`
        }
      </td>` : '';

    // Un solo boton para todos: "Ver manual". Ya no hay dos caminos —
    // abrirParaAceptar() mandaba a lectura.php con ?aceptar=1 para que
    // mostrara el boton de aceptar, y ese boton ya no existe.
    //
    // Se conserva el destacado en los pendientes: sigue siendo lo que el
    // socio tiene que hacer, aunque ahora alcance con abrirlo.
    const btnLabel   = 'Ver manual';
    const btnStyle   = (!aceptado && rolUsuario === 'franquiciado')
      ? 'class="btn btn-primary" style="padding:5px 12px;font-size:12px"'
      : 'class="btn btn-ghost" style="padding:5px 12px;font-size:12px"';
    const btnOnclick = `onclick="abrirManual('${m.public_id}')"`;

    return `<tr>
      <td>
        <div style="color:var(--blanco);font-weight:500;margin-top:2px">${esc(m.titulo)}${m.tipo === 'pdf' ? `<span style="margin-left:8px;font-size:9px;font-weight:700;letter-spacing:.06em;padding:2px 6px;border-radius:4px;background:rgba(201,168,76,.14);color:var(--dorado);vertical-align:middle;font-family:'Roboto',sans-serif">PDF</span>` : ''}</div>
      </td>
      <td>${esc(m.categoria) || '—'}</td>
      <td style="font-size:12px;font-family:'Roboto',sans-serif;color:var(--gris4)">${fecha}</td>
      <td style="font-family:'Roboto',sans-serif">${verNum}</td>
      ${colAceptacion}
      <td>
        <div style="display:flex;gap:4px;flex-wrap:wrap;align-items:center">
          <button ${btnStyle} ${btnOnclick}>${btnLabel}</button>
          ${rolUsuario === 'franquiciado' ? `
          <button class="btn btn-ghost" style="padding:5px 12px;font-size:12px" onclick="verNotas(${m.id}, '${esc(m.titulo).replace(/'/g, "\\'")}')">Escribir notas</button>` : ''}
        </div>
      </td>
    </tr>`;
  }).join('');
}

// ── NAVEGACIÓN ────────────────────────────────────────────────
// Abre lectura.php en modo solo lectura
function abrirManual(manualId) {
  window.location.href = `${BASE_PHP}/lectura.php?m=${manualId}`;
}

// ── NOTAS / SUGERENCIAS (solo franquiciado) ───────────────────
let notaManualActual = null;

async function verNotas(manualId, titulo) {
  notaManualActual = manualId;
  document.getElementById('notas-titulo').textContent = `Notas — ${titulo}`;
  document.getElementById('nota-contenido').value = '';
  document.getElementById('nota-error').style.display = 'none';
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
      `<div class="empty-state">Error al cargar tus notas.</div>`;
  }
}

function renderNotas(notas) {
  const body = document.getElementById('notas-body');
  if (!notas.length) {
    body.innerHTML = `<div class="empty-state">Todavía no hay notas ni mensajes para este manual.</div>`;
    return;
  }
  const estadoLabel = { pendiente: 'Pendiente', leida: 'Leída', resuelta: 'Resuelta' };
  body.innerHTML = notas.map(n => {
    const version = n.version ? `v${n.version.version_number}` : 'Sin versión publicada';

    // Release note: anuncio del publicador (super_admin/franquiciante) al subir una versión.
    // Estilo destacado, sin badge de estado (no se gestiona como feedback).
    if (n.tipo === 'release') {
      const autor = autorReleaseLabel(n);
      return `
        <div class="nota-card nota-release">
          <div class="nota-card-top">
            <div>
              <span class="nota-release-tag">Mensaje del publicador · ${version}</span>
              <span class="nota-meta" style="display:block;margin-top:4px">${esc(autor)} · ${formatFechaHora(n.created_at)}</span>
            </div>
          </div>
          <div class="nota-contenido">${esc(n.contenido)}</div>
        </div>`;
    }

    // Feedback (nota propia del franquiciado)
    return `
      <div class="nota-card">
        <div class="nota-card-top">
          <span class="nota-meta">${version} · ${formatFechaHora(n.created_at)}</span>
          <span class="nota-estado-pill nota-${n.estado}">${estadoLabel[n.estado] || n.estado}</span>
        </div>
        <div class="nota-contenido">${esc(n.contenido)}</div>
      </div>`;
  }).join('');
}

// Nombre legible del autor de una release note (v2.3: nombre toplevel en users)
function autorReleaseLabel(n) {
  const u = n.autor;
  if (!u) return 'Publicador';
  const nombre = [u.nombre, u.apellido].filter(Boolean).join(' ').trim();
  if (nombre) return nombre;
  // Fallback: si no hay nombre tampoco mostramos el email — solo el rol genérico.
  return 'Publicador';
}

async function enviarNota() {
  const contenido = document.getElementById('nota-contenido').value.trim();
  const errEl = document.getElementById('nota-error');
  errEl.style.display = 'none';
  if (!contenido) {
    errEl.textContent = 'Escribí una nota antes de enviar.';
    errEl.style.display = 'block';
    return;
  }
  const btn = document.getElementById('btn-enviar-nota');
  btn.disabled = true;
  try {
    await apiFetch('POST', `/manuales/${notaManualActual}/notas`, { contenido });
    document.getElementById('nota-contenido').value = '';
    mostrarToast('Nota enviada.', 'exito');
    await cargarNotas(notaManualActual);
  } catch (e) {
    errEl.textContent = e.data?.error || e.data?.message || 'No se pudo enviar la nota.';
    errEl.style.display = 'block';
  } finally {
    btn.disabled = false;
  }
}

function cerrarModalNotas() {
  document.getElementById('modal-notas').classList.remove('open');
  notaManualActual = null;
}

// ── HELPERS ───────────────────────────────────────────────────
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
  if (e.key === 'Escape') { cerrarModalNotas(); }
});

document.addEventListener('DOMContentLoaded', () => init());
</script>

<?php include 'layout/footer.php'; ?>