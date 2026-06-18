<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('franquicias', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nombre', 150);
            $table->string('razon_social', 200);
            $table->string('cuit', 15);
            $table->string('direccion', 300)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('email_contacto', 200)->nullable();
            $table->tinyInteger('activa')->default(1)->comment('0=inactiva, 1=activa');
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('franquicias');
    }
};
