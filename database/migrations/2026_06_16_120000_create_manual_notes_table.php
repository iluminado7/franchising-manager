<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manual_notes', function (Blueprint $table) {
            $table->id();

            // Manual al que pertenece la nota
            $table->foreignId('manual_id')->constrained('manuals')->cascadeOnDelete();

            // Empresa que la escribe (aislamiento multi-tenant)
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();

            // Autor (usuario franquiciante que la creó)
            $table->foreignId('user_id')->constrained('users');

            // Contenido de la sugerencia
            $table->text('contenido');

            // Estado que gestiona el super_admin
            $table->enum('estado', ['pendiente', 'leida', 'resuelta'])->default('pendiente');

            $table->timestamps();

            // Hilo: varias notas por manual y empresa (sin unique). Índice para las consultas.
            $table->index(['manual_id', 'empresa_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_notes');
    }
};
