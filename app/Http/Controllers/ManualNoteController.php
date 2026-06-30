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
    // Devuelve un hilo unificado que combina:
    //   - feedback (tabla manual_notes) de franquiciantes/franquiciados
    //   - release notes (manual_versions.nota_publicacion) escritas por quien publicó la versión
    // Cada item lleva un campo "tipo" ('feedback' o 'release') para que el front les dé estilo distinto.
    //
    // Visibilidad de feedback:
    //   super_admin: todas las notas del manual (de todas las empresas).
    //   franquiciante: todas las de su empresa (propias + de sus franquiciados).
    //   franquiciado: solo las propias.
    //   empleado: sin acceso.
    //
    // Visibilidad de release notes: TODOS los roles autorizados las ven (son anuncios públicos
    // del publicador a todos los franquiciados que tengan acceso al manual).
    public function porManual(Request $request, int $manualId): JsonResponse
    {
        $user = $request->user();

        // ── 1) Feedback (manual_notes) según visibilidad por rol ────────────
        $query = ManualNote::with(self::RELACIONES)
                           ->where('manual_id', $manualId);

        if ($user->esSuperAdmin()) {
            // todas
        } elseif ($user->esFranquiciante()) {
            $query->where('empresa_id', $user->empresa_id);
        } elseif ($user->esFranquiciado()) {
            $query->where('empresa_id', $user->empresa_id)
                  ->where('user_id', $user->id);
        } else {
            abort(403, 'Sin acceso a las notas.');
        }

        $feedback = $query->get()->map(function ($n) {
            // Convertimos a array y agregamos el tipo para uniformar con release notes.
            $arr = $n->toArray();
            $arr['tipo'] = 'feedback';
            return $arr;
        });

        // ── 2) Release notes (manual_versions.nota_publicacion) ─────────────
        $releases = ManualVersion::with(['publicadoPor.systemAdmin', 'publicadoPor.superAdmin', 'publicadoPor.franchiseStaff.franquicia'])
                                 ->where('manual_id', $manualId)
                                 ->whereNotNull('nota_publicacion')
                                 ->where('nota_publicacion', '!=', '')
                                 ->orderBy('version_number')
                                 ->get()
                                 ->map(function ($v) {
                                     return [
                                         'tipo'              => 'release',
                                         'id'                => 'rel_' . $v->id,
                                         'manual_id'         => $v->manual_id,
                                         'manual_version_id' => $v->id,
                                         'contenido'         => $v->nota_publicacion,
                                         'created_at'        => $v->publicado_at,
                                         'autor'             => $v->publicadoPor,
                                         'version'           => [
                                             'id'             => $v->id,
                                             'version_number' => $v->version_number,
                                         ],
                                     ];
                                 });

        // ── 3) Mezclar y ordenar cronológicamente (más viejas primero) ─────
        $hilo = $feedback->concat($releases)
                         ->sortBy(fn($item) => $item['created_at'] ?? '')
                         ->values();

        return response()->json($hilo);
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

    // PUT /api/notas/{id}/estado — super_admin o franquiciante de la empresa
    // Marca una nota como pendiente / leida / resuelta.
    // Reglas (v2.3):
    //   - super_admin: puede gestionar el estado de cualquier nota, EXCEPTO las propias
    //   - franquiciante: solo notas de SU empresa, EXCEPTO las propias
    //   - nadie más
    public function updateEstado(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'estado' => 'required|in:pendiente,leida,resuelta',
        ]);

        $nota = ManualNote::findOrFail($id);
        $user = $request->user();

        // No podés cambiar el estado de tus propias notas.
        if ((int) $nota->user_id === (int) $user->id) {
            return response()->json([
                'error' => 'No podés cambiar el estado de tus propias notas.',
            ], 403);
        }

        // Si es franquiciante, debe ser de la misma empresa de la nota.
        if ($user->esFranquiciante() && (int) $nota->empresa_id !== (int) $user->empresa_id) {
            return response()->json([
                'error' => 'Sin acceso a esta nota.',
            ], 403);
        }

        // Otros roles ya están bloqueados por la ruta (middleware role:...),
        // pero por defensa en profundidad lo repetimos acá.
        if (!$user->esSuperAdmin() && !$user->esFranquiciante()) {
            return response()->json(['error' => 'Sin permisos.'], 403);
        }

        $nota->estado = $request->estado;
        $nota->save();

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