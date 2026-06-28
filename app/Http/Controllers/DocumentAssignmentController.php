<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentCategoryAssignment;
use App\Models\DocumentUserAssignment;
use App\Models\FranchiseCategory;
use App\Models\User;
use App\Models\Notification;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Gestiona las asignaciones de un documento:
 *   - a CATEGORÍAS (alcanza a todos los usuarios con esa categoría)
 *   - a USUARIOS INDIVIDUALES (excepciones puntuales)
 *
 * Ambos caminos coexisten — un documento puede estar asignado por los dos
 * simultáneamente. La visibilidad final es OR (cualquiera de los dos alcanza).
 *
 * Diferencia con manuales: documents.empresa_id es la fuente única de verdad
 * (un doc pertenece a UNA sola empresa). No hace falta pasar empresa_id en el body.
 *
 * Permisos:
 *   - super_admin: cualquier documento ↔ cualquier categoría/usuario
 *   - franquiciante: solo docs de su empresa ↔ categorías/usuarios de su empresa
 *   - franquiciado/empleado: sin permisos
 */
class DocumentAssignmentController extends Controller
{
    // ════════════════════════════════════════════════════════════════
    // ── CATEGORÍAS
    // ════════════════════════════════════════════════════════════════

    // GET /api/documentos/{documentId}/categorias
    public function listarCategorias(Request $request, int $documentId): JsonResponse
    {
        $actor    = $request->user();
        $document = Document::findOrFail($documentId);

        if (!$this->actorPuedeVerDocumento($actor, $document)) {
            return response()->json(['error' => 'Sin acceso a este documento.'], 403);
        }

        $asignaciones = DocumentCategoryAssignment::with('category')
                                                   ->where('document_id', $documentId)
                                                   ->get();

        return response()->json($asignaciones);
    }

    // GET /api/categorias/{categoryId}/documentos
    // Endpoint inverso: lista documentos asignados a una categoría.
    public function porCategoria(Request $request, int $categoryId): JsonResponse
    {
        $actor     = $request->user();
        $categoria = FranchiseCategory::findOrFail($categoryId);

        if (!$actor->esSuperAdmin() && $categoria->empresa_id !== $actor->empresa_id) {
            return response()->json(['error' => 'Sin acceso a esta categoría.'], 403);
        }

        $asignaciones = DocumentCategoryAssignment::with('document.versionActiva')
                                                   ->where('category_id', $categoryId)
                                                   ->get();

        return response()->json($asignaciones);
    }

    // POST /api/documentos/{documentId}/categorias
    // Body: { "category_id": N }
    public function asignarCategoria(Request $request, int $documentId): JsonResponse
    {
        $actor    = $request->user();
        $document = Document::findOrFail($documentId);

        $error = $this->validarDocumentoOperable($actor, $document);
        if ($error) return response()->json($error, $error['_status']);

        $data = $request->validate([
            'category_id' => 'required|integer|exists:franchise_categories,id',
        ]);

        $categoria = FranchiseCategory::findOrFail($data['category_id']);

        $error = $this->validarAsignacionCategoria($document, $categoria);
        if ($error) return response()->json($error, $error['_status']);

        // Duplicado
        $existe = DocumentCategoryAssignment::where('empresa_id', $document->empresa_id)
            ->where('document_id', $document->id)
            ->where('category_id', $categoria->id)
            ->exists();

        if ($existe) {
            return response()->json([
                'message' => 'El documento ya está asignado a esta categoría.',
            ], 409);
        }

        $asignacion = DocumentCategoryAssignment::create([
            'empresa_id'  => $document->empresa_id,
            'document_id' => $document->id,
            'category_id' => $categoria->id,
            'assigned_by' => $actor->id,
            'assigned_at' => now(),
        ]);

        $this->notificarDocumentoAsignadoACategoria($document, $categoria);

        ActivityLog::registrar(
            userId:      $actor->id,
            accion:      'documento_asignado_categoria',
            ip:          $request->ip(),
            empresaId:   $document->empresa_id,
            entidadTipo: 'document_category_assignments',
            entidadId:   $asignacion->id,
            detalle:     [
                'documento_titulo' => $document->titulo,
                'categoria_nombre' => $categoria->name,
            ],
            userAgent:   $request->userAgent()
        );

        return response()->json([
            'message'    => 'Documento asignado a la categoría correctamente.',
            'asignacion' => $asignacion->load('category'),
        ], 201);
    }

