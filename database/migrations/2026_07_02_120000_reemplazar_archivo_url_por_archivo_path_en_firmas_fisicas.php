<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * H-017 fix — reemplaza archivo_url (URL pública) por archivo_path (path interno).
 *
 * Antes, PhysicalSignature.archivo_url guardaba Storage::url($path), que es una
 * URL directamente accesible por HTTP sin autenticación (ej: /storage/firmas/...).
 * Esto exponía firmas legales a cualquiera que conociera la URL, y al migrar a
 * S3 (roadmap) quedarían universalmente accesibles.
 *
 * Ahora se guarda solo el path relativo del disk, y los archivos se sirven vía
 * un endpoint autenticado (GET /firmas-fisicas/{id}/descargar) que valida
 * empresa y acceso al manual antes de responder.
 *
 * Notas sobre esta migración:
 *   - La tabla está vacía al momento del deploy (verificado en local y prod),
 *     por lo que el backfill es defensivo pero no debería procesar ninguna fila.
 *   - Si en el futuro se re-ejecuta con datos existentes, el backfill extrae el
 *     path del segmento posterior a "/storage/" en la URL. Funciona con URLs
 *     tanto locales como de S3 (que también incluyen "/storage/" en el path).
 *   - down() es reversible: regenera archivo_url desde archivo_path.
 */
return new class extends Migration {
    public function up(): void
    {
        // Paso 1 — agregar la columna nueva (nullable para permitir backfill sin errores)
        Schema::table('physical_signatures', function (Blueprint $table) {
            $table->string('archivo_path', 500)->nullable()->after('subido_por');
        });

        // Paso 2 — backfill defensivo (a la fecha la tabla está vacía, es no-op)
        DB::table('physical_signatures')
            ->whereNotNull('archivo_url')
            ->where('archivo_url', '!=', '')
            ->orderBy('id')
            ->chunkById(100, function ($rows) {
                foreach ($rows as $row) {
                    // Extrae el path relativo eliminando el segmento "/storage/"
                    // Ejemplo: "/storage/firmas/45/12/abc.pdf" -> "firmas/45/12/abc.pdf"
                    $path = ltrim(str_replace('/storage/', '', $row->archivo_url), '/');
                    DB::table('physical_signatures')
                        ->where('id', $row->id)
                        ->update(['archivo_path' => $path]);
                }
            });

        // Paso 3 — eliminar la columna vieja
        Schema::table('physical_signatures', function (Blueprint $table) {
            $table->dropColumn('archivo_url');
        });
    }

    public function down(): void
    {
        // Paso 1 — recrear la columna vieja
        Schema::table('physical_signatures', function (Blueprint $table) {
            $table->string('archivo_url', 1000)->nullable()->after('subido_por');
        });

        // Paso 2 — regenerar URLs desde paths (para rollback funcional)
        DB::table('physical_signatures')
            ->whereNotNull('archivo_path')
            ->where('archivo_path', '!=', '')
            ->orderBy('id')
            ->chunkById(100, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('physical_signatures')
                        ->where('id', $row->id)
                        ->update(['archivo_url' => '/storage/' . $row->archivo_path]);
                }
            });

        // Paso 3 — eliminar la columna nueva
        Schema::table('physical_signatures', function (Blueprint $table) {
            $table->dropColumn('archivo_path');
        });
    }
};
