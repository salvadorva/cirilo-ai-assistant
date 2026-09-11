<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $api_provider
 * @property string $api_type
 * @property string|null $model
 * @property string|null $prompt
 * @property int $prompt_tokens
 * @property int $completion_tokens
 * @property int $total_tokens
 * @property float $estimated_cost
 * @property string $status
 * @property string|null $error_message
 * @property int|null $response_time_ms
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property array|null $metadata
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \App\Models\User|null $user
 */
class ApiUsageLog extends Model
{
    protected $fillable = [
        'user_id',
        'api_provider',
        'api_type',
        'model',
        'prompt',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'estimated_cost',
        'status',
        'error_message',
        'response_time_ms',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'estimated_cost' => 'decimal:6',
    ];

    /**
     * Relación con el usuario
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope para filtrar por tipo de API
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('api_type', $type);
    }

    /**
     * Scope para filtrar por proveedor
     */
    public function scopeOfProvider($query, string $provider)
    {
        return $query->where('api_provider', $provider);
    }

    /**
     * Scope para filtrar por usuario
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope para filtrar por rango de fechas
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Calcular costo estimado basado en tokens y modelo
     */
    public static function calculateCost(string $model, int $promptTokens, int $completionTokens): float
    {
        // Precios aproximados por 1K tokens (actualizar según precios reales)
        $pricing = [
            'gpt-4o' => ['prompt' => 0.005, 'completion' => 0.015],
            'gpt-4o-mini' => ['prompt' => 0.00015, 'completion' => 0.0006],
            'gpt-3.5-turbo' => ['prompt' => 0.0015, 'completion' => 0.002],
            'dall-e-3' => ['per_image' => 0.04], // retirado 2026-05-12, queda por logs históricos
            'gpt-image-1' => ['per_image' => 0.042], // 1024x1024 quality=medium (low: 0.011, high: 0.167)
            'tts-1' => ['per_1k_chars' => 0.015],
            'whisper-1' => ['per_minute' => 0.006],
        ];

        if (str_contains($model, 'gpt-image')) {
            return $pricing['gpt-image-1']['per_image'] ?? 0;
        }

        if (str_contains($model, 'dall-e')) {
            return $pricing['dall-e-3']['per_image'] ?? 0;
        }

        if (str_contains($model, 'tts')) {
            // Estimación: ~1 token = ~4 caracteres
            $chars = $promptTokens * 4;

            return ($chars / 1000) * ($pricing['tts-1']['per_1k_chars'] ?? 0);
        }

        $modelPricing = $pricing[$model] ?? ['prompt' => 0, 'completion' => 0];
        $promptCost = ($promptTokens / 1000) * $modelPricing['prompt'];
        $completionCost = ($completionTokens / 1000) * $modelPricing['completion'];

        return round($promptCost + $completionCost, 6);
    }
}
