# Manuales Franquiciantes — Business Partner

Plataforma multi-tenant para que una empresa franquiciante redacte, publique y
distribuya sus **manuales operativos**, y para que sus socios comerciales los
lean con registro.

⚠️ **El cumplimiento es la FIRMA FÍSICA, no el registro digital.** Esto cambió en
julio de 2026 y es lo primero que hay que entender:

- Cuando un socio comercial **abre** un manual se escribe una fila en
  `acceptances`. Eso registra que lo abrió — ese día, desde esa IP. No es un
  consentimiento y la UI ya no dice que lo sea: el botón desapareció y la
  pantalla habla de **"Leído"**.
- Lo que prueba cumplimiento es el **PDF firmado a mano** que el franquiciante
  sube desde `aceptaciones.php` (`physical_signatures`).

La tabla se sigue llamando `acceptances` y la acción del log `manual_aceptado`
por la misma convención de siempre: los strings de base son inmutables, las
etiquetas de UI cambian. Ver §7 → *Lectura y firma física*.

Cliente inicial: **Cerrajería Leonardo** (razón social Acceso Leonardo S.A.S).

---

## 1. Arquitectura en una página

Hay **dos aplicaciones conviviendo** en el mismo repositorio, y entenderlo es
condición para tocar cualquier cosa:

```
┌─────────────────────────────────────────────────────────────┐
│  public/*.php          Frontend. PHP plano + HTML + JS       │
│                        vanilla. Sin framework, sin build.    │
│                        Cada página valida sesión por su      │
│                        cuenta con layout/auth.php (PDO       │
│                        directo contra la base).              │
│         │                                                    │
│         │  fetch() con cookie                                │
│         ▼                                                    │
│  routes/api.php        Backend. API REST de Laravel 12,      │
│  app/Http/...          autenticada con Sanctum.              │
└─────────────────────────────────────────────────────────────┘
```

**El frontend no usa Blade.** Son archivos `.php` que emiten HTML y hablan con
la API por `fetch`. Las páginas de Laravel (`resources/views`) casi no se usan;
la excepción es la plantilla de email.

**Hay dos caminos de autenticación distintos** sobre el mismo token:

| Camino | Quién | Cómo valida |
|---|---|---|
| API (`/api/*`) | Controladores Laravel | Middleware `auth:sanctum` |
| Páginas (`*.php`) | `public/layout/auth.php` | Consulta `personal_access_tokens` por PDO |

Los dos leen la cookie `auth_token`. Si tocás el esquema de tokens o la forma de
la cookie, hay que actualizar **los dos lados**.

---

## 2. Stack

| Componente | Versión / detalle |
|---|---|
| PHP | 8.3 en producción (8.2+ funciona) — socket `php8.3-fpm.sock` |
| Laravel | 12 |
| MySQL | 8.0.45 (no subir de versión mayor: se usan CHECK constraints y columnas generadas) |
| Auth | Laravel Sanctum, token en cookie `HttpOnly` + `SameSite=Strict` |
| Anti-bot | Cloudflare Turnstile en el login (plan Free) |
| PDF (generar) | mPDF |
| PDF (mostrar) | pdf.js 4.10.38, auto-hospedado en `public/js/pdfjs/` — **módulos ES (`.mjs`)**, ver §11 |
| Importar Word | Mammoth.js (browser) |
| Sanitización HTML | HTMLPurifier |
| Mail | Resend (producción) / `log` (desarrollo) |
| Storage | Disco por configuración: `local` en dev, `s3` en producción |
| Local | XAMPP — `C:/xampp/htdocs/manuales-franquiciantes/` |
| Producción | AWS EC2 (Ubuntu) + nginx 1.24 — `/var/www/franchising-manager`, dominio `businesspartner.goharv.com.ar`, TLS por Certbot |

---

## 3. Roles

Cuatro roles, en la columna `users.rol` (ENUM). **Los strings son inmutables**:
están cableados en middleware, Policies y consultas.

| Rol | `empresa_id` | Qué hace |
|---|---|---|
| `super_admin` | **NULL** | Administra la plataforma. Ve todo, atraviesa cualquier filtro por empresa. |
| `franquiciante` | obligatorio | Opera una empresa: crea manuales, publica versiones, gestiona usuarios y sucursales. |
| `franquiciado` | obligatorio | **En la UI se llama "Socio comercial".** Lee los manuales asignados y los acepta. |
| `empleado` | obligatorio | Lee. No acepta. |

Reglas que se repiten en todo el código:

- **`empresa_id` sale SIEMPRE del token autenticado**, nunca del cuerpo del
  request. Aceptarlo del request sería cambiar de tenant.
- El `super_admin` es el único con `empresa_id` NULL. Varias consultas dependen
  de eso.
- El **franquiciado tiene una cola de aceptación**: al entrar, si tiene manuales
  pendientes, `layout/auth.php` lo redirige al primero. No navega libre hasta
  ponerse al día.

---

## 4. Modelo de datos

### 4.1 Tablas principales

```
empresas ──┬── franquicias (sucursales)
           ├── users
           └── manual_empresa_assignments ── manuals

manuals ──── manual_versions ──┬── acceptances        (aceptación digital)
                               ├── physical_signatures (PDF de firma escaneada)
                               └── notifications

documents ── document_versions   (mismo patrón padre/hijo que manuales)

franchise_categories ──┬── user_categories
                       ├── manual_category_assignments
                       └── document_category_assignments
```

Tablas de perfil (`super_admins`, `system_admins`, `franchise_staff`): después de
v2.3 son **marcadores de rol**; nombre, apellido y DNI viven en `users`.
`franchise_staff` conserva además `franquicia_id`.

**Un usuario sin su fila de perfil queda a medias.** Al crear usuarios por fuera
de la UI, hay que insertar la fila correspondiente.

### 4.2 Visibilidad de un manual

Un usuario ve un manual si se cumple **todo**:

1. El manual está `publicado` y no eliminado.
2. Está asignado a su empresa (`manual_empresa_assignments`).
3. Y además: tiene una **categoría activa** que lo incluye **O** una
   **asignación individual** (`manual_user_assignments`).

Sin el punto 3 el manual queda publicado pero invisible. Es intencional: el
franquiciante decide a quién le llega.

La lógica central vive en `App\Services\ManualAccessService`. **Usalo siempre**
en vez de reimplementar el filtro.

### 4.3 Versionado

`manual_versions` y `document_versions` siguen el mismo esquema:

- Numeración `version_number` . `version_minor` (ej. `3.1`).
- Solo **una** versión activa por manual, garantizado por una columna generada
  con índice único (`uq_mv_es_activa`, `uq_dv_es_activa`).
- La versión guarda un **snapshot** del encabezado y el pie tal como estaban al
  publicar. La copia de trabajo vive en `manuals`. Leer del manual en vez de la
  versión fue un bug real: cambiar el pie alteraba el documento que un socio ya
  había aceptado.
- `documento_hash` (SHA-256 de encabezado + contenido + pie) es **lo que la
  aceptación certifica**.

---

## 5. Constraints de base de datos que hay que conocer

Estos no son detalles: **rompen inserciones en producción** si se los ignora.

### `chk_notif_fk` (notifications)

Cada `tipo` de notificación exige una combinación exacta de FKs. **No se pueden
inventar tipos nuevos sin migrar el CHECK.**

| Tipo | FK obligatoria |
|---|---|
| `nuevo_manual`, `nota_manual` | `manual_id` |
| `modificacion_manual`, `manual_asignado`, `acceso_anomalo_pdf` | `manual_version_id` |
| `nuevo_documento`, `documento_asignado` | `document_id` |
| `nueva_version_documento` | `document_version_id` |
| `manual_asignado_categoria` | `manual_id` + `category_id` |
| `documento_asignado_categoria` | `document_id` + `category_id` |
| `recordatorio_pendiente` | **ninguna** (todas NULL) |

⚠️ **`login_bloqueado` NO está en el CHECK.** Esta tabla lo listaba y es falso:
se verificó contra el `SHOW CREATE TABLE` real. Crear una notificación de ese
tipo hace fallar el INSERT.

⚠️ **Al modificar este CHECK, no hagas `DROP` + `ADD`.** Todas esas columnas
tienen FK con `ON DELETE CASCADE`, y MySQL prohíbe acciones referenciales en
columnas usadas por un CHECK (ver *Regla general de MySQL* más abajo). Si el
`ADD` falla, la tabla queda **sin ninguna restricción** y el DDL de MySQL no se
revierte solo. El camino seguro son cuatro pasos, y así está hecha la migración
`add_nota_manual_to_chk_notif_fk`:

1. `ADD chk_notif_fk_v2` con la expresión nueva — si MySQL la rechaza, falla acá
   y el CHECK original sigue en pie.
2. `DROP chk_notif_fk`
3. `ADD chk_notif_fk` con la misma expresión, ya probada en el paso 1.
4. `DROP chk_notif_fk_v2`

Para agregar un tipo, buscá si alguna rama existente ya admite la combinación de
FKs que necesitás y sumá el tipo a ese `IN`. Es mucho más seguro que agregar una
rama nueva.

### `chk_detalle_schema` (activity_logs)

`detalle` es JSON validado con `JSON_SCHEMA_VALID()`. Solo admite estas claves,
y **máximo 5 por registro**:

```
campo · valor_anterior · valor_nuevo · manual_titulo · empleado_nombre
version · categoria_nombre · user_email · documento_titulo
```

