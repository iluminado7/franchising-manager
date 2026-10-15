<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Manual;
use App\Models\User;
use App\Services\CupoDemo;
use App\Services\OnlyOffice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Edicion de manuales en Word con OnlyOffice.
 *
 * Reemplaza el circuito de importar .docx con Mammoth (que convertia a HTML y
 * perdia fidelidad) por la edicion del .docx original en el navegador.
 *
 * TODO ESTE CONTROLLER SE APAGA SOLO si no hay Document Server configurado:
 * cada endpoint empieza verificando OnlyOffice::habilitado(). Con la
 * configuracion vacia, el frontend nunca llega aca y sigue usando Mammoth.
 */
class OnlyOfficeController extends Controller
{
    /**
     * GET /api/onlyoffice/estado
     *
     * Lo consulta el frontend para decidir que editor ofrecer. Devuelve solo
     * un booleano: NO expone la URL del Document Server ni, muchisimo menos,
     * la clave compartida. La URL la necesita el navegador, pero se la damos
     * dentro de la configuracion firmada de un manual concreto, no suelta.
     */
    public function estado(): JsonResponse
    {
        return response()->json(['habilitado' => OnlyOffice::habilitado()]);
    }

    /**
     * POST /api/manuales/{id}/docx
     *
     * Sube (o reemplaza) el documento de trabajo en Word de un manual.
     * Es el paso que antes hacia Mammoth en el navegador.
     */
    public function subirDocx(Request $request, int $id): JsonResponse
    {
        if (!OnlyOffice::habilitado()) {
            return response()->json([
                'error' => 'La edicion con OnlyOffice no esta disponible.',
            ], 503);
        }

        $request->validate([
            // Igual que en publicarArchivo(): mimes valida la extension y
            // mimetypes el contenido real leido con finfo. Un .docx es un ZIP,
            // y hay servidores que lo reportan como tal: se aceptan los dos
            // MIME, pero la extension sigue siendo obligatoria.
            'archivo' => [
                'required',
                'file',
                'mimes:docx',
                'mimetypes:application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/zip',
                'max:' . (int) config('onlyoffice.max_kb'),
            ],
        ]);

        $manual = Manual::findOrFail($id);
        $user   = $request->user();

        $this->authorize('gestionar', $manual);

        // Un manual 'pdf' se publica tal cual y no tiene editor de ninguna
        // clase. Mismo criterio que guardarBorrador().
        if ($manual->esPdf()) {
            return response()->json([
                'error' => 'Este manual es un PDF: no se edita.',
            ], 409);
        }

        $archivo = $request->file('archivo');
        $tamano  = (int) $archivo->getSize();

        // Cupo de la demo ANTES de escribir nada: si no entra, no se sube.
        CupoDemo::exigirEspacio($user, $tamano);

        // La ruta deriva del manual y del hash del contenido. El nombre que
        // mando el cliente NUNCA toca el filesystem (podria traer ../ o
        // caracteres raros): se guarda aparte, como metadato.
        $hash    = hash_file('sha256', $archivo->getRealPath());
        $rutaRel = "manuales/docx/{$manual->id}/{$hash}.docx";

        $disk = config('filesystems.default');

        if (!Storage::disk($disk)->exists($rutaRel)) {
            Storage::disk($disk)->put($rutaRel, file_get_contents($archivo->getRealPath()));
        }

        $rutaAnterior = $manual->docx_path;

        // Setters directos: estas columnas no estan en el $fillable de Manual
        // (ver el comentario de tipo/public_id en el modelo). El manual queda
        // marcado como "editable en Word" por tener docx_path.
        $manual->docx_path          = $rutaRel;
        $manual->docx_nombre        = mb_substr(basename($archivo->getClientOriginalName()), 0, 255);
        $manual->docx_tamano        = $tamano;
        $manual->docx_clave         = OnlyOffice::nuevaClave();
        $manual->docx_actualizado_at = now();
        $manual->save();

        // El .docx que se reemplaza se borra para no acumular basura en el
        // storage. Solo si la ruta cambio: si el usuario sube el MISMO archivo
        // el hash es igual, la ruta tambien, y borrarla dejaria al manual
        // apuntando a un archivo inexistente.
        if ($rutaAnterior && $rutaAnterior !== $rutaRel) {
            try {
                Storage::disk($disk)->delete($rutaAnterior);
            } catch (\Throwable $e) {
                // Best-effort: un archivo viejo que queda ocupa lugar, pero
                // fallar aca no puede tumbar una importacion que ya funciono.
            }
        }

        ActivityLog::registrar(
            userId:      $user->id,
            accion:      'manual_docx_importado',
            ip:          $request->ip(),
            empresaId:   $user->empresa_id,
            entidadTipo: 'manuals',
            entidadId:   $manual->id,
            detalle:     ['manual_titulo' => $manual->titulo],
            userAgent:   $request->userAgent()
        );

        return response()->json([
            'message' => 'Documento importado.',
            'nombre'  => $manual->docx_nombre,
            'tamano'  => $tamano,
        ]);
    }

