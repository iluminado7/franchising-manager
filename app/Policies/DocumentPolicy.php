<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * V2-H-014 — Autorizacion centralizada para Document.
 *
 * ETAPA A (paridad). Reemplaza 8 gates copiados en DocumentController, que
 * habian divergido en 4 variantes distintas de la MISMA regla:
 *
 *   A) update            -> empresa, despues rol
 *   B) subirVersion, versiones, updateNota -> rol, despues empresa
 *   C) destroy, restore  -> SOLO empresa (sin guard de rol)
 *   D) destroyVersion, restoreVersion -> rol + empresa, mensaje 'Sin acceso.'
 *
 * Las variantes C no tenian guard de rol. Hoy quedan tapadas por el middleware
 * 'role:super_admin,franquiciante' de la ruta (api.php:129), asi que agregarlo
 * aca es defensa en profundidad, NO un cambio de comportamiento.
 *
 * La variante D devolvia 'Sin acceso.' en vez de 'Sin acceso a este documento.'.
 * Se unifica al mensaje largo.
 *
 * A diferencia de Manual, un Document pertenece a UNA sola empresa
 * (documents.empresa_id es la fuente unica de verdad), asi que no hace falta
 * consultar tabla de asignaciones: la regla es una comparacion directa.
 */
class DocumentPolicy
{
    /**
     * super_admin tiene acceso total al sistema.
     * Devuelve null (no false) para el resto, para que siga al metodo especifico.
     */
    public function before(User $user, string $ability): ?bool
    {
        return $user->esSuperAdmin() ? true : null;
    }

    /**
     * Gestionar el documento: editar metadatos, subir version, ver historial,
     * editar nota, eliminar y restaurar (documento y versiones).
     *
     * Solo franquiciante de la misma empresa, y super_admin (via before).
     * Franquiciado y empleado nunca gestionan documentos.
     */
    public function gestionar(User $user, Document $documento): Response
    {
        if (!$user->esFranquiciante()) {
            return Response::deny('Sin permisos.');
        }

        if ($documento->empresa_id !== $user->empresa_id) {
            return Response::deny('Sin acceso a este documento.');
        }

        return Response::allow();
    }
}
