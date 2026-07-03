<?php

namespace App\Http\Controllers;

use App\Models\Acceptance;
use App\Models\ManualVersion;
use App\Models\ActivityLog;
use App\Services\ManualAccessService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AcceptanceController extends Controller
{
    // POST /api/versiones/{versionId}/aceptar
    public function aceptar(Request $request, int $versionId): JsonResponse
    {
        $user    = $request->user();
        $version = ManualVersion::findOrFail($versionId);

        // H-001 fix: verificar que el usuario tenga acceso efectivo al manual.
        // Antes, un franquiciado de empresa A podía aceptar versiones de
        // manuales de empresa B enumerando IDs, contaminando el registro de
        // compliance. Ahora el gate es idéntico al usado para leer el manual.
        if (!ManualAccessService::usuarioTieneAccesoAlManual($user, $version->manual_id)) {
            return response()->json([
                'error' => 'Sin acceso a este manual.',
            ], 403);
        }

        if ($version->fueAceptadaPor($user->id)) {
            return response()->json([
                'message' => 'Ya aceptaste esta versión del manual.',
            ], 409);
        }

        $acceptance = Acceptance::create([
            'manual_version_id' => $version->id,
            'user_id'           => $user->id,
            'empresa_id'        => $user->empresa_id, // desnormalizado para performance
            'aceptado_at'       => now(),
            'ip_address'        => $request->ip(),
            'user_agent'        => $request->userAgent(),
            'hash_verificacion' => $version->contenido_hash,
            'pdf_generado'      => 0,
        ]);

        ActivityLog::registrar(
            userId:      $user->id,
            accion:      'manual_aceptado',
            ip:          $request->ip(),
            empresaId:   $user->empresa_id,
            entidadTipo: 'acceptances',
            entidadId:   $acceptance->id,
            detalle:     [
                'manual_titulo' => $version->manual->titulo,
                'version'       => $version->version_number,
            ],
            userAgent:   $request->userAgent()
        );

        return response()->json([
            'message'    => 'Aceptación registrada correctamente.',
            'acceptance' => $acceptance,
        ], 201);
    }

    // GET /api/versiones/{versionId}/aceptaciones
    public function porVersion(Request $request, int $versionId): JsonResponse
    {
        $version = ManualVersion::findOrFail($versionId);
        $user    = $request->user();

        // H-001 fix (bug B): antes se hacía findOrFail sin validar que el
        // franquiciante tuviera acceso al manual. El filtro por empresa_id en
        // el query devolvía vacío, pero permitía enumeración de IDs para
        // descubrir la existencia de manuales de otras empresas.
        // Ahora bloqueamos explícitamente al franquiciante que no tenga el
        // manual asignado. Super_admin pasa siempre.
        if ($user->esFranquiciante()) {
            if (!ManualAccessService::empresaTieneAccesoAlManual(
                $version->manual_id,
                $user->empresa_id
            )) {
                return response()->json([
                    'error' => 'Sin acceso a este manual.',
                ], 403);
            }
        }

        $query = $version->acceptances()
                         ->with('user.franchiseStaff.franquicia')
                         ->orderBy('aceptado_at', 'desc');

        // Franquiciante solo ve aceptaciones de su empresa (defensa en profundidad).
        if ($user->esFranquiciante()) {
            $query->where('empresa_id', $user->empresa_id);
        }

        return response()->json($query->get());
    }
}