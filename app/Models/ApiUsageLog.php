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
    protected static function booted(): void
    {
        static::saving(function (self $log) {
            $log->prompt = null;
            $log->ip_address = null;
            $log->user_agent = null;
            $log->metadata = \App\Support\AiLog::metadata($log->metadata ?? []);
            if ($log->error_message !== null) {
                $log->error_message = 'provider_request_failed';
            }
        });
    }

    protected $fillable = [
        'interaction_id', 'stage', 'cost_status', 'pricing_date',
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
        'estimated_cost' => 'decimal:8',
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
    public static function calculateCost(string $model, int $promptTokens, int $completionTokens): ?float
    {
        return app(\App\Services\AiPricing::class)->estimate('openai', $model,
            ['input_tokens' => $promptTokens, 'output_tokens' => $completionTokens])['estimated_cost'];
    }
}
