<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manuals', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('titulo', 200);
            $table->string('categoria', 100)->nullable();
            $table->unsignedBigInteger('created_by');
            $table->enum('estado', ['borrador', 'publicado', 'archivado'])->default('borrador');
            $table->integer('orden')->default(0);
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('created_by')
                  ->references('id')->on('users')
                  ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manuals');
    }
};
