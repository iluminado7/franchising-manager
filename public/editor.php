<?php
require_once __DIR__ . '/layout/config.php';
require_once __DIR__ . '/layout/auth.php';
verificarSesion('franquiciante'); // super_admin y franquiciante
$titulo        = 'Editor de Manual';
$pagina_actual = 'manuales';
include 'layout/head.php';
?>


<div class="app-layout">
  <?php include 'layout/topbar.php'; ?>

  <div class="editor-layout">

    <!-- ── ÁREA CENTRAL ─────────────────────────────────── -->
    <div class="editor-center">

      <!-- Barra de formato -->
      <div class="toolbar" id="toolbar">
        <button class="tb-btn" onclick="toggleBuscador()" id="btn-buscar" title="Buscar en el documento (Ctrl+F)">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.5" y2="16.5"/></svg>
        </button>

        <div class="tb-sep"></div>

        <select class="tb-select" onchange="formatBlock(this.value); this.value=''" id="sel-heading">
          <option value="">Formato</option>
          <option value="h1">Título 1</option>
          <option value="h2">Título 2</option>
          <option value="h3">Título 3</option>
          <option value="p">Párrafo</option>
        </select>

        <div class="tb-sep"></div>

        <button class="tb-btn" onclick="exec('bold')" data-cmd="bold"      title="Negrita">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 4h8a4 4 0 0 1 4 4 4 4 0 0 1-4 4H6z"/><path d="M6 12h9a4 4 0 0 1 4 4 4 4 0 0 1-4 4H6z"/></svg>
        </button>
        <button class="tb-btn" onclick="exec('italic')" data-cmd="italic"    title="Cursiva">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="4" x2="10" y2="4"/><line x1="14" y1="20" x2="5" y2="20"/><line x1="15" y1="4" x2="9" y2="20"/></svg>
        </button>
        <button class="tb-btn" onclick="exec('underline')" data-cmd="underline" title="Subrayado">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3v7a6 6 0 0 0 6 6 6 6 0 0 0 6-6V3"/><line x1="4" y1="21" x2="20" y2="21"/></svg>
        </button>

        <div class="tb-sep"></div>

        <!-- Color de texto -->
        <div class="tb-color-wrap">
          <button class="tb-color-btn" title="Color de texto"
                  onmousedown="guardarRangeEditor()" onclick="toggleColorPop('texto', event)">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 16 11 6h1l5 10"/><line x1="7.5" y1="12.5" x2="15.5" y2="12.5"/></svg>
            <span class="tb-color-bar" id="bar-color-texto" style="background:#c0392b"></span>
          </button>
          <div class="tb-color-pop" id="pop-color-texto" style="display:none">
            <div class="tb-swatches" id="swatches-texto"></div>
            <button type="button" class="tb-color-more"
                    onmousedown="event.preventDefault()" onclick="abrirColorNativo('texto')">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="13.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="10.5" r="2.5"/><circle cx="8.5" cy="7.5" r="2.5"/><circle cx="6.5" cy="12.5" r="2.5"/><path d="M12 2a10 10 0 0 0 0 20 3 3 0 0 0 3-3 2 2 0 0 1 2-2h1a4 4 0 0 0 4-4 10 10 0 0 0-10-11z"/></svg>
              Más colores…
            </button>
          </div>
        </div>

        <!-- Color de resaltado -->
        <div class="tb-color-wrap">
          <button class="tb-color-btn" title="Color de resaltado"
                  onmousedown="guardarRangeEditor()" onclick="toggleColorPop('fondo', event)">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20h16"/><path d="M6.5 15.5 13 9l4 4-6.5 6.5H6.5z"/><path d="M12 8l3-3 4 4-3 3"/></svg>
            <span class="tb-color-bar" id="bar-color-fondo" style="background:#f1c40f"></span>
          </button>
          <div class="tb-color-pop" id="pop-color-fondo" style="display:none">
            <button type="button" class="tb-color-none"
                    onmousedown="event.preventDefault()" onclick="aplicarColor('fondo','transparent')">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><line x1="5.6" y1="5.6" x2="18.4" y2="18.4"/></svg>
              Sin resaltado
            </button>
            <div class="tb-swatches" id="swatches-fondo"></div>
            <button type="button" class="tb-color-more"
                    onmousedown="event.preventDefault()" onclick="abrirColorNativo('fondo')">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="13.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="10.5" r="2.5"/><circle cx="8.5" cy="7.5" r="2.5"/><circle cx="6.5" cy="12.5" r="2.5"/><path d="M12 2a10 10 0 0 0 0 20 3 3 0 0 0 3-3 2 2 0 0 1 2-2h1a4 4 0 0 0 4-4 10 10 0 0 0-10-11z"/></svg>
              Más colores…
            </button>
          </div>
        </div>

        <!-- Inputs nativos ocultos (escape hatch: 16.7M colores) -->
        <input type="color" id="input-color-texto" style="display:none"
               onchange="aplicarColorDesdeInput('texto', this.value)">
        <input type="color" id="input-color-fondo" style="display:none"
               onchange="aplicarColorDesdeInput('fondo', this.value)">

        <button class="tb-btn" onclick="exec('insertUnorderedList')" title="Lista">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
        </button>
        <button class="tb-btn" onclick="exec('insertOrderedList')" title="Lista numerada">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="10" y1="6" x2="21" y2="6"/><line x1="10" y1="12" x2="21" y2="12"/><line x1="10" y1="18" x2="21" y2="18"/><path d="M4 6h1v4"/><path d="M4 10h2"/><path d="M6 18H4c0-1 2-2 2-3s-1-1.5-2-1"/></svg>
        </button>

        <div class="tb-sep"></div>

        <button class="tb-btn" onclick="exec('justifyLeft')" data-cmd="justifyLeft"   title="Izquierda">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="17" y1="10" x2="3" y2="10"/><line x1="21" y1="6" x2="3" y2="6"/><line x1="21" y1="14" x2="3" y2="14"/><line x1="17" y1="18" x2="3" y2="18"/></svg>
        </button>
        <button class="tb-btn" onclick="exec('justifyCenter')" data-cmd="justifyCenter" title="Centrar">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="10" x2="6" y2="10"/><line x1="21" y1="6" x2="3" y2="6"/><line x1="21" y1="14" x2="3" y2="14"/><line x1="18" y1="18" x2="6" y2="18"/></svg>
        </button>
       <button class="tb-btn" onclick="exec('justifyRight')" data-cmd="justifyRight" title="Derecha">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <line x1="21" y1="6" x2="3" y2="6"/>
          <line x1="21" y1="10" x2="7" y2="10"/>
          <line x1="21" y1="14" x2="3" y2="14"/>
          <line x1="21" y1="18" x2="7" y2="18"/>
        </svg>
      </button>
      <button class="tb-btn" onclick="exec('justifyFull')" data-cmd="justifyFull" title="Justificar">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <line x1="3" y1="6" x2="21" y2="6"/>
          <line x1="3" y1="10" x2="21" y2="10"/>
          <line x1="3" y1="14" x2="21" y2="14"/>
          <line x1="3" y1="18" x2="21" y2="18"/>
        </svg>
      </button>

        <div class="tb-sep"></div>

        <button class="tb-btn" onclick="insertarTabla()" title="Insertar tabla">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="15" x2="21" y2="15"/><line x1="9" y1="3" x2="9" y2="21"/><line x1="15" y1="3" x2="15" y2="21"/></svg>
        </button>
        <button class="tb-btn" onclick="agregarFila()" title="Agregar fila">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="12" y1="13" x2="12" y2="19"/><line x1="9" y1="16" x2="15" y2="16"/></svg>
        </button>
        <button class="tb-btn" onclick="agregarColumna()" title="Agregar columna">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="9" y1="3" x2="9" y2="21"/><line x1="13" y1="12" x2="19" y2="12"/><line x1="16" y1="9" x2="16" y2="15"/></svg>
        </button>

        <div class="tb-sep"></div>

        <button class="tb-btn" onclick="insertarImagenDesdeArchivo('editor')" title="Insertar imagen">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
        </button>

        <span class="tb-sep"></span>

        <button class="tb-btn" onclick="exec('undo')" title="Deshacer">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7v6h6"/><path d="M21 17a9 9 0 0 0-9-9 9 9 0 0 0-6 2.3L3 13"/></svg>
        </button>
        <button class="tb-btn" onclick="exec('redo')" title="Rehacer">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 7v6h-6"/><path d="M3 17a9 9 0 0 1 9-9 9 9 0 0 1 6 2.3L21 13"/></svg>
        </button>

        <div style="flex:1"></div>
        <span id="word-count" style="font-size:11px;color:var(--gris3);font-family:'Roboto',sans-serif">0 palabras</span>
      </div>

      <!-- Buscador en el documento -->
      <div class="find-bar" id="find-bar" style="display:none">
        <svg class="find-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.5" y2="16.5"/></svg>
        <input type="text" id="find-input" class="find-input" placeholder="Buscar en el documento…" autocomplete="off" spellcheck="false">
        <span class="find-count" id="find-count">0/0</span>
        <div class="find-sep"></div>
        <button class="find-nav" title="Anterior (Shift+Enter)" onclick="buscarNav(-1)">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="18 15 12 9 6 15"/></svg>
        </button>
        <button class="find-nav" title="Siguiente (Enter)" onclick="buscarNav(1)">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
        </button>
        <button class="find-close" title="Cerrar (Esc)" onclick="cerrarBuscador()">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>

      <!-- Área de escritura -->
      <div class="editor-scroll">
        <div class="editor-page">

          <!-- Mini-editor: ENCABEZADO ────────────────────────── -->
          <div class="mini-editor-wrap">
            <div class="mini-editor-label">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="4" rx="1"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
              Encabezado (aparece en cada página al imprimir)
            </div>
            <div class="mini-toolbar">
              <button class="mini-btn" onmousedown="event.preventDefault()" onclick="execMini('editor-header', 'bold')" title="Negrita"><b>B</b></button>
              <button class="mini-btn" onmousedown="event.preventDefault()" onclick="execMini('editor-header', 'italic')" title="Cursiva"><i>I</i></button>
              <button class="mini-btn" onmousedown="event.preventDefault()" onclick="execMini('editor-header', 'underline')" title="Subrayado"><u>U</u></button>
              <span class="mini-sep"></span>
              <button class="mini-btn" onmousedown="event.preventDefault()" onclick="execMini('editor-header', 'justifyLeft')" title="Alinear izquierda">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="17" y1="10" x2="3" y2="10"/><line x1="21" y1="6" x2="3" y2="6"/><line x1="21" y1="14" x2="3" y2="14"/><line x1="17" y1="18" x2="3" y2="18"/></svg>
              </button>
              <button class="mini-btn" onmousedown="event.preventDefault()" onclick="execMini('editor-header', 'justifyCenter')" title="Centrar">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="10" x2="6" y2="10"/><line x1="21" y1="6" x2="3" y2="6"/><line x1="21" y1="14" x2="3" y2="14"/><line x1="18" y1="18" x2="6" y2="18"/></svg>
              </button>
              <button class="mini-btn" onmousedown="event.preventDefault()" onclick="execMini('editor-header', 'justifyRight')" title="Alinear derecha">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="21" y1="10" x2="7" y2="10"/><line x1="21" y1="6" x2="3" y2="6"/><line x1="21" y1="14" x2="3" y2="14"/><line x1="21" y1="18" x2="7" y2="18"/></svg>
              </button>
              <span class="mini-sep"></span>
              <select class="mini-select" onchange="setFontSizePt('editor-header', this.value); this.selectedIndex=0" title="Tamaño de fuente (pt)">
                <option value="">Tamaño</option>
                <option value="8">8 pt</option>
                <option value="9">9 pt</option>
                <option value="10">10 pt</option>
                <option value="11">11 pt</option>
                <option value="12">12 pt</option>
                <option value="14">14 pt</option>
                <option value="16">16 pt</option>
                <option value="18">18 pt</option>
                <option value="20">20 pt</option>
                <option value="24">24 pt</option>
                <option value="28">28 pt</option>
                <option value="36">36 pt</option>
                <option value="48">48 pt</option>
                <option value="72">72 pt</option>
              </select>
              <span class="mini-sep"></span>
              <button class="mini-btn" onmousedown="event.preventDefault()" onclick="insertarImagenDesdeArchivo('editor-header')" title="Insertar imagen">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
              </button>
            </div>
            <div class="mini-editor" id="editor-header" contenteditable="true" spellcheck="false"
                 data-placeholder="Título del manual, logo institucional o texto legal..."
                 oninput="marcarConCambios()"></div>
          </div>

          <div class="mini-separator">Contenido del manual</div>

          <!-- Editor principal (contenido) ─────────────────────── -->
          <div class="editor-content" id="editor" contenteditable="true" spellcheck="false"></div>

          <div class="mini-separator">Pie de página</div>

          <!-- Mini-editor: PIE DE PÁGINA ─────────────────────── -->
          <div class="mini-editor-wrap">
            <div class="mini-editor-label">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><rect x="3" y="16" width="18" height="4" rx="1"/></svg>
              Pie de página (aparece en cada página al imprimir)
            </div>
            <div class="mini-toolbar">
              <button class="mini-btn" onmousedown="event.preventDefault()" onclick="execMini('editor-footer', 'bold')" title="Negrita"><b>B</b></button>
              <button class="mini-btn" onmousedown="event.preventDefault()" onclick="execMini('editor-footer', 'italic')" title="Cursiva"><i>I</i></button>
              <button class="mini-btn" onmousedown="event.preventDefault()" onclick="execMini('editor-footer', 'underline')" title="Subrayado"><u>U</u></button>
              <span class="mini-sep"></span>
              <button class="mini-btn" onmousedown="event.preventDefault()" onclick="execMini('editor-footer', 'justifyLeft')" title="Alinear izquierda">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="17" y1="10" x2="3" y2="10"/><line x1="21" y1="6" x2="3" y2="6"/><line x1="21" y1="14" x2="3" y2="14"/><line x1="17" y1="18" x2="3" y2="18"/></svg>
              </button>
              <button class="mini-btn" onmousedown="event.preventDefault()" onclick="execMini('editor-footer', 'justifyCenter')" title="Centrar">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="10" x2="6" y2="10"/><line x1="21" y1="6" x2="3" y2="6"/><line x1="21" y1="14" x2="3" y2="14"/><line x1="18" y1="18" x2="6" y2="18"/></svg>
              </button>
              <button class="mini-btn" onmousedown="event.preventDefault()" onclick="execMini('editor-footer', 'justifyRight')" title="Alinear derecha">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="21" y1="10" x2="7" y2="10"/><line x1="21" y1="6" x2="3" y2="6"/><line x1="21" y1="14" x2="3" y2="14"/><line x1="21" y1="18" x2="7" y2="18"/></svg>
              </button>
              <span class="mini-sep"></span>
              <select class="mini-select" onchange="setFontSizePt('editor-footer', this.value); this.selectedIndex=0" title="Tamaño de fuente (pt)">
                <option value="">Tamaño</option>
                <option value="8">8 pt</option>
                <option value="9">9 pt</option>
                <option value="10">10 pt</option>
                <option value="11">11 pt</option>
                <option value="12">12 pt</option>
                <option value="14">14 pt</option>
                <option value="16">16 pt</option>
                <option value="18">18 pt</option>
                <option value="20">20 pt</option>
                <option value="24">24 pt</option>
                <option value="28">28 pt</option>
                <option value="36">36 pt</option>
                <option value="48">48 pt</option>
                <option value="72">72 pt</option>
              </select>
              <span class="mini-sep"></span>
              <button class="mini-btn" onmousedown="event.preventDefault()" onclick="insertarImagenDesdeArchivo('editor-footer')" title="Insertar imagen">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
              </button>
            </div>
            <div class="mini-editor" id="editor-footer" contenteditable="true" spellcheck="false"
                 data-placeholder="Texto legal, número de página, información de contacto..."
                 oninput="marcarConCambios()"></div>
          </div>

        </div>
      </div>

    </div>

    <!-- ── PANEL DERECHO ──────────────────────────────────── -->
    <div class="editor-panel">

      <!-- Topbar del panel -->
      <div class="panel-topbar">
        <a href="#" id="btn-volver" class="panel-back">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
          Volver a manuales
        </a>
      </div>

      <!-- Info del manual -->
      <div class="panel-section">
        <div class="panel-section-title">Manual</div>
        <div class="manual-info-nombre" id="panel-titulo">Cargando...</div>
        <div class="manual-info-cat"    id="panel-categoria"></div>
        <button class="btn btn-ghost" onclick="abrirModalEditarDatos()" style="margin-top:10px;width:100%;justify-content:center;font-size:12px;padding:6px 12px">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
          Editar datos
        </button>
      </div>

      <!-- Estado -->
      <div class="panel-section">
        <div class="panel-section-title">Estado</div>
        <div class="status-row">
          <span class="status-label">Versión activa</span>
          <span class="status-value" id="st-version">—</span>
        </div>
        <div class="status-row">
          <span class="status-label">Estado</span>
          <span class="status-value" id="st-estado">—</span>
        </div>
        <div class="status-row">
          <span class="status-label">Cambios</span>
          <span class="status-value pendiente" id="st-cambios">Sin cambios</span>
        </div>
        <div class="status-row">
          <span class="status-label">Palabras</span>
          <span class="status-value" id="st-palabras">0</span>
        </div>
      </div>

      <!-- Importar Word -->
      <div class="panel-section">
        <div class="panel-section-title">Importar desde Word</div>
        <div class="drop-zone" id="drop-zone"
          onclick="document.getElementById('file-docx').click()"
          ondragover="onDragOver(event)"
          ondragleave="onDragLeave(event)"
          ondrop="onDrop(event)">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color:var(--gris3);margin-bottom:6px"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
          <div style="font-size:12px;color:var(--gris4);text-align:center">
            Arrastrá tu <strong style="color:var(--dorado)">.docx</strong> acá<br>o hacé clic para seleccionar
          </div>
        </div>
        <input type="file" id="file-docx" accept=".docx" style="display:none" onchange="importarDocx(this.files[0])">
        <div id="import-result"   style="display:none;margin-top:8px;background:rgba(92,184,122,.08);border:1px solid rgba(92,184,122,.2);border-radius:7px;padding:8px 10px;font-size:11px;color:var(--exito)"></div>
        <div id="import-warnings" style="display:none;margin-top:6px;background:rgba(201,168,76,.08);border:1px solid rgba(201,168,76,.2);border-radius:7px;padding:8px 10px;font-size:11px;color:var(--dorado);line-height:1.5"></div>
      </div>

      <!-- v2.3: Visible para qué categorías de Socios comerciales -->
      <div class="panel-section">
        <div class="panel-section-title">Visible para</div>
        <button class="btn btn-ghost" onclick="abrirModalVisible()" id="btn-visible" style="width:100%;justify-content:space-between;text-align:left">
          <span style="display:flex;align-items:center;gap:8px;min-width:0;flex:1">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
            <span id="visible-resumen" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">Cargando...</span>
          </span>
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;color:var(--gris4)"><polyline points="9 18 15 12 9 6"/></svg>
        </button>
        <div style="margin-top:8px;font-size:11px;color:var(--gris4);font-family:'Roboto',sans-serif;line-height:1.5">
          Definí qué categorías de Socios comerciales pueden ver este manual.
        </div>
      </div>

      <!-- Acciones -->
      <div class="panel-section">
        <div class="panel-section-title">Acciones</div>
        <div style="display:flex;flex-direction:column;gap:8px">
          <button class="btn btn-ghost" onclick="guardarBorrador()" id="btn-guardar" style="width:100%;justify-content:center" disabled>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
            Guardar borrador
          </button>
          <button class="btn btn-success" onclick="abrirModalVersion()" id="btn-publicar" style="width:100%;justify-content:center" disabled>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2L11 13"/><path d="M22 2L15 22 11 13 2 9l20-7z"/></svg>
            Publicar versión
          </button>
        </div>
        <div style="margin-top:10px;font-size:11px;color:var(--gris4);font-family:'Roboto',sans-serif;line-height:1.5;padding:8px 10px;background:rgba(255,255,255,.03);border-radius:6px;border-left:2px solid var(--gris2)">
          Al publicar se genera un hash SHA-256 y se notifica a todos los franquiciados activos.
        </div>
      </div>

      <!-- Notas / Sugerencias -->
      <div class="panel-section">
        <div class="panel-section-title">Notas y sugerencias</div>

        <!-- Caja para escribir (solo franquiciante) -->
        <div id="nota-form" style="display:none;margin-bottom:12px">
          <textarea id="nota-texto" rows="3" maxlength="5000" placeholder="Escrib&iacute; una sugerencia sobre este manual..." style="width:100%;background:rgba(255,255,255,.03);border:1px solid var(--gris2);border-radius:8px;color:var(--blanco);font-family:'Roboto',sans-serif;font-size:12.5px;padding:8px 10px;resize:vertical;outline:none;line-height:1.5"></textarea>
          <button class="btn btn-ghost" id="btn-nota-enviar" onclick="agregarNota()" style="width:100%;justify-content:center;margin-top:8px">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2L11 13"/><path d="M22 2L15 22 11 13 2 9l20-7z"/></svg>
            Enviar nota
          </button>
        </div>

        <!-- Hilo de notas -->
        <div class="notas-list" id="notas-list">
          <div style="font-size:12px;color:var(--gris3);font-family:'Roboto',sans-serif">Cargando notas...</div>
        </div>
      </div>

      <!-- Historial de versiones -->
      <div class="panel-section" style="flex:1">
        <div class="panel-section-title">Historial de versiones</div>
        <div class="version-list" id="version-list">
          <div style="font-size:12px;color:var(--gris3);font-family:'Roboto',sans-serif">Sin versiones publicadas aún.</div>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- ── MODAL ELEGIR TIPO DE VERSIÓN ───────────────────────────── -->
