<?php

namespace App\Console\Commands;

use App\Mail\AvisoVencimientoDemoMail;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Avisa por mail a los super_admin las empresas demo que están por vencer:
 * 7 días antes y el día anterior. Lo programa routes/console.php una vez por
 * día.
 *
 * ── DÍAS DE CALENDARIO, NO HORAS ──────────────────────────────────────────
 *
 * "Vence mañana" se calcula por FECHA en hora de Argentina, no por 24 horas.
 * El comando corre una vez al día: si una demo vence el 14/10 a las 12:00 y
 * se contaran horas, a las 9:00 del 13/10 faltarían 27 horas ("todavía no"), y
 * el aviso saldría recién el 14 a las 9:00, tres horas antes del corte.
 *
 * ── UMBRAL + REGISTRO, NO FECHA EXACTA ────────────────────────────────────
 *
 * No busca "demos que vencen exactamente en 7 días", sino "en 7 días o menos
 * y todavía no avisadas" (empresas.demo_aviso_7d_at / demo_aviso_1d_at). Con
 * una fecha exacta, un día en que el cron no corra se come el aviso para
 * siempre. Con el umbral, sale en la próxima corrida; con el registro, no se
 * repite.
 *
 * Si ya corresponde el de "mañana", no se manda además el de 7 días atrasado:
 * dos mails el mismo día sobre la misma demo es ruido.
 *
 * ── EL AVISO SE MARCA DESPUÉS DE MANDARLO ─────────────────────────────────
 *
 * Solo si llegó a salir al menos un mail. Si fallan todos, queda sin marcar y
 * se reintenta en la próxima corrida. El orden inverso (marcar y después
 * mandar) pierde el aviso en silencio si el envío falla; este, en el peor caso
 * (se cae entre el envío y la marca), lo duplica. Un aviso de más es mejor que
 * uno perdido.
 *
 * ── REQUIERE EL CRON ──────────────────────────────────────────────────────
 *
 * Sin `schedule:run` en el crontab del servidor esto no corre nunca, y no hay
 * ningún error visible (README §10).
 */
class AvisarVencimientoDemos extends Command
{
    protected $signature = 'demos:avisar-vencimiento
                            {--dry-run : Muestra qué avisos saldrían, sin mandar mails ni marcar nada}';

    protected $description = 'Avisa por mail a los super_admin las empresas demo que vencen en 7 días o mañana.';

    // El negocio opera en Argentina: "mañana" es mañana acá, aunque la app y
    // las fechas guardadas estén en UTC.
    private const ZONA = 'America/Argentina/Buenos_Aires';

    private const DIAS_AVISO_ANTICIPADO = 7;

