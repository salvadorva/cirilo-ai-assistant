<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserActivityTracker;
use App\Models\UserGameProgress;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class InactivityDetector
{
    /**
     * Detectar y actualizar usuarios inactivos
     */
    public function detectInactiveUsers(): Collection
    {
        Log::info('Iniciando detección de usuarios inactivos');

        $users = User::with('gameProgress')->get();
        $inactiveUsers = collect();

        foreach ($users as $user) {
            // Saltar usuarios sin gameProgress (nunca han usado el sistema)
            if (! $user->gameProgress) {
                continue;
            }

            $progress = $user->gameProgress;
            $progress->updateChurnRiskLevel();

            if (in_array($progress->churn_risk_level, ['medium', 'high', 'critical'])) {
                $inactiveUsers->push([
                    'user' => $user,
                    'progress' => $progress,
                    'days_inactive' => $progress->days_inactive,
                    'risk_level' => $progress->churn_risk_level,
                ]);
            }
        }

        Log::info("Detectados {$inactiveUsers->count()} usuarios inactivos");

        return $inactiveUsers;
    }

    /**
     * Actualizar scores de engagement para todos los usuarios
     */
    public function updateAllEngagementScores(): int
    {
        Log::info('Actualizando scores de engagement');

        $updated = 0;
        UserGameProgress::chunk(100, function ($progressRecords) use (&$updated) {
            foreach ($progressRecords as $progress) {
                $progress->calculateEngagementScore();
                $progress->updateChurnRiskLevel();
                $updated++;
            }
        });

        Log::info("Actualizados {$updated} scores de engagement");

        return $updated;
    }

    /**
     * Resetear contadores semanales
     */
    public function resetWeeklyCounters(): int
    {
        Log::info('Reseteando contadores semanales');

        $affected = UserGameProgress::where('sessions_this_week', '>', 0)
            ->update(['sessions_this_week' => 0]);

        Log::info("Reseteados contadores de {$affected} usuarios");

        return $affected;
    }

    /**
     * Obtener usuarios por nivel de riesgo
     */
    public function getUsersByRiskLevel(string $riskLevel): Collection
    {
        return User::whereHas('gameProgress', function ($query) use ($riskLevel) {
            $query->where('churn_risk_level', $riskLevel);
        })->with('gameProgress')->get();
    }

    /**
     * Obtener estadísticas de engagement
     */
    public function getEngagementStats(): array
    {
        $stats = UserGameProgress::selectRaw('
            churn_risk_level,
            COUNT(*) as count,
            AVG(engagement_score) as avg_engagement,
            AVG(days_inactive) as avg_days_inactive,
            AVG(sessions_this_week) as avg_weekly_sessions
        ')->groupBy('churn_risk_level')->get()->keyBy('churn_risk_level');

        $totalUsers = UserGameProgress::count();

        return [
            'total_users' => $totalUsers,
            'risk_distribution' => [
                'low' => $stats->get('low', (object) ['count' => 0, 'avg_engagement' => 0]),
                'medium' => $stats->get('medium', (object) ['count' => 0, 'avg_engagement' => 0]),
                'high' => $stats->get('high', (object) ['count' => 0, 'avg_engagement' => 0]),
                'critical' => $stats->get('critical', (object) ['count' => 0, 'avg_engagement' => 0]),
            ],
            'averages' => [
                'engagement_score' => UserGameProgress::avg('engagement_score'),
                'days_inactive' => UserGameProgress::avg('days_inactive'),
                'weekly_sessions' => UserGameProgress::avg('sessions_this_week'),
            ],
        ];
    }

    /**
     * Marcar usuario como activo (cuando inicia sesión)
     */
    public function markUserActive(User $user, string $activityType = 'general'): void
    {
        $progress = $user->gameProgress;

        if (! $progress) {
            /** @var \App\Models\UserGameProgress $progress */
            $progress = $user->gameProgress()->create([
                'user_id' => $user->id,
            ]);
        }

        // Actualizar activity tracker
        UserActivityTracker::create([
            'user_id' => $user->id,
            'activity_type' => $activityType,
            'session_start' => now(),
            'session_end' => now(),
            'duration_seconds' => 0,
            'xp_earned' => 0,
            'session_quality' => 'started',
        ]);

        // Actualizar progress
        $progress->incrementWeeklySessions();
        $progress->updateActivity($activityType);
        $progress->updateChurnRiskLevel();

        Log::info("Usuario {$user->id} marcado como activo - Tipo: {$activityType}");
    }

    /**
     * Procesar fin de sesión
     */
    public function endUserSession(User $user, int $durationSeconds, int $xpEarned = 0): void
    {
        // Buscar la sesión activa más reciente
        $tracker = UserActivityTracker::where('user_id', $user->id)
            ->whereNull('session_end')
            ->latest()
            ->first();

        if ($tracker) {
            $tracker->update([
                'session_end' => now(),
                'duration_seconds' => $durationSeconds,
                'xp_earned' => $xpEarned,
                'session_quality' => $this->calculateSessionQuality($durationSeconds, $xpEarned),
            ]);
        }

        // Actualizar progress
        $progress = $user->gameProgress;
        if ($progress) {
            $progress->total_time_played += $durationSeconds;
            $progress->updateChurnRiskLevel();
            $progress->save();
        }

        Log::info("Sesión finalizada para usuario {$user->id} - Duración: {$durationSeconds}s, XP: {$xpEarned}");
    }

    /**
     * Calcular calidad de sesión basada en duración y XP
     */
    private function calculateSessionQuality(int $durationSeconds, int $xpEarned): string
    {
        if ($durationSeconds < 60) {
            return 'poor';
        } elseif ($durationSeconds < 300 || $xpEarned < 10) {
            return 'fair';
        } elseif ($durationSeconds < 900 || $xpEarned < 50) {
            return 'good';
        } else {
            return 'excellent';
        }
    }
}