Cualquier otra clave hace fallar el INSERT.

### `chk_mv_contenido` (manual_versions)

Exige **exactamente uno** de `contenido_html` o `archivo_path`. Una versión es
HTML editable o es un archivo, nunca ambos ni ninguno.

### `chk_exenta_sin_plan` + `uq_unica_exenta` (empresas)

Si `facturable = 0`, entonces `plan_id` y los precios custom **deben ser NULL**.
Y la columna generada `unica_exenta` garantiza que **solo puede existir una
empresa exenta** en todo el sistema: Cerrajería Leonardo.

### `chk_estado_previo` (manuals)

`estado_previo` guarda en qué estado estaba un manual antes de archivarse, para
poder devolverlo ahí al restaurarlo. Solo admite `NULL`, `'borrador'` o
`'publicado'` — **nunca `'archivado'`**: si se pudiera, archivar dos veces el
mismo manual lo dejaría sin estado al cual volver.

Se asigna con **setter directo, nunca con `update()` masivo**: es un campo
derivado del servidor y no debe poder llegar desde el request. No está en el
`$fillable` de `Manual` y así debe quedar.

Antes de esto, `desarchivar()` forzaba `'borrador'`. Un manual publicado que se
archivaba y se restauraba quedaba invisible para los socios, y sacarlo de
borrador obligaba a publicar, lo que a su vez obligaba a subir de versión sin
que el documento hubiera cambiado. En los manuales PDF directamente no había
salida: no tienen `editor.php` y por lo tanto no tenían botón de publicar.

### Regla general de MySQL

**Una columna no puede tener a la vez un CHECK y una FK con `ON DELETE SET NULL`
o `CASCADE`.** Ya nos mordió dos veces. Salidas: usar `ON DELETE RESTRICT`, o
mover la validación al código.

---

## 6. Mass assignment: campos fuera de `$fillable`

Auditorías previas (H-015, V2-H-019) sacaron del `$fillable` los campos que
otorgan privilegios o definen identidad. **Se asignan con setter directo**:

```php
$user->rol = 'franquiciante';   // ✅
$user->save();

User::create($request->all());  // ❌ ignora rol en silencio
```

| Modelo | Fuera de `$fillable` | Por qué |
|---|---|---|
| `User` | `rol`, `empresa_id`, `activo`, `password_hash`, `deleted_by`, `deleted_at`, `foto_url` | auto-promoción de rol, cambio de tenant, reactivación de cuenta |
| `ManualVersion` | `es_activa` | decide qué contenido ve todo el mundo y sobre qué se firman las aceptaciones |
| `DocumentVersion` | `es_activa` | idem |
| `Manual` | `tipo`, `public_id` | el tipo define qué contenido tienen las versiones; el public_id es la URL pública |

**El peligro es que fallan en silencio.** Un `create()` con esos campos no lanza
error: los descarta. Si sacás un campo del `$fillable`, buscá y convertí **todos**
sus call sites en el mismo commit.

Los `update()` sobre Query Builder (`Model::where(...)->update([...])`) **no**
pasan por `$fillable` y siguen funcionando.

---

## 7. Funcionalidades

### Manuales editables
Editor WYSIWYG propio (`editor.php`) con importación de Word vía Mammoth.js.
Al importar se preserva el **justificado** mediante un truco: `transformDocument`
asigna un `styleName` sintético según `paragraph.alignment`, el `styleMap` lo
convierte en una clase temporal, y un post-proceso la pasa a `style="text-align"`
inline — que es lo único que sobrevive a HTMLPurifier.

Limitación conocida: Mammoth **no expone el sombreado de párrafo** (`w:shd`) ni
los bordes, así que los fondos de color del Word original se pierden. Decisión
tomada: el manual usa la estética del sistema, no la del Word.

### Manuales en PDF
Permite subir un PDF y publicarlo sin convertirlo. **Está activo**:
`PDF_MANUALES_HABILITADO` ya no existe en ningún archivo — la constante se
eliminó al reactivar la funcionalidad.

**Versionado.** Desde julio de 2026 los PDF tienen las mismas dos preguntas que
los manuales editables:

- Al crear uno, **con qué versión arranca** (para documentos que ya venían
  usándose fuera del sistema con su propia numeración).
- Al subir una versión nueva, si es **cambio mayor o menor**.

El backend ya las soportaba desde siempre: `ManualController::publicarArchivo()`
valida `tipo_cambio`, `version_inicial_number` y `version_inicial_minor`. Lo que
faltaba era que el `FormData` los mandara.

Los dos modales viven en **`layout.js`** (`pedirTipoCambioPdf()`,
`pedirVersionInicialPdf()`) con su CSS en `panel.css` bajo el prefijo `.vpdf-`.

⚠️ **Los nombres llevan sufijo `Pdf` a propósito.** `editor.php` ya define
`elegirTipoCambio`, `abrirModalVersionInicial`, `previewVersionInicial` y
compañía — y carga `layout.js` por `footer.php`, o sea **después** de su propio
`<script>`. Un homónimo en `layout.js` le pisaría la publicación al editor con
un error que no menciona la colisión. Mismo caso que `renderPaginacion()` vs
`log.php`. Por eso `editor.php` no se tocó y sus modales siguen siendo una
tercera copia.

**Las etiquetas del modal se calculan con TODAS las versiones**, no con la
activa: `calcularEtiquetasVersion()` toma el máximo `version_minor` entre las que
comparten `version_number`. Si un manual tiene v1.0, v1.1 y v1.2 y la activa
volvió a ser la v1.0, `activa.minor + 1` propondría v1.1 — que ya existe.

**La versión inicial se pregunta ANTES de crear el manual.** Si se preguntara
después, cancelar dejaría un manual en borrador sin versión que nadie pidió.

El visor (`pdf.js` renderizando a canvas, sin capa de texto, con marca de agua
superpuesta) quedó terminado y funcionando.

El visor (`pdf.js` renderizando a canvas, sin capa de texto, con marca de agua
superpuesta) quedó terminado y funcionando.

**Editar un manual PDF.** No tienen `editor.php`, así que el título, la categoría
y las asignaciones se cambian desde un modal en `manuales.php` y
`manuales-mi-empresa.php`. Reusa el modal de creación en "modo edición" en vez de
tener uno propio: el árbol de categorías está atado al contenedor
`manual-categorias-lista` por id fijo y a estado compartido (`catsCompletas`,
`usuariosSelManual`), así que duplicarlo serían cuatro copias de la misma lógica.
`crearManual()` no se tocó — el botón del pie llama a un dispatcher que decide
entre crear y guardar.

Ese modal **precarga** las asignaciones actuales. Si esa precarga falla, guardar
queda bloqueado por el flag `asignacionesCargadasOk`: con los checkboxes vacíos,
un `PUT` borraría todas las asignaciones del manual sin que nadie se entere.

**Vista previa.** Tanto la vista previa como "Ver PDF" navegan a
`lectura.php?m=<public_id>`, nunca al id de la base. En el caso del PDF eso además
importa de verdad: `lectura.php` entrega el archivo con `manual.archivo_token`
—opaco, atado al usuario, con vencimiento— y lo abre en el visor propio. La ruta
vieja (`/api/manuales/{id}/archivo`) se salteaba todo eso.

### Documentos
Subida y versionado de archivos, sin aceptación. Es el lugar correcto para
material que **no** requiere firma.

### Lectura y firma física

Antes de julio de 2026 el socio comercial apretaba **"Aceptar manual"**, confirmaba
en un modal que la acción quedaba registrada, y eso creaba la fila de
`acceptances`. Ese era el cumplimiento.

**Ahora no.** El botón y el modal se eliminaron. `lectura.php` llama al mismo
endpoint (`POST /versiones/{id}/aceptar`) **automáticamente al abrir el manual**,
y la pantalla dice **"Leído"**. Lo que queda registrado es que esa persona abrió
esa versión, ese día, desde esa IP.

El cumplimiento pasó a ser la **firma física**: el PDF firmado a mano que se sube
desde `aceptaciones.php`.

Detalles que importan:

- **El registro dispara SOLO para `franquiciado`.** El endpoint no filtra por rol:
  sin esa condición, un franquiciante entrando en vista previa o un super_admin
  revisando el manual se registrarían a sí mismos.
- **El fallo no puede quedar mudo.** Si el POST falla, la cola de
  `layout/auth.php` devuelve al socio a ese mismo manual en cada page load, en
  loop y sin explicación. Por eso hay un estado visible con **"Reintentar"** en
  vez de un `catch` silencioso.
- **`auth.php` no se tocó.** Su cola sigue mirando `acceptances`; lo que cambió es
  qué significa: ahora es "tenés que abrir cada manual pendiente".
- **`aceptar()` sigue intacto en el backend.** Si algún día se reactiva la
  aceptación formal desde la UI, hay que agregar **antes** una columna que
  distinga los dos tipos de fila (`origen`): mezcladas en la misma tabla son
  indistinguibles y después no hay forma de separarlas. Postergarlo hoy es seguro
  porque, con la aceptación apagada, todo lo nuevo es lectura.
- Las ~60 filas anteriores al cambio eran **datos de prueba**, no consentimientos
  reales. Por eso no hizo falta preservar la distinción.

**Renombrado de UX.** `aceptaciones.php` y `mis-manuales.php` dejaron de hablar de
aceptación. Los pills de estado cambiaron de significado, no solo de nombre:

