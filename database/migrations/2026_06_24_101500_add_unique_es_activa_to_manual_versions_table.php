<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * v2.3 — Paso 15/19 (versión 3: VIRTUAL en lugar de STORED)
 *
 * Mismo fix que el paso 14/19: VIRTUAL en lugar de STORED para evitar
 * el conflicto entre columnas generadas STORED y FKs con ON DELETE CASCADE
 * en MySQL 8 (error 1215).
 *
 * manual_id en manual_versions tiene FK ON DELETE CASCADE hacia manuals.
 */
return new class extends Migration
{
    public function up(): void
    {
        $dupes = DB::select("
            SELECT manual_id, COUNT(*) AS activas
            FROM manual_versions
            WHERE es_activa = 1
            GROUP BY manual_id
            HAVING COUNT(*) > 1
        ");

        if (count($dupes) > 0) {
            throw new \RuntimeException(
                "Hay " . count($dupes) . " manuales con múltiples versiones activas. " .
                "Limpiar antes de aplicar el UNIQUE: " . json_encode($dupes)
            );
        }

        // Paso A: agregar la columna generada VIRTUAL
        DB::statement("
            ALTER TABLE manual_versions
            ADD COLUMN es_activa_manual_id BIGINT UNSIGNED
                GENERATED ALWAYS AS
                (CASE WHEN es_activa = 1 THEN manual_id ELSE NULL END)
                VIRTUAL
        ");

        // Paso B: agregar el UNIQUE
        DB::statement("
            ALTER TABLE manual_versions
            ADD UNIQUE KEY uq_mv_es_activa (es_activa_manual_id)
        ");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE manual_versions DROP INDEX uq_mv_es_activa");
        DB::statement("ALTER TABLE manual_versions DROP COLUMN es_activa_manual_id");
    }
};