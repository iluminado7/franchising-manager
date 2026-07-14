<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Versionado de encabezado y pie de pagina.
 *
 * Antes vivian solo en `manuals`, con lo cual:
 *   - el historial mostraba el encabezado/pie de HOY en versiones viejas;
 *   - se podian cambiar sin publicar, sobre un manual ya aceptado;
 *   - contenido_hash (= SHA-256 de contenido_html) no los cubria, asi que el
 *     hash_verificacion de una aceptacion no certificaba lo que el usuario firmo.
 *
 * A partir de aca:
 *   manuals.encabezado_html   -> COPIA DE TRABAJO (lo que se esta editando)
 *   manual_versions.*         -> SNAPSHOT INMUTABLE congelado al publicar
 *
 * BACKFILL — supuesto explicito:
 *   No sabemos que encabezado/pie tenian las versiones historicas: nunca se
 *   guardo. Se copia el ACTUAL de `manuals` a todas ellas. Es una suposicion, no
 *   un dato. Se elige asi para que el renderizado no cambie respecto de hoy.
 *   De esta migracion en adelante, cada version nueva guarda el suyo de verdad.
 *
 * El hash se calcula en PHP (no con SHA2() de MySQL) para que sea bit a bit el
 * mismo que produce hash('sha256', ...) en el controller. Mezclar los dos podria
 * dar digests distintos por diferencias de charset/collation.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE manual_versions
            ADD COLUMN encabezado_html LONGTEXT NULL AFTER contenido_html,
            ADD COLUMN pie_pagina_html LONGTEXT NULL AFTER encabezado_html,
            ADD COLUMN documento_hash  CHAR(64)  NULL
                COMMENT 'SHA-256 de encabezado+contenido+pie (lo que el usuario realmente firma)'
                AFTER contenido_hash
        ");

        // Backfill. Precargamos los manuales en memoria (son pocos) para no hacer
        // una query por version.
        $manuales = DB::table('manuals')
                      ->select('id', 'encabezado_html', 'pie_pagina_html')
                      ->get()
                      ->keyBy('id');

        DB::table('manual_versions')
          ->select('id', 'manual_id', 'contenido_html')
          ->orderBy('id')
          ->chunk(200, function ($versiones) use ($manuales) {
              foreach ($versiones as $v) {
                  $m   = $manuales->get($v->manual_id);
                  $enc = $m->encabezado_html ?? null;
                  $pie = $m->pie_pagina_html ?? null;

                  DB::table('manual_versions')
                    ->where('id', $v->id)
                    ->update([
                        'encabezado_html' => $enc,
                        'pie_pagina_html' => $pie,
                        'documento_hash'  => hash('sha256',
                            hash('sha256', (string) $enc) .
                            hash('sha256', (string) $v->contenido_html) .
                            hash('sha256', (string) $pie)
                        ),
                    ]);
              }
          });
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE manual_versions
            DROP COLUMN encabezado_html,
            DROP COLUMN pie_pagina_html,
            DROP COLUMN documento_hash
        ");
    }
};
