<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manual_assignments', function (Blueprint $table) {
            // PK compuesta: un empleado no puede tener el mismo manual asignado dos veces
            $table->unsignedBigInteger('manual_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('assigned_by');
            $table->dateTime('assigned_at')->useCurrent();

            $table->primary(['manual_id', 'user_id']);

            $table->foreign('manual_id')
                  ->references('id')->on('manuals')
                  ->onDelete('cascade');

            $table->foreign('user_id')
                  ->references('id')->on('users')
                  ->onDelete('cascade');

            $table->foreign('assigned_by')
                  ->references('id')->on('users')
                  ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_assignments');
    }
};
