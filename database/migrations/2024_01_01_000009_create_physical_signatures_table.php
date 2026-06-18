<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('physical_signatures', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('manual_version_id');
            $table->unsignedBigInteger('franquicia_id');
            $table->unsignedBigInteger('subido_por');
            $table->string('archivo_url', 500);
            $table->string('archivo_hash', 64)->comment('SHA-256 del PDF escaneado');
            $table->text('notas')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            // Una sola firma física por versión por franquicia
            $table->unique(['manual_version_id', 'franquicia_id']);

            $table->foreign('manual_version_id')
                  ->references('id')->on('manual_versions')
                  ->onDelete('restrict');

            $table->foreign('franquicia_id')
                  ->references('id')->on('franquicias')
                  ->onDelete('restrict');

            $table->foreign('subido_por')
                  ->references('id')->on('users')
                  ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('physical_signatures');
    }
};