    // PUT /api/documentos/{documentId}/categorias
    // Body: { "category_ids": [1, 2, 3] }
    public function sincronizarCategorias(Request $request, int $documentId): JsonResponse
    {
        $actor    = $request->user();
        $document = Document::findOrFail($documentId);

        $error = $this->validarDocumentoOperable($actor, $document);
        if ($error) return response()->json($error, $error['_status']);

        $data = $request->validate([
            'category_ids'   => 'present|array',
            'category_ids.*' => 'integer|exists:franchise_categories,id',
        ]);

        // Validar todas las categorías (misma empresa, activas)
        $categorias = FranchiseCategory::whereIn('id', $data['category_ids'])->get();

        foreach ($categorias as $cat) {
            if ($cat->empresa_id !== $document->empresa_id) {
                return response()->json([
                    'error' => "La categoría \"{$cat->name}\" no pertenece a la empresa del documento.",
                ], 422);
            }
            if (!$cat->is_active) {
                return response()->json([
                    'error' => "La categoría \"{$cat->name}\" está desactivada. Reactivala antes de asignar.",
                ], 422);
            }
        }

        $actuales = DocumentCategoryAssignment::where('document_id', $document->id)
                                               ->pluck('category_id')
                                               ->toArray();

        $nuevas   = collect($data['category_ids'])->unique()->values();
        $aAgregar = $nuevas->diff($actuales);
        $aQuitar  = collect($actuales)->diff($nuevas);

        DB::transaction(function () use ($document, $actor, $aAgregar, $aQuitar, $categorias, $request) {
            // Attach nuevas
            foreach ($aAgregar as $catId) {
                $cat = $categorias->firstWhere('id', $catId);

                $asg = DocumentCategoryAssignment::create([
                    'empresa_id'  => $document->empresa_id,
                    'document_id' => $document->id,
                    'category_id' => $catId,
                    'assigned_by' => $actor->id,
                    'assigned_at' => now(),
                ]);

                $this->notificarDocumentoAsignadoACategoria($document, $cat);

                ActivityLog::registrar(
                    userId:      $actor->id,
                    accion:      'documento_asignado_categoria',
                    ip:          $request->ip(),
                    empresaId:   $document->empresa_id,
                    entidadTipo: 'document_category_assignments',
                    entidadId:   $asg->id,
                    detalle:     [
                        'documento_titulo' => $document->titulo,
                        'categoria_nombre' => $cat?->name ?? '(desconocida)',
                    ],
                    userAgent:   $request->userAgent()
                );
            }

            // Detach las que sobran
            if ($aQuitar->isNotEmpty()) {
                $catsQuitadas = FranchiseCategory::whereIn('id', $aQuitar->toArray())->get();

                DocumentCategoryAssignment::where('document_id', $document->id)
                                           ->whereIn('category_id', $aQuitar->toArray())
                                           ->delete();

                foreach ($catsQuitadas as $cat) {
                    ActivityLog::registrar(
                        userId:      $actor->id,
                        accion:      'documento_desasignado_categoria',
                        ip:          $request->ip(),
                        empresaId:   $document->empresa_id,
                        entidadTipo: 'document_category_assignments',
                        entidadId:   $document->id,
                        detalle:     [
                            'documento_titulo' => $document->titulo,
                            'categoria_nombre' => $cat->name,
                        ],
                        userAgent:   $request->userAgent()
                    );
                }
            }
        });

        return response()->json([
            'message'      => 'Categorías del documento actualizadas correctamente.',
            'asignaciones' => DocumentCategoryAssignment::with('category')
                                ->where('document_id', $document->id)
                                ->get(),
        ]);
    }

    // DELETE /api/documentos/{documentId}/categorias/{categoryId}
    public function desasignarCategoria(Request $request, int $documentId, int $categoryId): JsonResponse
    {
        $actor     = $request->user();
        $document  = Document::findOrFail($documentId);
        $categoria = FranchiseCategory::findOrFail($categoryId);

        // Para desasignar no exigimos categoría activa (puede estar desactivada
        // y aun así limpiar la asignación).
        if (!$this->actorPuedeVerDocumento($actor, $document)) {
            return response()->json(['error' => 'Sin acceso a este documento.'], 403);
        }
        if (!$this->actorPuedeGestionarCategoria($actor, $categoria)) {
            return response()->json(['error' => 'Sin acceso a esta categoría.'], 403);
        }

        $asignacion = DocumentCategoryAssignment::where('document_id', $documentId)
                                                 ->where('category_id', $categoryId)
                                                 ->first();

        if (!$asignacion) {
            return response()->json([
                'error' => 'El documento no está asignado a esta categoría.',
            ], 404);
        }

        $asignacion->delete();

        ActivityLog::registrar(
            userId:      $actor->id,
            accion:      'documento_desasignado_categoria',
            ip:          $request->ip(),
            empresaId:   $document->empresa_id,
            entidadTipo: 'document_category_assignments',
            entidadId:   $documentId,
            detalle:     [
                'documento_titulo' => $document->titulo,
                'categoria_nombre' => $categoria->name,
            ],
            userAgent:   $request->userAgent()
        );

        return response()->json(['message' => 'Asignación eliminada correctamente.']);
    }