| Antes | Ahora | Por qué |
|---|---|---|
| Completo | Firmado | |
| **Solo digital** | **Falta firma** | Era cumplimiento parcial; ahora es cumplimiento CERO |
| Solo físico | Firmado sin lectura | |
| Mi aceptación / Aceptado | Leído / Leído | |

El nombre de la sección sigue siendo "Aceptaciones" por ahora. Los nombres de
clase CSS (`estado-solo-digital`, `badge-aceptado`) no se tocaron: son internos.

### Categorías

Los contadores de `categorias.php` (`manuales_asignados_count`,
`documentos_asignados_count`) cuentan **solo lo visible**: manuales
`publicado` + no eliminados, documentos `visible_franquiciado` + no eliminados.
Las dos condiciones en cada caso — un registro borrado puede haber quedado con
estado publicado.

El contador de **usuarios** también excluye los eliminados
(`whereNull('users.deleted_at')`). La fila de `user_categories` sobrevive al
soft-delete a propósito —si el usuario se restaura, recupera sus categorías— pero
para la pantalla ese usuario ya no está. Los **inactivos sí se cuentan**: una
cuenta bloqueada sigue perteneciendo a la categoría.

⚠️ **`destroy()` cuenta distinto a propósito.** Ahí el conteo no es informativo:
es la barrera que impide borrar físicamente una categoría con cosas colgando. Si
contara solo lo visible, una categoría con manuales en borrador daría 0, se
borraría, y quedarían filas huérfanas en `manual_category_assignments`. No
unificar los dos criterios.

**Botón "Eliminar" (borrado físico).** `DELETE /api/categorias/{id}`. Se muestra
siempre, aunque los contadores estén en 0: que la tabla muestre 0 **no** significa
que la categoría esté vacía, precisamente porque esos conteos filtran. El único
que sabe es el backend.

⚠️ **El 409 va a contradecir a la tabla, y es correcto.** La fila puede decir
"0 usuarios" y el error decir "1 usuario", porque `destroy()` cuenta todo —
eliminados y archivados incluidos. El modal desglosa el detalle **y aclara de
dónde sale la diferencia**; sin esa aclaración parece que el sistema se
contradice.

### Notificaciones
In-app (badge en la topbar) + email vía un **observer** de `Notification`. La
whitelist de tipos que disparan mail está en
`app/Observers/NotificationObserver.php` → `TIPOS_CON_EMAIL`.

`NotificationController::resolverDestino()` calcula **en el backend** a dónde
lleva cada notificación y si el recurso sigue disponible. El frontend no decide
eso.

El badge, el popup de entrada y el panel **ya están implementados** en
`layout.js` (`actualizarBadgeNotificaciones`, `toggleNotificaciones`). Crear la
fila en `notifications` alcanza: no hay que tocar frontend para un tipo nuevo.

**`nota_manual`** — cuando un socio comercial deja una nota en un manual, se les
avisa a todos los franquiciantes activos de esa empresa, por badge y por mail.

- **Solo si el autor es `franquiciado`.** `store()` también lo usa el
  franquiciante, y notificarse a sí mismo no aporta.
- **Si la empresa no tiene franquiciantes cargados, no se notifica a nadie.** No
  cae al super_admin: el feedback de una red es del franquiciante, y desviarlo en
  silencio sería una sorpresa desagradable.
- **Si la notificación falla, la nota igual se guarda** (`try/catch` + log).
  Perder lo que el socio escribió por no poder mandar un aviso sería el peor
  canje posible.
- **Su rama en `resolverDestino()` va ANTES de la de manuales**, igual que
  `acceso_anomalo_pdf` y por el mismo motivo: cuelga de `manual_id`, así que
  caería ahí y terminaría en `lectura.php`, que no es donde se leen las notas. Y
  esa rama exige estado `publicado`: archivar el manual mataría la notificación,
  cuando la nota ya fue escrita y sigue valiendo.
- El `titulo` nombra al autor ("Juan Pérez dejó una nota en: X") y es **también el
  asunto del mail** (`NotificacionMail::envelope()`). Es `varchar(200)` y se
  recorta el **título del manual**, no la cadena entera: cortar al final dejaría
  el nombre a medias sin decir nunca en qué manual.

### Registro de actividad
`activity_logs` guarda logins, publicaciones, accesos a archivos y accesos
anómalos. Se consulta en `log.php`.

### Seguridad de acceso a archivos
- Nada se sirve por URL directa del bucket: todo pasa por endpoints autenticados.
- El archivo de un manual PDF se entrega con un **token opaco, cifrado, atado al
  usuario y con vencimiento de 60 min** (`/manuales/archivo/{token}`).
- `lectura.php` navega con un **ULID público** (`?m=01K0S7...`), no con el ID de
  la base.

### Anti-bot en el login (Cloudflare Turnstile)

Widget en `login.html` + verificación server-side en
`AuthController::verificarTurnstile()`. **El widget solo no protege nada**: un
bot postea directo a `/api/login`. La única verificación real es la llamada a
Siteverify.

Se controla por `.env` — si `TURNSTILE_ENABLED` no está, queda apagado:

```
TURNSTILE_ENABLED=true
TURNSTILE_SECRET_KEY=...      # sin comillas
```

La **sitekey** es pública y vive hardcodeada en `login.html`. El **secret** solo
en el `.env`.

**Política de fallo** — está documentada en el propio método y conviene no
cambiarla sin leerla:

- **Rechaza** solo si la culpa es del cliente: token ausente, forjado, vencido o
  ya usado.
- **Deja pasar y loguea** si el problema es nuestro o de Cloudflare: secret vacío
  o mal pegado (`invalid-input-secret`), timeout, 5xx. Un secret mal copiado no
  puede dejar a toda la empresa afuera. El rate limiter compuesto
  (`throttle:login`, middleware de ruta, corre **antes**) sigue cubriendo fuerza
  bruta en esos casos.

El token dura 300 s y es de **un solo uso**: `login.html` resetea el widget en
cada error. Sin ese reset, después de un intento fallido el siguiente falla
siempre aunque la contraseña sea correcta. **Es la prueba de regresión del
feature**: contraseña mal una vez, después bien.

En modo *Managed* el checkbox no siempre aparece: Cloudflare decide según el
riesgo y muchas veces resuelve solo con un spinner. Eso es lo esperado.

No se registra en `activity_logs` porque `user_id` es `NOT NULL` y en ese punto
no hay usuario resuelto — misma limitación que los emails inexistentes (§9).

**Rollback**: `TURNSTILE_ENABLED=false` + `php artisan config:cache`. Sin tocar
código.

### Foto de perfil y avatares

`avatarUsuario(u, extraClase)`, `inicialesDeUsuario()`, `abrirFotoPerfil()`,
`cerrarFotoPerfil()` y el listener delegado viven en **`js/layout.js`**; el CSS
(`.u-avatar*`, `.avatar-lb*`) en **`panel.css`**, que `head.php` carga en todas
las páginas.

El círculo con iniciales se renderiza **siempre**; si hay foto, la `<img>` se le
monta encima y su `onerror` la elimina si el endpoint devuelve 404. Por eso el
fallback no necesita lógica de permisos en el frontend.

Tamaño base 32 px; `.u-avatar-sm` (30 px) lo usa `log.php`.

El avatar del **topbar** se pinta aparte, en `iniciarLayout()`, y **sin**
`u-avatar-click`: vive dentro del botón que va a `perfil.php` y con esa clase un
clic dispararía las dos cosas.

### Errores del servidor (tabla `error_logs`)

Pantalla `errores.php`, solo para `super_admin`. Existe porque los errores 5xx
solo quedaban en `storage/logs`: había que entrar por SSH para verlos, y en la
práctica nadie los miraba.

**No expone el archivo de log.** Un hook `report()` en `bootstrap/app.php`
escribe en una tabla propia con exactamente lo que se decide guardar. Exponer
`storage/logs` en una pantalla devolvería por la puerta de atrás lo que
`APP_DEBUG=false` esconde, y encima detrás de un solo chequeo de rol.

**Una fila por error ÚNICO**, agrupada por `huella` = `sha256(clase|archivo|línea)`,
con `ocurrencias`, `primera_vez` y `ultima_vez`. Si algo revienta 4.000 veces se
ve una fila que dice 4.000, no 4.000 filas. Sin eso, un error en bucle llena la
tabla y tapa todo lo demás justo cuando más se necesita la pantalla.

Lo que **no** se guarda, y es lo que hace segura la idea:

- **Nunca el cuerpo del request.** El POST a `/api/login` lleva la contraseña en
  texto plano.
- **La ruta va redactada.** `/manuales/archivo/{token}` usa un token opaco, atado
  al usuario y válido 60 minutos: es una credencial funcional. Se guarda el
  literal `{token}`. Ídem los valores de `password` y `turnstile_token` en el
  query string.
- **Solo 5xx.** Los 404, 422, 401 y 403 son comportamiento normal; incluirlos
  ahoga la señal.
- **El trace, recortado** a los primeros 8 frames.

El guardado va en `try/catch` vacío a propósito: si falla registrar el error **no
puede lanzar otra excepción**, porque eso es un bucle. `user_id` es nullable —
los errores también le pasan a gente sin sesión.

`resuelto` lo marca el super_admin desde la pantalla, y una ocurrencia nueva lo
vuelve a 0: si reapareció, no estaba resuelto.

