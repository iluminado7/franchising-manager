<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->bigIncrements('id'); // Secuencial: hueco = evidencia de manipulación
            $table->unsignedBigInteger('user_id');
            $table->enum('accion', [
                'login', 'logout',
                'manual_abierto', 'manual_aceptado',
                'archivo_subido',
                'manual_creado', 'manual_editado', 'manual_publicado', 'manual_archivado',
                'documento_subido', 'firma_fisica_subida',
                'manual_asignado', 'manual_desasignado',
                'usuario_creado', 'usuario_desactivado',
                'franquicia_creada', 'config_modificada'
            ]);
            // Polimórfico tolerado: no tiene CASCADE por diseño —
            // eliminar historial de auditoría es inaceptable
            $table->string('entidad_tipo', 50)->nullable()->comment('Nombre de la tabla afectada. Solo uso forense.');
            $table->unsignedBigInteger('entidad_id')->nullable()->comment('ID del registro. Puede apuntar a registros ya borrados.');
            $table->json('detalle')->nullable()->comment('Contexto adicional. Validado con JSON_SCHEMA_VALID()');
            $table->string('ip_address', 45);
            $table->string('user_agent', 500)->nullable();
            $table->dateTime('created_at')->useCurrent();

            // Sin updated_at: el log es inmutable por diseño
            // Sin onDelete cascade: nunca se borra historial de auditoría

            $table->foreign('user_id')
                  ->references('id')->on('users')
                  ->onDelete('restrict');

            $table->index(['user_id', 'created_at']);
            $table->index(['entidad_tipo', 'entidad_id']);
        });

        // CHECK CONSTRAINT con JSON_SCHEMA_VALID() — requiere MySQL 8.0
        DB::statement("
            ALTER TABLE activity_logs
            ADD CONSTRAINT chk_detalle_schema CHECK (
                detalle IS NULL OR JSON_SCHEMA_VALID(
                    '{
                        \"type\": \"object\",
                        \"properties\": {
                            \"campo\":           { \"type\": \"string\", \"maxLength\": 100 },
                            \"valor_anterior\":  { \"type\": \"string\", \"maxLength\": 500 },
                            \"valor_nuevo\":     { \"type\": \"string\", \"maxLength\": 500 },
                            \"manual_titulo\":   { \"type\": \"string\", \"maxLength\": 200 },
                            \"empleado_nombre\": { \"type\": \"string\", \"maxLength\": 200 },
                            \"version\":         { \"type\": \"integer\" }
                        },
                        \"additionalProperties\": false,
                        \"maxProperties\": 4
                    }',
                    detalle
                )
            )
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