<div class="modal-overlay" id="modal-version" onclick="if(event.target===this)cerrarModalVersion()">
  <div class="modal-box" style="max-width:480px">
    <div class="modal-header">
      <h3>¿Qué tipo de cambio es?</h3>
      <button class="modal-close" onclick="cerrarModalVersion()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-body">
      <p style="font-size:13.5px;color:var(--gris5);line-height:1.6;font-family:'Roboto',sans-serif;margin-bottom:16px">
        Elegí cómo numerar esta publicación según la magnitud de los cambios respecto de la versión actual (<span id="ver-actual-label" style="color:var(--blanco);font-weight:600">—</span>).
      </p>
      <div class="ver-opciones">
        <button class="ver-opcion" onclick="elegirTipoCambio('menor')">
          <div class="ver-opcion-num" id="ver-opcion-menor">v0.0</div>
          <div class="ver-opcion-titulo">Cambio menor</div>
          <div class="ver-opcion-desc">Correcciones, ajustes de redacción o cambios que no alteran el fondo del manual.</div>
        </button>
        <button class="ver-opcion" onclick="elegirTipoCambio('mayor')">
          <div class="ver-opcion-num" id="ver-opcion-mayor">v0.0</div>
          <div class="ver-opcion-titulo">Cambio mayor</div>
          <div class="ver-opcion-desc">Modificaciones sustanciales de contenido, cláusulas o estructura del manual.</div>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ── MODAL VERSIÓN INICIAL (solo en la primera publicación) ─── -->

<div class="modal-overlay" id="modal-version-inicial">
  <div class="modal-box" style="max-width:480px">
    <div class="modal-header">
      <h3>¿Con qué versión arranca este manual?</h3>
      <button class="modal-close" onclick="cerrarModalVersionInicial()">
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
          <label class="form-label">Versión</label>
          <input id="vi-number" type="number" class="form-input" min="1" max="999" step="1" value="1" oninput="previewVersionInicial()">
        </div>
        <div style="font-size:22px;font-weight:700;color:var(--gris4);padding-bottom:10px">.</div>
        <div class="form-group" style="flex:1;margin-bottom:0">
          <label class="form-label">Revisión</label>
          <input id="vi-minor" type="number" class="form-input" min="0" max="999" step="1" value="0" oninput="previewVersionInicial()">
        </div>
      </div>

      <div style="margin-top:16px;padding:12px;border:1px solid var(--gris2);border-radius:6px;background:rgba(201,168,76,.04)">
        <div style="font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:var(--gris4);font-weight:600;margin-bottom:4px">Se publicará como</div>
        <div id="vi-preview" style="font-size:20px;font-weight:700;color:var(--dorado);font-family:'Archivo',sans-serif">v1.0</div>
      </div>

      <div class="form-error" id="vi-error" style="display:none;margin-top:12px"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="cerrarModalVersionInicial()">Cancelar</button>
      <button class="btn btn-success" onclick="confirmarVersionInicial()">Continuar</button>
    </div>
  </div>
</div>

<!-- ── MODAL PUBLICAR ─────────────────────────────────────────── -->
<div class="modal-overlay" id="modal-publicar" onclick="if(event.target===this)cerrarModal()">
  <div class="modal-box" style="max-width:500px">
    <div class="modal-header">
      <h3 id="pub-modal-titulo">Publicar nueva versión</h3>
      <button class="modal-close" onclick="cerrarModal()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-body">

      <!-- Contenido para superadmin -->
      <div id="pub-body-admin">
        <p style="font-size:15px;color:var(--gris5);line-height:1.7;font-family:'Roboto',sans-serif">
          Se creará una nueva versión del manual. Todos los franquiciados activos recibirán una notificación. Esta acción no se puede deshacer.
        </p>
        <p style="margin-top:8px;font-size:14px;color:var(--gris4);font-family:'Roboto',sans-serif;line-height:1.5;padding:6px 8px;background:rgba(255,255,255,.03);border-radius:6px;border-left:2px solid var(--gris3)">
          Las imágenes del documento no se importan. Solo se conserva el texto y el formato.
        </p>
      </div>

      <!-- Contenido para franquiciante: advertencia legal -->
      <div id="pub-body-franquiciante" style="display:none">
        <div style="background:rgba(226,92,92,.08);border:1px solid rgba(226,92,92,.25);border-radius:10px;padding:14px 16px;margin-bottom:14px">
          <div style="display:flex;align-items:flex-start;gap:10px;margin-bottom:8px">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--error)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;margin-top:1px"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            <div style="font-size:13px;font-weight:600;color:var(--error)">Advertencia importante</div>
          </div>
          <p style="font-size:13.5px;color:var(--gris5);line-height:1.7;font-family:'Montserrat',sans-serif;margin:0">
            Estás por publicar una versión de un manual operativo. Toda modificación o creación de contenido que realices queda <strong style="color:var(--blanco)">bajo tu entera responsabilidad</strong> y puede tener <strong style="color:var(--blanco)">repercusiones legales</strong> para tu empresa, tus franquicias o demas socios comerciales.
          </p>
          <p style="font-size:13.5px;color:var(--gris5);line-height:1.7;font-family:'Montserrat',sans-serif;margin:10px 0 0">
            Antes de continuar, te recomendamos <strong style="color:var(--blanco)">consultar con el equipo técnico</strong> o legal de <a href="goharv.com.ar"></a> para validar los cambios.
          </p>
        </div>

        <p style="font-size:13px;color:var(--gris4);font-family:'Montserrat',sans-serif;line-height:1.5;margin-bottom:14px">
          Una vez publicado, todos los franquiciados activos de tu empresa recibirán una notificación y la versión quedará registrada con fecha, hora y autoría. Esta acción no se puede deshacer.
        </p>

        <label style="display:flex;align-items:flex-start;gap:9px;cursor:pointer;padding:10px 12px;background:rgba(255,255,255,.03);border-radius:8px;border:1px solid var(--gris2)">
          <input type="checkbox" id="pub-confirmo" onchange="toggleBotonPublicar()" style="margin-top:2px;width:15px;height:15px;accent-color:var(--dorado);flex-shrink:0">
          <span style="font-size:13px;color:var(--gris5);line-height:1.5;font-family:'Montserrat',sans-serif">
            Confirmo que reviso y asumo la responsabilidad por las modificaciones realizadas en este manual.
          </span>
        </label>
      </div>

      <!-- Nota de publicación (opcional, común a super_admin y franquiciante) -->
      <div style="margin-top:16px">
        <label style="display:block;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:var(--gris4);margin-bottom:6px;font-family:'Archivo',sans-serif">
          Mensaje al publicar <span style="text-transform:none;font-weight:400;color:var(--gris4);letter-spacing:0">(opcional)</span>
        </label>
        <textarea id="pub-nota" maxlength="2000" rows="3"
          placeholder="Ej: Actualizamos las cláusulas 3 y 5 según el nuevo decreto. Revisar antes del 30/06."
          style="width:100%;box-sizing:border-box;background:var(--gris1);border:1px solid var(--gris2);border-radius:8px;padding:10px 12px;font-size:13px;color:var(--blanco);font-family:'Roboto',sans-serif;resize:vertical;outline:none;transition:border-color .15s"
          onfocus="this.style.borderColor='var(--dorado)'" onblur="this.style.borderColor='var(--gris2)'"></textarea>
        <div style="font-size:11px;color:var(--gris4);margin-top:4px;font-family:'Roboto',sans-serif;line-height:1.5">
          Este mensaje aparecerá en el hilo de notas del manual para todos los que tengan acceso. Podés editarlo después.
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="cerrarModal()">Cancelar</button>
      <button class="btn btn-success" onclick="publicar()" id="btn-confirmar-publicar">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2L11 13"/><path d="M22 2L15 22 11 13 2 9l20-7z"/></svg>
        Confirmar publicación
      </button>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════
     MODAL EDITAR DATOS DEL MANUAL
     ══════════════════════════════════════════════════ -->
<!-- v2.3: Modal "Visible para" — gestiona categorías del manual -->
<div class="modal-overlay" id="modal-visible" onclick="if(event.target===this)cerrarModalVisible()">
  <div class="modal-box" style="max-width:480px">
    <div class="modal-header">
      <h3>¿Para quién es visible este manual?</h3>
      <button class="modal-close" onclick="cerrarModalVisible()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-body">
      <div style="font-size:13px;color:var(--gris5);font-family:'Roboto',sans-serif;line-height:1.5;margin-bottom:14px">
        Elegí "Toda la empresa" para que todas las categorías activas lo vean, o seleccioná categorías específicas.
      </div>
      <div id="modal-visible-lista">
        <div style="font-size:12px;color:var(--gris4);font-family:'Roboto',sans-serif;padding:8px 0">Cargando...</div>
      </div>
      <div class="form-error" id="modal-visible-error" style="display:none;margin-top:12px"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="cerrarModalVisible()">Cancelar</button>
      <button class="btn btn-primary" id="btn-guardar-visible" onclick="guardarVisible()">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
        Guardar
      </button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="modal-editar-datos" onclick="if(event.target===this)cerrarModalEditar()">
  <div class="modal-box">
    <div class="modal-header">
      <h3>Editar datos del manual</h3>
      <button class="modal-close" onclick="cerrarModalEditar()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-body">
      <p style="font-size:12px;color:var(--gris4);margin-bottom:16px;font-family:'Roboto',sans-serif">
        Los cambios se aplican a todas las versiones del manual.
      </p>

      <div class="form-group">
        <label class="form-label">Título *</label>
        <input id="edit-titulo" type="text" class="form-input" maxlength="200" placeholder="Título del manual">
      </div>

      <div class="form-group">
        <label class="form-label">Categoría</label>
        <input id="edit-categoria" type="text" class="form-input" maxlength="100" placeholder="Ej: Operativo, Recursos humanos...">
      </div>

      <div class="form-error" id="edit-error"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="cerrarModalEditar()">Cancelar</button>
      <button class="btn btn-success" id="btn-guardar-datos" onclick="guardarDatosManual()">
        Guardar cambios
      </button>
    </div>
  </div>
</div>

<!-- ── TOAST ──────────────────────────────────────────────────── -->
<div class="toast" id="toast">
  <span id="toast-icon"></span>
  <span id="toast-msg"></span>
</div>

<style>
/* Editor layout — sobreescribe app-body del panel.css */
.editor-layout {
  display: grid;
  grid-template-columns: 1fr 280px;
  height: calc(100vh - 56px);
  overflow: hidden;
}

.editor-center {
  display: flex;
  flex-direction: column;
  overflow: hidden;
  position: relative;
}

