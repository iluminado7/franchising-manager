<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Foto de perfil del usuario.
 *
 * Guarda solo la CLAVE del objeto en S3 (p. ej. "avatars/12/ab34…​.jpg"), no la
 * URL absoluta: si cambia el bucket o el CDN no hay que reescribir filas. La URL
 * publica la arma el modelo con un accessor (Storage::disk('s3')->url()).
 *
 * NULL = sin foto. Una foto por usuario (se reemplaza al subir una nueva).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE `users` ADD COLUMN `foto_url` VARCHAR(500) " .
            "COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `celular`"
        );
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE `users` DROP COLUMN `foto_url`");
    }
};
