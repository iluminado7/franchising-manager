<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Empresas demo: prueba gratuita para ofrecer la plataforma a otras empresas.
 *
 *   - es_demo       : 1 = empresa en periodo de prueba.
 *   - demo_vence_at : cuando se corta el acceso. Lo calcula el servidor al
 *                     crear la empresa (Empresa::DEMO_DIAS) y NO se extiende.
 *                     Al vencer se bloquea el acceso y los datos se conservan
 *                     hasta que un super_admin dé de baja la empresa.
 *
 * La fecha la escribe PHP en UTC (config app.timezone), nunca un DEFAULT de
 * MySQL: el reloj de la base es UTC en RDS y hora de Buenos Aires en XAMPP
 * (README §9). Por eso layout/auth.php la compara contra UTC_TIMESTAMP() y
 * no contra NOW().
 *
 * chk_empresa_demo garantiza que una demo siempre tenga vencimiento, y que una
 * empresa que no es demo no arrastre uno. Sin eso, una demo con fecha NULL
 * seria ambigua: ¿vencida o eterna? El codigo la trata como vencida (falla
 * cerrado), pero es mejor que ese estado no pueda existir.
 *
 * El CHECK es seguro aca: ninguna de las dos columnas tiene FK, asi que no
 * aplica la regla de MySQL que prohibe CHECK + FK con acciones referenciales
 * (README §5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->boolean('es_demo')
                  ->default(false)
                  ->after('activa')
                  ->comment('1 = empresa en periodo de prueba gratuito.');

            $table->dateTime('demo_vence_at')
                  ->nullable()
                  ->after('es_demo')
                  ->comment('Fin de la prueba (UTC). Obligatorio si es_demo = 1; NULL si no.');
        });

        DB::statement("
            ALTER TABLE `empresas`
            ADD CONSTRAINT `chk_empresa_demo` CHECK (
                (`es_demo` = 0 AND `demo_vence_at` IS NULL)
             OR (`es_demo` = 1 AND `demo_vence_at` IS NOT NULL)
            )
        ");
    }

    public function down(): void
    {
        // El CHECK primero: MySQL no deja soltar una columna referenciada
        // por un CHECK vivo.
        DB::statement('ALTER TABLE `empresas` DROP CHECK `chk_empresa_demo`');

        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn(['es_demo', 'demo_vence_at']);
        });
    }
};
