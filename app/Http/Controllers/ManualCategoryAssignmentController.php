<?php

namespace App\Http\Controllers;

use App\Models\Manual;
use App\Models\ManualCategoryAssignment;
use App\Models\FranchiseCategory;
use App\Models\Notification;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Asignación de manuales a categorías (manual → categoría).
 *
 * Diferencia con ManualAssignmentController:
 *   - Este controller maneja asignación a CATEGORÍAS (alcanza a todos los usuarios
 *     que tengan esa categoría).
 *   - ManualAssignmentController maneja asignación INDIVIDUAL (a un usuario específico).
 *   - Ambos coexisten — un manual puede estar asignado por los dos caminos.
 *
 * Permisos:
 *   - super_admin: cualquier manual ↔ cualquier categoría
 *   - franquiciante: solo manuales disponibles en su empresa, solo categorías de su empresa
 *   - franquiciado/empleado: sin permisos
 *
 * empresa_id se infiere de la categoría — no se pasa en el request.
 * Eso garantiza coherencia: la asignación siempre vive en la empresa de la categoría,
 * y se valida que el manual esté disponible en esa empresa.
 */
class ManualCategoryAssignmentController extends Controller
{
    // GET /api/manuales/{manualId}/categorias
    public function porManual(Request $request, int $manualId): JsonResponse
    {
        $actor  = $request->user();
        $manual = Manual::findOrFail($manualId);

        if (!$this->actorPuedeVerManual($actor, $manualId)) {
            return response()->json(['error' => 'Sin acceso a este manual.'], 403);
        }

        $query = ManualCategoryAssignment::with('category')
                                          ->where('manual_id', $manualId);

        // Franquiciante solo ve las asignaciones de su empresa
        if ($actor->esFranquiciante()) {
            $query->where('empresa_id', $actor->empresa_id);
        }

        return response()->json($query->get());
    }

    // GET /api/categorias/{categoryId}/manuales
    // Endpoint inverso: lista manuales asignados a una categoría.
    public function porCategoria(Request $request, int $categoryId): JsonResponse
    {
        $actor    = $request->user();
        $categoria = FranchiseCategory::findOrFail($categoryId);

        // Super_admin ve cualquier categoría. Resto solo su empresa.
        if (!$actor->esSuperAdmin() && $categoria->empresa_id !== $actor->empresa_id) {
            return response()->json(['error' => 'Sin acceso a esta categoría.'], 403);
        }

        $asignaciones = ManualCategoryAssignment::with('manual.versionActiva')
                                                 ->where('category_id', $categoryId)
                                                 ->get();

        return response()->json($asignaciones);
    }

    // POST /api/manuales/{manualId}/categorias
    // Asigna UNA categoría al manual. Body: { "category_id": N }
    public function asignar(Request $request, int $manualId): JsonResponse
    {
        $actor  = $request->user();
        $manual = Manual::findOrFail($manualId);

        if ($manual->deleted_at !== null) {
            return response()->json(['error' => 'No se puede asignar a un manual eliminado.'], 409);
        }

        $data = $request->validate([
            'category_id' => 'required|integer|exists:franchise_categories,id',
        ]);

        $categoria = FranchiseCategory::findOrFail($data['category_id']);

        // Validar permisos y coherencia multi-tenant
        $error = $this->validarAsignacion($actor, $manual, $categoria);
        if ($error) {
            return response()->json($error, $error['_status']);
        }

        // Verificar duplicado
        $existe = ManualCategoryAssignment::where('empresa_id', $categoria->empresa_id)
            ->where('manual_id', $manualId)
            ->where('category_id', $categoria->id)
            ->exists();

        if ($existe) {
            return response()->json([
                'message' => 'El manual ya está asignado a esta categoría.',
            ], 409);
        }

        $asignacion = ManualCategoryAssignment::create([
            'empresa_id'  => $categoria->empresa_id,
            'manual_id'   => $manual->id,
            'category_id' => $categoria->id,
            'assigned_by' => $actor->id,
            'assigned_at' => now(),
        ]);

        // Notificar (solo si el manual está publicado)
        $this->notificarManualAsignadoACategoria($manual, $categoria);

        ActivityLog::registrar(
            userId:      $actor->id,
            accion:      'manual_asignado_categoria',
            ip:          $request->ip(),
            empresaId:   $categoria->empresa_id,
            entidadTipo: 'manual_category_assignments',
            entidadId:   $asignacion->id,
            detalle:     [
                'manual_titulo'    => $manual->titulo,
                'categoria_nombre' => $categoria->name,
            ],
            userAgent:   $request->userAgent()
        );

        return response()->json([
            'message'    => 'Manual asignado a la categoría correctamente.',
            'asignacion' => $asignacion->load('category'),
        ], 201);
    }

