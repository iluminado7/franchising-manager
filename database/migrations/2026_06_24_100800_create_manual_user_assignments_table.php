<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v2.3 — Paso 8/19
 * Asignación de manuales a usuarios individuales.
 * Reemplaza la tabla manual_assignments (se migran datos en paso 9/19).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manual_user_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('manual_id')->constrained('manuals')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('assigned_at')->useCurrent();

            $table->unique(['empresa_id', 'manual_id', 'user_id'], 'uq_manual_user');
            $table->index(['user_id', 'empresa_id'], 'idx_mua_user');
            $table->index(['manual_id', 'empresa_id'], 'idx_mua_manual');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_user_assignments');
    }
};
