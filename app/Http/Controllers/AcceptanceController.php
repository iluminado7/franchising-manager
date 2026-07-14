<?php

namespace App\Http\Controllers;

use App\Models\Acceptance;
use App\Models\ManualVersion;
use App\Models\ActivityLog;
use App\Services\ManualAccessService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

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

        // V2-H-010: todo el flujo de aceptación va dentro de una transacción con
        // lock pesimista sobre la fila de la versión. Motivos:
        //
        //  1. aceptar() no validaba que la versión fuera la ACTIVA. Sin ninguna
        //     carrera de por medio, un usuario podía postear el ID de una versión
        //     vieja y registrar una aceptación sobre contenido ya superado.
        //
        //  2. Un check de es_activa NO alcanza: archivar() marca el manual como
        //     'archivado' pero NO toca las versiones, así que la versión de un
        //     manual archivado sigue con es_activa = 1. Hay que validar también
        //     el estado del manual.
        //
        //  3. acceptances NO tenía UNIQUE (manual_version_id, user_id): dos
        //     requests concurrentes pasaban ambos por fueAceptadaPor() y creaban
        //     DOS filas de compliance. El lock serializa; el UNIQUE nuevo (ver
        //     migración add_unique_version_user_to_acceptances) es la garantía real.
        try {
            $acceptance = DB::transaction(function () use ($version, $user, $request) {

                // Lock pesimista: nadie más toca esta versión hasta el commit.
                $v = ManualVersion::where('id', $version->id)
                                  ->lockForUpdate()
                                  ->firstOrFail();

                if (!$v->es_activa) {
                    return ['_error' => 'Esta versión ya no es la vigente. Actualizá la página.', '_status' => 409];
                }

                $manual = $v->manual;
                if (!$manual || $manual->deleted_at !== null || $manual->estado !== 'publicado') {
                    return ['_error' => 'Este manual no está disponible para aceptación.', '_status' => 409];
                }

                // Re-chequeo DENTRO del lock (el de afuera puede haber quedado obsoleto).
                if ($v->fueAceptadaPor($user->id)) {
                    return ['_error' => 'Ya aceptaste esta versión del manual.', '_status' => 409];
                }

                return Acceptance::create([
                    'manual_version_id' => $v->id,
                    'user_id'           => $user->id,
                    'empresa_id'        => $user->empresa_id, // desnormalizado para performance
                    'aceptado_at'       => now(),
                    'ip_address'        => $request->ip(),
                    'user_agent'        => $request->userAgent(),
                    'hash_verificacion' => $v->contenido_hash,
                    'pdf_generado'      => 0,
                ]);
            });
        } catch (\Illuminate\Database\QueryException $e) {
            // Red de seguridad: si el UNIQUE salta igual (dos nodos, lock perdido),
            // respondemos 409 en vez de un 500.
            if ($e->getCode() === '23000') {
                return response()->json(['message' => 'Ya aceptaste esta versión del manual.'], 409);
            }
            throw $e;
        }

        // La closure devuelve un array cuando hay que abortar con un código propio.
        if (is_array($acceptance)) {
            return response()->json(['message' => $acceptance['_error']], $acceptance['_status']);
        }

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