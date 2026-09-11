<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Progressive Web App Configuration
    |--------------------------------------------------------------------------
    |
    | Configuración para la funcionalidad PWA de la aplicación
    |
    */

    'name' => env('PWA_NAME', 'Asistente Personal IA'),
    'short_name' => env('PWA_SHORT_NAME', 'Asistente IA'),
    'description' => env('PWA_DESCRIPTION', 'Tu asistente personal con IA para gestión de agenda, cursos de inglés y más'),

    'start_url' => env('PWA_START_URL', '/'),
    'display' => env('PWA_DISPLAY', 'standalone'),
    'orientation' => env('PWA_ORIENTATION', 'portrait-primary'),
    'scope' => env('PWA_SCOPE', '/'),

    'theme_color' => env('PWA_THEME_COLOR', '#232946'),
    'background_color' => env('PWA_BACKGROUND_COLOR', '#232946'),

    'lang' => env('PWA_LANG', 'es'),
    'dir' => env('PWA_DIR', 'ltr'),

    'categories' => [
        'productivity',
        'education',
        'lifestyle',
    ],

    'icons' => [
        [
            'src' => '/pwa-icons/icon-72x72.png',
            'sizes' => '72x72',
            'type' => 'image/png',
            'purpose' => 'any maskable',
        ],
        [
            'src' => '/pwa-icons/icon-96x96.png',
            'sizes' => '96x96',
            'type' => 'image/png',
            'purpose' => 'any maskable',
        ],
        [
            'src' => '/pwa-icons/icon-128x128.png',
            'sizes' => '128x128',
            'type' => 'image/png',
            'purpose' => 'any maskable',
        ],
        [
            'src' => '/pwa-icons/icon-144x144.png',
            'sizes' => '144x144',
            'type' => 'image/png',
            'purpose' => 'any maskable',
        ],
        [
            'src' => '/pwa-icons/icon-152x152.png',
            'sizes' => '152x152',
            'type' => 'image/png',
            'purpose' => 'any maskable',
        ],
        [
            'src' => '/pwa-icons/icon-192x192.png',
            'sizes' => '192x192',
            'type' => 'image/png',
            'purpose' => 'any maskable',
        ],
        [
            'src' => '/pwa-icons/icon-384x384.png',
            'sizes' => '384x384',
            'type' => 'image/png',
            'purpose' => 'any maskable',
        ],
        [
            'src' => '/pwa-icons/icon-512x512.png',
            'sizes' => '512x512',
            'type' => 'image/png',
            'purpose' => 'any maskable',
        ],
    ],

    'shortcuts' => [
        [
            'name' => 'Agenda',
            'short_name' => 'Agenda',
            'description' => 'Acceso rápido a tu agenda',
            'url' => '/agenda',
            'icons' => [
                [
                    'src' => '/icons/icon-192x192.png',
                    'sizes' => '192x192',
                ],
            ],
        ],
        [
            'name' => 'Tutor de Inglés',
            'short_name' => 'Tutor',
            'description' => 'Practica inglés con IA',
            'url' => '/tutor',
            'icons' => [
                [
                    'src' => '/icons/icon-192x192.png',
                    'sizes' => '192x192',
                ],
            ],
        ],
        [
            'name' => 'Cursos',
            'short_name' => 'Cursos',
            'description' => 'Tus cursos personalizados',
            'url' => '/cursos',
            'icons' => [
                [
                    'src' => '/icons/icon-192x192.png',
                    'sizes' => '192x192',
                ],
            ],
        ],
    ],

    'cache' => [
        'version' => env('PWA_CACHE_VERSION', '1.0.0'),
        'name' => env('PWA_CACHE_NAME', 'asistente-pwa'),
        'urls' => [
            '/',
            '/tutor',
            '/agenda',
            '/cursos',
            '/offline',
            // CSS
            '/resources/css/app.css',
            '/src/assets/css/light/elements/alert.css',
            '/src/assets/css/dark/elements/alert.css',
            // JS
            '/js/agenda-voice-assistant.js',
            // Imágenes
            '/resources/favicon.ico',
            '/icons/icon-192x192.png',
            '/icons/icon-512x512.png',
        ],
        'external_urls' => [
            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
            'https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css',
            'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css',
        ],
    ],

    'screenshots' => [
        // Screenshots removidos temporalmente para evitar errores 404
        // Agregar cuando tengas screenshots reales de la aplicación
    ],

    'notifications' => [
        'enabled' => env('PWA_NOTIFICATIONS_ENABLED', true),
        'vapid_public_key' => env('PWA_VAPID_PUBLIC_KEY'),
        'vapid_private_key' => env('PWA_VAPID_PRIVATE_KEY'),
        'vapid_subject' => env('PWA_VAPID_SUBJECT', 'mailto:admin@asistente.com'),
    ],

    'features' => [
        'install_prompt' => env('PWA_INSTALL_PROMPT', true),
        'update_notifications' => env('PWA_UPDATE_NOTIFICATIONS', true),
        'offline_page' => env('PWA_OFFLINE_PAGE', true),
        'connection_status' => env('PWA_CONNECTION_STATUS', true),
    ],
];
