<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * password_resets — tokens de recuperación de contraseña.
 *
 * Tabla propia y no la nativa de Laravel (`password_reset_tokens`) porque hace
 * falta más de lo que esa trae: quién generó el token (autoservicio vs admin),
 * si ya se usó, y la IP desde la que se pidió. La nativa solo guarda email,
 * token y fecha, y no marca el consumo: un enlace servía hasta vencer.
 *
 * DECISIONES DE SEGURIDAD — no cambiar sin leer esto:
 *
 * 1. EL TOKEN SE GUARDA HASHEADO (sha256), nunca en claro.
 *    Un token de recuperación vale tanto como la contraseña: con él se toma la
 *    cuenta. Guardarlo en claro significaría que cualquiera que lea la base
 *    —un backup, un dump, una inyección SQL— puede entrar como cualquier
 *    usuario. Mismo criterio que password_hash.
 *
 * 2. UN SOLO USO (`usado_at`).
 *    Al consumirlo se marca la fecha. Un enlace reenviado, quedado en el
 *    historial del navegador o en el hilo de un mail no sirve dos veces.
 *
 * 3. VENCE (`expira_at`), 60 minutos.
 *    Mismo horizonte que el token de archivos de manuales.
 *
 * 4. `creado_por` distingue el origen.
 *    NULL = el usuario lo pidió por el formulario público.
 *    Con valor = un admin lo generó. Para auditoría no es lo mismo "se olvidó
 *    la contraseña" que "un administrador intervino en su cuenta".
 *
 * 5. SIN foreign key sobre `creado_por`.
 *    Si ese admin se borra, el registro del reseteo tiene que sobrevivir: es
 *    rastro de auditoría. Sobre `user_id` sí hay FK con CASCADE: si el usuario
 *    desaparece, sus tokens no tienen sentido.
 *
 * RETENCIÓN
 *   Las filas usadas o vencidas se pueden purgar sin problema — no son un
 *   registro de cumplimiento. El rastro auditable queda en activity_logs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('password_resets', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id');

            // sha256 del token que viaja en la URL. 64 chars hex.
            // Único: dos tokens no pueden colisionar, y permite buscar por hash
            // sin escanear la tabla.
            $table->char('token_hash', 64)->unique();

            $table->dateTime('expira_at');

            // NULL mientras no se haya consumido. Al usarlo, la fecha.
            $table->dateTime('usado_at')->nullable();

            // NULL = autoservicio (formulario público).
            // Con valor = el id del admin que lo generó.
            $table->unsignedBigInteger('creado_por')->nullable();

            // Desde dónde se pidió. Sirve para detectar abuso del formulario
            // público: muchas solicitudes desde una misma IP contra distintos
            // emails es enumeración.
            $table->string('ip_solicitud', 45)->nullable();

            $table->dateTime('created_at');

            // Buscar los tokens vivos de un usuario al generar uno nuevo.
            $table->index(['user_id', 'usado_at']);
            $table->index('expira_at');

            $table->foreign('user_id')
                  ->references('id')->on('users')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_resets');
    }
};
