<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Security Headers Configuration
    |--------------------------------------------------------------------------
    |
    | Esta configuración define los security headers que se aplicarán
    | a todas las respuestas de la aplicación.
    |
    */

    'headers' => [
        /*
        |--------------------------------------------------------------------------
        | X-Content-Type-Options
        |--------------------------------------------------------------------------
        |
        | Previene MIME type sniffing attacks
        |
        */
        'x_content_type_options' => 'nosniff',

        /*
        |--------------------------------------------------------------------------
        | X-Frame-Options
        |--------------------------------------------------------------------------
        |
        | Previene clickjacking attacks
        | Valores: DENY, SAMEORIGIN, ALLOW-FROM uri
        |
        */
        'x_frame_options' => 'DENY',

        /*
        |--------------------------------------------------------------------------
        | X-XSS-Protection
        |--------------------------------------------------------------------------
        |
        | Activa la protección XSS del navegador
        |
        */
        'x_xss_protection' => '1; mode=block',

        /*
        |--------------------------------------------------------------------------
        | Referrer-Policy
        |--------------------------------------------------------------------------
        |
        | Controla cuánta información de referrer se envía
        |
        */
        'referrer_policy' => 'strict-origin-when-cross-origin',

        /*
        |--------------------------------------------------------------------------
        | Strict-Transport-Security (HSTS)
        |--------------------------------------------------------------------------
        |
        | Fuerza el uso de HTTPS (solo aplicable si la app usa HTTPS)
        |
        */
        'hsts' => [
            'max_age' => 31536000, // 1 año en segundos
            'include_subdomains' => true,
            'preload' => true,
        ],

        /*
        |--------------------------------------------------------------------------
        | Content-Security-Policy
        |--------------------------------------------------------------------------
        |
        | Define las fuentes permitidas para diferentes tipos de contenido
        | IMPORTANTE: Actualizar connect_src con los dominios de tu proyecto
        |
        */
        'csp' => [
            'default_src' => ["'self'"],
            'script_src' => [
                "'self'",
                "'unsafe-inline'",
                "'unsafe-eval'",
                'https://cdn.jsdelivr.net',
                'https://cdnjs.cloudflare.com',
                'https://code.jquery.com',
                'https://unpkg.com',
                'https://www.google.com',
                'https://www.gstatic.com',
                'https://cdn.quilljs.com',
            ],
            'style_src' => [
                "'self'",
                "'unsafe-inline'",
                'https://fonts.googleapis.com',
                'https://cdn.jsdelivr.net',
                'https://cdnjs.cloudflare.com',
                'https://unpkg.com',
                'https://cdn.quilljs.com',
            ],
            'font_src' => [
                "'self'",
                'https://fonts.gstatic.com',
                'https://cdn.jsdelivr.net',
                'https://cdnjs.cloudflare.com',
                'data:',
            ],
            'img_src' => [
                "'self'",
                'data:',
                'blob:',
                'https:',
                'http:',
            ],
            'connect_src' => [
                "'self'",
                // AGREGAR AQUÍ LOS DOMINIOS DE TU PROYECTO:
                // 'http://tu-dominio-local.com',
                // 'https://tu-dominio-local.com',
                // 'https://tu-dominio-produccion.com',
                'https://api.github.com',
                'https://cdn.jsdelivr.net',
                'https://cdn.quilljs.com',
                'wss:',
                'ws:',
            ],
            'frame_src' => [
                "'self'",
                'https://www.google.com',
                'https://www.youtube.com',
            ],
            'object_src' => ["'none'"],
            'base_uri' => ["'self'"],
            'form_action' => ["'self'"],
            'upgrade_insecure_requests' => true,
        ],

        /*
        |--------------------------------------------------------------------------
        | Permissions-Policy
        |--------------------------------------------------------------------------
        |
        | Controla el acceso a APIs sensibles del navegador
        |
        */
        'permissions_policy' => [
            'camera' => [],
            'microphone' => [],
            'geolocation' => [],
            'interest-cohort' => [],
        ],

        /*
        |--------------------------------------------------------------------------
        | Cross-Origin Policies
        |--------------------------------------------------------------------------
        |
        | Configuración para políticas de cross-origin
        | NOTA: Para CDNs externos, usar 'unsafe-none' en cross_origin_embedder_policy
        |
        */
        'cross_origin_embedder_policy' => 'unsafe-none',
        'cross_origin_opener_policy' => 'same-origin',
        'cross_origin_resource_policy' => 'cross-origin',
    ],

    /*
    |--------------------------------------------------------------------------
    | Headers to Remove
    |--------------------------------------------------------------------------
    |
    | Headers que deben ser removidos por seguridad
    |
    */
    'remove_headers' => [
        'Server',
        'X-Powered-By',
    ],

    /*
    |--------------------------------------------------------------------------
    | Development Mode
    |--------------------------------------------------------------------------
    |
    | En modo desarrollo, algunos headers pueden ser más permisivos
    |
    */
    'development_mode' => env('APP_DEBUG', false),
];
