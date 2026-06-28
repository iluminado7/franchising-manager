<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * v2.3 — Paso 9/19
 * Copia los datos de manual_assignments a manual_user_assignments.
 *
 * Verificaciones previas:
 *  1. No debe haber filas con empresa_id NULL (la nueva tabla lo requiere NOT NULL).
 *  2. No debe haber duplicados de (empresa_id, manual_id, user_id) — la nueva UNIQUE los rechaza.
 *
 * Si alguna verificación falla, aborta sin tocar la base.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            // VERIFICACIÓN 1: empresa_id no debe ser NULL
            $nullCount = DB::table('manual_assignments')->whereNull('empresa_id')->count();
            if ($nullCount > 0) {
                throw new \RuntimeException(
                    "Hay {$nullCount} filas en manual_assignments con empresa_id NULL. " .
                    "Completar empresa_id (cruzando con users.empresa_id) o eliminar esas filas antes de migrar."
                );
            }

            // VERIFICACIÓN 2: no debe haber duplicados
            $dupes = DB::table('manual_assignments')
                ->whereNotNull('empresa_id')
                ->select('empresa_id', 'manual_id', 'user_id', DB::raw('COUNT(*) as c'))
                ->groupBy('empresa_id', 'manual_id', 'user_id')
                ->having('c', '>', 1)
                ->get();

            if ($dupes->count() > 0) {
                throw new \RuntimeException(
                    "Hay {$dupes->count()} combinaciones duplicadas en manual_assignments. " .
                    "Limpiar duplicados antes de migrar: " . $dupes->toJson()
                );
            }

            // MIGRACIÓN
            DB::statement("
                INSERT INTO manual_user_assignments
                    (empresa_id, manual_id, user_id, assigned_by, assigned_at)
                SELECT empresa_id, manual_id, user_id, assigned_by, assigned_at
                FROM manual_assignments
                WHERE empresa_id IS NOT NULL
            ");

            // VERIFICACIÓN POST: los conteos deben coincidir
            $src = DB::table('manual_assignments')->whereNotNull('empresa_id')->count();
            $dst = DB::table('manual_user_assignments')->count();

            if ($src !== $dst) {
                throw new \RuntimeException(
                    "Migración inconsistente: manual_assignments tiene {$src} filas con empresa_id NOT NULL, " .
                    "pero manual_user_assignments quedó con {$dst}."
                );
            }
        });
    }

    public function down(): void
    {
        throw new \RuntimeException(
            "Migración no reversible: los datos se copiaron a manual_user_assignments. " .
            "Para revertir, restaurar backup completo."
        );
    }
};
