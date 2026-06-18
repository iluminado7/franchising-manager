<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Mismo motivo que 'accion' en activity_logs: si 'tipo' es un enum o varchar corto,
        // los tipos nuevos de notificación (p. ej. 'version_franquiciante') no entran.
        // Lo pasamos a varchar generoso para no tener que migrar por cada tipo nuevo.
        DB::statement("ALTER TABLE notifications MODIFY tipo VARCHAR(50) NOT NULL");
    }

    public function down(): void
    {
        // No se revierte automáticamente para no perder datos ni reintroducir el límite.
    }
};