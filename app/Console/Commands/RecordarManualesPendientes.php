<?php

namespace App\Console\Commands;

use App\Mail\RecordatorioLecturaMail;
use App\Models\User;
use App\Services\ManualAccessService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Recordatorio por mail a los socios comerciales (rol franquiciado) de los
 * manuales que todavía no leyeron.
 *
 * ── CUÁNDO CORRE ──────────────────────────────────────────────────────────
 *
 * Programado los JUEVES a las 9:00 de Argentina (routes/console.php). Sin el
 * cron de schedule:run no corre nunca y no hay error visible (README §10).
 *
 * También se puede correr a mano en cualquier momento:
 *
 *     sudo -u www-data php artisan manuales:recordar-lectura --dry-run   # ver a quién y qué
 *     sudo -u www-data php artisan manuales:recordar-lectura             # mandarlo
 *
 * Correrlo de más no molesta a nadie: una misma versión no se recuerda dos
 * veces en menos de DIAS_ENTRE_INSISTENCIAS días (ver abajo).
 *
 * ── QUÉ SE RECUERDA ───────────────────────────────────────────────────────
 *
 * Un manual entra en el recordatorio si se cumple todo:
 *   - el socio lo ve (ManualAccessService, la fuente de verdad de quién ve qué);
 *   - no leyó su versión VIGENTE (no hay fila en acceptances para esa versión);
 *   - hace 7 días o más que lo tiene disponible (ver visibleDesde());
 *   - no se le recordó esa versión en los últimos DIAS_ENTRE_INSISTENCIAS días.
 *
 * SE INSISTE HASTA QUE LEA (decisión del 18/09/2026). Cada jueves, a cada socio
 * le llega la lista de TODO lo que le sigue faltando, incluido lo que ya se le
 * recordó antes. Lo que leyó durante la semana desaparece solo de la lista,
 * porque deja de estar pendiente. La insistencia no tiene tope: se corta
 * cuando el socio lee.
 *
 * recordatorios_lectura pasó a ser el historial: enviado_at es el ÚLTIMO
 * recordatorio de esa versión y veces cuántos se mandaron. Ya no sirve para
 * excluir; lo único que excluye es DIAS_ENTRE_INSISTENCIAS, que evita que dos
 * corridas seguidas (por ejemplo, una a mano el mismo día) manden dos mails.
 *
 * UN MAIL POR SOCIO con todos sus pendientes de ese día, no uno por manual.
 *
 * ── A QUIÉN NO ────────────────────────────────────────────────────────────
 *
 * Mismo criterio que el login: cuenta inactiva o eliminada, empresa suspendida
 * o dada de baja, demo vencida, sucursal suspendida. No pueden entrar, así que
 * un mail que los invita a leer sobra.
 *
 * ── REGISTRO DESPUÉS DE ENVIAR ────────────────────────────────────────────
 *
 * Se registra solo si send() devolvió un mensaje. Devuelve null si el envío se
 * canceló (por ejemplo, el tope diario de mails de una demo, LimiteMailsDemo),
 * y lanza excepción si falló. En los dos casos no se registra, y en la próxima
 * corrida vuelve a salir. Un recordatorio de más es mejor que uno perdido.
 *
 * ── RESEND ────────────────────────────────────────────────────────────────
 *
 * Los mails salen de a uno, con una pausa corta entre cada uno: Resend limita
 * las requests por segundo, y la primera corrida manda de golpe a todos los
 * socios con pendientes acumulados.
 */
class RecordarManualesPendientes extends Command
{
    protected $signature = 'manuales:recordar-lectura
                            {--dry-run : Muestra a quién le llegaría y qué, sin mandar mails ni registrar nada}';

    protected $description = 'Recuerda por mail a los socios comerciales los manuales que todavía no leyeron.';

    // Días que un manual tiene que estar disponible sin leer antes del primer
    // recordatorio.
    private const DIAS = 7;

