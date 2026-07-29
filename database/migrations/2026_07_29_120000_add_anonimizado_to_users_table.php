<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Purga de datos personales de un usuario ("borrado definitivo").
 *
 * La fila de users NO se borra nunca. `acceptances.user_id` y
 * `activity_logs.user_id` son ON DELETE RESTRICT, asi que un DELETE fisico
 * fallaria — y eso esta bien: la fila es el sujeto de la cadena de
 * cumplimiento. Lo que se destruye son los datos de la persona, dejando el id
 * en pie.
 *
 * Estas dos columnas registran que esa purga ocurrio:
 *   - anonimizado_at  : cuando. Tambien es el flag que usa el sistema para
 *                       saber que el usuario ya no se puede restaurar (no le
 *                       queda email con el cual entrar).
 *   - anonimizado_por : quien. FK a users, ON DELETE SET NULL por consistencia
 *                       con deleted_by, que ya es asi.
 *
 * No lleva CHECK constraint a proposito: MySQL no admite una columna con CHECK
 * y a la vez una FK con ON DELETE SET NULL (README §5, ya mordio dos veces).
 * La regla "anonimizado_at implica deleted_at no nulo" se valida en el
 * controlador.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dateTime('anonimizado_at')
                  ->nullable()
                  ->after('deleted_at')
                  ->comment('Fecha de purga de datos personales. No nulo = usuario anonimizado, no restaurable.');

            $table->unsignedBigInteger('anonimizado_por')
                  ->nullable()
                  ->after('anonimizado_at')
                  ->comment('super_admin que ejecuto la purga.');

            $table->foreign('anonimizado_por', 'fk_users_anonimizado_por')
                  ->references('id')
                  ->on('users')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // La FK primero: MySQL no deja soltar una columna con FK viva.
            $table->dropForeign('fk_users_anonimizado_por');
            $table->dropColumn(['anonimizado_at', 'anonimizado_por']);
        });
    }
};
