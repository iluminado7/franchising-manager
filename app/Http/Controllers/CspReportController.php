<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * H-025 fix — recibe reportes de violaciones de CSP del navegador.
 *
 * Los reportes CSP no incluyen cookies ni auth. La ruta es pública pero con
 * throttle agresivo (ver api.php) para evitar spam. El body es un JSON con la
 * violación (URL afectada, directiva violada, blocked URI, etc.).
 *
 * Los reportes se loguean a storage/logs/laravel.log con prefijo "CSP violation".
 * Para revisarlos:
 *   grep "CSP violation" storage/logs/laravel.log
 *
 * En una futura iteración: canal de log dedicado en config/logging.php.
 */
class CspReportController extends Controller
{
    public function receive(Request $request)
    {
        // El body puede venir como application/csp-report o application/json
        // dependiendo del navegador. Laravel parsea ambos con $request->all().
        $report = $request->all();

        Log::warning('CSP violation', [
            'report' => $report,
            'ip'     => $request->ip(),
            'ua'     => $request->userAgent(),
        ]);

        // 204 No Content — el navegador no necesita ver la respuesta.
        return response()->noContent();
    }
}