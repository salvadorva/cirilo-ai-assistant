<?php

return [
    // Keep the existing model choices. F1 measures them; it does not migrate them.
    'models' => [
        'chat' => 'gpt-4.1', 'extraction' => 'gpt-4.1-mini', 'summary' => 'gpt-4o-mini',
        'vision' => 'gpt-4o', 'legacy_creative' => 'gpt-4', 'tutor' => 'gpt-3.5-turbo',
        'grok' => 'grok-4-1-fast-reasoning', 'creative_grok' => 'grok-3', 'image' => 'gpt-image-1',
        'tts' => 'tts-1', 'tts_hd' => 'tts-1-hd', 'stt' => 'whisper-1',
    ],
    // USD, standard (not Batch/Flex), verified against official model pages.
    'pricing_date' => '2026-09-22',
    'pricing_source' => 'https://developers.openai.com/api/docs/pricing',
    'prices' => [
        'openai' => [
            'gpt-4.1' => ['input' => 2, 'cached' => 0.5, 'output' => 8],
            'gpt-4.1-mini' => ['input' => 0.4, 'cached' => 0.1, 'output' => 1.6],
            'gpt-4o-mini' => ['input' => 0.15, 'cached' => 0.075, 'output' => 0.6],
            'gpt-4o' => ['input' => 2.5, 'cached' => 1.25, 'output' => 10],
            'gpt-4' => ['input' => 30, 'output' => 60],
            'gpt-3.5-turbo' => ['input' => 0.5, 'output' => 1.5],
            'gpt-image-1' => ['input' => 5, 'image_input' => 10, 'output' => 40],
            'tts-1' => ['characters' => 15],
            'tts-1-hd' => ['characters' => 30],
            'whisper-1' => ['minute' => 0.006],
        ],
        // Do not silently assign OpenAI tariffs to xAI models.
        'grok' => [],
    ],
    'aliases' => [
        'gpt-4.1-2025-04-14' => 'gpt-4.1',
        'gpt-4.1-mini-2025-04-14' => 'gpt-4.1-mini',
        'gpt-4o-mini-2024-07-18' => 'gpt-4o-mini',
        'gpt-4o-2024-08-06' => 'gpt-4o',
        'gpt-4-0613' => 'gpt-4',
        'gpt-3.5-turbo-0125' => 'gpt-3.5-turbo',
    ],
    'web_search_preview_per_call' => 0.025, // Non-reasoning models; search content free.
    // F4-02: composición del contexto del chat. Estimación de tokens ≈ caracteres / 4.
    'context' => [
        'history_messages' => 10,
        'history_budget_tokens' => 4000,
        'memory_budget_chars' => 8000,
    ],
    // F2-02: agenda por herramientas del modelo (solo OpenAI; Grok usa el flujo determinista). Apagado por defecto.
    'agenda_tools' => [
        'enabled' => (bool) env('AGENDA_TOOLS_ENABLED', false),
        'max_iterations' => 4,
    ],
    // F5-04: un reintento para errores recuperables del proveedor (429, 5xx, red). Nunca para 4xx.
    'retry' => [
        'max' => 1,
        'delay_ms' => 500,
    ],
    // F5-06: tamaño del contexto de la búsqueda web del chat (low | medium | high).
    'web_search' => [
        'context_size' => env('AI_WEB_SEARCH_CONTEXT', 'medium'),
    ],
    // IE1: edición de imágenes (solo app). Decisiones de Salva del 30/09/2026: calidad media, tamaño
    // automático, resultado 7 días, cuota compartida con la generación. Apagado hasta la prueba real.
    'image_edit' => [
        'enabled' => (bool) env('IMAGE_EDIT_ENABLED', false),
        'model' => 'gpt-image-1',
        'quality' => 'medium',
        'size' => 'auto',
        'timeout_seconds' => 120,
        'max_side' => 2048,
        'retention_days' => 7,
        // Imágenes por cadena: la edición inicial más dos ajustes guiados por Cirilo.
        'max_rounds' => 3,
    ],
];
