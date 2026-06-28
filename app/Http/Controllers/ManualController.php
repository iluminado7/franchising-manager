<?php

namespace App\Http\Controllers;

use HTMLPurifier;
use HTMLPurifier_Config;
use App\Models\Manual;
use App\Models\ManualVersion;
use App\Models\ManualEmpresaAssignment;
use App\Models\Notification;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

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
            'super_admin' => Manual::with(['versionActiva', 'empresasAsignadas'])
                       ->when(!$includeDeleted, fn($q) => $q->visiblesParaSuperAdmin())
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
            'franquiciado'  => $this->manualesVisiblesParaUsuario($user),
            'empleado'      => $this->manualesVisiblesParaUsuario($user),
        };

        return response()->json($manuales);
    }

    // GET /api/manuales/{id}
    public function show(Request $request, int $id): JsonResponse
    {
        $user   = $request->user();
        // v2.3: cargamos empresasAsignadas para poder devolver empresa_id en
        // el JSON (la tabla manuals no tiene la columna; vive en el pivote).
        $manual = Manual::with(['versionActiva', 'versiones', 'creador', 'empresasAsignadas'])
                        ->findOrFail($id);

        // Franquiciante: solo manuales asignados a su empresa
        if ($user->esFranquiciante()) {
            $asignado = ManualEmpresaAssignment::where('manual_id', $id)
                                               ->where('empresa_id', $user->empresa_id)
                                               ->exists();
            if (!$asignado) {
                return response()->json(['error' => 'Sin acceso a este manual.'], 403);
            }
        }

        // Franquiciado/empleado: v2.3 — categoría activa O individual.
        // usuarioTieneAccesoAlManual ya verifica que la categoría sea de la
        // empresa del usuario (vía fc.empresa_id), por lo que un único gate
        // alcanza. Antes había un doble gate redundante con manual_empresa_assignments
        // que provocaba 403 en falsos negativos cuando mca.empresa_id no estaba
        // poblado consistentemente.
        if ($user->esFranquiciado() || $user->esEmpleado()) {
            $tieneAcceso = $this->usuarioTieneAccesoAlManual($user->id, $manual->id, $user->empresa_id);
            if (!$tieneAcceso) {
                return response()->json(['error' => 'Sin acceso a este manual.'], 403);
            }
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
    public function versiones(int $id): JsonResponse
    {
        $manual   = Manual::findOrFail($id);
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

            if ($user->esFranquiciante()) {
                $asignado = ManualEmpresaAssignment::where('manual_id', $id)
                                                ->where('empresa_id', $user->empresa_id)
                                                ->exists();
                if (!$asignado) {
                    return response()->json(['error' => 'Sin acceso a este manual.'], 403);
                }
            }
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
            'contenido_html' => 'required|string|max:5000000',
        ]);

        $manual = Manual::findOrFail($id);
        $html   = $this->sanitizarHtml($request->contenido_html);
        $user   = $request->user();

    // Franquiciante solo puede editar manuales de su empresa
        if ($user->esFranquiciante()) {
            $asignado = ManualEmpresaAssignment::where('manual_id', $id)
                                            ->where('empresa_id', $user->empresa_id)
                                            ->exists();
            if (!$asignado) {
                return response()->json(['error' => 'Sin acceso a este manual.'], 403);
            }
        }
        $borrador = ManualVersion::where('manual_id', $manual->id)
                                 ->where('es_activa', 0)
                                 ->where('version_number', 0)
                                 ->first();

        if ($borrador) {
            $borrador->update([
                'contenido_html' => $html,
                'contenido_hash' => hash('sha256', $html),
            ]);
        } else {
            ManualVersion::create([
                'manual_id'      => $manual->id,
                'version_number' => 0,
                'contenido_html' => $html,
                'contenido_hash' => hash('sha256', $html),
                'publicado_por'  => $request->user()->id,
                'publicado_at'   => now(),
                'es_activa'      => 0,
            ]);
        }

        return response()->json(['message' => 'Borrador guardado.']);
    }

    // POST /api/manuales/{id}/publicar — solo super_admin
    public function publicar(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'contenido_html'   => 'required|string|max:5000000',
            'nota_publicacion' => 'nullable|string|max:2000',
        ]);

        $manual = Manual::findOrFail($id);
        $user   = $request->user();
        $html   = $this->sanitizarHtml($request->contenido_html);
        $hash   = hash('sha256', $html);

        // Nota del publicador: si viene vacía o solo whitespace, guardamos NULL.
        $notaPub = trim($request->nota_publicacion ?? '');
        $notaPub = $notaPub === '' ? null : $notaPub;

        if ($user->esFranquiciante()) {
            $asignado = ManualEmpresaAssignment::where('manual_id', $id)
                                            ->where('empresa_id', $user->empresa_id)
                                            ->exists();
            if (!$asignado) {
                return response()->json(['error' => 'Sin acceso a este manual.'], 403);
            }
        }

        DB::transaction(function () use ($manual, $user, $html, $hash, $request, $notaPub) {

            ManualVersion::where('manual_id', $manual->id)
                         ->where('es_activa', 1)
                         ->update(['es_activa' => 0]);

            $ultimaVersion = ManualVersion::where('manual_id', $manual->id)
                                          ->max('version_number') ?? 0;

            $version = ManualVersion::create([
                'manual_id'        => $manual->id,
                'version_number'   => $ultimaVersion + 1,
                'contenido_html'   => $html,
                'contenido_hash'   => $hash,
                'publicado_por'    => $user->id,
                'publicado_at'     => now(),
                'es_activa'        => 1,
                'nota_publicacion' => $notaPub,
            ]);

            $manual->update(['estado' => 'publicado']);

            // v2.3: notificar SOLO a usuarios con asignación efectiva al manual
            // (categoría activa OR individual). Si nadie tiene asignación, no
            // se notifica a nadie — el manual queda publicado pero invisible
            // hasta que el franquiciante lo asigne explícitamente.
            //
            // Antes (legacy): notificaba a TODOS los franquiciados de TODAS las
            // empresas asignadas, sin importar si podían verlo.
            $tipo = $ultimaVersion === 0 ? 'nuevo_manual' : 'modificacion_manual';

            $userIds = $this->usuariosConAccesoAlManual($manual->id);

            if ($userIds->isNotEmpty()) {
                $notificaciones = $userIds->map(fn($uid) => [
                    'user_id'             => $uid,
                    'tipo'                => $tipo,
                    'manual_id'           => $tipo === 'nuevo_manual' ? $manual->id : null,
                    'manual_version_id'   => $tipo === 'modificacion_manual' ? $version->id : null,
                    'document_id'         => null,
                    'document_version_id' => null,
                    'category_id'         => null,
                    'titulo'              => $tipo === 'nuevo_manual'
                                            ? "Nuevo manual: {$manual->titulo}"
                                            : "Manual actualizado: {$manual->titulo} (v{$version->version_number})",
                    'created_at'          => now(),
                ])->toArray();

                try {
                    Notification::insert($notificaciones);
                } catch (\Throwable $e) { /* best-effort */ }
            }

            $esFranq = $user->esFranquiciante();

            // Si publica un franquiciante: avisar a los super_admin.
            // v2.3: usamos $user->nombreCompleto() en lugar de buscar en franchiseStaff
            // (que para un franquiciante siempre era NULL — bug pre-existente).
            if ($esFranq) {
                $nombreAutor = $user->nombreCompleto();
                if ($nombreAutor === '') {
                    $nombreAutor = $user->empresa?->nombre ?? ($user->email ?? 'Franquiciante');
                }

                $superadmins = User::where('rol', 'super_admin')->where('activo', 1)->pluck('id');
                $notifSuper  = $superadmins->map(fn($uid) => [
                    'user_id'             => $uid,
                    'tipo'                => 'modificacion_manual',
                    'manual_id'           => null,
                    'manual_version_id'   => $version->id,
                    'document_id'         => null,
                    'document_version_id' => null,
                    'category_id'         => null,
                    'titulo'              => "El franquiciante {$nombreAutor} publicó una nueva versión de {$manual->titulo} (v{$version->version_number})",
                    'created_at'          => now(),
                ])->toArray();
                try {
                    if (!empty($notifSuper)) {
                        Notification::insert($notifSuper);
                    }
                } catch (\Throwable $e) { /* best-effort */ }
            }

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

        return response()->json([
            'message' => 'Manual publicado correctamente.',
            'manual'  => $manual->fresh('versionActiva'),
        ]);
    }

    // PUT /api/manuales/{manualId}/versiones/{versionId}/nota-publicacion
    // Permite editar (o limpiar) la nota de publicación de una versión específica.
    // Solo super_admin y franquiciantes con acceso al manual.
    public function updateNotaPublicacion(Request $request, int $manualId, int $versionId): JsonResponse
    {
        $request->validate([
            'nota_publicacion' => 'nullable|string|max:2000',
        ]);

        $version = ManualVersion::where('manual_id', $manualId)->findOrFail($versionId);
        $manual  = $version->manual;
        $user    = $request->user();

        if (!$user->esSuperAdmin() && !$user->esFranquiciante()) {
            return response()->json(['error' => 'Sin permisos.'], 403);
        }

        if ($user->esFranquiciante()) {
            $asignado = ManualEmpresaAssignment::where('manual_id', $manualId)
                                            ->where('empresa_id', $user->empresa_id)
                                            ->exists();
            if (!$asignado) {
                return response()->json(['error' => 'Sin acceso a este manual.'], 403);
            }
        }

        // Normalizar: vacío o solo whitespace → NULL
        $nota = trim($request->nota_publicacion ?? '');
        $nota = $nota === '' ? null : $nota;

        $version->update(['nota_publicacion' => $nota]);

        try {
            ActivityLog::registrar(
                userId:      $user->id,
                accion:      'nota_publicacion_editada',
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
        } catch (\Throwable $e) { /* best-effort */ }

        return response()->json([
            'message' => 'Nota de publicación actualizada.',
            'version' => $version,
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
        if ($user->esFranquiciante()) {
            $asignado = ManualEmpresaAssignment::where('manual_id', $id)
                                            ->where('empresa_id', $user->empresa_id)
                                            ->exists();
            if (!$asignado) {
                return response()->json(['error' => 'Sin acceso a este manual.'], 403);
            }
        }

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

        if ($user->esFranquiciante()) {
            $asignado = ManualEmpresaAssignment::where('manual_id', $id)
                                            ->where('empresa_id', $user->empresa_id)
                                            ->exists();
            if (!$asignado) {
                return response()->json(['error' => 'Sin acceso a este manual.'], 403);
            }
        }

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
    private function sanitizarHtml(string $html): string
    {
        $config = HTMLPurifier_Config::createDefault();

        $config->set('HTML.Allowed',
            'h1,h2,h3,p,br,' .
            'strong,em,u,s,' .
            'ul,ol,li,' .
            'table[style],thead,tbody,tr,' .
            'th[style],td[style],' .
            'a[href|target],' .
            'div[style],span[style]'
        );

        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true]);
        $config->set('CSS.AllowedProperties',
            'text-align,font-weight,font-style,text-decoration,width,height'
        );
        $cacheDir = storage_path('app/htmlpurifier');
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }
        $config->set('Cache.SerializerPath', $cacheDir);

        return (new HTMLPurifier($config))->purify($html);
    }

    // DELETE /api/manuales/{id}
    public function destroy(Request $request, int $id): JsonResponse
    {
        $manual = Manual::findOrFail($id);
        $user   = $request->user();

        // Franquiciante solo puede eliminar manuales de su empresa
        if ($user->esFranquiciante()) {
            $asignado = ManualEmpresaAssignment::where('manual_id', $id)
                                            ->where('empresa_id', $user->empresa_id)
                                            ->exists();
            if (!$asignado) {
                return response()->json(['error' => 'Sin acceso a este manual.'], 403);
            }
        }

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
                    'titulo'              => "El franquiciante {$nombreAutor} eliminó el manual \"{$manual->titulo}\"",
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
        if ($user->esFranquiciante()) {
            $asignado = ManualEmpresaAssignment::where('manual_id', $id)
                                            ->where('empresa_id', $user->empresa_id)
                                            ->exists();
            if (!$asignado) {
                return response()->json(['error' => 'Sin acceso a este manual.'], 403);
            }
        }

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
    private function manualesVisiblesParaUsuario(User $user)
    {
        $empresaId = $user->empresa_id;
        $userId    = $user->id;

        return Manual::publicados()
            ->with('versionActiva')
            ->whereHas('empresasAsignadas', fn($q) => $q->where('empresa_id', $empresaId))
            // v2.3: filtro por asignaciones (categoría activa OR individual)
            ->where(function ($q) use ($userId, $empresaId) {
                $q->whereExists(function ($sub) use ($userId, $empresaId) {
                    $sub->select(DB::raw(1))
                        ->from('manual_category_assignments as mca')
                        ->join('user_categories as uc', 'uc.category_id', '=', 'mca.category_id')
                        ->join('franchise_categories as fc', function ($j) {
                            $j->on('fc.id', '=', 'mca.category_id')
                              ->where('fc.is_active', 1);
                        })
                        ->whereColumn('mca.manual_id', 'manuals.id')
                        ->where('mca.empresa_id', $empresaId)
                        ->where('uc.user_id', $userId);
                })->orWhereExists(function ($sub) use ($userId, $empresaId) {
                    $sub->select(DB::raw(1))
                        ->from('manual_user_assignments as mua')
                        ->whereColumn('mua.manual_id', 'manuals.id')
                        ->where('mua.user_id', $userId)
                        ->where('mua.empresa_id', $empresaId);
                });
            })
            ->orderBy('orden')
            ->get()
            ->map(function ($manual) use ($userId) {
                $version = $manual->versionActiva->first();
                $manual->mi_aceptacion = $version
                    ? $version->acceptances()->where('user_id', $userId)->exists()
                    : false;
                return $manual;
            });
    }

    /**
     * v2.3: Verifica si un usuario (franquiciado/empleado) tiene acceso a un manual
     * por categoría activa O asignación individual.
     */
    private function usuarioTieneAccesoAlManual(int $userId, int $manualId, int $empresaId): bool
    {
        $porCategoria = DB::table('manual_category_assignments as mca')
            ->join('user_categories as uc', 'uc.category_id', '=', 'mca.category_id')
            ->join('franchise_categories as fc', function ($j) {
                $j->on('fc.id', '=', 'mca.category_id')
                  ->where('fc.is_active', 1);
            })
            ->where('mca.manual_id', $manualId)
            ->where('mca.empresa_id', $empresaId)
            ->where('uc.user_id', $userId)
            ->exists();

        if ($porCategoria) return true;

        return DB::table('manual_user_assignments')
            ->where('manual_id', $manualId)
            ->where('empresa_id', $empresaId)
            ->where('user_id', $userId)
            ->exists();
    }

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