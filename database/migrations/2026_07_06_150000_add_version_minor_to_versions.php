<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Versionado en dos niveles (mayor.menor) para manuales y documentos.
 *
 * Se mantiene `version_number` como el número MAYOR y se agrega `version_minor`
 * (default 0). Así:
 *   v3.0 = version_number=3, version_minor=0
 *   v3.1 = version_number=3, version_minor=1
 *   v4.0 = version_number=4, version_minor=0
 *
 * Todas las filas existentes quedan en `.0` automáticamente por el DEFAULT 0,
 * sin necesidad de migrar datos.
 *
 * Punto clave: los UNIQUE actuales son sobre (id_padre, version_number). Con el
 * modelo mayor.menor, v3.0 y v3.1 comparten version_number=3, así que esos UNIQUE
 * los bloquearían. Hay que DROPearlos y recrearlos incluyendo version_minor.
 * El UNIQUE nuevo, además, sirve como índice de orden (version_number DESC,
 * version_minor DESC), por lo que no hace falta un índice adicional.
 *
 * Los UNIQUE de es_activa (uq_mv_es_activa / uq_dv_es_activa, sobre columnas
 * generadas virtuales) NO se tocan: siguen forzando una sola versión activa.
 *
 * Se usan ALTER crudos vía DB::statement porque estas tablas tienen columnas
 * GENERATED VIRTUAL que doctrine/dbal no infiere correctamente.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── manual_versions ──────────────────────────────────────────
        DB::statement(
            "ALTER TABLE `manual_versions` " .
            "ADD COLUMN `version_minor` INT NOT NULL DEFAULT 0 AFTER `version_number`, " .
            "DROP INDEX `manual_versions_manual_id_version_number_unique`, " .
            "ADD UNIQUE KEY `uq_mv_manual_version` (`manual_id`, `version_number`, `version_minor`)"
        );

        // ── document_versions ────────────────────────────────────────
        DB::statement(
            "ALTER TABLE `document_versions` " .
            "ADD COLUMN `version_minor` INT NOT NULL DEFAULT 0 AFTER `version_number`, " .
            "DROP INDEX `uq_doc_version`, " .
            "ADD UNIQUE KEY `uq_doc_version` (`document_id`, `version_number`, `version_minor`)"
        );
    }

    public function down(): void
    {
        // ADVERTENCIA: este rollback es intencionalmente NO destructivo. Si ya se
        // publicaron versiones menores (version_minor > 0), al quitar la columna
        // quedarían filas con (id_padre, version_number) duplicado y el ADD del
        // UNIQUE viejo fallará. Es a propósito: preferimos que el rollback falle
        // ruidosamente a borrar versiones en silencio. En ese caso hay que
        // decidir manualmente qué hacer con las versiones menores antes de revertir.

        DB::statement(
            "ALTER TABLE `manual_versions` " .
            "DROP INDEX `uq_mv_manual_version`, " .
            "DROP COLUMN `version_minor`, " .
            "ADD UNIQUE KEY `manual_versions_manual_id_version_number_unique` (`manual_id`, `version_number`)"
        );

        DB::statement(
            "ALTER TABLE `document_versions` " .
            "DROP INDEX `uq_doc_version`, " .
            "DROP COLUMN `version_minor`, " .
            "ADD UNIQUE KEY `uq_doc_version` (`document_id`, `version_number`)"
        );
    }
};
