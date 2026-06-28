<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * v2.3 — Paso 14/19 (versión 3: VIRTUAL en lugar de STORED)
 *
 * MySQL 8 rechaza columnas generadas STORED cuando la columna base tiene
 * una FK con ON DELETE/UPDATE CASCADE. document_id en document_versions
 * tiene FK con ON DELETE CASCADE hacia documents, lo que dispara el error
 * 1215 (engañoso, habla de "Cannot add foreign key constraint" cuando en
 * realidad la limitación es sobre la columna generada).
 *
 * Solución: usar VIRTUAL. Las columnas VIRTUAL no se almacenan en disco
 * (se computan al vuelo), por lo que MySQL no impone esa restricción.
 * Pero SÍ se pueden indexar en InnoDB — el UNIQUE crea un índice
 * secundario que se mantiene automáticamente. Para forzar unicidad,
 * VIRTUAL es funcionalmente equivalente a STORED.
 */
return new class extends Migration
{
    public function up(): void
    {
        $dupes = DB::select("
            SELECT document_id, COUNT(*) AS activas
            FROM document_versions
            WHERE es_activa = 1
            GROUP BY document_id
            HAVING COUNT(*) > 1
        ");

        if (count($dupes) > 0) {
            throw new \RuntimeException(
                "Hay " . count($dupes) . " documentos con múltiples versiones activas. " .
                "Limpiar antes de aplicar el UNIQUE: " . json_encode($dupes)
            );
        }

        // Paso A: agregar la columna generada VIRTUAL
        DB::statement("
            ALTER TABLE document_versions
            ADD COLUMN es_activa_doc_id BIGINT UNSIGNED
                GENERATED ALWAYS AS
                (CASE WHEN es_activa = 1 THEN document_id ELSE NULL END)
                VIRTUAL
        ");

        // Paso B: agregar el UNIQUE (índice secundario sobre la columna virtual)
        DB::statement("
            ALTER TABLE document_versions
            ADD UNIQUE KEY uq_dv_es_activa (es_activa_doc_id)
        ");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE document_versions DROP INDEX uq_dv_es_activa");
        DB::statement("ALTER TABLE document_versions DROP COLUMN es_activa_doc_id");
    }
};