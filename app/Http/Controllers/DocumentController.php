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

        if ($user->esFranquiciante()) {
            $documentos = Document::with(['franquicia', 'empresa'])
                                  ->where('empresa_id', $user->empresa_id)
                                  ->orderBy('created_at', 'desc')
                                  ->get();

        } elseif ($user->esSuperAdmin()) {
            $query = Document::with(['franquicia', 'empresa'])->orderBy('created_at', 'desc');
            if ($request->filled('empresa_id')) {
                $query->where('empresa_id', $request->empresa_id);
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
            'tipo'                 => 'required|in:contrato,anexo,acta,otro',
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