/* Buscador en el documento (lupa) */
.find-bar {
  position: absolute; top: 12px; right: 24px; z-index: 40;
  display: flex; align-items: center; gap: 4px;
  padding: 6px 8px;
  background: var(--gris1); border: 1px solid var(--gris2);
  border-radius: 10px; box-shadow: 0 10px 30px rgba(0,0,0,.5);
}
.find-ico { width: 15px; height: 15px; color: var(--gris4); flex-shrink: 0; margin: 0 2px; }
.find-input {
  width: 190px; padding: 5px 6px;
  background: transparent; border: none; outline: none;
  color: var(--blanco); font-family: 'Roboto', sans-serif; font-size: 13px;
}
.find-input::placeholder { color: var(--gris4); }
.find-count {
  font-size: 11px; color: var(--gris4); font-family: 'Roboto', sans-serif;
  min-width: 38px; text-align: center; white-space: nowrap;
}
.find-sep { width: 1px; height: 18px; background: var(--gris2); margin: 0 3px; }
.find-nav, .find-close {
  display: flex; align-items: center; justify-content: center;
  width: 26px; height: 26px; padding: 0;
  background: transparent; border: none; border-radius: 6px;
  color: var(--gris5); cursor: pointer; transition: background .12s, color .12s;
}
.find-nav:hover, .find-close:hover { background: var(--gris2); color: var(--blanco); }
.find-nav svg, .find-close svg { width: 14px; height: 14px; }

/* Resaltado de coincidencias — pintado por la Custom Highlight API,
   no inserta nodos en el DOM, así el contenido guardado no se altera. */
::highlight(doc-find)        { background-color: rgba(255, 213, 79, .35); }
::highlight(doc-find-active) { background-color: rgba(255, 179, 0, .85); color: #1a1a1a; }

/* Toolbar */
.toolbar {
  background: var(--gris1);
  border-bottom: 1px solid var(--gris2);
  padding: 6px 16px;
  display: flex;
  align-items: center;
  gap: 2px;
  flex-wrap: wrap;
  flex-shrink: 0;
}

.tb-sep { width: 1px; height: 18px; background: var(--gris2); margin: 0 4px; }

.tb-btn {
  width: 30px; height: 30px;
  display: flex; align-items: center; justify-content: center;
  background: transparent; border: none; border-radius: 5px;
  color: var(--gris4); cursor: pointer; transition: background .12s, color .12s;
}
.tb-btn svg { width: 14px; height: 14px; }
.tb-btn:hover  { background: var(--gris2); color: var(--blanco); }
.tb-btn.active { background: rgba(201,168,76,.15); color: var(--dorado); }

.tb-select {
  background: transparent; border: 1px solid var(--gris2);
  border-radius: 5px; color: var(--gris5);
  font-size: 12px; font-family: 'Archivo', sans-serif;
  padding: 4px 8px; cursor: pointer; outline: none;
  transition: border-color .15s;
}
.tb-select:hover { border-color: var(--gris3); }
.tb-select option { background: var(--gris1); }

/* Color de texto / resaltado — botón con paleta desplegable */
.tb-color-wrap { position: relative; display: inline-flex; }
.tb-color-btn {
  width: 30px; height: 30px;
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  gap: 2px; background: transparent; border: none; border-radius: 5px;
  color: var(--gris4); cursor: pointer; transition: background .12s, color .12s;
}
.tb-color-btn:hover { background: var(--gris2); color: var(--blanco); }
.tb-color-btn svg { width: 14px; height: 12px; }
.tb-color-bar { display: block; width: 16px; height: 3px; border-radius: 2px; background: var(--dorado); }
.tb-color-pop {
  position: absolute; top: calc(100% + 5px); left: 0; z-index: 60;
  width: 176px; padding: 9px;
  background: var(--gris1); border: 1px solid var(--gris2);
  border-radius: 9px; box-shadow: 0 10px 28px rgba(0,0,0,.45);
}
.tb-swatches { display: grid; grid-template-columns: repeat(5, 1fr); gap: 5px; }
.tb-swatch {
  width: 100%; aspect-ratio: 1; padding: 0; cursor: pointer;
  border: 1px solid rgba(255,255,255,.14); border-radius: 5px;
  transition: transform .1s, border-color .1s;
}
.tb-swatch:hover { transform: scale(1.12); border-color: var(--dorado); }
.tb-color-more, .tb-color-none {
  width: 100%; padding: 6px; cursor: pointer;
  background: transparent; border: 1px solid var(--gris2); border-radius: 6px;
  color: var(--gris5); font-family: 'Roboto', sans-serif; font-size: 11px;
  display: flex; align-items: center; justify-content: center; gap: 6px;
  transition: border-color .12s, color .12s;
}
.tb-color-more { margin-top: 8px; }
.tb-color-none { margin-bottom: 8px; }
.tb-color-more:hover, .tb-color-none:hover { border-color: var(--gris3); color: var(--blanco); }
.tb-color-more svg, .tb-color-none svg { width: 12px; height: 12px; }

/* Área de escritura */
.editor-scroll {
  flex: 1;
  overflow-y: auto;
  padding: 40px 48px;
  background: var(--negro);
}

.editor-page {
  max-width: 760px;
  margin: 0 auto;
  background:  var(--gris1);
  border: 1px solid var(--gris2);
  border-radius: 4px;
  min-height: 900px;
  padding: 56px 64px;
}

.editor-content {
  outline: none;
  font-family: 'Roboto', sans-serif;
  font-size: 14px;
  line-height: 1.8;
  color: var(--blanco);
  min-height: 780px;
}

.editor-content:empty::before {
  content: 'Comenzá a escribir o importá un archivo .docx desde el panel derecho...';
}

/* ══════════════════════════════════════════════════════════
   Mini-editores: header y footer
   ══════════════════════════════════════════════════════════ */
.mini-editor-wrap {
  margin-bottom: 8px;
  border: 1px dashed var(--gris3);
  border-radius: 6px;
  background: rgba(0,0,0,.02);
}
.mini-editor-label {
  display: flex; align-items: center; gap: 6px;
  padding: 6px 10px;
  font-size: 11px;
  color: var(--gris5);
  font-family: 'Roboto', sans-serif;
  text-transform: uppercase;
  letter-spacing: .06em;
  border-bottom: 1px solid var(--gris2);
  background: rgba(0,0,0,.03);
  border-radius: 6px 6px 0 0;
}
.mini-toolbar {
  display: flex; align-items: center; gap: 3px;
  padding: 4px 8px;
  background: var(--gris1);
  border-bottom: 1px solid var(--gris2);
  flex-wrap: wrap;
}
.mini-btn {
  background: transparent; border: 1px solid transparent;
  color: var(--gris5); cursor: pointer;
  padding: 3px 8px; border-radius: 4px;
  font-family: 'Roboto', sans-serif; font-size: 11px;
  min-width: 24px; height: 24px;
  display: inline-flex; align-items: center; justify-content: center;
  transition: background .15s, color .15s;
}
.mini-btn:hover { background: var(--gris2); color: var(--blanco); }
.mini-btn:active { background: rgba(201,168,76,.15); }
.mini-sep { width: 1px; height: 14px; background: var(--gris2); margin: 0 2px; }
.mini-select {
  background: transparent; border: 1px solid var(--gris2); color: var(--gris5);
  border-radius: 4px; padding: 2px 6px; font-size: 11px; height: 24px;
  font-family: 'Roboto', sans-serif; cursor: pointer;
}
.mini-select option { background: var(--gris1); }

.mini-editor {
  min-height: 80px;
  max-height: 200px;
  overflow-y: auto;
  padding: 12px 16px;
  font-family: 'Roboto', sans-serif;
  font-size: 13px;
  line-height: 1.5;
  outline: none;
  color: var(--blanco);
}
.mini-editor:empty::before {
  content: attr(data-placeholder);
  color: var(--gris3);
  font-style: italic;
  pointer-events: none;
}
.mini-editor:focus { background: rgba(255,255,255,.02); }

.mini-separator {
  display: flex; align-items: center; justify-content: center;
  gap: 12px;
  margin: 12px 0;
  font-size: 11px;
  color: var(--gris4);
  font-family: 'Roboto', sans-serif;
  text-transform: uppercase;
  letter-spacing: .1em;
}
.mini-separator::before, .mini-separator::after {
  content: '';
  flex: 1;
  height: 1px;
  background: linear-gradient(to right, transparent, var(--gris2), transparent);
}

/* Imágenes dentro de los editables: nunca romper el layout */
.editor-content img, .mini-editor img {
  max-width: 100%;
  height: auto;
  display: inline-block;
  margin: 4px 0;
  cursor: pointer; /* indicar que son clickeables para el toolbar contextual */
}

/* Imagen actualmente seleccionada (con toolbar contextual activo) */
.editor-content img.img-seleccionada,
.mini-editor img.img-seleccionada {
  outline: 2px solid var(--dorado);
  outline-offset: 2px;
}

/* Toolbar contextual flotante */
.img-toolbar {
  position: fixed;
  z-index: 500;
  background: var(--gris1);
  border: 1px solid var(--gris3);
  border-radius: 8px;
  padding: 4px;
  display: flex;
  align-items: center;
  gap: 2px;
  box-shadow: 0 4px 12px rgba(0,0,0,.15);
  font-family: 'Roboto', sans-serif;
}
.img-toolbar button {
  background: transparent;
  border: none;
  color: var(--blanco);
  cursor: pointer;
  padding: 5px 10px;
  border-radius: 4px;
  font-size: 11px;
  font-family: 'Roboto', sans-serif;
  transition: background .15s;
  display: inline-flex;
  align-items: center;
}
.img-toolbar button:hover { background: var(--gris2); }
.img-toolbar-sep {
  width: 1px;
  height: 14px;
  background: var(--gris3);
  margin: 0 2px;
}
.img-toolbar-danger { color: var(--error) !important; }
.img-toolbar-danger:hover { background: rgba(226,92,92,.15) !important; }
.mini-editor img {
  max-height: 60px; /* Los mini-editores son chicos, forzamos altura razonable */
}

/* Placeholder del editor principal */
.editor-content:empty::before {
  color: var(--gris3);
  font-style: italic;
  pointer-events: none;
}

.editor-content h1 { font-family: 'Roboto', sans-serif; font-size: 24px; font-weight: 700; color: var(--blanco); margin: 0 0 16px; border-bottom: 1px solid var(--gris2); padding-bottom: 10px; }
.editor-content h2 { font-family: 'Roboto', sans-serif; font-size: 18px; font-weight: 600; color: var(--blanco); margin: 24px 0 10px; }
.editor-content h3 { font-family: 'Roboto', sans-serif; font-size: 14px; font-weight: 600; color: var(--gris5); margin: 16px 0 8px; }
.editor-content p  { margin: 0 0 10px; }
.editor-content ul, .editor-content ol { padding-left: 22px; margin: 0 0 10px; }
.editor-content li { margin-bottom: 4px; }
.editor-content strong { font-weight: 700; color: var(--blanco); }
.editor-content table { width: 100%; border-collapse: collapse; margin: 14px 0; font-size: 13px; }
.editor-content td, .editor-content th { border: 1px solid var(--gris2); padding: 8px 12px; text-align: left; }
.editor-content th { background: var(--gris1); font-weight: 600; color: var(--blanco); font-size: 12px; text-transform: uppercase; letter-spacing: .04em; }

/* Panel derecho */
.editor-panel {
  background: var(--gris1);
  border-left: 1px solid var(--gris2);
  display: flex;
  flex-direction: column;
  overflow-y: auto;
}

.panel-topbar {
  padding: 12px 16px;
  border-bottom: 1px solid var(--gris2);
  flex-shrink: 0;
}

.panel-back {
  display: inline-flex; align-items: center; gap: 6px;
  font-size: 12px; color: var(--gris4);
  text-decoration: none; transition: color .15s;
}
.panel-back:hover { color: var(--blanco); }

.panel-section {
  padding: 14px 16px;
  border-bottom: 1px solid var(--gris2);
}

.panel-section-title {
  font-size: 11px; font-weight: 600;
  letter-spacing: .1em; text-transform: uppercase;
  color: var(--gris3); margin-bottom: 10px;
}

.manual-info-nombre { font-size: 13px; font-weight: 600; color: var(--blanco); margin-bottom: 3px; line-height: 1.3; }
.manual-info-cat    { font-size: 12px; color: var(--gris4); font-family: 'Roboto', sans-serif; }

.status-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 7px; }
.status-label { font-size: 12px; color: var(--gris4); font-family: 'Roboto', sans-serif; }
.status-value { font-size: 12px; color: var(--gris5); font-weight: 500; }
.status-value.guardado  { color: var(--exito); }
.status-value.pendiente { color: var(--dorado); }

.drop-zone {
  border: 1px dashed var(--gris2); border-radius: 8px;
  padding: 16px 10px; text-align: center; cursor: pointer;
  display: flex; flex-direction: column; align-items: center;
  transition: border-color .2s, background .2s;
}
.drop-zone:hover { border-color: var(--dorado); background: rgba(201,168,76,.04); }
.drop-zone.drag  { border-color: var(--dorado); background: rgba(201,168,76,.08); }

.version-list { display: flex; flex-direction: column; gap: 6px; }

.version-item {
  background: rgba(255,255,255,.03); border: 1px solid var(--gris2);
  border-radius: 8px; padding: 10px 12px; cursor: pointer;
  transition: border-color .15s, background .15s;
}
.version-item:hover  { border-color: var(--gris3); background: rgba(255,255,255,.05); }
.version-item.activa { border-color: rgba(201,168,76,.4); background: rgba(201,168,76,.05); }

.version-item-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 3px; }
.version-num  { font-size: 13px; font-weight: 600; color: var(--blanco); }
.version-fecha { font-size: 11px; color: var(--gris4); font-family: 'Roboto', sans-serif; }
.version-autor { font-size: 11px; color: var(--gris4); font-family: 'Roboto', sans-serif; }
.version-tag   { display: inline-block; font-size: 11px; font-weight: 500; padding: 2px 7px; border-radius: 20px; background: rgba(201,168,76,.15); color: var(--dorado); margin-top: 4px; }

/* Modal elegir tipo de version (menor / mayor) */
.ver-opciones { display: flex; flex-direction: column; gap: 10px; }
.ver-opcion {
  text-align: left; width: 100%; cursor: pointer;
  background: rgba(255,255,255,.03); border: 1px solid var(--gris2);
  border-radius: 10px; padding: 14px 16px;
  transition: border-color .15s, background .15s;
}
.ver-opcion:hover { border-color: var(--dorado); background: rgba(201,168,76,.06); }
.ver-opcion-num    { font-size: 18px; font-weight: 700; color: var(--dorado); font-family: 'Archivo', sans-serif; margin-bottom: 2px; }
.ver-opcion-titulo { font-size: 14px; font-weight: 600; color: var(--blanco); margin-bottom: 4px; }
.ver-opcion-desc   { font-size: 12.5px; color: var(--gris4); line-height: 1.5; font-family: 'Roboto', sans-serif; }

/* Modal */
.modal-overlay {
  display: none; position: fixed; inset: 0;
  background: rgba(0,0,0,.65); z-index: 500;
  align-items: center; justify-content: center; padding: 16px;
}
.modal-overlay.open { display: flex; }

.modal-box {
  background: var(--gris1); border: 1px solid var(--gris2);
  border-radius: 14px; width: 50%; overflow: hidden;
}

.modal-header {
  padding: 16px 20px; border-bottom: 1px solid var(--gris2);
  display: flex; align-items: center; justify-content: space-between;
}
.modal-header h3 { font-size: 14px; font-weight: 600; color: var(--blanco); }

.modal-close {
  background: transparent; border: none; cursor: pointer;
  color: var(--gris4); padding: 4px; border-radius: 5px;
  transition: color .15s, background .15s; display: flex;
}
.modal-close:hover { color: var(--blanco); background: var(--gris2); }

.modal-body   { padding: 20px; }
.modal-footer {
  padding: 14px 20px; border-top: 1px solid var(--gris2);
  display: flex; justify-content: flex-end; gap: 8px;
}

