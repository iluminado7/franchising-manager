<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ETAPA 6a (parte 1) — habilita el tipo de notificacion `acceso_anomalo_pdf`.
 *
 * Se dispara cuando un socio comercial (franquiciado/empleado) pide el ARCHIVO
 * de un manual PDF por fuera del visor embebido — es decir, navegando directo a
 * la URL del archivo. Es el proxy mas cercano a "se lo esta bajando", ya que el
 * boton de descarga del visor del navegador NO genera request al servidor y por
 * lo tanto es indetectable.
 *
 * POR QUE NO SE AGREGA UNA RAMA NUEVA AL CHECK:
 * la combinacion de FKs que necesita este tipo (manual_version_id NOT NULL, el
 * resto NULL) ya existe en la rama de 'modificacion_manual'/'manual_asignado'.
 * Solo se suma el tipo a ese IN. Verificado sobre las 320 combinaciones
 * posibles (10 tipos x 32 combinaciones de FK): cambia UNA sola fila, la del
 * tipo nuevo. Ningun tipo existente altera su comportamiento.
 *
 * MySQL no permite modificar un CHECK in place: hay que DROP + ADD. Al agregarlo
 * se revalidan las filas existentes; como el resto de la expresion es identica,
 * todas las notificaciones ya guardadas siguen siendo validas.
 */
return new class extends Migration
{
    private function expresion(bool $conAccesoAnomalo): string
    {
        $mvTipos = $conAccesoAnomalo
            ? "'modificacion_manual','manual_asignado','acceso_anomalo_pdf'"
            : "'modificacion_manual','manual_asignado'";

        return "
            (`tipo` = 'nuevo_manual'
                AND `manual_id` IS NOT NULL
                AND `manual_version_id` IS NULL AND `document_id` IS NULL
                AND `document_version_id` IS NULL AND `category_id` IS NULL)
         OR (`tipo` IN ({$mvTipos})
                AND `manual_version_id` IS NOT NULL
                AND `manual_id` IS NULL AND `document_id` IS NULL
                AND `document_version_id` IS NULL AND `category_id` IS NULL)
         OR (`tipo` = 'nuevo_documento'
                AND `document_id` IS NOT NULL
                AND `manual_id` IS NULL AND `manual_version_id` IS NULL
                AND `document_version_id` IS NULL AND `category_id` IS NULL)
         OR (`tipo` = 'recordatorio_pendiente'
                AND `manual_id` IS NULL AND `manual_version_id` IS NULL
                AND `document_id` IS NULL AND `document_version_id` IS NULL
                AND `category_id` IS NULL)
         OR (`tipo` = 'manual_asignado_categoria'
                AND `manual_id` IS NOT NULL AND `category_id` IS NOT NULL
                AND `manual_version_id` IS NULL AND `document_id` IS NULL
                AND `document_version_id` IS NULL)
         OR (`tipo` = 'documento_asignado'
                AND `document_id` IS NOT NULL
                AND `manual_id` IS NULL AND `manual_version_id` IS NULL
                AND `document_version_id` IS NULL AND `category_id` IS NULL)
         OR (`tipo` = 'documento_asignado_categoria'
                AND `document_id` IS NOT NULL AND `category_id` IS NOT NULL
                AND `manual_id` IS NULL AND `manual_version_id` IS NULL
                AND `document_version_id` IS NULL)
         OR (`tipo` = 'nueva_version_documento'
                AND `document_version_id` IS NOT NULL
                AND `manual_id` IS NULL AND `manual_version_id` IS NULL
                AND `document_id` IS NULL AND `category_id` IS NULL)
        ";
    }

    public function up(): void
    {
        DB::statement("ALTER TABLE `notifications` DROP CHECK `chk_notif_fk`");
        DB::statement(
            "ALTER TABLE `notifications` ADD CONSTRAINT `chk_notif_fk` CHECK ("
            . $this->expresion(true) . ")"
        );
    }

    public function down(): void
    {
        // OJO: si ya existen notificaciones con tipo 'acceso_anomalo_pdf', volver
        // al CHECK anterior FALLA (MySQL revalida las filas existentes). Es
        // intencional: primero hay que decidir que hacer con esas filas.
        //   DELETE FROM notifications WHERE tipo = 'acceso_anomalo_pdf';
        DB::statement("ALTER TABLE `notifications` DROP CHECK `chk_notif_fk`");
        DB::statement(
            "ALTER TABLE `notifications` ADD CONSTRAINT `chk_notif_fk` CHECK ("
            . $this->expresion(false) . ")"
        );
    }
};
