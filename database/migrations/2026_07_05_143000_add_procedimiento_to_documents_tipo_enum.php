<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Agrega el valor 'procedimiento' al enum `tipo` de la tabla `documents`.
 *
 * Se usa un ALTER ... MODIFY COLUMN crudo (DB::statement) en vez del schema
 * builder porque doctrine/dbal no infiere correctamente las columnas ENUM de
 * MySQL y termina rompiendo la definición. El nuevo valor se inserta antes de
 * 'otro', que se mantiene como catch-all final.
 *
 * Se preservan COLLATE, NOT NULL y el orden original de los valores existentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE `documents` MODIFY COLUMN `tipo` " .
            "ENUM('contrato','politica','protocolo','circular','anexo','acta','procedimiento','otro') " .
            "COLLATE utf8mb4_unicode_ci NOT NULL"
        );
    }

    public function down(): void
    {
        // Defensa: si ya hay documentos guardados como 'procedimiento', el MODIFY
        // de vuelta al enum sin ese valor fallaría (o truncaría el dato). Los
        // reasignamos a 'otro' antes de revertir la definición.
        DB::statement("UPDATE `documents` SET `tipo` = 'otro' WHERE `tipo` = 'procedimiento'");

        DB::statement(
            "ALTER TABLE `documents` MODIFY COLUMN `tipo` " .
            "ENUM('contrato','politica','protocolo','circular','anexo','acta','otro') " .
            "COLLATE utf8mb4_unicode_ci NOT NULL"
        );
    }
};
