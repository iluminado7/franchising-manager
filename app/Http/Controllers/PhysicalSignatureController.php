<?php

namespace App\Http\Controllers;

use App\Models\PhysicalSignature;
use App\Models\ManualVersion;
use App\Models\Manual;
use App\Models\Franquicia;
use App\Models\User;
use App\Models\ActivityLog;
use App\Services\ManualAccessService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PhysicalSignatureController extends Controller
{
    // POST /api/versiones/{versionId}/firma-fisica
    //
    // Feature "Aceptaciones": solo super_admin y franquiciante suben firmas.
    // El campo user_id (obligatorio en el body) indica DE QUIÉN es la firma.
    public function subir(Request $request, int $versionId): JsonResponse
    {
        $actor   = $request->user();
        $version = ManualVersion::findOrFail($versionId);

        if (!ManualAccessService::usuarioTieneAccesoAlManual($actor, $version->manual_id)) {
            return response()->json(['error' => 'Sin acceso a este manual.'], 403);
        }

        $request->validate([
            'archivo' => 'required|file|mimes:pdf|max:10240',
            'user_id' => [
                'required', 'integer',
                Rule::exists('users', 'id')->where(function ($q) use ($actor) {
                    $q->where('rol', 'franquiciado')
                      ->where('activo', 1)
                      ->whereNull('deleted_at');
                    if ($actor->esFranquiciante()) {
                        $q->where('empresa_id', $actor->empresa_id);
                    }
                }),
            ],
            'notas'   => 'nullable|string|max:500',
        ]);

        $socio = User::findOrFail($request->user_id);

        if (!ManualAccessService::usuarioTieneAccesoAlManual($socio, $version->manual_id)) {
            return response()->json([
                'error' => 'Ese socio comercial no tiene acceso a este manual.',
            ], 422);
        }

        // La franquicia_id se deriva del socio (puede ser null si no tiene sucursal).
        $franquiciaId = optional($socio->franchiseStaff)->franquicia_id;

        $archivo = $request->file('archivo');
        $hash    = hash_file('sha256', $archivo->getRealPath());

        $path = Storage::disk('local')
                       ->putFile(
                           "firmas/{$versionId}/{$socio->id}",
                           $archivo
                       );

        $firma = PhysicalSignature::updateOrCreate(
            [
                'manual_version_id' => $version->id,
                'user_id'           => $socio->id,
            ],
            [
                'franquicia_id' => $franquiciaId,
                'subido_por'    => $actor->id,
                'archivo_path'  => $path,
                'archivo_hash'  => $hash,
                'notas'         => $request->notas,
                'updated_at'    => now(),
            ]
        );

        try {
            ActivityLog::registrar(
                userId:      $actor->id,
                accion:      'firma_fisica_subida',
                ip:          $request->ip(),
                empresaId:   $socio->empresa_id,
                entidadTipo: 'physical_signatures',
                entidadId:   $firma->id,
                detalle:     [
                    'manual_titulo' => $version->manual->titulo,
                    'version'       => $version->version_number,
                ],
                userAgent:   $request->userAgent()
            );
        } catch (\Throwable $e) { /* best-effort */ }

        return response()->json([
            'message' => 'Firma física cargada correctamente.',
            'firma'   => $firma->load(['user', 'franquicia', 'manualVersion.manual']),
        ], 201);
    }

    // GET /api/versiones/{versionId}/firmas-fisicas
    public function porVersion(Request $request, int $versionId): JsonResponse
    {
        $user    = $request->user();
        $version = ManualVersion::findOrFail($versionId);

        if ($user->esFranquiciante()) {
            if (!ManualAccessService::empresaTieneAccesoAlManual(
                $version->manual_id,
                $user->empresa_id
            )) {
                return response()->json(['error' => 'Sin acceso a este manual.'], 403);
            }
        }

        $query = PhysicalSignature::where('manual_version_id', $versionId)
                                  ->with(['user', 'franquicia', 'subidoPor']);

        if ($user->esFranquiciante()) {
            $query->whereHas('user', fn($q) => $q->where('empresa_id', $user->empresa_id));
        }

        return response()->json($query->get());
    }

    // GET /api/firmas-fisicas
    //
    // Devuelve una fila por (socio con acceso al manual, versión publicada),
    // con aceptación digital y firma física adjuntas si existen.
    public function index(Request $request): JsonResponse
    {
        $actor = $request->user();

        $empresaId = $actor->esSuperAdmin()
            ? ($request->query('empresa_id') ? (int) $request->query('empresa_id') : null)
            : $actor->empresa_id;

        if ($actor->esSuperAdmin() && !$empresaId) {
            return response()->json([
                'filas'   => [],
                'mensaje' => 'Seleccioná una empresa para ver aceptaciones.',
            ]);
        }

        if (!$empresaId) {
            return response()->json(['filas' => []]);
        }

        // Franquiciante no puede pedir otra empresa.
        if ($actor->esFranquiciante() && $request->query('empresa_id')
            && (int) $request->query('empresa_id') !== (int) $actor->empresa_id) {
            return response()->json(['error' => 'Sin acceso a esa empresa.'], 403);
        }

        $franquiciaId = $request->query('franquicia_id') ? (int) $request->query('franquicia_id') : null;
        $manualId     = $request->query('manual_id')     ? (int) $request->query('manual_id')     : null;
        $versionId    = $request->query('version_id')    ? (int) $request->query('version_id')    : null;
        $userId       = $request->query('user_id')       ? (int) $request->query('user_id')       : null;

        // Paso 1: versiones a considerar
        $versionesQuery = ManualVersion::query()
            ->join('manuals', 'manuals.id', '=', 'manual_versions.manual_id')
            ->join('manual_empresa_assignments as mea', 'mea.manual_id', '=', 'manuals.id')
            ->where('mea.empresa_id', $empresaId)
            ->where('manuals.estado', 'publicado')
            ->whereNull('manuals.deleted_at')
            ->select('manual_versions.*', 'manuals.titulo as manual_titulo');

        if ($manualId) {
            $versionesQuery->where('manual_versions.manual_id', $manualId);
        }
        if ($versionId) {
            $versionesQuery->where('manual_versions.id', $versionId);
        } else {
            $versionesQuery->where('manual_versions.es_activa', 1);
        }

        $versiones = $versionesQuery->get();

        if ($versiones->isEmpty()) {
            return response()->json(['filas' => []]);
        }

        // Paso 2: socios base
        $sociosQuery = User::query()
            ->where('rol', 'franquiciado')
            ->where('activo', 1)
            ->whereNull('deleted_at')
            ->where('empresa_id', $empresaId)
            ->with('franchiseStaff.franquicia');

        if ($userId) {
            $sociosQuery->where('id', $userId);
        }
        if ($franquiciaId) {
            $sociosQuery->whereHas('franchiseStaff', fn($q) =>
                $q->where('franquicia_id', $franquiciaId)
            );
        }

        $socios = $sociosQuery->get();

        if ($socios->isEmpty()) {
            return response()->json(['filas' => []]);
        }

        // Paso 3: precargar aceptaciones y firmas en bulk (evita N+1)
        $versionIds = $versiones->pluck('id')->unique()->values();
        $socioIds   = $socios->pluck('id')->unique()->values();

        $aceptaciones = DB::table('acceptances')
            ->whereIn('manual_version_id', $versionIds)
            ->whereIn('user_id', $socioIds)
            ->select('manual_version_id', 'user_id', 'aceptado_at', 'ip_address', 'id')
            ->get()
            ->keyBy(fn($r) => $r->manual_version_id . '_' . $r->user_id);

        $firmas = DB::table('physical_signatures')
            ->whereIn('manual_version_id', $versionIds)
            ->whereIn('user_id', $socioIds)
            ->select('id', 'manual_version_id', 'user_id', 'archivo_path', 'notas', 'created_at', 'updated_at', 'subido_por')
            ->get()
            ->keyBy(fn($r) => $r->manual_version_id . '_' . $r->user_id);

        // Paso 4: armar filas (una por socio con acceso × versión)
        $filas = [];

        foreach ($versiones as $version) {
            foreach ($socios as $socio) {
                if (!ManualAccessService::usuarioTieneAccesoAlManual($socio, $version->manual_id)) {
                    continue;
                }

                $key         = $version->id . '_' . $socio->id;
                $aceptacion  = $aceptaciones->get($key);
                $firma       = $firmas->get($key);
                $franquicia  = optional($socio->franchiseStaff)->franquicia;

                $filas[] = [
                    'socio' => [
                        'id'       => $socio->id,
                        'nombre'   => $socio->nombre,
                        'apellido' => $socio->apellido,
                        'email'    => $socio->email,
                    ],
                    'franquicia' => $franquicia ? [
                        'id'     => $franquicia->id,
                        'nombre' => $franquicia->nombre,
                    ] : null,
                    'manual' => [
                        'id'     => $version->manual_id,
                        'titulo' => $version->manual_titulo,
                    ],
                    'version' => [
                        'id'             => $version->id,
                        'version_number' => $version->version_number,
                        'es_activa'      => (bool) $version->es_activa,
                    ],
                    'aceptacion_digital' => $aceptacion ? [
                        'id'          => $aceptacion->id,
                        'aceptado_at' => $aceptacion->aceptado_at,
                        'ip_address'  => $aceptacion->ip_address,
                    ] : null,
                    'firma_fisica' => $firma ? [
                        'id'         => $firma->id,
                        'notas'      => $firma->notas,
                        'created_at' => $firma->created_at,
                        'updated_at' => $firma->updated_at,
                    ] : null,
                ];
            }
        }

        return response()->json(['filas' => $filas]);
    }

    // GET /api/firmas-fisicas/socios-para-manual?manual_id=X&empresa_id=Y
    //
    // Para poblar el dropdown "Socio comercial" del modal de subir firma.
    public function sociosParaManual(Request $request): JsonResponse
    {
        $request->validate([
            'manual_id'  => 'required|integer|exists:manuals,id',
            'empresa_id' => 'sometimes|integer|exists:empresas,id',
        ]);

        $actor    = $request->user();
        $manualId = (int) $request->query('manual_id');

        $empresaId = $actor->esSuperAdmin()
            ? ($request->query('empresa_id') ? (int) $request->query('empresa_id') : null)
            : $actor->empresa_id;

        if (!$empresaId) {
            return response()->json(['socios' => []]);
        }

        if ($actor->esFranquiciante()) {
            if (!ManualAccessService::empresaTieneAccesoAlManual($manualId, $empresaId)) {
                return response()->json(['error' => 'Sin acceso a este manual.'], 403);
            }
        }

        $socios = User::where('rol', 'franquiciado')
                      ->where('activo', 1)
                      ->whereNull('deleted_at')
                      ->where('empresa_id', $empresaId)
                      ->with('franchiseStaff.franquicia')
                      ->orderBy('apellido')
                      ->orderBy('nombre')
                      ->get()
                      ->filter(fn($u) =>
                          ManualAccessService::usuarioTieneAccesoAlManual($u, $manualId)
                      )
                      ->values();

        return response()->json([
            'socios' => $socios->map(fn($u) => [
                'id'         => $u->id,
                'nombre'     => $u->nombre,
                'apellido'   => $u->apellido,
                'email'      => $u->email,
                'franquicia' => optional(optional($u->franchiseStaff)->franquicia)->only(['id', 'nombre']),
            ]),
        ]);
    }

    // GET /api/firmas-fisicas/{id}/descargar
    public function descargar(Request $request, int $id): StreamedResponse|JsonResponse
    {
        $user    = $request->user();
        $firma   = PhysicalSignature::findOrFail($id);
        $version = ManualVersion::findOrFail($firma->manual_version_id);

        // Gate 1: acceso al manual
        if (!ManualAccessService::usuarioTieneAccesoAlManual($user, $version->manual_id)) {
            return response()->json(['error' => 'Sin acceso a este manual.'], 403);
        }

        // Gate 2: franquiciante — el socio debe ser de su empresa
        if ($user->esFranquiciante()) {
            $socio = User::find($firma->user_id);
            if (!$socio || (int) $socio->empresa_id !== (int) $user->empresa_id) {
                return response()->json(['error' => 'Sin acceso a esta firma.'], 403);
            }
        }

        // Gate 3: franquiciado (legacy) — solo su propia firma
        if ($user->esFranquiciado()) {
            if ((int) $firma->user_id !== (int) $user->id) {
                return response()->json(['error' => 'Sin acceso a esta firma.'], 403);
            }
        }

        // Gate 4: empleado bloqueado
        if ($user->esEmpleado()) {
            return response()->json(['error' => 'Sin permisos.'], 403);
        }

        if (!$firma->archivo_path || !Storage::disk('local')->exists($firma->archivo_path)) {
            Log::warning('PhysicalSignature.descargar: archivo faltante en disk', [
                'firma_id'     => $firma->id,
                'archivo_path' => $firma->archivo_path,
            ]);
            return response()->json(['error' => 'Archivo no encontrado.'], 404);
        }

        try {
            ActivityLog::registrar(
                userId:      $user->id,
                accion:      'firma_fisica_descargada',
                ip:          $request->ip(),
                empresaId:   $user->empresa_id,
                entidadTipo: 'physical_signatures',
                entidadId:   $firma->id,
                userAgent:   $request->userAgent()
            );
        } catch (\Throwable $e) { /* best-effort */ }

        $stream = Storage::disk('local')->readStream($firma->archivo_path);
        if ($stream === null || $stream === false) {
            return response()->json(['error' => 'Error al abrir el archivo.'], 500);
        }

        $nombreDescarga = "firma-{$firma->id}.pdf";

        return response()->stream(
            function () use ($stream) {
                fpassthru($stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }
            },
            200,
            [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => "inline; filename=\"{$nombreDescarga}\"",
                'Cache-Control'       => 'private, no-store, max-age=0',
            ]
        );
    }
}