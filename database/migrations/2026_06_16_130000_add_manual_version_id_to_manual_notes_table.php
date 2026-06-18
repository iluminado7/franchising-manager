<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manual_notes', function (Blueprint $table) {
            // Versión del manual sobre la que se deja la nota
            // (la versión activa al momento de escribirla; null si el manual aún no tiene publicada)
            $table->foreignId('manual_version_id')
                  ->nullable()
                  ->after('empresa_id')
                  ->constrained('manual_versions')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('manual_notes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('manual_version_id');
        });
    }
};
