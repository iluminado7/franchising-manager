<?php

namespace App\Http\Controllers;

use App\Models\ManualNote;
use App\Models\ManualVersion;
use App\Models\Manual;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use App\Models\ActivityLog;
use App\Services\ManualAccessService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ManualNoteController extends Controller
{
    // Relaciones que se cargan para mostrar cada nota (incluye autor + franquicia del autor).
    private const RELACIONES = ['empresa', 'version', 'autor.franchiseStaff.franquicia'];

    // GET /api/manuales/{manualId}/notas
    // Devuelve un hilo unificado que combina:
    //   - feedback (tabla manual_notes) de franquiciantes/franquiciados
    //   - release notes (manual_versions.nota_publicacion) escritas por quien publicó la versión
    // Cada item lleva un campo "tipo" ('feedback' o 'release') para que el front les dé estilo distinto.
    //
    // Visibilidad de feedback:
    //   super_admin: todas las notas del manual (de todas las empresas).
    //   franquiciante: todas las de su empresa (propias + de sus franquiciados).
    //   franquiciado: solo las propias.
    //   empleado: sin acceso.
    //
    // Visibilidad de release notes: TODOS los roles autorizados las ven (son anuncios públicos
    // del publicador a todos los franquiciados que tengan acceso al manual).
    public function porManual(Request $request, int $manualId): JsonResponse
    {
        $user = $request->user();

        // ── 1) Feedback (manual_notes) según visibilidad por rol ────────────
        $query = ManualNote::with(self::RELACIONES)
                           ->where('manual_id', $manualId);

        if ($user->esSuperAdmin()) {
            // todas
        } elseif ($user->esFranquiciante()) {
            $query->where('empresa_id', $user->empresa_id);
        } elseif ($user->esFranquiciado()) {
            $query->where('empresa_id', $user->empresa_id)
                  ->where('user_id', $user->id);
        } else {
            abort(403, 'Sin acceso a las notas.');
        }

        $feedback = $query->get()->map(function ($n) {
            // Convertimos a array y agregamos el tipo para uniformar con release notes.
            $arr = $n->toArray();
            $arr['tipo'] = 'feedback';
            return $arr;
        });

        // ── 2) Release notes (manual_versions.nota_publicacion) ─────────────
        $releases = ManualVersion::with(['publicadoPor.systemAdmin', 'publicadoPor.superAdmin', 'publicadoPor.franchiseStaff.franquicia'])
                                 ->where('manual_id', $manualId)
                                 ->whereNotNull('nota_publicacion')
                                 ->where('nota_publicacion', '!=', '')
                                 ->orderBy('version_number')
                                 ->get()
                                 ->map(function ($v) {
                                     return [
                                         'tipo'              => 'release',
                                         'id'                => 'rel_' . $v->id,
                                         'manual_id'         => $v->manual_id,
                                         'manual_version_id' => $v->id,
                                         'contenido'         => $v->nota_publicacion,
                                         'created_at'        => $v->publicado_at,
                                         'autor'             => $v->publicadoPor,
                                         'version'           => [
                                             'id'             => $v->id,
                                             'version_number' => $v->version_number,
                                         ],
                                     ];
                                 });

        // ── 3) Mezclar y ordenar cronológicamente (más viejas primero) ─────
        $hilo = $feedback->concat($releases)
                         ->sortBy(fn($item) => $item['created_at'] ?? '')
                         ->values();

        return response()->json($hilo);
    }

    // POST /api/manuales/{manualId}/notas — franquiciante y franquiciado
    // Agrega una nota (sugerencia) al hilo del manual.
    //
    // H-009 fix: ahora valida acceso EFECTIVO al manual (no solo asignación a
    // la empresa). Antes, un franquiciado de la empresa X podía escribir notas
    // en cualquier manual de X aunque no tuviera la categoría asignada para
    // verlo. El servicio maneja la lógica por rol:
    //   super_admin    → siempre pasa (no debería llegar acá, la ruta lo excluye)
    //   franquiciante  → manual asignado a su empresa (comportamiento previo)
    //   franquiciado   → categoría activa OR asignación individual (NUEVO)
    //   empleado       → mismo criterio (aunque la ruta lo bloquea antes)
    public function store(Request $request, int $manualId): JsonResponse
    {
        $request->validate([
            'contenido' => 'required|string|max:5000',
        ]);

        $user = $request->user();

        if (!ManualAccessService::usuarioTieneAccesoAlManual($user, $manualId)) {
            return response()->json(['error' => 'Sin acceso a este manual.'], 403);
        }

        // Versión activa del manual al momento de escribir la nota (puede no existir)
        $versionActivaId = ManualVersion::where('manual_id', $manualId)
                                        ->where('es_activa', 1)
                                        ->value('id');

        $nota = ManualNote::create([
            'manual_id'         => $manualId,
            'empresa_id'        => $user->empresa_id,   // del usuario, nunca del request
            'manual_version_id' => $versionActivaId,    // null si el manual aún no tiene versión publicada
            'user_id'           => $user->id,
            'contenido'         => $request->contenido,
            'estado'            => 'pendiente',
        ]);

        // Avisarle al franquiciante que tiene feedback nuevo.
        //
        // Solo cuando la nota la escribe un SOCIO COMERCIAL: este metodo lo
        // usa tambien el franquiciante, y notificarse a si mismo no aporta.
        //
        // Va en try/catch a proposito: la nota ya esta guardada, que es lo
        // que el socio pidio. Hacer fallar la operacion por un aviso seria
        // perder lo importante para no perder lo accesorio.
        if ($user->esFranquiciado()) {
            try {
                $this->notificarNotaAlFranquiciante($manualId, $user, $nota);
            } catch (\Throwable $e) {
                Log::warning('No se pudo notificar la nota al franquiciante', [
                    'manual_id' => $manualId,
                    'nota_id'   => $nota->id ?? null,
                    'error'     => $e->getMessage(),
                ]);
            }
        }

        ActivityLog::registrar(
            userId:      $user->id,
            accion:      'nota_manual_enviada',
            ip:          $request->ip(),
            empresaId:   $user->empresa_id,
            entidadTipo: 'manual_notes',
            entidadId:   $nota->id,
            userAgent:   $request->userAgent()
        );

        return response()->json($nota->load(self::RELACIONES), 201);
    }

    /**
     * Notifica a los franquiciantes de la empresa que hay una nota nueva.
     *
     * Pueden ser varios: se le avisa a todos los activos. Si la empresa no
     * tiene ninguno cargado, no se notifica a nadie — NO se cae al
     * super_admin. El feedback de una red es del franquiciante, y desviarlo
     * en silencio a la plataforma seria una sorpresa desagradable.
     *
     * El tipo 'nota_manual' vive en la rama de chk_notif_fk que exige
     * manual_id y prohibe el resto de las FKs (ver la migracion
     * add_nota_manual_to_chk_notif_fk). Mandar manual_version_id aca haria
     * fallar el INSERT.
     */
    private function notificarNotaAlFranquiciante(int $manualId, User $autor, ManualNote $nota): void
    {
        if (empty($autor->empresa_id)) {
            return;
        }

        $destinatarios = User::where('empresa_id', $autor->empresa_id)
                             ->where('rol', 'franquiciante')
                             ->where('activo', 1)
                             ->whereNull('deleted_at')
                             ->get();

        if ($destinatarios->isEmpty()) {
            return;
        }

        $titulo = Manual::whereKey($manualId)->value('titulo') ?: 'un manual';
        $quien  = trim("{$autor->nombre} {$autor->apellido}") ?: 'Un socio comercial';

        // El texto se recorta: contenido admite 5000 caracteres y esto entra
        // en el cuerpo del mail. El hilo completo se lee en la pantalla.
        $extracto = mb_substr(trim($nota->contenido), 0, 400);
        if (mb_strlen(trim($nota->contenido)) > 400) {
            $extracto .= '…';
        }

        // El titulo nombra a quien escribio: es lo primero que se lee en el
        // badge y es el asunto del mail, y "alguien dejo una nota" obliga a
        // abrir para saber de quien.
        //
        // notifications.titulo es varchar(200) y se recorta el TITULO DEL
        // MANUAL, no la cadena entera: si se cortara al final, un nombre
        // largo dejaria "Juan Sebastián Ferná…" sin decir nunca en que
        // manual. De los dos datos, el prescindible es el manual.
        $prefijo = "{$quien} dejó una nota en: ";
        $espacio = 200 - mb_strlen($prefijo);
        $tituloNotif = $espacio > 0
            ? $prefijo . mb_substr($titulo, 0, $espacio)
            : $prefijo;

        // Red final: si el nombre solo pasara los 200, la columna rechazaria
        // el INSERT y la nota se quedaria sin avisar a nadie.
        $tituloNotif = mb_substr($tituloNotif, 0, 200);

        foreach ($destinatarios as $destinatario) {
            $n = new Notification([
                'tipo'      => 'nota_manual',
                'manual_id' => $manualId,
                'titulo'    => $tituloNotif,
                // El cuerpo ya no repite el nombre: lo dice el titulo.
                'mensaje'   => "«{$extracto}»",
                'leida'     => 0,
            ]);
            // V2-H-020: user_id esta fuera de $fillable, setter directo.
            $n->user_id = $destinatario->id;
            $n->save();
        }
    }

    // PUT /api/notas/{id}/estado — super_admin o franquiciante de la empresa
    // Marca una nota como pendiente / leida / resuelta.
    // Reglas (v2.3):
    //   - super_admin: puede gestionar el estado de cualquier nota, EXCEPTO las propias
    //   - franquiciante: solo notas de SU empresa, EXCEPTO las propias
    //   - nadie más
    public function updateEstado(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'estado' => 'required|in:pendiente,leida,resuelta',
        ]);

        $nota = ManualNote::findOrFail($id);
        $user = $request->user();

        // No podés cambiar el estado de tus propias notas.
        if ((int) $nota->user_id === (int) $user->id) {
            return response()->json([
                'error' => 'No podés cambiar el estado de tus propias notas.',
            ], 403);
        }

        // Si es franquiciante, debe ser de la misma empresa de la nota.
        if ($user->esFranquiciante() && (int) $nota->empresa_id !== (int) $user->empresa_id) {
            return response()->json([
                'error' => 'Sin acceso a esta nota.',
            ], 403);
        }

        // Otros roles ya están bloqueados por la ruta (middleware role:...),
        // pero por defensa en profundidad lo repetimos acá.
        if (!$user->esSuperAdmin() && !$user->esFranquiciante()) {
            return response()->json(['error' => 'Sin permisos.'], 403);
        }

        $nota->estado = $request->estado;
        $nota->save();

        ActivityLog::registrar(
            userId:      $user->id,
            accion:      'nota_manual_estado',
            ip:          $request->ip(),
            empresaId:   $nota->empresa_id,
            entidadTipo: 'manual_notes',
            entidadId:   $nota->id,
            userAgent:   $request->userAgent()
        );

        return response()->json($nota->load(self::RELACIONES));
    }
}