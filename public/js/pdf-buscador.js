

// Texto por página: [{ items: [{ texto, transform, width, height }], plano }]
// Se extrae UNA vez por documento y se cachea: getTextContent() por página es
// barato comparado con renderizar, pero repetirlo en cada tecla no lo es.
let bpPaginas   = null;
let bpDocId     = null;   // para invalidar el cache al cambiar de documento
let bpMatches   = [];     // [{ pagina, itemIdx }]
let bpIdx       = -1;
let bpTermino   = '';

/**
 * Saca acentos y pasa a minúsculas.
 *
 * NFD separa la letra de su tilde y el rango \u0300-\u036f son justamente las
 * marcas diacríticas: sacarlas convierte "í" en "i" sin tablas de conversión.
 */
function bpNormalizar(s) {
  return String(s || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase();
}

/**
 * Extrae el texto de todas las páginas. Idempotente por documento.
 *
 * docId sirve para invalidar: sin él, abrir un segundo documento en la misma
 * sesión buscaría sobre el texto del primero.
 */
async function pdfBuscarPreparar(pdfDoc, docId) {
  if (bpPaginas && bpDocId === docId) return;

  bpPaginas = [];
  bpDocId   = docId;

  for (let n = 1; n <= pdfDoc.numPages; n++) {
    const page = await pdfDoc.getPage(n);
    const tc   = await page.getTextContent();

    const items = tc.items.map(it => ({
      texto:     it.str || '',
      transform: it.transform,
      width:     it.width,
      height:    it.height,
    }));

    // El plano se usa solo para descartar páginas rápido antes de recorrer
    // ítem por ítem.
    bpPaginas.push({
      items,
      plano: bpNormalizar(items.map(i => i.texto).join(' ')),
    });
  }
}

/**
 * Busca el término y devuelve la cantidad de coincidencias.
 *
 * Una coincidencia = un ÍTEM que contiene el término. Un término que cruza dos
 * ítems ("Franq" + "uiciado") NO se detecta: pdf.js parte donde el PDF cambió
 * de fuente o de posición, y reconstruir el texto continuo con sus posiciones
 * es bastante más caro. Se asume la limitación: buscar palabras sueltas anda,
 * buscar frases largas puede fallar.
 */
function pdfBuscar(termino) {
  bpTermino = bpNormalizar(termino).trim();
  bpMatches = [];
  bpIdx     = -1;

  if (!bpPaginas || bpTermino.length < 2) return 0;

  bpPaginas.forEach((pag, i) => {
    if (!pag.plano.includes(bpTermino)) return;   // descarte rápido
    pag.items.forEach((item, j) => {
      if (bpNormalizar(item.texto).includes(bpTermino)) {
        bpMatches.push({ pagina: i + 1, itemIdx: j });
      }
    });
  });

  return bpMatches.length;
}

function pdfBuscarTotal()  { return bpMatches.length; }
function pdfBuscarActual() { return bpIdx + 1; }

/** Páginas con coincidencias, sin repetir y en orden. */
function pdfBuscarPaginas() {
  return [...new Set(bpMatches.map(m => m.pagina))];
}

/**
 * Mueve al match siguiente (dir 1) o anterior (dir -1) y devuelve su página,
 * o null si no hay coincidencias. Da la vuelta en los extremos.
 */
function pdfBuscarMover(dir) {
  if (!bpMatches.length) return null;
  bpIdx = (bpIdx + dir + bpMatches.length) % bpMatches.length;
  return bpMatches[bpIdx].pagina;
}

/**
 * Pinta los resaltados de una página sobre su canvas.
 *
 * Se llama DESPUÉS de renderPaginaPdf(), que hace div.innerHTML = '' y por lo
 * tanto borra los resaltados anteriores. Por eso el visor tiene que invocarla
 * al final de cada render y también al cambiar el zoom.
 *
 * viewport: el mismo que se usó para dibujar la página. Sin él las
 * coordenadas del PDF no se pueden llevar a píxeles de pantalla.
 */
function pdfBuscarPintarPagina(n, viewport, lib) {
  const div = document.querySelector(`.pdfjs-pagina[data-pag="${n}"]`);
  if (!div || !bpPaginas) return;

  div.querySelectorAll('.pdf-hl').forEach(el => el.remove());
  if (!bpMatches.length) return;

  const pag = bpPaginas[n - 1];
  if (!pag) return;

  bpMatches.forEach((m, idxGlobal) => {
    if (m.pagina !== n) return;
    const item = pag.items[m.itemIdx];
    if (!item) return;

    // El transform del ítem está en coordenadas del PDF (origen abajo a la
    // izquierda). Componerlo con el del viewport lo lleva a coordenadas de
    // pantalla, ya con el zoom y la rotación aplicados.
    const t = lib.Util.transform(viewport.transform, item.transform);

    // t[5] es la línea BASE del texto, no su borde superior: por eso se resta
    // el alto. Sin esto los rectángulos aparecen corridos hacia abajo.
    const alto  = Math.abs(item.height * viewport.scale) || 12;
    const ancho = Math.abs(item.width  * viewport.scale) || 8;

    const hl = document.createElement('div');
    hl.className = 'pdf-hl' + (idxGlobal === bpIdx ? ' actual' : '');
    hl.style.left   = t[4] + 'px';
    hl.style.top    = (t[5] - alto) + 'px';
    hl.style.width  = ancho + 'px';
    hl.style.height = alto + 'px';
    div.appendChild(hl);
  });
}

/** Limpia los resaltados de todas las páginas y el estado de búsqueda. */
function pdfBuscarLimpiar() {
  document.querySelectorAll('.pdf-hl').forEach(el => el.remove());
  bpMatches = [];
  bpIdx     = -1;
  bpTermino = '';
}