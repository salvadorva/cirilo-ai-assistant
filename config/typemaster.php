<?php

return [

    /*
    |--------------------------------------------------------------------------
    | TypeMaster Configuration
    |--------------------------------------------------------------------------
    |
    | Configuración específica para el sistema TypeMaster AI de mecanografía.
    | Aquí se definen las configuraciones de teclado, idioma y ejercicios.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Keyboard Layout
    |--------------------------------------------------------------------------
    |
    | Layout de teclado utilizado en el sistema. Por defecto se usa el layout
    | español que incluye la ñ en la fila central.
    |
    */

    'keyboard_layout' => env('TYPEMASTER_KEYBOARD_LAYOUT', 'spanish'),

    'keyboard_layouts' => [
        'spanish' => [
            'name' => 'Español',
            'home_row' => [
                'left' => ['a', 's', 'd', 'f'],
                'right' => ['j', 'k', 'l', 'ñ'],
            ],
            'top_row' => [
                'left' => ['q', 'w', 'e', 'r', 't'],
                'right' => ['y', 'u', 'i', 'o', 'p'],
            ],
            'bottom_row' => [
                'left' => ['z', 'x', 'c', 'v'],
                'right' => ['b', 'n', 'm', ','],
            ],
            'numbers' => ['1', '2', '3', '4', '5', '6', '7', '8', '9', '0'],
            'symbols' => ['+', '¡', '´', '¿', "'", '!', '"', '·', '$', '%', '&', '/', '(', ')', '='],
        ],
        'english' => [
            'name' => 'English',
            'home_row' => [
                'left' => ['a', 's', 'd', 'f'],
                'right' => ['j', 'k', 'l', ';'],
            ],
            'top_row' => [
                'left' => ['q', 'w', 'e', 'r', 't'],
                'right' => ['y', 'u', 'i', 'o', 'p'],
            ],
            'bottom_row' => [
                'left' => ['z', 'x', 'c', 'v'],
                'right' => ['b', 'n', 'm', ','],
            ],
            'numbers' => ['1', '2', '3', '4', '5', '6', '7', '8', '9', '0'],
            'symbols' => ['!', '@', '#', '$', '%', '^', '&', '*', '(', ')', '-', '_', '=', '+'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Training Settings
    |--------------------------------------------------------------------------
    |
    | Configuración de los ejercicios de entrenamiento y modos de juego.
    |
    */

    'training' => [
        'default_session_duration' => 300, // 5 minutos en segundos
        'min_accuracy_for_progress' => 85, // Porcentaje mínimo de precisión
        'target_wpm' => [
            'beginner' => 20,
            'intermediate' => 40,
            'advanced' => 60,
            'expert' => 80,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Game Modes Configuration
    |--------------------------------------------------------------------------
    |
    | Configuración específica para cada modo de juego.
    |
    */

    'game_modes' => [
        'training' => [
            'enabled' => true,
            'xp_multiplier' => 1.0,
            'modes' => [
                'central-row' => 'Fila Central',
                'top-row' => 'Fila Superior',
                'bottom-row' => 'Fila Inferior',
                'numbers' => 'Números y Símbolos',
                'common-words' => 'Palabras Comunes',
                'finger-combos' => 'Combinaciones de Dedos',
            ],
        ],
        'arcade' => [
            'enabled' => true,
            'xp_multiplier' => 1.2,
            'lives' => 5,
            'speed_increase_interval' => 30, // segundos
        ],
        'survival' => [
            'enabled' => true,
            'xp_multiplier' => 1.5,
            'enemies_count' => 8,
            'difficulty_progression' => true,
        ],
        'zen' => [
            'enabled' => true,
            'xp_multiplier' => 1.1,
            'default_session_duration' => 10, // minutos
            'content_types' => [
                'inspirational' => 'Frases Inspiradoras',
                'mindfulness' => 'Mindfulness',
                'poetry' => 'Poesía',
                'philosophy' => 'Filosofía',
                'nature' => 'Naturaleza',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | XP and Leveling System
    |--------------------------------------------------------------------------
    |
    | Sistema de experiencia y niveles para TypeMaster AI.
    |
    */

    'xp_system' => [
        'base_xp_per_word' => 5,
        'accuracy_bonus_threshold' => 95, // Porcentaje para bonus de precisión
        'accuracy_bonus_multiplier' => 1.2,
        'speed_bonus_threshold' => 50, // WPM para bonus de velocidad
        'speed_bonus_multiplier' => 1.3,
        'level_up_base_xp' => 100,
        'level_up_multiplier' => 1.15, // Cada nivel requiere 15% más XP
    ],

    /*
    |--------------------------------------------------------------------------
    | Language and Content
    |--------------------------------------------------------------------------
    |
    | Configuración de idioma y contenido para los ejercicios.
    |
    */

    'language' => env('TYPEMASTER_LANGUAGE', 'es'),

    'content' => [
        'es' => [
            'common_words' => [
                'que', 'para', 'con', 'una', 'por', 'de', 'la', 'en', 'se', 'el',
                'es', 'te', 'lo', 'le', 'da', 'su', 'por', 'son', 'pero', 'sus',
                'como', 'esto', 'estar', 'tener', 'hacer', 'todo', 'ser', 'poder',
                'decir', 'uno', 'ir', 'mi', 'ya', 'sobre', 'también', 'tiempo',
                'él', 'ella', 'más', 'muy', 'cuando', 'pueden', 'hasta', 'forma',
            ],
            'practice_sentences' => [
                'El gato duerme en la ventana soleada.',
                'María estudia español todos los días.',
                'Los niños juegan en el parque verde.',
                'Mi hermana cocina una deliciosa paella.',
                'El profesor explica la lección con paciencia.',
                'Las flores del jardín huelen muy bien.',
                'Carlos lee un libro interesante.',
                'La música suena hermosa esta noche.',
                'Mis amigos vienen a visitarme mañana.',
                'El cielo está lleno de estrellas brillantes.',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Audio Settings
    |--------------------------------------------------------------------------
    |
    | Configuración del sistema de audio para feedback y ambiente.
    |
    */

    'audio' => [
        'enabled' => env('TYPEMASTER_AUDIO_ENABLED', true),
        'default_volume' => 0.3,
        'sounds' => [
            'correct_keystroke' => '/audio/hit.mp3',
            'incorrect_keystroke' => '/audio/miss.mp3',
            'game_start' => '/audio/game_start.mp3',
            'level_complete' => '/audio/level_complete.mp3',
            'power_up' => '/audio/power_up.mp3',
            'game_over' => '/audio/game_over.mp3',
        ],
        'ambient_sounds' => [
            'rain' => '/audio/ambient/rain.mp3',
            'ocean' => '/audio/ambient/ocean.mp3',
            'forest' => '/audio/ambient/forest.mp3',
            'nature' => '/audio/ambient/nature.mp3',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | UI Customization
    |--------------------------------------------------------------------------
    |
    | Configuraciones de interfaz de usuario y personalización.
    |
    */

    'ui' => [
        'theme' => env('TYPEMASTER_THEME', 'default'),
        'show_keyboard_hints' => true,
        'highlight_current_key' => true,
        'show_finger_guide' => true,
        'animation_speed' => 'normal', // slow, normal, fast
        'color_scheme' => [
            'correct' => '#28a745',
            'incorrect' => '#dc3545',
            'current' => '#007bff',
            'pending' => '#6c757d',
        ],
    ],

];
