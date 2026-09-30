<?php

return [
    'requests_per_minute' => [
        'chat' => 20,
        'image' => 4,
        'tts' => 20,
        'stt' => 10,
        'generation' => 10,
        'conversation' => 30,
        'resource' => 60,
    ],
    'input_bytes' => 131072,
    'conversation_bytes' => 1048576,
    'string_length' => 16000,
    'history_messages' => 2000,
    'prompt_length' => 8000,
    'tts_length' => 3000,
    // Diagnostic content is deliberately not retained. Operational metadata only.
    'log_retention_days' => 14,
];