    // ════════════════════════════════════════════════════════════════
    // ── USUARIOS INDIVIDUALES
    // ════════════════════════════════════════════════════════════════

    // GET /api/documentos/{documentId}/usuarios
    public function listarUsuarios(Request $request, int $documentId): JsonResponse
    {
        $actor    = $request->user();
        $document = Document::findOrFail($documentId);

        if (!$this->actorPuedeVerDocumento($actor, $document)) {
            return response()->json(['error' => 'Sin acceso a este documento.'], 403);
        }

        $asignaciones = DocumentUserAssignment::with('user:id,nombre,apellido,email,rol')
                                               ->where('document_id', $documentId)
                                               ->get();

        return response()->json($asignaciones);
    }

    // POST /api/documentos/{documentId}/usuarios
    // Body: { "user_id": N }
    public function asignarUsuario(Request $request, int $documentId): JsonResponse
    {
        $actor    = $request->user();
        $document = Document::findOrFail($documentId);

        $error = $this->validarDocumentoOperable($actor, $document);
        if ($error) return response()->json($error, $error['_status']);

        $data = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $usuario = User::findOrFail($data['user_id']);

        $error = $this->validarAsignacionUsuario($document, $usuario);
        if ($error) return response()->json($error, $error['_status']);

        $existe = DocumentUserAssignment::where('empresa_id', $document->empresa_id)
            ->where('document_id', $document->id)
            ->where('user_id', $usuario->id)
            ->exists();

        if ($existe) {
            return response()->json([
                'message' => 'El documento ya está asignado a este usuario.',
            ], 409);
        }

        $asignacion = DocumentUserAssignment::create([
            'empresa_id'  => $document->empresa_id,
            'document_id' => $document->id,
            'user_id'     => $usuario->id,
            'assigned_by' => $actor->id,
            'assigned_at' => now(),
        ]);

        $this->notificarDocumentoAsignadoAUsuario($document, $usuario);

        ActivityLog::registrar(
            userId:      $actor->id,
            accion:      'documento_asignado_usuario',
            ip:          $request->ip(),
            empresaId:   $document->empresa_id,
            entidadTipo: 'document_user_assignments',
            entidadId:   $asignacion->id,
            detalle:     [
                'documento_titulo' => $document->titulo,
                'user_email'       => $usuario->email,
            ],
            userAgent:   $request->userAgent()
        );

        return response()->json([
            'message'    => 'Documento asignado al usuario correctamente.',
            'asignacion' => $asignacion->load('user:id,nombre,apellido,email,rol'),
        ], 201);
    }

