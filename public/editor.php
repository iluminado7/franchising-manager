<?php
require_once __DIR__ . '/layout/config.php';
require_once __DIR__ . '/layout/auth.php';
verificarSesion(); // super_admin y franquiciante
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

        <button class="tb-btn" onclick="exec('undo')" title="Deshacer">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7v6h6"/><path d="M21 17a9 9 0 0 0-9-9 9 9 0 0 0-6 2.3L3 13"/></svg>
        </button>
        <button class="tb-btn" onclick="exec('redo')" title="Rehacer">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 7v6h-6"/><path d="M3 17a9 9 0 0 1 9-9 9 9 0 0 1 6 2.3L21 13"/></svg>
        </button>

        <div style="flex:1"></div>
        <span id="word-count" style="font-size:11px;color:var(--gris3);font-family:'Roboto',sans-serif">0 palabras</span>
      </div>

      <!-- Área de escritura -->
      <div class="editor-scroll">
        <div class="editor-page">
          <div class="editor-content" id="editor" contenteditable="true" spellcheck="false"></div>
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
          <button class="btn btn-success" onclick="abrirModalPublicar()" id="btn-publicar" style="width:100%;justify-content:center" disabled>
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
}

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
  font-size: 15px;
  line-height: 1.8;
  color: var(--blanco);
  min-height: 780px;
}

.editor-content:empty::before {
  content: 'Comenzá a escribir o importá un archivo .docx desde el panel derecho...';
  color: var(--gris3);
  font-style: italic;
  pointer-events: none;
}

.editor-content h1 { font-family: 'Roboto', sans-serif; font-size: 24px; font-weight: 700; color: var(--blanco); margin: 0 0 16px; border-bottom: 1px solid var(--gris2); padding-bottom: 10px; }
.editor-content h2 { font-family: 'Roboto', sans-serif; font-size: 18px; font-weight: 600; color: var(--blanco); margin: 24px 0 10px; }
.editor-content h3 { font-family: 'Roboto', sans-serif; font-size: 15px; font-weight: 600; color: var(--gris5); margin: 16px 0 8px; }
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
  font-size: 10px; font-weight: 600;
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
.version-tag   { display: inline-block; font-size: 10px; font-weight: 500; padding: 2px 7px; border-radius: 20px; background: rgba(201,168,76,.15); color: var(--dorado); margin-top: 4px; }

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
.modal-header h3 { font-size: 15px; font-weight: 600; color: var(--blanco); }

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
.nota-ver { font-size: 10px; color: var(--gris4); font-family: 'Roboto', sans-serif; }
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
};

