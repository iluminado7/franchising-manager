<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Foto de perfil del usuario autenticado.
 *
 * Reglas de seguridad:
 *   - Solo se opera sobre $request->user(): un usuario únicamente puede cambiar
 *     SU PROPIA foto. No hay {userId} en la ruta, así que es imposible tocar la
 *     de otro (no hacen falta chequeos de jerarquía).
 *   - foto_url NO está en el $fillable de User (fix H-015): se setea con setter
 *     directo tras procesar el archivo, nunca desde el request.
 *
 * Almacenamiento: mismo disco por defecto que DocumentController
 * (config('filesystems.default'), = s3 en producción). La imagen se recorta a un
 * cuadrado 512x512 y se reencodea a JPG con GD, de modo que lo que se sube pesa
 * ~30-80 KB aunque el original sea de varios MB. Clave: avatars/{userId}/{hash}.jpg
 *
 * La foto se guarda en el disco por defecto (privado, = local o s3 segun entorno)
 * y se sirve por un endpoint autenticado, igual que documentos e imagenes de
 * manuales. No se usa URL directa de S3: asi funciona en local y en Cloud sin
 * depender de que el bucket sea publico ni de AWS_URL.
 *
 * Endpoints:
 *   POST   /api/perfil/foto          → sube/reemplaza la foto propia
 *   DELETE /api/perfil/foto          → quita la foto propia
 *   GET    /api/perfil/foto/{userId} → sirve la foto (uno mismo, misma empresa, o super_admin)
 */
class ProfilePhotoController extends Controller
{
    private const LADO = 512;          // px del avatar final (cuadrado)
    private const CALIDAD_JPG = 82;    // 0-100

    // POST /api/perfil/foto
    public function subir(Request $request): JsonResponse
    {
        $user = $request->user();

        // V2-H-021: además del peso, hay que acotar las DIMENSIONES.
        // Antes solo se validaba MIME + 5 MB. Un PNG de 20000x20000 comprime a muy
        // poco y entraba holgado en el límite de 5 MB, pero procesarlo con GD
        // (imagecreatefrompng) reserva ancho * alto * 4 bytes ≈ 1,6 GB → agota la
        // memoria de PHP. DoS con un solo upload.
        //
        // Con el tope de 5000x5000 el peor caso de GD queda en 5000*5000*4 = 100 MB.
        // Si el memory_limit del server es menor a 256M, bajar estos valores.
        // La regla dimensions usa getimagesize(), que solo lee el header: es barata
        // y corre ANTES de que GD toque el archivo.
        $request->validate([
            // max en KB (5 MB). Aceptamos fotos grandes de celular y luego
            // las achicamos server-side.
            'foto' => 'required|file|image|max:5120|mimes:jpg,jpeg,png,webp'
                      . '|dimensions:min_width=50,min_height=50,max_width=5000,max_height=5000',
        ]);

        if (!function_exists('imagecreatetruecolor')) {
            return response()->json([
                'error' => 'El servidor no tiene la extensión de imágenes (GD) habilitada.',
            ], 500);
        }

        $archivo = $request->file('foto');
        $mime    = $archivo->getMimeType();

        // Procesar (recorte cuadrado + resize + reencode a JPG).
        $binario = $this->procesarCuadrado($archivo->getRealPath(), $mime);
        if ($binario === null) {
            return response()->json(['error' => 'No se pudo procesar la imagen.'], 422);
        }

        $disk = config('filesystems.default');
        $key  = "avatars/{$user->id}/" . substr(hash('sha256', $binario), 0, 16) . '.jpg';

        // El avatar se sirve SIEMPRE por el endpoint autenticado /api/perfil/foto/{id}.
        // NUNCA por URL directa, asi que no debe subirse con visibilidad 'public':
        // en S3 eso dejaria el objeto accesible sin auth, con clave adivinable
        // (avatars/{userId}/{hash16}.jpg). Misma clase de bug que v1 H-017.
        Storage::disk($disk)->put($key, $binario);

        // Borrar la foto anterior si existía y es distinta.
        $anterior = $user->foto_url;
        if ($anterior && $anterior !== $key) {
            try {
                if (Storage::disk($disk)->exists($anterior)) {
                    Storage::disk($disk)->delete($anterior);
                }
            } catch (\Throwable $e) { /* best-effort */ }
        }

        // Guardar la clave con setter directo (foto_url no está en $fillable).
        $user->foto_url = $key;
        $user->save();

        return response()->json([
            'message'    => 'Foto de perfil actualizada.',
            'avatar_url' => $user->fresh()->avatar_url,
        ]);
    }

