<?php

namespace App\Http\Controllers;

use App\Models\Manual;
use App\Models\Empresa;
use App\Models\ManualEmpresaAssignment;
use App\Models\Notification;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ManualEmpresaAssignmentController extends Controller
{
    // GET /api/manuales/{manualId}/empresas
    // Lista las empresas asignadas a un manual
    public function porManual(int $manualId): JsonResponse
    {
        $manual = Manual::findOrFail($manualId);

        $asignaciones = ManualEmpresaAssignment::where('manual_id', $manualId)
                                               ->with(['empresa.plan', 'asignadoPor.superAdmin'])
                                               ->get();

        return response()->json($asignaciones);
    }

    // GET /api/empresas/{empresaId}/manuales
    // Lista los manuales asignados a una empresa
    public function porEmpresa(int $empresaId): JsonResponse
    {
        Empresa::findOrFail($empresaId);

        $asignaciones = ManualEmpresaAssignment::where('empresa_id', $empresaId)
                                               ->with(['manual.versionActiva'])
                                               ->get();

        return response()->json($asignaciones);
    }

    // POST /api/manuales/{manualId}/empresas
    // Asigna un manual a una empresa
    public function asignar(Request $request, int $manualId): JsonResponse
    {
        $request->validate([
            'empresa_id' => 'required|integer|exists:empresas,id',
        ]);

        $manual  = Manual::findOrFail($manualId);
        $empresa = Empresa::findOrFail($request->empresa_id);

        $yaAsignado = ManualEmpresaAssignment::where('manual_id', $manualId)
                                             ->where('empresa_id', $request->empresa_id)
                                             ->exists();

        if ($yaAsignado) {
            return response()->json([
                'message' => 'Este manual ya está asignado a la empresa.'
            ], 409);
        }

        $asignacion = ManualEmpresaAssignment::create([
            'manual_id'   => $manualId,
            'empresa_id'  => $request->empresa_id,
            'asignado_por'=> $request->user()->id,
            'asignado_at' => now(),
        ]);

        // Notificar a todos los franquiciados activos de la empresa
        $this->notificarFranquiciados($manual, $empresa);

        ActivityLog::registrar(
            userId:      $request->user()->id,
            accion:      'manual_asignado_empresa',
            ip:          $request->ip(),
            empresaId:   $empresa->id,
            entidadTipo: 'manual_empresa_assignments',
            entidadId:   $manual->id,
            detalle:     [
                'manual_titulo' => $manual->titulo,
                'empresa_nombre'=> $empresa->nombre,
            ],
            userAgent:   $request->userAgent()
        );

        return response()->json([
            'message'    => 'Manual asignado a la empresa correctamente.',
            'asignacion' => $asignacion,
        ], 201);
    }

    // DELETE /api/manuales/{manualId}/empresas/{empresaId}
    public function desasignar(Request $request, int $manualId, int $empresaId): JsonResponse
    {
        $asignacion = ManualEmpresaAssignment::where('manual_id', $manualId)
                                             ->where('empresa_id', $empresaId)
                                             ->firstOrFail();

        $manual  = Manual::find($manualId);
        $empresa = Empresa::find($empresaId);

        $asignacion->delete();

        ActivityLog::registrar(
            userId:      $request->user()->id,
            accion:      'manual_asignado_empresa',
            ip:          $request->ip(),
            empresaId:   $empresaId,
            entidadTipo: 'manual_empresa_assignments',
            entidadId:   $manualId,
            detalle:     [
                'manual_titulo'  => $manual?->titulo,
                'empresa_nombre' => $empresa?->nombre,
                'valor_nuevo'    => 'desasignado',
            ],
            userAgent:   $request->userAgent()
        );

        return response()->json(['message' => 'Asignación eliminada correctamente.']);
    }

    private function notificarFranquiciados(Manual $manual, Empresa $empresa): void
    {
        $versionActiva = $manual->versionActiva()->first();
        if (!$versionActiva) return;

        $franquiciados = User::where('rol', 'franquiciado')
                             ->where('activo', 1)
                             ->where('empresa_id', $empresa->id)
                             ->pluck('id');

        $notificaciones = $franquiciados->map(fn($uid) => [
            'user_id'           => $uid,
            'tipo'              => 'nuevo_manual',
            'manual_id'         => $manual->id,
            'manual_version_id' => null,
            'document_id'       => null,
            'titulo'            => "Nuevo manual disponible: {$manual->titulo}",
            'created_at'        => now(),
        ])->toArray();

        if (!empty($notificaciones)) {
            Notification::insert($notificaciones);
        }
    }
}
