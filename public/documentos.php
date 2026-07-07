<?php
require_once __DIR__ . '/layout/config.php';
require_once __DIR__ . '/layout/auth.php';
verificarSesion();
$titulo        = 'Documentos';
$pagina_actual = 'documentos';
include 'layout/head.php';
?>

<div class="app-layout">
  <?php include 'layout/topbar.php'; ?>
  <div class="app-body">
    <?php include 'layout/sidebar.php'; ?>
    <main class="main-content">

      <div class="page-header">
        <div>
          <div class="page-title" id="page-title">Documentos</div>
          <div class="page-sub" id="page-sub">Repositorio de documentos operativos</div>
        </div>
        <button class="btn btn-primary" id="btn-subir" style="display:none" onclick="abrirModalSubir()">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Subir documento
        </button>
      </div>

      <!-- ══════════════════════════════════════════════════
           VISTA LISTA (documentos padre)
           ══════════════════════════════════════════════════ -->
      <div id="vista-lista">

      <!-- Filtros -->
      <div id="filtros-wrap" style="display:none;gap:8px;margin-bottom:20px;flex-wrap:wrap;align-items:center">

        <!-- Combobox empresa — solo super_admin (mismo patrón que en manuales.php) -->
        <div id="grupo-sel-empresa" style="display:none;align-items:center;gap:8px;position:relative">
          <div id="empresa-combo" style="position:relative;width:240px">
            <input type="text" id="inp-empresa" placeholder="Buscar empresa..." autocomplete="off" name="combo-empresa-doc"
                   class="buscar-input" style="width:100%;box-sizing:border-box;padding-right:30px"
                   oninput="filtrarOpcionesEmpresa()" onfocus="filtrarOpcionesEmpresa()">
            <svg style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--gris4)" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <button type="button" id="empresa-clear" onclick="limpiarEmpresa()" title="Mostrar todas las empresas"
                    style="display:none;position:absolute;right:8px;top:50%;transform:translateY(-50%);background:transparent;border:none;color:var(--gris4);cursor:pointer;padding:2px;line-height:0">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
            <div id="empresa-opciones" class="combo-opciones"></div>
          </div>
        </div>

        <!-- Filtros de tipo, visibilidad y franquicia -->
        <select id="sel-tipo" class="filtro-select" onchange="aplicarFiltros()">
          <option value="">Todos los tipos</option>
          <option value="contrato">Contrato</option>
          <option value="politica">Política</option>
          <option value="protocolo">Protocolo</option>
          <option value="circular">Circular</option>
          <option value="anexo">Anexo</option>
          <option value="acta">Acta</option>
          <option value="procedimiento">Procedimiento</option>
          <option value="otro">Otro</option>
        </select>

        <!-- v2.3: filtro por visibilidad (categorías) -->
        <select id="sel-categoria" class="filtro-select" onchange="aplicarFiltros()" disabled title="Elegí una empresa para habilitar este filtro">
          <option value="">Todas las categorías</option>
        </select>

        <select id="sel-franquicia" class="filtro-select" onchange="aplicarFiltros()">
          <option value="">Todas las franquicias</option>
          <option value="global">Solo globales</option>
        </select>

        <div style="margin-left:auto;position:relative">
          <input type="text" id="inp-buscar" placeholder="Buscar documento..." oninput="aplicarFiltros()" class="buscar-input">
          <svg style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--gris4)" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        </div>
      </div>

      <!-- Tabla -->
      <div class="tabla-wrap">
        <div class="tabla-header">
          <span id="tabla-titulo">Cargando...</span>
        </div>
        <div style="overflow-x:auto">
          <table class="tabla" id="tabla-docs">
            <thead id="tabla-head"></thead>
            <tbody id="tabla-body">
              <tr><td colspan="6"><div class="empty-state">Cargando documentos...</div></td></tr>
            </tbody>
          </table>
        </div>
      </div>

      </div><!-- /vista-lista -->

      <!-- ══════════════════════════════════════════════════
           VISTA DETALLE (versiones de un documento)
           ══════════════════════════════════════════════════ -->
      <div id="vista-detalle" style="display:none">
        <div style="margin-bottom:20px">
          <button class="btn btn-ghost" onclick="volverALista()" style="padding:6px 12px;font-size:12px">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            Volver a documentos
          </button>
        </div>

        <!-- Header del documento -->
        <div class="detalle-header">
          <div style="flex:1">
            <div class="detalle-titulo" id="detalle-titulo">—</div>
            <div class="detalle-meta" id="detalle-meta">—</div>
          </div>
          <button class="btn btn-primary" id="btn-subir-version" style="display:none" onclick="abrirModalSubirVersion()">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Subir nueva versión
          </button>
        </div>

        <!-- Lista de versiones -->
        <div class="tabla-wrap">
          <div class="tabla-header" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
            <span id="versiones-titulo">Historial de versiones</span>
            <button id="btn-mostrar-eliminadas-ver" class="filtro-btn" style="display:none" onclick="toggleMostrarEliminadasVer(this)">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:4px"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              Mostrar eliminadas
            </button>
          </div>
          <div id="versiones-lista">
            <div class="empty-state" style="padding:24px"><div class="spinner" style="display:block;margin:0 auto 8px"></div>Cargando versiones...</div>
          </div>
        </div>
      </div><!-- /vista-detalle -->

    </main>
  </div>
</div>

<!-- ══════════════════════════════════════════════════
     MODAL SUBIR DOCUMENTO
══════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modal-subir">
  <div class="modal-box">
    <div class="modal-header">
      <h3>Subir documento</h3>
      <button class="modal-close" onclick="cerrarModalSubir()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-body">

      <div class="form-group">
        <label>Título del documento *</label>
        <input type="text" id="doc-titulo" placeholder="Ej: Contrato de franquicia 2026" maxlength="200">
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Tipo *</label>
          <select id="doc-tipo" class="form-select">
            <option value="">Seleccioná un tipo</option>
            <option value="contrato">Contrato</option>
            <option value="politica">Política</option>
            <option value="protocolo">Protocolo</option>
            <option value="circular">Circular</option>
            <option value="anexo">Anexo</option>
            <option value="acta">Acta</option>
            <option value="procedimiento">Procedimiento</option>
            <option value="otro">Otro</option>
          </select>
        </div>
        <div class="form-group" id="grupo-empresa-doc" style="display:none">
        <label>Empresa *</label>
        <select id="doc-empresa" class="form-select" onchange="onEmpresaDocChange()">
          <option value="">Seleccioná una empresa</option>
        </select>
        </div>
      </div>

      <div class="form-group">
        <label style="cursor:pointer">
          <input type="checkbox" id="doc-visible" style="margin-right:6px;accent-color:var(--dorado)" onchange="onToggleVisibilidadCategorias('doc')">
          ¿Permitir que sea visible para Socios comerciales?
        </label>
        <div style="font-size:13px;color:var(--gris4);margin-top:4px;font-family:'Roboto',sans-serif">
          Si está activado, elegí qué categorías de Socios comerciales podrán ver y descargar este documento.
        </div>

        <!-- Sub-bloque: lista de categorías (visible solo cuando el toggle está ON) -->
        <div id="doc-categorias-wrap" style="display:none;margin-top:12px;background:var(--negro);border:1px solid var(--gris2);border-radius:8px;padding:12px">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
            <span style="font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:var(--gris4)">Categorías visibles</span>
            <button type="button" id="doc-btn-sel-todas" onclick="toggleSeleccionarTodasCategorias('doc')"
                    style="background:transparent;border:none;color:var(--dorado);font-size:12px;cursor:pointer;font-family:'Roboto',sans-serif;padding:0;text-decoration:underline">
              Seleccionar todos
            </button>
          </div>
          <div id="doc-categorias-lista">
            <div style="font-size:12px;color:var(--gris4);font-family:'Roboto',sans-serif;padding:8px 0">
              ${''}
            </div>
          </div>
          <div id="doc-categorias-warning" style="display:none;margin-top:10px;padding:8px 10px;background:rgba(212,165,46,.08);border:1px solid rgba(212,165,46,.3);border-radius:6px;font-size:12px;color:var(--dorado);font-family:'Roboto',sans-serif;line-height:1.5">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:4px;flex-shrink:0"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            Sin categorías marcadas: ningún socio comercial verá este documento.
          </div>
        </div>
      </div>

      <!-- Empresa — solo super_admin -->
      <div class="form-group">
          <label>Franquicia destino</label>
          <select id="doc-franquicia" class="form-select">
            <option value="">Toda la empresa (global)</option>
          </select>
          <div style="font-size:13px;color:var(--gris4);margin-top:4px;font-family:'Roboto',sans-serif">Dejá vacío para que aplique a todas las franquicias.</div>
      </div>

      <!-- Zona de archivo -->
      <div class="form-group">
        <label>Archivo *</label>
        <div class="drop-zone" id="drop-zone" onclick="document.getElementById('doc-archivo').click()">
          <input type="file" id="doc-archivo" accept=".pdf,.doc,.docx" style="display:none" onchange="onArchivoSeleccionado(this)">
          <div id="drop-zone-content">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color:var(--gris4);margin-bottom:8px"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            <div style="font-size:13px;color:var(--gris5)">Hacé clic o arrastrá un archivo</div>
            <div style="font-size:11px;color:var(--gris4);margin-top:4px">PDF, DOC o DOCX — máximo 20 MB</div>
          </div>
        </div>
      </div>

      <div class="form-error" id="doc-error" style="display:none"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="cerrarModalSubir()">Cancelar</button>
      <button class="btn btn-primary" id="btn-confirmar-subir" onclick="subirDocumento()">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
        Subir documento
      </button>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════
     MODAL ELIMINAR DOCUMENTO
     ══════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modal-eliminar">
  <div class="modal-box" style="max-width:380px">
    <div class="modal-header">
      <h3>Eliminar documento</h3>
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