Guard doble: la ruta está en el grupo `role:super_admin` **y** el controlador
re-verifica `esSuperAdmin()`. Son stack traces; si un refactor moviera esas rutas
de grupo, quedarían expuestas en silencio.

A diferencia de `activity_logs`, estos registros **se pueden borrar**: son
diagnóstico, no cumplimiento.

### Purga de datos personales de un usuario

En `usuarios.php`, sobre un usuario ya eliminado, el super_admin ve
**"Borrar definitivamente"**. `POST /api/usuarios/{id}/purgar`.

**NO borra la fila, y no puede.** `acceptances.user_id` y `activity_logs.user_id`
son `ON DELETE RESTRICT`: un DELETE físico falla en la base. Y está bien que
falle — esa fila es el **sujeto** de la cadena de cumplimiento. Sin ella, una
aceptación diría "alguien aceptó la versión 2.1", que no prueba nada.

Lo que se destruye son los datos de la persona:

| Columna | Queda | Por qué |
|---|---|---|
| `email` | `eliminado+{id}@usuario.invalid` | Es `NOT NULL UNIQUE`. El `id` garantiza unicidad y `.invalid` está reservado por RFC 2606 |
| `nombre` / `apellido` | `Usuario` / `eliminado` | Los dos son `NOT NULL` |
| `cuit`, `dni_legacy`, `celular`, `foto_url` | `NULL` | `foto_url` **después** de borrar el objeto de S3 |
| `password_hash` | hash aleatorio de 64 chars | `NOT NULL`. Más seguro que un valor conocido |
| `activo` | `0` | |
| `rol`, `empresa_id` | **se conservan** | No son dato personal; sin ellos los logs viejos pierden contexto |

Columnas nuevas: `anonimizado_at` y `anonimizado_por`.

**Guards:** solo `super_admin` (doble, ruta + controlador), no sobre uno mismo,
solo sobre usuarios ya eliminados —obliga a pasar antes por el borrado, que sí es
reversible— y exige el **email exacto** en `confirmacion_email`, comparado con
`hash_equals`.

⚠️ **El `ActivityLog` va DENTRO de la transacción y SIN `try/catch`**, al revés
que el resto del proyecto. Si registrar falla, la purga se revierte entera. Una
destrucción irreversible sin registro es peor que una que no ocurre.

**El log NO guarda el email**, a propósito: escribirlo en un registro nuevo justo
cuando se lo está borrando contradice la operación.

**Un usuario purgado no se puede restaurar** (`restore()` devuelve 409) y
**desaparece de todos los listados** (`scopeNoPurgados()` en `index()`, fuera del
`if` de `include_deleted`). El rastro queda en `activity_logs` como
`usuario_purgado`, consultable desde `log.php`.

⚠️ **Lo que esta purga NO alcanza**, y es deuda conocida, no un olvido:

- `acceptances.pdf_sellado_url`: el PDF sellado lleva el nombre impreso. Es el
  certificado; uno anónimo no prueba nada.
- `physical_signatures`: la firma manuscrita escaneada.
- `acceptances.ip_address`: es dato personal y es parte de la evidencia.
- `activity_logs.detalle` puede tener `user_email` en registros viejos. Se
  decidió **no** redactarlo: `activity_logs` es inmutable por diseño.

Si la purga sale de un pedido legal de supresión, eso es una supresión
**incompleta** y hay que decirlo, no asumir que alcanza.

### CUIT / CUIL

`users.dni` pasó a `users.cuit`. **Una sola columna para los dos**: CUIT y CUIL
tienen formato idéntico y el mismo dígito verificador; lo que cambia es la
semántica —CUIT para quien factura, CUIL para el empleado en relación de
dependencia— y eso se resuelve con la etiqueta de la UI según el rol.

Se valida con `App\Rules\Cuit`: **módulo 11 sobre el dígito verificador**, sin
consultar ARCA. Eso descarta los errores de tipeo, que son la mayoría. Verificar
que el CUIT *exista* y a nombre de quién es otra cosa y necesita credenciales y
homologación — sigue pendiente.

⚠️ **`users.dni_legacy` es temporal.** Un DNI no es un CUIT: los 8 dígitos de
`20068467` no se convierten sin prefijo ni verificador. La migración movió ahí
los valores que no pasaban la validación y dejó `cuit` en NULL, para que no
quedara un dato inválido haciéndose pasar por CUIT. **Cuando todos tengan su
CUIT cargado, esa columna se borra.**

Nullable por ahora: hay usuarios reales sin CUIT y un `NOT NULL` haría fallar la
migración. El camino es obligatorio en el formulario para altas nuevas, y
`NOT NULL` recién cuando los existentes estén completos.

### Nombre y apellido

Son **dos columnas** en la base, pero **un solo campo** en el formulario de
usuarios. Al guardar se parten por el primer espacio.

No se unificaron en la base a propósito: sería irreversible —"María del Carmen
Fernández López" no se puede volver a partir—, rompería las iniciales del avatar
y obligaría a tocar unos 15 archivos. Y para validar contra ARCA no hace falta:
`User::nombreCompleto()` ya concatena.

La heurística se equivoca con nombres compuestos ("Ana María López" → nombre
"Ana"), pero el dato completo nunca se pierde y se corrige editando.

### El email no es autoadministrable para socios y empleados

`AuthController::updateEmail()` devuelve 403 para `franquiciado` y `empleado`.
El email del socio comercial es su identificación legal en la red: es a donde
llegan las notificaciones y lo que queda asociado a cada aceptación. Si el
propio usuario puede cambiarlo, se corta la cadena entre "esta persona aceptó la
versión 2.1" y "esta persona es quien decimos que es".

Lo cambia un super_admin o el franquiciante desde `usuarios.php`, y esa
operación queda registrada (`password_reseteada_admin` tiene su equivalente para
la contraseña).

`perfil.php` además oculta la tarjeta, pero **eso es cosmético**: el guard del
backend es la restricción real. El CUIT no necesitó nada porque nunca tuvo
endpoint de autoservicio.

### Paginación

`renderPaginacion()` vive en **`layout.js`** y su CSS en **`panel.css`**. La
pantalla solo necesita `<div id="paginacion"></div>`, cortar con `slice` y
llamarla:

```js
renderPaginacion({
  total, pagina: paginaActual, porPagina: POR_PAGINA,
  onCambio: p => { paginaActual = p; renderTabla(); },
});
```

10 por página en las pantallas de gestión; **`log.php` usa 50 y su propia copia**
porque es de consulta y conviene ver mucho de una.

⚠️ **Al filtrar hay que volver a `paginaActual = 1`.** Si se estaba en la página
5 y el resultado tiene 2, el `slice` apunta a un rango inexistente y la tabla
sale **vacía sin ninguna explicación**. Es el bug clásico de agregar paginación
a una pantalla que no la tenía.

`manuales.php` corta **antes** de agrupar por empresa: si cortara después, se
armarían todas las filas para descartarlas, y `filas` incluye los encabezados de
grupo, así que "10 filas" no serían 10 manuales. Efecto asumido: una empresa
puede quedar partida entre dos páginas con el encabezado repetido.

### Cabeceras de seguridad (nginx)

Viven en `/etc/nginx/snippets/security-headers.conf`, **incluido en tres lugares**
del server block (ver §11 para el porqué): `X-Content-Type-Options: nosniff`,
`X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: strict-origin-when-cross-origin`,
`Permissions-Policy`. Más `server_tokens off` en `nginx.conf`.

`nosniff` es el que más importa acá, porque hay subida de archivos: sin él, un
archivo servido con el `Content-Type` equivocado puede terminar interpretado como
HTML y ejecutarse en el propio origen.

Pendiente: **HSTS** (es lo único que separa el A+ en SSL Labs) y **CSP**, ambos
en §9.

---

## 8. Convenciones de trabajo

### Line endings — importante
**El repositorio tiene EOL mixto por archivo.** No es prolijo, pero cambiarlo
masivamente generaría un diff inmanejable. Antes de editar un archivo,
**detectá su EOL y preservalo**.

| Archivo | EOL |
|---|---|
| `ManualController.php`, `NotificationController.php`, **`ManualNoteController.php`**, `PdfController.php`, `AuthController.php`, `AppServiceProvider.php`, `config/services.php`, `lectura.php`, `mis-manuales.php`, `api.php`, **`usuarios.php`**, **`js/layout.js`**, **`layout/topbar.php`**, **`layout/footer.php`**, **`layout/head.php`** | LF |
| `ManualImageController.php`, `ProfilePhotoController.php`, `NotificationObserver.php`, `FranchiseCategoryController.php`, `ManualCategoryAssignmentController.php`, `editor.php`, `manuales.php`, `manuales-mi-empresa.php`, `log.php`, `aceptaciones.php`, `categorias.php`, `documentos.php`, `login.html`, `styles/panel.css`, `styles/login.css` | CRLF |

⚠️ **`usuarios.php` es LF, no CRLF.** Esta tabla lo listaba mal y se detectó al
deduplicar el avatar. Un script que asuma CRLF ahí le mete `\r` a las 1.500
líneas del archivo. Moraleja: **la tabla es una ayuda, no la fuente de verdad**
— el script tiene que detectar el EOL del archivo, no confiar en esta lista.

Los archivos de `public/layout/` están mezclados.

### Cambios por script
Los cambios de código se aplican con **scripts Python** que usan anclas de string
únicas. Un script bien hecho:

