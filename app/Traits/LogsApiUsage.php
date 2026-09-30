<?php

namespace App\Traits;

use App\Models\ApiUsageLog;
use Illuminate\Support\Facades\Auth;
use App\Support\AiLog as Log;

/**
 * @deprecated Compatibility only. These methods do NOT write usage records.
 * New integrations must use AiTransport or Laravel Http, observed by AiTelemetry.
 */
trait LogsApiUsage
{
    /**
     * Registrar uso de API
     *
     * @param  string  $apiType  Tipo de API (text_generation, image_generation, tts, stt, image_analysis)
     * @param  string  $model  Modelo usado (gpt-4o, dall-e-3, etc.)
     * @param  array  $options  Opciones adicionales
     */
    protected function logApiUsage(
        string $apiType,
        string $model,
        array $options = []
    ): ApiUsageLog {
        // Compatibility shim: AiTelemetry now records each actual HTTP attempt.
        // Do not persist here: callers also use this after normalization/retries,
        // which previously caused omissions and would now double-count usage.
        return new ApiUsageLog(['api_type' => $apiType, 'model' => $model]);
    }

    /**
     * Registrar uso de generación de texto
     */
    protected function logTextGeneration(
        string $model,
        string $prompt,
        array $response,
        string $provider = 'openai',
        int $responseTimeMs = 0
    ): ApiUsageLog {
        $usage = $response['usage'] ?? [];

        return $this->logApiUsage('text_generation', $model, [
            'provider' => $provider,
            'prompt' => substr($prompt, 0, 500), // Limitar tamaño del prompt
            'prompt_tokens' => $usage['prompt_tokens'] ?? 0,
            'completion_tokens' => $usage['completion_tokens'] ?? 0,
            'total_tokens' => $usage['total_tokens'] ?? 0,
            'response_time_ms' => $responseTimeMs,
            'metadata' => [
                'response_length' => strlen($response['choices'][0]['message']['content'] ?? ''),
            ],
        ]);
    }

    /**
     * Registrar uso de generación de imágenes
     */
    protected function logImageGeneration(
        string $model,
        string $prompt,
        bool $success = true,
        ?string $errorMessage = null,
        int $responseTimeMs = 0
    ): ApiUsageLog {
        return $this->logApiUsage('image_generation', $model, [
            'provider' => 'openai',
            'prompt' => substr($prompt, 0, 500),
            'prompt_tokens' => strlen($prompt) / 4, // Estimación
            'status' => $success ? 'success' : 'error',
            'error_message' => $errorMessage,
            'response_time_ms' => $responseTimeMs,
            'metadata' => [
                'size' => '1024x1024',
            ],
        ]);
    }

    /**
     * Registrar uso de text-to-speech
     */
    protected function logTextToSpeech(
        string $text,
        bool $success = true,
        ?string $errorMessage = null,
        int $responseTimeMs = 0
    ): ApiUsageLog {
        $textLength = strlen($text);

        return $this->logApiUsage('tts', 'tts-1', [
            'provider' => 'openai',
            'prompt' => substr($text, 0, 500),
            'prompt_tokens' => (int) ($textLength / 4), // Estimación
            'status' => $success ? 'success' : 'error',
            'error_message' => $errorMessage,
            'response_time_ms' => $responseTimeMs,
            'metadata' => [
                'text_length' => $textLength,
                'voice' => 'echo',
            ],
        ]);
    }

    /**
     * Registrar uso de speech-to-text
     */
    protected function logSpeechToText(
        int $audioSizeBytes,
        bool $success = true,
        ?string $errorMessage = null,
        int $responseTimeMs = 0
    ): ApiUsageLog {
        return $this->logApiUsage('stt', 'whisper-1', [
            'provider' => 'openai',
            'status' => $success ? 'success' : 'error',
            'error_message' => $errorMessage,
            'response_time_ms' => $responseTimeMs,
            'metadata' => [
                'audio_size_bytes' => $audioSizeBytes,
            ],
        ]);
    }

    /**
     * Registrar uso de análisis de imágenes
     */
    protected function logImageAnalysis(
        string $prompt,
        array $response,
        int $responseTimeMs = 0
    ): ApiUsageLog {
        $usage = $response['usage'] ?? [];

        return $this->logApiUsage('image_analysis', 'gpt-4o', [
            'provider' => 'openai',
            'prompt' => substr($prompt, 0, 500),
            'prompt_tokens' => $usage['prompt_tokens'] ?? 0,
            'completion_tokens' => $usage['completion_tokens'] ?? 0,
            'total_tokens' => $usage['total_tokens'] ?? 0,
            'response_time_ms' => $responseTimeMs,
            'metadata' => [
                'has_image' => true,
            ],
        ]);
    }
}
