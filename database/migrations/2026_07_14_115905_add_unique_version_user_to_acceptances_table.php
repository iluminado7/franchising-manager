<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * V2-H-010 — UNIQUE (manual_version_id, user_id) en acceptances.
 *
 * La tabla se creo (2024_01_01_000008) solo con dos FKs y sin UNIQUE. Sin esa
 * restriccion, dos requests concurrentes de POST /versiones/{id}/aceptar pasaban
 * ambos el chequeo fueAceptadaPor() y creaban DOS filas de aceptacion para el
 * mismo usuario y la misma version. En una tabla de compliance eso no es un
 * detalle: duplica el registro legal de conformidad.
 *
 * El lock en AcceptanceController::aceptar cierra la carrera a nivel aplicacion.
 * Este UNIQUE la cierra a nivel base de datos, que es donde la garantia es real.
 *
 * Mismo patron que 2026_06_24_101500_add_unique_es_activa_to_manual_versions:
 * si hay duplicados preexistentes, FALLA RUIDOSAMENTE en vez de borrarlos por su
 * cuenta. En una tabla de compliance no se decide automaticamente que fila se tira.
 */
return new class extends Migration
{
    public function up(): void
    {
        $dupes = DB::select("
            SELECT manual_version_id, user_id, COUNT(*) AS c
            FROM acceptances
            GROUP BY manual_version_id, user_id
            HAVING COUNT(*) > 1
        ");

        if (count($dupes) > 0) {
            throw new \RuntimeException(
                "Hay " . count($dupes) . " pares (version, usuario) con aceptaciones " .
                "duplicadas. Resolver a mano antes de aplicar el UNIQUE (es una tabla " .
                "de compliance): " . json_encode($dupes)
            );
        }

        DB::statement("
            ALTER TABLE acceptances
            ADD UNIQUE KEY uq_acceptance_version_user (manual_version_id, user_id)
        ");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE acceptances DROP INDEX uq_acceptance_version_user");
    }
};
