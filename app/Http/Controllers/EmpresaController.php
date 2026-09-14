<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\Franquicia;
use App\Models\User;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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
            'es_demo'                      => 'sometimes|boolean',
        ]);

        // Solo super_admin puede crear una empresa exenta.
        if (!$esSuperAdmin) {
            unset($data['facturable']);
        }

        // es_demo no esta en $fillable: se saca de $data y se asigna abajo con
        // setter directo. Solo super_admin: la ruta ya lo exige, y se repite
        // por defensa en profundidad, igual que con facturable.
        $esDemo = $esSuperAdmin && !empty($data['es_demo']);
        unset($data['es_demo']);

        // Una demo no tiene plan ni precios: pasar a cliente es un flujo aparte
        // que todavia no existe. Queda con facturable = 1 porque no puede ser
        // exenta (uq_unica_exenta admite una sola); lo que la saca de la
        // facturacion es Empresa::scopeFacturables().
        if ($esDemo) {
            $data['facturable']                   = true;
            $data['plan_id']                      = null;
            $data['precio_custom_por_franquicia'] = null;
            $data['precio_custom_global']         = null;
        }

        $data = $this->normalizarFacturacion($data);

        $empresa = new Empresa($data);
        if ($esDemo) {
            $empresa->es_demo = true;
            // Siempre del servidor, nunca del request: el vencimiento no se
            // extiende.
            $empresa->demo_vence_at = now()->addDays(Empresa::DEMO_DIAS);
        }
        $empresa->save();

        ActivityLog::registrar(
            userId:      $request->user()->id,
            accion:      $esDemo ? 'empresa_demo_creada' : 'empresa_creada',
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

        // Una demo no recibe plan ni cambia su facturacion desde aca:
        // convertirla en cliente es un flujo aparte (pendiente, con validacion
        // en ARCA). Sin este filtro, asignarle un plan desde "Editar" la
        // dejaria a medio convertir: todavia demo, pero con plan.
        if ($empresa->es_demo) {
            unset(
                $data['facturable'],
                $data['plan_id'],
                $data['precio_custom_por_franquicia'],
                $data['precio_custom_global']
            );
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

    // POST /api/empresas/{id}/borrar-definitivo  (solo super_admin)
    //
    // Borrado FISICO de una empresa y todo lo suyo. Irreversible. En la UI se
    // ofrece solo sobre empresas ya dadas de baja.
    //
    // ── QUE SE PUEDE BORRAR ─────────────────────────────────────────────
    //
    //   - Empresa demo: todo. Sus lecturas, firmas y logs no son evidencia de
    //     nada: son una prueba.
    //   - Empresa real: solo si nunca se le facturo y no tiene lecturas ni
    //     firmas. Es el caso de una empresa cargada por error. Si tiene algo
    //     de eso, se rechaza: las facturas tienen obligacion legal de
    //     conservarse, y lecturas y firmas son la evidencia de cumplimiento.
    //
    // ── POR QUE NO UN DELETE DE LA EMPRESA ──────────────────────────────
    //
    // La base lo aceptaria, y dejaria todo roto: users.empresa_id es
    // ON DELETE SET NULL, asi que sus usuarios quedarian con empresa_id NULL,
    // que es la marca del super_admin (README §3). Lo mismo sucursales,
    // documentos y lecturas: huerfanos. Y los archivos quedarian en el storage.
    //
    // Por eso se borra de las hojas hacia la empresa, tabla por tabla. Las FK
    // RESTRICT hacia users (lecturas, firmas, logs, manuales) obligan a ese
    // orden: si falta un paso, MySQL rechaza el DELETE y la transaccion se
    // revierte entera. Es una red, no un problema.
    //
    // ── LO QUE FRENA EL BORRADO AUNQUE SEA DEMO ─────────────────────────
    //
    // Cualquier cosa de la empresa que use OTRA empresa. El caso tipico: un
    // manual creado por su franquiciante y asignado tambien a otra empresa.
    // Borrarlo le rompe el manual a esa otra; conservarlo es imposible
    // (manuals.created_by es RESTRICT). Se informa y no se borra nada.
    //
    // ── ARCHIVOS ────────────────────────────────────────────────────────
    //
    // Se juntan las rutas ANTES de borrar las filas y se borran DESPUES de
    // confirmar la transaccion. Al reves, si la transaccion se revirtiera, las
    // filas volverian apuntando a archivos que ya no existen. Si falla borrar
    // algun archivo, queda huerfano en el storage: se loguea y se informa.
    public function borrarDefinitivo(Request $request, int $id): JsonResponse
    {
        $actor = $request->user();

        // Doble guard, igual que la purga de usuarios: la ruta ya exige
        // super_admin, pero si un refactor la moviera de grupo, esta accion
        // quedaria expuesta en silencio.
        if (!$actor->esSuperAdmin()) {
            return response()->json(['message' => 'Sin permisos.'], 403);
        }

        $empresa = Empresa::findOrFail($id);

        // Obliga a pasar por la baja, que es reversible, antes de lo
        // irreversible.
        if ($empresa->deleted_at === null) {
            return response()->json([
                'message' => 'Primero hay que dar de baja la empresa. El borrado definitivo solo se hace sobre empresas dadas de baja.',
            ], 409);
        }

        $nombre   = $empresa->nombre;
        $cuit     = $empresa->cuit;
        $esDemo   = (bool) $empresa->es_demo;
        $archivos = [];
        $resumen  = [];

        DB::transaction(function () use ($id, $actor, $request, $nombre, $cuit, $esDemo, &$archivos, &$resumen) {
            // Bloquea la fila: nadie modifica la empresa mientras se calcula
            // que borrar. Todo lo de abajo se calcula ADENTRO de la transaccion,
            // no antes: un chequeo previo quedaria viejo en el momento de borrar.
            DB::table('empresas')->where('id', $id)->lockForUpdate()->first();

            $userIds  = DB::table('users')->where('empresa_id', $id)->pluck('id')->all();
            $franqIds = DB::table('franquicias')->where('empresa_id', $id)->pluck('id')->all();

            // Manuales que creo alguien de la empresa. Se borran con ella.
            $manualIds = $userIds
                ? DB::table('manuals')->whereIn('created_by', $userIds)->pluck('id')->all()
                : [];
            $versionIds = $manualIds
                ? DB::table('manual_versions')->whereIn('manual_id', $manualIds)->pluck('id')->all()
                : [];

            $docIds = DB::table('documents')
                        ->where('empresa_id', $id)
                        ->when($franqIds, fn ($q) => $q->orWhereIn('franquicia_id', $franqIds))
                        ->pluck('id')->all();
            $docVersionIds = $docIds
                ? DB::table('document_versions')->whereIn('document_id', $docIds)->pluck('id')->all()
                : [];

            $impedimentos = $this->impedimentosBorradoDefinitivo(
                $id, $esDemo, $userIds, $franqIds, $manualIds, $versionIds, $docIds, $docVersionIds
            );
            if ($impedimentos) {
                // abort() adentro de la transaccion la revierte y sale como 409
                // con {message}, que es lo que la pantalla muestra.
                abort(409, 'No se puede borrar definitivamente. ' . implode(' ', $impedimentos));
            }

            // ── Rutas de archivos, ANTES de borrar las filas ────────────
            $firmas = DB::table('physical_signatures')
                        ->where(function ($q) use ($userIds, $franqIds, $versionIds) {
                            $q->whereRaw('1 = 0');
                            if ($userIds)    $q->orWhereIn('user_id', $userIds);
                            if ($franqIds)   $q->orWhereIn('franquicia_id', $franqIds);
                            if ($versionIds) $q->orWhereIn('manual_version_id', $versionIds);
                        });
            $lecturas = DB::table('acceptances')
                          ->where(function ($q) use ($id, $userIds, $versionIds) {
                              $q->where('empresa_id', $id);
                              if ($userIds)    $q->orWhereIn('user_id', $userIds);
                              if ($versionIds) $q->orWhereIn('manual_version_id', $versionIds);
                          });

            $archivos = array_merge(
                $manualIds ? DB::table('manual_versions')->whereIn('manual_id', $manualIds)->whereNotNull('archivo_path')->pluck('archivo_path')->all() : [],
                $manualIds ? DB::table('manual_images')->whereIn('manual_id', $manualIds)->pluck('archivo_path')->all() : [],
                $docIds    ? DB::table('document_versions')->whereIn('document_id', $docIds)->whereNotNull('archivo_url')->pluck('archivo_url')->all() : [],
                (clone $firmas)->whereNotNull('archivo_path')->pluck('archivo_path')->all(),
                (clone $lecturas)->whereNotNull('pdf_sellado_url')->pluck('pdf_sellado_url')->all(),
                $userIds   ? DB::table('users')->whereIn('id', $userIds)->whereNotNull('foto_url')->pluck('foto_url')->all() : [],
            );
            $archivos = array_values(array_unique(array_filter($archivos)));

            $resumen = [
                'usuarios'   => count($userIds),
                'sucursales' => count($franqIds),
                'manuales'   => count($manualIds),
                'documentos' => count($docIds),
                'archivos'   => count($archivos),
            ];

            // ── Borrado, de las hojas hacia la empresa ──────────────────

            // Evidencia de lectura y firma (en una real, ya se verifico que no hay).
            (clone $firmas)->delete();
            (clone $lecturas)->delete();

            // Notas: las de sus usuarios, las de la empresa y las de sus manuales.
            DB::table('manual_notes')->where(function ($q) use ($id, $userIds, $manualIds) {
                $q->where('empresa_id', $id);
                if ($userIds)   $q->orWhereIn('user_id', $userIds);
                if ($manualIds) $q->orWhereIn('manual_id', $manualIds);
            })->delete();

            // Asignaciones. Van antes que los usuarios porque assigned_by es
            // RESTRICT: aunque cuelguen de la empresa en CASCADE, el DELETE de
            // users llega primero y lo rechazaria.
            DB::table('manual_category_assignments')->where(function ($q) use ($id, $manualIds) {
                $q->where('empresa_id', $id);
                if ($manualIds) $q->orWhereIn('manual_id', $manualIds);
            })->delete();
            DB::table('manual_user_assignments')->where(function ($q) use ($id, $userIds, $manualIds) {
                $q->where('empresa_id', $id);
                if ($userIds)   $q->orWhereIn('user_id', $userIds);
                if ($manualIds) $q->orWhereIn('manual_id', $manualIds);
            })->delete();
            DB::table('manual_empresa_assignments')->where(function ($q) use ($id, $manualIds) {
                $q->where('empresa_id', $id);
                if ($manualIds) $q->orWhereIn('manual_id', $manualIds);
            })->delete();
            DB::table('user_categories')->where(function ($q) use ($id, $userIds) {
                $q->where('empresa_id', $id);
                if ($userIds) $q->orWhereIn('user_id', $userIds);
            })->delete();
            DB::table('document_category_assignments')->where(function ($q) use ($id, $docIds) {
                $q->where('empresa_id', $id);
                if ($docIds) $q->orWhereIn('document_id', $docIds);
            })->delete();
            DB::table('document_user_assignments')->where(function ($q) use ($id, $userIds, $docIds) {
                $q->where('empresa_id', $id);
                if ($userIds) $q->orWhereIn('user_id', $userIds);
                if ($docIds)  $q->orWhereIn('document_id', $docIds);
            })->delete();

            // Manuales propios: imagenes y versiones antes que el manual
            // (manual_versions.manual_id es RESTRICT; subido_por tambien).
            if ($manualIds) {
                DB::table('manual_images')->whereIn('manual_id', $manualIds)->delete();
                DB::table('manual_versions')->whereIn('manual_id', $manualIds)->delete();
                DB::table('manuals')->whereIn('id', $manualIds)->delete();
            }

            // Documentos: versiones antes que el documento (subido_por RESTRICT).
            if ($docIds) {
                DB::table('document_versions')->whereIn('document_id', $docIds)->delete();
                DB::table('documents')->whereIn('id', $docIds)->delete();
            }

            DB::table('franchise_categories')->where('empresa_id', $id)->delete();

            if ($userIds) {
                // Su registro de actividad. activity_logs es inmutable por
                // diseño, pero user_id es RESTRICT: sin borrar sus filas no se
                // puede borrar al usuario. Las acciones de los super_admin
                // sobre la empresa NO se borran: quedan con empresa_id NULL.
                DB::table('activity_logs')->whereIn('user_id', $userIds)->delete();

                // Tokens: son polimorficos y no tienen FK, no caerian solos.
                DB::table('personal_access_tokens')
                  ->where('tokenable_type', User::class)
                  ->whereIn('tokenable_id', $userIds)
                  ->delete();

                // error_logs no tiene FK: se desvincula para no apuntar a ids
                // que ya no existen. El error en si sigue siendo diagnostico.
                DB::table('error_logs')->whereIn('user_id', $userIds)->update(['user_id' => null]);

                // franchise_staff, system_admins, notifications y
                // password_resets caen en CASCADE.
                DB::table('users')->whereIn('id', $userIds)->delete();
            }

            DB::table('franquicias')->where('empresa_id', $id)->delete();
            DB::table('error_logs')->where('empresa_id', $id)->update(['empresa_id' => null]);

            // empresa_emails, invoices (ya verificado: ninguna) y lo que quede
            // en CASCADE. activity_logs de los super_admin: SET NULL.
            DB::table('empresas')->where('id', $id)->delete();

            // DENTRO de la transaccion y SIN try/catch, como la purga de
            // usuarios: si no se puede registrar, el borrado se revierte. Una
            // destruccion irreversible sin rastro es peor que una que no ocurre.
            ActivityLog::registrar(
                userId:      $actor->id,
                accion:      'empresa_borrada_definitivamente',
                ip:          $request->ip(),
                empresaId:   null,   // la empresa ya no existe
                entidadTipo: 'empresas',
                entidadId:   $id,
                detalle:     [
                    'campo'          => $esDemo ? 'empresa_demo' : 'empresa',
                    'valor_anterior' => mb_substr("{$nombre} (CUIT {$cuit})", 0, 500),
                ],
                userAgent:   $request->userAgent()
            );
        });

        // ── Archivos, recien con la transaccion confirmada ──────────────
        $disk      = config('filesystems.default');
        $noBorrados = 0;
        foreach ($archivos as $ruta) {
            try {
                if (Storage::disk($disk)->exists($ruta) && !Storage::disk($disk)->delete($ruta)) {
                    $noBorrados++;
                }
            } catch (\Throwable $e) {
                $noBorrados++;
            }
        }
        if ($noBorrados > 0) {
            Log::warning('Borrado definitivo de empresa: quedaron archivos sin borrar en el storage.', [
                'empresa_id'  => $id,
                'no_borrados' => $noBorrados,
                'total'       => count($archivos),
            ]);
        }

        return response()->json([
            'message'     => $noBorrados > 0
                ? "Empresa borrada definitivamente. {$noBorrados} archivo(s) no se pudieron borrar del almacenamiento (quedó registrado)."
                : 'Empresa borrada definitivamente.',
            'resumen'     => $resumen,
            'no_borrados' => $noBorrados,
        ]);
    }

    /**
     * Motivos por los que la empresa NO se puede borrar. Vacio = se puede.
     *
     * Dos familias:
     *   1. Empresa real con historia que hay que conservar.
     *   2. Algo de la empresa que usa otra empresa (vale tambien para demos).
     *
     * @return string[]
     */
    private function impedimentosBorradoDefinitivo(
        int $id, bool $esDemo, array $userIds, array $franqIds,
        array $manualIds, array $versionIds, array $docIds, array $docVersionIds
    ): array {
        $motivos = [];

        // Facturas: nunca, ni en una demo. Son comprobantes fiscales.
        if (DB::table('invoices')->where('empresa_id', $id)->exists()) {
            $motivos[] = 'Tiene facturas emitidas, que deben conservarse.';
        }

        if (!$esDemo) {
            $lecturas = DB::table('acceptances')->where(function ($q) use ($id, $userIds) {
                $q->where('empresa_id', $id);
                if ($userIds) $q->orWhereIn('user_id', $userIds);
            })->exists();
            $firmas = DB::table('physical_signatures')->where(function ($q) use ($userIds, $franqIds) {
                $q->whereRaw('1 = 0');
                if ($userIds)  $q->orWhereIn('user_id', $userIds);
                if ($franqIds) $q->orWhereIn('franquicia_id', $franqIds);
            })->exists();
            if ($lecturas || $firmas) {
                $motivos[] = 'Es una empresa real con lecturas o firmas registradas: son evidencia de cumplimiento y deben conservarse.';
            }
        }

        if ($userIds) {
            // Nunca deberia haber un super_admin con empresa, pero si lo
            // hubiera, borrarlo junto con la empresa seria gravisimo.
            if (DB::table('users')->whereIn('id', $userIds)->where('rol', 'super_admin')->exists()) {
                $motivos[] = 'Tiene un super_admin asociado.';
            }

            // Manuales creados por la empresa y asignados a otra.
            $compartidos = $manualIds
                ? DB::table('manual_empresa_assignments as mea')
                    ->join('manuals as m', 'm.id', '=', 'mea.manual_id')
                    ->whereIn('mea.manual_id', $manualIds)
                    ->where('mea.empresa_id', '!=', $id)
                    ->distinct()->limit(3)->pluck('m.titulo')->all()
                : [];
            if ($compartidos) {
                $motivos[] = 'Estos manuales los creó un usuario de la empresa y también están asignados a otra: «'
                           . implode('», «', $compartidos) . '». Hay que desasignarlos de las otras empresas o borrarlos antes.';
            }

            // Lecturas o firmas de OTRAS empresas sobre sus manuales.
            if ($versionIds) {
                $ajenas = DB::table('acceptances')->whereIn('manual_version_id', $versionIds)->whereNotIn('user_id', $userIds)->exists()
                       || DB::table('physical_signatures')->whereIn('manual_version_id', $versionIds)->whereNotIn('user_id', $userIds)->exists();
                if ($ajenas) {
                    $motivos[] = 'Usuarios de otra empresa tienen lecturas o firmas sobre manuales creados por esta.';
                }
            }

            // Autoria de usuarios de la empresa sobre contenido que NO se borra.
            $autoria = DB::table('manual_versions')->whereIn('publicado_por', $userIds)->when($manualIds, fn ($q) => $q->whereNotIn('manual_id', $manualIds))->exists()
                    || DB::table('manual_images')->whereIn('subido_por', $userIds)->when($manualIds, fn ($q) => $q->whereNotIn('manual_id', $manualIds))->exists()
                    || DB::table('documents')->whereIn('subido_por', $userIds)->when($docIds, fn ($q) => $q->whereNotIn('id', $docIds))->exists()
                    || DB::table('document_versions')->whereIn('subido_por', $userIds)->when($docVersionIds, fn ($q) => $q->whereNotIn('id', $docVersionIds))->exists()
                    || DB::table('physical_signatures')->whereIn('subido_por', $userIds)->whereNotIn('user_id', $userIds)
                         ->when($franqIds, fn ($q) => $q->whereNotIn('franquicia_id', $franqIds))->exists();
            if ($autoria) {
                $motivos[] = 'Usuarios de la empresa publicaron o subieron contenido que pertenece a otra empresa o a la plataforma.';
            }

            // Asignaciones hechas por sus usuarios en otras empresas.
            $asignoAfuera = false;
            foreach ([
                ['manual_category_assignments',   'assigned_by'],
                ['manual_user_assignments',       'assigned_by'],
                ['user_categories',               'assigned_by'],
                ['document_category_assignments', 'assigned_by'],
                ['document_user_assignments',     'assigned_by'],
                ['manual_empresa_assignments',    'asignado_por'],
            ] as [$tabla, $col]) {
                if (DB::table($tabla)->whereIn($col, $userIds)->where('empresa_id', '!=', $id)->exists()) {
                    $asignoAfuera = true;
                    break;
                }
            }
            if ($asignoAfuera) {
                $motivos[] = 'Usuarios de la empresa hicieron asignaciones en otra empresa.';
            }
        }

        // Usuarios de otra empresa en sus sucursales: al borrar la sucursal
        // perderian su perfil (franchise_staff cae en CASCADE).
        if ($franqIds) {
            $ajenos = DB::table('franchise_staff as fs')
                        ->join('users as u', 'u.id', '=', 'fs.user_id')
                        ->whereIn('fs.franquicia_id', $franqIds)
                        ->where(fn ($q) => $q->whereNull('u.empresa_id')->orWhere('u.empresa_id', '!=', $id))
                        ->exists();
            if ($ajenos) {
                $motivos[] = 'Hay usuarios de otra empresa asignados a sus sucursales.';
            }
        }

        return $motivos;
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
