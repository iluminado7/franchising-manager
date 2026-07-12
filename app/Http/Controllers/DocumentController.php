<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Notification;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    // GET /api/documentos
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Super admin puede ver TODOS los eliminados (incluso los borrados por él) con ?include_deleted=1
        $includeDeleted = (bool) $request->query('include_deleted', false);

        if ($user->esFranquiciante()) {
            $documentos = Document::with(['franquicia', 'empresa', 'versionActiva', 'subidoPor', 'categorias:id,name'])
                                  ->where('empresa_id', $user->empresa_id)
                                  ->noEliminados()
                                  ->orderBy('created_at', 'desc')
                                  ->get();

        } elseif ($user->esSuperAdmin()) {
            $query = Document::with(['franquicia', 'empresa', 'versionActiva', 'subidoPor', 'categorias:id,name', 'deletedBy:id,rol,nombre,apellido'])
                             ->orderBy('created_at', 'desc');
            if ($request->filled('empresa_id')) {
                $query->where('empresa_id', $request->empresa_id);
            }
            // Por defecto: no-eliminados + los eliminados por franquiciantes.
            // Con ?include_deleted=1: también los eliminados por super_admin.
            if (!$includeDeleted) {
                $query->visiblesParaSuperAdmin();
            }
            $documentos = $query->get();

        } else {
            // Franquiciado / empleado.
            // v2.3: además de los filtros legacy (empresa, franquicia, visible_franquiciado),
            // el documento debe estar asignado al usuario por categoría activa O individualmente.
            $franquiciaId = $user->franchiseStaff->franquicia_id;
            $empresaId    = $user->empresa_id;
            $userId       = $user->id;

            $documentos = Document::with(['franquicia', 'empresa', 'versionActiva', 'subidoPor', 'categorias:id,name'])
                                  ->where('empresa_id', $empresaId)
                                  ->where(function ($q) use ($franquiciaId) {
                                      $q->whereNull('franquicia_id')
                                        ->orWhere('franquicia_id', $franquiciaId);
                                  })
                                  ->visiblesParaFranquiciado()
                                  ->noEliminados()
                                  // v2.3: filtro por asignaciones (categoría activa O individual)
                                  ->where(function ($q) use ($userId, $empresaId) {
                                      $q->whereExists(function ($sub) use ($userId, $empresaId) {
                                          $sub->select(DB::raw(1))
                                              ->from('document_category_assignments as dca')
                                              ->join('user_categories as uc', 'uc.category_id', '=', 'dca.category_id')
                                              ->join('franchise_categories as fc', function ($j) {
                                                  $j->on('fc.id', '=', 'dca.category_id')
                                                    ->where('fc.is_active', 1);
                                              })
                                              ->whereColumn('dca.document_id', 'documents.id')
                                              ->where('dca.empresa_id', $empresaId)
                                              ->where('uc.user_id', $userId);
                                      })->orWhereExists(function ($sub) use ($userId, $empresaId) {
                                          $sub->select(DB::raw(1))
                                              ->from('document_user_assignments as dua')
                                              ->whereColumn('dua.document_id', 'documents.id')
                                              ->where('dua.user_id', $userId)
                                              ->where('dua.empresa_id', $empresaId);
                                      });
                                  })
                                  ->orderBy('created_at', 'desc')
                                  ->get();
        }

        return response()->json($documentos);
    }

    // POST /api/documentos
    // Crea el documento padre + su versión 1 (es_activa = 1).
    // v2.3: no auto-asigna a ninguna categoría. La asignación a categorías/usuarios
    // se hace en un endpoint separado (DocumentAssignment). Por ese motivo,
    // notificarDocumento() no notifica a nadie en este punto (no hay asignaciones).
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        // H-003 fix: resolver empresa_id ANTES de la validación para poder
        // validar que franquicia_id pertenezca a esa empresa. Antes, cualquier
        // franquicia existente en el sistema era aceptada, lo que permitía
        // asociar el documento a una franquicia de otra empresa.
        $empresaId = $user->esSuperAdmin()
            ? ($request->empresa_id ?? null)
            : $user->empresa_id;

        $request->validate([
            'archivo'              => 'required|file|mimes:pdf,doc,docx|max:20480',
            'titulo'               => 'required|string|max:200',
            'tipo'                 => 'required|in:contrato,politica,protocolo,circular,anexo,acta,procedimiento,otro',
            'franquicia_id'        => [
                'nullable', 'integer',
                Rule::exists('franquicias', 'id')->where(
                    fn($q) => $q->where('empresa_id', $empresaId)
                ),
            ],
            'visible_franquiciado' => 'nullable|boolean',
            'empresa_id'           => 'nullable|integer|exists:empresas,id',
            'nota'                 => 'nullable|string|max:500',
        ]);

        $archivo = $request->file('archivo');
        $hash    = hash_file('sha256', $archivo->getRealPath());

        $disk = config('filesystems.default');
        $path = Storage::disk($disk)->putFile('documentos', $archivo);

        $documento = DB::transaction(function () use ($user, $request, $empresaId, $archivo, $hash, $path) {
            $doc = Document::create([
                'empresa_id'           => $empresaId,
                'titulo'               => $request->titulo,
                'tipo'                 => $request->tipo,
                'subido_por'           => $user->id,
                'franquicia_id'        => $request->franquicia_id,
                'visible_franquiciado' => $request->visible_franquiciado ?? true,
            ]);

            DocumentVersion::create([
                'document_id'         => $doc->id,
                'version_number'      => 1,
                'version_minor'       => 0,
                'previous_version_id' => null,   // v2.3: primera versión, sin antecesora
                'archivo_url'         => $path,
                'archivo_hash'        => $hash,
                'mime_type'           => $archivo->getMimeType(),
                'tamano_bytes'        => $archivo->getSize(),
                'nota'                => $request->nota,
                'es_activa'           => 1,
                'subido_por'          => $user->id,
                'subido_at'           => now(),
            ]);

            return $doc;
        });

        // v2.3: si el documento ya tiene asignaciones (raro al crear, pero posible),
        // notifica. Si no, este método no hace nada — esperado.
        $this->notificarDocumento($documento, "Nuevo documento disponible: {$documento->titulo}");

        ActivityLog::registrar(
            userId:      $user->id,
            accion:      'documento_subido',
            ip:          $request->ip(),
            empresaId:   $empresaId,
            entidadTipo: 'documents',
            entidadId:   $documento->id,
            userAgent:   $request->userAgent()
        );

        return response()->json($documento->load(['franquicia', 'empresa', 'versionActiva', 'subidoPor']), 201);
    }

    // PUT /api/documentos/{id}
    // Edita los metadatos del documento padre (titulo, tipo, visibilidad, franquicia).
    // No toca las versiones.
    public function update(Request $request, int $id): JsonResponse
    {
        $documento = Document::findOrFail($id);
        $user      = $request->user();

        if ($user->esFranquiciante() && $documento->empresa_id !== $user->empresa_id) {
            return response()->json(['error' => 'Sin acceso a este documento.'], 403);
        }
        if (!$user->esSuperAdmin() && !$user->esFranquiciante()) {
            return response()->json(['error' => 'Sin permisos.'], 403);
        }

        // H-003 fix: la franquicia debe pertenecer a la empresa del documento.
        // El $documento->empresa_id ya está validado por el gate al inicio del método.
        $data = $request->validate([
            'titulo'               => 'sometimes|string|max:200',
            'tipo'                 => 'sometimes|in:contrato,politica,protocolo,circular,anexo,acta,procedimiento,otro',
            'visible_franquiciado' => 'sometimes|boolean',
            'franquicia_id'        => [
                'nullable', 'integer',
                Rule::exists('franquicias', 'id')->where(
                    fn($q) => $q->where('empresa_id', $documento->empresa_id)
                ),
            ],
        ]);

        $documento->update($data);

        ActivityLog::registrar(
            userId:      $user->id,
            accion:      'documento_editado',
            ip:          $request->ip(),
            empresaId:   $documento->empresa_id,
            entidadTipo: 'documents',
            entidadId:   $documento->id,
            userAgent:   $request->userAgent()
        );

        return response()->json($documento->load(['franquicia', 'empresa', 'versionActiva', 'subidoPor']));
    }

    // POST /api/documentos/{id}/version
    // Sube una versión nueva. La anterior queda como "histórica" (es_activa = 0).
    // Solo super_admin o franquiciante con acceso.
    //
    // v2.3:
    //  - lockForUpdate() para evitar race conditions con uploads concurrentes
    //  - previous_version_id se completa con el ID de la versión que estaba activa
    //  - notifica con tipo nueva_version_documento (en vez de nuevo_documento)
    public function subirVersion(Request $request, int $id): JsonResponse
    {
        $documento = Document::findOrFail($id);
        $user      = $request->user();

        if ($documento->deleted_at !== null) {
            return response()->json(['error' => 'No se puede subir una versión a un documento eliminado.'], 409);
        }
        if (!$user->esSuperAdmin() && !$user->esFranquiciante()) {
            return response()->json(['error' => 'Sin permisos.'], 403);
        }
        if ($user->esFranquiciante() && $documento->empresa_id !== $user->empresa_id) {
            return response()->json(['error' => 'Sin acceso a este documento.'], 403);
        }

        $request->validate([
            'archivo'     => 'required|file|mimes:pdf,doc,docx|max:20480',
            'nota'        => 'nullable|string|max:500',
            'tipo_cambio' => 'nullable|in:mayor,menor',
        ]);

        $archivo = $request->file('archivo');
        $hash    = hash_file('sha256', $archivo->getRealPath());
        $disk    = config('filesystems.default');
        $path    = Storage::disk($disk)->putFile('documentos', $archivo);

        try {
            $nuevaVersion = DB::transaction(function () use ($documento, $user, $request, $archivo, $hash, $path) {
                // v2.3: lock pesimista sobre la versión activa actual.
                // Evita race conditions cuando dos uploads concurrentes leerían
                // el mismo es_activa = 1 y romperían el UNIQUE generado uq_dv_es_activa.
                $activaAnterior = DocumentVersion::where('document_id', $documento->id)
                                                 ->where('es_activa', 1)
                                                 ->lockForUpdate()
                                                 ->first();

                // Desactivar la anterior (si existe). El UNIQUE generado pasa a NULL
                // en esa fila al cambiar es_activa a 0.
                if ($activaAnterior) {
                    $activaAnterior->update(['es_activa' => 0]);
                }

                // Cálculo mayor/menor. Contamos también las eliminadas (no hay trait
                // SoftDeletes) para no reutilizar un número ya usado y romper el UNIQUE.
                //  - menor (sobre la activa) → mismo número, minor = max(minor) + 1
                //  - mayor (default)         → max(número) + 1, minor 0
                $maxNumber = DocumentVersion::where('document_id', $documento->id)
                                ->max('version_number') ?? 0;

                if ($request->tipo_cambio === 'menor' && $activaAnterior) {
                    $nuevoNumber = $activaAnterior->version_number;
                    $nuevoMinor  = (DocumentVersion::where('document_id', $documento->id)
                                        ->where('version_number', $nuevoNumber)
                                        ->max('version_minor') ?? 0) + 1;
                } else {
                    $nuevoNumber = $maxNumber + 1;
                    $nuevoMinor  = 0;
                }

                return DocumentVersion::create([
                    'document_id'         => $documento->id,
                    'version_number'      => $nuevoNumber,
                    'version_minor'       => $nuevoMinor,
                    'previous_version_id' => $activaAnterior?->id,   // v2.3: cadena de versiones
                    'archivo_url'         => $path,
                    'archivo_hash'        => $hash,
                    'mime_type'           => $archivo->getMimeType(),
                    'tamano_bytes'        => $archivo->getSize(),
                    'nota'                => $request->nota,
                    'es_activa'           => 1,
                    'subido_por'          => $user->id,
                    'subido_at'           => now(),
                ]);
            });
        } catch (\Illuminate\Database\QueryException $e) {
            // Violación del UNIQUE uq_doc_version: dos uploads que calcularon el
            // mismo número.minor casi a la vez. Mensaje claro en vez de un 500.
            if ($e->getCode() === '23000') {
                return response()->json([
                    'error' => 'Esa versión ya existe para este documento. Actualizá la página e intentá de nuevo.',
                ], 409);
            }
            throw $e;
        }

        // v2.3: notifica con tipo nueva_version_documento (con document_version_id)
        $this->notificarDocumento(
            $documento,
            "Nueva versión disponible: {$documento->titulo} (v{$nuevaVersion->version_label})",
            $nuevaVersion
        );

        ActivityLog::registrar(
            userId:      $user->id,
            accion:      'nueva_version_documento',
            ip:          $request->ip(),
            empresaId:   $documento->empresa_id,
            entidadTipo: 'documents',
            entidadId:   $documento->id,
            userAgent:   $request->userAgent()
        );

        return response()->json($nuevaVersion->load('subidoPor'), 201);
    }

    // GET /api/documentos/{id}/versiones
    // Historial completo del documento. Solo super_admin / franquiciante.
    public function versiones(Request $request, int $id): JsonResponse
    {
        $documento = Document::findOrFail($id);
        $user      = $request->user();

        if (!$user->esSuperAdmin() && !$user->esFranquiciante()) {
            return response()->json(['error' => 'Sin permisos.'], 403);
        }
        if ($user->esFranquiciante() && $documento->empresa_id !== $user->empresa_id) {
            return response()->json(['error' => 'Sin acceso a este documento.'], 403);
        }

        $includeDeleted = (bool) $request->query('include_deleted', false);

        // v2.3: subidoPor y deletedBy se simplifican — nombre/apellido viven en users.
        $versiones = DocumentVersion::with(['subidoPor', 'deletedBy', 'previousVersion'])
                                    ->where('document_id', $id);
        if (!$includeDeleted) {
            $versiones->whereNull('deleted_at');
        }
        $versiones = $versiones->orderByDesc('version_number')->get();

        return response()->json($versiones);
    }

    // PUT /api/documentos/{id}/versiones/{versionId}/nota
    // Permite editar (o limpiar) la nota de una versión. Util si se subió sin nota
    // o si quien la subió quiere corregirla.
    public function updateNota(Request $request, int $id, int $versionId): JsonResponse
    {
        $version   = DocumentVersion::where('document_id', $id)->findOrFail($versionId);
        $documento = $version->document;
        $user      = $request->user();

        if (!$user->esSuperAdmin() && !$user->esFranquiciante()) {
            return response()->json(['error' => 'Sin permisos.'], 403);
        }
        if ($user->esFranquiciante() && $documento->empresa_id !== $user->empresa_id) {
            return response()->json(['error' => 'Sin acceso a este documento.'], 403);
        }

        $data = $request->validate([
            'nota' => 'nullable|string|max:500',
        ]);

        $version->update(['nota' => $data['nota'] ?? null]);

        return response()->json($version);
    }

    // GET /api/documentos/{id}/descargar  → versión activa
    public function descargar(Request $request, int $id): StreamedResponse
    {
        $documento = Document::findOrFail($id);
        $version   = $documento->versiones()->where('es_activa', 1)->first();
        if (!$version) abort(404, 'Documento sin versión activa.');
        return $this->streamDocumento($request, $documento, $version, 'attachment');
    }

    // GET /api/documentos/{id}/preview  → versión activa, inline
    public function preview(Request $request, int $id): StreamedResponse
    {
        $documento = Document::findOrFail($id);
        $version   = $documento->versiones()->where('es_activa', 1)->first();
        if (!$version) abort(404, 'Documento sin versión activa.');
        return $this->streamDocumento($request, $documento, $version, 'inline');
    }

    // GET /api/documentos/{id}/versiones/{versionId}/descargar  → versión específica
    public function descargarVersion(Request $request, int $id, int $versionId): StreamedResponse
    {
        $version   = DocumentVersion::where('document_id', $id)->findOrFail($versionId);
        $documento = $version->document;
        return $this->streamDocumento($request, $documento, $version, 'attachment');
    }

    // GET /api/documentos/{id}/versiones/{versionId}/preview  → versión específica, inline
    public function previewVersion(Request $request, int $id, int $versionId): StreamedResponse
    {
        $version   = DocumentVersion::where('document_id', $id)->findOrFail($versionId);
        $documento = $version->document;
        return $this->streamDocumento($request, $documento, $version, 'inline');
    }

    // DELETE /api/documentos/{id}
    // Soft delete del documento padre. Las versiones quedan intactas (no se ven mientras el padre esté eliminado).
    public function destroy(Request $request, int $id): JsonResponse
    {
        $documento = Document::findOrFail($id);
        $user      = $request->user();

        if ($user->esFranquiciante()) {
            if ($documento->empresa_id !== $user->empresa_id) {
                return response()->json(['error' => 'Sin acceso a este documento.'], 403);
            }
        }

        $documento->update([
            'deleted_by' => $user->id,
            'deleted_at' => now(),
        ]);

        // Si quien elimina es franquiciante: avisar a los super_admin.
        // v2.3: nombre/apellido viven en users — usamos nombreCompleto() del User
        // (antes el código buscaba en franchiseStaff, que para un franquiciante
        // siempre era NULL, ese era un bug pre-existente).
        if ($user->esFranquiciante()) {
            $nombreAutor = $user->nombreCompleto();
            if ($nombreAutor === '') {
                $nombreAutor = $user->empresa?->nombre ?? ($user->email ?? 'Franquiciante');
            }
            // H-020 fix: sanitizar el nombre del autor (defense-in-depth).
            $nombreAutor = strip_tags($nombreAutor);

            $superadmins = User::where('rol', 'super_admin')->where('activo', 1)->pluck('id');
            $notifSuper  = $superadmins->map(fn($uid) => [
                'user_id'             => $uid,
                'tipo'                => 'nuevo_documento',
                'manual_id'           => null,
                'manual_version_id'   => null,
                'document_id'         => $documento->id,
                'document_version_id' => null,
                'category_id'         => null,
                'titulo'              => "El franquiciante {$nombreAutor} eliminó el documento \"" . strip_tags($documento->titulo ?? '') . "\"",
                'created_at'          => now(),
            ])->toArray();

            try {
                // insert() a proposito: los super_admin reciben la notif in-app (aviso
                // de gestion) pero NO email — no son destinatarios del documento.
                // insert() saltea el observer, asi que no se encola mail.
                if (!empty($notifSuper)) Notification::insert($notifSuper);
            } catch (\Throwable $e) { /* best-effort */ }
        }

        ActivityLog::registrar(
            userId:      $user->id,
            accion:      'documento_eliminado',
            ip:          $request->ip(),
            empresaId:   $user->empresa_id,
            entidadTipo: 'documents',
            entidadId:   $documento->id,
            userAgent:   $request->userAgent()
        );

        return response()->json(['message' => 'Documento eliminado correctamente.']);
    }

    // DELETE /api/documentos/{id}/versiones/{versionId}
    public function destroyVersion(Request $request, int $id, int $versionId): JsonResponse
    {
        $user = $request->user();

        $version = DocumentVersion::where('document_id', $id)
            ->findOrFail($versionId);

        $documento = $version->document;

        if (!$user->esSuperAdmin() && !$user->esFranquiciante()) {
            return response()->json(['error' => 'Sin permisos.'], 403);
        }

        if ($user->esFranquiciante() &&
            $documento->empresa_id !== $user->empresa_id) {
            return response()->json(['error' => 'Sin acceso.'], 403);
        }

        if ($version->deleted_at) {
            return response()->json([
                'error' => 'La versión ya fue eliminada.'
            ], 409);
        }

        // Si es la única versión disponible (no eliminada), no permitir borrar:
        // hay que eliminar el documento completo en su lugar.
        $disponibles = DocumentVersion::where('document_id', $id)
            ->whereNull('deleted_at')
            ->count();
        if ($disponibles <= 1) {
            return response()->json([
                'error' => 'No podés eliminar la única versión disponible. Eliminá el documento completo.'
            ], 409);
        }

        DB::transaction(function () use ($version, $id, $user) {
            // v2.3: lockForUpdate sobre todas las versiones del documento para
            // evitar carreras con uploads/restores concurrentes.
            $version->lockForUpdate()->refresh();

            // Si la versión a eliminar es la activa, promover la más reciente
            // (por version_number desc) entre las disponibles, distinta a ésta.
            $promovida = null;
            if ($version->es_activa) {
                $promovida = DocumentVersion::where('document_id', $id)
                    ->where('id', '!=', $version->id)
                    ->whereNull('deleted_at')
                    ->orderByDesc('version_number')
                    ->lockForUpdate()
                    ->first();
            }

            // Primero desactivar y marcar como eliminada (libera el UNIQUE generado)
            $version->update([
                'es_activa'  => 0,
                'deleted_by' => $user->id,
                'deleted_at' => now(),
            ]);

            // Después promover la nueva activa, si corresponde
            if ($promovida) {
                $promovida->update(['es_activa' => 1]);
            }
        });

        try {
            ActivityLog::registrar(
                userId: $user->id,
                accion: 'version_documento_eliminada',
                ip: $request->ip(),
                empresaId: $documento->empresa_id,
                entidadTipo: 'document_versions',
                entidadId: $version->id,
                userAgent: $request->userAgent()
            );
        } catch (\Throwable $e) { /* best-effort: no romper la respuesta si el log falla */ }

        return response()->json([
            'message' => 'Versión eliminada.',
        ]);
    }

    // POST /api/documentos/{id}/restore
    public function restore(Request $request, int $id): JsonResponse
    {
        $documento = Document::findOrFail($id);
        $user      = $request->user();

        if ($user->esFranquiciante()) {
            if ($documento->empresa_id !== $user->empresa_id) {
                return response()->json(['error' => 'Sin acceso a este documento.'], 403);
            }
        }

        $documento->update([
            'deleted_by' => null,
            'deleted_at' => null,
        ]);

        ActivityLog::registrar(
            userId:      $user->id,
            accion:      'documento_restaurado',
            ip:          $request->ip(),
            empresaId:   $user->empresa_id,
            entidadTipo: 'documents',
            entidadId:   $documento->id,
            userAgent:   $request->userAgent()
        );

        return response()->json(['message' => 'Documento restaurado correctamente.']);
    }

    public function restoreVersion(
        Request $request,
        int $id,
        int $versionId
    ): JsonResponse {

        $version = DocumentVersion::where(
            'document_id',
            $id
        )->findOrFail($versionId);

        $documento = $version->document;
        $user      = $request->user();

        if (!$user->esSuperAdmin() && !$user->esFranquiciante()) {
            return response()->json(['error' => 'Sin permisos.'], 403);
        }

        if ($user->esFranquiciante() &&
            $documento->empresa_id !== $user->empresa_id) {
            return response()->json(['error' => 'Sin acceso.'], 403);
        }

        if ($version->deleted_at === null) {
            return response()->json([
                'error' => 'La versión no está eliminada.'
            ], 409);
        }

        // Restaurar dentro de una transacción.
        // Lógica de re-promoción: si la versión restaurada tiene el mayor
        // version_number entre las disponibles, vuelve a ser la vigente
        // y se desactiva la que estaba marcada como activa.
        //
        // v2.3: lockForUpdate para evitar carreras con uploads/destroyVersion concurrentes.
        $promovida = false;

        DB::transaction(function () use ($version, $documento, &$promovida) {
            // Lock sobre la versión a restaurar
            $version->lockForUpdate()->refresh();

            // Restaurar inactiva primero (no choca con UNIQUE generado)
            $version->update([
                'deleted_by' => null,
                'deleted_at' => null,
                'es_activa'  => 0,
            ]);
            $version->refresh();

            // Vigente actual entre las NO eliminadas (lockeada también)
            $vigenteActual = DocumentVersion::where('document_id', $documento->id)
                ->where('id', '!=', $version->id)
                ->where('es_activa', 1)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();

            // Si no hay vigente, o la restaurada es más nueva → promoverla
            if (!$vigenteActual || $version->version_number > $vigenteActual->version_number) {
                if ($vigenteActual) {
                    $vigenteActual->update(['es_activa' => 0]);
                }
                $version->update(['es_activa' => 1]);
                $promovida = true;
            }
        });

        try {
            ActivityLog::registrar(
                userId: $user->id,
                accion: 'version_documento_restaurada',
                ip: $request->ip(),
                empresaId: $documento->empresa_id,
                entidadTipo: 'document_versions',
                entidadId: $version->id,
                userAgent: $request->userAgent()
            );
        } catch (\Throwable $e) { /* best-effort: no romper la respuesta si el log falla */ }

        return response()->json([
            'message'   => $promovida
                ? 'Versión restaurada y promovida a vigente.'
                : 'Versión restaurada (queda en el historial como inactiva).',
            'promovida' => $promovida,
        ]);
    }


    // ── PRIVADOS ──────────────────────────────────────────────────────

    /**
     * Stream común para descargar/preview de cualquier versión.
     * Aplica todos los controles de acceso (eliminado, empresa, visibilidad, rol).
     *
     * v2.3: agrega chequeo de asignación (categoría activa o individual) para
     * franquiciado/empleado. Sin esto, podrían descargar URL directa de un
     * documento al que no les corresponde acceso.
     */
    private function streamDocumento(Request $request, Document $documento, DocumentVersion $version, string $disposition): StreamedResponse
    {
        $user = $request->user();

        // Si el documento está eliminado, solo super_admin puede acceder
        if ($documento->deleted_at !== null && !$user->esSuperAdmin()) {
            abort(403, 'Sin acceso a este documento.');
        }

        // Verificación de empresa/visibilidad
        if ($user->esFranquiciante() || $user->esFranquiciado() || $user->esEmpleado()) {
            if ($documento->empresa_id !== $user->empresa_id) {
                abort(403, 'Sin acceso a este documento.');
            }
            if (!$user->esFranquiciante() && !$documento->visible_franquiciado) {
                abort(403, 'Sin acceso a este documento.');
            }
        }

        // Franquiciado/empleado: chequeos extra
        if ($user->esFranquiciado() || $user->esEmpleado()) {
            // Solo accede a la versión vigente — nunca al historial
            if (!$version->es_activa) {
                abort(403, 'Sin acceso a versiones anteriores.');
            }

            // Franquicia legacy: si el documento está acotado a una franquicia,
            // solo usuarios de esa franquicia pueden acceder.
            if ($documento->franquicia_id !== null) {
                if ($documento->franquicia_id !== $user->franchiseStaff?->franquicia_id) {
                    abort(403, 'Sin acceso a este documento.');
                }
            }

            // v2.3: el documento debe estar asignado al usuario por categoría
            // activa o individualmente. Sin asignación, no hay acceso.
            $tieneAcceso = $this->usuarioTieneAccesoAlDocumento($user->id, $documento);
            if (!$tieneAcceso) {
                abort(403, 'Sin acceso a este documento.');
            }
        }

        if ($version->deleted_at !== null) {
            abort(404,'Versión eliminada.');
        }
        $disk = config('filesystems.default');
        if (!Storage::disk($disk)->exists($version->archivo_url)) {
            abort(404, 'Archivo no encontrado.');
        }

        $extension = pathinfo($version->archivo_url, PATHINFO_EXTENSION);
        $nombre    = $documento->titulo . '_v' . $version->version_number . '.' . $extension;

        $headers = [
            'Content-Type'   => $version->mime_type,
            'Content-Length' => $version->tamano_bytes,
        ];

        if ($disposition === 'inline') {
            return response()->streamDownload(function () use ($disk, $version) {
                echo Storage::disk($disk)->get($version->archivo_url);
            }, $nombre, $headers, 'inline');
        }

        return response()->streamDownload(function () use ($disk, $version) {
            echo Storage::disk($disk)->get($version->archivo_url);
        }, $nombre, $headers);
    }

    /**
     * v2.3: Verifica si un usuario (franquiciado/empleado) tiene acceso al documento
     * según las asignaciones por categoría activa o individuales.
     * Reutilizado por streamDocumento y por notificarDocumento.
     */
    private function usuarioTieneAccesoAlDocumento(int $userId, Document $documento): bool
    {
        // Asignación por categoría activa
        $porCategoria = DB::table('document_category_assignments as dca')
            ->join('user_categories as uc', 'uc.category_id', '=', 'dca.category_id')
            ->join('franchise_categories as fc', function ($j) {
                $j->on('fc.id', '=', 'dca.category_id')
                  ->where('fc.is_active', 1);
            })
            ->where('dca.document_id', $documento->id)
            ->where('dca.empresa_id', $documento->empresa_id)
            ->where('uc.user_id', $userId)
            ->exists();

        if ($porCategoria) return true;

        // Asignación individual
        return DB::table('document_user_assignments')
            ->where('document_id', $documento->id)
            ->where('empresa_id', $documento->empresa_id)
            ->where('user_id', $userId)
            ->exists();
    }

    /**
     * Notifica a los usuarios asignados al documento (por categoría o individual)
     * cuando se sube un documento nuevo o una versión nueva.
     *
     * v2.3:
     *  - Acepta opcionalmente $version. Si se pasa, dispara tipo nueva_version_documento
     *    con document_version_id (en vez de nuevo_documento con document_id).
     *  - Incluye empleados además de franquiciados.
     *  - Solo notifica a usuarios con asignación efectiva al documento. Si el
     *    documento no tiene asignaciones todavía, NO notifica a nadie — coherente
     *    con la decisión 10.2 (sin categoría = no ve nada).
     */
    private function notificarDocumento(
        Document $documento,
        string $titulo,
        ?DocumentVersion $version = null
    ): void
    {
        if (!$documento->visible_franquiciado) return;

        // Determinar tipo y FK según contexto
        if ($version) {
            $tipo                = 'nueva_version_documento';
            $documentIdNotif     = null;
            $documentVersionId   = $version->id;
        } else {
            $tipo                = 'nuevo_documento';
            $documentIdNotif     = $documento->id;
            $documentVersionId   = null;
        }

        // 1. Recolectar IDs de usuarios con el documento asignado
        $idsPorCategoria = DB::table('document_category_assignments as dca')
            ->join('user_categories as uc', 'uc.category_id', '=', 'dca.category_id')
            ->join('franchise_categories as fc', function ($j) {
                $j->on('fc.id', '=', 'dca.category_id')
                  ->where('fc.is_active', 1);
            })
            ->where('dca.document_id', $documento->id)
            ->where('dca.empresa_id', $documento->empresa_id)
            ->pluck('uc.user_id');

        $idsIndividuales = DB::table('document_user_assignments')
            ->where('document_id', $documento->id)
            ->where('empresa_id', $documento->empresa_id)
            ->pluck('user_id');

        $candidateIds = $idsPorCategoria->merge($idsIndividuales)->unique();

        if ($candidateIds->isEmpty()) {
            return; // documento sin asignaciones → no notifica
        }

        // 2. Filtrar candidatos: activos, franquiciado/empleado, misma empresa,
        //    y si el documento está acotado a una franquicia, misma franquicia.
        $query = User::whereIn('id', $candidateIds)
                     ->whereIn('rol', ['franquiciado', 'empleado'])
                     ->where('activo', 1)
                     ->whereNull('deleted_at')
                     ->where('empresa_id', $documento->empresa_id);

        if ($documento->franquicia_id) {
            $query->whereHas('franchiseStaff', fn($q) =>
                $q->where('franquicia_id', $documento->franquicia_id)
            );
        }

        $userIds = $query->pluck('id');

        if ($userIds->isEmpty()) return;

        // 3. Crear las notificaciones.
        //    create() (no insert()) para que dispare el observer de Notification,
        //    que encola el email a cada destinatario. insert() es un bulk que saltea
        //    el modelo y por ende el observer — con insert() no saldria ningun mail.
        foreach ($userIds as $uid) {
            try {
                Notification::create([
                    'user_id'             => $uid,
                    'tipo'                => $tipo,
                    'manual_id'           => null,
                    'manual_version_id'   => null,
                    'document_id'         => $documentIdNotif,
                    'document_version_id' => $documentVersionId,
                    'category_id'         => null,
                    'titulo'              => $titulo,
                    'created_at'          => now(),
                ]);
            } catch (\Throwable $e) { /* best-effort: una notif fallida no corta las demas */ }
        }
    }
}