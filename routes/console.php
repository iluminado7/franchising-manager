<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── TAREAS PROGRAMADAS ─────────────────────────────────────────────
//
// NADA DE ESTO CORRE SIN EL CRON. En el servidor tiene que existir, en el
// crontab de www-data (no de root: ver README §10):
//
//     * * * * * cd /var/www/franchising-manager && php artisan schedule:run >> /dev/null 2>&1
//
// Sin esa linea no hay ningun error visible: las tareas simplemente no pasan.
// Para verificar que esta cargada: `php artisan schedule:list`.

// Aviso a los super_admin de las empresas demo que vencen en 7 dias o mañana.
// A las 9 de Argentina: en horario laboral, y con el dia entero por delante
// para contactar a la empresa. withoutOverlapping() evita dos corridas a la vez
// (usa el cache, que en produccion es la base: CACHE_STORE=database).
Schedule::command('demos:avisar-vencimiento')
    ->dailyAt('09:00')
    ->timezone('America/Argentina/Buenos_Aires')
    ->withoutOverlapping();

// Recordatorio a los socios comerciales de los manuales que todavía no leyeron
// (7 días o más disponibles, una sola vez por versión). Misma hora que el aviso
// de las demos: las dos tareas corren una detrás de la otra en la misma pasada
// de schedule:run.
Schedule::command('manuales:recordar-lectura')
    ->dailyAt('09:00')
    ->timezone('America/Argentina/Buenos_Aires')
    ->withoutOverlapping();
