<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Errores 5xx del servidor, para que el super_admin los vea sin entrar por SSH.
 *
 * La tabla la llena el hook report() de bootstrap/app.php, agrupando por huella
 * (clase + archivo + linea): una fila por error UNICO con contador, no una por
 * ocurrencia. Un error en bucle no llena la tabla.
 *
 * NO reemplaza a storage/logs. Ahi sigue el trace completo; esto es un resumen
 * consultable con los primeros 8 frames.
 *
 * SEGURIDAD
 *   Solo super_admin. La ruta ya esta en el grupo role:super_admin y ademas se
 *   re-verifica aca (defensa en profundidad, mismo criterio que
 *   PdfController::generar): si un refactor de api.php moviera estas rutas de
 *   grupo, los stack traces quedarian expuestos en silencio.
 *
 *   Los datos que se muestran ya vienen filtrados desde el hook: nunca el cuerpo
 *   del request (el POST a /api/login lleva la contrasena en texto plano) y la
 *   ruta guardada tiene redactado el token opaco de /manuales/archivo/{token}.
 */
class ErrorLogController extends Controller
{
    /** Techo de filas. La tabla crece por error UNICO, asi que es holgado. */
    private const LIMITE = 500;

    // GET /api/errores
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->esSuperAdmin()) {
            return response()->json(['error' => 'Sin permisos.'], 403);
        }

        $query = DB::table('error_logs');

        // ?resuelto=0 | 1
        if ($request->has('resuelto')) {
            $query->where('resuelto', (bool) $request->query('resuelto'));
        }

        $errores = $query->orderByDesc('ultima_vez')
                         ->limit(self::LIMITE)
                         ->get();

        return response()->json([
            'errores' => $errores,
            // Los totales se calculan sobre TODA la tabla, no sobre lo devuelto:
            // si algun dia el limite recorta, las tarjetas seguirian diciendo la
            // verdad en vez de mentir por lo bajo.
            'resumen' => [
                'distintos'      => DB::table('error_logs')->count(),
                'sin_resolver'   => DB::table('error_logs')->where('resuelto', 0)->count(),
                'ocurrencias'    => (int) DB::table('error_logs')->sum('ocurrencias'),
                'ultimo'         => DB::table('error_logs')->max('ultima_vez'),
            ],
        ]);
    }

    // POST /api/errores/{id}/resolver
    //
    // Marca o desmarca un error como resuelto. Si vuelve a ocurrir, el hook lo
    // pone en 0 solo: si reaparecio, no estaba resuelto.
    public function resolver(Request $request, int $id): JsonResponse
    {
        if (!$request->user()->esSuperAdmin()) {
            return response()->json(['error' => 'Sin permisos.'], 403);
        }

        $error = DB::table('error_logs')->find($id);
        if (!$error) {
            return response()->json(['error' => 'Error no encontrado.'], 404);
        }

        $nuevo = !$error->resuelto;
        DB::table('error_logs')->where('id', $id)->update(['resuelto' => $nuevo]);

        return response()->json([
            'message'  => $nuevo ? 'Marcado como resuelto.' : 'Reabierto.',
            'resuelto' => $nuevo,
        ]);
    }

    // DELETE /api/errores/{id}
    //
    // Borrado fisico. A diferencia de activity_logs, esto NO es un registro de
    // cumplimiento: es diagnostico. Borrar ruido resuelto es legitimo.
    public function destroy(Request $request, int $id): JsonResponse
    {
        if (!$request->user()->esSuperAdmin()) {
            return response()->json(['error' => 'Sin permisos.'], 403);
        }

        $borrados = DB::table('error_logs')->where('id', $id)->delete();
        if (!$borrados) {
            return response()->json(['error' => 'Error no encontrado.'], 404);
        }

        return response()->json(['message' => 'Registro eliminado.']);
    }
}