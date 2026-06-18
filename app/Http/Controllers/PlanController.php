<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PlanController extends Controller
{
    // GET /api/planes
    public function index(): JsonResponse
    {
        $planes = Plan::withCount('empresas')
                      ->orderBy('nombre')
                      ->get();

        return response()->json($planes);
    }

    // GET /api/planes/{id}
    public function show(int $id): JsonResponse
    {
        $plan = Plan::withCount('empresas')->findOrFail($id);
        return response()->json($plan);
    }

    // POST /api/planes
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nombre'                    => 'required|string|max:100',
            'descripcion' => 'nullable|string|max:500',
            'tipo_plan'                 => 'required|in:por_franquicia,global',
            'precio_base_por_franquicia'=> 'nullable|numeric|min:0',
            'precio_global'             => 'nullable|numeric|min:0',
            'limite_franquicias'        => 'nullable|integer|min:1',
        ]);

        // Validar coherencia tipo_plan / precio
        if ($data['tipo_plan'] === 'por_franquicia' && empty($data['precio_base_por_franquicia'])) {
            return response()->json([
                'message' => 'Un plan por_franquicia requiere precio_base_por_franquicia.'
            ], 422);
        }
        if ($data['tipo_plan'] === 'global' && empty($data['precio_global'])) {
            return response()->json([
                'message' => 'Un plan global requiere precio_global.'
            ], 422);
        }

        $plan = Plan::create($data);

        ActivityLog::registrar(
            userId:      $request->user()->id,
            accion:      'plan_modificado',
            ip:          $request->ip(),
            entidadTipo: 'planes',
            entidadId:   $plan->id,
            detalle:     ['campo' => 'nombre', 'valor_nuevo' => $plan->nombre],
            userAgent:   $request->userAgent()
        );

        return response()->json($plan, 201);
    }

    // PUT /api/planes/{id}
    public function update(Request $request, int $id): JsonResponse
    {
        $plan = Plan::findOrFail($id);

        $data = $request->validate([
            'nombre'                    => 'sometimes|string|max:100',
            'descripcion' => 'nullable|string|max:500',
            'precio_base_por_franquicia'=> 'nullable|numeric|min:0',
            'precio_global'             => 'nullable|numeric|min:0',
            'limite_franquicias'        => 'nullable|integer|min:1',
            'activo'                    => 'sometimes|boolean',
        ]);

        $plan->update($data);

        ActivityLog::registrar(
            userId:      $request->user()->id,
            accion:      'plan_modificado',
            ip:          $request->ip(),
            entidadTipo: 'planes',
            entidadId:   $plan->id,
            detalle:     ['campo' => 'precio', 'valor_nuevo' => (string)($data['precio_base_por_franquicia'] ?? $data['precio_global'] ?? '')],
            userAgent:   $request->userAgent()
        );

        return response()->json($plan->fresh());
    }
}
