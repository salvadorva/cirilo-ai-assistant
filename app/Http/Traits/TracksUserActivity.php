<?php

namespace App\Http\Traits;

use App\Models\UserActivityTracker;
use Illuminate\Support\Facades\Log;

trait TracksUserActivity
{
    /**
     * Registrar actividad del usuario
     *
     * @param  int  $userId  ID del usuario
     * @param  string  $activityType  Tipo de actividad (typing, english_games, tutor)
     * @param  int  $durationSeconds  Duración de la sesión en segundos
     * @param  int  $xpEarned  XP ganado en la sesión
     * @param  string  $quality  Calidad de la sesión (poor, fair, good, excellent)
     */
    protected function trackActivity(
        int $userId,
        string $activityType,
        int $durationSeconds,
        int $xpEarned = 0,
        string $quality = 'productive'
    ): ?UserActivityTracker {
        try {
            $tracker = UserActivityTracker::create([
                'user_id' => $userId,
                'activity_type' => $activityType,
                'session_start' => now()->subSeconds($durationSeconds),
                'session_end' => now(),
                'session_duration_seconds' => $durationSeconds,
                'xp_earned' => $xpEarned,
                // Normalizar calidad a los valores permitidos por el enum: micro | productive | excellent
                'activity_quality' => $this->normalizeQuality($quality, $durationSeconds, $xpEarned),
                'completed' => true,
            ]);

            // Actualizar el progress del usuario
            $user = \App\Models\User::find($userId);
            if ($user && $user->gameProgress) {
                $user->gameProgress->incrementWeeklySessions();
                $user->gameProgress->updateChurnRiskLevel();
            }

            Log::info("Actividad registrada: Usuario {$userId}, Tipo: {$activityType}, Duración: {$durationSeconds}s, XP: {$xpEarned}");

            return $tracker;

        } catch (\Exception $e) {
            Log::error('Error registrando actividad: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Calcular calidad de sesión automáticamente
     *
     * @param  int  $durationSeconds  Duración en segundos
     * @param  int  $xpEarned  XP ganado
     * @return string Calidad (poor, fair, good, excellent)
     */
    protected function calculateSessionQuality(int $durationSeconds, int $xpEarned): string
    {
        // Mapear a enum existente: micro (<5min), productive (5-15min), excellent (>15min)
        if ($durationSeconds < 300) {
            return 'micro';
        }
        if ($durationSeconds < 900) {
            return 'productive';
        }

        return 'excellent';
    }

    /**
     * Normaliza etiquetas externas (poor/fair/good/excellent) a los valores del enum.
     */
    private function normalizeQuality(string $quality, int $durationSeconds, int $xpEarned): string
    {
        $q = strtolower($quality);
        // Si ya es un valor permitido, devolverlo
        if (in_array($q, ['micro', 'productive', 'excellent'], true)) {
            return $q;
        }

        // Mapear valores antiguos a nuevos
        return match ($q) {
            'poor' => 'micro',
            'fair' => 'productive',
            'good' => 'productive',
            default => $this->calculateSessionQuality($durationSeconds, $xpEarned),
        };
    }

    /**
     * Registrar inicio de sesión
     *
     * @param  int  $userId  ID del usuario
     * @param  string  $activityType  Tipo de actividad
     */
    protected function startSession(int $userId, string $activityType = 'typing'): ?UserActivityTracker
    {
        try {
            return UserActivityTracker::create([
                'user_id' => $userId,
                'activity_type' => $activityType,
                'session_start' => now(),
                // Dejar que el enum aplique su valor por defecto (productive) al finalizar
            ]);
        } catch (\Exception $e) {
            Log::error('Error iniciando sesión: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Finalizar sesión abierta
     *
     * @param  int  $trackerId  ID del tracker
     * @param  int  $xpEarned  XP ganado
     */
    protected function endSession(int $trackerId, int $xpEarned = 0): bool
    {
        try {
            $tracker = UserActivityTracker::find($trackerId);

            if (! $tracker) {
                return false;
            }

            $durationSeconds = (int) now()->diffInSeconds($tracker->session_start);
            $quality = $this->calculateSessionQuality($durationSeconds, $xpEarned);

            $tracker->update([
                'session_end' => now(),
                'session_duration_seconds' => $durationSeconds,
                'xp_earned' => $xpEarned,
                'activity_quality' => $quality,
                'completed' => true,
            ]);

            // Actualizar progress del usuario
            $user = \App\Models\User::find($tracker->user_id);
            if ($user && $user->gameProgress) {
                $user->gameProgress->incrementWeeklySessions();
                $user->gameProgress->updateChurnRiskLevel();
            }

            return true;

        } catch (\Exception $e) {
            Log::error('Error finalizando sesión: '.$e->getMessage());

            return false;
        }
    }
}
