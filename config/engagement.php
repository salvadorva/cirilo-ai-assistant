<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Engagement & Retention Configuration
    |--------------------------------------------------------------------------
    |
    | Configuración para el sistema de engagement y retención de usuarios.
    | Estos valores controlan cuándo y cómo se detectan usuarios inactivos
    | y se envían campañas de re-engagement.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Umbrales de Inactividad (en días)
    |--------------------------------------------------------------------------
    */
    'inactivity_thresholds' => [
        'reminder' => 3,        // Recordatorio suave
        'warning' => 7,         // Advertencia
        'urgent' => 14,         // Campaña urgente
        'critical' => 21,       // Riesgo crítico
        'churn' => 30,          // Considerado perdido
    ],

    /*
    |--------------------------------------------------------------------------
    | Umbrales de Engagement Score
    |--------------------------------------------------------------------------
    */
    'engagement_thresholds' => [
        'excellent' => 80,      // Usuario altamente comprometido
        'good' => 60,           // Usuario comprometido
        'fair' => 40,           // Usuario moderadamente comprometido
        'poor' => 20,           // Usuario poco comprometido
        'critical' => 10,       // Usuario en riesgo crítico
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuración de Risk Levels
    |--------------------------------------------------------------------------
    */
    'risk_levels' => [
        'low' => [
            'max_days_inactive' => 3,
            'min_engagement_score' => 60,
            'color' => '#28a745',
            'label' => 'Bajo Riesgo',
        ],
        'medium' => [
            'max_days_inactive' => 7,
            'min_engagement_score' => 40,
            'color' => '#ffc107',
            'label' => 'Riesgo Medio',
        ],
        'high' => [
            'max_days_inactive' => 14,
            'min_engagement_score' => 20,
            'color' => '#fd7e14',
            'label' => 'Alto Riesgo',
        ],
        'critical' => [
            'max_days_inactive' => 999,
            'min_engagement_score' => 0,
            'color' => '#dc3545',
            'label' => 'Riesgo Crítico',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuración de Emails
    |--------------------------------------------------------------------------
    */
    'emails' => [
        'enabled' => env('ENGAGEMENT_EMAILS_ENABLED', true),
        'from_address' => env('MAIL_FROM_ADDRESS', 'noreply@typemaster.ai'),
        'from_name' => env('MAIL_FROM_NAME', 'TypeMaster AI'),

        // Frecuencia de envío (en horas)
        'frequency' => [
            'inactivity_reminder' => 24,    // Máximo 1 por día
            'reconnection_campaign' => 72,  // Máximo 1 cada 3 días
            'weekly_report' => 168,         // Una vez por semana
        ],

        // Límites de envío
        'limits' => [
            'max_reminders_per_user' => 3,  // Máximo 3 recordatorios por usuario
            'max_campaigns_per_user' => 2,  // Máximo 2 campañas por usuario
            'cooldown_days' => 7,           // Días de espera entre emails del mismo tipo
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuración de Engagement Score
    |--------------------------------------------------------------------------
    */
    'scoring' => [
        'weights' => [
            'recent_activity' => 40,    // 40% - Actividad reciente
            'session_frequency' => 30,  // 30% - Frecuencia de sesiones
            'streak_consistency' => 20, // 20% - Consistencia de racha
            'progress_achievement' => 10, // 10% - Logros y progreso
        ],

        // Configuración de actividad reciente (días)
        'recent_activity_ranges' => [
            'excellent' => 1,   // 40 puntos
            'good' => 3,        // 30 puntos
            'fair' => 7,        // 20 puntos
            'poor' => 14,       // 10 puntos
            'critical' => 999,   // 0 puntos
        ],

        // Configuración de frecuencia semanal (sesiones)
        'weekly_frequency_ranges' => [
            'excellent' => 7,   // 30 puntos
            'good' => 5,        // 25 puntos
            'fair' => 3,        // 20 puntos
            'poor' => 1,        // 15 puntos
            'critical' => 0,     // 0 puntos
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuración de Automatización
    |--------------------------------------------------------------------------
    */
    'automation' => [
        // Horarios de ejecución de comandos (cron expressions)
        'schedules' => [
            'update_scores' => '0 */6 * * *',      // Cada 6 horas
            'detect_inactive' => '0 9 * * *',      // Diario a las 9 AM
            'send_weekly_reports' => '0 10 * * 1',  // Lunes a las 10 AM
            'reset_weekly_counters' => '0 0 * * 1', // Lunes a medianoche
        ],

        // Configuración de queue
        'queue' => [
            'name' => 'engagement',
            'connection' => env('QUEUE_CONNECTION', 'sync'),
            'retry_after' => 300,   // 5 minutos
            'max_tries' => 3,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuración de Incentivos
    |--------------------------------------------------------------------------
    */
    'incentives' => [
        'comeback_bonus' => [
            'enabled' => true,
            'xp_per_day_inactive' => 5,     // 5 XP por día inactivo
            'max_bonus_xp' => 100,          // Máximo 100 XP de bonus
        ],

        'streak_protection' => [
            'enabled' => true,
            'min_streak_to_protect' => 3,   // Mínimo 3 días de racha
            'protection_days' => 2,         // Proteger por 2 días
        ],

        'level_boost' => [
            'enabled' => true,
            'multiplier' => 1.5,            // 50% más XP
            'duration_days' => 3,           // Por 3 días
            'min_level_required' => 2,      // Mínimo nivel 2
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuración de Analytics
    |--------------------------------------------------------------------------
    */
    'analytics' => [
        'retention_periods' => [
            'daily' => 1,       // Retención día 1
            'weekly' => 7,      // Retención día 7
            'monthly' => 30,    // Retención día 30
        ],

        'cohort_analysis' => [
            'enabled' => true,
            'periods' => ['1d', '3d', '7d', '14d', '30d'],
        ],

        'kpis' => [
            'target_daily_retention' => 40,    // 40% retención diaria
            'target_weekly_retention' => 20,   // 20% retención semanal
            'target_monthly_retention' => 10,  // 10% retención mensual
            'target_avg_engagement' => 65,     // Score promedio de 65
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuración de Testing
    |--------------------------------------------------------------------------
    */
    'testing' => [
        'dry_run_mode' => env('ENGAGEMENT_DRY_RUN', false),
        'test_email' => env('ENGAGEMENT_TEST_EMAIL', null),
        'mock_inactive_days' => env('ENGAGEMENT_MOCK_DAYS', null),
    ],
];
