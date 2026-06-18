<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Empresa;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class InvoiceController extends Controller
{
    // GET /api/invoices
    // Super admin: todos | Franquiciante: solo su empresa
    public function index(Request $request): JsonResponse
    {
        $user  = $request->user();
        $query = Invoice::with(['empresa', 'plan'])->orderBy('periodo', 'desc');

        if ($user->esFranquiciante()) {
            $query->where('empresa_id', $user->empresa_id);
        } elseif ($user->esSuperAdmin() && $request->filled('empresa_id')) {
            $query->where('empresa_id', $request->empresa_id);
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        return response()->json($query->get());
    }

    // GET /api/invoices/{id}
    public function show(Request $request, int $id): JsonResponse
    {
        $invoice = Invoice::with(['empresa', 'plan'])->findOrFail($id);

        if ($request->user()->esFranquiciante()) {
            if ($invoice->empresa_id !== $request->user()->empresa_id) {
                return response()->json(['error' => 'Sin acceso.'], 403);
            }
        }

        return response()->json($invoice);
    }

    // PUT /api/invoices/{id}
    // Solo super_admin — actualizar estado de pago
    public function update(Request $request, int $id): JsonResponse
    {
        $invoice = Invoice::findOrFail($id);

        $data = $request->validate([
            'estado'    => 'required|in:pendiente,pagada,vencida',
            'notas'     => 'nullable|string|max:1000',
            'pagado_at' => 'nullable|date',
        ]);

        // Si se marca como pagada sin fecha, usar ahora
        if ($data['estado'] === 'pagada' && empty($data['pagado_at'])) {
            $data['pagado_at'] = now();
        }

        $invoice->update($data);

        ActivityLog::registrar(
            userId:      $request->user()->id,
            accion:      'invoice_generada',
            ip:          $request->ip(),
            empresaId:   $invoice->empresa_id,
            entidadTipo: 'invoices',
            entidadId:   $invoice->id,
            detalle:     ['campo' => 'estado', 'valor_nuevo' => $data['estado']],
            userAgent:   $request->userAgent()
        );

        return response()->json($invoice->fresh(['empresa', 'plan']));
    }

    // POST /api/invoices/generar
    // Solo super_admin — genera facturas del mes actual para todas las empresas activas
    public function generar(Request $request): JsonResponse
    {
        $periodo = now()->startOfMonth()->toDateString();
        $generadas = 0;
        $errores   = [];

        $empresas = Empresa::with('plan')->where('activa', 1)->get();

        foreach ($empresas as $empresa) {
            // Evitar duplicado para el mismo período
            $existe = Invoice::where('empresa_id', $empresa->id)
                             ->where('periodo', $periodo)
                             ->exists();

            if ($existe) continue;

            try {
                $plan = $empresa->plan;
                $franquiciasActivas = $empresa->franquiciasActivas()->count();

                $precioPorFranquicia = null;
                $precioGlobalSnapshot = null;
                $total = 0;

                if ($plan->esPorFranquicia()) {
                    $precioPorFranquicia = $empresa->precioEfectivoporFranquicia();
                    $total = $franquiciasActivas * $precioPorFranquicia;
                } else {
                    $precioGlobalSnapshot = $empresa->precioEfectivoGlobal();
                    $total = $precioGlobalSnapshot;
                }

                $numeroFactura = $empresa->id . '-' . now()->format('Ym');

                Invoice::create([
                    'empresa_id'            => $empresa->id,
                    'plan_id'               => $plan->id,
                    'periodo'               => $periodo,
                    'numero_factura'        => $numeroFactura,
                    'franquicias_activas'   => $franquiciasActivas,
                    'precio_por_franquicia' => $precioPorFranquicia,
                    'precio_global_snapshot'=> $precioGlobalSnapshot,
                    'total'                 => $total,
                    'estado'                => 'pendiente',
                ]);

                $generadas++;

            } catch (\Exception $e) {
                $errores[] = "Empresa {$empresa->nombre}: {$e->getMessage()}";
            }
        }

        ActivityLog::registrar(
            userId:    $request->user()->id,
            accion:    'invoice_generada',
            ip:        $request->ip(),
            detalle:   ['campo' => 'periodo', 'valor_nuevo' => $periodo],
            userAgent: $request->userAgent()
        );

        return response()->json([
            'message'   => "{$generadas} factura(s) generada(s) para el período {$periodo}.",
            'generadas' => $generadas,
            'errores'   => $errores,
        ]);
    }
}
