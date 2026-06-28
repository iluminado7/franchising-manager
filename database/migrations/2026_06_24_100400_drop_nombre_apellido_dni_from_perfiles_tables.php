<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v2.3 — Paso 4/19
 * Elimina las columnas nombre, apellido y dni de las tablas de perfil.
 * Los datos ya viven en users desde el paso 2/19.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('super_admins', function (Blueprint $table) {
            $table->dropColumn(['nombre', 'apellido', 'dni']);
        });

        Schema::table('system_admins', function (Blueprint $table) {
            $table->dropColumn(['nombre', 'apellido', 'dni']);
        });

        Schema::table('franchise_staff', function (Blueprint $table) {
            $table->dropColumn(['nombre', 'apellido', 'dni']);
        });
    }

    public function down(): void
    {
        throw new \RuntimeException(
            "Migración no reversible: las columnas nombre/apellido/dni se eliminaron de " .
            "super_admins, system_admins y franchise_staff. Los datos viven en users. " .
            "Para revertir, restaurar backup completo de la base."
        );
    }
};