// v2.3 — categorías activas de la empresa del manual + ids actualmente asignados
let categoriasEmpresa     = []; // [{ id, name, description, is_active, empresa_id }]
let idsAsignadasManual    = new Set(); // ids de cats asignadas al manual (estado en DB)

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

  document.getElementById('st-version').textContent =
    `v${version.version_number}`;

  actualizarContador();
  habilitarEditor();
  marcarSinCambios();
  document.querySelectorAll('.version-item').forEach(el => el.classList.remove('cargada'));
  const id = version.version_number === 0 ? 'borrador' : `v${version.version_number}`;
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
    .sort((a, b) => b.version_number - a.version_number)
    .map(v => `
      <div class="version-item ${v.es_activa ? 'activa' : ''}" data-version="v${v.version_number}" onclick='cargarVersionDesdeJSON(${JSON.stringify(v)})'>
        <div class="version-item-header">
          <span class="version-num">v${v.version_number}</span>
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

  try {
    const ab = await file.arrayBuffer();
    const result = await mammoth.convertToHtml({ arrayBuffer: ab }, {
      styleMap: [
        "p[style-name='Heading 1'] => h1:fresh",
        "p[style-name='Heading 2'] => h2:fresh",
        "p[style-name='Heading 3'] => h3:fresh",
        "p[style-name='Título 1'] => h1:fresh",
        "p[style-name='Título 2'] => h2:fresh",
        "p[style-name='Título 3'] => h3:fresh",
        "p[style-name='Title']    => h1:fresh",
      ]
    });

    document.getElementById('editor').innerHTML = result.value;
    actualizarContador();
    marcarConCambios();

    const tmp   = document.createElement('div');
    tmp.innerHTML = result.value;
    const words = (tmp.innerText || '').trim().split(/\s+/).filter(Boolean).length;

    resultEl.textContent   = `✓ ${file.name} — ${words.toLocaleString('es-AR')} palabras importadas`;
    resultEl.style.display = 'block';

    if (result.messages.length) {
      warningsEl.innerHTML = `⚠ ${result.messages.length} advertencia(s): ` +
        result.messages.slice(0,3).map(m => m.message).join(' · ');
      warningsEl.style.display = 'block';
    }

    mostrarToast('Documento importado correctamente.', 'exito');

  } catch (err) {
    mostrarToast('Error al convertir el archivo.', 'error');
  }
}

// ── EDITOR ────────────────────────────────────────────────────
function exec(cmd, val = null) {
  document.getElementById('editor').focus();
  document.execCommand(cmd, false, val);
  actualizarContador();
  marcarConCambios();
  actualizarEstadoToolbar();
}

function formatBlock(tag) {
  if (!tag) return;
  document.getElementById('editor').focus();
  document.execCommand('formatBlock', false, tag);
  marcarConCambios();
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

document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('editor').addEventListener('input', () => {
    actualizarContador();
    marcarConCambios();
  });
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

    const body = { contenido_html: html };
    if (nota) body.nota_publicacion = nota;

    const res = await apiFetch('POST', `/manuales/${MANUAL_ID}/publicar`, body);

    cerrarModal();
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
        document.getElementById('st-version').textContent = `v${nuevaActiva.version_number}`;
        setTimeout(() => {
            document.querySelectorAll('.version-item').forEach(el => el.classList.remove('cargada'));
            const itemEl = document.querySelector(`.version-item[data-version="v${nuevaActiva.version_number}"]`);
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

    // Asignaciones actuales del manual
    const asig = await apiFetch('GET', `/manuales/${MANUAL_ID}/categorias`);
    idsAsignadasManual = new Set((asig || []).map(a => a.category_id));

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
        <label class="cat-item-editor" style="display:flex;align-items:flex-start;gap:8px;padding:8px 10px;cursor:pointer;border-radius:5px;transition:background .12s;${todasMarcadas ? 'opacity:.45;pointer-events:none' : ''}" onmouseover="this.style.background='var(--gris2)'" onmouseout="this.style.background='transparent'">
          <input type="checkbox" data-cat-id="${c.id}" class="cat-especifica-editor" ${idsAsignadasManual.has(c.id) ? 'checked' : ''} ${todasMarcadas ? 'disabled' : ''} style="margin:0;margin-top:2px;cursor:pointer;accent-color:var(--dorado);flex-shrink:0">
          <div style="flex:1;min-width:0">
            <div style="font-size:13px;color:var(--blanco);font-weight:500">${escNota(c.name)}</div>
            ${c.description ? `<div style="font-size:11px;color:var(--gris4);margin-top:2px;font-family:'Roboto',sans-serif;line-height:1.4">${escNota(c.description)}</div>` : ''}
          </div>
        </label>
      `).join('')}
    </div>
  `;
}

function onToggleTodaLaEmpresaEditor() {
  const checked = document.getElementById('modal-toda-empresa').checked;
  document.querySelectorAll('.cat-especifica-editor').forEach(cb => {
    if (checked) cb.checked = false;
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
  const checks = document.querySelectorAll('.cat-especifica-editor:checked');
  return Array.from(checks).map(cb => parseInt(cb.dataset.catId, 10));
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
    const ver = n.version?.version_number ? `<span class="nota-ver">v${n.version.version_number}</span>` : '';

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

<?php include 'layout/footer.php'; ?>