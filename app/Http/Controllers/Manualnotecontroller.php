<?php

namespace App\Http\Controllers;

use App\Models\ManualNote;
use App\Models\ManualVersion;
use App\Models\ManualEmpresaAssignment;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ManualNoteController extends Controller
{
    // Relaciones que se cargan para mostrar cada nota (incluye autor + franquicia del autor).
    private const RELACIONES = ['empresa', 'version', 'autor.franchiseStaff.franquicia'];

    // GET /api/manuales/{manualId}/notas
    // super_admin: todas las notas del manual (de todas las empresas).
    // franquiciante: todas las de su empresa (propias + de sus franquiciados).
    // franquiciado: solo las propias.
    // empleado: sin acceso.
    public function porManual(Request $request, int $manualId): JsonResponse
    {
        $user = $request->user();

        $query = ManualNote::with(self::RELACIONES)
                           ->where('manual_id', $manualId)
                           ->orderBy('created_at'); // hilo cronológico (más viejas primero)

        if ($user->esSuperAdmin()) {
            // todas
        } elseif ($user->esFranquiciante()) {
            $query->where('empresa_id', $user->empresa_id);
        } elseif ($user->esFranquiciado()) {
            $query->where('empresa_id', $user->empresa_id)
                  ->where('user_id', $user->id); // solo las suyas
        } else {
            abort(403, 'Sin acceso a las notas.');
        }

        return response()->json($query->get());
    }

    // POST /api/manuales/{manualId}/notas — franquiciante y franquiciado
    // Agrega una nota (sugerencia) al hilo del manual.
    public function store(Request $request, int $manualId): JsonResponse
    {
        $request->validate([
            'contenido' => 'required|string|max:5000',
        ]);

        $user = $request->user();

        // El manual tiene que estar asignado a la empresa del autor
        $asignado = ManualEmpresaAssignment::where('manual_id', $manualId)
                                           ->where('empresa_id', $user->empresa_id)
                                           ->exists();
        if (!$asignado) {
            return response()->json(['error' => 'Sin acceso a este manual.'], 403);
        }

        // Versión activa del manual al momento de escribir la nota (puede no existir)
        $versionActivaId = ManualVersion::where('manual_id', $manualId)
                                        ->where('es_activa', 1)
                                        ->value('id');

        $nota = ManualNote::create([
            'manual_id'         => $manualId,
            'empresa_id'        => $user->empresa_id,   // del usuario, nunca del request
            'manual_version_id' => $versionActivaId,    // null si el manual aún no tiene versión publicada
            'user_id'           => $user->id,
            'contenido'         => $request->contenido,
            'estado'            => 'pendiente',
        ]);

        ActivityLog::registrar(
            userId:      $user->id,
            accion:      'nota_manual_enviada',
            ip:          $request->ip(),
            empresaId:   $user->empresa_id,
            entidadTipo: 'manual_notes',
            entidadId:   $nota->id,
            userAgent:   $request->userAgent()
        );

        return response()->json($nota->load(self::RELACIONES), 201);
    }

    // PUT /api/notas/{id}/estado — solo super_admin
    // Marca una nota como pendiente / leida / resuelta.
    public function updateEstado(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'estado' => 'required|in:pendiente,leida,resuelta',
        ]);

        $nota = ManualNote::findOrFail($id);
        $nota->estado = $request->estado;
        $nota->save();

        $user = $request->user();

        ActivityLog::registrar(
            userId:      $user->id,
            accion:      'nota_manual_estado',
            ip:          $request->ip(),
            empresaId:   $nota->empresa_id,
            entidadTipo: 'manual_notes',
            entidadId:   $nota->id,
            userAgent:   $request->userAgent()
        );

        return response()->json($nota->load(self::RELACIONES));
    }
}