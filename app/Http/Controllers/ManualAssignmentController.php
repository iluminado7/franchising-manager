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

    // GET /api/manuales/{manualId}/usuarios
    // Lista las asignaciones INDIVIDUALES del manual. Franquiciante solo ve las
    // de su empresa; super_admin ve todas.
    public function porManual(Request $request, int $manualId): JsonResponse
    {
        $actor  = $request->user();
        Manual::findOrFail($manualId);

        if (!$actor->esSuperAdmin() && !$actor->esFranquiciante()) {
            return response()->json(['error' => 'Sin permisos.'], 403);
        }

        $query = ManualUserAssignment::with('user:id,nombre,apellido,email,rol')
                                     ->where('manual_id', $manualId);

        if ($actor->esFranquiciante()) {
            $query->where('empresa_id', $actor->empresa_id);
        }

        return response()->json($query->get());
    }

    // PUT /api/manuales/{manualId}/usuarios
    // Body: { "user_ids": [...], "empresa_id"?: N }
    // Sincroniza las asignaciones INDIVIDUALES del manual dentro de una empresa.
    // A diferencia de documentos (una empresa), un manual puede vivir en varias,
    // por eso el scope de empresa se resuelve igual que en el sync de categorias:
    // super_admin lo manda, franquiciante lo deriva de su token.
    public function sincronizarPorManual(Request $request, int $manualId): JsonResponse
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
            'empresa_id' => 'sometimes|integer|exists:empresas,id',
            'user_ids'   => 'present|array',
            'user_ids.*' => 'integer|exists:users,id',
        ]);

        $empresaId = $actor->esSuperAdmin()
            ? ($data['empresa_id'] ?? null)
            : $actor->empresa_id;

        if (!$empresaId) {
            return response()->json(['error' => 'empresa_id es requerido para sincronizar.'], 422);
        }

        // El manual debe estar disponible en esa empresa.
        $manualEnEmpresa = DB::table('manual_empresa_assignments')
            ->where('manual_id', $manual->id)
            ->where('empresa_id', $empresaId)
            ->exists();

        if (!$manualEnEmpresa) {
            return response()->json(['error' => 'El manual no está disponible en esta empresa.'], 422);
        }

        // Validar cada usuario destino (rol, empresa del scope, activo).
        $usuarios = User::whereIn('id', $data['user_ids'])->get();
        foreach ($usuarios as $u) {
            $error = $this->validarAsignacionUsuarioManual($u, $empresaId);
            if ($error) {
                return response()->json(['error' => $error['error'] . " (usuario: {$u->email})"], $error['_status']);
            }
        }

        // Diff respecto a las asignaciones actuales en esa empresa.
        $actuales = ManualUserAssignment::where('manual_id', $manual->id)
                                        ->where('empresa_id', $empresaId)
                                        ->pluck('user_id')
                                        ->toArray();

        $nuevos   = collect($data['user_ids'])->unique()->values();
        $aAgregar = $nuevos->diff($actuales);
        $aQuitar  = collect($actuales)->diff($nuevos);

        DB::transaction(function () use ($manual, $actor, $aAgregar, $aQuitar, $usuarios, $empresaId, $request) {
            $versionActiva = $manual->versionActiva()->first();

            foreach ($aAgregar as $uid) {
                $u = $usuarios->firstWhere('id', $uid);

                ManualUserAssignment::create([
                    'empresa_id'  => $empresaId,
                    'manual_id'   => $manual->id,
                    'user_id'     => $uid,
                    'assigned_by' => $actor->id,
                    'assigned_at' => now(),
                ]);

                // Notificacion: tipo 'manual_asignado' con manual_version_id (soportado
                // en chk_notif_fk). Best-effort: no rompe la transaccion si falla.
                if ($versionActiva) {
                    try {
                        Notification::create([
                            'user_id'             => $uid,
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
                    empresaId:   $empresaId,
                    entidadTipo: 'manual_user_assignments',
                    entidadId:   $manual->id,
                    detalle:     [
                        'manual_titulo'   => $manual->titulo,
                        'empleado_nombre' => $u?->nombreCompleto() ?? '(desconocido)',
                    ],
                    userAgent:   $request->userAgent()
                );
            }

            if ($aQuitar->isNotEmpty()) {
                $usersQuitados = User::whereIn('id', $aQuitar->toArray())->get();

                ManualUserAssignment::where('manual_id', $manual->id)
                                    ->where('empresa_id', $empresaId)
                                    ->whereIn('user_id', $aQuitar->toArray())
                                    ->delete();

                foreach ($usersQuitados as $u) {
                    ActivityLog::registrar(
                        userId:      $actor->id,
                        accion:      'manual_desasignado',
                        ip:          $request->ip(),
                        empresaId:   $empresaId,
                        entidadTipo: 'manual_user_assignments',
                        entidadId:   $manual->id,
                        detalle:     [
                            'manual_titulo'   => $manual->titulo,
                            'empleado_nombre' => $u->nombreCompleto(),
                        ],
                        userAgent:   $request->userAgent()
                    );
                }
            }
        });

        return response()->json([
            'message'      => 'Asignaciones individuales del manual actualizadas.',
            'asignaciones' => ManualUserAssignment::with('user:id,nombre,apellido,email,rol')
                                ->where('manual_id', $manual->id)
                                ->where('empresa_id', $empresaId)
                                ->get(),
        ]);
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

    /**
     * Valida que un usuario pueda recibir asignacion individual del manual
     * dentro de una empresa. Espejo de validarAsignacionUsuario de documentos,
     * pero el scope de empresa se pasa explicito (el manual vive en varias).
     */
    private function validarAsignacionUsuarioManual(User $usuario, int $empresaId): ?array
    {
        if (!in_array($usuario->rol, ['franquiciado', 'empleado'])) {
            return ['error' => 'Solo se pueden asignar manuales individualmente a franquiciados o empleados.', '_status' => 422];
        }
        if ($usuario->empresa_id !== $empresaId) {
            return ['error' => 'El usuario no pertenece a la empresa del manual.', '_status' => 422];
        }
        if (!$usuario->activo || $usuario->deleted_at !== null) {
            return ['error' => 'El usuario está inactivo o eliminado.', '_status' => 422];
        }
        return null;
    }
}