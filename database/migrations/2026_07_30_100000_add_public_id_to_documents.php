<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Identificador publico (ULID) para los documentos.
 *
 * Los manuales ya lo tienen: lectura.php navega con ?m=01KYJ9DTX8... y nunca
 * con el id de la base. Los documentos quedaron con ?d=1, que es la misma
 * informacion que el proyecto decidio no exponer para los manuales.
 *
 * ── QUE RESUELVE Y QUE NO ─────────────────────────────────────────────────
 *
 * NO es control de acceso: eso lo hace DocumentController::streamDocumento(),
 * que verifica empresa, visibilidad y asignacion en cada request. Enumerar
 * ids nunca devolvio documentos ajenos.
 *
 * Lo que evita es que la URL diga cuantos documentos hay en el sistema y que
 * se puedan tantear ids correlativos. Es defensa en profundidad, y sobre todo
 * consistencia: un socio que ve ?m=01KYJ... en un manual y ?d=1 en un
 * documento nota la diferencia.
 *
 * ── POR QUE NULLABLE Y NO NOT NULL ────────────────────────────────────────
 *
 * Se agrega nullable, se rellenan las filas existentes y recien despues se
 * pone el indice unico. Hacerlo NOT NULL en el mismo ALTER falla si alguna
 * fila quedo sin rellenar, y el error no dice cual.
 *
 * En MySQL un indice UNIQUE admite varios NULL, asi que la columna puede
 * quedar nullable sin perder la garantia de unicidad sobre los valores que
 * si estan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->char('public_id', 26)
                  ->nullable()
                  ->after('id')
                  ->comment('ULID publico para las URLs. El id de la base no se expone.');
        });

        // Backfill. Se juntan primero los ids y despues se actualiza uno por
        // uno: un chunkById sobre whereNull('public_id') se rompe solo, porque
        // las filas dejan de matchear el filtro a medida que se actualizan.
        $ids = DB::table('documents')->whereNull('public_id')->pluck('id');
        foreach ($ids as $id) {
            DB::table('documents')
              ->where('id', $id)
              ->update(['public_id' => (string) Str::ulid()]);
        }

        // El indice va DESPUES del backfill: antes, varias filas con NULL lo
        // pasarian igual (MySQL admite multiples NULL en un UNIQUE), pero
        // dejarlo para el final deja explicito que a esta altura ya no hay.
        Schema::table('documents', function (Blueprint $table) {
            $table->unique('public_id', 'uq_documents_public_id');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            // El indice primero: MySQL no deja soltar una columna indexada.
            $table->dropUnique('uq_documents_public_id');
            $table->dropColumn('public_id');
        });
    }
};
