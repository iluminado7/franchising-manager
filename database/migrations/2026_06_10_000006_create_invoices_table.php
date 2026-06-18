<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')
                  ->constrained('empresas')
                  ->cascadeOnDelete();
            $table->foreignId('plan_id')
                  ->constrained('planes');
            $table->date('periodo');
            $table->string('numero_factura', 50)->nullable();
            $table->integer('franquicias_activas');
            $table->decimal('precio_por_franquicia', 10, 2)->nullable();
            $table->decimal('precio_global_snapshot', 10, 2)->nullable();
            $table->decimal('total', 10, 2);
            $table->enum('estado', ['pendiente', 'pagada', 'vencida'])->default('pendiente');
            $table->dateTime('pagado_at')->nullable();
            $table->text('notas')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['empresa_id', 'periodo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
