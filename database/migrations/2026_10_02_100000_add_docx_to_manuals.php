<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documento de trabajo en Word para los manuales editables (OnlyOffice).
 *
 * ── QUE CAMBIA Y QUE NO ──────────────────────────────────────────────────
 *
 * NO cambia el enum `tipo`. Un manual sigue siendo 'editable' o 'pdf'. Lo que
 * cambia es COMO se guarda el borrador de un manual editable:
 *
 *      docx_path NULL      -> borrador en HTML, editor propio (editor.php)
 *      docx_path NOT NULL  -> borrador en .docx, editor de OnlyOffice
 *
 * Esa columna es el discriminador. Permite que convivan los manuales viejos
 * con los nuevos sin migrar nada: cada manual abre el editor que corresponde
 * a como esta guardado.
 *
 * NO toca `manual_versions` ni el CHECK chk_mv_contenido. Lo que se publica
 * de un manual en Word es un PDF convertido, que ya cae en la rama
 * archivo_path del CHECK, igual que un manual tipo 'pdf'. El .docx es el
 * ORIGINAL EDITABLE, no la version publicada: son dos cosas distintas y
 * viven en lugares distintos a proposito.
 *
 * Nullable todo: los manuales que ya existen quedan con NULL y siguen
 * funcionando exactamente como hoy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manuals', function (Blueprint $table) {
            // Ruta en el disco configurado (local en dev, s3 en prod).
            $table->string('docx_path', 500)
                  ->nullable()
                  ->after('pie_pagina_html')
                  ->comment('Documento de trabajo .docx. NULL = manual con borrador en HTML.');

            // Nombre con el que lo subio el usuario. Solo metadato: NUNCA se
            // usa para construir una ruta del filesystem (podria traer ../).
            $table->string('docx_nombre', 255)
                  ->nullable()
                  ->after('docx_path');

            $table->unsignedBigInteger('docx_tamano')
                  ->nullable()
                  ->after('docx_nombre')
                  ->comment('Bytes. Se usa para el cupo de las empresas demo.');

            // Clave de version para OnlyOffice. Mientras no cambie, el
            // Document Server sirve lo que tiene cacheado e ignora el
            // storage: tiene que renovarse en CADA guardado.
            $table->string('docx_clave', 40)
                  ->nullable()
                  ->after('docx_tamano');

            $table->timestamp('docx_actualizado_at')
                  ->nullable()
                  ->after('docx_clave');
        });
    }

    public function down(): void
    {
        // OJO: esto NO borra los archivos del storage, solo la referencia.
        // Si hay manuales con docx_path cargado, al revertir quedan huerfanos
        // y el manual pierde su documento de trabajo. Es intencional que haya
        // que decidirlo a mano antes de bajar esta migracion.
        Schema::table('manuals', function (Blueprint $table) {
            $table->dropColumn([
                'docx_path',
                'docx_nombre',
                'docx_tamano',
                'docx_clave',
                'docx_actualizado_at',
            ]);
        });
    }
};
