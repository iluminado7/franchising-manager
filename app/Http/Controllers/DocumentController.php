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
            $documentos = Document::with(['franquicia', 'empresa', 'versionActiva'])
                                  ->where('empresa_id', $user->empresa_id)
                                  ->noEliminados()
                                  ->orderBy('created_at', 'desc')
                                  ->get();

        } elseif ($user->esSuperAdmin()) {
            $query = Document::with(['franquicia', 'empresa', 'versionActiva', 'deletedBy:id,rol'])
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
            $franquiciaId = $user->franchiseStaff->franquicia_id;
            $empresaId    = $user->empresa_id;

            $documentos = Document::with(['franquicia', 'empresa', 'versionActiva'])
                                  ->where('empresa_id', $empresaId)
                                  ->where(function ($q) use ($franquiciaId) {
                                      $q->whereNull('franquicia_id')
                                        ->orWhere('franquicia_id', $franquiciaId);
                                  })
                                  ->visiblesParaFranquiciado()
                                  ->noEliminados()
                                  ->orderBy('created_at', 'desc')
                                  ->get();
        }

        return response()->json($documentos);
    }

    // POST /api/documentos
    // Crea el documento padre + su versión 1 (es_activa = 1).
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'archivo'              => 'required|file|mimes:pdf,doc,docx|max:20480',
            'titulo'               => 'required|string|max:200',
            'tipo'                 => 'required|in:contrato,anexo,acta,otro',
            'franquicia_id'        => 'nullable|integer|exists:franquicias,id',
            'visible_franquiciado' => 'nullable|boolean',
            'empresa_id'           => 'nullable|integer|exists:empresas,id',
            'nota'                 => 'nullable|string|max:500',
        ]);

        $user    = $request->user();
        $archivo = $request->file('archivo');
        $hash    = hash_file('sha256', $archivo->getRealPath());

        $empresaId = $user->esSuperAdmin()
            ? ($request->empresa_id ?? null)
            : $user->empresa_id;

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
                'document_id'    => $doc->id,
                'version_number' => 1,
                'archivo_url'    => $path,
                'archivo_hash'   => $hash,
                'mime_type'      => $archivo->getMimeType(),
                'tamano_bytes'   => $archivo->getSize(),
                'nota'           => $request->nota,
                'es_activa'      => 1,
                'subido_por'     => $user->id,
                'subido_at'      => now(),
            ]);

            return $doc;
        });

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

        return response()->json($documento->load(['franquicia', 'empresa', 'versionActiva']), 201);
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

        $data = $request->validate([
            'titulo'               => 'sometimes|string|max:200',
            'tipo'                 => 'sometimes|in:contrato,anexo,acta,otro',
            'visible_franquiciado' => 'sometimes|boolean',
            'franquicia_id'        => 'nullable|integer|exists:franquicias,id',
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

        return response()->json($documento->load(['franquicia', 'empresa', 'versionActiva']));
    }

    // POST /api/documentos/{id}/version
    // Sube una versión nueva. La anterior queda como "histórica" (es_activa = 0).
    // Solo super_admin o franquiciante con acceso.
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
            'archivo' => 'required|file|mimes:pdf,doc,docx|max:20480',
            'nota'    => 'nullable|string|max:500',
        ]);

        $archivo = $request->file('archivo');
        $hash    = hash_file('sha256', $archivo->getRealPath());
        $disk    = config('filesystems.default');
        $path    = Storage::disk($disk)->putFile('documentos', $archivo);

        $nuevaVersion = DB::transaction(function () use ($documento, $user, $request, $archivo, $hash, $path) {
            // Desactivar la versión activa actual (solo debería haber una).
            DocumentVersion::where('document_id', $documento->id)
                           ->where('es_activa', 1)
                           ->update(['es_activa' => 0]);

            // Siguiente número de versión: MAX(existente) + 1.
            // Importante: contamos también las eliminadas para no reutilizar
            // un version_number ya usado (rompería el UNIQUE de document_id, version_number).
            // El modelo no usa el trait SoftDeletes, así que un where directo
            // ya devuelve TODAS las versiones (incluso las que tienen deleted_at).
            $next = (
                DocumentVersion::where('document_id', $documento->id)
                    ->max('version_number')
            ) + 1;

            return DocumentVersion::create([
                'document_id'    => $documento->id,
                'version_number' => $next,
                'archivo_url'    => $path,
                'archivo_hash'   => $hash,
                'mime_type'      => $archivo->getMimeType(),
                'tamano_bytes'   => $archivo->getSize(),
                'nota'           => $request->nota,
                'es_activa'      => 1,
                'subido_por'     => $user->id,
                'subido_at'      => now(),
            ]);
        });

        $this->notificarDocumento(
            $documento,
            "Nueva versión disponible: {$documento->titulo} (v{$nuevaVersion->version_number})"
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

        return response()->json($nuevaVersion->load('subidoPor.systemAdmin', 'subidoPor.superAdmin'), 201);
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

        $versiones = DocumentVersion::with([
            'subidoPor.systemAdmin',
            'subidoPor.superAdmin',
            'subidoPor.franchiseStaff',
            'deletedBy.systemAdmin',
            'deletedBy.superAdmin',
            'deletedBy.franchiseStaff'
        ])
        ->where('document_id', $id);
        if (!$includeDeleted) {
            $versiones->whereNull('deleted_at');
        }
        $versiones = $versiones
            ->orderByDesc('version_number')
            ->get();
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
        if ($user->esFranquiciante()) {
            $nombreAutor = trim(($user->franchiseStaff?->nombre ?? '') . ' ' . ($user->franchiseStaff?->apellido ?? ''));
            if ($nombreAutor === '') {
                $nombreAutor = $user->empresa?->nombre ?? ($user->email ?? 'Franquiciante');
            }

            $superadmins = User::where('rol', 'super_admin')->where('activo', 1)->pluck('id');
            $notifSuper  = $superadmins->map(fn($uid) => [
                'user_id'           => $uid,
                'tipo'              => 'nuevo_documento',
                'manual_id'         => null,
                'manual_version_id' => null,
                'document_id'       => $documento->id,
                'titulo'            => "El franquiciante {$nombreAutor} eliminó el documento \"{$documento->titulo}\"",
                'created_at'        => now(),
            ])->toArray();

            try {
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

            // Si es la versión activa, promovemos a activa la más reciente
            // (por version_number desc) que no esté eliminada y no sea ésta.
            $promovida = null;
            if ($version->es_activa) {
                $promovida = DocumentVersion::where('document_id', $id)
                    ->where('id', '!=', $version->id)
                    ->whereNull('deleted_at')
                    ->orderByDesc('version_number')
                    ->first();
            }

            DB::transaction(function () use ($version, $promovida, $user) {
                $version->update([
                    'es_activa'  => 0,
                    'deleted_by' => $user->id,
                    'deleted_at' => now(),
                ]);

                if ($promovida) {
                    // Por las dudas, garantizar que no quede ninguna otra activa.
                    DocumentVersion::where('document_id', $promovida->document_id)
                        ->where('id', '!=', $promovida->id)
                        ->where('es_activa', 1)
                        ->update(['es_activa' => 0]);

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
                'message'        => 'Versión eliminada.',
                'nueva_activa_id'=> $promovida?->id,
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
        $promovida = false;

        DB::transaction(function () use ($version, $documento, &$promovida) {
            $version->update([
                'deleted_by' => null,
                'deleted_at' => null,
                'es_activa'  => 0,
            ]);

            // Refrescamos por las dudas
            $version->refresh();

            // Vigente actual entre las NO eliminadas (incluyendo la recién restaurada)
            $vigenteActual = DocumentVersion::where('document_id', $documento->id)
                ->where('id', '!=', $version->id)
                ->where('es_activa', 1)
                ->whereNull('deleted_at')
                ->first();

            // Si no hay vigente, o la restaurada es más nueva → promoverla
            if (!$vigenteActual || $version->version_number > $vigenteActual->version_number) {
                // Desactivar cualquier otra que estuviera activa
                DocumentVersion::where('document_id', $documento->id)
                    ->where('id', '!=', $version->id)
                    ->where('es_activa', 1)
                    ->update(['es_activa' => 0]);

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

        // Franquiciado/empleado solo accede a la versión vigente — nunca al historial
        if (($user->esFranquiciado() || $user->esEmpleado()) && !$version->es_activa) {
            abort(403, 'Sin acceso a versiones anteriores.');
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
     * Notifica a los franquiciados de la empresa (o de la franquicia, si está
     * acotado) cuando se sube un documento nuevo o una versión nueva.
     * Reusa el tipo 'nuevo_documento' (compatible con chk_notif_fk).
     */
    private function notificarDocumento(Document $documento, string $titulo): void
    {
        if (!$documento->visible_franquiciado) return;

        $query = User::where('rol', 'franquiciado')
                     ->where('activo', 1)
                     ->whereNull('deleted_at')
                     ->where('empresa_id', $documento->empresa_id);

        if ($documento->franquicia_id) {
            $query->whereHas('franchiseStaff', fn($q) =>
                $q->where('franquicia_id', $documento->franquicia_id)
            );
        }

        $ids = $query->pluck('id');

        $notificaciones = $ids->map(fn($uid) => [
            'user_id'           => $uid,
            'tipo'              => 'nuevo_documento',
            'manual_id'         => null,
            'manual_version_id' => null,
            'document_id'       => $documento->id,
            'titulo'            => $titulo,
            'created_at'        => now(),
        ])->toArray();

        if (!empty($notificaciones)) {
            try {
                Notification::insert($notificaciones);
            } catch (\Throwable $e) { /* best-effort */ }
        }
    }
}