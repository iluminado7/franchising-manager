<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * v2.3 — Paso 17/19
 * Rehace los CHECK constraints de notifications:
 *  1. DROP chk_notification_ref (obsoleto: exigía exclusividad de UNA sola FK,
 *     incompatible con manual_id + category_id juntos).
 *  2. DROP chk_notif_fk y RECREATE con las 8 combinaciones válidas
 *     (4 existentes + 4 nuevas).
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Eliminar el constraint obsoleto de exclusividad
        DB::statement("ALTER TABLE notifications DROP CHECK chk_notification_ref");

        // 2. Reemplazar chk_notif_fk con la versión extendida
        DB::statement("ALTER TABLE notifications DROP CHECK chk_notif_fk");

        DB::statement("
            ALTER TABLE notifications ADD CONSTRAINT chk_notif_fk CHECK (
              -- EXISTENTES
              (tipo = 'nuevo_manual'
                AND manual_id IS NOT NULL
                AND manual_version_id IS NULL AND document_id IS NULL
                AND document_version_id IS NULL AND category_id IS NULL)
              OR
              (tipo IN ('modificacion_manual','manual_asignado')
                AND manual_version_id IS NOT NULL
                AND manual_id IS NULL AND document_id IS NULL
                AND document_version_id IS NULL AND category_id IS NULL)
              OR
              (tipo = 'nuevo_documento'
                AND document_id IS NOT NULL
                AND manual_id IS NULL AND manual_version_id IS NULL
                AND document_version_id IS NULL AND category_id IS NULL)
              OR
              (tipo = 'recordatorio_pendiente'
                AND manual_id IS NULL AND manual_version_id IS NULL
                AND document_id IS NULL AND document_version_id IS NULL
                AND category_id IS NULL)
              -- NUEVOS
              OR
              (tipo = 'manual_asignado_categoria'
                AND manual_id IS NOT NULL AND category_id IS NOT NULL
                AND manual_version_id IS NULL AND document_id IS NULL
                AND document_version_id IS NULL)
              OR
              (tipo = 'documento_asignado'
                AND document_id IS NOT NULL
                AND manual_id IS NULL AND manual_version_id IS NULL
                AND document_version_id IS NULL AND category_id IS NULL)
              OR
              (tipo = 'documento_asignado_categoria'
                AND document_id IS NOT NULL AND category_id IS NOT NULL
                AND manual_id IS NULL AND manual_version_id IS NULL
                AND document_version_id IS NULL)
              OR
              (tipo = 'nueva_version_documento'
                AND document_version_id IS NOT NULL
                AND manual_id IS NULL AND manual_version_id IS NULL
                AND document_id IS NULL AND category_id IS NULL)
            )
        ");
    }

    public function down(): void
    {
        // Restaurar los CHECK constraints originales de v1.7
        DB::statement("ALTER TABLE notifications DROP CHECK chk_notif_fk");

        DB::statement("
            ALTER TABLE notifications ADD CONSTRAINT chk_notif_fk CHECK (
              (tipo = 'nuevo_manual' AND manual_id IS NOT NULL AND manual_version_id IS NULL AND document_id IS NULL)
              OR (tipo IN ('modificacion_manual','manual_asignado') AND manual_version_id IS NOT NULL AND manual_id IS NULL AND document_id IS NULL)
              OR (tipo = 'nuevo_documento' AND document_id IS NOT NULL AND manual_id IS NULL AND manual_version_id IS NULL)
              OR (tipo = 'recordatorio_pendiente' AND manual_id IS NULL AND manual_version_id IS NULL AND document_id IS NULL)
            )
        ");

        DB::statement("
            ALTER TABLE notifications ADD CONSTRAINT chk_notification_ref CHECK (
              (manual_id IS NOT NULL AND manual_version_id IS NULL AND document_id IS NULL)
              OR (manual_id IS NULL AND manual_version_id IS NOT NULL AND document_id IS NULL)
              OR (manual_id IS NULL AND manual_version_id IS NULL AND document_id IS NOT NULL)
            )
        ");
    }
};