1. Valida **todos** los anchors antes de escribir **nada** (si toca varios
   archivos, ninguno se modifica si uno falla).
2. Detecta y preserva el EOL de cada archivo.
3. Verifica el balance de llaves y paréntesis (excluyendo comentarios: un
   comentario con `1)` desbalancea el conteo).
4. Es **idempotente**: la segunda corrida aborta limpio.
5. Para JS, se valida con `node --check`; para PHP, con `php -l`. Sobre un
   temporal, y el temporal se borra siempre.
6. Deja `.bak` al lado del original. **Borrarlos antes del commit** — el
   `.gitignore` ya cubre `*.bak`, `*.orig`, `*.old`.

**Dos bugs que costaron una sesión entera, los dos por escribir Python de Unix
que se corre en Windows:**

- **`os.system('... > /dev/null 2>&1')` no funciona en `cmd`.** Interpreta
  `/dev/null` como una ruta, imprime "El sistema no puede encontrar la ruta
  especificada" y devuelve un código distinto de cero. El script lo lee como
  "el linter falló" y aborta **con el archivo perfectamente bien**. Usar
  `subprocess.run([...], stdout=PIPE, stderr=STDOUT)`, que no pasa por la shell.
- **El verificador de balance rompía con URLs.** Si se sacan los comentarios
  `//` **antes** que los strings, el `//` de `https://...` se come el resto de
  la línea —incluidos corchetes y la comilla de cierre—, descuadra las comillas
  siguientes y arrastra llaves de más abajo. Falso positivo sobre código
  correcto. Se resuelve con un scanner de estados carácter por carácter, no con
  regex. Ninguno de los dos apareció antes porque ningún patch previo invocaba
  un binario externo ni insertaba una URL.

Regla general: cuando el verificador dice que algo está roto, **confirmar que el
roto no es el verificador** antes de tocar el código.

Esa regla se ganó en un solo día, con tres falsos positivos seguidos, todos por
aplicar un patrón sobre el texto equivocado:

1. El `//` de una URL, al quitar comentarios antes que los strings.
2. `border: 1px` matcheando como `order: 1` — faltaba un límite de palabra.
3. Buscar `['borrador', 'publicado']` en el código **ya limpiado de strings**,
   donde por definición no puede aparecer.

Antes de creerle a un chequeo que falla: mirar **sobre qué texto** está mirando.

**Para borrar bloques grandes, no uses anclas literales.** Reproducir 55 líneas
de CSS a mano es una fuente de typos, y un typo ahí borra de más. Cortá entre
una marca de inicio y una de fin, y **antes de cortar verificá que el bloque no
contenga selectores o funciones ajenas**. Es más seguro que la alternativa.

### Frontend
- Sin build. Se edita el `.php` y listo.
- Globales disponibles en toda página: `API`, `BASE_PHP`, `apiFetch()`, `esc()`.
- `apiFetch` manda JSON. Para subir archivos hay que usar `fetch` crudo con
  `credentials: 'include'` y `FormData`.
- **Los modales no cierran al hacer clic afuera** (política de protección de
  datos). Excepción: los de solo lectura, como el lightbox de avatares.

---

## 9. Deuda técnica conocida

### ⚠️ La cadena de migraciones NO reconstruye la base

**Esto es lo más importante de este README.**

`php artisan migrate` sobre una base vacía **falla**. Varias tablas nunca
tuvieron migración de creación —se crearon a mano durante el desarrollo—, entre
ellas:

```
document_versions · manual_images · manual_user_assignments
manual_category_assignments · document_user_assignments
document_category_assignments · franchise_categories
user_categories · manual_notes
```

Además, algunas migraciones fueron editadas después de haberse ejecutado, así
que describen un esquema distinto al real (caso `chk_empresa_rol`).

**Consecuencia práctica:** la base se instala **desde un dump de estructura**, no
desde migraciones. Ver §10.

**Cómo salir de esto** (cuando haya tiempo): generar una **migración de línea
base** a partir del esquema actual —un solo archivo que cree las 31 tablas—,
vaciar `migrations` y registrar solo esa. A partir de ahí la cadena vuelve a ser
confiable.

Mientras tanto: **todo cambio de esquema nuevo va por migración**. Las que se
escribieron recientemente corren limpio; el problema es histórico.

### ⚠️ El frontend es incompatible con una CSP estricta

No está puesta, y **no alcanza con agregar el header**. Las 14 páginas usan
handlers y estilos inline por todos lados:

```html
<button onclick="hacerLogin()">
<div style="position:relative">
```

Una CSP sin `'unsafe-inline'` en `script-src` rompe la aplicación entera. Y
**con** `'unsafe-inline'` la CSP pierde casi todo su valor contra XSS, que es
justamente de lo que protegería.

CSP útil = refactor de todos los handlers inline a `addEventListener`. Es trabajo
real, no una línea de nginx. Mientras tanto va en
`Content-Security-Policy-Report-Only`, que reporta sin romper.

Cuando se escriba, tiene que incluir sí o sí:
- `worker-src 'self'` — lo necesita pdf.js
- `https://challenges.cloudflare.com` en `script-src` **y** `frame-src` — lo
  necesita Turnstile

Si se pasa a enforce sin eso y con `TURNSTILE_ENABLED=true`, **nadie puede
entrar**.

### ⚠️ Las fechas las escribe MySQL, no PHP

**Es la deuda más grande abierta.** Todas las tablas tienen
`DEFAULT CURRENT_TIMESTAMP` y `created_at` NO está en los `$fillable`, así que la
línea `'created_at' => now()` de `ActivityLog::registrar()` es **código muerto**:
`create()` la descarta en silencio y la columna la llena la base.

Consecuencia: el huso depende del servidor de base, no de la aplicación.

| | MySQL `NOW()` |
|---|---|
| Producción (RDS) | **UTC** |
| Local (XAMPP) | **Hora de Buenos Aires** |

La misma app guarda valores distintos según dónde corra. En producción las fechas
se muestran **tres horas adelantadas**; en local se ven bien.

Y hay un segundo problema encima: **`protected $dates` fue eliminado en Laravel
10+**. Los ~19 modelos que lo declaran creen estar casteando sus fechas y no
hacen nada. Sin cast, la fecha se serializa como string crudo de MySQL sin zona,
y JavaScript —ante un string con espacio en vez de `T`— la interpreta como hora
local.

**Lo que NO funciona, probado:**

- Castear a `datetime` sin arreglar la escritura: arregla producción y **rompe
  local**, porque ahí el valor guardado ya es hora local y el cast lo etiqueta
  como UTC.
- Cambiar `APP_TIMEZONE`: dejaría el histórico en un huso y lo nuevo en otro, con
  un salto de tres horas en el medio. En una herramienta cuyo valor es probar
  cuándo pasó cada cosa, es caro.

**El arreglo real:** que PHP escriba las fechas (`created_at` al `$fillable`, o
setter explícito), y recién entonces castear. PHP está en UTC en los dos
entornos, así que deja de importar la configuración de cada base. Hay que
revisar cada modelo con `DEFAULT CURRENT_TIMESTAMP` y decidir qué hacer con lo ya
guardado en producción.

Prioridad dentro de eso: **`Acceptance.aceptado_at`**, que es el timestamp que
prueba cuándo un socio aceptó una versión.

### Otras

- ~~**No hay recuperación de contraseña.**~~ **Resuelto.** Existen la tabla
  `password_resets`, `PasswordResetController`, `Jobs/EnviarRecuperacionPassword`
  y `Http/Concerns/VerificaTurnstile` compartido con el login. Esta sección decía
  lo contrario y se detectó al leer las FK de `users`.
- **El reseteo por admin sí está auditado.** `UserController::update()` acepta un
  `password` opcional; registra `password_reseteada_admin` con el actor en
  `user_id` y el afectado en `detalle.user_email`, y **revoca todas las sesiones**
  del usuario tocado. Esta sección pedía verificarlo: está hecho.
- ⚠️ **El franquiciante PUEDE resetear contraseñas de su empresa, y el
  franquiciado las de los empleados de su sucursal.** El backend nunca lo filtró
  por rol —solo por tenant— y desde julio de 2026 la UI también lo muestra. Antes
  el campo estaba oculto para el franquiciante: era un guard cosmético que hacía
  creer que la restricción existía.
- ⚠️ **`UserController::update()` no puede BORRAR `cuit` ni `celular`.** El
  `array_filter(..., fn($v) => $v !== null)` de la línea ~238 descarta los nulos
  explícitos, así que una vez cargados no se pueden vaciar desde el modal.
  `franquicia_id` sí se puede porque usa `array_key_exists`. Es el mismo caso que
  §11 documenta y quedó repetido en los otros dos campos.
- ⚠️ **`ManualNoteController::porManual()` no verifica acceso al manual.** El
  bloque de release notes filtra solo por `manual_id`, sin `empresa_id` ni
  `ManualAccessService`. Un socio comercial puede leer las `nota_publicacion` de
  **manuales de otras empresas** enumerando ids, con el autor incluido. El
  feedback ajeno sí está tapado (`where('user_id', $user->id)`). Es la misma
  familia que H-001 y H-009; el arreglo es el mismo guard que ya usa `store()`.
- **El `autor` de cada nota se serializa entero.** `User` solo tiene
  `password_hash` en `$hidden`, así que viajan email, CUIT, `dni_legacy`, celular
  y rol del autor. Al recortarlo, **no** usar `with('autor:id,nombre,...')`: el
  accessor `avatar_url` devuelve `null` y el avatar desaparece sin dar error. Va
  con `makeHidden([...])` sobre la relación ya cargada.
