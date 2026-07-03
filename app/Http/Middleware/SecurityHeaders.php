<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * H-025 fix — inyecta headers HTTP de seguridad en TODAS las respuestas.
 *
 * Headers aplicados:
 *   - Content-Security-Policy-Report-Only: modo report-only inicial. No bloquea,
 *     solo reporta violaciones al endpoint /api/csp-report. Después de 2-4 semanas
 *     de observar reportes y estabilizar la lista de fuentes permitidas, se cambia
 *     a Content-Security-Policy enforcement.
 *   - Strict-Transport-Security: HSTS con max-age corto (1 día) para arrancar.
 *     Después de estabilizar, subir a 1 año (max-age=31536000).
 *     Solo se emite en HTTPS (no en dev HTTP).
 *   - X-Frame-Options: DENY — nadie puede embeder el sitio en iframe.
 *   - Referrer-Policy: strict-origin-when-cross-origin — no filtra URLs
 *     completas a sitios externos.
 *   - X-Content-Type-Options: nosniff — previene MIME sniffing malicioso.
 *
 * NOTA sobre 'unsafe-inline' en script-src y style-src:
 *   El proyecto usa <script> y <style> inline en todas las páginas PHP. Sacar
 *   'unsafe-inline' requiere refactor con nonces por request (posible pero
 *   invasivo). Por ahora quedan permitidos para que el sitio funcione en modo
 *   Report-Only sin generar ruido. Cuando se implementen nonces, se pueden
 *   sacar.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // ── Content Security Policy (Report-Only) ────────────────
        $csp = implode('; ', [
            "default-src 'self'",
            // 'unsafe-inline' hoy es necesario porque los templates tienen
            // <script> y <style> inline. Migrar a nonces en el futuro.
            "script-src 'self' 'unsafe-inline'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob:",
            "font-src 'self' data:",
            "connect-src 'self'",
            "frame-ancestors 'none'",
            "form-action 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "report-uri /api/csp-report",
        ]);
        $response->headers->set('Content-Security-Policy-Report-Only', $csp);

        // ── HSTS (solo en HTTPS, no tiene sentido en dev HTTP) ──
        // max-age=86400 = 1 día. Después de 2 semanas de estabilidad, subir a
        // max-age=31536000 (1 año) + includeSubDomains + preload.
        if ($request->secure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=86400'
            );
        }

        // ── Clickjacking ───────────────────────────────────────
        $response->headers->set('X-Frame-Options', 'DENY');

        // ── Referrer leaks ─────────────────────────────────────
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // ── MIME sniffing ──────────────────────────────────────
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }
}