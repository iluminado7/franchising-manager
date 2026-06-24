<?php

namespace App\Http\Controllers;

use HTMLPurifier;
use HTMLPurifier_Config;
use App\Models\Manual;
use App\Models\ManualVersion;
use App\Models\ManualEmpresaAssignment;
use App\Models\Notification;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ManualController extends Controller
{
    // GET /api/manuales
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Super admin puede ver TODOS los eliminados (incluyendo los borrados por él) con ?include_deleted=1
        $includeDeleted = (bool) $request->query('include_deleted', false);

        $manuales = match($user->rol) {

            // Super admin: por defecto ve los no-eliminados + los eliminados por franquiciantes
            // (para él, el borrado de un franquiciante no es definitivo).
            // Con ?include_deleted=1 ve también los eliminados por super_admin.
            'super_admin' => Manual::with(['versionActiva', 'empresasAsignadas'])
                       ->when(!$includeDeleted, fn($q) => $q->visiblesParaSuperAdmin())
                       ->orderBy('orden')
                       ->get()
                       ->map(function ($manual) {
                           $empresa = $manual->empresasAsignadas->first();
                           $manual->empresa_id = $empresa?->id;
                           $manual->empresa    = $empresa
                               ? ['id' => $empresa->id, 'nombre' => $empresa->nombre]
                               : null;
                           return $manual;
                       }),
            // Franquiciante ve los manuales asignados a su empresa
            'franquiciante' => Manual::with('versionActiva')
                         ->noEliminados()
                         ->whereHas('empresasAsignadas', fn($q) =>
                             $q->where('empresa_id', $user->empresa_id)
                         )
                         ->orderBy('orden')
                         ->get(),

            // Franquiciado: manuales publicados de su empresa con estado de aceptación
            'franquiciado'  => Manual::publicados()
                                     ->with('versionActiva')
                                     ->whereHas('empresasAsignadas', fn($q) =>
                                         $q->where('empresa_id', $user->empresa_id)
                                     )
                                     ->orderBy('orden')
                                     ->get()
                                     ->map(function ($manual) use ($user) {
                                         $version = $manual->versionActiva->first();
                                         $manual->mi_aceptacion = $version
                                             ? $version->acceptances()
                                                       ->where('user_id', $user->id)
                                                       ->exists()
                                             : false;
                                         return $manual;
                                     }),

            // Empleado: solo manuales asignados directamente
            'empleado' => Manual::publicados()
                                ->whereHas('empleadosAsignados', fn($q) =>
                                    $q->where('users.id', $user->id)
                                )
                                ->with('versionActiva')
                                ->orderBy('orden')
                                ->get()
                                ->map(function ($manual) use ($user) {
                                    $version = $manual->versionActiva->first();
                                    $manual->mi_aceptacion = $version
                                        ? $version->acceptances()
                                                  ->where('user_id', $user->id)
                                                  ->exists()
                                        : false;
                                    return $manual;
                                }),
        };

        return response()->json($manuales);
    }

    // GET /api/manuales/{id}
    public function show(Request $request, int $id): JsonResponse
    {
        $user   = $request->user();
        $manual = Manual::with(['versionActiva', 'versiones', 'creador'])->findOrFail($id);

        // Empleados solo ven manuales asignados
        if ($user->esEmpleado()) {
            $asignado = $manual->empleadosAsignados()
                               ->where('users.id', $user->id)
                               ->exists();
            if (!$asignado) {
                return response()->json(['error' => 'Sin acceso a este manual.'], 403);
            }
        }

        // Franquiciante/franquiciado: verificar que el manual está asignado a su empresa
        if ($user->esFranquiciante() || $user->esFranquiciado()) {
            $asignado = ManualEmpresaAssignment::where('manual_id', $id)
                                               ->where('empresa_id', $user->empresa_id)
                                               ->exists();
            if (!$asignado) {
                return response()->json(['error' => 'Sin acceso a este manual.'], 403);
            }
        }

        if (!$user->esSuperAdmin() && !$user->esFranquiciante()) {
            $version = $manual->versionActiva->first();
            $manual->mi_aceptacion = $version
                ? $version->acceptances()->where('user_id', $user->id)->exists()
                : false;
        }

        ActivityLog::registrar(
            userId:      $user->id,
            accion:      'manual_abierto',
            ip:          $request->ip(),
            empresaId:   $user->empresa_id,
            entidadTipo: 'manuals',
            entidadId:   $manual->id,
            detalle:     ['manual_titulo' => $manual->titulo],
            userAgent:   $request->userAgent()
        );

        return response()->json($manual);
    }

    // GET /api/manuales/{id}/versiones
    public function versiones(int $id): JsonResponse
    {
        $manual   = Manual::findOrFail($id);
        $versiones = ManualVersion::where('manual_id', $manual->id)
                                  ->with('publicadoPor.superAdmin')
                                  ->orderBy('version_number', 'desc')
                                  ->get();

        return response()->json($versiones);
    }

    // POST /api/manuales — solo super_admin
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'titulo'     => 'required|string|max:200',
            'categoria'  => 'nullable|string|max:100',
            'orden'      => 'nullable|integer',
            'empresa_id' => 'nullable|integer|exists:empresas,id',
        ]);

        $user      = $request->user();
        $empresaId = $user->esSuperAdmin()
            ? ($data['empresa_id'] ?? null)
            : $user->empresa_id;

        $manual = Manual::create([
            'titulo'     => $data['titulo'],
            'categoria'  => $data['categoria'] ?? null,
            'created_by' => $user->id,
            'estado'     => 'borrador',
            'orden'      => $data['orden'] ?? 0,
        ]);

        // Auto-asignar a la empresa correspondiente
        if ($empresaId) {
            ManualEmpresaAssignment::create([
                'manual_id'    => $manual->id,
                'empresa_id'   => $empresaId,
                'asignado_por' => $user->id,
                'asignado_at'  => now(),
            ]);
        }

        ActivityLog::registrar(
            userId:      $user->id,
            accion:      'manual_creado',
            ip:          $request->ip(),
            empresaId:   $empresaId,
            entidadTipo: 'manuals',
            entidadId:   $manual->id,
            detalle:     ['manual_titulo' => $manual->titulo],
            userAgent:   $request->userAgent()
        );

        return response()->json($manual, 201);
    }

    // PUT /api/manuales/{id} — solo super_admin
    public function update(Request $request, int $id): JsonResponse
    {
        $manual = Manual::findOrFail($id);
        $user   = $request->user();

            if ($user->esFranquiciante()) {
                $asignado = ManualEmpresaAssignment::where('manual_id', $id)
                                                ->where('empresa_id', $user->empresa_id)
                                                ->exists();
                if (!$asignado) {
                    return response()->json(['error' => 'Sin acceso a este manual.'], 403);
                }
            }
        $data = $request->validate([
            'titulo'    => 'sometimes|string|max:200',
            'categoria' => 'nullable|string|max:100',
            'orden'     => 'nullable|integer',
        ]);

        $manual->update($data);

        ActivityLog::registrar(
            userId:      $request->user()->id,
            accion:      'manual_editado',
            ip:          $request->ip(),
            entidadTipo: 'manuals',
            entidadId:   $manual->id,
            detalle:     ['manual_titulo' => $manual->titulo],
            userAgent:   $request->userAgent()
        );

        return response()->json($manual);
    }

    // POST /api/manuales/{id}/borrador — solo super_admin
    public function guardarBorrador(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'contenido_html' => 'required|string|max:5000000',
        ]);

        $manual = Manual::findOrFail($id);
        $html   = $this->sanitizarHtml($request->contenido_html);
        $user   = $request->user();

    // Franquiciante solo puede editar manuales de su empresa
        if ($user->esFranquiciante()) {
            $asignado = ManualEmpresaAssignment::where('manual_id', $id)
                                            ->where('empresa_id', $user->empresa_id)
                                            ->exists();
            if (!$asignado) {
                return response()->json(['error' => 'Sin acceso a este manual.'], 403);
            }
        }
        $borrador = ManualVersion::where('manual_id', $manual->id)
                                 ->where('es_activa', 0)
                                 ->where('version_number', 0)
                                 ->first();

        if ($borrador) {
            $borrador->update([
                'contenido_html' => $html,
                'contenido_hash' => hash('sha256', $html),
            ]);
        } else {
            ManualVersion::create([
                'manual_id'      => $manual->id,
                'version_number' => 0,
                'contenido_html' => $html,
                'contenido_hash' => hash('sha256', $html),
                'publicado_por'  => $request->user()->id,
                'publicado_at'   => now(),
                'es_activa'      => 0,
            ]);
        }

        return response()->json(['message' => 'Borrador guardado.']);
    }

    // POST /api/manuales/{id}/publicar — solo super_admin
    public function publicar(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'contenido_html'   => 'required|string|max:5000000',
            'nota_publicacion' => 'nullable|string|max:2000',
        ]);

        $manual = Manual::findOrFail($id);
        $user   = $request->user();
        $html   = $this->sanitizarHtml($request->contenido_html);
        $hash   = hash('sha256', $html);

        // Nota del publicador: si viene vacía o solo whitespace, guardamos NULL.
        $notaPub = trim($request->nota_publicacion ?? '');
        $notaPub = $notaPub === '' ? null : $notaPub;

        if ($user->esFranquiciante()) {
            $asignado = ManualEmpresaAssignment::where('manual_id', $id)
                                            ->where('empresa_id', $user->empresa_id)
                                            ->exists();
            if (!$asignado) {
                return response()->json(['error' => 'Sin acceso a este manual.'], 403);
            }
        }

        DB::transaction(function () use ($manual, $user, $html, $hash, $request, $notaPub) {

            ManualVersion::where('manual_id', $manual->id)
                         ->where('es_activa', 1)
                         ->update(['es_activa' => 0]);

            $ultimaVersion = ManualVersion::where('manual_id', $manual->id)
                                          ->max('version_number') ?? 0;

            $version = ManualVersion::create([
                'manual_id'        => $manual->id,
                'version_number'   => $ultimaVersion + 1,
                'contenido_html'   => $html,
                'contenido_hash'   => $hash,
                'publicado_por'    => $user->id,
                'publicado_at'     => now(),
                'es_activa'        => 1,
                'nota_publicacion' => $notaPub,
            ]);

            $manual->update(['estado' => 'publicado']);

            // Notificar a franquiciados de TODAS las empresas asignadas
            $tipo = $ultimaVersion === 0 ? 'nuevo_manual' : 'modificacion_manual';

            $empresasAsignadas = ManualEmpresaAssignment::where('manual_id', $manual->id)
                                                        ->pluck('empresa_id');

            $franquiciados = User::where('rol', 'franquiciado')
                                 ->where('activo', 1)
                                 ->whereIn('empresa_id', $empresasAsignadas)
                                 ->pluck('id');

            $notificaciones = $franquiciados->map(fn($uid) => [
                'user_id'           => $uid,
                'tipo'              => $tipo,
                'manual_id'         => $tipo === 'nuevo_manual' ? $manual->id : null,
                'manual_version_id' => $tipo === 'modificacion_manual' ? $version->id : null,
                'document_id'       => null,
                'titulo'            => $tipo === 'nuevo_manual'
                                        ? "Nuevo manual: {$manual->titulo}"
                                        : "Manual actualizado: {$manual->titulo} (v{$version->version_number})",
                'created_at'        => now(),
            ])->toArray();

            if (!empty($notificaciones)) {
                Notification::insert($notificaciones);
            }

            $esFranq = $user->esFranquiciante();

            // Nombre legible del autor, con fallback seguro
            $nombreAutor = trim(($user->franchiseStaff?->nombre ?? '') . ' ' . ($user->franchiseStaff?->apellido ?? ''));
            if ($nombreAutor === '') {
                $nombreAutor = $user->empresa?->nombre ?? ($user->email ?? 'Franquiciante');
            }

            // Si publica un franquiciante: avisar a los super_admin.
            // Usamos el tipo 'modificacion_manual' (con manual_version_id) porque la tabla
            // notifications tiene un CHECK (chk_notif_fk) que valida la combinación tipo/FK.
            if ($esFranq) {
                $superadmins = User::where('rol', 'super_admin')->where('activo', 1)->pluck('id');
                $notifSuper  = $superadmins->map(fn($uid) => [
                    'user_id'           => $uid,
                    'tipo'              => 'modificacion_manual',
                    'manual_id'         => null,
                    'manual_version_id' => $version->id,
                    'document_id'       => null,
                    'titulo'            => "El franquiciante {$nombreAutor} publicó una nueva versión de {$manual->titulo} (v{$version->version_number})",
                    'created_at'        => now(),
                ])->toArray();
                // Best-effort: si la notificación fallara, no debe tumbar la publicación.
                try {
                    if (!empty($notifSuper)) {
                        Notification::insert($notifSuper);
                    }
                } catch (\Throwable $e) {
                    // Se ignora a propósito: la versión ya se publicó.
                }
            }

            ActivityLog::registrar(
                userId:      $user->id,
                accion:      $esFranq ? 'version_publicada_franquiciante' : 'manual_publicado',
                ip:          $request->ip(),
                entidadTipo: 'manual_versions',
                entidadId:   $version->id,
                detalle:     [
                    'manual_titulo' => $manual->titulo,
                    'version'       => $version->version_number,
                ],
                userAgent:   $request->userAgent()
            );
        });

        return response()->json([
            'message' => 'Manual publicado correctamente.',
            'manual'  => $manual->fresh('versionActiva'),
        ]);
    }

    // PUT /api/manuales/{manualId}/versiones/{versionId}/nota-publicacion
    // Permite editar (o limpiar) la nota de publicación de una versión específica.
    // Solo super_admin y franquiciantes con acceso al manual.
    public function updateNotaPublicacion(Request $request, int $manualId, int $versionId): JsonResponse
    {
        $request->validate([
            'nota_publicacion' => 'nullable|string|max:2000',
        ]);

        $version = ManualVersion::where('manual_id', $manualId)->findOrFail($versionId);
        $manual  = $version->manual;
        $user    = $request->user();

        if (!$user->esSuperAdmin() && !$user->esFranquiciante()) {
            return response()->json(['error' => 'Sin permisos.'], 403);
        }

        if ($user->esFranquiciante()) {
            $asignado = ManualEmpresaAssignment::where('manual_id', $manualId)
                                            ->where('empresa_id', $user->empresa_id)
                                            ->exists();
            if (!$asignado) {
                return response()->json(['error' => 'Sin acceso a este manual.'], 403);
            }
        }

        // Normalizar: vacío o solo whitespace → NULL
        $nota = trim($request->nota_publicacion ?? '');
        $nota = $nota === '' ? null : $nota;

        $version->update(['nota_publicacion' => $nota]);

        try {
            ActivityLog::registrar(
                userId:      $user->id,
                accion:      'nota_publicacion_editada',
                ip:          $request->ip(),
                empresaId:   $user->empresa_id,
                entidadTipo: 'manual_versions',
                entidadId:   $version->id,
                detalle:     [
                    'manual_titulo' => $manual->titulo,
                    'version'       => $version->version_number,
                ],
                userAgent:   $request->userAgent()
            );
        } catch (\Throwable $e) { /* best-effort */ }

        return response()->json([
            'message' => 'Nota de publicación actualizada.',
            'version' => $version,
        ]);
    }

    // POST /api/manuales/{id}/archivar — solo super_admin
    public function archivar(Request $request, int $id): JsonResponse
    {
        $manual = Manual::findOrFail($id);
        $manual->update(['estado' => 'archivado']);
        $user   = $request->user();

        // Franquiciante solo puede archivar manuales de su empresa
        if ($user->esFranquiciante()) {
            $asignado = ManualEmpresaAssignment::where('manual_id', $id)
                                            ->where('empresa_id', $user->empresa_id)
                                            ->exists();
            if (!$asignado) {
                return response()->json(['error' => 'Sin acceso a este manual.'], 403);
            }
        }
        ActivityLog::registrar(
            userId:      $request->user()->id,
            accion:      'manual_archivado',
            ip:          $request->ip(),
            entidadTipo: 'manuals',
            entidadId:   $manual->id,
            detalle:     ['manual_titulo' => $manual->titulo],
            userAgent:   $request->userAgent()
        );

        return response()->json(['message' => 'Manual archivado correctamente.']);
    }
    
    // POST /api/manuales/{id}/desarchivar
    public function desarchivar(Request $request, int $id): JsonResponse
    {
        $manual = Manual::findOrFail($id);
        $user   = $request->user();

        if ($user->esFranquiciante()) {
            $asignado = ManualEmpresaAssignment::where('manual_id', $id)
                                            ->where('empresa_id', $user->empresa_id)
                                            ->exists();
            if (!$asignado) {
                return response()->json(['error' => 'Sin acceso a este manual.'], 403);
            }
        }

        $manual->update(['estado' => 'borrador']);

        ActivityLog::registrar(
            userId:      $user->id,
            accion:      'manual_editado',
            ip:          $request->ip(),
            entidadTipo: 'manuals',
            entidadId:   $manual->id,
            detalle:     ['campo' => 'estado', 'valor_nuevo' => 'borrador'],
            userAgent:   $request->userAgent()
        );

        return response()->json(['message' => 'Manual restaurado a borrador.']);
    }
    // ── HTMLPurifier ──────────────────────────────────────────────────
    private function sanitizarHtml(string $html): string
    {
        $config = HTMLPurifier_Config::createDefault();

        $config->set('HTML.Allowed',
            'h1,h2,h3,p,br,' .
            'strong,em,u,s,' .
            'ul,ol,li,' .
            'table[style],thead,tbody,tr,' .
            'th[style],td[style],' .
            'a[href|target],' .
            'div[style],span[style]'
        );

        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true]);
        $config->set('CSS.AllowedProperties',
            'text-align,font-weight,font-style,text-decoration,width,height'
        );
        $cacheDir = storage_path('app/htmlpurifier');
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }
        $config->set('Cache.SerializerPath', $cacheDir);

        return (new HTMLPurifier($config))->purify($html);
    }
    // DELETE /api/manuales/{id}
    public function destroy(Request $request, int $id): JsonResponse
    {
        $manual = Manual::findOrFail($id);
        $user   = $request->user();

        // Franquiciante solo puede eliminar manuales de su empresa
        if ($user->esFranquiciante()) {
            $asignado = ManualEmpresaAssignment::where('manual_id', $id)
                                            ->where('empresa_id', $user->empresa_id)
                                            ->exists();
            if (!$asignado) {
                return response()->json(['error' => 'Sin acceso a este manual.'], 403);
            }
        }

        $manual->update([
            'estado'     => 'eliminado',
            'deleted_by' => $user->id,
            'deleted_at' => now(),
        ]);

        // Si quien elimina es franquiciante: avisar a los super_admin.
        // Usamos el tipo 'modificacion_manual' (con manual_version_id) porque la tabla
        // notifications tiene un CHECK (chk_notif_fk) que valida la combinación tipo/FK.
        if ($user->esFranquiciante()) {
            // Nombre legible del franquiciante (mismo patrón que en publicar())
            $nombreAutor = trim(($user->franchiseStaff?->nombre ?? '') . ' ' . ($user->franchiseStaff?->apellido ?? ''));
            if ($nombreAutor === '') {
                $nombreAutor = $user->empresa?->nombre ?? ($user->email ?? 'Franquiciante');
            }

            // Tomamos la última versión activa o la última creada, si existe
            $versionId = $manual->versiones()->orderByDesc('id')->value('id');

            if ($versionId !== null) {
                $superadmins = User::where('rol', 'super_admin')->where('activo', 1)->pluck('id');
                $notifSuper  = $superadmins->map(fn($uid) => [
                    'user_id'           => $uid,
                    'tipo'              => 'modificacion_manual',
                    'manual_id'         => null,
                    'manual_version_id' => $versionId,
                    'document_id'       => null,
                    'titulo'            => "El franquiciante {$nombreAutor} eliminó el manual \"{$manual->titulo}\"",
                    'created_at'        => now(),
                ])->toArray();
                // Best-effort: si la notificación fallara, no debe tumbar la eliminación.
                try {
                    if (!empty($notifSuper)) {
                        Notification::insert($notifSuper);
                    }
                } catch (\Throwable $e) {
                    // Se ignora a propósito: el manual ya fue marcado como eliminado.
                }
            }
        }

        ActivityLog::registrar(
            userId:      $user->id,
            accion:      'manual_eliminado',
            ip:          $request->ip(),
            empresaId:   $user->empresa_id,
            entidadTipo: 'manuals',
            entidadId:   $manual->id,
            detalle:     ['manual_titulo' => $manual->titulo],
            userAgent:   $request->userAgent()
        );

        return response()->json(['message' => 'Manual eliminado correctamente.']);
    }

    // POST /api/manuales/{id}/restore
    public function restore(Request $request, int $id): JsonResponse
    {
        $manual = Manual::findOrFail($id);
        $user   = $request->user();

        // Franquiciante solo puede restaurar manuales de su empresa
        if ($user->esFranquiciante()) {
            $asignado = ManualEmpresaAssignment::where('manual_id', $id)
                                            ->where('empresa_id', $user->empresa_id)
                                            ->exists();
            if (!$asignado) {
                return response()->json(['error' => 'Sin acceso a este manual.'], 403);
            }
        }

        // Restaurar a borrador (estado seguro) y limpiar metadata de eliminación
        $manual->update([
            'estado'     => 'borrador',
            'deleted_by' => null,
            'deleted_at' => null,
        ]);

        ActivityLog::registrar(
            userId:      $user->id,
            accion:      'manual_restaurado',
            ip:          $request->ip(),
            empresaId:   $user->empresa_id,
            entidadTipo: 'manuals',
            entidadId:   $manual->id,
            detalle:     ['manual_titulo' => $manual->titulo],
            userAgent:   $request->userAgent()
        );

        return response()->json(['message' => 'Manual restaurado a borrador.']);
    }
}