- **El contador de categorías todavía cuenta usuarios suspendidos** (`activo = 0`).
  Se excluyeron los eliminados; los suspendidos quedaron a propósito, pendiente de
  decisión.
- **La columna "Destinatario" de `documentos.php` no muestra los socios asignados
  individualmente.** `DocumentController::index()` no carga
  `document_user_assignments`. Un documento dirigido a un distribuidor puntual,
  sin categoría, sigue diciendo "Toda la empresa".
- **Typo en `usuarios.php`**: `cerrarModalEliminar()` empieza con
  `ocument.getElementById` (falta la `d`). El modal de eliminar no cierra ni con
  la X ni con el clic afuera, y el botón no se rehabilita.
- **El árbol de asignación de visibilidad está TRIPLICADO**: `manuales.php`,
  `manuales-mi-empresa.php` y `editor.php` tienen cada uno su copia, con
  variaciones (`sublistaUsuariosHTML` vs `sublistaUsuariosHTMLEditor`, `esc` vs
  `escNota`). Agregar la sucursal al lado del nombre requirió **tres patches
  para un cambio de una línea**. Cuando se unifique, comparar las tres antes:
  como con el lightbox, alguna puede manejar un caso que las otras no.
- **`users.dni_legacy` hay que borrarla** cuando todos los usuarios tengan su
  CUIT cargado. Es una migración de una línea.
- **La validación contra ARCA (CUIT) y ANSES (CUIL) no está.** Hoy solo se
  valida el dígito verificador localmente. La consulta real necesita
  credenciales, certificado y homologación.
- **`mostrarToast` está duplicada** en al menos cuatro páginas (`aceptaciones.php`,
  `categorias.php`, `documentos.php`...). Mismo caso que el lightbox de avatares:
  conviene moverla a `layout.js`, comparando las copias antes de unificar por si
  divergieron.
- **HSTS no está puesto.** Es lo único que separa el A (actual) del A+ en SSL
  Labs. La redirección HTTP→HTTPS ya funciona (301), pero queda la primera
  petición interceptable. Se agrega al snippet de headers y **se escala**:
  `max-age=300`, verificar un par de días, después subir a un año. Un `max-age`
  largo mal puesto no se puede revertir para quien ya lo cacheó.
  `includeSubDomains` aplica al host que envía el header y sus subdominios —
  desde `businesspartner.goharv.com.ar` **no** afecta a `goharv.com.ar` ni al
  sitio institucional.
- Los intentos de login contra **emails inexistentes no se registran**:
  `activity_logs.user_id` es `NOT NULL`. La enumeración de emails queda invisible.
  Mismo motivo por el que no se loguea un captcha rechazado.
- No existe forma de **obligar** el cambio de contraseña en el primer ingreso.
  Es una convención, no una regla.
- El bloqueo de F12 / DevTools en `lectura.php` **no funciona** en navegadores
  modernos. Se deja como fricción, no cuenta como protección.
- ~~El código del lightbox de avatares está duplicado en `usuarios.php` y
  `log.php`.~~ **Resuelto.** El CSS vive en `panel.css`, y `avatarUsuario()`,
  `inicialesDeUsuario()`, `abrirFotoPerfil()`, `cerrarFotoPerfil()` y el
  listener delegado viven en `layout.js`. Al unificar se descubrió que las dos
  copias **ya habían divergido**: la de `log.php` manejaba `u` nulo y el nombre
  sin apellido, la de `usuarios.php` no. Se tomó la de `log.php`, que era
  superset.
- **La foto de perfil todavía no se muestra en las notas ni en aceptaciones.**
  El frontend está listo (`avatarUsuario()` es global), pero falta confirmar que
  la API mande `avatar_url` en esos objetos anidados. `avatar_url` es un accessor
  sobre `foto_url`: si la relación se serializa con lista de columnas —como
  `with('user:id,nombre,apellido,email,rol')`— el accessor devuelve null y el
  avatar **nunca aparece, sin dar error**: cae al fallback de iniciales y parece
  que el frontend no anda. Verificar antes de tocar nada.
- **El avatar del super_admin no lo ve nadie más.** `ProfilePhotoController::ver()`
  autoriza a uno mismo, a la misma empresa o al super_admin — y el super_admin
  tiene `empresa_id` NULL, así que para franquiciado y empleado su foto siempre
  cae a iniciales. Es la decisión de V2-H-004 y es correcta, pero limita el valor
  de mostrar avatares en las notas: los manuales suelen publicarlos super_admins.
- Los escaneos externos (SSL Labs, securityheaders.com) **no miran nada de la
  aplicación**. Un A+ es compatible con que un `franquiciado` lea manuales de
  otra empresa. La superficie real está en los dos caminos de autenticación (§1)
  y en `ManualAccessService`, y se audita leyendo código.
- **Las imágenes de los manuales viven en base64 dentro de `contenido_html`.**
  Medición real: un manual con 4 imágenes daba 1.114.240 caracteres contra ~6.000
  de los que no tienen ninguna. Eso reventaba el PDF (§11) y hoy está parcheado
  subiendo `pcre.backtrack_limit`, que corre el techo sin eliminarlo. El arreglo
  de fondo son dos trabajos: que Mammoth suba las imágenes a `manual_images` al
  importar en vez de embeberlas, y una migración que extraiga los data URI ya
  guardados.
- **`resolverImagenes()` puede devolver null en silencio.** Las funciones `preg_*`
  no lanzan excepción al pasarse del límite de PCRE: devuelven `null`. Esa función
  declara `: string`, así que sería un `TypeError`. Hoy no se dispara porque un
  HTML con base64 no contiene `manuales-imagenes` y corta antes del regex, pero un
  manual grande que sí use imágenes del endpoint entraría por ahí. Falta un guard.
- **No existe el usuario `manuales_deploy`.** El §10 lo da por hecho; en producción
  las migraciones se corrieron con `dbmaster`. Crearlo con permisos acotados.

---

## 10. Instalación

### Desarrollo (XAMPP)

```bash
git clone <repo> && cd manuales-franquiciantes
composer install
cp .env.example .env
php artisan key:generate
```

Crear la base e importar el dump de estructura (**no** correr `migrate`):

```sql
CREATE DATABASE manuales_operativos_db
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```bash
mysql -u root manuales_operativos_db < base_produccion.sql
```

En el `.env` local:

```
DB_DATABASE=manuales_operativos_db
FILESYSTEM_DISK=local
MAIL_MAILER=log
SESSION_SECURE_COOKIE=false     # XAMPP es HTTP: sin esto no viaja la cookie
```

```bash
mkdir -p storage/app/mpdf-tmp
php artisan config:clear
```

La app queda en `http://localhost/manuales-franquiciantes/public/`.

### Producción

**Infraestructura:** AWS EC2 con Ubuntu, nginx 1.24 + PHP-FPM 8.3, TLS por
Certbot (renovación automática). La app vive en
`/var/www/franchising-manager`, con document root en `public/`.

Archivos de configuración que hay que conocer:

| Archivo | Qué tiene |
|---|---|
| `/etc/nginx/sites-enabled/businesspartner` | el server block del sitio |
| `/etc/nginx/snippets/security-headers.conf` | las 4 cabeceras de seguridad (§11) |
| `/etc/nginx/mime.types` | **acá va `mjs`** (§11) |
| `/etc/nginx/nginx.conf` | `server_tokens off` |
| `/var/www/franchising-manager/.env` | `640 www-data:www-data` — hace falta `sudo` |

El server block ya trae, además de los headers: bloqueo de `/layout/`, de
dotfiles, y de extensiones sensibles (`sql|log|bak|old|orig|save|tmp|swp|ini|sh|env|pem|key|dist`)
con `return 404`. También `fastcgi_hide_header X-Powered-By`, que hace
innecesario tocar `expose_php`.

Deploy: `git pull` + `config:cache` + `reload php8.3-fpm`. El `.env` **no** viaja
por git: cualquier variable nueva hay que ponerla a mano en el servidor.

```bash
mysql -u <usuario_con_ddl> -p manuales_prod < base_produccion.sql
```

El dump ya incluye estructura + datos iniciales (3 planes, la empresa, 5
sucursales, 4 usuarios) + las 59 filas de `migrations`. **Esa última parte es
clave**: sin ella Laravel intentaría correr todas las migraciones y fallaría.

Verificación obligatoria:

```bash
php artisan migrate        # debe decir "Nothing to migrate"
```

**Dos usuarios de base de datos:**

| Usuario | Permisos | Dónde vive |
|---|---|---|
| `manuales_user` | SELECT, INSERT, UPDATE, DELETE | en el `.env` de la app |
| `manuales_deploy` | + DDL | **solo** en la línea de comando al migrar |

```bash
DB_DEPLOY_USERNAME=manuales_deploy DB_DEPLOY_PASSWORD=xxx \
  php artisan migrate --database=mysql_deploy
```

### Checklist de deploy

- [ ] `APP_KEY` **nueva** (no la de desarrollo)
- [ ] `APP_DEBUG=false`
- [ ] `SANCTUM_STATEFUL_DOMAINS` y `SESSION_DOMAIN` = el dominio real
- [ ] `SESSION_SECURE_COOKIE=true` y `SESSION_ENCRYPT=true`
- [ ] `TrustProxies` configurado (detrás de balanceador)
- [ ] `FILESYSTEM_DISK=s3` + bucket **privado**
- [ ] `CACHE_STORE=database` (el rate limiter necesita contador compartido)
- [ ] Worker de colas corriendo — lo administra **supervisor**, no systemd:
      `sudo supervisorctl status businesspartner-worker`
