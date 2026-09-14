<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tamaño del PDF de una firma física, en bytes.
 *
 * Lo necesita el cupo de almacenamiento de las empresas demo
 * (App\Services\CupoDemo): es el único archivo subido que no guardaba su
 * tamaño. Los manuales (archivo_tamano), los documentos (tamano_bytes) y las
 * imágenes (size) ya lo tenían.
 *
 * Nullable: las firmas existentes no lo tienen y se cuentan como 0. Son todas
 * de empresas anteriores a las demo, así que no afectan ningún cupo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('physical_signatures', function (Blueprint $table) {
            $table->unsignedBigInteger('archivo_tamano')
                  ->nullable()
                  ->after('archivo_hash')
                  ->comment('Bytes del PDF. NULL en firmas anteriores a 2026-09-14.');
        });
    }

    public function down(): void
    {
        Schema::table('physical_signatures', function (Blueprint $table) {
            $table->dropColumn('archivo_tamano');
        });
    }
};