    /**
     * GET /api/manuales/{id}/editor-config
     *
     * Configuracion firmada del editor. La pide la pagina editor-word.php y
     * se la entrega tal cual a DocsAPI.DocEditor.
     *
     * La firma se arma ACA, en el servidor, nunca en el navegador: la clave
     * compartida con el Document Server no sale del backend.
     */
    public function configEditor(Request $request, int $id): JsonResponse
    {
        if (!OnlyOffice::habilitado()) {
            return response()->json([
                'error' => 'La edicion con OnlyOffice no esta disponible.',
            ], 503);
        }

        $manual = Manual::findOrFail($id);
        $user   = $request->user();

        $this->authorize('gestionar', $manual);

        if (!$manual->docx_path) {
            return response()->json([
                'error' => 'Este manual no tiene un documento de Word cargado.',
            ], 409);
        }

        return response()->json([
            // La URL del Document Server viaja junto con la configuracion
            // porque el navegador tiene que bajar de ahi el JavaScript del
            // editor. Va solo a quien ya probo que puede gestionar el manual.
            'servidor' => config('onlyoffice.url'),
            'config'   => OnlyOffice::configEditor($manual, $user),
            'manual'   => [
                'id'     => $manual->id,
                'titulo' => $manual->titulo,
                'estado' => $manual->estado,
            ],
        ]);
    }

