<?php

namespace App\Services;

use App\Models\UserGameProgress;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class StreakService
{
    /**
     * Actualizar la racha del usuario basada en su actividad
     *
     * @return array ['current_streak' => int, 'longest_streak' => int, 'streak_updated' => bool]
     */
    public function updateStreak(int $userId): array
    {
        $gameProgress = UserGameProgress::where('user_id', $userId)->first();

        if (! $gameProgress) {
            return [
                'current_streak' => 0,
                'longest_streak' => 0,
                'streak_updated' => false,
            ];
        }

        $today = Carbon::today();
        $lastActivityDate = $gameProgress->last_activity_date
            ? Carbon::parse($gameProgress->last_activity_date)
            : null;

        // Si es la primera actividad o no hay fecha registrada
        if (! $lastActivityDate) {
            $gameProgress->current_streak = 1;
            $gameProgress->longest_streak = max(1, $gameProgress->longest_streak);
            $gameProgress->last_activity_date = $today;
            $gameProgress->save();

            return [
                'current_streak' => 1,
                'longest_streak' => $gameProgress->longest_streak,
                'streak_updated' => true,
                'streak_status' => 'started',
            ];
        }

        // Calcular diferencia en días
        $daysDifference = (int) $lastActivityDate->diffInDays($today);

        // Caso 1: Actividad el mismo día (no se actualiza la racha)
        if ($daysDifference === 0) {
            return [
                'current_streak' => $gameProgress->current_streak,
                'longest_streak' => $gameProgress->longest_streak,
                'streak_updated' => false,
                'streak_status' => 'maintained',
            ];
        }

        // Caso 2: Actividad consecutiva (diferencia de 1 día)
        if ($daysDifference === 1) {
            $gameProgress->current_streak += 1;
            $gameProgress->longest_streak = max($gameProgress->current_streak, $gameProgress->longest_streak);
            $gameProgress->last_activity_date = $today;
            $gameProgress->save();

            return [
                'current_streak' => $gameProgress->current_streak,
                'longest_streak' => $gameProgress->longest_streak,
                'streak_updated' => true,
                'streak_status' => 'increased',
            ];
        }

        // Caso 3: Racha rota (diferencia mayor a 1 día)
        if ($daysDifference > 1) {
            $gameProgress->current_streak = 1; // Reiniciar racha
            $gameProgress->last_activity_date = $today;
            $gameProgress->save();

            return [
                'current_streak' => 1,
                'longest_streak' => $gameProgress->longest_streak,
                'streak_updated' => true,
                'streak_status' => 'broken',
            ];
        }

        return [
            'current_streak' => $gameProgress->current_streak,
            'longest_streak' => $gameProgress->longest_streak,
            'streak_updated' => false,
            'streak_status' => 'unknown',
        ];
    }

    /**
     * Verificar si el usuario está en riesgo de perder su racha
     *
     * @return array ['at_risk' => bool, 'hours_remaining' => int]
     */
    public function checkStreakRisk(int $userId): array
    {
        $gameProgress = UserGameProgress::where('user_id', $userId)->first();

        if (! $gameProgress || ! $gameProgress->last_activity_date) {
            return ['at_risk' => false, 'hours_remaining' => 0];
        }

        $lastActivity = Carbon::parse($gameProgress->last_activity_date);
        $now = Carbon::now();
        $tomorrow = Carbon::today()->addDay();

        // Si la última actividad fue hoy, no hay riesgo
        if ($lastActivity->isToday()) {
            return ['at_risk' => false, 'hours_remaining' => 0];
        }

        // Si la última actividad fue ayer, el usuario está en riesgo
        if ($lastActivity->isYesterday()) {
            $hoursRemaining = $now->diffInHours($tomorrow, false);

            return [
                'at_risk' => true,
                'hours_remaining' => max(0, $hoursRemaining),
                'current_streak' => $gameProgress->current_streak,
            ];
        }

        // Si pasaron más de 2 días, la racha ya se perdió
        return [
            'at_risk' => false,
            'hours_remaining' => 0,
            'streak_lost' => true,
        ];
    }

    /**
     * Recalcular racha desde cero basado en el historial de sesiones
     */
    public function recalculateStreakFromHistory(int $userId): array
    {
        // Obtener todas las fechas únicas con actividad
        $activityDates = DB::table('typing_sessions')
            ->select(DB::raw('DATE(session_date) as activity_date'))
            ->where('user_id', $userId)
            ->where('completed', true)
            ->groupBy('activity_date')
            ->orderBy('activity_date', 'desc')
            ->pluck('activity_date')
            ->map(fn ($date) => Carbon::parse($date))
            ->toArray();

        if (empty($activityDates)) {
            return [
                'current_streak' => 0,
                'longest_streak' => 0,
                'total_active_days' => 0,
            ];
        }

        $currentStreak = 1;
        $longestStreak = 1;
        $today = Carbon::today();

        // Si la última actividad no fue hoy ni ayer, la racha actual es 0
        $lastActivity = $activityDates[0];
        if (! $lastActivity->isToday() && ! $lastActivity->isYesterday()) {
            $currentStreak = 0;
        }

        // Calcular racha actual (desde el día más reciente hacia atrás)
        for ($i = 0; $i < count($activityDates) - 1; $i++) {
            $current = $activityDates[$i];
            $next = $activityDates[$i + 1];

            $diff = $current->diffInDays($next);

            if ($diff === 1) {
                // Días consecutivos
                if ($currentStreak > 0) {
                    $currentStreak++;
                }
            } else {
                // Racha rota
                break;
            }
        }

        // Calcular la racha más larga en todo el historial
        $tempStreak = 1;
        for ($i = 0; $i < count($activityDates) - 1; $i++) {
            $current = $activityDates[$i];
            $next = $activityDates[$i + 1];

            $diff = $current->diffInDays($next);

            if ($diff === 1) {
                $tempStreak++;
                $longestStreak = max($longestStreak, $tempStreak);
            } else {
                $tempStreak = 1;
            }
        }

        // Actualizar en la base de datos
        $gameProgress = UserGameProgress::where('user_id', $userId)->first();
        if ($gameProgress) {
            $gameProgress->current_streak = $currentStreak;
            $gameProgress->longest_streak = $longestStreak;
            $gameProgress->last_activity_date = $lastActivity;
            $gameProgress->save();
        }

        return [
            'current_streak' => $currentStreak,
            'longest_streak' => $longestStreak,
            'total_active_days' => count($activityDates),
            'last_activity' => $lastActivity->format('Y-m-d'),
        ];
    }
}
