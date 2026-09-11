<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyStreak extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'activity_date',
        'activity_type',
        'sessions_count',
        'minutes_spent',
        'xp_earned',
        'details',
    ];

    protected $casts = [
        'activity_date' => 'date',
        'details' => 'array',
    ];

    /**
     * Relación con el usuario
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope para un tipo de actividad específico
     */
    public function scopeByActivityType($query, $type)
    {
        return $query->where('activity_type', $type);
    }

    /**
     * Scope para una fecha específica
     */
    public function scopeByDate($query, $date)
    {
        return $query->where('activity_date', $date);
    }

    /**
     * Scope para un rango de fechas
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('activity_date', [$startDate, $endDate]);
    }

    /**
     * Incrementar contador de sesiones
     */
    public function incrementSessions($minutes = 0, $xp = 0)
    {
        $this->increment('sessions_count');
        $this->increment('minutes_spent', $minutes);
        $this->increment('xp_earned', $xp);
    }

    /**
     * Obtener estadísticas de racha del usuario
     */
    public static function getUserStreakStats($userId, $activityType = null)
    {
        $query = self::where('user_id', $userId);

        if ($activityType) {
            $query->where('activity_type', $activityType);
        }

        $activities = $query->orderBy('activity_date', 'desc')->get();

        if ($activities->isEmpty()) {
            return [
                'current_streak' => 0,
                'longest_streak' => 0,
                'total_days' => 0,
                'last_activity' => null,
            ];
        }

        $currentStreak = 0;
        $longestStreak = 0;
        $tempStreak = 0;
        $lastActivity = $activities->first()->activity_date;

        // Verificar si la actividad más reciente fue ayer o hoy
        $yesterday = now()->subDay()->toDateString();
        $today = now()->toDateString();

        if ($lastActivity == $today || $lastActivity == $yesterday) {
            $currentStreak = 1;
            $tempStreak = 1;

            // Calcular racha actual
            for ($i = 1; $i < $activities->count(); $i++) {
                $currentDate = $activities[$i - 1]->activity_date;
                $previousDate = $activities[$i]->activity_date;

                if ($currentDate->diffInDays($previousDate) == 1) {
                    $currentStreak++;
                    $tempStreak++;
                } else {
                    break;
                }
            }
        }

        // Calcular racha más larga
        $tempStreak = 1;
        for ($i = 1; $i < $activities->count(); $i++) {
            $currentDate = $activities[$i - 1]->activity_date;
            $previousDate = $activities[$i]->activity_date;

            if ($currentDate->diffInDays($previousDate) == 1) {
                $tempStreak++;
            } else {
                $longestStreak = max($longestStreak, $tempStreak);
                $tempStreak = 1;
            }
        }
        $longestStreak = max($longestStreak, $tempStreak);

        return [
            'current_streak' => $currentStreak,
            'longest_streak' => $longestStreak,
            'total_days' => $activities->count(),
            'last_activity' => $lastActivity,
        ];
    }
}
