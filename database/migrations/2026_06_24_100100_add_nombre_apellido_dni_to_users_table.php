<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v2.3 — Paso 1/19
 * Agrega columnas nombre, apellido y dni a `users` como NULL.
 * Los datos se migran en la siguiente migración (paso 2/19).
 * NO se hace NOT NULL todavía — eso queda para el paso 3/19.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('nombre', 100)->nullable()->after('email');
            $table->string('apellido', 100)->nullable()->after('nombre');
            $table->string('dni', 15)->nullable()->after('apellido');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['nombre', 'apellido', 'dni']);
        });
    }
};
