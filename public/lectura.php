<?php
require_once __DIR__ . '/layout/config.php';
require_once __DIR__ . '/layout/auth.php';
verificarSesion();
$titulo        = 'Lectura de Manual';
$pagina_actual = '';
$modo_editor   = true;
include 'layout/head.php';
?>

<style>
body {
  background: #F2F0EB !important;
  --negro:  #F2F0EB;
  --blanco: #1A1A1A;
  --gris1:  #FFFFFF;
  --gris2:  #E0DDD6;
  --gris3:  #AAAAAA;
  --gris4:  #666666;
  --gris5:  #333333;
}
.topbar { background: #FFFFFF !important; border-bottom: 1px solid #E0DDD6 !important; }
.topbar-brand     { color: #1A1A1A !important; }
.topbar-brand-dot { background: var(--dorado) !important; }
.btn-logout       { color: #666; border-color: #ddd; }
.btn-logout:hover { color: #1A1A1A; border-color: #aaa; }
.notif-btn        { color: #888; }
.notif-btn:hover  { color: #1A1A1A; background: #f0ede8; }

.lectura-layout {
  min-height: calc(100vh - 56px);
  padding: 40px 24px 80px;
  display: flex; flex-direction: column; align-items: center;
}

.doc-topbar {
  width: 100%; max-width: 800px;
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: 20px; flex-wrap: wrap; gap: 10px;

  /* Sticky: en un manual largo, volver al listado (o abrir el buscador) no
     debe obligar a scrollear hasta el tope del documento.
     top: 56px = alto de .app-topbar, que es lo que .lectura-layout descuenta
     en su min-height. Si .app-topbar no fuera sticky/fixed, poner top: 0. */
  position: sticky;
  top: 56px;
  z-index: 100;

  /* Fondo opaco + separador: el contenido del manual pasa por debajo. */
  background: #F5F3EE;
  border-bottom: 1px solid #E0DDD6;
  padding: 12px 0;
  margin-top: -12px;   /* compensa el padding para no correr el layout */
}
.doc-back {
  display: inline-flex; align-items: center; gap: 6px;
  font-size: 13px; color: #888; text-decoration: none;
  transition: color .15s; font-family: 'Archivo', sans-serif;
}
.doc-back:hover { color: #1A1A1A; }
.doc-meta {
  display: flex; align-items: center; gap: 12px;
  font-size: 12px; color: #888; font-family: 'Roboto', sans-serif;
}
.doc-meta span { display: flex; align-items: center; gap: 5px; }

.doc-page {
  width: 100%; max-width: 800px;
  background: #FFFFFF; border-radius: 4px;
  box-shadow: 0 2px 20px rgba(0,0,0,.08), 0 0 0 1px rgba(0,0,0,.06);
  padding: 72px 80px; min-height: 600px;
  position: relative;
}
/* Marca de agua con datos del usuario (socios comerciales / empleados).
   Va detras del contenido, no interfiere con clicks y se repite en mosaico. */
.watermark-container {
  position: absolute; inset: 0; z-index: 0;
  pointer-events: none; overflow: hidden;
  background-repeat: repeat;
}
#doc-header, #doc-content-wrap, #doc-footer-manual { position: relative; z-index: 1; }
/* Refuerzo anti-copia: sin seleccion de texto para socios comerciales. */
.sin-seleccion, .sin-seleccion * { -webkit-user-select: none !important; user-select: none !important; }
.doc-content {
  font-family: 'Roboto', sans-serif;
  font-size: 15px; line-height: 1.85; color: #2A2A2A;
}
.doc-content h1 {
  font-family: 'Roboto', sans-serif; font-size: 26px; font-weight: 700;
  color: #111; margin: 0 0 20px; padding-bottom: 14px;
  border-bottom: 2px solid #E8E4DC; line-height: 1.2;
}
.doc-content h2 {
  font-family: 'Roboto', sans-serif; font-size: 18px; font-weight: 600;
  color: #1A1A1A; margin: 32px 0 12px;
}
.doc-content h3 {
  font-family: 'Roboto', sans-serif; font-size: 15px; font-weight: 600;
  color: #333; margin: 20px 0 8px;
}
.doc-content p  { margin: 0 0 12px; }
.doc-content ul, .doc-content ol { padding-left: 24px; margin: 0 0 12px; }
.doc-content li { margin-bottom: 5px; }
.doc-content strong { font-weight: 700; color: #111; }
.doc-content em     { font-style: italic; }
.doc-content u      { text-decoration: underline; }
#doc-content-wrap table  { width: 100%; border-collapse: collapse; border: 1px solid #E0DDD6; margin: 16px 0; font-size: 13px; font-family: 'Roboto', sans-serif; }
#doc-content-wrap td, #doc-content-wrap th { border: 1px solid #E0DDD6; padding: 9px 14px; text-align: left; }
#doc-content-wrap th { background: #F7F5F0; font-weight: 600; color: #1A1A1A; font-family: 'Roboto', sans-serif; font-size: 12px; letter-spacing: .04em; text-transform: uppercase; }

/* Imagenes del contenido: nunca desbordan el ancho de la hoja. !important
   para ganar a dimensiones inline que puedan venir de Word o del editor. */
#doc-content-wrap img { max-width: 100% !important; height: auto !important; }

/* Buscador en el documento */
.doc-find-btn {
  display: inline-flex; align-items: center; justify-content: center;
  width: 30px; height: 30px; padding: 0;
  background: transparent; border: 1px solid #E0DDD6; border-radius: 8px;
  color: #666; cursor: pointer; transition: all .15s;
}
.doc-find-btn:hover { border-color: var(--dorado); color: #1A1A1A; }
.doc-find-btn svg { width: 15px; height: 15px; }
.find-bar {
  position: fixed; top: 72px; right: 28px; z-index: 500;
  display: flex; align-items: center; gap: 4px;
  padding: 6px 8px;
  background: #FFFFFF; border: 1px solid #E0DDD6;
  border-radius: 10px; box-shadow: 0 8px 28px rgba(0,0,0,.15);
}
.find-ico { width: 15px; height: 15px; color: #999; flex-shrink: 0; margin: 0 2px; }
.find-input {
  width: 190px; padding: 5px 6px;
  background: transparent; border: none; outline: none;
  color: #1A1A1A; font-family: 'Roboto', sans-serif; font-size: 13px;
}
.find-input::placeholder { color: #aaa; }
.find-count {
  font-size: 11px; color: #999; font-family: 'Roboto', sans-serif;
  min-width: 38px; text-align: center; white-space: nowrap;
}
.find-sep { width: 1px; height: 18px; background: #E0DDD6; margin: 0 3px; }
.find-nav, .find-close {
  display: flex; align-items: center; justify-content: center;
  width: 26px; height: 26px; padding: 0;
  background: transparent; border: none; border-radius: 6px;
  color: #666; cursor: pointer; transition: background .12s, color .12s;
}
.find-nav:hover, .find-close:hover { background: #F0EDE6; color: #1A1A1A; }
.find-nav svg, .find-close svg { width: 14px; height: 14px; }

/* Resaltado por Custom Highlight API: no inserta nodos en el DOM. */
::highlight(doc-find)        { background-color: rgba(255, 213, 79, .45); }
::highlight(doc-find-active) { background-color: rgba(255, 179, 0, .9); color: #1a1a1a; }

.doc-footer { width: 100%; max-width: 800px; margin-top: 24px; display: flex; flex-direction: column; gap: 12px; }

.estado-aceptacion {
  padding: 14px 20px; border-radius: 10px; font-size: 13px;
  font-family: 'Roboto', sans-serif;
  display: flex; align-items: center; gap: 10px;
}
.estado-aceptacion.aceptado  { background: rgba(92,184,122,.1);  border: 1px solid rgba(92,184,122,.25); color: #27500A; }
.estado-aceptacion.pendiente { background: rgba(226,92,92,.06);  border: 1px solid rgba(226,92,92,.2);  color: #791F1F; }

.btn-aceptar-doc {
  width: 100%; padding: 16px; background: var(--dorado);
  color: #1A1A1A; border: none; border-radius: 10px;
  font-size: 15px; font-weight: 700; font-family: 'Archivo', sans-serif;
  cursor: pointer; transition: opacity .2s, transform .1s;
  display: flex; align-items: center; justify-content: center; gap: 10px;
}
.btn-aceptar-doc:hover    { opacity: .88; }
.btn-aceptar-doc:active   { transform: scale(.99); }
.btn-aceptar-doc:disabled { opacity: .4; cursor: not-allowed; }

.nota-legal {
  font-size: 11px; color: #999; text-align: center;
  font-family: 'Roboto', sans-serif; line-height: 1.5; padding: 0 8px;
}

.modal-overlay { display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:500;align-items:center;justify-content:center;padding:16px; }
.modal-overlay.open { display:flex; }
.modal-box { background:#FFFFFF;border:1px solid #E0DDD6;border-radius:14px;width:100%;max-width:420px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.15); }
.modal-header { padding:18px 20px;border-bottom:1px solid #E8E4DC;display:flex;align-items:center;justify-content:space-between; }
.modal-header h3 { font-size:15px;font-weight:600;color:#1A1A1A; }
.modal-close { background:transparent;border:none;cursor:pointer;color:#999;padding:4px;border-radius:5px;display:flex;transition:color .15s; }
.modal-close:hover { color:#1A1A1A; }
.modal-body  { padding:20px; }
.modal-footer { padding:14px 20px;border-top:1px solid #E8E4DC;display:flex;justify-content:flex-end;gap:8px; }
.btn-modal-cancel  { padding:8px 16px;border-radius:7px;border:1px solid #E0DDD6;background:transparent;font-size:13px;font-family:'Archivo',sans-serif;color:#666;cursor:pointer;transition:all .15s; }
.btn-modal-cancel:hover { border-color:#aaa;color:#1A1A1A; }
.btn-modal-confirm { padding:8px 16px;border-radius:7px;border:none;background:var(--dorado);color:#1A1A1A;font-size:13px;font-weight:600;font-family:'Archivo',sans-serif;cursor:pointer;transition:opacity .15s;display:flex;align-items:center;gap:6px; }
.btn-modal-confirm:hover    { opacity:.88; }
.btn-modal-confirm:disabled { opacity:.4;cursor:not-allowed; }

.loading-doc { display:flex;align-items:center;justify-content:center;gap:12px;padding:80px 0;color:#999;font-size:14px;font-family:'Roboto',sans-serif; }
.spinner-doc { width:20px;height:20px;border:2px solid rgba(201,168,76,.2);border-top-color:var(--dorado);border-radius:50%;animation:spin .7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

.toast { position:fixed;bottom:24px;right:24px;background:#1A1A1A;border:1px solid #333;border-radius:10px;padding:12px 16px;font-size:13px;color:#F5F3EE;display:flex;align-items:center;gap:10px;transform:translateY(80px);opacity:0;transition:transform .3s,opacity .3s;z-index:600;font-family:'Roboto',sans-serif;max-width:340px; }
.toast.show { transform:translateY(0);opacity:1; }

::-webkit-scrollbar { width: 6px; }
::-webkit-scrollbar-track { background: #F2F0EB; }
::-webkit-scrollbar-thumb { background: #D0CDC6; border-radius: 3px; }

@media (max-width: 860px) { .doc-page { padding: 40px 28px; } }

/* ══════════════════════════════════════════════════════════
   Header y footer del manual
   ══════════════════════════════════════════════════════════ */
.doc-header {
  padding: 16px 24px;
  margin-bottom: 32px;
  border-bottom: 1px solid #e8e8e8;
  color: #444;
  font-size: 13px;
  line-height: 1.5;
  font-family: 'Roboto', sans-serif;
}
.doc-footer-manual {
  padding: 16px 24px;
  margin-top: 32px;
  border-top: 1px solid #e8e8e8;
  color: #666;
  font-size: 12px;
  line-height: 1.5;
  font-family: 'Roboto', sans-serif;
}
.doc-header img, .doc-footer-manual img { max-width: 100%; max-height: 60px; height: auto; }
.doc-header p, .doc-footer-manual p { margin: 0 0 4px; }
.doc-header p:last-child, .doc-footer-manual p:last-child { margin-bottom: 0; }

/* ══════════════════════════════════════════════════════════
   Impresión: header/footer fijos que se repiten en cada página.
   Chrome/Edge/Safari respetan position:fixed en @media print
   como "repetir en cada página".
   ══════════════════════════════════════════════════════════ */
@media print {
  @page {
    size: A4;
    margin: 3cm 2cm 3cm 2cm; /* top/right/bottom/left — deja espacio a header y footer */
  }

  /* Ocultar todo lo que no sea el contenido del manual (topbars, sidebars, botones) */
  .doc-topbar, .doc-footer, .find-bar, #estado-aceptacion-wrap, #btn-aceptar-wrap,
  .app-topbar, .app-sidebar { display: none !important; }

  /* La marca de agua SI se imprime (disuasivo ante capturas/impresiones). */
  .watermark-container {
    display: block !important;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }

  .app-layout, .app-body, .lectura-layout, main { display: block !important; }

  .doc-page {
    padding: 0 !important;
    box-shadow: none !important;
    background: white !important;
    max-width: none !important;
  }

  /* Header: fijo arriba en cada página impresa */
  .doc-header {
    position: fixed;
    top: 0; left: 0; right: 0;
    margin: 0;
    padding: 10px 20px;
    border-bottom: 1px solid #ccc;
    background: white;
    color: #333;
    page-break-after: avoid;
  }

  /* Footer: fijo abajo en cada página impresa */
  .doc-footer-manual {
    position: fixed;
    bottom: 0; left: 0; right: 0;
    margin: 0;
    padding: 10px 20px;
    border-top: 1px solid #ccc;
    background: white;
    color: #333;
    page-break-before: avoid;
  }

  /* Body a color negro y letras oscuras para impresión legible */
  body, #doc-content-wrap, #doc-content-wrap * {
    color: #000 !important;
    background: white !important;
  }
}

/* Notas / Sugerencias (vista franquiciado) */
.notas-box {
  width: 100%; max-width: 800px; margin-top: 24px;
  background: #FFFFFF; border: 1px solid #E0DDD6; border-radius: 12px;
  padding: 24px 28px; box-shadow: 0 2px 20px rgba(0,0,0,.05);
}
.notas-box-title { font-family: 'Archivo', sans-serif; font-size: 16px; font-weight: 700; color: #1A1A1A; margin-bottom: 4px; }
.notas-box-sub   { font-size: 12.5px; color: #888; font-family: 'Roboto', sans-serif; margin-bottom: 14px; line-height: 1.5; }
.nota-textarea {
  width: 100%; background: #F7F5F0; border: 1px solid #E0DDD6; border-radius: 8px;
  color: #1A1A1A; font-family: 'Roboto', sans-serif; font-size: 13.5px;
  padding: 10px 12px; resize: vertical; outline: none; line-height: 1.5;
}
.nota-textarea:focus { border-color: var(--dorado); }
.btn-nota-enviar {
  margin-top: 10px; padding: 10px 18px; background: var(--dorado);
  color: #1A1A1A; border: none; border-radius: 8px;
  font-size: 13px; font-weight: 600; font-family: 'Archivo', sans-serif;
  cursor: pointer; transition: opacity .2s;
  display: inline-flex; align-items: center; gap: 8px;
}
.btn-nota-enviar:hover    { opacity: .88; }
.btn-nota-enviar:disabled { opacity: .4; cursor: not-allowed; }
.notas-list { display: flex; flex-direction: column; gap: 10px; }
.notas-list .nota-item {
  background: #F7F5F0; border: 1px solid #E8E4DC; border-radius: 10px; padding: 12px 14px;
}
.nota-item-header { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 5px; }
.nota-fecha { font-size: 11px; color: #999; font-family: 'Roboto', sans-serif; }
.nota-contenido { font-size: 13.5px; color: #333; font-family: 'Roboto', sans-serif; line-height: 1.55; white-space: pre-wrap; word-break: break-word; }
.nota-estado { display: inline-block; font-size: 10px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; padding: 3px 9px; border-radius: 20px; }
.nota-estado.pendiente { background: rgba(201,168,76,.15); color: #8A6D1B; }
.nota-estado.leida     { background: rgba(55,138,221,.12); color: #2A5E9E; }
.nota-estado.resuelta  { background: rgba(92,184,122,.15); color: #27500A; }
.notas-empty { font-size: 12.5px; color: #aaa; font-family: 'Roboto', sans-serif; padding: 4px 0; }

</style>

<link href="https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,400;0,500;1,400&display=swap" rel="stylesheet">

<div class="app-layout">
  <?php include 'layout/topbar.php'; ?>

  <div class="lectura-layout">

    <div class="doc-topbar">
      <a href="mis-manuales.php" class="doc-back">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        Volver a manuales
      </a>
      <div class="doc-meta">
        <span id="doc-empresa">—</span>
        <span style="color:#ccc">·</span>
        <span id="doc-version">—</span>
        <span style="color:#ccc">·</span>
        <span id="doc-fecha">—</span>
        <button class="doc-find-btn" onclick="toggleBuscador()" title="Buscar en el documento (Ctrl+F)">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.5" y2="16.5"/></svg>
        </button>
      </div>
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

    <div class="doc-page">

      <!-- Marca de agua (datos del usuario) — se llena por JS solo para socios comerciales -->
      <div class="watermark-container" id="watermark"></div>

      <!-- Encabezado del manual (mostrado en pantalla y en impresión) -->
      <div id="doc-header" class="doc-header" style="display:none"></div>

      <div id="doc-content-wrap" class="doc-content">
        <div class="loading-doc">
          <div class="spinner-doc"></div>
          Cargando manual...
        </div>
      </div>

      <!-- Pie de página del manual (mostrado en pantalla y en impresión) -->
      <div id="doc-footer-manual" class="doc-footer-manual" style="display:none"></div>
    </div>

    <div class="doc-footer" id="doc-footer" style="display:none">
      <div id="estado-aceptacion-wrap"></div>
      <div id="btn-aceptar-wrap"></div>
      <div class="nota-legal" id="nota-legal" style="display:none">
        Al aceptar, confirmás haber leído y comprendido el contenido de este manual.
      </div>
    </div>

    <!-- Notas / Sugerencias (solo franquiciado) -->
    <div class="notas-box" id="notas-box" style="display:none">
      <div class="notas-box-title">Notas y sugerencias</div>
      <p class="notas-box-sub">Dej&aacute; una sugerencia sobre este manual.</p>

      <textarea id="nota-texto" rows="3" maxlength="5000" placeholder="Escrib&iacute; tu sugerencia..." class="nota-textarea"></textarea>
      <button class="btn-nota-enviar" id="btn-nota-enviar" onclick="agregarNota()">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2L11 13"/><path d="M22 2L15 22 11 13 2 9l20-7z"/></svg>
        Enviar sugerencia
      </button>

      <div class="notas-list" id="notas-list" style="margin-top:16px"></div>
    </div>

  </div>
</div>

<!-- MODAL CONFIRMACIÓN -->
<div class="modal-overlay" id="modal-confirmar">
  <div class="modal-box">
    <div class="modal-header">
      <h3>Confirmar aceptación digital</h3>
      <button class="modal-close" onclick="cerrarModal()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-body">
      <p style="font-size:14px;color:#444;line-height:1.7;font-family:'Montserrat',sans-serif;margin-bottom:12px">
        Estás a punto de aceptar digitalmente el manual:
      </p>
      <div style="background:#F7F5F0;border-radius:8px;padding:12px 16px;margin-bottom:14px">
        <div style="font-size:14px;font-weight:600;color:#1A1A1A" id="modal-manual-nombre"></div>
        <div style="font-size:12px;color:#888;margin-top:3px;font-family:'Roboto',sans-serif" id="modal-manual-version"></div>
      </div>
      <p style="font-size:14px;color:#666;line-height:1.6;font-family:'Montserrat',sans-serif">
        Esta acción es <strong style="color:#1A1A1A">queda registrada</strong> una vez aceptes que leiste el manual.</strong>.
      </p>
      <div id="modal-error" style="display:none;margin-top:12px;background:rgba(226,92,92,.1);border:1px solid rgba(226,92,92,.3);border-radius:7px;padding:10px 12px;font-size:13px;color:#E25C5C"></div>
    </div>
    <div class="modal-footer">
      <button class="btn-modal-cancel" onclick="cerrarModal()">Cancelar</button>
      <button class="btn-modal-confirm" id="btn-confirmar" onclick="ejecutarAceptacion()">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        Confirmar aceptación
      </button>
    </div>
  </div>
</div>

<div class="toast" id="toast"><span id="toast-icon"></span><span id="toast-msg"></span></div>

<script>
const MANUAL_ID = new URLSearchParams(location.search).get('id');

let versionActivaId = null;
let yaAceptado      = false;
let rolUsuario      = '';

const LISTADO_POR_ROL = {
  franquiciado:  'mis-manuales.php',
  empleado:      'mis-manuales.php',
  franquiciante: 'dashboard.php',   // llega en modo "vista previa" desde el dashboard
  super_admin:   'manuales.php',
};

function destinoListado(rol) {
  // perfil.php es el fallback: no tiene restriccion de rol, asi que es seguro
  // para cualquier usuario autenticado.
  return LISTADO_POR_ROL[rol] ?? 'perfil.php';
}

// Redirect de error: resuelve el rol antes de elegir destino, para no mandar a
// un usuario a una pagina que le va a responder 403.
async function volverAlListado(mensaje) {
  mostrarToast(mensaje, 'error');

  let destino = 'perfil.php';
  try {
    const me = await apiFetch('GET', '/me');
    destino = destinoListado(me.rol);
  } catch (e) {
    // Sin sesion valida: perfil.php ya redirige solo a login.html.
  }

  setTimeout(() => window.location.href = destino, 2000);
}

async function init() {
  if (!MANUAL_ID) {
    await volverAlListado('No se especificó un manual.');
    return;
  }

  try {
    // Obtener rol del usuario para saber si mostrar aceptación
    const me = await apiFetch('GET', '/me');
    rolUsuario = me.rol;

    // Marca de agua con datos del usuario: solo socios comerciales (franquiciado) y empleados.
    if (rolUsuario === 'franquiciado' || rolUsuario === 'empleado') {
      ponerMarcaDeAgua(me);
      document.getElementById('doc-content-wrap')?.classList.add('sin-seleccion');
    }

    // Destino del boton "Volver" segun el rol (ver LISTADO_POR_ROL).
    // El franquiciante llega aca en modo "vista previa" desde el dashboard;
    // el super_admin, desde manuales.php; el resto, desde mis-manuales.php.
    const back = document.querySelector('.doc-back');
    if (back) back.setAttribute('href', destinoListado(rolUsuario));

    const manual  = await apiFetch('GET', `/manuales/${MANUAL_ID}`);
    const version = manual.version_activa?.[0];

    if (!version) {
      document.getElementById('doc-content-wrap').innerHTML =
        `<div class="loading-doc" style="color:#999">Este manual no tiene versión publicada.</div>`;
      return;
    }

    versionActivaId = version.id;
    yaAceptado      = manual.mi_aceptacion || false;

    // Header/footer: se leen del SNAPSHOT DE LA VERSION, no del manual.
    //
    // El manual guarda la copia de trabajo (lo que el franquiciante está editando
    // ahora). La versión guarda lo que estaba congelado al publicarse — que es lo
    // que el socio comercial aceptó y lo que su hash_verificacion certifica.
    //
    // Leer del manual era el bug: cambiar el pie de página de un manual ya aceptado
    // alteraba el documento que el socio imprimía y firmaba, sin versión nueva.
    //
    // El ?? manual.* es fallback para versiones anteriores a la migración; después
    // del backfill no debería usarse nunca.
    const encabezado = version.encabezado_html ?? manual.encabezado_html;
    const pie        = version.pie_pagina_html ?? manual.pie_pagina_html;

    const headerEl = document.getElementById('doc-header');
    const footerEl = document.getElementById('doc-footer-manual');
    if (encabezado && encabezado.trim()) {
      headerEl.innerHTML = encabezado;
      headerEl.style.display = 'block';
    }
    if (pie && pie.trim()) {
      footerEl.innerHTML = pie;
      footerEl.style.display = 'block';
    }

    document.title = `${manual.titulo} — Cerrajería Leonardo`;
    // v2.3: me.perfil ya no existe; empresa.nombre viene anidado en /me
    document.getElementById('doc-empresa').textContent = me.empresa?.nombre || '—';
    document.getElementById('doc-version').textContent  = `v${version.version_label || (version.version_number + '.' + (version.version_minor ?? 0))}`;
    document.getElementById('doc-fecha').textContent    = formatFecha(version.publicado_at);

    document.getElementById('doc-content-wrap').innerHTML =
      version.contenido_html || '<p style="color:#999">Sin contenido.</p>';

    document.getElementById('doc-footer').style.display = 'flex';

    // Solo el franquiciado acepta; el resto ve solo lectura, sin aceptación ni nota legal
    if (rolUsuario !== 'franquiciado') {
      document.getElementById('doc-footer').style.display         = 'none';
      document.getElementById('estado-aceptacion-wrap').innerHTML = '';
      document.getElementById('btn-aceptar-wrap').innerHTML       = '';
      document.getElementById('nota-legal').style.display         = 'none';
      return;
    }

    // Franquiciado: muestra estado de aceptación
    const estadoWrap = document.getElementById('estado-aceptacion-wrap');
    if (yaAceptado) {
      estadoWrap.innerHTML = `
        <div class="estado-aceptacion aceptado">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
          Ya aceptaste este manual digitalmente.
        </div>`;
    } else {
      estadoWrap.innerHTML = `
        <div class="estado-aceptacion pendiente">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          Todavía no aceptaste este manual. Leelo y aceptá al finalizar.
        </div>`;

      document.getElementById('btn-aceptar-wrap').innerHTML = `
        <button class="btn-aceptar-doc" onclick="abrirModal()">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
          Aceptar manual
        </button>`;

      document.getElementById('nota-legal').style.display = 'block';

      document.getElementById('modal-manual-nombre').textContent  = manual.titulo;
      document.getElementById('modal-manual-version').textContent =
        `Versión ${version.version_label || (version.version_number + '.' + (version.version_minor ?? 0))} · Publicado el ${formatFecha(version.publicado_at)}`;
    }

    if (rolUsuario === 'franquiciado') cargarNotas();

  } catch (e) {
    document.getElementById('doc-content-wrap').innerHTML =
      `<div class="loading-doc" style="color:#999">Error al cargar el manual.</div>`;
  }
}

function abrirModal() {
  document.getElementById('modal-error').style.display = 'none';
  document.getElementById('modal-confirmar').classList.add('open');
}

function cerrarModal() {
  document.getElementById('modal-confirmar').classList.remove('open');
}

async function ejecutarAceptacion() {
  const btn = document.getElementById('btn-confirmar');
  btn.disabled  = true;
  btn.innerHTML = `<div style="width:14px;height:14px;border:2px solid rgba(26,26,26,.3);border-top-color:#1A1A1A;border-radius:50%;animation:spin .6s linear infinite"></div> Registrando...`;

  try {
    await apiFetch('POST', `/versiones/${versionActivaId}/aceptar`);
    cerrarModal();
    mostrarToast('¡Manual aceptado correctamente!', 'exito');
    document.getElementById('estado-aceptacion-wrap').innerHTML = `
      <div class="estado-aceptacion aceptado">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        ¡Aceptaste este manual digitalmente!
      </div>`;
    document.getElementById('btn-aceptar-wrap').innerHTML   = '';
    document.getElementById('nota-legal').style.display     = 'none';
  } catch (e) {
    const msg = e.data?.message || 'Error al registrar la aceptación.';
    document.getElementById('modal-error').textContent   = msg;
    document.getElementById('modal-error').style.display = 'block';
    btn.disabled  = false;
    btn.innerHTML = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> Confirmar aceptación`;
  }
}

function formatFecha(str) {
  if (!str) return '—';
  return new Date(str).toLocaleDateString('es-AR', { day:'2-digit', month:'2-digit', year:'numeric' });
}

let toastTimer;
function mostrarToast(msg, tipo = 'exito') {
  const el   = document.getElementById('toast');
  const icon = tipo === 'exito'
    ? `<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#5CB87A" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>`
    : `<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#E25C5C" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>`;
  document.getElementById('toast-icon').innerHTML  = icon;
  document.getElementById('toast-msg').textContent = msg;
  el.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => el.classList.remove('show'), 3500);
}

// ── NOTAS / SUGERENCIAS (franquiciado) ────────────────────────
async function cargarNotas() {
  document.getElementById('notas-box').style.display = 'block';
  const el = document.getElementById('notas-list');
  el.innerHTML = `<div class="notas-empty">Cargando notas...</div>`;
  try {
    const notas = await apiFetch('GET', `/manuales/${MANUAL_ID}/notas`);
    renderNotas(notas);
  } catch (e) {
    el.innerHTML = `<div class="notas-empty" style="color:#E25C5C">Error al cargar las notas.</div>`;
  }
}

function escNota(str) {
  return String(str ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
}

function renderNotas(notas) {
  const el = document.getElementById('notas-list');
  if (!notas.length) {
    el.innerHTML = `<div class="notas-empty">Todavía no dejaste ninguna nota en este manual.</div>`;
    return;
  }
  el.innerHTML = notas.map(n => {
    const estado = n.estado || 'pendiente';
    return `
      <div class="nota-item">
        <div class="nota-item-header">
          <span class="nota-estado ${estado}">${estado}</span>
          <span class="nota-fecha">${formatFecha(n.created_at)}</span>
        </div>
        <div class="nota-contenido">${escNota(n.contenido || '')}</div>
      </div>`;
  }).join('');
}

async function agregarNota() {
  const ta  = document.getElementById('nota-texto');
  const txt = ta.value.trim();
  if (!txt) { mostrarToast('Escribí algo antes de enviar.', 'error'); return; }

  const btn = document.getElementById('btn-nota-enviar');
  btn.disabled = true;
  try {
    await apiFetch('POST', `/manuales/${MANUAL_ID}/notas`, { contenido: txt });
    ta.value = '';
    mostrarToast('Sugerencia enviada.', 'exito');
    await cargarNotas();
  } catch (e) {
    mostrarToast(e.data?.message || 'Error al enviar la sugerencia.', 'error');
  } finally {
    btn.disabled = false;
  }
}

// ── MARCA DE AGUA ─────────────────────────────────────────────
// Estampa nombre + apellido (+ sucursal si tiene) en mosaico diagonal, muy tenue,
// detras del contenido. Disuasivo ante capturas/impresiones no autorizadas.
function ponerMarcaDeAgua(me) {
  const nombre   = [me.nombre, me.apellido].filter(Boolean).join(' ').trim() || me.email || '';
  const sucursal = me.perfil && me.perfil.franquicia ? me.perfil.franquicia.nombre : null;
  const texto    = sucursal ? `${nombre} \u00B7 ${sucursal}` : nombre;
  const cont = document.getElementById('watermark');
  if (!cont || !texto) return;

  // SVG tile: texto rotado -30, gris muy tenue (no estorba la lectura).
  const svg =
    `<svg xmlns='http://www.w3.org/2000/svg' width='360' height='210'>` +
    `<text x='180' y='110' fill='rgba(0,0,0,0.07)' font-size='15' font-weight='600' ` +
    `font-family='Arial, sans-serif' text-anchor='middle' transform='rotate(-30 180 105)'>` +
    `${escaparXML(texto)}</text></svg>`;
  cont.style.backgroundImage = `url("data:image/svg+xml,${encodeURIComponent(svg)}")`;
}

function escaparXML(s) {
  return String(s)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;').replace(/'/g, '&apos;');
}

document.addEventListener('contextmenu', (e) => {
    e.preventDefault();
    // Opcional: mostrar un toast diciendo "Acción no permitida por confidencialidad"
});


document.addEventListener('keydown', (e) => {
    // Bloquear Ctrl + P (Imprimir)
    if (e.ctrlKey && e.key === 'p') {
        e.preventDefault();
        alert('La impresión de este documento está deshabilitada.');
    }
    // Bloquear Ctrl + S (Guardar página)
    if (e.ctrlKey && e.key === 's') {
        e.preventDefault();
    }
    if (e.ctrlKey && e.key === 'c') {
        e.preventDefault();
    }
    if (e.ctrlKey && e.key === 'v') {
        e.preventDefault();
    }

    // Bloquear F12 o Ctrl+Shift+I (Inspeccionar elemento)
    if (e.key === 'F12' || (e.ctrlKey && e.shiftKey && e.key === 'I')) {
        e.preventDefault();
    }
});
document.addEventListener('keydown', e => { if (e.key === 'Escape') cerrarModal(); });
// ── BUSCADOR EN EL DOCUMENTO ─────────────────────
// Resalta con la CSS Custom Highlight API (sin tocar el DOM). El contenido
// de lectura no se edita, y el scroll es el de la ventana.
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
  document.getElementById('find-bar').style.display = 'flex';
  const input = document.getElementById('find-input');
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

  const cont = document.getElementById('doc-content-wrap');
  const walker = document.createTreeWalker(cont, NodeFilter.SHOW_TEXT, null);
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
    } catch (e) { /* rango invalido: se ignora */ }
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
    hActive.priority = 1;
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
  el.textContent = findMatches.length ? (findIndex + 1) + '/' + findMatches.length : '0/0';
}

// El scroll de la vista de lectura es el de la ventana.
function scrollARange(range) {
  const rect = range.getBoundingClientRect();
  if (!rect.height && !rect.width) return;
  const target = rect.top + window.scrollY - (window.innerHeight / 2) + (rect.height / 2);
  window.scrollTo({ top: Math.max(0, target), behavior: 'smooth' });
}

function initBuscador() {
  const input = document.getElementById('find-input');
  input.addEventListener('input', ejecutarBusqueda);
  input.addEventListener('keydown', (e) => {
    if (e.key === 'Enter')       { e.preventDefault(); buscarNav(e.shiftKey ? -1 : 1); }
    else if (e.key === 'Escape') { e.preventDefault(); cerrarBuscador(); }
  });
  document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && (e.key === 'f' || e.key === 'F')) {
      e.preventDefault();
      abrirBuscador();
    }
  });
}

document.addEventListener('DOMContentLoaded', () => { init(); initBuscador(); });
</script>

<?php include 'layout/footer.php'; ?>