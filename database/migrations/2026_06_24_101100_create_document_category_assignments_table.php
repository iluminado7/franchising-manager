<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v2.3 — Paso 11/19
 * Asignación de documentos a una categoría completa.
 * Apunta al document (cabecera), no a una versión específica:
 * el usuario siempre ve la versión activa del document asignado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_category_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('franchise_categories')->cascadeOnDelete();
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('assigned_at')->useCurrent();

            $table->unique(['empresa_id', 'document_id', 'category_id'], 'uq_doc_cat');
            $table->index(['document_id', 'empresa_id'], 'idx_dca_document');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_category_assignments');
    }
};