/* Form fields (modal de editar datos) */
.form-group { margin-bottom: 16px; }
.form-group:last-of-type { margin-bottom: 0; }
.form-label {
  display: block; font-size: 11px; font-weight: 600;
  text-transform: uppercase; letter-spacing: 0.6px;
  color: var(--gris4); margin-bottom: 6px;
  font-family: 'Archivo', sans-serif;
}
.form-input {
  width: 100%; box-sizing: border-box;
  background: var(--gris1); border: 1px solid var(--gris2);
  border-radius: 8px; padding: 10px 12px;
  font-size: 13px; color: var(--blanco);
  font-family: 'Roboto', sans-serif;
  transition: border-color .15s, background .15s;
}
.form-input::placeholder { color: var(--gris3); }
.form-input:focus {
  outline: none; border-color: var(--dorado); background: var(--negro);
}
.form-error {
  display: none; background: rgba(226,92,92,.1);
  border: 1px solid rgba(226,92,92,.3); border-radius: 8px;
  padding: 10px 12px; color: var(--error); font-size: 12px;
  margin-top: 12px; font-family: 'Roboto', sans-serif;
}

.btn-success { background: var(--exito); color: var(--negro); }
.btn-success:hover { opacity: .88; }

/* Toast */
.toast {
  position: fixed; bottom: 24px; right: 24px;
  background: var(--gris1); border: 1px solid var(--gris2);
  border-radius: 10px; padding: 12px 16px;
  font-size: 13px; color: var(--blanco);
  display: flex; align-items: center; gap: 10px;
  transform: translateY(80px); opacity: 0;
  transition: transform .3s, opacity .3s;
  z-index: 600; font-family: 'Roboto', sans-serif; max-width: 340px;
}
.toast.show { transform: translateY(0); opacity: 1; }

/* Scrollbar */
::-webkit-scrollbar { width: 6px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: var(--gris2); border-radius: 3px; }
::-webkit-scrollbar-thumb:hover { background: var(--gris3); }

.version-item.cargada {
  border-color: rgba(55, 138, 221, .5);
  background: rgba(55, 138, 221, .06);
}
.version-item.cargada .version-num {
  color: #378ADD;
}