- [ ] `upload_max_filesize` y `post_max_size` ≥ 50M
- [ ] `mkdir storage/app/mpdf-tmp`
- [ ] `public/js/pdfjs/` desplegado (1,75 MB, no debe estar en `.gitignore`)
- [ ] **`mjs` agregado en `/etc/nginx/mime.types`** — sin esto el visor de PDF no
      arranca aunque el archivo cargue con 200 (§11). Un `apt upgrade` puede
      pisar ese archivo y el problema vuelve sin aviso.
- [ ] `TURNSTILE_ENABLED=true` + `TURNSTILE_SECRET_KEY` en el `.env`, y el
      hostname del dominio cargado en el widget de Cloudflare
- [ ] `include snippets/security-headers.conf` en los **tres** lugares (§11)
- [ ] `server_tokens off` en `nginx.conf`
- [ ] Ningún `.bak` / `.orig` / `.old` en `public/`
- [ ] CSP: `worker-src 'self'` + `challenges.cloudflare.com` antes de pasarla a
      enforce (§9)
- [ ] `php artisan config:cache`
- [ ] `systemctl reload php8.3-fpm` — el opcache no se limpia solo

**Sin el worker de colas los mails no salen nunca, y no hay ningún error
visible.** Es el fallo más silencioso de la lista.

### El worker lo administra supervisor, NO systemd

Programa `businesspartner-worker`, configuración en `/etc/supervisor/conf.d/`.

```bash
sudo supervisorctl status
sudo supervisorctl restart businesspartner-worker
```

Buscarlo con `systemctl status laravel-worker` **no lo encuentra**, y eso lleva a
concluir que no está supervisado y a crear una unidad de systemd duplicada. Ya
pasó. Para verificarlo de verdad: `supervisorctl status`, o mirar el PPID del
proceso — si el padre es el PID de `supervisord`, está cubierto.

El `--max-time=3600` hace que el worker se cierre solo cada hora, lo cual es
correcto (evita fugas de memoria) **porque supervisor lo relanza**. Si algún día
se administrara a mano, esa opción se vuelve una bomba de tiempo.

### Secuencia de un deploy con migración

**El orden importa: la migración va ANTES del `git pull`.** Si el código sube
primero, cualquier endpoint que use la columna nueva tira 500 hasta que exista.

```bash
cd /var/www/franchising-manager

# 1. config:clear ANTES de migrar. Con bootstrap/cache/config.php presente,
#    Laravel no evalúa config/database.php, así que DB_DEPLOY_USERNAME y
#    DB_DEPLOY_PASSWORD se IGNORAN y la migración corre como manuales_user →
#    "ALTER command denied". No da ninguna pista de que la causa es la caché.
sudo -u www-data php artisan config:clear

# 2. Migrar con el usuario de DDL. El espacio inicial mantiene la contraseña
#    fuera del historial si HISTCONTROL=ignorespace.
 DB_DEPLOY_USERNAME=... DB_DEPLOY_PASSWORD='...' \
   sudo -u www-data -E php artisan migrate --database=mysql_deploy

# 3. Recién ahora el código.
git pull

# 4. sudo -u www-data, NO sudo a secas: con sudo el archivo de caché queda
#    con dueño root y después www-data no puede reescribirlo.
sudo -u www-data php artisan config:cache

# 5. El opcache no se limpia solo: sin esto PHP-FPM sirve el código viejo.
sudo systemctl reload php8.3-fpm
```

Verificación: `sudo -u www-data php artisan migrate` debe decir
"Nothing to migrate".

**Si algo falla y hay que hacerlo a mano** (SQL directo contra RDS), no olvidar
registrar la migración, o el próximo deploy la reintenta y falla porque la
columna ya existe:

```sql
INSERT INTO migrations (migration, batch)
VALUES ('<nombre_sin_.php>', (SELECT * FROM (SELECT MAX(batch) FROM migrations) t));
```


### Verificación post-deploy

```bash
# headers en los tres caminos: PHP, estático y pdfjs
for u in /login.html /styles/style.css /js/pdfjs/pdf.min.mjs; do
  echo "--- $u"
  curl -sI https://businesspartner.goharv.com.ar$u \
    | grep -i "x-content\|x-frame\|referrer\|permissions"
done

# el .mjs NO debe salir como octet-stream
curl -sI https://businesspartner.goharv.com.ar/js/pdfjs/pdf.min.mjs | grep -i content-type

# los .bak deben dar 404
curl -sI https://businesspartner.goharv.com.ar/login.html.bak | head -1
```

Y a mano, en ventana de incógnito: **contraseña mal una vez, después bien** (§7).

---

## 11. Trampas conocidas (leer antes de debuggear)

Cada una costó tiempo real:

**Imágenes que se ven en pantalla pero no en el PDF** → caché del navegador
tapando que el archivo no existe en el servidor.

**Cambiaste `Cache-Control` y sigue mostrando lo viejo** → el navegador no
vuelve a pedir el recurso mientras su copia siga "fresca" según las reglas
anteriores. Hay que limpiar la caché una vez. Truco de diagnóstico: agregá
`?x=123` a la URL — si así funciona, el servidor está bien y el problema es la
caché.

**`php artisan optimize:clear` no limpia el opcache de PHP.** En XAMPP hay que
reiniciar Apache para que tome el código nuevo.

**Algo falla solo con F12 abierto** → revisá el dropdown de throttling en la
pestaña Network. Modo "Offline" activado.

**`.htaccess` no funciona en producción** → producción es nginx, que lo ignora
por completo. Cualquier protección tiene que ser portable (guard en PHP o en el
server block).

**pdf.js carga con 200 pero el visor no arranca** → nginx sirve `.mjs` como
`application/octet-stream` y el navegador **rechaza el módulo**: para scripts de
tipo módulo el chequeo de MIME es estricto y no negociable. Consola:
*"Expected a JavaScript-or-Wasm module script"*. Despista mucho porque en Network
el archivo figura **200 y completo** — llegó bien, lo que falló es la ejecución.
Se arregla agregando `mjs` a la línea de `application/javascript` en
`/etc/nginx/mime.types`. Y después **vaciar caché y recargar forzado**: `Ctrl+F5`
solo no invalida módulos ES.

Corolario de diagnóstico: si `/api/manuales/N/archivo` entrega el PDF pero
`lectura.php` no lo muestra, **el archivo está**. El problema es del visor, no
del storage ni de la base.

**`add_header` no se hereda en un `location` que tenga su propio `add_header`.**
Es la trampa más silenciosa de la config de nginx. Basta un
`add_header Cache-Control ...` en el location de estáticos para que **todos** los
headers de seguridad del bloque `server` desaparezcan en esas rutas. Sin error,
sin aviso. `always` **no** arregla esto (`always` es otra cosa: manda el header
también en respuestas 4xx/5xx).

Por eso los headers viven en un snippet incluido en **tres** lugares: el `server`,
`location ^~ /js/pdfjs/` y el location de estáticos. Verificar solo contra la
home da todo verde con la mitad del sitio desprotegido.

**Si `nginx -t` falla, no corras el `reload`.** No rompe nada —el nginx en
memoria sigue con la config vieja y el sitio sigue en pie— pero el error del
reload es mucho menos claro que el del `-t`, y te manda a buscar al lugar
equivocado. El `-t` te dice archivo y línea.

**Un pedido sin autenticar a `/api/*` devolvía 500, no 401** → Laravel intenta
redirigir a la ruta llamada `login`, que en un backend headless no existe, y eso
revienta como `RouteNotFoundException`. Resuelto con un `render()` en
`bootstrap/app.php` que lo convierte en 401. **Este bug llevaba tiempo invisible
y lo encontró la tabla `error_logs` antes de tener pantalla**: sin ella, cada
sesión vencida generaba un 500 que nadie veía.

**Una columna nullable en la base no alcanza si la firma del método declara el
tipo estricto** → `activity_logs.user_id` pasó a nullable, pero
`ActivityLog::registrar()` seguía declarando `int $userId`. Pasarle `null` daba
`TypeError`, que el `try/catch` de `logLoginFallido()` se comía en silencio: la
columna ya aceptaba NULL y seguía sin registrarse nada. Si algo "no guarda" y no
hay error visible, **forzalo desde `tinker`** — ahí sí se ve.

**`throw` dentro de tinker NO dispara el hook de errores** → tinker atrapa la
excepción en su propio bucle y no pasa por el manejador de Laravel. Para probar
el registro hay que usar `report(new \RuntimeException('...'))`.

**`mostrarToast` y los estilos de modal NO son globales** → cada página define los
suyos en su propio `<style>` y su propio `<script>`. `panel.css` tiene las
tarjetas, la tabla y el layout; pero `.modal-overlay`, `.accion-pill`,
`.accion-btn`, los tabs y `mostrarToast` viven duplicados por página. Asumir que
son globales produce una pantalla a medio estilar y botones que no refrescan la
vista (la función revienta antes del `await`).

