<?php

namespace App\Http\Controllers;

use App\Models\Manual;
use App\Models\ManualImage;
use App\Models\ManualVersion;
use App\Services\ManualAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;

/**
 * Genera el PDF imprimible de un manual, con encabezado y pie en cada hoja.
 *
 * Solo super_admin y franquiciante (la ruta está en ese grupo role:, y acá se
 * re-verifica el acceso al manual). El socio comercial lee y acepta en pantalla;
 * no descarga el documento maestro.
 */
class PdfController extends Controller
{
    public function generar(Request $request, int $id)
    {
        $user   = $request->user();
        $manual = Manual::findOrFail($id);

        // Defensa en profundidad: la ruta ya filtra por rol, pero el franquiciante
        // solo puede imprimir manuales de su empresa.
        if (!$user->esSuperAdmin() &&
            !ManualAccessService::empresaTieneAccesoAlManual($manual->id, $user->empresa_id)) {
            return response()->json(['error' => 'Sin acceso a este manual.'], 403);
        }

        // Versión ACTIVA: su contenido + su snapshot de encabezado/pie. Es lo que
        // se lee y se acepta; el PDF debe reflejar exactamente eso.
        $version = ManualVersion::where('manual_id', $manual->id)
                                ->where('es_activa', 1)
                                ->first();

        if (!$version) {
            return response()->json(['error' => 'El manual no tiene una versión publicada.'], 409);
        }
        $contenido  = $this->resolverImagenes($version->contenido_html ?? '', $manual->id);
        $encabezado = $this->resolverImagenes($version->encabezado_html ?? '', $manual->id);
        $pie        = $this->resolverImagenes($version->pie_pagina_html ?? '', $manual->id);

        $mpdf = new Mpdf([
            'mode'          => 'utf-8',
            'format'        => 'A4',
            'margin_top'    => 50,   // deja lugar al header
            'margin_bottom' => 34,   // deja lugar al footer + nº de página
            'margin_left'   => 18,
            'margin_right'  => 18,
            'margin_header' => 8,
            'margin_footer' => 8,
            'tempDir'       => storage_path('app/mpdf-tmp'),
            'allow_local_files' => true,
        ]);

        $mpdf->SetTitle($manual->titulo);
        $mpdf->SetAuthor('Manuales Franquiciantes');

        // Header repetido en cada página (si el manual tiene encabezado).
        if (trim(strip_tags($encabezado)) !== '' || str_contains($encabezado, '<img')) {
            $mpdf->SetHTMLHeader(
                '<div style="border-bottom:1px solid #ccc;padding-bottom:4px;text-align:center;font-family:sans-serif;font-size:10px;color:#333;line-height:1.3">'
                . $this->limitarImagenes($encabezado) .
                '</div>'
            );
        }

        // Footer repetido en cada página: el pie del franquiciante (centrado) +
        // el nº de página SIEMPRE a la derecha. {PAGENO}/{nbpg} los completa mPDF.
       $pieFranquiciante = (trim(strip_tags($pie)) !== '' || str_contains($pie, '<img'))
            ? '<div style="text-align:center;font-family:sans-serif;font-size:10px;color:#333;line-height:1.3">' . $this->limitarImagenes($pie) . '</div>'
            : '';

        $mpdf->SetHTMLFooter(
            '<div style="border-top:1px solid #ccc;padding-top:4px">'
            . $pieFranquiciante .
            '<div style="text-align:right;font-family:sans-serif;font-size:9px;color:#666;margin-top:2px">'
            . 'Página {PAGENO} de {nbpg}'
            . '</div></div>'
        );

        // Marca de agua para socios (acá el actor es admin, pero se deja el título
        // del manual como watermark tenue, disuasivo ante fotocopias).
        $mpdf->SetWatermarkText($manual->titulo);
        //$mpdf->showWatermarkText  = true;
        $mpdf->watermarkTextAlpha = 0.04;

        $css = '
            body { font-family: sans-serif; font-size: 12px; color: #000; line-height: 1.5; }
            h1 { font-size: 18px; margin: 0 0 10px; }
            h2 { font-size: 15px; margin: 14px 0 8px; }
            h3 { font-size: 13px; margin: 12px 0 6px; }
            p  { margin: 0 0 9px; }
            table { width: 100%; border-collapse: collapse; margin: 12px 0; }
            td, th { border: 1px solid #ccc; padding: 6px 9px; text-align: left; }
            th { background: #f2f2f2; }
            img { max-width: 100%; }
        ';

        // Imágenes del encabezado y pie: acotadas en alto para que no empujen el
        // layout ni tapen el contenido. El logo y la balanza venían enormes.
        $cssHeaderFooter = '
            .pdf-hf-img { max-height: 22mm; width: auto; }
        ';
        $mpdf->WriteHTML($cssHeaderFooter, \Mpdf\HTMLParserMode::HEADER_CSS);
        $mpdf->WriteHTML($css, \Mpdf\HTMLParserMode::HEADER_CSS);
        $mpdf->WriteHTML($contenido, \Mpdf\HTMLParserMode::HTML_BODY);

        $verLabel = $version->version_number . '.' . ($version->version_minor ?? 0);
        $nombre   = 'manual-' . $manual->id . '-v' . $verLabel . '.pdf';

        // 'S' = mPDF devuelve el documento como string y Laravel lo envuelve en
        // la response. ('I' imprimiria directo a la salida; 'D' forzaria descarga.)
        return response($mpdf->Output($nombre, 'S'), 200)
            ->header('Content-Type', 'application/pdf');
    }

    /**
     * Reescribe los <img> que apuntan al endpoint autenticado
     * (/api/manuales-imagenes/{id}/descargar) por la RUTA LOCAL del archivo.
     *
     * mPDF corre server-side sin la cookie de sesión del usuario, así que no puede
     * descargar la imagen por HTTP: se le da la ruta física del disco 'local', que
     * lee directo. Solo se resuelven imágenes de ESTE manual (no se sirve nada de
     * otro tenant aunque el HTML lo pidiera).
     */
  private function resolverImagenes(string $html, int $manualId): string
    {
        if ($html === '' || !str_contains($html, 'manuales-imagenes')) {
            return $html;
        }

        $resultado = preg_replace_callback(
            '#src\s*=\s*(["\'])(.*?manuales-imagenes/(\d+)/descargar.*?)\1#i',
            function ($m) use ($manualId) {
                $imagenId = (int) $m[3];
                $imagen   = ManualImage::find($imagenId);

                if (!$imagen || (int) $imagen->manual_id !== $manualId) {
                    return 'src=""';
                }

                $full = Storage::disk('local')->path($imagen->archivo_path);
                if (!is_file($full)) {
                    return 'src=""';
                }

                return 'src="' . $full . '"';
            },
            $html
        );

        return $resultado;
    }
   /**
     * Acota las imágenes de encabezado/pie para mPDF.
     *
     * CLAVE: mPDF IGNORA max-width/max-height del CSS inline en imágenes de header
     * y footer. Solo respeta el ATRIBUTO width=. Por eso, en vez de style, se
     * reescribe el atributo width a un valor fijo en mm y se ELIMINA height (para
     * que mPDF recalcule el alto manteniendo la proporción).
     *
     * width="55mm" → el logo apaisado (423x80) queda en ~55mm de ancho y ~10mm de
     * alto, tamaño de membrete. En el CUERPO las imágenes NO pasan por acá.
     */
    private function limitarImagenes(string $html): string
    {
        // 1. Quitar height (atributo): mPDF recalcula el alto por proporción.
        $html = preg_replace('#\s+height\s*=\s*["\']?\d+(?:mm|px)?["\']?#i', '', $html);

        // 2. Reemplazar el width existente por uno fijo en mm.
        $html = preg_replace('#\s+width\s*=\s*["\']?\d+(?:mm|px)?["\']?#i', ' width="55mm"', $html);

        // 3. Para <img> que NO tenían width, agregárselo.
        $html = preg_replace('#<img\b(?![^>]*\swidth=)#i', '<img width="55mm"', $html);

        return $html;
    }
}
