<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

/**
 * Adapter for legacy AI logs which may interpolate personal content in messages.
 * Only a call-site identifier and allowlisted operational fields reach disk.
 */
class AiLog
{
    public static function metadata(array $context): array
    {
        $safe = [];
        $numbers = ['user_id', 'conversation_id', 'event_id', 'log_id', 'response_time_ms',
            'status_code', 'tokens', 'total_tokens', 'prompt_tokens', 'completion_tokens',
            'response_length', 'text_length', 'audio_size_bytes', 'count', 'attempt',
            'cached_tokens', 'tool_calls', 'web_search_calls', 'image_count', 'characters', 'duration_seconds'];
        foreach ($numbers as $key) {
            if (isset($context[$key]) && is_numeric($context[$key])) {
                $safe[$key] = (float) $context[$key];
            }
        }
        foreach (['provider', 'model', 'api_type', 'status', 'voice', 'size'] as $key) {
            if (isset($context[$key]) && is_string($context[$key])
                && preg_match('/\A[a-z0-9_.-]{1,80}\z/i', $context[$key])) {
                $safe[$key] = $context[$key];
            }
        }

        return $safe;
    }

    public static function __callStatic(string $level, array $arguments): void
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = $trace[1] ?? [];
        $context = self::metadata($arguments[1] ?? []);
        $context['source'] = ($caller['class'] ?? 'app').'@'.($caller['function'] ?? 'unknown');
        $context['line'] = $trace[0]['line'] ?? null;

        Log::channel('ai')->log($level, 'AI operation', $context);
    }
}
