<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('titulo', 200);
            $table->enum('tipo', ['contrato', 'anexo', 'acta', 'otro']);
            $table->unsignedBigInteger('subido_por');
            $table->unsignedBigInteger('franquicia_id')->nullable()->comment('NULL = global para todas');
            $table->string('archivo_url', 500);
            $table->string('archivo_hash', 64)->comment('SHA-256 del archivo');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('tamano_bytes');
            $table->tinyInteger('visible_franquiciado')->default(1)->comment('0=solo franquiciante, 1=visible');
            $table->dateTime('created_at')->useCurrent();

            $table->foreign('subido_por')
                  ->references('id')->on('users')
                  ->onDelete('restrict');

            $table->foreign('franquicia_id')
                  ->references('id')->on('franquicias')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
