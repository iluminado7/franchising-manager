<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Identificador PUBLICO opaco para los manuales.
 *
 * Hoy lectura.php navega con ?id=44. Ese ID secuencial deja ver cuantos manuales
 * hay en la base y permite sondear cuales existen. No expone el contenido (de eso
 * ya se ocupa ManualAccessService), pero es informacion que no hace falta dar.
 *
 * Se agrega un ULID de 26 caracteres (no un UUID de 36: mas corto y sin guiones,
 * queda mucho mejor en la barra de direcciones):
 *
 *     lectura.php?m=01K0S7QW9F3X2M8B4NRTVCJH6D
 *
 * DIFERENCIA CON EL TOKEN DEL ARCHIVO (etapa A):
 *   - aquel es EFIMERO y atado a un usuario: sirve para que el enlace del archivo
 *     no se pueda compartir ni guardar.
 *   - este es ESTABLE: la URL de un manual tiene que poder guardarse en favoritos
 *     y seguir funcionando manana.
 * Son complementarios, no redundantes.
 *
 * NO ES AUTORIZACION: el public_id solo reemplaza al ID como forma de NOMBRAR el
 * manual. Quien puede verlo lo sigue decidiendo ManualAccessService.
 *
 * El ID numerico NO se toca: sigue siendo la PK y todas las FKs siguen igual.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) Columna nullable (hay filas existentes que todavia no tienen valor).
        DB::statement("
            ALTER TABLE `manuals`
            ADD COLUMN `public_id` CHAR(26)
                CHARACTER SET ascii COLLATE ascii_general_ci
                NULL DEFAULT NULL
                COMMENT 'ULID publico: se usa en las URLs de lectura en vez del id secuencial'
                AFTER `id`
        ");

        // 2) Backfill. Se genera uno por fila, en PHP, porque MySQL no trae ULID.
        //    chunkById para no cargar toda la tabla si algun dia son muchos.
        DB::table('manuals')->select('id')->orderBy('id')->chunkById(200, function ($filas) {
            foreach ($filas as $fila) {
                DB::table('manuals')
                    ->where('id', $fila->id)
                    ->update(['public_id' => (string) Str::ulid()]);
            }
        });

        // 3) Recien ahora NOT NULL + UNIQUE: si quedara alguna fila sin valor, esto
        //    falla ruidosamente en vez de dejar manuales sin identificador publico.
        DB::statement("
            ALTER TABLE `manuals`
            MODIFY COLUMN `public_id` CHAR(26)
                CHARACTER SET ascii COLLATE ascii_general_ci
                NOT NULL
                COMMENT 'ULID publico: se usa en las URLs de lectura en vez del id secuencial'
        ");
        DB::statement("ALTER TABLE `manuals` ADD UNIQUE KEY `uq_manuals_public_id` (`public_id`)");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE `manuals` DROP INDEX `uq_manuals_public_id`");
        DB::statement("ALTER TABLE `manuals` DROP COLUMN `public_id`");
    }
};
