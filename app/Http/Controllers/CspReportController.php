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
    // Campos del estandar CSP (W3C). Cualquier otra clave del payload se descarta.
    private const CAMPOS_CSP = [
        'document-uri', 'referrer', 'violated-directive', 'effective-directive',
        'original-policy', 'disposition', 'blocked-uri', 'line-number',
        'column-number', 'source-file', 'status-code', 'script-sample',
    ];

    private const MAX_LARGO = 500;

    public function receive(Request $request)
    {
        // El body puede venir como application/csp-report o application/json
        // dependiendo del navegador. Laravel parsea ambos con $request->all().
        $body = $request->all();

        // V2-H-034: esta ruta es PUBLICA (los reportes CSP no llevan auth). Antes
        // se logueaba $request->all() crudo, lo que permitia:
        //   a) LOG INJECTION: meter \n en cualquier campo y forjar lineas de log
        //      falsas, ensuciando o desviando una investigacion posterior.
        //   b) volcar payloads arbitrariamente grandes al disco.
        //
        // Ahora: se extrae SOLO la whitelist de campos del estandar, se limpian los
        // caracteres de control (incluidos \r y \n) y se truncan a MAX_LARGO.
        $report = (isset($body['csp-report']) && is_array($body['csp-report']))
            ? $body['csp-report']
            : $body;

        if (!is_array($report)) {
            return response()->noContent();
        }

        $limpio = [];
        foreach (self::CAMPOS_CSP as $campo) {
            if (!isset($report[$campo]) || !is_scalar($report[$campo])) {
                continue;
            }
            // Reemplaza saltos de linea y cualquier caracter de control por espacio.
            $valor = preg_replace('/[\x00-\x1F\x7F]/u', ' ', (string) $report[$campo]);
            $limpio[$campo] = mb_substr($valor, 0, self::MAX_LARGO);
        }

        // Si no quedo ningun campo valido, el payload no es un reporte CSP: se
        // descarta sin loguear (evita que un atacante llene el log con basura).
        if (empty($limpio)) {
            return response()->noContent();
        }

        Log::warning('CSP violation', [
            'report' => $limpio,
            'ip'     => $request->ip(),
            'ua'     => mb_substr((string) $request->userAgent(), 0, self::MAX_LARGO),
        ]);

        // 204 No Content — el navegador no necesita ver la respuesta.
        return response()->noContent();
    }
}