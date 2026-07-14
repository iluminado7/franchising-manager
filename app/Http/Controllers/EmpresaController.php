<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\User;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class EmpresaController extends Controller
{
    // GET /api/empresas
    public function index(Request $request): JsonResponse
    {
        $empresas = Empresa::with(['plan', 'emails'])
                           ->withCount(['franquicias', 'franquiciasActivas'])
                           ->orderBy('nombre')
                           ->get();

        return response()->json($empresas);
    }

    // GET /api/empresas/{id}
    public function show(int $id): JsonResponse
    {
        $empresa = Empresa::with([
            'plan',
            'emails',
            'franquicias',
            'systemAdmins.user',
        ])->withCount(['franquicias', 'franquiciasActivas'])
          ->findOrFail($id);

        return response()->json($empresa);
    }

    // POST /api/empresas
    public function store(Request $request): JsonResponse
    {
        $esSuperAdmin = $request->user()->rol === 'super_admin';

        $data = $request->validate([
            'nombre'                       => 'required|string|max:200',
            'razon_social'                 => 'required|string|max:200',
            'cuit'                         => 'required|string|max:15|unique:empresas,cuit',
            'plan_id'                      => 'required_if:facturable,1,true|nullable|integer|exists:planes,id',
            'precio_custom_por_franquicia' => 'nullable|numeric|min:0',
            'precio_custom_global'         => 'nullable|numeric|min:0',
            'facturable'                   => 'sometimes|boolean',
        ]);

        // Solo super_admin puede crear una empresa exenta.
        if (!$esSuperAdmin) {
            unset($data['facturable']);
        }

        $data = $this->normalizarFacturacion($data);

        $empresa = Empresa::create($data);

        ActivityLog::registrar(
            userId:      $request->user()->id,
            accion:      'empresa_creada',
            ip:          $request->ip(),
            empresaId:   $empresa->id,
            entidadTipo: 'empresas',
            entidadId:   $empresa->id,
            detalle:     ['campo' => 'nombre', 'valor_nuevo' => $empresa->nombre],
            userAgent:   $request->userAgent()
        );

        return response()->json($empresa->load('plan'), 201);
    }

    // PUT /api/empresas/{id}

    public function update(Request $request, int $id): JsonResponse
    {
        $empresa      = Empresa::findOrFail($id);
        $esSuperAdmin = $request->user()->rol === 'super_admin';

        $data = $request->validate([
            'nombre'                       => 'sometimes|string|max:200',
            'razon_social'                 => 'sometimes|string|max:200',
            'cuit'                         => "sometimes|string|max:15|unique:empresas,cuit,{$id}",
            'plan_id'                      => 'sometimes|nullable|integer|exists:planes,id',
            'precio_custom_por_franquicia' => 'nullable|numeric|min:0',
            'precio_custom_global'         => 'nullable|numeric|min:0',
            'facturable'                   => 'sometimes|boolean',
            'activa'                       => 'sometimes|boolean',
        ]);

        // Solo super_admin puede cambiar el estado de exención.
        if (!$esSuperAdmin) {
            unset($data['facturable']);
        }

        // Estado final de facturable (el del payload si vino, si no el actual)
        $facturableFinal = array_key_exists('facturable', $data)
            ? (bool) $data['facturable']
            : (bool) $empresa->facturable;

        $data = $this->normalizarFacturacion($data, $facturableFinal);

        $empresa->update($data);

        // Si se suspende, revocar todos los tokens de los usuarios de la empresa
        if (isset($data['activa']) && !$data['activa']) {
            User::where('empresa_id', $id)->each(fn($u) => $u->tokens()->delete());

            ActivityLog::registrar(
                userId:      $request->user()->id,
                accion:      'empresa_suspendida',
                ip:          $request->ip(),
                empresaId:   $empresa->id,
                entidadTipo: 'empresas',
                entidadId:   $empresa->id,
                userAgent:   $request->userAgent()
            );
        }

        return response()->json($empresa->fresh('plan'));
    }

    /**
     * Si la empresa queda exenta, fuerza plan_id y precios custom a null.
     * Así el CHECK chk_exenta_sin_plan nunca es la primera línea de defensa
     * (un 500 de MySQL es peor UX que un guardado coherente).
     */
    private function normalizarFacturacion(array $data, ?bool $facturable = null): array
    {
        $facturable ??= (bool) ($data['facturable'] ?? true);

        if (!$facturable) {
            $data['plan_id']                      = null;
            $data['precio_custom_por_franquicia'] = null;
            $data['precio_custom_global']         = null;
        }

        return $data;
    }
    // GET /api/empresas/{id}/dashboard
    // Resumen ejecutivo: franquicias, usuarios, manuales asignados, aceptaciones
    public function dashboard(int $id): JsonResponse
    {
        $empresa = Empresa::with(['plan'])->findOrFail($id);

        $totalFranquicias  = $empresa->franquicias()->count();
        $franquiciasActivas = $empresa->franquiciasActivas()->count();
        $totalUsuarios     = User::where('empresa_id', $id)->count();
        $manualesAsignados = $empresa->manualEmpresaAssignments()->count();

        $aceptacionesPendientes = User::where('empresa_id', $id)
            ->where('rol', 'franquiciado')
            ->where('activo', 1)
            ->whereDoesntHave('acceptances')
            ->count();

        return response()->json([
            'empresa'                 => $empresa->nombre,
            'facturable'              => (bool) $empresa->facturable,
            'plan'                    => $empresa->facturable
                                         ? $empresa->plan?->nombre
                                         : 'Exenta (interna)',
            'total_franquicias'       => $totalFranquicias,
            'franquicias_activas'     => $franquiciasActivas,
            'total_usuarios'          => $totalUsuarios,
            'manuales_asignados'      => $manualesAsignados,
            'aceptaciones_pendientes' => $aceptacionesPendientes,
            'precio_efectivo'         => $empresa->precioEfectivoporFranquicia()
                                         ?? $empresa->precioEfectivoGlobal(),
        ]);
    }
}
