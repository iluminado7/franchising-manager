<?php

namespace App\Http\Controllers;

use App\Models\ManualImage;
use App\Models\Manual;
use App\Models\ManualVersion;
use App\Models\ActivityLog;
use App\Services\ManualAccessService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Feature: imágenes en manuales.
 *
 * Endpoints:
 *   POST /api/manuales/{manualId}/imagenes      → upload de imagen (super_admin, franquiciante)
 *   GET  /api/manuales-imagenes/{id}/descargar  → sirve la imagen (todos los roles con acceso al manual)
 *
 * Storage: disk 'local' (privado, no accesible via URL directa).
 * Deduplicación: SHA-256 dentro del mismo manual. Si el mismo archivo se sube
 * dos veces al mismo manual, devuelve la fila existente en vez de crear duplicado.
 *
 * Limpieza de huérfanas: se llama desde ManualController::guardarBorrador y
 * publicar() con el método estático limpiarHuerfanas().
 */
class ManualImageController extends Controller
{
    // MIME types aceptados. Restringido a formatos web estándar.
    private const MIMES_PERMITIDOS = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
        'image/svg+xml' => 'svg',
    ];

    // POST /api/manuales/{manualId}/imagenes
    public function upload(Request $request, int $manualId): JsonResponse
    {
        $actor = $request->user();

        // Gate por rol: solo super_admin y franquiciante suben imágenes.
        // Franquiciado y empleado no editan manuales, no necesitan.
        if (!$actor->esSuperAdmin() && !$actor->esFranquiciante()) {
            return response()->json(['error' => 'Sin permisos.'], 403);
        }

        $manual = Manual::findOrFail($manualId);

        // Gate por acceso: mismo servicio que usan los demás endpoints.
        // Super_admin: siempre. Franquiciante: solo si el manual está asignado a su empresa.
        if (!ManualAccessService::usuarioTieneAccesoAlManual($actor, $manual->id)) {
            return response()->json(['error' => 'Sin acceso a este manual.'], 403);
        }

        $request->validate([
            'archivo' => 'required|file|image|max:5120|mimes:jpg,jpeg,png,gif,webp,svg',
        ]);

        $archivo = $request->file('archivo');
        $mime    = $archivo->getMimeType();

        if (!isset(self::MIMES_PERMITIDOS[$mime])) {
            return response()->json(['error' => 'Formato de imagen no soportado.'], 422);
        }

        $hash = hash_file('sha256', $archivo->getRealPath());
        $size = $archivo->getSize();

        // Deduplicación: si ya existe la misma imagen para este manual,
        // devolvemos la existente sin subir de nuevo.
        $existente = ManualImage::where('manual_id', $manual->id)
                                ->where('archivo_hash', $hash)
                                ->first();

        if ($existente) {
            return response()->json([
                'id'  => $existente->id,
                'url' => self::urlDescarga($existente->id),
                'deduplicado' => true,
            ]);
        }

        // Nombre del archivo en storage: {hash}.{ext}. Predecible para debug
        // pero no expone info del usuario. Path incluye manual_id para
        // facilitar la limpieza al eliminar el manual.
        $ext  = self::MIMES_PERMITIDOS[$mime];
        $path = "manuales/imagenes/{$manual->id}/{$hash}.{$ext}";

        // Storage en disk 'local' (privado). Usamos putFileAs para controlar
        // el nombre exacto y evitar random UUIDs de Laravel.
        Storage::disk('local')->putFileAs(
            "manuales/imagenes/{$manual->id}",
            $archivo,
            "{$hash}.{$ext}"
        );

        $imagen = ManualImage::create([
            'manual_id'    => $manual->id,
            'archivo_path' => $path,
            'archivo_hash' => $hash,
            'mime'         => $mime,
            'size'         => $size,
            'subido_por'   => $actor->id,
        ]);

        try {
            ActivityLog::registrar(
                userId:      $actor->id,
                accion:      'manual_imagen_subida',
                ip:          $request->ip(),
                empresaId:   $actor->empresa_id,
                entidadTipo: 'manual_images',
                entidadId:   $imagen->id,
                userAgent:   $request->userAgent()
            );
        } catch (\Throwable $e) { /* best-effort */ }

        return response()->json([
            'id'  => $imagen->id,
            'url' => self::urlDescarga($imagen->id),
            'deduplicado' => false,
        ], 201);
    }

    // GET /api/manuales-imagenes/{id}/descargar
    //
    // Sirve la imagen inline. Cualquier usuario con acceso al manual puede verla.
    // Sin gate estricto por rol: si el usuario ve el manual, ve sus imágenes.
    public function descargar(Request $request, int $id): StreamedResponse|JsonResponse
    {
        $user   = $request->user();
        $imagen = ManualImage::findOrFail($id);

        if (!ManualAccessService::usuarioTieneAccesoAlManual($user, $imagen->manual_id)) {
            return response()->json(['error' => 'Sin acceso.'], 403);
        }

        if (!Storage::disk('local')->exists($imagen->archivo_path)) {
            Log::warning('ManualImage.descargar: archivo faltante en disk', [
                'imagen_id'    => $imagen->id,
                'archivo_path' => $imagen->archivo_path,
            ]);
            return response()->json(['error' => 'Archivo no encontrado.'], 404);
        }

        $stream = Storage::disk('local')->readStream($imagen->archivo_path);
        if (!$stream) {
            return response()->json(['error' => 'Error al abrir el archivo.'], 500);
        }

        return response()->stream(
            function () use ($stream) {
                fpassthru($stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }
            },
            200,
            [
                'Content-Type'        => $imagen->mime,
                'Content-Length'      => $imagen->size,
                'Content-Disposition' => 'inline; filename="imagen-' . $imagen->id . '"',
                // Cache moderado: las imágenes de manuales no cambian, pero
                // deben re-validarse por si el manual pierde el acceso.
                'Cache-Control'       => 'private, max-age=3600',
            ]
        );
    }

    /**
     * Genera la URL relativa de descarga. Usado por el frontend para
     * insertar en el HTML del contenido.
     */
    public static function urlDescarga(int $imagenId): string
    {
        return "/api/manuales-imagenes/{$imagenId}/descargar";
    }

    /**
     * Limpia imágenes huérfanas del manual: aquellas registradas en
     * manual_images pero que ya NO están referenciadas en el HTML de ninguna
     * versión (activa, borrador o pasadas) ni en el header/footer del manual.
     *
     * Se llama desde ManualController::guardarBorrador y publicar() después
     * de guardar los cambios.
     */
    public static function limpiarHuerfanas(int $manualId, ?string $htmlExtra = null): int
    {
        $manual = Manual::find($manualId);
        if (!$manual) return 0;

        // Recolectar todo el HTML referenciable del manual:
        //   - encabezado_html y pie_pagina_html (del propio manual)
        //   - contenido_html de TODAS las versiones (incluyendo borrador y publicadas)
        $htmls = [];
        $htmls[] = $manual->encabezado_html ?? '';
        $htmls[] = $manual->pie_pagina_html ?? '';

        $versiones = ManualVersion::where('manual_id', $manualId)
                                  ->pluck('contenido_html');
        foreach ($versiones as $html) {
            $htmls[] = $html ?? '';
        }

        // HTML que está por publicarse/guardarse pero AÚN NO es una versión en la
        // base. Sin esto, sus imágenes recién subidas se verían como huérfanas y se
        // borrarían justo antes de crear la versión que las referencia -> candado.
        if ($htmlExtra !== null) {
            $htmls[] = $htmlExtra;
        }

        $htmlCompleto = implode(' ', $htmls);

        // Detectar IDs de imágenes referenciadas via regex.
        // URLs esperadas: /api/manuales-imagenes/{id}/descargar
        preg_match_all('#/api/manuales-imagenes/(\d+)/descargar#', $htmlCompleto, $matches);
        $referenciados = array_map('intval', $matches[1] ?? []);
        $referenciados = array_unique($referenciados);

        // Buscar imágenes de este manual que NO están en la lista de referenciadas.
        $huerfanas = ManualImage::where('manual_id', $manualId)
                                ->when(!empty($referenciados), fn($q) =>
                                    $q->whereNotIn('id', $referenciados)
                                )
                                ->get();

        $eliminadas = 0;
        foreach ($huerfanas as $img) {
            try {
                if (Storage::disk('local')->exists($img->archivo_path)) {
                    Storage::disk('local')->delete($img->archivo_path);
                }
                $img->delete();
                $eliminadas++;
            } catch (\Throwable $e) {
                // best-effort: si una falla, seguimos con las demás.
                Log::warning('ManualImage.limpiarHuerfanas: error al eliminar', [
                    'imagen_id' => $img->id,
                    'error'     => $e->getMessage(),
                ]);
            }
        }

        return $eliminadas;
    }
}