<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ── FRANQUICIAS — agregar empresa_id ─────────────────
        if (!Schema::hasColumn('franquicias', 'empresa_id')) {
            Schema::table('franquicias', function (Blueprint $table) {
                $table->unsignedBigInteger('empresa_id')->nullable()->after('id');
                $table->index('empresa_id');
            });
            Schema::table('franquicias', function (Blueprint $table) {
                $table->foreign('empresa_id')->references('id')->on('empresas')->nullOnDelete();
            });
        }

        // ── USERS — agregar empresa_id y celular ──────────────
        if (!Schema::hasColumn('users', 'empresa_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedBigInteger('empresa_id')->nullable()->after('id');
                $table->index('empresa_id');
            });

            // CHECK antes de la FK
            DB::statement("ALTER TABLE users ADD CONSTRAINT chk_empresa_rol CHECK (
                (rol = 'super_admin' AND empresa_id IS NULL)
                OR
                (rol != 'super_admin' AND empresa_id IS NOT NULL)
            )");

            Schema::table('users', function (Blueprint $table) {
                $table->foreign('empresa_id')->references('id')->on('empresas')->nullOnDelete();
            });
        }

        if (!Schema::hasColumn('users', 'celular')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('celular', 30)->nullable()->after('activo');
            });
        }

        // Ampliar ENUM rol para incluir super_admin
        DB::statement("ALTER TABLE users MODIFY COLUMN rol
            ENUM('super_admin','franquiciante','franquiciado','empleado') NOT NULL");

        // ── SYSTEM_ADMINS — agregar dni ───────────────────────
        if (!Schema::hasColumn('system_admins', 'dni')) {
            Schema::table('system_admins', function (Blueprint $table) {
                $table->string('dni', 15)->nullable()->after('apellido');
            });
        }

        // ── FRANCHISE_STAFF — agregar dni ────────────────────
        if (!Schema::hasColumn('franchise_staff', 'dni')) {
            Schema::table('franchise_staff', function (Blueprint $table) {
                $table->string('dni', 15)->nullable()->after('apellido');
            });
        }

        // ── MANUAL_ASSIGNMENTS — agregar empresa_id ───────────
        if (!Schema::hasColumn('manual_assignments', 'empresa_id')) {
            Schema::table('manual_assignments', function (Blueprint $table) {
                $table->unsignedBigInteger('empresa_id')->nullable()->after('user_id');
                $table->index('empresa_id');
            });
            Schema::table('manual_assignments', function (Blueprint $table) {
                $table->foreign('empresa_id')->references('id')->on('empresas')->nullOnDelete();
            });
        }

        // ── ACCEPTANCES — agregar empresa_id ──────────────────
        if (!Schema::hasColumn('acceptances', 'empresa_id')) {
            Schema::table('acceptances', function (Blueprint $table) {
                $table->unsignedBigInteger('empresa_id')->nullable()->after('user_id');
                $table->index('empresa_id');
            });
            Schema::table('acceptances', function (Blueprint $table) {
                $table->foreign('empresa_id')->references('id')->on('empresas')->nullOnDelete();
            });
        }

        // ── DOCUMENTS — agregar empresa_id ────────────────────
        if (!Schema::hasColumn('documents', 'empresa_id')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->unsignedBigInteger('empresa_id')->nullable()->after('id');
                $table->index('empresa_id');
            });
            Schema::table('documents', function (Blueprint $table) {
                $table->foreign('empresa_id')->references('id')->on('empresas')->nullOnDelete();
            });
        }

        // ── ACTIVITY_LOGS — agregar empresa_id ────────────────
        if (!Schema::hasColumn('activity_logs', 'empresa_id')) {
            Schema::table('activity_logs', function (Blueprint $table) {
                $table->unsignedBigInteger('empresa_id')->nullable()->after('user_id');
                $table->index('empresa_id');
            });
            Schema::table('activity_logs', function (Blueprint $table) {
                $table->foreign('empresa_id')->references('id')->on('empresas')->nullOnDelete();
            });
        }

        // Ampliar ENUM accion con nuevos valores multi-tenant
        DB::statement("ALTER TABLE activity_logs MODIFY COLUMN accion
            ENUM(
                'login','logout','manual_abierto','manual_aceptado','archivo_subido',
                'manual_creado','manual_editado','manual_publicado','manual_archivado',
                'manual_asignado_empresa','documento_subido','firma_fisica_subida',
                'manual_asignado','manual_desasignado','usuario_creado','usuario_desactivado',
                'franquicia_creada','empresa_creada','franquiciante_creado',
                'empresa_suspendida','invoice_generada','plan_modificado','config_modificada'
            ) NOT NULL");

        // ── NOTIFICATIONS — restaurar CHECK CONSTRAINT ────────
        // Verificar si ya existe antes de agregar
        $checks = DB::select("
            SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'notifications'
            AND CONSTRAINT_NAME = 'chk_notif_fk'
        ");
        if (empty($checks)) {
            DB::statement("ALTER TABLE notifications ADD CONSTRAINT chk_notif_fk CHECK (
                (tipo = 'nuevo_manual' AND manual_id IS NOT NULL AND manual_version_id IS NULL AND document_id IS NULL)
                OR (tipo IN ('modificacion_manual','manual_asignado') AND manual_version_id IS NOT NULL AND manual_id IS NULL AND document_id IS NULL)
                OR (tipo = 'nuevo_documento' AND document_id IS NOT NULL AND manual_id IS NULL AND manual_version_id IS NULL)
                OR (tipo = 'recordatorio_pendiente' AND manual_id IS NULL AND manual_version_id IS NULL AND document_id IS NULL)
            )");
        }
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE notifications DROP CONSTRAINT IF EXISTS chk_notif_fk');
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS chk_empresa_rol');

        foreach (['activity_logs','acceptances','manual_assignments','documents','users','franquicias'] as $table) {
            if (Schema::hasColumn($table, 'empresa_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropForeign(['empresa_id']);
                    $t->dropColumn('empresa_id');
                });
            }
        }

        if (Schema::hasColumn('users', 'celular')) {
            Schema::table('users', function (Blueprint $table) { $table->dropColumn('celular'); });
        }
        if (Schema::hasColumn('system_admins', 'dni')) {
            Schema::table('system_admins', function (Blueprint $table) { $table->dropColumn('dni'); });
        }
        if (Schema::hasColumn('franchise_staff', 'dni')) {
            Schema::table('franchise_staff', function (Blueprint $table) { $table->dropColumn('dni'); });
        }
    }
};