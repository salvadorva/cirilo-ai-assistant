<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property int $total_xp
 * @property int $level
 * @property int $xp_to_next_level
 * @property int $typing_score
 * @property int $tutor_score
 * @property int $total_sessions
 * @property int $total_time_played
 * @property float $best_wpm
 * @property float $average_accuracy
 * @property int $current_streak
 * @property int $longest_streak
 * @property \Carbon\Carbon|string|null $last_activity_date
 * @property array|null $weekly_stats
 * @property array|null $monthly_stats
 * @property int $days_inactive
 * @property float $engagement_score
 * @property string $churn_risk_level
 * @property \Carbon\Carbon|null $last_activity_at
 * @property int $sessions_this_week
 * @property int $total_login_days
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read int $current_level_xp
 * @property-read int $current_xp
 * @property-read int $required_xp
 * @property-read int $xp_needed
 * @property-read \App\Models\User $user
 */
class UserGameProgress extends Model
{
    use HasFactory;

    protected $table = 'user_game_progress';

    protected $fillable = [
        'user_id',
        'total_xp',
        'level',
        'xp_to_next_level',
        'typing_score',
        'tutor_score',
        'total_sessions',
        'total_time_played',
        'best_wpm',
        'average_accuracy',
        'current_streak',
        'longest_streak',
        'last_activity_date',
        'weekly_stats',
        'monthly_stats',
        'days_inactive',
        'engagement_score',
        'churn_risk_level',
        'last_activity_at',
        'sessions_this_week',
        'total_login_days',
    ];

    protected $casts = [
        'weekly_stats' => 'array',
        'monthly_stats' => 'array',
        'last_activity_date' => 'date',
        'last_activity_at' => 'datetime',
        'best_wpm' => 'float',
        'average_accuracy' => 'float',
        'engagement_score' => 'float',
    ];

    /**
     * Relación con el usuario
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Calcular XP necesario para el siguiente nivel
     */
    public function calculateXPForNextLevel($level = null)
    {
        $targetLevel = max(1, (int) ($level ?? $this->level));

        // Parámetros de progresión (ajustables)
        $baseStep = 250;       // XP base por nivel
        $growthScale = 35;     // Factor de crecimiento
        $growthExponent = 1.5; // Curvatura de crecimiento

        static $xpCache = [0 => 0];
        static $computedMax = 0;

        if ($targetLevel <= $computedMax) {
            return $xpCache[$targetLevel];
        }

        for ($current = $computedMax + 1; $current <= $targetLevel; $current++) {
            $xpPerLevel = (int) round($baseStep + $growthScale * pow($current, $growthExponent));
            $xpCache[$current] = ($xpCache[$current - 1] ?? 0) + $xpPerLevel;
        }

        $computedMax = $targetLevel;

        return $xpCache[$targetLevel];
    }

    /**
     * Agregar XP y verificar si sube de nivel
     */
    public function addXP($xp)
    {
        $oldLevel = $this->level;
        $this->total_xp += $xp;
        $this->last_activity_date = now()->toDateString();
        $leveledUp = false;

        // Calcular cuánto XP necesita para el nivel actual
        $requiredXPForCurrentLevel = $this->calculateXPForNextLevel($this->level);

        // Si el XP total alcanza o supera el requerido, subir de nivel
        while ($this->total_xp >= $requiredXPForCurrentLevel) {
            $this->level++;
            $leveledUp = true;
            $requiredXPForCurrentLevel = $this->calculateXPForNextLevel($this->level);
        }

        // Actualizar xp_to_next_level con lo que necesita para el SIGUIENTE nivel
        $this->xp_to_next_level = $requiredXPForCurrentLevel;

        $this->save();

        // Crear notificación si subió de nivel
        if ($leveledUp && $this->user) {
            \App\Services\NotificationService::levelUp($this->user, $oldLevel, $this->level);
        }

        return $leveledUp;
    }

    /**
     * Obtener porcentaje de progreso al siguiente nivel
     */
    public function getLevelProgressPercentage()
    {
        $xpForCurrentLevel = $this->calculateXPForNextLevel($this->level - 1); // XP necesario para el nivel actual
        $xpForNextLevel = $this->calculateXPForNextLevel($this->level); // XP necesario para el siguiente nivel

        $currentLevelXP = $this->total_xp - $xpForCurrentLevel; // XP ganado en el nivel actual
        $xpNeededForNextLevel = $xpForNextLevel - $xpForCurrentLevel; // XP total que necesita para pasar de nivel

        if ($xpNeededForNextLevel == 0) {
            return 100;
        }

        return round(($currentLevelXP / $xpNeededForNextLevel) * 100);
    }

    /**
     * Actualizar última actividad y racha
     */
    public function updateActivity($activityType = 'general')
    {
        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();

        if ($this->last_activity_date == $today) {
            // Ya tuvo actividad hoy, no hacer nada
            return;
        }

        $oldStreak = $this->current_streak;

        if ($this->last_activity_date == $yesterday) {
            // Actividad consecutiva, aumentar racha
            $this->current_streak++;
        } else {
            // Se rompió la racha
            $this->current_streak = 1;
        }

        // Actualizar racha más larga si es necesario
        if ($this->current_streak > $this->longest_streak) {
            $this->longest_streak = $this->current_streak;
        }

        // Notificaciones de hitos de racha (7, 14, 30, 100 días)
        $milestones = [7, 14, 30, 50, 100, 365];
        if ($this->user && in_array($this->current_streak, $milestones) && $this->current_streak > $oldStreak) {
            \App\Services\NotificationService::streakMilestone($this->user, $this->current_streak);
        }

        $this->last_activity_date = $today;
        $this->save();

        // Registrar en daily_streaks
        DailyStreak::updateOrCreate([
            'user_id' => $this->user_id,
            'activity_date' => $today,
            'activity_type' => $activityType,
        ], [
            'sessions_count' => 1,
            'xp_earned' => 0, // Se actualizará después
        ]);
    }