/* Notas / Sugerencias */
.notas-list { display: flex; flex-direction: column; gap: 8px; }
.nota-item {
  background: rgba(255,255,255,.03); border: 1px solid var(--gris2);
  border-radius: 8px; padding: 10px 12px;
}
.nota-item-header {
  display: flex; align-items: center; justify-content: space-between;
  gap: 8px; margin-bottom: 5px;
}
.nota-autor { font-size: 12px; font-weight: 600; color: var(--blanco); }
.nota-fecha { font-size: 10.5px; color: var(--gris4); font-family: 'Roboto', sans-serif; white-space: nowrap; }
.nota-contenido { font-size: 12.5px; color: var(--gris5); font-family: 'Roboto', sans-serif; line-height: 1.5; white-space: pre-wrap; word-break: break-word; }
.nota-meta { display: flex; align-items: center; gap: 6px; margin-top: 7px; flex-wrap: wrap; }
.nota-estado {
  display: inline-block; font-size: 9.5px; font-weight: 600;
  letter-spacing: .04em; text-transform: uppercase;
  padding: 2px 7px; border-radius: 20px;
}
.nota-estado.pendiente { background: rgba(201,168,76,.15); color: var(--dorado); }
.nota-estado.leida     { background: rgba(55,138,221,.15); color: #378ADD; }
.nota-estado.resuelta  { background: rgba(92,184,122,.15); color: var(--exito); }
.nota-ver { font-size: 11px; color: var(--gris4); font-family: 'Roboto', sans-serif; }
.nota-acciones { display: flex; gap: 6px; margin-top: 8px; }
.nota-btn {
  flex: 1; font-size: 10.5px; font-family: 'Roboto', sans-serif;
  padding: 5px 8px; border-radius: 6px; cursor: pointer;
  background: transparent; border: 1px solid var(--gris2); color: var(--gris5);
  transition: border-color .15s, color .15s, background .15s;
}
.nota-btn:hover { border-color: var(--gris3); color: var(--blanco); background: rgba(255,255,255,.04); }
.nota-btn.activo { border-color: rgba(201,168,76,.4); color: var(--dorado); background: rgba(201,168,76,.06); cursor: default; }

</style>

<script src="<?= BASE_URL_PHP ?>/js/mammoth.browser.min.js"></script>
<script>
const MANUAL_ID  = new URLSearchParams(location.search).get('id');
const BASE_PHP   = '<?= BASE_URL_PHP ?>';
let rolUsuario   = '';
let miUserId     = null; // v2.3: id del usuario actual, para chequear notas propias

let estado = {
  manual:        null,
  versiones:     [],
  htmlOriginal:  '',
  modificado:    false,
  tipoCambio:    'mayor',   // elegido en el modal de version antes de publicar
  versionInicial: null,     // {number, minor} — SOLO en la primera publicacion del manual
};

// v2.3 — categorías activas de la empresa del manual + ids actualmente asignados
let categoriasEmpresa     = []; // [{ id, name, description, is_active, empresa_id }]
let idsAsignadasManual    = new Set(); // ids de cats asignadas al manual (estado en DB)
let usuariosEmpresa       = [];        // socios comerciales de la empresa (con .categorias)
let catsCompletas         = new Set(); // category_ids que van completos (dinamicos)
let usuariosSelManual     = new Set(); // user_ids asignados individualmente

// ── INICIALIZAR ───────────────────────────────────────────────
async function init() {
  if (!MANUAL_ID) {
    mostrarToast('No se especificó un manual.', 'error');
    setTimeout(() => window.location.href = 'manuales.php', 2000);
    return;
  }

  // Setear botón volver según rol
  try {
    const me = await apiFetch('GET', '/me');
    rolUsuario = me.rol;
    miUserId   = me.id;
    const urlVolver = me.rol === 'super_admin'
      ? `${BASE_PHP}/manuales.php`
      : `${BASE_PHP}/manuales-mi-empresa.php`;
    document.getElementById('btn-volver').href = urlVolver;
  } catch { /* si falla, el href queda en # */ }

  try {
    const manual = await apiFetch('GET', `/manuales/${MANUAL_ID}`);
    estado.manual = manual;

    document.title = `Editor — ${manual.titulo}`;
    document.getElementById('panel-titulo').textContent    = manual.titulo;
    document.getElementById('panel-categoria').textContent = manual.categoria || 'Sin categoría';
    document.getElementById('st-estado').textContent       = manual.estado;
    document.getElementById('st-estado').className =
      `status-value ${manual.estado === 'publicado' ? 'guardado' : 'pendiente'}`;

    // Cargar versiones
  const todasVersiones = await apiFetch('GET', `/manuales/${MANUAL_ID}/versiones`);
    estado.versiones = todasVersiones;

    // Filtrar borradores y versiones publicadas por separado
    const borrador = todasVersiones.find(v => v.version_number === 0);
    const activa   = todasVersiones.find(v => v.es_activa == 1);

    renderVersiones(todasVersiones.filter(v => v.version_number > 0));

    // Prioridad correcta: activa primero, borrador solo si no hay versión publicada
    if (activa) {
      cargarVersion(activa);
      if (borrador) {
        mostrarToast('Hay un borrador guardado disponible en el historial.', 'exito');
      }
    } else if (borrador) {
      cargarVersion(borrador);
      mostrarToast('Se cargó el borrador guardado.', 'exito');
    } else {
      habilitarEditor();
    }
    cargarNotas();

    // v2.3: cargar categorías de la empresa del manual + ids asignados.
    // Lo hacemos en paralelo y actualizamos el resumen del botón "Visible para".
    await cargarCategoriasYAsignaciones();

    // Si viene de importación desde manuales.php
    const htmlImportado = sessionStorage.getItem(`import_html_${MANUAL_ID}`);
    if (htmlImportado) {
      document.getElementById('editor').innerHTML = htmlImportado;
      sessionStorage.removeItem(`import_html_${MANUAL_ID}`);
      actualizarContador();
      marcarConCambios();
      mostrarToast('Documento importado y listo para editar.', 'exito');
    }

  } catch (e) {
    mostrarToast('Error al cargar el manual.', 'error');
  }
}

function cargarVersion(version) {
  estado.htmlOriginal = version.contenido_html || '';
  document.getElementById('editor').innerHTML = version.contenido_html || '';

  // Header/footer: se leen del SNAPSHOT DE LA VERSIÓN.
  //
  // Antes salían de estado.manual (la copia de trabajo), con el argumento de que
  // "viven en el manual y no cambian entre versiones". Eso dejó de ser cierto: ahora
  // cada versión congela su propio encabezado/pie al publicarse, y lectura.php ya
  // lee ese snapshot. Si el editor siguiera mostrando la copia de trabajo, el
  // historial del editor mentiría aunque el manual publicado dijera la verdad.
  //
  // Esto importa especialmente al CARGAR UNA VERSIÓN VIEJA del historial
  // (cargarVersionDesdeJSON): antes traía el contenido de la v1 con el encabezado
  // de HOY, y publicar desde ahí generaba un documento mitad viejo y mitad nuevo.
  // Ahora el rollback es coherente: contenido de la v1 + encabezado de la v1.
  //
  // ?? y no ||: un encabezado guardado como cadena vacía ('') es una decisión del
  // usuario (lo borró). Con || caería al del manual y le reaparecería lo que acaba
  // de borrar. El ?? solo cubre null/undefined: versiones anteriores a la migración,
  // o un manual que todavía no tiene ninguna versión.
  const headerHtml = version.encabezado_html ?? estado.manual?.encabezado_html ?? '';
  const footerHtml = version.pie_pagina_html ?? estado.manual?.pie_pagina_html ?? '';
  estado.headerOriginal = headerHtml;
  estado.footerOriginal = footerHtml;
  document.getElementById('editor-header').innerHTML = headerHtml;
  document.getElementById('editor-footer').innerHTML = footerHtml;

  // Feature imágenes: setear listeners de paste/drop en los 3 editables.
  // Se hace una sola vez después de cargar el contenido.
  setupTodosLosImagenListeners();

  document.getElementById('st-version').textContent =
    `v${verNum(version)}`;

  actualizarContador();
  habilitarEditor();
  marcarSinCambios();
  document.querySelectorAll('.version-item').forEach(el => el.classList.remove('cargada'));
  const id = version.version_number === 0 ? 'borrador' : `v${verNum(version)}`;
  const itemEl = document.querySelector(`.version-item[data-version="${id}"]`);
  if (itemEl) itemEl.classList.add('cargada');
}

function habilitarEditor() {
  document.getElementById('btn-guardar').disabled  = false;
  document.getElementById('btn-publicar').disabled = false;
}

// ── VERSIONES ─────────────────────────────────────────────────
function renderVersiones(versiones) {
  const el = document.getElementById('version-list');
  const borrador = estado.versiones.find(v => v.version_number === 0);

  if (!versiones.length && !borrador) {
    el.innerHTML = `<div style="font-size:12px;color:var(--gris3);font-family:'Roboto',sans-serif">Sin versiones publicadas aún.</div>`;
    return;
  }

  const itemsBorrador = borrador ? [`
    <div class="version-item" data-version="borrador" style="border-color:rgba(201,168,76,.2)" onclick='cargarVersionDesdeJSON(${JSON.stringify(borrador)})'>
      <div class="version-item-header">
        <span class="version-num" style="color:var(--dorado)">Borrador</span>
        <span class="version-fecha">${formatFecha(borrador.publicado_at)}</span>
      </div>
      <div class="version-autor">Sin publicar</div>
    </div>
  `] : [];

  el.innerHTML = [...versiones]
    .sort((a, b) => (b.version_number - a.version_number) || ((b.version_minor ?? 0) - (a.version_minor ?? 0)))
    .map(v => `
      <div class="version-item ${v.es_activa ? 'activa' : ''}" data-version="v${verNum(v)}" onclick='cargarVersionDesdeJSON(${JSON.stringify(v)})'>
        <div class="version-item-header">
          <span class="version-num">v${verNum(v)}</span>
          <span class="version-fecha">${formatFecha(v.publicado_at)}</span>
        </div>
        <div class="version-autor">${v.publicado_por?.system_admin?.nombre || 'Sistema'}</div>
        ${v.es_activa ? '<span class="version-tag">Activa</span>' : ''}
      </div>
    `)
    .concat(itemsBorrador)
    .join('');
}
function cargarVersionDesdeJSON(version) {
  if (estado.modificado && !confirm('Hay cambios sin guardar. ¿Continuar?')) return;
  cargarVersion(version);
}

// ── IMPORTAR WORD ─────────────────────────────────────────────
function onDragOver(e) { e.preventDefault(); document.getElementById('drop-zone').classList.add('drag'); }
function onDragLeave()  { document.getElementById('drop-zone').classList.remove('drag'); }
function onDrop(e) {
  e.preventDefault();
  document.getElementById('drop-zone').classList.remove('drag');
  const f = e.dataTransfer.files[0];
  if (f) importarDocx(f);
}

async function importarDocx(file) {
  if (!file?.name.endsWith('.docx')) {
    mostrarToast('Solo se aceptan archivos .docx', 'error'); return;
  }

  const resultEl   = document.getElementById('import-result');
  const warningsEl = document.getElementById('import-warnings');
  resultEl.style.display = warningsEl.style.display = 'none';

  // Contadores para reportar al usuario al final.
  let imagenesSubidas = 0;
  let imagenesFallidas = 0;

  mostrarToast('Convirtiendo documento...', 'exito');

  try {
    const ab = await file.arrayBuffer();
    // Preserva la ALINEACIÓN de párrafos (justificado del Word). Mammoth la
    // descarta por defecto; transformDocument da acceso a paragraph.alignment.
    // Se marca cada párrafo con una clase temporal (__al-*) que el post-proceso
    // de abajo convierte en style="text-align:*" inline (lo único que sobrevive
    // el HTMLPurifier del backend, que no permite class ni p sin [style]).
     const marcarAlineacion = mammoth.transforms.paragraph(function (par) {
      const a = par.alignment;
      if (a === 'both' || a === 'justified') {
        return Object.assign({}, par, { styleId: 'AlJustify', styleName: 'AlJustify' });
      }
      if (a === 'center') {
        return Object.assign({}, par, { styleId: 'AlCenter', styleName: 'AlCenter' });
      }
      if (a === 'right' || a === 'end') {
        return Object.assign({}, par, { styleId: 'AlRight', styleName: 'AlRight' });
      }
      return par;
    });

    // Mapa de resaltados estandar de Word (w:highlight) -> color hex.
    // Son 16 nombres FIJOS; el highlight de Word no admite hex arbitrario
    // (eso seria color de fuente/fondo de celda, que van por otra via).
    const _HL_MAP = {
      yellow:'#FFFF00', green:'#00FF00', cyan:'#00FFFF', magenta:'#FF00FF',
      blue:'#0000FF', red:'#FF0000', darkblue:'#000080', darkcyan:'#008080',
      darkgreen:'#008000', darkmagenta:'#800080', darkred:'#800000',
      darkyellow:'#808000', darkgray:'#808080', lightgray:'#C0C0C0',
      black:'#000000', white:'#FFFFFF'
    };

    // Marca cada run resaltado con un styleName sintetico (__hl_<nombre>),
    // igual que la alineacion pero a nivel run. 'none' o desconocido se
    // dejan sin tocar.
    const marcarResaltado = mammoth.transforms.run(function (run) {
      const h = run.highlight ? String(run.highlight).toLowerCase() : null;
      if (h && _HL_MAP[h]) {
        return Object.assign({}, run, { styleId: '__hl_' + h, styleName: '__hl_' + h });
      }
      return run;
    });

    // Reglas de styleMap para los resaltados, generadas del mapa (una por color).
    const _hlRules = Object.keys(_HL_MAP).map(function (n) {
      return "r[style-name='__hl_" + n + "'] => span.__hl-" + n + ":fresh";
    });

    const result = await mammoth.convertToHtml({ arrayBuffer: ab }, {
      transformDocument: function (d) { return marcarResaltado(marcarAlineacion(d)); },
      styleMap: [
        // Ahora SÍ con style-name (lo único que Mammoth parsea). Los nombres
        // 'AlJustify' etc. los inyectó transformDocument arriba.
        "p[style-name='AlJustify'] => p.__al-justify:fresh",
        "p[style-name='AlCenter']  => p.__al-center:fresh",
        "p[style-name='AlRight']   => p.__al-right:fresh",
        ..._hlRules,
        "p[style-name='Heading 1'] => h1:fresh",
        "p[style-name='Heading 2'] => h2:fresh",
        "p[style-name='Heading 3'] => h3:fresh",
        "p[style-name='Título 1'] => h1:fresh",
        "p[style-name='Título 2'] => h2:fresh",
        "p[style-name='Título 3'] => h3:fresh",
        "p[style-name='Title']    => h1:fresh",
      ],
      // Handler de imágenes: cada imagen del docx se sube al server y en el
      // HTML queda la URL de nuestro endpoint autenticado. Si el upload falla,
      // la imagen se pierde pero la conversión sigue con las demás.
      convertImage: mammoth.images.imgElement(async function(image) {
        try {
          const contentType = image.contentType || 'image/png';
          const buffer      = await image.read('base64');
          const blob        = base64AImageBlob(buffer, contentType);
          const url         = await subirImagen(blob);
          imagenesSubidas++;
          return { src: url };
        } catch (err) {
          console.warn('Error al subir imagen del docx:', err);
          imagenesFallidas++;
          // Devolver src vacío hace que la imagen no se renderice pero la
          // conversión sigue con las demás.
          return { src: '' };
        }
      })
    });

    // Post-proceso: convertir las clases temporales de alineación en style inline.
    // HTMLPurifier del backend borra class y p pelado, pero conserva
    // style="text-align:*". Así el justificado sobrevive editor -> publicar -> lectura -> PDF.
    const _tmpDoc = document.createElement('div');
    _tmpDoc.innerHTML = result.value;
    _tmpDoc.querySelectorAll('.__al-justify').forEach(function (el) {
      el.style.textAlign = 'justify'; el.classList.remove('__al-justify');
      if (!el.getAttribute('class')) el.removeAttribute('class');
    });
    _tmpDoc.querySelectorAll('.__al-center').forEach(function (el) {
      el.style.textAlign = 'center'; el.classList.remove('__al-center');
      if (!el.getAttribute('class')) el.removeAttribute('class');
    });
    _tmpDoc.querySelectorAll('.__al-right').forEach(function (el) {
      el.style.textAlign = 'right'; el.classList.remove('__al-right');
      if (!el.getAttribute('class')) el.removeAttribute('class');
    });

    // Resaltados: clase temporal __hl-<nombre> -> background-color inline.
    // Mismo criterio que la alineacion: HTMLPurifier conserva el style, no la clase.
    Object.keys(_HL_MAP).forEach(function (n) {
      _tmpDoc.querySelectorAll('.__hl-' + n).forEach(function (el) {
        el.style.backgroundColor = _HL_MAP[n];
        el.classList.remove('__hl-' + n);
        if (!el.getAttribute('class')) el.removeAttribute('class');
      });
    });

    document.getElementById('editor').innerHTML = _tmpDoc.innerHTML;
    actualizarContador();
    marcarConCambios();

    const tmp   = document.createElement('div');
    tmp.innerHTML = result.value;
    const words = (tmp.innerText || '').trim().split(/\s+/).filter(Boolean).length;

    let resumen = `✓ ${file.name} — ${words.toLocaleString('es-AR')} palabras importadas`;
    if (imagenesSubidas > 0) resumen += ` · ${imagenesSubidas} imagen(es) subidas`;
    if (imagenesFallidas > 0) resumen += ` · ⚠ ${imagenesFallidas} imagen(es) no pudieron subirse`;
    resultEl.textContent   = resumen;
    resultEl.style.display = 'block';

    if (result.messages.length) {
      warningsEl.innerHTML = `⚠ ${result.messages.length} advertencia(s): ` +
        result.messages.slice(0,3).map(m => m.message).join(' · ');
      warningsEl.style.display = 'block';
    }

    mostrarToast('Documento importado correctamente.', 'exito');

  } catch (err) {
    console.error('Error importarDocx:', err);
    mostrarToast('Error al convertir el archivo.', 'error');
  }
}

// ══════════════════════════════════════════════════════════
// FEATURE IMÁGENES — helpers y listeners
// ══════════════════════════════════════════════════════════

// Sube un Blob (imagen) al backend y devuelve la URL relativa que hay
// que meter en el src del <img>. La URL apunta a nuestro endpoint
// autenticado (/api/manuales-imagenes/{id}/descargar) — el sanitizador
// del backend solo permite src que empiecen con /api/manuales-imagenes/
// o con data:image, así que cualquier otra URL sería descartada.
async function subirImagen(blob) {
  const fd = new FormData();
  fd.append('archivo', blob, blob.name || 'imagen.png');
  const res = await fetch(`${API}/manuales/${MANUAL_ID}/imagenes`, {
    method: 'POST',
    credentials: 'include',
    body: fd,
  });
  if (!res.ok) {
    let msg = 'Error al subir imagen';
    try { const data = await res.json(); msg = data.error || msg; } catch {}
    throw new Error(msg);
  }
  const data = await res.json();

  // FIX: el backend devuelve URL relativa a la raíz del dominio
  // (/api/manuales-imagenes/N/descargar). En setups con subpath (XAMPP en
  // /manuales-franquiciantes/public), esa URL genera 404 porque apunta a
  // localhost/api/... en vez de localhost/manuales-franquiciantes/public/api/...
  //
  // La variable global API ya tiene el path base correcto. Reemplazamos el
  // prefijo /api por el valor de API. Así el src del <img> queda con URL
  // completa que resuelve correctamente en todos los entornos.
  //
  // Backend sanitizador acepta URLs que empiecen con /api/manuales-imagenes/
  // o data:image/. También acepta URLs absolutas con http/https, entonces
  // ${API}/manuales-imagenes/N/descargar pasa el filtro porque conserva el
  // path /api/manuales-imagenes/ (aunque tenga http://host: prefijo).
  //
  // WAIT: revisar sanitizador — el filtro exige que empiece EXACTAMENTE con
  // '/api/manuales-imagenes/'. Una URL http://.../api/... NO va a pasar.
  // Solución: si API es solo un path (empieza con /), concatenar simple.
  // Si es URL absoluta (http://), usar tal cual (el filtro backend hay que
  // ajustar). Por ahora asumimos API es un path relativo tipo
  // '/manuales-franquiciantes/public/api' — el más común en el proyecto.
  if (data.url.startsWith('/api/')) {
    // Reemplazar /api al inicio por el path base completo de API.
    // API típicamente es '/manuales-franquiciantes/public/api'.
    return API + data.url.substring(4); // quita '/api' del inicio y concatena
  }
  return data.url;
}

// Convierte una cadena base64 (sin prefijo data:) en un Blob del contentType
// especificado. Usado por el handler de Mammoth.
function base64AImageBlob(base64, contentType) {
  const binary = atob(base64);
  const bytes  = new Uint8Array(binary.length);
  for (let i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i);
  return new Blob([bytes], { type: contentType });
}

// Setea listeners de paste y drop en un editable, para subir imágenes
// automáticamente al server cuando el usuario las pegue o arrastre.
function setupImagenListeners(editableId) {
  const el = document.getElementById(editableId);
  if (!el) return;

  // Paste: interceptar imágenes del portapapeles (Ctrl+V con imagen).
  el.addEventListener('paste', async (e) => {
    const items = e.clipboardData?.items || [];
    for (const item of items) {
      if (item.type && item.type.startsWith('image/')) {
        e.preventDefault();
        const blob = item.getAsFile();
        if (!blob) continue;
        await procesarYInsertarImagen(el, blob);
        return; // procesamos una sola imagen por paste
      }
    }
    // Si no había imagen, dejamos que el paste normal siga (texto/html).
  });

  // Drag & drop: interceptar imágenes arrastradas desde el sistema de archivos.
  el.addEventListener('dragover', (e) => {
    if (e.dataTransfer?.types?.includes('Files')) {
      e.preventDefault();
    }
  });
  el.addEventListener('drop', async (e) => {
    const files = Array.from(e.dataTransfer?.files || []);
    const imagenes = files.filter(f => f.type.startsWith('image/'));
    if (imagenes.length === 0) return;
    e.preventDefault();
    for (const file of imagenes) {
      await procesarYInsertarImagen(el, file);
    }
  });
}

// Sube la imagen y la inserta en el editor en la posición del cursor.
// Mientras sube, muestra toast. Si falla, muestra error.
//
// IMPORTANTE: no usamos execCommand('insertImage', url) porque en Chrome
// éste comando a veces convierte la URL en data URI (base64 inline) — bug
// conocido del navegador. En su lugar creamos el <img> manualmente y lo
// insertamos con range.insertNode() en la posición del cursor.
async function procesarYInsertarImagen(editorEl, blob) {
  mostrarToast('Subiendo imagen...', 'exito');
  try {
    const url = await subirImagen(blob);

    editorEl.focus();

    // Crear el <img> con la URL del server (no base64).
    const img = document.createElement('img');
    img.src   = url;
    img.alt   = '';

    // Insertar en la posición del cursor. Si no hay selección adentro del
    // editable, insertamos al final.
    const sel = window.getSelection();
    let inserted = false;
    if (sel && sel.rangeCount > 0) {
      const range = sel.getRangeAt(0);
      if (editorEl.contains(range.commonAncestorContainer)) {
        range.deleteContents();
        range.insertNode(img);
        // Mover el cursor después de la imagen para que el usuario pueda seguir escribiendo.
        range.setStartAfter(img);
        range.setEndAfter(img);
        sel.removeAllRanges();
        sel.addRange(range);
        inserted = true;
      }
    }
    if (!inserted) {
      editorEl.appendChild(img);
    }

    marcarConCambios();
    mostrarToast('Imagen subida.', 'exito');
  } catch (err) {
    console.error('procesarYInsertarImagen:', err);
    mostrarToast(err.message || 'Error al subir la imagen.', 'error');
  }
}

// Se llama al cargar la página, después de que existan los 3 editables.
function setupTodosLosImagenListeners() {
  setupImagenListeners('editor');
  setupImagenListeners('editor-header');
  setupImagenListeners('editor-footer');
  setupToolbarContextualImagen();
}

// Abre un file dialog para seleccionar una imagen del disco, la sube al server
// y la inserta en el editor especificado.
function insertarImagenDesdeArchivo(editorId) {
  const input = document.createElement('input');
  input.type = 'file';
  input.accept = 'image/jpeg,image/png,image/gif,image/webp,image/svg+xml';
  input.style.display = 'none';
  document.body.appendChild(input);

  input.addEventListener('change', async () => {
    const file = input.files?.[0];
    document.body.removeChild(input);
    if (!file) return;

    // Validación básica en cliente (el backend también valida).
    if (!file.type.startsWith('image/')) {
      mostrarToast('El archivo debe ser una imagen.', 'error');
      return;
    }
    if (file.size > 5 * 1024 * 1024) {
      mostrarToast('La imagen supera el máximo de 5 MB.', 'error');
      return;
    }

    const editorEl = document.getElementById(editorId);
    if (!editorEl) return;
    editorEl.focus();
    await procesarYInsertarImagen(editorEl, file);
  });

  input.click();
}

// ══════════════════════════════════════════════════════════
// Toolbar contextual de imagen (resize + eliminar)
// ══════════════════════════════════════════════════════════
let imagenActiva = null;

function setupToolbarContextualImagen() {
  // Un solo listener global de click captura todo:
  //   - Click en imagen dentro de editable → mostrar toolbar
  //   - Click fuera → ocultar
  document.addEventListener('click', (e) => {
    const target = e.target;

    // ¿Click en una imagen dentro de un editable?
    if (target.tagName === 'IMG' && target.closest('.editor-content, .mini-editor')) {
      seleccionarImagen(target);
      return;
    }

    // ¿Click en el toolbar de imagen? No hacer nada — los botones tienen sus handlers.
    if (target.closest('#img-toolbar')) return;

    // Click en cualquier otro lado → deseleccionar
    deseleccionarImagen();
  });

  // Reposicionar toolbar al hacer scroll o resize de ventana.
  window.addEventListener('scroll', reposicionarToolbarImagen, true);
  window.addEventListener('resize', reposicionarToolbarImagen);
}

function seleccionarImagen(img) {
  // Quitar highlight de imagen previa
  if (imagenActiva && imagenActiva !== img) {
    imagenActiva.classList.remove('img-seleccionada');
  }
  imagenActiva = img;
  img.classList.add('img-seleccionada');
  reposicionarToolbarImagen();
}

function deseleccionarImagen() {
  if (!imagenActiva) return;
  imagenActiva.classList.remove('img-seleccionada');
  imagenActiva = null;
  document.getElementById('img-toolbar').style.display = 'none';
}

function reposicionarToolbarImagen() {
  const toolbar = document.getElementById('img-toolbar');
  if (!imagenActiva || !toolbar) return;

  const rect = imagenActiva.getBoundingClientRect();
  toolbar.style.display = 'flex';

  // Medimos el toolbar para centrar horizontalmente
  const tbRect = toolbar.getBoundingClientRect();

  // Preferimos colocar el toolbar arriba de la imagen.
  // Si no hay espacio arriba (imagen en el tope de la pantalla), lo ponemos abajo.
  const espacioArriba = rect.top;
  const arriba = espacioArriba > tbRect.height + 12;

  const top = arriba
    ? rect.top - tbRect.height - 8
    : rect.bottom + 8;

  // Centrar horizontalmente sobre la imagen, sin salirse del viewport.
  let left = rect.left + (rect.width / 2) - (tbRect.width / 2);
  left = Math.max(8, Math.min(left, window.innerWidth - tbRect.width - 8));

  toolbar.style.top  = `${top}px`;
  toolbar.style.left = `${left}px`;
}

function setearImagenAncho(porcentaje) {
  if (!imagenActiva) return;
  imagenActiva.style.width  = `${porcentaje}%`;
  imagenActiva.style.height = 'auto'; // preservar proporción
  marcarConCambios();
  // Reposicionar toolbar porque el tamaño cambió
  setTimeout(reposicionarToolbarImagen, 50);
}

function eliminarImagenSeleccionada() {
  if (!imagenActiva) return;
  const img = imagenActiva;
  deseleccionarImagen();
  img.remove();
  marcarConCambios();
  // Nota: el archivo del server queda huérfano hasta el próximo guardado,
  // cuando limpiarHuerfanas() del backend lo detecta y borra.
}

// ── EDITOR ────────────────────────────────────────────────────
function exec(cmd, val = null) {
  document.getElementById('editor').focus();
  document.execCommand(cmd, false, val);
  actualizarContador();
  marcarConCambios();
  actualizarEstadoToolbar();
}

// Como exec() pero contra un mini-editor específico (header/footer).
// Preserva el foco en el mini-editor antes de aplicar el comando.
function execMini(editorId, cmd, val = null) {
  const el = document.getElementById(editorId);
  if (!el) return;
  el.focus();
  document.execCommand(cmd, false, val);
  marcarConCambios();
}

// Aplica un tamaño de fuente en pt (como Word) a la selección actual del
// mini-editor especificado. No usa execCommand('fontSize', ...) porque este
// solo acepta 1-7 (categorías HTML de <font size="X">, deprecadas).
//
// En su lugar, envuelve la selección en un <span style="font-size: Xpt">.
// Fallback: si surroundContents falla (por selección multi-elemento), usa
// execCommand y convierte los <font size="7"> resultantes a spans.
function setFontSizePt(editorId, pt) {
  if (!pt) return;
  const el = document.getElementById(editorId);
  if (!el) return;

  el.focus();

  const sel = window.getSelection();
  if (!sel || sel.rangeCount === 0) {
    // Sin cursor todavía. Aplicar el próximo tipeo.
    return aplicarProximoFontSize(el, pt);
  }

  const range = sel.getRangeAt(0);

  // Verificar que el cursor esté dentro del editable correcto.
  if (!el.contains(range.commonAncestorContainer)) {
    return aplicarProximoFontSize(el, pt);
  }

  if (range.collapsed) {
    // Sin texto seleccionado, solo cursor. Insertamos un <span> vacío con el
    // tamaño y ponemos el cursor adentro. Cualquier tipeo siguiente hereda el
    // estilo. Es como funciona Word.
    aplicarProximoFontSize(el, pt, range);
    marcarConCambios();
    return;
  }

  // Hay texto seleccionado. Envolver en <span>.
  try {
    const span = document.createElement('span');
    span.style.fontSize = `${pt}pt`;
    range.surroundContents(span);
  } catch (e) {
    // surroundContents falla cuando la selección abarca elementos parciales.
    // Fallback: usar execCommand con size=7 y luego reemplazar los <font>.
    document.execCommand('fontSize', false, '7');
    el.querySelectorAll('font[size="7"]').forEach(f => {
      const s = document.createElement('span');
      s.style.fontSize = `${pt}pt`;
      s.innerHTML = f.innerHTML;
      f.replaceWith(s);
    });
  }

  marcarConCambios();
}

// Helper: inserta un <span> vacío con el font-size deseado en la posición
// del cursor (o al final del editable si no hay cursor), y coloca el cursor
// adentro. El siguiente tipeo hereda el estilo del span.
function aplicarProximoFontSize(el, pt, existingRange) {
  const span = document.createElement('span');
  span.style.fontSize = `${pt}pt`;
  // Un zero-width space para que el span no colapse hasta que el usuario tipee.
  span.appendChild(document.createTextNode('\u200B'));

  const sel = window.getSelection();
  let range;
  if (existingRange && el.contains(existingRange.commonAncestorContainer)) {
    range = existingRange;
  } else {
    range = document.createRange();
    range.selectNodeContents(el);
    range.collapse(false); // al final
  }

  range.insertNode(span);

  // Mover el cursor DENTRO del span, después del ZWSP.
  const inside = document.createRange();
  inside.setStart(span.firstChild, 1);
  inside.setEnd(span.firstChild, 1);
  sel.removeAllRanges();
  sel.addRange(inside);

  marcarConCambios();
}

function formatBlock(tag) {
  if (!tag) return;
  document.getElementById('editor').focus();
  document.execCommand('formatBlock', false, tag);
  marcarConCambios();
}

// ── COLOR: texto y resaltado (Opción C: paleta + picker nativo) ────────
// Paleta homogénea: neutros + dorado de marca + cálidos + fríos.
const COLORES_PALETA = [
  '#000000','#434343','#666666','#999999','#cccccc','#ffffff',
  '#c9a84c','#c0392b','#e67e22','#f1c40f',
  '#27ae60','#2980b9','#3498db','#8e44ad'
];

// La selección se pierde cuando el foco sale del editable (al abrir el popover
// o el picker nativo). Guardamos el Range en el mousedown del botón —ANTES de
// perder foco— y lo restauramos justo antes de aplicar el color.
let colorSavedRange = null;

function guardarRangeEditor() {
  const editor = document.getElementById('editor');
  const sel = window.getSelection();
  if (sel && sel.rangeCount > 0) {
    const r = sel.getRangeAt(0);
    if (editor.contains(r.commonAncestorContainer)) {
      colorSavedRange = r.cloneRange();
      return;
    }
  }
  colorSavedRange = null;
}

function restaurarRangeEditor() {
  const editor = document.getElementById('editor');
  editor.focus();
  if (!colorSavedRange) return;
  const sel = window.getSelection();
  sel.removeAllRanges();
  sel.addRange(colorSavedRange);
}

// Aplica color de 'texto' o de 'fondo' (resaltado) a la selección guardada.
// styleWithCSS=true fuerza <span style="color:…"> en vez de <font color> —
// crítico porque HTMLPurifier conserva span[style] pero NO <font>.
function aplicarColor(tipo, color) {
  restaurarRangeEditor();
  try { document.execCommand('styleWithCSS', false, true); } catch (e) {}
  if (tipo === 'texto') {
    document.execCommand('foreColor', false, color);
  } else {
    // hiliteColor es el nombre estándar; backColor es el fallback legacy.
    if (!document.execCommand('hiliteColor', false, color)) {
      document.execCommand('backColor', false, color);
    }
  }
  if (color !== 'transparent') {
    const bar = document.getElementById('bar-color-' + tipo);
    if (bar) bar.style.background = color;
  }
  cerrarColorPops();
  actualizarContador();
  marcarConCambios();
}

function aplicarColorDesdeInput(tipo, color) {
  aplicarColor(tipo, color);
}

function abrirColorNativo(tipo) {
  cerrarColorPops();
  document.getElementById('input-color-' + tipo).click();
}

function toggleColorPop(tipo, ev) {
  ev.stopPropagation();
  const pop = document.getElementById('pop-color-' + tipo);
  const abierto = pop.style.display === 'block';
  cerrarColorPops();
  if (!abierto) pop.style.display = 'block';
}

function cerrarColorPops() {
  document.querySelectorAll('.tb-color-pop').forEach(p => p.style.display = 'none');
}

// Cerrar los popovers al hacer click fuera del grupo de color.
document.addEventListener('click', (e) => {
  if (!e.target.closest('.tb-color-wrap')) cerrarColorPops();
});

// Genera los swatches de ambas paletas. Los botones usan mousedown+preventDefault
// para no robar el foco/selección del editor antes de aplicar el color.
function generarSwatches() {
  ['texto', 'fondo'].forEach(tipo => {
    const cont = document.getElementById('swatches-' + tipo);
    if (!cont) return;
    COLORES_PALETA.forEach(c => {
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'tb-swatch';
      b.style.background = c;
      b.title = c;
      b.addEventListener('mousedown', ev => ev.preventDefault());
      b.addEventListener('click', () => aplicarColor(tipo, c));
      cont.appendChild(b);
    });
  });
}

function insertarTabla() {
  exec('insertHTML', `<table><tr><th>Columna 1</th><th>Columna 2</th><th>Columna 3</th></tr><tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr><tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr></table><p></p>`);
}

// ── TABLAS: agregar fila / columna ────────────────────────────
function celdaActual() {
  const sel = window.getSelection();
  if (!sel.rangeCount) return null;
  let node = sel.anchorNode;
  const editor = document.getElementById('editor');
  while (node && node !== editor) {
    if (node.nodeType === 1 && (node.tagName === 'TD' || node.tagName === 'TH')) return node;
    node = node.parentNode;
  }
  return null;
}

function agregarFila() {
  const celda = celdaActual();
  if (!celda) { mostrarToast('Poné el cursor dentro de una tabla.', 'error'); return; }
  const fila = celda.parentNode;
  const nuevaFila = document.createElement('tr');
  for (let i = 0; i < fila.children.length; i++) {
    const td = document.createElement('td');
    td.innerHTML = '&nbsp;';
    nuevaFila.appendChild(td);
  }
  fila.after(nuevaFila);
  actualizarContador();
  marcarConCambios();
}

function agregarColumna() {
  const celda = celdaActual();
  if (!celda) { mostrarToast('Poné el cursor dentro de una tabla.', 'error'); return; }
  const tabla = celda.closest('table');
  const idx = Array.from(celda.parentNode.children).indexOf(celda);
  tabla.querySelectorAll('tr').forEach(tr => {
    const ref = tr.children[idx];
    const esHeader = ref && ref.tagName === 'TH';
    const nueva = document.createElement(esHeader ? 'th' : 'td');
    nueva.innerHTML = esHeader ? 'Columna' : '&nbsp;';
    if (ref) ref.after(nueva); else tr.appendChild(nueva);
  });
  actualizarContador();
  marcarConCambios();
}

// ── RESALTADO DE BOTONES ACTIVOS (B/I/U y alineación) ─────────
function actualizarEstadoToolbar() {
  const editor = document.getElementById('editor');
  const sel = window.getSelection();
  if (!sel.anchorNode || !editor.contains(sel.anchorNode)) return;
  ['bold','italic','underline','justifyLeft','justifyCenter'].forEach(cmd => {
    const btn = document.querySelector(`.tb-btn[data-cmd="${cmd}"]`);
    if (!btn) return;
    let activo = false;
    try { activo = document.queryCommandState(cmd); } catch (e) {}
    btn.classList.toggle('active', activo);
  });
}

document.addEventListener('selectionchange', actualizarEstadoToolbar);

// Actualiza el select de tamaño de los mini-editores para reflejar el tamaño
// de fuente aplicado al elemento donde está el cursor. Si el cursor está en
// texto sin font-size explícito, muestra "Tamaño" (default).
document.addEventListener('selectionchange', () => {
  const sel = window.getSelection();
  if (!sel || sel.rangeCount === 0) return;
  const node = sel.anchorNode;
  if (!node) return;

  // Encontrar el editor (header o footer) que contiene el cursor.
  let ancestor = node.nodeType === 3 ? node.parentElement : node;
  const miniEditor = ancestor?.closest('.mini-editor');
  if (!miniEditor) return;

  // Encontrar el select de tamaño correspondiente a este mini-editor.
  const wrap = miniEditor.closest('.mini-editor-wrap');
  const select = wrap?.querySelector('.mini-select');
  if (!select) return;

  // Buscar en los padres hasta el editable el font-size explícito.
  let pt = '';
  let cur = ancestor;
  while (cur && cur !== miniEditor) {
    const fs = cur.style?.fontSize || '';
    if (fs.endsWith('pt')) {
      pt = fs.replace('pt', '');
      break;
    }
    cur = cur.parentElement;
  }

  // Setear el select (o volver a "Tamaño" si no hay pt explícito).
  select.value = pt || '';
});

// ── BUSCADOR EN EL DOCUMENTO (estilo Word) ─────────────────────────────
// Resalta coincidencias con la CSS Custom Highlight API: pinta a partir de
// objetos Range SIN insertar nada en el DOM, por lo que el innerHTML que se
// guarda no se ve afectado. Se limpia al cerrar la barra.
let findMatches = [];
let findIndex   = -1;
const findSupported =
  (typeof CSS !== 'undefined' && CSS.highlights && typeof Highlight !== 'undefined');

function toggleBuscador() {
  const bar = document.getElementById('find-bar');
  if (bar.style.display === 'flex') cerrarBuscador();
  else abrirBuscador();
}

function abrirBuscador() {
  const bar = document.getElementById('find-bar');
  bar.style.display = 'flex';
  const input = document.getElementById('find-input');
  // Si hay texto seleccionado en el editor, lo usamos como término inicial.
  const sel = window.getSelection();
  const selText = sel && !sel.isCollapsed ? sel.toString().trim() : '';
  if (selText && selText.length <= 60) input.value = selText;
  input.focus();
  input.select();
  if (input.value) ejecutarBusqueda();
}

function cerrarBuscador() {
  document.getElementById('find-bar').style.display = 'none';
  limpiarHighlights();
  findMatches = [];
  findIndex = -1;
  document.getElementById('editor').focus();
}

function limpiarHighlights() {
  if (!findSupported) return;
  CSS.highlights.delete('doc-find');
  CSS.highlights.delete('doc-find-active');
}

function ejecutarBusqueda() {
  const term = document.getElementById('find-input').value;
  limpiarHighlights();
  findMatches = [];
  findIndex = -1;

  const countEl = document.getElementById('find-count');
  if (!term) { countEl.textContent = '0/0'; return; }
  if (!findSupported) { countEl.textContent = 'N/D'; return; }

  const editor = document.getElementById('editor');

  // Recorremos los nodos de texto y armamos el texto completo con un mapa de
  // offsets, para poder encontrar coincidencias que crucen varios nodos
  // (p. ej. texto partido por un <span> de color).
  const walker = document.createTreeWalker(editor, NodeFilter.SHOW_TEXT, null);
  const nodes = [];
  let full = '';
  let node;
  while ((node = walker.nextNode())) {
    nodes.push({ node, start: full.length });
    full += node.nodeValue;
  }
  if (!nodes.length) { countEl.textContent = '0/0'; return; }

  const hay    = full.toLowerCase();
  const needle = term.toLowerCase();
  const L      = needle.length;

  // Mapea una posición global del texto a { node, offset }.
  const locate = (pos) => {
    for (let i = 0; i < nodes.length; i++) {
      const s = nodes[i].start;
      const e = s + nodes[i].node.nodeValue.length;
      if (pos <= e) return { node: nodes[i].node, offset: pos - s };
    }
    const last = nodes[nodes.length - 1];
    return { node: last.node, offset: last.node.nodeValue.length };
  };

  let from = 0, idx;
  while ((idx = hay.indexOf(needle, from)) !== -1) {
    const a = locate(idx);
    const b = locate(idx + L);
    try {
      const r = document.createRange();
      r.setStart(a.node, a.offset);
      r.setEnd(b.node, b.offset);
      findMatches.push(r);
    } catch (e) { /* rango inválido: se ignora */ }
    from = idx + L;
  }

  if (findMatches.length) {
    CSS.highlights.set('doc-find', new Highlight(...findMatches));
    findIndex = 0;
    activarMatch(0);
  } else {
    actualizarFindCount();
  }
}

function activarMatch(i) {
  if (!findMatches.length) return;
  findIndex = (i + findMatches.length) % findMatches.length;
  const activo = findMatches[findIndex];
  if (findSupported) {
    const hActive = new Highlight(activo);
    hActive.priority = 1; // se dibuja encima del resaltado general
    CSS.highlights.set('doc-find-active', hActive);
  }
  scrollARange(activo);
  actualizarFindCount();
}

function buscarNav(dir) {
  if (!findMatches.length) return;
  activarMatch(findIndex + dir);
}

function actualizarFindCount() {
  const el = document.getElementById('find-count');
  el.textContent = findMatches.length ? `${findIndex + 1}/${findMatches.length}` : '0/0';
}

// Centra la coincidencia activa dentro del área scrolleable del editor.
function scrollARange(range) {
  const scroll = document.querySelector('.editor-scroll');
  if (!scroll) return;
  const rr = range.getBoundingClientRect();
  if (!rr.height && !rr.width) return;
  const sr = scroll.getBoundingClientRect();
  const relTop = rr.top - sr.top + scroll.scrollTop;
  const target = relTop - (scroll.clientHeight / 2) + (rr.height / 2);
  scroll.scrollTo({ top: Math.max(0, target), behavior: 'smooth' });
}

function initBuscador() {
  const input = document.getElementById('find-input');
  input.addEventListener('input', ejecutarBusqueda);
  input.addEventListener('keydown', (e) => {
    if (e.key === 'Enter')       { e.preventDefault(); buscarNav(e.shiftKey ? -1 : 1); }
    else if (e.key === 'Escape') { e.preventDefault(); cerrarBuscador(); }
  });
  // Ctrl+F / Cmd+F abre el buscador del documento (en vez del del navegador).
  document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && (e.key === 'f' || e.key === 'F')) {
      e.preventDefault();
      abrirBuscador();
    }
  });
}

