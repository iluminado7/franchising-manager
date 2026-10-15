<?php

namespace App\Services;

use App\Models\Manual;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Contracts\Encryption\DecryptException;

/**
 * Integracion con OnlyOffice Document Server.
 *
 * Fuente unica de verdad de DOS cosas:
 *   1. si la funcionalidad esta encendida (habilitado());
 *   2. como se firma todo lo que viaja al Document Server (firmar()).
 *
 * Nada fuera de esta clase arma un JWT ni decide si OnlyOffice esta activo.
 *
 * Ver config/onlyoffice.php para el detalle de las tres URLs en juego.
 */
class OnlyOffice
{
    /**
     * ¿Hay un Document Server configurado?
     *
     * Se exigen LAS DOS cosas, no una: una URL sin clave compartida daria un
     * editor que el Document Server rechaza en cada pedido, y el usuario
     * veria un cuadro de error en vez del editor propio que si funciona.
     * Ante configuracion a medias, se prefiere el camino viejo.
     */
    public static function habilitado(): bool
    {
        return config('onlyoffice.url') !== ''
            && config('onlyoffice.jwt_secret') !== '';
    }

    /**
     * Firma un payload con la clave compartida (JWT HS256).
     *
     * Se implementa a mano y no con una libreria porque es una sola funcion
     * de ocho lineas y evita sumar una dependencia al proyecto.
     *
     * OJO con el orden de las claves: la firma cubre el JSON exacto, asi que
     * el payload se serializa UNA vez y de ahi salen el cuerpo y la firma.
     * Volver a serializar el mismo array en otro lugar puede dar otro string.
     */
    public static function firmar(array $payload): string
    {
        $secreto = (string) config('onlyoffice.jwt_secret');

        $cabecera = self::b64url(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $cuerpo   = self::b64url(json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ));

        $firma = self::b64url(hash_hmac('sha256', "$cabecera.$cuerpo", $secreto, true));

        return "$cabecera.$cuerpo.$firma";
    }

    /**
     * Enlace con el que el Document Server baja el .docx de un manual.
     *
     * POR QUE UN TOKEN Y NO LA SESION: el Document Server corre en otro lado
     * y descarga el archivo por su cuenta. No es el navegador del usuario: no
     * manda la cookie auth_token, asi que la ruta no puede estar detras del
     * middleware de sesion.
     *
     * El token es la credencial, y por eso lleva TODO lo que hace falta para
     * verificar el pedido sin confiar en nada externo:
     *   - 'm' el manual, para que un token no sirva para otro;
     *   - 'u' quien lo pidio, que queda registrado en el log de actividad;
     *   - 'exp' vencimiento corto (ver config).
     *
     * Va cifrado con la APP_KEY, no solo firmado: desde afuera es opaco.
     * Mismo criterio que ManualController::tokenArchivo().
     */
    public static function tokenDocumento(Manual $manual, User $user, ?int $minutos = null): string
    {
        $payload = json_encode([
            'm'   => $manual->id,
            'u'   => $user->id,
            'exp' => now()->addMinutes(
                $minutos ?? (int) config('onlyoffice.minutos_token')
            )->timestamp,
        ]);

        // Los mismos reemplazos que ya usa el proyecto para que el token
        // viaje en una URL sin encodear.
        return strtr(Crypt::encryptString($payload), '+/=', '-_~');
    }

    /**
     * Abre un token de documento. Devuelve ['m' => int, 'u' => int] o null si
     * es invalido, esta vencido o fue manipulado.
     *
     * Null para CUALQUIER falla, sin distinguir el motivo: a quien prueba
     * tokens al azar no se le informa si fallo la firma o el vencimiento.
     */
    public static function abrirTokenDocumento(string $token): ?array
    {
        try {
            $datos = json_decode(
                Crypt::decryptString(strtr($token, '-_~', '+/=')),
                true
            );
        } catch (DecryptException $e) {
            return null;
        }

        if (!is_array($datos)
            || !isset($datos['m'], $datos['u'], $datos['exp'])
            || (int) $datos['exp'] < time()) {
            return null;
        }

        return ['m' => (int) $datos['m'], 'u' => (int) $datos['u']];
    }

