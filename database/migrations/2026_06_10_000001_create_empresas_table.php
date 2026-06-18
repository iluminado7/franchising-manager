<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empresas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 200);
            $table->string('razon_social', 200);
            $table->string('cuit', 15);
            $table->unsignedBigInteger('plan_id')->nullable(); // nullable hasta crear planes
            $table->decimal('precio_custom_por_franquicia', 10, 2)->nullable();
            $table->decimal('precio_custom_global', 10, 2)->nullable();
            $table->tinyInteger('activa')->default(1);
            $table->timestamps();

            // CHECK: no pueden tener valor los dos precios custom al mismo tiempo
            $table->index('plan_id');
        });

        DB::statement('ALTER TABLE empresas ADD CONSTRAINT chk_precios_custom
            CHECK (precio_custom_por_franquicia IS NULL OR precio_custom_global IS NULL)');
    }

    public function down(): void
    {
        Schema::dropIfExists('empresas');
    }
};