    // PUT /api/documentos/{documentId}/usuarios
    // Body: { "user_ids": [1, 2, 3] }
    public function sincronizarUsuarios(Request $request, int $documentId): JsonResponse
    {
        $actor    = $request->user();
        $document = Document::findOrFail($documentId);

        $error = $this->validarDocumentoOperable($actor, $document);
        if ($error) return response()->json($error, $error['_status']);

        $data = $request->validate([
            'user_ids'   => 'present|array',
            'user_ids.*' => 'integer|exists:users,id',
        ]);

        // Validar todos los usuarios
        $usuarios = User::whereIn('id', $data['user_ids'])->get();

        foreach ($usuarios as $u) {
            $error = $this->validarAsignacionUsuario($document, $u);
            if ($error) {
                return response()->json([
                    'error' => $error['error'] . " (usuario: {$u->email})",
                ], $error['_status']);
            }
        }

        $actuales = DocumentUserAssignment::where('document_id', $document->id)
                                           ->pluck('user_id')
                                           ->toArray();

        $nuevos   = collect($data['user_ids'])->unique()->values();
        $aAgregar = $nuevos->diff($actuales);
        $aQuitar  = collect($actuales)->diff($nuevos);

        DB::transaction(function () use ($document, $actor, $aAgregar, $aQuitar, $usuarios, $request) {
            // Attach nuevos
            foreach ($aAgregar as $uid) {
                $u = $usuarios->firstWhere('id', $uid);

                $asg = DocumentUserAssignment::create([
                    'empresa_id'  => $document->empresa_id,
                    'document_id' => $document->id,
                    'user_id'     => $uid,
                    'assigned_by' => $actor->id,
                    'assigned_at' => now(),
                ]);

                $this->notificarDocumentoAsignadoAUsuario($document, $u);

                ActivityLog::registrar(
                    userId:      $actor->id,
                    accion:      'documento_asignado_usuario',
                    ip:          $request->ip(),
                    empresaId:   $document->empresa_id,
                    entidadTipo: 'document_user_assignments',
                    entidadId:   $asg->id,
                    detalle:     [
                        'documento_titulo' => $document->titulo,
                        'user_email'       => $u?->email ?? '(desconocido)',
                    ],
                    userAgent:   $request->userAgent()
                );
            }

            // Detach
            if ($aQuitar->isNotEmpty()) {
                $usersQuitados = User::whereIn('id', $aQuitar->toArray())->get();

                DocumentUserAssignment::where('document_id', $document->id)
                                       ->whereIn('user_id', $aQuitar->toArray())
                                       ->delete();

                foreach ($usersQuitados as $u) {
                    ActivityLog::registrar(
                        userId:      $actor->id,
                        accion:      'documento_desasignado_usuario',
                        ip:          $request->ip(),
                        empresaId:   $document->empresa_id,
                        entidadTipo: 'document_user_assignments',
                        entidadId:   $document->id,
                        detalle:     [
                            'documento_titulo' => $document->titulo,
                            'user_email'       => $u->email,
                        ],
                        userAgent:   $request->userAgent()
                    );
                }
            }
        });

        return response()->json([
            'message'      => 'Usuarios del documento actualizados correctamente.',
            'asignaciones' => DocumentUserAssignment::with('user:id,nombre,apellido,email,rol')
                                ->where('document_id', $document->id)
                                ->get(),
        ]);
    }

    // DELETE /api/documentos/{documentId}/usuarios/{userId}
    public function desasignarUsuario(Request $request, int $documentId, int $userId): JsonResponse
    {
        $actor    = $request->user();
        $document = Document::findOrFail($documentId);
        $usuario  = User::findOrFail($userId);

        if (!$this->actorPuedeVerDocumento($actor, $document)) {
            return response()->json(['error' => 'Sin acceso a este documento.'], 403);
        }

        $asignacion = DocumentUserAssignment::where('document_id', $documentId)
                                             ->where('user_id', $userId)
                                             ->first();

        if (!$asignacion) {
            return response()->json([
                'error' => 'El documento no está asignado a este usuario.',
            ], 404);
        }

        $asignacion->delete();

        ActivityLog::registrar(
            userId:      $actor->id,
            accion:      'documento_desasignado_usuario',
            ip:          $request->ip(),
            empresaId:   $document->empresa_id,
            entidadTipo: 'document_user_assignments',
            entidadId:   $documentId,
            detalle:     [
                'documento_titulo' => $document->titulo,
                'user_email'       => $usuario->email,
            ],
            userAgent:   $request->userAgent()
        );

        return response()->json(['message' => 'Asignación eliminada correctamente.']);
    }

    // ════════════════════════════════════════════════════════════════
    // ── PRIVADOS
    // ════════════════════════════════════════════════════════════════

    /**
     * Si el actor puede VER las asignaciones de un documento.
     */
    private function actorPuedeVerDocumento($actor, Document $document): bool
    {
        if ($actor->esSuperAdmin()) {
            return true;
        }
        if ($actor->esFranquiciante() && $document->empresa_id === $actor->empresa_id) {
            return true;
        }
        return false;
    }

    /**
     * Si el actor puede gestionar (asignar/desasignar) sobre una categoría específica.
     */
    private function actorPuedeGestionarCategoria($actor, FranchiseCategory $categoria): bool
    {
        if ($actor->esSuperAdmin()) {
            return true;
        }
        if ($actor->esFranquiciante() && $categoria->empresa_id === $actor->empresa_id) {
            return true;
        }
        return false;
    }

    /**
     * Valida que el documento esté en condiciones de recibir asignaciones nuevas
     * y que el actor tenga permisos.
     */
    private function validarDocumentoOperable($actor, Document $document): ?array
    {
        if (!$this->actorPuedeVerDocumento($actor, $document)) {
            return ['error' => 'Sin acceso a este documento.', '_status' => 403];
        }
        if ($document->deleted_at !== null) {
            return ['error' => 'No se puede asignar un documento eliminado.', '_status' => 409];
        }
        if (!$document->empresa_id) {
            return [
                'error'   => 'El documento no está asignado a una empresa. Asignalo primero.',
                '_status' => 422,
            ];
        }
        return null;
    }

