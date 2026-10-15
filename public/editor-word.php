<?php
require_once __DIR__ . '/layout/config.php';
require_once __DIR__ . '/layout/auth.php';
verificarSesion('franquiciante'); // super_admin entra igual (ver auth.php)
$titulo        = 'Editor de Manual (Word)';
$pagina_actual = 'manuales';
include 'layout/head.php';
?>
<!--
  Editor de manuales en formato Word, con OnlyOffice Document Server.

  Reemplaza a editor.php para los manuales cuyo borrador es un .docx. El
  editor propio sigue existiendo y sigue atendiendo a los manuales viejos,
  que guardan su borrador en HTML.

  POR QUE NO USA EL LAYOUT COMPLETO (sidebar + topbar): el editor necesita la
  pantalla entera. Una barra propia, finita, alcanza para volver y para saber
  que manual se esta editando.

  NADA DE LA CONFIGURACION SE ARMA ACA. El navegador pide
  /api/manuales/{id}/editor-config y recibe el bloque ya firmado por el
  backend. La clave compartida con el Document Server no baja nunca al
  navegador: si bajara, cualquiera con la consola abierta podria firmar
  pedidos y hacer que el Document Server descargue URLs de nuestra red.
-->
<style>
  html, body { margin:0; height:100%; overflow:hidden; background:#0A0A0A; }

  .ow-barra {
    height:46px; background:#0A0A0A; border-bottom:1px solid #26231D;
    display:flex; align-items:center; gap:14px; padding:0 16px;
    font-family:'Archivo', Arial, sans-serif; color:#F5F3EE; font-size:13px;
  }
  .ow-volver {
    color:#C9A84C; text-decoration:none; display:flex; align-items:center;
    gap:6px; font-weight:500; white-space:nowrap;
  }
  .ow-volver:hover { text-decoration:underline; }
  .ow-sep { width:1px; height:18px; background:#26231D; }
  .ow-titulo {
    font-weight:600; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
  }
  .ow-estado {
    font-size:11px; color:#8A8A8A; font-family:'Roboto', sans-serif;
    margin-left:auto; white-space:nowrap;
  }

  #ow-editor { height:calc(100% - 46px); }

  /* Pantalla de carga y de error: ocupan el lugar del editor. */
  .ow-centro {
    height:100%; display:flex; flex-direction:column; align-items:center;
    justify-content:center; gap:14px; color:#8A8A8A;
    font-family:'Roboto', sans-serif; font-size:14px; text-align:center;
    padding:0 24px;
  }
  .ow-centro strong { color:#F5F3EE; font-size:15px; }
  .ow-spinner {
    width:26px; height:26px; border:2px solid #26231D; border-top-color:#C9A84C;
    border-radius:50%; animation:ow-girar .8s linear infinite;
  }
  @keyframes ow-girar { to { transform:rotate(360deg); } }
  .ow-btn {
    background:#C9A84C; color:#1A1A1A; border:none; border-radius:6px;
    padding:9px 18px; font-weight:600; cursor:pointer; font-size:13px;
    font-family:'Archivo', Arial, sans-serif;
  }
</style>

<div class="ow-barra">
  <a class="ow-volver" href="<?= BASE_URL_PHP ?>/manuales.php">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
    Manuales
  </a>
  <div class="ow-sep"></div>
  <span class="ow-titulo" id="ow-titulo">Cargando…</span>
  <span class="ow-estado" id="ow-estado"></span>
</div>

<div id="ow-editor">
  <div class="ow-centro">
    <div class="ow-spinner"></div>
    <span>Abriendo el documento…</span>
  </div>
</div>

<script>
const MANUAL_ID = parseInt(new URLSearchParams(location.search).get('id') || '0', 10);

// Muestra un cartel en lugar del editor. Se usa para todos los fallos: el
// usuario tiene que entender que paso y poder volver, no quedarse mirando un
// spinner eterno.
function owError(titulo, detalle) {
  document.getElementById('ow-editor').innerHTML = `
    <div class="ow-centro">
      <strong></strong>
      <span></span>
      <button class="ow-btn" onclick="location.href='${BASE_URL}/manuales.php'">Volver a manuales</button>
    </div>`;
  const c = document.querySelector('#ow-editor .ow-centro');
  c.querySelector('strong').textContent = titulo;
  c.querySelector('span').textContent   = detalle || '';
}

// Carga el JavaScript del editor DESDE EL DOCUMENT SERVER.
//
// Se inyecta a mano y no con una etiqueta <script> fija en el HTML porque la
// direccion del Document Server la decide el backend segun el entorno: en
// local es localhost:8080, en produccion un subdominio con https. Escribirla
// en el HTML obligaria a tener dos versiones de esta pagina.
function owCargarApi(servidor) {
  return new Promise((resolve, reject) => {
    const s = document.createElement('script');
    s.src     = servidor + '/web-apps/apps/api/documents/api.js';
    s.onload  = resolve;
    // Este es el fallo tipico cuando el Document Server esta apagado, mal
    // configurado o sin https en produccion. Conviene decirlo con todas las
    // letras, porque desde afuera parece "el editor no abre".
    s.onerror = () => reject(new Error('No se pudo contactar al servidor de edición.'));
    document.head.appendChild(s);
  });
}

async function owIniciar() {
  if (!MANUAL_ID) {
    owError('Falta el manual', 'La dirección no indica qué manual abrir.');
    return;
  }

  let datos;
  try {
    datos = await apiFetch('GET', `/manuales/${MANUAL_ID}/editor-config`);
  } catch (err) {
    // apiFetch ya redirige solo si la sesión venció (401). Acá llegan el 403
    // (no es tu manual), el 409 (no tiene .docx) y el 503 (OnlyOffice apagado).
    const msg = err?.data?.error || 'No se pudo abrir el documento.';
    owError('No se puede editar este manual', msg);
    return;
  }

  document.getElementById('ow-titulo').textContent = datos.manual.titulo;
  document.title = datos.manual.titulo + ' — Editor';

  try {
    await owCargarApi(datos.servidor);
  } catch (err) {
    owError('El servidor de edición no responde', err.message);
    return;
  }

  // El contenedor se reemplaza por un div limpio: DocsAPI escribe adentro y
  // el spinner tiene que desaparecer antes.
  document.getElementById('ow-editor').innerHTML = '<div id="ow-placeholder"></div>';

  const config = datos.config;
  config.events = {
    onAppReady: () => {
      document.getElementById('ow-estado').textContent = 'Documento abierto';
    },

    // RENOMBRAR. El editor avisa acá cuando el usuario cambia el nombre.
    //
    // Lo que se renombra es EL MANUAL, no un archivo suelto: el mismo nombre
    // que aparece en el listado, en lectura, en los mails y en el PDF que
    // descarga el socio. Por eso se guarda con el endpoint de siempre.
    //
    // Si falla (sin permisos, sesión vencida, red), se avisa y se deja el
    // nombre viejo en la barra: callar acá haría creer que se guardó.
    onRequestRename: async (e) => {
      // El editor manda el nombre con extensión o sin ella según el caso.
      const crudo  = (e?.data?.title ?? e?.data ?? '').toString();
      const nuevo  = crudo.replace(/\.docx$/i, '').trim();

      if (!nuevo) return;

      try {
        await apiFetch('PUT', `/manuales/${MANUAL_ID}`, { titulo: nuevo });
        document.getElementById('ow-titulo').textContent = nuevo;
        document.title = nuevo + ' — Editor';
      } catch (err) {
        alert(err?.data?.error
              || 'No se pudo cambiar el nombre del manual. Probá de nuevo.');
      }
    },
    // El Document Server avisa sus propios errores por acá (documento
    // corrupto, token rechazado, descarga fallida). Sin esto quedarían solo
    // en la consola.
    onError: (e) => {
      console.error('OnlyOffice:', e);
      document.getElementById('ow-estado').textContent = 'Error del editor';
    },
    // e.data === true cuando el documento tiene cambios sin guardar.
    //
    // "Se guardan al cerrar" no es un consuelo: es literal. Aunque el usuario
    // nunca apriete Guardar, al cerrar la pestaña el servidor de edición nos
    // devuelve el documento igual (callback con status 2). Decirlo evita el
    // miedo a perder trabajo, que es lo que lleva a guardar compulsivamente.
    onDocumentStateChange: (e) => {
      document.getElementById('ow-estado').textContent = e.data
        ? 'Cambios sin guardar — se guardan al cerrar'
        : 'Guardado';
    },
  };

  new DocsAPI.DocEditor('ow-placeholder', config);
}

owIniciar();
</script>
</body>
</html>
