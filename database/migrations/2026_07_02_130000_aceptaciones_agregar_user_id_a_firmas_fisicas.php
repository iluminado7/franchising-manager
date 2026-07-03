<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aceptaciones (feature nueva) — modelo de firma física ahora es POR SOCIO
 * COMERCIAL (user_id), no por sucursal (franquicia_id).
 *
 * Cambios:
 *   - Agregar user_id BIGINT con FK a users
 *   - Cambiar franquicia_id de NOT NULL a NULL (socios sin sucursal pueden firmar)
 *   - Reemplazar UNIQUE (manual_version_id, franquicia_id) por
 *                 UNIQUE (manual_version_id, user_id)
 *
 * IMPORTANTE — orden de operaciones:
 * MySQL requiere que cada FK tenga un índice de soporte. El UNIQUE original
 * (manual_version_id, franquicia_id) era el único índice sobre
 * manual_version_id, así que hacía doble función: enforce unique + soporte de
 * la FK a manual_versions. Si se dropea antes de crear otro índice sobre esa
 * columna, MySQL lanza error 1553.
 *
 * Por eso el orden es:
 *   1) Agregar user_id (nullable + FK, que crea automáticamente un índice)
 *   2) Si la tabla está vacía, endurecer user_id a NOT NULL
 *   3) Crear el nuevo UNIQUE (manual_version_id, user_id) — este también
 *      empieza con manual_version_id, por lo que soporta la FK a manual_versions
 *   4) Dropear el UNIQUE viejo — ya no falla porque el nuevo lo reemplazó
 *      como soporte de la FK
 *   5) Cambiar franquicia_id a NULL
 */
return new class extends Migration {
    public function up(): void
    {
        // Paso 1: agregar user_id nullable con FK a users.
        // Laravel crea automáticamente un índice sobre user_id.
        Schema::table('physical_signatures', function (Blueprint $table) {
            $table->foreignId('user_id')
                  ->nullable()
                  ->after('franquicia_id')
                  ->constrained('users')
                  ->restrictOnDelete();
        });

        // Paso 2: si la tabla está vacía, hacer user_id NOT NULL.
        $filasConUserIdNull = DB::table('physical_signatures')
                                ->whereNull('user_id')
                                ->count();

        if ($filasConUserIdNull === 0) {
            DB::statement("ALTER TABLE physical_signatures MODIFY COLUMN user_id BIGINT UNSIGNED NOT NULL");
        }

        // Paso 3: crear el nuevo UNIQUE (manual_version_id, user_id).
        // MySQL puede usar el prefijo (manual_version_id) para soportar la FK
        // a manual_versions, así el dropUnique del paso 4 no va a fallar.
        Schema::table('physical_signatures', function (Blueprint $table) {
            $table->unique(
                ['manual_version_id', 'user_id'],
                'physical_signatures_manual_version_id_user_id_unique'
            );
        });

        // Paso 4: ahora sí, dropear el UNIQUE viejo.
        Schema::table('physical_signatures', function (Blueprint $table) {
            $table->dropUnique('physical_signatures_manual_version_id_franquicia_id_unique');
        });

        // Paso 5: cambiar franquicia_id a NULL para permitir socios sin sucursal.
        // Raw SQL evita depender de doctrine/dbal en Laravel 12.
        DB::statement("ALTER TABLE physical_signatures MODIFY COLUMN franquicia_id BIGINT UNSIGNED NULL");
    }

    public function down(): void
    {
        // Reversa: orden espejo del up() para respetar los mismos requerimientos
        // de índices sobre FKs.

        // Paso 1: recrear el UNIQUE viejo (asume que no hay franquicia_id NULL,
        // caso contrario este ALTER falla y hay que rellenar antes).
        DB::statement("ALTER TABLE physical_signatures MODIFY COLUMN franquicia_id BIGINT UNSIGNED NOT NULL");

        Schema::table('physical_signatures', function (Blueprint $table) {
            $table->unique(
                ['manual_version_id', 'franquicia_id'],
                'physical_signatures_manual_version_id_franquicia_id_unique'
            );
        });

        // Paso 2: dropear el UNIQUE nuevo — ahora la FK ya tiene otro soporte
        // (el UNIQUE viejo recién creado empieza también con manual_version_id).
        Schema::table('physical_signatures', function (Blueprint $table) {
            $table->dropUnique('physical_signatures_manual_version_id_user_id_unique');
        });

        // Paso 3: dropear la FK y la columna user_id.
        Schema::table('physical_signatures', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};