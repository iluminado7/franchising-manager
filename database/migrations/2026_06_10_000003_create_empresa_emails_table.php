<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empresa_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')
                  ->constrained('empresas')
                  ->cascadeOnDelete();
            $table->string('email', 200);
            $table->enum('tipo', ['contacto', 'facturacion']);
            $table->tinyInteger('principal')->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['empresa_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresa_emails');
    }
};
