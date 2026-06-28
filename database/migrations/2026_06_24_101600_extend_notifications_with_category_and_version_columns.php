<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v2.3 — Paso 16/19
 * Agrega document_version_id y category_id a notifications.
 *
 * Necesario para los tipos nuevos:
 *  - nueva_version_documento       → document_version_id
 *  - manual_asignado_categoria     → manual_id + category_id
 *  - documento_asignado_categoria  → document_id + category_id
 *
 * Los CHECK constraints se actualizan en el paso 17/19.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->foreignId('document_version_id')
                ->nullable()
                ->after('document_id')
                ->constrained('document_versions')
                ->cascadeOnDelete();

            $table->foreignId('category_id')
                ->nullable()
                ->after('document_version_id')
                ->constrained('franchise_categories')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropForeign(['document_version_id']);
            $table->dropForeign(['category_id']);
            $table->dropColumn(['document_version_id', 'category_id']);
        });
    }
};
