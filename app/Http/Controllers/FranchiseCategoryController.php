<?php

namespace App\Http\Controllers;

use App\Models\FranchiseCategory;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

/**
 * CRUD del catálogo de categorías por empresa.
 *
 * Cada empresa tiene su propio set independiente (Distribuidor, Licenciatario,
 * Dropshipper, etc.). Las categorías no modifican permisos — solo afectan
 * qué manuales y documentos ve cada usuario según las asignaciones.
 *
 * Permisos:
 *   - super_admin: full CRUD en cualquier empresa
 *   - franquiciante: full CRUD pero solo en su empresa
 *   - franquiciado/empleado: solo lectura de las categorías activas de su empresa
 *
 * Para "borrar" una categoría existen dos caminos:
 *   - toggleActiva(): la oculta (is_active = 0) pero conserva el historial.
 *     Las asignaciones quedan en disco; las queries de visibilidad las ignoran.
 *   - destroy(): borrado físico. Solo permitido si la categoría está vacía.
 *     Si tiene asignaciones, hay que desasignarlas o desactivar la categoría.
 */
class FranchiseCategoryController extends Controller
{
    // GET /api/categorias
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = FranchiseCategory::withCount([
            'usuarios',
            'manualesAsignados',
            'documentosAsignados',
        ]);

        if ($user->esSuperAdmin()) {
            if ($request->filled('empresa_id')) {
                $query->where('empresa_id', $request->empresa_id);
            }
        } else {
            // Franquiciante, franquiciado, empleado: solo su empresa
            $query->where('empresa_id', $user->empresa_id);

            // Franquiciado/empleado: solo activas
            if ($user->esFranquiciado() || $user->esEmpleado()) {
                $query->where('is_active', true);
            }
        }

        // Filtro opcional ?activa=1 / ?activa=0
        if ($request->has('activa')) {
            $query->where('is_active', (bool) $request->query('activa'));
        }

