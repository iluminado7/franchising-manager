<?php
require_once __DIR__ . '/layout/config.php';
require_once __DIR__ . '/layout/auth.php';
verificarSesion();
$titulo        = 'Ver documento';
$pagina_actual = '';
$modo_editor   = true;
include 'layout/head.php';
?>

<style>
/* Tema oscuro. head.php ya define las variables del panel; acá se pisan
   solo para esta pantalla, igual que hace lectura.php con el tema claro. */
body {
  background: #1B1B1B !important;
}
.topbar { background: #111 !important; border-bottom: 1px solid #2A2A2A !important; }

.visor-layout {
  min-height: calc(100vh - 56px);
  display: flex;
  flex-direction: column;
  background: #1B1B1B;
}

/* ── Barra superior del documento ───────────────────────────── */
.visor-topbar {
  position: sticky;
  /* Estaba en 56px fijos, asumiendo la topbar de la app arriba. Esa topbar es
     sticky (panel.css) PERO en esta pantalla no se renderiza, asi que los
     56px eran un hueco por el que se veia pasar el documento.

     Ahora lo mide ajustarTopBarra(): si .topbar esta en el DOM vale su alto,
     si no vale 0. Asi funciona en los dos casos. */
  top: var(--app-topbar-h, 0px);
  z-index: 6;
  display: flex;
  align-items: center;
  gap: 16px;
  flex-wrap: wrap;
  padding: 12px 24px;
  background: #141414;
  border-bottom: 1px solid #2A2A2A;
}

.visor-back {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  background: transparent;
  border: 1px solid #333;
  border-radius: 7px;
  padding: 7px 13px;
  color: #C9C4BA;
  font-family: 'Archivo', sans-serif;
  font-size: 13px;
  cursor: pointer;
  text-decoration: none;
  transition: border-color .15s, color .15s;
}
.visor-back:hover { border-color: var(--dorado); color: var(--dorado); }

.visor-titulo {
  font-family: 'Archivo', sans-serif;
  font-size: 15px;
  font-weight: 600;
  color: #F0EDE6;
  margin: 0;
}
.visor-meta {
  font-family: 'Roboto', sans-serif;
  font-size: 12px;
  color: #7C7770;
}

/* ── Barra del visor ────────────────────────────────────────── */
.pdfjs-toolbar {
  position: sticky;
  /* La topbar de la app (0 si no está) + el alto real de .visor-topbar, que
     cambia porque hace flex-wrap en pantallas angostas. Las dos las mide
     ajustarTopBarra(); los fallback son el caso común por si el JS todavía
     no corrió. */
  top: calc(var(--app-topbar-h, 0px) + var(--visor-topbar-h, 52px));
  z-index: 5;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-wrap: wrap;
  gap: 10px;
  padding: 10px 14px;
  background: #141414;
  border-bottom: 1px solid #2A2A2A;
  font-family: 'Roboto', sans-serif;
  font-size: 14px;
  color: #C9C4BA;
}
.pdfjs-btn {
  min-width: 40px;
  height: 34px;
  padding: 0 13px;
  border: 1px solid #383838;
  border-radius: 6px;
  background: #1F1F1F;
  color: #E8E4DC;
  font-family: inherit;
  font-size: 16px;
  line-height: 1;
  cursor: pointer;
}
.pdfjs-btn:hover  { background: #2A2A2A; border-color: #4A4A4A; }
.pdfjs-btn:active { transform: scale(.98); }
.pdfjs-info { min-width: 92px; text-align: center; }
.pdfjs-sep  { width: 1px; height: 22px; background: #333; }

/* ── Páginas ────────────────────────────────────────────────── */
.pdfjs-paginas {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 18px;
  padding: 22px 16px 60px;
}
.pdfjs-pagina {
  position: relative;
  max-width: 100%;
  background: #fff;
  box-shadow: 0 2px 14px rgba(0, 0, 0, .5);
}
.pdfjs-pagina canvas { display: block; max-width: 100%; height: auto; }
/* Marca de agua por encima del canvas. */
.pdfjs-wm { position: absolute; inset: 0; pointer-events: none; }

/* ── Resaltados de búsqueda ──────────────────────────────────
   Divs absolutos sobre el canvas, NO una capa de texto: no hay nada
   seleccionable ni copiable. pointer-events: none para que no tapen
   el bloqueo de menú contextual del canvas de abajo. */
.pdf-hl {
  position: absolute;
  background: rgba(232, 196, 106, .38);
  border-radius: 2px;
  pointer-events: none;
  mix-blend-mode: multiply;   /* deja leer el texto debajo */
}
.pdf-hl.actual {
  background: rgba(232, 196, 106, .75);
  outline: 1px solid rgba(191, 174, 118, .9);
}

/* ── Barra de búsqueda ───────────────────────────────────────── */
.buscar-bar {
  display: none;
  align-items: center;
  gap: 8px;
  margin-left: auto;
  padding: 5px 8px;
  background: #1F1F1F;
  border: 1px solid #383838;
  border-radius: 8px;
}
.buscar-bar.abierta { display: flex; }
.buscar-input {
  width: 190px;
  background: transparent;
  border: none;
  outline: none;
  color: #E8E4DC;
  font-family: 'Roboto', sans-serif;
  font-size: 13px;
}
.buscar-input::placeholder { color: #6E6A63; }
.buscar-cont {
  font-family: 'Roboto', sans-serif;
  font-size: 11.5px;
  color: #8A857D;
  min-width: 44px;
  text-align: center;
}
.buscar-btn {
  width: 26px; height: 26px;
  display: flex; align-items: center; justify-content: center;
  background: transparent;
  border: none;
  color: #C9C4BA;
  cursor: pointer;
  border-radius: 5px;
}
.buscar-btn:hover:not(:disabled) { background: #2A2A2A; color: var(--dorado); }
.buscar-btn:disabled { opacity: .35; cursor: default; }

@media (max-width: 768px) {
  .buscar-bar  { margin-left: 0; width: 100%; }
  .buscar-input { width: 100%; }
}

.pdfjs-msg {
  text-align: center;
  color: #8A857D;
  font-family: 'Roboto', sans-serif;
  font-size: 13px;
  padding: 60px 20px;
}

/* Contenedor del tile de marca de agua. No se ve: solo guarda el
   backgroundImage que después copia cada página. */
.watermark-container { display: none; }

/* Sin selección: acá no hay capa de texto, pero cubre el título y la meta. */
.sin-seleccion, .sin-seleccion * {
  -webkit-user-select: none !important;
  user-select: none !important;
}

/* Ir al final. Mismo patrón que lectura.php. */
.btn-ir-final {
  position: fixed;
  right: 24px;
  bottom: 24px;
  width: 44px;
  height: 44px;
  border-radius: 50%;
  border: 1px solid #3A3A3A;
  background: #202020;
  color: #C9C4BA;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4px 14px rgba(0,0,0,.4);
  z-index: 120;
  transition: opacity .18s, visibility .18s, color .15s, border-color .15s;
}
.btn-ir-final:hover  { color: var(--dorado); border-color: var(--dorado); }
.btn-ir-final:active { transform: scale(.95); }
/* visibility además lo saca del foco por teclado: con opacity: 0 solo, el
   botón sigue siendo tabulable y se puede activar sin verlo. */
.btn-ir-final.oculto { opacity: 0; visibility: hidden; pointer-events: none; }

@media (max-width: 768px) {
  .visor-topbar { padding: 10px 16px; gap: 10px; }
  .visor-titulo { font-size: 14px; }
  .pdfjs-paginas { padding: 16px 8px 50px; }
}

/* Imprimir desde acá no tiene sentido: el canvas sale en blanco y el visor
   es justamente para no dar el archivo. */
@media print {
  body { display: none !important; }
}
</style>

<div class="visor-layout">

  <div class="visor-topbar">
    <a href="#" class="visor-back" id="btn-volver">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
      Volver a documentos
    </a>
    <div>
      <h1 class="visor-titulo" id="visor-titulo">Cargando…</h1>
      <div class="visor-meta" id="visor-meta"></div>
    </div>

    <!-- Buscador. Arranca oculto: se abre con Ctrl+F o con la lupa. -->
    <button class="buscar-btn" id="btn-abrir-buscar" onclick="toggleBuscador()" title="Buscar (Ctrl+F)" aria-label="Buscar en el documento">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.5" y2="16.5"/></svg>
    </button>

    <div class="buscar-bar" id="buscar-bar">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#6E6A63" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.5" y2="16.5"/></svg>
      <input type="text" id="buscar-input" class="buscar-input" placeholder="Buscar en el documento…" autocomplete="off" spellcheck="false">
      <span class="buscar-cont" id="buscar-cont">0/0</span>
      <button class="buscar-btn" id="buscar-prev" onclick="buscarMover(-1)" title="Anterior (Shift+Enter)" disabled>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="18 15 12 9 6 15"/></svg>
      </button>
      <button class="buscar-btn" id="buscar-next" onclick="buscarMover(1)" title="Siguiente (Enter)" disabled>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
      </button>
      <button class="buscar-btn" onclick="cerrarBuscador()" title="Cerrar (Esc)">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
  </div>

  <!-- Guarda el tile de la marca de agua. Oculto: cada página se copia el
       backgroundImage de acá. -->
  <div class="watermark-container" id="watermark"></div>

  <div id="visor-wrap">
    <div class="pdfjs-msg">Cargando documento…</div>
  </div>
</div>

<button class="btn-ir-final oculto" id="btn-ir-final" type="button"
        onclick="irAlFinal()" title="Ir al final del documento"
        aria-label="Ir al final del documento">
  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg>
</button>

<!-- Motor de busqueda. Vive en un archivo compartido porque lectura.php
     tambien lo va a usar: el visor esta duplicado, este motor no. -->
<script src="<?= BASE_URL_PHP ?>/js/pdf-buscador.js"></script>

<script>
// El id del documento viaja en la URL. Los documentos no tienen public_id
// como los manuales, así que va el id de la base — el control de acceso lo
// hace el backend en cada request, no la opacidad del identificador.
// Llega el ULID publico. Se acepta tambien el id numerico para que un link
// viejo guardado por alguien siga funcionando — mismo criterio que
// ManualController::show, que resuelve por una via o por la otra segun si
// el identificador es numerico.
const DOC_REF = (new URLSearchParams(location.search).get('d') || '').trim();

let rolUsuario = '';

// ══════════════════════════════════════════════════════════
// VISOR DE PDF (pdf.js, render a canvas)
// ══════════════════════════════════════════════════════════
//
// ⚠️ COPIA de lectura.php. Ver el comentario del encabezado del archivo.
//
// Sin capa de texto A PROPOSITO: el texto no se puede seleccionar ni copiar.
// La contra asumida es que tampoco se puede buscar ni leerlo con un lector de
// pantalla; por eso los controles de zoom están a la vista.

let pdfDoc            = null;
let pdfEscala         = 1.2;
let pdfEscalaAjustada = false;
let pdfPagActual      = 1;
let pdfObs            = null;
let pdfLib            = null;

// BASE_PHP no es global en este proyecto (solo cuatro paginas la
// declaran), asi que la base de la app se deriva de API, que si lo es.
// Es el mismo criterio que documenta lectura.php.
const BASE_APP   = API.replace(/\/api\/?$/, '');
const PDFJS_BASE = BASE_APP + '/js/pdfjs';

async function cargarLibPdfJs() {
  if (pdfLib) return pdfLib;
  const lib = await import(`${PDFJS_BASE}/pdf.min.mjs`);
  lib.GlobalWorkerOptions.workerSrc = `${PDFJS_BASE}/pdf.worker.min.mjs`;
  pdfLib = lib;
  return lib;
}

async function abrirVisorPdf(url) {
  const cont = document.getElementById('visor-wrap');
  cont.innerHTML = `<div class="pdfjs-msg">Cargando documento…</div>`;

  try {
    const lib  = await cargarLibPdfJs();
    const resp = await fetch(url, { credentials: 'include' });
    if (!resp.ok) throw new Error('HTTP ' + resp.status);

    pdfDoc = await lib.getDocument({ data: await resp.arrayBuffer() }).promise;
    cont.innerHTML = plantillaVisorPdf(pdfDoc.numPages);

    // Fricción menor: sin menú contextual no hay "guardar imagen como".
    document.getElementById('pdfjs-paginas')
            .addEventListener('contextmenu', (e) => e.preventDefault());

    await armarPaginasPdf();
    ajustarTopBarra();
    actualizarBotonIrFinal();

    // Indice de texto para el buscador. Va DESPUES de mostrar las paginas:
    // extraerlo antes retrasaria el primer dibujo por algo que quiza no se
    // use. Si falla, el visor sigue andando sin buscador.
    try {
      await pdfBuscarPreparar(pdfDoc, DOC_REF);
      document.getElementById('btn-abrir-buscar').style.display = '';
    } catch (e) {
      console.warn('No se pudo indexar el texto para buscar:', e);
      document.getElementById('btn-abrir-buscar').style.display = 'none';
    }
  } catch (e) {
    cont.innerHTML =
      `<div class="pdfjs-msg" style="color:#D46A6A">No se pudo mostrar el documento.` +
      ` Recargá la página; si sigue igual, avisale al administrador.</div>`;
  }
}

function plantillaVisorPdf(total) {
  return `
    <div class="pdfjs-toolbar">
      <button class="pdfjs-btn" onclick="pdfIrPagina(pdfPagActual - 1)" title="Página anterior">‹</button>
      <span class="pdfjs-info">Página <strong id="pdf-pag-actual">1</strong> de ${total}</span>
      <button class="pdfjs-btn" onclick="pdfIrPagina(pdfPagActual + 1)" title="Página siguiente">›</button>
      <span class="pdfjs-sep"></span>
      <button class="pdfjs-btn" onclick="pdfZoom(-1)" title="Reducir">−</button>
      <span class="pdfjs-info" id="pdf-zoom-lbl">120%</span>
      <button class="pdfjs-btn" onclick="pdfZoom(1)" title="Ampliar">+</button>
    </div>
    <div class="pdfjs-paginas" id="pdfjs-paginas"></div>`;
}

async function armarPaginasPdf() {
  const cont = document.getElementById('pdfjs-paginas');
  cont.innerHTML = '';

  // Se usan las medidas de la página 1 como referencia para los marcadores de
  // todas: pedir el viewport de cada una antes de mostrar algo retrasa el
  // primer dibujo sin necesidad. Cada una se corrige al renderizarse.
  const pag1 = await pdfDoc.getPage(1);
  if (!pdfEscalaAjustada) {
    const anchoUtil = (cont.clientWidth || 900) - 32;
    const anchoBase = pag1.getViewport({ scale: 1 }).width;
    if (anchoBase > 0) {
      // Tope 1.5 y no 3: en una pantalla de 1920 el ajuste al ancho daba 251%,
      // que obliga a scrollear en horizontal para leer un renglon.
      //
      // Sigue siendo ajuste al ancho, no un 150% fijo: en una pantalla angosta
      // un 150% fijo desbordaria. El tope solo corta para arriba.
      pdfEscala = Math.min(1.5, Math.max(0.8, anchoUtil / anchoBase));
    }
    pdfEscalaAjustada = true;
    const lblZoom = document.getElementById('pdf-zoom-lbl');
    if (lblZoom) lblZoom.textContent = Math.round(pdfEscala * 100) + '%';
  }

  const vp1 = pag1.getViewport({ scale: pdfEscala });

  for (let n = 1; n <= pdfDoc.numPages; n++) {
    const div = document.createElement('div');
    div.className    = 'pdfjs-pagina';
    div.dataset.pag  = n;
    div.style.width  = vp1.width + 'px';
    div.style.height = vp1.height + 'px';
    cont.appendChild(div);
  }

  if (pdfObs) pdfObs.disconnect();

  // Render perezoso: dibujar 24 páginas de una revienta la memoria en celular.
  // El rootMargin adelanta el dibujo para que no se vea el hueco al scrollear.
  pdfObs = new IntersectionObserver((entradas) => {
    entradas.forEach((en) => {
      if (!en.isIntersecting) return;
      const n = parseInt(en.target.dataset.pag, 10);
      renderPaginaPdf(n);
      if (en.intersectionRatio >= 0.5) {
        pdfPagActual = n;
        const lbl = document.getElementById('pdf-pag-actual');
        if (lbl) lbl.textContent = n;
      }
    });
  }, { rootMargin: '600px 0px', threshold: [0, 0.5] });

  cont.querySelectorAll('.pdfjs-pagina').forEach((el) => pdfObs.observe(el));
}

async function renderPaginaPdf(n) {
  const div = document.querySelector(`.pdfjs-pagina[data-pag="${n}"]`);
  // dataset.render guarda la escala con la que se dibujó: si cambia el zoom
  // hay que rehacerla, si no, no se toca.
  if (!div || div.dataset.render === String(pdfEscala)) return;
  div.dataset.render = String(pdfEscala);

  try {
    const page = await pdfDoc.getPage(n);
    const vp   = page.getViewport({ scale: pdfEscala });

    // devicePixelRatio: sin esto el texto sale borroso en pantallas HiDPI, que
    // es justo lo que más molesta a quien ya le cuesta leer.
    const dpr    = window.devicePixelRatio || 1;
    const canvas = document.createElement('canvas');
    canvas.width        = Math.floor(vp.width  * dpr);
    canvas.height       = Math.floor(vp.height * dpr);
    canvas.style.width  = vp.width  + 'px';
    canvas.style.height = vp.height + 'px';

    div.style.width  = vp.width  + 'px';
    div.style.height = vp.height + 'px';

    await page.render({
      canvasContext: canvas.getContext('2d'),
      viewport: vp,
      transform: dpr !== 1 ? [dpr, 0, 0, dpr, 0, 0] : null,
    }).promise;

    div.innerHTML = '';
    div.appendChild(canvas);

    // innerHTML = '' borro los resaltados. Repintar aca es lo que hace que
    // sobrevivan al scroll: sin esto desaparecen al alejarse de la pagina y
    // volver.
    if (typeof pdfBuscarPintarPagina === 'function') {
      pdfBuscarPintarPagina(n, vp, pdfLib);
    }

    const wmBase = document.getElementById('watermark');
    if (wmBase && wmBase.style.backgroundImage) {
      const wm = document.createElement('div');
      wm.className = 'pdfjs-wm';
      wm.style.backgroundImage = wmBase.style.backgroundImage;
      div.appendChild(wm);
    }
  } catch (e) {
    delete div.dataset.render;   // que pueda reintentarse
  }
}

function pdfIrPagina(n) {
  if (!pdfDoc) return;
  n = Math.max(1, Math.min(pdfDoc.numPages, n));
  const div = document.querySelector(`.pdfjs-pagina[data-pag="${n}"]`);
  if (!div) return;

  pdfPagActual = n;
  const lbl = document.getElementById('pdf-pag-actual');
  if (lbl) lbl.textContent = n;

  // El offset sale de las barras reales, no de un numero fijo: si cambian de
  // alto (flex-wrap, o la topbar de la app que aparece o no), un 140 fijo deja
  // la pagina tapada o con un hueco.
  const css  = getComputedStyle(document.documentElement);
  const alto = (n) => parseInt(css.getPropertyValue(n), 10) || 0;
  const off  = alto('--app-topbar-h') + alto('--visor-topbar-h') + 70;

  window.scrollTo({
    top: div.getBoundingClientRect().top + window.scrollY - off,
    behavior: 'smooth',
  });
}

function pdfZoom(dir) {
  if (!pdfDoc) return;

  const pasos = [0.8, 1, 1.2, 1.5, 2, 2.5, 3];
  let i = pasos.findIndex((v) => Math.abs(v - pdfEscala) < 0.01);
  if (i === -1) i = 2;
  i = Math.max(0, Math.min(pasos.length - 1, i + dir));
  if (pasos[i] === pdfEscala) return;

  pdfEscala = pasos[i];
  const lbl = document.getElementById('pdf-zoom-lbl');
  if (lbl) lbl.textContent = Math.round(pdfEscala * 100) + '%';

  // Se invalida lo dibujado y se rehace SOLO lo que está en pantalla. No se
  // reconstruye la lista de páginas para no perder la posición de lectura.
  document.querySelectorAll('.pdfjs-pagina').forEach((el) => {
    delete el.dataset.render;
    el.style.width  = '';
    el.style.height = '';
  });
  document.querySelectorAll('.pdfjs-pagina').forEach((el) => {
    const r = el.getBoundingClientRect();
    if (r.bottom > -600 && r.top < window.innerHeight + 600) {
      // renderPaginaPdf repinta los resaltados con el viewport NUEVO. Sin
      // rehacerlos, quedarian en las coordenadas de la escala anterior: es
      // decir, corridos respecto del texto.
      renderPaginaPdf(parseInt(el.dataset.pag, 10));
    }
  });
}

// ── MARCA DE AGUA ─────────────────────────────────────────────
// ⚠️ COPIA de lectura.php.
//
// Estampa nombre + apellido (+ sucursal si tiene) en mosaico diagonal, muy
// tenue, encima de cada página. No evita que se lleven el documento: hace
// identificable a quien lo filtre.
function ponerMarcaDeAgua(me) {
  const nombre   = [me.nombre, me.apellido].filter(Boolean).join(' ').trim() || me.email || '';
  const sucursal = me.perfil && me.perfil.franquicia ? me.perfil.franquicia.nombre : null;
  const texto    = sucursal ? `${nombre} \u00B7 ${sucursal}` : nombre;
  const cont = document.getElementById('watermark');
  if (!cont || !texto) return;

  // Mas grande que en lectura.php (15px): un documento se mira menos tiempo
  // que un manual, asi que la marca tiene que leerse de una.
  //
  // El tile crece con la fuente para que no se amontone. La opacidad queda en
  // 0.07: subirla molesta para leer, y el punto es que se lea en una captura,
  // no que estorbe. Si hace falta mas presencia, la perilla es el fill.
  const svg =
    `<svg xmlns='http://www.w3.org/2000/svg' width='460' height='260'>` +
    `<text x='230' y='136' fill='rgba(0,0,0,0.07)' font-size='22' font-weight='600' ` +
    `font-family='Arial, sans-serif' text-anchor='middle' transform='rotate(-30 230 130)'>` +
    `${escaparXML(texto)}</text></svg>`;
  cont.style.backgroundImage = `url("data:image/svg+xml,${encodeURIComponent(svg)}")`;
}

function escaparXML(s) {
  return String(s)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;').replace(/'/g, '&apos;');
}

// ── BLOQUEOS ANTI-COPIA (solo socio comercial y empleado) ─────
//
// ⚠️ COPIA de lectura.php.
//
// Es FRICCION, no proteccion, y una parte directamente no funciona:
//   · F12 y Ctrl+Shift+I: los navegadores modernos IGNORAN el preventDefault.
//     Se intenta igual, sabiendo que no alcanza.
//   · Ctrl+P y Ctrl+S: estos SI se bloquean.
//   · El menu contextual: se bloquea, pero el PDF sigue estando en la pestana
//     Network.
//
// Lo que de verdad disuade es la marca de agua con el nombre del socio.
function activarBloqueosLectura() {
  // ¿El evento viene de un campo donde el usuario escribe? Ahi NO se bloquea.
  //
  // En ESTA pantalla no hay campos de texto (no hay notas), asi que el guard
  // nunca se activa. Se conserva igual que en lectura.php para que las dos
  // copias sigan siendo comparables: si divergen, sincronizarlas despues es
  // peor que tener una linea de mas.
  const enCampoDeTexto = (e) => {
    const t = e.target;
    if (!t || !t.tagName) return false;
    return t.tagName === 'TEXTAREA'
        || t.tagName === 'INPUT'
        || t.isContentEditable;
  };

  document.addEventListener('contextmenu', (e) => {
    if (enCampoDeTexto(e)) return;
    e.preventDefault();
  });

  document.addEventListener('keydown', (e) => {
    if (!e.ctrlKey && !e.metaKey) {
      // F12: se intenta, aunque el navegador lo ignore.
      if (e.key === 'F12') e.preventDefault();
      return;
    }

    const k = (e.key || '').toLowerCase();

    // Ctrl+P (imprimir) y Ctrl+S (guardar pagina): bloqueados, sin alert.
    // El cartel del navegador interrumpia mas de lo que aportaba.
    if (k === 'p' || k === 's') {
      e.preventDefault();
      return;
    }

    // Ctrl+C: solo fuera de los campos de texto.
    if (k === 'c' && !enCampoDeTexto(e)) {
      e.preventDefault();
      return;
    }

    // Ctrl+V NO se bloquea: es pegar, no copiar.

    // Ctrl+Shift+I (inspeccionar): mismo caso que F12.
    if (e.shiftKey && k === 'i') {
      e.preventDefault();
    }
  });
}

// ── BUSCADOR ──────────────────────────────────────────────────
//
// El motor esta en js/pdf-buscador.js. Aca solo vive la UI.
//
// Se puede BUSCAR pero no copiar: el motor lee el texto con getTextContent()
// sin meterlo en el DOM, y los resaltados son divs con pointer-events: none.
// Los bloqueos de Ctrl+C y menu contextual siguen intactos.

let buscarTimer = null;

function toggleBuscador() {
  const bar = document.getElementById('buscar-bar');
  if (bar.classList.contains('abierta')) cerrarBuscador();
  else abrirBuscador();
}

function abrirBuscador() {
  document.getElementById('buscar-bar').classList.add('abierta');
  const inp = document.getElementById('buscar-input');
  inp.focus();
  inp.select();
  if (inp.value) ejecutarBusqueda();
}

function cerrarBuscador() {
  document.getElementById('buscar-bar').classList.remove('abierta');
  pdfBuscarLimpiar();
  document.getElementById('buscar-cont').textContent = '0/0';
  actualizarBotonesBuscar();
}

function ejecutarBusqueda() {
  const term = document.getElementById('buscar-input').value;
  const n = pdfBuscar(term);

  document.getElementById('buscar-cont').textContent =
    n ? `0/${n}` : (term.trim().length >= 2 ? 'Sin resultados' : '0/0');

  actualizarBotonesBuscar();
  repintarPaginasVisibles();

  // Salta al primero solo — si no, hay que apretar la flecha para ver algo.
  if (n) buscarMover(1);
}

function buscarMover(dir) {
  const pagina = pdfBuscarMover(dir);
  if (!pagina) return;

  document.getElementById('buscar-cont').textContent =
    `${pdfBuscarActual()}/${pdfBuscarTotal()}`;

  pdfIrPagina(pagina);

  // El scroll es suave, asi que la pagina destino puede no estar dibujada
  // todavia. Se repinta despues de que termine el movimiento.
  setTimeout(repintarPaginasVisibles, 350);
}

function actualizarBotonesBuscar() {
  const hay = pdfBuscarTotal() > 0;
  document.getElementById('buscar-prev').disabled = !hay;
  document.getElementById('buscar-next').disabled = !hay;
}

// Repinta solo lo que esta en pantalla (mas un margen): recorrer 200 paginas
// en cada tecla no tendria sentido.
function repintarPaginasVisibles() {
  if (!pdfDoc || !pdfLib) return;
  document.querySelectorAll('.pdfjs-pagina').forEach(async (el) => {
    const r = el.getBoundingClientRect();
    if (r.bottom < -600 || r.top > window.innerHeight + 600) return;
    const n = parseInt(el.dataset.pag, 10);
    const page = await pdfDoc.getPage(n);
    pdfBuscarPintarPagina(n, page.getViewport({ scale: pdfEscala }), pdfLib);
  });
}

function initBuscador() {
  const inp = document.getElementById('buscar-input');

  // Debounce: buscar en cada tecla sobre un documento largo traba la escritura.
  inp.addEventListener('input', () => {
    clearTimeout(buscarTimer);
    buscarTimer = setTimeout(ejecutarBusqueda, 220);
  });

  inp.addEventListener('keydown', (e) => {
    if (e.key === 'Enter')  { e.preventDefault(); buscarMover(e.shiftKey ? -1 : 1); }
    if (e.key === 'Escape') { e.preventDefault(); cerrarBuscador(); }
  });

  // Ctrl+F abre ESTE buscador, no el del navegador.
  //
  // El nativo sobre un canvas no encuentra nada y muestra "0/0": peor que no
  // tener buscador, porque parece que el documento no contiene la palabra.
  document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && (e.key || '').toLowerCase() === 'f') {
      e.preventDefault();
      abrirBuscador();
    }
  });
}

// ── BARRA STICKY E IR AL FINAL ────────────────────────────────
// La barra del visor se pega debajo de .visor-topbar, cuyo alto cambia con el
// flex-wrap. Se mide y se publica como custom property; el CSS tiene fallback.
function ajustarTopBarra() {
  const raiz = document.documentElement;

  // La topbar de la app puede no existir en esta pantalla. Se mide en vez de
  // asumir 56px: si no está, el offset es 0 y las barras se pegan arriba.
  const app = document.querySelector('.topbar');
  raiz.style.setProperty(
    '--app-topbar-h', `${app ? Math.round(app.getBoundingClientRect().height) : 0}px`);

  const tb = document.querySelector('.visor-topbar');
  if (tb) {
    raiz.style.setProperty(
      '--visor-topbar-h', `${Math.round(tb.getBoundingClientRect().height)}px`);
  }
}

function irAlFinal() {
  if (pdfDoc) { pdfIrPagina(pdfDoc.numPages); return; }
  window.scrollTo({ top: document.documentElement.scrollHeight, behavior: 'smooth' });
}

function actualizarBotonIrFinal() {
  const btn = document.getElementById('btn-ir-final');
  if (!btn) return;
  const restante = document.documentElement.scrollHeight
                 - window.scrollY - window.innerHeight;
  btn.classList.toggle('oculto', restante < 120);
}

// ── ARRANQUE ──────────────────────────────────────────────────
async function init() {
  document.getElementById('btn-volver').href = `${BASE_APP}/documentos.php`;

  if (!DOC_REF) {
    document.getElementById('visor-wrap').innerHTML =
      `<div class="pdfjs-msg">Falta el documento. Volvé al listado y elegí uno.</div>`;
    document.getElementById('visor-titulo').textContent = 'Documento no indicado';
    return;
  }

  // Datos del usuario: rol (para la marca de agua) y nombre.
  let me = null;
  try {
    me = await apiFetch('GET', '/me');
    rolUsuario = me.rol || '';
  } catch (e) { /* si falla, se sigue sin marca de agua */ }

  // Marca de agua y bloqueo de selección SOLO para socio comercial y empleado.
  // El super_admin y el franquiciante son quienes SUBEN los documentos: no
  // tiene sentido marcarles su propio material.
  if (rolUsuario === 'franquiciado' || rolUsuario === 'empleado') {
    if (me) ponerMarcaDeAgua(me);
    document.body.classList.add('sin-seleccion');
    activarBloqueosLectura();
  }

  // No hay endpoint GET /documentos/{id}: se busca en el listado, que ya
  // aplica el control de acceso por rol. Si el documento no está ahí, este
  // usuario no tiene por qué verlo.
  try {
    const docs = await apiFetch('GET', '/documentos');
    const doc  = (docs || []).find(d =>
      (d.public_id && d.public_id === DOC_REF) || String(d.id) === DOC_REF);

    if (!doc) {
      document.getElementById('visor-titulo').textContent = 'Sin acceso';
      document.getElementById('visor-wrap').innerHTML =
        `<div class="pdfjs-msg">Este documento no existe o no tenés acceso.</div>`;
      return;
    }

    document.getElementById('visor-titulo').textContent = doc.titulo || 'Documento';

    const va   = Array.isArray(doc.version_activa) ? doc.version_activa[0] : doc.version_activa;
    const vLbl = (typeof numeroVersion === 'function') ? numeroVersion(va) : '';
    const meta = [];
    if (vLbl) meta.push('v' + vLbl);
    if (doc.tipo) meta.push(doc.tipo);
    document.getElementById('visor-meta').textContent = meta.join(' · ');

    // El endpoint va por id numerico. No hay problema: es una llamada de la
    // API, no una URL que el usuario ve, y el control de acceso lo hace
    // streamDocumento() en cada request.
    await abrirVisorPdf(`${API}/documentos/${doc.id}/preview`);

  } catch (e) {
    document.getElementById('visor-titulo').textContent = 'Error';
    document.getElementById('visor-wrap').innerHTML =
      `<div class="pdfjs-msg" style="color:#D46A6A">No se pudo cargar el documento.</div>`;
  }
}

// layout.js carga DESPUÉS de este script (footer.php), así que todo arranca
// en DOMContentLoaded: apiFetch y numeroVersion no existen antes.
document.addEventListener('DOMContentLoaded', () => {
  // init() es async: sin este catch, cualquier excepcion adentro queda
  // como promesa rechazada y la pagina se cuelga en "Cargando…" sin un
  // solo mensaje. Fue exactamente lo que paso con BASE_PHP.
  initBuscador();
  init().catch((e) => {
    console.error('Fallo al iniciar el visor:', e);
    const t = document.getElementById('visor-titulo');
    const w = document.getElementById('visor-wrap');
    if (t) t.textContent = 'Error';
    if (w) w.innerHTML =
      `<div class="pdfjs-msg" style="color:#D46A6A">No se pudo iniciar el visor.` +
      ` Mirá la consola del navegador para el detalle.</div>`;
  });
  ajustarTopBarra();
  window.addEventListener('scroll', actualizarBotonIrFinal, { passive: true });
  window.addEventListener('resize', () => { ajustarTopBarra(); actualizarBotonIrFinal(); });
});
</script>

<?php include 'layout/footer.php'; ?>