document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('editor').addEventListener('input', () => {
    actualizarContador();
    marcarConCambios();
    // Si el buscador está abierto, re-buscamos para reflejar el texto editado.
    if (document.getElementById('find-bar').style.display === 'flex') ejecutarBusqueda();
  });
  generarSwatches();
  initBuscador();
});

function actualizarContador() {
  const txt   = document.getElementById('editor').innerText || '';
  const words = txt.trim().split(/\s+/).filter(Boolean).length;
  document.getElementById('word-count').textContent  = `${words} palabras`;
  document.getElementById('st-palabras').textContent = words.toLocaleString('es-AR');
}

function marcarConCambios() {
  estado.modificado = true;
  document.getElementById('st-cambios').textContent = 'Cambios sin guardar';
  document.getElementById('st-cambios').className   = 'status-value pendiente';
}

function marcarSinCambios() {
  estado.modificado = false;
  document.getElementById('st-cambios').textContent = 'Sin cambios';
  document.getElementById('st-cambios').className   = 'status-value guardado';
}

// ── GUARDAR BORRADOR ──────────────────────────────────────────
async function guardarBorrador() {
  const html = document.getElementById('editor').innerHTML.trim();
  if (!html) return;

  const btn = document.getElementById('btn-guardar');
  btn.disabled    = true;
  btn.textContent = 'Guardando...';

  try {
    await apiFetch('POST', `/manuales/${MANUAL_ID}/borrador`, {
      contenido_html: html,
      encabezado_html: document.getElementById('editor-header').innerHTML.trim() || null,
      pie_pagina_html: document.getElementById('editor-footer').innerHTML.trim() || null,
    });
    marcarSinCambios();
    mostrarToast('Borrador guardado correctamente.', 'exito');
  } catch (e) {
    mostrarToast('Error al guardar el borrador.', 'error');
  } finally {
    btn.disabled  = false;
    btn.innerHTML = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg> Guardar borrador`;
  }
}

// ── PUBLICAR ──────────────────────────────────────────────────
// Etiqueta de version "numero.minor" (fallback si el backend no mando version_label).
function verNum(v) {
  if (!v) return '';
  return v.version_label || (v.version_number + '.' + (v.version_minor ?? 0));
}

// Paso 1 del publicado: elegir si el cambio es menor (sube el minor) o mayor
// (sube el numero). Se calcula sobre la version activa. En la primera
// publicacion no hay nada que elegir -> va directo a v1.0.
function abrirModalVersion() {
  const html = document.getElementById('editor').innerHTML.trim();
  if (!html) { mostrarToast('El contenido no puede estar vacío.', 'error'); return; }

  const activa = estado.versiones.find(v => v.es_activa == 1 && v.version_number > 0);

  // Primera publicacion real: no hay version anterior contra la cual comparar,
  // asi que en vez de preguntar mayor/menor preguntamos con que numero arranca.
  // Es el unico momento en que el usuario puede elegir el numero (ver backend:
  // ManualController::publicar, rama $ultimaVersion === 0).
  if (!activa) {
    estado.tipoCambio = 'mayor';
    abrirModalVersionInicial();
    return;
  }

  const baseNumber = activa.version_number;
  const maxMinorBase = Math.max(
    ...estado.versiones
      .filter(v => v.version_number === baseNumber)
      .map(v => v.version_minor ?? 0)
  );
  const maxNumber = Math.max(
    ...estado.versiones.filter(v => v.version_number > 0).map(v => v.version_number)
  );

  document.getElementById('ver-actual-label').textContent = 'v' + verNum(activa);
  document.getElementById('ver-opcion-menor').textContent = `v${baseNumber}.${maxMinorBase + 1}`;
  document.getElementById('ver-opcion-mayor').textContent = `v${maxNumber + 1}.0`;

  document.getElementById('modal-version').classList.add('open');
}

// ── VERSION INICIAL (solo primera publicacion) ────────────────

function abrirModalVersionInicial() {
  estado.versionInicial = null;
  document.getElementById('vi-number').value = 1;
  document.getElementById('vi-minor').value  = 0;
  document.getElementById('vi-error').style.display = 'none';
  previewVersionInicial();
  document.getElementById('modal-version-inicial').classList.add('open');
}

function cerrarModalVersionInicial() {
  document.getElementById('modal-version-inicial').classList.remove('open');
}

function previewVersionInicial() {
  const n = parseInt(document.getElementById('vi-number').value, 10);
  const m = parseInt(document.getElementById('vi-minor').value, 10);
  const nOk = Number.isInteger(n) && n >= 1   && n <= 999;
  const mOk = Number.isInteger(m) && m >= 0   && m <= 999;
  document.getElementById('vi-preview').textContent =
    (nOk && mOk) ? `v${n}.${m}` : '—';
}

function confirmarVersionInicial() {
  const errEl = document.getElementById('vi-error');
  const n = parseInt(document.getElementById('vi-number').value, 10);
  const m = parseInt(document.getElementById('vi-minor').value, 10);

  // version_number = 0 esta reservado para el borrador -> minimo 1.
  if (!Number.isInteger(n) || n < 1 || n > 999) {
    errEl.textContent = 'La versión debe ser un número entero entre 1 y 999.';
    errEl.style.display = 'block';
    return;
  }
  if (!Number.isInteger(m) || m < 0 || m > 999) {
    errEl.textContent = 'La revisión debe ser un número entero entre 0 y 999.';
    errEl.style.display = 'block';
    return;
  }

  estado.versionInicial = { number: n, minor: m };
  cerrarModalVersionInicial();
  abrirModalPublicar();   // paso 2: aviso legal (franquiciante) o confirmacion (admin)
}

function elegirTipoCambio(tipo) {
  estado.tipoCambio = tipo;
  cerrarModalVersion();
  abrirModalPublicar();   // paso 2: aviso legal (franquiciante) o confirmacion (admin)
}

function cerrarModalVersion() {
  document.getElementById('modal-version').classList.remove('open');
}

function abrirModalPublicar() {
  const esFranquiciante = rolUsuario === 'franquiciante';

  document.getElementById('pub-body-admin').style.display         = esFranquiciante ? 'none' : 'block';
  document.getElementById('pub-body-franquiciante').style.display = esFranquiciante ? 'block' : 'none';
  document.getElementById('pub-modal-titulo').textContent = esFranquiciante
    ? 'Publicar — Confirmación requerida'
    : 'Publicar nueva versión';

  const btn = document.getElementById('btn-confirmar-publicar');
  if (esFranquiciante) {
    document.getElementById('pub-confirmo').checked = false;
    btn.disabled = true;
    btn.style.opacity = '.4';
    btn.style.cursor  = 'not-allowed';
  } else {
    btn.disabled = false;
    btn.style.opacity = '';
    btn.style.cursor  = '';
  }

  // Resetear el textarea de la nota de publicación
  const notaInput = document.getElementById('pub-nota');
  if (notaInput) notaInput.value = '';

  document.getElementById('modal-publicar').classList.add('open');
}

function toggleBotonPublicar() {
  const btn = document.getElementById('btn-confirmar-publicar');
  const ok  = document.getElementById('pub-confirmo').checked;
  btn.disabled      = !ok;
  btn.style.opacity = ok ? '' : '.4';
  btn.style.cursor  = ok ? '' : 'not-allowed';
}

function cerrarModal() {
  document.getElementById('modal-publicar').classList.remove('open');
}

async function publicar() {
  const html = document.getElementById('editor').innerHTML.trim();
  if (!html) { mostrarToast('El contenido no puede estar vacío.', 'error'); return; }

  const btn = document.getElementById('btn-confirmar-publicar');
  btn.disabled    = true;
  btn.textContent = 'Publicando...';

  try {
    const notaInput = document.getElementById('pub-nota');
    const nota = notaInput ? notaInput.value.trim() : '';

    const body = {
      contenido_html:  html,
      encabezado_html: document.getElementById('editor-header').innerHTML.trim() || null,
      pie_pagina_html: document.getElementById('editor-footer').innerHTML.trim() || null,
      tipo_cambio:     estado.tipoCambio || 'mayor',
    };
    if (nota) body.nota_publicacion = nota;

    // Solo va en la primera publicacion. El backend lo lee UNICAMENTE en la rama
    // "no hay versiones publicadas"; si el manual ya tiene versiones, lo ignora.
    if (estado.versionInicial) {
      body.version_inicial_number = estado.versionInicial.number;
      body.version_inicial_minor  = estado.versionInicial.minor;
    }

    const res = await apiFetch('POST', `/manuales/${MANUAL_ID}/publicar`, body);

    cerrarModal();
    estado.versionInicial = null;   // ya se uso: la proxima publicacion autoincrementa
    mostrarToast('¡Manual publicado! Los franquiciados fueron notificados.', 'exito');
    marcarSinCambios();

    // Actualizar estado en panel
    document.getElementById('st-estado').textContent = 'publicado';
    document.getElementById('st-estado').className   = 'status-value guardado';

    // Recargar versiones
    const todasVersiones = await apiFetch('GET', `/manuales/${MANUAL_ID}/versiones`);
    estado.versiones = todasVersiones;
    renderVersiones(todasVersiones.filter(v => v.version_number > 0));
    // Marcar la nueva versión activa como cargada
    const nuevaActiva = todasVersiones.find(v => v.es_activa == 1);
    if (nuevaActiva) {
        document.getElementById('st-version').textContent = `v${verNum(nuevaActiva)}`;
        setTimeout(() => {
            document.querySelectorAll('.version-item').forEach(el => el.classList.remove('cargada'));
            const itemEl = document.querySelector(`.version-item[data-version="v${verNum(nuevaActiva)}"]`);
            if (itemEl) itemEl.classList.add('cargada');
        }, 100);
    }

  } catch (e) {
    mostrarToast(e.data?.message || 'Error al publicar.', 'error');
  } finally {
    btn.disabled  = false;
    btn.innerHTML = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2L11 13"/><path d="M22 2L15 22 11 13 2 9l20-7z"/></svg> Confirmar publicación`;
  }
}