    /**
     * Configuracion completa del editor, ya firmada, lista para que el
     * navegador se la pase a DocsAPI.DocEditor.
     *
     * @param bool $soloLectura true para abrir sin permitir modificar.
     */
    public static function configEditor(Manual $manual, User $user, bool $soloLectura = false): array
    {
        $token = self::tokenDocumento($manual, $user);

        $config = [
            'document' => [
                'fileType' => 'docx',
                // La CLAVE identifica la version del documento. Mientras no
                // cambie, el Document Server sirve la copia que tiene en
                // cache y IGNORA el archivo del storage. Por eso se guarda en
                // la base y se renueva en cada guardado: si se dejara fija,
                // despues de editar se seguiria viendo la version vieja.
                'key'   => (string) $manual->docx_clave,
                // El editor muestra el TITULO DEL MANUAL, no el nombre del
                // .docx que se importo. Es el mismo nombre que ve todo el
                // mundo en el listado, en lectura y en los mails.
                //
                // Mostrar el nombre del archivo tenia un problema de fondo:
                // habia dos nombres para la misma cosa, el usuario leia uno y
                // el sistema usaba el otro.
                'title' => $manual->nombreArchivo('docx'),
                'url'   => config('onlyoffice.url_sistema') . '/api/onlyoffice/documento/' . $token,
                'permissions' => [
                    'edit'     => !$soloLectura,
                    // Renombrar desde el editor. Lo que se renombra es EL
                    // MANUAL: el editor avisa con onRequestRename y la pagina
                    // guarda el titulo nuevo. Ver editor-word.php.
                    'rename'   => !$soloLectura,
                    // Descargar e imprimir desde el editor: solo lo abren
                    // super_admin y franquiciante, que son los mismos roles
                    // que ya tienen el boton de imprimir en lectura.php.
                    'download' => true,
                    'print'    => true,
                ],
            ],
            'documentType' => 'word',
            'editorConfig' => [
                'mode' => $soloLectura ? 'view' : 'edit',
                'lang' => 'es',
                // A DONDE NOS DEVUELVE EL DOCUMENTO.
                //
                // Es el corazon del guardado: el editor no nos manda nada
                // mientras se trabaja; cuando el usuario guarda o cierra, el
                // Document Server llama A ESTA URL y ahi recien baja el .docx
                // modificado. Sin callbackUrl se puede editar pero no se
                // guarda nada, y el editor ni siquiera avisa.
                //
                // El token dura mucho mas que el de descarga (ver abajo):
                // alguien puede tener el documento abierto media jornada y el
                // aviso de guardado llega al final, no al principio.
                'callbackUrl' => config('onlyoffice.url_sistema')
                                 . '/api/onlyoffice/callback/'
                                 . self::tokenDocumento($manual, $user, (int) config('onlyoffice.minutos_callback')),
                // Identifica al autor en los cambios y comentarios. Van id y
                // nombre y nada mas: el Document Server no necesita el mail
                // ni el rol, y lo que no se manda no se puede filtrar.
                'user' => [
                    'id'   => (string) $user->id,
                    'name' => $user->nombre . ' ' . $user->apellido,
                ],
                'customization' => [
                    // forcesave: habilita el boton Guardar (y Ctrl+S) DENTRO
                    // del editor. Cada guardado dispara nuestro callback con
                    // status 6, igual que en Word: se guarda cuando el usuario
                    // decide, no cada dos minutos.
                    'forcesave'   => true,
                    // Sin autoguardado periodico: el documento igual se guarda
                    // solo al cerrar la pestana (callback con status 2), asi
                    // que no se pierde trabajo por no haber apretado Guardar.
                    'autosave'    => false,
                    'compactHeader' => true,
                    'help'        => false,
                    // Marca del editor: se oculta el logo de OnlyOffice en el
                    // encabezado para que no compita con el del sistema.
                    'logo' => ['visible' => false],
                ],
            ],
            'width'  => '100%',
            'height' => '100%',
        ];

        $config['token'] = self::firmar($config);

        return $config;
    }

    /** Clave nueva para el documento. Ver el comentario de 'key' arriba. */
    public static function nuevaClave(): string
    {
        return substr(bin2hex(random_bytes(16)), 0, 20);
    }

    /**
     * Verifica un JWT que mando el Document Server y devuelve su contenido, o
     * null si la firma no cierra.
     *
     * Es la contracara de firmar(): el Document Server tambien firma lo que
     * nos manda (el aviso de guardado), y sin verificarlo cualquiera que
     * conozca la URL del callback podria hacernos guardar el documento que
     * quiera como si fuera el manual.
     *
     * hash_equals y no ===: comparar firmas con == filtra informacion por el
     * tiempo que tarda en fallar.
     */
    public static function verificarJwt(string $jwt): ?array
    {
        $partes = explode('.', $jwt);
        if (count($partes) !== 3) {
            return null;
        }

        [$cabecera, $cuerpo, $firma] = $partes;

        $esperada = self::b64url(hash_hmac(
            'sha256', "$cabecera.$cuerpo", (string) config('onlyoffice.jwt_secret'), true
        ));

        if (!hash_equals($esperada, $firma)) {
            return null;
        }

        $datos = json_decode(self::b64urlDecode($cuerpo), true);

        return is_array($datos) ? $datos : null;
    }

