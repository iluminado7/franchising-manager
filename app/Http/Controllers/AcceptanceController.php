<?php

namespace App\Http\Controllers;

use App\Models\Acceptance;
use App\Models\ManualVersion;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AcceptanceController extends Controller
{
    // POST /api/versiones/{versionId}/aceptar
    public function aceptar(Request $request, int $versionId): JsonResponse
    {
        $user    = $request->user();
        $version = ManualVersion::findOrFail($versionId);

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

        $query = $version->acceptances()
                         ->with('user.franchiseStaff.franquicia')
                         ->orderBy('aceptado_at', 'desc');

        // Franquiciante solo ve aceptaciones de su empresa
        if ($user->esFranquiciante()) {
            $query->where('empresa_id', $user->empresa_id);
        }

        return response()->json($query->get());
    }
}
