<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature: header/footer configurable por manual.
 *
 * Objetivo: permitir que cada manual tenga su encabezado y pie de página
 * propios (logo, texto legal, etc.) que aparecen en la vista de lectura y
 * en cada página al imprimir. Motivo: los manuales corporativos suelen
 * tener elementos institucionales que Word maneja como header/footer de
 * página, y HTML web no tiene equivalente nativo.
 *
 * Decisión de schema: los campos van en 'manuals' (no en 'manual_versions')
 * porque son "identidad del manual", no contenido versionable. El logo
 * corporativo no cambia entre v1 y v2 del manual.
 *
 * Tamaño: LONGTEXT (4GB). El HTML de header/footer suele ser pequeño (< 50KB
 * incluyendo logo base64 si el usuario lo pega directo), pero LONGTEXT no
 * tiene costo extra y evita límites arbitrarios.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('manuals', function (Blueprint $table) {
            $table->longText('encabezado_html')->nullable()->after('titulo');
            $table->longText('pie_pagina_html')->nullable()->after('encabezado_html');
        });
    }

    public function down(): void
    {
        Schema::table('manuals', function (Blueprint $table) {
            $table->dropColumn(['encabezado_html', 'pie_pagina_html']);
        });
    }
};
