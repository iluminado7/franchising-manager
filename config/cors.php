<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // H-013 fix: dominios desde env var CORS_ALLOWED_ORIGINS (separados por coma).
    // Ejemplo en .env:
    //   CORS_ALLOWED_ORIGINS=http://localhost,https://mi-dominio-prod.com
    //
    // Si la variable no está definida, cae a http://localhost (dev). En prod la
    // variable siempre debería estar definida explícita en el .env de Laravel Cloud.
    'allowed_origins' => array_filter(array_map(
        'trim',
        explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost'))
    )),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];