        return response()->json(
            $query->orderBy('name')->get()
        );
    }

    // GET /api/categorias/{id}
    public function show(Request $request, int $id): JsonResponse
    {
        $user      = $request->user();
        $categoria = FranchiseCategory::withCount([
            'usuarios',
            'manualesAsignados',
            'documentosAsignados',
        ])->findOrFail($id);

        if (!$this->actorPuedeVer($user, $categoria)) {
            return response()->json(['error' => 'Sin acceso a esta categoría.'], 403);
        }

        return response()->json($categoria);
    }

    // POST /api/categorias
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->esSuperAdmin() && !$user->esFranquiciante()) {
            return response()->json(['error' => 'Sin permisos.'], 403);
        }

        // Determinar empresa_id según rol del actor
        $empresaId = $user->esSuperAdmin()
            ? $request->input('empresa_id')
            : $user->empresa_id;

        if (!$empresaId) {
            return response()->json(['error' => 'empresa_id es requerido.'], 422);
        }

        $data = $request->validate([
            'empresa_id'  => 'sometimes|integer|exists:empresas,id',
            'name'        => [
                'required',
                'string',
                'max:100',
                Rule::unique('franchise_categories', 'name')
                    ->where('empresa_id', $empresaId),
            ],
            'description' => 'nullable|string|max:1000',
            'is_active'   => 'nullable|boolean',
        ]);

        $categoria = FranchiseCategory::create([
            'empresa_id'  => $empresaId,
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active'   => $data['is_active'] ?? true,
        ]);

        ActivityLog::registrar(
            userId:      $user->id,
            accion:      'categoria_creada',
            ip:          $request->ip(),
            empresaId:   $empresaId,
            entidadTipo: 'franchise_categories',
            entidadId:   $categoria->id,
            detalle:     ['categoria_nombre' => $categoria->name],
            userAgent:   $request->userAgent()
        );

        return response()->json($categoria, 201);
    }

    // PUT /api/categorias/{id}
    public function update(Request $request, int $id): JsonResponse
    {
        $user      = $request->user();
        $categoria = FranchiseCategory::findOrFail($id);

        if (!$this->actorPuedeGestionar($user, $categoria)) {
            return response()->json(['error' => 'Sin acceso a esta categoría.'], 403);
        }

        $data = $request->validate([
            'name' => [
                'sometimes',
                'string',
                'max:100',
                Rule::unique('franchise_categories', 'name')
                    ->where('empresa_id', $categoria->empresa_id)
                    ->ignore($categoria->id),
            ],
            'description' => 'nullable|string|max:1000',
        ]);

        $nombreAnterior = $categoria->name;
        $cambioNombre   = isset($data['name']) && $data['name'] !== $nombreAnterior;

        $categoria->update($data);

        // Log: si cambió el nombre, log específico con valor_anterior y valor_nuevo
        if ($cambioNombre) {
            ActivityLog::registrar(
                userId:      $user->id,
                accion:      'categoria_editada',
                ip:          $request->ip(),
                empresaId:   $categoria->empresa_id,
                entidadTipo: 'franchise_categories',
                entidadId:   $categoria->id,
                detalle:     [
                    'campo'           => 'name',
                    'valor_anterior'  => $nombreAnterior,
                    'valor_nuevo'     => $categoria->name,
                    'categoria_nombre'=> $categoria->name,
                ],
                userAgent:   $request->userAgent()
            );
        } else {
            ActivityLog::registrar(
                userId:      $user->id,
                accion:      'categoria_editada',
                ip:          $request->ip(),
                empresaId:   $categoria->empresa_id,
                entidadTipo: 'franchise_categories',
                entidadId:   $categoria->id,
                detalle:     ['categoria_nombre' => $categoria->name],
                userAgent:   $request->userAgent()
            );
        }

        return response()->json($categoria);
    }

    // POST /api/categorias/{id}/toggle-activa
    public function toggleActiva(Request $request, int $id): JsonResponse
    {
        $user      = $request->user();
        $categoria = FranchiseCategory::findOrFail($id);

        if (!$this->actorPuedeGestionar($user, $categoria)) {
            return response()->json(['error' => 'Sin acceso a esta categoría.'], 403);
        }

        $nuevoEstado = !$categoria->is_active;
        $categoria->update(['is_active' => $nuevoEstado]);

        ActivityLog::registrar(
            userId:      $user->id,
            accion:      $nuevoEstado ? 'categoria_activada' : 'categoria_desactivada',
            ip:          $request->ip(),
            empresaId:   $categoria->empresa_id,
            entidadTipo: 'franchise_categories',
            entidadId:   $categoria->id,
            detalle:     ['categoria_nombre' => $categoria->name],
            userAgent:   $request->userAgent()
        );

        return response()->json([
            'message'   => $nuevoEstado
                ? 'Categoría activada.'
                : 'Categoría desactivada.',
            'is_active' => $nuevoEstado,
        ]);
    }

    // DELETE /api/categorias/{id}
    // Borrado físico. Solo si la categoría está vacía (sin asignaciones).
    // Si tiene asignaciones, devuelve 409 sugiriendo desactivar.
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user      = $request->user();
        $categoria = FranchiseCategory::withCount([
            'usuarios',
            'manualesAsignados',
            'documentosAsignados',
        ])->findOrFail($id);

        if (!$this->actorPuedeGestionar($user, $categoria)) {
            return response()->json(['error' => 'Sin acceso a esta categoría.'], 403);
        }

        $total = $categoria->usuarios_count
               + $categoria->manuales_asignados_count
               + $categoria->documentos_asignados_count;

        if ($total > 0) {
            return response()->json([
                'error'   => 'No podés eliminar una categoría con asignaciones.',
                'sugerencia' => 'Desactivá la categoría (toggle-activa) o desasigná primero ' .
                                'los usuarios/manuales/documentos vinculados.',
                'detalle' => [
                    'usuarios'   => $categoria->usuarios_count,
                    'manuales'   => $categoria->manuales_asignados_count,
                    'documentos' => $categoria->documentos_asignados_count,
                ],
            ], 409);
        }

        $nombre    = $categoria->name;
        $empresaId = $categoria->empresa_id;
        $categoria->delete();

        ActivityLog::registrar(
            userId:      $user->id,
            accion:      'categoria_eliminada',
            ip:          $request->ip(),
            empresaId:   $empresaId,
            entidadTipo: 'franchise_categories',
            entidadId:   $id,
            detalle:     ['categoria_nombre' => $nombre],
            userAgent:   $request->userAgent()
        );

        return response()->json(['message' => 'Categoría eliminada correctamente.']);
    }

    // ── PRIVADOS ─────────────────────────────────────────────────────

    /**
     * Si el actor puede ver (leer) la categoría.
     */
    private function actorPuedeVer($actor, FranchiseCategory $categoria): bool
    {
        if ($actor->esSuperAdmin()) {
            return true;
        }
        // Resto: misma empresa
        return $categoria->empresa_id === $actor->empresa_id;
    }

    /**
     * Si el actor puede gestionar (crear/editar/desactivar/eliminar) la categoría.
     */
    private function actorPuedeGestionar($actor, FranchiseCategory $categoria): bool
    {
        if ($actor->esSuperAdmin()) {
            return true;
        }
        if ($actor->esFranquiciante() && $categoria->empresa_id === $actor->empresa_id) {
            return true;
        }
        return false;
    }
}