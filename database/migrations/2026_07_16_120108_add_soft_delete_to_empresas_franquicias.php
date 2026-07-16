<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Soft-delete para empresas y franquicias.
 *
 * Mismo esquema que users/manuals/documents: deleted_at (cuando) + deleted_by
 * (quien, FK a users con ON DELETE SET NULL para no perder el registro si el
 * admin que la borro se elimina despues).
 *
 * No se toca ninguna FK de compliance. El soft-delete solo agrega dos columnas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dateTime('deleted_at')->nullable()->after('activa');
            $table->unsignedBigInteger('deleted_by')->nullable()->after('deleted_at');
            $table->foreign('deleted_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('franquicias', function (Blueprint $table) {
            $table->dateTime('deleted_at')->nullable()->after('es_sede_central');
            $table->unsignedBigInteger('deleted_by')->nullable()->after('deleted_at');
            $table->foreign('deleted_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropForeign(['deleted_by']);
            $table->dropColumn(['deleted_at', 'deleted_by']);
        });

        Schema::table('franquicias', function (Blueprint $table) {
            $table->dropForeign(['deleted_by']);
            $table->dropColumn(['deleted_at', 'deleted_by']);
        });
    }
};
