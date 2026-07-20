<?php

namespace App\Http\Controllers;

use HTMLPurifier;
use HTMLPurifier_Config;
use App\Http\Controllers\ManualImageController;
use App\Models\Manual;
use App\Models\ManualVersion;
use App\Models\ManualEmpresaAssignment;
use App\Models\Notification;
use App\Models\ActivityLog;
use App\Services\ManualAccessService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

class ManualController extends Controller
{
    // GET /api/manuales
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Super admin puede ver TODOS los eliminados (incluyendo los borrados por él) con ?include_deleted=1
        $includeDeleted = (bool) $request->query('include_deleted', false);

        $manuales = match($user->rol) {

            // Super admin: por defecto ve los no-eliminados + los eliminados por franquiciantes
            // (para él, el borrado de un franquiciante no es definitivo).
            // Con ?include_deleted=1 ve también los eliminados por super_admin.
            //
            // Fix aceptaciones: si el super_admin envía ?empresa_id=X, filtramos los
            // manuales asignados a esa empresa. Sin el param, comportamiento anterior
            // (todos los manuales del sistema con empresa como metadata).
            'super_admin' => Manual::with(['versionActiva', 'empresasAsignadas'])
                       ->when(!$includeDeleted, fn($q) => $q->visiblesParaSuperAdmin())
                       ->when($request->filled('empresa_id'), fn($q) =>
                           $q->whereHas('empresasAsignadas', fn($e) =>
                               $e->where('empresa_id', (int) $request->query('empresa_id'))
                           )
                       )
                       ->orderBy('orden')
                       ->get()
                       ->map(function ($manual) {
                           $empresa = $manual->empresasAsignadas->first();
                           $manual->empresa_id = $empresa?->id;
                           $manual->empresa    = $empresa
                               ? ['id' => $empresa->id, 'nombre' => $empresa->nombre]
                               : null;
                           return $manual;
                       }),

            // Franquiciante ve TODOS los manuales asignados a su empresa
            // (sin filtro v2.3 — el franquiciante necesita ver todos para gestionarlos)
            'franquiciante' => Manual::with('versionActiva')
                         ->noEliminados()
                         ->whereHas('empresasAsignadas', fn($q) =>
                             $q->where('empresa_id', $user->empresa_id)
                         )
                         ->orderBy('orden')
                         ->get(),

            // Franquiciado y empleado: comportamiento unificado en v2.3.
            // Ambos ven manuales publicados de su empresa CON asignación efectiva
            // (categoría activa OR individual).
            'franquiciado'  => ManualAccessService::manualesVisiblesParaUsuario($user),
            'empleado'      => ManualAccessService::manualesVisiblesParaUsuario($user),
        };

