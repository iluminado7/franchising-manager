<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Notification;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    // GET /api/documentos
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Super admin puede ver TODOS los eliminados (incluso los borrados por él) con ?include_deleted=1
        $includeDeleted = (bool) $request->query('include_deleted', false);

        if ($user->esFranquiciante()) {
            $documentos = Document::with(['franquicia', 'empresa'])
                                  ->where('empresa_id', $user->empresa_id)
                                  ->noEliminados()
                                  ->orderBy('created_at', 'desc')
                                  ->get();

        } elseif ($user->esSuperAdmin()) {
            $query = Document::with(['franquicia', 'empresa', 'deletedBy:id,rol'])->orderBy('created_at', 'desc');
            if ($request->filled('empresa_id')) {
                $query->where('empresa_id', $request->empresa_id);
            }
            // Por defecto: no-eliminados + los eliminados por franquiciantes.
            // Con ?include_deleted=1: también los eliminados por super_admin.
            if (!$includeDeleted) {
                $query->visiblesParaSuperAdmin();
            }
            $documentos = $query->get();

        } else {
            $franquiciaId = $user->franchiseStaff->franquicia_id;
            $empresaId    = $user->empresa_id;

            $documentos = Document::with(['franquicia', 'empresa'])
                                  ->where('empresa_id', $empresaId)
                                  ->where(function ($q) use ($franquiciaId) {
                                      $q->whereNull('franquicia_id')
                                        ->orWhere('franquicia_id', $franquiciaId);
                                  })
                                  ->visiblesParaFranquiciado()
                                  ->noEliminados()
                                  ->orderBy('created_at', 'desc')
                                  ->get();
        }

        return response()->json($documentos);
    }

    // POST /api/documentos
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'archivo'              => 'required|file|mimes:pdf,doc,docx|max:20480',
            'titulo'               => 'required|string|max:200',
            'tipo'                 => 'required|in:contrato,politica,protocolo,circular,anexo,acta,otro',
            'franquicia_id'        => 'nullable|integer|exists:franquicias,id',
            'visible_franquiciado' => 'nullable|boolean',
            'empresa_id'           => 'nullable|integer|exists:empresas,id',
        ]);

        $user    = $request->user();
        $archivo = $request->file('archivo');
        $hash    = hash_file('sha256', $archivo->getRealPath());

        $empresaId = $user->esSuperAdmin()
            ? ($request->empresa_id ?? null)
            : $user->empresa_id;

        // Disco segun entorno: 'local' en desarrollo, 's3' en produccion (definido en .env)
        $disk = config('filesystems.default');

        // Guardar en el disco activo y persistir la ruta relativa (no URL publica)
        $path = Storage::disk($disk)->putFile('documentos', $archivo);

        $documento = Document::create([
            'empresa_id'           => $empresaId,
            'titulo'               => $request->titulo,
            'tipo'                 => $request->tipo,
            'subido_por'           => $user->id,
            'franquicia_id'        => $request->franquicia_id,
            'archivo_url'          => $path, // ruta relativa, no URL publica
            'archivo_hash'         => $hash,
            'mime_type'            => $archivo->getMimeType(),
            'tamano_bytes'         => $archivo->getSize(),
            'visible_franquiciado' => $request->visible_franquiciado ?? true,
        ]);

        $this->notificarNuevoDocumento($documento);

        ActivityLog::registrar(
            userId:      $user->id,
            accion:      'documento_subido',
            ip:          $request->ip(),
            empresaId:   $empresaId,
            entidadTipo: 'documents',
            entidadId:   $documento->id,
            userAgent:   $request->userAgent()
        );

        return response()->json($documento->load(['franquicia', 'empresa']), 201);
    }

    // GET /api/documentos/{id}/descargar
    public function descargar(Request $request, int $id): StreamedResponse
    {
        $documento = Document::findOrFail($id);
        $user      = $request->user();

        // Si el documento está eliminado, solo super_admin puede acceder
        if ($documento->deleted_at !== null && !$user->esSuperAdmin()) {
            abort(403, 'Sin acceso a este documento.');
        }

        // Verificar acceso segun rol
        if ($user->esFranquiciante() || $user->esFranquiciado() || $user->esEmpleado()) {
            if ($documento->empresa_id !== $user->empresa_id) {
                abort(403, 'Sin acceso a este documento.');
            }
            if (!$user->esFranquiciante() && !$documento->visible_franquiciado) {
                abort(403, 'Sin acceso a este documento.');
            }
        }

        // Disco segun entorno: mismo flujo en local y en S3
        $disk = config('filesystems.default');

        if (!Storage::disk($disk)->exists($documento->archivo_url)) {
            abort(404, 'Archivo no encontrado.');
        }

        $extension      = pathinfo($documento->archivo_url, PATHINFO_EXTENSION);
        $nombreDescarga = $documento->titulo . '.' . $extension;

        // streamDownload funciona identico en disco local y en S3.
        // Usamos get()/exists() (disponibles en este setup), no download()/response().
        return response()->streamDownload(function () use ($disk, $documento) {
            echo Storage::disk($disk)->get($documento->archivo_url);
        }, $nombreDescarga, [
            'Content-Type'   => $documento->mime_type,
            'Content-Length' => $documento->tamano_bytes,
        ]);
    }

    // GET /api/documentos/{id}/preview
    // Igual que descargar, pero inline: el navegador lo muestra (PDF/imagen) en vez de bajarlo.
    // Sigue detrás de auth:sanctum -> nadie sin sesión puede verlo por la URL.
    public function preview(Request $request, int $id): StreamedResponse
    {
        $documento = Document::findOrFail($id);
        $user      = $request->user();

        // Si el documento está eliminado, solo super_admin puede acceder
        if ($documento->deleted_at !== null && !$user->esSuperAdmin()) {
            abort(403, 'Sin acceso a este documento.');
        }

        // Mismo control de acceso que descargar
        if ($user->esFranquiciante() || $user->esFranquiciado() || $user->esEmpleado()) {
            if ($documento->empresa_id !== $user->empresa_id) {
                abort(403, 'Sin acceso a este documento.');
            }
            if (!$user->esFranquiciante() && !$documento->visible_franquiciado) {
                abort(403, 'Sin acceso a este documento.');
            }
        }

        $disk = config('filesystems.default');

        if (!Storage::disk($disk)->exists($documento->archivo_url)) {
            abort(404, 'Archivo no encontrado.');
        }

        $extension = pathinfo($documento->archivo_url, PATHINFO_EXTENSION);
        $nombre    = $documento->titulo . '.' . $extension;

        // El 4to argumento 'inline' fija Content-Disposition: inline.
        return response()->streamDownload(function () use ($disk, $documento) {
            echo Storage::disk($disk)->get($documento->archivo_url);
        }, $nombre, [
            'Content-Type'   => $documento->mime_type,
            'Content-Length' => $documento->tamano_bytes,
        ], 'inline');
    }

    // DELETE /api/documentos/{id}
    public function destroy(Request $request, int $id): JsonResponse
    {
        $documento = Document::findOrFail($id);
        $user      = $request->user();

        // Franquiciante solo puede eliminar documentos de su empresa
        if ($user->esFranquiciante()) {
            if ($documento->empresa_id !== $user->empresa_id) {
                return response()->json(['error' => 'Sin acceso a este documento.'], 403);
            }
        }

        $documento->update([
            'deleted_by' => $user->id,
            'deleted_at' => now(),
        ]);

        // Si quien elimina es franquiciante: avisar a los super_admin.
        // Reusamos el tipo 'nuevo_documento' (con document_id) — la constraint chk_notif_fk lo permite.
        if ($user->esFranquiciante()) {
            $nombreAutor = trim(($user->franchiseStaff?->nombre ?? '') . ' ' . ($user->franchiseStaff?->apellido ?? ''));
            if ($nombreAutor === '') {
                $nombreAutor = $user->empresa?->nombre ?? ($user->email ?? 'Franquiciante');
            }

            $superadmins = User::where('rol', 'super_admin')->where('activo', 1)->pluck('id');
            $notifSuper  = $superadmins->map(fn($uid) => [
                'user_id'           => $uid,
                'tipo'              => 'nuevo_documento',
                'manual_id'         => null,
                'manual_version_id' => null,
                'document_id'       => $documento->id,
                'titulo'            => "El franquiciante {$nombreAutor} eliminó el documento \"{$documento->titulo}\"",
                'created_at'        => now(),
            ])->toArray();

            // Best-effort: si la notificación fallara, no debe tumbar la eliminación.
            try {
                if (!empty($notifSuper)) {
                    Notification::insert($notifSuper);
                }
            } catch (\Throwable $e) {
                // Se ignora a propósito: el documento ya fue marcado como eliminado.
            }
        }

        ActivityLog::registrar(
            userId:      $user->id,
            accion:      'documento_eliminado',
            ip:          $request->ip(),
            empresaId:   $user->empresa_id,
            entidadTipo: 'documents',
            entidadId:   $documento->id,
            userAgent:   $request->userAgent()
        );

        return response()->json(['message' => 'Documento eliminado correctamente.']);
    }

    // POST /api/documentos/{id}/restore
    public function restore(Request $request, int $id): JsonResponse
    {
        $documento = Document::findOrFail($id);
        $user      = $request->user();

        // Franquiciante solo puede restaurar documentos de su empresa
        if ($user->esFranquiciante()) {
            if ($documento->empresa_id !== $user->empresa_id) {
                return response()->json(['error' => 'Sin acceso a este documento.'], 403);
            }
        }

        $documento->update([
            'deleted_by' => null,
            'deleted_at' => null,
        ]);

        ActivityLog::registrar(
            userId:      $user->id,
            accion:      'documento_restaurado',
            ip:          $request->ip(),
            empresaId:   $user->empresa_id,
            entidadTipo: 'documents',
            entidadId:   $documento->id,
            userAgent:   $request->userAgent()
        );

        return response()->json(['message' => 'Documento restaurado correctamente.']);
    }

    private function notificarNuevoDocumento(Document $documento): void
    {
        if (!$documento->visible_franquiciado) return;

        $query = User::where('rol', 'franquiciado')
                     ->where('activo', 1)
                     ->where('empresa_id', $documento->empresa_id);

        if ($documento->franquicia_id) {
            $query->whereHas('franchiseStaff', fn($q) =>
                $q->where('franquicia_id', $documento->franquicia_id)
            );
        }

        $ids = $query->pluck('id');

        $notificaciones = $ids->map(fn($uid) => [
            'user_id'           => $uid,
            'tipo'              => 'nuevo_documento',
            'manual_id'         => null,
            'manual_version_id' => null,
            'document_id'       => $documento->id,
            'titulo'            => "Nuevo documento disponible: {$documento->titulo}",
            'created_at'        => now(),
        ])->toArray();

        if (!empty($notificaciones)) {
            Notification::insert($notificaciones);
        }
    }
}