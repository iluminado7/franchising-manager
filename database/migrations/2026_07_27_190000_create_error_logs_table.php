<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * error_logs — errores 5xx del servidor, agrupados por huella.
 *
 * Una fila por error UNICO (clase + archivo + linea), no por ocurrencia. Un
 * error en bucle se ve como una fila con ocurrencias=4000, no como 4000 filas
 * que tapan todo lo demas.
 *
 * Los campos de contexto (metodo, ruta, user_id, ip, trace...) son los del
 * ULTIMO caso registrado. Alcanza para diagnosticar: si hace falta el detalle
 * de una ocurrencia puntual, esta en storage/logs.
 *
 * NO se guarda el cuerpo del request: el POST a /api/login lleva la contrasena
 * en texto plano. Y la ruta va redactada — ver el hook en bootstrap/app.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('error_logs', function (Blueprint $table) {
            $table->id();

            // sha256(clase|archivo|linea). Unico: es la clave del agrupado.
            $table->char('huella', 64)->unique();

            $table->string('excepcion', 255);
            $table->text('mensaje');
            $table->string('archivo', 500);
            $table->unsignedInteger('linea')->nullable();

            // Contexto del ULTIMO caso.
            $table->string('metodo', 10)->nullable();      // GET, POST, CLI
            $table->string('ruta', 500)->nullable();       // REDACTADA
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->text('trace')->nullable();             // primeros frames

            $table->unsignedInteger('ocurrencias')->default(1);
            $table->dateTime('primera_vez');
            $table->dateTime('ultima_vez');

            // Lo marca el super_admin desde la pantalla. Una ocurrencia nueva
            // lo vuelve a 0: si reaparece, no estaba resuelto.
            $table->boolean('resuelto')->default(false);

            $table->index('ultima_vez');
            $table->index(['resuelto', 'ultima_vez']);

            // SIN foreign key sobre user_id a proposito: si un usuario se borra,
            // el registro del error tiene que sobrevivir. Es diagnostico, no
            // una relacion de negocio.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('error_logs');
    }
};