        return response()->json($manuales);
    }

    // GET /api/manuales/{id}
    // El identificador llega como ULID publico (lectura.php) o como id numerico
    // (pantallas de admin, que siguen trabajando con ids). Por eso `string`.
    public function show(Request $request, string $id): JsonResponse
    {
        $user   = $request->user();
        // v2.3: cargamos empresasAsignadas para poder devolver empresa_id en
        // el JSON (la tabla manuals no tiene la columna; vive en el pivote).
        // Se resuelve por una via o por la otra, nunca mezclando: un ULID es
        // siempre no numerico, asi que no hay ambiguedad posible.
        $query = Manual::with(['versionActiva', 'versiones', 'creador', 'empresasAsignadas']);
        $manual = ctype_digit($id)
            ? $query->findOrFail((int) $id)
            : $query->where('public_id', $id)->firstOrFail();

        // Franquiciante: solo manuales asignados a su empresa
        // v2.3 (post-auditoría): gate único centralizado en ManualAccessService.
        // El servicio maneja todos los roles con la lógica correcta:
        //   super_admin    → siempre pasa
        //   franquiciante  → manual_empresa_assignments
        //   franquiciado   → categoría activa OR asignación individual
        //   empleado       → mismo criterio que franquiciado
        if (!ManualAccessService::usuarioTieneAccesoAlManual($user, $manual->id)) {
            return response()->json(['error' => 'Sin acceso a este manual.'], 403);
        }

        // v2.3: enriquecer el JSON con empresa_id + empresa (mismo patrón que
        // index() para super_admin). Esto es necesario porque el frontend
        // (editor.php) usa estado.manual.empresa_id para sincronizar
        // categorías visibles del manual.
        $empresaAsignada = $manual->empresasAsignadas->first();
        $manual->empresa_id = $empresaAsignada?->id;
        $manual->empresa    = $empresaAsignada
            ? ['id' => $empresaAsignada->id, 'nombre' => $empresaAsignada->nombre]
            : null;

        if (!$user->esSuperAdmin() && !$user->esFranquiciante()) {
            $version = $manual->versionActiva->first();
            $manual->mi_aceptacion = $version
                ? $version->acceptances()->where('user_id', $user->id)->exists()
                : false;
        }

        // Manual PDF: enlace opaco y temporal para que ESTE usuario lea el
        // archivo. lectura.php lo usa como src del visor, asi la URL del visor no
        // lleva ningun ID y ademas caduca.
        if ($manual->tipo === 'pdf') {
            $manual->archivo_token = $this->tokenArchivo($manual, $user);
        }

        ActivityLog::registrar(
            userId:      $user->id,
            accion:      'manual_abierto',
            ip:          $request->ip(),
            empresaId:   $user->empresa_id,
            entidadTipo: 'manuals',
            entidadId:   $manual->id,
            detalle:     ['manual_titulo' => $manual->titulo],
            userAgent:   $request->userAgent()
        );

        return response()->json($manual);
    }

    // GET /api/manuales/{id}/versiones
    public function versiones(Request $request, int $id): JsonResponse
    {
        $manual = Manual::findOrFail($id);
        $user   = $request->user();

        // H-002 fix: aplicar el mismo gate que show(). Sin esto, un franquiciante
        // de empresa A podía enumerar IDs y obtener todas las versiones y contenido
        // HTML completo de manuales de empresa B.
        if (!ManualAccessService::usuarioTieneAccesoAlManual($user, $manual->id)) {
            return response()->json(['error' => 'Sin acceso a este manual.'], 403);
        }

        // v2.3: nombre/apellido viven en users — simplifico el eager load.
        // Antes: 'publicadoPor.superAdmin' (cargaba el perfil que ya no tiene nombre).
        $versiones = ManualVersion::where('manual_id', $manual->id)
                                  ->with('publicadoPor')
                                  ->orderBy('version_number', 'desc')
                                  ->get();

        return response()->json($versiones);
    }

    // POST /api/manuales — solo super_admin
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'titulo'     => 'required|string|max:200',
            'categoria'  => 'nullable|string|max:100',
            'orden'      => 'nullable|integer',
            'empresa_id' => 'nullable|integer|exists:empresas,id',
            // Tipo de manual. 'editable' (default) = se redacta en el editor.
            // 'pdf' = se sube un archivo y no se edita nunca mas.
            'tipo'       => 'nullable|in:editable,pdf',
        ]);

        $user      = $request->user();
        $empresaId = $user->esSuperAdmin()
            ? ($data['empresa_id'] ?? null)
            : $user->empresa_id;

        $manual = Manual::create([
            'titulo'     => $data['titulo'],
            'categoria'  => $data['categoria'] ?? null,
            'created_by' => $user->id,
            'estado'     => 'borrador',
            'orden'      => $data['orden'] ?? 0,
        ]);

        // `tipo` esta fuera del $fillable de Manual (ver el comentario alli):
        // se asigna con setter directo y SOLO en este punto. A partir de aca es
        // inmutable — update() ni siquiera lo valida, asi que no puede colarse.
        $manual->tipo = $data['tipo'] ?? 'editable';
        $manual->save();

        // Auto-asignar a la empresa correspondiente
        if ($empresaId) {
            ManualEmpresaAssignment::create([
                'manual_id'    => $manual->id,
                'empresa_id'   => $empresaId,
                'asignado_por' => $user->id,
                'asignado_at'  => now(),
            ]);
        }

        ActivityLog::registrar(
            userId:      $user->id,
            accion:      'manual_creado',
            ip:          $request->ip(),
            empresaId:   $empresaId,
            entidadTipo: 'manuals',
            entidadId:   $manual->id,
            detalle:     ['manual_titulo' => $manual->titulo],
            userAgent:   $request->userAgent()
        );

        return response()->json($manual, 201);
    }

    // PUT /api/manuales/{id} — solo super_admin
    public function update(Request $request, int $id): JsonResponse
    {
        $manual = Manual::findOrFail($id);
        $user   = $request->user();

        $this->authorize('gestionar', $manual);
        $data = $request->validate([
            'titulo'    => 'sometimes|string|max:200',
            'categoria' => 'nullable|string|max:100',
            'orden'     => 'nullable|integer',
        ]);

        $manual->update($data);

        ActivityLog::registrar(
            userId:      $request->user()->id,
            accion:      'manual_editado',
            ip:          $request->ip(),
            entidadTipo: 'manuals',
            entidadId:   $manual->id,
            detalle:     ['manual_titulo' => $manual->titulo],
            userAgent:   $request->userAgent()
        );

        return response()->json($manual);
    }

    // POST /api/manuales/{id}/borrador — solo super_admin
    public function guardarBorrador(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'contenido_html'  => 'required|string|max:5000000',
            // Header/footer: opcionales, cada uno hasta 200KB. Suficiente para
            // logo pegado como base64 (típico ~30KB) + texto legal.
            'encabezado_html' => 'nullable|string|max:200000',
            'pie_pagina_html' => 'nullable|string|max:200000',
        ]);

        $manual = Manual::findOrFail($id);
        $html   = $this->sanitizarHtml($request->contenido_html);
        $user   = $request->user();

    // Franquiciante solo puede editar manuales de su empresa
        $this->authorize('gestionar', $manual);

        // Un manual tipo 'pdf' no tiene editor: su contenido es el archivo
        // subido. Guardar HTML sobre el crearia una version incoherente con su
        // tipo (y el CHECK chk_mv_contenido lo rechazaria con un 500 crudo).
        if ($manual->esPdf()) {
            return response()->json([
                'error' => 'Este manual es un PDF: no se edita desde el editor.',
            ], 409);
        }

        // Header/footer viven en 'manuals' (no en 'manual_versions') porque
        // son identidad del manual, no versionables. Setter directo para no
        // depender del $fillable del modelo Manual.
        if ($request->has('encabezado_html')) {
            $manual->encabezado_html = $request->encabezado_html
                ? $this->sanitizarHtml($request->encabezado_html)
                : null;
        }
        if ($request->has('pie_pagina_html')) {
            $manual->pie_pagina_html = $request->pie_pagina_html
                ? $this->sanitizarHtml($request->pie_pagina_html)
                : null;
        }
        if ($manual->isDirty(['encabezado_html', 'pie_pagina_html'])) {
            $manual->save();
        }

        $borrador = ManualVersion::where('manual_id', $manual->id)
                                 ->where('es_activa', 0)
                                 ->where('version_number', 0)
                                 ->first();

        if ($borrador) {
            // El borrador (v0) guarda tambien su encabezado/pie, para que sea un
            // snapshot completo de "lo que se publicaria si publicaras ahora".
            $borrador->update([
                'contenido_html' => $html,
                'contenido_hash' => hash('sha256', $html),
                'encabezado_html' => $manual->encabezado_html,
                'pie_pagina_html' => $manual->pie_pagina_html,
                'documento_hash'  => $this->hashDocumento(
                    $manual->encabezado_html, $html, $manual->pie_pagina_html
                ),
            ]);
        } else {
            // V2-H-019: es_activa ya no esta en $fillable. No hace falta pasarlo:
            // la columna tiene DEFAULT 0, que es justo lo que queremos para un
            // borrador (version_number = 0, nunca activa).
            ManualVersion::create([
                'manual_id'      => $manual->id,
                'version_number' => 0,
                'contenido_html' => $html,
                'contenido_hash' => hash('sha256', $html),
                'encabezado_html' => $manual->encabezado_html,
                'pie_pagina_html' => $manual->pie_pagina_html,
                'documento_hash'  => $this->hashDocumento(
                    $manual->encabezado_html, $html, $manual->pie_pagina_html
                ),
                'publicado_por'  => $request->user()->id,
                'publicado_at'   => now(),
            ]);
        }

        // Feature imágenes: limpiar imágenes que ya no están referenciadas.
        // Se pasa $html (lo recién guardado) por robustez, para que nunca se borre
        // una imagen que el borrador actual referencia.
        try {
            ManualImageController::limpiarHuerfanas($manual->id, $html);
        } catch (\Throwable $e) { /* best-effort — no bloquea el guardado */ }

        return response()->json(['message' => 'Borrador guardado.']);
    }

    // POST /api/manuales/{id}/publicar — solo super_admin
    public function publicar(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'contenido_html'   => 'required|string|max:5000000',
            'nota_publicacion' => 'nullable|string|max:2000',
            'encabezado_html'  => 'nullable|string|max:200000',
            'pie_pagina_html'  => 'nullable|string|max:200000',
            'tipo_cambio'      => 'nullable|in:mayor,menor',
            // Version inicial declarada por el usuario. Solo se USA en la primera
            // publicacion del manual (ver mas abajo). version_number = 0 esta
            // reservado para el borrador, por eso min:1.
            'version_inicial_number' => 'nullable|integer|min:1|max:999',
            'version_inicial_minor'  => 'nullable|integer|min:0|max:999',
        ]);

        $manual = Manual::findOrFail($id);
        $user   = $request->user();
        $html   = $this->sanitizarHtml($request->contenido_html);
        $hash   = hash('sha256', $html);

        // Nota del publicador: si viene vacía o solo whitespace, guardamos NULL.
        $notaPub = trim($request->nota_publicacion ?? '');
        $notaPub = $notaPub === '' ? null : $notaPub;

        $this->authorize('gestionar', $manual);

        // Un manual tipo 'pdf' publica subiendo un archivo, no HTML del editor.
        // (El endpoint de subida llega en la etapa 2b.)
        if ($manual->esPdf()) {
            return response()->json([
                'error' => 'Este manual es un PDF: se publica subiendo el archivo, no desde el editor.',
            ], 409);
        }

        // Header/footer: se guardan a nivel manual (identidad, no versionable).
        // Se sanitizan con el mismo HTMLPurifier que contenido_html.
        if ($request->has('encabezado_html')) {
            $manual->encabezado_html = $request->encabezado_html
                ? $this->sanitizarHtml($request->encabezado_html)
                : null;
        }
        if ($request->has('pie_pagina_html')) {
            $manual->pie_pagina_html = $request->pie_pagina_html
                ? $this->sanitizarHtml($request->pie_pagina_html)
                : null;
        }
        if ($manual->isDirty(['encabezado_html', 'pie_pagina_html'])) {
            $manual->save();
        }

        try {
            DB::transaction(function () use ($manual, $user, $html, $hash, $request, $notaPub) {

                // Feature imágenes: limpiamos huérfanas al publicar. Se PASA el HTML
                // que estamos por publicar ($html) para que sus imágenes NO se
                // consideren huérfanas: todavía no están en ninguna versión guardada.
                // Sin esto, las imágenes recién importadas se borraban acá y la
                // versión nueva quedaba referenciando imágenes inexistentes (candado
                // en el PDF; src="" tras resolverImagenes).
                try {
                    ManualImageController::limpiarHuerfanas($manual->id, $html);
                } catch (\Throwable $e) { /* best-effort */ }

                // Capturamos la version activa ANTES de desactivarla: su numero es
                // la base de un cambio menor (v3.x -> v3.(x+1)).
                $activaActual = ManualVersion::where('manual_id', $manual->id)
                                             ->where('es_activa', 1)
                                             ->first();

                ManualVersion::where('manual_id', $manual->id)
                             ->where('es_activa', 1)
                             ->update(['es_activa' => 0]);

                // max(version_number) ignora el borrador (v0); 0 si no hay publicadas.
                $ultimaVersion = ManualVersion::where('manual_id', $manual->id)
                                              ->max('version_number') ?? 0;

                // Calculo mayor/menor de la version a crear:
                //  - primera publicacion    -> v1.0
                //  - menor (sobre la activa) -> mismo numero, minor = max(minor) + 1
                //  - mayor (default)         -> numero + 1, minor 0
                if ($ultimaVersion === 0) {
                    // PRIMERA PUBLICACION: el usuario puede declarar la version real
                    // que el manual ya tenia fuera del sistema (ej. 10.3). Si no
                    // declara nada, arranca en 1.0 como siempre.
                    //
                    // Este es el UNICO lugar de todo el sistema donde el numero de
                    // version viene del request, y se lee SOLO dentro de esta rama.
                    // Si el manual ya tiene versiones publicadas, el campo ni se
                    // consulta: degradar una version existente es imposible por
                    // construccion, no por una validacion que alguien pueda saltear.
                    // (Ver V2-H-027 de la auditoria v2.)
                    $nuevoNumber = (int) ($request->input('version_inicial_number') ?: 1);
                    $nuevoMinor  = (int) ($request->input('version_inicial_minor') ?? 0);
                } elseif ($request->tipo_cambio === 'menor' && $activaActual) {
                    $nuevoNumber = $activaActual->version_number;
                    $nuevoMinor  = (ManualVersion::where('manual_id', $manual->id)
                                        ->where('version_number', $nuevoNumber)
                                        ->max('version_minor') ?? 0) + 1;
                } else {
                    $nuevoNumber = $ultimaVersion + 1;
                    $nuevoMinor  = 0;
                }

                // V2-H-019: es_activa salio del $fillable de ManualVersion, asi que
                // NO se puede setear via create()/fill() — se ignoraria en silencio y
                // la version nacaria inactiva (manual publicado sin version visible).
                // Se asigna con setter directo, igual que password_hash en H-015.
                // SNAPSHOT INMUTABLE del encabezado y el pie.
                //
                // $manual->encabezado_html ya viene sanitizado y guardado unas lineas
                // mas arriba, asi que aca solo se congela. A partir de este momento,
                // editar el manual NO cambia lo que esta version muestra ni lo que su
                // documento_hash certifica.
                //
                // Esto es lo que arregla el agujero: antes, cambiar el pie de pagina
                // de un manual ya aceptado modificaba el documento que el socio
                // comercial imprimia y firmaba, sin generar version nueva y sin que
                // el hash de verificacion se moviera un bit.
                $version = new ManualVersion([
                    'manual_id'        => $manual->id,
                    'version_number'   => $nuevoNumber,
                    'version_minor'    => $nuevoMinor,
                    'contenido_html'   => $html,
                    'contenido_hash'   => $hash,
                    'encabezado_html'  => $manual->encabezado_html,
                    'pie_pagina_html'  => $manual->pie_pagina_html,
                    'documento_hash'   => $this->hashDocumento(
                        $manual->encabezado_html, $html, $manual->pie_pagina_html
                    ),
                    'publicado_por'    => $user->id,
                    'publicado_at'     => now(),
                    'nota_publicacion' => $notaPub,
                ]);
                $version->es_activa = 1;
                $version->save();

                // Etiqueta mayor.menor para notificaciones ("v3.1").
                $versionLabel = $version->version_number . '.' . $version->version_minor;

                $manual->update(['estado' => 'publicado']);

                $esFranq = $user->esFranquiciante();

                // Notificaciones in-app + emails. La logica vive en un metodo
                // privado porque la comparten DOS caminos de publicacion: este
                // (manual editable) y publicarArchivo() (manual PDF). Duplicarla
                // garantizaba que algun dia quedaran desincronizadas.
                $this->notificarPublicacion(
                    $manual, $version, $user, $ultimaVersion === 0, $versionLabel
                );

                ActivityLog::registrar(
                    userId:      $user->id,
                    accion:      $esFranq ? 'version_publicada_franquiciante' : 'manual_publicado',
                    ip:          $request->ip(),
                    entidadTipo: 'manual_versions',
                    entidadId:   $version->id,
                    detalle:     [
                        'manual_titulo' => $manual->titulo,
                        'version'       => $version->version_number,
                    ],
                    userAgent:   $request->userAgent()
                );
            });
        } catch (\Illuminate\Database\QueryException $e) {
            // Violacion del UNIQUE uq_mv_manual_version: dos publicaciones que
            // calcularon el mismo numero.minor casi a la vez. Mensaje claro en
            // vez de un 500 crudo.
            if ($e->getCode() === '23000') {
                return response()->json([
                    'error' => 'Esa version ya existe para este manual. Actualiza la pagina e intenta de nuevo.',
                ], 409);
            }
            throw $e;
        }

        return response()->json([
            'message' => 'Manual publicado correctamente.',
            'manual'  => $manual->fresh('versionActiva'),
        ]);
    }

    // POST /api/manuales/{id}/archivar
    public function archivar(Request $request, int $id): JsonResponse
    {
        $manual = Manual::findOrFail($id);
        $user   = $request->user();

        // v2.3: validar permisos ANTES de modificar el estado.
        // Antes el update se ejecutaba primero y después se devolvía 403, dejando
        // el manual archivado aunque el franquiciante no tuviera acceso.
        $this->authorize('gestionar', $manual);

        $manual->update(['estado' => 'archivado']);

        ActivityLog::registrar(
            userId:      $user->id,
            accion:      'manual_archivado',
            ip:          $request->ip(),
            entidadTipo: 'manuals',
            entidadId:   $manual->id,
            detalle:     ['manual_titulo' => $manual->titulo],
            userAgent:   $request->userAgent()
        );

        return response()->json(['message' => 'Manual archivado correctamente.']);
    }

    // POST /api/manuales/{id}/desarchivar
    public function desarchivar(Request $request, int $id): JsonResponse
    {
        $manual = Manual::findOrFail($id);
        $user   = $request->user();

        $this->authorize('gestionar', $manual);

        $manual->update(['estado' => 'borrador']);

        ActivityLog::registrar(
            userId:      $user->id,
            accion:      'manual_editado',
            ip:          $request->ip(),
            entidadTipo: 'manuals',
            entidadId:   $manual->id,
            detalle:     ['campo' => 'estado', 'valor_nuevo' => 'borrador'],
            userAgent:   $request->userAgent()
        );

        return response()->json(['message' => 'Manual restaurado a borrador.']);
    }

    // ── HTMLPurifier ──────────────────────────────────────────────────
    //
    // v2.4 (feature imágenes): además de permitir <img>, aplicamos un post-filtro
    // que remueve cualquier <img> cuyo src NO empiece con /api/manuales-imagenes/
    // o con data:image (para preview client-side ANTES de que el JS suba al server).
    //
    // Razón: si permitimos <img src="https://malware.com/x.gif"> queda un tracking
    // pixel externo. La política es "solo imágenes servidas por nuestro sistema".
    // El frontend intercepta paste/drag y sube al server ANTES de mostrar, así
    // que en el HTML final que llega al backend NO debería haber data: URIs
    // — pero por defensa las permitimos igual.
    /**
     * Hash del DOCUMENTO COMPLETO: encabezado + contenido + pie.
     *
     * Es lo que el usuario realmente ve, imprime y firma. contenido_hash cubre
     * solo contenido_html, asi que por si solo no certifica nada del membrete:
     * razon social, fecha de vigencia o aviso legal podian cambiar sin que el
     * hash se moviera.
     *
     * Se hashea cada parte por separado y despues se concatenan los DIGESTS (no
     * los HTML crudos). Concatenar los HTML seria ambiguo: encabezado "AB" +
     * contenido "C" produciria el mismo string que encabezado "A" + contenido "BC",
     * y por lo tanto el mismo hash para dos documentos distintos.
     */
    /**
     * Avisa a los franquiciantes de la empresa del socio y a los super_admin que
     * alguien pidio el archivo de un manual PDF por fuera del visor.
     *
     * Dedupe de 1 hora por (version + socio): sin esto, refrescar la URL diez
     * veces genera diez mails y la alerta se vuelve ruido que todos ignoran. El
     * activity_log SI guarda cada acceso, asi que no se pierde trazabilidad.
     *
     * tipo 'acceso_anomalo_pdf' requiere la migracion de chk_notif_fk: usa
     * manual_version_id, misma combinacion de FKs que modificacion_manual.
     */
    private function notificarAccesoAnomalo(Manual $manual, ManualVersion $version, User $socio): void
    {
        try {
            $nombreSocio = strip_tags($socio->nombreCompleto() ?: ($socio->email ?? 'Un usuario'));
            $tituloManual = strip_tags($manual->titulo ?? '');
            $titulo = mb_substr(
                "Acceso directo al archivo de {$tituloManual} por {$nombreSocio}",
                0, 200
            );

            $yaAvisado = Notification::where('tipo', 'acceso_anomalo_pdf')
                                     ->where('manual_version_id', $version->id)
                                     ->where('titulo', $titulo)
                                     ->where('created_at', '>=', now()->subHour())
                                     ->exists();
            if ($yaAvisado) {
                return;
            }

            // Franquiciantes de la empresa del socio + todos los super_admin.
            $destinatarios = User::where('activo', 1)
                ->whereNull('deleted_at')
                ->where(function ($q) use ($socio) {
                    $q->where('rol', 'super_admin')
                      ->orWhere(function ($q2) use ($socio) {
                          $q2->where('rol', 'franquiciante')
                             ->where('empresa_id', $socio->empresa_id);
                      });
                })
                ->pluck('id');

            foreach ($destinatarios as $uid) {
                try {
                    // create() (no insert()) para que el observer encole el mail.
                    Notification::create([
                        'user_id'             => $uid,
                        'tipo'                => 'acceso_anomalo_pdf',
                        'manual_id'           => null,
                        'manual_version_id'   => $version->id,
                        'document_id'         => null,
                        'document_version_id' => null,
                        'category_id'         => null,
                        'titulo'              => $titulo,
                        'created_at'          => now(),
                    ]);
                } catch (\Throwable $e) { /* best-effort */ }
            }
        } catch (\Throwable $e) {
            // Nunca romper la lectura del manual por fallar el aviso.
        }
    }

    /**
     * Notificaciones + emails de una publicacion (manual editable o PDF).
     *
     * Extraido de publicar() al agregar la publicacion por archivo: los dos
     * caminos deben avisar EXACTAMENTE igual (para el socio comercial es el
     * mismo evento). Comportamiento sin cambios respecto del original.
     *
     * @param bool $esPrimeraPublicacion antes era ($ultimaVersion === 0)
     */
    private function notificarPublicacion(
        Manual $manual,
        ManualVersion $version,
        User $user,
        bool $esPrimeraPublicacion,
        string $versionLabel
    ): void {
        // v2.3: notificar SOLO a usuarios con asignación efectiva al manual
        // (categoría activa OR individual). Si nadie tiene asignación, no se
        // notifica a nadie — el manual queda publicado pero invisible hasta que
        // el franquiciante lo asigne explícitamente.
        $tipo = $esPrimeraPublicacion ? 'nuevo_manual' : 'modificacion_manual';

        $userIds = $this->usuariosConAccesoAlManual($manual->id);

        // H-020 fix (defense in depth): strip_tags remueve cualquier marcado HTML
        // del título antes de guardarlo en la notificación.
        $tituloManual = strip_tags($manual->titulo ?? '');

        if ($userIds->isNotEmpty()) {
            // create() (no insert()) para que dispare el observer de Notification,
            // que encola el email a cada destinatario.
            $tituloNotif = $tipo === 'nuevo_manual'
                ? "Nuevo manual asignado: {$tituloManual}"
                : "Manual actualizado: {$tituloManual} (v{$versionLabel})";

            foreach ($userIds as $uid) {
                try {
                    Notification::create([
                        'user_id'             => $uid,
                        'tipo'                => $tipo,
                        'manual_id'           => $tipo === 'nuevo_manual' ? $manual->id : null,
                        'manual_version_id'   => $tipo === 'modificacion_manual' ? $version->id : null,
                        'document_id'         => null,
                        'document_version_id' => null,
                        'category_id'         => null,
                        'titulo'              => $tituloNotif,
                        'created_at'          => now(),
                    ]);
                } catch (\Throwable $e) { /* best-effort: una notif fallida no corta las demás */ }
            }
        }

        // Si publica un franquiciante: avisar a los super_admin.
        if ($user->esFranquiciante()) {
            $nombreAutor = $user->nombreCompleto();
            if ($nombreAutor === '') {
                $nombreAutor = $user->empresa?->nombre ?? ($user->email ?? 'Franquiciante');
            }
            // H-020 fix: sanitizar el nombre del autor también.
            $nombreAutor = strip_tags($nombreAutor);

            $superadmins = User::where('rol', 'super_admin')->where('activo', 1)->pluck('id');
            $notifSuper  = $superadmins->map(fn($uid) => [
                'user_id'             => $uid,
                'tipo'                => 'modificacion_manual',
                'manual_id'           => null,
                'manual_version_id'   => $version->id,
                'document_id'         => null,
                'document_version_id' => null,
                'category_id'         => null,
                'titulo'              => "El franquiciante {$nombreAutor} publicó una nueva versión de {$tituloManual} (v{$versionLabel})",
                'created_at'          => now(),
            ])->toArray();
            try {
                if (!empty($notifSuper)) {
                    // insert() a propósito: los super_admin reciben la notif in-app
                    // pero NO email — no son destinatarios del manual.
                    Notification::insert($notifSuper);
                }
            } catch (\Throwable $e) { /* best-effort */ }
        }
    }

    // POST /api/manuales/{id}/archivo — solo super_admin y franquiciante.
    //
    // Publica una version de un manual tipo 'pdf' subiendo el archivo. Espeja
    // publicar(): misma numeracion mayor/menor, misma desactivacion de la
    // version anterior, mismo paso a estado 'publicado', mismas notificaciones.
    public function publicarArchivo(Request $request, int $id): JsonResponse
    {
        $request->validate([
            // mimes: valida extension + tipo adivinado. mimetypes: valida el MIME
            // REAL leido del contenido (finfo). Los dos: no alcanza con renombrar
            // cualquier cosa a .pdf. max en KB (51200 = 50 MB).
            'archivo'          => 'required|file|mimes:pdf|mimetypes:application/pdf|max:51200',
            'nota_publicacion' => 'nullable|string|max:2000',
            'tipo_cambio'      => 'nullable|in:mayor,menor',
            'version_inicial_number' => 'nullable|integer|min:1|max:999',
            'version_inicial_minor'  => 'nullable|integer|min:0|max:999',
        ]);

        $manual = Manual::findOrFail($id);
        $user   = $request->user();

        $this->authorize('gestionar', $manual);

        if (!$manual->esPdf()) {
            return response()->json([
                'error' => 'Este manual es editable: se publica desde el editor, no subiendo un archivo.',
            ], 409);
        }

        $archivo = $request->file('archivo');
        $hash    = hash_file('sha256', $archivo->getRealPath());

        // La ruta se deriva SIEMPRE del manual_id + hash. El nombre que mando el
        // cliente nunca toca el filesystem (podria traer ../ o caracteres raros):
        // se guarda aparte, solo como metadato para la descarga.
        $rutaRel = "manuales/archivos/{$manual->id}/{$hash}.pdf";
        if (!Storage::disk('local')->exists($rutaRel)) {
            Storage::disk('local')->put($rutaRel, file_get_contents($archivo->getRealPath()));
        }

        $nombreOriginal = mb_substr(basename($archivo->getClientOriginalName()), 0, 255);
        $tamano         = $archivo->getSize();
        $mime           = $archivo->getMimeType() ?: 'application/pdf';

        $notaPub = trim($request->nota_publicacion ?? '');
        $notaPub = $notaPub === '' ? null : $notaPub;

        $version = null;

        try {
            DB::transaction(function () use (
                $manual, $user, $request, $notaPub, $hash, $rutaRel,
                $nombreOriginal, $tamano, $mime, &$version
            ) {
                // Misma mecanica que publicar(): capturar la activa antes de
                // bajarla, porque su numero es la base de un cambio menor.
                $activaActual = ManualVersion::where('manual_id', $manual->id)
                                             ->where('es_activa', 1)
                                             ->first();

                ManualVersion::where('manual_id', $manual->id)
                             ->where('es_activa', 1)
                             ->update(['es_activa' => 0]);

                $ultimaVersion = ManualVersion::where('manual_id', $manual->id)
                                              ->max('version_number') ?? 0;

                if ($ultimaVersion === 0) {
                    // Primera publicacion: el usuario puede declarar la version que
                    // el manual ya tenia fuera del sistema. Igual que en publicar(),
                    // este campo SOLO se lee dentro de esta rama (V2-H-027).
                    $nuevoNumber = (int) ($request->input('version_inicial_number') ?: 1);
                    $nuevoMinor  = (int) ($request->input('version_inicial_minor') ?? 0);
                } elseif ($request->tipo_cambio === 'menor' && $activaActual) {
                    $nuevoNumber = $activaActual->version_number;
                    $nuevoMinor  = (ManualVersion::where('manual_id', $manual->id)
                                        ->where('version_number', $nuevoNumber)
                                        ->max('version_minor') ?? 0) + 1;
                } else {
                    $nuevoNumber = $ultimaVersion + 1;
                    $nuevoMinor  = 0;
                }

                // contenido_html queda NULL (satisface chk_mv_contenido) y el hash
                // del ARCHIVO cumple los dos roles: contenido_hash (obligatorio) y
                // documento_hash (lo que la aceptacion certifica). En un PDF no hay
                // encabezado/pie separados: el archivo ya los trae adentro.
                $version = new ManualVersion([
                    'manual_id'        => $manual->id,
                    'version_number'   => $nuevoNumber,
                    'version_minor'    => $nuevoMinor,
                    'contenido_hash'   => $hash,
                    'documento_hash'   => $hash,
                    'archivo_path'     => $rutaRel,
                    'archivo_nombre'   => $nombreOriginal,
                    'archivo_mime'     => $mime,
                    'archivo_tamano'   => $tamano,
                    'publicado_por'    => $user->id,
                    'publicado_at'     => now(),
                    'nota_publicacion' => $notaPub,
                ]);
                // V2-H-019: es_activa fuera de $fillable -> setter directo.
                $version->es_activa = 1;
                $version->save();

                $versionLabel = $version->version_number . '.' . $version->version_minor;

                $manual->update(['estado' => 'publicado']);

                $this->notificarPublicacion(
                    $manual, $version, $user, $ultimaVersion === 0, $versionLabel
                );

                ActivityLog::registrar(
                    userId:      $user->id,
                    accion:      $user->esFranquiciante() ? 'version_publicada_franquiciante' : 'manual_publicado',
                    ip:          $request->ip(),
                    entidadTipo: 'manual_versions',
                    entidadId:   $version->id,
                    detalle:     [
                        'manual_titulo' => $manual->titulo,
                        'version'       => $version->version_number,
                    ],
                    userAgent:   $request->userAgent()
                );
            });
        } catch (\Illuminate\Database\QueryException $e) {
            // Violacion del UNIQUE uq_mv_manual_version: dos publicaciones que
            // calcularon el mismo numero.minor casi a la vez.
            if ($e->getCode() === '23000') {
                return response()->json([
                    'error' => 'Esa version ya existe para este manual. Actualiza la pagina e intenta de nuevo.',
                ], 409);
            }
            throw $e;
        }

        return response()->json([
            'message' => 'Archivo publicado correctamente.',
            'manual'  => $manual->fresh('versionActiva'),
        ]);
    }

    // GET /api/manuales/{id}/archivo — SOLO super_admin y franquiciante.
    //
    // La ruta con ID quedo reservada a los admins, que ya ven los IDs en todo el
    // panel. El socio comercial entra por servirArchivoToken(), con un enlace
    // opaco y temporal que no revela nada de la base.
    public function servirArchivo(Request $request, int $id)
    {
        $user = $request->user();

        if (!$user->esSuperAdmin() && !$user->esFranquiciante()) {
            return response()->json([
                'error' => 'Sin permiso para acceder al archivo por esta via.',
            ], 403);
        }

        return $this->entregarArchivo($request, Manual::findOrFail($id));
    }

    // GET /api/manuales/archivo/{token} — todos los roles.
    //
    // El token es un payload CIFRADO con Crypt (que ademas autentica con MAC, asi
    // que no se puede forjar ni alterar) con el manual, el usuario y el
    // vencimiento. Tres propiedades:
    //   - no expone el ID del manual: no se puede enumerar la base;
    //   - esta atado al usuario: si lo comparte, al otro no le sirve;
    //   - caduca: guardarselo para despues tampoco sirve.
    //
    // NO es autorizacion: entregarArchivo() revalida el acceso igual. El token
    // define QUE se pide, no SI se puede.
    public function servirArchivoToken(Request $request, string $token)
    {
        $user = $request->user();

        try {
            $datos = json_decode(Crypt::decryptString(strtr($token, '-_~', '+/=')), true);
        } catch (\Throwable $e) {
            $datos = null;
        }

        if (!is_array($datos) || !isset($datos['m'], $datos['u'], $datos['exp'])) {
            return response()->json(['error' => 'Enlace invalido.'], 403);
        }
        if ((int) $datos['u'] !== (int) $user->id) {
            return response()->json(['error' => 'Este enlace no corresponde a tu usuario.'], 403);
        }
        if ((int) $datos['exp'] < time()) {
            return response()->json([
                'error' => 'El enlace expiro. Volve a abrir el manual.',
            ], 410);
        }

        return $this->entregarArchivo($request, Manual::findOrFail((int) $datos['m']));
    }

    /**
     * Token opaco y temporal para que UN usuario lea UN manual.
     *
     * strtr en vez de base64url: el string que devuelve Crypt ya es base64, solo
     * hay que sacarle los caracteres que romperian la URL (+ / =).
     */
    private function tokenArchivo(Manual $manual, User $user, int $minutos = 60): string
    {
        $payload = json_encode([
            'm'   => $manual->id,
            'u'   => $user->id,
            'exp' => now()->addMinutes($minutos)->timestamp,
        ]);

        return strtr(Crypt::encryptString($payload), '+/=', '-_~');
    }

    /**
     * Entrega efectiva del archivo. Comun a las dos rutas (ID y token): el
     * control de acceso, el log y la deteccion de acceso anomalo viven ACA, para
     * que no dependan de por donde entro el pedido.
     */
    private function entregarArchivo(Request $request, Manual $manual)
    {
        $user = $request->user();

        if (!ManualAccessService::usuarioTieneAccesoAlManual($user, $manual->id)) {
            return response()->json(['error' => 'Sin acceso a este manual.'], 403);
        }

        $version = ManualVersion::where('manual_id', $manual->id)
                                ->where('es_activa', 1)
                                ->first();

        if (!$version || !$version->esPdf()) {
            return response()->json([
                'error' => 'Este manual no tiene un archivo publicado.',
            ], 409);
        }

        if (!Storage::disk('local')->exists($version->archivo_path)) {
            return response()->json([
                'error' => 'El archivo de esta version no esta disponible.',
            ], 404);
        }

        $nombre = $version->archivo_nombre ?: ('manual-' . $manual->id . '.pdf');

        $esSocio = $user->esFranquiciado() || $user->esEmpleado();

        // ?descargar=1 fuerza la descarga, pero SOLO para admins. Si lo manda un
        // socio se ignora: el manual maestro se lee, no se baja de un click.
        $dispo = ($request->boolean('descargar') && !$esSocio) ? 'attachment' : 'inline';

        // ACCESO ANOMALO: el socio pidio el archivo por fuera del visor embebido.
        //
        // Sec-Fetch-Dest dice en que contexto se pidio el recurso: 'iframe' cuando
        // lo carga el visor de lectura.php, 'document' cuando alguien navega
        // directo a la URL. El Referer descarta el falso positivo del link de
        // respaldo ("Abrilo en una pestaña nueva") que la propia lectura.php ofrece.
        //
        // LIMITE: el boton de descarga del visor NO genera request, asi que esa
        // descarga es indetectable. Esto detecta al que se guarda la URL.
        $dest    = strtolower((string) $request->header('Sec-Fetch-Dest', ''));
        $referer = (string) $request->header('Referer', '');
        $anomalo = $esSocio
                   && $dest !== 'iframe'
                   && !str_contains($referer, 'lectura.php');

        // Log SIEMPRE (barato y forense). Las claves del detalle son las unicas
        // que admite chk_detalle_schema; ip y user_agent son columnas propias.
        ActivityLog::registrar(
            userId:      $user->id,
            accion:      $anomalo ? 'manual_pdf_acceso_directo' : 'manual_pdf_abierto',
            ip:          $request->ip(),
            empresaId:   $user->empresa_id,
            entidadTipo: 'manual_versions',
            entidadId:   $version->id,
            detalle:     [
                'manual_titulo' => $manual->titulo,
                'version'       => $version->version_number,
            ],
            userAgent:   $request->userAgent()
        );

        if ($anomalo) {
            $this->notificarAccesoAnomalo($manual, $version, $user);
        }

        // nosniff: el archivo lo subio un franquiciante y lo abre un socio de otra
        // sucursal; se sirve SIEMPRE como application/pdf, sin dejar que el
        // navegador adivine otro tipo. El nombre se escapa por el header.
        return Storage::disk('local')->response($version->archivo_path, $nombre, [
            'Content-Type'           => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition'    => $dispo . '; filename="' . addslashes($nombre) . '"',
        ]);
    }

    private function hashDocumento(?string $encabezado, string $contenido, ?string $pie): string
    {
        return hash('sha256',
            hash('sha256', (string) $encabezado) .
            hash('sha256', $contenido) .
            hash('sha256', (string) $pie)
        );
    }

    private function sanitizarHtml(string $html): string
    {
        $config = HTMLPurifier_Config::createDefault();

        $config->set('HTML.Allowed',
            // p[style] para conservar text-align (justificado importado del Word).
            // text-align ya está en CSS.AllowedProperties; sin [style] acá,
            // HTMLPurifier borraría la alineación al publicar.
            'h1,h2,h3,p[style],br,' .
            'strong,em,u,s,' .
            'ul,ol,li,' .
            'table[style],thead,tbody,tr,' .
            'th[style],td[style],' .
            'a[href|target],' .
            'div[style],span[style],' .
            // v2.4: <img> con atributos seguros. Sin onerror/onload etc.
            // (HTMLPurifier los strip automáticamente).
            'img[src|alt|title|width|height|style]'
        );

        // Permitir data: URI para preview client-side. HTMLPurifier los sanitiza
        // (solo formatos de imagen conocidos, no javascript: data:text/html etc).
        $config->set('URI.AllowedSchemes', [
            'http' => true, 'https' => true, 'data' => true,
        ]);
        $config->set('CSS.AllowedProperties',
            'text-align,font-weight,font-style,text-decoration,width,height,' .
            // color / background-color: para colores de texto y resaltado del
            // editor (Opción C, paleta + picker). HTMLPurifier valida el valor
            // (hex, rgb, rgba, nombres, transparent) y descarta lo inválido.
            'color,background-color,' .
            // Para img alignments: margin, float, max-width, font-size (en pt).
            // OJO: 'display' NO se puede porque HTMLPurifier necesitaría config
            // extra (CSS.AllowTricky) para soportarlo. Las imágenes son inline
            // por default, y las alineaciones de párrafo usan text-align.
            'margin,margin-left,margin-right,float,max-width,font-size'
        );
        $cacheDir = storage_path('app/htmlpurifier');
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }
        $config->set('Cache.SerializerPath', $cacheDir);

        $html = (new HTMLPurifier($config))->purify($html);

        // Post-filtro: remover <img> con src externos (no de nuestro sistema).
        // Solo aceptamos:
        //   - URLs que contengan el path /api/manuales-imagenes/{id}/descargar
        //     (relativas o con subpath, ej: /manuales-franquiciantes/public/api/...)
        //   - data:image/* URIs
        // Cualquier otra cosa se descarta.
        //
        // V2-H-024: el filtro anterior usaba "contains" sobre el path para soportar
        // instalaciones en subpath (XAMPP: /manuales-franquiciantes/public/api/...).
        // El comentario decía que "URLs a otros dominios no matchean el pattern".
        // ERA FALSO: el (?:^|/) del regex matcheaba la barra que viene DESPUÉS del
        // host, así que todo esto pasaba el filtro:
        //
        //     https://evil.com/api/manuales-imagenes/1/descargar
        //     //tracker.malicioso.net/api/manuales-imagenes/1/descargar
        //     http://attacker.io/api/manuales-imagenes/999/descargar?x=1
        //
        // HTMLPurifier permite http/https, así que un franquiciante podía insertar
        // un pixel de tracking en un manual: cada franquiciado que abría lectura.php
        // disparaba un request al servidor del atacante, filtrando IP, user-agent,
        // confirmación de lectura y Referer.
        //
        // Ahora se parsea la URL y se RECHAZA cualquier src que traiga scheme o host.
        // Solo se aceptan rutas relativas (que por definición apuntan a nuestro
        // servidor) cuyo path termine en el endpoint, o data:image/.
        $html = preg_replace_callback(
            '#<img\s+[^>]*>#i',
            function ($m) {
                $tag = $m[0];
                if (!preg_match('#src\s*=\s*["\']([^"\']+)["\']#i', $tag, $srcMatch)) {
                    return ''; // sin src → descartar
                }
                $src = trim(html_entity_decode($srcMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));

                // data:image/ → lo permitimos (HTMLPurifier ya validó el subtipo).
                if (str_starts_with($src, 'data:image/')) {
                    return $tag;
                }

                $partes = parse_url($src);

                // parse_url devuelve false ante URLs malformadas → descartar.
                if ($partes === false) {
                    return '';
                }

                // CLAVE: cualquier scheme (http:, https:, //host) implica un destino
                // externo. Solo aceptamos rutas relativas a nuestro propio servidor.
                if (!empty($partes['scheme']) || !empty($partes['host'])) {
                    return '';
                }

                $path = $partes['path'] ?? '';

                // El path debe TERMINAR en nuestro endpoint. El (?:^|/) inicial sigue
                // siendo necesario para tolerar el subpath de XAMPP, pero ahora es
                // seguro porque ya descartamos todo lo que tenga host.
                $esNuestraApi = (bool) preg_match(
                    '#(?:^|/)api/manuales-imagenes/\d+/descargar$#',
                    $path
                );

                return $esNuestraApi ? $tag : '';
            },
            $html
        );

        return $html;
    }

    // DELETE /api/manuales/{id}
    public function destroy(Request $request, int $id): JsonResponse
    {
        $manual = Manual::findOrFail($id);
        $user   = $request->user();

        // Franquiciante solo puede eliminar manuales de su empresa
        $this->authorize('gestionar', $manual);

        $manual->update([
            'estado'     => 'eliminado',
            'deleted_by' => $user->id,
            'deleted_at' => now(),
        ]);

        // Si quien elimina es franquiciante: avisar a los super_admin.
        // Usamos el tipo 'modificacion_manual' (con manual_version_id) porque la tabla
        // notifications tiene un CHECK (chk_notif_fk) que valida la combinación tipo/FK.
        //
        // v2.3: nombre del franquiciante via $user->nombreCompleto() — antes el código
        // buscaba en franchiseStaff (que para un franquiciante siempre era NULL).
        if ($user->esFranquiciante()) {
            $nombreAutor = $user->nombreCompleto();
            if ($nombreAutor === '') {
                $nombreAutor = $user->empresa?->nombre ?? ($user->email ?? 'Franquiciante');
            }
            // H-020 fix: sanitizar el nombre del autor (defense-in-depth).
            $nombreAutor = strip_tags($nombreAutor);

            // Tomamos la última versión activa o la última creada, si existe
            $versionId = $manual->versiones()->orderByDesc('id')->value('id');

            if ($versionId !== null) {
                $superadmins = User::where('rol', 'super_admin')->where('activo', 1)->pluck('id');
                $notifSuper  = $superadmins->map(fn($uid) => [
                    'user_id'             => $uid,
                    'tipo'                => 'modificacion_manual',
                    'manual_id'           => null,
                    'manual_version_id'   => $versionId,
                    'document_id'         => null,
                    'document_version_id' => null,
                    'category_id'         => null,
                    'titulo'              => "El franquiciante {$nombreAutor} eliminó el manual \"" . strip_tags($manual->titulo ?? '') . "\"",
                    'created_at'          => now(),
                ])->toArray();
                try {
                    if (!empty($notifSuper)) {
                        Notification::insert($notifSuper);
                    }
                } catch (\Throwable $e) { /* best-effort */ }
            }
        }

        ActivityLog::registrar(
            userId:      $user->id,
            accion:      'manual_eliminado',
            ip:          $request->ip(),
            empresaId:   $user->empresa_id,
            entidadTipo: 'manuals',
            entidadId:   $manual->id,
            detalle:     ['manual_titulo' => $manual->titulo],
            userAgent:   $request->userAgent()
        );

        return response()->json(['message' => 'Manual eliminado correctamente.']);
    }

    // POST /api/manuales/{id}/restore
    public function restore(Request $request, int $id): JsonResponse
    {
        $manual = Manual::findOrFail($id);
        $user   = $request->user();

        // Franquiciante solo puede restaurar manuales de su empresa
        $this->authorize('gestionar', $manual);

        // Restaurar a borrador (estado seguro) y limpiar metadata de eliminación
        $manual->update([
            'estado'     => 'borrador',
            'deleted_by' => null,
            'deleted_at' => null,
        ]);

        ActivityLog::registrar(
            userId:      $user->id,
            accion:      'manual_restaurado',
            ip:          $request->ip(),
            empresaId:   $user->empresa_id,
            entidadTipo: 'manuals',
            entidadId:   $manual->id,
            detalle:     ['manual_titulo' => $manual->titulo],
            userAgent:   $request->userAgent()
        );

        return response()->json(['message' => 'Manual restaurado a borrador.']);
    }

    // ── PRIVADOS — Helpers de visibilidad v2.3 ─────────────────────────

    /**
     * v2.3: Devuelve los manuales publicados visibles para un franquiciado o empleado.
     * Aplica el OR de asignación (categoría activa O individual) sobre el scope de empresa.
     * Incluye el flag mi_aceptacion para cada manual.
     */
    /**
     * v2.3: Devuelve los IDs de usuarios (franquiciado/empleado) activos que tienen
     * acceso al manual por categoría activa O asignación individual.
     * Usado por publicar() para determinar a quién notificar.
     */
    private function usuariosConAccesoAlManual(int $manualId)
    {
        // IDs candidatos: por categoría activa
        $idsPorCategoria = DB::table('manual_category_assignments as mca')
            ->join('user_categories as uc', 'uc.category_id', '=', 'mca.category_id')
            ->join('franchise_categories as fc', function ($j) {
                $j->on('fc.id', '=', 'mca.category_id')
                  ->where('fc.is_active', 1);
            })
            ->where('mca.manual_id', $manualId)
            ->pluck('uc.user_id');

        // IDs candidatos: por asignación individual
        $idsIndividuales = DB::table('manual_user_assignments')
            ->where('manual_id', $manualId)
            ->pluck('user_id');

        $candidateIds = $idsPorCategoria->merge($idsIndividuales)->unique();

        if ($candidateIds->isEmpty()) {
            return collect();
        }

        // Filtrar: activos, franquiciado/empleado
        return User::whereIn('id', $candidateIds)
                   ->whereIn('rol', ['franquiciado', 'empleado'])
                   ->where('activo', 1)
                   ->whereNull('deleted_at')
                   ->pluck('id');
    }
}