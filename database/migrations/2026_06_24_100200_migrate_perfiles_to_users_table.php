<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * v2.3 — Paso 2/19
 * Copia nombre/apellido/dni desde las tres tablas de perfil a `users`.
 *  - super_admins   → users (rol = super_admin)
 *  - system_admins  → users (rol = franquiciante)
 *  - franchise_staff → users (rol = franquiciado o empleado)
 *
 * Verifica que no queden usuarios activos sin nombre/apellido al final.
 * Si hay huérfanos (usuarios sin perfil asociado), aborta para revisión manual.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            // 1. Desde super_admins
            DB::statement("
                UPDATE users u
                INNER JOIN super_admins sa ON sa.user_id = u.id
                SET u.nombre = sa.nombre,
                    u.apellido = sa.apellido,
                    u.dni = sa.dni
                WHERE u.nombre IS NULL
            ");

            // 2. Desde system_admins
            DB::statement("
                UPDATE users u
                INNER JOIN system_admins sy ON sy.user_id = u.id
                SET u.nombre = sy.nombre,
                    u.apellido = sy.apellido,
                    u.dni = sy.dni
                WHERE u.nombre IS NULL
            ");

            // 3. Desde franchise_staff
            DB::statement("
                UPDATE users u
                INNER JOIN franchise_staff fs ON fs.user_id = u.id
                SET u.nombre = fs.nombre,
                    u.apellido = fs.apellido,
                    u.dni = fs.dni
                WHERE u.nombre IS NULL
            ");

            // Verificación: no debe quedar ningún usuario activo sin nombre/apellido
            $orphans = DB::table('users')
                ->whereNull('deleted_at')
                ->where(function ($q) {
                    $q->whereNull('nombre')->orWhereNull('apellido');
                })
                ->get(['id', 'email', 'rol']);

            if ($orphans->count() > 0) {
                $list = $orphans->map(fn($u) => "  - id={$u->id} email={$u->email} rol={$u->rol}")->implode(PHP_EOL);
                throw new \RuntimeException(
                    "Quedaron {$orphans->count()} usuarios activos sin nombre/apellido tras migrar:" . PHP_EOL .
                    $list . PHP_EOL .
                    "Completar manualmente o eliminar antes de continuar."
                );
            }
        });
    }

    public function down(): void
    {
        throw new \RuntimeException(
            "Migración no reversible: los datos de nombre/apellido/dni se copiaron a users. " .
            "Para revertir, restaurar backup completo de la base."
        );
    }
};
