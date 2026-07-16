<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\User;
use App\Services\ManualAccessService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    // ──────────────────────────────────────────────────────────────────
    // GET /api/notificaciones
    // Devuelve las notificaciones del usuario autenticado
    // Query params: ?solo_no_leidas=1
    // ──────────────────────────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // documentVersion.document hace falta porque nueva_version_documento trae
        // document_version_id y NO document_id: sin eso no hay forma de saber a que
        // documento pertenece.
        $query = $user->notifications()
                      ->with([
                          'manual',
                          'manualVersion.manual',
                          'document',
                          'documentVersion.document',
                          'category',
                      ])
                      ->orderBy('created_at', 'desc');

        if ($request->boolean('solo_no_leidas')) {
            $query->noLeidas();
        }

        $notificaciones = $query->limit(50)->get();

        // Se calcula el destino y la disponibilidad ACA, no en el frontend.
        //
        // El frontend no tiene forma honesta de decidir esto: necesitaria hurgar
        // relaciones anidadas del JSON, conocer los estados validos de un manual y
        // reimplementar las reglas de acceso. Todo eso ya vive en PHP.
        //
        // Mismo patron que ManualController::show, que inyecta empresa_id y
        // mi_aceptacion en el modelo antes de serializarlo.
        $cacheAcceso = [];   // manual_id => bool, para no repetir queries de acceso
        foreach ($notificaciones as $n) {
            [$destino, $disponible] = $this->resolverDestino($n, $user, $cacheAcceso);
            $n->destino    = $destino;
            $n->disponible = $disponible;
        }

        return response()->json([
            'notificaciones' => $notificaciones,
            'total_no_leidas' => $request->user()
                                         ->notifications()
                                         ->noLeidas()
                                         ->count(),
        ]);
    }

    /**
     * Resuelve a donde lleva una notificacion y si el recurso sigue disponible.
     *
     * Devuelve [destino, disponible]:
     *   destino    -> ruta relativa ('lectura.php?id=5', 'documentos.php',
     *                 'mis-manuales.php') o null si no hay a donde ir
     *   disponible -> false si el recurso fue borrado, archivado, despublicado, o
     *                 el usuario ya no tiene acceso. El frontend la muestra
     *                 atenuada y con leyenda, en vez de mandarlo a un 404.
     */
    private function resolverDestino(Notification $n, User $user, array &$cacheAcceso): array
    {
        // ── MANUALES ──
        // El manual puede venir directo (manual_id) o colgando de la version
        // (modificacion_manual, manual_asignado -> manual_version_id).
        $manual = $n->manual ?? $n->manualVersion?->manual;

        if ($manual) {
            // No alcanza con deleted_at: un manual 'archivado' o vuelto a 'borrador'
            // tampoco se puede abrir. Los cuatro estados son:
            // borrador | publicado | archivado | eliminado.
            if ($manual->deleted_at !== null || $manual->estado !== 'publicado') {
                return [null, false];
            }

            // El manual pudo desasignarse de la empresa o de la categoria del usuario
            // despues de emitida la notificacion. Se consulta el mismo servicio que
            // usan todos los endpoints, memoizado por manual_id para no disparar una
            // query por notificacion (varias suelen apuntar al mismo manual).
            if (!array_key_exists($manual->id, $cacheAcceso)) {
                $cacheAcceso[$manual->id] =
                    ManualAccessService::usuarioTieneAccesoAlManual($user, $manual->id);
            }
            if (!$cacheAcceso[$manual->id]) {
                return [null, false];
            }

            // El FRANQUICIADO no navega libre: al entrar, la app lo empuja por una
            // cola de aceptacion (acepta el manual 1, lo redirige al 2, y asi).
            // Mandarlo directo al manual 5 no sirve: la cola lo devuelve al 1 igual.
            // Se lo manda a SU COLA y que ella decida el orden.
            //
            // El empleado si navega libre (la UI de aceptacion es solo para
            // 'franquiciado', ver lectura.php), asi que a el se lo manda al manual.
            if ($user->esFranquiciado()) {
                return ['mis-manuales.php', true];
            }

            return ['lectura.php?id=' . $manual->id, true];
        }

        // ── DOCUMENTOS ──
        $doc = $n->document ?? $n->documentVersion?->document;

        if ($doc) {
            if ($doc->deleted_at !== null) {
                return [null, false];
            }

            // visible_franquiciado se puede apagar despues de emitida la
            // notificacion. Para socio comercial y empleado eso equivale a que el
            // documento ya no exista.
            if (($user->esFranquiciado() || $user->esEmpleado()) && !$doc->visible_franquiciado) {
                return [null, false];
            }

            // Los documentos no tienen pagina de detalle: se va al listado. Por eso
            // no hace falta un ID en la URL — y por eso nunca puede dar 404.
            return ['documentos.php', true];
        }

        // Sin FK que resolver (ej: recordatorio_pendiente). Se marca leida, no navega.
        return [null, true];
    }

    // ──────────────────────────────────────────────────────────────────
    // POST /api/notificaciones/{id}/leer
    // Marca una notificación como leída
    // ──────────────────────────────────────────────────────────────────
    public function marcarLeida(Request $request, int $id): JsonResponse
    {
        $notificacion = Notification::where('id', $id)
                                    ->where('user_id', $request->user()->id)
                                    ->firstOrFail();

        $notificacion->marcarComoLeida();

        return response()->json(['message' => 'Notificación marcada como leída.']);
    }

    // ──────────────────────────────────────────────────────────────────
    // POST /api/notificaciones/leer-todas
    // Marca todas las notificaciones del usuario como leídas
    // ──────────────────────────────────────────────────────────────────
    public function marcarTodasLeidas(Request $request): JsonResponse
    {
        $request->user()
                ->notifications()
                ->noLeidas()
                ->update([
                    'leida'    => 1,
                    'leida_at' => now(),
                ]);

        return response()->json(['message' => 'Todas las notificaciones marcadas como leídas.']);
    }
}
