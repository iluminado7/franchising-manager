<?php

namespace App\Policies;

use App\Models\Manual;
use App\Models\User;
use App\Services\ManualAccessService;
use Illuminate\Auth\Access\Response;

/**
 * V2-H-014 — Autorizacion centralizada para Manual.
 *
 * ETAPA A (paridad): esta Policy NO introduce reglas nuevas. Replica
 * exactamente el gate que estaba copiado 7 veces en ManualController
 * (update, guardarBorrador, publicar, archivar, desarchivar, destroy, restore).
 *
 * Unica diferencia intencional: el `return false` final para franquiciado y
 * empleado. Hoy esos roles ya quedan bloqueados por el middleware
 * 'role:super_admin,franquiciante' de la ruta, asi que en la practica es un
 * no-op. Se agrega como defensa en profundidad: si alguien mueve una ruta de
 * grupo por error, la Policy sigue negando.
 *
 * La logica de acceso NO se reimplementa: se delega en ManualAccessService,
 * que ya es la fuente unica de verdad.
 */
class ManualPolicy
{
    /**
     * super_admin tiene acceso total al sistema (mismo criterio que
     * ManualAccessService::usuarioTieneAccesoAlManual).
     *
     * Devuelve null (no false) para el resto, para que la ejecucion siga
     * hacia el metodo especifico de la habilidad.
     */
    public function before(User $user, string $ability): ?bool
    {
        return $user->esSuperAdmin() ? true : null;
    }

    /**
     * Ver el manual y su contenido (show, versiones, imagenes).
     * Aplica a los cuatro roles.
     */
    public function ver(User $user, Manual $manual): Response
    {
        return ManualAccessService::usuarioTieneAccesoAlManual($user, $manual->id)
            ? Response::allow()
            : Response::deny('Sin acceso a este manual.');
    }

    /**
     * Gestionar el manual: editar, guardar borrador, publicar, archivar,
     * desarchivar, eliminar, restaurar.
     *
     * Solo franquiciante (con el manual asignado a su empresa) y super_admin
     * (via before). Franquiciado y empleado nunca gestionan manuales.
     */
    public function gestionar(User $user, Manual $manual): Response
    {
        if (!$user->esFranquiciante()) {
            return Response::deny('Sin permisos.');
        }

        if (!$user->empresa_id) {
            return Response::deny('Sin acceso a este manual.');
        }

        return ManualAccessService::empresaTieneAccesoAlManual($manual->id, $user->empresa_id)
            ? Response::allow()
            : Response::deny('Sin acceso a este manual.');
    }
}