    // PUT /api/manuales/{manualId}/categorias
    // Sync: reemplaza la lista completa de categorías a las que está asignado el manual.
    // Body: { "category_ids": [1, 2, 3] }
    //
    // Para super_admin: requiere ?empresa_id=X o body.empresa_id para saber en qué scope sincronizar
    // (el manual puede estar en múltiples empresas con asignaciones distintas en cada una).
    // Para franquiciante: se infiere de su empresa.
    public function sincronizar(Request $request, int $manualId): JsonResponse
    {
        $actor  = $request->user();
        $manual = Manual::findOrFail($manualId);

        if ($manual->deleted_at !== null) {
            return response()->json(['error' => 'No se puede asignar a un manual eliminado.'], 409);
        }

        if (!$actor->esSuperAdmin() && !$actor->esFranquiciante()) {
            return response()->json(['error' => 'Sin permisos.'], 403);
        }

        $data = $request->validate([
            'empresa_id'     => 'sometimes|integer|exists:empresas,id',
            'category_ids'   => 'present|array',
            'category_ids.*' => 'integer|exists:franchise_categories,id',
        ]);

        // Determinar empresa_id del scope del sync
        $empresaId = $actor->esSuperAdmin()
            ? ($data['empresa_id'] ?? null)
            : $actor->empresa_id;

        if (!$empresaId) {
            return response()->json([
                'error' => 'empresa_id es requerido para sincronizar.',
            ], 422);
        }

        // El manual debe estar disponible en esa empresa
        $manualEnEmpresa = DB::table('manual_empresa_assignments')
            ->where('manual_id', $manual->id)
            ->where('empresa_id', $empresaId)
            ->exists();

        if (!$manualEnEmpresa) {
            return response()->json([
                'error' => 'El manual no está disponible en esta empresa.',
            ], 422);
        }

        // Validar todas las categorías: deben ser de la misma empresa y estar activas
        $categorias = FranchiseCategory::whereIn('id', $data['category_ids'])->get();

        foreach ($categorias as $cat) {
            if ($cat->empresa_id !== $empresaId) {
                return response()->json([
                    'error' => "La categoría \"{$cat->name}\" no pertenece a esta empresa.",
                ], 422);
            }
            if (!$cat->is_active) {
                return response()->json([
                    'error' => "La categoría \"{$cat->name}\" está desactivada. Reactivala antes de asignar.",
                ], 422);
            }
        }

        // Calcular diff respecto a la empresa específica
        $actuales = ManualCategoryAssignment::where('manual_id', $manual->id)
                                             ->where('empresa_id', $empresaId)
                                             ->pluck('category_id')
                                             ->toArray();

        $nuevas  = collect($data['category_ids'])->unique()->values();
        $aAgregar = $nuevas->diff($actuales);
        $aQuitar  = collect($actuales)->diff($nuevas);

        DB::transaction(function () use ($manual, $actor, $aAgregar, $aQuitar, $categorias, $empresaId, $request) {
            // Attach nuevas
            foreach ($aAgregar as $catId) {
                $cat = $categorias->firstWhere('id', $catId);

                $asg = ManualCategoryAssignment::create([
                    'empresa_id'  => $empresaId,
                    'manual_id'   => $manual->id,
                    'category_id' => $catId,
                    'assigned_by' => $actor->id,
                    'assigned_at' => now(),
                ]);

                $this->notificarManualAsignadoACategoria($manual, $cat);

                ActivityLog::registrar(
                    userId:      $actor->id,
                    accion:      'manual_asignado_categoria',
                    ip:          $request->ip(),
                    empresaId:   $empresaId,
                    entidadTipo: 'manual_category_assignments',
                    entidadId:   $asg->id,
                    detalle:     [
                        'manual_titulo'    => $manual->titulo,
                        'categoria_nombre' => $cat?->name ?? '(desconocida)',
                    ],
                    userAgent:   $request->userAgent()
                );
            }

            // Detach las que sobran
            if ($aQuitar->isNotEmpty()) {
                $catsQuitadas = FranchiseCategory::whereIn('id', $aQuitar->toArray())->get();

                ManualCategoryAssignment::where('manual_id', $manual->id)
                                         ->where('empresa_id', $empresaId)
                                         ->whereIn('category_id', $aQuitar->toArray())
                                         ->delete();

                foreach ($catsQuitadas as $cat) {
                    ActivityLog::registrar(
                        userId:      $actor->id,
                        accion:      'manual_desasignado_categoria',
                        ip:          $request->ip(),
                        empresaId:   $empresaId,
                        entidadTipo: 'manual_category_assignments',
                        entidadId:   $manual->id,
                        detalle:     [
                            'manual_titulo'    => $manual->titulo,
                            'categoria_nombre' => $cat->name,
                        ],
                        userAgent:   $request->userAgent()
                    );
                }
            }
        });

        return response()->json([
            'message'     => 'Categorías del manual actualizadas correctamente.',
            'asignaciones' => ManualCategoryAssignment::with('category')
                                ->where('manual_id', $manual->id)
                                ->where('empresa_id', $empresaId)
                                ->get(),
        ]);
    }

