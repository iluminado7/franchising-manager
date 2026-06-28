<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v2.3 — Paso 13/19
 * Agrega previous_version_id (FK auto-referencial) a document_versions.
 * Permite navegar la cadena completa de versiones.
 *
 * ON DELETE SET NULL: si se borra una versión intermedia, la versión que la
 * apuntaba queda con previous_version_id = NULL en lugar de romper el FK.
 *
 * NOTA: v2.2 también proponía cambiar `nota` a NULLable, pero el dump real
 * muestra que YA es NULL — no hace falta tocar nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_versions', function (Blueprint $table) {
            $table->foreignId('previous_version_id')
                ->nullable()
                ->after('version_number')
                ->constrained('document_versions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('document_versions', function (Blueprint $table) {
            $table->dropForeign(['previous_version_id']);
            $table->dropColumn('previous_version_id');
        });
    }
};
