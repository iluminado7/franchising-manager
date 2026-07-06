<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature: imágenes en manuales.
 *
 * Tabla para trackear las imágenes subidas al server que se referencian
 * desde el HTML de contenido / encabezado / pie de un manual.
 *
 * Cada imagen tiene:
 *   - archivo_path: path en disk 'local' (privado, servido vía endpoint autenticado)
 *   - archivo_hash: SHA-256 del archivo para deduplicación dentro del manual
 *   - subido_por: quién subió (super_admin o franquiciante)
 *
 * UNIQUE (manual_id, archivo_hash): dos uploads del mismo contenido al mismo
 * manual solo generan una fila en DB y un archivo en disco.
 *
 * FK a manuals con onDelete cascade: al eliminar el manual definitivamente,
 * se limpian las filas automáticamente (el disk cleanup se hace aparte).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('manual_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manual_id')->constrained('manuals')->cascadeOnDelete();
            $table->string('archivo_path', 500);
            $table->string('archivo_hash', 64)->comment('SHA-256 para deduplicación');
            $table->string('mime', 100);
            $table->unsignedInteger('size')->comment('Bytes');
            $table->foreignId('subido_por')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['manual_id', 'archivo_hash'],
                           'manual_images_manual_hash_unique');
            $table->index('archivo_path');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_images');
    }
};
