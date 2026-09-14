<?php

namespace App\Services;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

/**
 * Cupo de almacenamiento de una empresa demo (Empresa::DEMO_CUPO_BYTES).
 *
 * Una demo se le da a alguien de afuera, con permisos de franquiciante: sin
 * cupo, puede subir PDFs de 50 MB sin límite, y el costo del almacenamiento
 * lo pagan ustedes.
 *
 * ── SE IMPUTA A QUIEN SUBE, NO AL DUEÑO DEL CONTENIDO ─────────────────────
 *
 * El uso se suma por el usuario que subió cada cosa (publicado_por /
 * subido_por) y no por quién creó el manual. Si se contara por creador, el
 * franquiciante de la demo podría subir versiones de un manual de la
 * plataforma asignado a su empresa, y no contarían nunca. Por lo mismo, lo que
 * sube un super_admin nunca cuenta contra una demo.
 *
 * ── QUÉ CUENTA ────────────────────────────────────────────────────────────
 *
 *   - manual_versions: PDF (archivo_tamano) + HTML (contenido, encabezado y
 *     pie). El HTML importa: una importación desde Word embebe las imágenes en
 *     base64 y cada versión publicada puede pesar varios MB.
 *   - manual_images, document_versions y physical_signatures.
 *   - Lo eliminado con soft-delete TAMBIÉN cuenta: la fila y el archivo siguen
 *     ahí. Eliminar un manual no libera espacio.
 *
 * Quedan afuera las fotos de perfil: una por usuario, se reemplaza borrando la
 * anterior, y los usuarios ya tienen tope.
 *
 * ── LÍMITE CONOCIDO ───────────────────────────────────────────────────────
 *
 * El chequeo va antes de guardar y sin bloqueo: dos subidas simultáneas pueden
 * pasar las dos y superar el cupo por, como mucho, el tamaño de una de ellas.
 * Bloquear la empresa mientras se sube un archivo de 50 MB frenaría a todos sus
 * usuarios; para un cupo de costo, ese margen es aceptable.
 */
class CupoDemo
{
    /** La empresa demo del usuario, o null si no aplica cupo. */
    public static function empresaDemoDe(?User $usuario): ?Empresa
    {
        if (!$usuario || $usuario->esSuperAdmin() || !$usuario->empresa_id) {
            return null;
        }

        $empresa = $usuario->empresa;

        return $empresa && $empresa->es_demo ? $empresa : null;
    }

    /** Bytes usados por los usuarios de la empresa (incluidos los eliminados). */
    public static function usoBytes(Empresa $empresa): int
    {
        $ids = DB::table('users')->where('empresa_id', $empresa->id)->pluck('id')->all();
        if (!$ids) {
            return 0;
        }

        $versiones = (int) DB::table('manual_versions')
            ->whereIn('publicado_por', $ids)
            ->selectRaw('COALESCE(SUM(
                  COALESCE(archivo_tamano, 0)
                + COALESCE(LENGTH(contenido_html), 0)
                + COALESCE(LENGTH(encabezado_html), 0)
                + COALESCE(LENGTH(pie_pagina_html), 0)
            ), 0) AS bytes')
            ->value('bytes');

        return $versiones
            + (int) DB::table('manual_images')->whereIn('subido_por', $ids)->sum('size')
            + (int) DB::table('document_versions')->whereIn('subido_por', $ids)->sum('tamano_bytes')
            + (int) DB::table('physical_signatures')->whereIn('subido_por', $ids)->sum('archivo_tamano');
    }

    /**
     * Corta la request con 422 si lo que se va a guardar no entra en el cupo.
     * Para usuarios que no son de una demo, no hace nada.
     *
     * @param int $bytesLiberados lo que se reemplaza y deja de contar (un
     *                            borrador que se pisa, una firma que se resube)
     */
    public static function exigirEspacio(?User $usuario, int $bytesNuevos, int $bytesLiberados = 0): void
    {
        $empresa = self::empresaDemoDe($usuario);
        if (!$empresa) {
            return;
        }

        $uso = self::usoBytes($empresa);

        if ($uso - $bytesLiberados + $bytesNuevos <= Empresa::DEMO_CUPO_BYTES) {
            return;
        }

        // Sin "liberá espacio": borrar es soft-delete y no libera nada. Decirlo
        // mandaría a borrar contenido para después seguir sin poder subir.
        $mensaje = sprintf(
            'No se puede guardar: la empresa está en período de prueba y su espacio de almacenamiento es de %s. '
            . 'Ya se usaron %s y esto ocupa %s.',
            self::mb(Empresa::DEMO_CUPO_BYTES),
            self::mb(max(0, $uso - $bytesLiberados)),
            self::mb($bytesNuevos)
        );

        // "error" y "message": las pantallas de subida leen uno u otro.
        throw new HttpResponseException(
            response()->json(['error' => $mensaje, 'message' => $mensaje], 422)
        );
    }

    /**
     * Variante para guardar el borrador de un manual HTML, que se pisa en lugar
     * de acumularse: lo que ya ocupaba el borrador se descuenta.
     *
     * Si el borrador lo había creado alguien de otra empresa (un super_admin),
     * esa fila no se le imputa a la demo, así que tampoco su reemplazo.
     */
    public static function exigirEspacioBorrador(?User $usuario, int $manualId, int $bytesNuevos): void
    {
        $empresa = self::empresaDemoDe($usuario);
        if (!$empresa) {
            return;
        }

        $borrador = DB::table('manual_versions')
            ->where('manual_id', $manualId)
            ->where('version_number', 0)
            ->where('es_activa', 0)
            ->selectRaw('publicado_por,
                  COALESCE(LENGTH(contenido_html), 0)
                + COALESCE(LENGTH(encabezado_html), 0)
                + COALESCE(LENGTH(pie_pagina_html), 0) AS bytes')
            ->first();

        if ($borrador) {
            $imputado = DB::table('users')
                ->where('id', $borrador->publicado_por)
                ->where('empresa_id', $empresa->id)
                ->exists();
            if (!$imputado) {
                return;
            }
            self::exigirEspacio($usuario, $bytesNuevos, (int) $borrador->bytes);
            return;
        }

        self::exigirEspacio($usuario, $bytesNuevos);
    }

    // KB por debajo de 1 MB: en MB con un decimal, una imagen de 20 KB se leía
    // "esto ocupa 0,0 MB" y el mensaje no se entendía.
    private static function mb(int $bytes): string
    {
        if ($bytes < 1048576) {
            return number_format(max(1, (int) ceil($bytes / 1024)), 0, ',', '.') . ' KB';
        }

        return number_format($bytes / 1048576, 1, ',', '.') . ' MB';
    }
}
