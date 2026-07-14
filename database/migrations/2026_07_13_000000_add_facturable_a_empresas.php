<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Columna del flag. Default 1 => toda empresa nueva es facturable.
        Schema::table('empresas', function (Blueprint $table) {
            $table->boolean('facturable')
                  ->default(true)
                  ->after('precio_custom_global');
        });

        // 2) Marcar a Cerrajería Leonardo y limpiar plan/precios.
        //    Va ANTES del CHECK: si no, la fila existente lo violaría.
        DB::table('empresas')
            ->where('id', 1)
            ->update([
                'facturable'                   => 0,
                'plan_id'                      => null,
                'precio_custom_por_franquicia' => null,
                'precio_custom_global'         => null,
                'updated_at'                   => now(),
            ]);

        // 3) Quitar la FK actual.
        //    MySQL (error 3823) no permite que una columna con acción referencial
        //    (ON DELETE SET NULL) participe de un CHECK. Además ese SET NULL era
        //    peligroso: borrar un plan dejaba a sus empresas con plan_id = NULL
        //    en silencio, que con este cambio sería indistinguible de "gratis".
        DB::statement("ALTER TABLE `empresas` DROP FOREIGN KEY `empresas_plan_id_foreign`");

        // 4) Ahora sí, el CHECK (la columna ya no tiene acción referencial).
        DB::statement("
            ALTER TABLE `empresas`
            ADD CONSTRAINT `chk_exenta_sin_plan` CHECK (
                `facturable` = 1
                OR (
                    `plan_id` IS NULL
                    AND `precio_custom_por_franquicia` IS NULL
                    AND `precio_custom_global` IS NULL
                )
            )
        ");

        // 5) Recrear la FK SIN acción referencial (RESTRICT = default NO ACTION).
        //    Ahora borrar un plan con empresas asignadas falla con error 1451
        //    en vez de vaciarles el plan por atrás.
        DB::statement("
            ALTER TABLE `empresas`
            ADD CONSTRAINT `empresas_plan_id_foreign`
                FOREIGN KEY (`plan_id`) REFERENCES `planes` (`id`)
                ON DELETE RESTRICT ON UPDATE RESTRICT
        ");

        // 6) Garantizar que la exenta sea UNA SOLA.
        //    La columna generada vale 1 si es exenta y NULL si no; como los NULL
        //    no colisionan en un UNIQUE, permite N facturables y máximo 1 exenta.
        DB::statement("
            ALTER TABLE `empresas`
            ADD COLUMN `unica_exenta` TINYINT
                GENERATED ALWAYS AS (IF(`facturable` = 0, 1, NULL)) VIRTUAL,
            ADD UNIQUE KEY `uq_unica_exenta` (`unica_exenta`)
        ");
    }

    /**
     * OJO: el rollback NO restaura plan_id ni los precios custom de la empresa 1.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE `empresas` DROP INDEX `uq_unica_exenta`");
        DB::statement("ALTER TABLE `empresas` DROP COLUMN `unica_exenta`");

        // Mismo baile en reversa: sacar la FK para poder soltar el CHECK.
        DB::statement("ALTER TABLE `empresas` DROP FOREIGN KEY `empresas_plan_id_foreign`");
        DB::statement("ALTER TABLE `empresas` DROP CHECK `chk_exenta_sin_plan`");

        DB::statement("
            ALTER TABLE `empresas`
            ADD CONSTRAINT `empresas_plan_id_foreign`
                FOREIGN KEY (`plan_id`) REFERENCES `planes` (`id`)
                ON DELETE SET NULL
        ");

        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn('facturable');
        });
    }
};