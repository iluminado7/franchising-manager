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

// Recordatorio a los socios comerciales de los manuales que todavía no leyeron:
// los JUEVES a las 9:00 de Argentina, insistiendo cada semana hasta que lean
// (decisión del 18/09/2026).
//
// weeklyOn(4, ...) es jueves: 0 = domingo. Si se cambia el día, cambiar también
// el comentario del comando, que es lo que lee el que viene después.
Schedule::command('manuales:recordar-lectura')
    ->weeklyOn(4, '09:00')
    ->timezone('America/Argentina/Buenos_Aires')
    ->withoutOverlapping();
