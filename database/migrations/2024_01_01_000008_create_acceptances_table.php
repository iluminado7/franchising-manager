<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acceptances', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('manual_version_id');
            $table->unsignedBigInteger('user_id');
            $table->dateTime('aceptado_at');
            $table->string('ip_address', 45)->comment('IPv4 e IPv6');
            $table->string('user_agent', 500);
            $table->string('pdf_sellado_url', 500)->nullable();
            $table->string('hash_verificacion', 64)->comment('SHA-256 del contenido al momento de aceptación');
            $table->tinyInteger('pdf_generado')->default(0)->comment('0=generando, 1=listo');

            $table->foreign('manual_version_id')
                  ->references('id')->on('manual_versions')
                  ->onDelete('restrict');

            $table->foreign('user_id')
                  ->references('id')->on('users')
                  ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acceptances');
    }
};