<!-- ══════════════════════════════════════════════════
     MODAL ELIMINAR VERSIÓN
     ══════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modal-eliminar-version">
  <div class="modal-box" style="max-width:420px">
    <div class="modal-header">
      <h3>Eliminar versión</h3>
      <button class="modal-close" onclick="cerrarModalEliminarVersion()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-body">
      <p id="eliminar-ver-msg" style="font-size:14px;color:var(--gris5);line-height:1.6;font-family:'Roboto',sans-serif"></p>
      <p id="eliminar-ver-aviso" style="font-size:12px;color:var(--dorado);line-height:1.5;font-family:'Roboto',sans-serif;margin-top:10px;display:none">
        <strong>Atención:</strong> esta es la versión vigente. Al eliminarla, la versión inmediatamente anterior pasará a ser la vigente.
      </p>
      <div class="form-error" id="eliminar-ver-error"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="cerrarModalEliminarVersion()">Cancelar</button>
      <button class="btn btn-danger" id="btn-eliminar-ver-confirmar" onclick="ejecutarEliminarVersion()">Eliminar</button>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════
     MODAL SUBIR NUEVA VERSIÓN
     ══════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modal-subir-version">
  <div class="modal-box">
    <div class="modal-header">
      <h3>Subir nueva versión</h3>
      <button class="modal-close" onclick="cerrarModalSubirVersion()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-body">
      <p style="font-size:12px;color:var(--gris4);margin-bottom:14px;font-family:'Roboto',sans-serif">
        La nueva versión quedará como vigente. La anterior pasa al historial.
      </p>

      <div class="form-group">
        <label class="form-label">Archivo *</label>
        <div class="drop-zone" id="drop-zone-v">
          <input type="file" id="archivo-v" accept=".pdf,.doc,.docx" style="display:none" onchange="onArchivoVersionSel(event)">
          <div id="drop-msg-v">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--gris4);margin-bottom:8px"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            <div style="font-size:13px;color:var(--gris5);margin-bottom:4px">Soltá el archivo acá o</div>
            <button type="button" class="btn btn-ghost" onclick="document.getElementById('archivo-v').click()" style="font-size:12px;padding:6px 14px">
              Seleccionar archivo
            </button>
            <div style="font-size:11px;color:var(--gris4);margin-top:10px;font-family:'Roboto',sans-serif">PDF, DOC o DOCX · máx. 20 MB</div>
          </div>
          <div id="drop-info-v" style="display:none">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;justify-content:center">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--dorado)"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
              <div>
                <div id="drop-nombre-v" style="color:var(--blanco);font-weight:500;font-size:13px"></div>
                <div id="drop-tamano-v" style="font-size:11px;color:var(--gris4);font-family:'Roboto',sans-serif"></div>
              </div>
            </div>
            <button type="button" class="btn btn-ghost" onclick="resetDropZoneVersion()" style="font-size:12px;padding:4px 10px">Cambiar archivo</button>
          </div>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Tipo de cambio *</label>
        <div class="tipo-cambio-grid">
          <button type="button" class="tipo-cambio-opt" id="tc-menor" onclick="setTipoCambio('menor')">
            <div class="tc-num" id="tc-num-menor">v0.0</div>
            <div class="tc-titulo">Cambio menor</div>
            <div class="tc-desc">Correcciones o ajustes que no alteran el fondo del documento.</div>
          </button>
          <button type="button" class="tipo-cambio-opt" id="tc-mayor" onclick="setTipoCambio('mayor')">
            <div class="tc-num" id="tc-num-mayor">v0.0</div>
            <div class="tc-titulo">Cambio mayor</div>
            <div class="tc-desc">Modificaciones sustanciales del contenido del documento.</div>
          </button>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Nota (opcional)</label>
        <textarea id="version-nota" maxlength="500" rows="3" class="form-input" placeholder="Describí qué cambió en esta versión (opcional)..." style="resize:vertical;font-family:'Roboto',sans-serif"></textarea>
        <div style="font-size:11px;color:var(--gris4);margin-top:4px;font-family:'Roboto',sans-serif">Podés agregar o editar la nota más tarde.</div>
      </div>

      <div class="form-error" id="version-error"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="cerrarModalSubirVersion()">Cancelar</button>
      <button class="btn btn-primary" id="btn-confirmar-version" onclick="subirNuevaVersion()">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
        Subir versión
      </button>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════
     MODAL EDITAR DOCUMENTO (padre)
     ══════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modal-editar-doc">
  <div class="modal-box">
    <div class="modal-header">
      <h3>Editar documento</h3>
      <button class="modal-close" onclick="cerrarModalEditarDoc()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-body">
      <p style="font-size:12px;color:var(--gris4);margin-bottom:14px;font-family:'Roboto',sans-serif">
        Estos datos se aplican a todas las versiones del documento.
      </p>

      <div class="form-group">
        <label>Título *</label>
        <input type="text" id="edit-doc-titulo" maxlength="200" placeholder="Título del documento">
      </div>

      <div class="form-group">
        <label>Tipo *</label>
        <select id="edit-doc-tipo" class="form-select">
          <option value="contrato">Contrato</option>
          <option value="politica">Política</option>
          <option value="protocolo">Protocolo</option>
          <option value="circular">Circular</option>
          <option value="anexo">Anexo</option>
          <option value="acta">Acta</option>
          <option value="procedimiento">Procedimiento</option>
          <option value="otro">Otro</option>
        </select>
      </div>

      <div class="form-group">
        <label>Franquicia destino</label>
        <select id="edit-doc-franquicia" class="form-select">
          <option value="">Toda la empresa (global)</option>
        </select>
        <div style="font-size:13px;color:var(--gris4);margin-top:4px;font-family:'Roboto',sans-serif">
          Si elegís una franquicia específica, solo los franquiciados/empleados de esa sucursal verán este documento.
        </div>
      </div>

      <div class="form-group">
        <label style="cursor:pointer">
          <input type="checkbox" id="edit-doc-visible" style="margin-right:6px;accent-color:var(--dorado)" onchange="onToggleVisibilidadCategorias('edit-doc')">
          ¿Permitir que sea visible para Socios comerciales?
        </label>
        <div style="font-size:13px;color:var(--gris4);margin-top:4px;font-family:'Roboto',sans-serif">
          Si está desactivado, solo los franquiciantes y super admins verán este documento. Las categorías marcadas se preservan para cuando vuelvas a activarlo.
        </div>

        <div id="edit-doc-categorias-wrap" style="display:none;margin-top:12px;background:var(--negro);border:1px solid var(--gris2);border-radius:8px;padding:12px">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
            <span style="font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:var(--gris4)">Categorías visibles</span>
            <button type="button" id="edit-doc-btn-sel-todas" onclick="toggleSeleccionarTodasCategorias('edit-doc')"
                    style="background:transparent;border:none;color:var(--dorado);font-size:12px;cursor:pointer;font-family:'Roboto',sans-serif;padding:0;text-decoration:underline">
              Seleccionar todos
            </button>
          </div>
          <div id="edit-doc-categorias-lista">
            <div style="font-size:12px;color:var(--gris4);font-family:'Roboto',sans-serif;padding:8px 0">
              Cargando categorías...
            </div>
          </div>
          <div id="edit-doc-categorias-warning" style="display:none;margin-top:10px;padding:8px 10px;background:rgba(212,165,46,.08);border:1px solid rgba(212,165,46,.3);border-radius:6px;font-size:12px;color:var(--dorado);font-family:'Roboto',sans-serif;line-height:1.5">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:4px;flex-shrink:0"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            Sin categorías marcadas: ningún socio comercial verá este documento.
          </div>
        </div>
      </div>

      <div class="form-error" id="edit-doc-error"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="cerrarModalEditarDoc()">Cancelar</button>
      <button class="btn btn-success" id="btn-guardar-doc" onclick="guardarEdicionDocumento()">Guardar cambios</button>
    </div>
  </div>
</div>

<!-- ── TOAST ──────────────────────────────────────────────────── -->
<div class="toast" id="toast"><span id="toast-icon"></span><span id="toast-msg"></span></div>

<style>
/* ── Filtros ──────────────────────────────────────────────── */
.filtro-select {
  background: var(--gris2); border: 1px solid var(--gris2);
  border-radius: 7px; color: var(--gris5); font-size: 12px;
  font-family: 'Roboto', sans-serif; padding: 7px 10px;
  cursor: pointer; outline: none; transition: border-color .2s;
}
.filtro-select:focus { border-color: var(--dorado); }
.filtro-select option { background: var(--gris1); }
.filtro-select-lg {
  background: var(--gris1); border: 1px solid var(--gris2);
  border-radius: 8px; color: var(--blanco); font-size: 13px;
  font-family: 'Roboto', sans-serif; padding: 9px 14px;
  outline: none; cursor: pointer; transition: border-color .2s; min-width: 220px;
}
.filtro-select-lg:focus { border-color: var(--dorado); }
.filtro-select-lg option { background: var(--gris1); }
.buscar-input {
  background: var(--gris1); border: 1px solid var(--gris2);
  border-radius: 8px; padding: 8px 12px 8px 32px;
  font-size: 13px; font-family: 'Roboto', sans-serif;
  color: var(--blanco); outline: none; transition: border-color .2s; width: 220px;
}
.buscar-input:focus { border-color: var(--dorado); }
.buscar-input::placeholder { color: var(--gris3); }

/* ── Combobox de empresa (igual a manuales.php) ─────────────── */
.combo-opciones { display:none;position:absolute;top:calc(100% + 4px);left:0;right:0;max-height:240px;overflow-y:auto;background:var(--gris1);border:1px solid var(--gris2);border-radius:8px;z-index:50;box-shadow:0 8px 24px rgba(0,0,0,.4); }
.combo-opcion { padding:9px 12px;font-size:13px;color:var(--gris5);cursor:pointer;font-family:'Roboto',sans-serif;transition:background .12s; }
.combo-opcion:hover { background:var(--gris2);color:var(--blanco); }
.combo-vacio { padding:10px 12px;font-size:12px;color:var(--gris4);font-family:'Roboto',sans-serif; }

/* ── Tabla ────────────────────────────────────────────────── */
.tabla-wrap { background: var(--gris1); border: 1px solid var(--gris2); border-radius: 12px; overflow: hidden; }
.tabla-header { padding: 14px 18px; border-bottom: 1px solid var(--gris2); font-size: 13px; font-weight: 500; color: var(--gris5); }
.tabla { width: 100%; border-collapse: collapse; }
.tabla th { font-size: 10px; font-weight: 600; letter-spacing: .07em; text-transform: uppercase; color: var(--gris4); padding: 10px 16px; text-align: left; border-bottom: 1px solid var(--gris2); white-space: nowrap; }
.tabla td { padding: 13px 16px; font-size: 13px; color: var(--gris5); border-bottom: 1px solid var(--gris2); font-family: 'Roboto', sans-serif; vertical-align: middle; }
.tabla tr:last-child td { border-bottom: none; }
.tabla tr:hover td { background: rgba(255,255,255,.02); }

