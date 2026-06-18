<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manual_empresa_assignments', function (Blueprint $table) {
            $table->unsignedBigInteger('manual_id');
            $table->unsignedBigInteger('empresa_id');
            $table->foreignId('asignado_por')
                  ->constrained('users');
            $table->dateTime('asignado_at');

            $table->primary(['manual_id', 'empresa_id']);
            $table->foreign('manual_id')->references('id')->on('manuals')->cascadeOnDelete();
            $table->foreign('empresa_id')->references('id')->on('empresas')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_empresa_assignments');
    }
};
