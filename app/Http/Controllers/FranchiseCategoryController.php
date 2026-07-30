<?php

namespace App\Http\Controllers;

use App\Models\FranchiseCategory;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
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

        // Conteos VISIBLES: lo que un socio de la categoria podria ver hoy.
        // destroy() cuenta distinto a proposito — ver conteosVisibles().
        $query = FranchiseCategory::withCount(self::conteosVisibles());

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
        $categoria = FranchiseCategory::withCount(self::conteosVisibles())
                                      ->findOrFail($id);

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

        // Manuales y documentos se cuentan TODOS, sin filtrar por estado ni por
        // visibilidad. No es un descuido: aca el conteo no es informativo, es
        // la barrera que impide borrar fisicamente una categoria que todavia
        // tiene cosas colgando. Si se contara solo lo visible, una categoria
        // con manuales en borrador o archivados daria total 0, se borraria, y
        // quedarian filas huerfanas en manual_category_assignments apuntando a
        // una categoria inexistente.
        //
        // Los USUARIOS ELIMINADOS son la excepcion, y es a proposito.
        //
        // Un usuario eliminado no se puede borrar fisicamente —
        // acceptances.user_id y activity_logs.user_id son ON DELETE RESTRICT—
        // asi que su fila en user_categories vive para siempre. Contandola,
        // una categoria que alguna vez tuvo un usuario que despues se elimino
        // NO SE PODIA BORRAR NUNCA. No habia salida posible.
        //
        // Y no es lo mismo que un manual en borrador: ese es contenido real
        // que en cualquier momento se publica. Un usuario eliminado no vuelve
        // solo, y mientras lo esta no ve nada.
        //
        // Las filas que quedan se limpian mas abajo, antes del delete().
        $categoria = FranchiseCategory::withCount([
            'usuarios' => fn ($q) => $q->whereNull('users.deleted_at'),
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

        // Las asignaciones de usuarios ELIMINADOS no bloquearon el borrado, asi
        // que hay que sacarlas de en medio o quedarian apuntando a una
        // categoria inexistente.
        //
        // Se borran explicitamente aunque la FK pudiera tener ON DELETE
        // CASCADE: si la tiene, esto es redundante y no molesta; si no la
        // tiene, es lo unico que evita el huerfano. No depende de una
        // definicion de esquema que puede cambiar sin que nadie lo mire.
        //
        // Va en una transaccion con el delete(): si el borrado de la categoria
        // fallara despues de limpiar el pivote, esos usuarios se quedarian sin
        // su categoria y la categoria seguiria existiendo.
        DB::transaction(function () use ($categoria) {
            DB::table('user_categories')
              ->where('category_id', $categoria->id)
              ->whereIn('user_id', function ($q) {
                  $q->select('id')->from('users')->whereNotNull('deleted_at');
              })
              ->delete();

            $categoria->delete();
        });

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
     * Conteos para las pantallas: SOLO lo que un socio de esta categoría
     * podría ver hoy.
     *
     * Antes se contaba todo lo asignado, incluidos eliminados, archivados y
     * borradores. El número quedaba inflado y no significaba nada útil: la
     * pantalla decía 5 manuales donde llegaban 2.
     *
     * Las DOS condiciones en cada caso, no una sola: un registro borrado puede
     * haber quedado con estado 'publicado' o con visible_franquiciado en 1, así
     * que filtrar por uno solo deja pasar los eliminados.
     *
     * Los alias son obligatorios: sin ellos Laravel nombraría las columnas
     * según la relación y el frontend (categorias.php) dejaría de encontrar
     * manuales_asignados_count y documentos_asignados_count.
     *
     * Los usuarios eliminados tampoco se cuentan. La fila de user_categories
     * SOBREVIVE al soft-delete a proposito —si el usuario se restaura,
     * recupera sus categorias— pero para la pantalla ese usuario ya no esta,
     * y el numero decia lo contrario.
     *
     * Alcanza con deleted_at: un usuario purgado siempre esta eliminado
     * primero (purgar() exige deleted_at no nulo), asi que queda cubierto.
     *
     * Los inactivos SI se cuentan: una cuenta bloqueada sigue perteneciendo a
     * la categoria y puede volver a habilitarse. Es distinto de eliminada.
     *
     * NO usar esto en destroy(): ahí el conteo es una barrera de integridad y
     * necesita ver TODO lo asignado. Está explicado en ese método.
     */
    private static function conteosVisibles(): array
    {
        return [
            // La columna va calificada ('users.deleted_at') y no pelada: la
            // query trae users unido al pivote, y sin el prefijo la condicion
            // seria ambigua si user_categories tuviera su propia deleted_at.
            'usuarios' => fn ($q) => $q->whereNull('users.deleted_at'),

            'manualesAsignados as manuales_asignados_count' => fn ($q) =>
                $q->where('manuals.estado', 'publicado')
                  ->whereNull('manuals.deleted_at'),

            'documentosAsignados as documentos_asignados_count' => fn ($q) =>
                $q->where('documents.visible_franquiciado', 1)
                  ->whereNull('documents.deleted_at'),
        ];
    }

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