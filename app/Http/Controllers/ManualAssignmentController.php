<?php

namespace App\Http\Controllers;

use App\Models\Manual;
use App\Models\ManualAssignment;
use App\Models\Notification;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ManualAssignmentController extends Controller
{
    // GET /api/empleados/{userId}/asignaciones
    public function porEmpleado(Request $request, int $userId): JsonResponse
    {
        $actor    = $request->user();
        $empleado = User::findOrFail($userId);

        // Franquiciante solo puede ver empleados de su empresa
        if ($actor->esFranquiciante() && $empleado->empresa_id !== $actor->empresa_id) {
            return response()->json(['error' => 'Sin acceso.'], 403);
        }

        $asignaciones = ManualAssignment::where('user_id', $userId)
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

        $actor    = $request->user();
        $empleado = User::where('id', $userId)
                        ->where('rol', 'empleado')
                        ->firstOrFail();

        // Verificar que el empleado pertenece a la empresa del actor
        if ($actor->esFranquiciante() || $actor->esFranquiciado()) {
            if ($empleado->empresa_id !== $actor->empresa_id) {
                return response()->json(['error' => 'Sin acceso.'], 403);
            }
        }

        $manual = Manual::findOrFail($request->manual_id);

        $yaAsignado = ManualAssignment::where('manual_id', $manual->id)
                                      ->where('user_id', $userId)
                                      ->exists();

        if ($yaAsignado) {
            return response()->json(['message' => 'Este manual ya está asignado.'], 409);
        }

        ManualAssignment::create([
            'manual_id'   => $manual->id,
            'user_id'     => $userId,
            'empresa_id'  => $empleado->empresa_id,
            'assigned_by' => $actor->id,
            'assigned_at' => now(),
        ]);

        $versionActiva = $manual->versionActiva()->first();
        if ($versionActiva) {
            Notification::create([
                'user_id'           => $userId,
                'tipo'              => 'manual_asignado',
                'manual_id'         => null,
                'manual_version_id' => $versionActiva->id,
                'document_id'       => null,
                'titulo'            => "Se te asignó el manual: {$manual->titulo}",
            ]);
        }

        ActivityLog::registrar(
            userId:      $actor->id,
            accion:      'manual_asignado',
            ip:          $request->ip(),
            empresaId:   $empleado->empresa_id,
            entidadTipo: 'manual_assignments',
            entidadId:   $manual->id,
            detalle:     [
                'manual_titulo'   => $manual->titulo,
                'empleado_nombre' => $empleado->franchiseStaff->nombreCompleto(),
            ],
            userAgent:   $request->userAgent()
        );

        return response()->json(['message' => 'Manual asignado correctamente.'], 201);
    }

    // DELETE /api/empleados/{userId}/asignaciones/{manualId}
    public function desasignar(Request $request, int $userId, int $manualId): JsonResponse
    {
        $actor    = $request->user();
        $empleado = User::findOrFail($userId);

        if ($actor->esFranquiciante() && $empleado->empresa_id !== $actor->empresa_id) {
            return response()->json(['error' => 'Sin acceso.'], 403);
        }

        $asignacion = ManualAssignment::where('user_id', $userId)
                                      ->where('manual_id', $manualId)
                                      ->firstOrFail();

        $manual = Manual::find($manualId);
        $asignacion->delete();

        ActivityLog::registrar(
            userId:      $actor->id,
            accion:      'manual_desasignado',
            ip:          $request->ip(),
            empresaId:   $empleado->empresa_id,
            entidadTipo: 'manual_assignments',
            entidadId:   $manualId,
            detalle:     [
                'manual_titulo'   => $manual?->titulo,
                'empleado_nombre' => $empleado->franchiseStaff?->nombreCompleto(),
            ],
            userAgent:   $request->userAgent()
        );

        return response()->json(['message' => 'Asignación eliminada correctamente.']);
    }
}
