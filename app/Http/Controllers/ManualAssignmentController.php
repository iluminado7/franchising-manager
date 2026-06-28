<?php

namespace App\Http\Controllers;

use App\Models\Manual;
use App\Models\ManualUserAssignment;
use App\Models\Notification;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Controller de asignaciones INDIVIDUALES de manuales (manual ↔ usuario).
 * Las asignaciones POR CATEGORÍA viven en otro controller.
 *
 * Las rutas siguen siendo /api/empleados/... por compatibilidad con el frontend
 * pero ahora aceptan tanto empleados como franquiciados.
 */
class ManualAssignmentController extends Controller
{
    // GET /api/empleados/{userId}/asignaciones
    public function porEmpleado(Request $request, int $userId): JsonResponse
    {
        $actor   = $request->user();
        $usuario = User::findOrFail($userId);

        if (!$this->actorPuedeGestionar($actor, $usuario)) {
            return response()->json(['error' => 'Sin acceso.'], 403);
        }

        $asignaciones = ManualUserAssignment::where('user_id', $userId)
                                            ->with('manual.versionActiva')
                                            ->get();

        return response()->json($asignaciones);
    }

    // POST /api/empleados/{userId}/asignaciones
    public function asignar(Request $request, int $userId): JsonResponse
    {
        $request->validate([
            'manual_id' => 'required|integer|exists:manuals,id',
        ]);

        $actor = $request->user();

        // v2.3: aceptar franquiciado o empleado (antes solo empleado).
        $usuario = User::where('id', $userId)
                       ->whereIn('rol', ['franquiciado', 'empleado'])
                       ->firstOrFail();

        if (!$this->actorPuedeGestionar($actor, $usuario)) {
            return response()->json(['error' => 'Sin acceso.'], 403);
        }

        $manual = Manual::findOrFail($request->manual_id);

        // v2.3: validar que el manual esté disponible en la empresa del usuario.
        // Antes faltaba este check — se podían crear asignaciones inconsistentes
        // (manual no asociado a la empresa del usuario, que después no aparece
        // en las queries de visibilidad).
        $manualEnEmpresa = DB::table('manual_empresa_assignments')
            ->where('manual_id', $manual->id)
            ->where('empresa_id', $usuario->empresa_id)
            ->exists();

        if (!$manualEnEmpresa) {
            return response()->json([
                'error' => 'Este manual no está disponible en la empresa del usuario.'
            ], 422);
        }

        $yaAsignado = ManualUserAssignment::where('manual_id', $manual->id)
                                          ->where('user_id', $userId)
                                          ->exists();

        if ($yaAsignado) {
            return response()->json(['message' => 'Este manual ya está asignado.'], 409);
        }

        ManualUserAssignment::create([
            'manual_id'   => $manual->id,
            'user_id'     => $userId,
            'empresa_id'  => $usuario->empresa_id,
            'assigned_by' => $actor->id,
            'assigned_at' => now(),
        ]);

        // Notificación: tipo 'manual_asignado' con manual_version_id (sin cambios v2.3,
        // ya está soportado en chk_notif_fk).
        $versionActiva = $manual->versionActiva()->first();
        if ($versionActiva) {
            try {
                Notification::create([
                    'user_id'             => $userId,
                    'tipo'                => 'manual_asignado',
                    'manual_id'           => null,
                    'manual_version_id'   => $versionActiva->id,
                    'document_id'         => null,
                    'document_version_id' => null,
                    'category_id'         => null,
                    'titulo'              => "Se te asignó el manual: {$manual->titulo}",
                ]);
            } catch (\Throwable $e) { /* best-effort */ }
        }

        ActivityLog::registrar(
            userId:      $actor->id,
            accion:      'manual_asignado',
            ip:          $request->ip(),
            empresaId:   $usuario->empresa_id,
            entidadTipo: 'manual_user_assignments',  // v2.3: tabla renombrada
            entidadId:   $manual->id,
            detalle:     [
                'manual_titulo'   => $manual->titulo,
                'empleado_nombre' => $usuario->nombreCompleto(),  // v2.3: nombre vive en users
            ],
            userAgent:   $request->userAgent()
        );

        return response()->json(['message' => 'Manual asignado correctamente.'], 201);
    }

    // DELETE /api/empleados/{userId}/asignaciones/{manualId}
    public function desasignar(Request $request, int $userId, int $manualId): JsonResponse
    {
        $actor   = $request->user();
        $usuario = User::findOrFail($userId);

        if (!$this->actorPuedeGestionar($actor, $usuario)) {
            return response()->json(['error' => 'Sin acceso.'], 403);
        }

        $asignacion = ManualUserAssignment::where('user_id', $userId)
                                          ->where('manual_id', $manualId)
                                          ->firstOrFail();

        $manual = Manual::find($manualId);
        $asignacion->delete();

        ActivityLog::registrar(
            userId:      $actor->id,
            accion:      'manual_desasignado',
            ip:          $request->ip(),
            empresaId:   $usuario->empresa_id,
            entidadTipo: 'manual_user_assignments',
            entidadId:   $manualId,
            detalle:     [
                'manual_titulo'   => $manual?->titulo,
                'empleado_nombre' => $usuario->nombreCompleto(),
            ],
            userAgent:   $request->userAgent()
        );

        return response()->json(['message' => 'Asignación eliminada correctamente.']);
    }

    // ── PRIVADOS ─────────────────────────────────────────────────────

    /**
     * Determina si el actor tiene permiso para gestionar (ver/asignar/desasignar)
     * las asignaciones individuales de manuales del $usuario objetivo.
     *
     * Reglas:
     *  - super_admin: cualquier usuario.
     *  - franquiciante: franquiciados y empleados de su empresa.
     *  - franquiciado: solo empleados de SU MISMA franquicia/sucursal.
     *  - empleado: sin permisos.
     *
     * v2.3: el chequeo de franquicia para franquiciado es nuevo. Antes faltaba
     * y un franquiciado podía gestionar empleados de otras sucursales de la
     * misma empresa, lo cual era un agujero de aislamiento intra-empresa.
     */
    private function actorPuedeGestionar(User $actor, User $usuario): bool
    {
        if ($actor->esSuperAdmin()) {
            return true;
        }

        if ($actor->esFranquiciante()) {
            // Solo gestiona franquiciados/empleados de su empresa
            if (!in_array($usuario->rol, ['franquiciado', 'empleado'])) {
                return false;
            }
            return $usuario->empresa_id === $actor->empresa_id;
        }

        if ($actor->esFranquiciado()) {
            // Solo gestiona empleados de su misma franquicia
            if ($usuario->rol !== 'empleado') {
                return false;
            }
            if ($usuario->empresa_id !== $actor->empresa_id) {
                return false;
            }
            $actorFranquiciaId   = $actor->franchiseStaff?->franquicia_id;
            $usuarioFranquiciaId = $usuario->franchiseStaff?->franquicia_id;
            if (!$actorFranquiciaId || !$usuarioFranquiciaId) {
                return false;
            }
            return $actorFranquiciaId === $usuarioFranquiciaId;
        }

        return false;
    }
}