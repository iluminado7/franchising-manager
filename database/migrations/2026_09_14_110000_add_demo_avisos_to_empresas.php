<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de los avisos de vencimiento de una empresa demo que se mandaron a
 * los super_admin (comando demos:avisar-vencimiento).
 *
 *   - demo_aviso_7d_at : cuando salio el aviso de "vence en 7 dias".
 *   - demo_aviso_1d_at : cuando salio el aviso de "vence mañana".
 *
 * Existen para dos cosas:
 *
 *   1. Que un aviso no se mande dos veces. El comando corre todos los dias, y
 *      mas de una vez si alguien lo ejecuta a mano.
 *   2. Que un aviso no se PIERDA si el cron no corrio algun dia. El comando no
 *      busca "demos que vencen exactamente en 7 dias", sino "demos que vencen
 *      en 7 dias o menos y todavia no fueron avisadas". Sin estas columnas, ese
 *      criterio mandaria el mismo aviso todos los dias.
 *
 * Las escribe PHP en UTC, igual que demo_vence_at (README §9).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dateTime('demo_aviso_7d_at')
                  ->nullable()
                  ->after('demo_vence_at')
                  ->comment('Aviso a super_admin: la demo vence en 7 dias (UTC). NULL = no enviado.');

            $table->dateTime('demo_aviso_1d_at')
                  ->nullable()
                  ->after('demo_aviso_7d_at')
                  ->comment('Aviso a super_admin: la demo vence mañana (UTC). NULL = no enviado.');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn(['demo_aviso_7d_at', 'demo_aviso_1d_at']);
        });
    }
};
