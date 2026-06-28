<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * v2.3 — Paso 10/19
 * Elimina la tabla manual_assignments después de migrar sus datos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('manual_assignments');
    }

    public function down(): void
    {
        throw new \RuntimeException(
            "Migración no reversible: manual_assignments fue eliminada. " .
            "Sus datos viven en manual_user_assignments. Para revertir, restaurar backup completo."
        );
    }
};
