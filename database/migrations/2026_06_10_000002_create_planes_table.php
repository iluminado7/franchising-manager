<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->enum('tipo_plan', ['por_franquicia', 'global']);
            $table->decimal('precio_base_por_franquicia', 10, 2)->nullable();
            $table->decimal('precio_global', 10, 2)->nullable();
            $table->integer('limite_franquicias')->nullable();
            $table->tinyInteger('manuales_ilimitados')->default(1);
            $table->tinyInteger('activo')->default(1);
            $table->timestamp('created_at')->useCurrent();
        });

        DB::statement('ALTER TABLE planes ADD CONSTRAINT chk_tipo_precio CHECK (
            (tipo_plan = \'por_franquicia\' AND precio_base_por_franquicia IS NOT NULL AND precio_global IS NULL)
            OR
            (tipo_plan = \'global\' AND precio_global IS NOT NULL AND precio_base_por_franquicia IS NULL)
        )');

        // Agregar FK de empresas → planes ahora que planes existe
        Schema::table('empresas', function (Blueprint $table) {
            $table->foreign('plan_id')->references('id')->on('planes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropForeign(['plan_id']);
        });
        Schema::dropIfExists('planes');
    }
};
