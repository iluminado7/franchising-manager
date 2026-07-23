# Manuales Franquiciantes — Business Partner

Plataforma multi-tenant para que una empresa franquiciante redacte, publique y
distribuya sus **manuales operativos**, y para que sus socios comerciales los
lean y los **acepten con registro**. La aceptación es el corazón del sistema: no
es un gestor de documentos, es una herramienta de **cumplimiento** — lo que
importa es poder demostrar quién leyó y aceptó qué versión de qué manual, y
cuándo.

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
| PHP | 8.2+ |
| Laravel | 12 |
| MySQL | 8.0.45 (no subir de versión mayor: se usan CHECK constraints y columnas generadas) |
| Auth | Laravel Sanctum, token en cookie `HttpOnly` + `SameSite=Strict` |
| PDF (generar) | mPDF |
| PDF (mostrar) | pdf.js 4.10.38, auto-hospedado en `public/js/pdfjs/` |
| Importar Word | Mammoth.js (browser) |
| Sanitización HTML | HTMLPurifier |
| Mail | Resend (producción) / `log` (desarrollo) |
| Storage | Disco por configuración: `local` en dev, `s3` en producción |
| Local | XAMPP — `C:/xampp/htdocs/manuales-franquiciantes/` |

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
| `nuevo_manual` | `manual_id` |
| `modificacion_manual`, `manual_asignado`, `acceso_anomalo_pdf` | `manual_version_id` |
| `nuevo_documento`, `documento_asignado` | `document_id` |
| `nueva_version_documento` | `document_version_id` |
| `manual_asignado_categoria` | `manual_id` + `category_id` |
| `documento_asignado_categoria` | `document_id` + `category_id` |
| `recordatorio_pendiente`, `login_bloqueado` | **ninguna** (todas NULL) |

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

### Manuales en PDF — ⏸ PAUSADO
Permite subir un PDF y publicarlo sin convertirlo. **Está deshabilitado**:
`PDF_MANUALES_HABILITADO = false` en `manuales.php` y `manuales-mi-empresa.php`.

Se pausó porque no cerró la experiencia de lectura ni el valor de la aceptación
(el socio puede descargar el archivo igual). **Todo el backend sigue funcionando**
y hay manuales PDF publicados: para retomarla alcanza con poner la constante en
`true`.

El visor (`pdf.js` renderizando a canvas, sin capa de texto, con marca de agua
superpuesta) quedó terminado y funcionando.

### Documentos
Subida y versionado de archivos, sin aceptación. Es el lugar correcto para
material que **no** requiere firma.

### Aceptaciones
Digital (el socio confirma en pantalla, se registra contra el `documento_hash`) o
física (el franquiciante sube el PDF firmado). Se consultan en `aceptaciones.php`.

### Notificaciones
In-app (badge en la topbar) + email vía un **observer** de `Notification`. La
whitelist de tipos que disparan mail está en
`app/Observers/NotificationObserver.php` → `TIPOS_CON_EMAIL`.

`NotificationController::resolverDestino()` calcula **en el backend** a dónde
lleva cada notificación y si el recurso sigue disponible. El frontend no decide
eso.

### Registro de actividad
`activity_logs` guarda logins, publicaciones, accesos a archivos y accesos
anómalos. Se consulta en `log.php`.

### Seguridad de acceso a archivos
- Nada se sirve por URL directa del bucket: todo pasa por endpoints autenticados.
- El archivo de un manual PDF se entrega con un **token opaco, cifrado, atado al
  usuario y con vencimiento de 60 min** (`/manuales/archivo/{token}`).
- `lectura.php` navega con un **ULID público** (`?m=01K0S7...`), no con el ID de
  la base.

---

## 8. Convenciones de trabajo

### Line endings — importante
**El repositorio tiene EOL mixto por archivo.** No es prolijo, pero cambiarlo
masivamente generaría un diff inmanejable. Antes de editar un archivo,
**detectá su EOL y preservalo**.

| Archivo | EOL |
|---|---|
| `ManualController.php`, `NotificationController.php`, `PdfController.php`, `lectura.php`, `mis-manuales.php`, `api.php` | LF |
| `ManualImageController.php`, `ProfilePhotoController.php`, `NotificationObserver.php`, `editor.php`, `usuarios.php`, `manuales.php`, `manuales-mi-empresa.php`, `log.php`, `aceptaciones.php`, `panel.css` | CRLF |

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
5. Para JS, se valida con `node --check` sobre el bloque extraído.

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

### Otras

- Los intentos de login contra **emails inexistentes no se registran**:
  `activity_logs.user_id` es `NOT NULL`. La enumeración de emails queda invisible.
- No existe forma de **obligar** el cambio de contraseña en el primer ingreso.
  Es una convención, no una regla.
- El bloqueo de F12 / DevTools en `lectura.php` **no funciona** en navegadores
  modernos. Se deja como fricción, no cuenta como protección.
- El código del lightbox de avatares está duplicado en `usuarios.php` y
  `log.php`. Si aparece en una tercera pantalla, conviene moverlo a `layout.js`.

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
- [ ] Worker de colas corriendo: `php artisan queue:work` supervisado
- [ ] `upload_max_filesize` y `post_max_size` ≥ 50M
- [ ] `mkdir storage/app/mpdf-tmp`
- [ ] `public/js/pdfjs/` desplegado (1,75 MB, no debe estar en `.gitignore`)
- [ ] CSP: `worker-src 'self'` antes de pasarla a enforce (lo necesita pdf.js)
- [ ] `php artisan config:cache`

**Sin el worker de colas los mails no salen nunca, y no hay ningún error
visible.** Es el fallo más silencioso de la lista.

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

**`.htaccess` no funciona en producción** → Laravel Cloud usa nginx, que lo
ignora por completo. Cualquier protección tiene que ser portable (guard en PHP).

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
├── lectura.php                      ⭐ lectura + aceptación + visor PDF
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

---

*Documento generado en julio de 2026. Si el sistema cambió, este README también
debería.*
