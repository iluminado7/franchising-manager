<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ETAPA 1 — Manuales en PDF (no editables).
 *
 * Agrega el discriminador de tipo en `manuals` y las columnas de archivo en
 * `manual_versions`, para que un manual pueda ser:
 *   - 'editable' → se redacta en el editor, la versión guarda contenido_html
 *   - 'pdf'      → se sube un archivo, la versión guarda archivo_path
 *
 * Se reusa `manual_versions` a propósito: así el versionado, es_activa,
 * aceptaciones, notificaciones, asignaciones y activity_logs siguen
 * funcionando sin cambios para ambos tipos.
 *
 * NOTAS DE ESQUEMA:
 * - `contenido_html` pasa a NULLABLE (hoy es NOT NULL): una versión PDF no
 *   tiene HTML. El CHECK de abajo garantiza que nunca queden ambos vacíos.
 * - `contenido_hash` SIGUE siendo NOT NULL. En versiones PDF se guarda ahí el
 *   SHA-256 del ARCHIVO. Se evita así relajar el NOT NULL y agregar una
 *   columna de hash redundante. Solo se actualiza el COMMENT.
 * - El CHECK toca `contenido_html` y `archivo_path`, que NO son columnas de
 *   ninguna FK, así que no aplica la restricción de MySQL sobre CHECK +
 *   acciones referenciales (el problema que tuvimos con ON DELETE SET NULL).
 * - Las 108 filas existentes cumplen el CHECK sin migración de datos:
 *   contenido_html NOT NULL + archivo_path NULL.
 * - MySQL NO puede atar el CHECK a `manuals.tipo` (un CHECK no puede
 *   referenciar otra tabla). La coherencia tipo↔contenido y la INMUTABILIDAD
 *   de `tipo` tras la creación se enforcan en el controlador (etapa 2).
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1) Discriminador de tipo en manuals ───────────────────────────
        // Los 40 manuales existentes quedan en 'editable' por el DEFAULT.
        DB::statement("
            ALTER TABLE `manuals`
            ADD COLUMN `tipo` ENUM('editable','pdf')
                CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
                NOT NULL DEFAULT 'editable'
                COMMENT 'editable = se redacta en el editor (HTML); pdf = archivo subido, no editable'
                AFTER `titulo`
        ");

        // ── 2) Columnas de archivo en manual_versions ─────────────────────
        // NULL en versiones editables; obligatorias (vía CHECK) en versiones PDF.
        DB::statement("
            ALTER TABLE `manual_versions`
            ADD COLUMN `archivo_path` VARCHAR(255)
                CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
                NULL DEFAULT NULL
                COMMENT 'Ruta en disco privado: manuales/archivos/{manual_id}/{sha256}.pdf'
                AFTER `contenido_html`,
            ADD COLUMN `archivo_nombre` VARCHAR(255)
                CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
                NULL DEFAULT NULL
                COMMENT 'Nombre original del archivo subido (para la descarga)'
                AFTER `archivo_path`,
            ADD COLUMN `archivo_mime` VARCHAR(100)
                CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
                NULL DEFAULT NULL
                COMMENT 'MIME real detectado al subir (se espera application/pdf)'
                AFTER `archivo_nombre`,
            ADD COLUMN `archivo_tamano` BIGINT UNSIGNED
                NULL DEFAULT NULL
                COMMENT 'Tamaño del archivo en bytes'
                AFTER `archivo_mime`
        ");

        // ── 3) contenido_html pasa a NULLABLE ─────────────────────────────
        // (Era NOT NULL: impedía crear una versión PDF.)
        DB::statement("
            ALTER TABLE `manual_versions`
            MODIFY COLUMN `contenido_html` MEDIUMTEXT
                CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
                NULL DEFAULT NULL
                COMMENT 'HTML de la versión. Solo manuales editables; NULL en versiones PDF'
        ");

        // ── 4) contenido_hash: sigue NOT NULL, se aclara el significado ────
        DB::statement("
            ALTER TABLE `manual_versions`
            MODIFY COLUMN `contenido_hash` VARCHAR(64)
                CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
                NOT NULL
                COMMENT 'SHA-256: del HTML en versiones editables, del ARCHIVO en versiones PDF'
        ");

        // ── 5) CHECK: exactamente uno de los dos contenidos ───────────────
        // Impide versiones sin contenido y versiones híbridas (HTML + archivo).
        DB::statement("
            ALTER TABLE `manual_versions`
            ADD CONSTRAINT `chk_mv_contenido` CHECK (
                   (`contenido_html` IS NOT NULL AND `archivo_path`   IS NULL)
                OR (`archivo_path`   IS NOT NULL AND `contenido_html` IS NULL)
            )
        ");
    }

    public function down(): void
    {
        // OJO: si ya existen versiones PDF (contenido_html NULL), el paso de
        // volver contenido_html a NOT NULL FALLARÁ. Es intencional: hacer
        // rollback con datos PDF cargados implicaría perderlos silenciosamente.
        // Para revertir de verdad, primero hay que decidir qué hacer con esas
        // versiones.
        DB::statement("ALTER TABLE `manual_versions` DROP CHECK `chk_mv_contenido`");

        DB::statement("
            ALTER TABLE `manual_versions`
            MODIFY COLUMN `contenido_hash` VARCHAR(64)
                CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
                NOT NULL
                COMMENT 'SHA-256 del contenido HTML'
        ");

        DB::statement("
            ALTER TABLE `manual_versions`
            MODIFY COLUMN `contenido_html` MEDIUMTEXT
                CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
                NOT NULL
        ");

        DB::statement("
            ALTER TABLE `manual_versions`
            DROP COLUMN `archivo_tamano`,
            DROP COLUMN `archivo_mime`,
            DROP COLUMN `archivo_nombre`,
            DROP COLUMN `archivo_path`
        ");

        DB::statement("ALTER TABLE `manuals` DROP COLUMN `tipo`");
    }
};
