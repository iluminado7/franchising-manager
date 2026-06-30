<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Extiende el ENUM de la columna `tipo` en la tabla `documents` para incluir
 * los nuevos tipos: politica, protocolo, circular.
 *
 * Antes:   ENUM('contrato','anexo','acta','otro')
 * Después: ENUM('contrato','politica','protocolo','circular','anexo','acta','otro')
 *
 * Operación segura: solo agrega valores aceptados al ENUM, no modifica datos
 * existentes. Los documentos con tipo 'contrato'/'anexo'/'acta'/'otro' quedan
 * intactos.
 *
 * Down: vuelve al ENUM original. Si hay documentos con los tipos nuevos al
 * momento del rollback, MySQL convertirá esos valores a string vacío y emitirá
 * warnings — conviene reasignarlos a 'otro' antes de ejecutar down().
 */
return new class extends Migration {
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE documents MODIFY COLUMN tipo " .
            "ENUM('contrato','politica','protocolo','circular','anexo','acta','otro') NOT NULL"
        );
    }

    public function down(): void
    {
        DB::statement(
            "ALTER TABLE documents MODIFY COLUMN tipo " .
            "ENUM('contrato','anexo','acta','otro') NOT NULL"
        );
    }
};