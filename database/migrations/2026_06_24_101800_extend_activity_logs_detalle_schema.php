<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * v2.3 — Paso 18/19
 * Rehace chk_detalle_schema en activity_logs con keys nuevas:
 *   - categoria_nombre   (≤100) — acciones de categoría
 *   - user_email         (≤200) — sujeto/objeto usuario
 *   - documento_titulo   (≤200) — acciones sobre documentos
 *
 * Se mantienen las keys existentes. maxProperties pasa de 4 a 5.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE activity_logs DROP CHECK chk_detalle_schema");

        DB::statement(<<<'SQL'
            ALTER TABLE activity_logs ADD CONSTRAINT chk_detalle_schema CHECK (
              detalle IS NULL OR JSON_SCHEMA_VALID('{
                "type": "object",
                "properties": {
                  "campo":            { "type": "string", "maxLength": 100 },
                  "valor_anterior":   { "type": "string", "maxLength": 500 },
                  "valor_nuevo":      { "type": "string", "maxLength": 500 },
                  "manual_titulo":    { "type": "string", "maxLength": 200 },
                  "empleado_nombre":  { "type": "string", "maxLength": 200 },
                  "version":          { "type": "integer" },
                  "categoria_nombre": { "type": "string", "maxLength": 100 },
                  "user_email":       { "type": "string", "maxLength": 200 },
                  "documento_titulo": { "type": "string", "maxLength": 200 }
                },
                "additionalProperties": false,
                "maxProperties": 5
              }', detalle)
            )
        SQL);
    }

    public function down(): void
    {
        // Restaurar chk_detalle_schema original de v1.7
        DB::statement("ALTER TABLE activity_logs DROP CHECK chk_detalle_schema");

        DB::statement(<<<'SQL'
            ALTER TABLE activity_logs ADD CONSTRAINT chk_detalle_schema CHECK (
              detalle IS NULL OR JSON_SCHEMA_VALID('{
                "type": "object",
                "properties": {
                  "campo":           { "type": "string", "maxLength": 100 },
                  "valor_anterior":  { "type": "string", "maxLength": 500 },
                  "valor_nuevo":     { "type": "string", "maxLength": 500 },
                  "manual_titulo":   { "type": "string", "maxLength": 200 },
                  "empleado_nombre": { "type": "string", "maxLength": 200 },
                  "version":         { "type": "integer" }
                },
                "additionalProperties": false,
                "maxProperties": 4
              }', detalle)
            )
        SQL);
    }
};
