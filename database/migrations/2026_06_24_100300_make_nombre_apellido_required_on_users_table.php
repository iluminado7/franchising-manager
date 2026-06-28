<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * v2.3 — Paso 3/19
 * Convierte users.nombre y users.apellido a NOT NULL después de haber migrado.
 * dni se deja NULLable (hay perfiles donde es opcional).
 *
 * Usa DB::statement raw para no depender de doctrine/dbal.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN nombre VARCHAR(100) NOT NULL");
        DB::statement("ALTER TABLE users MODIFY COLUMN apellido VARCHAR(100) NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN nombre VARCHAR(100) NULL");
        DB::statement("ALTER TABLE users MODIFY COLUMN apellido VARCHAR(100) NULL");
    }
};
