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
| `ManualController.php`, `NotificationController.php`, `PdfController.php`, `AuthController.php`, `AppServiceProvider.php`, `config/services.php`, `lectura.php`, `mis-manuales.php`, `api.php` | LF |
| `ManualImageController.php`, `ProfilePhotoController.php`, `NotificationObserver.php`, `editor.php`, `usuarios.php`, `manuales.php`, `manuales-mi-empresa.php`, `log.php`, `aceptaciones.php`, `login.html`, `panel.css` | CRLF |

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

### Otras

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
- El código del lightbox de avatares está duplicado en `usuarios.php` y
  `log.php`. Si aparece en una tercera pantalla, conviene moverlo a `layout.js`.
- Los escaneos externos (SSL Labs, securityheaders.com) **no miran nada de la
  aplicación**. Un A+ es compatible con que un `franquiciado` lea manuales de
  otra empresa. La superficie real está en los dos caminos de autenticación (§1)
  y en `ManualAccessService`, y se audita leyendo código.

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
- [ ] Worker de colas corriendo: `php artisan queue:work` supervisado
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

**`nano archivo` abre un buffer vacío en vez del archivo** → no tenés permiso de
lectura. El `.env` es `640 www-data:www-data`: hace falta `sudo`. Si guardás ese
buffer creás un archivo nuevo y el original queda intacto — el síntoma es que
nano pregunta "File Name to Write". Después de editar con sudo, verificar que el
dueño no haya cambiado a `root`.

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

---

*Documento generado en julio de 2026, actualizado el 27/07/2026 (migración a
AWS, Turnstile, cabeceras de seguridad). Si el sistema cambió, este README
también debería.*