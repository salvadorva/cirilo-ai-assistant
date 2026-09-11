<?php

namespace App\Traits;

use App\Models\ApiUsageLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

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
        $startTime = microtime(true);

        try {
            $log = ApiUsageLog::create([
                'user_id' => Auth::id(),
                'api_provider' => $options['provider'] ?? 'openai',
                'api_type' => $apiType,
                'model' => $model,
                'prompt' => $options['prompt'] ?? null,
                'prompt_tokens' => $options['prompt_tokens'] ?? 0,
                'completion_tokens' => $options['completion_tokens'] ?? 0,
                'total_tokens' => $options['total_tokens'] ?? 0,
                'estimated_cost' => $options['estimated_cost'] ??
                    ApiUsageLog::calculateCost(
                        $model,
                        $options['prompt_tokens'] ?? 0,
                        $options['completion_tokens'] ?? 0
                    ),
                'status' => $options['status'] ?? 'success',
                'error_message' => $options['error_message'] ?? null,
                'response_time_ms' => $options['response_time_ms'] ??
                    (int) ((microtime(true) - $startTime) * 1000),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'metadata' => $options['metadata'] ?? null,
            ]);

            Log::info('API usage logged', [
                'log_id' => $log->id,
                'user_id' => $log->user_id,
                'api_type' => $apiType,
                'model' => $model,
                'cost' => $log->estimated_cost,
            ]);

            return $log;
        } catch (\Exception $e) {
            Log::error('Error logging API usage: '.$e->getMessage());

            // Crear un log mínimo aunque falle
            return new ApiUsageLog([
                'api_type' => $apiType,
                'model' => $model,
                'status' => 'error',
                'error_message' => 'Failed to log: '.$e->getMessage(),
            ]);
        }
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
