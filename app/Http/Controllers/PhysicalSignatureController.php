<?php

namespace App\Http\Controllers;

use App\Models\PhysicalSignature;
use App\Models\ManualVersion;
use App\Models\Franquicia;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class PhysicalSignatureController extends Controller
{
    // POST /api/versiones/{versionId}/firma-fisica
    // Solo franquiciado — sube el PDF escaneado firmado
    public function subir(Request $request, int $versionId): JsonResponse
    {
        $request->validate([
            'archivo'       => 'required|file|mimes:pdf|max:10240',
            'franquicia_id' => 'required|integer|exists:franquicias,id',
            'notas'         => 'nullable|string|max:500',
        ]);

        $user    = $request->user();
        $version = ManualVersion::findOrFail($versionId);

        // Verificar que la franquicia pertenece a la empresa del usuario
        $franquicia = Franquicia::findOrFail($request->franquicia_id);
        if ($franquicia->empresa_id !== $user->empresa_id) {
            return response()->json(['error' => 'Sin acceso a esta franquicia.'], 403);
        }

        $archivo = $request->file('archivo');
        $hash    = hash_file('sha256', $archivo->getRealPath());

        $path = Storage::disk(config('filesystems.default'))
                       ->putFile(
                           "firmas/{$versionId}/{$request->franquicia_id}",
                           $archivo
                       );

        $firma = PhysicalSignature::updateOrCreate(
            [
                'manual_version_id' => $version->id,
                'franquicia_id'     => $request->franquicia_id,
            ],
            [
                'subido_por'   => $user->id,
                'archivo_url'  => Storage::url($path),
                'archivo_hash' => $hash,
                'notas'        => $request->notas,
                'updated_at'   => now(),
            ]
        );

        ActivityLog::registrar(
            userId:      $user->id,
            accion:      'firma_fisica_subida',
            ip:          $request->ip(),
            empresaId:   $user->empresa_id,
            entidadTipo: 'physical_signatures',
            entidadId:   $firma->id,
            detalle:     [
                'manual_titulo' => $version->manual->titulo,
                'version'       => $version->version_number,
            ],
            userAgent:   $request->userAgent()
        );

        return response()->json([
            'message' => 'Firma física cargada correctamente.',
            'firma'   => $firma,
        ], 201);
    }

    // GET /api/versiones/{versionId}/firmas-fisicas
    public function porVersion(Request $request, int $versionId): JsonResponse
    {
        $user  = $request->user();
        $query = PhysicalSignature::where('manual_version_id', $versionId)
                                  ->with(['franquicia', 'subidoPor.franchiseStaff']);

        // Franquiciante solo ve firmas de su empresa
        if ($user->esFranquiciante()) {
            $query->whereHas('franquicia', fn($q) =>
                $q->where('empresa_id', $user->empresa_id)
            );
        }

        return response()->json($query->get());
    }
}