**`nano archivo` abre un buffer vacío en vez del archivo** → no tenés permiso de
lectura. El `.env` es `640 www-data:www-data`: hace falta `sudo`. Si guardás ese
buffer creás un archivo nuevo y el original queda intacto — el síntoma es que
nano pregunta "File Name to Write". Después de editar con sudo, verificar que el
dueño no haya cambiado a `root`.

**500 en producción sin ninguna pista** → `APP_DEBUG=false` reemplaza la pantalla
de excepción por un "500 SERVER ERROR" genérico, y está bien que así sea. El
error está en el log, pero con `LOG_CHANNEL=daily` el archivo se llama
`laravel-AAAA-MM-DD.log`, no `laravel.log`. Si `ls -ld storage/logs` muestra una
mtime reciente, el log se escribió: estás mirando el nombre equivocado.

**`MpdfException: The HTML code size is larger than pcre.backtrack_limit`** →
mPDF procesa todo el HTML con expresiones regulares y PHP corta el backtracking
de PCRE en 1.000.000. No es un problema del entorno: es el tamaño del
`contenido_html`, inflado por imágenes en base64. Parcheado con
`ini_set('pcre.backtrack_limit', '10000000')` en `PdfController::generar()`,
puesto **antes de `resolverImagenes()`** y no solo antes de `WriteHTML()`: esa
función también corre un `preg_replace_callback` sobre el mismo HTML.

**`--database=mysql_deploy` corre como `manuales_user`** → la config está
cacheada. Ver §10.

**Una función nueva en `layout.js` puede PISAR una local del mismo nombre.**
Como `layout.js` carga al final, su declaración gana. Si las firmas difieren, la
página revienta con un error que no menciona la colisión: pasó al agregar
`renderPaginacion()` compartida, que pisó la de `log.php` —misma nombre, otra
firma— y dejó el log con "Error al cargar" sin más pistas. **Antes de agregar
algo a `layout.js`, buscar ese nombre en todo `public/`.**

**Un campo que no se envía no se puede borrar.** En `usuarios.php` había
`if (franqId) body.franquicia_id = franqId;`: al elegir "sin sucursal" el campo
no viajaba, el backend no lo veía en el request y no lo tocaba. Se podía cambiar
de sucursal pero nunca quitarla. Mismo caso que las asignaciones de usuarios en
el modal de manuales: **si un campo puede quedar vacío, hay que mandarlo igual**,
como `null` o como lista vacía.

**`layout.js` carga DESPUÉS de los `<script>` de cada página** (`footer.php`).
Cualquier función que se mueva a `layout.js` no existe todavía para código que
corra durante el parseo: las páginas tienen que arrancar con
`document.addEventListener('DOMContentLoaded', ...)`, no con una llamada directa.
El síntoma es un `ReferenceError` que el `catch` de la página convierte en su
mensaje de error genérico, así que no parece un problema de orden de carga.

Y **`layout.js` NO se puede mover a `head.php`** para "arreglarlo": `topbar.php`
tiene un `<script>` inline que documenta que sus funciones deben estar
disponibles *antes* de que cargue `layout.js`. Invertir el orden rompe el
sidebar y deja el topbar sin contenido.

**`sudo php artisan ...` deja los archivos con dueño `root`** → después
PHP-FPM (que corre como `www-data`) no puede reescribir
`bootstrap/cache/config.php`. Anda hasta que algo necesita escribir, y ahí falla
en el peor momento. Siempre `sudo -u www-data`.

**`$_ENV` vacío en producción** → depende de `variables_order` en php.ini, que
por defecto no incluye el entorno. Usar `getenv()`.

**mPDF ignora `max-width` en imágenes de header/footer.** Solo respeta el
atributo `width="55mm"`. Y necesita `allow_local_files => true`.

**mPDF no lee de S3.** Necesita un archivo en disco: hay que descargar el objeto
a un temporal (`PdfController::rutaLocalDeImagen`).

**`withTrashed()` no funciona en `DocumentVersion`**: maneja `deleted_at` a mano,
sin el trait SoftDeletes. Usar `where()` común.

**`execCommand('insertImage')` en Chrome** convierte las URLs a base64 en
silencio. Hay que usar `createElement('img')` + `range.insertNode()`.

---

## 12. Mapa de archivos

```
app/
├── Http/Controllers/
│   ├── AuthController.php           login, logout, /me, cambio de credenciales
│   ├── ManualController.php         CRUD, publicar, archivo PDF, notificaciones
│   ├── PdfController.php            genera el PDF con mPDF
│   ├── ManualImageController.php    imágenes del editor + limpieza de huérfanas
│   ├── DocumentController.php       documentos y sus versiones
│   ├── AcceptanceController.php     aceptación digital
│   ├── NotificationController.php   listado + resolución de deep-links
│   ├── ErrorLogController.php       errores 5xx (solo super_admin)
│   ├── PasswordResetController.php  recuperación de contraseña
├── Rules/Cuit.php                   validación de CUIT/CUIL (módulo 11)
├── Jobs/EnviarRecuperacionPassword.php
├── Http/Concerns/VerificaTurnstile.php  compartido login + recuperación
│   └── ...
├── Models/                          Manual, ManualVersion, User, Empresa...
├── Observers/NotificationObserver.php   dispara los emails
├── Services/ManualAccessService.php     ⭐ quién ve qué manual
└── Providers/AppServiceProvider.php     rate limiters

public/
├── login.html                       entrada
├── dashboard.php                    panel del super_admin
├── manuales.php                     listado (super_admin)
├── manuales-mi-empresa.php          listado (franquiciante)
├── editor.php                       editor WYSIWYG
├── lectura.php                      ⭐ lectura + registro de lectura + visor PDF
├── mis-manuales.php                 cola del socio comercial
├── documentos.php / usuarios.php / franquicias.php / log.php ...
├── layout/                          config, auth, head, topbar, sidebar, footer
└── js/pdfjs/                        pdf.js auto-hospedado

database/
├── migrations/                      ⚠️ ver §9: no reconstruye desde cero
└── seeders/ProduccionInicialSeeder.php
```

---

## 13. Para una IA que retome el proyecto

Lo que más ayuda a no romper nada:

1. **Leé §5, §6 y §9 antes de escribir código.** Los constraints y el
   `$fillable` fallan en silencio; la cadena de migraciones no es confiable.
2. **Pedí el archivo actual antes de editarlo.** Reconstruirlo de memoria o
   asumir su contenido genera anchors que no matchean. Pasó varias veces.
3. **Preservá el EOL** de cada archivo (§8).
4. **Un cambio lógico por script**, con validación previa a la escritura.
5. **No hay PHP en el entorno de asistencia**: la lógica se verifica con
   réplicas en Python y el JS con `node --check`. Decilo cuando no puedas
   verificar algo en runtime, en vez de afirmar que funciona.
6. **`ManualAccessService` es la fuente de verdad** de quién ve qué. No
   reimplementes el filtro.
7. Cuando algo "no se actualiza" en el navegador, sospechá de la caché **antes**
   que del código (§11).
8. **Los scripts se corren en Windows.** Nada de `/dev/null`, `os.system` ni
   rutas con `/`. `subprocess.run` y `os.path.join` (§8).
9. **Pedí la config antes de opinar sobre la config.** En esta sesión se afirmó
   que los `.bak` estaban expuestos en producción y que hacía falta cambiar la
   `Referrer-Policy`: las dos cosas eran falsas, y se vio al leer el server block
   —que ya bloqueaba los `.bak`— y al revisar qué manda `strict-origin-when-cross-origin`.
   Vale igual para las herramientas: SSL Labs no mide cabeceras HTTP, eso es
   securityheaders.com. Confundirlas produce diagnósticos coherentes sobre
   premisas falsas.
10. **Antes de tocar el código porque un verificador se queja, confirmá que el
    roto no sea el verificador** (§8).
11. **Un refactor de deduplicación no es neutro por defecto.** Antes de unificar
    dos copias, compará las dos: en este proyecto ya habían divergido y una era
    superset de la otra. Unificar en la equivocada rompe una pantalla en silencio.
12. **En este proyecto casi nada es global.** `mostrarToast`, los estilos de
    modal, los botones de acción y las píldoras están duplicados por página.
    Antes de usar una clase o una función "que ya existe", confirmá dónde está
    definida: `panel.css` y `layout.js` tienen menos de lo que parece.
13. **Cuando el frontend "no anda", verificá que el dato llegue.** Varios bugs de
    hoy se veían como fallas de UI y eran de backend o de infraestructura: el MIME
    de `.mjs`, `avatar_url` que no viaja en relaciones con `select`, el contador
    inflado. El fallback silencioso a un valor por defecto es el patrón que los
    disfraza.

---

*Documento generado en julio de 2026, actualizado el 29/07/2026.*

*Primera tanda: migración a AWS, Turnstile, cabeceras de seguridad,
`estado_previo`, avatares compartidos, registro de errores en base.*

*Segunda tanda (29/07): **el cumplimiento pasó a ser la firma física** y la
aceptación digital se convirtió en registro de lectura al abrir; purga de datos
personales; manuales PDF reactivados con versionado completo; tipo de cambio en
documentos; notificación `nota_manual` al franquiciante; filtro de categorías en
usuarios; borrado de categorías; orden por defecto de manuales por recencia. Se
corrigieron cuatro afirmaciones falsas de este documento: `login_bloqueado` en
`chk_notif_fk`, los manuales PDF como pausados, la recuperación de contraseña
como inexistente, y el badge de notificaciones como pendiente.*

*Si el sistema cambió, este README también debería.*