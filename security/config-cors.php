<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'login', 'logout', 'register'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        // DESARROLLO LOCAL
        'http://localhost',
        'http://localhost:8000',
        'http://127.0.0.1',
        'http://127.0.0.1:8000',

        // AGREGAR AQUÍ LOS DOMINIOS DE TU PROYECTO:
        // 'http://tu-dominio-local.com',
        // 'https://tu-dominio-local.com',
        // 'https://tu-dominio-produccion.com',
        // 'https://tu-dominio-secundario.com',
    ],

    'allowed_origins_patterns' => [
        // Patrones para dominios dinámicos si es necesario
        // 'https://*.tu-dominio.com',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 86400,

    'supports_credentials' => true,

];
