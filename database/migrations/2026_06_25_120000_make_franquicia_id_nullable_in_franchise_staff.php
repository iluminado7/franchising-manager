<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * v2.3 UX: el rol 'franquiciado' representa a un "Socio comercial" en la UI.
     * No siempre tiene sucursal asignada (distribuidor, dropshipper, proveedor
     * de servicios, etc.). Hacemos la columna nullable.
     *
     * La FK a `franquicias` se mantiene — un NULL en la columna no la viola
     * (las FK sólo se chequean cuando hay valor).
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE franchise_staff MODIFY franquicia_id BIGINT UNSIGNED NULL');
    }

    /**
     * Rollback defensivo: si hay socios comerciales sin sucursal asignada,
     * no podemos revertir sin perder datos. Lanzamos excepción para que
     * Franco resuelva manualmente antes de hacer rollback.
     */
    public function down(): void
    {
        $nulls = DB::table('franchise_staff')->whereNull('franquicia_id')->count();

        if ($nulls > 0) {
            throw new \RuntimeException(
                "No se puede revertir esta migración: hay {$nulls} fila(s) en "
                . "franchise_staff con franquicia_id NULL (socios comerciales sin "
                . "sucursal asignada). Asignales una sucursal o eliminá esos "
                . "usuarios antes de rollback."
            );
        }

        DB::statement('ALTER TABLE franchise_staff MODIFY franquicia_id BIGINT UNSIGNED NOT NULL');
    }
};