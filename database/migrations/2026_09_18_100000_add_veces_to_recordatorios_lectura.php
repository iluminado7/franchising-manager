<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El recordatorio de lectura pasa a INSISTIR cada semana hasta que el socio
 * lea, en vez de avisar una sola vez (decisión del 18/09/2026).
 *
 * La tabla cambia de sentido: antes una fila significaba "ya se le recordó, no
 * se le recuerda más". Ahora es el historial de la insistencia:
 *
 *   - enviado_at : cuándo fue el ÚLTIMO recordatorio de esa versión.
 *   - veces      : cuántos se le mandaron.
 *
 * Sigue habiendo una sola fila por socio y versión (la clave única no cambia):
 * lo que se actualiza es la fecha y el contador. Así, cuando el socio lee,
 * queda registrado cuántas veces hizo falta insistirle.
 *
 * Las filas que ya existen son de la corrida del 17/09: quedan con veces = 1,
 * que es exactamente lo que pasó.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recordatorios_lectura', function (Blueprint $table) {
            $table->unsignedInteger('veces')
                  ->default(1)
                  ->after('enviado_at')
                  ->comment('Cuántos recordatorios se mandaron de esta versión a este socio.');
        });
    }

    public function down(): void
    {
        Schema::table('recordatorios_lectura', function (Blueprint $table) {
            $table->dropColumn('veces');
        });
    }
};
