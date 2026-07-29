<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Nuevo tipo de notificacion: 'nota_manual'.
 *
 * Se dispara cuando un socio comercial deja una nota en un manual, para que el
 * franquiciante se entere sin tener que entrar a mirar.
 *
 * ── POR QUE SE SUMA A LA RAMA DE nuevo_manual ─────────────────────────────
 *
 * chk_notif_fk exige una combinacion exacta de FKs por tipo. La nota necesita
 * manual_id y nada mas, que es exactamente la forma de la rama de
 * 'nuevo_manual'. Se convierte esa igualdad en un IN, que es lo que el README
 * recomienda: sumar a una rama existente en vez de inventar una nueva.
 *
 * NO se usa manual_version_id (la rama de modificacion_manual) a proposito:
 * ManualNote guarda manual_version_id con la version activa, que puede ser NULL
 * si el manual todavia no publico ninguna. Esa rama exige NOT NULL, asi que una
 * nota sobre un manual sin version reventaria el INSERT.
 *
 * ── POR QUE CUATRO PASOS Y NO DROP + ADD ──────────────────────────────────
 *
 * Todas esas columnas tienen FK con ON DELETE CASCADE, y MySQL prohibe
 * acciones referenciales en columnas usadas por un CHECK. La tabla hoy tiene
 * las dos cosas —se creo en un orden que MySQL acepto— pero no esta garantizado
 * que un ADD CONSTRAINT ahora sea aceptado.
 *
 * Con DROP + ADD, si el ADD falla la tabla queda SIN NINGUNA restriccion y
 * cualquier INSERT invalido entra. El DDL en MySQL no es transaccional, asi que
 * no se revierte solo.
 *
 * Con estos cuatro pasos siempre hay al menos un CHECK activo, y si MySQL va a
 * rechazar la expresion lo hace en el paso 1, antes de tocar nada.
 */
return new class extends Migration
{
    /**
     * Expresion completa del CHECK, con 'nota_manual' sumado a la primera rama.
     *
     * Es la del esquema actual reproducida tal cual, salvo esa rama. Si alguien
     * agrega un tipo nuevo por otro lado y despues corre el down() de esta
     * migracion, ese tipo se pierde: conviene mirar el SHOW CREATE TABLE antes
     * de revertir.
     */
    private function expresion(string $primeraRama): string
    {
        return "
            (
                ({$primeraRama} AND manual_id IS NOT NULL AND manual_version_id IS NULL AND document_id IS NULL AND document_version_id IS NULL AND category_id IS NULL)
                OR (tipo IN ('modificacion_manual','manual_asignado','acceso_anomalo_pdf') AND manual_version_id IS NOT NULL AND manual_id IS NULL AND document_id IS NULL AND document_version_id IS NULL AND category_id IS NULL)
                OR (tipo = 'nuevo_documento' AND document_id IS NOT NULL AND manual_id IS NULL AND manual_version_id IS NULL AND document_version_id IS NULL AND category_id IS NULL)
                OR (tipo = 'recordatorio_pendiente' AND manual_id IS NULL AND manual_version_id IS NULL AND document_id IS NULL AND document_version_id IS NULL AND category_id IS NULL)
                OR (tipo = 'manual_asignado_categoria' AND manual_id IS NOT NULL AND category_id IS NOT NULL AND manual_version_id IS NULL AND document_id IS NULL AND document_version_id IS NULL)
                OR (tipo = 'documento_asignado' AND document_id IS NOT NULL AND manual_id IS NULL AND manual_version_id IS NULL AND document_version_id IS NULL AND category_id IS NULL)
                OR (tipo = 'documento_asignado_categoria' AND document_id IS NOT NULL AND category_id IS NOT NULL AND manual_id IS NULL AND manual_version_id IS NULL AND document_version_id IS NULL)
                OR (tipo = 'nueva_version_documento' AND document_version_id IS NOT NULL AND manual_id IS NULL AND manual_version_id IS NULL AND document_id IS NULL AND category_id IS NULL)
            )
        ";
    }

    public function up(): void
    {
        $nueva = $this->expresion("tipo IN ('nuevo_manual','nota_manual')");

        // 1. Probar la expresion nueva con OTRO nombre. Si MySQL la rechaza por
        //    las FK con CASCADE, falla aca y el CHECK original sigue en pie.
        DB::statement("ALTER TABLE notifications ADD CONSTRAINT chk_notif_fk_v2 CHECK {$nueva}");

        // 2. Recien ahora se puede soltar el viejo: v2 ya esta protegiendo.
        DB::statement("ALTER TABLE notifications DROP CHECK chk_notif_fk");

        // 3. Volver a ponerlo con el nombre de siempre. Que el paso 1 haya
        //    funcionado garantiza que este tambien.
        DB::statement("ALTER TABLE notifications ADD CONSTRAINT chk_notif_fk CHECK {$nueva}");

        // 4. Sacar el temporal. Si esto quedara, cada INSERT evaluaria el mismo
        //    CHECK dos veces.
        DB::statement("ALTER TABLE notifications DROP CHECK chk_notif_fk_v2");
    }

    public function down(): void
    {
        // Antes de revertir hay que borrar las notificaciones del tipo nuevo:
        // si quedara alguna, el CHECK viejo la rechazaria y el ALTER falla con
        // un error que no menciona la causa.
        DB::table('notifications')->where('tipo', 'nota_manual')->delete();

        $vieja = $this->expresion("tipo = 'nuevo_manual'");

        DB::statement("ALTER TABLE notifications ADD CONSTRAINT chk_notif_fk_v2 CHECK {$vieja}");
        DB::statement("ALTER TABLE notifications DROP CHECK chk_notif_fk");
        DB::statement("ALTER TABLE notifications ADD CONSTRAINT chk_notif_fk CHECK {$vieja}");
        DB::statement("ALTER TABLE notifications DROP CHECK chk_notif_fk_v2");
    }
};