// ── HELPERS ───────────────────────────────────────────────────
function formatFecha(str) {
  if (!str) return '—';
  return new Date(str).toLocaleDateString('es-AR', { day:'2-digit', month:'2-digit', year:'numeric' });
}

// ══════════════════════════════════════════════════════════════
// v2.3 — VISIBILIDAD POR CATEGORÍAS (botón "Visible para" + modal)
// ══════════════════════════════════════════════════════════════

async function cargarCategoriasYAsignaciones() {
  if (!estado.manual) return;
  const empresaId = estado.manual.empresa_id;
  if (!empresaId) {
    // Sin empresa, no hay catálogo de categorías
    document.getElementById('visible-resumen').textContent = 'Sin empresa asignada';
    return;
  }

  try {
    // Catálogo de cats activas de la empresa
    const url = (rolUsuario === 'super_admin')
      ? `/categorias?empresa_id=${empresaId}&activa=1`
      : `/categorias?activa=1`;
    const cats = await apiFetch('GET', url);
    categoriasEmpresa = (cats || []).filter(c => c.is_active);

    // Asignaciones actuales del manual (categorias)
    const asig = await apiFetch('GET', `/manuales/${MANUAL_ID}/categorias`);
    idsAsignadasManual = new Set((asig || []).map(a => a.category_id));

    // Socios comerciales de la empresa (con sus categorias) para las sublistas.
    const usersUrl = (rolUsuario === 'super_admin') ? `/usuarios?empresa_id=${empresaId}` : `/usuarios`;
    const users = await apiFetch('GET', usersUrl);
    usuariosEmpresa = users || [];

    // Asignaciones individuales actuales del manual.
    const asigU = await apiFetch('GET', `/manuales/${MANUAL_ID}/usuarios`);
    usuariosSelManual = new Set((asigU || []).map(a => a.user_id));

    // Las categorias ya asignadas van "completas" (dinamicas).
    catsCompletas = new Set(idsAsignadasManual);

    actualizarResumenVisible();
  } catch (e) {
    console.error('Error al cargar visibilidad:', e);
    document.getElementById('visible-resumen').textContent = 'Error al cargar';
  }
}

function actualizarResumenVisible() {
  const resumenEl = document.getElementById('visible-resumen');
  const total     = categoriasEmpresa.length;
  const asignadas = idsAsignadasManual.size;

  if (total === 0) {
    resumenEl.textContent = 'Sin categorías en la empresa';
    resumenEl.style.color = 'var(--gris3)';
  } else if (asignadas === 0) {
    resumenEl.textContent = 'Nadie todavía';
    resumenEl.style.color = 'var(--gris3)';
  } else if (asignadas === total) {
    resumenEl.textContent = 'Toda la empresa';
    resumenEl.style.color = 'var(--dorado)';
  } else {
    resumenEl.textContent = `${asignadas} de ${total} categoría${asignadas === 1 ? '' : 's'}`;
    resumenEl.style.color = 'var(--blanco)';
  }
}

function abrirModalVisible() {
  // Reset error
  document.getElementById('modal-visible-error').style.display = 'none';
  renderListaModalVisible();
  document.getElementById('modal-visible').classList.add('open');
}

function cerrarModalVisible() {
  document.getElementById('modal-visible').classList.remove('open');
}

function renderListaModalVisible() {
  const cont = document.getElementById('modal-visible-lista');

  if (!categoriasEmpresa.length) {
    cont.innerHTML = `<div style="font-size:13px;color:var(--gris4);font-family:'Roboto',sans-serif;padding:12px;text-align:center;background:var(--negro);border-radius:8px">
      No hay categorías activas en esta empresa.<br>
      <a href="${BASE_PHP}/categorias.php" style="color:var(--dorado);text-decoration:underline">Crear una categoría primero</a>.
    </div>`;
    document.getElementById('btn-guardar-visible').disabled = true;
    return;
  }

  document.getElementById('btn-guardar-visible').disabled = false;

  // ¿Todas las cats están asignadas? → marcamos "toda la empresa"
  const todasMarcadas = categoriasEmpresa.every(c => idsAsignadasManual.has(c.id));

  cont.innerHTML = `
    <label style="display:flex;align-items:flex-start;gap:8px;padding:10px 12px;cursor:pointer;border-radius:6px;background:rgba(212,165,46,.08);border:1px solid rgba(212,165,46,.3);margin-bottom:10px">
      <input type="checkbox" id="modal-toda-empresa" ${todasMarcadas ? 'checked' : ''} style="margin:0;margin-top:2px;cursor:pointer;accent-color:var(--dorado)" onchange="onToggleTodaLaEmpresaEditor()">
      <div style="flex:1">
        <div style="font-size:13px;color:var(--dorado);font-weight:600">Toda la empresa</div>
        <div style="font-size:11px;color:var(--gris4);margin-top:2px;font-family:'Roboto',sans-serif">El manual será visible para todas las categorías activas.</div>
      </div>
    </label>

    <div style="display:flex;align-items:center;gap:10px;margin:8px 0">
      <div style="flex:1;height:1px;background:var(--gris2)"></div>
      <span style="font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:var(--gris4);font-family:'Archivo',sans-serif">O elegí categorías específicas</span>
      <div style="flex:1;height:1px;background:var(--gris2)"></div>
    </div>

    <div id="modal-cats-especificas" style="max-height:280px;overflow-y:auto">
      ${categoriasEmpresa.map(c => `
        <div class="cat-item-wrap" data-cat-wrap="${c.id}">
          <div class="cat-item-editor" style="display:flex;align-items:center;gap:8px;padding:8px 10px;border-radius:5px;${todasMarcadas ? 'opacity:.45;pointer-events:none' : ''}">
            <input type="checkbox" data-cat-id="${c.id}" class="cat-especifica-editor" ${todasMarcadas ? 'disabled' : ''} style="margin:0;cursor:pointer;accent-color:var(--dorado);flex-shrink:0" onchange="onToggleCategoriaManualEditor(${c.id})">
            <div style="flex:1;min-width:0;cursor:pointer" onclick="toggleCheckCatEditor(${c.id})">
              <div style="font-size:13px;color:var(--blanco);font-weight:500">${escNota(c.name)} <span style="color:var(--gris4);font-weight:400;font-size:11px">(${usuariosDeCategoria(c.id).length})</span></div>
              ${c.description ? `<div style="font-size:11px;color:var(--gris4);margin-top:2px;font-family:'Roboto',sans-serif;line-height:1.4">${escNota(c.description)}</div>` : ''}
            </div>
            <button type="button" class="cat-chevron" data-chevron="${c.id}" onclick="onToggleSublistaEditor(${c.id})" style="display:none;background:none;border:none;color:var(--gris4);cursor:pointer;font-size:12px;padding:2px 6px">▾</button>
          </div>
          <div class="cat-sublista" data-sublista="${c.id}" style="display:none;margin:2px 0 8px 26px;padding:8px 10px;background:rgba(255,255,255,.02);border:1px solid var(--gris2);border-radius:6px">
            ${sublistaUsuariosHTMLEditor(c.id)}
          </div>
        </div>
      `).join('')}
    </div>
  `;
  precargarSeleccionesEditor();
}

// Usuarios (socios comerciales) que tienen una categoria dada.
function usuariosDeCategoria(catId) {
  return usuariosEmpresa.filter(u => (u.categorias || []).some(c => c.id === catId));
}

