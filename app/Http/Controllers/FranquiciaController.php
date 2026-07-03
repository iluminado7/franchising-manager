<?php

namespace App\Http\Controllers;

use App\Models\Franquicia;
use App\Models\ActivityLog;
use App\Models\ManualVersion;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class FranquiciaController extends Controller
{
    // GET /api/franquicias
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Franquicia::withCount('staff')->orderBy('nombre');

        // Franquiciante solo ve las franquicias de su empresa
        if ($user->esFranquiciante()) {
            $query->where('empresa_id', $user->empresa_id);
        }

        // Super admin puede filtrar por empresa
        if ($user->esSuperAdmin() && $request->filled('empresa_id')) {
            $query->where('empresa_id', $request->empresa_id);
        }

        return response()->json($query->get());
    }

    // GET /api/franquicias/{id}
    public function show(Request $request, int $id): JsonResponse
    {
        $franquicia = Franquicia::with([
            'staff.user',
            'documentos',
            'empresa',
        ])->findOrFail($id);

        // Verificar que pertenece a la empresa del franquiciante
        if ($request->user()->esFranquiciante()) {
            if ($franquicia->empresa_id !== $request->user()->empresa_id) {
                return response()->json(['error' => 'Sin acceso.'], 403);
            }
        }

        return response()->json($franquicia);
    }

    // POST /api/franquicias
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nombre'          => 'required|string|max:150',
            'razon_social'    => 'required|string|max:200',
            'cuit'            => 'required|string|max:15',
            'direccion'       => 'nullable|string|max:300',
            'telefono'        => 'nullable|string|max:30',
            'email_contacto'  => 'nullable|email|max:200',
            'es_sede_central' => 'sometimes|boolean',
            'empresa_id'      => 'sometimes|integer|exists:empresas,id',
        ]);

        // Asignar empresa_id según rol
        $data['empresa_id'] = $request->user()->esSuperAdmin()
            ? ($data['empresa_id'] ?? null)
            : $request->user()->empresa_id;

        // Si se marca como sede central, desmarcar la anterior de la misma empresa
        if (!empty($data['es_sede_central']) && $data['empresa_id']) {
            Franquicia::where('empresa_id', $data['empresa_id'])
                      ->where('es_sede_central', 1)
                      ->update(['es_sede_central' => 0]);
        }

        $franquicia = Franquicia::create($data);

        ActivityLog::registrar(
            userId:    $request->user()->id,
            accion:    'franquicia_creada',
            ip:        $request->ip(),
            empresaId: $data['empresa_id'],
            entidadTipo: 'franquicias',
            entidadId:   $franquicia->id,
            userAgent: $request->userAgent()
        );

        return response()->json($franquicia, 201);
    }

    // PUT /api/franquicias/{id}
    public function update(Request $request, int $id): JsonResponse
    {
        $franquicia = Franquicia::findOrFail($id);

        // Verificar acceso
        if ($request->user()->esFranquiciante()) {
            if ($franquicia->empresa_id !== $request->user()->empresa_id) {
                return response()->json(['error' => 'Sin acceso.'], 403);
            }
        }

        $data = $request->validate([
            'nombre'          => 'sometimes|string|max:150',
            'razon_social'    => 'sometimes|string|max:200',
            'cuit'            => 'sometimes|string|max:15',
            'direccion'       => 'nullable|string|max:300',
            'telefono'        => 'nullable|string|max:30',
            'email_contacto'  => 'nullable|email|max:200',
            'activa'          => 'sometimes|boolean',
            'es_sede_central' => 'sometimes|boolean',
        ]);

        // Si se marca como sede central, desmarcar la anterior de la misma empresa
        if (!empty($data['es_sede_central'])) {
            Franquicia::where('empresa_id', $franquicia->empresa_id)
                      ->where('id', '!=', $id)
                      ->where('es_sede_central', 1)
                      ->update(['es_sede_central' => 0]);
        }

        // H-016 fix: detectar si esta operación está suspendiendo la franquicia
        // (transición activa=true → activa=false). Antes, la suspensión dejaba
        // los tokens Sanctum válidos hasta 8h; solo el middleware EnsureActiveTenant
        // impedía el uso, lo que dejaba una ventana peligrosa si el middleware
        // se desactivaba temporalmente. Ahora revocamos explícitamente.
        $seSuspendio = array_key_exists('activa', $data)
                       && $data['activa'] === false
                       && (bool) $franquicia->activa === true;

        $franquicia->update($data);

        if ($seSuspendio) {
            // Revocar tokens de todos los usuarios asignados a esta franquicia.
            $userIds = User::whereHas('franchiseStaff', fn($q) =>
                $q->where('franquicia_id', $franquicia->id)
            )->pluck('id');

            if ($userIds->isNotEmpty()) {
                // Borra tokens Sanctum en bulk (más eficiente que iterar).
                DB::table('personal_access_tokens')
                    ->where('tokenable_type', User::class)
                    ->whereIn('tokenable_id', $userIds)
                    ->delete();
            }

            try {
                ActivityLog::registrar(
                    userId:      $request->user()->id,
                    accion:      'franquicia_suspendida_tokens_revocados',
                    ip:          $request->ip(),
                    empresaId:   $franquicia->empresa_id,
                    entidadTipo: 'franquicias',
                    entidadId:   $franquicia->id,
                    userAgent:   $request->userAgent()
                );
            } catch (\Throwable $e) { /* best-effort */ }
        }

        return response()->json($franquicia);
    }

    // GET /api/franquicias/{id}/dashboard
    public function dashboard(Request $request, int $id): JsonResponse
    {
        $franquicia = Franquicia::with([
            'staff' => fn($q) => $q->whereHas('user', fn($u) =>
                $u->where('rol', 'franquiciado')
            )->with('user')
        ])->findOrFail($id);

        // Verificar acceso
        if ($request->user()->esFranquiciante()) {
            if ($franquicia->empresa_id !== $request->user()->empresa_id) {
                return response()->json(['error' => 'Sin acceso.'], 403);
            }
        }

        // Versiones activas de manuales publicados asignados a esta empresa
        $versiones = ManualVersion::where('es_activa', 1)
            ->whereHas('manual', fn($q) =>
                $q->where('estado', 'publicado')
                  ->whereHas('empresasAsignadas', fn($e) =>
                      $e->where('empresa_id', $franquicia->empresa_id)
                  )
            )
            ->with('manual')
            ->get();

        $resultado = [];

        foreach ($franquicia->staff as $staff) {
            $userId = $staff->user_id;

            foreach ($versiones as $version) {
                $digital = $version->acceptances()
                                   ->where('user_id', $userId)
                                   ->first();

                $fisica = $version->firmasFisicas()
                                  ->where('franquicia_id', $id)
                                  ->first();

                $resultado[] = [
                    'franquiciado' => $staff->nombreCompleto(),
                    'manual'       => $version->manual->titulo,
                    'version'      => $version->version_number,
                    'digital'      => $digital ? ['fecha' => $digital->aceptado_at] : null,
                    'fisica'       => $fisica   ? ['fecha' => $fisica->created_at]  : null,
                    'estado'       => match(true) {
                        (bool)$digital && (bool)$fisica  => 'completo',
                        (bool)$digital && !(bool)$fisica => 'solo_digital',
                        !(bool)$digital && (bool)$fisica => 'solo_fisico',
                        default                          => 'pendiente_total',
                    },
                ];
            }
        }

        return response()->json([
            'franquicia'   => $franquicia->nombre,
            'validaciones' => $resultado,
        ]);
    }
}