    /**
     * Convierte un .docx a PDF usando el Document Server.
     *
     * @param string $urlDocx URL desde la que el Document Server baja el .docx.
     *                        Tiene que ser alcanzable POR EL, no por nosotros.
     * @param string $clave   Identifica la conversion. Si se repite con el
     *                        mismo valor, devuelve el resultado cacheado.
     *
     * @return string Contenido binario del PDF.
     *
     * @throws \RuntimeException con un mensaje en castellano si algo falla.
     */
    public static function convertirAPdf(string $urlDocx, string $clave): string
    {
        $peticion = [
            // Sincrono: se espera el PDF en la misma llamada. Un documento de
            // 8 paginas con imagenes tarda unos 4 segundos.
            'async'      => false,
            'filetype'   => 'docx',
            'outputtype' => 'pdf',
            'key'        => $clave,
            'title'      => 'manual.docx',
            'url'        => $urlDocx,
        ];
        $peticion['token'] = self::firmar($peticion);

        $ch = curl_init(config('onlyoffice.url_interna') . '/converter');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($peticion, JSON_UNESCAPED_SLASHES),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 180,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
                // La firma va TAMBIEN en la cabecera: es lo que valida el
                // Document Server cuando corre con JWT_HEADER=Authorization,
                // que es la configuracion por defecto.
                'Authorization: Bearer ' . self::firmar(['payload' => $peticion]),
            ],
        ]);

        $respuesta = curl_exec($ch);
        $errorCurl = curl_error($ch);
        $http      = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($respuesta === false) {
            throw new \RuntimeException('No se pudo contactar al servidor de conversion. ' . $errorCurl);
        }
        if ($http !== 200) {
            throw new \RuntimeException("El servidor de conversion respondio $http.");
        }

        $json = json_decode((string) $respuesta, true);

        // El Document Server contesta 200 incluso cuando falla: el error va
        // adentro del cuerpo. Sin este chequeo guardariamos un PDF vacio.
        if (isset($json['error'])) {
            throw new \RuntimeException(
                'El documento no se pudo convertir a PDF (codigo ' . $json['error'] . ').'
            );
        }
        if (empty($json['fileUrl'])) {
            throw new \RuntimeException('El servidor de conversion no devolvio ningun archivo.');
        }

        $pdf = self::bajarDelDocumentServer($json['fileUrl']);

        // %PDF- son los cinco bytes con los que empieza todo PDF. Si no estan,
        // lo que llego es otra cosa (una pagina de error, por ejemplo) y es
        // mejor cortar aca que publicar un archivo ilegible.
        if (!str_starts_with($pdf, '%PDF-')) {
            throw new \RuntimeException('Lo que devolvio el servidor de conversion no es un PDF.');
        }

        return $pdf;
    }

    /**
     * Baja un archivo que el Document Server dejo listo (una conversion o el
     * documento guardado desde el editor).
     *
     * OJO CON LA URL: el Document Server devuelve direcciones armadas desde
     * SU punto de vista, que desde este servidor pueden no resolver. Por eso
     * se le reemplaza el origen por 'url_interna', que es por donde nosotros
     * si lo alcanzamos.
     */
    public static function bajarDelDocumentServer(string $url): string
    {
        $partes = parse_url($url);
        $camino = ($partes['path'] ?? '')
                . (isset($partes['query']) ? '?' . $partes['query'] : '');

        $ch = curl_init(config('onlyoffice.url_interna') . $camino);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 180,
            CURLOPT_FOLLOWLOCATION => false,
        ]);

        $contenido = curl_exec($ch);
        $errorCurl = curl_error($ch);
        $http      = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($contenido === false || $http !== 200) {
            throw new \RuntimeException(
                'No se pudo descargar el archivo del servidor de edicion'
                . ($errorCurl ? ": $errorCurl" : " (HTTP $http).")
            );
        }

        return $contenido;
    }

    private static function b64urlDecode(string $dato): string
    {
        return (string) base64_decode(strtr($dato, '-_', '+/'));
    }

    private static function b64url(string $dato): string
    {
        return rtrim(strtr(base64_encode($dato), '+/', '-_'), '=');
    }
}