    /**
     * GET /api/onlyoffice/documento/{token}
     *
     * Entrega el .docx AL DOCUMENT SERVER, no al usuario.
     *
     * ESTA RUTA VA FUERA DEL MIDDLEWARE DE SESION a proposito: quien pide es
     * el Document Server, un servicio que corre en otro lado y no tiene la
     * cookie del usuario. El control de acceso lo hace el token, que es una
     * credencial de corta vida, cifrada, atada al manual y al usuario que
     * abrio el editor (ver OnlyOffice::tokenDocumento).
     *
     * Se vuelve a verificar el permiso con el usuario del token: si a alguien
     * le revocaron el acceso entre que abrio el editor y el momento de la
     * descarga, el documento no sale.
     */
    public function servirDocumento(Request $request, string $token)
    {
        if (!OnlyOffice::habilitado()) {
            abort(404);
        }

        $datos = OnlyOffice::abrirTokenDocumento($token);

        // 404 y no 403: a quien prueba tokens no se le confirma que el manual
        // exista ni que el token tuviera la forma correcta.
        if (!$datos) {
            abort(404);
        }

        $manual = Manual::find($datos['m']);
        $user   = User::find($datos['u']);

        if (!$manual || !$user || !$manual->docx_path) {
            abort(404);
        }

        // El permiso se revalida ahora, no se da por hecho por tener el token.
        if ($user->cannot('gestionar', $manual)) {
            abort(404);
        }

        $disk = config('filesystems.default');

        if (!Storage::disk($disk)->exists($manual->docx_path)) {
            abort(404);
        }

        ActivityLog::registrar(
            userId:      $user->id,
            accion:      'manual_docx_servido',
            ip:          $request->ip(),
            empresaId:   $user->empresa_id,
            entidadTipo: 'manuals',
            entidadId:   $manual->id,
            detalle:     ['manual_titulo' => $manual->titulo],
            userAgent:   $request->userAgent()
        );

        return Storage::disk($disk)->response(
            $manual->docx_path,
            $manual->nombreArchivo('docx'),
            [
                'Content-Type'           => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    /**
     * POST /api/onlyoffice/callback/{token}
     *
     * GUARDADO DEL BORRADOR. Lo llama el Document Server, no el navegador.
     *
     * ── COMO FUNCIONA EL GUARDADO ────────────────────────────────────────
     *
     * Mientras se edita, el documento vive en el Document Server y nosotros no
     * tenemos nada. Cuando el usuario aprieta Guardar, o cierra la pestana, el
     * Document Server llama a esta ruta avisando que hay una version nueva y
     * diciendo de donde bajarla. Recien ahi el .docx vuelve a nuestro storage.
     *
     * Esto significa que SI ESTA RUTA FALLA, EL TRABAJO SE PIERDE. De ahi que
     * todo lo que pueda romperse este contemplado y quede registrado.
     *
     * ── LOS ESTADOS ──────────────────────────────────────────────────────
     *
     *   1  alguien empezo a editar          -> no hay nada que guardar
     *   2  listo para guardar (se cerro)    -> SE GUARDA
     *   3  error al preparar el guardado    -> queda registrado
     *   4  se cerro sin cambios             -> no hay nada que guardar
     *   6  guardado explicito (boton/Ctrl+S)-> SE GUARDA, sigue abierto
     *   7  error en el guardado explicito   -> queda registrado
     *
     * ── LA RESPUESTA ─────────────────────────────────────────────────────
     *
     * Hay que contestar {"error":0} SIEMPRE que se haya procesado bien. Si se
     * contesta otra cosa, el Document Server reintenta un rato y despues le
     * muestra al usuario que el documento no se pudo guardar.
     */
    public function callback(Request $request, string $token): JsonResponse
    {
        if (!OnlyOffice::habilitado()) {
            return response()->json(['error' => 1]);
        }

        $datos = OnlyOffice::abrirTokenDocumento($token);
        if (!$datos) {
            return response()->json(['error' => 1]);
        }

        $manual = Manual::find($datos['m']);
        $user   = User::find($datos['u']);

        if (!$manual || !$user || $user->cannot('gestionar', $manual)) {
            return response()->json(['error' => 1]);
        }

        // El cuerpo tambien viene FIRMADO por el Document Server. Verificarlo
        // es lo que impide que, conociendo esta URL, cualquiera nos haga
        // guardar el archivo que quiera como si fuera el manual.
        //
        // La firma llega en el campo 'token' del cuerpo o en la cabecera
        // Authorization, segun como este configurado el Document Server. Se
        // aceptan las dos, pero se exige al menos una.
        $cuerpo = $request->all();
        $firmado = null;

        if (!empty($cuerpo['token'])) {
            $firmado = OnlyOffice::verificarJwt((string) $cuerpo['token']);
        } elseif ($cabecera = $request->header('Authorization')) {
            $verificado = OnlyOffice::verificarJwt(
                trim(str_replace('Bearer', '', $cabecera))
            );
            // Con JWT_HEADER el payload viaja anidado bajo 'payload'.
            $firmado = $verificado['payload'] ?? $verificado;
        }

        if (!is_array($firmado)) {
            return response()->json(['error' => 1]);
        }

        // De aca en mas se usa lo FIRMADO, no lo que mando el cuerpo crudo:
        // si se leyera $cuerpo['url'], verificar la firma no serviria de nada.
        $estado = (int) ($firmado['status'] ?? 0);

        // Nada que guardar. Se contesta OK igual: no es un error.
        if (in_array($estado, [1, 4], true)) {
            return response()->json(['error' => 0]);
        }

        // El Document Server no pudo armar el documento. No hay archivo que
        // bajar; se deja constancia para poder investigarlo despues.
        if (in_array($estado, [3, 7], true)) {
            report(new \RuntimeException(
                "OnlyOffice no pudo guardar el manual {$manual->id} (status $estado)."
            ));
            return response()->json(['error' => 0]);
        }

        if (!in_array($estado, [2, 6], true)) {
            return response()->json(['error' => 0]);
        }

        $url = (string) ($firmado['url'] ?? '');
        if ($url === '') {
            return response()->json(['error' => 1]);
        }

        try {
            $contenido = OnlyOffice::bajarDelDocumentServer($url);

            // Un .docx es un ZIP: los dos primeros bytes son 'PK'. Sin este
            // chequeo, una pagina de error del Document Server se guardaria
            // como si fuera el manual y lo dejaria ilegible.
            if (!str_starts_with($contenido, 'PK')) {
                throw new \RuntimeException('Lo descargado no es un .docx valido.');
            }

            $tamano = strlen($contenido);

            // Cupo de la demo: se descuenta lo que ocupaba la version anterior
            // del borrador, porque se pisa y no se acumula.
            CupoDemo::exigirEspacio($user, max(0, $tamano - (int) $manual->docx_tamano));

            $disk    = config('filesystems.default');
            $hash    = hash('sha256', $contenido);
            $rutaRel = "manuales/docx/{$manual->id}/{$hash}.docx";

            Storage::disk($disk)->put($rutaRel, $contenido);

            $rutaAnterior = $manual->docx_path;

            $manual->docx_path           = $rutaRel;
            $manual->docx_tamano         = $tamano;
            // CLAVE NUEVA EN CADA GUARDADO. Si no se renovara, la proxima vez
            // que alguien abra el manual el Document Server serviria la copia
            // vieja que tiene en cache y los cambios parecerian perdidos.
            $manual->docx_clave          = OnlyOffice::nuevaClave();
            $manual->docx_actualizado_at = now();
            $manual->save();

            if ($rutaAnterior && $rutaAnterior !== $rutaRel) {
                try {
                    Storage::disk($disk)->delete($rutaAnterior);
                } catch (\Throwable $e) { /* best-effort */ }
            }

            ActivityLog::registrar(
                userId:      $user->id,
                accion:      'manual_docx_guardado',
                ip:          $request->ip(),
                empresaId:   $user->empresa_id,
                entidadTipo: 'manuals',
                entidadId:   $manual->id,
                detalle:     ['manual_titulo' => $manual->titulo],
                userAgent:   $request->userAgent()
            );
        } catch (\Throwable $e) {
            // error:1 hace que el Document Server REINTENTE, que es justo lo
            // que se quiere ante una falla transitoria (storage, red, cupo).
            // Queda registrado para que no pase inadvertido si es permanente.
            report($e);
            return response()->json(['error' => 1]);
        }

        return response()->json(['error' => 0]);
    }
}
