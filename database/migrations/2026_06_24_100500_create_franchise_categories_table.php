<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v2.3 — Paso 5/19
 * Catálogo de categorías por empresa.
 * Cada empresa crea sus propias (Dropshipper, Técnico, Licenciatario, etc).
 * No modifican permisos — son etiquetas visuales y filtros de asignación.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('franchise_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['empresa_id', 'name'], 'uq_cat_name');
            $table->index(['empresa_id', 'is_active'], 'idx_cat_empresa_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('franchise_categories');
    }
};
