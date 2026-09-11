<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $activity_type
 * @property \Carbon\Carbon $session_start
 * @property \Carbon\Carbon|null $session_end
 * @property int $session_duration_seconds
 * @property int $xp_earned
 * @property string $activity_quality
 * @property array|null $session_data
 * @property bool $completed
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \App\Models\User $user
 */
class UserActivityTracker extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'activity_type',
        'session_start',
        'session_end',
        'session_duration_seconds',
        'xp_earned',
        'activity_quality',
        'session_data',
        'completed',
    ];

    protected $casts = [
        'session_start' => 'datetime',
        'session_end' => 'datetime',
        'session_data' => 'array',
        'completed' => 'boolean',
    ];

    // Constantes para tipos de actividad
    const TYPE_TYPING = 'typing';

    const TYPE_ENGLISH_GAMES = 'english_games';

    const TYPE_TUTOR = 'tutor';

    // Constantes para calidad de sesión
    const QUALITY_MICRO = 'micro';        // < 5 minutos

    const QUALITY_PRODUCTIVE = 'productive'; // 5-15 minutos

    const QUALITY_EXCELLENT = 'excellent';   // > 15 minutos

    /**
     * Relación con el usuario
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Crear nueva sesión de actividad
     */
    public static function startSession(int $userId, string $activityType, ?array $sessionData = null): self
    {
        return self::create([
            'user_id' => $userId,
            'activity_type' => $activityType,
            'session_start' => now(),
            'session_data' => $sessionData,
            'completed' => false,
        ]);
    }

    /**
     * Terminar sesión y calcular duración
     */
    public function endSession(?Carbon $endTime = null): void
    {
        $endTime = $endTime ?? now();
        $this->session_end = $endTime;
        $this->session_duration_seconds = (int) $this->session_start->diffInSeconds($endTime);
        $this->activity_quality = $this->calculateActivityQuality();
        $this->completed = true;
        $this->save();
    }

    /**
     * Calcular y asignar la calidad de la sesión basada en duración
     */
    public function calculateActivityQuality(): string
    {
        if ($this->session_duration_seconds < 300) { // < 5 minutos
            return self::QUALITY_MICRO;
        } elseif ($this->session_duration_seconds < 900) { // < 15 minutos
            return self::QUALITY_PRODUCTIVE;
        } else {
            return self::QUALITY_EXCELLENT;
        }
    }

    /**
     * Scope para obtener sesiones completadas
     */
    public function scopeCompleted($query)
    {
        return $query->where('completed', true);
    }

    /**
     * Scope para obtener sesiones recientes
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('session_start', '>=', now()->subDays($days));
    }

    /**
     * Detectar días de inactividad para un usuario
     */
    public static function getDaysInactive(int $userId): int
    {
        $lastActivity = self::where('user_id', $userId)
            ->completed()
            ->latest('session_start')
            ->first();

        if (! $lastActivity) {
            return 999; // Usuario nunca activo
        }

        return (int) $lastActivity->session_start->diffInDays(now());
    }

    /**
     * Verificar si un usuario está en riesgo de abandono
     */
    public static function isUserAtRisk(int $userId): array
    {
        $daysInactive = self::getDaysInactive($userId);

        $riskFactors = [];
        $riskLevel = 'low';

        // Factor 1: Días de inactividad
        if ($daysInactive >= 7) {
            $riskFactors[] = 'inactive_7_days';
            $riskLevel = 'high';
        } elseif ($daysInactive >= 3) {
            $riskFactors[] = 'inactive_3_days';
            $riskLevel = 'medium';
        }

        return [
            'is_at_risk' => ! empty($riskFactors),
            'risk_level' => $riskLevel,
            'risk_factors' => $riskFactors,
            'days_inactive' => $daysInactive,
        ];
    }
}