    // Mínimo entre dos recordatorios de la MISMA versión al MISMO socio. Seis y
    // no siete: con la corrida semanal los envíos quedan separados por 7 días
    // exactos, y cualquier diferencia de minutos (el cron, una corrida a mano
    // un rato antes) no tiene que saltear la semana.
    private const DIAS_ENTRE_INSISTENCIAS = 6;

    // Resend permite pocas requests por segundo: 600 ms entre mails.
    private const PAUSA_ENTRE_MAILS_US = 600000;

    public function handle(): int
    {
        $simular  = (bool) $this->option('dry-run');
        $limite   = now()->subDays(self::DIAS);
        $base     = rtrim((string) (config('app.frontend_url') ?: config('app.url')), '/');
        $enviados = 0;
        $primero  = true;

        $socios = User::where('rol', 'franquiciado')
                      ->where('activo', 1)
                      ->whereNull('deleted_at')
                      ->whereNotNull('empresa_id')
                      ->whereNotNull('email')
                      ->where('email', '!=', '')
                      ->with(['empresa', 'franchiseStaff.franquicia'])
                      ->orderBy('id')
                      ->get();

        foreach ($socios as $socio) {
            if (!$this->puedeIngresar($socio)) {
                continue;
            }

            $pendientes = $this->pendientes($socio, $limite);
            if (!$pendientes) {
                continue;
            }

            $titulos = array_column($pendientes, 'titulo');

            if ($simular) {
                $this->line("[dry-run] {$socio->email}: " . implode(' | ', $titulos));
                continue;
            }

            if (!$primero) {
                usleep(self::PAUSA_ENTRE_MAILS_US);
            }
            $primero = false;

            try {
                $mensaje = Mail::to($socio->email)->send(new RecordatorioLecturaMail(
                    nombre:  trim("{$socio->nombre} {$socio->apellido}") ?: 'socio',
                    titulos: $titulos,
                    url:     $base . '/mis-manuales.php',
                ));
            } catch (\Throwable $e) {
                Log::error('Recordatorio de lectura: no se pudo enviar.', [
                    'user_id' => $socio->id,
                    'error'   => $e->getMessage(),
                ]);
                $this->error("user {$socio->id}: falló el envío (ver log).");
                continue;
            }

            if ($mensaje === null) {
                // Cancelado antes de salir (tope de mails de una demo).
                $this->warn("user {$socio->id}: envío cancelado, sale en la próxima corrida.");
                continue;
            }

            // Una fila por socio y versión: se actualiza la fecha del último
            // recordatorio y se suma uno al contador. Así queda registrado
            // cuántas veces hizo falta insistir antes de que leyera.
            $ahora = now();
            foreach ($pendientes as $p) {
                $fila = DB::table('recordatorios_lectura')
                          ->where('user_id', $socio->id)
                          ->where('manual_version_id', $p['version_id']);

                if ($fila->exists()) {
                    $fila->update(['enviado_at' => $ahora, 'veces' => DB::raw('veces + 1')]);
                } else {
                    DB::table('recordatorios_lectura')->insert([
                        'user_id'           => $socio->id,
                        'manual_version_id' => $p['version_id'],
                        'enviado_at'        => $ahora,
                        'veces'             => 1,
                    ]);
                }
            }

            $enviados++;
            $this->info("user {$socio->id}: recordatorio con " . count($pendientes) . ' manual(es).');
        }

        if (!$simular) {
            Log::info('Recordatorio de lectura: corrida terminada.', ['mails' => $enviados]);
            $this->line("Mails enviados: {$enviados}");
        }

        return self::SUCCESS;
    }

    /** Mismo criterio que AuthController::login(). */
    private function puedeIngresar(User $socio): bool
    {
        $empresa = $socio->empresa;
        if (!$empresa || !$empresa->activa || $empresa->deleted_at !== null || $empresa->demoVencida()) {
            return false;
        }

        $sucursal = optional($socio->franchiseStaff)->franquicia;

        return !($sucursal && !$sucursal->activa);
    }

