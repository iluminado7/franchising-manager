<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v2.3 — Paso 7/19
 * Asignación de manuales a una categoría completa.
 * Todos los usuarios que tengan esa categoría en esa empresa verán el manual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manual_category_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('manual_id')->constrained('manuals')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('franchise_categories')->cascadeOnDelete();
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('assigned_at')->useCurrent();

            $table->unique(['empresa_id', 'manual_id', 'category_id'], 'uq_manual_cat');
            $table->index(['manual_id', 'empresa_id'], 'idx_mca_manual');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_category_assignments');
    }
};
