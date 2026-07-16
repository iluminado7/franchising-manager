<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\Franquicia;
use App\Models\User;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class EmpresaController extends Controller
{
    // GET /api/empresas
    public function index(Request $request): JsonResponse
    {
        // Por defecto se ocultan las dadas de baja. Con ?include_deleted=1 se
        // incluyen (para el toggle "mostrar eliminadas" del panel).
        $incluirEliminadas = (bool) $request->query('include_deleted', false);

        $empresas = Empresa::with(['plan', 'emails', 'deletedBy:id,nombre,apellido,rol'])
                           ->withCount(['franquicias', 'franquiciasActivas'])
                           ->when(!$incluirEliminadas, fn($q) => $q->noEliminadas())
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

    // DELETE /api/empresas/{id}  (solo super_admin)
    // Soft-delete en cascada: baja la empresa y TODAS sus franquicias no-eliminadas
    // en la misma transaccion, con el mismo timestamp. Suspende (activa=false) y
    // revoca los tokens de todos los usuarios de la empresa.
    public function destroy(Request $request, int $id): JsonResponse
    {
        $empresa = Empresa::findOrFail($id);
        $actor   = $request->user();

        if ($empresa->deleted_at !== null) {
            return response()->json(['error' => 'La empresa ya fue dada de baja.'], 409);
        }

        $ahora = now();

        DB::transaction(function () use ($empresa, $actor, $ahora, $id) {
            // Empresa: baja + suspension. deleted_at/deleted_by/activa con setter
            // directo (deleted_* no estan en $fillable; activa si, pero lo unificamos).
            $empresa->deleted_by = $actor->id;
            $empresa->deleted_at = $ahora;
            $empresa->activa     = false;
            $empresa->save();

            // Cascada: solo las franquicias que HOY estan activas (no las ya
            // dadas de baja). Se marcan con el MISMO deleted_at que la empresa;
            // ese timestamp compartido es la firma de "cayo por esta cascada" y
            // permite revivir solo estas al restaurar.
            Franquicia::where('empresa_id', $id)
                      ->whereNull('deleted_at')
                      ->update([
                          'deleted_by' => $actor->id,
                          'deleted_at' => $ahora,
                          'activa'     => 0,
                      ]);

            // Revocar tokens de todos los usuarios de la empresa: los saca del
            // sistema de inmediato, sin esperar a que expiren.
            User::where('empresa_id', $id)->each(fn($u) => $u->tokens()->delete());
        });

        ActivityLog::registrar(
            userId:      $actor->id,
            accion:      'empresa_eliminada',
            ip:          $request->ip(),
            empresaId:   $empresa->id,
            entidadTipo: 'empresas',
            entidadId:   $empresa->id,
            userAgent:   $request->userAgent()
        );

        return response()->json(['message' => 'Empresa dada de baja correctamente.']);
    }

    // POST /api/empresas/{id}/restore  (solo super_admin)
    // Restaura la empresa y las franquicias que cayeron en la MISMA cascada
    // (mismo deleted_at). NO reactiva: activa queda en false hasta que el
    // super_admin la reactive a mano. Restaurar != reactivar.
    public function restore(Request $request, int $id): JsonResponse
    {
        $empresa = Empresa::findOrFail($id);
        $actor   = $request->user();

        if ($empresa->deleted_at === null) {
            return response()->json(['error' => 'La empresa no esta dada de baja.'], 409);
        }

        $tsBaja = $empresa->deleted_at;

        DB::transaction(function () use ($empresa, $id, $tsBaja) {
            $empresa->deleted_by = null;
            $empresa->deleted_at = null;
            // activa NO se toca: queda en false. Reactivar es un paso aparte.
            $empresa->save();

            // Solo las franquicias que se bajaron EN ESTA cascada (mismo timestamp).
            // Las que el usuario habia dado de baja por su cuenta antes NO reviven.
            Franquicia::where('empresa_id', $id)
                      ->where('deleted_at', $tsBaja)
                      ->update([
                          'deleted_by' => null,
                          'deleted_at' => null,
                          // activa queda en 0: reactivar la sucursal es aparte.
                      ]);
        });

        ActivityLog::registrar(
            userId:      $actor->id,
            accion:      'empresa_restaurada',
            ip:          $request->ip(),
            empresaId:   $empresa->id,
            entidadTipo: 'empresas',
            entidadId:   $empresa->id,
            userAgent:   $request->userAgent()
        );

        return response()->json(['message' => 'Empresa restaurada. Reactivala cuando quieras habilitar el acceso.']);
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