    /**
     * Manuales que hay que recordarle hoy a este socio.
     *
     * @return array<int, array{version_id:int, titulo:string}>
     */
    private function pendientes(User $socio, Carbon $limite): array
    {
        // Versiones que YA se le recordaron hace poco: no se insiste dos veces
        // en la misma semana. Lo recordado hace más tiempo vuelve a entrar.
        //
        // intval: in_array estricto de abajo no puede depender de si el driver
        // devuelve los ids como int o como string.
        $recordadasReciente = array_map('intval', DB::table('recordatorios_lectura')
                          ->where('user_id', $socio->id)
                          ->where('enviado_at', '>', now()->subDays(self::DIAS_ENTRE_INSISTENCIAS))
                          ->pluck('manual_version_id')
                          ->all());

        $resultado = [];

        foreach (ManualAccessService::manualesVisiblesParaUsuario($socio) as $manual) {
            $version = $manual->versionActiva->first();

            // Sin versión vigente no hay nada que leer; si ya la leyó, tampoco.
            if (!$version || $manual->mi_aceptacion) {
                continue;
            }
            if (in_array((int) $version->id, $recordadasReciente, true)) {
                continue;
            }

            $desde = $this->visibleDesde($socio, $manual->id, $version->publicado_at);
            if ($desde === null || $desde->gt($limite)) {
                continue;
            }

            // La versión se aclara solo si no es la 1.0: en un manual que el
            // socio ya había leído, "Manual X" solo haría pensar que es un error.
            $etiqueta = $version->version_number . '.' . $version->version_minor;
            $resultado[] = [
                'version_id' => $version->id,
                'titulo'     => $etiqueta === '1.0'
                    ? $manual->titulo
                    : "{$manual->titulo} (versión {$etiqueta})",
            ];
        }

        return $resultado;
    }

    /**
     * Desde cuándo el socio tiene disponible la versión vigente de un manual.
     *
     * Es la fecha MÁS TARDÍA entre:
     *   - la asignación del manual a su empresa;
     *   - su acceso al manual: el primer camino que se lo dio (asignación
     *     individual, o categoría: la más tardía entre el manual entrando a la
     *     categoría y el socio entrando a ella);
     *   - la publicación de la versión vigente.
     *
     * Así, un manual asignado hace un mes y con una versión publicada ayer no se
     * recuerda hasta dentro de 6 días.
     *
     * Las asignaciones sin fecha (filas viejas) cuentan como "desde siempre".
     */
    private function visibleDesde(User $socio, int $manualId, $publicadoAt): ?Carbon
    {
        $siempre = '1970-01-01 00:00:00';

        $empresa = DB::table('manual_empresa_assignments')
                     ->where('manual_id', $manualId)
                     ->where('empresa_id', $socio->empresa_id)
                     ->value(DB::raw("COALESCE(asignado_at, '$siempre')"));

        $individual = DB::table('manual_user_assignments')
                        ->where('manual_id', $manualId)
                        ->where('user_id', $socio->id)
                        ->where('empresa_id', $socio->empresa_id)
                        ->value(DB::raw("COALESCE(assigned_at, '$siempre')"));

        $categoria = DB::table('manual_category_assignments as mca')
                       ->join('user_categories as uc', 'uc.category_id', '=', 'mca.category_id')
                       ->join('franchise_categories as fc', function ($j) {
                           $j->on('fc.id', '=', 'mca.category_id')->where('fc.is_active', 1);
                       })
                       ->where('mca.manual_id', $manualId)
                       ->where('mca.empresa_id', $socio->empresa_id)
                       ->where('uc.user_id', $socio->id)
                       ->value(DB::raw("MIN(GREATEST(COALESCE(mca.assigned_at, '$siempre'), COALESCE(uc.assigned_at, '$siempre')))"));

        $accesos = array_filter([$individual, $categoria]);
        if (!$accesos || $empresa === null || $publicadoAt === null) {
            return null;
        }

        $fechas = [
            Carbon::parse($empresa, 'UTC'),
            Carbon::parse(min($accesos), 'UTC'),
            Carbon::parse($publicadoAt, 'UTC'),
        ];

        return max($fechas);
    }
}