    // GET /api/perfil/foto/{userId}
    // Sirve la foto por stream desde el disco privado.
    public function ver(Request $request, int $userId): Response
    {
        $actor   = $request->user();
        $usuario = User::find($userId);
        if (!$usuario || empty($usuario->foto_url)) {
            return response()->json(['error' => 'Sin foto.'], 404);
        }

        // V2-H-004: antes bastaba estar autenticado. Un usuario de la empresa A
        // podia ver el avatar de cualquier usuario de la empresa B enumerando IDs.
        // Ahora: uno mismo, alguien de la misma empresa, o super_admin.
        //
        // Se niega con 404 'Sin foto.' y NO con 403, a proposito:
        //   1. Es la misma respuesta que da hoy cuando el usuario no tiene foto,
        //      asi que el frontend ya la maneja (fallback a iniciales). Cero riesgo
        //      de dejar avatares rotos en un listado que no previmos.
        //   2. Impide distinguir "existe pero no es tuyo" de "no tiene foto", con lo
        //      cual la enumeracion no revela nada.
        //
        // Nota: super_admin tiene empresa_id NULL. Su avatar cae al fallback de
        // iniciales para los demas roles. Es cosmetico, no un error.
        $puedeVer = $actor->esSuperAdmin()
            || $actor->id === $usuario->id
            || ($actor->empresa_id !== null && $actor->empresa_id === $usuario->empresa_id);

        if (!$puedeVer) {
            return response()->json(['error' => 'Sin foto.'], 404);
        }

        // CACHE: la clave del archivo ya lleva un hash del contenido
        // (avatars/{id}/{sha256:16}.jpg), asi que cambia sola cuando cambia la
        // foto. Sirve de ETag sin releer ni rehashear nada.
        $etag = '"' . substr(hash('sha256', $usuario->foto_url), 0, 32) . '"';

        // Se responde el 304 ANTES de tocar Storage: con S3 eso evita leer el
        // objeto del bucket en cada revalidacion.
        if ($this->etagCoincide($request->headers->get('If-None-Match'), $etag)) {
            return response('', 304, [
                'ETag'          => $etag,
                'Cache-Control' => 'private, max-age=0, must-revalidate',
            ]);
        }

        $disk = config('filesystems.default');
        if (!Storage::disk($disk)->exists($usuario->foto_url)) {
            Log::warning('ProfilePhoto.ver: archivo faltante en disk', [
                'user_id'  => $usuario->id,
                'foto_url' => $usuario->foto_url,
            ]);
            return response()->json(['error' => 'Archivo no encontrado.'], 404);
        }

        $stream = Storage::disk($disk)->readStream($usuario->foto_url);
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
                'Content-Type'        => 'image/jpeg',
                'Content-Disposition' => 'inline; filename="avatar-' . $usuario->id . '.jpg"',
                // max-age=0 + must-revalidate: el navegador pregunta SIEMPRE.
                // Antes habia un max-age=3600 que dejaba la foto vieja hasta una
                // hora en los listados (la URL del avatar nunca cambia).
                'ETag'                => $etag,
                'Cache-Control'       => 'private, max-age=0, must-revalidate',
            ]
        );
    }

    // DELETE /api/perfil/foto
    public function quitar(Request $request): JsonResponse
    {
        $user = $request->user();
        $disk = config('filesystems.default');

        if ($user->foto_url) {
            try {
                if (Storage::disk($disk)->exists($user->foto_url)) {
                    Storage::disk($disk)->delete($user->foto_url);
                }
            } catch (\Throwable $e) { /* best-effort */ }
        }

        $user->foto_url = null;
        $user->save();

        return response()->json(['message' => 'Foto de perfil eliminada.']);
    }

    /**
     * Compara el If-None-Match del pedido contra el ETag actual.
     *
     * El header puede traer varios valores separados por coma y con el prefijo
     * W/ (weak). Se normaliza antes de comparar; un '*' matchea siempre.
     */
    private function etagCoincide(?string $ifNoneMatch, string $etag): bool
    {
        $ifNoneMatch = trim((string) $ifNoneMatch);
        if ($ifNoneMatch === '') {
            return false;
        }
        if ($ifNoneMatch === '*') {
            return true;
        }

        foreach (explode(',', $ifNoneMatch) as $candidato) {
            $candidato = trim($candidato);
            if (str_starts_with($candidato, 'W/')) {
                $candidato = substr($candidato, 2);
            }
            if ($candidato === $etag) {
                return true;
            }
        }

        return false;
    }

    /**
     * Recorta al cuadrado centrado, redimensiona a LADO x LADO y devuelve el
     * binario JPG. Las transparencias (PNG/WebP) se aplanan sobre blanco.
     * Devuelve null si GD no pudo decodificar el archivo.
     */
    private function procesarCuadrado(string $ruta, string $mime): ?string
    {
        $src = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($ruta),
            'image/png'  => @imagecreatefrompng($ruta),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($ruta) : false,
            default      => false,
        };
        if (!$src) {
            return null;
        }

        $w = imagesx($src);
        $h = imagesy($src);
        $lado = min($w, $h);
        $x = intval(($w - $lado) / 2);
        $y = intval(($h - $lado) / 2);

        $dst = imagecreatetruecolor(self::LADO, self::LADO);
        // Fondo blanco para aplanar posibles transparencias.
        $blanco = imagecolorallocate($dst, 255, 255, 255);
        imagefilledrectangle($dst, 0, 0, self::LADO, self::LADO, $blanco);

        imagecopyresampled(
            $dst, $src,
            0, 0, $x, $y,
            self::LADO, self::LADO, $lado, $lado
        );

        ob_start();
        imagejpeg($dst, null, self::CALIDAD_JPG);
        $binario = ob_get_clean();

        imagedestroy($src);
        imagedestroy($dst);

        return $binario !== false ? $binario : null;
    }
}