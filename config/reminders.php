<?php

/*
|--------------------------------------------------------------------------
| Recordatorios contextuales (plan RC)
|--------------------------------------------------------------------------
|
| Acuerdos puntuales Salva–Hermes persistidos por Cirilo. Ver
| docs/PLAN-RECORDATORIOS-CONTEXTUALES.md. Todas las funciones nacen
| apagadas: activarlas requiere autorización explícita del piloto.
|
*/

return [
    'hermes_api_enabled' => (bool) env('REMINDERS_HERMES_API_ENABLED', false),
    'mobile_api_enabled' => (bool) env('REMINDERS_MOBILE_API_ENABLED', false),
    // Decisión de Salva (23/09/2026): piloto solo texto, sin audio. La variante
    // privada (RC4) está probada con TTS falso; activarla requiere nueva decisión.
    // 30/09/2026: Salva aprueba el audio bajo petición (privado, sin reproducción automática).
    'audio_enabled' => (bool) env('REMINDERS_AUDIO_ENABLED', false),
    'audio' => ['timeout_seconds' => 30],

    // RC2 — despacho durable. Apagado hasta autorizar el piloto.
    'contextual_dispatch_enabled' => (bool) env('REMINDERS_CONTEXTUAL_DISPATCH_ENABLED', false),
    'routine_dispatch_enabled' => (bool) env('REMINDERS_ROUTINE_DISPATCH_ENABLED', false),
    // Instante de corte ISO 8601: el camino nuevo de rutinas solo toma ocurrencias posteriores.
    'routine_dispatch_cutover_at' => env('REMINDERS_ROUTINE_DISPATCH_CUTOVER_AT'),
    // Piloto por usuario: recibe en su instalación compatible más reciente (sobrevive a reinstalar).
    'dispatch_user_allowlist' => array_values(array_filter(array_map('intval', explode(',', (string) env('REMINDERS_DISPATCH_USER_IDS', ''))))),
    // Piloto por instalación: IDs de device_tokens elegidos uno a uno (se pierde al reinstalar).
    'dispatch_device_allowlist' => array_values(array_filter(array_map('intval', explode(',', (string) env('REMINDERS_DISPATCH_DEVICE_IDS', ''))))),
    'device_capability' => 'contextual_reminders_v1',
    // Capacidades que el backend reconoce al registrar una instalación (RC3).
    'known_capabilities' => ['contextual_reminders_v1'],
    // Reclamo explícito de instalación: intentos por hora, por usuario y por instalación.
    'claims' => ['attempts_per_hour' => 5],
    // Crear exige un destino compatible (409 device_not_ready si no existe).
    'require_ready_device' => true,

    'dispatch' => [
        'http_timeout' => 20,
        'connect_timeout' => 5,
        'job_timeout' => 45,
        'lease_seconds' => 120,
        'max_attempts' => 3,
        'backoff_base_seconds' => 60,
        'jitter_ratio' => 0.2,
        'requeue_after_seconds' => 90,
        'batch' => 100,
    ],

    'routine' => [
        'timezone' => 'America/Guatemala',
        // Se conserva la ventana de recuperación actual de focus:send-messages.
        'catchup_minutes' => 10,
        // Vigencia del push de rutina (TTL); después ya no es pertinente.
        'expiry_minutes' => 60,
    ],

    'default_timezone' => 'America/Guatemala',
    'voices' => ['alloy', 'echo', 'fable', 'onyx', 'nova', 'shimmer'],

    'limits' => [
        'title' => 120,
        'context' => 500,
        'next_action' => 300,
    ],

    // Aprobado por Salva (23/09/2026): caducidad sugerida 90 min (la propone Hermes),
    // máximo 4 h, horizonte 7 días, 5 pendientes y 10 altas diarias.
    'suggested_expiry_minutes' => 90,
    'min_lead_seconds' => 60,
    'max_horizon_days' => 7,
    'max_window_minutes' => 240,
    'confirmation_skew_seconds' => 300,
    'snooze_minutes' => [15, 30, 60],
    'max_pending' => 5,
    'max_daily_creates' => 10,

    'rate_limits' => [
        'mutations_per_minute' => 10,
        'reads_per_minute' => 60,
        'auth_failures_per_minute' => 20,
    ],

    'per_page' => 20,
    'max_per_page' => 50,
    // Retención aprobada por Salva: contenido 7 días tras el cierre; metadatos e
    // idempotencia 30 días. La purga todavía no está implementada ni programada.
    'content_retention_days' => 7,
    'idempotency_retention_days' => 30,
    // RC5: la purga programada solo borra si esto está activo (requiere autorización). Sin el flag,
    // `reminders:purge` solo informa.
    'retention_purge_enabled' => (bool) env('REMINDERS_RETENTION_PURGE_ENABLED', false),
];
