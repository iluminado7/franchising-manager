<?php

/*
|--------------------------------------------------------------------------
| OnlyOffice Document Server
|--------------------------------------------------------------------------
|
| Edicion de manuales en formato Word (.docx) dentro del navegador, en
| reemplazo del editor propio (public/editor.php) y de la conversion con
| Mammoth, que aproximaba el documento a HTML y perdia fidelidad.
|
| TODO ESTO ESTA APAGADO SI FALTA CONFIGURACION. Ver habilitado() abajo: sin
| 'url' o sin 'jwt_secret' el sistema se comporta exactamente como antes.
| Es deliberado: el codigo puede desplegarse a produccion sin que exista
| ningun Document Server, y se enciende el dia que haya uno.
|
| ── LAS TRES URLS ────────────────────────────────────────────────────────
|
| Hay tres partes hablando entre si, y cada una ve a las otras por una
| direccion distinta. Confundirlas es el error clasico de esta integracion:
|
|   1. 'url'         NAVEGADOR -> Document Server
|                    De aca baja el JavaScript del editor. La escribe el
|                    navegador del usuario, asi que tiene que ser publica.
|                    En produccion DEBE ser https: el sistema corre en https
|                    y el navegador bloquea cargar scripts por http.
|
|   2. 'url_interna' LARAVEL -> Document Server
|                    Para pedirle conversiones (/converter). No pasa por el
|                    navegador, asi que puede ser la direccion privada: en
|                    produccion, la IP interna de la VPC; en local, la misma
|                    que 'url'.
|
|   3. 'url_sistema' Document Server -> LARAVEL
|                    Con esta el Document Server baja el .docx y devuelve el
|                    documento guardado. En local el contenedor NO llega por
|                    'localhost' (eso es el contenedor mismo): se usa
|                    host.docker.internal. En produccion es el dominio real.
|
| ── LA FIRMA ─────────────────────────────────────────────────────────────
|
| 'jwt_secret' es una clave COMPARTIDA con el Document Server: la misma que
| lleva el contenedor en su variable JWT_SECRET. Todo pedido va firmado con
| ella; sin firma valida, el Document Server rechaza.
|
| Importa que sea larga y aleatoria: quien la tenga puede hacer que el
| Document Server descargue cualquier URL que nuestro servidor alcance.
|
*/

return [

    'url' => rtrim((string) env('ONLYOFFICE_URL', ''), '/'),

    'url_interna' => rtrim((string) env(
        'ONLYOFFICE_URL_INTERNA',
        (string) env('ONLYOFFICE_URL', '')
    ), '/'),

    'url_sistema' => rtrim((string) env(
        'ONLYOFFICE_URL_SISTEMA',
        (string) env('APP_URL', '')
    ), '/'),

    'jwt_secret' => (string) env('ONLYOFFICE_JWT_SECRET', ''),

    // Minutos de validez del enlace con el que el Document Server baja el
    // .docx. Es corto a proposito: ese enlace entrega el documento SIN
    // sesion, porque el Document Server no es un navegador y no tiene la
    // cookie. Solo tiene que vivir lo que tarda la descarga inicial.
    'minutos_token' => (int) env('ONLYOFFICE_MINUTOS_TOKEN', 10),

    // Minutos de validez del enlace por el que el Document Server nos DEVUELVE
    // el documento guardado. Mucho mas largo que el de descarga, y por un
    // motivo concreto: el aviso de guardado puede llegar horas despues de
    // abrir el editor (alguien que deja el manual abierto toda la jornada).
    // Si vence, el trabajo de esa sesion se pierde.
    'minutos_callback' => (int) env('ONLYOFFICE_MINUTOS_CALLBACK', 720),

    // Tamano maximo del .docx que se acepta importar, en KB.
    // 51200 = 50 MB, el mismo tope que ya tienen los manuales en PDF.
    'max_kb' => (int) env('ONLYOFFICE_MAX_KB', 51200),

];