    public function handle(): int
    {
        $simular = (bool) $this->option('dry-run');
        $ahora   = now();
        $hoy     = $ahora->copy()->timezone(self::ZONA)->startOfDay();

        $superAdmins = User::where('rol', 'super_admin')
                           ->where('activo', 1)
                           ->whereNull('deleted_at')
                           ->whereNotNull('email')
                           ->where('email', '!=', '')
                           ->get();

        // Candidatas: demos vigentes, no dadas de baja ni suspendidas, que
        // vencen dentro del horizonte del aviso anticipado. Se trae un día de
        // margen y la decisión fina (por fecha de calendario) va en PHP.
        //
        // Una demo suspendida se saltea: alguien la frenó a propósito, y
        // avisar que "se termina la prueba" de algo ya cortado no sirve.
        $demos = Empresa::where('es_demo', 1)
                        ->whereNull('deleted_at')
                        ->where('activa', 1)
                        ->where('demo_vence_at', '>', $ahora)
                        ->where('demo_vence_at', '<=', $ahora->copy()->addDays(self::DIAS_AVISO_ANTICIPADO + 1))
                        ->orderBy('demo_vence_at')
                        ->get();

        $enviados = 0;

        foreach ($demos as $empresa) {
            $diaVence = $empresa->demo_vence_at->copy()->timezone(self::ZONA)->startOfDay();
            $dias     = (int) round($hoy->diffInDays($diaVence, false));

            $aviso = $this->avisoQueCorresponde($empresa, $dias);
            if ($aviso === null) {
                continue;
            }

            $cuandoVence = match (true) {
                $dias <= 0 => 'vence hoy',
                $dias === 1 => 'vence mañana',
                default    => "vence en {$dias} días",
            };
            $asunto = "La prueba de {$empresa->nombre} {$cuandoVence}";

            if ($superAdmins->isEmpty()) {
                // Sin destinatarios no se marca nada: si mañana se da de alta
                // un super_admin, el aviso todavía puede salir.
                $this->warn("{$empresa->nombre}: correspondía avisar, pero no hay super_admin activos con email.");
                Log::warning('AvisarVencimientoDemos: no hay super_admin a quien avisar.', ['empresa_id' => $empresa->id]);
                continue;
            }

            if ($simular) {
                $this->line("[dry-run] {$asunto} -> " . $superAdmins->pluck('email')->implode(', '));
                continue;
            }

            $mail = fn (User $sa) => new AvisoVencimientoDemoMail(
                nombreDestinatario: trim("{$sa->nombre} {$sa->apellido}") ?: 'administrador',
                asunto:             $asunto,
                cuandoVence:        $cuandoVence,
                fechaVence:         $empresa->demo_vence_at->copy()->timezone(self::ZONA)->format('d/m/Y H:i'),
                empresa:            [
                    'nombre'       => $empresa->nombre,
                    'razon_social' => $empresa->razon_social,
                    'cuit'         => $empresa->cuit,
                ],
                franquiciantes:     $this->franquiciantes($empresa),
                emailsContacto:     $empresa->emails()->where('tipo', 'contacto')->pluck('email')->all(),
                uso:                $this->uso($empresa),
                urlEmpresas:        rtrim((string) (config('app.frontend_url') ?: config('app.url')), '/') . '/empresas.php',
            );

            $okEmpresa = 0;
            foreach ($superAdmins as $sa) {
                try {
                    Mail::to($sa->email)->send($mail($sa));
                    $okEmpresa++;
                } catch (\Throwable $e) {
                    // Sin el mensaje completo del throwable en la salida: puede
                    // arrastrar datos del servidor de mail.
                    Log::error('AvisarVencimientoDemos: no se pudo enviar el aviso.', [
                        'empresa_id' => $empresa->id,
                        'user_id'    => $sa->id,
                        'aviso'      => $aviso,
                        'error'      => $e->getMessage(),
                    ]);
                    $this->error("{$empresa->nombre}: falló el envío a un super_admin (ver log).");
                }
            }

            if ($okEmpresa === 0) {
                continue; // queda sin marcar: se reintenta en la próxima corrida
            }

            // DB::table y no el modelo: no toca updated_at, y estas columnas
            // no están en $fillable.
            DB::table('empresas')
              ->where('id', $empresa->id)
              ->update([$aviso === '1d' ? 'demo_aviso_1d_at' : 'demo_aviso_7d_at' => now()]);

            $enviados += $okEmpresa;
            $this->info("{$asunto}: enviado a {$okEmpresa} super_admin.");
            Log::info('AvisarVencimientoDemos: aviso enviado.', [
                'empresa_id'   => $empresa->id,
                'aviso'        => $aviso,
                'destinatarios' => $okEmpresa,
            ]);
        }

        if (!$simular && $enviados === 0) {
            $this->line('Sin avisos para mandar hoy.');
        }

        return self::SUCCESS;
    }

    /**
     * '1d', '7d' o null.
     *
     * @param int $dias días de calendario hasta la fecha de vencimiento (0 = hoy)
     */
    private function avisoQueCorresponde(Empresa $empresa, int $dias): ?string
    {
        if ($dias <= 1) {
            return $empresa->demo_aviso_1d_at === null ? '1d' : null;
        }

        if ($dias <= self::DIAS_AVISO_ANTICIPADO) {
            return $empresa->demo_aviso_7d_at === null ? '7d' : null;
        }

        return null;
    }

    /** @return array<int, array{nombre:string, email:string, celular:?string}> */
    private function franquiciantes(Empresa $empresa): array
    {
        return User::where('empresa_id', $empresa->id)
                   ->where('rol', 'franquiciante')
                   ->whereNull('deleted_at')
                   ->get()
                   ->map(fn (User $u) => [
                       'nombre'  => trim("{$u->nombre} {$u->apellido}"),
                       'email'   => $u->email,
                       'celular' => $u->celular,
                   ])
                   ->all();
    }

    /** @return array{socios:int, empleados:int, manuales:int, documentos:int} */
    private function uso(Empresa $empresa): array
    {
        $porRol = User::where('empresa_id', $empresa->id)
                      ->whereNull('deleted_at')
                      ->selectRaw('rol, COUNT(*) AS n')
                      ->groupBy('rol')
                      ->pluck('n', 'rol');

        return [
            'socios'     => (int) ($porRol['franquiciado'] ?? 0),
            'empleados'  => (int) ($porRol['empleado'] ?? 0),
            'manuales'   => $empresa->manualEmpresaAssignments()->count(),
            'documentos' => DB::table('documents')
                              ->where('empresa_id', $empresa->id)
                              ->whereNull('deleted_at')
                              ->count(),
        ];
    }
}
