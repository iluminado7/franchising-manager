<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * v2.3 — Paso 19/19 — Seed de visibilidad histórica
 *
 * Decisiones de producto aplicadas (sección 10 del documento v2.3):
 *   10.1 — Opción B: crear categoría "General" por empresa y asignar todos
 *          los manuales/documentos ya publicados a ella.
 *   10.2 — Sin categoría = no ve nada. Por eso también asignamos la categoría
 *          "General" a todos los franquiciados y empleados existentes.
 *   10.3 — Empleados con categorías propias: se les asigna "General" igual que
 *          a los franquiciados (independiente del padre).
 *
 * Para usuarios y manuales/documentos creados a partir de ahora, la asignación
 * a categoría es responsabilidad de la aplicación, no de esta migración.
 *
 * El campo assigned_by se completa con el primer super_admin activo encontrado.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $now = Carbon::now();

            // Super_admin para usar como assigned_by en todas las asignaciones masivas
            $superAdmin = DB::table('users')
                ->where('rol', 'super_admin')
                ->where('activo', 1)
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->first();

            if (!$superAdmin) {
                throw new \RuntimeException(
                    "No hay ningún super_admin activo. Crear uno antes de ejecutar esta migración."
                );
            }

            $empresas = DB::table('empresas')->where('activa', 1)->get();

            if ($empresas->isEmpty()) {
                // No hay empresas activas — nada que seedear. No es error.
                return;
            }

            foreach ($empresas as $empresa) {
                // 1. Crear (o recuperar) la categoría "General" de esta empresa
                $existing = DB::table('franchise_categories')
                    ->where('empresa_id', $empresa->id)
                    ->where('name', 'General')
                    ->first();

                if ($existing) {
                    $categoryId = $existing->id;
                } else {
                    $categoryId = DB::table('franchise_categories')->insertGetId([
                        'empresa_id'  => $empresa->id,
                        'name'        => 'General',
                        'description' => 'Categoría por defecto creada en la migración v2.3. ' .
                                         'Conserva la visibilidad histórica de manuales y documentos.',
                        'is_active'   => 1,
                        'created_at'  => $now,
                        'updated_at'  => $now,
                    ]);
                }

                // 2. Asignar todos los manuales de la empresa a "General"
                //    (cruza vía manual_empresa_assignments, que es el scope manual ↔ empresa)
                $manualIds = DB::table('manual_empresa_assignments')
                    ->where('empresa_id', $empresa->id)
                    ->pluck('manual_id');

                foreach ($manualIds as $manualId) {
                    DB::table('manual_category_assignments')->insertOrIgnore([
                        'empresa_id'  => $empresa->id,
                        'manual_id'   => $manualId,
                        'category_id' => $categoryId,
                        'assigned_by' => $superAdmin->id,
                        'assigned_at' => $now,
                    ]);
                }

                // 3. Asignar todos los documentos de la empresa a "General"
                $documentIds = DB::table('documents')
                    ->where('empresa_id', $empresa->id)
                    ->whereNull('deleted_at')
                    ->pluck('id');

                foreach ($documentIds as $docId) {
                    DB::table('document_category_assignments')->insertOrIgnore([
                        'empresa_id'  => $empresa->id,
                        'document_id' => $docId,
                        'category_id' => $categoryId,
                        'assigned_by' => $superAdmin->id,
                        'assigned_at' => $now,
                    ]);
                }

                // 4. Asignar la categoría "General" a todos los franquiciados y empleados
                //    activos de esta empresa (decisión 10.2)
                $userIds = DB::table('users')
                    ->where('empresa_id', $empresa->id)
                    ->whereIn('rol', ['franquiciado', 'empleado'])
                    ->where('activo', 1)
                    ->whereNull('deleted_at')
                    ->pluck('id');

                foreach ($userIds as $userId) {
                    DB::table('user_categories')->insertOrIgnore([
                        'user_id'     => $userId,
                        'category_id' => $categoryId,
                        'empresa_id'  => $empresa->id,
                        'assigned_by' => $superAdmin->id,
                        'assigned_at' => $now,
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        throw new \RuntimeException(
            "Migración no reversible: el seed de visibilidad histórica creó la categoría 'General' " .
            "y múltiples asignaciones masivas. Para revertir, restaurar backup completo. " .
            "Si solo querés deshacer el seed sin restaurar backup, eliminar manualmente las filas " .
            "donde franchise_categories.name = 'General' (ON DELETE CASCADE limpia el resto)."
        );
    }
};
