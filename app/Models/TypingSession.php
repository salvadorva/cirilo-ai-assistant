<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $mode
 * @property string $level
 * @property string|null $theme
 * @property string $text_used
 * @property int $wpm
 * @property float $accuracy
 * @property int $errors
 * @property int $time_seconds
 * @property int $total_keystrokes
 * @property int $correct_keystrokes
 * @property int $xp_earned
 * @property array|null $error_analysis
 * @property bool $completed
 * @property \Carbon\Carbon $session_date
 * @property string|null $game_mode
 * @property int|null $mode_score
 * @property array|null $mode_data
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \App\Models\User $user
 */
class TypingSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'mode',
        'level',
        'theme',
        'text_used',
        'wpm',
        'accuracy',
        'errors',
        'time_seconds',
        'total_keystrokes',
        'correct_keystrokes',
        'xp_earned',
        'error_analysis',
        'completed',
        'session_date',
        // Sprint 3: Nuevos campos para modos de juego
        'game_mode',
        'mode_score',
        'mode_data',
    ];

    protected $casts = [
        'error_analysis' => 'array',
        'mode_data' => 'array',
        'accuracy' => 'decimal:2',
        'completed' => 'boolean',
        'session_date' => 'datetime',
    ];

    /**
     * Relación con el usuario
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope para sesiones de un modo específico
     */
    public function scopeByMode($query, $mode)
    {
        return $query->where('mode', $mode);
    }

    /**
     * Scope para sesiones de un nivel específico
     */
    public function scopeByLevel($query, $level)
    {
        return $query->where('level', $level);
    }

    /**
     * Scope para sesiones completadas
     */
    public function scopeCompleted($query)
    {
        return $query->where('completed', true);
    }

    /**
     * Scope para obtener sesiones recientes
     */
    public function scopeRecent($query, $days = 7)
    {
        return $query->where('session_date', '>=', now()->subDays($days));
    }

    /**
     * Obtener la duración formateada de la sesión
     */
    public function getFormattedDurationAttribute()
    {
        $minutes = floor($this->time_seconds / 60);
        $seconds = $this->time_seconds % 60;

        return sprintf('%d:%02d', $minutes, $seconds);
    }

    /**
     * Obtener el rendimiento general (combinando WPM y precisión)
     */
    public function getPerformanceScoreAttribute()
    {
        // Fórmula: (WPM * Accuracy) / 100
        return round(($this->wpm * $this->accuracy) / 100, 1);
    }

    /**
     * Determinar si la sesión fue excelente
     */
    public function isExcellentPerformance()
    {
        return $this->accuracy >= 95 && $this->wpm >= 40;
    }

    /**
     * Determinar si la sesión fue buena
     */
    public function isGoodPerformance()
    {
        return $this->accuracy >= 85 && $this->wpm >= 25;
    }

    /**
     * Obtener estadísticas de sesiones de un usuario
     */
    public static function getUserStats($userId, $days = 30)
    {
        $sessions = static::where('user_id', $userId)
            ->completed()
            ->where('session_date', '>=', now()->subDays($days))
            ->get();

        if ($sessions->isEmpty()) {
            return [
                'total_sessions' => 0,
                'avg_wpm' => 0,
                'best_wpm' => 0,
                'avg_accuracy' => 0,
                'best_accuracy' => 0,
                'total_time_minutes' => 0,
                'total_xp_earned' => 0,
                'improvement_trend' => 0,
            ];
        }

        return [
            'total_sessions' => $sessions->count(),
            'avg_wpm' => round($sessions->avg('wpm'), 1),
            'best_wpm' => $sessions->max('wpm'),
            'avg_accuracy' => round($sessions->avg('accuracy'), 1),
            'best_accuracy' => $sessions->max('accuracy'),
            'total_time_minutes' => round($sessions->sum('time_seconds') / 60),
            'total_xp_earned' => $sessions->sum('xp_earned'),
            'improvement_trend' => static::calculateImprovementTrend($sessions),
        ];
    }

    /**
     * Calcular tendencia de mejora
     */
    protected static function calculateImprovementTrend($sessions)
    {
        if ($sessions->count() < 2) {
            return 0;
        }

        $sortedSessions = $sessions->sortBy('session_date');
        $firstHalf = $sortedSessions->take($sessions->count() / 2);
        $secondHalf = $sortedSessions->skip($sessions->count() / 2);

        $firstHalfAvg = $firstHalf->avg('performance_score');
        $secondHalfAvg = $secondHalf->avg('performance_score');

        return round(($secondHalfAvg - $firstHalfAvg), 1);
    }

    // ====== SPRINT 3: MÉTODOS PARA MODOS DE JUEGO ======

    /**
     * Scope para sesiones de un modo de juego específico
     */
    public function scopeByGameMode($query, $gameMode)
    {
        return $query->where('game_mode', $gameMode);
    }

    /**
     * Verificar si es una sesión de modo de juego
     */
    public function isGameMode()
    {
        return ! is_null($this->game_mode);
    }

    /**
     * Obtener el mejor score por modo de juego para un usuario
     */
    public static function getBestScoreByMode($userId, $gameMode)
    {
        return static::where('user_id', $userId)
            ->where('game_mode', $gameMode)
            ->whereNotNull('mode_score')
            ->max('mode_score') ?? 0;
    }

    /**
     * Obtener estadísticas por modo de juego
     */
    public static function getGameModeStats($userId, $gameMode, $days = 30)
    {
        $sessions = static::where('user_id', $userId)
            ->where('game_mode', $gameMode)
            ->where('session_date', '>=', now()->subDays($days))
            ->get();

        if ($sessions->isEmpty()) {
            return [
                'total_sessions' => 0,
                'best_score' => 0,
                'avg_score' => 0,
                'total_time_minutes' => 0,
                'avg_wpm' => 0,
                'avg_accuracy' => 0,
            ];
        }

        return [
            'total_sessions' => $sessions->count(),
            'best_score' => $sessions->max('mode_score') ?? 0,
            'avg_score' => round($sessions->avg('mode_score') ?? 0, 1),
            'total_time_minutes' => round($sessions->sum('time_seconds') / 60),
            'avg_wpm' => round($sessions->avg('wpm'), 1),
            'avg_accuracy' => round($sessions->avg('accuracy'), 1),
        ];
    }
}