    // DELETE /api/manuales/{manualId}/categorias/{categoryId}
    public function desasignar(Request $request, int $manualId, int $categoryId): JsonResponse
    {
        $actor     = $request->user();
        $manual    = Manual::findOrFail($manualId);
        $categoria = FranchiseCategory::findOrFail($categoryId);

        // Para desasignar, NO requerimos que la categoría esté activa (puede estar
        // desactivada y aun así limpiar la asignación).
        if (!$this->actorPuedeGestionarCategoria($actor, $categoria)) {
            return response()->json(['error' => 'Sin acceso a esta categoría.'], 403);
        }

        $asignacion = ManualCategoryAssignment::where('manual_id', $manualId)
                                               ->where('category_id', $categoryId)
                                               ->where('empresa_id', $categoria->empresa_id)
                                               ->first();

        if (!$asignacion) {
            return response()->json([
                'error' => 'El manual no está asignado a esta categoría.',
            ], 404);
        }

        $asignacion->delete();

        ActivityLog::registrar(
            userId:      $actor->id,
            accion:      'manual_desasignado_categoria',
            ip:          $request->ip(),
            empresaId:   $categoria->empresa_id,
            entidadTipo: 'manual_category_assignments',
            entidadId:   $manualId,
            detalle:     [
                'manual_titulo'    => $manual->titulo,
                'categoria_nombre' => $categoria->name,
            ],
            userAgent:   $request->userAgent()
        );

        return response()->json(['message' => 'Asignación eliminada correctamente.']);
    }

    // ── PRIVADOS ─────────────────────────────────────────────────────

    /**
     * Si el actor puede VER las asignaciones de un manual.
     * Igual que la lógica de show() en ManualController.
     */
    private function actorPuedeVerManual($actor, int $manualId): bool
    {
        if ($actor->esSuperAdmin()) {
            return true;
        }

        if ($actor->esFranquiciante()) {
            return DB::table('manual_empresa_assignments')
                ->where('manual_id', $manualId)
                ->where('empresa_id', $actor->empresa_id)
                ->exists();
        }

        // franquiciado/empleado no ven asignaciones a categorías
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
     * Valida que el actor pueda asignar este manual a esta categoría.
     * Devuelve null si OK, o un array con el error y un campo `_status` si falla.
     */
    private function validarAsignacion($actor, Manual $manual, FranchiseCategory $categoria): ?array
    {
        // Permisos del actor sobre la categoría
        if (!$this->actorPuedeGestionarCategoria($actor, $categoria)) {
            return ['error' => 'Sin acceso a esta categoría.', '_status' => 403];
        }

        // La categoría debe estar activa para crear asignaciones nuevas
        if (!$categoria->is_active) {
            return [
                'error'    => 'La categoría está desactivada. Reactivala antes de asignar.',
                '_status'  => 422,
            ];
        }

        // El manual debe estar disponible en la empresa de la categoría
        $manualEnEmpresa = DB::table('manual_empresa_assignments')
            ->where('manual_id', $manual->id)
            ->where('empresa_id', $categoria->empresa_id)
            ->exists();

        if (!$manualEnEmpresa) {
            return [
                'error'    => 'El manual no está disponible en la empresa de la categoría.',
                '_status'  => 422,
            ];
        }

        return null;
    }

    /**
     * Notifica a los usuarios de una categoría que se les asignó un manual nuevo.
     * Solo dispara si el manual está PUBLICADO. Si está en borrador, no notifica
     * (se notificará al publicar via ManualController::publicar).
     */
    private function notificarManualAsignadoACategoria(Manual $manual, ?FranchiseCategory $categoria): void
    {
        if (!$categoria) return;
        if ($manual->estado !== 'publicado') return;

        // Usuarios franquiciado/empleado activos con esta categoría en esta empresa
        $userIds = DB::table('user_categories')
            ->where('category_id', $categoria->id)
            ->where('empresa_id', $categoria->empresa_id)
            ->pluck('user_id');

        if ($userIds->isEmpty()) return;

        $userIds = \App\Models\User::whereIn('id', $userIds)
            ->whereIn('rol', ['franquiciado', 'empleado'])
            ->where('activo', 1)
            ->whereNull('deleted_at')
            ->pluck('id');

        if ($userIds->isEmpty()) return;

        $notificaciones = $userIds->map(fn($uid) => [
            'user_id'             => $uid,
            'tipo'                => 'manual_asignado_categoria',
            'manual_id'           => $manual->id,
            'manual_version_id'   => null,
            'document_id'         => null,
            'document_version_id' => null,
            'category_id'         => $categoria->id,
            'titulo'              => "Se te asignó el manual \"{$manual->titulo}\" (categoría: {$categoria->name})",
            'created_at'          => now(),
        ])->toArray();

        try {
            Notification::insert($notificaciones);
        } catch (\Throwable $e) { /* best-effort */ }
    }
}