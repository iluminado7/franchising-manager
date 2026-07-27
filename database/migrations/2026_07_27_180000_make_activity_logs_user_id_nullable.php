<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * activity_logs.user_id pasa a ser NULLABLE.
 *
 * POR QUE
 *   AuthController::logLoginFallido() ya intenta registrar los intentos de
 *   login contra emails inexistentes, pasando userId: null. Hoy ese INSERT
 *   falla por el NOT NULL, y como la llamada esta envuelta en try/catch, falla
 *   EN SILENCIO.
 *
 *   Resultado: la enumeracion de emails es invisible. Alguien puede probar diez
 *   mil direcciones para descubrir cuales existen y no queda ni un registro.
 *   Es la deuda anotada en el §9 del README.
 *
 *   No hace falta cambiar codigo: el call site ya manda null.
 *
 * SOBRE EL TIPO
 *   Se usa ->change() de Laravel en vez de un ALTER crudo a proposito: un
 *   MODIFY COLUMN en MySQL exige repetir la definicion COMPLETA, y si el tipo
 *   declarado no coincide con el real (INT vs BIGINT, signed vs unsigned) rompe
 *   la foreign key o falla el ALTER. change() lo resuelve solo.
 *
 *   La FK sobrevive: cambiar la nulabilidad no la toca. Lo que si obligaria a
 *   recrearla es cambiar el TIPO, y aca no se cambia.
 *
 * SOBRE EL CHECK
 *   activity_logs tiene chk_detalle_schema, pero es sobre `detalle`, no sobre
 *   user_id. No aplica la regla del §5 (una columna no puede tener a la vez un
 *   CHECK y una FK con accion referencial).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Guard: si ya es nullable, no hay nada que hacer. Evita que un
        // re-run manual sobre una base ya migrada tire error.
        $col = collect(DB::select("SHOW COLUMNS FROM activity_logs LIKE 'user_id'"))->first();
        if ($col && strtoupper($col->Null) === 'YES') {
            return;
        }

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // OJO: volver atras solo es posible si NO hay filas con user_id NULL.
        // Si las hay, MySQL rechaza el NOT NULL. Se avisa en vez de borrarlas:
        // son registros de seguridad y esta migracion no tiene por que decidir
        // eliminarlos.
        $huerfanos = DB::table('activity_logs')->whereNull('user_id')->count();

        if ($huerfanos > 0) {
            throw new \RuntimeException(
                "No se puede revertir: hay {$huerfanos} registros con user_id NULL. " .
                "Son intentos de login contra emails inexistentes. Revisalos y " .
                "decidí a mano qué hacer con ellos antes de revertir."
            );
        }

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });
    }
};
