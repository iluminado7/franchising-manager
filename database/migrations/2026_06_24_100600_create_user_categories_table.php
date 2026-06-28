<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v2.3 — Paso 6/19
 * Pivote N:M entre usuarios y categorías.
 * Un usuario puede tener cero, una o varias categorías.
 * PK compuesta (user_id, category_id) evita duplicados.
 *
 * El índice explícito sobre category_id es necesario porque la PK lo tiene
 * como segundo elemento (no como prefijo). Sin él, los JOIN inverso desde
 * manual_category_assignments serían table scans.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_categories', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('franchise_categories')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('assigned_at')->useCurrent();

            $table->primary(['user_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_categories');
    }
};