// HTML de la sublista de usuarios de una categoria.
function sublistaUsuariosHTMLEditor(catId) {
  const us = usuariosDeCategoria(catId);
  if (!us.length) {
    return `<div style="font-size:11px;color:var(--gris4);font-family:'Roboto',sans-serif">Sin usuarios en esta categoría.</div>`;
  }
  const todos = `
    <label style="display:flex;align-items:center;gap:8px;padding:4px 2px;cursor:pointer;border-bottom:1px solid var(--gris2);margin-bottom:4px">
      <input type="checkbox" data-todos="${catId}" style="margin:0;cursor:pointer;accent-color:var(--dorado)" onchange="onToggleSeleccionarTodosEditor(${catId})">
      <span style="font-size:12px;color:var(--dorado);font-weight:600">Seleccionar todos</span>
    </label>`;
  const items = us.map(u => `
    <label style="display:flex;align-items:center;gap:8px;padding:3px 2px;cursor:pointer">
      <input type="checkbox" data-user-cat="${catId}" data-user-id="${u.id}" style="margin:0;cursor:pointer;accent-color:var(--dorado)" onchange="onToggleUsuarioEditor(${u.id})">
      <span style="font-size:12px;color:var(--blanco)">${escNota((u.nombre || '') + ' ' + (u.apellido || ''))}</span>
    </label>`).join('');
  return todos + items;
}

// Aplica el estado ya cargado (catsCompletas / usuariosSelManual) al DOM del modal.
function precargarSeleccionesEditor() {
  categoriasEmpresa.forEach(c => {
    const catId    = c.id;
    const completa = catsCompletas.has(catId);
    const tieneInd = usuariosDeCategoria(catId).some(u => usuariosSelManual.has(u.id));
    const cb  = document.querySelector(`.cat-especifica-editor[data-cat-id="${catId}"]`);
    const sub = document.querySelector(`.cat-sublista[data-sublista="${catId}"]`);
    const chv = document.querySelector(`[data-chevron="${catId}"]`);
    const tod = sub ? sub.querySelector(`[data-todos="${catId}"]`) : null;
    if (completa || tieneInd) {
      if (cb && !cb.disabled) cb.checked = true;
      if (sub) sub.style.display = 'block';
      if (chv) { chv.style.display = ''; chv.textContent = '▾'; }
      if (tod) tod.checked = completa;
    }
  });
  sincronizarChecksUsuariosEditor();
}

function toggleCheckCatEditor(catId) {
  const cb = document.querySelector(`.cat-especifica-editor[data-cat-id="${catId}"]`);
  if (!cb || cb.disabled) return;
  cb.checked = !cb.checked;
  onToggleCategoriaManualEditor(catId);
}

// Marcar/desmarcar categoria: despliega sublista y activa "seleccionar todos".
function onToggleCategoriaManualEditor(catId) {
  const cb  = document.querySelector(`.cat-especifica-editor[data-cat-id="${catId}"]`);
  const sub = document.querySelector(`.cat-sublista[data-sublista="${catId}"]`);
  const chv = document.querySelector(`[data-chevron="${catId}"]`);
  const tod = sub ? sub.querySelector(`[data-todos="${catId}"]`) : null;
  if (cb.checked) {
    sub.style.display = 'block'; chv.style.display = ''; chv.textContent = '▾';
    if (tod) tod.checked = true;
    catsCompletas.add(catId);
  } else {
    sub.style.display = 'none'; chv.style.display = 'none';
    if (tod) tod.checked = false;
    catsCompletas.delete(catId);
    usuariosDeCategoria(catId).forEach(u => usuariosSelManual.delete(u.id));
  }
  actualizarSublistaEditor(catId);
  sincronizarChecksUsuariosEditor();
}

function onToggleSublistaEditor(catId) {
  const sub = document.querySelector(`.cat-sublista[data-sublista="${catId}"]`);
  const chv = document.querySelector(`[data-chevron="${catId}"]`);
  const oculto = sub.style.display === 'none';
  sub.style.display = oculto ? 'block' : 'none';
  chv.textContent = oculto ? '▾' : '▸';
}

// "Seleccionar todos" = la categoria va completa (dinamica). Opcion A.
function onToggleSeleccionarTodosEditor(catId) {
  const on = document.querySelector(`[data-todos="${catId}"]`).checked;
  if (on) {
    catsCompletas.add(catId);
    usuariosDeCategoria(catId).forEach(u => usuariosSelManual.delete(u.id));
  } else {
    catsCompletas.delete(catId);
  }
  actualizarSublistaEditor(catId);
  sincronizarChecksUsuariosEditor();
}

// Usuario individual: marca/desmarca globalmente (afecta todas sus categorias).
function onToggleUsuarioEditor(userId) {
  const cb = document.querySelector(`[data-user-id="${userId}"]`);
  if (cb.checked) usuariosSelManual.add(userId);
  else usuariosSelManual.delete(userId);
  sincronizarChecksUsuariosEditor();
}

// Checks de usuario de UNA categoria: si esta completa, todos marcados+deshabilitados.
function actualizarSublistaEditor(catId) {
  const completa = catsCompletas.has(catId);
  document.querySelectorAll(`[data-user-cat="${catId}"]`).forEach(cb => {
    const uid = parseInt(cb.dataset.userId, 10);
    if (completa) { cb.checked = true; cb.disabled = true; }
    else { cb.disabled = false; cb.checked = usuariosSelManual.has(uid); }
  });
}

// Refleja el set individual en TODOS los checks (Pepe en 2 cats) + "seleccionar todos".
function sincronizarChecksUsuariosEditor() {
  document.querySelectorAll('#modal-cats-especificas [data-user-id]').forEach(cb => {
    const catId = parseInt(cb.dataset.userCat, 10);
    if (catsCompletas.has(catId)) { cb.checked = true; cb.disabled = true; return; }
    cb.disabled = false;
    cb.checked = usuariosSelManual.has(parseInt(cb.dataset.userId, 10));
  });
  document.querySelectorAll('#modal-cats-especificas [data-todos]').forEach(cbT => {
    cbT.checked = catsCompletas.has(parseInt(cbT.dataset.todos, 10));
  });
}

function onToggleTodaLaEmpresaEditor() {
  const checked = document.getElementById('modal-toda-empresa').checked;
  if (checked) { catsCompletas.clear(); usuariosSelManual.clear(); }
  document.querySelectorAll('.cat-especifica-editor').forEach(cb => {
    if (checked) {
      cb.checked = false;
      const cid = parseInt(cb.dataset.catId, 10);
      catsCompletas.delete(cid);
      const sub = document.querySelector(`.cat-sublista[data-sublista="${cid}"]`);
      const chv = document.querySelector(`[data-chevron="${cid}"]`);
      if (sub) sub.style.display = 'none';
      if (chv) chv.style.display = 'none';
    }
    cb.disabled = checked;
  });
  document.querySelectorAll('#modal-cats-especificas .cat-item-editor').forEach(el => {
    el.style.opacity      = checked ? '.45' : '1';
    el.style.pointerEvents = checked ? 'none' : 'auto';
  });
}

function leerCategoriasModalEditor() {
  const todaLaEmpresa = document.getElementById('modal-toda-empresa')?.checked;
  if (todaLaEmpresa) {
    return categoriasEmpresa.map(c => c.id);
  }
  return [...catsCompletas];
}

// user_ids individuales, excluyendo los cubiertos por una categoria completa.
function leerUsuariosModalEditor() {
  if (document.getElementById('modal-toda-empresa')?.checked) return [];
  const cubiertos = new Set();
  catsCompletas.forEach(catId => usuariosDeCategoria(catId).forEach(u => cubiertos.add(u.id)));
  return [...usuariosSelManual].filter(uid => !cubiertos.has(uid));
}

async function guardarVisible() {
  if (!estado.manual) return;
  const btn   = document.getElementById('btn-guardar-visible');
  const errEl = document.getElementById('modal-visible-error');
  errEl.style.display = 'none';

  const cats      = leerCategoriasModalEditor();
  const empresaId = estado.manual.empresa_id;

  const labelOriginal = btn.innerHTML;
  btn.disabled = true;
  btn.textContent = 'Guardando...';

  try {
    await apiFetch('PUT', `/manuales/${MANUAL_ID}/categorias`, {
      category_ids: cats,
      empresa_id:   empresaId,
    });

    // Sincronizar asignaciones individuales (se manda siempre para reflejar bajas).
    await apiFetch('PUT', `/manuales/${MANUAL_ID}/usuarios`, {
      user_ids:   leerUsuariosModalEditor(),
      empresa_id: empresaId,
    });

    // Actualizar state local y resumen
    idsAsignadasManual = new Set(cats);
    actualizarResumenVisible();

    mostrarToast('Visibilidad actualizada.', 'exito');
    cerrarModalVisible();
  } catch (e) {
    console.error('Falló guardar visibilidad:', e);
    const msg = e?.data?.error || e?.data?.message || 'Error desconocido.';
    errEl.textContent = msg;
    errEl.style.display = 'block';
  } finally {
    btn.disabled = false;
    btn.innerHTML = labelOriginal;
  }
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
  if (e.key === 'Escape') cerrarModal();
});

window.addEventListener('beforeunload', e => {
  if (estado.modificado) { e.preventDefault(); e.returnValue = ''; }
});

// ── NOTAS / SUGERENCIAS ───────────────────────────────────────
async function cargarNotas() {
  const el = document.getElementById('notas-list');
  // La caja para escribir solo la ve el franquiciante; el super_admin solo marca estado.
  if (rolUsuario === 'franquiciante') {
    document.getElementById('nota-form').style.display = 'block';
  }
  try {
    const notas = await apiFetch('GET', `/manuales/${MANUAL_ID}/notas`);
    renderNotas(notas);
  } catch (e) {
    el.innerHTML = `<div style="font-size:12px;color:var(--error);font-family:'Roboto',sans-serif">Error al cargar las notas.</div>`;
  }
}

function escNota(str) {
  return String(str ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
}

function nombreAutorNota(nota) {
  // v2.3: nombre/apellido viven en users (toplevel), no en relaciones por rol.
  const a = nota.autor || {};
  const nombre = [a.nombre, a.apellido].filter(Boolean).join(' ').trim();
  if (nombre) return nombre;
  return a.email || 'Usuario';
}

function renderNotas(notas) {
  const el = document.getElementById('notas-list');
  if (!notas.length) {
    el.innerHTML = `<div style="font-size:12px;color:var(--gris3);font-family:'Roboto',sans-serif">Todav&iacute;a no hay notas para este manual.</div>`;
    return;
  }

  el.innerHTML = notas.map(n => {
    const ver = n.version?.version_number ? `<span class="nota-ver">v${verNum(n.version)}</span>` : '';

    // v2.3: release notes (mensajes del publicador). Sin estado ni acciones, son inmutables.
    if (n.tipo === 'release') {
      return `
        <div class="nota-item nota-item-release">
          <div class="nota-item-header">
            <span class="nota-autor">${escNota(nombreAutorNota(n))}</span>
            <span class="nota-fecha">${formatFecha(n.created_at)}</span>
          </div>
          <div class="nota-contenido">${escNota(n.contenido || '')}</div>
          <div class="nota-meta">
            <span class="nota-estado pendiente" style="background:rgba(201,168,76,.15);color:#8A6D1B">mensaje del publicador</span>
            ${ver}
          </div>
        </div>`;
    }

    // v2.3: feedback. Botones de estado para super_admin y franquiciante, salvo notas propias.
    const estado     = n.estado || 'pendiente';
    const esPropia   = miUserId && Number(n.user_id) === Number(miUserId);
    const puedeMarcar = !esPropia && (rolUsuario === 'super_admin' || rolUsuario === 'franquiciante');

    const acciones = puedeMarcar ? `
      <div class="nota-acciones">
        <button class="nota-btn ${estado === 'leida' ? 'activo' : ''}"    onclick="marcarEstadoNota(${n.id}, 'leida')">Le&iacute;da</button>
        <button class="nota-btn ${estado === 'resuelta' ? 'activo' : ''}" onclick="marcarEstadoNota(${n.id}, 'resuelta')">Resuelta</button>
      </div>` : '';

    return `
      <div class="nota-item">
        <div class="nota-item-header">
          <span class="nota-autor">${escNota(nombreAutorNota(n))}</span>
          <span class="nota-fecha">${formatFecha(n.created_at)}</span>
        </div>
        <div class="nota-contenido">${escNota(n.contenido || '')}</div>
        <div class="nota-meta">
          <span class="nota-estado ${estado}">${estado}</span>
          ${ver}
        </div>
        ${acciones}
      </div>`;
  }).join('');
}

// Franquiciante: agrega una nota (siempre se asocia a la versión activa en el backend)
async function agregarNota() {
  const ta  = document.getElementById('nota-texto');
  const txt = ta.value.trim();
  if (!txt) { mostrarToast('Escribí algo antes de enviar.', 'error'); return; }

  const btn = document.getElementById('btn-nota-enviar');
  btn.disabled = true; btn.textContent = 'Enviando...';
  try {
    await apiFetch('POST', `/manuales/${MANUAL_ID}/notas`, { contenido: txt });
    ta.value = '';
    mostrarToast('Nota enviada.', 'exito');
    await cargarNotas();
  } catch (e) {
    mostrarToast(e.data?.message || 'Error al enviar la nota.', 'error');
  } finally {
    btn.disabled  = false;
    btn.innerHTML = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2L11 13"/><path d="M22 2L15 22 11 13 2 9l20-7z"/></svg> Enviar nota`;
  }
}

// Super_admin: marca el estado de una nota
async function marcarEstadoNota(id, estado) {
  try {
    await apiFetch('PUT', `/notas/${id}/estado`, { estado });
    mostrarToast(`Nota marcada como ${estado}.`, 'exito');
    await cargarNotas();
  } catch (e) {
    mostrarToast(e.data?.message || 'Error al actualizar la nota.', 'error');
  }
}

// ── MODAL EDITAR DATOS DEL MANUAL ─────────────────────────────
function abrirModalEditarDatos() {
  if (!estado.manual) return;

  document.getElementById('edit-titulo').value    = estado.manual.titulo    || '';
  document.getElementById('edit-categoria').value = estado.manual.categoria || '';
  document.getElementById('edit-error').textContent   = '';
  document.getElementById('edit-error').style.display = 'none';

  document.getElementById('modal-editar-datos').classList.add('open');
}

function cerrarModalEditar() {
  document.getElementById('modal-editar-datos').classList.remove('open');
}

async function guardarDatosManual() {
  const btn    = document.getElementById('btn-guardar-datos');
  const errBox = document.getElementById('edit-error');
  errBox.style.display = 'none';

  const titulo    = document.getElementById('edit-titulo').value.trim();
  const categoria = document.getElementById('edit-categoria').value.trim();

  if (!titulo) {
    errBox.textContent = 'El título es obligatorio.';
    errBox.style.display = 'block';
    return;
  }

  btn.disabled = true; btn.textContent = 'Guardando...';
  try {
    const updated = await apiFetch('PUT', `/manuales/${MANUAL_ID}`, { titulo, categoria });
    estado.manual = { ...estado.manual, ...updated };
    document.title = `Editor — ${updated.titulo}`;
    document.getElementById('panel-titulo').textContent    = updated.titulo;
    document.getElementById('panel-categoria').textContent = updated.categoria || 'Sin categoría';
    mostrarToast('Datos del manual actualizados.', 'exito');
    cerrarModalEditar();
  } catch (e) {
    errBox.textContent = e.data?.message || 'Error al guardar los cambios.';
    errBox.style.display = 'block';
  } finally {
    btn.disabled = false; btn.textContent = 'Guardar cambios';
  }
}


document.addEventListener('DOMContentLoaded', () => init());
</script>

<!-- Toolbar contextual: aparece al clickear una imagen en cualquier editable.
     Permite cambiar el tamaño (25/50/75/100%) o eliminar la imagen. -->
<div id="img-toolbar" class="img-toolbar" style="display:none">
  <button type="button" onclick="setearImagenAncho(25)" title="25% del ancho">25%</button>
  <button type="button" onclick="setearImagenAncho(50)" title="50% del ancho">50%</button>
  <button type="button" onclick="setearImagenAncho(75)" title="75% del ancho">75%</button>
  <button type="button" onclick="setearImagenAncho(100)" title="100% del ancho">100%</button>
  <span class="img-toolbar-sep"></span>
  <button type="button" onclick="eliminarImagenSeleccionada()" title="Eliminar imagen" class="img-toolbar-danger">
    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-2 14a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L5 6"/></svg>
  </button>
</div>

<?php include 'layout/footer.php'; ?>