    /**
     * Accessor para current_xp (XP ganado en el nivel actual)
     */
    public function getCurrentXpAttribute()
    {
        $xpForCurrentLevel = $this->level > 1 ? $this->calculateXPForNextLevel($this->level - 1) : 0;

        return max(0, $this->total_xp - $xpForCurrentLevel);
    }

    /**
     * Accessor para required_xp (XP total necesario para el siguiente nivel)
     */
    public function getRequiredXpAttribute()
    {
        $xpForCurrentLevel = $this->level > 1 ? $this->calculateXPForNextLevel($this->level - 1) : 0;
        $xpForNextLevel = $this->calculateXPForNextLevel($this->level);

        return $xpForNextLevel - $xpForCurrentLevel;
    }

    /**
     * Accessor para xp_needed (compatibilidad - XP restante para subir de nivel)
     */
    public function getXpNeededAttribute()
    {
        return $this->required_xp - $this->current_xp;
    }

    /**
     * Relación con las actividades del usuario
     */
    public function activityTrackers(): HasMany
    {
        return $this->hasMany(UserActivityTracker::class, 'user_id', 'user_id');
    }

    /**
     * Actualizar días de inactividad
     */
    public function updateDaysInactive()
    {
        if ($this->last_activity_at) {
            $this->days_inactive = (int) abs(now()->diffInDays($this->last_activity_at, false));
        } else {
            // Si nunca ha tenido actividad, calcular desde la creación del registro
            $this->days_inactive = $this->created_at ? (int) abs($this->created_at->diffInDays(now(), false)) : 0;
        }
        $this->save();
    }

    /**
     * Calcular y actualizar score de engagement
     */
    public function calculateEngagementScore()
    {
        $score = 0;

        // Componente de actividad reciente (40%)
        if ($this->days_inactive <= 1) {
            $score += 40;
        } elseif ($this->days_inactive <= 3) {
            $score += 30;
        } elseif ($this->days_inactive <= 7) {
            $score += 20;
        } elseif ($this->days_inactive <= 14) {
            $score += 10;
        }

        // Componente de frecuencia de sesiones (30%)
        if ($this->sessions_this_week >= 7) {
            $score += 30;
        } elseif ($this->sessions_this_week >= 5) {
            $score += 25;
        } elseif ($this->sessions_this_week >= 3) {
            $score += 20;
        } elseif ($this->sessions_this_week >= 1) {
            $score += 15;
        }

        // Componente de racha (20%)
        if ($this->current_streak >= 7) {
            $score += 20;
        } elseif ($this->current_streak >= 3) {
            $score += 15;
        } elseif ($this->current_streak >= 1) {
            $score += 10;
        }

        // Componente de progreso (10%)
        if ($this->level >= 10) {
            $score += 10;
        } elseif ($this->level >= 5) {
            $score += 8;
        } elseif ($this->level >= 2) {
            $score += 5;
        }

        $this->engagement_score = $score;
        $this->save();

        return $score;
    }

    /**
     * Actualizar nivel de riesgo de churn
     */
    public function updateChurnRiskLevel()
    {
        $this->updateDaysInactive();
        $engagementScore = $this->calculateEngagementScore();

        // Priorizar días de inactividad sobre engagement score
        if ($this->days_inactive >= 14) {
            $this->churn_risk_level = 'critical';
        } elseif ($this->days_inactive >= 7) {
            $this->churn_risk_level = 'high';
        } elseif ($this->days_inactive >= 3) {
            $this->churn_risk_level = 'medium';
        } elseif ($engagementScore <= 20) {
            $this->churn_risk_level = 'critical';
        } elseif ($engagementScore <= 40) {
            $this->churn_risk_level = 'high';
        } elseif ($engagementScore <= 60) {
            $this->churn_risk_level = 'medium';
        } else {
            $this->churn_risk_level = 'low';
        }

        $this->save();
    }

    /**
     * Resetear contador de sesiones semanales
     */
    public function resetWeeklySessions()
    {
        $this->sessions_this_week = 0;
        $this->save();
    }

    /**
     * Incrementar sesiones de la semana
     */
    public function incrementWeeklySessions()
    {
        $this->sessions_this_week++;
        $this->total_sessions++;
        $this->last_activity_at = now();
        $this->save();
    }

    /**
     * Scope para usuarios en riesgo
     */
    public function scopeAtRisk($query, $riskLevel = 'high')
    {
        return $query->whereIn('churn_risk_level',
            $riskLevel === 'high' ? ['high', 'critical'] : [$riskLevel]
        );
    }

    /**
     * Scope para usuarios inactivos por X días
     */
    public function scopeInactiveForDays($query, $days)
    {
        return $query->where('days_inactive', '>=', $days);
    }
}
