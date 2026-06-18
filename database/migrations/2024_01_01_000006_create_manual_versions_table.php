<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manual_versions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('manual_id');
            $table->integer('version_number');
            $table->mediumText('contenido_html');
            $table->string('contenido_hash', 64)->comment('SHA-256 del contenido HTML');
            $table->unsignedBigInteger('publicado_por');
            $table->dateTime('publicado_at');
            $table->tinyInteger('es_activa')->default(0)->comment('Solo una versión activa por manual');
            $table->dateTime('created_at')->useCurrent();

            $table->unique(['manual_id', 'version_number']);

            $table->foreign('manual_id')
                  ->references('id')->on('manuals')
                  ->onDelete('restrict');

            $table->foreign('publicado_por')
                  ->references('id')->on('users')
                  ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_versions');
    }
};