    /**
     * Valida que la categoría pueda recibir asignación nueva del documento.
     */
    private function validarAsignacionCategoria(Document $document, FranchiseCategory $categoria): ?array
    {
        if ($categoria->empresa_id !== $document->empresa_id) {
            return [
                'error'   => "La categoría \"{$categoria->name}\" no pertenece a la empresa del documento.",
                '_status' => 422,
            ];
        }
        if (!$categoria->is_active) {
            return [
                'error'   => "La categoría \"{$categoria->name}\" está desactivada. Reactivala antes de asignar.",
                '_status' => 422,
            ];
        }
        return null;
    }

    /**
     * Valida que un usuario pueda recibir asignación individual del documento.
     */
    private function validarAsignacionUsuario(Document $document, User $usuario): ?array
    {
        // Solo franquiciado/empleado
        if (!in_array($usuario->rol, ['franquiciado', 'empleado'])) {
            return [
                'error'   => 'Solo se pueden asignar documentos individualmente a franquiciados o empleados.',
                '_status' => 422,
            ];
        }

        // Misma empresa
        if ($usuario->empresa_id !== $document->empresa_id) {
            return [
                'error'   => 'El usuario no pertenece a la empresa del documento.',
                '_status' => 422,
            ];
        }

        // Usuario activo
        if (!$usuario->activo || $usuario->deleted_at !== null) {
            return [
                'error'   => 'El usuario está inactivo o eliminado.',
                '_status' => 422,
            ];
        }

        // Si el documento tiene franquicia_id, el usuario debe ser de esa franquicia
        if ($document->franquicia_id !== null) {
            $franqUsuario = $usuario->franchiseStaff?->franquicia_id;
            if ($franqUsuario !== $document->franquicia_id) {
                return [
                    'error'   => 'El documento está acotado a una franquicia distinta a la del usuario.',
                    '_status' => 422,
                ];
            }
        }

        return null;
    }

    /**
     * Notifica a los usuarios de una categoría que se les asignó un documento nuevo.
     * Solo si el documento es visible_franquiciado.
     */
    private function notificarDocumentoAsignadoACategoria(Document $document, ?FranchiseCategory $categoria): void
    {
        if (!$categoria) return;
        if (!$document->visible_franquiciado) return;

        // Usuarios franquiciado/empleado activos con esta categoría
        $userIds = DB::table('user_categories')
            ->where('category_id', $categoria->id)
            ->where('empresa_id', $categoria->empresa_id)
            ->pluck('user_id');

        if ($userIds->isEmpty()) return;

        $query = User::whereIn('id', $userIds)
            ->whereIn('rol', ['franquiciado', 'empleado'])
            ->where('activo', 1)
            ->whereNull('deleted_at');

        // Si el documento está acotado a una franquicia, solo usuarios de esa franquicia
        if ($document->franquicia_id) {
            $query->whereHas('franchiseStaff', fn($q) =>
                $q->where('franquicia_id', $document->franquicia_id)
            );
        }

        $userIds = $query->pluck('id');

        if ($userIds->isEmpty()) return;

        $notificaciones = $userIds->map(fn($uid) => [
            'user_id'             => $uid,
            'tipo'                => 'documento_asignado_categoria',
            'manual_id'           => null,
            'manual_version_id'   => null,
            'document_id'         => $document->id,
            'document_version_id' => null,
            'category_id'         => $categoria->id,
            'titulo'              => "Se te asignó el documento \"{$document->titulo}\" (categoría: {$categoria->name})",
            'created_at'          => now(),
        ])->toArray();

        try {
            Notification::insert($notificaciones);
        } catch (\Throwable $e) { /* best-effort */ }
    }

    /**
     * Notifica a un usuario que se le asignó un documento individualmente.
     */
    private function notificarDocumentoAsignadoAUsuario(Document $document, ?User $usuario): void
    {
        if (!$usuario) return;
        if (!$document->visible_franquiciado) return;

        try {
            Notification::create([
                'user_id'             => $usuario->id,
                'tipo'                => 'documento_asignado',
                'manual_id'           => null,
                'manual_version_id'   => null,
                'document_id'         => $document->id,
                'document_version_id' => null,
                'category_id'         => null,
                'titulo'              => "Se te asignó el documento \"{$document->titulo}\"",
            ]);
        } catch (\Throwable $e) { /* best-effort */ }
    }
}