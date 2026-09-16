<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de los recordatorios de lectura enviados a socios comerciales
 * (comando manuales:recordar-lectura).
 *
 * Una fila = "a este socio ya se le recordó esta versión de este manual". Es lo
 * que hace que cada manual se recuerde UNA sola vez: la tarea corre todos los
 * días y, sin este registro, mandaría el mismo recordatorio cada mañana.
 *
 * Es por VERSIÓN y no por manual: si se publica una versión nueva, el socio la
 * tiene que volver a leer, y esa versión nueva se puede recordar una vez.
 *
 * FKs en CASCADE a propósito: si se borra el usuario (purga o borrado
 * definitivo de una empresa) o la versión, el registro no tiene sentido y no
 * debe frenar el borrado. Sin CHECK: MySQL no admite CHECK y FK con acciones
 * referenciales sobre la misma columna (README §5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recordatorios_lectura', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('manual_version_id');
            $table->dateTime('enviado_at')->comment('UTC, escrito por PHP.');

            $table->unique(['user_id', 'manual_version_id'], 'uq_recordatorio_user_version');

            $table->foreign('user_id', 'fk_recordatorio_user')
                  ->references('id')->on('users')
                  ->cascadeOnDelete();
            $table->foreign('manual_version_id', 'fk_recordatorio_version')
                  ->references('id')->on('manual_versions')
                  ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recordatorios_lectura');
    }
};
