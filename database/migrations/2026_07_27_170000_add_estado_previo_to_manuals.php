<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * manuals.estado_previo — recuerda en que estado estaba un manual antes de
 * archivarse, para poder devolverlo ahi al restaurarlo.
 *
 * Sin esta columna, desarchivar() no tiene forma de distinguir un manual que
 * estaba publicado de uno que estaba en borrador, y cae siempre en borrador.
 * Eso dejaba invisibles a los socios manuales que estaban publicados, y
 * obligaba a republicarlos subiendo de version sin que el contenido hubiera
 * cambiado.
 *
 * El CHECK es importante: si estado_previo pudiera valer 'archivado', archivar
 * dos veces un mismo manual lo dejaria atrapado sin estado al cual volver.
 * (No hay FK sobre esta columna, asi que el CHECK es viable — ver la regla de
 * MySQL en el §5 del README.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manuals', function (Blueprint $table) {
            $table->string('estado_previo', 20)->nullable()->after('estado');
        });

        DB::statement("
            ALTER TABLE manuals
            ADD CONSTRAINT chk_estado_previo
            CHECK (estado_previo IS NULL OR estado_previo IN ('borrador','publicado'))
        ");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE manuals DROP CHECK chk_estado_previo');

        Schema::table('manuals', function (Blueprint $table) {
            $table->dropColumn('estado_previo');
        });
    }
};
