<?php

namespace App\Services;

use App\Models\Manual;
use App\Models\ManualEmpresaAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Collection;

/**
 * Fuente única de verdad para el control de acceso a manuales (v2.3).
 *
 * Reemplaza la lógica que antes estaba duplicada en ManualController y otros
 * controllers. Antes, el patrón `findOrFail($id)` seguido de acciones sobre el
 * recurso sin validar tenant permitía a un franquiciado enumerar IDs y actuar
 * sobre manuales de otras empresas (ver informe de auditoría H-001 a H-007).
 *
 * Uso típico:
 *   if (!ManualAccessService::usuarioTieneAccesoAlManual($user, $manualId)) {
 *       return response()->json(['error' => 'Sin acceso a este manual.'], 403);
 *   }
 *
 * Reglas por rol:
 *   super_admin    → siempre TRUE (acceso total al sistema)
 *   franquiciante  → TRUE si el manual está asignado a su empresa
 *                    (manual_empresa_assignments)
 *   franquiciado   → TRUE si tiene una categoría activa que apunta al manual,
 *                    O si tiene asignación individual (manual_user_assignments)
 *   empleado       → mismo criterio que franquiciado
 *   otros roles    → FALSE (defensa en profundidad)
 */
class ManualAccessService
{
    /**
     * Gate principal: ¿este usuario puede ver/interactuar con este manual?
     *
     * Usar en TODOS los endpoints que reciban un {manualId} o {versionId} en
     * el path y no estén ya cubiertos por un scope Eloquent.
     */
    public static function usuarioTieneAccesoAlManual(User $user, int $manualId): bool
    {
        // Super admin: acceso total al sistema.
        if ($user->esSuperAdmin()) {
            return true;
        }

        // Franquiciante: puede si el manual está asignado a su empresa.
        if ($user->esFranquiciante()) {
            if (!$user->empresa_id) {
                return false; // franquiciante sin empresa (anomalía) → sin acceso
            }
            return self::empresaTieneAccesoAlManual($manualId, $user->empresa_id);
        }

        // Franquiciado / empleado: categoría activa O asignación individual.
        if ($user->esFranquiciado() || $user->esEmpleado()) {
            if (!$user->empresa_id) {
                return false;
            }
            return self::tienePorCategoriaOIndividual(
                $user->id,
                $manualId,
                $user->empresa_id
            );
        }

        // Cualquier otro rol: sin acceso (defensa en profundidad).
        return false;
    }

    /**
     * ¿La empresa tiene el manual asignado en manual_empresa_assignments?
     *
     * Se usa como gate para franquiciante (ve todo lo de su empresa) y como
     * precondición cuando se listan cosas relacionadas al manual desde el rol
     * de admin (ej: aceptaciones de todos los franquiciados).
     */
    public static function empresaTieneAccesoAlManual(int $manualId, int $empresaId): bool
    {
        return ManualEmpresaAssignment::where('manual_id', $manualId)
                                      ->where('empresa_id', $empresaId)
                                      ->exists();
    }

    /**
     * Devuelve todos los manuales publicados visibles para un franquiciado o
     * empleado, con la propiedad `mi_aceptacion` (bool) inyectada según haya
     * aceptado ya la versión activa.
     *
     * Este método NO valida rol — se asume que el caller ya sabe que el
     * usuario es franquiciado o empleado. Para super_admin y franquiciante,
     * usar los scopes Eloquent directamente desde el ManualController.
     */
    public static function manualesVisiblesParaUsuario(User $user): Collection
    {
        $empresaId = $user->empresa_id;
        $userId    = $user->id;

        return Manual::publicados()
            ->with('versionActiva')
            ->whereHas('empresasAsignadas', fn($q) => $q->where('empresa_id', $empresaId))
            // v2.3: visibilidad por asignación de categoría activa OR asignación individual.
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
     * Verifica si un usuario (franquiciado/empleado) tiene acceso a un manual
     * por categoría activa O asignación individual, dentro de una empresa.
     *
     * Método privado — el punto de entrada público es usuarioTieneAccesoAlManual.
     */
    private static function tienePorCategoriaOIndividual(int $userId, int $manualId, int $empresaId): bool
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
}