/* ── Tipo badge ───────────────────────────────────────────── */
.tipo-badge {
  display: inline-flex; align-items: center;
  padding: 3px 9px; border-radius: 20px;
  font-size: 10px; font-weight: 600; letter-spacing: .06em;
  text-transform: uppercase; white-space: nowrap;
}
.tipo-contrato  { background: rgba(101,163,255,.12); color: #65a3ff;       border: 1px solid rgba(101,163,255,.25); }
.tipo-politica  { background: rgba(186,104,200,.12); color: #ba68c8;       border: 1px solid rgba(186,104,200,.25); }
.tipo-protocolo { background: rgba(255,138,101,.12); color: #ff8a65;       border: 1px solid rgba(255,138,101,.25); }
.tipo-circular  { background: rgba(38,198,218,.12);  color: #26c6da;       border: 1px solid rgba(38,198,218,.25); }
.tipo-anexo     { background: rgba(201,168,76,.12);  color: var(--dorado); border: 1px solid rgba(201,168,76,.25); }
.tipo-acta      { background: rgba(76,175,80,.12);   color: var(--exito);  border: 1px solid rgba(76,175,80,.25); }
.tipo-procedimiento { background: rgba(121,134,203,.12); color: #7986cb;      border: 1px solid rgba(121,134,203,.25); }
.tipo-otro      { background: rgba(255,255,255,.07); color: var(--gris5);  border: 1px solid var(--gris2); }

/* ── Drop zone ────────────────────────────────────────────── */
.drop-zone {
  border: 1.5px dashed var(--gris3); border-radius: 9px;
  padding: 28px 16px; text-align: center; cursor: pointer;
  transition: border-color .2s, background .2s;
}
.drop-zone:hover, .drop-zone.dragover {
  border-color: var(--dorado); background: rgba(201,168,76,.04);
}
.drop-zone.has-file {
  border-color: var(--exito); background: rgba(76,175,80,.04);
}

/* ── Modal ────────────────────────────────────────────────── */
.modal-overlay { display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:500;align-items:center;justify-content:center;padding:16px; }
.modal-overlay.open { display:flex; }
.modal-box { background:var(--gris1);border:1px solid var(--gris2);border-radius:14px;width:100%;max-width:540px;max-height:90vh;overflow-y:auto; }
.modal-header { padding:18px 20px;border-bottom:1px solid var(--gris2);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:var(--gris1);z-index:1; }
.modal-header h3 { font-size:15px;font-weight:600;color:var(--blanco); }
.modal-close { background:transparent;border:none;cursor:pointer;color:var(--gris4);padding:4px;border-radius:5px;transition:color .15s,background .15s;display:flex; }
.modal-close:hover { color:var(--blanco);background:var(--gris2); }
.modal-body { padding:20px; }
.modal-footer { padding:14px 20px;border-top:1px solid var(--gris2);display:flex;justify-content:flex-end;gap:8px;position:sticky;bottom:0;background:var(--gris1); }
.form-row { display:grid;grid-template-columns:1fr 1fr;gap:12px; }
@media(max-width:520px) { .form-row { grid-template-columns:1fr; } }
.form-group { margin-bottom:14px; }
.form-group label { display:block;font-size:11px;font-weight:500;letter-spacing:.06em;text-transform:uppercase;color:var(--gris5);margin-bottom:6px; }
.form-group input[type=text], .form-group .form-select {
  width:100%;background:var(--negro);border:1px solid var(--gris2);border-radius:7px;
  padding:10px 12px;font-size:13px;font-family:'Archivo',sans-serif;color:var(--blanco);
  outline:none;transition:border-color .2s;box-sizing:border-box;
}
.form-group input[type=text]:focus, .form-group .form-select:focus { border-color:var(--dorado); }
.form-group input::placeholder { color:var(--gris3); }
.form-select { background:var(--negro)!important;color:var(--blanco)!important;cursor:pointer; }
.form-select option { background:var(--gris1); }
.form-error { background:rgba(226,92,92,.1);border:1px solid rgba(226,92,92,.3);border-radius:7px;padding:9px 12px;font-size:12px;color:var(--error);margin-top:8px;line-height:1.5; }
.accion-btn { background:transparent;border:none;cursor:pointer;padding:5px 8px;border-radius:5px;font-size:12px;font-family:'Archivo',sans-serif;transition:background .15s;display:inline-flex;align-items:center;gap:4px;color:var(--gris5); }
.accion-btn:hover { background:var(--gris2);color:var(--blanco); }

/* ── Toast ────────────────────────────────────────────────── */
.toast { position:fixed;bottom:24px;right:24px;background:var(--gris1);border:1px solid var(--gris2);border-radius:10px;padding:12px 16px;font-size:13px;color:var(--blanco);display:flex;align-items:center;gap:10px;transform:translateY(80px);opacity:0;transition:transform .3s,opacity .3s;z-index:600;font-family:'Roboto',sans-serif;max-width:320px; }
.toast.show { transform:translateY(0);opacity:1; }
/* ── Vista detalle ─────────────────────────────────────────── */
.detalle-header {
  display: flex; align-items: flex-start; gap: 16px;
  margin-bottom: 20px; padding-bottom: 18px;
  border-bottom: 1px solid var(--gris2);
  flex-wrap: wrap;
}
.detalle-titulo {
  font-size: 22px; font-weight: 600; color: var(--blanco);
  font-family: 'Roboto', sans-serif; margin-bottom: 4px;
}
.detalle-meta {
  font-size: 13px; color: var(--gris4);
  font-family: 'Roboto', sans-serif;
  display: flex; gap: 14px; flex-wrap: wrap; align-items: center;
}
.detalle-meta .sep { color: var(--gris3); }

/* ── Cards de versiones ────────────────────────────────────── */
.version-card {
  padding: 18px 20px; border-bottom: 1px solid var(--gris2);
  display: grid; grid-template-columns: auto 1fr auto; gap: 18px; align-items: center;
}
.version-card:last-child { border-bottom: none; }
.version-card:hover { background: rgba(255,255,255,0.015); }

/* Versión eliminada: opaca, sin acciones (salvo restaurar) */
.version-card.eliminada { opacity: .55; background: rgba(226,92,92,.04); }
.version-card.eliminada:hover { background: rgba(226,92,92,.07); }
.version-eliminada-pill {
  display: inline-block; padding: 2px 8px; border-radius: 10px;
  font-size: 9px; font-weight: 600; letter-spacing: .04em;
  background: rgba(226,92,92,.15); color: var(--error);
  border: 1px solid rgba(226,92,92,.3);
  font-family: 'Archivo', sans-serif; text-transform: uppercase;
}

/* Botón pequeño tipo pill para toggles del header */
.filtro-btn {
  background: transparent; border: 1px solid var(--gris2);
  border-radius: 20px; padding: 6px 14px;
  font-size: 12px; color: var(--gris5);
  font-family: 'Roboto', sans-serif;
  cursor: pointer; transition: all .15s; outline: none;
}
.filtro-btn:hover { border-color: var(--dorado); color: var(--blanco); }
.filtro-btn.active { background: var(--dorado); color: var(--negro); border-color: var(--dorado); }

.version-numero {
  display: flex; flex-direction: column; align-items: center;
  min-width: 56px; gap: 4px;
}
.version-numero .num {
  font-size: 18px; font-weight: 600; color: var(--blanco);
  font-family: 'Roboto', sans-serif;
}
.version-vigente-pill {
  display: inline-block; padding: 2px 8px; border-radius: 10px;
  font-size: 9px; font-weight: 600; letter-spacing: .04em;
  background: rgba(166,200,132,.15); color: var(--exito);
  border: 1px solid rgba(166,200,132,.3);
  font-family: 'Archivo', sans-serif; text-transform: uppercase;
}

.version-info-autor {
  font-size: 12px; color: var(--gris5);
  font-family: 'Roboto', sans-serif; margin-bottom: 4px;
}
.version-info-meta {
  font-size: 11px; color: var(--gris4);
  font-family: 'Roboto', sans-serif;
  display: flex; gap: 12px; flex-wrap: wrap; align-items: center;
}

.version-nota {
  margin-top: 8px; padding: 8px 12px;
  background: var(--gris1); border-left: 2px solid var(--gris3);
  border-radius: 4px; font-size: 12px; color: var(--gris5);
  font-family: 'Roboto', sans-serif; line-height: 1.5;
  display: flex; gap: 8px; align-items: flex-start;
}
.version-nota.vacia { color: var(--gris3); font-style: italic; border-left-color: var(--gris2); }
.version-nota .texto-nota { flex: 1; white-space: pre-wrap; word-break: break-word; }
.version-nota .btn-editar-nota {
  background: transparent; border: none; cursor: pointer;
  color: var(--gris4); padding: 2px; line-height: 0;
  transition: color .15s; flex-shrink: 0;
}
.version-nota .btn-editar-nota:hover { color: var(--dorado); }

.version-acciones {
  display: flex; gap: 12px; align-items: center; flex-wrap: wrap;
}

/* Textarea inline para editar nota */
.nota-edit-area {
  width: 100%; box-sizing: border-box;
  background: var(--gris1); border: 1px solid var(--gris2);
  border-radius: 6px; padding: 8px 10px;
  font-size: 12px; color: var(--blanco);
  font-family: 'Roboto', sans-serif;
  resize: vertical; min-height: 60px; outline: none;
  transition: border-color .15s;
}
.nota-edit-area:focus { border-color: var(--dorado); }

/* ── Form inputs (modal subir versión) ─────────────────────── */


  /* Modal subir version: opciones de tipo de cambio (menor/mayor) */
  .tipo-cambio-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
  .tipo-cambio-opt {
    text-align: left; cursor: pointer;
    background: rgba(255,255,255,.03); border: 1px solid var(--gris2);
    border-radius: 10px; padding: 12px 14px;
    transition: border-color .15s, background .15s;
  }
  .tipo-cambio-opt:hover { border-color: var(--gris3); }
  .tipo-cambio-opt.active { border-color: var(--dorado); background: rgba(201,168,76,.08); }
  .tc-num    { font-size: 17px; font-weight: 700; color: var(--dorado); font-family: 'Archivo', sans-serif; margin-bottom: 2px; }
  .tc-titulo { font-size: 13px; font-weight: 600; color: var(--blanco); margin-bottom: 3px; }
  .tc-desc   { font-size: 11.5px; color: var(--gris4); line-height: 1.45; font-family: 'Roboto', sans-serif; }
</style>

<script>
let todosLosDocumentos = [];
let todasLasEmpresas  = [];
let todasLasFranquicias = [];
let rolUsuario        = '';
let miEmpresaId       = null;
let pendingEliminar   = null;
let pendingEliminarVer = null;  // { docId, versionId, vigente }
let mostrarVersionesEliminadas = false;
let empresaFiltroId   = ''; // filtro activo del combobox de empresa (super_admin)
let documentoActivo   = null; // documento padre cargado en la vista detalle
let archivoVersion    = null; // archivo seleccionado para subir nueva versión
let versionesDoc      = [];   // versiones del documento en el detalle (para calcular menor/mayor)
let tipoCambioVersion = 'mayor'; // elección del modal de subir versión

// v2.3 — categorías activas de la empresa actual (para los modales de subir/editar)
let categoriasEmpresa = []; // [{ id, name, description, is_active, empresa_id }]

// v2.3: cats activas de la empresa actualmente filtrada (para el select "Por visibilidad")
let categoriasFiltro = [];

// ── INIT ──────────────────────────────────────────────────────
async function init() {
  try {
    const me = await apiFetch('GET', '/me');
    rolUsuario  = me.rol;
    miEmpresaId = me.empresa_id;

    // Solo super_admin y franquiciante ven filtros y pueden subir
    if (rolUsuario === 'super_admin' || rolUsuario === 'franquiciante') {
      document.getElementById('btn-subir').style.display      = 'flex';
      document.getElementById('filtros-wrap').style.display   = 'flex';
    }

    // Franquiciado y empleado redirigen si no tienen acceso
    if (rolUsuario === 'empleado') {
      window.location.href = 'manuales.php'; return;
    }

    renderThead();

    if (rolUsuario === 'super_admin') {
      // Cargar empresas para el combobox del filtro y el modal de subida
      const empresas = await apiFetch('GET', '/empresas');
      todasLasEmpresas = empresas;
      document.getElementById('grupo-sel-empresa').style.display = 'flex';

      // Select empresa en modal
      const selDocEmp = document.getElementById('doc-empresa');
      empresas.filter(e => e.activa).forEach(e => {
        const opt = document.createElement('option');
        opt.value = e.id; opt.textContent = e.nombre;
        selDocEmp.appendChild(opt);
      });
      document.getElementById('grupo-empresa-doc').style.display = 'block';
    } else {
      // Franquiciante: cargar sus franquicias y sus categorías activas
      await cargarFranquicias(miEmpresaId);
      await cargarCategoriasDeEmpresa(miEmpresaId);
      // v2.3: el franquiciante tiene empresa fija → habilitamos el filtro de cats al inicio
      await poblarFiltroCategorias(miEmpresaId);
    }

    await cargarDocumentos();

  } catch (e) {
    document.getElementById('tabla-body').innerHTML =
      `<tr><td colspan="6"><div class="empty-state">Error al cargar.</div></td></tr>`;
  }
}

async function cargarFranquicias(empresaId) {
  if (!empresaId) return;
  try {
    const franquicias = await apiFetch('GET', '/franquicias');
    todasLasFranquicias = franquicias;

    // Filtro
    const selFiltro = document.getElementById('sel-franquicia');
    franquicias.forEach(f => {
      const opt = document.createElement('option');
      opt.value = f.id; opt.textContent = f.nombre;
      selFiltro.appendChild(opt);
    });

    // Modal
    const selDoc = document.getElementById('doc-franquicia');
    franquicias.forEach(f => {
      const opt = document.createElement('option');
      opt.value = f.id; opt.textContent = f.nombre;
      selDoc.appendChild(opt);
    });
  } catch {}
}

// v2.3 — Helpers para el bloque de categorías visibles en los modales
async function cargarCategoriasDeEmpresa(empresaId) {
  if (!empresaId) { categoriasEmpresa = []; return; }
  try {
    const url = (rolUsuario === 'super_admin')
      ? `/categorias?empresa_id=${empresaId}&activa=1`
      : `/categorias?activa=1`;
    const cats = await apiFetch('GET', url);
    categoriasEmpresa = (cats || []).filter(c => c.is_active);
  } catch {
    categoriasEmpresa = [];
  }
}

function renderListaCategoriasModal(prefix, idsMarcadas) {
  // prefix: 'doc' o 'edit-doc'
  const cont = document.getElementById(`${prefix}-categorias-lista`);
  if (!cont) return;

  if (!categoriasEmpresa.length) {
    cont.innerHTML = `<div style="font-size:12px;color:var(--gris4);font-family:'Roboto',sans-serif;padding:8px 0">
      No hay categorías activas en esta empresa. <a href="${BASE_URL}/categorias.php" style="color:var(--dorado);text-decoration:underline">Crear una</a>.
    </div>`;
    document.getElementById(`${prefix}-categorias-warning`).style.display = 'none';
    return;
  }

  cont.innerHTML = categoriasEmpresa.map(c => `
    <label style="display:flex;align-items:flex-start;gap:8px;padding:6px 8px;cursor:pointer;border-radius:5px;transition:background .12s" onmouseover="this.style.background='var(--gris2)'" onmouseout="this.style.background='transparent'">
      <input type="checkbox" data-cat-id="${c.id}" ${idsMarcadas.has(c.id) ? 'checked' : ''} style="margin:0;margin-top:2px;cursor:pointer;accent-color:var(--dorado);flex-shrink:0" onchange="onCambioCheckboxCategoria('${prefix}')">
      <div style="flex:1;min-width:0">
        <div style="font-size:13px;color:var(--blanco);font-weight:500">${esc(c.name)}</div>
        ${c.description ? `<div style="font-size:11px;color:var(--gris4);margin-top:2px;font-family:'Roboto',sans-serif;line-height:1.4">${esc(c.description)}</div>` : ''}
      </div>
    </label>
  `).join('');

  actualizarBotonSelTodas(prefix);
  actualizarWarningCategorias(prefix);
}

function onToggleVisibilidadCategorias(prefix) {
  const checked = document.getElementById(`${prefix}-visible`).checked;
  document.getElementById(`${prefix}-categorias-wrap`).style.display = checked ? 'block' : 'none';
  if (checked) actualizarWarningCategorias(prefix);
}

function onCambioCheckboxCategoria(prefix) {
  actualizarWarningCategorias(prefix);
  actualizarBotonSelTodas(prefix);
}

function toggleSeleccionarTodasCategorias(prefix) {
  const checkboxes = document.querySelectorAll(`#${prefix}-categorias-lista input[type=checkbox]`);
  if (!checkboxes.length) return;
  const todosMarcados = Array.from(checkboxes).every(cb => cb.checked);
  checkboxes.forEach(cb => { cb.checked = !todosMarcados; });
  actualizarBotonSelTodas(prefix);
  actualizarWarningCategorias(prefix);
}

function actualizarBotonSelTodas(prefix) {
  const checkboxes = document.querySelectorAll(`#${prefix}-categorias-lista input[type=checkbox]`);
  const btn = document.getElementById(`${prefix}-btn-sel-todas`);
  if (!btn) return;
  if (!checkboxes.length) { btn.style.display = 'none'; return; }
  btn.style.display = '';
  const todosMarcados = Array.from(checkboxes).every(cb => cb.checked);
  btn.textContent = todosMarcados ? 'Deseleccionar todos' : 'Seleccionar todos';
}

function actualizarWarningCategorias(prefix) {
  const checkboxes = document.querySelectorAll(`#${prefix}-categorias-lista input[type=checkbox]`);
  const algunaMarcada = Array.from(checkboxes).some(cb => cb.checked);
  const warn = document.getElementById(`${prefix}-categorias-warning`);
  if (warn) warn.style.display = (checkboxes.length && !algunaMarcada) ? 'block' : 'none';
}

function leerCategoriasSeleccionadas(prefix) {
  const checkboxes = document.querySelectorAll(`#${prefix}-categorias-lista input[type=checkbox]:checked`);
  return Array.from(checkboxes).map(cb => parseInt(cb.dataset.catId, 10));
}

async function onEmpresaChange() {
  const empresaId = empresaFiltroId;

  // Actualizar franquicias del filtro
  const selFiltro = document.getElementById('sel-franquicia');
  selFiltro.innerHTML = '<option value="">Todas las franquicias</option><option value="global">Solo globales</option>';

  if (empresaId) {
    try {
      const franquicias = await apiFetch('GET', `/franquicias?empresa_id=${empresaId}`);
      todasLasFranquicias = franquicias;
      franquicias.forEach(f => {
        const opt = document.createElement('option');
        opt.value = f.id; opt.textContent = f.nombre;
        selFiltro.appendChild(opt);
      });
    } catch {}
  }

  // v2.3: refrescar filtro de categorías según la empresa elegida.
  // Sin empresa → select queda deshabilitado (caso super_admin).
  await poblarFiltroCategorias(empresaId || null);

  await cargarDocumentos();
}

// ── AUTOCOMPLETADO DE EMPRESA (combobox) ──────────────────────
function filtrarOpcionesEmpresa() {
  const input = document.getElementById('inp-empresa');
  const cont  = document.getElementById('empresa-opciones');
  const texto = input.value.toLowerCase().trim();

  // Si vació el campo y había una empresa elegida, vuelve a "todas".
  if (texto === '' && empresaFiltroId !== '') {
    empresaFiltroId = '';
    document.getElementById('empresa-clear').style.display = 'none';
    onEmpresaChange();
  }

  // Solo mostramos empresas activas en el autocomplete. todasLasEmpresas sigue
  // conteniendo también las suspendidas para lookups por ID.
  const coincidencias = todasLasEmpresas.filter(e => e.activa && e.nombre.toLowerCase().includes(texto));

  if (!coincidencias.length) {
    cont.innerHTML = `<div class="combo-vacio">Sin coincidencias</div>`;
    cont.style.display = 'block';
    return;
  }

  cont.innerHTML = coincidencias.map(e => `
    <div class="combo-opcion" onmousedown="seleccionarEmpresa(${e.id}, '${esc(e.nombre).replace(/'/g, "\\'")}')">
      ${esc(e.nombre)}${e.activa ? '' : ' <span style="color:var(--gris4)">(suspendida)</span>'}
    </div>`).join('');
  cont.style.display = 'block';
}

function seleccionarEmpresa(id, nombre) {
  empresaFiltroId = String(id);
  document.getElementById('inp-empresa').value = nombre;
  document.getElementById('empresa-clear').style.display = 'block';
  document.getElementById('empresa-opciones').style.display = 'none';
  onEmpresaChange();
}

function limpiarEmpresa() {
  empresaFiltroId = '';
  document.getElementById('inp-empresa').value = '';
  document.getElementById('empresa-clear').style.display = 'none';
  document.getElementById('empresa-opciones').style.display = 'none';
  onEmpresaChange();
}

async function onEmpresaDocChange() {
  const empresaId = document.getElementById('doc-empresa').value;
  const selDoc    = document.getElementById('doc-franquicia');
  selDoc.innerHTML = '<option value="">Toda la empresa (global)</option>';
  if (!empresaId) {
    categoriasEmpresa = [];
    renderListaCategoriasModal('doc', new Set());
    return;
  }
  try {
    const franquicias = await apiFetch('GET', `/franquicias?empresa_id=${empresaId}`);
    franquicias.forEach(f => {
      const opt = document.createElement('option');
      opt.value = f.id; opt.textContent = f.nombre;
      selDoc.appendChild(opt);
    });
  } catch {}
  // Cargar también las categorías activas de esta empresa para el bloque de visibilidad
  await cargarCategoriasDeEmpresa(empresaId);
  renderListaCategoriasModal('doc', new Set());
}

async function cargarDocumentos() {
  document.getElementById('tabla-body').innerHTML =
    `<tr><td colspan="6"><div class="empty-state"><div class="spinner" style="display:block;margin:0 auto 8px"></div>Cargando...</div></td></tr>`;

  try {
    let url = '/documentos';
    if (rolUsuario === 'super_admin') {
      if (empresaFiltroId) url += `?empresa_id=${empresaFiltroId}`;
    }
    const docs = await apiFetch('GET', url);
    todosLosDocumentos = docs;
    aplicarFiltros();
  } catch (e) {
    document.getElementById('tabla-body').innerHTML =
      `<tr><td colspan="6"><div class="empty-state">Error al cargar los documentos.</div></td></tr>`;
  }
}

// ── FILTROS ───────────────────────────────────────────────────
function aplicarFiltros() {
  let lista = [...todosLosDocumentos];
  const tipo       = document.getElementById('sel-tipo').value;
  const categoria  = document.getElementById('sel-categoria').value;
  const franquicia = document.getElementById('sel-franquicia').value;
  const texto      = document.getElementById('inp-buscar').value.toLowerCase().trim();

  if (tipo)      lista = lista.filter(d => d.tipo === tipo);

  // v2.3: filtro por categoría asignada al documento
  if (categoria === '__sin_asignar__') {
    lista = lista.filter(d => !d.categorias || d.categorias.length === 0);
  } else if (categoria) {
    const catId = Number(categoria);
    lista = lista.filter(d => (d.categorias || []).some(c => Number(c.id) === catId));
  }

  if (franquicia === 'global') lista = lista.filter(d => !d.franquicia_id);
  else if (franquicia) lista = lista.filter(d => String(d.franquicia_id) === franquicia);
  if (texto)     lista = lista.filter(d => d.titulo.toLowerCase().includes(texto));

  renderTabla(lista);
  document.getElementById('tabla-titulo').textContent = `${lista.length} documento(s)`;
}

// v2.3: pobla el select "Por visibilidad" con las cats activas de una empresa.
// Si empresaId es null/falsy, deshabilita el select (caso super_admin sin empresa elegida).
async function poblarFiltroCategorias(empresaId) {
  const sel = document.getElementById('sel-categoria');
  // Reseteamos siempre el valor seleccionado al cambiar de contexto
  sel.value = '';

  if (!empresaId) {
    sel.innerHTML = '<option value="">Todas las categorías</option>';
    sel.disabled  = true;
    sel.title     = 'Elegí una empresa para habilitar este filtro';
    categoriasFiltro = [];
    return;
  }

  try {
    // super_admin pasa empresa_id explícito; franquiciante lo infiere el server
    const url = (rolUsuario === 'super_admin')
      ? `/categorias?empresa_id=${empresaId}&activa=1`
      : `/categorias?activa=1`;
    const cats = await apiFetch('GET', url);
    categoriasFiltro = (cats || []).filter(c => c.is_active);
  } catch {
    categoriasFiltro = [];
  }

  const opciones = ['<option value="">Todas las categorías</option>',
                    '<option value="__sin_asignar__">Sin asignar</option>'];
  if (categoriasFiltro.length) {
    opciones.push('<option disabled>──────────</option>');
    categoriasFiltro.forEach(c => {
      opciones.push(`<option value="${c.id}">${esc(c.name)}</option>`);
    });
  }
  sel.innerHTML = opciones.join('');
  sel.disabled  = false;
  sel.title     = '';
}

// ── RENDER ────────────────────────────────────────────────────
function renderThead() {
  const cols = rolUsuario === 'super_admin'
    ? ['Empresa', 'Título', 'Tipo', 'Franquicia', 'Visibilidad', 'Fecha', 'Acciones']
    : rolUsuario === 'franquiciante'
    ? ['Título', 'Tipo', 'Franquicia', 'Visibilidad', 'Fecha', 'Acciones']
    : ['Título', 'Tipo', 'Fecha', 'Acciones'];  // franquiciado: vista simplificada
  document.getElementById('tabla-head').innerHTML =
    `<tr>${cols.map(c => `<th>${c}</th>`).join('')}</tr>`;
}



function renderTabla(lista) {
  const tbody = document.getElementById('tabla-body');
  const cols = rolUsuario === 'super_admin' ? 7 : rolUsuario === 'franquiciante' ? 6 : 4;

  if (!lista.length) {
    tbody.innerHTML = `<tr><td colspan="${cols}"><div class="empty-state">No hay documentos disponibles.</div></td></tr>`;
    return;
  }

  const tipoBadge = (tipo) => {
    const map = {
      contrato:  'tipo-contrato',
      politica:  'tipo-politica',
      protocolo: 'tipo-protocolo',
      circular:  'tipo-circular',
      anexo:     'tipo-anexo',
      acta:      'tipo-acta',
      procedimiento: 'tipo-procedimiento',
      otro:      'tipo-otro',
    };
    return `<span class="tipo-badge ${map[tipo] || 'tipo-otro'}">${tipo}</span>`;
  };

  const tamano = (bytes) => {
    if (!bytes) return '—';
    if (bytes < 1024)    return `${bytes} B`;
    if (bytes < 1048576) return `${(bytes/1024).toFixed(0)} KB`;
    return `${(bytes/1048576).toFixed(1)} MB`;
  };

  tbody.innerHTML = lista.map(d => {
    // versionActiva viene del backend como HasMany → array.
    // Tomamos la primera (debería haber sólo una con es_activa=1).
    const va = Array.isArray(d.version_activa) ? d.version_activa[0] : d.version_activa;
    const mime  = va?.mime_type     || '';
    const bytes = va?.tamano_bytes  || 0;
    const vNum  = va ? fmtVer(va) : null;

    const empresaCol = rolUsuario === 'super_admin'
      ? `<td style="font-size:12px;color:var(--gris4)">${esc(d.empresa?.nombre || '—')}</td>` : '';

    const franquiciaCol = (rolUsuario === 'super_admin' || rolUsuario === 'franquiciante')
      ? `<td>${d.franquicia?.nombre
          ? `<span style="font-size:11px;color:var(--gris5)">${esc(d.franquicia.nombre)}</span>`
          : `<span style="font-size:11px;color:var(--gris3);font-style:italic">Global</span>`
        }</td>` : '';

    const visibilidadCol = (rolUsuario === 'super_admin' || rolUsuario === 'franquiciante')
      ? `<td>${d.visible_franquiciado
          ? `<span style="color:var(--exito);font-size:11px">● Visible</span>`
          : `<span style="color:var(--gris3);font-size:11px">● Oculto</span>`
        }</td>` : '';

    const esPdf = (mime === 'application/pdf') || /\.pdf$/i.test(va?.archivo_url || '');

    // Estado de eliminación (solo relevante para super_admin: a los demás no les llega un eliminado)
    const eliminado          = !!d.deleted_at;
    const eliminadoPorFranq  = eliminado && d.deleted_by?.rol === 'franquiciante';
    const badgeEliminado     = eliminado
      ? `<span style="display:inline-block;margin-left:8px;padding:2px 8px;border-radius:10px;font-size:10px;font-family:'Archivo',sans-serif;background:rgba(226,92,92,.12);color:var(--error);border:1px solid rgba(226,92,92,.3);vertical-align:middle">Eliminado${eliminadoPorFranq ? ' por franquiciante' : ''}</span>`
      : '';

    // El título de la fila es clickeable: lleva a la vista detalle
    const tituloHTML = `
      <div style="color:var(--blanco);font-weight:500;cursor:pointer;display:inline-flex;align-items:center;gap:6px"
           onclick="verDocumento(${d.id})" title="Ver versiones del documento">
        ${esc(d.titulo)}
        ${vNum ? `<span style="font-size:10px;color:var(--gris4);font-weight:400">v${vNum}</span>` : ''}
      </div>${badgeEliminado}
      <div style="font-size:11px;color:var(--gris4);margin-top:2px">${esc(mime)}${bytes ? ' · ' + tamano(bytes) : ''}</div>`;

    return `<tr>
      ${empresaCol}
      <td>${tituloHTML}</td>
      <td>${tipoBadge(d.tipo)}</td>
      ${franquiciaCol}
      ${visibilidadCol}
      <td style="font-size:12px;color:var(--gris4);white-space:nowrap">${formatFecha(d.created_at)}</td>
      <td>
        <div style="display:flex;gap:14px;align-items:center;flex-wrap:wrap">

          ${(rolUsuario === 'super_admin' || rolUsuario === 'franquiciante') ? `
          <a href="#" onclick="event.preventDefault(); verDocumento(${d.id})"
            class="accion-btn"
            style="color:var(--gris5)">
            Versiones
          </a>` : ''}

          ${esPdf ? `
          <a href="#"
            onclick="event.preventDefault(); previsualizarDocumento(${d.id})"
            class="accion-btn"
            style="color:var(--gris5)">
            Vista previa
          </a>` : ''}

          <a href="#"
            onclick="event.preventDefault(); descargarDocumento(${d.id})"
            class="accion-btn"
            style="color:var(--dorado)">
            Descargar
          </a>

          ${(rolUsuario === 'super_admin' || rolUsuario === 'franquiciante') && !eliminado
            ? `
              <a href="#"
                onclick="event.preventDefault(); abrirModalEditarDoc(${d.id})"
                class="accion-btn"
                style="color:var(--gris5)">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Editar
              </a>` : ''}

          ${(rolUsuario === 'super_admin' || rolUsuario === 'franquiciante')
            ? (
                eliminado
                  ? `
                  <a href="#"
                    onclick="event.preventDefault(); restaurarDocumento(${d.id}, '${esc(d.titulo)}')"
                    class="accion-btn"
                    style="color:var(--dorado)">
                    Restaurar
                  </a>`
                  : `
                  <a href="#"
                    onclick="event.preventDefault(); abrirModalEliminar(${d.id}, '${esc(d.titulo)}')"
                    class="accion-btn"
                    style="color:var(--gris5)">
                    Eliminar
                  </a>`
              )
            : ''}

        </div>
      </td>
    </tr>`;
  }).join('');
}

// ── MODAL SUBIR ───────────────────────────────────────────────
function abrirModalSubir() {
  document.getElementById('doc-titulo').value      = '';
  document.getElementById('doc-tipo').value        = '';
  document.getElementById('doc-franquicia').value  = '';
  document.getElementById('doc-visible').checked   = false;
  document.getElementById('doc-archivo').value     = '';
  document.getElementById('doc-error').style.display = 'none';
  resetDropZone();

  // Resetear bloque de categorías: oculto + lista limpia
  document.getElementById('doc-categorias-wrap').style.display = 'none';

  // Si hay empresa seleccionada en filtro y es super_admin, preseleccionar
  if (rolUsuario === 'super_admin') {
    const empId = empresaFiltroId;
    document.getElementById('doc-empresa').value = empId || '';
    if (empId) onEmpresaDocChange(); // esto carga categorías + render lista
    else renderListaCategoriasModal('doc', new Set()); // lista vacía hasta que elija empresa
  } else {
    // Franquiciante: las categorías ya están cargadas en init, solo render con set vacío
    renderListaCategoriasModal('doc', new Set());
  }

  document.getElementById('modal-subir').classList.add('open');
  setTimeout(() => document.getElementById('doc-titulo').focus(), 100);
}

function cerrarModalSubir() {
  document.getElementById('modal-subir').classList.remove('open');
}

function resetDropZone() {
  const dz = document.getElementById('drop-zone');
  dz.classList.remove('has-file');
  document.getElementById('drop-zone-content').innerHTML = `
    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color:var(--gris4);margin-bottom:8px"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
    <div style="font-size:13px;color:var(--gris5)">Hacé clic o arrastrá un archivo</div>
    <div style="font-size:11px;color:var(--gris4);margin-top:4px">PDF, DOC o DOCX — máximo 20 MB</div>`;
}

function onArchivoSeleccionado(input) {
  const file = input.files[0];
  if (!file) { resetDropZone(); return; }
  const dz = document.getElementById('drop-zone');
  dz.classList.add('has-file');
  document.getElementById('drop-zone-content').innerHTML = `
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--exito)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom:6px"><polyline points="20 6 9 17 4 12"/></svg>
    <div style="font-size:13px;color:var(--blanco);font-weight:500">${esc(file.name)}</div>
    <div style="font-size:11px;color:var(--gris4);margin-top:4px">${(file.size/1024).toFixed(0)} KB · ${file.type || 'archivo'}</div>`;
}

// Drag & drop
const dropZone = document.getElementById('drop-zone');
dropZone.addEventListener('dragover',  e => { e.preventDefault(); dropZone.classList.add('dragover'); });
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));
dropZone.addEventListener('drop', e => {
  e.preventDefault(); dropZone.classList.remove('dragover');
  const file = e.dataTransfer.files[0];
  if (file) {
    const input = document.getElementById('doc-archivo');
    const dt = new DataTransfer(); dt.items.add(file);
    input.files = dt.files;
    onArchivoSeleccionado(input);
  }
});

async function subirDocumento() {
  const titulo     = document.getElementById('doc-titulo').value.trim();
  const tipo       = document.getElementById('doc-tipo').value;
  const franqId    = document.getElementById('doc-franquicia').value;
  const visible    = document.getElementById('doc-visible').checked;
  const empresaId  = document.getElementById('doc-empresa')?.value || '';
  const archivo    = document.getElementById('doc-archivo').files[0];
  const errEl      = document.getElementById('doc-error');
  const btn        = document.getElementById('btn-confirmar-subir');

  errEl.style.display = 'none';

  if (!titulo)  { errEl.textContent = 'El título es obligatorio.'; errEl.style.display = 'block'; return; }
  if (!tipo)    { errEl.textContent = 'Seleccioná un tipo.';       errEl.style.display = 'block'; return; }
  if (!archivo) { errEl.textContent = 'Seleccioná un archivo.';    errEl.style.display = 'block'; return; }
  if (rolUsuario === 'super_admin' && !empresaId) {
    errEl.textContent = 'Seleccioná una empresa.'; errEl.style.display = 'block'; return;
  }
  if (archivo.size > 20 * 1024 * 1024) {
    errEl.textContent = 'El archivo supera los 20 MB.'; errEl.style.display = 'block'; return;
  }

  btn.disabled = true; btn.textContent = 'Subiendo...';

  try {
    const form = new FormData();
    form.append('titulo',               titulo);
    form.append('tipo',                 tipo);
    form.append('visible_franquiciado', visible ? '1' : '0');
    form.append('archivo',             archivo);
    if (franqId)   form.append('franquicia_id', franqId);
    if (rolUsuario === 'super_admin' && empresaId) form.append('empresa_id', empresaId);

    // apiFetch no sirve para multipart, usamos fetch directo
    const res = await fetchMultipart('/documentos', form);

    // v2.3: si el toggle de visibilidad está ON, sync de categorías visibles.
    // Si está OFF no tocamos nada (las categorías quedan en su estado por defecto vacío).
    if (visible) {
      const catsSeleccionadas = leerCategoriasSeleccionadas('doc');
      try {
        await apiFetch('PUT', `/documentos/${res.id}/categorias`, { category_ids: catsSeleccionadas });
      } catch (errCat) {
        // Si falla el sync, el documento ya está creado: avisamos pero no abortamos.
        mostrarToast('Documento subido, pero falló la asignación de categorías. Editalo para reintentar.', 'error');
        todosLosDocumentos.unshift(res);
        aplicarFiltros();
        cerrarModalSubir();
        return;
      }
    }

    todosLosDocumentos.unshift(res);
    aplicarFiltros();
    cerrarModalSubir();
    mostrarToast('Documento subido correctamente.', 'exito');
  } catch (e) {
    const msg = e.data?.errors
      ? Object.values(e.data.errors).flat().join(' ')
      : e.data?.message || 'Error al subir el documento.';
    errEl.textContent = msg; errEl.style.display = 'block';
  } finally {
    btn.disabled = false;
    btn.innerHTML = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg> Subir documento`;
  }
}

// fetch multipart (no usa apiFetch porque necesita FormData sin Content-Type)
async function fetchMultipart(endpoint, formData) {
  const res = await fetch(API + endpoint, {
    method: 'POST',
    credentials: 'include',
    body: formData,
  });
  const data = await res.json();
  if (!res.ok) { const err = new Error(); err.data = data; throw err; }
  return data;
}

// ── DESCARGA AUTENTICADA ──────────────────────────────────────
// Pasa por el endpoint protegido (valida rol + empresa) y recibe un stream.
// Se maneja como blob, asi funciona igual en disco local y en S3.
async function descargarDocumento(id) {
  const doc = todosLosDocumentos.find(d => d.id === id);
  try {
    const res = await fetch(API + '/documentos/' + id + '/descargar', {
      credentials: 'include',
    });

    if (!res.ok) {
      mostrarToast('No se pudo descargar el documento.', 'error');
      return;
    }

    const blob = await res.blob();

    // Nombre del archivo: lo toma del header del backend, o lo arma desde el titulo
    let filename = 'documento';
    const cd = res.headers.get('Content-Disposition');
    if (cd && cd.includes('filename=')) {
      filename = decodeURIComponent(cd.split('filename=')[1].replace(/["';]/g, '').trim());
    } else if (doc) {
      const ext = (doc.archivo_url || '').split('.').pop();
      filename = doc.titulo + (ext ? '.' + ext : '');
    }

    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    a.remove();
    window.URL.revokeObjectURL(url);
  } catch (e) {
    mostrarToast('Error al descargar el documento.', 'error');
  }
}

async function previsualizarDocumento(id) {
  // Abrimos la pestaña en el mismo gesto del click para que no la bloquee el navegador.
  const win = window.open('', '_blank');
  if (win) win.document.write('<p style="font-family:sans-serif;color:#555;padding:24px">Cargando vista previa...</p>');
  try {
    const res = await fetch(API + '/documentos/' + id + '/preview', { credentials: 'include' });
    if (!res.ok) {
      if (win) win.close();
      mostrarToast('No se pudo abrir la vista previa.', 'error');
      return;
    }
    const blob = await res.blob();
    const url  = window.URL.createObjectURL(blob);
    if (win) { win.location.href = url; } else { window.open(url, '_blank'); }
    // Liberamos el blob después de un rato (ya quedó cargado en el visor).
    setTimeout(() => window.URL.revokeObjectURL(url), 60000);
  } catch (e) {
    if (win) win.close();
    mostrarToast('Error al abrir la vista previa.', 'error');
  }
}

// ── MODAL ELIMINAR DOCUMENTO ──────────────────────────────────
function abrirModalEliminar(id, titulo) {
  pendingEliminar = id;
  document.getElementById('eliminar-msg').textContent = `¿Eliminar "${titulo}"? Dejará de ser visible para los Socios comerciales y empleados. El super_admin podrá restaurarlo si fue un error.`;
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
    await apiFetch('DELETE', `/documentos/${pendingEliminar}`);
    mostrarToast('Documento eliminado correctamente.', 'exito');
    cerrarModalEliminar();
    await cargarDocumentos();
  } catch (e) {
    document.getElementById('eliminar-error').textContent = e.data?.message || 'Error al eliminar.';
    document.getElementById('eliminar-error').style.display = 'block';
    btn.disabled = false; btn.textContent = 'Eliminar';
  }
}

// ── RESTAURAR DOCUMENTO (solo super_admin) ────────────────────
async function restaurarDocumento(id, titulo) {
  try {
    await apiFetch('POST', `/documentos/${id}/restore`);
    mostrarToast(`"${titulo}" restaurado correctamente.`, 'exito');
    await cargarDocumentos();
  } catch (e) {
    mostrarToast(e.data?.message || 'Error al restaurar.', 'error');
  }
}

// ══════════════════════════════════════════════════
//   EDITAR DATOS DEL DOCUMENTO PADRE
// ══════════════════════════════════════════════════

let documentoEditandoId = null;

async function abrirModalEditarDoc(id) {
  const doc = todosLosDocumentos.find(d => d.id === id);
  if (!doc) {
    mostrarToast('Documento no encontrado.', 'error');
    return;
  }
  documentoEditandoId = id;

  // Precargar campos
  document.getElementById('edit-doc-titulo').value   = doc.titulo || '';
  document.getElementById('edit-doc-tipo').value     = doc.tipo || 'contrato';
  document.getElementById('edit-doc-visible').checked = !!doc.visible_franquiciado;
  document.getElementById('edit-doc-error').textContent = '';
  document.getElementById('edit-doc-error').style.display = 'none';

  // Cargar franquicias de la empresa del documento (no es editable la empresa)
  const sel = document.getElementById('edit-doc-franquicia');
  sel.innerHTML = '<option value="">Toda la empresa (global)</option>';
  try {
    const franquicias = await apiFetch('GET', `/franquicias?empresa_id=${doc.empresa_id}`);
    franquicias.forEach(f => {
      const opt = document.createElement('option');
      opt.value = f.id; opt.textContent = f.nombre;
      sel.appendChild(opt);
    });
    // Preseleccionar la actual
    sel.value = doc.franquicia_id ? String(doc.franquicia_id) : '';
  } catch {
    // Si falla la carga de franquicias, igual mostramos el modal para poder editar título/tipo/visibilidad
  }

  // v2.3: cargar categorías de la empresa del doc + las asignadas actualmente
  await cargarCategoriasDeEmpresa(doc.empresa_id);
  let idsAsignadas = new Set();
  try {
    const asignadas = await apiFetch('GET', `/documentos/${id}/categorias`);
    idsAsignadas = new Set((asignadas || []).map(c => c.id));
  } catch {
    // Si el endpoint falla (ej. no existe), seguimos con set vacío — el usuario puede asignar de cero.
  }
  renderListaCategoriasModal('edit-doc', idsAsignadas);
  // Mostrar/ocultar wrap según el toggle actual
  document.getElementById('edit-doc-categorias-wrap').style.display =
    document.getElementById('edit-doc-visible').checked ? 'block' : 'none';

  document.getElementById('modal-editar-doc').classList.add('open');
  setTimeout(() => document.getElementById('edit-doc-titulo').focus(), 100);
}

function cerrarModalEditarDoc() {
  document.getElementById('modal-editar-doc').classList.remove('open');
  documentoEditandoId = null;
}

async function guardarEdicionDocumento() {
  if (!documentoEditandoId) return;
  const btn    = document.getElementById('btn-guardar-doc');
  const errBox = document.getElementById('edit-doc-error');
  errBox.style.display = 'none';

  const titulo    = document.getElementById('edit-doc-titulo').value.trim();
  const tipo      = document.getElementById('edit-doc-tipo').value;
  const franqId   = document.getElementById('edit-doc-franquicia').value;
  const visible   = document.getElementById('edit-doc-visible').checked;

  if (!titulo) {
    errBox.textContent = 'El título es obligatorio.';
    errBox.style.display = 'block';
    return;
  }
  if (!tipo) {
    errBox.textContent = 'El tipo es obligatorio.';
    errBox.style.display = 'block';
    return;
  }

  const body = {
    titulo,
    tipo,
    visible_franquiciado: visible,
    franquicia_id: franqId ? parseInt(franqId, 10) : null,
  };

  btn.disabled = true; btn.textContent = 'Guardando...';
  try {
    const updated = await apiFetch('PUT', `/documentos/${documentoEditandoId}`, body);

    // v2.3: sync de categorías si el toggle está ON.
    // Si está OFF, NO tocamos las cats: preservamos la selección anterior en DB
    // para que el usuario la recupere si vuelve a activarlo.
    if (visible) {
      const catsSeleccionadas = leerCategoriasSeleccionadas('edit-doc');
      try {
        await apiFetch('PUT', `/documentos/${documentoEditandoId}/categorias`, { category_ids: catsSeleccionadas });
      } catch (errCat) {
        mostrarToast('Cambios guardados, pero falló la sincronización de categorías.', 'error');
      }
    }

    mostrarToast('Documento actualizado.', 'exito');
    cerrarModalEditarDoc();

    // Refrescar la lista en memoria + UI
    const url = '/documentos' + (empresaFiltroId ? `?empresa_id=${empresaFiltroId}` : '');
    const docs = await apiFetch('GET', url);
    todosLosDocumentos = docs;
    renderTabla(docs);

    // Si estoy en vista detalle de este documento, actualizar el header
    if (documentoActivo && documentoActivo.id === updated.id) {
      const fresh = docs.find(d => d.id === updated.id);
      if (fresh) {
        documentoActivo = fresh;
        // Re-pintar header de detalle si está visible
        if (document.getElementById('vista-detalle').style.display !== 'none') {
          verDocumento(fresh.id);
        }
      }
    }
  } catch (e) {
    errBox.textContent = e.data?.message || e.data?.error || 'Error al guardar los cambios.';
    errBox.style.display = 'block';
  } finally {
    btn.disabled = false; btn.textContent = 'Guardar cambios';
  }
}

// ══════════════════════════════════════════════════
//   VISTA DETALLE — versiones del documento
// ══════════════════════════════════════════════════

async function verDocumento(id) {
  // Buscar el documento padre en la lista ya cargada
  const doc = todosLosDocumentos.find(d => d.id === id);
  if (!doc) {
    mostrarToast('Documento no encontrado.', 'error');
    return;
  }
  documentoActivo = doc;

  // Renderizar header del documento
  const va     = Array.isArray(doc.version_activa) ? doc.version_activa[0] : doc.version_activa;
  const vNum   = va ? fmtVer(va) : null;
  const tipo   = doc.tipo ? doc.tipo.charAt(0).toUpperCase() + doc.tipo.slice(1) : '—';
  const empOj  = doc.empresa?.nombre ? `<span>${esc(doc.empresa.nombre)}</span><span class="sep">·</span>` : '';
  const franq  = doc.franquicia?.nombre
    ? `<span>${esc(doc.franquicia.nombre)}</span>`
    : `<span style="font-style:italic">Global</span>`;
  const visib  = doc.visible_franquiciado
    ? `<span style="color:var(--exito)">● Visible a Socios comerciales</span>`
    : `<span style="color:var(--gris3)">● Oculto para Socios comerciales</span>`;

  document.getElementById('detalle-titulo').textContent = doc.titulo;
  document.getElementById('detalle-meta').innerHTML = `
    <span>${tipo}</span><span class="sep">·</span>
    ${empOj}
    ${franq}<span class="sep">·</span>
    ${visib}
    ${vNum ? `<span class="sep">·</span><span>Vigente: v${vNum}</span>` : ''}
  `;

  // Mostrar botón "Subir nueva versión" para super_admin / franquiciante,
  // siempre y cuando el documento no esté eliminado.
  const puedeSubir = (rolUsuario === 'super_admin' || rolUsuario === 'franquiciante') && !doc.deleted_at;
  document.getElementById('btn-subir-version').style.display = puedeSubir ? 'inline-flex' : 'none';

  // Cambiar de vista
  document.getElementById('vista-lista').style.display    = 'none';
  document.getElementById('vista-detalle').style.display  = 'block';
  document.getElementById('page-title').textContent       = 'Versiones del documento';
  document.getElementById('page-sub').textContent         = doc.titulo;
  document.getElementById('btn-subir').style.display      = 'none'; // ocultar "Subir documento" en detalle

  // Cargar versiones
  await cargarVersiones(id);
}

function volverALista() {
  documentoActivo = null;
  document.getElementById('vista-detalle').style.display = 'none';
  document.getElementById('vista-lista').style.display   = '';
  document.getElementById('page-title').textContent      = 'Documentos';
  document.getElementById('page-sub').textContent        = 'Repositorio de documentos operativos';
  // Reponer botón "Subir documento" según rol
  if (rolUsuario === 'super_admin' || rolUsuario === 'franquiciante') {
    document.getElementById('btn-subir').style.display = 'inline-flex';
  }
}

async function cargarVersiones(docId) {
  const cont = document.getElementById('versiones-lista');
  cont.innerHTML = `<div class="empty-state" style="padding:24px"><div class="spinner" style="display:block;margin:0 auto 8px"></div>Cargando versiones...</div>`;

  // Mostrar el toggle "Mostrar eliminadas" solo para super_admin
  const btnToggle = document.getElementById('btn-mostrar-eliminadas-ver');
  if (btnToggle) btnToggle.style.display = (rolUsuario === 'super_admin') ? 'inline-flex' : 'none';

  try {
    const url = mostrarVersionesEliminadas
      ? `/documentos/${docId}/versiones?include_deleted=1`
      : `/documentos/${docId}/versiones`;
    const versiones = await apiFetch('GET', url);
    versionesDoc = versiones;
    const activas = versiones.filter(v => !v.deleted_at).length;
    const elim    = versiones.length - activas;
    document.getElementById('versiones-titulo').textContent =
      mostrarVersionesEliminadas && elim > 0
        ? `${activas} versión(es) activa(s) + ${elim} eliminada(s)`
        : `${versiones.length} versión(es) en el historial`;
    renderVersiones(versiones);
  } catch (e) {
    cont.innerHTML = `<div class="empty-state" style="padding:24px;color:var(--error)">Error al cargar las versiones.</div>`;
  }
}

// Toggle para mostrar/ocultar versiones eliminadas (solo super_admin)
async function toggleMostrarEliminadasVer(btn) {
  if (!documentoActivo) return;
  mostrarVersionesEliminadas = !mostrarVersionesEliminadas;
  btn.classList.toggle('active', mostrarVersionesEliminadas);
  await cargarVersiones(documentoActivo.id);
}

function renderVersiones(versiones) {
  const cont = document.getElementById('versiones-lista');
  if (!versiones.length) {
    cont.innerHTML = `<div class="empty-state" style="padding:24px">Este documento no tiene versiones.</div>`;
    return;
  }

  const tamano = (bytes) => {
    if (!bytes) return '—';
    if (bytes < 1024)    return `${bytes} B`;
    if (bytes < 1048576) return `${(bytes/1024).toFixed(0)} KB`;
    return `${(bytes/1048576).toFixed(1)} MB`;
  };

  const fechaHora = (str) => {
    if (!str) return '—';
    const d = new Date(str);
    return d.toLocaleString('es-AR', { day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit' });
  };

  // Nombre legible del autor según el perfil cargado
  const nombreAutor = (rel) => {
    const user = rel || null;
    const p = user?.system_admin || user?.super_admin || user?.franchise_staff;
    if (p?.nombre) return `${p.nombre} ${p.apellido}`;
    return user?.email || '—';
  };

  // Cuántas versiones disponibles (no eliminadas) hay
  const disponibles = versiones.filter(v => !v.deleted_at).length;

  // ¿El usuario actual tiene permisos para eliminar/restaurar versiones?
  const puedeAdministrar = (rolUsuario === 'super_admin' || rolUsuario === 'franquiciante') && !documentoActivo.deleted_at;

  cont.innerHTML = versiones.map(v => {
    const esEliminada = !!v.deleted_at;
    const vigente     = !!v.es_activa && !esEliminada;
    const esPdf       = (v.mime_type === 'application/pdf') || /\.pdf$/i.test(v.archivo_url || '');

    // ── VERSIÓN ELIMINADA: card opaca, badge, sin acciones de archivo, solo restaurar ──
    if (esEliminada) {
      const autorBorrado = nombreAutor(v.deleted_by);
      return `<div class="version-card eliminada">
        <div class="version-numero">
          <div class="num">v${fmtVer(v)}</div>
          <div class="version-eliminada-pill">Eliminada</div>
        </div>
        <div>
          <div class="version-info-autor">Eliminada por <strong style="color:var(--blanco)">${esc(autorBorrado)}</strong></div>
          <div class="version-info-meta">
            <span>${fechaHora(v.deleted_at)}</span>
            <span>·</span>
            <span>${esc(v.mime_type || '')}</span>
            <span>·</span>
            <span>${tamano(v.tamano_bytes)}</span>
          </div>
          ${v.nota ? `<div class="version-nota" style="margin-top:8px"><div class="texto-nota">${esc(v.nota)}</div></div>` : ''}
        </div>
        <div class="version-acciones">
          ${puedeAdministrar ? `
          <a href="#" onclick="event.preventDefault(); restaurarVersion(${documentoActivo.id}, ${v.id}, 'v${fmtVer(v)}')" class="accion-btn" style="color:var(--dorado)">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
            Restaurar
          </a>` : ''}
        </div>
      </div>`;
    }

    // ── VERSIÓN ACTIVA O HISTÓRICA NO ELIMINADA: render normal ──
    const notaHTML = v.nota
      ? `<div class="version-nota" id="nota-${v.id}">
          <div class="texto-nota">${esc(v.nota)}</div>
          <button class="btn-editar-nota" onclick="editarNotaVersion(${v.id})" title="Editar nota">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
          </button>
        </div>`
      : `<div class="version-nota vacia" id="nota-${v.id}">
          <div class="texto-nota">Sin nota</div>
          <button class="btn-editar-nota" onclick="editarNotaVersion(${v.id})" title="Agregar nota">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          </button>
        </div>`;

    // Eliminar disponible siempre que haya >1 versión disponible (el backend bloquea borrar la única).
    const puedeEliminar = puedeAdministrar && disponibles > 1;

    return `<div class="version-card">
      <div class="version-numero">
        <div class="num">v${fmtVer(v)}</div>
        ${vigente ? `<div class="version-vigente-pill">Vigente</div>` : ''}
      </div>
      <div>
        <div class="version-info-autor">Subido por <strong style="color:var(--blanco)">${esc(nombreAutor(v.subido_por))}</strong></div>
        <div class="version-info-meta">
          <span>${fechaHora(v.subido_at)}</span>
          <span>·</span>
          <span>${esc(v.mime_type || '')}</span>
          <span>·</span>
          <span>${tamano(v.tamano_bytes)}</span>
        </div>
        ${notaHTML}
      </div>
      <div class="version-acciones">
        ${esPdf ? `
        <a href="#" onclick="event.preventDefault(); previsualizarVersion(${documentoActivo.id}, ${v.id})" class="accion-btn" style="color:var(--gris5)">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          Vista previa
        </a>` : ''}
        <a href="#" onclick="event.preventDefault(); descargarVersion(${documentoActivo.id}, ${v.id})" class="accion-btn" style="color:var(--dorado)">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
          Descargar
        </a>
        ${puedeEliminar ? `
        <a href="#" onclick="event.preventDefault(); abrirModalEliminarVersion(${documentoActivo.id}, ${v.id}, 'v${fmtVer(v)}', ${vigente ? 'true' : 'false'})" class="accion-btn" style="color:var(--gris5)">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="3 6 5 6 21 6"/>
            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/>
            <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
            <line x1="10" y1="11" x2="10" y2="17"/>
            <line x1="14" y1="11" x2="14" y2="17"/>
          </svg>
          Eliminar
        </a>` : ''}
      </div>
    </div>`;
  }).join('');
}

// ── PREVIEW Y DESCARGA DE VERSIONES ESPECÍFICAS ───────────────
async function previsualizarVersion(docId, versionId) {
  const win = window.open('', '_blank');
  if (win) win.document.write('<p style="font-family:sans-serif;color:#555;padding:24px">Cargando vista previa...</p>');
  try {
    const res = await fetch(API + `/documentos/${docId}/versiones/${versionId}/preview`, { credentials: 'include' });
    if (!res.ok) { if (win) win.close(); mostrarToast('No se pudo abrir la vista previa.', 'error'); return; }
    const blob = await res.blob();
    const url  = window.URL.createObjectURL(blob);
    if (win) win.location.href = url; else window.open(url, '_blank');
    setTimeout(() => window.URL.revokeObjectURL(url), 60000);
  } catch { if (win) win.close(); mostrarToast('Error al abrir la vista previa.', 'error'); }
}

async function descargarVersion(docId, versionId) {
  try {
    const res = await fetch(API + `/documentos/${docId}/versiones/${versionId}/descargar`, { credentials: 'include' });
    if (!res.ok) { mostrarToast('No se pudo descargar.', 'error'); return; }
    const blob = await res.blob();
    const url  = window.URL.createObjectURL(blob);
    const a    = document.createElement('a');
    // Tomar el filename del header si está, sino armarlo
    const cd = res.headers.get('Content-Disposition') || '';
    const m  = cd.match(/filename="?([^"]+)"?/);
    a.href = url; a.download = m ? m[1] : `documento_v${versionId}`;
    document.body.appendChild(a); a.click(); a.remove();
    setTimeout(() => window.URL.revokeObjectURL(url), 10000);
  } catch { mostrarToast('Error al descargar.', 'error'); }
}

// ── ELIMINAR VERSIÓN ──────────────────────────────────────────
function abrirModalEliminarVersion(docId, versionId, label, esVigente) {
  pendingEliminarVer = { docId, versionId, vigente: !!esVigente };

  const titulo = documentoActivo?.titulo || 'este documento';
  document.getElementById('eliminar-ver-msg').innerHTML =
    `¿Eliminar <strong>${esc(label)}</strong> de "<em>${esc(titulo)}</em>"?<br><br>
     Esta acción se puede deshacer desde el toggle <strong>Mostrar eliminadas</strong> (solo super_admin).`;

  // Aviso especial si es la vigente
  document.getElementById('eliminar-ver-aviso').style.display = esVigente ? 'block' : 'none';
  document.getElementById('eliminar-ver-error').textContent   = '';
  document.getElementById('eliminar-ver-error').style.display = 'none';

  document.getElementById('modal-eliminar-version').classList.add('open');
}

function cerrarModalEliminarVersion() {
  document.getElementById('modal-eliminar-version').classList.remove('open');
  pendingEliminarVer = null;
}

async function ejecutarEliminarVersion() {
  if (!pendingEliminarVer) return;
  const { docId, versionId } = pendingEliminarVer;
  const btn = document.getElementById('btn-eliminar-ver-confirmar');
  const errBox = document.getElementById('eliminar-ver-error');
  errBox.style.display = 'none';

  btn.disabled = true; btn.textContent = 'Eliminando...';
  try {
    const resp = await apiFetch('DELETE', `/documentos/${docId}/versiones/${versionId}`);
    cerrarModalEliminarVersion();

    let msg = 'Versión eliminada.';
    if (resp?.nueva_activa_id) msg += ' La versión anterior pasó a ser la vigente.';
    mostrarToast(msg, 'exito');

    // Refrescar versiones y la lista padre (cambió version_activa)
    await cargarVersiones(docId);
    try {
      const url = '/documentos' + (empresaFiltroId ? `?empresa_id=${empresaFiltroId}` : '');
      const docs = await apiFetch('GET', url);
      todosLosDocumentos = docs;
      const fresh = docs.find(d => d.id === docId);
      if (fresh) documentoActivo = fresh;
    } catch { /* no rompe el flujo */ }
  } catch (e) {
    errBox.textContent = e.data?.error || e.data?.message || 'Error al eliminar la versión.';
    errBox.style.display = 'block';
  } finally {
    btn.disabled = false; btn.textContent = 'Eliminar';
  }
}

async function restaurarVersion(docId, versionId, label) {
  try {
    const resp = await apiFetch('POST', `/documentos/${docId}/versiones/${versionId}/restore`);

    const msg = resp?.promovida
      ? `${label} restaurada y promovida a vigente.`
      : `${label} restaurada (queda en el historial como inactiva).`;
    mostrarToast(msg, 'exito');

    // Refrescar versiones siempre
    await cargarVersiones(docId);

    // Si hubo promoción, también refrescar la lista padre porque cambió version_activa
    if (resp?.promovida) {
      try {
        const url = '/documentos' + (empresaFiltroId ? `?empresa_id=${empresaFiltroId}` : '');
        const docs = await apiFetch('GET', url);
        todosLosDocumentos = docs;
        const fresh = docs.find(d => d.id === docId);
        if (fresh) documentoActivo = fresh;
      } catch { /* no rompe el flujo */ }
    }
  } catch (e) {
    mostrarToast(e.data?.error || e.data?.message || 'Error al restaurar la versión.', 'error');
  }
}

// ── EDITAR NOTA INLINE ────────────────────────────────────────
function editarNotaVersion(versionId) {
  const cont = document.getElementById(`nota-${versionId}`);
  if (!cont) return;
  const textoActual = cont.querySelector('.texto-nota')?.textContent || '';
  const valorInicial = textoActual === 'Sin nota' ? '' : textoActual;

  cont.classList.remove('vacia');
  cont.innerHTML = `
    <div style="flex:1">
      <textarea class="nota-edit-area" id="nota-edit-${versionId}" maxlength="500" placeholder="Escribí una nota (opcional)...">${esc(valorInicial)}</textarea>
      <div style="display:flex;gap:6px;margin-top:6px;justify-content:flex-end">
        <button class="btn btn-ghost" onclick="cancelarEditarNota(${versionId})" style="padding:4px 10px;font-size:11px">Cancelar</button>
        <button class="btn btn-primary" onclick="guardarNotaVersion(${versionId})" style="padding:4px 10px;font-size:11px">Guardar</button>
      </div>
    </div>`;
  document.getElementById(`nota-edit-${versionId}`).focus();
}

async function guardarNotaVersion(versionId) {
  if (!documentoActivo) return;
  const ta = document.getElementById(`nota-edit-${versionId}`);
  const nota = ta.value.trim();
  try {
    await apiFetch('PUT', `/documentos/${documentoActivo.id}/versiones/${versionId}/nota`, { nota: nota || null });
    mostrarToast('Nota actualizada.', 'exito');
    await cargarVersiones(documentoActivo.id);
  } catch (e) {
    mostrarToast(e.data?.message || 'Error al guardar la nota.', 'error');
  }
}

function cancelarEditarNota(versionId) {
  // Recargar versiones para resetear UI al estado original
  if (documentoActivo) cargarVersiones(documentoActivo.id);
}

// ══════════════════════════════════════════════════
//   MODAL SUBIR NUEVA VERSIÓN
// ══════════════════════════════════════════════════

function setTipoCambio(tipo) {
  tipoCambioVersion = tipo;
  document.getElementById('tc-menor').classList.toggle('active', tipo === 'menor');
  document.getElementById('tc-mayor').classList.toggle('active', tipo === 'mayor');
}

function abrirModalSubirVersion() {
  if (!documentoActivo) return;
  resetDropZoneVersion();
  document.getElementById('version-nota').value = '';
  document.getElementById('version-error').textContent = '';
  document.getElementById('version-error').style.display = 'none';

  // Opciones menor/mayor calculadas desde el historial ya cargado. El backend
  // recalcula y tiene la ultima palabra; esto es la vista previa para el usuario.
  const va = versionesDoc.find(v => v.es_activa && !v.deleted_at)
          || (Array.isArray(documentoActivo.version_activa) ? documentoActivo.version_activa[0] : documentoActivo.version_activa);
  const nums = versionesDoc.length ? versionesDoc : (va ? [va] : []);
  const maxNumber  = nums.length ? Math.max(...nums.map(v => v.version_number || 0)) : (va?.version_number || 0);
  const baseNumber = va?.version_number ?? maxNumber;
  const maxMinorBase = Math.max(0, ...nums.filter(v => v.version_number === baseNumber).map(v => v.version_minor ?? 0));
  document.getElementById('tc-num-menor').textContent = `v${baseNumber}.${maxMinorBase + 1}`;
  document.getElementById('tc-num-mayor').textContent = `v${maxNumber + 1}.0`;
  setTipoCambio('mayor');

  document.getElementById('modal-subir-version').classList.add('open');
}

function cerrarModalSubirVersion() {
  document.getElementById('modal-subir-version').classList.remove('open');
  archivoVersion = null;
}

function resetDropZoneVersion() {
  archivoVersion = null;
  document.getElementById('drop-msg-v').style.display  = '';
  document.getElementById('drop-info-v').style.display = 'none';
  document.getElementById('archivo-v').value = '';
}

function onArchivoVersionSel(e) {
  const f = e.target.files?.[0];
  if (!f) return;
  archivoVersion = f;
  document.getElementById('drop-nombre-v').textContent = f.name;
  document.getElementById('drop-tamano-v').textContent = `${(f.size/1048576).toFixed(2)} MB · ${f.type || 'archivo'}`;
  document.getElementById('drop-msg-v').style.display  = 'none';
  document.getElementById('drop-info-v').style.display = '';
}

async function subirNuevaVersion() {
  if (!documentoActivo) return;
  const errBox = document.getElementById('version-error');
  errBox.style.display = 'none';

  if (!archivoVersion) {
    errBox.textContent = 'Seleccioná un archivo.'; errBox.style.display = 'block'; return;
  }

  const btn = document.getElementById('btn-confirmar-version');
  btn.disabled = true; btn.innerHTML = 'Subiendo...';

  const fd = new FormData();
  fd.append('archivo', archivoVersion);
  const nota = document.getElementById('version-nota').value.trim();
  if (nota) fd.append('nota', nota);
  fd.append('tipo_cambio', tipoCambioVersion);

  try {
    const res = await fetch(API + `/documentos/${documentoActivo.id}/version`, {
      method: 'POST', credentials: 'include', body: fd,
    });
    if (!res.ok) {
      const body = await res.json().catch(() => ({}));
      throw { data: body };
    }
    mostrarToast('Nueva versión subida correctamente.', 'exito');
    cerrarModalSubirVersion();
    // Refrescar versiones + recargar lista padre (para que se actualice versionActiva)
    await cargarVersiones(documentoActivo.id);
    const docs = await apiFetch('GET', '/documentos' + (empresaFiltroId ? `?empresa_id=${empresaFiltroId}` : ''));
    todosLosDocumentos = docs;
    // Actualizar el documento activo en memoria con los nuevos datos
    const fresh = docs.find(d => d.id === documentoActivo.id);
    if (fresh) documentoActivo = fresh;
  } catch (e) {
    errBox.textContent = e.data?.message || 'Error al subir la versión.';
    errBox.style.display = 'block';
  } finally {
    btn.disabled = false;
    btn.innerHTML = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg> Subir versión`;
  }
}

// Drag & drop en zona de subir versión
(() => {
  const dz = document.getElementById('drop-zone-v');
  if (!dz) return;
  ['dragenter','dragover'].forEach(ev => dz.addEventListener(ev, e => { e.preventDefault(); dz.classList.add('drag-over'); }));
  ['dragleave','drop'].forEach(ev => dz.addEventListener(ev, e => { e.preventDefault(); dz.classList.remove('drag-over'); }));
  dz.addEventListener('drop', e => {
    const f = e.dataTransfer?.files?.[0];
    if (!f) return;
    document.getElementById('archivo-v').files = e.dataTransfer.files;
    onArchivoVersionSel({ target: { files: [f] } });
  });
})();
async function eliminarVersion(documentId, versionId, versionLabel) {

  const ok = confirm(
    `¿Eliminar ${versionLabel}?\n\nEl documento seguirá existiendo.`
  );

  if (!ok) return;

  try {

    const res = await fetch(
      `/api/documentos/${documentId}/versiones/${versionId}`,
      {
        method: 'DELETE',
        headers: {
  'Accept': 'application/json',
  'Authorization': `Bearer ${localStorage.getItem('token')}`
}
      }
    );

    const text = await res.text();
console.log(text);

const data = text ? JSON.parse(text) : {};

    if (!res.ok) {
      throw new Error(data.error || 'Error al eliminar');
    }

    alert('Versión eliminada correctamente');

    await verDocumento(documentId);

  } catch (e) {

    alert(e.message);

  }

}

// ── HELPERS ───────────────────────────────────────────────────
// Etiqueta de version "numero.minor" (fallback si falta version_label).
function fmtVer(v) {
  if (!v) return '';
  return v.version_label || (v.version_number + '.' + (v.version_minor ?? 0));
}

function formatFecha(str) {
  if (!str) return '—';
  const d = new Date(str);
  return d.toLocaleDateString('es-AR', { day:'2-digit', month:'2-digit', year:'numeric' });
}

function esc(str) {
  if (!str) return '—';
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
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
  if (e.key === 'Escape') cerrarModalSubir();
});

// Cerrar el desplegable del combobox de empresa al hacer clic afuera
document.addEventListener('click', e => {
  const combo = document.getElementById('empresa-combo');
  if (combo && !combo.contains(e.target)) {
    document.getElementById('empresa-opciones').style.display = 'none';
  }
});

init();
</script>

<?php include 'layout/footer.php'; ?>