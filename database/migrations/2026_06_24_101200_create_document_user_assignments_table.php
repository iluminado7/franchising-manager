<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v2.3 — Paso 12/19
 * Asignación de documentos a usuarios individuales.
 * Apunta al document cabecera (no a versión).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_user_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('assigned_at')->useCurrent();

            $table->unique(['empresa_id', 'document_id', 'user_id'], 'uq_doc_user');
            $table->index(['user_id', 'empresa_id'], 'idx_dua_user');
            $table->index(['document_id', 'empresa_id'], 'idx_dua_document');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_user_assignments');
    }
};
